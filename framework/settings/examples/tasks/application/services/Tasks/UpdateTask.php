<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

#[Capability(
    name: 'update_task',
    description: 'Renames a task, changes its notes, or marks it done or open. Returns the task.',
    scope: 'tasks:write',
    public: true
)]
class UpdateTask extends Service
{
    public $rules = array('id' => 'required|integer', 'title' => 'max_len,200', 'notes' => 'max_len,1000', 'done' => 'boolean');
    public $filters = array('title' => 'trim', 'notes' => 'trim');
    public $descriptions = array('id' => 'The id of the task', 'title' => 'A new title', 'notes' => 'New notes', 'done' => 'true when the task is done');

    public function handle(array $input, Context $context)
    {
        $tasks = new Task('tasks');
        if (!$tasks->find($input['id'])) {
            throw ServiceError::notFound("No task #{$input['id']}");
        }
        $changes = array();
        if (isset($input['title'])) {
            if (trim((string) $input['title']) === '') {
                throw ServiceError::invalid(array('title' => 'The Title field is required'));
            }
            $changes['title'] = $input['title'];
        }
        if (isset($input['notes'])) {
            $changes['notes'] = $input['notes'];
        }
        if (isset($input['done'])) {
            $changes['done'] = filter_var($input['done'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }
        return $changes ? $tasks->update($changes, $input['id']) : $tasks->find($input['id']);
    }
}
