#!/usr/bin/env bash
# github.sh - puts a project on GitHub without stopping the user: SSH first, then HTTPS, then a browser approval
#
#   github.sh publish <name> [--public]       creates <owner>/<name> if needed (private by default) and pushes HEAD
#   github.sh deploy-key <owner/repo> <file>   adds a read-only deploy key (Caligrafy Cloud reads a private repository)
#   github.sh push                             pushes the current branch again, after a change
#   github.sh login                            signs the GitHub CLI in (browser approval), nothing else
#
# Runs on the computer (not in the Docker container: the keys and the browser are here), from the project folder.
# The last line says what happened, for the skill that runs it:
#   REPO https://github.com/<owner>/<name> PROTOCOL ssh|https CREATED private|public|existing BRANCH <branch>
#   KEY added|present <owner/repo>
#   FAIL <reason>
# While it waits for an approval in the browser it prints: APPROVE <url> CODE <code> (and opens the page itself).
# Never prints a token. Exit 0 on success, 1 on failure, 3 when <owner>/<name> exists with other history.

set -u
export GIT_TERMINAL_PROMPT=0            # git never waits for a password on a terminal nobody watches
SSH_OPTS=(-o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new)
export GIT_SSH_COMMAND="ssh ${SSH_OPTS[*]}"

fail() { echo "FAIL $1"; exit "${2:-1}"; }

# The GitHub login of the SSH key, or nothing: ssh -T answers "Hi <login>! You've successfully authenticated"
# (CALIGRAFY_GITHUB_HTTPS=1 skips SSH)
ssh_login() {
    [ -n "${CALIGRAFY_GITHUB_HTTPS:-}" ] && return 0
    ssh -T "${SSH_OPTS[@]}" git@github.com 2>&1 | sed -n 's/^Hi \([A-Za-z0-9-]*\)!.*/\1/p' | head -1
}

# git over HTTPS with the GitHub CLI's sign-in, for this command only (no change to the git settings)
GIT_HTTPS=()
git_() { git ${GIT_HTTPS[@]+"${GIT_HTTPS[@]}"} "$@"; }   # the +: an empty array is an unbound variable for macOS's bash 3.2 under set -u

# opens a page in the browser of this computer (CALIGRAFY_NO_BROWSER=1: print only)
open_page() {
    [ -n "${CALIGRAFY_NO_BROWSER:-}" ] && return 0
    if command -v open >/dev/null 2>&1; then open "$1" >/dev/null 2>&1
    elif command -v xdg-open >/dev/null 2>&1; then xdg-open "$1" >/dev/null 2>&1; fi
}

gh_ready() { command -v gh >/dev/null 2>&1 && gh auth status --hostname github.com >/dev/null 2>&1; }

# The GitHub CLI, installed when it is missing (Homebrew), then signed in with a browser approval
gh_login() {
    local protocol="${1:-https}"
    gh_ready && return 0
    if ! command -v gh >/dev/null 2>&1; then
        if command -v brew >/dev/null 2>&1; then
            echo "Installing the GitHub CLI (brew install gh)..."
            brew install gh >/dev/null 2>&1 || return 1
        else
            echo "The GitHub CLI is not installed: https://cli.github.com"
            return 1
        fi
    fi
    # --web: a one-time code approved at github.com/login/device; gh opens the page after Enter (the echo)
    local log
    log=$(mktemp)
    local enter="\n"; [ -n "${CALIGRAFY_NO_BROWSER:-}" ] && enter=""
    printf "$enter" | BROWSER=true gh auth login --hostname github.com --git-protocol "$protocol" --web --skip-ssh-key --scopes repo >"$log" 2>&1 &
    local pid=$! code=""
    for _ in $(seq 1 30); do
        code=$(grep -o '[A-Z0-9]\{4\}-[A-Z0-9]\{4\}' "$log" | head -1)
        [ -n "$code" ] && break
        sleep 1
    done
    if [ -n "$code" ]; then
        echo "APPROVE https://github.com/login/device CODE $code"
        open_page "https://github.com/login/device"
    fi
    wait "$pid"
    local status=$?
    rm -f "$log"
    [ $status -eq 0 ] && gh_ready
}

