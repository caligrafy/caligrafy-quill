# 6. Validation

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - `check()` returns an ARRAY both on success with filters (the filtered data) and on failure (errors). Prefer `filter()` then `validate()` (gotchas G6).
> - Rules other than `required` are skipped for empty values (verified by the QA suite).

# Validation

Validation checks that an input follows the rules the application expects before any code uses it; filtering cleans
an input (trims it, lowers its case) before the check. In Caligrafy both are written once, in the `$rules` and
`$filters` of a capability. The framework applies them on every call: a form (`Route::form`), the REST API, a Vue app,
the command line and AI agents all get the same message for each field. The rules also become the JSON Schema that
agents and the JS client see. The rules are those of [GUMP](https://github.com/Wixel/GUMP) 2.3 (the validation
library Caligrafy uses), plus Caligrafy's own: `exists`, `unique`, `current_year` and the file rules.

## With Claude

```text
/caligrafy-quill:new-capability create_book books:write
"The title is required, 200 characters at most, trimmed. The year is a whole number from 1450 to this year.
The author must exist."
```

Claude writes the `$rules`, `$filters` and `$descriptions` of the capability in `application/services/<Folder>/`.

**Check**

- [ ] Every input `handle()` reads is in `$rules` (`''` when it has no rule): the others never reach it.
- [ ] Mandatory inputs have `required`; a mandatory file input has `required|file` (or `required_file`, 2.19.1).
- [ ] A foreign key has `exists,<table>.id`; a value that must be unique has `unique,<table>.<column>` and the
      column keeps its `UNIQUE` index.
- [ ] `php caligrafer.php call <name>` with a wrong input answers `invalid` with a message per field.

## By hand

