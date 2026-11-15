A **capability** is a class of `application/services/` that extends `Caligrafy\Service` and carries the attribute
`#[Capability(...)]`. The attribute says what the capability is called, what it does, who may call it and how the
channels treat it. This page lists every option; each one has a page of its own. Since 2.1.0 (`webOnly` 2.21.0,
`async` 2.23.0).

<br/>

## With Claude

```text
/caligrafy-quill:new-capability delete_book books:write destructive: only administrators (level 1)
```

Claude runs `php caligrafer.php service <Folder>/<Name> --scope <scope>` and writes the attribute: a `description`
written for an AI agent, the scope, and `readOnly`, `destructive`, `level` or `public` from what you asked.

**Check** before you say OK: `php caligrafer.php capabilities` lists it with the scope (or `public`) and the flag you
expect (`read`, `writes`, `destroys`).

<br/>

## By hand

`php caligrafer.php service Bookshelf/DeleteBook --scope books:write` writes the class with its name in snake_case
(`DeleteBook` becomes `delete_book`). Complete the attribute:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

#[Capability(
    name: 'delete_book',
    description: 'Deletes a book of the catalog for good, with its loans and its tags. Returns the id of the deleted book.',
    scope: 'books:write',
    level: 1,
    destructive: true
)]
class DeleteBook extends Service
{
    public $rules = array('id' => 'required|integer');

    public $descriptions = array('id' => 'The id of the book to delete');

    public function handle(array $input, Context $context)
    {
        if (Book::where('id', $input['id'])->delete() === 0) {
            throw ServiceError::notFound('There is no book #' . $input['id']);
        }
        return array('deleted' => (int) $input['id']);
    }
}
```

<br/>

## <a name="options"></a>Every option

The arguments of the attribute's constructor, in their order (`framework/core/Capability.php`):

| Option | Type, default | What it does | Learn more |
| --- | --- | --- | --- |
| `name` | `string`, required | snake_case: a letter, then letters, digits and `_`, 64 characters at most; unique in the application. How REST, MCP, the JS client, the command line and the routes name it. Any other name throws `InvalidArgumentException` | [Calling a capability from code](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Call-a-Capability-from-Code) |
| `description` | `string`, `''` | one or two sentences: what it does and what it returns. Agents read it as the tool description | [MCP for AI agents](https://github.com/caligrafy/caligrafy-quill/wiki/MCP-for-AI-Agents) |
| `scope` | `string`, `''` | the token scope it needs, `resource:action` (`books:write`). `''`: any token | [Scopes and access](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Scopes-and-Access) |
| `level` | `int`, `0` | the minimum value of the users' `permissions` column. `0`: anyone signed in | [Scopes and access](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Scopes-and-Access#level) |
| `readOnly` | `bool`, `false` | it changes nothing: not audited when it succeeds, can be page data and a download, `readOnlyHint` for agents | [Read-only and destructive](https://github.com/caligrafy/caligrafy-quill/wiki/Read-Only-and-Destructive-Capabilities) |
| `destructive` | `bool`, `false` | it deletes or overwrites data: `destructiveHint`, agents ask the user first | [Read-only and destructive](https://github.com/caligrafy/caligrafy-quill/wiki/Read-Only-and-Destructive-Capabilities#destructive) |
| `public` | `bool`, `false` | anyone may call it, without signing in or a token | [Scopes and access](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Scopes-and-Access#public) |
| `mcp` | `bool`, `true` | `false` keeps it out of the MCP tools (one only a browser can use, such as a push subscription). REST still reaches it | [MCP for AI agents](https://github.com/caligrafy/caligrafy-quill/wiki/MCP-for-AI-Agents#mcpfalse) |
| `webOnly` | `bool`, `false` | `true` (2.21.0): only `Route::action()` reaches it; it may read the session and sign people in | [Web-only capabilities](https://github.com/caligrafy/caligrafy-quill/wiki/Web-Only-Capabilities) |
| `async` | `bool`, `false` | `true` (2.23.0): a slow capability; Snap runs it in the background as a Track card: Task. Other channels call it as before | [Async capabilities](https://github.com/caligrafy/caligrafy-quill/wiki/Async-Capabilities) |

The class itself declares the inputs (`$rules`, `$filters`, `$descriptions`) and `handle()`:
[Capability inputs](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Inputs). Its constructor gets what it
uses from the [container](https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection).

`php caligrafer.php service <Folder>/<Name> [--scope <scope>] [--read-only]` writes `scope` and `readOnly`. There is no
flag for `destructive`, `level`, `public`, `mcp`, `webOnly` or `async`: write them in the attribute.

<br/>

## <a name="registry"></a>How the attribute is found

`Caligrafy\Capabilities::all()` builds the registry once per request: it loads the files of `application/services/`
that mention `#[Capability]` (with the class map, 2.18.0) and the framework's own capabilities of `framework/services/`,
then keeps every class that extends `Service`, is not abstract and carries the attribute. Two classes that declare the
same name stop the registry with a `LogicException` naming both. A class outside `application/services/` (a package, a
test) is added with `Capabilities::register(MyService::class)`.

Reference: [`Capability`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#capability),
[`Capabilities::all()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#capabilities-all),
[`Capabilities::register()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities#capabilities-register).

<br/>

## Common mistakes

+ **`service` without `--scope`** (G76): it writes `scope: ''`, which any token may call. Give a scope, or write it in the
  attribute right away.
+ **`webOnly: true` on a feature agents or programs need** (G143): they get "There is no capability". Keep the work in a
  plain capability and let the web-only one call it with `$this->capability()`.

<br/>

## Related pages

+ [Services, capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP): the whole chapter
+ [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability): the short tour
+ [Capability inputs](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Inputs) · [Refusing a call](https://github.com/caligrafy/caligrafy-quill/wiki/Refusing-a-Call-ServiceError) · [The context](https://github.com/caligrafy/caligrafy-quill/wiki/The-Context)
+ [API reference: services and capabilities](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities)
