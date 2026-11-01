---
name: idea
description: From an idea to an application online, in one conversation and one confirmation - turn the user's idea into a plan, set up a Caligrafy Quill project, write the tables and capabilities once, serve them to a web page, a REST API, the JS client and AI agents (MCP), check every experience, then ship it to GitHub and https://<name>.caligrafy.com by itself (the ship skill). The user only approves in the browser. The Quickstart with Claude.
when_to_use: The user describes an application they want ("I want an app where...", "build me a ... app", "/caligrafy-quill:idea a reading list..."), asks Claude to build and deploy something from scratch with Caligrafy, or follows the Quickstart with Claude.
argument-hint: "<your idea, in a sentence or two>"
---

# From an idea to an application online

Idea: `$ARGUMENTS` (if empty, ask the user to describe it in a sentence or two: who uses it, what they do with it).

This is the Quickstart with Claude: you take the idea through the seven steps of Caligrafy (start, data, build
once, experience everywhere, people and trust, ship, online), **without stopping**. Keep it **small and finished**: 1 to 3 tables, 4 to 12
capabilities, one page per main table. A working first version online beats a big plan. Read
[ui-guidelines.md](../caligrafy/reference/ui-guidelines.md) and the Tasks example
(`framework/settings/examples/tasks/`: its services, model, `views/Tasks/index.pug` and `public/js/tasks.js`):
**copy its patterns**, they are verified.

**Ask once: the plan** (step 1). Its OK covers everything after it: deleting the framework's `.git`, building,
committing, creating the private GitHub repository and pushing, creating the app on Caligrafy Cloud with its deploy
key, and deploying. Then never ask the user to run a command, copy a key or come back: you do it. What only they can
do happens in their browser, which you open for them (approving GitHub's and Caligrafy Cloud's codes, signing up and
confirming their email the first time). The only other question: before spending credits. Never ask for, print or
write a password, token or key.

Run every `php caligrafer.php` command in the Docker server: `docker compose exec dev-box php caligrafer.php ...`
(`-T` when the command reads no keyboard).

## 1. The plan (the one confirmation)

Turn the idea into a short plan and show it as tables, then wait for the user's OK or changes. End it with what the
OK lets you do: "I'll then set up the project (it replaces the framework's git history with your app's), build and
check it, create the private repository `<owner>/<name>` on GitHub and put it online at `https://<name>.caligrafy.com`.
You'll only approve sign-ins in your browser; I'll ask before spending any credits."

| Part | Decide |
| --- | --- |
| Name | 3 to 30 lowercase letters, digits and dashes, starting with a letter: it becomes `https://<name>.caligrafy.com` |
| Tables | each table, its columns and types, links between tables (foreign keys) |
| Capabilities | `list_<things>`, `get_<thing>`, `create_<thing>`, `update_<thing>`, `delete_<thing>` per table, plus the actions the idea needs (`mark_read`, `rate_book`...); for each: what it does, read-only or destructive, its scope `<things>:read` / `<things>:write` |
| Who may do what | **Default: anyone can read, people who signed up change** (sign-in pages on; writes are not `public`). The other choice, everything `public` like the Tasks example, is for a demo only: say that online, anyone could change or delete the data |
| Pages | one page per main table at `/<things>`: a form, the list with its actions, an empty state; `/` opens the main page |
| Sample data | 3 to 6 rows so the app is not empty, online too |

## 2. Start

- Check Docker is running (`docker info`) and that ports 8080, 8077 and 3306 are free (`lsof -nP -iTCP:<port> -sTCP:LISTEN`);
  if one is taken (another project), use a `docker-compose.override.yml` with other host ports as the
  [server skill](../server/SKILL.md) says, and use those ports in every URL (quickstart still prints `8080`, G78).
- If `application/` does not exist, set the project up: `.bin/caligrafer quickstart` (without `--example`). It asks to
  delete the framework's `.git`: answer yes (the plan's OK covers it; the app gets its own history). In a folder that
  is not a clone of the framework, never delete a `.git`. If the project is set up already, check it with
  `docker compose exec dev-box php caligrafer.php doctor` and build inside it.
