# How Caligrafy works

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

![Caligrafy: Describe it. Approve it. Ship it.](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/describe-approve-ship-banner.png)

With Caligrafy you **describe** your idea, **approve** the plan and **ship** a complete app: Claude does the work, you keep the final say. What makes the app complete is one idea: **build once, experience everywhere**. A feature is written once, as a capability, and every experience of your application (web pages, Vue apps, a REST API, AI agents) uses that same capability. From the first command to a public address, it is one project. This page explains the few ideas behind it. To try it first, follow the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart).

## A capability is a feature, written once

A **capability** is one thing your application can do: list the tasks, add a book, place an order. It is a small PHP class in `application/services/` that says four things:

| What | Where in the class | Example |
| --- | --- | --- |
| its name and what it does | the `#[Capability]` line | `name: 'create_task'`, `description: 'Adds a task...'` |
| the inputs it accepts and their rules | `$rules` | `'title' => 'required|max_len,200'` |
| who may use it | the `#[Capability]` line | `scope: 'tasks:write'`, `level: 1`, or `public: true` |
| what it does | `handle()` | save the task, return it |

```php
#[Capability(name: 'create_task', description: 'Adds a task and returns it with its id.', scope: 'tasks:write')]
class CreateTask extends Service
{
    public $rules = array('title' => 'required|max_len,200', 'notes' => 'max_len,1000');

    public function handle(array $input, Context $context)
    {
        return (new Task('tasks'))->create($input);
    }
}
```

A capability only returns data or refuses (`ServiceError`): it never draws a page or answers HTTP itself. That is what lets every experience use it.

## Experiences use the capabilities

An **experience** is a way to reach your application. Each one is shaped for its audience, and each one calls the same capabilities:

| Experience | Who uses it | How it calls `create_task` |
| --- | --- | --- |
| Web pages (Pug) | people in a browser | a page script calls the JS client, or the page gets it from its route: `Route::page('/tasks/{id}', 'Tasks/show', ['data' => ['task' => 'get_task']])` |
| Vue apps and the single page application | people on desktop, tablet and phone | `await createTask({ title })` from the generated client (`php caligrafer.php client`) |
| REST API | other applications | `POST /api/tasks` (`Route::capability('POST', '/api/tasks', 'create_task')`) |
| AI agents (MCP) | Claude, Cursor and other agents | the `create_task` tool (`Route::mcp('/mcp')`) |
| Scripts | you, from the command line | `Capabilities::call('create_task', $input, Context::cli())` |

You decide how each experience looks (a dense page on a desktop, a touch-first app on a phone, a tool for an agent); you never re-write the rules.

## Who is calling

Every call knows who is making it:

+ **People** sign in (password, passkey or face login). A capability can ask for a minimum permission level: `level: 1`.
+ **Programs and AI agents** use a **personal access token** (`php caligrafer.php token create "my agent" --scopes tasks:read,tasks:write`). A token only reaches the capabilities its scopes allow, can expire, and can be revoked on its own.
+ **Public** capabilities (`public: true`) need neither: use it for data anyone may read.

## What happens on every call

Whatever the experience, every call goes through the same five steps:

1. **Find** the capability (unknown: `404`).
2. **Check who calls**: signed in or a token (`401`), with the right scope and level (`403`).
3. **Check the inputs** against `$rules`, keeping only the declared inputs (`422`, with a message per field).
4. **Run** `handle()`.
5. **Record** it in the audit log: every change and every refusal (`php caligrafer.php audit`).

So an invalid title gets the same message on the web page, over REST and for an agent, and a token without the scope is refused everywhere.

## From your computer to the internet

Build once does not end on your computer. The project you build is the project you put online, unchanged:

+ a production `Dockerfile` at its root builds it as it is (PHP, Apache, your libraries, no development tools);
+ when it starts, `php caligrafer.php deploy` creates and updates the tables from your `create_db.sql` scripts;
+ `php caligrafer.php cloud deploy <name>` puts it at `https://<name>.caligrafy.com` with [Caligrafy Cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#cloud), or you run the same image on [your own server](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#ownserver).

Online, every experience still calls the same capabilities: the pages, the apps, the REST API and the AI agents of the public address follow the same rules as on your computer. From the first command to that address, the steps are the same three, whether Claude does them or you do: **describe it** (start, your data, build once), **approve it** (experience everywhere, people and trust, the checks) and **ship it** (online).

## Where things live

```
application/
├── services/      capabilities: each feature, once (Users/: the people who sign in)
├── models/        the data: one class per table, and its create_db.sql
├── views/         the web pages (Pug), on the master default/master/site with Caligrafy's look
├── controllers/   only for pages with logic of their own (form posts, sign-in): they call capabilities
└── config/routing/web.php   the routes: Route::page, Route::capability, Route::capabilities, Route::mcp
Dockerfile                   the production image (php caligrafer.php deploy runs when it starts)
public/
├── js/capabilities.js       the generated JS client
├── css/layout.css, look.css  Caligrafy's look (tokens.css: the design tokens; camera.css: camera apps)
└── <app>/                   Vue apps and the single page application
```

**One rule keeps every experience the same: only capabilities touch models.** Pages get their data from capabilities (`Route::page` with `data`, or the JS client in the browser), controllers call capabilities (`$this->call()`), REST and AI agents call capabilities. Every page goes through `Route::page`, which also gives it who is signed in; see [Page Routes](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#pageroutes).

Caligrafy is also a classic MVC framework (routes, controllers, models, views), documented in the rest of this wiki. Start with capabilities; reach for a controller when a page needs logic of its own, and let it call capabilities.

## Next

+ [Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude): your idea, built and online, or the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart): a working application in 5 minutes.
+ [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability): build a complete feature, page included.
+ [Build Once: Services, Capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP): every option, in depth.
+ [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online): from your computer to a public address.
