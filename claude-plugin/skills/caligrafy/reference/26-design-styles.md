# Design styles: another look for every page

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Where this fits.** Every Caligrafy page has the same look out of the box: the sign-in card, the [page components](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#pagecomponents), black and white. A design style gives an application another look, taken from a design system description, without touching that default: one setting picks it, one stylesheet applies it, and emptying the setting brings the Caligrafy look back.

## What a design style is

A design style is a `DESIGN.md`: a plain-text description of a website's design system (its colors, typography, spacing, components, do's and don'ts) written for AI agents to read. Everything about design styles lives in one folder, `framework/design/`:

```
framework/design/
  vendor/<vendor>/<name>/DESIGN.md   collections copied from elsewhere, each with its license and attribution
      awesome-design-md/             84 styles from VoltAgent/awesome-design-md (MIT License, copyright VoltAgent)
  local/<name>/DESIGN.md             styles added to Caligrafy itself, their origin in local/README.md
      interstellar/                  a calm, dense laboratory operations system (cool grey canvas, Inter, one brand blue)
application/design/<name>/DESIGN.md  your own styles: drop a DESIGN.md here
```

A style's name is its folder name. When a name exists in several places, the first one wins: `application/design`, then `framework/design/local`, then each vendor in alphabetical order. `design list` shows where each style comes from; `design show <name>` also names the copies it hides.

The styles describe the visual language of public websites to inspire yours: your application keeps its own name and logo, and never presents itself as one of those sites.

## Picking a style

```bash
php caligrafer.php design list              # the 85 styles, with one line each
php caligrafer.php design show linear.app   # its DESIGN.md and its stylesheet
php caligrafer.php design use linear.app    # DESIGN_STYLE=linear.app in .env
php caligrafer.php design status            # the selected style
php caligrafer.php design off               # DESIGN_STYLE= : the Caligrafy look again
```

Or set it in `.env` yourself:

```bash
# DESIGN_STYLE: empty = Caligrafy's own look
DESIGN_STYLE=linear.app
```

## Restyling the pages with Claude

A `DESIGN.md` is read by an AI agent, which writes the stylesheet. In Claude Code, with the [Caligrafy plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin):

```
/caligrafy-quill:design linear.app
```

Claude reads the whole `DESIGN.md` (its tokens and its prose), looks at the pages of your project, and writes **one** file, `public/css/designs/linear.app.css`: the design's colors, fonts (free substitutes from Google Fonts), radii and spacing applied to the layout (header, footer), the sign-in pages, the buttons and fields, and every page component (`.cq-card`, `.cq-form`, `.cq-tabs`, `.cq-list`...). It selects the style, then opens the sign-in pages and your pages in a browser at desktop and phone width and fixes what does not read well. `/caligrafy-quill:design off` goes back to the Caligrafy look.

## How it works

+ The layout (`application/views/default/master/layoutblocks.pug`) and the Vue app template (`public/<app>/index.php`) link `public/css/designs/<name>.css` **after** `styles.css`, Bootstrap, `theme.css` and `tokens.css`, with `design_stylesheet()`. The stylesheet overrides the design tokens (`--cq-color-ink`, `--cq-color-paper`...) and the Caligrafy selectors; `tokens.css`, `theme.css`, `styles.css` and the views are never edited.
+ Nothing is linked when `DESIGN_STYLE` is empty, names no style, or names a style whose stylesheet is not written yet: those pages keep the Caligrafy look.
+ The link carries the stylesheet's modification time (`?v=...`), so browsers pick up every change.
+ Several stylesheets can live side by side in `public/css/designs/`: switching is `design use <other>`.
+ A Vite single page application imports its CSS at build time: add `import '../../css/designs/<name>.css'` to its `src/main.js` (the skill does it) and rebuild.
+ `php caligrafer.php doctor` reports the selected style: `OK` with its stylesheet, `WARN` for an unknown name or a style whose stylesheet is not written yet.

## Online

Commit `public/css/designs/<name>.css` (it is part of your application) and set `DESIGN_STYLE` where the application runs: in the server's `.env`, or on Caligrafy Cloud with `php caligrafer.php cloud secret set <app> DESIGN_STYLE=<name>`. The production image leaves `framework/design/` out: only the stylesheet is needed at run time.

## Updating the styles

`sh framework/design/vendor/awesome-design-md/sync.sh` copies the latest `DESIGN.md` files from VoltAgent/awesome-design-md (and `SOURCE` in that folder records the commit); it never touches the other vendors, `local/` or `application/design/`. Another collection is another folder in `framework/design/vendor/`, with its license and a README that credits it. Framework updates bring new styles too.
