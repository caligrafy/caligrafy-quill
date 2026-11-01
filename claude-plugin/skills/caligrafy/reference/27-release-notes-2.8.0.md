# Release notes: 2.8.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.8.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.8.0** brings **design styles**: one setting gives every page another look, and Claude restyles the pages to match it. Caligrafy's own black and white look stays the default, and nothing changes until you pick a style. Nothing breaks in existing projects. The full list is in the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

## Design styles

A design style is a `DESIGN.md`: a plain description of a design system (colors, type, spacing, components), written for AI agents to read. They all live in one folder:

```
framework/design/
  vendor/awesome-design-md/<name>/DESIGN.md   84 styles from VoltAgent/awesome-design-md (MIT)
  local/interstellar/DESIGN.md                styles added to Caligrafy itself
application/design/<name>/DESIGN.md           your own: drop a DESIGN.md here
```

Pick one and let Claude restyle the pages:

```bash
php caligrafer.php design list              # every style, with one line each
php caligrafer.php design use linear.app    # DESIGN_STYLE=linear.app in .env
```
```
/caligrafy-quill:design
```

+ **One stylesheet.** The `design` skill reads the style's `DESIGN.md` and writes `public/css/designs/<name>.css`, loaded after the Caligrafy stylesheets: the colors, type, layout, sign-in pages, buttons, fields and every page component, down to phone width. It never edits the Caligrafy stylesheets or your views, and it checks the pages in a browser.
+ **Back to the Caligrafy look** with `php caligrafer.php design off` (or an empty `DESIGN_STYLE`). An unknown name, or a style whose stylesheet is not written yet, changes nothing.
+ **`doctor`** reports the selected style, and warns about an unknown name or a missing stylesheet.
+ The styles inspire the look only: your application keeps its own name and logo.

Learn more in [Design styles](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles).

## Upgrading from 2.7

1. Update the framework: `git pull`, or copy `framework/`, `caligrafer.php`, `.bin/` and `claude-plugin/` from the release.
2. Run `php caligrafer.php upgrade`, then `php caligrafer.php upgrade --apply`. This updates `layoutblocks.pug` if you never changed it. A customized layout needs the stylesheet link by hand: see [Design styles](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles).
3. Add `DESIGN_STYLE=` to `.env` when you want a style.

The Claude Code plugin is 1.20.0: the `design` skill, gotchas G92-G93.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
