# Caligrafy - Build Once, Experience Everywhere
![Caligrafy: Build Once, Experience Everywhere](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/build-once-banner.png)

**Write a feature once. Use it everywhere.** In Caligrafy, a feature of your application (add a task, list the
books, place an order) is written once, as a **capability**: its inputs, its rules, who may use it, and what it does.
Caligrafy then serves that same capability to every way people and machines reach your application:

| Experience | How it uses the capability |
| --- | --- |
| **Web pages** | a page calls it and shows the result |
| **Vue apps** on desktop, tablet and phone | a generated JavaScript function: `await createTask({ title })` |
| **REST API** for other applications | `POST /api/tasks` |
| **AI agents** such as Claude or Cursor | an MCP tool: `create_task` |

The same validation messages, the same permissions and the same audit log, whichever way it is called. You never
write a feature twice, and the experiences never drift apart. And it does not stop at your computer: the same
project goes online in a few commands.

<br/>

## From an idea to an application online

Caligrafy is the whole way from zero to one, in one project, with one command line:

| Step | What you do | With |
| --- | --- | --- |
| **1. Start** | a running application, database included | `.bin/caligrafer quickstart` |
| **2. Your data** | the tables and their models | `create_db.sql`, models and relationships |
| **3. Build once** | each feature, written once, with its rules | a capability (`php caligrafer.php service`) |
| **4. Experience everywhere** | web pages, a Vue app, a phone and tablet app, a REST API, AI agents | Caligrafy's look, the JS client, `Route::capability`, `Route::mcp` |
| **5. People and trust** | sign-in, permissions, tokens for programs and agents, an audit log | `scope` and `level`, `php caligrafer.php token` |
| **6. Ship** | tests, a review, a health check | PHPUnit, the reviewer, `php caligrafer.php doctor` |
| **7. Online** | your application at a public address | the production `Dockerfile`, `php caligrafer.php cloud deploy` |

