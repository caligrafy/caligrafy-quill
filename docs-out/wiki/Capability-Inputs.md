A capability declares its inputs with three arrays of its class: `$rules` (what is accepted), `$filters` (how it is
cleaned) and `$descriptions` (what each input is). The same three arrays validate every call, whatever the channel,
and become the **JSON Schema** that AI agents, the JS client and `GET /capabilities` see. Since 2.1.0.

<br/>

## With Claude

```text
/caligrafy-quill:new-capability create_book books:write: add a book (title up to 200 characters, published year from 1450 to this year, optional summary, an existing author)
```

Claude writes a rule for every input, a `trim` filter for the text ones and a description for each, written for an
agent ("The id of its author (list_authors)").

**Check** before you say OK:
+ every input `handle()` reads is in `$rules` (with `''` when it has no rule);
+ `php caligrafer.php capabilities --json` shows the schema you expect;
+ `php caligrafer.php call <name>` with a wrong value answers a message for that field.

<br/>

## By hand

```php
#[Capability(name: 'create_book', description: 'Adds a book to the Bookshelf catalog and returns it with its id and its author.', scope: 'books:write')]
class CreateBook extends Service
{
    public $rules = array(
        'title' => 'required|max_len,200',
        'published_year' => 'required|integer|min_numeric,1450|max_numeric,current_year',
        'summary' => 'max_len,2000',
        'author_id' => 'required|integer|exists,authors.id'
    );

    public $filters = array(
        'title' => 'trim',
        'summary' => 'trim'
    );

    public $descriptions = array(
        'title' => 'The title of the book',
        'published_year' => 'The year it was first published, for example 1969 (not after this year)',
        'summary' => 'Optional: one or two sentences about the book',
        'author_id' => 'The id of its author (list_authors)'
    );

    public function handle(array $input, Context $context)
    {
        $id = Book::insert($input);
        return Book::with('author')->find($id);
    }
}
```

`php caligrafer.php call create_book title=Dune` answers a message per field:
```json
{
    "error": {
        "code": "invalid",
        "message": "Some of the inputs are invalid",
        "fields": {
            "published_year": "The Published Year field is required",
            "author_id": "The Author Id field is required"
        }
    }
}
```
REST answers the same body with `422`, MCP as a tool result with `"isError": true`, a form goes back with
`errors('published_year')`.

<br/>

## <a name="validate"></a>What happens to the inputs

`Service::validate()` runs before `handle()`, on every channel:

1. **Keep** only the inputs named in `$rules`. Any other key the caller sends is dropped: an input without a rule is
   listed with `''`.
2. **Files** (the `file` rule, 2.19.0): web pages and REST save the uploaded file and give the capability its public
   URL; MCP and the command line send a URL or a path, whose extension is checked. `required_file` (or
   `required|file`) with nothing sent is refused: "The Cover field needs a file".
3. **Numbers** (2.21.8): an input whose rule has `integer`, `numeric`, `float`, `min_numeric` or `max_numeric` never takes a
   list or `true`/`false`: "The Year field must be a number".
4. **Filter**, then **validate** with the [validation rules](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation)
   (GUMP). `$filters` apply only to declared inputs.
5. Anything wrong throws `ServiceError::invalid([field => message])`. Otherwise `handle()` gets the clean inputs.

`$rules` with `unique` and `exists` read the database (`exists,authors.id`); `current_year` in `min_numeric` and
`max_numeric` is the year of today.

<br/>

## <a name="schema"></a>From the rules to the JSON Schema

`Capabilities::schema('create_book')` (and `schemaFromRules($rules, $descriptions)`) gives an object schema with
`"additionalProperties": false`:

| Rule | Schema |
| --- | --- |
| `required` | listed in `required` |
| `integer` / `numeric`, `float` / `boolean` | `"type": "integer"` / `"number"` / `"boolean"` (otherwise `"string"`) |
| `valid_email`, `valid_url`, `uuid`, `date` | `"format": "email"`, `"uri"`, `"uuid"`, `"date"` |
| `max_len,N`, `min_len,N`, `exact_len,N` | `maxLength`, `minLength` (both for `exact_len`) |
| `min_numeric,N`, `max_numeric,N` | `minimum`, `maximum` (`current_year` is this year) |
| `contains_list,a;b` | `"enum": ["a", "b"]` |
| `file`, `required_file` | `"description": "The URL or the path of a file"` (`required_file` also in `required`) |
| `$descriptions[input]` | `"description"`, over the one above |

A string keeps only its `format` and lengths, a number only its `minimum` and `maximum`. A rule argument that is not a
number (`max_len` with nothing after it) is left out of the schema. The `create_book` above becomes:

```json
{"type": "object", "properties": {
    "title": {"type": "string", "maxLength": 200, "description": "The title of the book"},
    "published_year": {"type": "integer", "minimum": 1450, "maximum": 2026, "description": "The year it was first published, for example 1969 (not after this year)"},
    "summary": {"type": "string", "maxLength": 2000, "description": "Optional: one or two sentences about the book"},
    "author_id": {"type": "integer", "description": "The id of its author (list_authors)"}},
 "additionalProperties": false, "required": ["title", "published_year", "author_id"]}
```

Reference: [`Service::validate()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#service-validate),
[`Capabilities::schema()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#capabilities-schema),
[`Capabilities::schemaFromRules()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#capabilities-schemafromrules),
[`Uploads`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#uploads).

<br/>

## Common mistakes

+ **An input with no rule** (G52): it never reaches `handle()`. List it in `$rules`, with `''` when it has no rule.
+ **Forgetting `php caligrafer.php client` after changing the rules**: the Vue app's functions keep the old input types.

<br/>

## Related pages

+ [Validation rules](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation): every rule and filter
+ [Declarative routes: file inputs](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#files)
+ [The capability attribute](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Attribute) · [Refusing a call](https://github.com/caligrafy/caligrafy-quill/wiki/Refusing-a-Call-ServiceError)
+ [MCP for AI agents](https://github.com/caligrafy/caligrafy-quill/wiki/MCP-for-AI-Agents): where agents read the schema
