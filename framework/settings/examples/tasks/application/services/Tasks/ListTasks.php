<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// EXAMPLE: public: true lets anyone use the tasks without signing in, so the example works at once.
// Before you deploy, remove public: true here and in the other task capabilities (see "Your first capability").
#[Capability(
    name: 'list_tasks',
    description: 'Lists the tasks, newest first, with their id, title, notes and done flag. Optionally only open or only done tasks.',
    scope: 'tasks:read',
    readOnly: true,
    public: true
)]
class ListTasks extends Service
{
    public $rules = array('status' => 'contains_list,all;open;done');
    public $filters = array('status' => 'trim');
    public $descriptions = array('status' => 'Which tasks to list: all (default), open or done');

    public function handle(array $input, Context $context)
    {
        $args = array('order' => 'ORDER BY created_at DESC, id DESC');
        $status = $input['status'] ?? 'all';
        if ($status === 'open' || $status === 'done') {
            $args['condition'] = 'WHERE done = ?';
            $args['bindings'] = array($status === 'done' ? 1 : 0);
        }
        return (new Task('tasks'))->all($args);
    }
}
