**Updating** moves a project to a newer release of Caligrafy Quill in one command, `php caligrafer.php update`: the
framework, the release's other files, the settings and packages it adds, then the untouched files of `application/`
(`upgrade --apply`). Your code is never overwritten: a file you changed is kept, with the release's version next to it to
merge. This page is the how-to, then what each command does. `update` since 2.9.0; `upgrade` looks for a newer
release since 2.10.0.

<br/>

## With Claude

```text
/caligrafy-quill:update
```

The `update` skill runs `update --check`, shows what changes, runs `update`, merges every `.caligrafy-new` file into
yours, runs `platform install`, `doctor` and the tests, reads the release notes of every version passed, and refreshes
Claude Code's copy of the plugin.

**Check** before you say OK:
+ `php caligrafer.php version` is the new version;
+ `find . -name '*.caligrafy-new' -not -path './vendor/*' -not -path './backups/*'` prints nothing;
+ `php caligrafer.php doctor` has no `FAIL`, and sign-in and the forms work from a private window.

<br/>

## By hand

The commands run in the project's Docker server; without Docker, drop `docker compose exec dev-box`.

**1. Start from a clean state.** `git status --short`: commit what is there, so that the update is one change you can
review. With uncommitted changes `update` stops (`--yes` goes on anyway).

**2. See what changes.**

```bash
docker compose exec dev-box php caligrafer.php update --check
```

```text
 Updating Caligrafy Quill 2.24.1 to 2.25.0 (check: nothing is changed)

  replaced    framework/ (will be replaced)
  replaced    claude-plugin/ (will be replaced)
  replaced    caligrafer.php (will be replaced)
  customized  Dockerfile: kept, the new version will be written in Dockerfile.caligrafy-new
  setting     .env: <NEW_SETTING> will be added with its default

 Nothing was changed. php caligrafer.php update applies it.
```

(an example: the files and settings listed are those your two releases differ by). Already on the latest:
` Caligrafy Quill 2.25.0 is the latest: nothing to update. php caligrafer.php upgrade checks application/.`

**3. Update.**

```bash
docker compose exec dev-box php caligrafer.php update            # the latest release
docker compose exec dev-box php caligrafer.php update 2.25.0     # or one release
```

Run it whole, not through `| tail` (G89): it exits with `1` when something did not finish, and its last lines run
`upgrade --apply`. It ends with
` Caligrafy Quill 2.25.0 is in place. ... Check the project with php caligrafer.php doctor; the release notes: https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.25.0`.

**4. Merge every `.caligrafy-new`.**

```bash
find . -name '*.caligrafy-new' -not -path './vendor/*' -not -path './backups/*'
```

For each one, `diff <file> <file>.caligrafy-new`, write your changes plus the release's into `<file>`, then delete the
`.caligrafy-new`.

**5. Check.**

```bash
docker compose exec dev-box php caligrafer.php version
docker compose exec dev-box php caligrafer.php platform install   # the framework tables of the new release
docker compose exec dev-box php caligrafer.php upgrade --local    # nothing outdated left in application/
docker compose exec dev-box php caligrafer.php doctor
docker compose exec dev-box php caligrafer.php test
```

