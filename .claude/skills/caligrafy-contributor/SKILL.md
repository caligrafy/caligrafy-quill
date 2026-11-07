---
name: caligrafy-contributor
description: For contributors to the Caligrafy Quill framework repository only (github.com/caligrafy/caligrafy-quill). After any change to the framework itself (framework/, public/, caligrafer.php, .bin/, the templates, qa/, claude-plugin/), carry it everywhere it has to go - the Claude Code plugin, the README, the wiki, the version, changelog, QA and release - then ask whether to review the course and whether to update and deploy caligrafy.com and Caligrafy Cloud. Never for an application built with Caligrafy.
when_to_use: In the caligrafy-quill repository, whenever a change to the framework is done or about to be committed, released or pushed ("fix X in the framework", "add Y to Caligrafy", "release", "push it all"), and before saying the work is finished. Not in an application made from Caligrafy (a folder with application/ and its own git history).
---

# Caligrafy Quill: carry every framework change everywhere

A change to Caligrafy is finished only when the plugin, the README and the wiki say the same as the code, it is
released, and the maintainer has been asked about the course and the sites. Do this without being asked each time.

## 0. Only in the framework repository

Check first, and stop silently if any check fails (this is someone's application, not the framework):
```bash
git remote get-url origin 2>/dev/null | grep -q 'caligrafy/caligrafy-quill\(\.git\)\?$' \
  && [ ! -d application ] && [ -f claude-plugin/scripts/sync-wiki.py ] && [ -f qa/run.sh ] && echo framework
```
An application made with `initialize` never has this skill (`initialize` deletes `.claude/skills/caligrafy-contributor`,
releases leave it out, `update` never adds it).

The wiki clone is `../caligrafy-quill.wiki` (`git clone https://github.com/caligrafy/caligrafy-quill.wiki.git` next to
this folder if it is missing; `git -C ../caligrafy-quill.wiki pull` before editing). The release steps below follow the
wiki page [Testing Caligrafy, release process](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy#release).

## 1. The Claude Code plugin (`claude-plugin/`)

What projects' Claude knows comes from here, so it must describe the change:
- `skills/caligrafy/reference/api-reference.md` and `gotchas.md` are written by hand from the source: a new or changed
  method, command or behavior goes in the API reference; a pitfall that was found (a bug fixed, a surprising behavior)
  becomes the next `G<n>` gotcha, and the range in `skills/caligrafy/SKILL.md` ("G1-G<n>") follows.
- `skills/caligrafy/reference/ui-guidelines.md` when a page, a class or the look changes (the QA suite checks that every
  class it names exists in `tokens.css`, `layout.css`, `look.css` or `camera.css`).
- the other skills (`idea`, `new-app`, `new-capability`, `new-resource`, `design`, `ship`, `deploy`, `update`...) when the
  change alters what they generate or tell the user.
- the wiki mirrors: after step 3, `python3 claude-plugin/scripts/sync-wiki.py ../caligrafy-quill.wiki`; a new wiki
  page gets a line in `PAGES` of `sync-wiki.py` and a row in the reference map of `skills/caligrafy/SKILL.md`.
- the version: `claude-plugin/.claude-plugin/plugin.json` (minor with a framework minor, patch otherwise) and a line at
  the top of the changelog in `claude-plugin/README.md`. `claude plugin validate ./claude-plugin --strict`.
- **Claude Code's copy, every time the plugin changes**: Claude Code runs a copy of the plugin made at install time,
  not `claude-plugin/`. Run `php caligrafer.php plugin` in this repository right after changing the plugin (it
  refreshes the copy and keeps the source name `caligrafy`); `doctor` must say `the framework's own repository
  <plugin version>`, and the tooling tests fail while the copy is older. Never call a release done while any folder's
  copy is behind (step 6).

## 2. The README (GitHub's front page)

Read `README.md` against the change: the feature list, commands, versions, anything that is no longer true. Keep the
banner at the top (`public/images/resources/describe-approve-ship-banner.png`, black, no subline: "Describe it. Approve it. Ship it." leads every front page, build once is the proof of a complete app). Also `claude-plugin/README.md`. Change only
what the change makes wrong or incomplete; no rewrite for its own sake.

## 3. The wiki (`../caligrafy-quill.wiki`)

- Every page the change affects (`grep -l` for the class, command, method or file names you changed); fix examples too
  (Pug in Caligrafy is PHP: no `+` between strings).
- The banner image is only on `README.md` and the wiki's `Home.md`: never on another wiki page (new pages, release
  notes) or in the plugin's copies.
- A minor or major release: a page `Release-notes:-X.Y.0.md` (what changed, how to update), linked in
  `_Sidebar.md` ("Release notes:" line). A patch release: a page `Release-notes:-X.Y.Z.md` too, short (it is a fix
  release of X.Y.0, then its CHANGELOG section): `php caligrafer.php update` prints a link to that exact page.
- `10.-Claude-Code-Plugin.md`: the "Current version: **<plugin>**, for Caligrafy Quill v<version>." line.
- Then step 1's `sync-wiki.py`.

## 4. Version, changelog, QA, release

1. `framework/core/Framework.php` `VERSION` and a section at the top of `CHANGELOG.md` (semantic versioning: major when
   projects can break, minor for features, patch for fixes).
2. A template of `framework/settings/application` changed: `php qa/build-template-history.php`.
3. `QA_WIKI=../caligrafy-quill.wiki qa/run.sh` (run it in the background: it takes minutes). Before the tag exists the
   only expected failure is `caligrafer version` (it compares the version with the tag on the commit); after
   committing and tagging, `bash qa/tests/tooling_tests.sh` must pass entirely.
4. Commit only the files of this change (`git status`; other sessions may share the folder), with the attribution
   lines of the conversation; tag `git tag -a vX.Y.Z -m "Caligrafy Quill X.Y.Z"`; push `master`, the tag and the wiki.
5. The GitHub release (`gh release create vX.Y.Z --title "Caligrafy Quill X.Y.Z" --notes-file <its CHANGELOG
   section>`, `--latest` for the newest): it is public, so ask the maintainer first. Until it exists, projects running
   `php caligrafer.php update` without a version are not offered it.

Never print or commit `.env` values or tokens. Pushing, publishing and deploying are outward actions: do them when
the maintainer asked for them in this conversation, otherwise ask.

## 5. Ask: review the course?

With AskUserQuestion, ask whether to review the course (do nothing without a yes). When yes:
`../caligrafy-quill-course` (lessons in `wiki/`, projects in `checkpoints/lesson-NN/`) and its published wiki
`../caligrafy-quill-course.wiki`, kept identical:
- the pinned release (`--branch vX.Y.Z`, `caligrafy/caligrafy-quill:X.Y.Z`, the expected `doctor` and `update` lines);
- every checkpoint file and lesson the change touches (markup, commands, outputs, build sizes);
- `python3 tools/check-pages.py` and `python3 tools/check-pages.py ../caligrafy-quill-course.wiki` report 0 problems,
  and `diff -r -x .git wiki ../caligrafy-quill-course.wiki` prints nothing; then commit and push both.

## 5b. A change on the sites that the plugin describes

caligrafy.com and Caligrafy Cloud are documented by the plugin and the wiki (the `ship`, `deploy`, `marketplace` and
`idea` skills, Putting it online, Quickstart with Claude): sign-up and sign-in, emails, deploys, credits, payouts. When
a change on a site alters what they say, it is a framework change too: `grep -rn` the plugin and the wiki for it, update
them, and release (steps 1 to 4) before deploying the sites, so that every project's Claude says what the sites do.

## 6. Ask: update the sites?

With AskUserQuestion, ask whether to update and deploy caligrafy.com and Caligrafy Cloud (deploy.caligrafy.com),
which run on Caligrafy themselves (do nothing without a yes). For each one, in its own clone (caligrafy.com:
`DoryAzar/caligrafy-quill-site`; Cloud: `DoryAzar/caligrafy-cloud`), after the release is tagged:
1. commit or stash its own work first; `php caligrafer.php update X.Y.Z`;
2. both keep customized views: delete the `application/**/*.caligrafy-new` files `upgrade` writes next to them, unless
   the change must reach those views (then merge it by hand);
3. check it locally (the pages the change touches, sign-in, `doctor`: its line `Claude Code plugin` must show the new
   plugin version; if not, `php caligrafer.php plugin` there), then commit and push;
4. deploy through Coolify with the maintainer's own access (its address, token and app ids live outside this
   repository), wait for the deployment to finish, and check the live pages. Coolify is for these two sites only.

