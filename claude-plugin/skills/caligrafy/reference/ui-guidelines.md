# UI guidelines: every new page looks like Caligrafy's own pages

Since Quill 2.11.0, Caligrafy's own pages (sign-in, account, admin, welcome, the Tasks example, the payment pages,
the Vue app and SPA templates) share one look, and every page or client app you generate uses it:
**a white canvas, a light top bar with the Caligrafy mark, one message per screen, full-width bands, narrow columns for
forms, rows for lists, large tiles for links, Caligrafy black for primary actions, Inter type, no cards, no shadows**.
It is responsive at every size: phones (< 576px), tablets (576-991px), desktops (992px and up).

Three stylesheets give it, linked by the master `default/master/site.pug`, the Vue app template
(`framework/plugins/app`) and the SPA template (`src/main.js`): **no page-specific CSS is needed**.

| File | What it holds |
| --- | --- |
| `public/css/layout.css` | The structure: top bar, hero, bands, forms, rows, tiles, account area, footer (the classes below) |
| `public/css/look.css` | The style: tokens (`--design-*` feeding `--cq-*`), Inter, black primary buttons, fields, the mark |
| `public/css/camera.css` | Full-screen camera pages (face detection, machine learning, face sign-in) |

`tokens.css` (the design tokens and the older card components) is still linked first. A project created before
2.11.0 gets the three files with `php caligrafer.php update`, and the new view templates with `upgrade --apply`.

## Page skeleton (Pug view)

```pug
extends ../default/master/site

block title
  | Books · My app

block content
  section.cq-hero.cq-hero--page
    h1.cq-display Books
    p.cq-lead Every book of the catalog.

  section.cq-band.cq-band--tight
    .cq-inner.cq-inner--narrow
      //- form, error, segments, rows, empty state: in this order
```
The master's blocks: `title`, `brand` (the name in the top bar, after the mark), `nav_links` (the links on the right;
on phones they open under a Menu button: Sign in / Sign out follow the session, `authorized()`, even when the page's
controller passes no `$authorized`, 2.12.4+), `content`, `footer`, `additional_css`, `additional_scripts`. The top bar is
sticky, not fixed: nothing hides under it (G59 is about the older `layoutblocks.pug`).
Its route, in `web.php` (2.5.0+): `Route::page('/books', 'Books/index');`, or with server-side data
`Route::page('/books', 'Books/index', ['data' => ['books' => 'list_books']]);`. The view then always has `$authorized`
and `$user` for the signed-in parts (G74); data comes from capabilities only, never from a model in a route or a controller.

## The parts

