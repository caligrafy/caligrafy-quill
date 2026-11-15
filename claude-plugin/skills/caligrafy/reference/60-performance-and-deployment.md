# Performance and deployment

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Performance-and-Deployment
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

# Performance and deployment

Since 2.26.0 the production image (`Dockerfile`) is tuned for the container it runs in, and a project can go further
with a response cache and, experimentally, an async server. Every setting below is a line of `.env` (or a variable of
the container's environment); nothing in the application's code changes.

## What the production image does at start

`docker/production/start.sh` runs before Apache:

1. **PHP processes**: `PHP_PROCESSES`. By default it follows the app: **2 per CPU** the container may use (read
   from its cgroup quota, at least 4), or **6 per CPU** (at least 16) when an AI key is set (`OPEN_AI_KEY`,
   `GROQ_API_KEY`, `ANTHROPIC_API_KEY`, `AGENT_API_KEY`, `TOGETHER_API_KEY`, `FIREWORKS_API_KEY`,
   `OPENROUTER_API_KEY`, `WATSON_API_KEY`). Apache's own default (up to 150) makes processes fight over a few CPUs;
   too few, and a page waits behind requests that wait on something else.
2. **Keep-alive off** (`KEEP_ALIVE=on` keeps it). With a fixed number of processes, an idle open connection would hold
   one. The proxy in front (Caligrafy Cloud, Traefik, a load balancer) keeps the visitors' connections open.
3. **The response cache** (`CACHE=off` turns it off) and **OPcache preloading** (`PRELOAD=off`).
4. `php caligrafer.php deploy` (framework tables, SQL scripts, migrations), then `php caligrafer.php optimize` (every
   Pug view compiled, the class map built) as the web server's user: the first visitor of a new container compiles
   nothing.

OPcache keeps the compiled code and stops checking the files for changes (`docker/production/php.ini`): a new version
is a new image.

## How many processes

A process answers one request at a time. A page that only computes is fastest with one process per CPU; a request
that waits (the database, an AI service, an outside API) holds its process while it waits.

| Your app | `PHP_PROCESSES` |
|---|---|
| Pages and the database | leave it empty: 2 per CPU, at least 4 (database waits last milliseconds) |
| Calls an AI service (an AI key is set) | leave it empty: 6 per CPU, at least 16; more if more AI requests can wait at the same time (about 3 MB each when idle) |
| Calls another slow service (a payment API, an SMTP server) from its pages | set it: more than the requests that can wait at the same time |

Measured on 2 CPUs (HTML pages per second while other visitors wait 1 s on an outside call): 4 processes answer
8,051 pages a second when nothing waits, but 32 when 4 visitors wait at once; 8 processes answer 4,180, then 7,550
with 4 waiting, then 14 with 16 waiting. The async server answers AI calls without holding a page's process.

`php caligrafer.php doctor` warns when an app that has AI keys runs fewer than 16 processes, and when the database
allows fewer connections than the processes keep open.

## Persistent database connections

`DB_PERSISTENT` (empty means on, for MySQL and PostgreSQL in a web server): each process keeps its database
connection between requests instead of opening one per request. Before it is reused, a transaction the last request
left open is rolled back and the session time zone is set again. The command line (tests, migrations, the queue)
never keeps one. Each process holds one connection: the database must allow `PHP_PROCESSES` connections plus the
command line and the queue (`doctor` checks it). `DB_PERSISTENT=false` connects on every request.

## The response cache

A capability or a page whose answer is the same for every visitor can say how long that answer may be shared:

```php
#[Capability(name: 'list_books', description: 'The catalog', readOnly: true, public: true, cache: 30)]
class ListBooks extends Service { /* ... */ }
```

```php
Route::page('/about', 'Site/about', ['cache' => 300]);
Route::capabilities('/capabilities');   // GET /capabilities/list_books (a read-only capability may be called with GET)
```

A GET or HEAD from a visitor with no session cookie and no token gets `Cache-Control: public, max-age=30`, and the
production image's Apache keeps the answer in memory: the next visitors get it without PHP. Never kept:

- an answer to a signed-in person or to a token (it is not marked);
- an answer that sets a cookie, such as a page with a form (its CSRF token starts a session): `HttpCache::guard()`
  turns it into `Cache-Control: private, no-store` just before the headers go out;
- any answer other than a 200.

`cache` needs `readOnly: true` and `public: true`. Choose a few seconds for data that changes: a cached answer is not
cleared when the data changes, it expires.

## The async server (experimental)

```bash
php caligrafer.php add async        # Workerman
php caligrafer.php async check      # which capabilities may run there, and why the others may not
```

With `ASYNC=on`, the production image listens with the async server: processes that keep the application in memory
(`ASYNC_WORKERS`, one per CPU by default) answer the capabilities channel (`GET /capabilities`,
`/capabilities/{name}`), and every other request (pages, forms, sign-in, files) goes to Apache unchanged. A request
that brings the session cookie always goes to Apache.

A capability runs there only when its class uses no session, cookie or superglobal (`$_SESSION`, `$_GET`, `$_SERVER`...),
prints nothing, never calls `exit`, keeps no static state, is not `webOnly` and takes no file: `async check` names
what stops each one, and Apache answers the others as before. After each request, what it left in memory is
forgotten (the cache copies, a transaction, container instances). Its callers are tokens and visitors only.

Development: `php caligrafer.php async start --listen http://0.0.0.0:8081` answers on its own port.

## Capability calls

Since 2.26.1 a call of a capability by name (`/capabilities/{name}`, MCP, the JS client) reads the registry of
capabilities from a file kept next to the class map instead of building it on every request: it loads only the file of
the capability it calls. On 2 CPUs through Apache, a public read-only capability answered 2,816 calls a second on
2.26.0 and 4,948 on 2.26.1 (a plain route: about 6,900). Nothing to set: it follows the class map
([How the attribute is found](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Attribute#registry)).

## Measured

The numbers of the benchmark (Docker, 2 CPUs and 1 GB for the app) are on the
[benchmark dashboard](https://github.com/caligrafy/caligrafy-quill#performance) and in the release notes of
[2.26.0](Release-notes:-2.26.0).