- Needs Caligrafy Quill 2.7.0+ (`php caligrafer.php version`) for step 7 without hand-offs (2.4.0+ deploys, with a
  pasted token).

## 3. Build once

For each table (parents first):
1. `application/models/<Folder>/create_db.sql`: `CREATE TABLE IF NOT EXISTS`, `id` + `created_at` + `modified_at`
   like the Tasks example, foreign keys, InnoDB utf8mb4. It runs again at every deploy when it changes: never
   `DROP`, `DELETE` or `TRUNCATE` (G68). Folders run in alphabetical order online: name them so that a parent's
   folder sorts before its children's, or put every table in one folder's script, parents first.
2. The model `application/models/<Folder>/<Thing>.php` (see the Tasks model).
3. Run it: `docker compose exec dev-box php caligrafer.php sql application/models/<Folder>/create_db.sql`.

**Sample data**: `INSERT IGNORE` rows with explicit ids at the end of the `create_db.sql` that runs last (its folder
sorts last), so they exist online too and running it again changes nothing. Never put a password or a person's real
data in it.

**Capabilities**: one command per capability,
`docker compose exec dev-box php caligrafer.php service <Folder>/<PascalName> --scope <scope> [--read-only]` (the scope
is required: without it the attribute gets `scope: ''`, G76), then fill it as `/caligrafy-quill:new-capability` says (a description written for an AI agent, `$rules` for every
input, bound queries, `ServiceError` refusals, `readOnly`, `destructive`, `public: true` only for reads anyone may do).

**Sign-in** (unless everything is public): in `web.php` uncomment `Auth::authentication('User', 'users');` and the
authentication routes (`/login`, `/logout`, `/notAuthorized`, `/register`, both `POST`s), and make `/home` (where
sign-in lands) the main page: `Route::page('/home', '<Folder>/index');` instead of `AuthController@home`. Run `application/models/Auth/create_db.sql`. Add to
the main page a line with *Sign in* / *Sign up* links for visitors and *Sign out* for signed-in people. Every page is a
`Route::page` (`/`, `/<things>`, `/home`): it gives the view `$authorized` and `$user` (G74); a closure that returns
`view()` does not. The sign-up page accepts usernames of letters and digits only (no email
address, G75): say so next to the *Sign up* link. A capability that is not `public` is refused (401) to visitors: the page
shows its message in `.cq-error`.

**A token page** (so AI agents can use the app online, where there is no command line, G79): a capability
`create_my_token` in `application/services/Tokens/CreateMyToken.php`, for signed-in people only:
```php
#[Capability(name: 'create_my_token', description: 'Creates a personal access token of the signed-in person, for an AI agent or a program. It is shown once.', scope: 'tokens:write')]
class CreateMyToken extends Service
{
    public $rules = array('name' => 'required|max_len,100');
    public $filters = array('name' => 'trim');
    public $descriptions = array('name' => 'What it is for, for example "Claude"');

    public function handle(array $input, Context $context)
    {
        if ($context->token !== null || !$context->user) {
            throw ServiceError::forbidden('Create tokens on the site, signed in');
        }
        $created = Tokens::create($input['name'], array(/* every scope of the app's capabilities */), $context->user->id, time() + 86400 * 90);
        return array('token' => $created['token'], 'expires_in_days' => 90, 'shown_once' => true);
    }
}
```
(`use Caligrafy\Capability; use Caligrafy\Context; use Caligrafy\Service; use Caligrafy\ServiceError; use Caligrafy\Tokens;`).
It still appears in the MCP `tools/list` of a token with `tokens:write` or `*`: its refusal there is expected.
Add a small card **AI agents** to the main page, for signed-in people: a name field, a *Create a token* button that
calls it, then shows the token once and the line to paste:
`claude mcp add --transport http <name> <origin>/mcp --header "Authorization: Bearer <token>"` (the page fills in
`location.origin`). Skip it only when everything is public and the user does not want agents.

