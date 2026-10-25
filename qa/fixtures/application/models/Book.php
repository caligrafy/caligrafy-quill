<?php
// Wiki example model (4. Models / 5. Relationships), QA fixture

use Caligrafy\Model;

class Book extends Model {
    public $id;
    public $title;
    public $author;
    public $publisher_id;
    public $created_at;

    public function publisher()
    {
        return $this->hasOne('Publisher', 'publishers');
    }
}

class Publisher extends Model {
    public $id;
    public $publishername;
    public $created_at;

    public function books()
    {
        return $this->hasMany('Book', 'books');
    }
}
