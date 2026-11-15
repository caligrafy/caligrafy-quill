The [production image](https://github.com/caligrafy/caligrafy-quill/wiki/The-Production-Image) of a project runs on
any host that builds a Dockerfile and lets you give the container a file and a database: Coolify on your own server, or
any server with Docker. A PHP server without Docker works too. This page sets up each one, with the scheduled jobs, the
background jobs and the notifications. Since 2.4.0.

<br/>

## With Claude

```text
/caligrafy-quill:deploy --own-server
```

It checks that the project is ready, writes `.env.production` for the host with your OK (its own keys from
`generatekeys`, `DB_ACTIVATE=true` and the production `DB_*`; never committed), and says what to set up on the host. It deploys nothing:
the server is yours.

**Check** before you say OK: `curl -I https://<your-host>/.env` answers `403` or `404`, and sign-in and a form work.

<br/>

## By hand

### <a name="coolify"></a>Coolify

[Coolify](https://coolify.io) is open source and runs on your own server.

1. A **MySQL** database resource. MySQL 8.4 with its defaults needs more than 256 MB of memory (G71): give it more, or
   a smaller configuration (`performance_schema = OFF`, `innodb_buffer_pool_size = 64M`, about 384 MB).
2. A **Dockerfile** application from your repository (it uses the `Dockerfile` at the root).
3. The production `.env` as a **file storage** mounted at `/var/www/html/.env`, with the database's host, name, user
   and password in `DB_*` and `DB_ACTIVATE=true`.
4. Two **volumes**: `/var/www/html/public/uploads` and `/var/lib/php/sessions`.
5. Deploy. The application's log starts with [the deploy step](https://github.com/caligrafy/caligrafy-quill/wiki/The-Deploy-Step).

Coolify's proxy gives it HTTPS.

### <a name="docker"></a>Any server with Docker

```bash
docker build -t bookshelf .
docker run -d -p 80:80 \
  -v "$(pwd)/.env.production:/var/www/html/.env:ro" \
  -v bookshelf-uploads:/var/www/html/public/uploads \
  -v bookshelf-sessions:/var/lib/php/sessions \
  bookshelf
```

Put HTTPS in front of it (a load balancer, Caddy...). `-e CALIGRAFY_DEPLOY_WAIT=120` gives a database that starts
slowly two minutes.

### <a name="nodocker"></a>Without Docker

Files copied to a PHP 8.3+ server with Apache (`mod_rewrite`, `AllowOverride FileInfo` or `All` for the `.htaccess`
files) and the extensions the image has (`bcmath`, `exif`, `gd`, `intl`, `mysqli`, `pdo_mysql`, `zip`):

```bash
composer install --no-dev
php caligrafer.php env cache      # after each change to .env (never commit .env.php)
php caligrafer.php deploy         # the database: framework tables, scripts, migrations
php caligrafer.php cache clear    # after copying files that keep their old modification times (rsync -a, cp -p)
```

`cache clear` empties the compiled views and the class map; with `APP_ENV=production` the class map otherwise finds new
classes by itself within a minute (G117). On nginx, write the rules of the `.htaccess` files yourself: they deny
dotfiles, the server-side folders and scripts in `public/uploads`.

<br/>

## <a name="jobs"></a>Scheduled jobs, background jobs, notifications

+ **Scheduled jobs** ([Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs)): one of the
  two, not both:
  + a second container from the same image and `.env`, with the command `php caligrafer.php schedule work` (on Coolify,
    a second application with that start command);
  + or the server's cron, every minute: `* * * * * cd /var/www/html && php caligrafer.php schedule due`.
+ **Background jobs** ([Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs)): a job of
  `application/config/schedule.php` on the framework's `queue_work` capability runs them with the scheduler; for longer
  jobs, a container of its own runs `php caligrafer.php queue work`.
+ **Notifications** ([Notifications](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications)): the web push
  keys made once (`php caligrafer.php notify keys --subject https://<the address>`) and kept across deploys, `APP_URL`,
  the `MAIL_*` settings, and HTTPS.

<br/>

## <a name="checklist"></a>Before you open it to people

+ `APP_ENV=production`, `APP_DEBUG=false`, `APP_ROOT=` (empty), `APP_PROTOCOL=https`, keys of its own
  (`php caligrafer.php generatekeys`).
+ `curl -I https://<your-host>/.env` answers `403` or `404`.
+ Personal access tokens for programs and agents, then `LEGACY_API_KEY=false`.
+ `php caligrafer.php doctor --env .env.production` from your computer, when it can reach the database.

The whole list, and why: [the production checklist](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#checklist).

<br/>

## Common mistakes

| You see | Why, and what to do |
| --- | --- |
| The settings are ignored | the host gives environment variables, not a file (G67): mount the `.env` at `/var/www/html/.env`. Railway, Render and DigitalOcean App Platform are not supported yet |
| `Host ... is not allowed to connect` | MySQL was killed while it initialized (G71): more memory, then recreate its volume while it has no data |
| Everyone is signed out after a deploy | no volume at `/var/lib/php/sessions` (G70) |
| Uploaded files disappear after a deploy | no volume at `/var/www/html/public/uploads` (G70) |
| A changed view still shows the old page | files copied with old modification times (G117): `php caligrafer.php cache clear` |
| A job's `Cache::forget()` does not reach the pages | `CACHE_STORE=file`: the web server and the command line have their own folders (G119). Keep the `database` store |
| Jobs run twice | both a `schedule work` container and a cron line |
| A setting changed on the server seems ignored | run `php caligrafer.php env cache` again, or restart the container |

<br/>

## Related pages

+ [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online)
+ [The production image](https://github.com/caligrafy/caligrafy-quill/wiki/The-Production-Image) · [The deploy step](https://github.com/caligrafy/caligrafy-quill/wiki/The-Deploy-Step)
+ [Deploying to Caligrafy Cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Deploying-to-Caligrafy-Cloud)
+ [Security and upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading)

<br/><br/>
>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