**Experiences** in `application/config/routing/web.php`, before `Route::run()`:
- the page: `Route::page('/<things>', '<Folder>/index');` and `Route::page('/', '<Folder>/index');` (a page that shows one
  record server-side: `Route::page('/<things>/{id}', '<Folder>/show', ['data' => ['<thing>' => 'get_<thing>'], 'notFound' => '<Folder>/missing'])`, read-only
  capabilities only, G83);
  never a controller that reads the models: only capabilities touch them;
- REST for every capability: `Route::capability('GET', '/api/<things>', 'list_<things>');`,
  `Route::capability('GET', '/api/<things>/{id}', 'get_<thing>');`, `POST`, `PUT /{id}`, `DELETE /{id}`...;
- `Route::capabilities('/capabilities');` and `Route::mcp('/mcp');` (quickstart adds them: keep one of each);
- the JS client: `docker compose exec dev-box php caligrafer.php client` (run it again after any capability change).

**The page** `application/views/<Folder>/index.pug` + `public/js/<things>.js`: the page components only
(`main.cq-page > .cq-card`, `.cq-form`, `.cq-list` / `.cq-item` / `.cq-actions`, `.cq-empty`, `.cq-error`), the JS client
for every call (never `fetch` by hand, never a key in the page), like the Tasks example. Tabs with counts are
optional: keep them only when the list capability gives what they need. No `style` block, no
Bootstrap colors.

## 4. Check every experience (before saying it works)

- `docker compose exec dev-box php caligrafer.php capabilities`: every capability, with the right flags.
- `php caligrafer.php call <name> key=value` for each one; an invalid input gives the field messages; `--guest` is
  refused for the writes (unless public).
- REST: `curl -s -H "Accept: application/json" http://localhost:8080/api/<things>`; a write without sign-in gets 401.
- The page: open `http://localhost:8080/<things>` (in a browser if you have one): sign up, add, change, delete; check it
  at phone width (375px).
- MCP: with a local token (`php caligrafer.php token create "check" --scopes "*"`, revoke it afterwards with
  `token revoke <id>`, the number after `#` in `token list`, G77), `tools/list` shows the capabilities and `tools/call` works.
- `docker compose exec dev-box php caligrafer.php doctor`, and ask the `caligrafy-reviewer` agent to review the new files.
Fix what fails before going on.

## 5. Experience everywhere (show the user, locally)

A short table: the page `http://localhost:8080/<things>`, the REST API (`curl` line), AI agents (`claude mcp add ...`
with a local token the user creates with `token create`, or the token card of the page), and the phone: the page
installs to the home screen once online (HTTPS). Then go on: the plan's OK covers putting it online.

## 6. Ship and 7. Online: the ship skill

Follow the [ship skill](../ship/SKILL.md) with `<name>`, from its step 1: commit, the private GitHub repository (SSH
first, then HTTPS, then a browser approval), the Caligrafy Cloud sign-in by browser approval, `cloud create`, the
deploy key added by you, `cloud deploy`, the check online. Every new app is free for its first 30 minutes online: ask
before spending credits when they are used. Pages call their own routes with the session (the JS client, or
`apiHeaders()`), never with the API key: online it is turned off (G87).

## 8. Done: the same application, everywhere, online

Tell the user, with their real addresses:

| Experience | Where |
| --- | --- |
| Web page (and the phone: *Add to Home Screen*) | `https://<name>.caligrafy.com/` |
| REST API | `curl https://<name>.caligrafy.com/api/<things>` |
| AI agents | sign in, create a token on the page, then the `claude mcp add` line it shows |
| The code | the GitHub repository; after a change: commit, `github.sh push`, `cloud deploy <name>` (the ship skill) |

and what comes next: `cloud status <name>` (time left), `cloud extend <name> week` to keep it online, a Vue app or more
features with `/caligrafy-quill:new-capability`, the course for learning each step by hand.
