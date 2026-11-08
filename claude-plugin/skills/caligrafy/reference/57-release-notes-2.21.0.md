# Release notes: 2.21.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.21.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.21.0** lets every request of an application be declared: controllers are no longer needed.

+ **`Route::action`** answers a request with a web-only capability, for what a page or a form cannot say: a sign-in
  between two sites, a page that decides where to go, a gate, a payment page.
+ **`#[Capability(..., webOnly: true)]`**: it may read the session, sign people in and change data on a `GET`, so nothing
  but `Route::action` reaches it: not REST, MCP, the JS client, the command line, `Route::page`, `Route::form` or
  `$this->call()`.
+ **`Caligrafy\Web`** is what it answers with: `Web::view()`, `Web::redirect()`, `Web::json()`, `Web::text()`.
+ **`Route::bots()`** declares the data routes of a Watson bot app.
+ `Route::page`'s `query` option takes defaults: `'query' => ['app' => 'index']`.
+ The Claude Code plugin is 1.33.0.

See [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#action) and the
[changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

## Updating a project

```bash
php caligrafer.php update
```

Nothing to change. Existing controllers keep working.

When the update ran in Docker, run `php caligrafer.php plugin` on your computer in the project's folder, then restart
`claude` there.
