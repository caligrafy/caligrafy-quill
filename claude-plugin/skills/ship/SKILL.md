---
name: ship
description: Put the current Caligrafy Quill project online at https://<name>.caligrafy.com without handing the work to the user - commit, create the GitHub repository and push (SSH first, then HTTPS, then a browser approval), sign in to Caligrafy Cloud with a browser approval (no token to copy), create the app, add its deploy key, deploy and check it. The user only approves in the browser and says yes before credits are spent.
when_to_use: The last step of /caligrafy-quill:idea, or the user asks to ship, publish, push and deploy, put the project online, "create the repo and deploy it", or to redeploy after a change. For a readiness check only, or the user's own server (Coolify, Docker), use the deploy skill.
argument-hint: "[app-name] [--public]"
---

# Ship: from the code on this computer to an application online

Target: `$ARGUMENTS` (the app name, which is also the GitHub repository name and `https://<name>.caligrafy.com`; the
project folder's name when empty; `--public` for a public repository, private by default).

**Do everything yourself.** The user is never asked to run a command, copy a key or a token, or come back and say
"done". What only they can do happens in their browser, opened for them: approving GitHub's code, approving Caligrafy
Cloud's code, signing up and confirming their email the first time, paying for credits. Tell them in one line what the
page will ask, open it, and keep going as soon as it is approved (the commands wait for it themselves). The one
question you ask is before spending credits.

Never ask for, print or write a password, token or key, and never read `.caligrafy/cloud.json`. Never pipe a command
whose result matters through `tail` or `head`: the pipe hides its exit code (G89); run it whole, in the background when
it is long, and read its output file.

Two places run commands:
+ **this computer** for git and GitHub (the SSH keys, the GitHub CLI and the browser are here):
  `bash claude-plugin/skills/ship/scripts/github.sh ...` (the plugin in the project; for a plugin installed from the
  marketplace, the `scripts/github.sh` next to this file)
+ **Caligrafy's commands**: `php caligrafer.php ...` when PHP 8.3+ is on this computer (`php -v`), otherwise in the
  Docker server: `docker compose exec -T dev-box php caligrafer.php ...`. Below, `caligrafer` means whichever works.

## 1. Ready (a few seconds)

```bash
caligrafer version                                     # 2.7.0+ for the browser sign-in (older: upgrade, or the deploy skill)
ls Dockerfile Dockerfile.dockerignore composer.lock    # 2.4.0 projects have them
git ls-files | grep -E '(^|/)\.env($|\.)' | grep -v '\.env\.example$'   # must print nothing
caligrafer doctor --only project                       # a WARN "Pages and the API key" must be fixed first (G87)
```
+ Every `application/models/*/create_db.sql` runs again online: `CREATE TABLE IF NOT EXISTS`, no `DROP`/`DELETE` (G68).
+ A tracked `.env` stops everything: say which file, never its values; it must leave git and its keys be replaced.
+ `git init -b main` when there is no repository; then commit the work (`git add -A`, a clear message).

## 2. GitHub: the repository, pushed

```bash
bash claude-plugin/skills/ship/scripts/github.sh publish <name> [--public]
```
Run it in the background and read its output. It tries, in order, and never stops to ask:
1. **SSH**: the key already on GitHub (`ssh -T git@github.com` says who). Pushing needs nothing more.
2. **HTTPS** with the GitHub CLI's sign-in (`gh auth status`), for this push only (no change to git's settings).
3. **Browser approval**: creating a repository needs GitHub's API, which SSH cannot reach. When the GitHub CLI is not
   signed in, the script installs it if needed (Homebrew), starts `gh auth login --web`, opens
   https://github.com/login/device and prints `APPROVE https://github.com/login/device CODE XXXX-XXXX`. Tell the user:
   "GitHub opened in your browser: enter the code XXXX-XXXX and approve." The script goes on by itself once approved.

The last line is `REPO https://github.com/<owner>/<name> PROTOCOL ssh|https CREATED private|public|existing BRANCH <b>`.
`FAIL ... exists with other history` (exit 3): the name is someone else's project of the user's: take `<name>-app` (or
ask for a name) and run it again. Any other `FAIL`: say what it says and what you will try.

