# Release notes: 2.13.1

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.13.1
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

![Caligrafy: Build Once, Experience Everywhere](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/build-once-banner.png)

Caligrafy Quill **2.13.1** is a fix release of [2.13.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.13.0).

## The new skills reach Claude Code after an update

Claude Code does not run the plugin in your project's `claude-plugin/` folder: it runs a copy it made when the plugin
was installed. `php caligrafer.php update` replaced the folder but not that copy, so after updating to 2.13.0 a project
kept its old plugin, without `/caligrafy-quill:marketplace`.

Now `php caligrafer.php update` and `php caligrafer.php plugin` refresh Claude Code's copy when the project's plugin is
newer, and `php caligrafer.php doctor` warns while it is older ("Claude Code runs caligrafy-quill 1.24.4, this project
has 1.25.1"). In Docker, where `claude` is not installed, the update says to run `php caligrafer.php plugin` on your
computer in the project's folder.

## Updating a project

```bash
php caligrafer.php update
```

Then, when the update ran in Docker, on your computer in the project's folder:

```bash
php caligrafer.php plugin
```

And restart `claude` in the folder.

The full list of changes is in the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).
