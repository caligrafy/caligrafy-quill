# File uploads: the `file` rule

A capability declares a file input with the rule `file`, an optional allow-list of extensions and an optional maximum
size. Each channel then brings the file its own way, and `handle()` always gets a string: the file's public URL (web
pages, REST) or a URL or path (AI agents, the command line). Since 2.19.0; `required` with a file on every channel since
2.19.1.

```php
public $rules = array('cover' => 'required|file|extension,png;jpg;webp|max_size,2mb');
```

<br/>

## With Claude

```text
/caligrafy-quill:new-capability set_cover books:write: sets the cover image of a book (PNG, JPG or WebP, 2 MB at most), from a form on the book's page
```

**Check** before you say OK:
+ the rule has `file`, an `extension` list and a `max_size`;
+ the form has `enctype='multipart/form-data'`;
+ a GIF, a 3 MB file and no file each come back with a message under the field.

<br/>

## By hand

1. **A column for the file.** The table holds data: add it with a
   [migration](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations):
   `docker compose exec dev-box php caligrafer.php migrate make add_books_cover`, then in the file it names:

   ```sql
   -- add_books_cover
   ALTER TABLE books ADD COLUMN cover VARCHAR(255) NULL AFTER summary;
   ```

   Run `docker compose exec dev-box php caligrafer.php migrate`.

2. **The capability.** `docker compose exec dev-box php caligrafer.php service Bookshelf/SetCover --scope books:write`,
   then make `application/services/Bookshelf/SetCover.php` read:

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

3. **The route and the form.** In `web.php`:

   ```php
       Route::form('/books/{id}/cover', 'set_cover', ['success' => '/books/{id}', 'flash' => 'Cover saved']);
   ```

   On the book's page:

   ```pug
   form(method='POST', action=$home . 'books/' . $book['id'] . '/cover', enctype='multipart/form-data')
     =csrf()
     .cq-field
       label(for='cover') Cover (PNG, JPG or WebP, 2 MB at most)
       input#cover(type='file', name='cover', accept='.png,.jpg,.webp')
       if errors('cover')
         p.cq-field-error= errors('cover')
     button.btn.btn-secondary(type='submit') Save the cover
   ```

4. **Try it.** Choose a PNG and click **Save the cover**: "Cover saved", and the `cover` column holds
   `http://localhost:8080/public/uploads/<24 random characters>.png`. A GIF answers "This type of file is not accepted:
   png, jpg, webp"; a 3 MB PNG "The file is too large (at most 2 MB)"; no file "The Cover field needs a file".

<br/>

## <a name="rules"></a>The rules

| Rule | What it does |
|---|---|
| `file` | the input is a file |
| `extension,png;jpg` | the types allowed; without it, the default list of `uploadFile()` applies (images, documents, audio, video, `zip`, `json`). Scripts and markup (`php`, `phtml`, `phar`, `html`, `js`, `svg`...) are always refused |
| `max_size,2mb` | a number of bytes, or `kb`, `mb`, `gb` (`500kb`, `1.5mb`). PHP's own limits (`upload_max_filesize`, `post_max_size`) apply first |
| `required` with `file`, or `required_file` | the file is required on every channel: no file, URL or path answers "The &lt;Field&gt; field needs a file", and the input schema of MCP and `GET /capabilities` lists the field in `required` (2.19.1) |

The file rules are never given to the validation library: `Service::validate()` takes them out and
`Caligrafy\Uploads` applies them.

<br/>

## <a name="channels"></a>What each channel sends

| Channel | What `handle()` gets |
|---|---|
| Web pages (`Route::form`, `Route::action`) and REST (`Route::capability`, `Route::capabilities`) | the file is taken from the request once the caller is allowed to call the capability, checked (one file, the allow-list, the size), saved in `public/uploads/` under a new random name (the name the browser sent is never used), and `handle()` gets its public **URL**. A text sent in that field is dropped. When the capability fails, the saved file is deleted |
| AI agents (MCP) and the command line | they cannot send a file: they send a URL or a path, whose extension is checked against the allow-list. Never open it without checking where it points |

<br/>

## Reference

+ [`Caligrafy\Uploads`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#uploads)
+ The helpers [`uploadFile()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Helpers#fn-uploadfile),
  [`isAllowedUpload()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Helpers#fn-isallowedupload) and
  [`deleteFile()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Helpers#fn-deletefile)

<br/>

## Common mistakes

+ **A file form without `enctype='multipart/form-data'`**: no file arrives, and the form comes back with "The Cover
  field needs a file".
+ **`required_file` alone on 2.19.0** was ignored; since 2.19.1 it makes the file required on every channel.
+ **Opening the path an agent sent** without checking where it points: the command line and MCP send a string, not an
  uploaded file.

## Related pages

+ [Forms: `Route::form`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Form)
+ [Validation](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation)
+ [Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations)
+ [Declarative routes, step 7](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#files)
