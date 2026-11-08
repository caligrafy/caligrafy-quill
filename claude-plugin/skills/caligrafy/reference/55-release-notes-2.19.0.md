# Release notes: 2.19.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.19.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.19.0** makes pages faster and removes most of the code a web app still needed besides its
capabilities: a query builder for lists, filters, paging and related rows, and one-line routes for forms, sign-in,
webhooks and downloads.

## What is new

+ **Faster, with less memory.** More than twice as many requests per second (the benchmark's HTML page: 1,350, now
  2,887), and each request uses about a third of the memory it did (details [below](#speed)).
+ **[Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries):** `Database::table('books')` (arrays) and
  `Book::query()`, `Book::where(...)`, `Book::find(12)` (models): filters, sorting, `paginate()`, `count()`,
  `chunk()`, `insert`/`update`/`delete`, every value bound and every name checked. `with('author', 'tags')` loads
  related rows in one query per relation, `withCount('books')` counts them, `Database::transaction()` saves all or
  nothing. While you develop, a query that runs 10 times in one request is logged as a probable N+1 query.
+ **[Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes):** one line of `web.php`
  each, no controller:
  + `Route::form('/books', 'create_book', ['success' => '/books/{id}'])`: a form's post calls a capability, then
    redirects; refused input goes back with `errors('title')`, `old('title')` and `flash('error')` for the view.
  + `Route::auth(['home' => '/books'])`: `/login`, `/logout`, `/register` and `/notAuthorized`, with failed sign-ins
    limited, a new session id at sign-in, and passkeys with `'passkeys' => true`. `'identifier' => 'email'` signs
    people in with their email (the sign-in page follows), and `'signInAfterRegister' => true` signs them in after
    sign-up.
  + `Route::redirect('/', '/books')`: an address that only sends visitors elsewhere.
  + `Route::webhook('/stripe/events', 'record_payment', ['verify' => 'stripe'])`: Stripe's events, signature checked.
  + `Route::download('/books.csv', 'list_books')`: a read-only capability's result as a CSV or JSON file.
  + The `file` rule of capabilities (`'cover' => 'required|file|extension,png;jpg|max_size,2mb'`): web pages and REST
    save the upload and give `handle()` its URL.
+ **[Validation](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation):** `exists,authors.id` (the value must be in that table), `unique,users.email`
  (it must not be there yet), and `max_numeric,current_year`.
+ **`php caligrafer.php env cache | clear`:** `.env` compiled to `.env.php`, which pages read without parsing `.env`.
  The deploy step does it in production.

Controllers keep working. New code rarely needs one: only for HTTP that none of these routes covers.

**More elegant than Laravel, measured.** The same app (sign-in, a filtered and paged list with authors, a form with
errors, a REST API with tokens, AI agent tools, a CSV export, a Stripe webhook, tests), built the way each framework's
docs teach and counted the same way:

| | Caligrafy 2.19 | Laravel 13.40 | Symfony 7.4 |
|---|---|---|---|
| Lines written (without tests) | **476** | 708 | 1,076 |
| Files written or changed | **24** | 40 | 39 |
| Concepts to learn | **25** | 42 | 42 |
| Files to touch to add one field everywhere | **4** | 7 | 6 |
| Security checklist (of 8) | **8** | 8 | 7 |

## Faster

+ **Phug's debug mode only with `APP_DEBUG=true`.** It gives the Pug line of an error and made every page about three
  times slower. Production (`APP_DEBUG=false`) renders without it.
+ **Cached views skip Phug's compiler** (the Optimizer). `VIEW_OPTIMIZER=false` turns it off. Views compiled in debug
  mode are kept apart (`caligrafy-views-debug-*`).
+ **No 1 MB output buffer per request**: the buffer grows with the page. Errors still get their status code.
+ **Sessions only when used.** A request with a session cookie, and every `POST`, `PUT` and `DELETE`, starts its
  session as before. A `GET` or `HEAD` without one (a first visit, an API client, an agent) gets a session only when it
  reads the CSRF token, writes the session after `config.php`, or prints a page that carries the token: no session
  file for the others. `SESSION_LAZY=false` starts every session, as before.
+ **Database rows fetched as arrays**, no longer encoded to JSON and back.
+ **`.env` compiled** to `.env.php` by the deploy step, used while `.env` is unchanged.

**The benchmark** (8 November 2026, on the 2.19.0 release commit, the same setup as before: Docker on an Apple M3
Pro, 2 CPUs and 1 GB for the app, PHP 8.5 with OPcache, Apache, MySQL 8.4, `wrk` with 32 connections, the median of 3
runs). Caligrafy 2.19 beats Symfony 8.1.11 and Laravel 13.40 on **all 16 measures**:

| Endpoint | Measure | Caligrafy 2.19 | Symfony 8.1.11 | Laravel 13.40 | Caligrafy 2.18.2 |
|---|---|---|---|---|---|
| Plain text | requests per second | **4,471** | 2,197 | 240 | 2,327 |
| | median (ms) | **2.9** | 6.1 | 83.5 | 6.6 |
| | slowest 1% (ms) | **85** | 92 | 732 | 646 |
| | memory (KB) | **545** | 578 | 808 | 1,549 |
| JSON | requests per second | **4,133** | 2,151 | 882 | 2,112 |
| | median (ms) | **3.1** | 6.7 | 47.5 | 6.9 |
| | slowest 1% (ms) | **86** | 92 | 158 | 708 |
| | memory (KB) | **492** | 585 | 774 | 1,549 |
| Database query | requests per second | **2,051** | 1,072 | 466 | 1,250 |
| | median (ms) | **7.9** | 13.0 | 90.2 | 13.3 |
| | slowest 1% (ms) | **76** | 95 | 188 | 192 |
| | memory (KB) | **570** | 772 | 1,005 | 1,597 |
| HTML page | requests per second | **2,887** | 1,789 | 324 | 1,350 |
| | median (ms) | **4.6** | 7.8 | 87.5 | 14.4 |
| | slowest 1% (ms) | **88** | 95 | 1,550 | 233 |
| | memory (KB) | **579** | 608 | 821 | 1,964 |

Memory is PHP's peak memory for one request.

## Updating a project

```bash
php caligrafer.php update
```

No new tables: `platform install` is not needed. In production the deploy step compiles `.env`; on your own server
without the production image, run `php caligrafer.php env cache` after each change to `.env`. `.gitignore` gets
`.env.php`: never commit it.

Then check:

+ **Sign-in flows.** Sign in, sign out, sign up, a page that needs someone signed in. Sign-in pages post, so their
  session starts as before; a page of yours that reads `$_SESSION['token']` directly on a first visit should call
  `session('token')` (or `csrf()`), which starts the session.
+ **Pages with forms.** Open each one in a private window (no session cookie yet) and submit it: the form must carry
  `=csrf()`.
+ **`Model::find()`.** `$book->find(1)` works as before, and `Book::find(1)` is now the query builder. A model that
  declares its own `find()` keeps it for instances, but `Book::find()` then fails ("Non-static method cannot be called
  statically"): use `Book::query()->find(1)`. `method_exists($model, 'find')` is now `false`.
+ **Errors in views** no longer show the Pug line unless `APP_DEBUG=true`: set it on your computer.

Two settings bring the old behavior back if something depends on it: `SESSION_LAZY=false` (a session on every
request) and `VIEW_OPTIMIZER=false` (every view through Phug's renderer).

To move existing code to the new routes, one at a time: a controller action that calls one capability and redirects
becomes `Route::form`; the authentication controller's routes become `Route::auth` (keep them if you use password
reset or face sign-in, which `Route::auth` does not have); a Stripe webhook controller becomes `Route::webhook`.

When the update ran in Docker, run `php caligrafer.php plugin` on your computer in the project's folder, then restart
`claude` there.
