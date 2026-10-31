<?php

use Caligrafy\Model;

// One Task object = one row of the tasks table
class Task extends Model
{
    public $id;
    public $title;
    public $notes;
    public $done;
    public $created_at;
    public $modified_at;
}
