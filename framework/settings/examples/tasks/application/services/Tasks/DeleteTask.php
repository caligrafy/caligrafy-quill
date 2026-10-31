<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

#[Capability(
    name: 'delete_task',
    description: 'Deletes a task by its id.',
    scope: 'tasks:write',
    destructive: true,
    public: true
)]
class DeleteTask extends Service
{
    public $rules = array('id' => 'required|integer');
    public $descriptions = array('id' => 'The id of the task');

    public function handle(array $input, Context $context)
    {
        $tasks = new Task('tasks');
        if (!$tasks->find($input['id'])) {
            throw ServiceError::notFound("No task #{$input['id']}");
        }
        $tasks->delete($input['id']);
        return array('deleted' => (int) $input['id']);
    }
}
