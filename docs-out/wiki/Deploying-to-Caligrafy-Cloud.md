[Caligrafy Cloud](https://deploy.caligrafy.com) puts a project online at `https://<name>.caligrafy.com`: it builds the
project from its GitHub repository with its own `Dockerfile`, gives it a MySQL database, its keys, its `.env` and the
volumes it needs, and runs its scheduled jobs. Every new app is free for its first 30 minutes online. Since 2.4.0.

<br/>

## With Claude

```text
/caligrafy-quill:ship bookshelf
```

It commits, creates the GitHub repository and pushes (your SSH key, else HTTPS with the GitHub CLI, else a code to
approve in your browser), signs in to Caligrafy Cloud with an approval in your browser, creates the app, adds its deploy
key, deploys it and checks it online. You approve in the browser, and say yes before credits are spent.

**Check** before you say OK:
+ `php caligrafer.php cloud status bookshelf` says `running`, with the time left;
+ `https://bookshelf.caligrafy.com` opens; sign-in and a form work there;
+ `git ls-files` lists no `.env`, `.env.php` or `.env.production`.

<br/>

## By hand

Run the commands on your computer, in the project's folder, with its Docker server up (without Docker, drop
`docker compose exec dev-box`).

**1. Check the project.**

```bash
docker compose exec dev-box php caligrafer.php version                        # 2.4.0 or later
ls Dockerfile Dockerfile.dockerignore composer.lock
git ls-files | grep -E '(^|/)\.env($|\.)' | grep -v '\.env\.example$' | grep -v '^framework/settings/'   # must print nothing
docker compose exec dev-box php caligrafer.php doctor --only project          # no FAIL
```

