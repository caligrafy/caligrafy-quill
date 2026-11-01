# Release notes: 2.4.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.4.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.4.0** takes your application the last step: **online**. Every project now has a production image, prepares its own database when it starts, and can be put at `https://<name>.caligrafy.com` in three commands with **Caligrafy Cloud**. Nothing breaks in existing projects. The full list is in the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

## Online in three commands: Caligrafy Cloud

```bash
php caligrafer.php cloud login                 # a token from https://deploy.caligrafy.com
php caligrafer.php cloud create bookshelf      # from this project's GitHub repository
php caligrafer.php cloud deploy bookshelf      # https://bookshelf.caligrafy.com
```

[Caligrafy Cloud](https://deploy.caligrafy.com) builds your project from GitHub (public, or private with a read-only deploy key), gives it its own MySQL database, its keys and an HTTPS address, and keeps your settings encrypted.

+ **The first 30 minutes are free**, to see your application online.
+ Then **credits**: 1 credit = 1 USD, packs of 5, 10, 25 or 50. A week online costs 2 credits, a month 5, a year 50.
+ When the time ends, the app is **paused**, not deleted: its address says so, you get an email, and its data waits 7 days.
+ The dashboard, the `cloud` commands and AI agents (MCP at `https://deploy.caligrafy.com/mcp`) do the same things: Caligrafy Cloud is itself built once with Caligrafy capabilities.

See [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#cloud) and [Caligrafer: cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#cloud).

## A production image in every project

`Dockerfile` (PHP 8.5, Apache, the extensions Caligrafy uses, your `composer.lock` without development packages) and `Dockerfile.dockerignore` (your `.env`, `.git`, tests and backups stay out). It runs on Caligrafy Cloud, on [Coolify](https://coolify.io), or on any server with Docker, with your `.env` given as a file. PHP sessions are kept in `/var/lib/php/sessions`, so a volume there keeps people signed in across deploys. See [your own server](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#ownserver).

## The database, at every start: `deploy`

```bash
php caligrafer.php deploy
```

The image runs it before Apache starts: it waits for the database, creates the framework tables, then runs every `create_db.sql` that is new or changed since the last deploy, and remembers what it ran. A changed script that drops or deletes data is never run again on its own. No more "the table does not exist" on a new server. See [Caligrafer: deploy](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#deploy).

## Also in 2.4.0

| Area | Before | Now |
| --- | --- | --- |
| Page components | a grey outline and a bolder style than the sign-in screen | the look of the Caligrafy sign-in card: a 600px card with a soft shadow, light labels, a 150px button, a white footer |
| Mail | with `APP_DEBUG=true`, the SMTP conversation was printed into the page or the JSON answer | it goes to the server log |
| `cloud extend` | times in the server's zone | times in your zone |

## Upgrading from 2.3.0

1. Update the framework (`git pull`, or copy `framework/`, `caligrafer.php`, `.bin/` and `claude-plugin/` from the release).
2. Copy `Dockerfile`, `Dockerfile.dockerignore` and `public/css/tokens.css` from the release (they are not templates), and add `/.caligrafy/` to your `.gitignore`.
3. `php caligrafer.php upgrade`, then `php caligrafer.php upgrade --apply`.
4. `php caligrafer.php doctor`.

The Claude Code plugin is 1.15.0: it knows `deploy` and `cloud`, and its new `/caligrafy-quill:deploy` skill puts a project online for you.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
