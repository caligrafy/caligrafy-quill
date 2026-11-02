# Caligrafy Quill project

This is a Caligrafy Quill PHP MVC project. The `caligrafy-quill` Claude Code plugin ships in `claude-plugin/` and is
enabled by `.claude/settings.json` once the folder is trusted. Its `caligrafy` skill holds the full framework
documentation, a source-verified API reference and known gotchas: consult it before writing Caligrafy code.

- Your code: `application/` (routes in `application/config/routing/web.php`, controllers, models, Pug views) and `public/` (client apps).
- Only capabilities touch models: pages use `Route::page('/books/{id}', 'Books/show', ['data' => ['book' => 'get_book']])`, controllers call `$this->call()`.
- Framework internals: `framework/` - change only when asked.
- Tools: `/caligrafy-quill:idea` (from an idea to an application online), `/caligrafy-quill:new-capability` (one feature for pages, REST, Vue apps and AI agents), `/caligrafy-quill:new-resource`, `/caligrafy-quill:new-app`, `/caligrafy-quill:server`, `/caligrafy-quill:db`, `/caligrafy-quill:design` (another look for every page, `DESIGN_STYLE`), `/caligrafy-quill:ship` (GitHub and Caligrafy Cloud, no hand-offs), `/caligrafy-quill:deploy` (readiness, own server), `/caligrafy-quill:marketplace` (sell a recipe of the app on caligrafy.com), `/caligrafy-quill:update` (to the latest release), agent `caligrafy-reviewer`.
- `php caligrafer.php initialize --yes` deletes `.git` (without `--yes` it keeps it); ask before running it in a git repository.
- Never print or commit secrets from `.env`.
