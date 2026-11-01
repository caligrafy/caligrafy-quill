<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Service;
use Caligrafy\ServiceError;

#[Capability(
    name: 'update_user',
    description: 'Changes the username, the password or the permissions of a person who can sign in (0 for everyone, 1 or more for administrators). Only the inputs given change. Returns the user, never the password.',
    scope: 'users:write',
    level: CaligrafyUsers::ADMIN_LEVEL
)]
class UpdateUser extends Service
{
    public $rules = array('id' => 'required|integer', 'username' => 'alpha_numeric|max_len,100', 'passcode' => 'min_len,8|max_len,72',
        'permissions' => 'integer|min_numeric,0|max_numeric,255');
    public $filters = array('username' => 'trim');
    public $descriptions = array('id' => 'The id of the user', 'username' => 'A new username: letters and digits',
        'passcode' => 'A new password: 8 to 72 characters', 'permissions' => '0 for everyone, 1 or more for administrators');

    public function handle(array $input, Context $context)
    {
        $users = new User('users');
        $user = $users->find($input['id']);
        if (!$user) {
            throw ServiceError::notFound("No user #{$input['id']}");
        }
        $changes = array();
        if (isset($input['username']) && $input['username'] !== '' && $input['username'] !== $user->username) {
            $taken = Database::toArray(Database::execute('SELECT id FROM users WHERE username = ? AND id <> ?', array($input['username'], $user->id)));
            if ($taken) {
                throw ServiceError::invalid(array('username' => 'The username already exists in the system'));
            }
            $changes['username'] = $input['username'];
        }
        if (isset($input['passcode']) && $input['passcode'] !== '') {
            $changes['passcode'] = User::hashPassword($input['passcode']);
        }
        if (isset($input['permissions']) && $input['permissions'] !== '') {
            $changes['permissions'] = (int) $input['permissions'];
        }
        if (!$changes) {
            return CaligrafyUsers::profile($user);
        }
        $changes['modified_at'] = date('Y-m-d H:i:s');
        return CaligrafyUsers::profile($users->update($changes, $user->id));
    }
}