Before you start: the Bookshelf tables of
[Routing, step 1](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#definingroutes).

1. **Write the rules.** Run `docker compose exec dev-box php caligrafer.php service Bookshelf/CreateBook --scope books:write` and replace the
   content of `application/services/Bookshelf/CreateBook.php` with:

   ```php
   <?php

   use Caligrafy\Capability;
   use Caligrafy\Context;
   use Caligrafy\Service;

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

   (`Book` is the model of [Routing, step 2](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#definingroutes).)

2. **Send wrong inputs.** Run:

   ```bash
   docker compose exec dev-box php caligrafer.php call create_book title= published_year=2999 author_id=9
   ```

   It prints (the year is the year of today):

   ```json
   {
       "error": {
           "code": "invalid",
           "message": "Some of the inputs are invalid",
           "fields": {
               "title": "The Title field is required",
               "published_year": "The Published Year field needs to be a numeric value, equal to, or lower than 2026",
               "author_id": "There is no such author"
           }
       }
   }
   ```

   The same `fields` come back to a form (`errors('title')`), to REST (`422`) and to an agent (`isError: true`).

3. **Send good ones.** Run `docker compose exec dev-box php caligrafer.php call create_book "title=  Dawn " published_year=1987 author_id=2`.
   It prints `{"data": {"id": 6, "title": "Dawn", ..., "author": {"id": 2, "name": "Octavia E. Butler", ...}}}`: the
   `trim` filter removed the spaces before the check.

4. **See what agents see.** Run `docker compose exec dev-box php caligrafer.php capabilities --json`. The `input` of `create_book` is:

   ```json
   {
       "type": "object",
       "properties": {
           "title": {"type": "string", "maxLength": 200, "description": "The title of the book"},
           "published_year": {"type": "integer", "minimum": 1450, "maximum": 2026, "description": "The year it was first published, for example 1969 (not after this year)"},
           "summary": {"type": "string", "maxLength": 2000, "description": "Optional: one or two sentences about the book"},
           "author_id": {"type": "integer", "description": "The id of its author (list_authors)"}
       },
       "additionalProperties": false,
       "required": ["title", "published_year", "author_id"]
   }
   ```

**Check**

- [ ] Each wrong field has its own message; a right field has none.
- [ ] The schema lists every input, with `required` for the mandatory ones.

## How the rules are applied

+ **Syntax**: rules are separated by `|`, a rule's argument follows a `,`, and a list inside an argument uses `;`:
  `'cover' => 'required|file|extension,png;jpg|max_size,2mb'`.
+ **Order**: the filters run first, then the rules. Each field stops at its first broken rule, so a field has one
  message.
+ **Empty inputs**: a field with neither `required` nor `required_file` is not checked when it is empty or missing:
  `'summary' => 'max_len,2000'` accepts no summary. Add `required` when the value must be there.
+ **Only declared inputs**: only the keys of `$rules` reach `handle()`; an input with no rule is declared with `''`.
+ **Values stay strings**: a rule checks a value, it does not convert it. `integer` accepts `"5"` and `handle()`
  gets `"5"`.
+ **An unknown rule name** stops the call with an error (`'x' validator does not exist`).
+ **Messages** are GUMP's English messages, built from the field name (`published_year` -> "Published Year").
  `exists` and `unique` name the table or the column.

## Rules of the framework

| Rule | What it checks | Message | In the JSON Schema |
| --- | --- | --- | --- |
| `exists,<table>.<column>` | the value is in that column: a foreign key that points at a real row | "There is no such author" (the table in the singular) | nothing added |
| `unique,<table>.<column>` | no row has the value yet | "This name is already used" (the column) | nothing added |
| `unique,<table>.<column>,<ignoreColumn>,<ignoreField>` | the same, leaving out the row whose `<ignoreColumn>` equals the input `<ignoreField>` (an update); `<ignoreField>` defaults to `<ignoreColumn>` | the same | nothing added |
| `min_numeric,current_year`, `max_numeric,current_year` | the year of today, read when the input is checked | GUMP's `min_numeric` / `max_numeric` message with the year | `minimum` / `maximum`: this year |
| `file` | a file input ([below](#filevalidation)) | | `"description": "The URL or the path of a file"` |
| `extension,<a>;<b>` (with `file`) | the file's type is in the list | "This type of file is not accepted: png, jpg, webp" | nothing added |
| `max_size,<size>` (with `file`) | the file is at most that size: bytes, or `kb`, `mb`, `gb` (`500kb`, `1.5mb`) | "The file is too large (at most 2 MB)" | nothing added |
| `required_file` | in a capability, a file input that must be sent, like `required\|file` (2.19.1; before, it did not make the file required) | "The Cover field needs a file" | listed in `required`, `"description": "The URL or the path of a file"` |

```php
public $rules = array(
    'author_id'      => 'required|integer|exists,authors.id',
    'name'           => 'required|max_len,150|unique,authors.name',
    'published_year' => 'required|integer|min_numeric,1450|max_numeric,current_year',
);

// an update: the author being changed does not count as a duplicate of itself
public $rules = array('id' => 'required|integer', 'name' => 'required|max_len,150|unique,authors.name,id,id');
```

+ `exists` and `unique` run one bound query through the query builder, on the app's database (in tests, the test
  database). The table and the column are checked as the builder checks names (letters, digits and `_`, one optional
  `db.` prefix): a wrong name stops with an error and is never sent to the database.
+ They are checks, not constraints: two sign-ups at the same instant can both pass `unique`. Keep the column's
  `UNIQUE` index and catch the duplicate.
+ A rule named `exists` or `unique` that your app added with `GUMP::add_validator()` before the first validation is
  kept instead of the framework's.
+ `GUMP::set_error_message('exists', '...')` replaces their message for every field.

## Every rule

GUMP 2.3.2 (the version in `composer.lock`), checked in `vendor/wixel/gump/gump.class.php`. "Schema" is what
`Capabilities::schemaFromRules()` adds to the input's JSON Schema; every input is `"type": "string"` unless a rule
says otherwise, and `required` lists it in `required`. On a non-string type, `format`, `minLength` and `maxLength`
are left out; on a string, `minimum` and `maximum` are.

**Presence and type**

| Rule | Passes when the value | Schema |
| --- | --- | --- |
| `required` | exists and is not empty (not `null`, `''` or `[]`) | listed in `required` |
| `integer` | is an integer | `"type": "integer"` |
| `numeric` | is a number or a numeric string | `"type": "number"` |
| `float` | is a number with a decimal point | `"type": "number"` |
| `boolean` | is yes/no, on/off, 1/0 or true/false; `boolean,strict`: only true/false | `"type": "boolean"` |
| `valid_json_string` | is valid JSON | |
| `valid_array_size_greater,n`, `valid_array_size_lesser,n`, `valid_array_size_equal,n` | is an array with at least, at most, exactly `n` items | |

**Length and numbers**

| Rule | Passes when the value | Schema |
| --- | --- | --- |
| `max_len,n` | has at most `n` characters | `maxLength` |
| `min_len,n` | has at least `n` characters | `minLength` |
| `exact_len,n` | has exactly `n` characters | `minLength` and `maxLength` |
| `between_len,min;max` | has `min` to `max` characters | |
| `min_numeric,n` | is a number `>= n` (`current_year` allowed) | `minimum` |
| `max_numeric,n` | is a number `<= n` (`current_year` allowed) | `maximum` |
| `even`, `odd`, `prime` | is an even, odd, prime number | |
| `word_count,min;3;max;5` | has 3 to 5 words. Write the `;` form: with commas (`word_count,min,3,max,5`) the numbers are dropped and every value passes | |

**Text and characters**

| Rule | Passes when the value |
| --- | --- |
| `alpha` | has only letters |
| `alpha_numeric` | has only letters and digits |
| `alpha_dash` | has only letters, `-` and `_` |
| `alpha_numeric_dash` | has only letters, digits, `-` and `_` |
| `alpha_space` | has only letters and spaces |
| `alpha_numeric_space` | has only letters, digits and spaces |
| `valid_name` | looks like a person's name |
| `street_address` | looks like a street address |
| `starts,x` | starts with `x` |
| `regex,/pattern/` | matches the regular expression |
| `camel_case`, `snake_case`, `url_slug` | is in that format |
| `strong_password` | has 8 characters or more with an upper case letter, a lower case letter, a digit and a special character |
| `no_sql_injection`, `no_xss` | has none of the common SQL injection or script patterns (a filter of last resort: bind values and escape output anyway) |

**Lists and other fields**

| Rule | Passes when the value | Schema |
| --- | --- | --- |
| `contains,red;green` | is one of the values (the message lists them) | |
| `contains_list,red;green` | is one of the values (the message does not list them) | `enum: ["red", "green"]` |
| `doesnt_contain_list,red;green` | is none of the values | |
| `equalsfield,other` | equals the input `other` (a password confirmation) | |

**Formats**

| Rule | Passes when the value is | Schema |
| --- | --- | --- |
| `valid_email` | an email address | `"format": "email"` |
| `valid_url` | a URL | `"format": "uri"` |
| `url_exists` | a URL that answers (a network call at validation) | |
| `uuid` | a UUID, any version | `"format": "uuid"` |
| `guidv4` | a version 4 GUID | |
| `valid_ip`, `valid_ipv4`, `valid_ipv6` | an IP address | |
| `domain_name` | a domain name without a protocol | |
| `port_number` | a port, 1 to 65535 | |
| `mac_address` | a MAC address | |
| `valid_cc` | a credit card number (Luhn check) | |
| `iban` | an IBAN | |
| `phone_number` | a phone number such as `555-555-5555` or `1 (519) 555-4422` | |
| `postal_code,US` | a postal code of that country | |
| `country_code`, `language_code`, `currency_code` | an ISO 3166, ISO 639 or ISO 4217 code | |
| `timezone` | a timezone identifier (`Europe/Paris`) | |
| `hex_color`, `rgb_color` | a color | |
| `base64`, `jwt_token` | base64 data, a JWT | |
| `hash,md5` | a hash of that algorithm | |
| `social_handle` | a social media handle | |
| `latitude`, `longitude`, `coordinates` | a coordinate, or `lat,lng` | |

**Dates and times**

| Rule | Passes when the value is | Schema |
| --- | --- | --- |
| `date` | a date (ISO 8601); `date,d/m/Y` with that format | `"format": "date"` (also with a custom format) |
| `min_age,18` | a date of birth (`Y-m-d`) at least 18 years ago | |
| `future_date`, `past_date` | after, before now | |
| `business_day` | a Monday to Friday | |
| `valid_time` | `HH:MM` or `HH:MM:SS` | |
| `date_range,2024-01-01;2024-12-31` | between the two dates | |

**Uploaded files with GUMP** (in a controller's `check()` on `$_FILES`; in a capability, use [the `file` rule](#filevalidation))

| Rule | Passes when |
| --- | --- |
| `required_file` | a file was uploaded in that field |
| `extension,png;jpg` | the uploaded file's name ends with one of the extensions (the type is not checked) |

## Filters

Filters change an input before the rules run. Write them in `$filters`, separated by `|`:
`public $filters = array('title' => 'trim', 'email' => 'trim|lower_case');`. Only filters of declared inputs run.

| Filter | What it does |
| --- | --- |
| `trim` | removes spaces at the start and the end |
| `lower_case`, `upper_case` | changes the case |
| `slug` | makes a URL slug (`Dune Messiah` -> `dune-messiah`) |
| `sanitize_string` | removes script tags and encodes HTML |
| `htmlencode`, `urlencode` | encodes HTML entities, URL characters |
| `sanitize_email`, `sanitize_numbers`, `sanitize_floats` | removes characters that cannot be in an email, a number, a decimal number |
| `whole_number` | turns a number into a whole number |
| `boolean` | `1`, `true`, `yes`, `on` become `true`, anything else `false` |
| `basic_tags` | removes every HTML tag but basic text tags |
| `rmpunctuation` | removes punctuation |
| `noise_words` | removes noise words (and, the, of...) |
| `ms_word_characters` | turns Word's curly quotes, dashes and ellipses into plain characters |
| any PHP function of one value | `base64_encode`, `json_decode`, `strtolower`... (`function_exists()`); an unknown name stops with an error |

## Other ways: validating in a controller

A controller (only for custom HTTP, see [Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers#when))
can validate data itself. The framework's rules work there too. These checks are for the web only: a capability is
the place for rules that every channel must follow.

```php
<?php

use Caligrafy\Controller;

class CheckController extends Controller
{
    public function show()
    {
        $input = array('username' => '  ada lovelace ', 'password' => '123');
        $rules = array('username' => 'required|alpha_numeric', 'password' => 'required|max_len,100|min_len,6');

        $errors = $this->validate($input, $rules);
        // ['username' => 'The Username field may only contain letters and numbers',
        //  'password' => 'The Password field needs to be at least 6 characters']

        $clean = $this->filter($input, array('username' => 'trim|lower_case'));
        // ['username' => 'ada lovelace', 'password' => '123']

        return api(array('errors' => $errors, 'clean' => $clean));
    }
}
```

| Method | Returns |
| --- | --- |
| `$this->validate($data, $rules)` | `true`, or `['field' => 'message']` |
| `$this->filter($data, $filters)` | the filtered data |
| `$this->check($data, $rules)` | as `validate()` |
| `$this->check($data, [], $filters)` | as `filter()` |
| `$this->check($data, $rules, $filters)` | the **filtered data** when valid, the **errors** when not: both are arrays, so `!== true` cannot tell them apart. Filter, then validate |
| `$this->validator->is_valid($data, $rules, $messages)` | `true`, or a list of readable messages (with HTML); `$messages` is `['username' => ['alpha_numeric' => 'Letters and digits only']]` |

Your own rule, for capabilities and controllers alike: write it in a file of its own under `application/`, for
example `application/rules/isbn.php`. The class map runs a file with top-level code at start, for web requests and for
the command line alike (`application/config/config.php` is not read by `php caligrafer.php call`, so a rule added
there is unknown to the command line). Use it as `'isbn' => 'required|isbn'`; the schema adds nothing for it.

```php
<?php
// application/rules/isbn.php
GUMP::add_validator('isbn', function ($field, array $input, array $params = [], $value = null) {
    return (bool) preg_match('/^(97[89])?\d{9}[\dX]$/', str_replace('-', '', (string) $value));
}, 'The {field} field must be an ISBN');
```

## File validation

**In a capability**: the `file` rule, with an allow-list and a size.

```php
public $rules = array('cover' => 'required|file|extension,png;jpg;webp|max_size,2mb');
```

+ Web pages (`Route::form`) and REST (`Route::capability`, `Route::capabilities`) check the uploaded file (one file,
  the allow-list, the size), save it in `public/uploads/` under a random name once the caller is allowed, and give
  `handle()` its URL. A text sent in that field is dropped. When the capability fails, the file is deleted.
+ AI agents (MCP) and the command line send a URL or a path: its extension is checked.
+ Without `extension`, the default list of `uploadFile()` applies; scripts and markup (`php`, `html`, `js`, `svg`...)
  are always refused.
+ GUMP never sees `file`, `extension`, `max_size` or `required_file` in a capability: Caligrafy handles them.
  `required|file` and `required_file` (2.19.1) make the file required on every channel: no file, URL or path answers
  `"cover": "The Cover field needs a file"`, and the JSON Schema lists the input in `required`. Before 2.19.1,
  `required_file` alone left the file optional.
+ The complete example: [Declarative routes: file inputs](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#files).

**In a controller** (other ways): check `$_FILES` with GUMP's file rules, then save with `uploadFile()`:

```php
$result = $this->check($_FILES, array('image' => 'required_file|extension,png;jpg'));
if ($result === true) {
    $url = uploadFile($this->request->files('image'), array('png', 'jpg'));   // '' when refused
}
```

## Common mistakes

+ **No `required`**: an empty input passes every other rule.
+ **`required_file` in a capability before 2.19.1**: the file was optional. Write `required|file`, which works on every
  version.
+ **`word_count,min,3,max,5`**: the commas drop the numbers and every value passes. Write `word_count,min;3;max;5`.
+ **An input `handle()` reads but `$rules` does not list**: it never arrives. Declare it with `''`.
+ **`unique` without the `UNIQUE` index**: two requests at the same instant can both pass.
+ **`date,d/m/Y` and agents**: the schema still says `"format": "date"` (ISO). Describe the format in `$descriptions`.
+ **`check()` with rules and filters, tested with `!== true`**: the filtered data is an array too.
+ **Appending `date('Y')` to a rule**: write `max_numeric,current_year`; the rules are read by the schema as written.

## Related pages

+ [Build once: write a capability](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service)
+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes): forms that show these messages
+ [Views: forms](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#formhelpers): `errors()`, `old()`
+ [GUMP](https://github.com/Wixel/GUMP)

>[Next Section: Learn about Views](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views)
