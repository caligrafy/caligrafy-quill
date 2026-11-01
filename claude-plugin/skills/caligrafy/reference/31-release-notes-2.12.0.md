# Release notes: 2.12.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.12.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

![Caligrafy: Build Once, Experience Everywhere](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/build-once-banner.png)

Caligrafy Quill **2.12.0** adds one part to [Caligrafy's look](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#pagecomponents): a list and its detail, side by side. Nothing changes in existing pages. The full list is in the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

## A list and its detail, side by side

A page that shows a list and the item opened (a finder, an inbox, a catalog and its add form) now puts them side by side from tablets up: the list on the left, the item in an ash panel on the right that stays in view while the list scrolls. On phones the panel comes after the list.

```pug
.cq-split
  section.cq-split-main
    //- search, ul.cq-rows, the empty state
  aside.cq-split-detail
    //- the item opened, or a form (one field a line)
```

It goes right after the title band, in Pug views, Vue apps and single page applications alike. See [side by side](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#sidebyside). The [course](https://github.com/caligrafy/caligrafy-quill-course/wiki) builds its book finder and its single page application this way, and the [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin) uses it for every list with a detail.

The older `.cq-split` of `tokens.css` keeps working on pages of the `layoutblocks` master.

## Updating a project

```bash
php caligrafer.php update
```

Or, in Claude Code, `/caligrafy-quill:update`. A single page application imports `layout.css` at build time: rebuild it after the update.
