<?php
// The REST example of the wiki (9. Helpers, Forms & REST API), QA fixture

use Caligrafy\Controller;

class BookController extends Controller {
    public function readAll()
    {
       $this->associate('Book', 'books'); // define the context
       return api($this->all()); // or you can use view instead of api with 'null' template
    }

    public function create()
    {
      $this->associate('Book', 'books'); // define the context
      $parameters = $this->request->parameters(); // get all the parameters posted
      $book = new Book();
      $book->title = isset($parameters['title'])? $parameters['title'] : '';
      $book->author = isset($parameters['author'])? $parameters['author'] : '';
      $book->publisher_id = isset($parameters['publisher_id'])? $parameters['publisher_id'] : null;
      return api($this->save($book));
    }

    public function update()
    {
      $this->associate('Book', 'books'); // define the context
      $book = $this->find(); // finds the book from id specified in the URI

      $parameters = $this->request->parameters(); // get all the parameters posted
      $book->title = isset($parameters['title'])? $parameters['title'] : $book->title; // take the title only if it is set in the post
      $book->author = isset($parameters['author'])? $parameters['author']: $book->author;
      return api($this->save($book));
    }

    public function remove()
    {
      $this->associate('Book', 'books'); // define the context
      return api($this->delete());
    }

    public function publisher()
    {
      $this->associate('Book', 'books');
      return api($this->find()->publisher());
    }
}
