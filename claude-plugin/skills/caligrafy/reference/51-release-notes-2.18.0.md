# Release notes: 2.18.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.18.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.18.0** brings what large apps rely on: migrations, background jobs, a cache, tests for your app,
dependency injection, and classes loaded only when they are used.

## What is new

+ **[Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations):** each database change in a numbered
  SQL file of `application/migrations`, run once, in order, on every database. `php caligrafer.php migrate`
  (`status`, `make <name>`); the deploy step runs them in production.
+ **[Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs):**
  `Queue::push('<capability>', $input)` runs a capability later, in its own process, with retries. The scheduler runs
  them (one line in `schedule.php`, on Caligrafy Cloud too), or `php caligrafer.php queue work`.
+ **[The cache](https://github.com/caligrafy/caligrafy-quill/wiki/Caching):** `Cache::remember('key', 600, fn () => ...)`,
  in the database by default (shared by every process and server), or files.
+ **[Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App):** `php caligrafer.php test`
  and `Caligrafy\Testing\TestCase`: call capabilities as a guest, a user or the command line, check refusals, render
  views, fake dependencies, each test rolled back.
+ **[Dependency injection](https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection):** a capability asks
  for what it uses in its constructor; bindings in `application/config/container.php`.
+ **Classes load when they are used.** The first unknown class of a request used to load every PHP file of the
  framework and of `application/`. Now a map of the classes, kept between requests, loads each one when it is first
  used. A file that does more than declare classes (it declares functions or runs code) is still loaded at start, as
  before. A class you add works at once while you develop; with `APP_ENV=production` the files are checked at most
  once a minute. `CLASS_MAP=false` in `.env` goes back to loading every file.
+ `tests/` is no longer loaded on every request: it holds your tests.

## Updating a project

```bash
php caligrafer.php update
php caligrafer.php platform install      # the new tables: caligrafy_jobs, caligrafy_cache, caligrafy_migrations
```

In production the deploy step creates the tables. Nothing else to change: an app that does not use the new features
works as before. Then, if you like:

+ put your next database change in a migration (`php caligrafer.php migrate make <name>`);
+ add `array('name' => 'queue', 'capability' => 'queue_work', 'every' => '1m', 'timeout' => 55)` to
  `application/config/schedule.php` before you push jobs;
+ write a first test: `php caligrafer.php test make <Name>`. A project made before 2.18.0 has a `tests/.gitignore`
  that ignores every file: `test make` rewrites it, so git keeps your tests.

When the update ran in Docker, run `php caligrafer.php plugin` on your computer in the project's folder, then restart
`claude` there.
