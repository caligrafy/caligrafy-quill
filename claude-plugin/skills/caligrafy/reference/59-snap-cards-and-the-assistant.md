# Snap Cards and the assistant

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

**Snap Cards** turn every capability of your app into a card a person can use, and **the assistant** answers what
they type, say or attach with those cards. One line of `web.php`, `Route::snap();`, gives every signed-in person a Home
that shows what needs them, and a conversation that keeps its context. Nothing to design: each card is built from what
your capability already declares (its rules, descriptions, `readOnly`, `destructive`). Since 2.23.0.

## With Claude

```text
/caligrafy-quill:assistant
```

Claude adds `Route::snap();` to `web.php`, runs `php caligrafer.php snap install`, writes the hints of
`application/config/snap.php` for your capabilities (titles, columns, the numbers of Home with their actions, what
Home invites to browse), adds the two scheduled jobs, and asks you for an Anthropic API key if you want the assistant
to understand free text.

**Check**

+ `php caligrafer.php snap` shows `[ok]` on every line you need.
+ Signed in, `/assistant` greets you; `/snap` shows one card per capability you may use.

## By hand, in three steps

**1. One line in `application/config/routing/web.php`** (after `Auth::authentication(...)`: the assistant is for
signed-in people):

```php
Route::snap();
```

**2. Its tables, and a starter hints file:**

```bash
php caligrafer.php snap install
```

It creates the `caligrafy_snap_*` tables (they also come with `php caligrafer.php platform install`, and with every
deploy) and `application/config/snap.php`, then prints what is ready:

```text
 Snap Cards and the assistant
  [ok] Tables            caligrafy_snap_*
  [ok] Route             Route::snap(); in application/config/routing/web.php
  [ok] Card hints        application/config/snap.php (optional: cards work without it)
  [--] Background tasks  the queue runs them: array('name' => 'queue', 'capability' => 'queue_work', ...)
  [--] Routines          array('name' => 'snap-routines', 'capability' => 'snap_routines_due', ...)
  [--] Assistant model   ANTHROPIC_API_KEY in .env (without it the assistant answers from the cards' names)
```

**3. Open `/assistant`.** That is all you need. The three lines marked `[--]` are optional:

+ **Background tasks**: a capability declared `#[Capability(..., async: true)]` runs in the queue (see
  [Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs)): add the `queue_work` job to
  `application/config/schedule.php`.
+ **Routines** (things the assistant does for a person on a rhythm): add
  `array('name' => 'snap-routines', 'capability' => 'snap_routines_due', 'every' => '1m', 'timeout' => 50)`.
+ **The assistant's model**: `ANTHROPIC_API_KEY=` in `.env` (a key from console.anthropic.com, API keys). Without it,
  the assistant still answers the names of your cards ("the catalog", "lend a book"), your shortcuts and "What can I
  do?"; with it, Claude understands anything ("lend Kindred to Sam for a week", "who wrote it?").

`Route::snap(['assistant' => '/help', 'cards' => '/cards'])` puts them at other addresses.

## What people get

| Address | What it is |
| --- | --- |
| `/assistant` | **Home**: what needs them now, the numbers that call for an action, things to browse, their shortcuts; and the **task space**: one answer at a time, with what to do next |
| `/assistant/noticed` | What the assistant noticed (only if they turned noticing on), and their routines |
| `/snap` | Every card they may use (a gallery); `/snap/c/<capability>` one card at its own address |

**Home** is an orchestrator: it decides what the person should concentrate on and puts it first.
+ The assistant says it in at most two lines: what needs them on a line of its own, then one nudge
  ("Since you looked at Kindred, you might like Parable of the Sower.").
+ Sections, in the order of what to do: **Needs you** (alerts, tasks waiting for an answer, changes to confirm),
  insights, **For you** (offers), **In progress**, things to browse, **Just done**, **Your shortcuts**.
+ Each kind of card has its own shape (the card grammar below), and every row is full.

