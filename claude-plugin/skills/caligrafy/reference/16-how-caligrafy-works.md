# How Caligrafy works

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy is built around one idea: **build once, experience everywhere**. A feature is written once, as a capability, and every experience of your application (web pages, Vue apps, a REST API, AI agents) uses that same capability. This page explains the few ideas behind it. To try it first, follow the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart).

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
| Web pages (Pug) | people in a browser | a page script calls the JS client, or a controller calls `$this->call('create_task')` |
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

## Where things live

```
application/
├── services/      capabilities: each feature, once
├── models/        the data: one class per table, and its create_db.sql
├── views/         the web pages (Pug), built with the page components
├── controllers/   pages that need server-side logic
└── config/routing/web.php   the routes: pages, Route::capability, Route::capabilities, Route::mcp
public/
├── js/capabilities.js       the generated JS client
├── css/tokens.css           the Caligrafy look and the page components
└── <app>/                   Vue apps and the single page application
```

Caligrafy is also a classic MVC framework (routes, controllers, models, views), documented in the rest of this wiki. Start with capabilities; reach for a controller when a page needs logic of its own.

## Next

+ [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart): a working application in 5 minutes.
+ [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability): build a complete feature, page included.
+ [Build Once: Services, Capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP): every option, in depth.
