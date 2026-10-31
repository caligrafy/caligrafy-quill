<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

#[Capability(
    name: 'create_task',
    description: 'Adds a task and returns it with its id. The title is required; notes are optional.',
    scope: 'tasks:write',
    public: true
)]
class CreateTask extends Service
{
    public $rules = array('title' => 'required|max_len,200', 'notes' => 'max_len,1000');
    public $filters = array('title' => 'trim', 'notes' => 'trim');
    public $descriptions = array('title' => 'What has to be done', 'notes' => 'Optional details');

    public function handle(array $input, Context $context)
    {
        return (new Task('tasks'))->create(array('title' => $input['title'], 'notes' => $input['notes'] ?? ''));
    }
}