Do the same update for the maintainer's other Caligrafy apps in `~/www` (every folder with
`framework/core/Framework.php`), push each one, and deploy them on Caligrafy Cloud with the maintainer's OK: from each
app's folder, `php caligrafer.php cloud deploy <name>` (its `.caligrafy/cloud.json` holds the owner's sign-in), never
Coolify's API. Cloud follows only the deploys it starts: one sent to Coolify directly never shows Deploying on
deploy.caligrafy.com and leaves the app's record on its previous deployment. Wait for `Online:`, then check the app. Before reporting, check
every folder: its `Framework::VERSION` is the release, and Claude Code runs the release's plugin there:
```bash
for d in ~/www/*/; do [ -f "$d/framework/core/Framework.php" ] && (cd "$d" && printf '%s ' "$(basename "$d")" && php caligrafer.php doctor --only project 2>&1 | grep -E 'Quill [0-9]|Claude Code plugin' | tr -s ' ' | tr '\n' ' '; echo); done
```
Any folder behind is not done.

## 7. The report, every time

End every framework update with this report to the maintainer, each answer checked now, never from memory, and say
plainly what is not done:
1. **Claude plugin updated?** Its version (`plugin.json`), `claude plugin validate ./claude-plugin --strict`, the
   changelog line, and whether its skills and references cover every change (site behavior they describe included).
2. **README, docs and wiki updated?** The README, `sync-wiki.py ../caligrafy-quill.wiki --check` (out of date: none),
   the "Current version" line of the Claude Code Plugin page, the release notes page.
3. **Course updated?** Its pinned release, the lessons the change touches, `tools/check-pages.py` (0 problems), the
   course wiki identical, both pushed.
4. **Inventory of the apps**: a table of every Caligrafy folder in `~/www` (the loop of step 6), with its Caligrafy
   version, the Claude plugin version Claude Code runs there, whether it is pushed, and what runs live (the commit of
   its last deployment and the address answering).

Also say what was left (a step skipped, a question unanswered, a check that could not be made). This report is the
closing message of the update, after the deploys too, sent without being asked: never a short status instead of it.
