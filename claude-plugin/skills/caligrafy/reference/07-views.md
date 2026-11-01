# 7. Views

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Where this fits.** Views are the web experience of your application. New pages extend the master `default/master/site` and are built with [Caligrafy's look](#pagecomponents) (a top bar, a title band, forms, rows, tiles: no CSS to write, responsive at every size) and get their data from your capabilities, through a route (`Route::page`), a controller (`$this->call()`) or the JS client.

Views are the front-end layer of your application. This is what gets displayed on the screen for your users.
In an MVC structure, the `View` interfaces with the `Controller` and the `Controller` determines what gets displayed to the `View`.

## Introduction to `Phug`
This framework integrates tightly with PHP Pug (Phug). `Phug` is a pug template engine for PHP. It offers you a clean and easy way to write HTML templates.

Instead of writing verbose tag syntax, `Phug` is based on the indentation like Python for example.
```pug
body
    h1 Hello

```
The same code in HTML would look like this:
```html
<body>
    <h1>Hello</h1>
</body>
```

### The two masters
Since 2.11.0, Caligrafy's own pages (sign-in, account, admin, welcome, the Tasks example, payment) extend **`default/master/site`**: a light top bar with the Caligrafy mark, then the page, then a footer, with [Caligrafy's look](#pagecomponents) (`public/css/layout.css` and `public/css/look.css`). Its blocks are `title`, `brand` (the name after the mark), `nav_links` (the links on the right; on phones a Menu button opens them), `content`, `footer`, `additional_css` and `additional_scripts`. Build new pages on it. Its stylesheet links carry their modification time (`?v=...`), so browsers get every change; do the same for your own scripts.

The older master `default/master/layoutblocks` keeps working for existing pages (it loads `look.css` too). The rest of this section describes it.

### Default layout and Bootstrap 5
The views provided in `/application/views/default` before 2.11.0 extend the layout `default/master/layoutblocks`, which loads Bootstrap 5.3 (`scripts('bootstrap_css')` and `scripts('bootstrap_script')`) and Caligrafy's styles. jQuery is not loaded: use Bootstrap 5 classes (`mb-3`, `w-100`, `d-none d-md-block`, `badge bg-success`, `row`/`col-*`) and plain JavaScript in your views. `public/css/theme.css` keeps the Caligrafy black and white look on top of Bootstrap 5: 14px body text, Caligrafy cards, black `btn-primary`, white `btn-secondary` with black text, black focus rings. Buttons inside forms are at most 150px wide (`form .btn` in `styles.css`): keep their labels short.

`theme.css` also keeps the Bootstrap 3 layout of the Caligrafy pages: the page `.container` stays full width, headings of the Pug layout keep their Bootstrap 3 margins (they center the header title), and a `.row` inside a Caligrafy `.wrapper` or `.bounded*` block keeps the wrapper centered (15px columns, no negative margins). The signed-in header keeps its Bootstrap 3 columns too (15px padding, title next to the menu icon on phones). Bootstrap 5 breakpoints are one step lower than Bootstrap 3's: a Bootstrap 3 `col-sm-*` (768px and up) is a Bootstrap 5 `col-md-*`, `col-md-*` is `col-lg-*`, `col-lg-*` is `col-xl-*`, `hidden-xs` is `d-none d-md-block` and `hidden-sm hidden-xs` is `d-none d-lg-block`. Put the columns in a `.row`:

```pug
.wrapper.bounded-large
    .row
        .col-md-6.col-lg-4
            .card.color0 ...
```

The layout body carries the class `caligrafy-layout` (`body(id='gesturize' class='caligrafy-layout')` in `default/master/layout.pug`): it scopes the Bootstrap 3 heading margins of `theme.css`. Layouts copied before this class existed keep working through the `gesturize` id; if you write your own layout, put `class='caligrafy-layout'` on its body. `theme.css` must load after Bootstrap (as in `layoutblocks.pug`).

Client apps that reuse the Caligrafy header outside the Pug layout (for example a PHP page in `public/<app>`) add the class `caligrafy-header` to the header, which gives the title the margins that center it, with no custom CSS:

```html
<header class="color0 caligrafy-header">
    <nav class="horizontal">
        <h1 class="large-text horizontal-center"><span class="semibold">Cali</span>grafy</h1>
    </nav>
</header>
```

Put `class="caligrafy-layout"` on the client app body instead to give all its headings the layout margins. Single page apps (Vue template) load `styles.css` after Bootstrap and keep the styles.css heading margin 0, as they did with Bootstrap 3: do not add these classes there.

> Projects created before Caligrafy moved to Bootstrap 5 keep their own copies of the views in `/application/views`: see [Upgrading an existing project](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#upgrading).

## `Pug` Templates
Every framework has a template system. Templates help separate the concerns in a an elegant way between logic and presentation. Such a mentality makes the code more maintainable and most importantly, more flexible for making more frequent user interface changes. 

`Phug` is a PHP library that uses the `Pug` language.
Make yourself familiar with its syntax. There is a slight learning curve in the beginning but once you get the hang of it, you will appreciate using it. 

> Learn Pug and Phug [here](https://www.phug-lang.com/#language-reference)

## Structure and Format
+ All the views that you create should be located in the `/application/views` folder. In that folder, you can create the structure that is the most convenient for you by creating subfolders at will. That folder should not contain any client-side scripts and sheets. Remember that this folder is not public and any client-side scripts that you link will not be read by the browser.

+ All the view files need to have a `.pug` extension. Remember, `Pug` is the language that `Phug` uses. `Pug` is a language that is very similar to HTML and that ends up rendering an HTML/CSS page. `Pug` files should be a replacement to the HTML pages you usually create.

+ Javascript files, script files, images and stylesheets that you would want to link to from your `Pug` view file must be in the `/public` folder. You could organize the `/public` folder to your convenience.  

## Getting Started with Views

Using templates can be overwhelming at first. We recommend that you first start creating `pug` pages individually and not worry about templates just yet. While you are developing your pages, you will notice blocks of code that start repeating. This should be a trigger for you to consider separating these blocks out in a separate `pug` file

Let's start with a basic "Hello World" example:
+ Create a new file called `helloworld.pug` in `/application/views`
```pug
head
    title Hello World Application
body
    h1 Hello World
    p This is our first view using pug
```
Now that we have a view created, we still need to define when to display it to the users. This view can be called from a `Controller` or directly from the `Route` if there is no extensive logic needed. 
We will show you how this can be done in the next sections.

## Simple `View`
Usually in web applications, the most common use case is to have a `Controller` for every presentation page. It is assumed that there is always a need to interface with the database and have a some sort of a logic that justifies the need to have a `Controller`. 

However, there are cases, especially for public pages, where they are just for presentations and no extensive logic nor database extensions are needed.

### `View` from `Route`
Since 2.5.0 the way to show a page is `Route::page`:
```php
Route::page('/hello', 'helloworld');
// Note that you only enter the name of the template without the `.pug` extension
```
It goes through the framework's page controller, which gives every page `$authorized`, `$user` (`id`, `username`, `permissions`) and its route variables, and optionally access rules and data from capabilities: see [Page Routes](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#pageroutes).

A closure that returns a view also works, but the view then gets only what the closure passes: no `$authorized`, so the header shows every visitor as signed out.
```php
Route::get('hello', function() { return view('helloworld'); });
```
### `View` from `Controller`
When the view is called from a `Controller`:
```php
use Caligrafy\Controller;

class HelloController extends Controller {
   public function display()
   {
       return $this->view('helloworld');

       // Alternatively
       return view('helloworld');
   }
}
```

## `View` with parameters
Most of the time, you are going to need to send the data to be displayed to the `View`. This is the cornerstone of the separation of concerns. In an MVC architecture, the `Controller` is the interface that gets the data from the 'Model', validates it, filters it, formats it and then sends it to the `View`. 

The `view` method takes 2 arguments

`view($viewName, $viewData = array())` (the `view()` helper and the `$this->view()` method of a controller have the same arguments)

The `templateName` is the name of the `pug` template (path from `/application/views`, without the `.pug` extension). When the request is an API call (`Accept: application/json`) or when the template name is `null`, the `view` method returns the `parameters` as JSON instead and ends the request: set any HTTP status code with `http_response_code()` before calling it. The `parameters` is a key/value pair array of the `template variable name` and the `value` that needs to be sent to the view.

Let's take the "Hello World" example from before and let's assume that we want the sentence in the body to change dynamically. In this example, we need to have a key/value pair that holds the name of the variable that will be used in the view and the value of it is the sentence that we want to display. 

We will illustrate how it could be done from both the `Route` and the `Controller`.

### Template with parameters
The first thing that needs to be done is prepare the template in the `View` to receive a variable `sentence`
```pug
head
    title Hello World Application
body
    h1 Hello World
    p= $sentence
```
### From the `Route`
We now need to make sure that when the view method is called to assign a value to the `sentence` variable that will be read by the template.
```php
Route::page('/hello', 'helloworld', ['with' => ['sentence' => 'This is our first view using a route']]);
```
Data from your database comes from a capability instead: `Route::page('/books/{id}', 'Books/show', ['data' => ['book' => 'get_book']]);`.

### From the `Controller`
Similarly, the `Controller` will need to assign a value to the `sentence` variable.
```php
use Caligrafy\Controller;

class HelloController extends Controller {
   public function display()
   {
       return $this->view('helloworld', array('sentence' => 'This is our first view using a controller') );

       // Alternatively
       // return view('helloworld', array('sentence' => 'This is our first view using a controller'));
   }
}
```
## Mobile: install to home screen
Browsers can install a Caligrafy application on a phone, a tablet or a computer: it opens from its own icon, full screen, without the browser bar. The default layout (`default/master/layoutblocks.pug`) links the web app manifest `public/manifest.webmanifest`, which gives browsers the name, the black and white colors and the icons of the app (`public/images/resources/icon-192x192.png`, `icon-512x512.png` and the Apple touch icons). Its `start_url` and `scope` are `../`, relative to the manifest: they always point to the root of your application, whatever `APP_ROOT` is. Change `name`, `short_name` and the icons in that file to brand your app (keep a 192x192 and a 512x512 PNG: Chrome needs both). Installation needs HTTPS (or `localhost`).

> This replaces the add-to-homescreen library of older versions (the `add_to_home` scripts keys and the old `default/modules/install.pug`), see [Upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#upgrading).

### Install button and iOS hint
Send `install` to the views where you want to invite visitors to install the app:
```php
return view('default/index', array('install' => true));
```
The layout then includes `default/modules/install.pug`, in plain JavaScript:
+ On Chrome, Edge and Android, an **Install** button (`btn-primary`) appears when the browser fires `beforeinstallprompt`, and opens the browser's own install prompt. **Not now** (`btn-secondary`) hides it.
+ On iOS and iPadOS Safari, which have no install prompt, a short hint says to tap Share, then Add to Home Screen. **Got it** hides it.
+ Nothing is shown once the app is installed (standalone mode) or after the visitor dismissed it: the choice is kept in `localStorage` (`caligrafy-install-dismissed`).

The box is a white card fixed at the bottom of the screen with the Caligrafy black and white buttons. Its styles are scoped to `.caligrafy-install` in the module, so you can restyle it in your own CSS.

### Offline page (optional service worker)
Add `PWA_SERVICE_WORKER=true` to the `.env` to register the service worker `public/sw.js` (it is off by default). It does one thing: when a page cannot be loaded because the device is offline, it shows `public/offline.html` instead of the browser error. It never caches pages, API routes, POST requests or signed-in pages: they always go to the network, so users never see stale or private data. Edit `offline.html` to change the message (it must stay self-contained, without external files) and change `CACHE` in `sw.js` when you do, so installed apps get the new copy. Setting `PWA_SERVICE_WORKER` back to `false` unregisters it on the next visit.

The service worker lives in `public/` but controls the whole application: the root `.htaccess` sends `Service-Worker-Allowed` for `public/sw.js` and the `application/manifest+json` type for `.webmanifest` files (both work with `AllowOverride FileInfo`). `php caligrafer.php doctor` checks that the manifest is valid and that its icons exist, and, when the service worker is on, that its files exist and production uses HTTPS.

### Client apps in `public/<app>`
Client apps that do not use the Pug layout link the manifest themselves in their `<head>`:
```html
<link rel="manifest" href="<?php echo session('public').'manifest.webmanifest'; ?>">
<meta name="theme-color" content="#ffffff">
```
This installs the whole application (it opens on its home page). To install a client app on its own, copy `public/manifest.webmanifest` into `public/<app>/manifest.webmanifest`, set `"start_url": "./"`, `"scope": "./"`, `"id": "./"` and its own `name`, prefix the icons with `../` (`"../images/resources/icon-192x192.png"`) and link that copy instead. For a Vue single page app, put the copy and its icons in the app's own `public/` folder, with icon paths relative to the manifest (Vite copies them to `dist/`), and link it from `index.html` with `<link rel="manifest" href="manifest.webmanifest">`.

> The use of the `View` in this framework is very straightforward. Most of the effort should be in understanding the syntax of the `Pug` language.It is a simple language to pick up especially if you are familiar with HTML. 
> Learn Pug and Phug [here](https://www.phug-lang.com/#language-reference)

## One look on every device

Experiences differ in layout (a dense page on a desktop, touch-first on a tablet, one column on a phone) but share one look. `public/css/tokens.css` defines the Caligrafy look as CSS custom properties, from `public/css/tokens.json`: colors (`--cq-color-ink`, `--cq-color-paper`...), type, spacing (`--cq-space-1` to `--cq-space-5`), radius and breakpoints. The default layout, the Vue app template and the Vite SPA template include it, and it adds:
+ touch targets of at least `--cq-touch-target` (44px) on touch screens,
+ safe-area padding when the application is installed to the home screen,
+ `.cq-split`: one column on phones, a list and its detail side by side from tablets up (since 2.12.0, on pages of the master `site.pug`: `.cq-split > section.cq-split-main + aside.cq-split-detail`, see [side by side](#sidebyside)).

```pug
.cq-split
  section.cq-split-main
    ul.cq-rows
      each $book in $books
        li.cq-row
          .cq-row-main
            p.cq-row-title
              a(href=$home . 'books/' . $book['id'])= $book['title']
  aside.cq-split-detail
    block detail
```
Use the tokens in your own styles (`color: var(--cq-color-text)`) so that every experience of the application stays the same. Learn more [here](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#experiences).

## Caligrafy's look: every page looks the same

Since 2.11.0 every page of Caligrafy shares one look: **a white canvas, a light top bar with the Caligrafy mark, one message per screen, full-width bands, narrow columns for forms, rows for lists, large tiles for links, Caligrafy black for the main action, Inter type, no cards and no shadows**. It works at every size: phones, tablets and desktops. Three stylesheets give it, and the master `site.pug`, the Vue app template and the single page application template link them for you:

| Stylesheet | What it holds |
| --- | --- |
| `public/css/layout.css` | the structure: top bar, title bands, bands, forms, rows, tiles, an account area, the footer |
| `public/css/look.css` | the look: its tokens (`--design-*`, feeding the `--cq-*` tokens), Inter, black buttons, fields, the mark |
| `public/css/camera.css` | full-screen camera pages: face detection, machine learning, face sign-in |

A page is a title band and bands, made of these parts:

```pug
extends ../default/master/site

block title
  | Tasks · My app

block content
  section.cq-hero.cq-hero--page
    h1.cq-display Tasks
    p.cq-lead Everything that has to be done.

  section.cq-band.cq-band--tight
    .cq-inner.cq-inner--narrow
      form#new-task(novalidate)
        .cq-form-grid
          .cq-field
            label(for='task-title') Task
            input#task-title.form-control(type='text', name='title', placeholder='What has to be done?')
          .cq-field
            label(for='task-notes') Notes
            input#task-notes.form-control(type='text', name='notes', placeholder='Optional')
          .cq-field
            label.cq-hidden-label(for='add-task') &nbsp;
            button#add-task.btn.btn-primary.cq-btn-block(type='submit') Add task
      p#error.cq-field-error(role='alert')

      .cq-segments(role='tablist')
        button.active(type='button', role='tab', aria-selected='true') All
          span.cq-count 2
        button(type='button', role='tab', aria-selected='false') Open
          span.cq-count 1

      ul.cq-rows
        li.cq-row
          input.form-check-input.cq-row-check(type='checkbox')
          .cq-row-main
            p.cq-row-title Buy milk
            p.cq-row-meta 2 liters
          .cq-row-actions
            button.btn.btn-secondary(type='button') Edit
            button.btn.btn-secondary(type='button') Delete
      p.cq-empty-state(hidden) No tasks yet: add the first one above.
```

| Part | Classes |
| --- | --- |
| Full-screen message | `section.cq-hero > h1.cq-display + p.cq-lead + .cq-cta > .btn` (a landing, a welcome) |
| Title band | `section.cq-hero.cq-hero--page`, same children: the title of an ordinary page |
| Band | `section.cq-band > .cq-inner > h2.cq-heading + p.cq-lead + ...`; `.cq-band--surface` (ash), `.cq-band--tight`, `.cq-inner--narrow` (720px), `.cq-center` |
| Sign-in style form | `main.cq-auth > h1 + form > .cq-field (label + .form-control) + .btn`; `.cq-auth--wide`, `.cq-or`, `p.cq-auth-note` |
| Form on a page | `.cq-form-grid(.cq-form-grid--2) > .cq-field`: fields side by side from tablets up |
| Messages | `.cq-alert` (`.cq-alert--error`) above a form (`include ../default/modules/notice` shows `$error` and `$message`), `p.cq-field-error` under it, `p.cq-empty-state` for empty lists |
| Choices | `.cq-segments > button` (`.active`) with `span.cq-count` |
| List | `ul.cq-rows > li.cq-row` (`.is-done` greys it) with `.cq-row-check`, `.cq-row-main` (`.cq-row-title`, `.cq-row-meta`), `.cq-row-actions` |
| Links | `.cq-tiles > a.cq-tile > h3 + p + span.cq-tile-link`: one, two or three columns by width |
| Side by side | `.cq-split > section.cq-split-main + aside.cq-split-detail`: a list and the item opened, or a list and its form (2.12.0+, [below](#sidebyside)) |
| Figures | `.cq-stats > .cq-stat > .cq-stat-value + .cq-stat-label`, `span.cq-badge` |
| Account area | `.cq-account > nav.cq-account-nav > a(href='#section')` + `.cq-account-main > section(id)`: a side menu on desktops that marks the section on screen |
| Text and code | `.cq-doc` (long text), `.cq-code`, `pre.cq-pre` |

The same classes work in Vue apps and the single page application. Keep to black and white (`btn-primary` is black, `btn-secondary` ash): no Bootstrap color classes. The [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin) builds every new page with this look.

Two things to know when writing these views: Caligrafy renders Pug with PHP expressions, so join strings with `.` (`'Hello ' . $name`), never `+`; and the top bar has its own button (Menu), so scripts find their elements by id, never with `document.querySelector('button')`.

### A list and its detail, side by side

Since 2.12.0, a page that shows a list and the item opened (a finder, a catalog and its add form) puts them side by side, right after its title band: the list on the left, the item in an ash panel on the right that stays in view while the list scrolls. On phones the panel comes after the list.

```pug
section.cq-hero.cq-hero--page
  h1.cq-display Find a book
  p.cq-lead Type a few words: the books answer as you type.

.cq-split
  section.cq-split-main
    .cq-field
      label(for='query') Search
      input#query.form-control(type='search')
    ul#books.cq-rows
  aside#book.cq-split-detail(aria-live='polite')
    p.cq-empty-state Open a book to read about it.
```

A form in the panel takes one field a line. Vue templates use the same classes; the course's [finder and single page application](https://github.com/caligrafy/caligrafy-quill-course/wiki) are built this way.

### Camera pages

Face detection, machine learning and face sign-in fill the screen with the camera (`camera.css`): the title and the status float at the top, the actions at the bottom (the main one white over the camera), an oval guides the face, and a sheet holds a form over the camera. See [Face Detection](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition) and [Machine Learning](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.3-Machine-Learning).

### Older pages: the card components

Pages built on `layoutblocks.pug` before 2.11.0 keep their card components (`public/css/tokens.css`, since 2.1.3): `main.cq-page > .cq-card` with `.cq-card-head` (`h2.cq-title + p.cq-note`), `form.cq-form > .cq-fields`, `button.btn.btn-primary.cq-submit`, `p.cq-error`, `.cq-tabs`, `ul.cq-list > li.cq-item` with `.cq-actions`, `p.cq-empty`, `.cq-foot`, `.cq-section` and `.cq-split`. They take the new look from `look.css` too. A project made before 2.11.0 gets the new stylesheets with `php caligrafer.php update` and the new templates with `php caligrafer.php upgrade --apply`.

## Another look: design styles

Caligrafy's look is the default. To give the whole application another one, pick a [design style](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles) (`php caligrafer.php design use <name>`, `DESIGN_STYLE` in `.env`) and let Claude write its stylesheet (`/caligrafy-quill:design`): the masters load `public/css/designs/<name>.css` last, after `look.css`, so the same markup takes the new look and your views do not change. An empty `DESIGN_STYLE` is the Caligrafy look again.

>[Next Section: Learn about Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers)
