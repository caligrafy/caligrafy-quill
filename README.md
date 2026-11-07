# Caligrafy - Describe it. Approve it. Ship it.
![Caligrafy: Describe it. Approve it. Ship it.](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/describe-approve-ship-banner.png)

**Your idea turned into a complete app.** Describe it to [Claude Code](https://claude.com/claude-code), approve the
plan, and Caligrafy ships it online, with the code in your own GitHub repository.

<br/>

## Get started quickly

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

## A complete app

Every feature is written once, as a **capability** (its inputs, rules and permissions), and served everywhere:
**web pages**, **Vue apps** on desktop, tablet and phone, a **REST API**, and **AI agents** such as Claude or Cursor
over MCP. Same rules, same permissions, same audit log, whichever way it is called. A capability can also run on a
timer, with no page open ([scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs)), and
reach your users with the app closed ([notifications](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications):
web push and email), or later, outside the request
([background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs), with retries). The database
changes go in [migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations), slow work in
[the cache](https://github.com/caligrafy/caligrafy-quill/wiki/Caching), and
[your tests](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App) call the capabilities the way every
channel does. Sign-in, tokens, payments, AI, design styles, a
[social card](https://github.com/caligrafy/caligrafy-quill/wiki/9.3-Metadata-&-Social-Rich-Cards) on every page and a
production `Dockerfile` come in the box. See
[How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works).

<br/>

## By hand

The same three steps without Claude, with Docker:
```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp
cd myapp
.bin/caligrafer quickstart --example
```
Open http://localhost:8080/tasks, then follow the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart) to use it over REST, from an AI agent,
and to ship it with `php caligrafer.php cloud deploy`.

<br/>

## Learn

| If you want to | Read |
| --- | --- |
| See everything Caligrafy does | [The documentation](https://github.com/caligrafy/caligrafy-quill/wiki/) |
| Learn step by step | [The Caligrafy course](https://github.com/caligrafy/caligrafy-quill-course) |
| Build a feature | [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability) |
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
