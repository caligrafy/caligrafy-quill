# Release notes: 2.9.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.9.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.9.0** updates a project in one command. `php caligrafer.php update` moves a project to the latest release and applies everything that can be applied safely, keeping what you changed. Nothing breaks in existing projects. The full list is in the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

## One command

```bash
php caligrafer.php update --check    # what it would change, nothing written
php caligrafer.php update            # to the latest release (or: update 2.9.0)
```

It downloads your project's release and the new one from GitHub, and compares them with your project:

+ **The framework.** `framework/` and `claude-plugin/` are replaced whole.
+ **The other files of the release** (`caligrafer.php`, `.bin/`, `Dockerfile`, `docker-compose.yaml`, `public/css`, `public/js`...):
  + a file you never changed is replaced, and a missing one is added;
  + a file you changed is kept, with the new version next to it as `<file>.caligrafy-new`;
  + a file the release did not change stays as it is.
+ **Settings.** `composer.json` gets the new packages (then `composer update` for them), `.gitignore` its new lines, and `.env` the new settings with their defaults. Your values never change.
+ **Your application.** `php caligrafer.php upgrade --apply` runs for `application/`, from the new release.

Everything replaced is backed up first in `backups/`. With uncommitted git changes the command stops, so the update is easy to review and undo. With Claude Code, `/caligrafy-quill:update` runs it and merges the `.caligrafy-new` files with you.

## Upgrading from 2.8.0 or earlier

Projects before 2.9.0 do not have the command yet. Get it once, then run it:

```bash
curl -fsSL https://raw.githubusercontent.com/caligrafy/caligrafy-quill/master/framework/core/Updater.php -o framework/core/Updater.php
php -r "require 'framework/core/Updater.php'; exit((new Caligrafy\Updater('.'))->run());"
```

From then on, `php caligrafer.php update` is enough. See [Caligrafer: update](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#update).

The Claude Code plugin is 1.21.0: the `update` skill, gotcha G94.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
