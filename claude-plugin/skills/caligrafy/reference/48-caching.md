# The cache

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Caching
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

The **cache** keeps a value for a while, so that slow work (a call to another service, a heavy count, a report) is done
once and read many times. A capability reads through the cache, and the capabilities that change the data forget the
value, so nobody reads an old copy for long. Since 2.18.0.

## With Claude

Say what is slow, how old the answer may be, and what changes it:

```text
/caligrafy-quill:new-capability shelf_stats books:read read-only public: the numbers of books, authors and open loans, at most 10 minutes old; forget them when a book is added
```

Claude reads the value with `Caligrafy\Cache::remember('<key>', <seconds>, fn () => ...)` in the capability, and calls
`Cache::forget('<key>')` in the capabilities that change the data.

**Check** before you say OK:
+ the key says what the value is (`bookshelf:stats`), and holds the user's id when the value is someone's;
+ every capability that changes the data forgets the key;
+ the value is plain data (numbers, strings, arrays), not a model object.

## By hand

Run the commands in the Docker container (`docker compose exec dev-box php caligrafer.php ...`; without Docker, drop
`docker compose exec dev-box`).

1. **The framework tables.** The cache is kept in the `caligrafy_cache` table by default.
   `docker compose exec dev-box php caligrafer.php platform install` creates it (the deploy step does too).
2. **Read through the cache.** Run
   `docker compose exec dev-box php caligrafer.php service Bookshelf/ShelfStats --scope books:read --read-only`, then
   make `application/services/Bookshelf/ShelfStats.php` read:
    ```php
    <?php

    use Caligrafy\Cache;
    use Caligrafy\Capability;
    use Caligrafy\Context;
    use Caligrafy\Database;
    use Caligrafy\Service;

    #[Capability(
        name: 'shelf_stats',
        description: 'Returns the numbers of the Bookshelf: books, authors, and books out on loan now. Up to 10 minutes old.',
        scope: 'books:read',
        readOnly: true,
        public: true
    )]
    class ShelfStats extends Service
    {
        public function handle(array $input, Context $context)
        {
            return Cache::remember('bookshelf:stats', 600, function () {
                return array(
                    'books' => Database::table('books')->count(),
                    'authors' => Database::table('authors')->count(),
                    'out' => Database::table('loans')->whereNull('returned_at')->count()
                );
            });
        }
    }
    ```
    `remember()` returns the kept value when there is one, otherwise runs the function, keeps its result for 600
    seconds and returns it.
3. **Forget it where the data changes.** In `application/services/Bookshelf/CreateBook.php` (from
   [Build once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service)),
   add `use Caligrafy\Cache;` under `<?php` and make `handle()` read:
    ```php
        public function handle(array $input, Context $context)
        {
            $id = Book::insert($input);
            Cache::forget('bookshelf:stats');   // the number of books changed
            return Book::with('author')->find($id);
        }
    ```
    Do the same in `delete_book` and `lend_book`.
4. **Run it.** With the sample rows of [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models):
    ```bash
    docker compose exec dev-box php caligrafer.php call shelf_stats
    ```
    ```json
    {
        "data": {
            "books": 5,
            "authors": 3,
            "out": 2
        }
    }
    ```
    The value is now in the table: `docker compose exec mysql mysql -uroot -proot caligrafy -e "SELECT cache_key, value FROM caligrafy_cache"`
    shows `bookshelf:stats  {"books":5,"authors":3,"out":2}`. Add a book with `call create_book`: the row is gone,
    and the next `call shelf_stats` says `"books": 6`.

**Check:**
+ two `call shelf_stats` in a row give the same answer, and the second one runs no count;
+ after `create_book`, `shelf_stats` has the new number;
+ `docker compose exec dev-box php caligrafer.php cache clear` prints
  ` Cache (database) emptied; <n> compiled view and class map file(s) removed`.

## Reference

```php
use Caligrafy\Cache;

Cache::remember('bookshelf:stats', 600, fn () => $this->count());   // the kept value, or the function's, kept 600 s
Cache::put('report:2026-11', $rows, 3600);    // for an hour (null: until forgotten; 0 or less: forgets it)
Cache::get('report:2026-11', []);             // the value, or [] when it is not there or too old
Cache::has('report:2026-11');                 // true or false
Cache::forever('settings', $settings);        // until it is forgotten
Cache::forget('report:2026-11');              // gone
Cache::flush();                               // the whole store
```

+ Values are stored as JSON: strings, numbers, booleans, `null` and arrays. An object (a model) comes back as an array,
  and `remember()` returns what a later read returns. A value that cannot be encoded is refused.
+ A key is any text but empty. Put whose it is in the key when it is someone's: `"loans:user:$id"`.
+ In one request, a value read once is read from memory after that.

### Where values are kept: `CACHE_STORE`

| `CACHE_STORE` in `.env` | Used when empty | Where |
|---|---|---|
| `database` | with `DB_ACTIVATE=true` | the `caligrafy_cache` table: one store for the web server, the queue, the scheduled jobs and every server of the app |
| `file` | without a database | a private folder of the temporary directory: one server, one system user |
| `off` | | nothing is kept; `remember()` always runs its function |

Another value stops with `CACHE_STORE=<value>: use file, database or off`. Until the `caligrafy_cache` table exists,
the database store uses the file store and writes `Caligrafy cache: the caligrafy_cache table is missing (php
caligrafer.php platform install): using the file store` in the PHP error log. One write in a hundred removes the
expired rows.

**Why the database by default:** the web server and the command line (the queue, scheduled jobs) run as different
system users, and each one has its own file store. A job that forgets a value in the file store would leave the web
server's copy in place. The database is one store for all of them.

`php caligrafer.php cache clear` empties the store, and the compiled views and the class map of the system user that
runs it. The web server keeps its own: run it as that user, or restart the container.

## Common mistakes

+ **Not forgetting** a value where the data changes: people read the old copy until it expires.
+ **`CACHE_STORE=file` with a queue or scheduled jobs** (G119): the job's `forget()` does not reach the page's copy.
  Keep the database store.
+ **Caching someone's data under a shared key**: one person sees another's. Put the user's id in the key.
+ **Expecting a model back**: it comes back as an array (`$stats['books']`).

## Related pages

+ [Build once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP): capabilities
+ [Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs): slow work done later
+ [Caligrafer: cache](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#cache)
