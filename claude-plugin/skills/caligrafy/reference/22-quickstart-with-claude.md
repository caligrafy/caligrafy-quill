# Quickstart with Claude: from an idea to an application online

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Describe your idea in a sentence. [Claude Code](https://claude.com/claude-code) builds it the Caligrafy way, every feature written once, checks it from every side, and puts it online at `https://<name>.caligrafy.com`. You then use the same application from a web page, your phone, other programs over REST and AI agents.

The [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart) shows Caligrafy with a ready-made example in 5 minutes. This page goes from **your** idea to an application online, usually in 15 to 30 minutes, most of it Claude working.

## What you need

+ [Git](https://git-scm.com/) and [Docker Desktop](https://www.docker.com/products/docker-desktop/), running
+ [Claude Code](https://claude.com/claude-code)
+ A [GitHub](https://github.com/) account (the [GitHub CLI](https://cli.github.com/), `gh`, makes creating the repository one step)
+ An account on [Caligrafy Cloud](https://deploy.caligrafy.com/signup): the first 30 minutes online are free

## Start

```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp
cd myapp
claude
```
Trust the folder when Claude Code asks: that turns on the Caligrafy plugin that ships in the project. Then give it your idea:

```
/caligrafy-quill:idea A reading list: I add books I want to read with their author, mark them as read, and rate them from 1 to 5.
```

## What Claude does, and when it asks you

| Step | What happens | You |
| --- | --- | --- |
| **1. Start** | sets the project up with `quickstart`: the Docker server, the database, the framework tables | OK to delete the framework's `.git` (your app gets its own history) |
| **2. The plan** | turns the idea into a name, tables, capabilities, who may do what, pages and sample data | read it, change it, say OK |
| **3. Build once** | writes the tables, each feature once as a capability, sign-in, the page, the REST routes, MCP for agents | nothing |
| **4. Check** | calls every capability, the REST API with and without sign-in, the MCP tools, `doctor`, a code review | nothing |
| **5. Everywhere, locally** | shows you the page at `http://localhost:8080`, a `curl` line, the line to connect an agent | try it |
| **6. Ship** | commits the code; creates your GitHub repository and pushes it | OK to create and push; sign in to GitHub if asked |
| **7. Online** | creates the app on Caligrafy Cloud and deploys it | run `php caligrafer.php cloud login` with a token from the dashboard; add the deploy key on GitHub for a private repository |

Claude never asks you for a password, a token or a key in the chat: you type them in your own terminal or browser. It asks before anything that costs money.

## What you get

| Experience | Where |
| --- | --- |
| **Web page** | `https://<name>.caligrafy.com/`: sign up, then add and change your data |
| **Phone and tablet** | the same address, *Add to Home Screen*: it opens like an app |
| **REST API** for other programs | `curl https://<name>.caligrafy.com/api/<things>` |
| **AI agents** (Claude, Cursor...) | sign in, create a token on the page, then paste the `claude mcp add ...` line it shows |
| **The code** | your GitHub repository: a normal Caligrafy project you can read, change and keep building |

Every experience calls the same capabilities: the same rules, the same permissions, the same error messages. By default anyone can read and people who signed up change the data; ask Claude for something else at the plan.

## Next

+ **Change it:** ask Claude for a new feature (`/caligrafy-quill:new-capability`), or edit the code yourself; then `git push` and `php caligrafer.php cloud deploy <name>`.
+ **Keep it online:** `php caligrafer.php cloud status <name>` shows the time left; `cloud extend <name> week` (2 credits) keeps it online a week. See [Caligrafy Cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#cloud).
+ **Your own server instead:** `/caligrafy-quill:deploy <name> --own-server` prepares it for Coolify or Docker ([your own server](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#ownserver)).
+ **Learn what Claude did:** [How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works) in 5 minutes, or [the course](https://github.com/caligrafy/caligrafy-quill-course/wiki), which builds an application step by step, by hand or with Claude.

## If something does not work

| You see | What to do |
| --- | --- |
| `/caligrafy-quill:idea` is not a command | trust the folder (restart `claude` in `myapp`), or run `/plugin` and enable `caligrafy-quill` |
| Docker is not running | start Docker Desktop, then tell Claude to go on |
| A port is already in use (8080, 8077, 3306) | Claude proposes other ports with `docker-compose.override.yml`; see the [server skill](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin) |
| Sign-up refuses your email address | usernames are letters and digits only, for example `sam42` |
| The name is taken on Caligrafy Cloud | give Claude another name |
| The free 30 minutes are used | buy credits on the dashboard (`cloud credits 5`), then `cloud extend <name> week` |

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