## 3. Caligrafy Cloud: signed in by an approval

```bash
test -f .caligrafy/cloud.json && caligrafer cloud account
```
Signed in when it prints the email and credits. Otherwise, in the background:
```bash
caligrafer cloud login
```
It prints a code and `https://deploy.caligrafy.com/cli?code=XXXX-XXXX`, then waits up to 10 minutes. On this computer
it opens the page itself; from the Docker server it cannot, so open it for the user (`open <url>` on macOS,
`xdg-open <url>` on Linux). Tell them: "Caligrafy Cloud opened in your browser: sign in with your email (or create an
account the first time), check that it shows the code XXXX-XXXX, and approve." The command saves the token and prints
`Signed in as ...`. Denied or expired: start it again once, then say what happened.

Caligrafy accounts (the same for caligrafy.com and Caligrafy Cloud) sign in with their email, once it is confirmed:
- **A new account**: *Create account* asks for the email and the password twice, then emails a link. Signing in waits
  for it: the sign-in page says *Confirm your email first* and offers to send the link again. Tell the user to open the
  email from Caligrafy (and to look in spam), then to sign in on the page that is still open; the command keeps waiting.
- **A forgotten password**: *Forgot your password?* on the sign-in page emails a 6-digit code (10 minutes, 5 tries),
  then asks for the code and the new password twice.
- **Too many tries**: sign-in pauses for 15 minutes after 10 failures; *Forgot your password?* still works.
Never ask the user for their password or the code: they type them in the browser.

## 4. The app, its key, its deploy

```bash
caligrafer cloud apps                                  # is <name> already one of the user's apps?
caligrafer cloud create <name> --repo https://github.com/<owner>/<name> --branch <b>
```
+ A taken name (someone else's): `<name>-<owner>` or ask once for another.
+ **Private repository**: create saves the read-only deploy key in `.caligrafy/<name>-deploy-key.pub` (`cloud key <name>`
  prints it again). Add it yourself; it is what lets Caligrafy Cloud read the code, and nothing else:
  ```bash
  bash claude-plugin/skills/ship/scripts/github.sh deploy-key <owner>/<name> .caligrafy/<name>-deploy-key.pub
  ```
  It signs the GitHub CLI in first if needed (the same browser approval as step 2). `KEY added` or `KEY present`.
+ **Settings the app needs** (`OPEN_AI_KEY`, `PAY_*`, `MAIL_*`...): name them to the user; they set each one with
  `caligrafer cloud secret set <name> KEY=value` in their terminal (you never type a real secret), or skip that feature.
+ **Time.** Every new app is free for its first 30 minutes online, from its first deploy (G90). Before deploying an app
  whose free minutes are used (`cloud status <name>` shows no time left, or `deploy` answers *has no time left*), ask
  once: "Keep <name> online for a week (2 credits, you have N)?". On yes: `caligrafer cloud extend <name> week`. Not
  enough credits: `caligrafer cloud credits 5` prints a Stripe page; open it for the user and wait until
  `cloud account` shows the credits.

Then deploy, in the background (several minutes; it waits and exits 1 on a refusal or a failed build):
```bash
caligrafer cloud deploy <name>
```
A failed build prints the end of its log: fix the cause (G69: `composer.lock`; a SQL error in a `create_db.sql`),
commit, `github.sh push`, deploy again.

## 5. Check it online, then report

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://<name>.caligrafy.com/
curl -s -H 'Accept: application/json' https://<name>.caligrafy.com/<a JSON route of the app>
```
Online, the legacy API key is off (`LEGACY_API_KEY=false`): a page that still sends it gets `[ERROR 1004] Access Denied:
the legacy API key is turned off` (G87). Pages call their routes with `apiHeaders()` (the session and its CSRF token)
or the generated JS client; programs and agents with personal access tokens. Fix, push, deploy again.

Report, with the real addresses: the site, the repository, the time left (`cloud status <name>`), and what the user
approved in the browser. After a change later: commit, `github.sh push`, `caligrafer cloud deploy <name>`.
