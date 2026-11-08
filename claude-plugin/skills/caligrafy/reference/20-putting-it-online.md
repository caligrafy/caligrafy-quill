# Putting it online: production image, deploy, Caligrafy Cloud

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

You built your capabilities, pages and apps on your computer. This page puts the same project online: one production
image (the `Dockerfile` of every project), one command that prepares the database at each start
(`php caligrafer.php deploy`), and two ways to run it, [Caligrafy Cloud](#cloud) (`https://<name>.caligrafy.com`, a
free first half hour) or [your own server](#ownserver). Since Caligrafy Quill 2.4.0.

## With Claude

| You want | Ask |
|---|---|
| the app online at `https://<name>.caligrafy.com` | `/caligrafy-quill:ship bookshelf` |
| a readiness check only, or your own server | `/caligrafy-quill:deploy` or `/caligrafy-quill:deploy --own-server` |
| to sell the app on the marketplace of caligrafy.com | `/caligrafy-quill:marketplace bookshelf` |

`/caligrafy-quill:ship` commits, creates the GitHub repository and pushes (your SSH key, else HTTPS with the GitHub
CLI, else a code to approve in your browser), signs in to Caligrafy Cloud with an approval in your browser (no token
to copy), creates the app, adds its deploy key, deploys it and checks it online. You approve in your browser, and say
yes before credits are spent. `/caligrafy-quill:deploy --own-server` prepares the project for Coolify or Docker and
deploys nothing: the server is yours.

**Check**

+ `docker compose exec dev-box php caligrafer.php cloud status bookshelf` says `running`, with the time left.
+ `https://bookshelf.caligrafy.com` opens; sign-in and a form work there.
+ `git ls-files` lists no `.env`, `.env.php` or `.env.production`.

## By hand: Caligrafy Cloud

Run these commands on your computer, in the project's folder, with its Docker server up (without Docker, drop
`docker compose exec dev-box`). Run on the Docker server, `cloud login` cannot open your browser: open the address it
prints.

**1. Check the project.**

```bash
docker compose exec dev-box php caligrafer.php version                        # 2.4.0 or later
ls Dockerfile Dockerfile.dockerignore composer.lock
git ls-files | grep -E '(^|/)\.env($|\.)' | grep -v '\.env\.example$' | grep -v '^framework/settings/'   # must print nothing
docker compose exec dev-box php caligrafer.php doctor --only project          # no FAIL
```

Every `application/models/<folder>/create_db.sql` must be safe to run again (`CREATE TABLE IF NOT EXISTS`, no `DROP`
or `DELETE`). `composer.lock` must be committed. The project's `.gitignore` already leaves out `.env`, `.env.php` and,
since 2.19.1, every other `.env.*` file (`.env.testing`, `.env.production`...) except `.env.example`. A single page
application made with `php caligrafer.php create` is online only once built: run `npm run build` in `public/<name>`
and commit its `dist/` folder (the image does not run `npm`; since 2.19.1 the template no longer ignores `dist/`).

**2. An account.** Create it at [deploy.caligrafy.com](https://deploy.caligrafy.com/signup) (your email is your
username; the same account signs in to caligrafy.com) and open the link of the confirmation email.

**3. Your code on GitHub.** Commit and push the project, with its `Dockerfile`:

```bash
git add -A
git commit -m "Bookshelf"
git push
```

**4. Sign in, create the app, deploy.**

```bash
docker compose exec dev-box php caligrafer.php cloud login                 # approve, in the browser page it opens, the code it shows
docker compose exec dev-box php caligrafer.php cloud create bookshelf      # the repository of this project (git origin), branch main
docker compose exec dev-box php caligrafer.php cloud deploy bookshelf      # builds and starts it, and follows the build
```

A **private** repository needs the app's read-only deploy key: `cloud create` saves it in
`.caligrafy/bookshelf-deploy-key.pub`; add it with
`gh repo deploy-key add .caligrafy/bookshelf-deploy-key.pub -R <owner>/<repo> --title "Caligrafy Cloud"` (or in GitHub,
*Settings, Deploy keys*, *Allow write access* off) before `cloud deploy`.

**5. The settings of your services.** The database settings, the keys, `APP_URL` and `LEGACY_API_KEY=false` are
written for the app. Set the others, then deploy again:

```bash
docker compose exec dev-box php caligrafer.php cloud secret set bookshelf PAY_PRIVATE_KEY_PRODUCTION=sk_live_...
docker compose exec dev-box php caligrafer.php cloud secret set bookshelf STRIPE_WEBHOOK_SECRET=whsec_...
docker compose exec dev-box php caligrafer.php cloud deploy bookshelf
```

**6. Check it.** Open `https://bookshelf.caligrafy.com`, sign in, send a form. Then:

