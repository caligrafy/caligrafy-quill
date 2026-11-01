---
name: idea
description: From an idea to an application online, in one conversation - set up a Caligrafy Quill project, turn the user's idea into tables and capabilities written once, serve them to a web page, a REST API, the JS client and AI agents (MCP), check every experience, then put it online at https://<name>.caligrafy.com with Caligrafy Cloud. The Quickstart with Claude.
when_to_use: The user describes an application they want ("I want an app where...", "build me a ... app", "/caligrafy-quill:idea a reading list..."), asks Claude to build and deploy something from scratch with Caligrafy, or follows the Quickstart with Claude.
argument-hint: "<your idea, in a sentence or two>"
---

# From an idea to an application online

Idea: `$ARGUMENTS` (if empty, ask the user to describe it in a sentence or two: who uses it, what they do with it).

This is the Quickstart with Claude: you take the idea through the seven steps of Caligrafy (start, data, build
once, experience everywhere, people and trust, ship, online). Keep it **small and finished**: 1 to 3 tables, 4 to 12
capabilities, one page per main table. A working first version online beats a big plan. Read
[ui-guidelines.md](../caligrafy/reference/ui-guidelines.md) and the Tasks example
(`framework/settings/examples/tasks/`: its services, model, `views/Tasks/index.pug` and `public/js/tasks.js`):
**copy its patterns**, they are verified.

**Ask the user only at these points**: the plan (step 2), before `quickstart` deletes the framework's `.git`, before
creating or pushing a GitHub repository, and for what only they can do (sign in to GitHub, `cloud login`, adding a
deploy key, paying). Never ask for, print or write a password, token or key: the user types those themselves.

Run every `php caligrafer.php` command in the Docker server: `docker compose exec dev-box php caligrafer.php ...`
(`-T` when the command reads no keyboard).

## 1. Start

- Check Docker is running (`docker info`) and that ports 8080, 8077 and 3306 are free (`lsof -nP -iTCP:<port> -sTCP:LISTEN`);
  if one is taken (another project), use a `docker-compose.override.yml` with other host ports as the
  [server skill](../server/SKILL.md) says, and use those ports in every URL (quickstart still prints `8080`).
- If `application/` does not exist, set the project up:
  `.bin/caligrafer quickstart` (without `--example`). It asks to delete the framework's `.git`: say why (the app gets
  its own history) and answer only with the user's OK. If the project is set up already, check it with
  `docker compose exec dev-box php caligrafer.php doctor` and build inside it.
- Needs Caligrafy Quill 2.4.0+ (`php caligrafer.php version`) for step 7.

## 2. The plan (one confirmation)

Turn the idea into a short plan and show it as tables, then wait for the user's OK or changes:

| Part | Decide |
| --- | --- |
| Name | 3 to 30 lowercase letters, digits and dashes, starting with a letter: it becomes `https://<name>.caligrafy.com` |
| Tables | each table, its columns and types, links between tables (foreign keys) |
| Capabilities | `list_<things>`, `get_<thing>`, `create_<thing>`, `update_<thing>`, `delete_<thing>` per table, plus the actions the idea needs (`mark_read`, `rate_book`...); for each: what it does, read-only or destructive, its scope `<things>:read` / `<things>:write` |
| Who may do what | **Default: anyone can read, people who signed up change** (sign-in pages on; writes are not `public`). The other choice, everything `public` like the Tasks example, is for a demo only: say that online, anyone could change or delete the data |
| Pages | one page per main table at `/<things>`: a form, the list with its actions, an empty state; `/` opens the main page |
| Sample data | 3 to 6 rows so the app is not empty, online too |

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
is required: without it the attribute gets `scope: ''`), then fill it as `/caligrafy-quill:new-capability` says (a description written for an AI agent, `$rules` for every
input, bound queries, `ServiceError` refusals, `readOnly`, `destructive`, `public: true` only for reads anyone may do).

**Sign-in** (unless everything is public): in `web.php` uncomment `Auth::authentication('User', 'users');` and the
authentication routes (`/login`, `/logout`, `/notAuthorized`, `/register`, both `POST`s), and point `/home` (where
sign-in lands) to the main page instead of `AuthController@home`. Run `application/models/Auth/create_db.sql`. Add to
the main page a line with *Sign in* / *Sign up* links for visitors and *Sign out* for signed-in people. A view gets
`$authorized` only when its route passes it: `Route::get('/<things>', function () { return view('<Folder>/index', array('authorized' => authorized())); });`,
and the same for `/` and `/home`. The sign-up page accepts usernames of letters and digits only (no email address):
say so next to the *Sign up* link. A capability that is not `public` is refused (401) to visitors: the page
shows its message in `.cq-error`.

**A token page** (so AI agents can use the app online, where there is no command line): a capability
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
        $created = Tokens::create($input['name'], array(/* every scope of the app's capabilities */), $context->user->id, date('Y-m-d H:i:s', strtotime('+90 days')));
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
- the page: `Route::get('/<things>', function () { return view('<Folder>/index'); });` and `/` to the same view;
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
  `token revoke <id>`, the number after `#` in `token list`), `tools/list` shows the capabilities and `tools/call` works.
- `docker compose exec dev-box php caligrafer.php doctor`, and ask the `caligrafy-reviewer` agent to review the new files.
Fix what fails before going on.

## 5. Experience everywhere (show the user, locally)

A short table: the page `http://localhost:8080/<things>`, the REST API (`curl` line), AI agents (`claude mcp add ...`
with a local token the user creates with `token create`, or the token card of the page), and the phone: the page
installs to the home screen once online (HTTPS). Then ask whether to put it online.

## 6. Ship

Commit the work (`git init` if needed, `git add -A`, a clear message). `.env` must not be tracked
(`git ls-files | grep -E '(^|/)\.env'` prints nothing but `.env.example`).

## 7. Online

Follow the [deploy skill](../deploy/SKILL.md), step 1 then 2a:
- **GitHub**: if `gh auth status` works, propose `gh repo create <name> --private --source . --push` and run it on the
  user's OK; otherwise ask the user to create an empty repository on GitHub (no README) and give you its URL, then
  `git remote add origin <url>` and `git push -u origin main` (or `master`) on their OK.
- **Caligrafy Cloud**: the user signs up at https://deploy.caligrafy.com and runs `php caligrafer.php cloud login` in
  their own terminal (`docker compose exec dev-box php caligrafer.php cloud login` works too). Then
  `cloud create <name> --repo https://github.com/<owner>/<name>` (the dev container has no git remote to read), the
  deploy key for a private repository (the user adds it on GitHub), and `cloud deploy <name>`.
- The first 30 minutes of an account are free; after that, deploying needs time bought with credits
  (`cloud credits`, `cloud extend`): say so before, never pay or spend credits without the user's OK.

## 8. Done: the same application, everywhere, online

Tell the user, with their real addresses:

| Experience | Where |
| --- | --- |
| Web page (and the phone: *Add to Home Screen*) | `https://<name>.caligrafy.com/` |
| REST API | `curl https://<name>.caligrafy.com/api/<things>` |
| AI agents | sign in, create a token on the page, then the `claude mcp add` line it shows |
| The code | the GitHub repository; change it, push, `cloud deploy <name>` |

and what comes next: `cloud status <name>` (time left), `cloud extend <name> week` to keep it online, a Vue app or more
features with `/caligrafy-quill:new-capability`, the course for learning each step by hand.