Read the release notes of every version you passed
(`https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-<version>`), and the "From X to Y" notes of
[Security and upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#upgrading).

**6. Commit and deploy.** Commit (`Caligrafy Quill <from> to <to>`), push, deploy. Delete `backups/` once you are done:
git ignores it and the production image leaves it out.

<br/>

## <a name="update"></a>What `update` does

From [`Caligrafy\Updater`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Tooling#updater). It downloads, from
GitHub, the project's own release (`Framework::VERSION`) and the target (the latest by default), and compares your
files with your own release, so it can tell what you changed:

1. **`framework/` and `claude-plugin/`** are the framework's: replaced whole, the files the release dropped included.
   `framework/librairies/app/.env` (written by `initialize`) is kept. Framework files you changed by hand are listed at
   the end; your copies are in the backup.
2. **Every other file of the release** (`caligrafer.php`, `.bin/`, `Dockerfile`, `docker-compose.yaml`, `index.php`,
   `public/css`, `public/js`...):

    | Your file | What happens | Report |
    | --- | --- | --- |
    | missing | added | `added` |
    | the same as your release's | replaced | `replaced` |
    | changed by you | kept; the new version written next to it as `<file>.caligrafy-new` | `customized` |

   Your documents (`README.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `ARCHITECTURE.md`,
   `LICENSE`) are replaced only when untouched, left alone without a `.caligrafy-new` when you changed them, and never
   added. Never touched here: `application/`, `tests/`, `qa/`, `public/uploads/`, `composer.lock`, the CI files, and the
   project's plugin source files.
3. **Merged** instead of compared: `composer.json` gets the new packages and versions you did not change (then
   `composer update` for them; `--no-composer` prints the command), `.gitignore` the new lines, `.env` the new settings
   with their defaults (existing values are never changed).
4. **The plugin.** The project's plugin source keeps its own name, and Claude Code's copy of the plugin is registered,
   installed or refreshed when `claude` is on this computer.
5. **`application/`**: `php caligrafer.php upgrade --apply --local`, from the new release.

Everything it replaces is copied first to `backups/caligrafy-update-<from>-to-<to>-<time>/`. It refuses a version lower
than the project's (` This project runs <from>: going back to <to> is not supported (restore a backup instead)`).

**A project before 2.9.0** has no `update`: get the command once (G94), then run it.

```bash
curl -fsSL https://raw.githubusercontent.com/caligrafy/caligrafy-quill/master/framework/core/Updater.php -o framework/core/Updater.php
php -r "require 'framework/core/Updater.php'; exit((new Caligrafy\Updater('.'))->run());"
```

<br/>

## <a name="upgrade"></a>What `upgrade` does

`application/` was copied from `framework/settings/application` when the project was made, and is yours. `upgrade`
(from [`Caligrafy\Upgrader`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Tooling#upgrader)) brings its
untouched files up to date. Without `--local`, `--force` or `--keep`, it first asks GitHub for a newer release (G96):
when there is one, `upgrade` is `update --check` and `upgrade --apply` is `update`.

```bash
php caligrafer.php upgrade                                      # dry run
php caligrafer.php upgrade --local                              # this release's templates only
php caligrafer.php upgrade --apply                              # replace the files you never changed
php caligrafer.php upgrade --force application/views/Auth/login.pug          # replace one file you changed
php caligrafer.php upgrade --apply --keep application/views/default/index.pug   # keep a template copy that is now yours
```

It tells your files from untouched copies with `application/.caligrafy-manifest.json` (written by `initialize`) and the
history of every template version (`framework/settings/template-history.json`). Each template file is:

| State | Means | With `--apply` |
| --- | --- | --- |
| `missing` | not in `application/` | added |
| `identical` | the same as the template | nothing |
| `outdated` | an untouched copy of an older template | replaced, with a backup in `backups/caligrafy-upgrade-<time>/` |
| `customized` | changed by you | kept; the new version written next to it as `.caligrafy-new` |
| `kept` | named with `--keep` (remembered in the manifest) | kept; the new version next to it |
| `held` | not added, with the reason: the user capabilities when the project manages users its own way (G80), `AuthController` or `AdminController` when its routes do not need them | nothing; `--force` adds one |
| `routes` | `application/config/routing/web.php`, your routes | never replaced |

It also reports, without changing them: client apps in `public/` that differ from their template, settings of
`.env.example` missing from `.env` and obsolete ones, and Bootstrap 3 classes left in views and apps.

<br/>

## Common mistakes

+ **`update | tail`** (G89): the pipe hides a failed `composer update` or `upgrade`. Run it whole.
+ **A `.caligrafy-new` left behind**: the release's fixes in that file are not in yours. Merge each one, then delete it.
+ **A new skill is missing in Claude Code after the update** (G103): Claude Code runs its own copy of the plugin. Run
  `php caligrafer.php plugin` on your computer, then restart `claude` in the folder.
+ **`upgrade` on an old project says nothing changes** (G96): before 2.10.0 it compared `application/` with the
  templates of the release the project already ran. Run `update`.
+ **The new framework tables are missing**: an update that adds tables needs `platform install` (the deploy step runs
  it online).
+ **The user capabilities are `held`** (G80): the project manages users its own way; `--force` adds them when you
  want them.

<br/>

## Related pages

+ [Security and upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading)
+ [Caligrafer: maintenance commands](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer-Maintenance-Commands)
+ [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin)
+ [Changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md)

<br/><br/>
>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
