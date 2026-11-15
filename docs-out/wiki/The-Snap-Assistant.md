**The assistant** of [Snap](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant) answers
what a person types, says or attaches on `/assistant` with your cards. Much of it needs no model: "What can I do?", the
name of a card or of a shortcut, "delete it" on what is on screen. With an Anthropic API key, Claude understands
anything else, looks things up with the read-only capabilities the person may call, and answers with cards. It never
changes data: a change always comes back as its Confirm. Since 2.23.0.

<br/>

## With Claude

```text
/caligrafy-quill:assistant
```

Claude sets up Snap, writes `about` in `application/config/snap.php`, and asks you for an Anthropic API key if you want
the assistant to understand free text.

**Check**

+ `php caligrafer.php snap` shows the model on the `Assistant model` line, or `[--]` without a key.
+ On `/assistant`, "What can I do?" lists every card you may use.

<br/>

## By hand

1. **Without a key**, the assistant works at once: `Route::snap();` in `web.php`, `php caligrafer.php snap install`.
2. **With Claude**, add to `.env`:
    ```bash
    ANTHROPIC_API_KEY=
    ```
   (a key from console.anthropic.com, API keys). The model is `claude-opus-5-5` at `low` effort; `SNAP_MODEL` and
   `SNAP_EFFORT` in `.env` change them. The `anthropic-ai/sdk` package must be installed: it is in Caligrafy's
   `composer.json` since 2.23.0 (after an `update`, `composer install`, or rebuild the Docker image).
3. **Say what the app is**, in `application/config/snap.php`:
    ```php
    'about' => 'a library app: the books of a catalog, their authors, and the books lent to people',
    ```
   `about` and `APP_NAME` in `.env` tell it what it is the assistant of.

<br/>

## <a name="order"></a>Reference: how a request is answered

The first that fits:

1. **At once, without a model** (`Caligrafy\Snap\Shell`, [API](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-shell)):
   + "What can I do?" (also `what else can i do`, `what can you do`, `help`, `menu`, `?`, `options`): the menu, every card
     this person may start, by family;
   + the title of one of their [shortcuts](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Go-and-Track-Cards#shortcut):
     that shortcut, run;
   + the title or the name of a card ("the catalog", "lend a book", "list_loans"): that card;
   + a follow-up on the record on screen, a verb and "it", "this", "that", "this one", "that one" or "this book":
     `delete`/`remove`, `change`/`edit`/`update`/`fix`, `lend`/`loan`, `return`, `open`/`show`/`see`, which open
     `delete_<thing>`, `update_<thing>`, `lend_<thing>`, `return_<thing>` or `get_<thing>` for that record. Anything
     longer ("lend it to Sam") goes to the model, with the conversation's memory.
2. **Claude** (`Caligrafy\Snap\Planner`, [API](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-planner)),
   when it is on: it reads the request with the conversation so far, what is on screen and what the person chose. It
   may look things up with the read-only capabilities the person may call, as tools run with their permissions (6
   steps at most), and answers with `show_cards`: which capability, with which values, and one sentence of caption.

**What the model may never do is enforced in code, not in the prompt.** Every card it names goes through `Snap\Flow`
like any other: a read runs, a change comes back as an Ask: Confirm whose token only the person's click sends. The model
never sees a token and never runs a change. Tool results and attached files are the app's data, never instructions.

**Attachments.** The + attaches up to 3 files, 5 MB in all: PNG, JPEG, GIF, WebP, PDF, plain text, CSV, Markdown. Without
the model, attached files get a message instead of an answer.

**The keyboard.** ⌥K / Alt+K to type, ⌥M / Alt+M to talk.

<br/>

## <a name="test"></a>Testing

Fake the model with `Caligrafy\Snap\Planner::$transport` (set it back to `null` in `tearDown`), and call `snap_ask`
with `$this->action()`: it is a web-only capability. The Snap tables must be in the test database
(`platform install` with the test settings).

```php
Caligrafy\Snap\Planner::$transport = function (array $request) {
    return (object) array('stopReason' => 'tool_use', 'content' => array((object) array('type' => 'tool_use', 'id' => 't1',
        'name' => 'show_cards', 'input' => array('caption' => 'Here it is.', 'cards' => array(array('capability' => 'list_books'))))));
};
$answer = $this->action('snap_ask', array('text' => 'show me the catalog'), $this->user(1))->describe()['data'];
```

The designed cards have their own fake, `Caligrafy\Snap\Designer::$transport`, or keep to the rules with
`Caligrafy\Snap\Designer::$model = false` (since 2.24.1).

<br/>

## Common mistakes

+ **The assistant only answers the names of cards** (G156): `ANTHROPIC_API_KEY` is missing, or the `anthropic-ai/sdk`
  package is not installed.
+ **Forcing a tool in your own calls to Claude** (`toolChoice` of type `tool` or `any`) answers `400` on the current
  Claude models; Snap uses a structured output instead (G161).
+ **A test that fakes the planner on 2.24.0** also received the designer's requests: since 2.24.1 the designer has its
  own fake (G162).
+ **`/assistant` throws `LogicException`**: `Route::snap()` needs `Auth::authentication(...)` above it (G154).

<br/>

## Related pages

+ [Snap Cards and the assistant](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant#assistant)
+ [Snap: Ask cards](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Ask-Cards#confirm): consent
+ [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App)
+ [API: Snap](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap) · [Snap\Conversation](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap-Conversation)
