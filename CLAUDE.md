# Caligrafy Quill project

This is a Caligrafy Quill PHP MVC project. The `caligrafy-quill` Claude Code plugin ships in `claude-plugin/` and is
enabled by `.claude/settings.json` once the folder is trusted. Its `caligrafy` skill holds the full framework
documentation, a source-verified API reference and known gotchas: consult it before writing Caligrafy code.

- Your code: `application/` (routes in `application/config/routing/web.php`, controllers, models, Pug views) and `public/` (client apps).
- Framework internals: `framework/` - change only when asked.
- Tools: `/caligrafy-quill:new-capability` (one feature for pages, REST, Vue apps and AI agents), `/caligrafy-quill:new-resource`, `/caligrafy-quill:new-app`, `/caligrafy-quill:server`, `/caligrafy-quill:db`, agent `caligrafy-reviewer`.
- `php caligrafer.php initialize` deletes `.git`; ask before running it in a git repository.
- Never print or commit secrets from `.env`.
