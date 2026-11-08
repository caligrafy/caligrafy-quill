# Release notes: 2.20.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.20.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.20.0** lets more of an application live in declarative routes, so fewer pages need a controller.

**Pages (`Route::page`)**
+ `visitorsOnly`: a path where people already signed in are sent, for a sign-up or a landing page.
+ `comeBack`: visitors go to `/login?return=<this page>` and come back after signing in.
+ `query`: the names of the query string the view gets, as strings.

**Forms (`Route::form`)**
+ `visitorsOnly`, as for pages.
+ `same`: two fields that must match, such as a password typed twice.
+ `accepted`: a box that must be ticked.
+ `inputs`: values only the server sets, such as the address a payment page comes back to.
+ `signsIn`: the person the capability created or found is signed in.

**Redirects and sign-in**
+ `Route::redirect('/old', '/new', 302, ['keepQuery' => true])` takes the query string along.
+ `Route::auth` goes back to `/login?return=<a page of this site>` after signing in. Any other address is ignored.
+ The Claude Code plugin is 1.32.0.

See [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes) and the
[changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

## Updating a project

```bash
php caligrafer.php update
```

Nothing to change. Moving a controller to these options is optional.

When the update ran in Docker, run `php caligrafer.php plugin` on your computer in the project's folder, then restart
`claude` there.