With [Claude Code](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin), the plugin that ships
in every project does each step with you, or all of them at once: `/caligrafy-quill:idea` takes an idea to an
application online. The
[course](https://github.com/caligrafy/caligrafy-quill-course) follows these seven steps from an empty folder to an
application on the internet.

<br/>

## Quickstart with Claude: from your idea to online

Describe an application in a sentence; [Claude Code](https://claude.com/claude-code) builds it the Caligrafy way and
puts it online for you to use everywhere:
```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp
cd myapp
claude
```
```
/caligrafy-quill:idea A reading list: I add books I want to read, mark them as read, and rate them from 1 to 5.
```
Claude plans it with you (tables, capabilities, who may do what), builds every feature once, checks it from the page,
REST and an AI agent, then creates your GitHub repository and deploys it to `https://<name>.caligrafy.com`. You say OK
to the plan once; after that you only approve sign-ins in the browser pages Claude opens. See
[Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude).

<br/>

## Quickstart: 5 minutes

You need [Git](https://git-scm.com/) and [Docker Desktop](https://www.docker.com/products/docker-desktop/)
(running). Nothing else: PHP, MySQL and Composer run in Docker.

```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp
cd myapp
.bin/caligrafer quickstart --example
```
`quickstart` installs the libraries, starts the server, creates the database and the framework tables, and adds a
**Tasks** example (the first start builds the PHP image: a few minutes). When it asks to delete the `.git` folder,
answer `y`: your project starts its own history.

**With Composer, on MAMP, XAMPP, LAMP or Docker.** If you have PHP 8.3+ and [Composer](https://getcomposer.org):
```bash
composer create-project caligrafy/caligrafy-quill myapp
cd myapp
.bin/caligrafer quickstart --example
```
`quickstart` uses your local Apache and MySQL when they are running (create the project in their web folder: it reads
MAMP's own settings, and asks for your MySQL settings with their defaults), Docker otherwise; `--local` or `--docker`
chooses. On Windows: `php caligrafer.php quickstart --example`. See
[Quickstart with Composer](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart#composer).

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
Ask Claude "add a task to buy milk": it calls `create_task`, and the task appears on the web page within seconds.
In Cursor, add to `.cursor/mcp.json`:
```json
{ "mcpServers": { "myapp": { "url": "http://localhost:8080/mcp", "headers": { "Authorization": "Bearer <the token>" } } } }
```

**4. Put it online.** Push the project to a GitHub repository (`git init`, commit, push), then:
```bash
docker compose exec dev-box php caligrafer.php cloud login                 # open the address it prints, approve the code
docker compose exec dev-box php caligrafer.php cloud create myapp --repo https://github.com/<you>/myapp
docker compose exec dev-box php caligrafer.php cloud deploy myapp          # https://myapp.caligrafy.com
```
`cloud login` opens [deploy.caligrafy.com](https://deploy.caligrafy.com), where you sign in (or sign up) and approve.
Every new app is free for its first 30 minutes online. With Claude Code, `/caligrafy-quill:ship myapp` does all of
this for you, the GitHub repository included. The Tasks example is open to everyone, online too: see
[Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online) before you share the address.

That is the whole idea: the Tasks feature lives in five small files in `application/services/Tasks/`, and every
experience above uses them.

<br/>

## Your first capability

```bash
docker compose exec dev-box php caligrafer.php service Books/CreateBook --scope books:write
```
This creates `application/services/Books/CreateBook.php`. Fill in its inputs and its work:
```php
#[Capability(name: 'create_book', description: 'Adds a book to the catalog and returns it.', scope: 'books:write')]
class CreateBook extends Service
{
    public $rules = array('title' => 'required|max_len,255', 'author' => 'required|max_len,255');

    public function handle(array $input, Context $context)
    {
        return (new Book('books'))->create($input);
    }
}
```
AI agents see it at once as the `create_book` tool. Give it a REST route in
`application/config/routing/web.php` with `Route::capability('POST', '/books', 'create_book');`, and a JavaScript
function for your pages with `php caligrafer.php client`. The tutorial
[Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability) builds a complete
feature step by step, page included.

<br/>

## What comes in the box

+ **Every experience:** server-rendered pages (Pug), Vue apps and a Vite single page application, installable mobile
  web apps, REST, and an MCP server for AI agents.
+ **One rule:** only capabilities touch your data. Pages get their data from capabilities in one line
  (`Route::page('/books/{id}', 'Books/show', ['data' => ['book' => 'get_book']])`), so a page, the API and an agent
  never follow different rules.
+ **One look, or yours:** Caligrafy's look on every page: a light top bar with the Caligrafy mark, title bands, forms,
  rows and tiles, Caligrafy black for the main action, full-screen camera apps, the same on every device. Or pick one of 85 [design styles](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles)
  (`DESIGN_STYLE` in `.env`, or your own `DESIGN.md`) and let `/caligrafy-quill:design` restyle every page.
+ **Secure by design:** sign-in, passkeys and face login for people; scoped, revocable tokens for programs and
  agents; CSRF protection; an audit log of every change; server files never served.
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
  `update` moves a project to the latest release in one command (keeping what you changed), `upgrade` updates its
  `application/` templates and tells you what to check, `version` tells you which release you run.

<br/>

## Learn

| If you want to | Read |
| --- | --- |
| Get started | [Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude) or the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart), then [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability) |
| Understand the idea | [How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works) |
| Learn step by step, from zero | [The Caligrafy course](https://github.com/caligrafy/caligrafy-quill-course): build a complete application in 15 lessons, capabilities first, by hand or with Claude, up to putting it online |
| Look something up | [The documentation](https://github.com/caligrafy/caligrafy-quill/wiki) |
| Put it online | [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online): Caligrafy Cloud or your own server |
| Use the command line | [Caligrafer: every command](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer) |
| See what changed | [Changelog](CHANGELOG.md) and the release notes ([2.9](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.9.0), [2.8](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.8.0), [2.7](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.7.0), [2.6](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.6.0), [2.5](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.5.0), [2.4](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.4.0), [2.3](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.3.0), [2.1](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.1.0)) |
| Contribute | [Contributing](CONTRIBUTING.md), [how the framework is built](ARCHITECTURE.md) and [Testing Caligrafy](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy) |

Other ways to install (your own Apache, MySQL and PHP; `composer create-project`) are in
[Getting Started](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started). Requirements without
Docker: PHP 8.3+ with curl, mbstring, openssl, sodium, gd, bcmath, intl and pdo_mysql, MySQL 8.4, Composer, and
Node.js 20.19+ for the single page application.

<br/>

## Community and support

+ **GitHub:** [issues](https://github.com/caligrafy/caligrafy-quill/issues) for bugs and feature requests.
+ **Slack:** [join the Caligrafy community](https://join.slack.com/t/caligrafy/shared_invite/enQtNzI4MDY2OTA4MTgzLTI2NDc2NTVmMDNlMWQ5YWYxN2RjZTkwZjdiNjM5ZTg3NjQ2YWYyMzRmZDgzNWE0Nzc4YjQyODM2NDNkNjQ2OTU) to get help and share what you build.
+ **Facebook:** the [Caligrafy group](https://www.facebook.com/groups/caligrafy/).
+ **Videos:** the [Caligrafy channel](https://www.youtube.com/playlist?list=PLsJG81fLk3_VWmXl9DnEldYY9ei4jTCjA).

If Caligrafy helps you, [sponsor it on GitHub](https://github.com/sponsors/DoryAzar) or share
[caligrafy.com](https://caligrafy.com).

<br/>

Caligrafy is released under the [MIT License](LICENSE).
