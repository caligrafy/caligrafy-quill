The **Show** cards of [Snap](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant) show
what a read returned: **Show: List** for rows (with their pages), that open one by one, and **Show: Answer** for one
record, a value or a message. A read-only capability whose inputs are complete runs at once and answers with its Show
card. Since 2.23.0; how they look (card views, designed cards) since 2.24.0 and 2.25.0.

<br/>

## With Claude

```text
/caligrafy-quill:assistant
```

Claude writes the hints of the lists (`title`, `columns`, `empty`, `open`) and a card view for each main list and
record from your own pages, so the cards look like your app.

**Check**

+ `/snap/c/list_books` shows the catalog as rows; a row opens its book.
+ `php caligrafer.php snap views` shows `[ok]` for each card view.

<br/>

## By hand

With `Route::snap();` in `web.php` and the Snap tables, every read-only capability the person may call is a Show card
as soon as its inputs are complete:

1. **Show: List.** `list_books` returns a list (or a page of
   [`paginate()`](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Pagination)): `/snap/c/list_books` is a
   Show: List. Its rows open `get_book` because the name says so (`list_books` and `search_books` open
   `get_book` with the row's `id`, when the person may call it).
2. **Show: Answer.** `get_book` returns one record: `/snap/c/get_book?in={"id":3}` is a Show: Answer, a record named
   by its own name, with the actions of the same thing the person may call (`update_book` as *Change*, `delete_book` as
   *Delete*, and other `<verb>_book` capabilities such as `lend_book`), each an Ask card.
3. **Better words and columns.** In `application/config/snap.php`:
    ```php
    <?php
    return array(
        'cards' => array(
            'list_books' => array(
                'title' => 'The catalog',
                'columns' => array('title', 'author.name', 'published_year'),
                'empty' => 'No book yet.',
            ),
        ),
    );
    ```

<br/>

## <a name="list"></a>Reference: Show: List

`Caligrafy\Snap\Cards::show()` builds both ([API](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap-Cards#snap-cards-show)).
A result that is a list (or a page: `data`, `total`, `pages`) is a List:

| The card's | Comes from |
|---|---|
| title | the hint `title`, else the capability's name in words |
| columns | the hint `columns` (a related row's field is named after the relation: `author.name` is "Author"), else the first row's fields, without `id`, `*_id`, passwords, `created_at`, `updated_at` and long texts (over 140 characters), a related row by its `name` or `title`; 5 at most |
| what a row opens | the hint `open` (`array('capability' => 'get_book', 'input' => 'id', 'from' => 'book_id')`), else `get_<thing>` for `list_<things>` and `search_<things>` |
| its count line | the total and the hint `noun` ("authors"), else a noun from the name |
| an empty list | the hint `empty`, else *Nothing here yet.*; the hint `empty_next` names what would fill it |
| pages | `page`, `pages`, `total` of a `paginate()` page |
| its look | `variant` (`rows` by default), then its card view, else the part of its page marked for it, else rows designed for its data ([card views](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Card-Views)) |

<br/>

## <a name="answer"></a>Reference: Show: Answer

| The result | The card |
|---|---|
| one record (an array) | variant `record`: its heading is the record's own title or name, else the title or name of the record it is about (a loan is about its book), else its username, else `#id` (`Cards::name()`); its fields; its actions; its card view or designed blocks |
| a value | variant `fact`: the value; `true` and `false` read *Yes* and *No*; nothing reads *Done* |

**The actions of a record** are the capabilities of the same thing the person may call: `update_<thing>` (*Change*),
`delete_<thing>` (*Delete*), and any other `<verb>_<thing>` (it gets `<thing>_id`). The hint `applies` keeps only what
can be done now:

```php
'lend_book' => array('applies' => array('not_in' => array('capability' => 'list_loans', 'field' => 'book_id'))),
'return_book' => array('applies' => array('in' => array('capability' => 'list_loans', 'field' => 'book_id'))),
```

The list is read as the person, once per request; a list they may not read leaves the action out
([`Cards::applies()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap-Cards#snap-cards-applies)).

**A refusal that is not about the inputs** (not found, conflict, forbidden) is a notice instead of a result
([`Cards::notice()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap-Cards#snap-cards-notice)); a refusal about
the inputs comes back as the Ask card with its errors.

<br/>

## Common mistakes

+ **A row does not open.** The getter must be named `get_<thing>` for `list_<things>`, and the person must be allowed
  to call it; or give the hint `open`.
+ **A card shows a whole page** on 2.24. Since 2.25.0 a card never does: it shows its card view, else the part of a page
  marked `data-snap`, else a card designed from its data (G163).
+ **`select()` with models** fills the other columns with `null`, and the card shows them: add `arrays()` (G138).

<br/>

## Related pages

+ [Snap Cards and the assistant](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant)
+ [Snap: card views](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Card-Views): how a Show card looks
+ [Snap: Ask cards](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Ask-Cards)
+ [Snap: hints](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Hints)
+ [API: Snap\Cards](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap-Cards)
