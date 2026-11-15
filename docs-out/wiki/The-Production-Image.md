Every Caligrafy project has a **production image**: the `Dockerfile` at its root. Caligrafy Cloud, Coolify and any
host that builds from a Dockerfile use it as it is. This page says what is in it, what is left out, what runs when it
starts, and what the container needs from the host. Since 2.4.0.

> `docker/Dockerfile` is another file: the development image of `docker-compose.yaml` (the `dev-box` and `scheduler`
> containers).

<br/>

## With Claude

```text
/caligrafy-quill:deploy
```

The `deploy` skill checks that the project is ready for the image (the `Dockerfile`, `composer.lock`, no `.env` in git,
safe `create_db.sql` scripts, a built SPA) and says what to fix. It deploys nothing.

**Check** before you say OK: `docker build -t myapp .` finishes, and `git ls-files` lists no `.env`, `.env.php` or
`.env.production`.

<br/>

## By hand

Build and run the image on your computer, with a production-like `.env` of your own:

```bash
docker build -t bookshelf .
docker run --rm -p 8090:80 -v "$(pwd)/.env.production:/var/www/html/.env:ro" bookshelf
```

The container's log starts with the [deploy step](https://github.com/caligrafy/caligrafy-quill/wiki/The-Deploy-Step),
then Apache's. A `.env.production` whose database cannot be reached prints
` FAIL  Database: not reachable after 60 s: check the DB_* settings` and
`caligrafer deploy: the database is not prepared (see above)`, and the site starts anyway.

<br/>

## <a name="contents"></a>What is in it

| Layer | What the `Dockerfile` does |
| --- | --- |
| Base | `php:8.5-apache` |
| System | Debian packages over `https`, the libraries for `gd`, `zip` and `intl`, `unzip` and `git`; Apache's `rewrite` and `headers` modules |
| PHP | the extensions `bcmath`, `exif`, `gd` (FreeType, JPEG), `intl`, `mysqli`, `pdo_mysql` and `zip`; the production `php.ini` |
| Sessions | `session.save_path = "/var/lib/php/sessions"`, a folder owned by `www-data` |
| Libraries | Composer 2, then `composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --no-plugins` from `composer.json` and `composer.lock` alone, so a code change does not install them again |
| Code | `COPY . .` (less what `Dockerfile.dockerignore` leaves out), `composer dump-autoload --optimize --no-dev --no-plugins`, `public/uploads` and `backups/` created, `public/uploads` owned by `www-data` |
| Port | `80` |

No Node.js and no `npm`: client apps are served as they are in the repository, a Vite app from its committed `dist/`.
Composer runs with `--no-plugins` (2.22.1), so no Composer plugin tries to run `npm` during the build.

<br/>

## <a name="ignore"></a>What is left out

`Dockerfile.dockerignore` keeps these out of the image:

| Left out | Why |
| --- | --- |
| `.env`, `.env.*` (but `.env.example`) | secrets: the host gives the settings |
| `.git`, `.github` | history and CI |
| `vendor`, `**/node_modules` | installed in the image, or not needed |
| `qa`, `tests`, `backups` | development, tests and update backups |
| `claude-plugin`, `.claude`, `.claude-plugin`, `framework/design` | tools for Claude Code and the design styles' sources |
| `*.log`, `.DS_Store` | |
| `public/uploads/*` (but its `.gitignore`) | uploads live in a volume |
| `.caligrafy` | the Cloud sign-in and the marketplace listing |

<br/>

## <a name="start"></a>What runs at start

```sh
php caligrafer.php deploy --wait ${CALIGRAFY_DEPLOY_WAIT:-60} || echo 'caligrafer deploy: the database is not prepared (see above)'; exec apache2-foreground
```

1. [`php caligrafer.php deploy`](https://github.com/caligrafy/caligrafy-quill/wiki/The-Deploy-Step): the scheduled jobs
   line, `.env` compiled to `.env.php`, then the database (it waits up to `CALIGRAFY_DEPLOY_WAIT` seconds, 60 by
   default, for a database that starts at the same time).
2. Apache in the foreground, whatever the deploy step said: a database that is not ready does not keep the site down.

<br/>

## <a name="host"></a>What the container needs from the host

+ **Its settings as a file** at `/var/www/html/.env` (G67): Caligrafy reads `.env`, and its values replace plain
  environment variables of the same name. Coolify mounts it as a *file storage*, `docker run` with
  `-v .env.production:/var/www/html/.env:ro`, and Caligrafy Cloud writes it. Hosts that only offer environment
  variables (Railway, Render, DigitalOcean App Platform) are not supported yet.
+ **The production settings** in that file: `APP_ROOT=` (empty), `APP_ENV=production`, `APP_DEBUG=false`,
  `APP_PROTOCOL=https`, its own `APP_KEY` and `API_KEY` (`php caligrafer.php generatekeys`), `DB_ACTIVATE=true` and the
  production `DB_*`, and the keys of your services. The list and the reasons:
  [Security: the production settings](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading).
+ **A MySQL database** it can reach.
+ **Two volumes** (G70): `/var/lib/php/sessions` (people stay signed in across deploys) and
  `/var/www/html/public/uploads` (uploads survive a deploy).
+ **HTTPS in front of it**: a load balancer, Coolify's proxy, Caddy... The session cookie is `Secure` behind a proxy
  that sends `X-Forwarded-Proto: https`.

<br/>

## Common mistakes

+ **The build fails on Composer** (G69): `composer.lock` is missing or older than `composer.json`. Run
  `composer update` and commit it.
+ **A Vite app is blank online**: its `dist/` is not committed. Run `npm run build` in `public/<name>` and commit
  `dist/`.
+ **Everyone is signed out after a deploy, uploads are gone** (G70): no volume at `/var/lib/php/sessions` or
  `/var/www/html/public/uploads`.
+ **Settings given as environment variables are ignored** (G67): give the container its `.env` as a file.
+ **`APP_ROOT=bookshelf` online**: the site's links point to `/bookshelf/...`. `APP_ROOT=` is empty in production.
+ **Pages are slow online**: `APP_DEBUG=true` in the production `.env`.

<br/>

## Related pages

+ [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online)
+ [The deploy step](https://github.com/caligrafy/caligrafy-quill/wiki/The-Deploy-Step)
+ [Deploying to Caligrafy Cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Deploying-to-Caligrafy-Cloud) · [your own server](https://github.com/caligrafy/caligrafy-quill/wiki/Deploying-to-Your-Own-Server)
+ [Security and upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading)

<br/><br/>
>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
