# Design styles: another look for every page

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

A **design style** gives the whole application another look, taken from the description of a design system, without changing your views. One setting in `.env` picks it (`DESIGN_STYLE`), one stylesheet applies it (`public/css/designs/<name>.css`), and emptying the setting brings back [Caligrafy's own look](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#pagecomponents): a white canvas, a light top bar with the Caligrafy mark, black for the main action, Inter type, no cards. Since 2.8.0.

## With Claude

A style is a text file written for AI agents, so Claude writes its stylesheet. In Claude Code, with the [Caligrafy plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin):

```text
/caligrafy-quill:design linear.app
```

Claude reads the whole `DESIGN.md` of the style (its tokens and its prose), looks at the pages and client apps of your project, and writes **one** file, `public/css/designs/linear.app.css`: the style's colors, fonts (free substitutes from Google Fonts), radii and spacing, applied first to the `--design-*` tokens of Caligrafy's look, then to the top bar and footer, the sign-in pages, the buttons and fields, every page component (`.cq-hero`, `.cq-band`, `.cq-rows`, `.cq-tiles`, `.cq-segments`...), the camera pages and the older card components. It selects the style (`design use`), then opens the sign-in pages and your pages at desktop and phone width and fixes what does not read well. It never edits `look.css`, `layout.css`, `tokens.css`, `theme.css`, `styles.css` or your views. Without a name it asks which style; your own brand becomes `application/design/<name>/DESIGN.md` first. `/caligrafy-quill:design off` goes back to the Caligrafy look.

**Check** before you say OK:
+ `php caligrafer.php design status` says `OK  <name>: public/css/designs/<name>.css loaded after the Caligrafy stylesheets`.
+ The sign-in page, a list page and a form page read well at desktop and phone width: text in fields and on buttons, error messages, the top bar and its Menu.
+ `git status` shows one new file, `public/css/designs/<name>.css` (and `.env`): nothing else changed.
+ A Vite single page app imports the stylesheet in its `src/main.js` and was rebuilt.

## By hand

You pick a style, write its stylesheet, check it, and go back to the Caligrafy look. Commands run in the project folder with the Docker server of the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart); with PHP on your computer, drop `docker compose exec dev-box` (`.bin/caligrafer design ...` works too).

**1. See the styles.** Run:
```bash
docker compose exec dev-box php caligrafer.php design list
```
The first line is `85 styles (* selected, + stylesheet written). DESIGN_STYLE empty = Caligrafy's own look (now)`, then one line per style: its name, where it comes from, one line of description.

**2. Read one.** Run `docker compose exec dev-box php caligrafer.php design show linear.app`: its whole `DESIGN.md`. Note its colors (`canvas`, `surface-1`, `ink`, `ink-muted`, `ink-subtle`, `hairline`, `primary`, `primary-hover`, `on-primary`) and its radii (`rounded`).

**3. Select it.** Run:
```bash
docker compose exec dev-box php caligrafer.php design use linear.app
```
Expected:
```text
 DESIGN_STYLE=linear.app in .env. Its stylesheet public/css/designs/linear.app.css is not written yet: pages keep Caligrafy's own look until it is.
```
Nothing changes on the pages yet: a style without its stylesheet keeps the Caligrafy look.

**4. Write the stylesheet.** Write `public/css/designs/linear.app.css`, with the values of the `DESIGN.md`:
```css
/* linear.app: from framework/design/vendor/awesome-design-md/linear.app/DESIGN.md */
:root {
  --design-canvas: #010102;
  --design-surface: #0f1011;
  --design-ink: #f7f8f8;
  --design-body: #d0d6e0;
  --design-muted: #8a8f98;
  --design-hairline: #23252a;
  --design-primary: #5e6ad2;
  --design-primary-hover: #828fff;
  --design-on-primary: #ffffff;
  --design-radius-control: 6px;
  --design-radius-card: 8px;
}

/* a dark canvas: the fields and the top bar too */
html body .form-control,
html body .form-select {
  background-color: var(--design-surface);
  border-color: var(--design-hairline);
  color: var(--design-ink);
}
html body .cq-nav {
  background-color: rgba(1, 1, 2, 0.85);
  border-bottom-color: var(--design-hairline);
}
```
The `--design-*` tokens go on `:root`: `look.css` builds the `--cq-*` tokens of every page from them there. Every other rule starts with `html body`: Caligrafy's stylesheets set fonts on `body *` and buttons on `html .btn-primary`, and a design stylesheet wins by being more specific, not with `!important`.