```bash
docker compose exec dev-box php caligrafer.php cloud status bookshelf      # running, the time left
docker compose exec dev-box php caligrafer.php cloud logs bookshelf        # the deploy step's lines, then what the app prints
docker compose exec dev-box php caligrafer.php cloud jobs bookshelf        # the scheduled jobs, their last and next runs
```

The log of a start shows the deploy step:

```text
 Caligrafy deploy
  OK    Settings: .env compiled to .env.php (read without parsing on every request)
  OK    Framework tables: tokens, audit log, job runs, jobs, cache, migrations, scripts
  OK    application/models/Auth/create_db.sql: run
  OK    application/models/Bookshelf/create_db.sql: run
  OK    application/models/Loans/create_db.sql: run
```

**7. Keep it online.** The first 30 minutes are free; then add time:

```bash
docker compose exec dev-box php caligrafer.php cloud credits 10            # opens the Stripe payment page
docker compose exec dev-box php caligrafer.php cloud extend bookshelf month   # 5 credits; a paused app starts again
```

**Check**

+ `cloud status` says `running`.
+ The pages, the sign-in and a form work online.
+ `cloud jobs` lists your scheduled jobs, if the app has some.

## Reference: the production image

Every project has a `Dockerfile` at its root. Hosts that build from a Dockerfile use it as it is:

+ PHP 8.5 and Apache (`mod_rewrite`, `mod_headers`), with the extensions Caligrafy uses (`bcmath`, `exif`, `gd`, `intl`,
  `mysqli`, `pdo_mysql`, `zip`) and the production `php.ini`;
+ the libraries of your `composer.lock`, without the development packages;
+ no Node.js and no `npm`: client apps are served as they are in the repository (a Vite app from its committed `dist/`);
+ your code, without what must not be online: `Dockerfile.dockerignore` leaves out `.env` and `.env.*` (but
  `.env.example`), `.git`, `vendor`, `node_modules`, `qa`, `tests`, `backups`, the Claude plugin, `public/uploads/*` and
  `.caligrafy`;
+ PHP sessions in `/var/lib/php/sessions`: a volume there keeps people signed in across deploys;
+ at start, `php caligrafer.php deploy --wait ${CALIGRAFY_DEPLOY_WAIT:-60}`, then Apache (a database that is not ready
  does not keep the site down).

**Settings are not in the image.** The running container reads its `.env` as a file at `/var/www/html/.env`, not plain
environment variables: the host must let you mount a file. In production it has `APP_ROOT=` (empty), `APP_ENV=production`,
`APP_DEBUG=false`, `APP_PROTOCOL=https`, its own `APP_KEY` and `API_KEY` (`php caligrafer.php generatekeys`),
`DB_ACTIVATE=true` and the production `DB_*`, and the keys of your services. The list and the reasons are in
[Security: the production settings](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading).

`docker/Dockerfile` is another file: the development image of `docker-compose.yaml`.

## Reference: `php caligrafer.php deploy`

What the image runs before Apache starts:

1. prints the scheduled jobs on one line (`caligrafy-schedule {...}`), which Caligrafy Cloud reads;
2. compiles `.env` to `.env.php` (2.19.0): pages then read the settings without parsing `.env` (`env cache`); a `.env`
   changed later is read as it is until the next start;
3. waits for the database (60 seconds by default, `--wait <seconds>` or `CALIGRAFY_DEPLOY_WAIT`);
4. creates the framework tables (tokens, the audit log, job runs, notifications, jobs, the cache, migrations), safe to
   run again;
5. runs every `application/models/<folder>/create_db.sql` that is **new or changed** since its last run, in folder
   order, and records it in `caligrafy_scripts`;
6. runs the [migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations) of `application/migrations`
   that never ran on this database, in order (2.18.0).

+ A changed script that drops, deletes or truncates (`DROP TABLE`, `DELETE FROM`, `TRUNCATE`...) is never run again on
  its own: the log says so, and you run it by hand when you mean it (`php caligrafer.php sql <file>`). `seed.sql` and
  other scripts are never run. Without `DB_ACTIVATE=true` there is nothing to do for the database.
+ A statement that adds a column, an index or a foreign key already there is skipped, so a `create_db.sql` keeps its
  `ALTER TABLE ... ADD` lines. Change a table that holds data with a migration.
+ It exits with `1` when the database could not be prepared, after saying why.

```bash
php caligrafer.php deploy                 # what the image runs at start
php caligrafer.php deploy --wait 120      # wait up to 2 minutes for the database
```

## Reference: Caligrafy Cloud

