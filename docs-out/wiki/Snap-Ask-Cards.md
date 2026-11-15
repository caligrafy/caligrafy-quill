The **Ask** cards are the cards of [Snap](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant)
that ask a person for something before anything runs: **Ask: Form** asks for the inputs still missing, **Ask: Choice**
asks for one pick, and **Ask: Confirm** shows what a change will do and waits for the person's click. Nothing to design:
each is built from what the capability already declares (its `$rules`, `$descriptions`, `readOnly`, `destructive`).
Since 2.23.0.

<br/>

## With Claude

```text
/caligrafy-quill:assistant
```

Claude adds `Route::snap();`, runs `php caligrafer.php snap install` and writes the hints of
`application/config/snap.php` for your capabilities: the titles, the labels of fields, where a `*_id` field's choices
come from, the line under a Confirm.

**Check**

+ Signed in, `/snap/c/create_book` shows the form of `create_book`, with a pick list for `author_id`.
+ Sending it shows a Confirm first; nothing is added until you click it.

<br/>

## By hand

Ask cards need nothing but `Route::snap();` in `web.php` (after `Auth::authentication(...)`) and the Snap tables
(`php caligrafer.php snap install`): every capability the person may call gets its card. Take the capability of
[Build once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service):

```php
#[Capability(name: 'create_book', description: 'Adds a book', scope: 'books:write')]
class CreateBook extends Service {
    public $rules = ['title' => 'required|max_len,255', 'author_id' => 'required|integer'];
    public $filters = ['title' => 'trim|sanitize_string'];
    public $descriptions = ['title' => 'The title of the book'];
    public function handle(array $input, Context $context) {
        return (new Book('books'))->create($input);
    }
}
```

1. **Ask: Form.** Open `/snap/c/create_book`. The card has a field per input of `$rules` (the input `page` is left
   out); its limits come from the rules (`max_len,255` is a maximum length), its help line from
   `$descriptions`, its button from the capability's verb ("Add").
2. **Ask: Choice.** `author_id` is a `<thing>_id`, and `list_authors` is a read-only capability the person may call: the
   field becomes a pick among the authors. Open `/snap/c/create_book?in={"title":"Kindred"}`: with the title known, one
   pick is all that is missing, so the card is an **Ask: Choice** ("Which author?"), with what was already said shown
   read-only above it.
3. **Ask: Confirm.** Send the card. `create_book` is not `readOnly`, so it does not run: Snap answers with its Confirm
   (what will change, its question, its button "Yes, add"), and only the person's click runs it. A change that can be
   undone then offers **Undo**.

<br/>

## <a name="form"></a>Reference: what builds an Ask card

`Caligrafy\Snap\Cards::ask()` builds the card ([API](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap-Cards#snap-cards-ask)),
`Cards::fields()` its fields ([API](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap-Cards#snap-cards-fields)):

| A field gets | From |
|---|---|
| its label | the hint `labels`, else the input's name in words, without `_id` (`published_year`: "Published year", `author_id`: "Author"); the `id` of `get_`, `update_` or `delete_<thing>` is named after the thing |
| its help line | `$descriptions` (a help line that starts with "The id of" is dropped when the field is a pick: a person picks by name) |
| required or not | the capability's schema (`required` in its rules) |
| its limits | the schema: `min`, `max`, `minlength`, `maxlength` |
| a select | the hint `values` (`array(1 => 'Monday', ...)`), or an `enum` in the schema |
| a pick (`choice`) | the first of: the hint `choices`, an `exists,<table>.<column>` rule (`list_<table>`), a `<thing>_id` input (`list_<things>`), the `id` of `get_`/`update_`/`delete_<thing>` (`list_<things>`); only a read-only capability the person may call, with 50 options at most (`Cards::MAX_OPTIONS`) |
| its icon | the hint `icons`, else a few known field names, else a dot |
| its current value | an `update_<thing>` form starts from the record of `get_<thing>` with the same `id` (the hint `current` names another reader) |

The options of a pick are the rows of its list: the value is `id` and the label the row's `title`, `name` or
`username`; `'choices' => array('author_id' => array('capability' => 'list_authors', 'value' => 'id', 'label' => 'name'))`
says otherwise.

A card is an **Ask: Choice** when exactly one required input is missing, it is a pick, and there are no errors to show;
anything else is an **Ask: Form**. `Cards::needsInput()` decides whether an Ask card still needs the person: a required
input is missing, or only the id of a thing to change was given (an `update_` starts as its form); a delete needs
nothing more than its id.

<br/>

## <a name="confirm"></a>Reference: Ask: Confirm and consent

**A change only ever runs from its Confirm.** `Caligrafy\Snap\Flow::next()` decides the next card of any capability,
whoever asks (a card that is sent, the assistant's fast path, the model's choice):

```text
read:          inputs missing -> its Ask card; complete -> runs -> Show card
write/destroy: inputs missing -> its Ask card; refused by its rules -> Ask with errors; else -> Ask: Confirm
```

`Caligrafy\Snap\Consent` ([API](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-consent)) makes the
Confirm:

+ what will change (before and after), and a one-time token bound to this person, this capability and these exact
  inputs, for 10 minutes (`Consent::TTL`), kept in the session, never in the card's address. Only the person's click on
  that card sends it back: a model, a page or a link cannot make one.
+ a confirmed change is recorded in the [audit log](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#audit)
  (capability `snap_confirm`).
+ what undoes it: what was true before (an `update_`), what was created (`create_` is undone by `delete_`), or the hint
  `undo`. Undoing is a change too: it gets its own Confirm.

The Confirm's words: its question is the hint `confirm`, else "<title>: <subject>?"; the line under it is the hint
`consequence`, else *This cannot be undone.* for a `destructive` capability, *You can undo it right after.* when it can
be undone, *This cannot be undone from here.* otherwise. Its button is the hint `submit` (or the verb), as
"Yes, <verb>" (a delete names what it deletes).

<br/>

## Common mistakes

+ **A pick shows as a text field.** Its list must be a read-only capability the person may call, named `list_<things>`
  (or given by the hint `choices`), with 50 rows at most.
+ **Hints on the capability.** Titles, labels, `choices`, `confirm` are keys of `application/config/snap.php`, keyed by
  capability name; a key the framework does not know is ignored silently (G157).
+ **A capability is not a card.** Snap's own (`snap_*`), `notify_*`, `queue_work` and web-only capabilities are left
  out, and so are the capabilities the person may not call.

<br/>

## Related pages

+ [Snap Cards and the assistant](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant): the overview
+ [Snap: Show cards](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Show-Cards)
+ [Snap: hints](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Hints)
+ [Validation rules](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation): the rules a form is built from
+ [API: Snap](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap) · [API: Snap\Cards](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap-Cards)