**5. Check it.** Run `docker compose exec dev-box php caligrafer.php design status`. Expected:
```text
 OK  linear.app: public/css/designs/linear.app.css loaded after the Caligrafy stylesheets
```
Open `http://localhost:8080/books` (or any page): a near-black page, light headings and text, dark fields, a lavender **Add book** button. In the page source the last stylesheet of the `head` is `public/css/designs/linear.app.css?v=<number>`. `docker compose exec dev-box php caligrafer.php doctor` prints the same line (`OK    Design style: ...`).

**6. Back to the Caligrafy look.** Run:
```bash
docker compose exec dev-box php caligrafer.php design off
```
Expected: `DESIGN_STYLE= in .env: every page has Caligrafy's own look again. The stylesheets in public/css/designs/ are kept.` The pages are white again and link no design stylesheet.

**Check**
+ With the style selected, every page and every Vue app of `public/<app>` link `public/css/designs/<name>.css` last.
+ With `DESIGN_STYLE=` empty, nothing is linked and the pages look as before.
+ No file of `public/css/` other than `designs/<name>.css` was edited.

## Reference

### What a design style is

A design style is a `DESIGN.md`: a plain-text description of a website's design system (its colors, typography, spacing, components, do's and don'ts) written for AI agents to read. Most start with YAML front matter (`colors`, `typography`, `rounded`, `spacing`, `components`); some are prose only. They live in three places:

```text
framework/design/
  vendor/<vendor>/<name>/DESIGN.md   collections copied from elsewhere, each with its license and attribution
      awesome-design-md/             84 styles from VoltAgent/awesome-design-md (MIT License, copyright VoltAgent)
  local/<name>/DESIGN.md             styles added to Caligrafy itself, their origin in local/README.md
      interstellar/                  a calm, dense laboratory operations system (cool grey canvas, Inter, one brand blue)
application/design/<name>/DESIGN.md  your own styles: put a DESIGN.md here
```

A style's name is its folder name: lowercase letters, digits, `.`, `_` and `-`. When a name exists in several places, the first wins: `application/design`, then `framework/design/local`, then each vendor in alphabetical order. `design list` shows where each style comes from; `design show <name>` also names the copies it hides.

The styles describe the visual language of public websites to inspire yours: your application keeps its own name and logo, and never presents itself as one of those sites.

### Commands

| Command | What it does |
| --- | --- |
| `php caligrafer.php design list` | every style: `*` selected, `+` stylesheet written (`--json` for a program) |
| `php caligrafer.php design show <name>` | its `DESIGN.md`, its source and its stylesheet |
| `php caligrafer.php design use <name>` | writes `DESIGN_STYLE=<name>` in `.env`, nothing else; an unknown name exits with `1` (`unknown style '<name>'`) |
| `php caligrafer.php design status` | the selected style: `OK` with its stylesheet, `WARN` for an unknown name or a stylesheet not written yet |
| `php caligrafer.php design off` | writes `DESIGN_STYLE=` and keeps the stylesheets |

You can also set it in `.env` yourself:
```bash
# DESIGN_STYLE: empty = Caligrafy's own look
DESIGN_STYLE=linear.app
```

### How it works

