---
name: update
description: Update a Caligrafy Quill project to the latest release (or a given one) and apply every change - php caligrafer.php update replaces the framework, the release files the project never changed, merges composer.json, .gitignore and the new .env settings, runs upgrade --apply; then Claude merges each .caligrafy-new file into the project's own version and checks the project.
when_to_use: The user asks to update, upgrade or bring a Caligrafy project to the latest version or to a given version ("upgrade to 2.9.0", "update caligrafy", "get the latest framework"), or php caligrafer.php version is behind the latest release.
argument-hint: "[version]"
---

# Update a Caligrafy project

Target: `$ARGUMENTS` (a version such as `2.9.0`; empty means the latest release). Read
[18-caligrafer.md](../caligrafy/reference/18-caligrafer.md) (`update`, `upgrade`) and G94 in
[gotchas.md](../caligrafy/reference/gotchas.md). Run `php caligrafer.php` on this computer when PHP 8.3+ is here, else
in the Docker server (`docker compose exec -T dev-box php caligrafer.php ...`).

## 1. Before

```bash
php caligrafer.php version
git status --short
```
+ Uncommitted changes: commit them first with the user's OK (a clear message), so the update is one reviewable change.
  Never pass `--yes` to skip that check without the user's OK.
+ **Before 2.9.0** the project has no `update` command. Get it once (G94):
  ```bash
  curl -fsSL https://raw.githubusercontent.com/caligrafy/caligrafy-quill/master/framework/core/Updater.php -o framework/core/Updater.php
  ```
  and in the steps below replace `php caligrafer.php update [--check]` with
  `php -r "require 'framework/core/Updater.php'; exit((new Caligrafy\Updater('.'))->run(<'version' or null>, <true for --check>));"`.

## 2. See, then apply

```bash
php caligrafer.php update --check [version]
php caligrafer.php update [version]
```
Show the user the check in a few lines (what is replaced, added, kept, the `.caligrafy-new` files, the new settings and
packages), then apply it: the user asked for the update. Run it whole, never through `| tail` (G89): it exits 1 when
something did not finish, and its last lines run `upgrade --apply` for `application/`. `composer update` runs for
new or changed packages; in Docker without Composer in the container, run it as the output says.

## 3. Merge every `.caligrafy-new`

```bash
find . -name '*.caligrafy-new' -not -path './vendor/*' -not -path './backups/*'
```
For each one: `diff <file> <file>.caligrafy-new`. Keep the project's own changes and take the release's: a new block, a
fixed line, a new setting. Write the merged result into `<file>` and delete the `.caligrafy-new`. When the two
cannot be combined (the project rewrote the same lines), keep the project's version, apply the release's fix by hand
if it is a fix, and tell the user what you chose. Views and controllers under `application/` follow the same rule
(written by `upgrade`). Never edit `framework/`: it is the release's.

## 4. Check

```bash
php caligrafer.php version
php caligrafer.php doctor
php caligrafer.php upgrade          # should report nothing outdated
```
+ The release notes of every version passed (`https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-<version>`):
  do what they ask of projects (for example 2.7.0: client apps call their routes with `apiHeaders()`; `doctor` lists
  the scripts that still send the key).
+ Open the main pages (or curl them) and try one capability (`php caligrafer.php call <name> ...`).
+ Commit with the user's OK: "Caligrafy Quill <from> to <to>". The backups are in `backups/` (git-ignored).
+ Online: redeploy (`php caligrafer.php cloud deploy <name>`, or the ship skill).

Report: from and to, what was replaced, what you merged and how, the settings added, anything left for the user.
