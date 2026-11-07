# Release notes: 2.16.1

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.16.1
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.16.1** is a release of [2.16.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.16.0).

## Worked examples, and Claude knows them everywhere

+ [Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs#example): clear the finished tasks
  every night. [Notifications](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications#example): tell the
  administrators what is late, every morning. The course has the whole lesson:
  [15 Reminders](https://github.com/caligrafy/caligrafy-quill-course/wiki/15-Reminders).
+ The Claude Code plugin (1.28.1): `/caligrafy-quill:idea` plans and builds jobs and alerts when an idea asks for them;
  `/caligrafy-quill:design` checks the emails in the new style; `/caligrafy-quill:deploy` sets up notifications on your
  own server (gotcha G111).

## Updating a project

```bash
php caligrafer.php update
```

When the update ran in Docker, run `php caligrafer.php plugin` on your computer in the project's folder, then restart
`claude` there.

See the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md) for every change.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
