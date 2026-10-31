<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

#[Capability(
    name: 'get_task',
    description: 'Returns one task by its id, with its title, notes and done flag.',
    scope: 'tasks:read',
    readOnly: true,
    public: true
)]
class GetTask extends Service
{
    public $rules = array('id' => 'required|integer');
    public $descriptions = array('id' => 'The id of the task');

    public function handle(array $input, Context $context)
    {
        $task = (new Task('tasks'))->find($input['id']);
        if (!$task) {
            throw ServiceError::notFound("No task #{$input['id']}");
        }
        return $task;
    }
}
