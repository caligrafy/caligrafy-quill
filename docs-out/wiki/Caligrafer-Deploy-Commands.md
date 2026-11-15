The **deploy commands** of [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer) put a project
online: `deploy` prepares the database at each start in production, `cloud` runs the project on Caligrafy Cloud, and
`generatekeys` and `generateapikey` make keys for another environment.

<br/>

## With Claude

```text
/caligrafy-quill:ship bookshelf
/caligrafy-quill:deploy --own-server
```

`ship` commits, creates the GitHub repository, pushes, signs in to Caligrafy Cloud with an approval in your browser,
runs `cloud create`, adds the deploy key, runs `cloud deploy` and checks the app online. `deploy --own-server` checks the
project and prepares it for your own server, and deploys nothing.

**Check** before you say OK: `php caligrafer.php cloud status bookshelf` says `running`, and
`https://bookshelf.caligrafy.com` opens.

<br/>

## By hand

```bash
docker compose exec dev-box php caligrafer.php cloud login
docker compose exec dev-box php caligrafer.php cloud create bookshelf
docker compose exec dev-box php caligrafer.php cloud deploy bookshelf
```

(without Docker, drop `docker compose exec dev-box`). The whole walk-through is
[Deploying to Caligrafy Cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Deploying-to-Caligrafy-Cloud).

<br/>

## <a name="deploy"></a>`deploy`

```text
deploy [--wait <seconds>]: prepares the database in production: framework tables, every new or changed application/models/<folder>/create_db.sql (never one that drops or deletes data twice), then the pending migrations
```

```bash
php caligrafer.php deploy                 # what the production image runs at start
php caligrafer.php deploy --wait 120      # wait up to 2 minutes for the database (default 60)
```

```text
 Caligrafy deploy
 caligrafy-schedule {"version":1,"jobs":[]}
  OK    Settings: .env compiled to .env.php (read without parsing on every request)
  OK    Framework tables: tokens, audit log, job runs, jobs, cache, migrations, scripts
  OK    application/models/Bookshelf/create_db.sql: run
  OK    Migrations: up to date
```

