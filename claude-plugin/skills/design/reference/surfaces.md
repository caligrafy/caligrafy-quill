# What a design style has to restyle

Every Caligrafy page is drawn by these stylesheets, in this order: `styles.css` (the original Caligrafy classes),
Bootstrap 5.3, `theme.css` (Caligrafy's black and white Bootstrap), `tokens.css` (design tokens and the card
components), then since 2.11.0 `layout.css` (the structure of Caligrafy's pages), `camera.css` (camera pages only) and
`look.css` (Caligrafy's look). `public/css/designs/<name>.css` comes last. Rules prefixed with `html body` outrank all
of them without `!important` (look.css uses the same prefix: the design stylesheet wins by coming after it).

## Caligrafy's look (2.11.0+): start here

`look.css` defines the look as `--design-*` tokens and maps them onto the `--cq-*` tokens every page reads. A design
style redefines the same names in its `:root`:

| Token (look.css) | Default | Role |
| --- | --- | --- |
| `--design-canvas` | `#ffffff` | the page (`--cq-color-canvas`, `--cq-color-page`) |
| `--design-surface` | `#f4f4f4` | tiles, surface bands, secondary buttons, alerts (`--cq-color-surface`, `--cq-color-paper`) |
| `--design-surface-raised` | `#ffffff` | fields and rows on a surface |
| `--design-ink` / `--design-body` / `--design-muted` | `#171a20` / `#393c41` / `#5c5e62` | headings / text / secondary text |
| `--design-hairline` / `--design-hairline-strong` | `#e2e3e3` / `#d0d1d2` | row dividers / field edges |
| `--design-primary` / `-hover` / `--design-on-primary` | `#000000` / `#333333` / `#ffffff` | the main action (`--cq-color-primary`) |
| `--design-error` / `--design-success` | `#b3261e` / `#1f7a4d` | messages |
| `--design-font` / `--design-font-code` | Inter / JetBrains Mono | type |
| `--design-radius-control` / `--design-radius-card` | `4px` / `12px` | buttons and fields / tiles |
| `--cq-camera-*` (camera.css) | white main action, see-through buttons | camera pages |

The structure classes of `layout.css` (`.cq-nav`, `.cq-hero`, `.cq-band`, `.cq-auth`, `.cq-field`, `.cq-rows`,
`.cq-tiles`, `.cq-segments`, `.cq-account`, `.cq-footer`...) read those tokens: restyle a selector only where the
design differs in shape (a bordered tile, a pill segment, an underlined link). The sections below map the older
surfaces, still used by pages on `layoutblocks.pug` and by `styles.css`.

## Tokens to replace in `:root`

| Caligrafy token | Default | Design role | Read by |
| --- | --- | --- | --- |
| `--cq-color-ink` | `#000000` | headings, strong text, active tab | `.cq-title`, `.cq-item-title`, `.cq-tabs .active`, `.cq-check:checked` |
| `--cq-color-paper` | `#ffffff` | card surface | `.cq-card`, `.cq-foot`, active tab background |
| `--cq-color-page` | `#f6f9fc` | page canvas | `.cq-foot` top band |
| `--cq-color-text` | `#333333` | body text | `.cq-card`, `.cq-note code` |
| `--cq-color-text-muted` | `#535150` | secondary text | `.cq-item-meta` |
| `--cq-color-accent-dark` | `#414141` | notes, labels, empty states | `.cq-note`, `.cq-form label`, `.cq-empty` |
| `--cq-color-border` | `#d9d9d9` | hairlines | (new code) |
| `--cq-color-accent-red` | `#990000` | errors | `.cq-error` |
| `--cq-radius-control` | `4px` | control radius | (new code) |

The tokens alone are not enough: `styles.css` and `theme.css` hard-code their colors, and some `.cq-*` rules use
fixed greys (`#e5e5e5`, `#fafafa`, `#eee`, `#f0f0f0`). Restyle the selectors below as well.

## Layout (`layoutblocks.pug`, every Pug page)

| Selector | Default | Notes |
| --- | --- | --- |
| `body` | `#f6f9fc` (theme.css) | page canvas |
| `body *` | IBM Plex Sans, `font-weight: lighter` (styles.css) | override both on `html body *`; restore weights on headings, `.semibold`, labels, buttons |
| `header`, `header.color0` | fixed, white, 60px `nav.horizontal` | keep fixed and 60px; color, border, blur are free |
| `header h1.large-text > a > span.semibold + span` | the "Caligrafy" wordmark (Cali in semibold) | restyle the type only, never replace the name |
| `header #menu_icon li`, `#vertical_menu .menulist` | phone menu (signed in) | the three bars use `border: thin solid` (currentColor) |
| `.shadowCoverTop`, `.radialShadowTop` | white strip and radial shadow under the header | hide the shadow for flat styles |
| `footer p.small-text` | copyright line | |
| `.content-section.color4`, `.color0`...`.color8`, `.textcolor0`...`.textcolor7` | Caligrafy color classes | `.card.colorN` is redefined in theme.css |
| `.alert.alert-{danger,success,info}` (`modules/messaging.pug`) | Bootstrap alerts | form errors of the sign-in pages |

## Sign-in pages (`Auth/login.pug`, `Auth/register.pug`, `Auth/users.pug`)

| Selector | Default | Notes |
| --- | --- | --- |
| `.card.color0.dropshadow` (`html .card` in theme.css) | white, 7px, three-layer shadow, 40px padding | two cards: the form, then "Don't have an account?" |
| `label.default-text.textcolor5` | small grey label | |
| `.form-control.input-default` (theme.css: `font-size: 1.5em; height: 3em`) | large fields | set `height: auto` and a `min-height`, keep 44px on touch screens |
| `input.btn.btn-primary.btn-secondary.w-100` | **the Sign in button**: both classes, so it renders as the white secondary button by default | treat `.btn-primary.btn-secondary` as the primary action |
| `a.btn.btn-secondary.input-size-150` | Sign up | secondary action |
| `p.medium-text` | "Don't have an account?" | |

## Buttons and fields (Bootstrap through theme.css)

Bootstrap 5 draws buttons from `--bs-btn-*` variables, which theme.css sets on `html .btn-primary` and
`html .btn-secondary`: redefine the whole set (color, bg, border for normal, hover, active, disabled, plus
`--bs-btn-focus-shadow-rgb`) and also set `background-color`, `border-color`, `color` directly, because styles.css
and theme.css set them too. `theme.css` gives `.btn` `height: 3em; font-size: 1.25em; padding: 12px`. Links styled as
buttons need `a.btn-primary:link, a.btn-primary:visited` colors (styles.css makes links inherit). Fields: `.form-control`,
`.form-select`, their `:focus` (theme.css draws a black ring), `::placeholder`, `:-webkit-autofill`.
`.btn-outline-dark` is the third button style of the page components.

## Page components (`tokens.css`)

| Selector | Default | Design role |
| --- | --- | --- |
| `.cq-page` | 76px top padding under the header, centered | keep the skeleton |
| `.cq-card` | paper, 7px radius, shadow as the edge | card (shadow or hairline, radius) |
| `.cq-card + .cq-card` | 15px gap | |
| `.cq-card-head > h2.cq-title + p.cq-note` | 17.5px regular title, light note | card title in the display face, muted note |
| `.cq-note code` | `#f3f3f3` chip | inline code |
| `.cq-form label`, `.cq-fields` | light grey label, 20px gaps | |
| `.cq-submit` | 150px minimum, left | primary button |
| `.cq-error` | accent red | error text |
| `.cq-tabs`, `.cq-tabs button`, `button.active` | grey `#fafafa` tabs, 3px ink underline when active | tabs / segmented control of the design |
| `.cq-count`, `.active .cq-count` | grey pill, inverted when active | badge |
| `.cq-list > .cq-item` | rows split by `#f0f0f0` | list rows, hairlines |
| `.cq-check.form-check-input`, `:checked` | grey box, ink when checked | checkbox |
| `.cq-item-title`, `.cq-item-meta`, `.cq-item.is-done` | semibold title, muted meta, struck when done | |
| `.cq-actions .btn` | small buttons | compact secondary buttons |
| `.cq-inline-edit` | inline field | |
| `.cq-empty` | centered light text | empty state |
| `.cq-foot` | 15px page-colored band on top, 17.5px light text | card footer |
| `.cq-section`, `.cq-split` | padding; one column on phones, two from 768px | |

Phone width (`max-width: 575px`): tokens.css reduces the card padding to 20px; keep that. Touch screens
(`pointer: coarse`): `--cq-touch-target` (44px) on `.btn`, `.form-control`, `.form-select`, `.nav-link`.

## Elsewhere

- Vue apps from `framework/plugins/app` (`public/<app>/index.php`) link the stylesheet like the layout; older copies
  without the `design_stylesheet()` line: add it after the `tokens.css` link.
- Vite SPAs (`public/<app>/src/main.js`) import the CSS at build time: add the import, rebuild.
- `bot`, `facedetect` and `ml` apps have their own `css/styles.css`: restyle them only when the user asks.
- `payment.pug` / `checkout.pug`: the Stripe Payment Element takes its colors from its `appearance` option in
  `public/js/services/stripe_client.js`, not from CSS; mention it rather than editing it.
