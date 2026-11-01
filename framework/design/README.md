# Design styles

Everything about design styles lives here. A style is a `DESIGN.md`: a plain-text description of a design system
(colors, typography, spacing, components, do's and don'ts) written for AI agents to read.

```
framework/design/
  vendor/<vendor>/<name>/DESIGN.md   collections copied from elsewhere, each with its license, attribution and refresh
      awesome-design-md/             84 styles from VoltAgent/awesome-design-md (MIT): see its README.md and LICENSE
  local/<name>/DESIGN.md             styles added to Caligrafy itself, their origin in local/README.md
      interstellar/
application/design/<name>/DESIGN.md  your project's own styles: drop a DESIGN.md here
```

A style's name is its folder name (lowercase letters, digits, `.`, `-`, `_`). When the same name exists in several
places, the first one wins: **`application/design`, then `framework/design/local`, then each vendor in alphabetical
order**. `php caligrafer.php design list` shows where every style comes from and `design show <name>` names the
copies it hides. A new vendor is a new folder in `vendor/` with its `LICENSE`, a `README.md` that credits it, and its
style folders; nothing else to register.

Caligrafy's own look (`public/css/tokens.css`, `theme.css`, `styles.css`) is the default and never changes. To give a
project another style:

```bash
php caligrafer.php design list            # every style, with its source
php caligrafer.php design use <name>      # DESIGN_STYLE=<name> in .env
```

then, in Claude Code, `/caligrafy-quill:design` reads the selected `DESIGN.md` and writes one override stylesheet,
`public/css/designs/<name>.css`, that the layout loads after the Caligrafy stylesheets. `php caligrafer.php design
off` (an empty `DESIGN_STYLE`) brings the Caligrafy look back.

The files describe the visual language of existing products to inspire a style; the brand names, logos and trademarks
they mention belong to their owners. A restyled application keeps its own name and logo and never presents itself as
one of those products. The production image (`Dockerfile`) leaves `framework/design/` out: only the generated
stylesheet is used at run time (a project's `application/design/` is small and stays in).
