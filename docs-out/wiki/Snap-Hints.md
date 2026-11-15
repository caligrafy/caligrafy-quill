**Hints** make a [Snap](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant) card better:
its name, the columns of a list, the words the assistant says, the numbers of Home with their actions, what Home
invites to browse, when an action applies. Cards work without any: each is built from what its capability declares.
The hints live in one file the app owns, `application/config/snap.php`, keyed by capability name. Since 2.23.0.

<br/>

## With Claude

```text
/caligrafy-quill:assistant
```

Claude writes `application/config/snap.php` for your capabilities: `about`, titles, columns, the numbers of Home with
their actions, what Home invites to browse, the card views.

**Check**

+ `php caligrafer.php snap` shows `[ok] Card hints`.
+ Each key you see in the file is in the table below: a key the framework does not know is ignored silently.

<br/>

## By hand

`php caligrafer.php snap install` writes a starter file when there is none. A complete one:

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

A file that returns the cards alone (no `'cards'` key) works too. `about` (and `APP_NAME` in `.env`) tells the
assistant what it is the assistant of.

<br/>

## <a name="keys"></a>Reference: every key of a card

| Key | What it does | Page |
|---|---|---|
| `title`, `lead`, `submit`, `icon` | the card's name, its line, its button, its icon (Lucide names) | [Ask](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Ask-Cards) |
| `labels`, `icons`, `hide` | the labels and icons of fields, the fields left out | [Ask](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Ask-Cards) |
| `values` | the labels of fixed values: `array('weekday' => array(1 => 'Monday', ...))` makes a select | [Ask](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Ask-Cards) |
| `choices` | where a field's choices come from: `array('author_id' => 'list_authors')`, or `array('capability' => ..., 'value' => 'id', 'label' => 'name')` | [Ask](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Ask-Cards) |
| `current` | the capability an `update_` form reads its current values from (`get_<thing>` by default) | [Ask](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Ask-Cards) |
| `confirm`, `consequence` | a Confirm's question, the line under it | [Ask](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Ask-Cards#confirm) |
| `undo` | what undoes it: `array('capability' => 'return_book', 'map' => array('book_id' => 'book_id'))` (each input of the undo, from an input or the result of the change), and its button `label` (*Undo*) | [Ask](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Ask-Cards#confirm) |
| `columns`, `noun`, `variant` | a list's columns, what its rows are ("authors"), its variant (`rows`) | [Show](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Show-Cards) |
| `open` | which capability a list's row opens: `array('capability' => 'get_book', 'input' => 'id', 'from' => 'book_id')` | [Show](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Show-Cards) |
| `full` | the page that shows the same thing in full: `'/books/{id}'` | |
| `empty`, `empty_next` | what an empty list says; what would fill it: `array('lend_book')` | [Show](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Show-Cards) |
| `applies` | when an action can be done to a record: `in` or `not_in` a list | [Show](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Show-Cards#answer) |
| `done` | the assistant's line once done; `{heading}` is what it was about | |
| `next` | the capabilities it leads to (suggestions after it) | |
| `alert` | an alert on Home when rows carry a flag | [Offer](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Offer-Cards#alert) |
| `insight` | a number on Home, only with the action it calls for | below |
| `discover` | the things Home invites to browse | below |
| `working`, `async` | what a background task says while it runs; async without the attribute | [Track](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Go-and-Track-Cards#task) |
| `card`, `view`, `design` | its card view, the page whose `data-snap` part shows it, how its data is designed | [Card views](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Card-Views) |

### <a name="insight"></a>`insight`

```php
'insight' => array('label' => 'New this month', 'icon' => 'sparkles',
    'action' => array('capability' => 'list_books', 'text' => 'Browse the catalog'),
    'since' => array('field' => 'created_at', 'days' => 30),          // count only what was added lately
    'ring' => array('of' => 'list_books', 'label' => 'of the catalog', 'invert' => false)),   // and a share of another count
```

One number per read capability with the hint, shown only when there is something to act on (a count above zero) and
the person may take its `action`. Without an `action` it is never shown (G157).

### <a name="discover"></a>`discover`

On a list capability, Home shows picks of what the app is about, each saying why it is there (6 per list at most, a different few each day):

+ **for you**: more like what this person opened (only with noticing on), sharing `related`;
+ **new**: up to 2 of what was added in the last 30 days, by the field `new`;
+ **the rule**: only what is available now, not in the `unavailable` list (`available_label` says it).

| Key | What it is |
|---|---|
| `open`, `key` | how an object opens: the capability, and the field it takes |
| `title`, `meta` | what a pick shows |
| `new` | the field that says when it was added |
| `related` | what "more like it" shares (`author.id`) |
| `opened` | the requests that mean "looked at it": `array('get_book' => 'id', 'lend_book' => 'book_id')` |
| `unavailable`, `available_label` | the business rule: `array('capability' => 'list_loans', 'field' => 'book_id')` |
| `title_section` | the section's title |

<br/>

## Common mistakes

+ **Hints on `#[Capability]`.** They are keys of `application/config/snap.php`; only `async` is also a flag of the
  attribute (G157).
+ **A key with a typo** is ignored silently: copy the shapes above.
+ **The file changed and nothing did**: in a long process (the queue worker, a test) the file is read once;
  `Caligrafy\Snap\Hints::forget()` reads it again.

<br/>

## Related pages

+ [Snap Cards and the assistant](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant#hints)
+ [Snap: Ask cards](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Ask-Cards) · [Show cards](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Show-Cards) · [Card views](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Card-Views)
+ [API: Snap\Hints](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-hints)
