<?php
// Web page channel of the Bookshelf proof of concept, QA fixture: a form post calls the same capability as REST and MCP

use Caligrafy\Controller;
use Caligrafy\ServiceError;

class BookPageController extends Controller
{
    public function store()
    {
        try {
            $book = $this->call('create_book');
            echo "created book #{$book['id']}: {$book['title']}";
        } catch (ServiceError $e) {
            http_response_code($e->status());
            echo "not saved ({$e->kind}): " . implode('; ', array_map(fn ($f, $m) => "$f: $m", array_keys($e->fields), $e->fields));
        }
    }
}