Every `create_db.sql` must be safe to run again ([The deploy step](https://github.com/caligrafy/caligrafy-quill/wiki/The-Deploy-Step#scripts)),
`composer.lock` committed, and a Vite single page application built (`npm run build` in `public/<name>`, `dist/`
committed).

**2. An account.** Create it at [deploy.caligrafy.com](https://deploy.caligrafy.com/signup) (your email is your
username; the same account signs in to caligrafy.com) and open the link of the confirmation email.

**3. Your code on GitHub.**

```bash
git add -A
git commit -m "Bookshelf"
git push
```

**4. Sign in.**

```bash
docker compose exec dev-box php caligrafer.php cloud login
```

```text
 To sign in, approve this code in your browser: <code>
 https://deploy.caligrafy.com/cli?code=...
```

Open the address (in the Docker server the browser does not open by itself), check that the code is the same, approve.
The command then prints ` Signed in as you@example.com (... credits). The token is in .caligrafy/cloud.json (git-ignored).`

**5. Create the app.**

```bash
docker compose exec dev-box php caligrafer.php cloud create bookshelf
```

```text
 Created https://bookshelf.caligrafy.com from https://github.com/you/bookshelf (main).
 Next: php caligrafer.php cloud deploy bookshelf
```

It reads the repository from the project's `origin` remote (`--repo <url>` names another, `--branch <b>` another
branch). A **private** repository gets a read-only deploy key, printed and saved in
`.caligrafy/bookshelf-deploy-key.pub`: add it on GitHub before deploying,

```bash
gh repo deploy-key add .caligrafy/bookshelf-deploy-key.pub -R <owner>/<repo> --title "Caligrafy Cloud"
```

or in the repository's *Settings, Deploy keys*, with *Allow write access* off.

**6. Its settings.** The database settings, the keys, `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false` and
`LEGACY_API_KEY=false` are written for the app. Add the keys of your services:

```bash
docker compose exec dev-box php caligrafer.php cloud secret set bookshelf PAY_PRIVATE_KEY_PRODUCTION=sk_live_...
docker compose exec dev-box php caligrafer.php cloud secret list bookshelf
```

Each one is stored encrypted, never shown again, and applied at the next deploy.

**7. Deploy.**

```bash
docker compose exec dev-box php caligrafer.php cloud deploy bookshelf
```

```text
 Deploying https://bookshelf.caligrafy.com (a few minutes)...
 Online: https://bookshelf.caligrafy.com
```

It follows the build for up to 15 minutes (`--no-wait` returns at once). A failed build prints the last 30 lines of
its log.

**8. Check it.** Open `https://bookshelf.caligrafy.com`, sign in, send a form. Then:

```bash
docker compose exec dev-box php caligrafer.php cloud status bookshelf      # running, the time left
docker compose exec dev-box php caligrafer.php cloud logs bookshelf        # the deploy step, then what the app prints
docker compose exec dev-box php caligrafer.php cloud jobs bookshelf        # the scheduled jobs, their last and next runs
```

**9. Keep it online.** After the free 30 minutes, add time:

```bash
docker compose exec dev-box php caligrafer.php cloud credits 10               # prints the Stripe page to pay on
docker compose exec dev-box php caligrafer.php cloud extend bookshelf month   # 5 credits; a paused app starts again
```

<br/>

## <a name="reference"></a>Reference

| | |
| --- | --- |
| Free time | every new app, its first 30 minutes online from its first deploy |
| Credits | 1 credit = 1 USD, in packs of 5, 10, 25 or 50, paid with Stripe |
| Time online | a week: 2 credits; a month: 5; a year: 50; added to what is left |
| When the time ends | the app is paused (its address shows a paused page) and you get an email; its data is kept 7 days, then deleted |
| Limits | 5 apps per account, 256 MB of memory and a quarter of a CPU per app |
| Settings | `.env` written by Cloud, with your `cloud secret` values; yours cannot replace the database settings and keys |
| Scheduled jobs | read at each deploy from the `caligrafy-schedule` line of the deploy step; `cloud jobs` shows them |
| Sign-in for the command line | `.caligrafy/cloud.json`, or `CALIGRAFY_CLOUD_TOKEN`; `CALIGRAFY_CLOUD_URL` for another service |
| AI agents | `https://deploy.caligrafy.com/mcp` with a token of the dashboard (*Command line and AI agents*): list, deploy and extend your apps |

Every `cloud` command is on [Caligrafer: deploy commands](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer-Deploy-Commands#cloud).

**A password until it is on the marketplace.** The app's page has a *Password* section that locks the app with a
password you share (off by default). Each browser types it once, for 30 days. While locked, pages send visitors to
`https://deploy.caligrafy.com/unlock/<name>`, API and agent calls get `401`, and `cloud apps` and `cloud status` print
`[password protected]`. Locking or unlocking restarts the app (2 to 5 minutes). Offering the app to the marketplace
removes the password for good ([Selling your app](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#marketplace)).

<br/>

## Common mistakes

| You see | Why, and what to do |
| --- | --- |
| The build fails | `cloud logs <name>`: usually `composer.lock` missing or out of date (G69): `composer update`, commit it |
| Cloud cannot read a private repository | the deploy key is not added on GitHub (`cloud key <name>` prints it again) |
| `deploy` answers *has no time left (its free 30 minutes are used)* | `cloud extend <name> week` (2 credits), then deploy again (G90) |
| `cloud deploy ... \| tail` says nothing went wrong | a pipe hides the exit code (G89): run it whole |
| Pages work locally, their calls get `[ERROR 1004] Access Denied` online | the legacy API key is off on Cloud (G87): call the routes with `apiHeaders()` or the JS client |
| The address shows a paused page | its time ended, it is deploying, or there is no app with that name |
| `cloud login` waits and nothing opens | it runs in the Docker server: open the address it printed |
| Stripe emails about failing test-mode webhooks once live | a test-mode endpoint still points at the production address (G115): create the endpoint in live mode, set its `whsec_...` as `STRIPE_WEBHOOK_SECRET`, and remove the test one |

<br/>

## Related pages

+ [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online)
+ [The production image](https://github.com/caligrafy/caligrafy-quill/wiki/The-Production-Image) · [The deploy step](https://github.com/caligrafy/caligrafy-quill/wiki/The-Deploy-Step)
+ [Deploying to your own server](https://github.com/caligrafy/caligrafy-quill/wiki/Deploying-to-Your-Own-Server)
+ [Caligrafer: deploy commands](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer-Deploy-Commands)

<br/><br/>
>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
