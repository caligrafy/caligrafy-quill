---
name: deploy
description: Put a Caligrafy Quill project online - check it is ready for production (Dockerfile, composer.lock, create_db.sql scripts safe to run again, no secrets in git, pushed to GitHub), then deploy it to Caligrafy Cloud (https://<name>.caligrafy.com) with php caligrafer.php cloud, or prepare it for the user's own server (Coolify, Docker).
when_to_use: The user asks to deploy, publish, host, ship or put online a Caligrafy Quill project, to use Caligrafy Cloud or deploy.caligrafy.com, to run php caligrafer.php cloud or deploy, or a production build, start or database fails ("caligrafer deploy: the database is not prepared", a paused page, a private repository Caligrafy Cloud cannot read).
argument-hint: "[app-name] [--own-server]"
---

# Put a Caligrafy project online

Target: `$ARGUMENTS` (an app name for Caligrafy Cloud; `--own-server` to prepare the project for Coolify or Docker
instead). Read [20-putting-it-online.md](../caligrafy/reference/20-putting-it-online.md), gotchas G67-G73 in
[gotchas.md](../caligrafy/reference/gotchas.md), and the `deploy` and `cloud` rows of the CLI table in
[api-reference.md](../caligrafy/reference/api-reference.md). Needs Caligrafy Quill 2.4.0+ (`php caligrafer.php version`).

## 1. Is the project ready? (read-only, report every point)

```bash
php caligrafer.php version                        # 2.4.0 or later; older: upgrade first (release notes 2.4.0)
ls Dockerfile Dockerfile.dockerignore composer.lock
git status --short && git log origin/HEAD..HEAD --oneline 2>/dev/null   # uncommitted or unpushed work
git remote get-url origin                         # a GitHub repository
git ls-files | grep -E '(^|/)\.env($|\.)' | grep -v '\.env\.example$'  # must print nothing
php caligrafer.php doctor --only project
```

+ **Dockerfile and `Dockerfile.dockerignore`** at the root: 2.4.0 projects have them; an upgraded project copies them
  from the release (they are not templates).
+ **`composer.lock` committed** (G69).
+ **No secrets in git**: no `.env` (or `.env.production`...) tracked, no keys in code or in `public/`. If one is tracked,
  stop: it must be removed from git *and* rotated (new `APP_KEY`/`API_KEY`, new service keys), because it stays in the
  history. Never print a secret's value; name the file and the key only.
+ **Every `application/models/*/create_db.sql` is safe to run again** (G68): `CREATE TABLE IF NOT EXISTS`, no `DROP`
  or `DELETE`, columns added with `ALTER TABLE ... ADD COLUMN`. Report the scripts that are not. Changes to tables
  that already have data belong in migrations (2.18.0+, `application/migrations`, run once by the deploy step): a
  migration edited after it ran never runs again (G120), so `php caligrafer.php migrate status` must show no `changed`.
+ **Background jobs, if the app pushes any** (`Queue::push`, 2.18.0+): `application/config/schedule.php` has a job on
  `queue_work`, or the own server runs `php caligrafer.php queue work` in a container of its own; never both.
+ **The app's tests pass** when it has some: `php caligrafer.php test` (2.18.0+).
+ **Payments, if the app takes them**: online (`APP_ENV=production`) it uses the `PAY_*_PRODUCTION` live keys. A Stripe
  webhook needs its live-mode endpoint and live secret, and its test-mode endpoint disabled or pointed elsewhere (G115).
+ **Committed and pushed**: the host builds what is on GitHub, not the working folder. Commit and push only with the
  user's OK.

## 2a. Caligrafy Cloud (default)

**To ship without hand-offs** (the repository, the sign-in, the deploy key, the deploy, all done for the user), follow
the [ship skill](../ship/SKILL.md). The steps below are the same commands, one by one.

The user needs an account at https://deploy.caligrafy.com (sign-up and the confirmation email are theirs: never create
an account or type a password).

1. **Sign in.** `test -f .caligrafy/cloud.json && php caligrafer.php cloud account`. If not signed in, run in the
   background (2.7.0+, G88):
   ```bash
   php caligrafer.php cloud login
   ```
   It prints a code and opens `https://deploy.caligrafy.com/cli?code=...` (from the Docker server, open that URL for
   the user): they sign in and approve the same code; the command collects the token. Before 2.7.0 it reads a token
   pasted from the dashboard (G72). Never ask for the token in chat, never read `.caligrafy/cloud.json`.
