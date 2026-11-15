# Release notes: 2.26.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.26.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.26.0** makes every project about **twice as fast**, a quarter of the size, and safer by default,
with no change to your code. On the same benchmark machine (Docker, 2 CPUs), it is faster than Next.js on every test,
faster than Nuxt on HTML pages, and its experimental async server answers capability calls faster than Nuxt answers
its own routes.

+ **A production image sized to its container.** The `Dockerfile` now starts with `docker/production/start.sh`: Apache
  runs a number of PHP processes that follows your app (2 per CPU, or 6 per CPU when an AI key is set, because a
  request waiting on an AI answer holds its process), keep-alive off, OPcache tuned for code that never changes, the
  framework preloaded, every view compiled before the first visitor. `PHP_PROCESSES` sets your own number.
+ **Persistent database connections** (`DB_PERSISTENT`, on by default for MySQL and PostgreSQL): each process keeps
  its connection, reset between requests. Controllers no longer open a second one.
+ **A response cache.** A public read-only capability with `cache: 30`, or a page with `['cache' => 30]`, is answered
  from memory to the next visitors, without PHP. Never an answer to a signed-in person or a token, never one that sets
  a cookie (a form), never an error.
+ **The async server** (experimental, `ASYNC=on` after `php caligrafer.php add async`): processes that keep the
  application in memory answer the capabilities channel for tokens and visitors; pages, forms and sign-in stay on
  Apache. Only capabilities that keep no state between callers run there (`php caligrafer.php async check`).
+ **A 15 MB install.** Stripe, Groq, web push, passkeys, the Anthropic SDK and the FaceDetect models are added when an
  app uses them: `php caligrafer.php add <payments | ai | push | passkeys | snap | async | facedetect>`. A new project
  installs 13 packages instead of 44; an updated project keeps what it has.
+ **Safer by default.** N+1 queries throw in development with the `with()` fix (production logs them), and a write
  capability without a `scope` needs a token with the scope named after it. The secure-by-default checklist of the
  benchmark goes from 5 to 7 of 8.
+ **Docs for people and agents.** 80 new wiki pages, among them a generated [API reference](API-Reference) of every
  class and method, [performance and deployment](Performance-and-Deployment) and
  [security defaults](Security-Defaults); `llms.txt`, `llms-full.txt`, `AGENTS.md` and `context7.json` for coding
  agents. The Claude Code plugin is 1.38.0.

## Measured

Docker on one computer, 2 CPUs and 1 GB per app, 32 connections, 3 runs of 15 s each; requests per second.

| | Plain text | JSON | Database | HTML page |
|---|---|---|---|---|
| Caligrafy 2.25 | 3,990 | 3,571 | 2,060 | 2,281 |
| **Caligrafy 2.26** (an app without AI keys: 4 processes) | **9,995** | **10,013** | **5,621** | **7,998** |
| Next.js 16.4 | 6,101 | 5,948 | 4,077 | 1,135 |
| Nuxt 4.6 | 53,842 | 39,975 | 12,477 | 4,182 |
| Symfony 8.1 | 2,139 | 2,141 | 1,036 | 1,830 |
| Laravel 13.42 | 267 | 850 | 457 | 266 |

The same capability call (20 items; 20 database rows), through Apache and through the async server:

| | 20 items | 20 database rows |
|---|---|---|
| Apache | 2,300 | 2,200 |
| Async server, 2 processes | **113,700** | 12,300 |
| Async server, 4 processes | 111,000 | **22,100** |

A capability call answers `{"data": [...]}` after checking its inputs and its caller; Nuxt's routes return the list.

First request of a new container: 37 to 42 ms (2.25: 51 to 101 ms). Container memory: 31 to 37 MB (2.25: 157 to
169 MB). Install: 13 packages, 16.6 MB (2.25: 44 packages, 72 MB).

## Updating a project

```bash
php caligrafer.php update
php caligrafer.php doctor
```

Then:

+ **Tokens:** `doctor` lists the write capabilities without a `scope`: declare one on each (`scope: 'books:write'`),
  or give the tokens that call them the scope named after the capability. `TOKEN_DEFAULT_SCOPES=false` (deprecated)
  keeps the old rule meanwhile.
+ **N+1:** a development page that calls a relation method in a loop over query results now shows
  `[ERROR 2004] Lazy loading prevented`: load the relation with `with()` (or allow it with `->lazy()`).
+ **Optional SDKs:** your project keeps the packages it uses; a new project adds them with `php caligrafer.php add`.
+ **Production:** nothing to do; to change the defaults, see [performance and deployment](Performance-and-Deployment).
  An app that calls a slow outside service other than AI from its pages (a payment API, an SMTP server) sets
  `PHP_PROCESSES` above the requests that can wait at the same time.

See the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).