[Caligrafy Cloud](https://deploy.caligrafy.com) builds your project from its GitHub repository and gives it a MySQL
database, its keys and an address: `https://<name>.caligrafy.com`.

| | |
| --- | --- |
| Free time | every new app is free for its first 30 minutes online, from its first deploy |
| Credits | 1 credit = 1 USD, in packs of 5, 10, 25 or 50, paid with Stripe |
| Time online | a week: 2 credits; a month: 5; a year: 50. Time is added to what is left |
| When the time ends | the app is paused (its address shows a paused page) and you get an email. Its data is kept 7 days, then deleted |
| Limits | 5 apps per account, 256 MB of memory and a quarter of a CPU per app |

| Command | What it does |
|---|---|
| `cloud login [--no-browser]` | prints a code and opens `https://deploy.caligrafy.com/cli?code=...`: approve it there. `--token cq_...` or `--paste` take a token of the dashboard. The token is kept in `.caligrafy/cloud.json` (ignored by git); `CALIGRAFY_CLOUD_TOKEN` overrides it |
| `cloud account` | your email and credits |
| `cloud create <name> [--repo <url>] [--branch <b>]` | the app, from this project's GitHub repository |
| `cloud key <name>` | the read-only deploy key of a private repository |
| `cloud deploy <name> [--no-wait]` | builds and starts it |
| `cloud apps`, `cloud status <name>`, `cloud logs <name> [--jobs]` | your apps, one app's state and time left, its logs |
| `cloud jobs <name> [run <job> \| history <job>]` | its scheduled jobs, one run now, a job's runs |
| `cloud secret set <name> KEY=value`, `secret list <name>`, `secret delete <name> KEY` | a setting of its `.env`, applied at the next deploy; stored encrypted, never shown again |
| `cloud extend <name> week\|month\|year` | 2, 5 or 50 credits (an account with no limit spends none and its apps have no end date) |
| `cloud credits 5\|10\|25\|50` | buy credits on Stripe |
| `cloud submit <name> [--no-deploy]` | sends the marketplace listing, then deploys |
| `cloud delete <name> --yes` | the app, its database and files, for good |
| `cloud logout` | |

+ Every `cloud` command exits with `1` when Caligrafy Cloud refuses; a pipe (`| tail`) hides it.
+ The database settings and the keys of the app are written for you, and your settings cannot replace them;
  `APP_ENV=production` and `APP_DEBUG=false` are the defaults.
+ **Pages online.** Caligrafy Cloud turns the shared legacy API key off (`LEGACY_API_KEY=false`). Pages call their
  routes with the session and its CSRF token (`apiHeaders()`, the generated JS client); a page that still sends the key
  gets `[ERROR 1004] Access Denied: the legacy API key is turned off`. Programs and agents use
  [personal access tokens](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP).
+ **A password until it is on the marketplace.** The app's page has a *Password* section that locks the app with a
  password you share (off by default). Everyone types it once per browser; it lasts 30 days. While locked, pages send
  visitors to `https://deploy.caligrafy.com/unlock/<name>`, API and agent calls get `401`, and `cloud apps` and
  `cloud status` print `[password protected]`. Locking or unlocking restarts the app (2 to 5 minutes). Offering the app
  to the marketplace removes the password for good.
+ **AI agents.** Caligrafy Cloud is itself a Caligrafy application: an agent connects to
  `https://deploy.caligrafy.com/mcp` with a token of the dashboard (*Command line and AI agents*) and can list, deploy
  and extend your apps.
+ Every command is also in [Caligrafer: cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#cloud).

## Selling your app on the marketplace

An app online on Caligrafy Cloud can be offered on the [marketplace of caligrafy.com](https://caligrafy.com/marketplace):
Caligrafy sells a prompt that recreates it with Caligrafy, using Claude, and you earn 25% of every sale.

1. On the app's page in the App console (deploy.caligrafy.com), **Marketplace**: say where we pay you (your country;
   wire details where Stripe does not pay out), then **Offer it to the marketplace**.
2. In Claude Code, in the app's folder, run `/caligrafy-quill:marketplace bookshelf`. It reads the whole code, runs the
   app on your computer and walks through it to screenshot its key features (5 at most), writes the title, the
   description, the prompt that recreates the app and an estimated price window in one private file,
   `.caligrafy/marketplace.json` (ignored by git), then sends it to Caligrafy and redeploys the app. It often takes 15 to
   30 minutes. Without Claude Code, write the file yourself and send it: `php caligrafer.php cloud submit bookshelf`.
3. Caligrafy reviews the listing, sets the final price and publishes it, or turns it down with a reason (offer it again
   after the changes). On caligrafy.com, **Listings** follows each listing (offered, to fix, published), **Earnings** its
   sales and your payouts (from 50 USD, once a month, 14 days after each sale), and **Tasks** what waits for you.

Each app on Explore comes three ways: its **prompt**, which recreates it with Caligrafy, using Claude; its **code**,
reproduced from the prompt into a private GitHub repository of the buyer; or **managed for you**, online at
`<name>.caligrafy.com` for a monthly or yearly price. You earn 25% of the prompt's price on each of them. What people buy
is in **Library** (prompts and code), and managed apps are in **Apps** (the App console), under *Managed for you*.

**Submitting an idea.** No code at all: on [caligrafy.com/ideas/new](https://caligrafy.com/ideas/new), describe the app
(what it does, who it is for, its powers) and get an estimate, free. Caligrafy reviews it, builds a proof of concept and
gives it to you to try, privately, for 3 days; ask for fixes once, then take it in one of the three ways or drop it.
Your ideas are in **Ideas** on caligrafy.com.

## Your own server

The same image runs on any host that builds a Dockerfile and lets you give it a file and a database.

+ **[Coolify](https://coolify.io)** (open source, on your own server): a *Dockerfile* application from your repository,
  a MySQL database, the `.env` as a *file storage* mounted at `/var/www/html/.env`, and volumes at
  `/var/www/html/public/uploads` and `/var/lib/php/sessions`. MySQL 8.4 with its defaults needs more than 256 MB of
  memory: give the database more, or a smaller configuration.
+ **Any server with Docker:**

```bash
docker build -t bookshelf .
docker run -d -p 80:80 \
  -v "$(pwd)/.env.production:/var/www/html/.env:ro" \
  -v bookshelf-uploads:/var/www/html/public/uploads \
  -v bookshelf-sessions:/var/lib/php/sessions \
  bookshelf
```

Put HTTPS in front of it (a load balancer, Coolify's proxy, Caddy...). Hosts that offer only environment variables and
no file (Railway, Render, DigitalOcean App Platform) are not supported yet.

**Without Docker** (files copied to a PHP 8.3+ server with Apache): run `php caligrafer.php env cache` after each change
to `.env` (never commit `.env.php`), and `php caligrafer.php deploy` for the database. After copying files that keep
their old modification times (`rsync -a`, `cp -p`), run `php caligrafer.php cache clear`: it empties the compiled views
and the class map (which otherwise finds new classes by itself within a minute with `APP_ENV=production`).

**Scheduled jobs** ([Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs)): run a second
container from the same image and `.env` with `php caligrafer.php schedule work` (on Coolify, a second application with
that start command), or `php caligrafer.php schedule due` from the server's cron every minute. One of the two.

**Background jobs** ([Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs)): a job on
`queue_work` in `schedule.php` runs them with the scheduler, or a container of its own runs `php caligrafer.php queue
work` for longer jobs.

**Notifications:** the web push keys made once (`php caligrafer.php notify keys --subject https://<the address>`) and
kept across deploys, `APP_URL`, `MAIL_*`, and HTTPS.

## Common mistakes

| You see | Why, and what to do |
| --- | --- |
| The build fails | `cloud logs <name>` shows the build: usually `composer.lock` missing or out of date (`composer update`, commit the lock file) |
| `caligrafer deploy: the database is not prepared` | the line above it says why: the database is not reachable (the `DB_*` settings, or `--wait` longer), or a SQL error in a `create_db.sql` or a migration (it names the statement that failed) |
| `Host ... is not allowed to connect` | the database was created but its user was not: on your own server, create the database again (it has no data yet) |
| Caligrafy Cloud cannot read a private repository | the deploy key is not added on GitHub |
| The address shows a paused page | its time ended (`cloud extend <name> week`), or it is deploying, or there is no app with that name |
| `deploy` answers *has no time left (its free 30 minutes are used)* | `cloud extend <name> week` (2 credits), then deploy again; `cloud credits 5` to buy credits |
| The page works locally, its calls get `[ERROR 1004] Access Denied` online | the page sends the legacy API key, off on Caligrafy Cloud: call the routes with `apiHeaders()` or the JS client. A single page application made before 2.19.1 sends `VITE_API_KEY`: give it the session headers of the 2.19.1 template (`apiHeaders()` of `src/common/app.js`) |
| `cloud login` waits and nothing opens | it runs in the Docker server: open the address it printed, approve the code |
| Everyone is signed out after a deploy | no volume at `/var/lib/php/sessions` |
| Uploaded files disappear after a deploy | no volume at `/var/www/html/public/uploads` |
| Pages are slow online | `APP_DEBUG=true` in the production `.env` |
| A setting changed on your own server seems ignored | run `php caligrafer.php env cache` again (or restart the container: the deploy step compiles `.env`) |

## Related pages

+ [Security and upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading)
+ [Caligrafer: cloud, deploy, env](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#cloud)
+ [Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations)
+ [Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs)
+ [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin)

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