The production image runs it before Apache starts. What it does, line by line, is
[The deploy step](https://github.com/caligrafy/caligrafy-quill/wiki/The-Deploy-Step). It exits with `1` when the
database could not be prepared, and `0` when it is ready or `DB_ACTIVATE` is not `true`.

<br/>

## <a name="cloud"></a>`cloud`

`php caligrafer.php cloud` (or `cloud help`) prints the list. Every command calls a capability of Caligrafy Cloud
(`POST https://deploy.caligrafy.com/capabilities/<name>`) with the account's token, and exits with `1` when Cloud
refuses, with its message.

### Signing in

| Command | What it does |
| --- | --- |
| `cloud login` | prints a code and opens `https://deploy.caligrafy.com/cli?code=...` in your browser: approve the code there, and the command collects the token |
| `cloud login --no-browser` | the same without opening the browser: open the address it prints |
| `cloud login --token cq_...` | takes a token created on the dashboard |
| `cloud login --paste` | asks for that token at a prompt |
| `cloud account` | your email and credits |
| `cloud logout` | removes `.caligrafy/cloud.json` |

```text
 To sign in, approve this code in your browser: <code>
 https://deploy.caligrafy.com/cli?code=...
 Signed in as you@example.com (10 credits). The token is in .caligrafy/cloud.json (git-ignored).
```

The token is kept in `.caligrafy/cloud.json` (ignored by git, never in the image). `CALIGRAFY_CLOUD_TOKEN` gives one
instead, and `CALIGRAFY_CLOUD_URL` points to another service. In the Docker `dev-box` the browser does not open: open the
address it printed.

### Apps

| Command | What it does |
| --- | --- |
| `cloud create <name> [--repo <url>] [--branch <b>]` | the app, built from a GitHub repository: the project's `origin` remote when `--repo` is missing (read from `.git/config` in the `dev-box`, which has no `git`) |
| `cloud key <name>` | the read-only deploy key of an app built from a private repository, printed alone (for `> key.pub`) and saved in `.caligrafy/<name>-deploy-key.pub` |
| `cloud deploy <name> [--no-wait]` | builds and starts it, and follows the build up to 15 minutes unless `--no-wait` |
| `cloud apps` | your apps: name, status, address and time left |
| `cloud status <name>` | one app's status and time left, and `[password protected]` when it is locked |
| `cloud logs <name>` | the deploy step's lines, then what the app prints |
| `cloud logs <name> --jobs` | the latest runs of every scheduled job |
| `cloud jobs <name>` | its scheduled jobs, their last and next runs |
| `cloud jobs <name> run <job>` | runs one now (it starts within a minute) |
| `cloud jobs <name> history <job> [--limit 20]` | the runs of one job, with their output |
| `cloud delete <name> --yes` | the app, its database and files, for good; without `--yes` it only says what it would delete |

A private repository: `create` prints the deploy key and the command that adds it,
`gh repo deploy-key add .caligrafy/<name>-deploy-key.pub --title "Caligrafy Cloud"`. Add it before `cloud deploy`.
A failed build prints the last 30 lines of its log.

### Settings

| Command | What it does |
| --- | --- |
| `cloud secret set <name> KEY=value` | a setting of the app's `.env`, applied at the next deploy |
| `cloud secret list <name>` | the names of its settings (never their values) |
| `cloud secret delete <name> KEY` | removes one, applied at the next deploy |

The database settings, the keys, `APP_URL` and `LEGACY_API_KEY=false` are written for the app; your settings cannot
replace them.

### Time and credits

| Command | What it does |
| --- | --- |
| `cloud extend <name> week\|month\|year` | 2, 5 or 50 credits of time online, added to what is left; a paused app starts again |
| `cloud credits 5\|10\|25\|50` | prints the Stripe page that buys credits (1 credit = 1 USD) |

### The marketplace

| Command | What it does |
| --- | --- |
| `cloud submit <name> [--file .caligrafy/marketplace.json] [--no-deploy] [--no-wait]` | sends the listing the `/caligrafy-quill:marketplace` skill wrote, then deploys the app again for the screenshots of its key pages |

The class behind them is [`Caligrafy\CloudClient`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Tooling#cloudclient).

<br/>

## <a name="keys"></a>`generatekeys`, `generateapikey`

```text
generatekeys: generates an APP and an API pair of keys that you can put in your app's environment variable 
generateapikey: generates an API key that you can provide to any third party desiring to access your services
```

```bash
php caligrafer.php generatekeys       #  APP_KEY=...  API_KEY=...
php caligrafer.php generateapikey     #  API_KEY=... for the APP_KEY of .env
```

+ `generatekeys` makes a new `APP_KEY` (32 random bytes, base64) and the `API_KEY` encrypted from it. Use it for another
  environment (a production `.env`), or to replace a leaked `APP_KEY`: changing it signs everyone out and revokes every
  legacy API key.
+ `generateapikey` gives the `API_KEY` of the `APP_KEY` in `.env`. Legacy API keys are all the same key and cannot be
  revoked one by one: for programs and agents, use [`token`](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer-Agent-Commands#token).
+ Both print the keys and write nothing. `initialize` already writes the keys of a new project.

<br/>

## Common mistakes

+ **`cloud deploy <name> | tail` looks fine** (G89): the pipe hides the exit code of a refused deploy. Run it whole.
+ **`deploy` answers *has no time left*** (G90): every new app is free for its first 30 minutes from its first deploy;
  then `cloud extend <name> week`.
+ **The build fails** (G69): `composer.lock` is missing or older than `composer.json`. Run `composer update` and commit
  the lock file.
+ **Pages get `[ERROR 1004]` online** (G87): Caligrafy Cloud turns the legacy API key off. Pages call their routes with
  the session (`apiHeaders()`, the JS client).
+ **A password-protected app answers a redirect** (G114): until it is offered to the marketplace, a locked app sends
  visitors to the unlock page and API calls get `401`.

<br/>

## Related pages

+ [Caligrafer: every command](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer)
+ [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online)
+ [Deploying to Caligrafy Cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Deploying-to-Caligrafy-Cloud) · [your own server](https://github.com/caligrafy/caligrafy-quill/wiki/Deploying-to-Your-Own-Server)
+ [The deploy step](https://github.com/caligrafy/caligrafy-quill/wiki/The-Deploy-Step) · [the production image](https://github.com/caligrafy/caligrafy-quill/wiki/The-Production-Image)

<br/><br/>
>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
