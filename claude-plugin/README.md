# caligrafy-quill - Claude Code plugin

Gives Claude Code full knowledge of the [Caligrafy Quill](https://github.com/caligrafy/caligrafy-quill) framework.

| Component | Name | What it does |
| - | - | - |
| Skill (auto) | `caligrafy` | Index of the complete Caligrafy wiki (all 33 pages in `skills/caligrafy/reference/`), a source-verified API reference and a list of verified gotchas/wiki errata. Loads whenever Claude works in a Quill project |
| Skill | `/caligrafy-quill:new-resource <Entity> [field:type ...]` | SQL `create_db.sql` + Model + Controller (filter/validate CRUD) + REST routes |
| Skill | `/caligrafy-quill:new-app <app\|create\|bot\|facedetect\|ml> <name>` | Scaffolds a client app with caligrafer and wires its page and data routes |
| Skill | `/caligrafy-quill:server <start\|stop\|status>` | Docker dev server via `.bin/caligrafer`, with port-clash and `.env` checks |
| Skill | `/caligrafy-quill:db <script.sql\|tables\|query "...">` | Runs SQL scripts in the `mysql` container (or local MySQL), with safety checks for destructive scripts |
| Agent | `caligrafy-reviewer` | Reviews Caligrafy code against the framework's real behavior |
| Hook | PostToolUse `php -l` | Syntax-checks every `.php` file Claude writes or edits (skipped when PHP is not installed) |

## Install

It installs itself: the Quill repository ships `.claude/settings.json`, which registers this folder's marketplace
(`.claude-plugin/marketplace.json` at the repo root) and enables `caligrafy-quill@caligrafy`. Open the project in
Claude Code and accept the folder trust prompt.

Manual install from GitHub:
```
/plugin marketplace add caligrafy/caligrafy-quill
/plugin install caligrafy-quill@caligrafy
```
or from a shell: `claude plugin marketplace add caligrafy/caligrafy-quill && claude plugin install caligrafy-quill@caligrafy`.

Local development: `claude --plugin-dir ./claude-plugin`, and `claude plugin validate ./claude-plugin --strict`.

## Updating the knowledge

The files in `skills/caligrafy/reference/0*.md` mirror the wiki (https://github.com/caligrafy/caligrafy-quill/wiki)
with a source link at the top of each. `api-reference.md` and `gotchas.md` are written from the framework source;
update them when `framework/` changes, and bump `version` in `.claude-plugin/plugin.json`.
