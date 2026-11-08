# Declarative routes: forms, sign-in, webhooks, downloads

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

# Declarative routes

A **declarative route** is one line of `web.php` that connects a request to a capability: a page, a form, the sign-in
pages, the events another service posts, a file to download, an address that moved. The capability holds the rules (who
may call it, which inputs it accepts), and the route says where the person goes next.

Since 2.21.0 every request of an application can be declared, so **a new application needs no controller**: what a page
or a form cannot say (a sign-in between two sites, a page that decides where to go, a gate, a payment page) is a
web-only capability served by [`Route::action`](#action). Existing controllers keep working.

| You need | One line in `application/config/routing/web.php` |
|---|---|
| A page, with its data | `Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book']]);` |
| A page people return to after signing in | `Route::page('/books/new', 'Bookshelf/new', ['signedIn' => true, 'comeBack' => true]);` |
| A page for visitors only | `Route::page('/join', 'Bookshelf/join', ['visitorsOnly' => '/books']);` |
| A form that creates, changes or deletes | `Route::form('/books', 'create_book', ['success' => '/books/{id}']);` |
| A sign-up form that signs the person in | `Route::form('/join', 'register_user', ['signsIn' => 'id', 'success' => '/books', 'same' => [...], 'accepted' => [...]]);` |
| A form that leaves for a payment page | `Route::form('/membership/checkout', 'start_checkout', ['success' => fn ($r) => $r['url']]);` |
| Sign-in, sign-out, sign-up | `Route::auth(['home' => '/books']);` |
| Events another service posts | `Route::webhook('/stripe/events', 'record_event', ['verify' => 'stripe']);` |
| A CSV or JSON export | `Route::download('/authors.csv', 'list_authors', ['filename' => 'authors.csv']);` |
| An address that moved | `Route::redirect('/old/books/{id}', '/books/{id}', 301);` |
| Anything else a browser asks | `Route::action('/authors/{author_id}/books', 'open_author_books');` |
| A file in a form | the `file` rule of the capability: `'cover' => 'required\|file\|extension,png;jpg\|max_size,2mb'` |

## With Claude

```text
/caligrafy-quill:new-capability create_book books:write
"Add a 'New book' form at /books/new for signed-in people. After saving, go to the book's page and say 'Book added'.
If a field is wrong, come back to the form with the message under the field and what was typed."
```

Other requests the plugin turns into one of these lines: "let people export the books as CSV", "receive Stripe's
checkout events", "add sign-in pages", "redirect /catalog to /books", "after signing in, come back to the page you were
on", "a sign-up page with the password typed twice and a terms box", "when an author has one book, go straight to it".
What Claude writes: the capability in `application/services/<Folder>/`, the route in
`application/config/routing/web.php`, and for a form the page (`Route::page`) and its Pug view with `=csrf()`, `old()`,
`errors()` and `flash()`.

**Check** before you say OK:

- [ ] No controller and no closure was written: each request is one of these lines.
- [ ] A capability with `webOnly: true` is one that needs the session, signs someone in, or chooses between a page and a
      redirect. Everything else stays a plain capability, so REST and agents can reach it.
- [ ] The form has `method='POST'`, `=csrf()`, and `=methodField('PUT')` or `'DELETE'` for those routes.
- [ ] `success` and `back` point at the pages that show `flash()` and `errors()`.
- [ ] A download names a read-only capability; a webhook has `verify` and its capability is safe to run twice.
- [ ] Sending the form empty shows the messages under the fields, and the typed values stay.

## By hand

Before you start: the Bookshelf tables, the `Book` model and the capabilities `list_books` and `get_book` of
[Routing, steps 1 to 3](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#definingroutes). The commands run
in the project folder (with Docker: `docker compose exec dev-box php caligrafer.php ...`); the addresses use
`http://localhost:8080`.

1. **Write the capabilities the forms call.** Run:

   ```bash
   docker compose exec dev-box php caligrafer.php service Bookshelf/ListAuthors --scope books:read --read-only
   docker compose exec dev-box php caligrafer.php service Bookshelf/CreateBook --scope books:write
   docker compose exec dev-box php caligrafer.php service Bookshelf/UpdateBook --scope books:write
   docker compose exec dev-box php caligrafer.php service Bookshelf/DeleteBook --scope books:write
   ```

   Replace the content of `application/services/Bookshelf/ListAuthors.php` (the authors of the form's list, and the
   export of step 8) with:

   ```php
   <?php

   use Caligrafy\Capability;
   use Caligrafy\Context;
   use Caligrafy\Service;

   // list_authors: every author, sorted by name. Anyone may read it
   #[Capability(
       name: 'list_authors',
       description: 'Lists the authors of the Bookshelf catalog, sorted by name: id, name and bio.',
       scope: 'books:read',
       readOnly: true,
       public: true
   )]
   class ListAuthors extends Service
   {
       public function handle(array $input, Context $context)
       {
           return Author::query()->orderBy('name')->get();
       }
   }
   ```

   `application/services/Bookshelf/CreateBook.php`:

   ```php
   <?php

   use Caligrafy\Capability;
   use Caligrafy\Context;
   use Caligrafy\Service;

   // create_book: adds a book, with the same rules whoever calls it
   #[Capability(
       name: 'create_book',
       description: 'Adds a book to the Bookshelf catalog and returns it with its id and its author. The author must already exist: use list_authors to find author_id.',
       scope: 'books:write'
   )]
   class CreateBook extends Service
   {
       public $rules = array(
           'title' => 'required|max_len,200',
           'published_year' => 'required|integer|min_numeric,1450|max_numeric,current_year',
           'summary' => 'max_len,2000',
           'author_id' => 'required|integer|exists,authors.id'
       );
       public $filters = array('title' => 'trim', 'summary' => 'trim');
       public $descriptions = array(
           'title' => 'The title of the book',
           'published_year' => 'The year it was first published, for example 1969 (not after this year)',
           'summary' => 'Optional: one or two sentences about the book',
           'author_id' => 'The id of its author (list_authors)'
       );

       public function handle(array $input, Context $context)
       {
           $id = Book::insert($input);   // one INSERT, every value bound; returns the id of the new book
           return Book::with('author')->find($id);
       }
   }
   ```

   `application/services/Bookshelf/UpdateBook.php`:

   ```php
   <?php

   use Caligrafy\Capability;
   use Caligrafy\Context;
   use Caligrafy\Service;
   use Caligrafy\ServiceError;

   // update_book: changes only the inputs that are given
   #[Capability(
       name: 'update_book',
       description: 'Changes the title, year, summary or author of a book of the catalog, by its id. Only the inputs given are changed. Returns the book with its author.',
       scope: 'books:write'
   )]
   class UpdateBook extends Service
   {
       public $rules = array(
           'id' => 'required|integer',
           'title' => 'max_len,200',
           'published_year' => 'integer|min_numeric,1450|max_numeric,current_year',
           'summary' => 'max_len,2000',
           'author_id' => 'integer|exists,authors.id'
       );
       public $filters = array('title' => 'trim', 'summary' => 'trim');
       public $descriptions = array(
           'id' => 'The id of the book to change',
           'title' => 'A new title',
           'published_year' => 'A new year of publication',
           'summary' => 'A new summary',
           'author_id' => 'The id of a new author (list_authors)'
       );

       public function handle(array $input, Context $context)
       {
           if (!Book::find($input['id'])) {
               throw ServiceError::notFound("There is no book #{$input['id']}");
           }
           // keep only the inputs that were given: a missing input never erases a column
           $changes = array();
           foreach (array('title', 'published_year', 'summary', 'author_id') as $field) {
               if (isset($input[$field])) {
                   $changes[$field] = $input[$field];
               }
           }
           // a book always has a title, a year and an author: given empty, they are refused like a missing required input
           $errors = array();
           foreach (array('title' => 'Title', 'published_year' => 'Published Year', 'author_id' => 'Author Id') as $field => $label) {
               if (($changes[$field] ?? null) === '') {
                   $errors[$field] = "The $label field is required";
               }
           }
           if ($errors) {
               throw ServiceError::invalid($errors);
           }
           if ($changes) {
               Book::where('id', $input['id'])->update($changes);   // only this book: an update needs a where
           }
           return Book::with('author')->find($input['id']);
       }
   }
   ```

   `application/services/Bookshelf/DeleteBook.php`:

   ```php
   <?php

   use Caligrafy\Capability;
   use Caligrafy\Context;
   use Caligrafy\Service;
   use Caligrafy\ServiceError;

   // delete_book: destructive, so AI agents ask before calling it
   #[Capability(
       name: 'delete_book',
       description: 'Deletes a book of the catalog, by its id. This cannot be undone.',
       scope: 'books:write',
       destructive: true
   )]
   class DeleteBook extends Service
   {
       public $rules = array('id' => 'required|integer');
       public $descriptions = array('id' => 'The id of the book to delete');

       public function handle(array $input, Context $context)
       {
           // delete() returns how many rows it deleted: 0 when there is no such book
           if (!Book::where('id', $input['id'])->delete()) {
               throw ServiceError::notFound("There is no book #{$input['id']}");
           }
           return array('deleted' => (int) $input['id']);
       }
   }
   ```

2. **Turn on sign-in.** The forms are for signed-in people. Create the `users` table of the authentication module:

   ```bash
   docker compose exec dev-box php caligrafer.php sql application/models/Auth/create_db.sql
   ```

   It prints ` application/models/Auth/create_db.sql run on <your database>`. In
   `application/config/routing/web.php`, remove the `//` in front of `Auth::authentication('User', 'users');`.

3. **Declare the routes.** In `application/config/routing/web.php`, replace the Bookshelf lines of Routing step 4 with
   these, before `Route::run();`:

   ```php
       // sign-in pages: /login, /logout, /register, /notAuthorized
       Route::auth(['home' => '/books']);

       // the Bookshelf pages and their forms
       Route::page('/books', 'Bookshelf/books', ['data' => ['books' => 'list_books']]);
       Route::page('/books/new', 'Bookshelf/new', ['signedIn' => true, 'comeBack' => true, 'data' => ['authors' => 'list_authors']]);
       Route::form('/books', 'create_book', ['success' => '/books/{id}', 'flash' => 'Book added']);
       Route::page('/books/{id}', 'Bookshelf/book', ['data' => ['book' => 'get_book'], 'notFound' => 'Bookshelf/notfound']);
       Route::form('/books/{id}', 'update_book', ['method' => 'PUT', 'success' => '/books/{id}', 'flash' => 'Saved']);
       Route::form('/books/{id}', 'delete_book', ['method' => 'DELETE', 'success' => '/books', 'flash' => 'Book deleted']);

       // an export and an address that moved
       Route::download('/authors.csv', 'list_authors', ['filename' => 'authors.csv']);
       Route::redirect('/catalog', '/books');
   ```

   `/books/new` comes before `/books/{id}`: the first route that matches wins. `comeBack` sends a visitor to
   `/login?return=/books/new` instead of `/login`, so that signing in lands on the form.

4. **Write the views.** `application/views/Bookshelf/new.pug`, the form that posts to `create_book`:

   ```pug
   extends ../default/master/site

   block content
     section.cq-hero.cq-hero--page
       h1.cq-display New book

     section.cq-band.cq-band--tight
       .cq-inner.cq-inner--narrow
         if flash('error')
           .cq-alert.cq-alert--error(role='alert')= flash('error')
         form(method='POST', action=$home . 'books')
           =csrf()
           .cq-field
             label(for='title') Title
             input#title.form-control(type='text', name='title', value=old('title'))
             if errors('title')
               p.cq-field-error= errors('title')
           .cq-field
             label(for='published_year') Year
             input#published_year.form-control(type='number', name='published_year', value=old('published_year'))
             if errors('published_year')
               p.cq-field-error= errors('published_year')
           .cq-field
             label(for='author_id') Author
             select#author_id.form-select(name='author_id')
               each $a in $authors
                 option(value=$a['id'], selected=$a['id'] == old('author_id'))= $a['name']
             if errors('author_id')
               p.cq-field-error= errors('author_id')
           button.btn.btn-primary(type='submit') Add book
   ```

   Replace `application/views/Bookshelf/book.pug` with the book's page and its forms (edit, cover, delete):

   ```pug
   extends ../default/master/site

   block content
     section.cq-hero.cq-hero--page
       h1.cq-display= $book['title']
       p.cq-lead= 'by ' . $book['author']['name'] . ', ' . $book['published_year']

     section.cq-band.cq-band--tight
       .cq-inner.cq-inner--narrow
         if flash('success')
           .cq-alert.cq-alert--success(role='status')= flash('success')
         if flash('error')
           .cq-alert.cq-alert--error(role='alert')= flash('error')
         form(method='POST', action=$home . 'books/' . $book['id'])
           =csrf()
           =methodField('PUT')
           .cq-field
             label(for='title') Title
             input#title.form-control(type='text', name='title', value=old('title', $book['title']))
             if errors('title')
               p.cq-field-error= errors('title')
           .cq-field
             label(for='published_year') Year
             input#published_year.form-control(type='number', name='published_year', value=old('published_year', $book['published_year']))
             if errors('published_year')
               p.cq-field-error= errors('published_year')
           button.btn.btn-primary(type='submit') Save
         form(method='POST', action=$home . 'books/' . $book['id'] . '/cover', enctype='multipart/form-data')
           =csrf()
           .cq-field
             label(for='cover') Cover (PNG, JPG or WebP, 2 MB at most)
             input#cover(type='file', name='cover', accept='.png,.jpg,.webp')
             if errors('cover')
               p.cq-field-error= errors('cover')
           button.btn.btn-secondary(type='submit') Save the cover
         form(method='POST', action=$home . 'books/' . $book['id'])
           =csrf()
           =methodField('DELETE')
           button.btn.btn-secondary(type='submit') Delete this book
   ```

   Replace `application/views/Bookshelf/books.pug` with the list, its message and a link to the form:

   ```pug
   extends ../default/master/site

   block content
     section.cq-hero.cq-hero--page
       h1.cq-display Books

     section.cq-band.cq-band--tight
       .cq-inner
         if flash('success')
           .cq-alert.cq-alert--success(role='status')= flash('success')
         if $books['total'] == 0
           p.cq-empty-state No books yet.
         else
           ul.cq-rows
             each $book in $books['data']
               li.cq-row
                 a(href=$home . 'books/' . $book['id'])= $book['title']
                 span= ' (' . $book['author']['name'] . ', ' . $book['published_year'] . ')'
         a.btn.btn-primary(href=$home . 'books/new') Add a book
   ```

   The cover form is used in [step 7](#files).

5. **Try the forms in the browser.** Open `http://localhost:8080/books/new`: you are sent to
   `/login?return=%2Fbooks%2Fnew`. Open `http://localhost:8080/register`, create an account (a username, a password
   typed twice), then open `/books/new` again and sign in: you land on the form.

   + On `/books/new`, send the form empty: the page comes back with "Some of the inputs are invalid" at the top and,
     under the fields, "The Title field is required" and "The Published Year field is required" (the list of authors
     always sends one).
   + Type `The Dispossessed` as the title and `2999` as the year: the title stays in its field, and the message is
     "The Published Year field needs to be a numeric value, equal to, or lower than <this year>".
   + Type `The Dispossessed`, `1974` and choose *Ursula K. Le Guin*: you land on `/books/6` with "Book added".
   + Change the title and click **Save**: "Saved". Click **Delete this book**: you land on `/books` with "Book deleted".

6. **Try them with curl.** A form post needs the session cookie and its CSRF token. Run:

   ```bash
   curl -s -c jar.txt -b jar.txt http://localhost:8080/login | grep -o "name='_token' value='[^']*'"
   ```

   It prints `name='_token' value='<token>'`. Sign in with that token:

   ```bash
   curl -s -i -c jar.txt -b jar.txt -X POST http://localhost:8080/login -d "_token=<token>&username=<you>&passcode=<your password>"
   ```

   It answers `302 Found` with `Location: http://localhost:8080/books`. Signing in gives the session a new CSRF token:
   read it again from the form page, then post the form with it:

   ```bash
   curl -s -c jar.txt -b jar.txt http://localhost:8080/books/new | grep -o "name='_token' value='[^']*'"
   curl -s -i -c jar.txt -b jar.txt -X POST http://localhost:8080/books -d "_token=<new token>&title=Wild Seed&published_year=1980&author_id=2"
   ```

   It answers `302 Found` with `Location: http://localhost:8080/books/<new id>`. With the old token, or without
   `_token`, a post answers `403` `[ERROR 3000] Security Error - Cross Site Forgery`.

   The same route answers a script that asks for JSON, with the token in the `X-CSRF-Token` header:

   ```bash
   curl -s -i -c jar.txt -b jar.txt -X POST -H "Accept: application/json" -H "X-CSRF-Token: <token>" \
        -H "Content-Type: application/json" http://localhost:8080/books -d '{"title":"","published_year":1969,"author_id":2}'
   ```

   It answers `422` with
   `{"error":{"code":"invalid","message":"Some of the inputs are invalid","fields":{"title":"The Title field is required"}}}`;
   with a title, `201` and `{"data":{"id":...,"title":...,"author":{...}}}`. Without the header: `403` and
   `{"error":{"code":"forbidden","message":"Missing or invalid CSRF token: send the X-CSRF-Token header"}}`.

7. **Add a file input.** The books need a column for the cover. The table holds data: add it with a
   [migration](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations). Run:

   ```bash
   docker compose exec dev-box php caligrafer.php migrate make add_books_cover
   ```

   It prints ` application/migrations/<number>_add_books_cover.sql written: add its SQL, then php caligrafer.php migrate`.
   Write in that file:

   ```sql
   -- add_books_cover
   ALTER TABLE books ADD COLUMN cover VARCHAR(255) NULL AFTER summary;
   ```

   Run `docker compose exec dev-box php caligrafer.php migrate` (it prints ` OK   <number>_add_books_cover.sql`), and
   add `public $cover;` after `public $summary;` in `application/models/Bookshelf/Book.php`. Then run
   `docker compose exec dev-box php caligrafer.php service Bookshelf/SetCover --scope books:write`, and replace the
   content of `application/services/Bookshelf/SetCover.php` with:

   ```php
   <?php

   use Caligrafy\Capability;
   use Caligrafy\Context;
   use Caligrafy\Database;
   use Caligrafy\Service;

   #[Capability(
       name: 'set_cover',
       description: 'Sets the cover image of a book, by its id. Returns the id and the URL of the cover.',
       scope: 'books:write'
   )]
   class SetCover extends Service
   {
       public $rules = array(
           'id' => 'required|integer|exists,books.id',
           'cover' => 'required|file|extension,png;jpg;webp|max_size,2mb',
       );

       public function handle(array $input, Context $context)
       {
           Database::table('books')->where('id', $input['id'])->update(array('cover' => $input['cover']));
           return array('id' => (int) $input['id'], 'cover' => $input['cover']);
       }
   }
   ```

   Add the route, after the other `Route::form` lines:

   ```php
       Route::form('/books/{id}/cover', 'set_cover', ['success' => '/books/{id}', 'flash' => 'Cover saved']);
   ```

   On a book's page, choose a PNG file and click **Save the cover**: "Cover saved", and the `cover` column of the book
   holds `http://localhost:8080/public/uploads/<24 random characters>.png`. A GIF answers "This type of file is not
   accepted: png, jpg, webp"; a 3 MB PNG "The file is too large (at most 2 MB)"; no file "The Cover field needs a
   file" (2.19.1).

8. **Try the export and the redirect.** Run:

   ```bash
   curl -s -i http://localhost:8080/authors.csv
   ```

   It answers `200` with `Content-Type: text/csv; charset=utf-8`, `Content-Disposition: attachment; filename="authors.csv"`
   and the rows of `list_authors`, the same capability the form's list reads:

   ```text
   id,name,bio,created_at,modified_at
   3,Italo Calvino,"Italian writer of playful, inventive novels and short stories.",2026-11-07 23:10:56,2026-11-07 23:10:56
   2,Octavia E. Butler,"American science fiction author, the first to receive a MacArthur fellowship.",2026-11-07 23:10:56,2026-11-07 23:10:56
   1,Ursula K. Le Guin,"American author of science fiction and fantasy, known for the Earthsea books.",2026-11-07 23:10:56,2026-11-07 23:10:56
   ```

   `curl -s -i http://localhost:8080/catalog` answers `302 Found` with `Location: http://localhost:8080/books`.

9. **Add a sign-up page of your own.** `/register` asks for a username and a password. The page `/join` also asks to
   accept the terms, signs the person in at once, and is for visitors only. In `web.php`, after `Route::auth(...)`:

   ```php
       Route::page('/join', 'Bookshelf/join', ['visitorsOnly' => '/books']);
       Route::form('/join', 'register_user', [
           'visitorsOnly' => '/books',
           'same' => ['confirmpasscode' => ['passcode', 'The two passwords are not the same']],
           'accepted' => ['terms' => 'Accept the terms of use to create an account'],
           'signsIn' => 'id',
           'success' => '/books',
           'back' => '/join',
           'flash' => 'Welcome to the Bookshelf',
       ]);
   ```

   `register_user` is the capability the authentication module ships. It answers the new account, and `'signsIn' => 'id'`
   signs in the person whose id is in that field. Write `application/views/Bookshelf/join.pug`:

   ```pug
   extends ../default/master/site

   block content
     main.cq-auth
       h1 Join the Bookshelf
       if flash('error')
         .cq-alert.cq-alert--error(role='alert')= flash('error')
       form(method='POST', action=$home . 'join')
         =csrf()
         .cq-field
           label(for='username') Username
           input#username.form-control(type='text', name='username', value=old('username'), required)
           if errors('username')
             p.cq-field-error= errors('username')
         .cq-field
           label(for='passcode') Password
           input#passcode.form-control(type='password', name='passcode', required, autocomplete='new-password', maxlength='72')
           if errors('passcode')
             p.cq-field-error= errors('passcode')
         .cq-field
           label(for='confirmpasscode') The same password again
           input#confirmpasscode.form-control(type='password', name='confirmpasscode', required, autocomplete='new-password', maxlength='72')
           if errors('confirmpasscode')
             p.cq-field-error= errors('confirmpasscode')
         .cq-field
           label
             input(type='checkbox', name='terms', value='1')
             |  I accept the terms of use
           if errors('terms')
             p.cq-field-error= errors('terms')
         button.btn.btn-primary(type='submit') Join
   ```

   Sign out and open `http://localhost:8080/join`. Send two different passwords and leave the box empty: the page comes
   back with "Some of the inputs are invalid", "The two passwords are not the same" under the second password and
   "Accept the terms of use to create an account" under the box, and the username is still there. Send the form right: you
   land on `/books`, signed in, with "Welcome to the Bookshelf" (`books.pug` shows `flash('success')`). Open `/join` again:
   you are sent to `/books`.

10. **Answer a request no page or form can say.** When an author has exactly one book, `/authors/{author_id}/books` goes
    straight to that book; otherwise it shows the list. One address, two answers: [a web-only capability](#action). Run
    `docker compose exec dev-box php caligrafer.php service Bookshelf/OpenAuthorBooks` and replace the content of
    `application/services/Bookshelf/OpenAuthorBooks.php` with:

    ```php
    <?php

    use Caligrafy\Capability;
    use Caligrafy\Context;
    use Caligrafy\Service;
    use Caligrafy\Web;

    // open_author_books: one book goes straight to its page, otherwise the list
    #[Capability(
        name: 'open_author_books',
        description: 'Opens the books of an author: the book itself when there is one, otherwise the list.',
        readOnly: true,
        public: true,
        webOnly: true
    )]
    class OpenAuthorBooks extends Service
    {
        public $rules = array('author_id' => 'required|integer');

        public function handle(array $input, Context $context)
        {
            $books = $this->capability('list_books', array('author_id' => $input['author_id']), $context);
            if ($books['total'] == 1) {
                return Web::redirect('/books/' . $books['data'][0]['id']);
            }
            return Web::view('Bookshelf/books', array('books' => $books));
        }
    }
    ```

    In `web.php`:

    ```php
        Route::action('/authors/{author_id}/books', 'open_author_books', ['back' => '/books']);
    ```

    Run:

    ```bash
    curl -s -i http://localhost:8080/authors/3/books | grep -iE "^HTTP|^Location"
    curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8080/authors/2/books
    curl -s -i http://localhost:8080/authors/abc/books | grep -iE "^HTTP|^Location"
    ```

    They print `HTTP/1.1 302 Found` with `Location: http://localhost:8080/books/5` (Italo Calvino has one book), `200` (the
    list of Octavia E. Butler's two books), and `302` to `/books` (the `back` option), where `flash('error')` says "Some of
    the inputs are invalid". `docker compose exec dev-box php caligrafer.php call open_author_books author_id=3` prints
    `{"error":{"code":"not_found","message":"There is no capability 'open_author_books'"}}`: only the route reaches it.

11. **Receive events from another service.** Webhooks are in [their own section](#webhook), with a complete example.

**Check**

- [ ] `/books/new` sends a visitor to `/login?return=%2Fbooks%2Fnew`, shows the form to someone signed in, and signing in
      from there lands on the form.
- [ ] An empty form comes back with a message under each field; a reload of that page shows no message (they last one
      request).
- [ ] A valid form lands on the new book's page with "Book added"; Save shows "Saved"; Delete lands on `/books` with
      "Book deleted".
- [ ] A post without `_token` answers `403`.
- [ ] `/authors.csv` downloads the rows; `/catalog` redirects to `/books`.
- [ ] `/join` signs a new person in and sends someone already signed in to `/books`.
- [ ] `/authors/3/books` redirects to `/books/5`, and `/authors/2/books` shows a list.

## Forms: `Route::form`

`Route::form($route, $capability, $options)` answers the form's `POST` (or its `PUT` or `DELETE` through `_method`)
by calling the capability with the form's fields, its files and the route variables (the route variables win), as the
person who sent it. The page that shows the form stays a `Route::page`.

| The capability | The person goes to | The next page has |
|---|---|---|
| succeeds | `success`, its `{name}` filled from the result, then from the route variables; without `success`, back to the form | `flash('success')` with the `flash` message |
| refuses the input (`invalid`) or finds a duplicate (`conflict`) | back: the `back` option, else the page it came from (the `Referer`, when it is a page of this site), else `/` | `errors('title')`, `old('title')`, `flash('error')` |
| needs someone signed in, nobody is | the sign-in page (`signIn`, default `/login`); without the authentication module, `403` | |
| refuses the person (`forbidden`) | `403` `[ERROR 1006] Forbidden` | |
| finds nothing, or anything else | `404` `[ERROR 1005] Page Not Found` | |

| Option | Default | What it does |
|---|---|---|
| `method` | `'POST'` | `'PUT'`, `'DELETE'`, or a list (`['POST', 'PUT']`); `GET` is refused (pages are `Route::page`) |
| `success` | back to the form | a path with `{placeholders}`, or `function ($result) { return '/books/' . $result['id']; }` |
| `flash` | none | the message for `flash('success')`, with `{placeholders}` as in `success`; or `['key' => 'message']` |
| `back` | the `Referer` of this site, else `/` | where invalid input goes, with the route's `{placeholders}`: `'back' => '/books/{id}'` |
| `error` | the capability's message | your message for `flash('error')`, or `['key' => 'message']` |
| `signIn` | `'/login'` | where a visitor goes when the capability needs someone |
| `visitorsOnly` | none | a path: people already signed in are sent there and the capability does not run; a JSON request gets `403` |
| `same` | none | fields that must match: `['password_again' => ['password', 'The two passwords are not the same']]`; without a message, "The two values are not the same" |
| `accepted` | none | boxes that must be ticked: `['terms' => 'Accept the terms of use']` |
| `inputs` | none | values only the server sets: an array, or `function ($input) { return [...]; }`. They replace what the form sent; the route variables still win |
| `signsInWith` | `'password'` | how `signsIn` signed the person in, for `AuthRoutes::signedInBy()` (2.21.7) |
| `signsIn` | none | the field of the capability's answer that holds the id of the person to sign in: `'signsIn' => 'id'`. A new session id and a new CSRF token, as for `Route::auth`. It records no way of signing in: `AuthRoutes::signedInBy()` answers `null` for that session |

The last five options arrived in 2.20.0.

**The order of the checks.** `visitorsOnly`, then (JSON with the session) the CSRF header, then `same` and `accepted`,
then the capability. A form that breaks `same` or `accepted` never reaches the capability, and their messages arrive in
`errors('password_again')`, like the capability's. Both are read from what the person sent: declare `password_again` in
`$rules` only if the capability needs it.

**Values the form must not choose.** A form can send any field. `inputs` writes the ones only the server knows, over
anything the form sent: the address a payment page comes back to, the visitor's address, today's price.

**A form that leaves the site.** `success` can be a full `https://` address, or a function that returns one: that is how a
form leaves for a payment page.

```php
Route::form('/orders/{order}/pay', 'start_checkout', [
    'inputs' => fn () => ['site' => session('home')],
    'success' => fn ($result) => $result['url'],
    'back' => '/orders',
]);
```

**A form that signs someone in.** `signsIn` is for a capability that creates or finds the person, and answers their id:
sign-up, an invitation link, a confirmation code. The form that decides how to sign in, or a sign-in with a state to
check, is a [`Route::action`](#action).

+ **The CSRF token** of the form (`=csrf()`) is checked as for every form (`403` `[ERROR 3000]` without it).
+ **One request only.** The errors, the old values and the messages are read by the next page, then gone: point
  `success` and `back` at the page that shows them. Passwords, card numbers, tokens (any field whose name contains
  `pass`, `secret`, `token`, `card`, `cvc`, `cvv` or `iban`), files and `_` fields are never kept for `old()`.
+ **A placeholder with no value** (`'success' => '/authors/{author}'` when neither the result nor the route has
  `author`) stops with an error that names it.
+ **JSON**: a request with `Accept: application/json` gets the REST answer instead of redirects: `{"data": ...}` with
  `201` (a `POST` that changes data) or `200`, or `{"error": ...}` with `401`, `403`, `404`, `409` or `422`. With the
  session cookie it needs the `X-CSRF-Token` header.
+ `flash('saved', 'Your changes are saved')` in your own code keeps a message for the next request too.

The view helpers are described in [Views: forms](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#formhelpers):
`errors()` (all the messages), `errors('title')` (one field's, or `null`), `old('title', $default)`, `flash('success')`.

## File inputs: the `file` rule

A capability declares a file input with the rule `file`, an allow-list and a maximum size:

```php
public $rules = array('cover' => 'required|file|extension,png;jpg;webp|max_size,2mb');
```

+ **Web pages (`Route::form`) and REST (`Route::capability`, `Route::capabilities`)** take the file from the request
  once the caller is allowed to call the capability, check it (one file, the allow-list, the size), save it in
  `public/uploads/` under a new random name (the name the browser sent is never used) and give `handle()` its public
  **URL**, a string. A text sent in that field is dropped. When the capability fails, the saved file is deleted.
+ `extension,png;jpg` lists the types allowed; without it, the default list of `uploadFile()` applies (images,
  documents, audio, video, `zip`, `json`). Scripts and markup (`php`, `phtml`, `phar`, `html`, `js`, `svg`...) are
  always refused.
+ `max_size` takes a number of bytes or `kb`, `mb`, `gb` (`500kb`, `1.5mb`). The size limits of PHP
  (`upload_max_filesize`, `post_max_size`) still apply first.
+ **AI agents (MCP) and the command line** cannot send a file: they send a URL or a path, whose extension is checked.
  Never open it without checking where it points.
+ The form needs `enctype='multipart/form-data'`. `required|file` and `required_file` both make the file required, on
  every channel: no file, URL or path answers "The Cover field needs a file", and the input schema of MCP and
  `GET /capabilities` lists the field in `required` (2.19.1; in 2.19.0 `required_file` alone was ignored).
+ `Route::action` passes the files of the request too: a web-only capability with a `file` rule gets the URL.

## Sign-in: `Route::auth`

```php
Auth::authentication('User', 'users');
Route::auth(['home' => '/books']);
// a users table with an email column: people sign in with it, and are signed in right after signing up
Route::auth(['home' => '/books', 'identifier' => 'email', 'signInAfterRegister' => true]);
```

This declares `GET`/`POST /login`, `GET`/`POST /logout`, `GET`/`POST /register` and `GET /notAuthorized`, with the
views of the authentication module (`application/views/Auth/login.pug`, `register.pug`).

| Option | Default | What it does |
|---|---|---|
| `home` | `'/'` | where people go after signing in (a signed-in person who opens `/login` goes there too) |
| `logout` | `'/'` | where people go after signing out |
| `register` | `true` | `true`: `/register` with the `register_user` capability; `false`: no sign-up; a capability name: yours |
| `identifier` | `'username'` | the `users` column people sign in with, and the name of the sign-in field (`'email'`) |
| `signInAfterRegister` | `false` | `true`: a new account is signed in and goes to `home` instead of the sign-in page |
| `attemptsPerUser` | `0` (off) | failed sign-ins of one username from any address before it is locked (2.19.6) |
| `level` | `0` | the lowest permission level that may sign in (2.19.5): below it, a sign-in is refused like a wrong password |
| `messages` | human words | what sign-in and sign-up say: `invalid`, `locked` (2.19.3, `{minutes}` is the wait), `mismatch`, `register_invalid`, `register_taken`, `registered` (2.19.4); the defaults are `AuthRoutes::MESSAGES` |
| `attempts`, `lock` | `5`, `900` | failed sign-ins per username and address before a `429`, and the window in seconds |
| `views` | `['login' => 'Auth/login', 'register' => 'Auth/register']` | your own views |
| `prefix` | `''` | `'/account'` gives `/account/login`... (the shipped views post to `/login`: write your own) |
| `passkeys` | `false` | `true` adds `POST /passkey/register/options`, `/passkey/register`, `/passkey/login/options`, `/passkey/login` |

+ Signing in and signing out give the person a new session id and a new CSRF token.
+ The fifth failed sign-in for a username from one address within 15 minutes, and every one after it until the 15
  minutes end, answers `429` "Too many tries in a row. For your security, wait 15 minutes, then try again." (2.19.3; the `messages` option sets your own words).
+ A JSON request with `X-CSRF-Token` gets `200` `{"data":{"user":{"id":1,"username":"ada","permissions":0}}}`, `401`
  or `429` in JSON: a Vue app signs in with the same routes.
+ Sign-up sends the register capability every input its `$rules` declare; nothing else of the form reaches it.
+ After signing in, a person goes to `home`, or to the page named by `/login?return=/books/3` (2.20.0). Only a path of
  this site is followed: another site, or `//evil.example`, is ignored and the person goes to `home`. `Route::page` and
  `Route::action` with `'comeBack' => true` build that address for you. A person already signed in who opens that address
  goes straight to the page.
+ Not in `Route::auth`: password reset and face sign-in. `'reset' => true` or `'face' => true` stops with an error. They
  are declared with the other routes: [password reset](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#passwordreset) is
  `Route::page` and `Route::form`, [face sign-in](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition#face-login)
  is `Route::action`.

+ **Sign in with an email.** The simplest way keeps the `users` table: the `username` column holds the lower-cased address.
  [Signing in with an email](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#emailsignin) has the
  steps. `'identifier' => 'email'` is for a table with a separate `email` column.
+ **How a session signed in.** `Route::auth` records `password` or `passkey`, and `AuthRoutes::signedInBy()` reads it (2.21.3).
  A face alone must not change how an account signs in:
  [How a session signed in](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#signedinby).

Everything about users, levels and the sign-in views:
[https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#routeauth).

## Webhooks: `Route::webhook`

A payment provider, a library service or GitHub posts events to an address of yours. `Route::webhook` checks that the
event comes from them (its signature), then calls a capability with it. There is no session and no CSRF token: the
signature is the only proof.

1. **A table that remembers the events.** The Bookshelf database already holds data: add the table with a
   migration. Run `docker compose exec dev-box php caligrafer.php migrate make create_webhook_events`, and write in the
   file it names (`application/migrations/<number>_create_webhook_events.sql`):

   ```sql
   -- create_webhook_events
   CREATE TABLE IF NOT EXISTS webhook_events (
       id INT UNSIGNED NOT NULL AUTO_INCREMENT,
       event_id VARCHAR(100) NOT NULL,
       type VARCHAR(100) NOT NULL,
       created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
       PRIMARY KEY (id),
       UNIQUE KEY uq_webhook_events_event_id (event_id)
   ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
   ```

   Run `docker compose exec dev-box php caligrafer.php migrate`. It prints ` OK   <number>_create_webhook_events.sql`.

2. **The capability.** Run `docker compose exec dev-box php caligrafer.php service Bookshelf/RecordEvent`, and replace the content of
   `application/services/Bookshelf/RecordEvent.php` with:

   ```php
   <?php

   use Caligrafy\Capability;
   use Caligrafy\Context;
   use Caligrafy\Database;
   use Caligrafy\Service;
   use Caligrafy\ServiceError;

   #[Capability(
       name: 'record_event',
       description: 'Records an event that the library service posts (its id and type), once per event id.'
   )]
   class RecordEvent extends Service
   {
       public $rules = array('event' => 'required');

       public function handle(array $input, Context $context)
       {
           // only the webhook route may record events: REST, MCP and pages would let a signed-in user make one up
           if ($context->actorType() !== 'webhook') {
               throw ServiceError::forbidden('Only the library service sends events');
           }
           $event = $input['event'];
           if (!is_array($event) || empty($event['id']) || empty($event['type'])) {
               throw ServiceError::invalid(array('event' => 'The event needs an id and a type'));
           }
           // the sender posts an event again until it gets a 2xx: record each id once
           if (!Database::table('webhook_events')->where('event_id', $event['id'])->exists()) {
               Database::table('webhook_events')->insert(array('event_id' => $event['id'], 'type' => $event['type']));
           }
           return array('recorded' => $event['id']);
       }
   }
   ```

3. **The secret and the route.** Add the secret the sender gave you to `.env` (a placeholder here):

   ```text
   LIBRARY_WEBHOOK_SECRET=whsec_test_library
   ```

   In `application/config/routing/web.php`, before `Route::run();`:

   ```php
       // the library service signs each event: X-Signature: sha256=<HMAC-SHA256 of the body with the secret>
       Route::webhook('/hooks/library', 'record_event', ['verify' => function ($rawBody, $headers) {
           $expected = 'sha256=' . hash_hmac('sha256', $rawBody, (string) getenv('LIBRARY_WEBHOOK_SECRET'));
           return hash_equals($expected, $headers['x-signature'] ?? '');
       }]);
   ```

4. **Post a signed event.** Run:

   ```bash
   BODY='{"id":"evt_1","type":"loan.returned"}'
   SIG="sha256=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac whsec_test_library -r | cut -d' ' -f1)"
   curl -s -i -X POST -H "Content-Type: application/json" -H "X-Signature: $SIG" http://localhost:8080/hooks/library -d "$BODY"
   ```

   It answers `200` with `{"received":true}`, and `webhook_events` has one row (`evt_1`, `loan.returned`). The same
   post again answers `200` and adds nothing. With `-H "X-Signature: sha256=bad"`: `400` and
   `{"error":{"code":"invalid_signature","message":"The signature is not valid"}}`. A `GET` answers `405`.

| The event | The answer |
|---|---|
| signature checked, capability done | `200 {"received": true}` |
| bad or missing signature | `400 {"error":{"code":"invalid_signature",...}}` |
| a body that is not JSON | `400 {"error":{"code":"invalid","message":"The body is not JSON"}}` |
| the capability refuses (`ServiceError`) | its status and its error (`422` for `invalid`) |
| no secret set, no such capability, an error in the capability | `500`; the message goes to the PHP error log |

**Stripe.** `'verify' => 'stripe'` checks the `Stripe-Signature` header with the Stripe library:

```php
Route::webhook('/stripe/events', 'record_event', ['verify' => 'stripe']);   // STRIPE_WEBHOOK_SECRET=whsec_... in .env
```

| Option | Default | What it does |
|---|---|---|
| `verify` | required | `'stripe'`, or `function (string $rawBody, array $headers): bool` (header names in lower case; only `true` passes) |
| `secret_env` | `'STRIPE_WEBHOOK_SECRET'` | the `.env` variable that holds the endpoint's signing secret (`whsec_...`, not the API key) |
| `secret` | none | the secret itself, instead of the variable (tests) |
| `tolerance` | `300` | how many seconds old a Stripe signature may be |

+ `verify` is required: a webhook without it stops with an error when `web.php` runs.
+ The body must be JSON; the capability gets `['event' => the decoded body]`, so declare `$rules = ['event' => 'required']`.
+ The capability runs as the system, with full access (the audit log shows `webhook /hooks/library`): treat the event
  as data from outside. Before trusting an amount, ask the provider again
  ([Stripe](https://github.com/caligrafy/caligrafy-quill/wiki/9.2-Stripe-&-Cryptocurrency-Payment#webhook)).
+ Services post an event again until they get a `2xx`: make the capability safe to run twice, as above.

## Downloads: `Route::download`

```php
Route::download('/authors.csv', 'list_authors', ['filename' => 'authors.csv']);
Route::download('/books.json', 'list_books', ['as' => 'json']);   // a page of list_books, ?page=2 for the next
```

| Option | Default | What it does |
|---|---|---|
| `as` | `'csv'` | `'csv'` or `'json'` (pretty-printed); anything else stops with an error when `web.php` runs |
| `columns` | the first row's keys | the header row and its order, written even when there is no row: `['title', 'year']` (2.19.5) |
| `filename` | `<capability>.<as>` | a name, or `function ($result, $input) { return 'books-' . date('Y-m-d') . '.csv'; }` |
| `bom` | `false` | `true` starts the CSV with a UTF-8 BOM (Excel) |
| `formulas` | `false` | `true` keeps cells that start with `=`, `+`, `-`, `@` as they are |
| `signIn` | `'/login'` | where a visitor goes when the capability needs someone |

+ The capability must be read-only (opening a link must not change data). It gets the query string and the route
  variables (`/books.csv?author_id=2`) as the person who opens the link. Refusals are those of `Route::page`: a
  visitor goes to sign in, `403`, or `404`. An input to fix (`?author_id=abc`) answers `422` with the capability's
  error as JSON, as REST does (2.19.5; before, `404`).
+ **CSV**: the header row comes from the `columns` option, else from the first row's columns (then an empty result is
  an empty file), values are quoted where needed (RFC 4180, lines end with
  CRLF), UTF-8. A text cell that starts with `=`, `+`, `-`, `@` or a tab gets a `'` first, so a spreadsheet does not
  run it as a formula. Arrays in a cell are written as JSON, booleans as `true` / `false`.
+ **The columns are the keys of the first row.** A model query returns models, and a model carries every one of its
  properties: `Book::query()->select('id', 'title')->get()` exports `id`, `title` and every other column of `Book`,
  empty. Return arrays for a narrow export: `Database::table('books')->select('id', 'title')->get()` or
  `Book::query()->select('id', 'title')->arrays()->get()`. A related row (`with('author')`) is one cell, in JSON.
+ Each row of the list the capability returns is a line. A `paginate()` page exports its rows (that page only: return
  `get()` to export every row); a single record gives one line.
+ The answer carries `Cache-Control: private, no-store` and `X-Content-Type-Options: nosniff`.

## Redirects: `Route::redirect`

```php
Route::redirect('/catalog', '/books');                   // 302
Route::redirect('/old/books/{id}', '/books/{id}', 301);  // moved for good
```

+ A `GET` that sends the visitor elsewhere, without a closure. `{name}` in the target takes the route variable of
  the same name (url-encoded); the query string never fills it (`/old/books/2?x=1` goes to `/books/2`).
+ The target is a path of the site or a full `https://` address. The status is `301`, `302` (default), `303`, `307`
  or `308`; anything else, or an empty target, stops with an error when `web.php` runs.
+ `Route::redirect($from, $to, $status, ['keepQuery' => true])` (2.20.0) takes the query string along:
  `/old?page=2` goes to `/new?page=2`. A `#fragment` of the target stays last.
+ A redirect that depends on who asks, or on the data, is a [`Route::action`](#action).

## Everything else: `Route::action`

`Route::page` shows a view and `Route::form` calls a capability and redirects. A few requests are neither: a sign-in
between two sites, a page that chooses between a view and a redirect, a gate, a page that creates a payment. Since
2.21.0 they are capabilities too, declared `webOnly: true` and served by one line. The controller of a new application
is gone: this replaces it.

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\Web;

#[Capability(
    name: 'open_author_books',
    description: 'Opens the books of an author: the book itself when there is one, otherwise the list.',
    readOnly: true,
    public: true,
    webOnly: true
)]
class OpenAuthorBooks extends Service
{
    public $rules = array('author_id' => 'required|integer');

    public function handle(array $input, Context $context)
    {
        $books = $this->capability('list_books', array('author_id' => $input['author_id']), $context);
        if ($books['total'] == 1) {
            return Web::redirect('/books/' . $books['data'][0]['id']);
        }
        return Web::view('Bookshelf/books', array('books' => $books));
    }
}
```

```php
Route::action('/authors/{author_id}/books', 'open_author_books', ['back' => '/books']);
Route::action('/unlock/{name}', 'unlock_shelf', ['method' => 'POST']);
Route::action('/shelf/report', 'shelf_report', ['level' => 1, 'comeBack' => true]);
```

| Option | Default | What it does |
|---|---|---|
| `method` | `'GET'` | `'POST'`, `'PUT'`, `'DELETE'`, or a list (`['GET', 'POST']`) |
| `signedIn`, `level` | none | as in `Route::page`: visitors go to `/login`, people below the level to `/notAuthorized` |
| `comeBack` | `false` | with `signedIn` or `level`, or when the capability needs someone: visitors go to `/login?return=<this page>` |
| `visitorsOnly` | none | a path: people already signed in are sent there |
| `back` | the `Referer` of this site, else `/` | where invalid input or a conflict goes, with the route's `{placeholders}` |
| `error` | the capability's message | your message for `flash('error')` |
| `signIn` | `'/login'` | where a visitor goes when the capability needs someone (`comeBack` replaces it) |

**What it answers.** A `Caligrafy\Web`, or plain data, which is sent as JSON.

| The capability returns | The browser gets |
|---|---|
| `Web::view('Bookshelf/book', $data, $status = 200)` | a Pug view, with `$authorized` and `$user` as on `Route::page` (`$data` wins). A request that asks for JSON gets the data as JSON |
| `Web::redirect('/books', $status = 302)` | a redirect: a path of this site or a full `http(s)` address |
| `Web::json($data, $status = 200)` | JSON, the data as it is, with no `{"data": ...}` around it |
| `Web::text($body, $status = 200)` | the text as it is |
| any of them `->header('Cache-Control', 'no-store')` | one more response header |
| plain data (an array, a model) | JSON, as with `Web::json` |

**When it refuses.** A capability that answers a refusal itself returns a `Web`. One that throws a `ServiceError` gets the
answers of `Route::form`:

| It throws | A browser | A JSON request |
|---|---|---|
| `invalid` or `conflict` | back (`back`, the `Referer`, or `/`) with `errors()`, `old()`, `flash('error')` | `422` or `409` and the REST error |
| `unauthenticated` | the sign-in page (`comeBack`: and back here) | `401` |
| `forbidden` | `403` `[ERROR 1006]` | `403` |
| `not_found` or anything else | `404` `[ERROR 1005]` | `404` |

**What it receives.** The inputs its `$rules` declare, from the form, the JSON body or the query string, and the route
variables. Its files are saved and given as URLs, as for `Route::form`. It runs as the person who sent the request, with
its `public`, `scope` and `level` checks, its validation and its audit log, like any capability.

> The route variables win over the query string and the form, as on `Route::page` and `Route::form`: `/books/3?id=9` gives
> `id = 3` (2.21.2; in 2.21.0 and 2.21.1 the query string replaced them here and in `Route::capability`). When a route
> variable decides what is reached, still check that the person may open that row.

### What a web-only capability may do

The rule of the framework still holds: **only capabilities touch models**. A web-only capability is a capability. What
sets it apart is what it may do besides:

| A plain capability | A web-only capability |
|---|---|
| returns data, or refuses | may also return a view, a redirect, JSON or text |
| never reads the session or the request: `$context` says who calls | may read `session()`, `request()`, `$_SERVER`, `$_COOKIE` |
| never signs anyone in | may sign people in and out |
| on a page, only `readOnly` | a `GET` may change data |
| reached by REST, MCP, the JS client, the command line, `Route::page`, `Route::form`, `$this->call()` | reached by **`Route::action` only** |

That last line is the price. Everywhere else it answers `not_found` "There is no capability", it is left out of
`GET /capabilities`, MCP and `php caligrafer.php capabilities`, and `Route::page`, `Route::form` or `Route::download` on it
fail. So keep the work that several channels share in plain capabilities, and call them from the web-only one:

```php
$books = $this->capability('list_books', array('author_id' => 2), $context);   // as the same person, with its rules
```

`$this->capability()` runs the other capability with its checks, validation and audit, and throws its `ServiceError`.

**Signing someone in.** For a page that leaves or reloads, `Caligrafy\AuthRoutes::signIn($user, null, 'partner')`: a new
session id, a new CSRF token and how it signed in (the third argument, 2.21.3, read by `AuthRoutes::signedInBy()`). For a page
that stays open and keeps sending JSON, `session_regenerate_id(true); Auth::authorize($user); AuthRoutes::rememberSignIn('face');`:
the page keeps its token. `$context` was built before the sign-in and does not change: to call a capability as the person
just signed in, build a new one with `Context::fromRequest(request(), 'web')`.

**CSRF and JSON.**

+ A form's `POST`, `PUT` or `DELETE` needs its `_token` (`=csrf()`), as for every form: `403` `[ERROR 3000]` without it.
+ A JSON call (`Accept: application/json`), `GET` included, needs the page's `X-CSRF-Token` header (`apiHeaders()` of the
  Vue apps) or, with `Auth::activateAPI();` in `web.php` (the template has it), a token that grants everything
  (`--scopes "*"`), signed in or not: `401` `[ERROR 1004]` without it (2.21.1). A scoped token is refused: programs use the
  capability routes.
+ A plain `GET` opened by a link has no token. A `GET` that changes data can be triggered by a link on another site: keep
  it to what is harmless when that happens, and use `'method' => 'POST'` with a form for the rest.

**Tests.** `$this->action('open_author_books', ['author_id' => 3], $user)` calls it as `Route::action` does and returns
what it answered; `->describe()` gives `type`, `target`, `data`, `status` and `headers`. `$this->call()` answers
`not_found`, as every other channel does. See [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App#helpers).

**The data routes of the Watson bot apps** are one line too: `Route::bots();`.

## Do you still need a controller?

No. With `Route::page`, `Route::form`, `Route::auth`, `Route::webhook`, `Route::download`, `Route::redirect`,
`Route::action` and the capability routes, every request is declared, and an application can have no controller at all.
Existing controllers keep working and can stay:
[Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers) shows how to move one when you want to. Never write data in a closure route
(`Route::post('/x', function () {...})`): it has no permission rules and no CSRF check for JSON calls.

| The request | Declare it with |
|---|---|
| A view, with data from read-only capabilities | `Route::page` |
| A form that calls one capability, then redirects | `Route::form` |
| Sign-in, sign-out, sign-up pages | `Route::auth` |
| A confirmation or sign-up that signs someone in | `Route::form` with `signsIn` |
| Events another service posts | `Route::webhook` |
| A file | `Route::download` |
| An address that moved | `Route::redirect` |
| Data for programs, the JS client, agents | `Route::capability`, `Route::capabilities`, `Route::mcp` |
| Different answers for one address, the session, a sign-in of your own, a page that creates a payment | `Route::action` |

## Common mistakes

+ **`success` redirects to a page that redirects again**: the message is read by the page in between and lost. Point
  `success` and `back` at the page that shows it.
+ **A JSON post with the session and no `X-CSRF-Token`** answers `403`: send the header (`apiHeaders()` in the Vue
  apps).
+ **`/books/new` declared after `/books/{id}`**: `/books/new` shows the *Not found* page with "Some of the inputs are
  invalid" (`get_book` refuses the id `new`).
+ **A file form without `enctype='multipart/form-data'`**: no file arrives, and the form comes back with "The Cover
  field needs a file".
+ **`'verify' => 'stripe'` without `STRIPE_WEBHOOK_SECRET`**: every event answers `500`. Use the endpoint's
  `whsec_...`, not the `sk_...` key.
+ **A webhook capability that is not idempotent**: a retried event is recorded twice.
+ **A download of a capability that writes**: `Route::download` refuses it when the link is opened.
+ **`Route::action` on a capability without `webOnly: true`**, or `webOnly: true` behind `Route::page`, `Route::form` or
  REST: the first stops with an error that names it, the others answer "There is no capability".
+ **A web-only capability for work other channels need**: agents and programs cannot reach it. Put the work in a plain
  capability and call it with `$this->capability()`.
+ **A JSON call to `Route::action` without `X-CSRF-Token`**: `401` `[ERROR 1004]`, even for a `GET` and for a visitor. Send
  `apiHeaders()`.
+ **`AuthRoutes::signIn()` on a page that stays open**: it gives the session a new CSRF token, and the page's next JSON call
  answers `401`. Use `session_regenerate_id(true); Auth::authorize($user);` there.
+ **`comeBack` with a `return=` you build by hand to another site**: it is ignored, the person goes to `home`.

## Related pages

+ [Routing](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing): `Route::page` and how routes match
+ [Validation](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation): the rules of `$rules`, `exists`, `unique`, `file`
+ [Views: forms](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#formhelpers): `errors()`, `old()`, `flash()`
+ [Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#routeauth): `Route::auth` in depth
+ [Stripe & cryptocurrency payment](https://github.com/caligrafy/caligrafy-quill/wiki/9.2-Stripe-&-Cryptocurrency-Payment#webhook)
+ [Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers): what existing controllers do, and how to move one
+ [Release notes 2.19.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.19.0), [2.20.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.20.0), [2.21.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.21.0)
