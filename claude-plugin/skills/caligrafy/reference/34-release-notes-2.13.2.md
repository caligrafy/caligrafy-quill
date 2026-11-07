# Release notes: 2.13.2

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.13.2
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.13.2** is a fix release of [2.13.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.13.0).

## Describe it. Approve it. Ship it.

Caligrafy's new positioning reaches your project: the Claude Code plugin, `CLAUDE.md` and `AGENTS.md` lead with the
three steps. You describe your idea to `/caligrafy-quill:idea`, approve the plan, and Claude ships a complete app;
build once, experience everywhere is what makes it complete.

## The `idea` skill

+ **Review before shipping.** Answer the plan with "OK. Build it and check it here, then stop before putting it
  online": Claude builds and checks the app, tells you what changed since the plan, and waits. Ship it when you are
  ready with `/caligrafy-quill:ship <name>`. Later changes get a plan first too.
+ **Field messages** of the pages it writes show under their own field.
+ Its check signs in after signing up (sign-up does not sign in).

The [course](https://github.com/caligrafy/caligrafy-quill-course/wiki) now teaches these three steps with Claude.

## Fixed

+ `php caligrafer.php plugin` in the framework's own repository kept renaming its plugin source; it now keeps
  `caligrafy`.
+ `php caligrafer.php plugin` took another project's install for this one when their names started alike, and did not
  install the plugin; it now reads Claude Code's install record for this folder.

## Updating a project

```bash
php caligrafer.php update
```

Then, when the update ran in Docker, on your computer in the project's folder:

```bash
php caligrafer.php plugin
```

And restart `claude` in the folder. See the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md) for every change.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