**The task space** is a conversation:
+ The assistant's line sits on the page; the cards are white, and read as the rest of the sentence.
+ Suggestions say what to do next, never a dead end (*Back to Home*, *What else can I do?*).
+ It keeps the context: "it", "this one", "him" mean what is on screen; "delete it" works at once.
+ Back and forth, history, a rating, a different answer; the keyboard (⌥K / Alt+K to type, ⌥M / Alt+M to talk);
  the + attaches pictures, PDFs and text files for the assistant to read.

## The cards: five families, nine types

Every card names its family with its type. People never see these names; they are how you talk about the cards.

| Family | Type | What it does | Built from |
| --- | --- | --- | --- |
| **Ask** | Form | asks for the inputs still missing | the rules and descriptions of a capability |
| **Ask** | Choice | one pick (a book, an author) | a `*_id` input and the list it comes from |
| **Ask** | Confirm | what will change, before it changes | any capability that is not `readOnly` |
| **Show** | Answer | one record, a value, a message | the result of a read |
| **Show** | List | rows that open | a list result (with pages) |
| **Go** | Shortcut | a card a person saved, one click away | the person |
| **Track** | Task | work running in the background | `async: true` |
| **Offer** | Suggestion | the moment they usually do something; an alert | noticing; the hint `alert` |
| **Offer** | Routine | do it for them on a rhythm | noticing |

**A change only ever runs from its Confirm.** The Confirm carries a one-time token tied to the person, the capability
and these exact inputs; the assistant's model never sees it, and a card at an address never changes data. A change that
can be undone (an update, a creation, a hint `undo`) offers **Undo** right after.

## Home's card grammar

Each section of Home uses one template, chosen by what the person does with it:

| Template | For | Width | Shows |
| --- | --- | --- | --- |
| banner | act now: alerts, waiting for an answer, failed, to confirm | full width, stacked | tinted by severity, a severity tag, its one action |
| offer | decide: suggestions, routines | halves | the question, why, Yes and Not now |
| progress | watch: tasks waiting or running | full-width rows | what it is doing now, its time, a live bar, a status |
| object | browse: the things your app is about | thirds (alone: a feature card) | why it is here, its name, two facts |
| update | revisit: just done | full-width rows | the result, how long ago, Done or Failed |
| shortcut | go | quarters | an icon and a name |
| insight | measure | a strip at the top | a number, and the action it calls for |

