# Release notes: 2.22.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.22.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.22.0** downloads many files as one ZIP, with one route.

+ **`Route::download(..., ['as' => 'zip'])`** sends the files a read-only capability lists as one archive:
  `Route::download('/photos.zip', 'list_photo_files', ['as' => 'zip', 'root' => 'public/uploads/photos']);`. The
  capability returns paths (or `['file' => ..., 'name' => 'pictures/x.jpg']`), so the same list also reaches REST, the
  JS client and agents. Only files under `root` go in; the archive streams from a temporary file, never from memory.
+ **Newer libraries:** Vue 3.5.45, axios 1.21.1 and Bootstrap 5.3.9 for pages and client apps; Vite 8.3.8 and Vue Router
  5.5.1 in the single page app template; stripe-php 23, PHPMailer 7.2, PHPUnit 12.5.44.
+ The Claude Code plugin is 1.34.0.

See [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#zip) and the
[changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

## Updating a project

```bash
php caligrafer.php update
```

`update` brings stripe-php 23 into `composer.json` and runs `composer update` for it. If your own code calls
`Stripe::setAccountId()` and then a `StripeClient`, pass `['stripe_account' => ...]` to the client or the request
instead; if it serializes Stripe objects (in a session, a cache), call `reattachApiKey($key)` on them after
`unserialize()`. Otherwise nothing changes.

When the update ran in Docker, run `php caligrafer.php plugin` on your computer in the project's folder, then restart
`claude` there.
