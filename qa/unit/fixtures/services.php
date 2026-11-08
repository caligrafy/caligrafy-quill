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

// Declarative routes (2.19.0): Route::form, Route::webhook, Route::download and the file rule

#[Capability(name: 'qa_save_book', description: 'Saves a book with an optional cover', scope: 'books:write')]
class QaSaveBook extends Service
{
    public $rules = array('id' => 'integer', 'title' => 'required|max_len,20', 'cover' => 'file|extension,png;jpg|max_size,1kb', 'password' => '');

    public function handle(array $input, Context $context)
    {
        if ($input['title'] === 'taken') {
            throw ServiceError::conflict('That title is taken');
        }
        if ($input['title'] === 'secret') {
            throw ServiceError::forbidden();
        }
        if ($input['title'] === 'missing') {
            throw ServiceError::notFound('No such shelf');
        }
        if ($input['title'] === 'crash') {
            throw new RuntimeException('crash');
        }
        return array('id' => (int) ($input['id'] ?? 7), 'title' => $input['title'], 'cover' => $input['cover'] ?? null);
    }
}

#[Capability(name: 'qa_record_event', description: 'Records a webhook event', scope: 'hooks:write')]
class QaRecordEvent extends Service
{
    public static $seen = array();
    public $rules = array('event' => 'required');

    public function handle(array $input, Context $context)
    {
        $type = $input['event']['type'] ?? '';
        if ($type === 'boom') {
            throw new RuntimeException('the database is down');
        }
        if ($type === 'duplicate') {
            throw ServiceError::conflict('Already recorded');
        }
        self::$seen[] = array('event' => $input['event'], 'actor' => $context->actorType(), 'id' => $context->actorId());
        return array('recorded' => true);
    }
}

#[Capability(name: 'qa_export_rows', description: 'Rows to export', readOnly: true, public: true)]
class QaExportRows extends Service
{
    public $rules = array('shelf' => '');

    public function handle(array $input, Context $context)
    {
        return array(array('id' => 1, 'title' => 'Dune', 'shelf' => $input['shelf'] ?? null));
    }
}

// a sign-up with an email address (Route::auth: every declared input, the rules exists and unique), on an SQLite users table
#[Capability(name: 'qa_register_member', description: 'Creates an account with an email address', scope: 'users:write', public: true)]
class QaRegisterMember extends Service
{
    public $rules = array('username' => 'required|alpha_numeric|max_len,100', 'email' => 'required|valid_email|unique,users.email',
        'passcode' => 'required|min_len,8', 'confirmpasscode' => 'required|equalsfield,passcode', 'team_id' => 'exists,teams.id');

    public function handle(array $input, Context $context)
    {
        $id = Caligrafy\Database::table('users')->insert(array('username' => $input['username'], 'email' => $input['email'],
            'passcode' => password_hash($input['passcode'], PASSWORD_BCRYPT, array('cost' => 4)), 'permissions' => 0));
        return array('id' => (int) $id, 'username' => $input['username'], 'email' => $input['email'], 'team_id' => $input['team_id'] ?? null);
    }
}

// an authentication model that needs no database (Route::auth tests)
class QaAuthUser extends Caligrafy\Model
{
    public $id;
    public $username;
    public $email;
    public $passcode;
    public $permissions;

    public function verifyPassword($password)
    {
        return password_verify((string) $password, (string) $this->passcode);
    }
}
