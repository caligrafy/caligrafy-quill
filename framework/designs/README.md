# Design styles

Each folder holds one `DESIGN.md`: a plain-text description of a website's design system (colors, typography,
spacing, components, do's and don'ts) written for AI agents to read. They come from
[VoltAgent/awesome-design-md](https://github.com/VoltAgent/awesome-design-md), copyright (c) 2026 VoltAgent,
released under the MIT License (the `LICENSE` file in this folder, which must stay with the copies). `SOURCE` records
the upstream commit; `sh framework/designs/sync.sh` refreshes them.

They are optional. Caligrafy's own look (`public/css/tokens.css`, `theme.css`, `styles.css`) is the default and never
changes. To give a project another style:

```bash
php caligrafer.php design list            # the styles available
php caligrafer.php design use <name>      # DESIGN_STYLE=<name> in .env
```

then, in Claude Code, `/caligrafy-quill:design` reads the selected `DESIGN.md` and writes one override stylesheet,
`public/css/designs/<name>.css`, that the layout loads after the Caligrafy stylesheets. `php caligrafer.php design
off` (an empty `DESIGN_STYLE`) brings the Caligrafy look back. Styles added outside this collection live in
`framework/designs-extra/` (see its README), which `sync.sh` never touches. A project can add its own style in
`application/designs/<name>/DESIGN.md`; it wins over a framework style of the same name.

The files describe the visual language of public websites to inspire a style; the brand names, logos and trademarks
they mention belong to their owners. A restyled application keeps its own name and logo and never presents itself as
one of those sites. The production image (`Dockerfile`) leaves this folder out: only the generated stylesheet is used
at run time.
