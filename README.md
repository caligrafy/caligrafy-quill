# Caligrafy - Describe it. Approve it. Ship it.
![Caligrafy: Describe it. Approve it. Ship it.](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/describe-approve-ship-banner.png)

**Your idea turned into a complete app.** Describe it to [Claude Code](https://claude.com/claude-code), approve the
plan, and Caligrafy ships it online, with the code in your own GitHub repository. Or write it yourself: everything
Claude writes is plain Caligrafy, documented step by step.

<br/>

## With Claude

```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp
cd myapp
claude
```
```
/caligrafy-quill:idea A reading list: I add books I want to read, mark them as read, and rate them from 1 to 5.
```

| | You | Claude and Caligrafy |
| --- | --- | --- |
| **1. Describe it** | your idea, in a sentence | a plan: the tables, every feature, who may do what, the pages |
| **2. Approve it** | read the plan, change it, say OK | build every feature once and check it |
| **3. Ship it** | approve the sign-ins in your browser | your GitHub repository, online at `https://<name>.caligrafy.com` |

Claude never asks for a password or a key, and asks before spending credits.
[Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude) has the details.

**Start from a proven prompt** on the [marketplace](https://caligrafy.com/marketplace), and sell yours there once it is
online (`/caligrafy-quill:marketplace`, 25% of every sale).

<br/>

## By hand

You need Git and Docker Desktop (running):
```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp
cd myapp
.bin/caligrafer quickstart --docker --example
```
Open http://localhost:8080/tasks. The [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart) then
takes you through the same app over REST and from an AI agent, and puts it online with `php caligrafer.php cloud deploy`.

A feature, by hand, is four small pieces:

| Piece | File | Example |
| --- | --- | --- |
| A table | `application/migrations/` | `php caligrafer.php migrate make create_books`, then `php caligrafer.php migrate` |
| A model | `application/models/Book.php` | `class Book extends Model {}` |
| A capability | `application/services/` | `#[Capability(name: 'list_books', readOnly: true)]` with `Book::with('author')->paginate(20)` |
| Its routes | `application/config/routing/web.php` | `Route::page('/books', 'Books/index', ['data' => ['books' => 'list_books']])` |

The same capability is then a REST endpoint (`Route::capabilities()`), a tool for AI agents (`Route::mcp()`) and a
function of the generated JavaScript client. Forms, sign-in, webhooks and downloads are one route each
(`Route::form`, `Route::auth`, `Route::webhook`, `Route::download`), and `Route::action` answers whatever a page or a form
cannot say. Every request is declared: an application needs no controller.
[Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability) builds one, page
included.

<br/>

## A complete app

Every feature is written once, as a **capability** (its inputs, rules and permissions), and served everywhere:
**web pages**, **Vue apps** on desktop, tablet and phone, a **REST API**, and **AI agents** such as Claude or Cursor
over MCP. Same rules, same permissions, same audit log, whichever way it is called. A capability can also run on a
timer ([scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs)), reach your users with the
app closed ([notifications](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications): web push and email), or
run later, outside the request ([background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs),
with retries).

| | |
| --- | --- |
| **Data** | [models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models), [queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries) (filters, paging, related rows, every value bound), [migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations), [the cache](https://github.com/caligrafy/caligrafy-quill/wiki/Caching) |
| **Pages** | [Pug views and page components](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views), [declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes), [design styles](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles), install to home screen |
| **People** | [sign-in, passkeys, permissions and tokens](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication) |
| **In the box** | [payments](https://github.com/caligrafy/caligrafy-quill/wiki/9.2-Stripe-&-Cryptocurrency-Payment), AI (OpenAI, open models, Watson), face detection, machine learning, [social cards](https://github.com/caligrafy/caligrafy-quill/wiki/9.3-Metadata-&-Social-Rich-Cards), a production `Dockerfile` |
| **Quality** | [tests](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App) that call capabilities the way every channel does, `php caligrafer.php doctor` |

See [How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works).

**Fast.** In the same benchmark, Caligrafy 2.19 beats Symfony 8.1 and Laravel 13 on all 16 measures (requests per
second, median and slowest response, memory, for plain text, JSON, a database query and an HTML page). An HTML page:
2,887 requests per second, against 1,789 for Symfony and 324 for Laravel
([release notes](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.19.0)).

<br/>

## Learn

| If you want to | Read |
| --- | --- |
| See everything Caligrafy does | [The documentation](https://github.com/caligrafy/caligrafy-quill/wiki/) |
| Learn step by step, with Claude or by hand | [The Caligrafy course](https://github.com/caligrafy/caligrafy-quill-course) |
| Build a feature | [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability) |
| Every command | [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer) |
| Put it online | [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online): Caligrafy Cloud or your own server |
| See what changed | [Changelog](CHANGELOG.md) |
| Contribute | [Contributing](CONTRIBUTING.md) and [how the framework is built](ARCHITECTURE.md) |

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
