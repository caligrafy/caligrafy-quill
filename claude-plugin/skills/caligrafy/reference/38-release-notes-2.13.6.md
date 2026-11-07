# Release notes: 2.13.6

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.13.6
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.13.6** is a fix release of [2.13.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.13.0).

## The QA suite finds free ports

`qa/run.sh` used ports 8090 and 3316, and failed to start when another project's server held them. It now takes the
first free ports from 8090 and 3316, unless you set `QA_WEB_PORT` and `QA_DB_PORT`. See
[Testing Caligrafy](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy).

The Claude Code plugin (1.25.6) says the same in its references.

## Updating a project

```bash
php caligrafer.php update
```

When the update ran in Docker, run `php caligrafer.php plugin` on your computer in the project's folder. Then restart
`claude` there.

See the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md) for every change.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
