<?php
/**
 * Services used by the unit tests of the capability registry and the MCP server (no database)
 */

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

#[Capability(name: 'qa_echo_note', description: 'Returns the note it was given', scope: 'notes:write')]
class QaEchoNote extends Service
{
    public $rules = array('title' => 'required|max_len,20', 'stars' => 'integer|min_numeric,1|max_numeric,5', 'tag' => 'contains_list,red;blue', 'note' => '');
    public $filters = array('title' => 'trim');
    public $descriptions = array('title' => 'The title');

    public function handle(array $input, Context $context)
    {
        if ($input['title'] === 'taken') {
            throw ServiceError::conflict('That title is taken');
        }
        return array('title' => $input['title'], 'stars' => $input['stars'] ?? null, 'actor' => $context->actorType(), 'channel' => $context->channel);
    }
}

#[Capability(name: 'qa_list_notes', description: 'Lists the notes', readOnly: true, public: true)]
class QaListNotes extends Service
{
    public function handle(array $input, Context $context)
    {
        return array((object) array('title' => 'one'), (object) array('title' => 'two'));
    }
}

#[Capability(name: 'qa_purge_notes', description: 'Deletes every note', scope: 'notes:write', level: 2, destructive: true)]
class QaPurgeNotes extends Service
{
    public function handle(array $input, Context $context)
    {
        return array('deleted' => 3);
    }
}

// a service without the attribute is not a capability
class QaHelperService extends Service
{
    public function handle(array $input, Context $context)
    {
        return null;
    }
}
