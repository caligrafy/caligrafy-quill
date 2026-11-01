# Quickstart with Claude: from an idea to an application online

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Describe your idea in a sentence. [Claude Code](https://claude.com/claude-code) builds it the Caligrafy way, every feature written once, checks it from every side, and puts it online at `https://<name>.caligrafy.com`. You then use the same application from a web page, your phone, other programs over REST and AI agents.

The [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart) shows Caligrafy with a ready-made example in 5 minutes. This page goes from **your** idea to an application online, usually in 15 to 30 minutes, most of it Claude working.

## What you need

+ [Git](https://git-scm.com/) and [Docker Desktop](https://www.docker.com/products/docker-desktop/), running
+ [Claude Code](https://claude.com/claude-code)
+ A [GitHub](https://github.com/) account. An SSH key on GitHub or the [GitHub CLI](https://cli.github.com/) helps; without them, Claude signs the GitHub CLI in with an approval in your browser
+ An account on [Caligrafy Cloud](https://deploy.caligrafy.com/signup), or create it when Claude opens the page: every new app is free for its first 30 minutes online

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

Claude asks you **once**, at the plan. Your OK covers the rest: setting the project up, building it, creating a
private GitHub repository and putting it online. After that it never asks you to run a command or copy a key: it does
the work and opens your browser when only you can approve something.

| Step | What happens | You |
| --- | --- | --- |
| **1. The plan** | turns the idea into a name, tables, capabilities, who may do what, pages and sample data, and says what your OK lets it do | read it, change it, say OK |
| **2. Start** | sets the project up with `quickstart`: the Docker server, the database, the framework tables (your app gets its own git history) | nothing |
| **3. Build once** | writes the tables, each feature once as a capability, sign-in, the page, the REST routes, MCP for agents | nothing |
| **4. Check** | calls every capability, the REST API with and without sign-in, the MCP tools, `doctor`, a code review | nothing |
| **5. Everywhere, locally** | shows you the page at `http://localhost:8080`, a `curl` line, the line to connect an agent | try it if you like |
| **6. Ship** | commits the code, creates your private GitHub repository and pushes it: with your SSH key when GitHub knows it, else over HTTPS with the GitHub CLI | only if neither is set up: approve the code GitHub shows in the browser page Claude opens |
| **7. Online** | signs in to Caligrafy Cloud, creates the app, adds its read-only deploy key to the repository, deploys and checks it | approve the code on the Caligrafy Cloud page Claude opens (sign up and confirm your email the first time) |

Claude never asks you for a password, a token or a key: approvals happen in your browser, on GitHub's and Caligrafy
Cloud's own pages. It asks before spending credits. The online part is the [`ship` skill](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin):
`/caligrafy-quill:ship <name>` puts any Caligrafy project online the same way.

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

+ **Change it:** ask Claude for a new feature (`/caligrafy-quill:new-capability`), or edit the code yourself; then `/caligrafy-quill:ship <name>` (or `git push` and `php caligrafer.php cloud deploy <name>`).
+ **Keep it online:** `php caligrafer.php cloud status <name>` shows the time left; `cloud extend <name> week` (2 credits) keeps it online a week. Claude asks you before spending credits. See [Caligrafy Cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#cloud).
+ **Your own server instead:** `/caligrafy-quill:deploy <name> --own-server` prepares it for Coolify or Docker ([your own server](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#ownserver)).
+ **Learn what Claude did:** [How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works) in 5 minutes, or [the course](https://github.com/caligrafy/caligrafy-quill-course/wiki), which builds an application step by step, by hand or with Claude.

## If something does not work

| You see | What to do |
| --- | --- |
| `/caligrafy-quill:idea` is not a command | trust the folder (restart `claude` in `myapp`), or run `/plugin` and enable `caligrafy-quill` |
| Docker is not running | start Docker Desktop, then tell Claude to go on |
| A port is already in use (8080, 8077, 3306) | Claude proposes other ports with `docker-compose.override.yml`; see the [server skill](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin) |
| Sign-up refuses your email address | usernames are letters and digits only, for example `sam42` |
| The name is taken on Caligrafy Cloud or GitHub | Claude proposes another name |
| The browser page did not open | Claude prints its address: open it, approve the code, Claude goes on |
| The free 30 minutes of the app are used | say yes when Claude asks to spend credits; to buy some, Claude opens the Stripe page (`cloud credits 5`) |
| The page works locally but its calls are refused online (`[ERROR 1004] Access Denied: the legacy API key is turned off`) | the page sends the shared API key, which Caligrafy Cloud turns off: ask Claude to call the routes with `apiHeaders()` (2.7.0+) |

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
