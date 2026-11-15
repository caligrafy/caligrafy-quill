# Why Caligrafy: the benefits for developers

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Why-Caligrafy
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

## Describe it. Approve it. Ship it.

**An open-source PHP framework where every feature is written once and served to pages, Vue apps, REST and AI
agents. Describe your app to Claude Code, approve the plan, and it is online, with the code in your own GitHub
repository.**

Caligrafy Quill is an MIT-licensed PHP MVC framework built around one idea: a feature is a **capability**, a small
PHP class that declares its inputs, its permissions and whether it reads, writes or destroys data. Pages, forms, the
REST API, the JavaScript client, MCP tools for AI agents and Snap Cards all call that same class, through the same
checks, into the same audit log. Write it yourself, or let Claude Code write it: either way it is plain Caligrafy,
documented step by step.

---

### 1. Write a feature once, serve it everywhere

A capability is one attribute and one `handle()`. Routes decide where it shows up: a Pug page, a form, a REST
endpoint, a function of the generated JS client, an MCP tool, a Snap Card. Every call takes the same path: find,
check who calls (401/403), validate (422, one message per field), run, audit.

```php
#[Capability(name: 'create_book', description: 'Adds a book...', scope: 'books:write')]
class CreateBook extends Service
{
    public $rules = array('title' => 'required|max_len,200', 'author_id' => 'required|integer|exists,authors.id');

    public function handle(array $input, Context $context)
    {
        $id = Database::table('books')->insert($input);
        return Database::table('books')->with('author')->find($id);
    }
}
```
```php
Route::form('/books', 'create_book');            // an HTML form
Route::capabilities('/capabilities');            // REST and the JS client of your Vue apps
Route::mcp('/mcp');                              // AI agents, personal access tokens only
Route::snap();                                   // every capability as a card, with an assistant
```

### 2. Less code to write, read and change

We wrote the same Bookshelf app (pages, sign-in, a REST API with scoped tokens, two MCP tools, a CSV export, a Stripe
webhook) in each framework, the way its official docs and generators teach it.

| | Lines | Files | Concepts to learn |
|---|---|---|---|
| **Caligrafy** | **471** | **24** | **25** |
| Laravel | 708 | 40 | 42 |
| Tempest | 902 | 42 | 40 |
| Symfony | 1,076 | 39 | 42 |

Adding one more field to the form, the page, the API and the MCP tool touches **4 files** in Caligrafy, 6 to 7 in the
others.

### 3. Secure before you write a line

CSRF on every form, bound queries, mass-assignment protection, password hashing, a new session id at sign-in, token
scopes and N+1 prevention are on in a new project. A write capability without a declared scope still needs a token
with the scope named after it, and an N+1 loop throws in development with the `with()` fix in the message.

**7 of the 8 items** of the benchmark's secure-by-default checklist need no code in 2.26.0 (Tempest: 6, Laravel and
Symfony: 5). The eighth, webhook signatures, is one line: `Route::webhook` refuses to register without `verify`.

### 4. Fast on modest hardware

2.26.0 is about twice as fast as 2.25 with no change to your code: the production image sizes its PHP processes to its
container, keeps database connections, preloads the framework and compiles every view before the first visitor.
Requests per second on our benchmark machine (Docker, 2 CPUs and 1 GB per app, 32 connections):

| | Plain text | JSON | Database | HTML page |
|---|---|---|---|---|
| **Caligrafy 2.26.0** | **9,995** | **10,013** | **5,621** | **7,998** |
| Next.js 16.4 | 6,101 | 5,948 | 4,077 | 1,135 |
| Nuxt 4.6 | 53,842 | 39,975 | 12,477 | 4,182 |
| Symfony 8.1 | 2,139 | 2,141 | 1,036 | 1,830 |
| Laravel 13.42 | 267 | 850 | 457 | 266 |

Nuxt is faster on plain text, JSON and database; Caligrafy is faster on HTML pages, and faster than Next.js, Symfony
and Laravel on every test. The experimental async server (`ASYNC=on`) answers a capability call at **113,700 a
second** (JSON) and **22,100** (20 database rows, 4 processes). One machine, our setup: run it on yours before you
quote it.

### 5. Small enough to forget about

A new project installs **13 packages and 16.6 MB** (2.25: 44 packages, 72 MB). Stripe, AI SDKs, web push, passkeys and
the face-detection models come in only when you use them: `php caligrafer.php add payments`.

| New project | Caligrafy | Symfony | Laravel | Nuxt | Next.js |
|---|---|---|---|---|---|
| Install | **16.6 MB** | 24.8 MB | 45 MB | 245 MB | 546 MB |

A running container uses about **35 MB**, and the first request of a new container answers in **37 to 42 ms**.

### 6. Built for AI coding agents

Every project ships a Claude Code plugin: a skill with the framework docs, an API reference generated from the source
and a numbered list of known gotchas, plus commands for the recurring jobs (`/caligrafy-quill:new-capability`,
`/caligrafy-quill:new-resource`, `/caligrafy-quill:ship`) and `/caligrafy-quill:idea`, which takes one sentence to an app
online. Other agents (Cursor, Codex, Copilot, Gemini) get `AGENTS.md`, `llms.txt`, `llms-full.txt` and a Context7
listing. And when something is off, one read-only command checks the project and every service in `.env`:

```bash
php caligrafer.php doctor
```

### 7. Ship it, to our cloud or your server

`/caligrafy-quill:ship` commits, creates your GitHub repository, pushes and deploys to
`https://<name>.caligrafy.com` on Caligrafy Cloud, with a MySQL database and your scheduled jobs; you approve the
sign-ins in your browser, and Claude never asks for a password or a key. Prefer your own server? Every project has a
production `Dockerfile` that runs 2 PHP processes per CPU (6 with an AI key), with `PHP_PROCESSES` to override.

```bash
php caligrafer.php cloud deploy
```

---

### What Caligrafy is not (yet)

Its ecosystem is young. Laravel and Symfony have years of packages, tutorials, hosting integrations and people who
already know them; Caligrafy has its wiki, a course and a community that is just starting. The benchmarks are ours,
on one computer. If you need a big hiring pool or a package for everything today, that matters. If you want to write
a feature once and have it work for people and agents alike, try it in five minutes.

---

## Start in five minutes

**With Claude Code:**
```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp && cd myapp && claude
```
```text
/caligrafy-quill:idea A reading list: I add books I want to read, mark them as read, and rate them from 1 to 5.
```

**By hand** (Git and Docker Desktop):
```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp && cd myapp && .bin/caligrafer quickstart --docker --example
```
Then open http://localhost:8080/tasks.

- **Docs:** https://github.com/caligrafy/caligrafy-quill/wiki
- **Course**, with Claude or by hand: https://github.com/caligrafy/caligrafy-quill-course
- **Your first capability:** https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability
- **Release notes and benchmark:** https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.26.0

MIT-licensed. Star it, break it, open an issue.
