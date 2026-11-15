A **card view** makes a [Snap](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant) card
that shows data (a list, a record) look like your app: `application/views/snap/<capability>.pug` is a small Pug view,
in your own markup and classes, that the server renders inside the card. Without one, the card shows the part of a page
marked for it, or a card designed for its data in your design system. A card never shows a whole page. Since 2.25.0
(the `data-snap` mark and designed cards since 2.24.0).

<br/>

## With Claude

```text
/caligrafy-quill:assistant
```

Claude reads each page and the scripts that draw it, keeps the part that matters (a list's rows, a record's header and
summary) and writes the card view with your classes only (a built stylesheet, such as Tailwind's, is checked class by
class).

**Check**

+ `php caligrafer.php snap views` shows `[ok]` for each card view.
+ `/snap/c/list_books` shows the rows as the catalog page does, without its title, tabs and filters.

<br/>

## By hand

1. **Write the card view.** It is named after a read-only capability: `application/views/snap/list_books.pug`. Its
   first line names the page it was made from and that page's fingerprint
   (`shasum application/views/Bookshelf/books.pug | cut -c1-8`):
    ```pug
    //- snap card of list_books, from Bookshelf/books.pug 5fb9e0cf
    ul.cq-rows
        each $b in $rows
            li.cq-row
                .cq-row-main
                    p.cq-row-title
                        a(href="{$home}books/{$b['id']}", data-snap-open='get_book', data-snap-input=json_encode(array('id' => $b['id'])))= $b['title']
                    p.cq-row-meta= $b['author']['name'] . ', ' . $b['published_year']
    ```
   Pug in Caligrafy is PHP: strings join with `.`, never `+`.
2. **Check it.**
    ```bash
    php caligrafer.php snap views
    ```
    ```text
     Card views (application/views/snap/<capability>.pug)
      [ok] get_book                   from Bookshelf/book.pug
      [ok] list_authors               made for the card (no page)
      [!!] list_books                 Bookshelf/books.pug changed since its card view was made: write it again
      [--] get_user                   no card view: a card designed from its data
    ```
   `snap views` exits 1 when a card view is stale; `php caligrafer.php doctor` warns too (*Snap card views*).

<br/>

## <a name="variables"></a>Reference: what a card view gets

| Variable | What it is |
|---|---|
| `$rows`, `$page` | for a list: its rows, and `page`, `pages`, `total` of a `paginate()` page |
| `$record` | for one record |
| `$result` | what the capability returned, pages included, as plain arrays |
| `$input` | the card's inputs |
| `$card` | the card: its `capability` and `title` |
| `$home`, `$authorized`, `$user` | the address of the application (ending with `/`), whether someone is signed in, who |

A link with `data-snap-open="<capability>"` and `data-snap-input='{"id":7}'` opens that card in the conversation
instead of leaving it. A card view is inserted as HTML: its `<script>` tags do not run (G163).

<br/>

## <a name="order"></a>Reference: how a Show card looks, best first

`Caligrafy\Snap\Views` ([API](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-views)) decides:

1. **Its card view**: `application/views/snap/<capability>.pug`, or the view the hint `card` names
   (`'card' => 'snap/list_books'`, under `application/views`); `'card' => false` for none. One that fails to render
   falls back to the designed card, and its error goes to the PHP log.
2. **The part of its page marked for it**: on the view of a `Route::page` whose first data is this capability, the
   element with `data-snap="<capability>"` is shown alone, in a frame sized to it. A whole page only when the hint
   `view` names it (`'view' => 'Bookshelf/book'`); `'view' => false` for none.
3. **A card designed for its data** (`Caligrafy\Snap\Designer`), in your design system: the `--design-*` tokens of
   [`DESIGN_STYLE`](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles), else Caligrafy's. A record becomes
   a hero (its name, a line under it, badges with a tone), a few numbers, its details, its text and its tags; a list
   becomes rows. Who designs it: the hint `design` when you give one; else Claude, with `ANTHROPIC_API_KEY`, from a
   sample of the data, once per shape of data (kept 30 days in the cache); else rules from the names of the fields.

```php
'search_books' => array('card' => 'snap/list_books'),   // the same rows as the catalog: its card view
'get_user' => array('card' => false),                     // no card view
'get_book' => array('view' => 'Bookshelf/book'),          // without a card view: this page, whole
'list_people' => array('design' => array('title' => 'name', 'subtitle' => 'role', 'meta' => array('email', 'active_cases'),
    'badge' => array('path' => 'status', 'tones' => array('away' => 'warning')))),   // a designed card, by hand
```

The `design` hint: `title`, `subtitle`, `eyebrow`, `image`, `badges` (`badge` for a list), `stats`, `fields`, `text`,
`tags` (`meta` for a list), each a field name or `array('path' => ..., 'label' => ..., 'format' => ..., 'tones' => array(...))`.
Formats: `text`, `number`, `money`, `percent`, `date`, `datetime`, `bool`, `email`, `url`; tones: `success`, `warning`,
`danger`, `info`, `neutral` (`Designer::FORMATS`, `Designer::TONES`).

<br/>

## Common mistakes

+ **A card view does not show.** It must be named after a read-only capability; run `php caligrafer.php snap views`.
+ **A card looked like a whole page on 2.24, and designed since 2.25.0.** Give it a card view, or mark the part with
  `data-snap` (G163).
+ **A class of a built stylesheet does nothing.** With Tailwind, a card view can only use classes the build already has.
+ **`+` between strings** in Pug: Caligrafy renders Pug with PHP; join with `.`.

<br/>

## Related pages

+ [Snap Cards and the assistant](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant#looks)
+ [Snap: Show cards](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Show-Cards)
+ [Views and page components](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views)
+ [Design styles](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles)
+ [Caligrafer: `snap`](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#snap)
+ [API: Snap\Views and Snap\Designer](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-views)
