# Caligrafy Quill overview (wiki Home / README)

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

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
write a feature twice, and the experiences never drift apart.

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

That is the whole idea: the Tasks feature lives in five small files in `application/services/Tasks/`, and every
experience above uses them.

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

## What comes in the box

+ **Every experience:** server-rendered pages (Pug), Vue apps and a Vite single page application, installable mobile
  web apps, REST, and an MCP server for AI agents.
+ **One look:** ready-made page components (cards, forms, tabs, lists) with the Caligrafy black and white style, the
  same on every page and device.
+ **Secure by design:** sign-in, passkeys and face login for people; scoped, revocable tokens for programs and
  agents; CSRF protection; an audit log of every change; server files never served.
+ **Batteries included:** card, ACH and crypto payments, OpenAI and open-model AI (Llama on Groq, OpenRouter,
  Ollama...), chatbots, face detection and machine learning in the browser, search and social metadata.
+ **Made for AI-assisted development:** a [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin)
  that knows the framework and builds features the Caligrafy way, and `AGENTS.md` for other coding agents.
+ **Tools:** `php caligrafer.php doctor` checks your setup and services, `upgrade` updates a project, `version`
  tells you which release you run.

## Where to go next

| You want to | Read |
| --- | --- |
| Get a feel for it | [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart), then [How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works) |
| Build a feature | [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability), then [Capabilities in depth](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP) |
| Build a web page | [Views and page components](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#pagecomponents) |
| Build a Vue app or a single page application | [Vue as a library](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.0-Setting-up-Vue.js-as-a-library), [the single page application](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.1---Setting-up-the-Vue-application) |
| Let AI agents use your application | [MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#mcp) |
| Store data | [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models) and [Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships) |
| Let people sign in | [Sign-in, users and tokens](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication) |
| Take payments, add AI, face login... | the **Built-in features** of the sidebar |
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
