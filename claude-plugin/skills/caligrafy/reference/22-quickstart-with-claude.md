# Quickstart with Claude: from an idea to an application online

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

**Describe it. Approve it. Ship it.** You describe an application to [Claude Code](https://claude.com/claude-code) in a sentence, approve the plan Claude proposes, and Claude builds it the Caligrafy way (every feature written once, as a capability), checks it from every side, and puts it online at `https://<name>.caligrafy.com`. You then use the same application from a web page, your phone, other programs over REST and AI agents.

It usually takes 15 to 30 minutes, most of it Claude working. The [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart) does the setup by hand, with a ready-made example, in 5 minutes.

**Start from a proven prompt.** The [marketplace of caligrafy.com](https://caligrafy.com/marketplace) sells the prompts behind apps that already shipped: give one to `/caligrafy-quill:idea` instead of your own sentence.

## What you need

+ [Git](https://git-scm.com/) and [Docker Desktop](https://www.docker.com/products/docker-desktop/), running
+ [Claude Code](https://claude.com/claude-code)
+ A [GitHub](https://github.com/) account. An SSH key on GitHub, or the [GitHub CLI](https://cli.github.com/) signed in, saves one step; without them, Claude installs the GitHub CLI (with Homebrew) and signs it in with a code you approve in your browser
+ An account on [Caligrafy Cloud](https://deploy.caligrafy.com/signup), or create it when Claude opens the page. Every new app is free for its first 30 minutes online

## Start

```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp
cd myapp
claude
```
Trust the folder when Claude Code asks: that turns on the Caligrafy plugin that ships in the project (`.claude/settings.json`). Then give it your idea:

```text
/caligrafy-quill:idea A reading list: I add books I want to read with their author, mark them as read, and rate them from 1 to 5.
```

## Describe it. Approve it. Ship it.

You approve **once**, at the plan. Your OK covers the rest: setting the project up (the framework's `.git` makes way for your app's own history), building it, creating a private GitHub repository and putting it online. After that Claude does not ask you to run a command or copy a key: it opens your browser when only you can approve something, and asks before spending credits.

| | What Claude does | What you do |
| --- | --- | --- |
| **1. Describe it** | | |
| The plan | turns the idea into a name, the tables, the capabilities (`list_`, `get_`, `create_`, `update_`, `delete_` per table, plus the actions the idea needs), who may do what (by default anyone reads and people who signed up change), the pages and some sample data; says what your OK lets it do | your idea, in a sentence |
| **2. Approve it** | | |
| Your OK | | read the plan, change it, say OK. Say "stop before putting it online" to review it locally first |
| Start | checks Docker and the ports, then `.bin/caligrafer quickstart`: the Docker server, the database, the framework tables, the `/capabilities` and `/mcp` routes | nothing |
| Build once | for each table a `create_db.sql` and a model; each feature as a capability (`php caligrafer.php service ...`); sign-in with `Route::auth`; the pages with `Route::page` and `Route::form`; the REST routes; the JavaScript client; a page where signed-in people create a token for their AI agents; tests | nothing |
| Check | `capabilities`, `call` on every capability, REST with and without sign-in, the page at desktop and phone width, MCP `tools/list` and `tools/call`, `test`, `doctor`, a review by the `caligrafy-reviewer` agent | nothing |
| Everywhere, locally | shows you the page at `http://localhost:8080`, a `curl` line and the line that connects an agent | try it if you like |
| **3. Ship it** (the `ship` skill) | | |
| GitHub | commits, creates the private repository and pushes: with your SSH key when GitHub knows it, else over HTTPS with the GitHub CLI, else after a browser approval | only if needed: enter the code GitHub shows in the page Claude opens |
| Online | signs in to Caligrafy Cloud (`cloud login`), creates the app, adds its read-only deploy key to the repository, deploys and checks the address | approve the code on the Caligrafy Cloud page Claude opens (sign up and confirm your email the first time) |

Claude never asks you for a password, a token or a key, and never prints one: approvals happen on GitHub's and Caligrafy Cloud's own pages. `/caligrafy-quill:ship <name>` puts any Caligrafy project online the same way, and puts it online again after a change.

### Check

Before you say OK to the plan:
+ the name is the one you want: it becomes `https://<name>.caligrafy.com` and the GitHub repository;
+ each table and capability is one you need, and nothing that changes data is open to everyone unless you want that.

Before you call it done:
+ the address Claude reports opens, you can sign up and sign in, and the main page adds and changes your data;
+ the `curl` line answers with your data;
+ `doctor` and the tests passed in Claude's report.

## What you get

| Experience | Where |
| --- | --- |
| **Web page** | `https://<name>.caligrafy.com/`: sign up, then add and change your data |
| **Phone and tablet** | the same address, *Add to Home Screen*: it opens like an app |
| **REST API** for other programs | `curl -H "Accept: application/json" https://<name>.caligrafy.com/api/<things>` |
| **AI agents** (Claude, Cursor...) | sign in, create a token on the page, then paste the `claude mcp add ...` line it shows |
| **The code** | your GitHub repository: a Caligrafy project you can read, change and keep building, by hand or with Claude |

Every experience calls the same capabilities: the same rules, the same permissions, the same error messages.

## By hand

Every step Claude takes is a step you can take yourself: the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart) sets the project up, [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability) builds a feature with its page, REST API, agent tools, sign-in and test, and [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online) ships it. The [course](https://github.com/caligrafy/caligrafy-quill-course/wiki) builds a whole application step by step.

## Next

+ **Change it:** ask Claude for a new feature (`/caligrafy-quill:new-capability`), or edit the code yourself. A change to a table that already has data goes in a [migration](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations), which the deploy runs. Then `/caligrafy-quill:ship <name>`.
+ **Keep it online:** `php caligrafer.php cloud status <name>` shows the time left; `php caligrafer.php cloud extend <name> week` (2 credits) keeps it online a week. See [Caligrafy Cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#cloud).
+ **Your own server instead:** `/caligrafy-quill:deploy <name> --own-server` prepares it for Coolify or Docker ([your own server](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#ownserver)).
+ **Learn what Claude did:** [How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works) in 5 minutes. Every skill is on the [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin) page.

## Troubleshooting

| You see | Do this |
| --- | --- |
| `/caligrafy-quill:idea` is not a command | in `myapp`, run `php caligrafer.php plugin` (with PHP on your computer) or `.bin/caligrafer quickstart` (with Docker): it registers the project's plugin with Claude Code. Then restart `claude` there |
| Docker is not running | start Docker Desktop, then tell Claude to go on |
| A port is already in use (8080, 8077, 3306) | Claude gives this project other ports with a `docker-compose.override.yml` and uses them in every address |
| Your app's sign-up refuses an email address | your app's usernames are letters and digits only, for example `sam42` (Caligrafy Cloud's own accounts use your email) |
| The name is taken on Caligrafy Cloud or GitHub | Claude proposes another name |
| The browser page did not open | Claude prints its address: open it, approve the code, and Claude goes on |
| Caligrafy Cloud says *Confirm your email first* | open the link of the email from Caligrafy (look in spam), then sign in on the same page; it can send the link again |
| You forgot your Caligrafy password | *Forgot your password?* on the sign-in page emails a 6-digit code, then you choose a new password |
| The free 30 minutes of the app are used | say yes when Claude asks to spend credits; to buy some, Claude opens the Stripe page (`cloud credits 5`) |
| The page works locally but its calls are refused online (`[ERROR 1004] Access Denied: the legacy API key is turned off`) | the page sends the shared API key, which Caligrafy Cloud turns off: ask Claude to call the routes with the JavaScript client or `apiHeaders()` |

## Related pages

+ [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart)
+ [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin)
+ [How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works)
+ [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online)

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
