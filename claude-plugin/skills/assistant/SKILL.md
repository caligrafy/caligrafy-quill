---
name: assistant
description: Give a Caligrafy Quill project Snap Cards and the assistant (2.23.0, Route::snap()) end to end - every capability becomes a card a person can use (an Ask card: Form, Choice or Confirm; a Show card: Answer or List; a Go card: Shortcut; a Track card: Task; an Offer card: Suggestion or Routine), and /assistant gives each signed-in person a Home that shows what needs them and a conversation that answers with those cards. Adds Route::snap(); to web.php, runs php caligrafer.php snap install, writes application/config/snap.php hints from the app's own capabilities, adds the queue and routines jobs, marks slow capabilities async, asks for an Anthropic API key, and checks /assistant and /snap.
when_to_use: The user asks for an assistant, Snap, Snap Cards, cards for their capabilities, a Home that shows what needs them, "let people ask the app in their own words", Route::snap, php caligrafer.php snap, or follows the wiki page "Snap Cards and the Assistant" ("/caligrafy-quill:assistant").
argument-hint: "[what the app is about, in a few words]"
---

# Snap Cards and the assistant

What the app is about: `$ARGUMENTS` (else read it from the README, `APP_NAME` and the capabilities). Read the wiki page
[Snap Cards and the Assistant](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant), the
Snap section of [api-reference.md](../caligrafy/reference/api-reference.md#snap) and G153-G159 of
[gotchas.md](../caligrafy/reference/gotchas.md). Snap needs Caligrafy Quill 2.23.0 or later
(`php caligrafer.php version`): older, run the `update` skill first.

In Docker, prefix every command with `docker compose exec dev-box` (from the project root). Say which step you are in.

## 1. The route

In `application/config/routing/web.php`, after `Auth::authentication(...)` (the assistant is for signed-in people,
G154) and before `Route::run()`, add once:

```php
// Snap Cards and the assistant: every capability as a card, and an assistant that answers with them
Route::snap();
```

Other addresses only when the user asks: `Route::snap(['assistant' => '/help', 'cards' => '/cards'])`.

## 2. The tables

```bash
php caligrafer.php snap install
```

It creates the `caligrafy_snap_*` tables (from `framework/settings/platform.sql`) and a starter
`application/config/snap.php` when there is none, then prints what is ready. A database error: check `DB_ACTIVATE`
and `DB_*` in `.env` (the `server` skill). The test database needs them too: `platform install` on it (G158).

## 3. The hints: `application/config/snap.php`

Cards work without any hint; hints make them good. Read every capability of the app
(`php caligrafer.php capabilities`, the files of `application/services/`) and write, keyed by capability name, only
the hints that help:

+ `'about'`: what the app is about, one line ("a library app: the books of a catalog, their authors, and the books
  lent to people"). The assistant reads it before every answer.
+ Each main list: `title` (a name a person would say: "The catalog"), `columns` (3 or 4 short fields, related ones as
  `author.name`), `empty` (what an empty list says), `open` when a row should open another capability
  (`array('capability' => 'get_book', 'input' => 'id', 'from' => 'book_id')`).
+ Each change: `done` (the assistant's line once done, `{heading}` is the record: `'Done! {heading} is lent.'`),
  `undo` when another capability undoes it, `applies` when it only makes sense for some records
  (`array('not_in' => array('capability' => 'list_loans', 'field' => 'book_id'))`: never lend a book that is out).
+ Home: an `insight` on a list whose number calls for an action (always give its `action`: a number alone is noise),
  an `alert` on a list whose rows carry a flag (`'when' => 'overdue'`, with its `action`, `yes`, `tag`), and one
  `discover` on the list of the things the app is about (what to open, the facts to show, `new` and `related` fields,
  an `unavailable` rule when some cannot be used now). Copy the shapes of the wiki page; never invent keys.
+ What fills an empty list: `empty_next` (`array('lend_book')`); what a change leads to: `next`.

`php -l application/config/snap.php` after writing it.

## 4. Slow work: `async: true`

A capability that takes seconds (a report, sending many emails, an import) gets `async: true` in its
`#[Capability(...)]`: Snap runs it in the background as a Track card: Task, as the person (G155: REST, MCP and pages
still call it directly). Give it a `working` hint: what it says while it runs, 2 or 3 short lines.

## 5. The jobs

In `application/config/schedule.php` (create it with `return array(...);` when missing), add what is missing:

```php
array('name' => 'queue', 'capability' => 'queue_work', 'every' => '1m', 'timeout' => 55),          // background tasks
array('name' => 'snap-routines', 'capability' => 'snap_routines_due', 'every' => '1m', 'timeout' => 50), // routines
```

Locally they run with `php caligrafer.php schedule work` (the `scheduler` container does it in Docker); Caligrafy
Cloud runs them online.

## 6. The assistant's model (ask first)

Without a key the assistant answers the names of the cards, the shortcuts and "What can I do?". With one, Claude
understands anything. Ask the user whether they want it; if yes, they need an **Anthropic API key**
(console.anthropic.com, API keys). Never ask them to paste it in the chat, and never print it: give them a command that
reads it hidden and writes it to `.env`, for example

```bash
read -rs -p "Anthropic API key: " K && printf '\nANTHROPIC_API_KEY=%s\n' "$K" >> .env && unset K && echo " saved"
```

(when `.env` already has an empty `ANTHROPIC_API_KEY=` line, replace that line instead of adding one). Then check only
that it is set (`grep -c '^ANTHROPIC_API_KEY=.\+' .env`), never its value. `SNAP_MODEL` and `SNAP_EFFORT` stay empty
unless the user asks. The planner also needs the `anthropic-ai/sdk` package: `composer install` after an update (G156).

## 7. Check

```bash
php caligrafer.php snap
```

Every line `[ok]` that the app needs. Then, signed in, open `/assistant` (Home greets the person, its sections show
what the hints describe), type the name of a card ("the catalog"), open a record, try an action up to its Confirm
(stop there unless the user asked to change data), and open `/snap` (one card per capability the person may use).
A page that says "Snap is almost ready" means the tables are missing (G153). Report the addresses, the hints written,
what is async, and whether the model is on.
