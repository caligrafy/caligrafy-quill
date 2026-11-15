The [query builder](https://github.com/caligrafy/caligrafy-quill/wiki/Queries) writes rows too: `insert()` one row or
many, `update()`, `delete()`, `increment()` and `decrement()` the rows a `where()` names, every value bound. A change
without a condition is refused, so a missing `where()` never empties a table. `Database::transaction()` saves several
changes together, or none of them. Since 2.19.0.

```php
$id = Database::table('tags')->insert(['name' => 'poetry']);                    // the new id, as a string: "4"
Database::table('tags')->where('id', $id)->update(['name' => 'poems']);         // 1: the rows changed
```

<br/>

## With Claude

```text
/caligrafy-quill:new-capability lend_book books:write: lends a book to a borrower until a date; the open loan of the book, if any, is closed first
```

Claude writes the change with the query builder, in `Database::transaction()` when it takes several statements.

**Check** before you say OK:
+ every `update()` and `delete()` has a `where()`;
+ the changes that belong together are in one `Database::transaction()`;
+ no table change (`CREATE`, `ALTER`) runs inside it: those go in a [migration](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations).

<br/>

## By hand

1. **Write the rows** in a capability (not `readOnly`):
    ```php
    $id = Database::table('tags')->insert(['name' => 'poetry']);
    Database::table('tags')->insert([['name' => 'essay'], ['name' => 'travel']]);   // several rows, one statement

    Database::table('tags')->where('id', $id)->update(['name' => 'poems']);         // 1: the rows changed
    Book::where('id', 1)->increment('published_year');                              // also decrement('stock', 2)
    Database::table('tags')->whereIn('name', ['essay', 'travel', 'poems'])->delete(); // 3: the rows deleted
    ```
2. **Several changes together**:
    ```php
    $id = Database::transaction(function () use ($input) {
        // the book changes hands: close its open loan, then open the new one
        Database::table('loans')->where('book_id', $input['book_id'])->whereNull('returned_at')->update(['returned_at' => date('Y-m-d H:i:s')]);
        return Database::table('loans')->insert(['book_id' => $input['book_id'], 'borrower' => $input['borrower'], 'due_on' => $input['due_on']]);
    });
    ```
   All of it is saved, or none of it: an exception rolls everything back and is thrown again; otherwise `transaction()`
   returns what the function returns.

<br/>

## <a name="write"></a>Reference: writing rows

| Call | Does | Returns |
|---|---|---|
| [`insert($row)`, `insert([$row, $row])`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-insert) | one `INSERT` for every row; the rows need the same columns | the last insert id |
| [`update($values)`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-update) | `UPDATE ... SET` the columns given, on the rows of the `where()` | the rows changed |
| [`delete()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-delete) | `DELETE` the rows of the `where()` | the rows deleted |
| [`increment($column, $amount = 1, $extra = [])`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query#query-increment), `decrement(...)` | `column = column + ?` (or `-`), and the `$extra` columns at the same time | the rows changed |

+ Arrays and objects are written as JSON, booleans as `0`/`1`.
+ `update()`, `delete()`, `increment()` and `decrement()` need a `where...()`. Without one they throw `LogicException`
  and run nothing:
  `delete() needs a where(): ->where('id', $id)->delete(...). For every row of the table, write the SQL with Database::execute()`.
+ `increment('stock', 5, ['restocked_at' => date('Y-m-d H:i:s')])` sets other columns at the same time; the amount must
  be a number.
+ `Book::insert([...])` writes on the model's table. New code writes with `Book::insert()`,
  `Book::where('id', $id)->update()` and `->delete()`; the model's own methods of before 2.19
  (`(new Book('books'))->create($input)`, `->update($changes, $id)`, `->delete($id)`) keep working.

<br/>

## <a name="transactions"></a>Reference: transactions

[`Database::transaction(callable $callback)`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Data#database-transaction):

+ committed when the function returns, rolled back when it throws (the exception is thrown again);
+ returns what the function returns;
+ a transaction inside another one (or inside a test, which runs in a transaction) just runs in the outer one:
  catching its exception inside the outer one does not undo its rows;
+ MySQL saves a `CREATE` or an `ALTER` at once, which ends a transaction early: keep table changes in migrations.

For what the builder does not write (joins with `GROUP BY`, subqueries, a change to every row), keep SQL with `?`
placeholders: `Database::execute($sql, $values)`
([when to keep SQL](https://github.com/caligrafy/caligrafy-quill/wiki/Queries#sql)).

<br/>

## Common mistakes

+ **`update()` without `where()`** (G125): refused. `whereNotIn('id', [])` and `search($columns, '')` add no condition,
  so they do not count as one.
+ **Transactions do not nest** (G128): an inner exception you catch inside the outer callback does not undo the inner
  writes.
+ **`Model::belongsToMany()` inside a transaction**: its `CREATE TABLE IF NOT EXISTS` commits at once on MySQL (G128).
+ **`Database::execute()` with values pasted into the SQL** (G2): always `?` placeholders.

<br/>

## Related pages

+ [Queries](https://github.com/caligrafy/caligrafy-quill/wiki/Queries#write)
+ [Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations)
+ [Filters and order](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Filters-and-Order)
+ [API: Caligrafy\Query](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query) · [Caligrafy\Database](https://github.com/caligrafy/caligrafy-quill/wiki/API-Data#database)