publish() {
    local name="$1" visibility="private"
    [ "${2:-}" = "--public" ] && visibility="public"
    git rev-parse --git-dir >/dev/null 2>&1 || fail "not a git repository: git init, then commit"
    git rev-parse HEAD >/dev/null 2>&1 || fail "nothing committed yet: commit first"
    local branch
    branch=$(git symbolic-ref --short HEAD 2>/dev/null || echo main)

    # 1. Who is this on GitHub: the SSH key first, then the GitHub CLI
    local owner protocol
    owner=$(ssh_login)
    if [ -n "$owner" ]; then
        protocol=ssh
    else
        protocol=https
        gh_login https || fail "no SSH key on GitHub and the GitHub CLI could not sign in"
        owner=$(gh api user --jq .login 2>/dev/null)
        [ -n "$owner" ] || fail "the GitHub CLI is signed in but did not say who you are"
    fi
    local ssh_url="git@github.com:$owner/$name.git" https_url="https://github.com/$owner/$name.git"
    local url; [ "$protocol" = ssh ] && url="$ssh_url" || url="$https_url"
    [ "$protocol" = https ] && GIT_HTTPS=(-c credential.https://github.com.helper= -c "credential.https://github.com.helper=!gh auth git-credential")

    # 2. The repository: reuse it when it is empty or ours, create it when it does not exist
    local refs created=existing
    if refs=$(git_ ls-remote "$url" 2>/dev/null); then
        if [ -n "$refs" ]; then
            local remote_head
            remote_head=$(echo "$refs" | awk -v b="refs/heads/$branch" '$2 == b {print $1}')
            if [ -n "$remote_head" ] && ! git merge-base --is-ancestor "$remote_head" HEAD 2>/dev/null; then
                fail "$owner/$name already exists with other history: choose another name" 3
            fi
        fi
    else
        # creating a repository needs GitHub's API: the CLI (SSH can push, not create)
        gh_login "$protocol" || fail "creating $owner/$name needs the GitHub CLI signed in (the browser approval did not finish)"
        gh repo create "$owner/$name" "--$visibility" >/dev/null 2>&1 \
            || git_ ls-remote "$url" >/dev/null 2>&1 \
            || fail "GitHub refused to create $owner/$name"
        created="$visibility"
    fi

    # 3. The remote and the push
    if git remote get-url origin >/dev/null 2>&1; then
        git remote set-url origin "$url"
    else
        git remote add origin "$url"
    fi
    git_ push -u origin "$branch" >/dev/null 2>&1 || fail "git push to $owner/$name was refused"
    echo "REPO https://github.com/$owner/$name PROTOCOL $protocol CREATED $created BRANCH $branch"
}

# pushes the current branch again (after a change), with the protocol of origin
push() {
    local url branch
    url=$(git remote get-url origin 2>/dev/null) || fail "no origin: github.sh publish <name> first"
    branch=$(git symbolic-ref --short HEAD 2>/dev/null || echo main)
    case "$url" in https://*) gh_ready && GIT_HTTPS=(-c credential.https://github.com.helper= -c "credential.https://github.com.helper=!gh auth git-credential") ;; esac
    git_ push -u origin "$branch" >/dev/null 2>&1 || fail "git push was refused"
    echo "PUSHED $url $branch"
}

deploy_key() {
    local repo="$1" file="$2"
    [ -f "$file" ] || fail "no key file $file (php caligrafer.php cloud key <name>)"
    gh_login "$( [ -n "$(ssh_login)" ] && echo ssh || echo https )" || fail "adding a deploy key needs the GitHub CLI signed in"
    local key
    key=$(awk '{print $2}' "$file")
    if gh repo deploy-key list -R "$repo" 2>/dev/null | grep -q -- "$key"; then
        echo "KEY present $repo"; return 0
    fi
    gh repo deploy-key add "$file" -R "$repo" --title "Caligrafy Cloud (read-only)" >/dev/null 2>&1 \
        || fail "GitHub refused the deploy key for $repo"
    echo "KEY added $repo"
}

case "${1:-}" in
    publish)    [ -n "${2:-}" ] || fail "usage: github.sh publish <name> [--public]"; publish "$2" "${3:-}" ;;
    deploy-key) [ -n "${3:-}" ] || fail "usage: github.sh deploy-key <owner/repo> <file>"; deploy_key "$2" "$3" ;;
    push)       push ;;
    login)      gh_login "$( [ -n "$(ssh_login)" ] && echo ssh || echo https )" && echo "LOGIN ok" || fail "the GitHub CLI could not sign in" ;;
    *)          fail "usage: github.sh publish <name> [--public] | deploy-key <owner/repo> <file> | login" ;;
esac
