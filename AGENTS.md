# Caligrafy Quill: instructions for AI coding agents

This file is for any coding agent (Cursor, Codex, Copilot, Claude Code...). Claude Code also gets the
`caligrafy-quill` plugin from `.claude/settings.json`; other agents should read the same knowledge files below.

## What this project is
A Caligrafy Quill PHP MVC project (PHP 8.3+, MySQL via PDO, Pug views, GUMP validation, Vue 3 client apps).
Your code goes in `application/` (routes in `application/config/routing/web.php`, controllers, models, services,
Pug views) and `public/<app>/` (client apps). `framework/` is the framework itself: change it only when asked.

## Read before writing code
- `claude-plugin/skills/caligrafy/SKILL.md`: the index of the framework knowledge and the workflow rules.
- `claude-plugin/skills/caligrafy/reference/api-reference.md`: exact signatures, verified against the source.
- `claude-plugin/skills/caligrafy/reference/gotchas.md`: verified pitfalls G1-G60 (read the relevant ones).
- `claude-plugin/skills/caligrafy/reference/13-build-once-services-capabilities-mcp.md`: capabilities, tokens, MCP.
- `claude-plugin/skills/caligrafy/reference/ui-guidelines.md`: how every new page and client app looks (page components).
- `ARCHITECTURE.md`: how a request runs and the build-once design.
- Step-by-step procedures: `claude-plugin/skills/<new-resource | new-capability | new-app | server | db>/SKILL.md`.

## Build once, experience everywhere
A feature that more than one channel needs (Pug pages, a Vue app, a REST API, AI agents) is a **capability**:
`php caligrafer.php service <Folder>/<Name> --scope <resource:action>` creates a `Caligrafy\Service` with
`#[Capability]` in `application/services/`. Declare every input in `$rules`, return data from `handle()`, refuse with
`ServiceError`; never render, redirect, print or read the session there. Expose it with `$this->call()` (pages),
`Route::capability()` (REST), `Route::capabilities()` + `php caligrafer.php client` (Vue apps) and `Route::mcp()`
(agents). `php caligrafer.php capabilities` lists them.

## Rules
- Bound queries only (`?` placeholders and `'bindings'`): never concatenate request data into SQL.
- Every new page or client app UI uses the page components of `public/css/tokens.css` (`main.cq-page > .cq-card`, `.cq-form`,
  `.cq-tabs`, `.cq-list`): see `ui-guidelines.md`, and check it at desktop and phone width.
- Never put an API key or a personal access token in a page, a `VITE_` variable or a bundle: browser clients use the
  session through the generated JS client; programs and agents use tokens (`php caligrafer.php token create`).
- Never print or commit secrets from `.env`. `php caligrafer.php initialize` deletes `.git`: ask before running it.
- After changing PHP, run `php -l <file>`; check the project with `php caligrafer.php doctor` (read-only).
- Framework changes need the QA suite: `vendor/bin/phpunit` and `./qa/run.sh` (see `CONTRIBUTING.md`).