| Part | Markup | Notes |
| --- | --- | --- |
| Top bar | `nav.cq-nav` (in `site.pug`) | Your links: `block nav_links` with `a.cq-nav-link`. Never a second header |
| Full-screen message | `section.cq-hero > h1.cq-display + p.cq-lead + .cq-cta > .btn` | One per page at most: a landing or a welcome |
| Title band | `section.cq-hero.cq-hero--page` | The title of an ordinary page, same children |
| Band | `section.cq-band > .cq-inner > h2.cq-heading + p.cq-lead + ...` | Full width. `.cq-band--surface`: ash background; `.cq-band--tight`: less padding; `.cq-inner--narrow`: 720px column; `.cq-center` centers a heading or a lead |
| Sign-in style form | `main.cq-auth > h1 + p.cq-lead + form > .cq-field (label + .form-control) + .btn` | 380px column, full-width button. `.cq-auth--wide`: 560px. `.cq-or` between two choices, `p.cq-auth-note` under |
| Form on a page | `.cq-form-grid(.cq-form-grid--2) > .cq-field` | Fields side by side from tablets up. A button in the grid: `label.cq-hidden-label` + `.btn.cq-btn-block` |
| Message | `.cq-alert` / `.cq-alert.cq-alert--error` | Above a form. Server messages: `include ../default/modules/notice` (`$error`, `$status`, `$message`) |
| Field error | `p.cq-field-error(role='alert')` | Under its own field, one per field (`CapabilityError`'s `fields`); a message without a field goes under the button |
| Form | `form(method='POST')` | Always a method, also for a form a script sends: without one it falls back to a GET, its fields in the address, when the script does not run (G104). The page loads the script that handles it. A page posted by a controller adds `=csrf()` |
| Choices | `.cq-segments(role='tablist') > button(.active)` + `span.cq-count` | Filters and views of the same list |
| List | `ul.cq-rows > li.cq-row > .cq-row-main (p.cq-row-title + p.cq-row-meta) + .cq-row-actions > .btn` | `li.cq-row.is-done` greys it; `input.form-check-input.cq-row-check` first; `.cq-row--link` for a whole-row link |
| Inline edit | `.cq-inline-edit > input.form-control.form-control-sm` | Replace the title in place; Save / Cancel in `.cq-row-actions` |
| Empty state | `p.cq-empty-state` | Always say what to do next ("No books yet: add the first one above.") |
| Tiles | `.cq-tiles(.cq-tiles--spaced) > a.cq-tile > h3 + p + span.cq-tile-link` | Links to other parts: 1, 2 or 3 columns by width |
| Side by side | `.cq-split > section.cq-split-main + aside.cq-split-detail` | A list and the item opened, or a list and its form (2.12.0+). Right after the title band, instead of a band. One column on phones, two from tablets up; the detail is an ash panel that stays in view while the list scrolls, and its form takes one field a line |
| Figures | `.cq-stats > .cq-stat > .cq-stat-value + .cq-stat-label` | Counts, credits, totals |
| Badge | `span.cq-badge(.cq-badge--strong)` | A status beside a title |
| Account area | `.cq-account > nav.cq-account-nav > a(href='#id')` + `.cq-account-main > section(id)` | Side menu on desktops (it marks the section on screen), links on top on phones |
| Long text | `.cq-doc` | Terms, help |
| Code | `.cq-code` (a code to compare), `pre.cq-pre` (a token, a command) | |
| Buttons | `.btn.btn-primary` (black), `.btn.btn-secondary` (ash), `.cq-buttons` for a row | Never Bootstrap colors (`btn-success`, `btn-danger`, `text-primary`...) |
| Footer | `footer.cq-footer` (in `site.pug`), a direct child of `body`: it starts one screen down on a short page (2.16.2) | `include ../default/modules/share` for the share links |

Colors and spacing come from the tokens (`var(--cq-color-ink)`, `var(--cq-color-primary)`, `var(--cq-color-surface)`,
`var(--cq-color-text-muted)`, `var(--cq-gutter)`...), never hard-coded hex values in new code.

## Worked example: a list with a form, filters and actions

The Tasks page of the build-once example (`framework/settings/examples/tasks`): a title band, a form row, segments
with counts, rows with a checkbox, Edit and Delete, an empty state, and the data from the capabilities through the
generated JS client (`php caligrafer.php client public/js/capabilities.js`).

```pug
extends ../default/master/site

block title
  | Tasks · Caligrafy

block content
  section.cq-hero.cq-hero--page
    h1.cq-display Tasks
    p.cq-lead Changes made from REST or MCP appear here within a few seconds.

  section.cq-band.cq-band--tight
    .cq-inner.cq-inner--narrow
      form#new-task(novalidate)
        .cq-form-grid
          .cq-field
            label(for='task-title') Task
            input#task-title.form-control(type='text', name='title', placeholder='What has to be done?', maxlength='200', autocomplete='off')
          .cq-field
            label(for='task-notes') Notes
            input#task-notes.form-control(type='text', name='notes', placeholder='Optional', maxlength='1000', autocomplete='off')
          .cq-field
            label.cq-hidden-label(for='add-task') &nbsp;
            button#add-task.btn.btn-primary.cq-btn-block(type='submit') Add task
      p#error.cq-field-error(role='alert')

      .cq-segments(role='tablist')
        button.active(type='button', role='tab', aria-selected='true', data-status='all') All
          span.cq-count(data-count='all') 0
        button(type='button', role='tab', aria-selected='false', data-status='open') Open
          span.cq-count(data-count='open') 0
        button(type='button', role='tab', aria-selected='false', data-status='done') Done
          span.cq-count(data-count='done') 0

      ul#list.cq-rows
      p#empty.cq-empty-state(hidden) No tasks here yet.

block additional_scripts
  script(type='module', src="{$home}public/js/tasks.js")
```

A row built by the script (plain DOM, or the same classes in a Vue template):
```js
li.className = 'cq-row' + (done ? ' is-done' : '')
// input.form-check-input.cq-row-check | .cq-row-main > p.cq-row-title + p.cq-row-meta | .cq-row-actions > .btn
```
Behavior that goes with the look:
- show `CapabilityError` field messages in `.cq-field-error` next to the action, never `alert()`, never in a part of
  the page that may be off screen;
- an action that waits on the server (a payment page, a long call) disables its button and says so ("Opening Stripe…"),
  and puts it back when the page is shown again from the browser's back button (`pageshow` with `event.persisted`);
- confirm destructive actions (`confirm('Delete "..."?')`) and rename inline (`.cq-inline-edit`), never `prompt()`;
- a periodic refresh must not replace a row that is being edited;
- after adding, reset the form and put the focus back in the first field;
- find elements by id, never by tag: the top bar has its own button (Menu), so `document.querySelector('button')` is
  not your button (G100).

## A list and its detail, side by side (2.12.0+)

When a page shows a list and the item opened (a finder, an inbox, a catalog with its add form), put them side by
side instead of a band under the list:

```pug
section.cq-hero.cq-hero--page
  h1.cq-display Find a book
  p.cq-lead Type a few words: the books answer as you type.

.cq-split
  section.cq-split-main
    .cq-field
      label(for='query') Search
      input#query.form-control(type='search')
    ul#books.cq-rows
    p#empty.cq-empty-state(hidden) No book matches.
  aside#book.cq-split-detail(aria-live='polite')
    p.cq-empty-state Open a book to read about it.
```
In a Vue template the same classes go on the same elements (`<div class="cq-split">`, `<aside class="cq-split-detail">`).
On phones the detail comes after the list: when opening an item there matters, scroll it into view
(`document.getElementById('book').scrollIntoView({ behavior: 'smooth' })` when `innerWidth < 768`).
The older `.cq-split` of `tokens.css` (list a third, detail two thirds, unstyled children) still applies on pages of
`layoutblocks.pug`; the rules above are scoped to `site.pug` pages (`body.cq-site`).

## Pug in Caligrafy is PHP

Caligrafy renders Pug with PHP expressions: `+` adds numbers and never joins strings (G98). Join with `.`, or let Pug
merge classes: `div.cq-alert(class=($failed ? 'cq-alert--error' : null))`. Version your own assets the way the
master does, so a deploy reaches every browser: `script(src=$home . 'public/js/app.js?v=' . @filemtime(PUBLIC_PATH . 'js/app.js'))`.
A dollar sign before digits vanishes (`$40` shows nothing): write `&#36;40` (G105). Never put code on the line of a
`- if (...)` or `- foreach (...)`: use `if` / `each` with the lines under them (G106). `doctor` names both.

A section of your own page with its own colors (a dark banner, a colored band) must also restyle the buttons in it:
`look.css` sets them with `html body .btn-primary` and `html body .btn-secondary:not(.btn-primary)` (and their `:hover`,
`a.btn-primary:link`, `:visited`), so a rule like `.my-band .btn-primary` loses. Scope yours the same way, in the page's
own stylesheet: `html body .my-band .cq-cta .btn.btn-primary { background-color: #fff; border-color: #fff; color: #0a0a0b; }`,
with its `:hover`, `:link` and `:visited`; check the buttons at rest, hovered and after a visit.

## Vue client apps (`public/<app>/index.php`) and the SPA

Same classes inside the app's root element: `body.cq-site > #app > nav.cq-nav + section.cq-hero.cq-hero--page +
section.cq-band > .cq-inner > ...` (the app template ships that way). The SPA template (`framework/librairies/app`)
imports `layout.css` and `look.css` in `src/main.js`, adds `cq-site` to the body, and its components are the top bar
(`ShowNavigation.vue`), the title band (`ShowMessage.vue`) and tiles (`ShowGeneralCard.vue`).

## Camera pages

Face detection, machine learning and face sign-in fill the screen with the camera (`public/css/camera.css`):
`body.cq-camera-body > .cq-camera > .cq-camera-media` (video and canvas, 640 x 480, `object-fit: cover`) +
`.cq-camera-top` (`h1.cq-camera-title`, `.cq-camera-status`) + `.cq-camera-bottom` (`.cq-camera-actions > button`,
`.cq-camera-button.is-primary` for the main action, white over the camera). `.cq-camera-oval` guides a face
(`.is-scanning`, `.is-success`, `.is-failed`); `.cq-camera-sheet` over a `.cq-camera-backdrop` holds a form (its main
action is black); `.cq-camera-mirror` flips a selfie camera. The ids the scripts look for stay: `#detector`,
`#infobar`, `#apps`.

## Older pages: the card components

Pages built on `default/master/layoutblocks.pug` before 2.11.0 keep working, with the card components of `tokens.css`
(`main.cq-page > .cq-card > .cq-card-head (h2.cq-title + p.cq-note)`, `form.cq-form > .cq-fields`, `.cq-submit`,
`.cq-error`, `.cq-tabs`, `ul.cq-list > li.cq-item`, `.cq-empty`, `.cq-foot`) and the look of `look.css`, which
`layoutblocks.pug` also loads. Add new pages to such a project with `site.pug`; move an old page when you rework it.

## Design styles

The structure above is the markup of every page, whatever the style. A project can give every page another look with
a design style (`php caligrafer.php design use <name>`, `DESIGN_STYLE` in `.env`): the masters then load
`public/css/designs/<name>.css` after `look.css`, which overrides the `--design-*` and `--cq-*` tokens and the
selectors. So:
- build pages with the same classes and tokens as always; never write style-specific markup or classes;
- restyle only in `public/css/designs/<name>.css`, with the `design` skill (`/caligrafy-quill:design`), never in
  `layout.css`, `look.css`, `tokens.css`, `theme.css`, `styles.css` or a view: an empty `DESIGN_STYLE` must give the
  Caligrafy look back (G92);
- a new class you add to a page under a selected style needs its rule in the style's stylesheet too: check the page
  with the style on and off;
- the application keeps its own name and logo; a style is inspiration, never another site's brand.

## Checklist before you say a page is done

- [ ] `extends ../default/master/site`, a `block title`, a title band (or a hero) then bands; nothing nested in cards
- [ ] every input has a `label`; the primary action is `btn btn-primary`; errors show next to the action that failed
- [ ] lists use `ul.cq-rows > li.cq-row` with `.cq-row-actions`; an empty state says what to do next; a list with an opened item or its form uses `.cq-split`
- [ ] no Bootstrap color classes, no hard-coded colors, no page-level `style` block unless a part has no class
- [ ] elements found by id; Pug expressions join strings with `.`, never `+`
- [ ] opened in a browser at desktop width and at phone width (375px): no horizontal scroll, readable, touch-size buttons, the Menu opens the links
- [ ] with a design style selected (`DESIGN_STYLE`): checked with the style on and off
