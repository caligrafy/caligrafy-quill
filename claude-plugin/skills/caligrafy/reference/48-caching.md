# The cache

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Caching
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

![Caligrafy: Describe it. Approve it. Ship it.](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/describe-approve-ship-banner.png)

The **cache** keeps a value for a while, so that slow work (a call to another service, a heavy query, a computed
report) is done once and read many times. Since 2.18.0.

## Using it

```php
use Caligrafy\Cache;

$rates = Cache::remember('exchange-rates', 600, fn () => $this->fetchRates());   // at most once every 10 minutes

Cache::put('report:2026-11', $rows, 3600);      // for an hour
Cache::get('report:2026-11', []);               // or [] when it is not there
Cache::has('report:2026-11');
Cache::forever('settings', $settings);          // until it is forgotten
Cache::forget('report:2026-11');                // after the data changed
```

+ Values are stored as JSON: strings, numbers, booleans, `null` and arrays. An object comes back as an array.
+ Forget a value where the data changes (the capability that adds a book forgets `books`), so nobody reads an old copy.
+ Put who it is for in the key when a value is someone's: `"orders:user:$id"`.

## Where values are kept

`CACHE_STORE` in `.env`:

| Store | Default | Where |
|---|---|---|
| `database` | with `DB_ACTIVATE=true` | the `caligrafy_cache` table: shared by the web server, the queue, scheduled jobs and every server of the app |
| `file` | without a database | a private folder of the temporary directory: one server, one system user |
| `off` | | nothing is kept; `remember()` always runs its function |

The deploy step and `php caligrafer.php platform install` create the table. Until it exists, the database store uses
the file store.

**Why the database by default:** the web server and the command line (the queue, scheduled jobs) run as different
system users, and each has its own file store. A job that forgets a value in the file store would leave the web
server's copy in place. The database is one store for all of them.

```bash
php caligrafer.php cache clear     # empties the store, the compiled views and the class map
```
