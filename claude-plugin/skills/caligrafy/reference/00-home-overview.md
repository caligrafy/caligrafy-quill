# Caligrafy Quill overview (wiki Home / README)

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

# Caligrafy - Describe it. Approve it. Ship it.
![Caligrafy: Describe it. Approve it. Ship it.](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/describe-approve-ship-banner.png)

**Your idea turned into a complete app.** Describe it to [Claude Code](https://claude.com/claude-code), approve the
plan, and Caligrafy ships it: the database, sign-in, web pages, a phone and tablet app, a REST API and tools for AI
agents, online at `https://<name>.caligrafy.com`, with the code in your own GitHub repository.

## Get started quickly

With Claude Code, three steps take your idea to a complete app online:

| | You | Claude and Caligrafy |
| --- | --- | --- |
| **1. Describe it** | your idea, in a sentence: `/caligrafy-quill:idea A reading list where I rate the books I read` | turn it into a plan: a name, the tables, every feature, who may do what, the pages |
| **2. Approve it** | read the plan, change it, say OK | build every feature once, then check it from a page, REST and an AI agent |
| **3. Ship it** | approve the sign-ins in your browser | create your GitHub repository, deploy it, check it online |

```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp
cd myapp
claude
```
```
/caligrafy-quill:idea A reading list: I add books I want to read, mark them as read, and rate them from 1 to 5.
```
You say OK once, at the plan; after that you only approve sign-ins on GitHub's and Caligrafy Cloud's own pages,
which Claude opens for you. It never asks for a password or a key, and asks before spending credits. See
[Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude).

**Start from a proven prompt.** The [marketplace of caligrafy.com](https://caligrafy.com/marketplace) offers apps that
already shipped, three ways: the prompt that recreates one with Caligrafy, its code, or the app managed for you. And
once yours is online, `/caligrafy-quill:marketplace` offers it there: you earn 25% of every sale. No time to build it?
[Submit your idea](https://caligrafy.com/ideas/new) and Caligrafy builds it for you.

## A complete app: build once, experience everywhere

What makes the app complete is how Caligrafy builds it. A feature (add a task, list the books, place an order) is
written once, as a **capability**: its inputs, its rules, who may use it, and what it does. Caligrafy then serves
that same capability to every way people and machines reach your application:

| Experience | How it uses the capability |
| --- | --- |
| **Web pages** | a page calls it and shows the result |
| **Vue apps** on desktop, tablet and phone | a generated JavaScript function: `await createTask({ title })` |
| **REST API** for other applications | `POST /api/tasks` |
| **AI agents** such as Claude or Cursor | an MCP tool: `create_task` |

The same validation messages, the same permissions and the same audit log, whichever way it is called. You never
write a feature twice, and the experiences never drift apart.

## By hand: the same three steps

Everything Claude does is plain Caligrafy you can write yourself, with the same files Claude writes:

| Step | What you do | With |
| --- | --- | --- |
| **1. Describe it** | a running project, your tables, and each feature written once with its rules | `.bin/caligrafer quickstart`, `create_db.sql` and a model, a capability (`php caligrafer.php service`) |
| **2. Approve it** | every experience and every permission, checked | `Route::page`, `Route::form`, `Route::auth`, `Route::capabilities`, `Route::mcp`, `scope` and `level`, tests, the reviewer, `php caligrafer.php doctor` |
| **3. Ship it** | your application at a public address | the production `Dockerfile`, `php caligrafer.php cloud deploy` |

The [course](https://github.com/caligrafy/caligrafy-quill-course) teaches both ways: with Claude, and by hand, every
file typed by you.

## Quickstart by hand: 5 minutes

You need [Git](https://git-scm.com/) and [Docker Desktop](https://www.docker.com/products/docker-desktop/)
(running). PHP, MySQL and Composer run in Docker.

```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp
cd myapp
.bin/caligrafer quickstart --docker --example
```
`quickstart` installs the libraries, starts the server, creates the database and the framework tables, and adds a
**Tasks** example (the first start builds the PHP image: a few minutes). When it asks to delete the `.git` folder,
answer `y`: your project starts its own history. To use your own Apache and MySQL (MAMP, XAMPP, LAMP) instead, see
[Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart#composer).

**1. Use it on the web.** Open **http://localhost:8080/tasks**, add a task, tick it, rename it.

**2. Use it from another application.** The same tasks, over REST:
```bash
curl -H "Accept: application/json" http://localhost:8080/api/tasks
```

**3. Use it from an AI agent.** Create a token and give it to Claude Code (or Cursor, below):
```bash
docker compose exec dev-box php caligrafer.php token create "my agent" --scopes "*"
claude mcp add --transport http myapp http://localhost:8080/mcp --header "Authorization: Bearer <the token>"
```
Ask Claude "add a task to buy milk": it calls `create_task`, and the task appears on the web page.
In Cursor, add to `.cursor/mcp.json`:
```json
{ "mcpServers": { "myapp": { "url": "http://localhost:8080/mcp", "headers": { "Authorization": "Bearer <the token>" } } } }
```

**4. Ship it.** Push the project to a GitHub repository (`git init`, commit, push), then:
```bash
docker compose exec dev-box php caligrafer.php cloud login                 # open the address it prints, approve the code
docker compose exec dev-box php caligrafer.php cloud create myapp --repo https://github.com/<you>/myapp
docker compose exec dev-box php caligrafer.php cloud deploy myapp          # https://myapp.caligrafy.com
```
`cloud login` opens [deploy.caligrafy.com](https://deploy.caligrafy.com), where you sign in (or sign up) and approve.
Every new app is free for its first 30 minutes online. The Tasks example is open to everyone, online too: see
[Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online) before you share the address.

## Your first capability

A feature, by hand, is a table, a model, a capability and its routes. The list of a book catalog:

**`application/services/Bookshelf/ListBooks.php`** (created by
`docker compose exec dev-box php caligrafer.php service Bookshelf/ListBooks --scope books:read`)
```php
#[Capability(name: 'list_books', description: 'Lists the books, 20 per page, each with its author.', scope: 'books:read', readOnly: true)]
class ListBooks extends Service
{
    public $rules = array('author_id' => 'integer', 'page' => 'integer');

    public function handle(array $input, Context $context)
    {
        $query = Book::with('author')->orderBy('title');
        if (!empty($input['author_id'])) {
            $query->where('author_id', $input['author_id']);
        }
        return $query->paginate(20, $input['page'] ?? 1);
    }
}
```
**`application/config/routing/web.php`**
```php
Route::page('/books', 'Bookshelf/books', ['data' => ['books' => 'list_books']]);   // a web page
Route::capabilities();                                                               // REST: POST /capabilities/list_books
Route::mcp();                                                                        // AI agents: the list_books tool
```
The same capability is also a function of the generated JavaScript client (`php caligrafer.php client`). The tutorial
[Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability) builds a complete
feature step by step: the table, the model, the page, a form and sign-in.

## What comes in the box

+ **Every experience:** server-rendered pages (Pug), Vue apps and a Vite single page application, installable mobile
  web apps, REST, and an MCP server for AI agents.
+ **One rule:** only capabilities touch your data. Pages get their data from capabilities in one line
  (`Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book']])`), so a page, the API and an agent
  never follow different rules. Forms, sign-in, webhooks, downloads and anything else a browser asks are one line each too
  ([declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes): `Route::form`,
  `Route::auth`, `Route::webhook`, `Route::download`, `Route::action`): an application needs no controller.
+ **One look, or yours:** Caligrafy's look on every page: a light top bar with the Caligrafy mark, title bands, forms,
  rows and tiles, Caligrafy black for the main action, full-screen camera apps, the same on every device. Or pick one of 85 [design styles](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles)
  (`DESIGN_STYLE` in `.env`, or your own `DESIGN.md`) and let `/caligrafy-quill:design` restyle every page.
+ **Secure by design:** sign-in, passkeys and face login for people; scoped, revocable tokens for programs and
  agents; CSRF protection; an audit log of every change; server files never served.
+ **What large apps need:** [queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries) with filters, paging,
  related rows in one query and transactions, [migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations), [background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs), [a cache](https://github.com/caligrafy/caligrafy-quill/wiki/Caching),
  [tests for your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App), [dependency injection](https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection), scheduled jobs and notifications.
+ **Fast:** in the same benchmark, Caligrafy 2.19 beats Symfony and Laravel on all 16 measures (requests per second,
  median and slowest response, memory, for plain text, JSON, a database query and an HTML page): an HTML page at
  2,887 requests per second, against 1,789 for Symfony and 324 for Laravel
  ([release notes](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.19.0)).
+ **Batteries included:** card, ACH and crypto payments, OpenAI and open-model AI (Llama on Groq, OpenRouter,
  Ollama...), chatbots, face detection and machine learning in the browser, search and social metadata.
+ **Made for AI-assisted development:** a [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin)
  that knows the framework, builds features the Caligrafy way or a whole application from your idea, and puts it
  online, and `AGENTS.md` for other coding agents.
+ **Online in a few commands:** a production `Dockerfile` in every project, the database prepared at every start,
  and [Caligrafy Cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#cloud):
  `php caligrafer.php cloud deploy` puts it at `https://<name>.caligrafy.com`, every new app free for its first 30
  minutes, and `/caligrafy-quill:ship` does it all for you, from the GitHub repository to the check online.
+ **Sell what you built:** offer an app online on the [marketplace of caligrafy.com](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#marketplace):
  Caligrafy sells a prompt that recreates it and you earn 25% of every sale. `/caligrafy-quill:marketplace` reads
  your code, screenshots its key features and submits the listing (`php caligrafer.php cloud submit`).
+ **Tools:** `php caligrafer.php doctor` checks your setup and services, `call` tries a capability in one line,
  `migrate` changes the database, `test` runs your tests, `queue work` runs background jobs,
  `update` moves a project to the latest release in one command (keeping what you changed), `upgrade` updates its
  `application/` templates and tells you what to check, `version` tells you which release you run.

## Where to go next

| You want to | Read |
| --- | --- |
| Get a feel for it | [Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude) or the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart), then [How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works) |
| Build a feature | [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability), then [Capabilities in depth](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP) |
| Build a web page | [Views and Caligrafy's look](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#pagecomponents) |
| Build a Vue app or a single page application | [Vue as a library](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.0-Setting-up-Vue.js-as-a-library), [the single page application](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.1---Setting-up-the-Vue-application) |
| Let AI agents use your application | [MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#mcp) |
| Store data | [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models) and [Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships) |
| Filter, page and load related rows | [Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries) |
| Take a form, sign people in, receive webhooks, export a CSV | [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes) |
| Change your tables, test your app | [Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations), [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App) |
| Work in the background, keep slow results | [Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs), [The cache](https://github.com/caligrafy/caligrafy-quill/wiki/Caching), [Dependency injection](https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection) |
| Let people sign in | [Sign-in, users and tokens](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication) (`Route::auth`), [with an email](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#emailsignin) |
| See what changed | [Release notes: 2.21.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.21.0), [2.20.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.20.0), [2.19.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.19.0) and the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md) |
| Take payments, add AI, face login... | the **Built-in features** of the sidebar |
| Put it online | [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online): Caligrafy Cloud or your own server |
| Sell your app | [Selling your app on the marketplace](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#marketplace): a prompt that recreates it, 25% of every sale |
| Build with Claude | [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin) |
| Learn step by step, from zero | [The Caligrafy course](https://github.com/caligrafy/caligrafy-quill-course) |
| Use the command line (set up, create, tokens, check, upgrade) | [Caligrafer: every command](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer) |
| Install another way, configure, upgrade | [Installation and configuration](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started), [Security and upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading) |

## Community and support

+ **GitHub:** [issues](https://github.com/caligrafy/caligrafy-quill/issues) for bugs and feature requests.
+ **Slack:** [join the Caligrafy community](https://join.slack.com/t/caligrafy/shared_invite/enQtNzI4MDY2OTA4MTgzLTI2NDc2NTVmMDNlMWQ5YWYxN2RjZTkwZjdiNjM5ZTg3NjQ2YWYyMzRmZDgzNWE0Nzc4YjQyODM2NDNkNjQ2OTU) to get help and share what you build.
+ **Facebook:** the [Caligrafy group](https://www.facebook.com/groups/caligrafy/).
+ **Videos:** the [Caligrafy channel](https://www.youtube.com/playlist?list=PLsJG81fLk3_VWmXl9DnEldYY9ei4jTCjA).

If Caligrafy helps you, [sponsor it on GitHub](https://github.com/sponsors/DoryAzar) or share
[caligrafy.com](https://caligrafy.com).

Caligrafy is released under the [MIT License](https://github.com/caligrafy/caligrafy-quill/blob/master/LICENSE).
