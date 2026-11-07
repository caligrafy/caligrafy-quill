# Release notes: 2.14.1

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.14.1
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.14.1** is a release of [2.14.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.14.0).

## Scheduled jobs on Caligrafy Cloud

Caligrafy Cloud now runs the [scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs) of
your apps: deploy, and they run on their rhythm while the app is online, with no page open.

```bash
php caligrafer.php cloud jobs myapp                         # each job, its last run and its next one
php caligrafer.php cloud jobs myapp run check-prices        # run one now
php caligrafer.php cloud jobs myapp history check-prices    # its runs, with their output
```

The app's page on Caligrafy Cloud has a **Jobs** section too. 20 jobs per app, included in the app's time.

The Claude Code plugin (1.26.1) knows it: after shipping an app with jobs, Claude checks they run.

## Updating a project

```bash
php caligrafer.php update
```

Then deploy again (`php caligrafer.php cloud deploy <name>`) for Cloud to read the jobs. When the update ran in Docker,
run `php caligrafer.php plugin` on your computer in the project's folder, then restart `claude` there.

See the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md) for every change.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