2. **The app.** `php caligrafer.php cloud apps`. If the name is new:
   ```bash
   php caligrafer.php cloud create <name>                 # repo from git origin, branch main (--repo, --branch)
   ```
   Names: 3 to 30 lowercase letters, digits and hyphens, starting with a letter; some are reserved (`www`, `admin`, `api`, `deploy`...); a taken name
   answers a conflict: propose another. For a **private repository** the answer contains a read-only deploy key, saved
   in `.caligrafy/<name>-deploy-key.pub` (2.7.0+, `cloud key <name>`): add it with
   `bash claude-plugin/skills/ship/scripts/github.sh deploy-key <owner>/<repo> .caligrafy/<name>-deploy-key.pub`
   (the GitHub CLI, signed in by a browser approval when needed), or on GitHub (*Settings, Deploy keys*, *Allow write
   access* off).
3. **Settings.** The database and the keys are written for the app. For each service the project uses (`OPEN_AI_KEY`,
   `PAY_*`, `MAIL_*`...), the user sets it; it is applied at the next deploy:
   ```bash
   php caligrafer.php cloud secret set <name> KEY=value   # the user runs it with their value; you never type a real secret
   php caligrafer.php cloud secret list <name>            # names only
   ```
4. **Deploy** (several minutes: run it in the background and wait for it):
   ```bash
   php caligrafer.php cloud deploy <name>
   ```
   Run it whole, never through `| tail` (G89): it exits 1 on a refusal. The first deploy of every new app starts its own
   free 30 minutes (G90). Then check it: `curl -s -o /dev/null -w '%{http_code}' https://<name>.caligrafy.com/`. A `302`
   to `deploy.caligrafy.com/unlock/<name>` means the owner locked it with a password (`[password protected]` in
   `cloud status`): it is online (G114).
5. **Keep it online.** Time left: `cloud status <name>`. Credits: `cloud credits 5|10|25|50` prints a Stripe page for the
   user to pay (never pay for them); `cloud extend <name> week|month|year` spends 2, 5 or 50 credits, and a paused app
   starts again. Spending credits and deleting (`cloud delete <name> --yes`, permanent) need the user's explicit OK.

## 2b. Own server (`--own-server`)

Prepare, do not deploy: the user's server, account and keys are theirs.
+ Write `.env.production` from `.env` **only with the user's OK, never committed** (it is ignored by `Dockerfile.dockerignore`;
  check `.gitignore` covers it): `APP_ROOT=` empty, `APP_ENV=production`, `APP_DEBUG=false`, new keys
  (`php caligrafer.php generatekeys`), `DB_ACTIVATE=true` and the production `DB_*`.
+ Coolify: a *Dockerfile* application, a MySQL database, the `.env` as a file storage at `/var/www/html/.env`, volumes at
  `/var/www/html/public/uploads` and `/var/lib/php/sessions` (G70). A MySQL with a small memory limit needs the
  configuration of G71.
+ Docker: the `docker run` of 20-putting-it-online.md (`#ownserver`), behind HTTPS.
+ Scheduled jobs (`php caligrafer.php schedule list` lists some): a second container from the same image, with the same
  `.env`, running `php caligrafer.php schedule work` (on Coolify, a second application with that start command), or
  `php caligrafer.php schedule due` from the server's cron every minute. Only one of them, never both.
+ Notifications (the project calls `Caligrafy\Notify`): the web push keys made once with `php caligrafer.php notify keys
  --subject https://<the address>` and kept across deploys (new keys cut off every subscribed browser), `APP_URL=https://<the
  address>` for the links of emails sent by jobs (G110), `MAIL_*` and `MAIL_FROM` for email, and HTTPS (push needs it).
+ Environment-variable-only hosts (Railway, Render, DigitalOcean App Platform) are not supported yet (G67): say so.

## 3. When it fails

| Symptom | Look at | Usual fix |
| --- | --- | --- |
| Build fails | `php caligrafer.php cloud logs <name>` | commit `composer.lock`; a PHP extension the image lacks |
| `caligrafer deploy: the database is not prepared` | the line above it in the logs | a SQL error in a `create_db.sql`; on an own server `DB_*` or `--wait` |
| `Host ... is not allowed to connect` | own server only | G71: recreate the empty database volume |
| `create` or `deploy` refuses the repository | the message | private repository without the deploy key; no `Dockerfile` on that branch |
| Paused page at the address | `cloud status <name>` | time ended: `cloud extend`; or still deploying; or a wrong name |
| Signed out after every deploy, uploads gone | own server | G70 volumes |

Report what you checked, what you changed (with the user's OK), the URL, and the time left.
