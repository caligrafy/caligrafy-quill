<?php
/**
 * Bookshelf capabilities, QA fixture of the build-once proof of concept: the same five capabilities are used by a web
 * page (BookPageController), REST (Route::capability), the generic endpoint (Route::capabilities) and MCP (Route::mcp)
 */

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

#[Capability(name: 'list_books', description: 'Lists the books of the catalog, oldest first, with their title, author and publisher id.', scope: 'books:read', readOnly: true)]
class ListBooks extends Service
{
    public function handle(array $input, Context $context)
    {
        return (new Book('books'))->all(array('order' => 'ORDER BY id'));
    }
}

#[Capability(name: 'get_book', description: 'Returns one book by its id.', scope: 'books:read', readOnly: true)]
class GetBook extends Service
{
    public $rules = array('id' => 'required|integer');
    public $descriptions = array('id' => 'The id of the book');

    public function handle(array $input, Context $context)
    {
        return (new Book('books'))->find($input['id']) ?? throw ServiceError::notFound("No book #{$input['id']}");
    }
}

#[Capability(name: 'create_book', description: 'Adds a book to the catalog and returns it with its id.', scope: 'books:write')]
class CreateBook extends Service
{
    public $rules = array('title' => BookRules::TITLE, 'author' => 'required|max_len,255', 'publisher_id' => 'required|integer');
    public $filters = array('title' => 'trim', 'author' => 'trim');
    public $descriptions = array('title' => 'The title', 'author' => 'The author', 'publisher_id' => 'The id of the publisher (1 = Penguin)');

    public function handle(array $input, Context $context)
    {
        if (!(new Publisher('publishers'))->find($input['publisher_id'])) {
            throw ServiceError::invalid(array('publisher_id' => 'There is no such publisher'));
        }
        return (new Book('books'))->create($input);
    }
}

#[Capability(name: 'update_book', description: 'Changes the title or the author of a book and returns it.', scope: 'books:write')]
class UpdateBook extends Service
{
    public $rules = array('id' => 'required|integer', 'title' => 'max_len,255', 'author' => 'max_len,255');
    public $filters = array('title' => 'trim', 'author' => 'trim');

    public function handle(array $input, Context $context)
    {
        $books = new Book('books');
        if (!$books->find($input['id'])) {
            throw ServiceError::notFound("No book #{$input['id']}");
        }
        $changes = array_filter(array_intersect_key($input, array('title' => 1, 'author' => 1)), 'strlen');
        return $changes ? $books->update($changes, $input['id']) : $books->find($input['id']);
    }
}

#[Capability(name: 'delete_book', description: 'Deletes a book. Needs permission level 1.', scope: 'books:write', level: 1, destructive: true)]
class DeleteBook extends Service
{
    public $rules = array('id' => 'required|integer');

    public function handle(array $input, Context $context)
    {
        $books = new Book('books');
        if (!$books->find($input['id'])) {
            throw ServiceError::notFound("No book #{$input['id']}");
        }
        $books->delete($input['id']);
        return array('deleted' => (int) $input['id']);
    }
}
