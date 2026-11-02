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
| Free time | every new app is free for its first 30 minutes online, from its first deploy |
| Credits | 1 credit = 1 USD, in packs of 5, 10, 25 or 50, paid with Stripe |
| Time online | a week: 2 credits; a month: 5; a year: 50. Time is added to what is left |
| When the time ends | the app is paused (its address shows a paused page) and you get an email. Its data is kept 7 days, then deleted |
| Limits | 5 apps per account, 256 MB of memory and a quarter of a CPU per app |

**1. An account.** Create it at [deploy.caligrafy.com](https://deploy.caligrafy.com/signup): your email is your username, and the same account signs in to caligrafy.com. Open the link of the confirmation email: signing in waits for it (the sign-in page sends it again). A forgotten password: *Forgot your password?* on the sign-in page emails you a 6-digit code.

**2. Your code on GitHub.** Commit and push the project, with its `Dockerfile` (`git push`). A public repository needs nothing more. A private one gets a read-only **deploy key**: the app page shows it, and `cloud create` saves it in `.caligrafy/<name>-deploy-key.pub` (2.7.0+; `cloud key <name>` prints it again). Add it with the GitHub CLI, `gh repo deploy-key add .caligrafy/myapp-deploy-key.pub -R <owner>/<repo> --title "Caligrafy Cloud"`, or in GitHub, *Settings, Deploy keys, Add deploy key*, with *Allow write access* off.

**3. Online.** From the dashboard (*New app*, then *Deploy* on the app page, which shows the build as it goes), or from the command line:

```bash
php caligrafer.php cloud login                       # approve the code it shows in the browser page it opens
php caligrafer.php cloud create myapp                # the repository of this project (git origin), branch main
php caligrafer.php cloud deploy myapp                # builds and starts it, and follows the build
```

`login` (2.7.0+) prints a code and opens `https://deploy.caligrafy.com/cli?code=...`: sign in there (or sign up first), check that the page shows the same code, and approve. The command then has its token, kept in `.caligrafy/cloud.json` (ignored by git). Run from the Docker server, it cannot open your browser: open the address it prints. `--token cq_...` takes a token created on the dashboard (*Command line and AI agents*), `--paste` asks for one without showing it; `CALIGRAFY_CLOUD_TOKEN` overrides the file, for scripts. The dashboard lists your tokens and revokes them. Every `cloud` command exits with 1 when Caligrafy Cloud refuses: a script that pipes it (`| tail`) loses that.

**Pages online.** Caligrafy Cloud turns the shared legacy API key off (`LEGACY_API_KEY=false`). Pages call their own routes with the session and its CSRF token (`apiHeaders()` of a client app, the generated JS client), never with the key: a page that still sends it gets `[ERROR 1004] Access Denied: the legacy API key is turned off` online, and `doctor` warns about it. Programs and AI agents use [personal access tokens](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP).

**4. Keep it online, change it, watch it.**

```bash
php caligrafer.php cloud status myapp                # running, paused..., and the time left
php caligrafer.php cloud logs myapp                  # the build while it deploys, then what the app prints
php caligrafer.php cloud secret set myapp OPEN_AI_KEY=sk-...    # a setting of its .env, applied at the next deploy
php caligrafer.php cloud deploy myapp                # after a git push, or a new setting
php caligrafer.php cloud credits 10                  # opens the Stripe payment page
php caligrafer.php cloud extend myapp month          # 5 credits; a paused app starts again
php caligrafer.php cloud delete myapp --yes          # the app, its database and its files, for good
```

The database settings and the keys of the app are written for you, and your settings cannot replace them; `APP_ENV`, `APP_DEBUG` and the rest of `.env` are yours to set (`APP_ENV=production` and `APP_DEBUG=false` by default). Settings are stored encrypted and never shown again: `cloud secret list` gives their names. Every command is listed in [Caligrafer: cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#cloud).

**AI agents.** Caligrafy Cloud is itself a Caligrafy application: the dashboard, the `cloud` commands and AI agents call the same capabilities. An agent connects to `https://deploy.caligrafy.com/mcp` with the same token (see [MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#mcp)) and can list, deploy and extend your apps. With the [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin), `/caligrafy-quill:ship <name>` does all of this for you: it commits, creates the GitHub repository and pushes (with your SSH key, else over HTTPS with the GitHub CLI, else a code to approve in your browser), signs in to Caligrafy Cloud with the approval above, adds the deploy key, deploys and checks the app. You only approve in your browser, and say yes before credits are spent. `/caligrafy-quill:deploy` checks that a project is ready, or prepares it for your own server.

## Selling your app on the marketplace

An app online on Caligrafy Cloud can be offered on the [marketplace of caligrafy.com](https://caligrafy.com/marketplace):
Caligrafy sells a prompt that recreates it with Caligrafy and Claude Code, and you earn 25% of every sale.

1. On the app's page in the App console (deploy.caligrafy.com), **Marketplace**: say where we pay you (your country;
   wire details where Stripe does not pay out), then **Offer it to the marketplace**.
2. In Claude Code, in the app's folder, run the marketplace skill. It reads the whole code, runs the app on your
   computer and walks through it to screenshot its key features (5 at most), writes the title, the description, the
   prompt that recreates the app and an estimated price window in one private file, `.caligrafy/marketplace.json`
   (ignored by git), then sends it to Caligrafy and redeploys the app. It takes a while, often 15 to 30 minutes.
   ```
   /caligrafy-quill:marketplace myapp
   ```
   Without Claude Code, write the file yourself and send it: `php caligrafer.php cloud submit myapp`.
3. Caligrafy reviews the listing, sets the final price and publishes it, or turns it down with a reason (offer it
   again after the changes). Published apps on caligrafy.com shows its sales and your earnings: get paid from 50 USD,
   once a month, 14 days after each sale.

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
| `deploy` answers *has no time left (its free 30 minutes are used)* | `cloud extend <name> week` (2 credits), then deploy again; `cloud credits 5` to buy credits |
| The page works locally, its calls get `[ERROR 1004] Access Denied` online | the page sends the legacy API key, off on Caligrafy Cloud: call the routes with `apiHeaders()` (2.7.0+) or the JS client |
| `cloud login` waits and nothing opens | it runs in the Docker server: open the address it printed, approve the code |
| Everyone is signed out after a deploy | no volume at `/var/lib/php/sessions` |
| Uploaded files disappear after a deploy | no volume at `/var/www/html/public/uploads` |

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
