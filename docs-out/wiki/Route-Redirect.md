# Redirects: `Route::redirect`

`Route::redirect($from, $to, $status = 302, $options = [])` answers a `GET` by sending the visitor elsewhere, without
a closure or a controller: an address that moved, a short address, the home page of a section. Since 2.19.0;
`keepQuery` since 2.20.0.

```php
Route::redirect('/catalog', '/books');                   // 302
Route::redirect('/old/books/{id}', '/books/{id}', 301);  // moved for good
```

<br/>

## With Claude

```text
"Redirect /catalog to /books, and the old /old/books/{id} addresses to /books/{id} for good."
```

<br/>

## By hand

1. In `application/config/routing/web.php`, before `Route::run();`:

   ```php
       Route::redirect('/catalog', '/books');
   ```

2. Run `curl -s -i http://localhost:8080/catalog`: it answers `302 Found` with `Location: http://localhost:8080/books`.

<br/>

## <a name="rules"></a>How it works

+ `{name}` in the target takes the route variable of the same name (url-encoded); the query string never fills it
  (`/old/books/2?x=1` goes to `/books/2`).
+ The target is a path of the site or a full `https://` address.
+ The status is `301`, `302` (default), `303`, `307` or `308`; anything else, or an empty target, stops with an error
  when `web.php` runs.
+ `['keepQuery' => true]` (2.20.0) takes the query string along: `/old?page=2` goes to `/new?page=2`. A `#fragment` of
  the target stays last.

```php
Route::redirect('/old', '/new', 301, ['keepQuery' => true]);
```

<br/>

## Reference

+ [`Route::redirect()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-redirect),
  [`Route::withQuery()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-withquery),
  [`Route::redirectTarget()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-redirecttarget)

<br/>

## Common mistakes

+ **A redirect that depends on who asks, or on the data**: that is a
  [`Route::action`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Action) whose capability returns
  `Web::redirect(...)`.
+ **`redirect()` in a closure without `return`**: `redirect()` only sends a `Location` header; code after it still
  runs. `Route::redirect` has no such problem.
+ **Expecting the query string to fill `{name}`**: only the route variables of `$from` do.

## Related pages

+ [Routing](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing): how a request finds its route
+ [Everything else: `Route::action`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Action)
+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#redirect)
