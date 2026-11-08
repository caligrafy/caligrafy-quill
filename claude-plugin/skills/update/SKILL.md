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

Since 2.10.0 `php caligrafer.php upgrade` checks for a newer release too and then does what `update` does
(`upgrade --apply` = `update`); `update` also takes a version. Use either.

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
php caligrafer.php upgrade --local  # should report nothing outdated in application/
php caligrafer.php platform install # the framework tables of the new release (2.18.0: jobs, cache, migrations); safe to run again
```
+ 2.18.0+: `doctor`'s Framework tables line must be OK (else `platform install`). If `tests/.gitignore` holds `*`
  (projects made before 2.18.0; `update` never touches `tests/`), replace it with the line
  `# Your tests: php caligrafer.php test (make <Name> writes one). Never served: the production image leaves tests/ out.`
  so that git keeps the tests. When `tests/` has tests: `php caligrafer.php test`. When `application/migrations` has
  files: `php caligrafer.php migrate status`. Classes now load when used (G118): open the pages; a page that breaks
  because a file relied on another being loaded first gets a direct reference to the class (`CLASS_MAP=false` restores
  the old loader meanwhile).
+ 2.19.0+ (no new tables: `platform install` changes nothing): sessions start only when used, so check the sign-in
  flows (sign in, sign out, sign up, a page that needs someone) and each page with a form from a fresh session (`curl`
  without a cookie, or a private window): the post must not be refused with 3000. Code that reads `$_SESSION['token']`
  on a GET reads `session('token')` instead. `grep -rn "function find(" application/models`: a model with its own
  `find()` keeps it for `$book->find()`, but `Book::find()` fails (G122); `method_exists($m, 'find')` is now false.
  Pug errors show their line only with `APP_DEBUG=true` (set it locally). `.env.php` is in `.gitignore`; production
  needs nothing (the deploy step runs `env cache`); an own server without the image runs `php caligrafer.php env cache`
  after each `.env` change. Escape hatches, to name only when something breaks: `SESSION_LAZY=false`,
  `VIEW_OPTIMIZER=false`. Moving controllers to `Route::form` / `Route::auth` / `Route::webhook` / `Route::action` is optional: offer
  it, never do it unasked (08-controllers.md shows how).
+ 2.21.6: in a client app made earlier, load `app.js` with a version: `APP_SERVICE_ROOT.'app.js?v='.@filemtime(PUBLIC_PATH.'js/services/app.js')`.
+ 2.21.5: `loadVue()` versions a client app's scripts. Nothing to change.
+ 2.21.3: a face sign-in without a liveness check must not change the face or add a passkey: check `AuthRoutes::signedInBy()` (G146), and record the sign-in with `AuthRoutes::signIn($user, null, 'face')`.
+ 2.21.2: on a `GET`, a route variable now wins over the query string in `Route::action` and `Route::capability`.
+ 2.21.1: a JSON call to `Route::action` needs the page's `X-CSRF-Token` or a token granting `*`, signed in or not. Nothing to
  change when pages send `apiHeaders()`.
+ 2.20.0-2.21.0: nothing to change. New: `Route::page` `visitorsOnly`, `comeBack`, `query`; `Route::form` `visitorsOnly`, `same`,
  `accepted`, `inputs`, `signsIn`; `Route::redirect` `keepQuery`; `Route::auth` `?return=`; `Route::action`, `webOnly: true`,
  `Route::bots()`. Existing controllers keep working.
+ 2.19.1+: `upgrade --apply` must replace `application/views/Client/app.pug` (its `env.request` no longer carries the
  session cookie or the `Authorization` header, G136); a customized copy gets `app.pug.caligrafy-new`: merge its
  `'request' => request()->forClient()` line. Check that the root `.htaccess` denies `backups` and `.gitignore` has
  `/.env.*` + `!/.env.example` (G34; `update` adds the new `.gitignore` lines). Vite SPAs made with `caligrafer create`
  before 2.19.1 are the project's code (`update` never touches `public/<name>/`): offer, never do unasked, to move
  them to the 2.19.1 template's session calls (`src/common/app.js` `apiHeaders()`, no `VITE_API_KEY`),
  `createWebHashHistory()`, `base: './'` and a committed `dist/` (G22, G50). A `Route::webhook` capability without
  the `actorType()` guard (G137), `select()` lists without `->arrays()` (G138), `word_count,min,...` rules (G139) and
  validators added in `config.php` (G141) are worth a look with the `caligrafy-reviewer` agent.
+ The release notes of every version passed (`https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-<version>`):
  do what they ask of projects (for example 2.7.0: client apps call their routes with `apiHeaders()`; `doctor` lists
  the scripts that still send the key).
+ The plugin: `doctor`'s "Claude Code plugin" line must be OK. Claude Code runs its own copy of the plugin (G103):
  when the update ran in Docker, or `doctor` says "Claude Code runs caligrafy-quill X, this project has Y", run
  `php caligrafer.php plugin` on this computer (not in Docker). Then tell the user to restart `claude` in the folder: the
  new skills of this session are only there after a restart.
+ Open the main pages (or curl them) and try one capability (`php caligrafer.php call <name> ...`).
+ Commit with the user's OK: "Caligrafy Quill <from> to <to>". The backups are in `backups/` (git-ignored).
+ Online: redeploy (`php caligrafer.php cloud deploy <name>`, or the ship skill).

Report: from and to, what was replaced, what you merged and how, the settings added, anything left for the user.
