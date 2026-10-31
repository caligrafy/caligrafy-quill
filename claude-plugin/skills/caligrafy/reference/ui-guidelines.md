# UI guidelines: every new page looks like the sign-in and face login screens

Every page or client app you generate in a Caligrafy Quill project uses the **page components** of
`public/css/tokens.css` (Quill 2.1.3+). They give the Caligrafy look: one centered white card under the fixed header,
labeled fields, a black primary button, tabs with counts, list rows with clear actions, black and white only.
`tokens.css` is linked by the default layout (`layoutblocks.pug`), the Vue app template (`framework/plugins/app`)
and the SPA template (`src/main.js`): **no page-specific CSS is needed**. If a project's `public/css/tokens.css` has
no `.cq-page` rule, it predates 2.1.3: copy the file from the framework (or update the framework) first.

## The components

| Part | Markup | Notes |
| --- | --- | --- |
| Page | `main.cq-page` | Starts below the fixed header (120px), centers the card. `.cq-page--wide` for 960px cards |
| Card | `.cq-card` | White, rounded, shadowed, 640px max, full width on phones. Several cards stack with a gap |
| Card header | `.cq-card-head > h2.cq-title + p.cq-note` | The title says what the page is; the note one line of context |
| Form | `form.cq-form > .cq-fields > div > label + input.form-control` | `.cq-fields--2` / `--3`: columns from 576px up, one column on phones. Every field has a `label` |
| Submit | `button.btn.btn-primary.cq-submit` | Full-width black button (Bootstrap's `w-100` is overridden by the theme: use `cq-submit`) |
| Error | `p.cq-error(role='alert')` | Under the form; show `ServiceError` field messages here |
| Tabs | `.cq-tabs(role='tablist') > button(role='tab')` + `span.cq-count` | Active tab: `.active` + `aria-selected`. Use for filters and views of the same list |
| List | `ul.cq-list > li.cq-item` | Row: optional `input.form-check-input.cq-check`, `.cq-item-body > p.cq-item-title + p.cq-item-meta`, `.cq-actions > .btn` |
| Done / inactive | `li.cq-item.is-done` | Greys and strikes the title |
| Inline edit | `.cq-inline-edit > input.form-control.form-control-sm` | Replace the title in place; Save / Cancel in `.cq-actions` |
| Empty state | `p.cq-empty` | Always say what to do next ("No books yet: add the first one above.") |
| Other content | `.cq-section` | Padding matching the card: details, text, a table |
| Footer | `.cq-foot` | Small grey line at the bottom of the card |
| Buttons | `.btn.btn-primary` (black), `.btn.btn-secondary` (white, black border), `.btn.btn-outline-dark` | Never Bootstrap colors (`btn-success`, `btn-danger`, `text-primary`...) |
| Layout split | `.cq-split` | One column on phones, list and detail side by side from tablets up |

Colors and spacing come from the tokens (`var(--cq-color-ink)`, `var(--cq-color-text-muted)`, `var(--cq-space-3)`...),
never hard-coded hex values in new code.

## Page skeleton (Pug view)

```pug
extends ../default/master/layoutblocks

block content
    main.cq-page
        .cq-card
            .cq-card-head
                h2.cq-title Books
                p.cq-note Every book of the catalog.
            //- form, error, tabs, list, empty state, footer: in this order
```
Never start a page with a bare heading or `.spacer-2`: the fixed header covers the first ~80px (gotcha G59).

## Worked example: a list with a form, filters and actions

The tasks page of the build-once example: a form to add, tabs with counts, rows with a checkbox, Edit and Delete,
an empty state, and the data from the capabilities through the generated JS client
(`php caligrafer.php client public/js/capabilities.js`).

```pug
extends ../default/master/layoutblocks

block content
    main.cq-page
        .cq-card
            .cq-card-head
                h2.cq-title Tasks
                p.cq-note Changes made from REST or MCP appear here within a few seconds.

            form#new-task.cq-form(novalidate)
                .cq-fields.cq-fields--2
                    div
                        label(for='task-title') * Task
                        input#task-title.form-control(type='text', name='title', placeholder='What has to be done?', maxlength='200', autocomplete='off')
                    div
                        label(for='task-notes') Notes
                        input#task-notes.form-control(type='text', name='notes', placeholder='Optional', maxlength='1000', autocomplete='off')
                button.btn.btn-primary.cq-submit(type='submit') Add task

            p#error.cq-error(role='alert')

            .cq-tabs(role='tablist')
                button.active(type='button', role='tab', aria-selected='true', data-status='all') All
                    span.cq-count(data-count='all') 0
                button(type='button', role='tab', aria-selected='false', data-status='open') Open
                    span.cq-count(data-count='open') 0
                button(type='button', role='tab', aria-selected='false', data-status='done') Done
                    span.cq-count(data-count='done') 0

            ul#list.cq-list
            p#empty.cq-empty(hidden) No tasks here yet.

            .cq-foot Updates every few seconds

block additional_scripts
    script(type='module', src="{$home}public/js/tasks.js")
```

A row built by the script (plain DOM, or the same classes in a Vue template):
```js
li.className = 'cq-item' + (done ? ' is-done' : '')
// input.form-check-input.cq-check | .cq-item-body > p.cq-item-title + p.cq-item-meta | .cq-actions > .btn
```
Behavior that goes with the look:
- show `CapabilityError` field messages in `.cq-error`, never `alert()`;
- confirm destructive actions (`confirm('Delete "..."?')`) and rename inline (`.cq-inline-edit`), never `prompt()`;
- a periodic refresh must not replace a row that is being edited;
- after adding, reset the form and put the focus back in the first field.

## Vue client apps (`public/<app>/index.php`) and the SPA

Same classes inside the app's root element: `#app > main.cq-page > .cq-card > ...`. Apps that draw their own
Caligrafy header (`header.color0.caligrafy-header`) keep `.cq-page` for the offset. Specialized screens keep their
own parts (the FaceDetect camera, a chat thread) but sit in a `.cq-card` with a `.cq-card-head`.

## Checklist before you say a page is done

- [ ] `main.cq-page > .cq-card`, with a `.cq-card-head` (title + note); nothing hidden under the header
- [ ] every input has a `label`; the primary action is `btn btn-primary cq-submit`; errors go to `.cq-error`
- [ ] lists use `.cq-list > .cq-item` with `.cq-actions`; an empty state says what to do next
- [ ] no Bootstrap color classes, no hard-coded colors, no page-level `style` block unless a part has no component
- [ ] opened in a browser at desktop width and at phone width (375px): no horizontal scroll, readable, touch-size buttons
