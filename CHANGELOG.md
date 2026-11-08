# Changelog

Every release of Caligrafy Quill is listed here. The project follows [semantic versioning](https://semver.org): a
major version can break existing projects, a minor version adds features, a patch version only fixes bugs. Each
release is a git tag `v<version>` (on [GitHub](https://github.com/caligrafy/caligrafy-quill/tags) and
[Packagist](https://packagist.org/packages/caligrafy/caligrafy-quill)), and `Caligrafy\Framework::VERSION` holds the
same number. Check the version of a project with `php caligrafer.php version`.

## [2.19.7] - 2026-11-08

### Fixed
- `upgrade` and `update` match the whole controller name in the routes: a route to `FaceAuthController` or
  `ServicesAdminController` counted as a use of `AuthController` or `AdminController` in 2.19.6, which added those unused
  files back to the project.

## [2.19.6] - 2026-11-08

### Added
- `Route::auth(['attemptsPerUser' => 20])`: the failed sign-ins of one username, from any address, before it is locked
  (0, off, by default), against guessing spread over many machines. `attempts` still counts per address and username.

### Changed
- `upgrade` and `update` do not add back `AuthController` or `AdminController` to a project that removed them when no
  line of code of its routes names them (only with `Route::auth` before): an app with its own sign-in keeps them out.
- With `Route::auth(['register' => false])` the sign-in page and the top bar of the pages do not link to a sign-up page
  that does not exist (`AuthRoutes::signUpOpen()`).

### Documentation
- Plugin 1.31.6; the wiki (Authentication, Declarative routes) documents `attemptsPerUser`.

## [2.19.5] - 2026-11-08

### Added
- `Route::auth(['level' => 1])`: the lowest permission level that may sign in (0 by default). Below it, a sign-in is
  refused exactly like a wrong password and counted toward the lock; a passkey sign-in answers 401, and
  `signInAfterRegister` does not sign that person in (`AuthRoutes::mayEnter()`).
- `Route::download(..., ['columns' => [...]])`: the header row and its order, written even when the result has no row.

### Changed
- A `Route::download` whose capability refuses its inputs (`invalid`: a wrong date, a bad filter) answers 422 with
  the error as JSON, as REST does; it answered 404.

### Documentation
- Plugin 1.31.5; the wiki (Authentication, Declarative routes) documents both options.

## [2.19.4] - 2026-11-08

### Changed
- The sign-up page of `Route::auth` speaks plainly too: "The two passwords don't match. Type the same password in both
  fields.", "A few details need a fix: see the notes under the fields.", "An account already exists with these details.
  Sign in instead, or use other details." and, on the sign-in page after signing up, "Your account is ready. Sign in to
  start." The `messages` option sets each of them (`mismatch`, `register_invalid`, `register_taken`, `registered`),
  like the sign-in ones (`AuthRoutes::MESSAGES`). The template `AuthController` uses the same words.

### Documentation
- Plugin 1.31.4; the wiki (Authentication, Declarative routes) lists the new keys.

## [2.19.3] - 2026-11-08

### Changed
- A failed sign-in of `Route::auth` speaks plainly: "We couldn't sign you in with those details. Check them and try
  again." and, after too many tries, "Too many tries in a row. For your security, wait 15 minutes, then try again."
  (`AuthRoutes::MESSAGES`). The new `messages` option (`['invalid' => ..., 'locked' => ...]`, `{minutes}` is the wait)
  sets an app's own words. The sign-in page shows again with what the person typed in the username (or identifier)
  field, never the password: `Route::auth` gives it in `$user`, and the template `login.pug` and `AuthController` keep it.
- The user capabilities of the template (`services/Users`: sign up, list, get, update and delete a user) use the query
  builder (`User::find()`, `User::where()`, `User::insert()`, `User::query()`), with every value bound, as 2.19 teaches.
- `upgrade` (and `update`) no longer add back `controllers/core/Auth/AuthController.php` to a project whose routes sign
  people in with `Route::auth`: it is held, with the reason (`--force` adds it).

### Documentation
- Plugin 1.31.3; the wiki (Authentication, Declarative routes) and the course (lesson 11) quote the new words.

## [2.19.2] - 2026-11-08

### Fixed
- Face detection drawings sat off the faces with most cameras: `FaceDetector` sized its canvas from the `<video>`'s
  `width`/`height` attributes (640 x 480, 4:3) while cameras usually send 16:9 (1280 x 720), so boxes, landmarks and
  labels were squeezed (about 25% too narrow) and slid off the face away from the center. The canvas now takes the
  picture's size (`detector.mediaSize()`: `videoWidth` x `videoHeight`), follows a change of resolution while the
  camera streams and copies the media's `object-fit`; `fetchImage()` draws the whole picture at the canvas's size.
- The FaceDetect scaffold loads `detect.js?v=<file time>`, so browsers that kept the old script get the new one.
  Existing client apps keep their own `public/<app>/index.php`: add the same `?v=` there.

### Documentation
- Plugin 1.31.2: gotcha G142 (camera overlays: size them from the picture, not the element), `mediaSize()` in the
  API reference and the wiki.

## [2.19.1] - 2026-11-08

### Security
- Client app pages no longer put the request's Cookie (the session id, which defeated HttpOnly) or Authorization
  header in `env.request`: `views/Client/app.pug` passes `request()->forClient()` (method, host, uri, fullUri,
  uriComponents, uriParameters, parameters, parsedURI, currentRoute, fetch, acceptLanguage; the query string only,
  never a POST body or `_token`). `php caligrafer.php update` gives existing projects the new view.
- The root `.htaccess` refuses `backups/` (the copies made by `caligrafer update` and `upgrade`) like the other
  server folders.
- The Vue SPA template (`caligrafer create`) no longer builds `'Bearer ' + VITE_API_KEY` into its public bundle:
  `src/common/app.js` calls the application with the session and an `X-CSRF-Token` header (`apiHeaders()`, the token
  read once from `GET /capabilities`), as client apps do. `initialize` writes no key into the template's `.env`.
- `.gitignore` ignores every `.env.*` file of the project (`.env.testing`, `.env.production`...) except `.env.example`.

### Fixed
- The sign-out form of `Route::auth` (POST `/logout` with the page's `_token`) answered `[ERROR 3000]` after signing
  out; it signs out and redirects.
- `required_file` (and `required|file`) in a capability's rules makes the file input required on every channel: no
  file (web, REST) or no URL/path (MCP, command line) is an `invalid` error, "The <Field> field needs a file"; the
  JSON Schema of the capability lists the field as required, with the file description.
- `POST /mcp` without a token answered the session CSRF check (403, plain text) instead of the JSON-RPC 401 with
  `WWW-Authenticate: Bearer`: MCP posts skip the session check (they authenticate with personal access tokens only
  and never act as the session's user).
- `php caligrafer.php migrate --env <file>` printed the usage: `--env` works anywhere (`migrate --env X`,
  `migrate status --env X`, `migrate "" --env X`).
- The Vue SPA template builds to files that work from `public/<name>/dist/`: hash history (`createWebHashHistory`),
  `base: './'` in `vite.config.js`, no nested `#app` in `App.vue`, and `dist/` is no longer git-ignored (the
  production image does not run npm).

### Documentation
- The README, the wiki and the course are rewritten for 2.19, each topic both with Claude and by hand (one
  prescriptive way, every example run). Plugin 1.31.1: gotchas G136-G141, the AI recipes as capabilities, the
  webhook capability guard.

## [2.19.0] - 2026-11-08

### Performance
- Faster than Symfony 8.1 and Laravel 13 on all 16 measures of the benchmark (requests per second, median, slowest
  1%, memory; plain text, JSON, a database query, an HTML page), more than twice the requests per second of 2.18.2.
- Phug's debug mode only with `APP_DEBUG=true`; cached views run through Phug's Optimizer (`VIEW_OPTIMIZER=false`
  turns it off); views compiled in debug mode are kept apart.
- No 1 MB output buffer per request.
- Sessions only when used: a read (GET, HEAD) without a session cookie gets its session when it reads the CSRF
  token, writes to the session after the settings ran, or its page carries the token; POST, PUT and DELETE always
  start it. `SESSION_LAZY=false` starts every session at once, as before.
- Database rows fetched as arrays, no longer encoded to JSON and back.
- `.env` compiled to `.env.php` (`php caligrafer.php env cache | clear`; the deploy step does it), used while `.env`
  keeps the content it was compiled from.
- The class map is checked by one request at a time in production.

### Added
- Queries: `Database::table('books')` and `Book::query()` / `Book::where()` / `Book::find()`: bound values and
  validated names, `paginate`, `count`, `pluck`, `chunk`, `search`, `insert` / `update` / `delete` (a where required),
  eager loading `with()` / `withCount()` by naming convention or explicit settings, `Database::transaction()`, an N+1
  warning in development.
- Declarative routes: `Route::form` (a form's post calls a capability, then redirects, or goes back with
  `errors()`, `old()` and `flash()`), `Route::auth` (sign-in, sign-out, sign-up, throttled, passkeys, `identifier`,
  `signInAfterRegister`), `Route::webhook` (Stripe signatures), `Route::download` (CSV or JSON), `Route::redirect`,
  and the `file` rule of capabilities.
- Validation rules `exists,<table>.<column>`, `unique,<table>.<column>` and `current_year` in `min_numeric` /
  `max_numeric`.

### Changed
- `Model` provides `find()` through `__call` (an instance: as before) and `__callStatic` (`Book::find()`: the query
  builder) (G122).
- The sign-in page uses `Route::auth`'s identifier.

### Fixed
- An empty argument in a rule (`max_numeric,`) no longer stops the capability schema.

### Documentation
- The wiki pages Queries, Declarative routes and the 2.19.0 release notes; every page revisited; the elegance
  benchmark (the same app in Caligrafy, Laravel and Symfony).
- Claude Code plugin 1.31.0: skills build with declarative routes and queries instead of controllers, the reviewer
  checks them, API reference and gotchas G122-G135.

## [2.18.2] - 2026-11-07

### Fixed
- `php caligrafer.php doctor` checks every framework table (tokens, audit log, job runs, jobs, cache, migrations):
  a project updated to 2.18 without `platform install` is told so. `quickstart` and the `platform install` help name
  the tables 2.18.0 added.

### Documentation
- Every wiki page and the Claude Code plugin read again against 2.17.0 and 2.18.0: the class map replaces "every file
  is loaded on each request" (Getting started, Controllers, Models, Testing Caligrafy, `ARCHITECTURE.md`), `tests/`
  holds the app's tests (`CONTRIBUTING.md`, `phpunit.xml.dist`), migrations wherever a table changes (Models,
  Relationships, Authentication, Security and upgrading, How Caligrafy works, Quickstart with Claude), the new commands
  and tables in every list, the behavior changes of 2.17 and 2.18 in Security and upgrading, Home's features and links.
- Claude Code plugin 1.30.2: the `db` skill writes and runs migrations; `update` installs the new tables, fixes
  `tests/.gitignore` and checks tests and migrations; `ship` and `idea` check tests, migrations and background jobs;
  `marketplace` reads migrations and jobs; the `caligrafy-reviewer` agent checks the 2.18 rules; the skill's workflow,
  the API reference (autoloading, capability discovery, the CLI table) and gotchas G19 and G68 are current.

## [2.18.1] - 2026-11-07

### Documentation
- The banner image is only on the README and the wiki's Home: removed from the other wiki pages and from the Claude
  Code plugin's copies of them (plugin 1.30.1).

## [2.18.0] - 2026-11-07

### Added
- Migrations (`Caligrafy\Migrations`): each database change in a numbered SQL file of `application/migrations`,
  run once, in order, recorded in `caligrafy_migrations`; `php caligrafer.php migrate [status | make <name>]`. The
  deploy step runs the pending ones after the `create_db.sql` scripts; `doctor` reports pending ones and ones edited
  after they ran. Statements are split outside quotes and comments.
- Background jobs (`Caligrafy\Queue`): `Queue::push('<capability>', $input, ['delay', 'attempts', 'timeout', 'queue'])`
  runs a capability later, each run in its own process, stopped at its timeout, tried again after 30 s, 1 min, 2 min...
  (an input the capability refuses fails at once). Workers: the framework's capability `queue_work` in
  `application/config/schedule.php` (the scheduler, Caligrafy Cloud), or `php caligrafer.php queue work`;
  `queue status | list | failed | retry`. Table `caligrafy_jobs`.
- The cache (`Caligrafy\Cache`): `remember`, `get`, `put`, `forever`, `forget`, `flush`. `CACHE_STORE`: `database` by
  default with a database (the `caligrafy_cache` table, shared by the web server, the queue, scheduled jobs and every
  server), `file` without one, or `off`. `php caligrafer.php cache clear`.
- Testing an app: `Caligrafy\Testing\TestCase` (`call()` as the command line, a guest or a user,
  `assertCapabilityFails()`, `render()`, `fake()`, `assertQueued()`), each test in a rolled-back transaction;
  `php caligrafer.php test [<filter>]` and `test make <Name>`; `DB_TEST_DATABASE` or `.env.testing`.
- Dependency injection (`Caligrafy\Container`): capabilities are built by the container, so their constructors get what
  they use; `bind`, `singleton`, `instance`, `make`, bindings in `application/config/container.php`.
- `Database::connection()`; `Scheduler::spawn()` (the child process of a scheduled job and of a background job).
- `.env.example`: `DB_TEST_DATABASE`, `CACHE_STORE`. `platform install` and the deploy step create `caligrafy_jobs`,
  `caligrafy_cache` and `caligrafy_migrations`.

### Changed
- Classes load when they are used (`Caligrafy\ClassMap`): the first unknown class of a request no longer loads every
  PHP file of the framework and of `application/`. A map of the classes, kept in a private folder of the temporary
  directory, loads each one when it is first used; a file that does more than declare classes (functions, code) is
  loaded at start, as before. Capabilities are found from the files that mention `#[Capability`. A class added while the
  app runs is found at once in development, within a minute with `APP_ENV=production`. `CLASS_MAP=false` in `.env`
  restores the old loader.
- `tests/` is no longer loaded on every request (it holds the app's tests), and its `.gitignore` keeps them in git.
- Claude Code plugin 1.30.0: the Migrations, Background jobs, Caching, Testing your app and Dependency injection pages,
  the API reference, gotchas G118-G121; the `new-capability`, `new-resource`, `idea` and `deploy` skills use
  migrations, jobs, the cache and tests.

## [2.17.0] - 2026-11-07

### Changed
- Compiled Pug views are kept between requests (`View::cache()`): a view is compiled to PHP once, and again only when
  it or a file it includes or extends is saved, so edits still show at once. Pages are the same, 3 to 8 times faster
  (the HTML page of the benchmark went from about 124 to about 941 requests per second). The folder is private to the
  project and the system user (`<tmp>/caligrafy-views-<project>-<user>`, `0700`); without one that can be written,
  views are compiled as before. `VIEW_CACHE=false` turns it off; a project that sets Phug's `cache_dir` keeps its own.
- After copying files that keep their old modification times to a server without Docker (`rsync -a`, `cp -p`), empty
  `/tmp/caligrafy-views-*` (G117).
- Claude Code plugin 1.29.0: gotcha G117, the API reference of `View`, the Views and Putting it online pages.

## [2.16.5] - 2026-11-06

### Fixed
- `/caligrafy-quill:ship` on a Mac with an SSH key on GitHub: `github.sh push` stopped with no message and pushed
  nothing, and `github.sh publish` took an existing repository for a missing one and asked for the GitHub CLI. An empty
  array is an unbound variable for macOS's bash 3.2 under `set -u` (G116). QA now runs both offline with the computer's
  own `/bin/bash`.

## [2.16.4] - 2026-11-05

### Documentation
- Stripe, going live (wiki 9.2): with `APP_ENV=production` payments use the live keys; an app that added a Stripe
  webhook creates its endpoint again in live mode with the live signing secret, and disables the test-mode endpoint left
  on the production address (its events fail with `400`, and Stripe emails about them). The framework's own checkout
  methods use no webhook.
- Claude Code plugin 1.28.4: gotcha G115, and the `deploy` skill checks the payment keys and webhooks before going live.

## [2.16.3] - 2026-11-03

### Changed
- `php caligrafer.php cloud apps`, `cloud status` and the end of `cloud deploy` print `[password protected]` for an app
  its owner locked with a password on Caligrafy Cloud (never the password itself). Until an app is offered to the
  marketplace, its Cloud page can lock it: every visitor types the password once per browser, its owner included.
- Claude Code plugin 1.28.3: the `ship` and `deploy` skills take a locked app (a `302` to its password page, a `401` to
  an API call) for an app online, not a failed deploy; the `marketplace` skill says offering removes the password;
  gotcha G114.

## [2.16.2] - 2026-11-03

### Fixed
- The social card (`modules/metadata.pug`): every page built on `site.pug` prints it, from `metadata()` in
  `application/config/config.php` (new projects have a commented example) or the `'metadata'` of its view; `og:type`
  is `website` (was `og:product`, refused by Facebook), the image is 1200x630 with its alt text, `og:url` is the page's
  own address (`site_url`, else `APP_URL`), and nothing is printed before a `title` (no placeholder values).

### Changed
- The footer of `site.pug` always starts one screen down: just below the screen on a short page, right after the
  content on a long one (`body.cq-site` is a flex column in `layout.css`, `--cq-footer-h` set by a script).
- Caligrafy's black logo for `favicon.ico`, the Apple touch icons and the manifest's icons; `site.pug` and
  `touchicons.pug` link them with their modification time, so browsers fetch a replaced icon again.
- Claude Code plugin 1.28.2: `/caligrafy-quill:idea` writes the card of every new app; gotchas G112 and G113.

## [2.16.1] - 2026-11-03

### Changed
- The wiki shows a worked example of a scheduled job (Scheduled jobs) and of a notification sent by a job
  (Notifications); the course has lesson 15, Reminders.
- Claude Code plugin 1.28.1: the `idea` skill plans and builds jobs and alerts when an idea asks for them, the `design`
  skill checks the emails in the new style, the `deploy` skill sets up notifications on an own server; gotcha G111.

## [2.16.0] - 2026-11-03

### Changed
- An address no route declares answers 404 Page Not Found (`[ERROR 1005]`, or JSON to an API call) instead of running
  the first route of `web.php` with status 200; an address declared only with other methods answers 405 with an `Allow`
  header; `/` is matched by its own route, and `HEAD` is answered like `GET`. Projects whose links relied on landing on
  the first route need a route for them.
- Claude Code plugin 1.28.0: gotcha G4, the Routing and Helpers pages.

## [2.15.0] - 2026-11-03

### Added
- Notifications: `Caligrafy\Notify::user($userId, $message)` sends a message (title, body, url, tag, urgency) to every
  channel the user turned on: web push to each browser they subscribed (`minishlink/web-push` 11, VAPID keys from
  `php caligrafer.php notify keys`, kept 24 hours for a device that is off, an expired subscription deleted at its first
  404 or 410) and email to a confirmed address (a link valid 24 hours, one-click unsubscribe with the
  `List-Unsubscribe` headers, in the colors and font of the app's design style). At most `NOTIFY_RATE_PER_HOUR` (30)
  per user and hour; every call logged without its body (`caligrafy_notifications`, 30 days); `Notify::forget()`.
- Built-in capabilities in every project (`framework/services/Notify`): `notify_status`, `notify_subscribe`,
  `notify_unsubscribe`, `notify_email_set`, `notify_email_confirm`, `notify_preferences`, `notify_test`, each on the
  signed-in user's own data and kept out of MCP (`#[Capability(mcp: false)]`, new).
- `public/js/notify.js` (`Notify.status()`, `enable()`, `disable()`, `test()`...), the `push` and `notificationclick`
  handlers of `public/sw.js`, `Route::notifications()` (the confirm and unsubscribe pages; `quickstart` adds it), the
  settings `NOTIFY_CHANNELS`, `NOTIFY_RATE_PER_HOUR`, `VAPID_*`, `APP_URL` and `MAIL_FROM`, `Mail::sendMail()` headers,
  `php caligrafer.php notify keys | test | status`, and a doctor check of the keys.
- Claude Code plugin 1.27.0: the Notifications page, the API reference, gotchas G109-G110, and the `new-capability`
  skill writes alerts.

## [2.14.1] - 2026-11-03

### Added
- `php caligrafer.php cloud jobs <name> [run <job> | history <job> [--limit 20]]` and `cloud logs <name> --jobs`:
  Caligrafy Cloud now runs the scheduled jobs of its apps (read from the deploy step's `caligrafy-schedule` line at
  each deploy, one run at a time, while the app is online; 20 jobs per app; an email after 10 failures in a row).
- Claude Code plugin 1.26.1: the Scheduled jobs page and the release notes say how Cloud runs them; the `ship` skill
  checks the jobs of a shipped app.

## [2.14.0] - 2026-11-03

### Added
- Scheduled jobs: an app declares in `application/config/schedule.php` which capabilities run on a timer (`every`
  1m to 1d, or a five-field `cron` in a timezone), with fixed inputs, a timeout (55 s by default, at most 300) and
  `enabled`. `Caligrafy\Scheduler` validates the file and runs the jobs: one run at a time per job (a database lock),
  in a child process stopped at its timeout, with full access as `Context::system('schedule', <job>)` (the audit log
  records `schedule` and the job's name), each run recorded in the new framework table `caligrafy_job_runs` (kept
  30 days).
- `php caligrafer.php schedule list [--json] | run <job> | due | work | history [<job>]`: `--json` is the versioned
  listing Caligrafy Cloud reads at deploy time. Exit codes 0 ok, 1 failed or timed out, 2 invalid file, 3 skipped.
- `doctor` checks the schedule file; `platform install` and the deploy step create `caligrafy_job_runs`. The deploy
  step prints the jobs on one line of the start-up log (`caligrafy-schedule {...}`, or `caligrafy-schedule-invalid`),
  where Caligrafy Cloud reads them; `schedule run <job> --if-due` runs a job only when it is due this minute in its
  timezone.
- `docker-compose.yaml` has a `scheduler` service running `schedule work`, started by `php caligrafer.php server start`.
- Claude Code plugin 1.26.0: the Scheduled jobs page, the API reference, gotcha G108 (a job has nobody signed in),
  the `new-capability` skill writes jobs and the `deploy` skill runs them on an own server.

## [2.13.6] - 2026-11-03

### Changed
- `qa/run.sh` takes the first free ports from 8090 and 3316 when `QA_WEB_PORT` / `QA_DB_PORT` are not set: other
  projects' servers often hold those, and the suite failed to start. The wiki (Testing Caligrafy, Security and
  upgrading) and gotcha G38 of the Claude Code plugin 1.25.6 say so.
- The release steps of the contributor skill: the maintainer's apps on Caligrafy Cloud are deployed with
  `php caligrafer.php cloud deploy <name>`, never through Coolify, and the four-part report closes every update.

## [2.13.5] - 2026-11-02

### Fixed
- `php caligrafer.php deploy` stopped at an `ALTER TABLE ... ADD COLUMN` (or `ADD INDEX`, `ADD CONSTRAINT`) of a
  changed `create_db.sql` the second time the script ran ("Duplicate column"): the script was never recorded and every
  later deploy failed on it, although gotcha G68 recommended those lines. A statement that only adds what is already
  there (MySQL/MariaDB errors 1060, 1061, 1826) is now skipped (`Deployer::alreadyApplied()`), so a new column ships
  with the code.
- `redirect()` put the site's own address in front of a full address (`https://...`): it is now used as it is.
- `doctor` warns about two Pug mistakes that only show when the page renders: a dollar sign before digits, which
  vanishes (`$40` shows nothing; write `&#36;40`), and code on the line of a one-line `- if` / `- foreach`, which does
  not compile (`Doctor::pugPitfalls()`; gotchas G105, G106).

### Changed
- Claude Code plugin 1.25.5: gotchas G105-G107 and G68, the UI guidelines (buttons of a page's own colored section),
  and the pages of caligrafy.com it describes: Listings, Earnings, Tasks and Library, the three ways to get an app on
  Explore (the prompt, the code, managed for you) and Submit your idea.

## [2.13.4] - 2026-11-02

### Changed
- Caligrafy accounts (caligrafy.com and Caligrafy Cloud) sign in with their email once it is confirmed, and have a
  *Forgot your password?* with a 6-digit emailed code. The `ship` skill tells the user what to do at each step (the
  confirmation email, the code), the wiki (Putting it online, Quickstart with Claude) says it, and
  `php caligrafer.php cloud account` asks to open the confirmation link.

## [2.13.3] - 2026-11-02

### Fixed
- A form with no `method` leaks its fields into the address when its script does not run: the browser sends it as a
  GET (found on Caligrafy Cloud's sign-up page, whose view did not load its script: the email and the password went
  into the address). The Tasks example's form and the template payment page now have `method='POST'`, and `doctor`
  warns about a Pug form of `application/views` with an email or a password field and no method
  (`Doctor::formsWithoutMethod()`). The UI guidelines, the `idea`, `new-capability` and `new-resource` skills and the
  new gotcha G104 say it: every form has a method, the page loads its script, field errors go under their own field.
- `doctor` in the framework's own repository said its Claude Code plugin was fine without comparing versions; it now
  warns when Claude Code runs an older copy than `claude-plugin/`, as it does in projects. The tooling tests fail in
  that case, so a release cannot go out with a stale copy.

## [2.13.2] - 2026-11-02

### Changed
- Caligrafy's new positioning: **Describe it. Approve it. Ship it.** Your idea turned into a complete app. The README,
  the wiki (Home, sidebar, Quickstart with Claude, Quickstart, How Caligrafy works, Claude Code Plugin), the plugin,
  `CLAUDE.md`, `AGENTS.md` and `composer.json` lead with the three steps; build once, experience everywhere is what
  makes the app complete. A new black banner, `public/images/resources/describe-approve-ship-banner.png`.
- The `idea` skill follows the three steps. When the user asks to review before shipping ("stop before putting it
  online"), it builds and checks the app, reports what changed since the plan, and waits; later changes get a plan
  first too. Its pages show each refused input's message under its own field, and its check signs in after signing
  up. Found by running the new course (caligrafy-quill-course) with Claude.

### Fixed
- `php caligrafer.php plugin` in the framework's own repository renamed its plugin source from `caligrafy` (the name
  people add from GitHub) to a project name; it now keeps it, as `doctor` already did (`PluginSource::isFramework()`).
- `php caligrafer.php plugin` took another project's install for this one when that project's source name started with
  this one's (`caligrafy` and `caligrafy-myapp-1a2b` in `claude plugin list`), and did not install the plugin. It now
  reads Claude Code's install record for this folder.
- During a QA run (`qa/run.sh`, which generates an `application/` in the framework's folder), the framework's own
  repository was taken for a project: `doctor` warned about its shared plugin name and `plugin` could rename it.

## [2.13.1] - 2026-11-02

### Fixed
- A new skill of the Claude Code plugin was missing after an update (`/caligrafy-quill:marketplace` after updating to
  2.13.0): Claude Code runs a copy of the plugin made at install time, which `update` did not refresh. `update` and
  `php caligrafer.php plugin` now refresh it when the project's plugin is newer (`claude plugin marketplace update`,
  `claude plugin update ... --scope project`), and `doctor` warns while it is older. In Docker, the update says to run
  `php caligrafer.php plugin` on the computer.

## [2.13.0] - 2026-11-01

### Added
- Selling an app on the marketplace of caligrafy.com (Caligrafy sells a prompt that recreates it; its maker earns 25%
  of every sale). The Claude Code plugin's new `marketplace` skill reads the whole code, runs the app as a separate
  Docker project and walks it to screenshot its key features (5 at most, a fake camera for camera apps), writes the
  title, the description, the prompt and an estimated price window in `.caligrafy/marketplace.json` (ignored by git),
  then submits it. See [Release notes: 2.13.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.13.0).
- `php caligrafer.php cloud submit <name> [--file <path>] [--no-deploy] [--no-wait]`: sends the listing and its
  screenshots to Caligrafy Cloud with the project's sign-in, then deploys the app.

## [2.12.4] - 2026-11-01

### Fixed
- The top bar of the masters (`site.pug`, and `layout.pug` under `layoutblocks.pug`) showed Sign in and Create account
  to a signed-in person on every page whose controller did not pass `$authorized` (the payment page, for example). It
  now asks the session (`$authorized || authorized()`).

## [2.12.3] - 2026-11-01

### Fixed
- `php caligrafer.php cloud account` and `cloud apps` show an unlimited Caligrafy Cloud account as such: "unlimited (no
  credits needed...)" instead of a credit count, and "(no end)" instead of the time left of its apps, which Caligrafy
  Cloud never pauses (`no_end` and `unlimited` in its answers).

## [2.12.2] - 2026-11-01

### Added
- For contributors to the framework: the Claude Code skill `caligrafy-contributor`
  (`.claude/skills/caligrafy-contributor/SKILL.md`) carries every framework change to the plugin, the README, the wiki,
  the version, the changelog, QA and the release, then asks whether to review the course and whether to update and
  deploy caligrafy.com and Caligrafy Cloud.

### Fixed
- Contributor files stay out of projects: release archives leave out the skill and `.gitattributes`
  (`export-ignore`), `php caligrafer.php update` never adds them, and `php caligrafer.php initialize` deletes the
  skill from a clone.

## [2.12.1] - 2026-11-01

### Fixed
- The last pages outside Caligrafy's look:
  - `public/offline.html` (shown by the service worker when a page cannot load): the top bar with the Caligrafy mark,
    one message and a black action, still self-contained.
  - `modules/messaging.pug` and `modules/social.pug` (pages of the older masters): the message is an `.alert` /
    `.cq-alert` that `look.css` styles; the share links are plain links (they needed an icon font no page loads).
- The bot template (`php caligrafer.php app <name>` of type bot) no longer calls `__bots__/<app>/undefined`, which
  answered 500, when the address has no `?botSkillId=`: it says so at once.

## [2.12.0] - 2026-11-01

### Added
- A list and its detail, side by side, in Caligrafy's look (`public/css/layout.css`): `.cq-split > section.cq-split-main
  + aside.cq-split-detail`, right after a title band. One column on phones; from tablets up the list on the left and
  the item opened (or a form, one field a line) in an ash panel on the right that stays in view while the list
  scrolls. Scoped to pages of `site.pug` (`body.cq-site`): the older `.cq-split` of `tokens.css` is unchanged.

## [2.11.0] - 2026-11-01

### Added
- Caligrafy's new look on every out-of-the-box page: sign-in, create account, the account and admin pages, the
  welcome page, the AI page, the Tasks example, payment and checkout, the bot, the Vue app and single page application
  templates. A white canvas, a light top bar with the Caligrafy mark, one message per screen, full-width bands, rows
  and tiles, Caligrafy black for the main action, Inter type, no cards; responsive on phones, tablets and desktops.
  - `public/css/layout.css` (the structure), `public/css/look.css` (the look, as `--design-*` tokens feeding the `--cq-*`
    tokens) and `public/css/camera.css` (full-screen camera pages).
  - The master `default/master/site.pug` (blocks `title`, `brand`, `nav_links`, `content`, `footer`,
    `additional_css`, `additional_scripts`; a Menu button on phones; versioned stylesheet links) and the modules
    `notice.pug` (messages above a form) and `share.pug`. `layoutblocks.pug` stays and loads `look.css` too.
  - The Caligrafy mark, `public/images/resources/caligrafy-mark.svg`, and the documentation banner
    `public/images/resources/build-once-banner.png`, now black.
- Face detection, machine learning and the face sign-in recipe fill the screen with the camera: the title and status
  at the top, the actions at the bottom, an oval guide and a sheet for forms over the camera.
- The Stripe Payment Element of `default/pages/payment` takes the page's colors, corners and type from its tokens.

### Fixed
- `Payment::createCheckout()` failed on Stripe accounts with Managed Payments (the default for new accounts) when the
  product had no tax code. Without `tax_code` it now creates a classic Checkout (`managed_payments` off); with one,
  Managed Payments stays on; `managed_payments` in `$payment_intent_data` decides either way.
- The single page application template (`framework/librairies/app`) imports `layout.css` and `look.css` and its
  components use the new look (top bar with the mark, title band, tiles).

## [2.10.2] - 2026-11-01

### Fixed
- A new project opened in Claude Code before `quickstart` (the Quickstart with Claude: clone, `claude`, then
  `/caligrafy-quill:idea`) registers the shared `caligrafy` source from its folder. Once `quickstart` gives the project
  its own name, `php caligrafer.php plugin` (which `quickstart` runs) now removes that `caligrafy` registration when it
  points to this folder, so the next clone registers its own instead of loading this one's plugin.

## [2.10.1] - 2026-11-01

### Fixed
- Each project's Claude Code plugin source gets the project's own name (`Caligrafy\PluginSource`). Every project
  named it `caligrafy`, and Claude Code keeps one source per name for the whole computer: the first project opened
  won, every other project got that project's plugin (an older one, without the newer skills such as `design`), and
  none got any once that folder was deleted. `initialize` renames it to `caligrafy-<folder>-<4 hex>` in
  `.claude/settings.json` and `.claude-plugin/marketplace.json`; `update` does it for existing projects (and no longer
  compares those two files with the release's).

### Added
- `php caligrafer.php plugin`: gives the project's plugin source its own name, registers it with Claude Code
  (`claude plugin marketplace add`, replacing a registration from another folder) and installs the plugin for the
  project, when the `claude` command is on the computer. `.bin/caligrafer quickstart` does it after the quickstart,
  also for a project served by Docker (the container has no `claude`).
- `doctor` reports the plugin: a source still named `caligrafy`, or read from another folder, is a warning.

## [2.10.0] - 2026-11-01

`php caligrafer.php upgrade` checks for a newer release of Caligrafy Quill first.

### Changed
- `upgrade` asks GitHub for the latest release (`Updater::newerRelease()`). When it is newer than the project's, the
  dry run shows what moving to it changes and `upgrade --apply` applies it, exactly like `update` (the framework, the
  release's other files, `composer.json`, `.gitignore`, `.env`, then the `application/` templates). When the project
  runs the latest release, or with `--local`, or with `--force`/`--keep`, it compares `application/` with this
  release's templates as before. Offline, it says it could not check and compares the templates.
- `update` runs `upgrade --apply --local` at the end, so it never looks for another release.

## [2.9.2] - 2026-11-01

### Fixed
- `.bin/caligrafer` passes the arguments of a command as typed. It passed only the first word after the command,
  lowercased, then `off` when no local server was running, so `.bin/caligrafer design use linear.app` selected a
  style named `off`, and `cloud deploy <name>`, `token create <name>`, `call`, `service` and the other commands with
  more than one argument lost theirs. `initialize` and `server` still get the word that says Docker serves the project.
- `initialize` no longer deletes a `.git` folder without being told: it asks in a terminal and keeps it otherwise;
  `initialize --yes` deletes it (`quickstart` passes it, after its own question). `.bin/caligrafer` never runs
  `initialize` on the way to another command any more: in a folder without `application/` it says to run
  `quickstart`. Before, any launcher command run in a clone of the framework initialized it and deleted its history.

## [2.9.1] - 2026-11-01

### Fixed
- `php caligrafer.php update` no longer adds the release's documents (`LICENSE`, `README.md`, `CHANGELOG.md`...) to a
  project that does not have them: a project's documents are its own, and the framework's `LICENSE` must not license
  it. They are still replaced when the project has an untouched copy.

## [2.9.0] - 2026-11-01

A project updates in one command: `php caligrafer.php update` moves it to the latest release and applies everything
that can be applied safely, keeping what you changed.

### Added
- `php caligrafer.php update [<version>] [--check] [--yes] [--no-composer]` (`Caligrafy\Updater`): downloads the
  project's release (`Framework::VERSION`) and the target release (the latest by default) from GitHub, then replaces
  `framework/` and `claude-plugin/` whole (keeping `framework/librairies/app/.env`), compares every other file of the
  release with the project's release (an untouched copy is replaced, a missing one added, a changed one kept with the
  new version in `<file>.caligrafy-new`, a file the release did not change left as it is; the project's documents
  such as `README.md` are never overwritten when changed), merges `composer.json` (new packages and the versions the
  project did not change, then `composer update` for them), the new lines of `.gitignore` and the new settings of
  `.env` with their defaults, and runs `upgrade --apply` from the new release. Everything replaced is backed up first
  in `backups/caligrafy-update-<from>-to-<to>-<time>/`; uncommitted git changes stop it unless `--yes`; `--check`
  reports only. `.bin/caligrafer update` passes it through.
- Claude Code plugin 1.21.0: the `update` skill runs it and merges every `.caligrafy-new` file into the project's own
  version; gotcha G94.

## [2.8.0] - 2026-11-01

Design styles: every page can take another look with one setting, and Claude restyles the pages to match it.
Caligrafy's own look stays the default, and nothing changes until a style is selected.

### Added
- Design styles: another look for every page, picked with one setting, with Caligrafy's own look as the default.
  `framework/design/` holds every style (`DESIGN.md`: a design system description written for AI agents: colors,
  type, spacing, components), with `README.md` explaining the layout: `vendor/<vendor>/` for collections copied from
  elsewhere, starting with the 84 styles of [VoltAgent/awesome-design-md](https://github.com/VoltAgent/awesome-design-md)
  (`vendor/awesome-design-md/`, MIT, with its `LICENSE`, attribution, `SOURCE` commit and `sync.sh` refresh), and
  `local/` for styles added to Caligrafy itself, starting with `interstellar` (the Interstellar laboratory operations
  design system, its origin in `local/README.md`). A project adds its own in `application/design/<name>/DESIGN.md`.
  On a name clash the first wins: `application/design`, then `local`, then the vendors in alphabetical order.
- `DESIGN_STYLE` in `.env` (empty by default, in `.env.example` and `.env.docker`). When it names a style whose
  stylesheet `public/css/designs/<name>.css` exists, the layout (`layoutblocks.pug`) and the Vue app template
  (`framework/plugins/app/index.php`) link it after `styles.css`, Bootstrap, `theme.css` and `tokens.css` with the new
  `design_stylesheet()` helper (versioned by its modification time). An empty setting, an unknown name, a name that
  is not a style name (no paths) or a style without its stylesheet changes nothing: the Caligrafy look.
- `php caligrafer.php design list [--json] | show <name> | use <name> | off | status`: lists the styles, selects one
  or goes back to the Caligrafy look by writing `DESIGN_STYLE` in `.env` only (`Caligrafy\Designs`).
- `doctor` reports the selected style: `OK` for the Caligrafy look or a style with its stylesheet, `WARN` for an
  unknown name or a style whose stylesheet is not written yet, with the fix.
- Claude Code plugin: the `design` skill (`/caligrafy-quill:design [style | off]`) reads the selected `DESIGN.md`,
  writes the one override stylesheet (tokens, layout, sign-in pages, buttons, fields, every page component, phone
  width) without editing the Caligrafy stylesheets or the views, and checks the pages in a browser at desktop and
  phone width. Worked examples for a light and a dark style; gotchas G92-G93; the wiki page Design styles.

### Changed
- The production image (`Dockerfile.dockerignore`) leaves `framework/design/` out: only the stylesheet is used at
  run time. `layoutblocks.pug` has the stylesheet link: `php caligrafer.php upgrade` updates untouched copies.

## [2.7.1] - 2026-11-01

### Fixed
- A personal access token whose expiry falls in the hour repeated when the clocks fall back is no longer refused at
  once. Dates are stored in local time (`SERVER_TIMEZONE`), and that hour names two instants: in America/New_York, a
  token created at 05:21 UTC on 2026-11-01 to expire an hour later was stored as `01:21`, read back as the first
  01:21 (now) and refused. `Tokens::create()` now takes the expiry as a Unix time (`time() + 86400 * 90`) and stores
  it with `Tokens::expiresAt()`: an instant of the second occurrence becomes `02:00`, so a token never stops early,
  at worst an hour late, and a token that expired a minute ago stays expired. A local `'Y-m-d H:i:s'` string is still
  accepted and stored as given; stored tokens keep working unchanged. `php caligrafer.php token create --expires`
  uses it, and `Tokens::verify()` and `token list` share `Tokens::expired()`.

## [2.7.0] - 2026-11-01

From an idea to an application online without handing the work to anyone: pages work online as they do locally,
Caligrafy Cloud signs in with a browser approval, and Claude ships the project by itself.

### Added
- JSON calls from a page to a controller route are accepted with the session and its CSRF token in an
  `X-CSRF-Token` header (`Request::authorizeSessionRequest()`), as capabilities already were: the controller sees the
  signed-in user or a visitor, exactly as for a form post. Pages no longer need the shared API key, which Caligrafy
  Cloud turns off (`LEGACY_API_KEY=false`). The form CSRF check accepts the same header, so a JSON body sent without
  the JSON `Accept` header passes it too.
- `apiHeaders()` in `public/js/services/app.js`: the headers of a call from a client app to its own routes (`Accept` and
  `X-CSRF-Token`; axios sets `Content-Type` itself); `env.csrf` holds the token.
- `php caligrafer.php cloud login` is approved in the browser: it prints a code, opens
  `https://deploy.caligrafy.com/cli?code=...` (from a Docker server it prints the address), and collects the token
  once the owner approves it. No token to create or copy; `--token` and `--paste` keep the old ways, `--no-browser`
  only prints the address.
- `php caligrafer.php cloud key <name>`: the read-only deploy key of an app built from a private repository; `cloud
  create` also saves it in `.caligrafy/<name>-deploy-key.pub`, for `gh repo deploy-key add`.
- `doctor` warns about client app scripts that send the legacy API key.
- Claude Code plugin 1.19.0: the `ship` skill (commit, the GitHub repository over SSH, HTTPS or a browser approval, the
  Cloud sign-in, the deploy key, the deploy and the check, all without hand-offs) and an `idea` skill that asks once.

### Changed
- `[ERROR 1004] Access Denied` says why: no CSRF token and no bearer, a token that is unknown or lacks `*`, the legacy
  key turned off (`LEGACY_API_KEY=false`), the API not activated, or a key of another application. `Errors::raise()`
  takes an optional detail.
- `views/Client/app.pug` gives pages `apiKey` only while the legacy key is on (`null` with `LEGACY_API_KEY=false`).
- The face-login recipe, the bot template (`framework/plugins/bot`) and the documentation examples call their routes
  with `apiHeaders()` instead of the API key.
- Caligrafy Cloud: every new app is free for its first 30 minutes online, from its first deploy (the free minutes
  were once per account); a refused deploy says how many credits the account has.

## [2.6.0] - 2026-11-01

A quickstart from Composer, on the server you already have: MAMP, XAMPP, LAMP or Docker.

### Added
- `composer create-project caligrafy/caligrafy-quill <folder>` ends with `php caligrafer.php created` (Composer's
  `post-create-project-cmd`): it says where the project will be served and the one command to run next.
- `quickstart` on a local server (MAMP, XAMPP, LAMP; Windows too with `php caligrafer.php quickstart`): it gives the
  project its address and `APP_ROOT` from its place in the server's web folder, reads MAMP's own settings (its web
  folder and its Apache and MySQL ports), asks for the MySQL settings with the server's defaults (without a terminal to
  confirm them it skips the database, unless `--db-defaults`: it never writes settings no one confirmed), and creates the database, the framework tables and the example. If the settings it wrote do
  not work, it switches the database off again so that the pages still open. Options `--url <address>`, `--no-db` and
  `--db-defaults`.

### Changed
- `.bin/caligrafer quickstart` picks the server like `.bin/caligrafer server`: a running local Apache with PHP
  installed, else Docker; `--local` or `--docker` chooses.
- `.bin/caligrafer` recognizes the Apache of LAMP on Debian and Ubuntu (`apache2`) as a local server, not only `httpd`.

## [2.5.0] - 2026-11-01

Pages go through one controller, and only capabilities touch the data: the rules of a feature live once, whether a
page, REST, an AI agent or the command line uses it.

### Added
- `Route::page($route, $view, $options)`: a page rendered through the framework's page controller
  (`Caligrafy\PageController`). Every page gets `authorized`, the signed-in `user` (id, username, permissions) and its
  route variables, so no page shows a signed-in person as a visitor. Options: `signedIn` (visitors go to `/login`),
  `level` (people below it go to `/notAuthorized`), `data` (`['book' => 'get_book']`: capabilities called as the person
  who opens the page, with the route variables as inputs; a refusal sends a visitor to sign in, or answers 403 or 404)
  and `with` (fixed values).
- The user capabilities in `application/services/Users` (framework class names: `CaligrafyListUsers`...): `list_users`, `get_user`, `update_user` and `delete_user`
  (administrators: `CaligrafyUsers::ADMIN_LEVEL`, 1 by default, which `AdminController` uses too; never a password; an administrator cannot delete their own account) and
  `register_user` (the sign-up page and the command line only: refused over REST and MCP). AI agents and programs with
  an administrator's token can manage users, with the audit log.
- Error codes 1005 (page not found, 404) and 1006 (forbidden, 403).
- `Route::page` option `notFound`: a view shown, with a 404, when a data capability finds nothing.
- `upgrade` holds the user capabilities (status `held`, with the reason) when the project manages users its own way: a
  customized `AdminController`, or a class or capability of the same name in `application/`. The controllers that
  call them are held too; `--force` adds a file anyway.

### Security
- The user capabilities only answer the command line or a real person at `CaligrafyUsers::ADMIN_LEVEL`: never the
  shared legacy API key or a token without an owner. No one manages someone above their own level or grants more than
  it; an administrator cannot delete their own account; deleting a user revokes their tokens.
- A token whose owner no longer exists is refused (with `Auth::authentication` on), instead of keeping the rights of a
  token without an owner.
- `Route::page` only calls read-only capabilities (opening a page never changes data), takes route variables from the
  route pattern (they win over the query string, which never reaches the view), and reserves `authorized` and `user`.

### Changed
- The user administration pages (`AdminController`) and sign-up (`AuthController@register`) call those capabilities
  instead of the `User` model: the same pages and messages, one set of rules. Signing in and out stay in
  `AuthController`: they are the web session, which capabilities never touch.
- The welcome page (`/` in the template `web.php`) and the Tasks example of `quickstart --example` use `Route::page`.

## [2.4.0] - 2026-11-01

Putting an application online: a production image in every project, the database prepared at every start, and
Caligrafy Cloud.

### Added
- `Dockerfile` and `Dockerfile.dockerignore`: the production image of a project (PHP 8.5 and Apache, the extensions
  Caligrafy uses, `composer.lock` without development packages, the production `php.ini`). `.env` stays out of the
  image: give the container its `.env` as a file at `/var/www/html/.env`. `docker/Dockerfile` remains the development
  image of `docker-compose.yaml`.
- `php caligrafer.php deploy [--wait <seconds>]`: what the production image runs before Apache starts. It waits for the
  database, creates the framework tables, then runs every `application/models/<folder>/create_db.sql` that is new or
  changed since its last run, recorded in the new `caligrafy_scripts` table. A changed script that drops, deletes or
  truncates is never run again on its own; `seed.sql` is never run.
- `php caligrafer.php cloud <login | account | create | deploy | apps | status | logs | extend | credits | secret |
  delete | logout>`: puts the project online at `https://<name>.caligrafy.com` with Caligrafy Cloud
  (https://deploy.caligrafy.com): built from its GitHub repository, its own MySQL database, the first 30 minutes free,
  then credits (a week 2, a month 5, a year 50; 1 credit = 1 USD). The token is kept in `.caligrafy/cloud.json`, now
  ignored by git; `CALIGRAFY_CLOUD_TOKEN` and `CALIGRAFY_CLOUD_URL` override it.
- The production image keeps PHP sessions in `/var/lib/php/sessions`: a volume there keeps people signed in across
  deploys.

### Changed
- The page components of `public/css/tokens.css` take the look of the Caligrafy sign-in card: 600px cards with the
  soft shadow and no grey outline, light labels, the 150px primary button on the left, a white footer section, the
  page just under the header. No markup changes: copy `public/css/tokens.css` from the release.

### Fixed
- With `APP_DEBUG=true`, the SMTP conversation of the mailer was printed into the page or into the JSON answer of an
  API call; it now goes to the server log.

## [2.3.0] - 2026-10-31

What upgrading a real site (caligrafy.com, from a September 2025 project) and building the course on 2.2.0 uncovered.

### Added
- `php caligrafer.php call <capability> ['{"json"}' | key=value ...] [--token <token>] [--guest]`: calls a capability
  from the command line and prints the same JSON as REST. The quickest way to try a capability before any route.
- `upgrade --keep <application/path>`: keeps a page that is an untouched copy of an older template (a default page that
  became a page of your site, such as the home page). The new version is written next to it, and the choice is
  remembered in the manifest.
- `upgrade` also reports, without changing anything: the settings of `framework/settings/.env.example` missing from
  `.env` and the obsolete ones (`CRYPTO_PAY_KEY`), and the views and client apps that still use Bootstrap 3 classes
  (`col-xs-`, `pull-left`, `btn-default`...) that break under Bootstrap 5.
- `doctor` warns about `APP_DEBUG=true` on a site served over HTTPS, and about a `CRYPTO_PAY_KEY` left from Coinbase
  Commerce (closed on March 31, 2026).
- `DB_TIMEZONE`: by default the MySQL session now uses `SERVER_TIMEZONE`, like PHP, so `NOW()`, `CURRENT_TIMESTAMP`
  columns and the audit log share one clock; `DB_TIMEZONE=server` keeps the database server's own time zone.

### Fixed
- The payment page template (`views/default/pages/payment.pug`): the Stripe Payment Element overflowed its box and
  `stripe_global.css` styled the whole page. The payment and checkout templates are now built with the page components.
- Several `.cq-card` in one `.cq-page` now stack (they sat side by side on wide screens).
- The Vue app template (`php caligrafer.php app`) calls the capabilities through the generated JS client instead of
  axios with the shared API key, which `LEGACY_API_KEY=false` refuses.
- `php caligrafer.php create` no longer says the packages were installed when `npm` is missing (the Docker `dev-box`
  has no Node.js) or failed: it says how to install them.
- The bot template says when the assistant cannot be reached, or that the address needs `?botSkillId=`.
- The face detection template shows its buttons at once (enabled when the models are loaded) and says when the camera
  is blocked.
- Claude Code plugin 1.14.0.

### Upgrading
`php caligrafer.php upgrade` (read its new reports), then `upgrade --apply`. Copy `public/css/tokens.css` and
`public/js/services/detect.js` from the release: they are not templates. Existing rows keep their times; new rows use
`SERVER_TIMEZONE` unless you set `DB_TIMEZONE=server`.

## [2.2.0] - 2026-10-31

One command from a fresh project to a working build-once application, and documentation that starts there.

### Added
- `.bin/caligrafer quickstart [--example]`, with Docker only (Composer runs in Docker): installs the libraries, starts
  the server, initializes the project (it asks before deleting `.git`), points `.env` at the Docker database, creates
  the database and the framework tables, and adds `Route::capabilities('/capabilities')` and `Route::mcp('/mcp')`.
  `php caligrafer.php quickstart` does the same on your own server. Every step can be run again.
- `--example`: the Tasks example (`framework/settings/examples/tasks`): five capabilities, a page built with the page
  components, REST routes, the table and the JS client; a web page, `curl` and an AI agent use the same tasks.
- `php caligrafer.php sql <file.sql>` runs a SQL script on the database of `.env`.
- Documentation: a new README and wiki Home, a sidebar organized by what you want to do, and three Start here pages
  (Quickstart, How Caligrafy works, Your first capability); a Caligrafer page with every command in one place;
  every core page says where it fits.
- Claude Code plugin 1.13.0.

## [2.1.3] - 2026-10-31

### Added
- Page components in `public/css/tokens.css`: the look of the sign-in and face login screens for every new page
  (`.cq-page`, `.cq-card`, `.cq-card-head`, `.cq-form`, `.cq-fields`, `.cq-submit`, `.cq-error`, `.cq-tabs`,
  `.cq-count`, `.cq-list`, `.cq-item`, `.cq-actions`, `.cq-empty`, `.cq-foot`...), linked by the default layout, the
  Vue app template and the SPA template.
- Claude Code plugin 1.12.0: UI guidelines that every scaffolding skill, the reviewer and `AGENTS.md` follow, so new
  pages are built with the page components and checked at desktop and phone width; gotchas G59 and G60.

## [2.1.2] - 2026-10-31

### Changed
- The classes that expose capabilities to the world moved to `framework/interfaces/`: `Rest`, `Client` and
  `Mcp\Server`, `Mcp\Http`. `framework/core/` keeps the framework and what a capability is. Class names, routes
  and projects do not change.
- A framework copied over 2.1.0 or 2.1.1 keeps old copies in `framework/core/`: they are never loaded, and
  `php caligrafer.php doctor` asks to delete them.
- `ARCHITECTURE.md` describes where the code lives.

## [2.1.1] - 2026-10-31

### Fixed
- New projects get an empty `application/services/` folder, where capabilities live, as the documentation says.
  `php caligrafer.php upgrade --apply` adds it to existing projects.

## [2.1.0] - 2026-10-31

Build once, experience everywhere: a feature written once as a capability is served to web pages, Vue apps, REST and
AI agents. Additive: existing projects keep working unchanged. The
[Release notes: 2.1.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.1.0) have the upgrade checklist.

### Added
- Services and capabilities: `Caligrafy\Service` (GUMP rules and filters, `handle()` returns data), `ServiceError`
  (422, 401, 403, 404, 409 with field messages), the `#[Capability]` attribute (scope, permission level, read-only,
  destructive, public) and the `Capabilities` registry (JSON Schema from the rules, permission, validation and audit
  on every call).
- Channels: `Controller::call()` for Pug pages, `Route::capability()` and `Route::capabilities()` for REST (the session
  writes need `X-CSRF-Token`), MCP for AI agents with `Route::mcp()` (Streamable HTTP) and `php caligrafer.php mcp`
  (stdio), and a generated JS client (`php caligrafer.php client`).
- Personal access tokens (`Caligrafy\Tokens`): scoped, expiring, revocable, stored as SHA-256 hashes;
  `php caligrafer.php token create|list|revoke`. `LEGACY_API_KEY=false` turns the shared API key off.
- Audit log (`Caligrafy\Audit`, `php caligrafer.php audit`), framework tables (`php caligrafer.php platform install`).
- `php caligrafer.php service <Name>` and `capabilities [--json|--markdown]`.
- Design tokens (`public/css/tokens.css`, `tokens.json`) shared by the layout, the Vue app and the SPA templates.
- `ARCHITECTURE.md` and `AGENTS.md`; Claude Code plugin 1.11.0 with `/caligrafy-quill:new-capability`.

### Changed
- `Database::execute()` connects on first use, so closure and capability routes can use models.
- Requests with an `Authorization: Bearer` header skip the form CSRF check; JSON controller routes also accept
  personal access tokens with the scope `*`.
- `doctor` reports the framework tables and whether the legacy API key is allowed.

### Security
- The legacy API key is readable by anyone who opens an app page: move clients to tokens and the session, then set
  `LEGACY_API_KEY=false`.

## [2.0.0] - 2026-10-30

The first tagged release. Everything before it was only available as `dev-master`. The
[Release notes: October 2026](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-October-2026) explain
every change, what can break and the upgrade checklist for existing projects.

### Breaking changes
- PHP 8.3 or higher, and stable Composer releases instead of `dev-master` branches (stripe-php 22, Phug 2,
  PHPMailer 7, phpdotenv 5.8, GUMP 2.3, SimpleImage 4.4, groq-php 1.4).
- Errors return real HTTP status codes and JSON errors for API requests.
- Bootstrap 5 without jQuery (the Caligrafy look is kept), Vue 3.5, a Vite 8 + Pinia SPA template instead of Vuex.
- OpenAI on current models with images from `gpt-image-2` (base64), `Assistant` on the Responses API, the Stripe
  Payment Element and Checkout URLs, Coinbase Business, Watson on `WATSON_URL`, ML Core 2.0 and
  @vladmandic/face-api.
- The example controllers moved to the `Caligrafy\Controllers` namespace (short route names keep working).

### Added
- `php caligrafer.php upgrade` brings an existing project's `application/` up to date without overwriting
  customized files.
- `php caligrafer.php doctor` checks `.env`, the database and every configured service (read-only).
- `php caligrafer.php version` and `Caligrafy\Framework::VERSION`; `doctor` shows the version on its first line.
- Namespaced controllers in routes (`'Shop\OrderController@show'`).
- Install to home screen (web app manifest, optional service worker).
- Face login with liveness challenges and anti-replay checks, and passkeys (WebAuthn).
- Open model agents on Groq, Together, Fireworks, OpenRouter or Ollama (`AGENT_PROVIDER`).
- The `caligrafy-quill` Claude Code plugin in `claude-plugin/`.
- PHPUnit unit tests (`qa/unit`) and the Docker QA suite (`qa/run.sh`).

### Security
- `.env` and the server files are no longer served, uploads are limited to an allow-list of extensions, and
  scripts never run from `public/uploads`.

[2.3.0]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.3.0
[2.2.0]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.2.0
[2.1.3]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.1.3
[2.1.2]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.1.2
[2.1.1]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.1.1
[2.1.0]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.1.0
[2.0.0]: https://github.com/caligrafy/caligrafy-quill/releases/tag/v2.0.0
