# Release notes: 2.11.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.11.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.11.0** gives every page Caligrafy makes for you a new look, and fixes Stripe Checkout on new Stripe accounts. Existing pages keep working. The full list is in the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

## Caligrafy's new look

Every out-of-the-box page has been rebuilt: sign-in, create account, the account and admin pages, the welcome page, the AI page, the Tasks example, payment and checkout, the Vue app and single page application templates, and the bot.

+ **A white canvas, one message per screen.** A light top bar with the Caligrafy mark, a title band, full-width bands, narrow columns for forms, rows for lists, large tiles for links. No cards, no shadows.
+ **Caligrafy black.** The main action of every page is black; the others are light ash. Type is Inter, in two weights.
+ **Every screen size.** Phones, tablets and desktops: on phones the links of the top bar open under a Menu button, forms take one column, tiles stack.
+ **The Caligrafy mark** next to the name in the top bar (`public/images/resources/caligrafy-mark.svg`), and a black banner for the documentation (`public/images/resources/build-once-banner.png`).

It comes from three stylesheets, linked for you:

| Stylesheet | What it holds |
| --- | --- |
| `public/css/layout.css` | the structure: top bar, title bands, bands, forms, rows, tiles, an account area, the footer |
| `public/css/look.css` | the look: its tokens, Inter, black buttons, fields, the mark |
| `public/css/camera.css` | full-screen camera pages |

New pages extend the master `default/master/site` and use the classes of [Caligrafy's look](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#pagecomponents); the [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin) builds them that way. Pages on the older `layoutblocks` master keep their card components and take the new colors and type.

## The camera fills the screen

Face detection, machine learning and the face sign-in recipe now show the camera full screen: the title and the messages float at the top, the buttons at the bottom, an oval guides the face, and the drawings stay on the person at every size. See [the screen of a FaceDetect app](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition#facedetect-markup-components).

## Payments

+ **Stripe Checkout works on new Stripe accounts.** New accounts have Managed Payments on by default, which refuses a product without a tax code: `createCheckout()` failed and the checkout page said it could not be created. A checkout without a tax code is now a classic Checkout; give the product a `tax_code` to keep Managed Payments. See [Stripe & Cryptocurrency Payment](https://github.com/caligrafy/caligrafy-quill/wiki/9.2-Stripe-&-Cryptocurrency-Payment).
+ **The payment page in Caligrafy's look.** `default/pages/payment` and `checkout` use the new master, and the Stripe Payment Element takes the page's colors, corners and type.

## Design styles

A [design style](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles) now restyles the new look: its stylesheet is loaded after `look.css` and redefines the same tokens (`--design-canvas`, `--design-primary`, `--design-font`...). `/caligrafy-quill:design` knows the new pages and the camera pages.

## Updating a project

```bash
php caligrafer.php update          # the new stylesheets, the mark, the templates of the apps
php caligrafer.php upgrade --apply # the new views of application/ (a view you changed is kept, the new one written next to it)
```

Or, in Claude Code, `/caligrafy-quill:update`. A single page application made before 2.11.0 adds two lines to its `src/main.js`, after the `tokens.css` import, and rebuilds:

```js
import '../../css/layout.css'
import '../../css/look.css'
```

Two things to know when you write views now: Caligrafy renders Pug with PHP expressions, so join strings with `.`, never `+`; and the top bar has its own button, so scripts find their elements by id.