+ The masters (`application/views/default/master/site.pug` and the older `layoutblocks.pug`), the Vue app template (`public/<app>/index.php`) and the camera apps link `public/css/designs/<name>.css` **last**, after `styles.css`, Bootstrap, `theme.css`, `tokens.css`, `layout.css`, `camera.css` and `look.css`, with the helper `design_stylesheet()`. In `site.pug`, a page's `additional_css` block comes before it.
+ `design_stylesheet()` returns `''` (nothing is linked) when `DESIGN_STYLE` is empty, names no style, or names a style whose stylesheet does not exist yet.
+ The link carries the stylesheet's modification time (`?v=...`), so browsers fetch every change.
+ Several stylesheets can live side by side in `public/css/designs/`: switching is `design use <other>`.
+ A client app you write yourself links it in its `public/<app>/index.php`, after its other stylesheets: `<?php if (design_stylesheet()): ?><link rel="stylesheet" href="<?php echo design_stylesheet(); ?>"><?php endif; ?>`.
+ A Vite single page app imports its CSS when it is built: add `import '../../css/designs/<name>.css'` to its `src/main.js`, after the `look.css` import, and rebuild (`npm run build`). That build then always has the style, whatever `DESIGN_STYLE` says.

### The tokens a stylesheet overrides

`look.css` sets these on `:root` and builds the `--cq-*` tokens of every page from them: `--design-canvas`, `--design-surface`, `--design-surface-raised`, `--design-ink` (headings), `--design-body` (text), `--design-muted`, `--design-placeholder`, `--design-hairline`, `--design-hairline-strong`, `--design-primary`, `--design-primary-hover`, `--design-on-primary`, `--design-focus`, `--design-error`, `--design-success`, `--design-overlay`, `--design-font`, `--design-font-code`, `--design-radius-control`, `--design-radius-card`, `--design-transition`. Override them on `:root`: set on `html body`, they would not reach the `--cq-*` tokens. Camera pages have their own `--cq-camera-*` tokens in `camera.css`. Buttons also take Bootstrap's `--bs-btn-*` variables from `theme.css`: to restyle them beyond the tokens, redefine the whole `--bs-btn-*` set on `html body .btn-primary` and set `background-color`, `border-color` and `color` too. The sign-in button is `btn btn-primary btn-secondary`: style `.btn-primary.btn-secondary` as the main action.

### Online

Commit `public/css/designs/<name>.css` (it is part of your application) and set `DESIGN_STYLE` where the application runs: in the server's `.env`, or on Caligrafy Cloud with `php caligrafer.php cloud secret set <app> DESIGN_STYLE=<name>`. The production image leaves `framework/design/` out: only the stylesheet is needed at run time.

### Updating the styles

`sh framework/design/vendor/awesome-design-md/sync.sh` copies the latest `DESIGN.md` files from VoltAgent/awesome-design-md (`SOURCE` in that folder records the commit); it never touches the other vendors, `local/` or `application/design/`. Another collection is another folder in `framework/design/vendor/`, with its license and a README that credits it. Framework updates bring new styles too.

## Common mistakes

+ **The style is selected but nothing changed**: its stylesheet does not exist yet. `design status` says so.
+ **The tokens on `html body`**: the canvas changes but headings keep their color, because the `--cq-*` tokens are built on `:root`. Put the `--design-*` tokens on `:root`.
+ **A dark style with white fields**: the tokens do not cover every part. Look at the fields, the top bar and the alerts, and add `html body` rules for them.
+ **Editing `look.css`, `tokens.css` or a view to restyle**: the Caligrafy look would not come back with `design off`, and the QA suite checks that `tokens.css` and `tokens.json` match. Write only `public/css/designs/<name>.css`.
+ **`!important` everywhere**: prefix the rules with `html body` instead.
+ **Online, the old look**: the stylesheet was not committed, or `DESIGN_STYLE` is not set on the server.
+ **A Vite app keeps the old look**: it needs the import in `src/main.js` and a new build.

## Related pages

+ [Views: Caligrafy's look and the page components](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#pagecomponents)
+ [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin)
+ [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer)
+ [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online)
