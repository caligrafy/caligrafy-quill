---
name: design
description: Restyle every page of a Caligrafy Quill project to match a design style - a DESIGN.md of framework/designs (84 styles from VoltAgent/awesome-design-md, MIT), framework/designs-extra (interstellar...) or the project's own application/designs/<name>/DESIGN.md - by writing ONE override stylesheet, public/css/designs/<name>.css, that the layout loads after the Caligrafy stylesheets when DESIGN_STYLE=<name> in .env. Caligrafy's own look stays the default and comes back when DESIGN_STYLE is emptied.
when_to_use: The user asks to restyle, theme or re-skin the application, to make it look like a website or a design system, to use a DESIGN.md, a design style, DESIGN_STYLE or php caligrafer.php design, or to go back to the Caligrafy look ("/caligrafy-quill:design linear.app", "/caligrafy-quill:design off").
argument-hint: "[style-name | off]"
---

# Design: one DESIGN.md, one override stylesheet, every page restyled

Style: `$ARGUMENTS` (a style name; empty = the `DESIGN_STYLE` of `.env`, or ask; `off` = back to the Caligrafy look).

How it fits together (Quill 2.8+):
+ `framework/designs/<name>/DESIGN.md`: 84 design systems of public websites (colors, type, spacing, components,
  do's and don'ts) from [VoltAgent/awesome-design-md](https://github.com/VoltAgent/awesome-design-md), MIT, attribution
  in `framework/designs/README.md` and `LICENSE`; `framework/designs-extra/<name>/DESIGN.md`: styles added outside
  that collection (`interstellar`), their origin in its README. A project's own `application/designs/<name>/DESIGN.md` wins.
+ `DESIGN_STYLE=<name>` in `.env` selects one; empty is Caligrafy's own look.
+ The layout (`layoutblocks.pug`) and the Vue app template (`framework/plugins/app/index.php`) link
  `public/css/designs/<name>.css` **after** styles.css, Bootstrap, theme.css and tokens.css, through
  `design_stylesheet()`, only when that file exists. Unknown name or no stylesheet: the Caligrafy look, unchanged.
+ `php caligrafer.php design list | show <name> | use <name> | off | status`; `doctor` reports the selected style.

**The one rule: write only `public/css/designs/<name>.css`.** Never edit `tokens.css`, `tokens.json` (the QA suite
checks they match), `theme.css`, `styles.css`, the layout or the views to restyle: the user's Caligrafy look must come
back intact the moment `DESIGN_STYLE` is emptied. If a page truly cannot be styled without a markup change, tell the
user which one and why instead of making it.

Commands: `php caligrafer.php ...` when PHP 8.3+ is on this computer (`php -v`), otherwise in the Docker server:
`docker compose exec -T dev-box php caligrafer.php ...`. Below, `caligrafer` means whichever works. Never print `.env`.

## 1. Pick the style

- `off`: `caligrafer design off`, say the Caligrafy look is back (the stylesheets stay in `public/css/designs/`, unused),
  check one page in the browser, stop.
- A name: `caligrafer design show <name>`. Unknown: `caligrafer design list` and offer the closest names.
- Empty: `caligrafer design status`. Nothing selected: ask which style, with three or four fitting suggestions from
  `caligrafer design list` (light editorial, dark developer, colorful consumer...) and "Other". A project without
  `framework/designs/` (before 2.8): fetch the one file the user picked,
  `https://raw.githubusercontent.com/VoltAgent/awesome-design-md/main/design-md/<name>/DESIGN.md`, into
  `application/designs/<name>/DESIGN.md` with a line crediting VoltAgent/awesome-design-md (MIT).
- The user's own design ("our brand", a Figma, a site they own): write `application/designs/<name>/DESIGN.md` in the
  same sections first, then continue with it.

## 2. Read the DESIGN.md completely

Most files open with YAML front matter: `colors`, `typography` (fontFamily, fontSize, fontWeight, lineHeight,
letterSpacing per role), `rounded`, `spacing`, `components` (button-primary, card, text-input...: background, text
color, radius, padding, height, with `{colors.x}` references). The prose explains what the tokens can't: the
atmosphere, elevation (shadows or borders), the font substitutes, do's and don'ts, responsive behavior. About ten files
are prose only: take the hex values and sizes from their tables. Note before writing anything:
- canvas, surface (card), ink (headings), body text, muted text, hairline (borders), primary (CTA) and its text,
  secondary button, focus ring, error, success; whether the style is light or dark;
- display and body fonts and their **free substitutes** (Google Fonts); weights; heading sizes; letter spacing;
- radius of buttons, inputs, cards, pills; shadows or hairlines for cards; button height and padding;
- the "Don't" list: it is the quickest way to tell a faithful restyle from a generic one.

## 3. See what the project shows

Pages share a few surfaces, so one stylesheet covers them all ([surfaces.md](reference/surfaces.md) maps every
selector to its design role and the Caligrafy rule it has to beat):
- the layout: `header` (fixed, 60px, `.color0`, the "Caligrafy" wordmark in `h1.large-text`), body background,
  `footer`, the `.shadowCoverTop` / `.radialShadowTop` strips;
- the sign-in pages (`Auth/login.pug`, `register.pug`): `.card.color0.dropshadow` cards, `.form-control.input-default`,
  `label.textcolor5`, `.btn.btn-primary.btn-secondary` (the main action), `.btn-secondary` (Sign up), alerts;
- the page components of `tokens.css`: `.cq-page`, `.cq-card`, `.cq-card-head`, `.cq-title`, `.cq-note`, `.cq-form`,
  `.cq-submit`, `.cq-error`, `.cq-tabs`, `.cq-count`, `.cq-list`, `.cq-item`, `.cq-check`, `.cq-actions`, `.cq-empty`,
  `.cq-foot`, `.cq-section`, `.cq-split`;
- the project's own views and apps: `grep -rhoE "class=['\"][^'\"]+|\.[a-z][a-z0-9-]+" application/views` and the
  `public/<app>/index.php` templates. Vite SPAs (`public/<app>/src/main.js`) import the stylesheets at build time:
  add `import '../../css/designs/<name>.css'` after the tokens import there and say so (it is then always on for
  that SPA until removed).

## 4. Write `public/css/designs/<name>.css`

Follow the six sections of the two worked examples, written by this skill and checked at desktop and phone width:
[examples/claude.css](examples/claude.css) (a light style: cream canvas, serif headings, hairline cards) and
[examples/linear.app.css](examples/linear.app.css) (a dark one: layered surfaces, one accent). Fill every section
from your notes, never from the example's values:
1. A header comment: the style, its source (`framework/designs/<name>/DESIGN.md`, VoltAgent/awesome-design-md, MIT),
   "loaded after the Caligrafy stylesheets when DESIGN_STYLE=<name>; empty it to get the Caligrafy look back".
2. Fonts: `@import` the free substitutes from Google Fonts at the very top. Never link or embed proprietary fonts
   (Söhne, SF Pro, Copernicus, Styrene, Circular...): name them first in `font-family` only when the DESIGN.md does,
   followed by the substitute and a generic family.
3. `:root`: the design's values as `--design-*` custom properties, and the Caligrafy tokens they replace
   (`--cq-color-ink`, `--cq-color-paper`, `--cq-color-page`, `--cq-color-text`, `--cq-color-text-muted`,
   `--cq-color-border`, `--cq-color-accent-dark`, `--cq-color-accent-red`, `--cq-radius-control`). Every rule below
   uses these variables, never repeated hex values.
4. Layout, then sign-in surfaces, then Bootstrap controls (buttons through their `--bs-btn-*` variables, fields, focus),
   then the page components, then phone width (`@media (max-width: 575px)`).

Rules that keep it working:
- Beat the Caligrafy selectors by **specificity**, not `!important`: prefix with `html body` (theme.css uses
  `html .btn-primary`, styles.css `body *`). `!important` only against an inline `style` or a Bootstrap utility.
- `styles.css` sets `font-family` and `font-weight: lighter` on `body *`: reset both on `html body *` and restore
  weights on headings, labels, buttons and `.semibold`.
- Keep the structure: the 60px fixed header, `.cq-page`'s 76px top padding, card widths, the one-column phone
  layout. Change colors, type, radii, borders, shadows, spacing inside cards; not the page skeleton.
- A dark style restyles everything that is white by default: `.color0`, `.card.color0`, `.cq-card`, `.cq-tabs button`,
  `.cq-count`, `.form-control` (text, placeholder, background, autofill), alerts, `.cq-note code`, `.btn-secondary`.
- Accessible: text contrast at least 4.5:1 (3:1 for large text and control borders), visible focus on buttons,
  fields, tabs and checkboxes, touch targets stay 44px (`--cq-touch-target`).
- The application keeps its own name and logo: never add the inspiring site's logo, wordmark, brand name, product
  photos or slogans, and never make a page that passes itself off as that site. The style is inspiration.
- No Bootstrap color classes in markup and no page `style` blocks: the stylesheet does the work.

Then select it: `caligrafer design use <name>` (it sets `DESIGN_STYLE=<name>` in `.env`; the layout adds `?v=<mtime>`
so the browser picks up every edit).

## 5. Check every page in a browser, desktop and phone

Open the pages at 1280 px and 375 px wide (the built-in browser or Claude in Chrome): `/login`, `/register`, each page
of `application/views` and its routes (the Tasks example at `/tasks` with a few tasks added, an empty state, a done
task, a tab switched), the client apps (`/apps/<name>`), signed out and signed in. For each one:
- compare with the DESIGN.md: canvas, card, primary button, fields, type, radii, the "Don't" list;
- nothing unreadable (white on white, dark text on a dark card, placeholders, disabled buttons, autofill);
- focus a field and a button with the keyboard; hover the buttons; no horizontal scroll on the phone;
- the console has no errors and the stylesheet loads (`link[href*="css/designs/"]`).
Fix the stylesheet and reload until all of them pass. Show the user desktop and phone screenshots of the sign-in page
and of a component page, say which file you wrote, and how to switch back: `caligrafer design off` (or another style
with `design use <name>`, then this skill again).

`caligrafer doctor` must then report `OK Design style: <name>: public/css/designs/<name>.css loaded...`.
Commit only when the user asks: the stylesheet, `.env` stays out of git (set `DESIGN_STYLE` on the production server
or Caligrafy Cloud: `caligrafer cloud secret set <app> DESIGN_STYLE=<name>`).
