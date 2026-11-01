# Putting it online: production image, deploy, Caligrafy Cloud

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Where this fits.** You built your capabilities, pages and apps on your computer. This page puts the same project online: one production image, one command that prepares the database, and two ways to run it, [Caligrafy Cloud](#cloud) (a few commands, a free first half hour) or [your own server](#ownserver). Since Caligrafy Quill 2.4.0.

## The production image: `Dockerfile`

Every project has a `Dockerfile` at its root. Hosts that build from a Dockerfile use it as it is:

+ PHP 8.5 and Apache, with the extensions Caligrafy uses (`bcmath`, `exif`, `gd`, `intl`, `mysqli`, `pdo_mysql`, `zip`) and the production `php.ini`;
+ the libraries of your `composer.lock`, without the development packages;
+ your code, without what must not be online: `Dockerfile.dockerignore` leaves out `.env` and `.env.*` (but `.env.example`), `.git`, `vendor`, `node_modules`, `qa`, `tests`, `backups`, the Claude plugin, `public/uploads/*` and `.caligrafy`;
+ PHP sessions in `/var/lib/php/sessions`: put a volume there and a new deploy does not sign everyone out.

**Settings are not in the image.** Give the running container its `.env` as a file at `/var/www/html/.env`. Caligrafy reads that file only (not plain environment variables), so the host must let you mount a file. In production the file has:

+ `APP_ROOT=` (empty: the site is at the root of its domain), `APP_ENV=production`, `APP_DEBUG=false`;
+ its own `APP_KEY` and `API_KEY` (`php caligrafer.php generatekeys` prints a new pair);
+ `DB_ACTIVATE=true` and the `DB_*` settings of the production database;
+ the keys of the services you use (`OPEN_AI_KEY`, `PAY_*`, `MAIL_*`...).

`docker/Dockerfile` is a different file: the development image of `docker-compose.yaml`.

## The database, at every start: `php caligrafer.php deploy`

The image runs `php caligrafer.php deploy` before Apache starts. It:

1. waits for the database (60 seconds by default: a new server starts its database at the same time as the app; `CALIGRAFY_DEPLOY_WAIT` changes it);
2. creates the framework tables (personal access tokens, the audit log), safe to run again;
3. runs every `application/models/<folder>/create_db.sql` that is **new or changed** since its last run, in folder order, and records it in the `caligrafy_scripts` table.

A changed script that drops, deletes or truncates (`DROP TABLE`, `DELETE FROM`, `TRUNCATE`...) is never run again on its own: it says so in the log, and you run it by hand when you mean it (`php caligrafer.php sql <file>`). `seed.sql` and other scripts are never run. Without `DB_ACTIVATE=true` there is nothing to do.

So write your tables with `CREATE TABLE IF NOT EXISTS`, and add a column with a new statement (`ALTER TABLE ... ADD COLUMN ...`) rather than by dropping the table.

```bash
php caligrafer.php deploy                 # what the image runs at start
php caligrafer.php deploy --wait 120      # wait up to 2 minutes for the database
```

If the database cannot be prepared, the log says why and Apache starts anyway, so that your pages can show their own error.

## Caligrafy Cloud: online in a few commands

[Caligrafy Cloud](https://deploy.caligrafy.com) builds your project from its GitHub repository, gives it a MySQL database, its keys and an address: `https://<name>.caligrafy.com`.

| | |
| --- | --- |
| Trial | the first 30 minutes of your account are free |
| Credits | 1 credit = 1 USD, in packs of 5, 10, 25 or 50, paid with Stripe |
| Time online | a week: 2 credits; a month: 5; a year: 50. Time is added to what is left |
| When the time ends | the app is paused (its address shows a paused page) and you get an email. Its data is kept 7 days, then deleted |
| Limits | 5 apps per account, 256 MB of memory and a quarter of a CPU per app |

**1. An account.** Sign up at [deploy.caligrafy.com](https://deploy.caligrafy.com/signup) and open the link of the confirmation email (the sign-up page can send it again).

**2. Your code on GitHub.** Commit and push the project, with its `Dockerfile` (`git push`). A public repository needs nothing more. A private one gets a read-only **deploy key**: the app page shows it; add it in GitHub, *Settings, Deploy keys, Add deploy key*, with *Allow write access* off.

**3. Online.** From the dashboard (*New app*, then *Deploy* on the app page, which shows the build as it goes), or from the command line:

```bash
php caligrafer.php cloud login                       # paste a token from the dashboard ("Command line and AI agents")
php caligrafer.php cloud create bookshelf            # the repository of this project (git origin), branch main
php caligrafer.php cloud deploy bookshelf            # builds and starts it, and follows the build
```

`login` asks for the token without showing it: run it in a terminal where you can paste. The token is kept in `.caligrafy/cloud.json` (ignored by git); `CALIGRAFY_CLOUD_TOKEN` overrides it, for scripts.

**4. Keep it online, change it, watch it.**

```bash
php caligrafer.php cloud status bookshelf            # running, paused..., and the time left
php caligrafer.php cloud logs bookshelf              # the build while it deploys, then what the app prints
php caligrafer.php cloud secret set bookshelf OPEN_AI_KEY=sk-...    # a setting of its .env, applied at the next deploy
php caligrafer.php cloud deploy bookshelf            # after a git push, or a new setting
php caligrafer.php cloud credits 10                  # opens the Stripe payment page
php caligrafer.php cloud extend bookshelf month      # 5 credits; a paused app starts again
php caligrafer.php cloud delete bookshelf --yes      # the app, its database and its files, for good
```

The database settings and the keys of the app are written for you, and your settings cannot replace them; `APP_ENV`, `APP_DEBUG` and the rest of `.env` are yours to set (`APP_ENV=production` and `APP_DEBUG=false` by default). Settings are stored encrypted and never shown again: `cloud secret list` gives their names. Every command is listed in [Caligrafer: cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#cloud).

**AI agents.** Caligrafy Cloud is itself a Caligrafy application: the dashboard, the `cloud` commands and AI agents call the same capabilities. An agent connects to `https://deploy.caligrafy.com/mcp` with the same token (see [MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#mcp)) and can list, deploy and extend your apps. With the [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin), `/caligrafy-quill:deploy` checks that the project is ready and puts it online for you.

## Your own server

The same image runs on any host that builds a Dockerfile and lets you give it a file and a database:

+ **[Coolify](https://coolify.io)** (open source, on your own server): a *Dockerfile* application from your repository, a MySQL database, the `.env` as a *file storage* mounted at `/var/www/html/.env`, and volumes at `/var/www/html/public/uploads` and `/var/lib/php/sessions`.
+ **Any server with Docker:**

```bash
docker build -t my-app .
docker run -d -p 80:80 \
  -v "$(pwd)/.env.production:/var/www/html/.env:ro" \
  -v my-app-uploads:/var/www/html/public/uploads \
  -v my-app-sessions:/var/lib/php/sessions \
  my-app
```

Put HTTPS in front of it (a load balancer, Coolify's proxy, Caddy...). Hosts that offer only environment variables and no file (Railway, Render, DigitalOcean App Platform) are not supported yet.

## When something does not work

| You see | Why, and what to do |
| --- | --- |
| The build fails | `cloud logs <name>` (or the app page) shows the build: usually `composer.lock` missing or out of date (`composer update`, commit the lock file) |
| `caligrafer deploy: the database is not prepared` | the line above it says why: database not reachable (check the `DB_*` settings, or `--wait` longer), or a SQL error in a `create_db.sql` |
| `Host ... is not allowed to connect` | the database was created but its user was not: on your own server, recreate the database (it has no data yet) |
| Caligrafy Cloud cannot read a private repository | the deploy key is not added on GitHub, or *Allow write access* was expected: add the key of the app page |
| The address shows a paused page | its time ended (`cloud extend <name> week`), or it is deploying, or there is no app with that name |
| Everyone is signed out after a deploy | no volume at `/var/lib/php/sessions` |
| Uploaded files disappear after a deploy | no volume at `/var/www/html/public/uploads` |

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
