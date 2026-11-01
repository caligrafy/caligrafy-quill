# Release notes: 2.5.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.5.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.5.0** makes one rule part of the framework: **only capabilities touch models**. Pages get one route method that gives them who is signed in and their data from capabilities, and the people who sign in are managed by capabilities too, so AI agents can manage them. Existing projects keep working. The full list is in the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

## Every page in one line: `Route::page`

```php
Route::page('/', 'default/index');
Route::page('/books/{id}', 'Books/show', ['data' => ['book' => 'get_book']]);
Route::page('/admin/books', 'Books/admin', ['level' => 1, 'data' => ['books' => 'list_books']]);
```

+ Every page gets `$authorized`, `$user` (`id`, `username`, `permissions`) and its route variables: no page shows a signed-in person as a visitor any more.
+ `data` calls capabilities as the person who opens the page, with the route variables and the query string as inputs. A refusal sends a visitor to sign in, or answers 403 (`1006`) or 404 (`1005`).
+ `signedIn` and `level` keep a page for signed-in people or administrators.

See [Page Routes](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#pageroutes).

## One rule: only capabilities touch models

Pages get their data from capabilities, controllers call capabilities (`$this->call()`), the REST API and AI agents call capabilities. The rules of a feature exist once, whichever way it is used. Write a controller only when a page has logic of its own: a form post, several capabilities with conditions, or the web session (sign-in). See [When to write a controller](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers#when).

## Users are capabilities too

`list_users`, `get_user`, `update_user` and `delete_user` (administrators only, never a password, nobody deletes their own account) and `register_user` (the sign-up page). The user administration pages and sign-up now call them, with the same look and messages. Give an AI agent an administrator's token and it can manage users, with the audit log. See [User capabilities](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#usercapabilities).

## Also in 2.5.0

| Area | Before | Now |
| --- | --- | --- |
| Welcome page and Tasks example | closure routes: the page did not know who was signed in | `Route::page` |
| Errors | no page-level not found or forbidden | `[ERROR 1005] Page Not Found` (404), `[ERROR 1006] Forbidden` (403) |
| Administrator level | `AdminController::ADMIN_PERMISSION` | `CaligrafyUsers::ADMIN_LEVEL`, used by the pages and the capabilities |

## Upgrading from 2.4.0

1. Update the framework (`git pull`, or copy `framework/`, `caligrafer.php`, `.bin/` and `claude-plugin/` from the release).
2. A capability of yours named like the new user capabilities (or a class named `ListUsers`, `GetUser`, `UpdateUser`, `DeleteUser`, `RegisterUser`, `CaligrafyUsers`): rename it first.
3. `php caligrafer.php upgrade`, then `php caligrafer.php upgrade --apply`: it adds `application/services/Users/` and updates the controllers you never changed.
4. In `web.php` (never replaced), change your page closures to `Route::page`, starting with `/`.
5. `php caligrafer.php doctor`.

The Claude Code plugin is 1.17.0: it builds pages with `Route::page`, writes features capabilities-first (`/caligrafy-quill:new-resource` too), and its reviewer flags controllers and pages that touch models.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