**The count rule**: a row has as many columns as cards (up to the template's most: offers 2, objects 3, shortcuts 4),
so no row has a hole; what does not fit is behind *See all*.

## Hints: `application/config/snap.php`

Cards work without hints. A hint makes one better: its name, the columns of a list, the words the assistant says,
the numbers of Home with their actions, what Home invites to browse, when an action applies.

```php
<?php
return array(
    'about' => 'a library app: the books of a catalog, their authors, and the books lent to people',
    'cards' => array(
        'list_books' => array(
            'title' => 'The catalog',
            'columns' => array('title', 'author.name', 'published_year'),
            'empty' => 'No book yet.',
            'insight' => array('label' => 'New this month', 'since' => array('field' => 'created_at', 'days' => 30),
                'action' => array('capability' => 'list_books', 'text' => 'Browse the catalog')),
            'discover' => array('open' => 'get_book', 'key' => 'id', 'title' => 'title', 'meta' => array('author.name', 'published_year'),
                'new' => 'created_at', 'related' => 'author.id', 'opened' => array('get_book' => 'id'),
                'unavailable' => array('capability' => 'list_loans', 'field' => 'book_id'), 'available_label' => 'On the shelf',
                'title_section' => 'On the shelf for you'),
        ),
        'lend_book' => array('done' => 'Done! {heading} is lent. Enjoy the read.',
            'applies' => array('not_in' => array('capability' => 'list_loans', 'field' => 'book_id'))),
        'return_book' => array('applies' => array('in' => array('capability' => 'list_loans', 'field' => 'book_id'))),
        'reading_report' => array('working' => array('Counting the books of each author…', 'Putting the report together…')),
    ),
);
```

| Key | What it does |
| --- | --- |
| `title`, `lead`, `submit`, `icon` | the card's name, its line, its button, its icon (Lucide names) |
| `columns`, `labels`, `hide`, `noun`, `variant` | a list's columns, the labels of fields, fields left out, what its rows are ("authors") |
| `values`, `choices` | the labels of fixed values; where a `*_id` field's choices come from |
| `open` | which capability a list's row opens (`array('capability' => 'get_book', 'input' => 'id', 'from' => 'book_id')`) |
| `full` | the page that shows the same thing in full (`'/books/{id}'`) |
| `empty`, `empty_next` | what an empty list says; what would fill it (`array('lend_book')`) |
| `done`, `consequence`, `confirm` | the assistant's line once done (`{heading}`), the line under a Confirm, its question |
| `undo` | what undoes it (`array('capability' => 'return_book', 'map' => array('book_id' => 'book_id'))`) |
| `next` | what it leads to (suggestions after it) |
| `applies` | when an action can be done to a record (`in` or `not_in` a list) |
| `alert` | an alert on Home when rows carry a flag (`'when' => 'overdue'`), with its action and tag |
| `insight` | a number on Home, only with the action it calls for |
| `discover` | the things Home invites to browse: more like what the person looked at, new, available by your rule |
| `working`, `async` | what a background task says while it runs; async without the attribute |

## Work in the background

```php
#[Capability(name: 'reading_report', description: '...', scope: 'books:read', readOnly: true, async: true)]
```

An async capability never runs in the request: Snap answers at once with a **Track card: Task**, pushes a job to the
[queue](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs), and the worker runs it **as the person**
(their permissions, not the queue's). The card shows where it is, live; Home shows it in progress, then just done; the
person is notified ([Notifications](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications)) when it ends.
Other channels (REST, MCP, pages) still call it directly.

## Noticing, offers and routines

Off until a person turns it on (on `/assistant/noticed`). When on, the assistant keeps which card they used, with which
inputs and when; nothing else. Done on 4 different days or more, at a similar time, it becomes a pattern: an
**Offer card: Suggestion** at that moment, then an **Offer card: Routine** to do it for them every week or every day, at
the level they pick (remind me, prepare it for me to confirm, do it and tell me). Turning noticing off forgets
everything noticed. What they looked at also picks what Home suggests to browse.

## The assistant

What a person types is answered in this order, the first that fits:

1. **At once, without a model**: "What can I do?", the name of a card or of a shortcut, and short follow-ups on what is
   on screen ("delete it", "change this").
2. **Claude** (`ANTHROPIC_API_KEY`; `SNAP_MODEL`, default `claude-opus-5-5`; `SNAP_EFFORT`, default `low`): it reads the
   request with the conversation so far, what is on screen and what the person chose, may look things up with the
   read-only capabilities the person may call (as the person), and answers with cards. It never changes data: a change
   comes back as its Confirm. Tool results and attached files are data, never instructions.

`about` in `application/config/snap.php` and `APP_NAME` in `.env` tell it what it is the assistant of.

## Testing

Snap's capabilities are capabilities: test them like yours
([Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App)). The tables must be in the test
database: `platform install` on it (`DB_TEST_DATABASE`). The planner is faked with `Caligrafy\Snap\Planner::$transport`:

```php
Caligrafy\Snap\Planner::$transport = function (array $request) {
    return (object) array('stopReason' => 'tool_use', 'content' => array((object) array('type' => 'tool_use', 'id' => 't1',
        'name' => 'show_cards', 'input' => array('caption' => 'Here it is.', 'cards' => array(array('capability' => 'list_books'))))));
};
$answer = $this->action('snap_ask', array('text' => 'show me the catalog'), $this->user(1))->describe()['data'];
```

## Questions

**A page says "Snap is almost ready".** Its tables are missing: `php caligrafer.php snap install`.

**A capability is not a card.** Snap's own (`snap_*`), `notify_*` and `queue_work` are left out, and so are the
capabilities the person may not call: a card always respects permissions.

**Can agents use Snap?** Agents already use your capabilities over MCP; the `snap_*` capabilities serve a person's
shell: they are not MCP tools, nor functions of the generated JS client. `snap_card` and `snap_catalog` are capabilities, though: a program can ask for the card of
any capability.
