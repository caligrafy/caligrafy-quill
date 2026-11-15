# Security defaults

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Security-Defaults
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

# Security defaults

What Caligrafy Quill does for every project without a line of code (2.26.0), and what one line turns on.

| Protection | How | Since |
|---|---|---|
| CSRF on forms | `Request` checks `_token` on every POST, PUT and DELETE of a form; `=csrf()` in a view; session-authenticated JSON writes need `X-CSRF-Token`; `Route::webhook` is exempt by itself | default |
| SQL injection | the query builder binds every value and checks identifiers | default |
| Mass assignment | only the inputs listed in a capability's `$rules` reach `handle()` | default |
| Passwords | `password_hash` in the shipped sign-up, `password_verify` at sign-in | default |
| Session fixation | `Route::auth` gives a new session id and a new CSRF token at sign-in and sign-out | default |
| Session cookie | `HttpOnly`, and `Secure` on HTTPS (also behind a load balancer that says so) | default |
| Token scopes | a write capability without a `scope` needs a token with the scope named after it, or `*`; read-only and public ones need none; REST, MCP and command-line tokens are all checked, 403 otherwise; `doctor` lists the unscoped writes | **default since 2.26.0** |
| N+1 queries | calling a relation method on one of several rows a query read throws `[ERROR 2004]` in development with the `with()` fix, and logs a warning in production; `->lazy()` or `#[Caligrafy\Lazy]` allow one | **default since 2.26.0** |
| Shared caches | an answer that sets a cookie, goes to a signed-in person or a token, or is not a 200 is never marked public | default (2.26.0) |
| Webhook signatures | `Route::webhook` refuses to register without `verify` (`'stripe'` checks `STRIPE_WEBHOOK_SECRET`) | one line |
| Personal access tokens | shown once, stored as SHA-256, revocable, with an expiry | one command |

Settings that turn a default off, for a migration period only:

- `TOKEN_DEFAULT_SCOPES=false` (deprecated): any token may call a write capability without a scope, as before 2.26.0.
- `STRICT_RELATIONS=false`: the N+1 check warns instead of throwing in development.

`php caligrafer.php doctor` reports both, with the fix.
