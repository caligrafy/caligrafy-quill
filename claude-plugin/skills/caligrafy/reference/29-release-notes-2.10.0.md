# Release notes: 2.10.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.10.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.10.0** makes `upgrade` look for the latest release. Nothing breaks in existing projects. The full list is in the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

## `upgrade` checks for a newer release

```bash
php caligrafer.php upgrade            # a newer release? what moving to it changes (nothing is written)
php caligrafer.php upgrade --apply    # moves the project to it
```

`upgrade` used to compare your `application/` folder with the templates of the release your project already runs, so it never told you a newer release was out. Now it asks GitHub first:

+ **A newer release is out:** `upgrade` shows what moving to it changes, and `upgrade --apply` applies it exactly like [`update`](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#update): the framework, the release's other files you never changed, `composer.json`, `.gitignore`, the new `.env` settings, then the `application/` templates. Files you changed are kept, with the new version next to them as `<file>.caligrafy-new`.
+ **Your project runs the latest release:** `upgrade` compares `application/` with its templates, as before.
+ **Offline:** it says it could not check, and compares the templates.

`upgrade --local` skips the check. `update` still exists, and also takes a version: `update 2.10.0`.

## Upgrading from 2.9

```bash
php caligrafer.php update
```

From 2.10.0 on, `php caligrafer.php upgrade --apply` does it too.

The Claude Code plugin is 1.22.0: the `update` skill knows that `upgrade` finds newer releases (gotcha G96).

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
