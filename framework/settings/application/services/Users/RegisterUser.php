<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// Sign-up: the sign-up page (AuthController@register) calls it, then sends the person to sign in. Signing in itself
// stays in the controller: it is the web session, which capabilities never touch.
// Web pages and the command line only: over REST or MCP anyone could create accounts in bulk.
#[Capability(
    name: 'register_user',
    description: 'Creates an account that can sign in, with permissions 0. Used by the sign-up page.',
    scope: 'users:write',
    public: true
)]
class RegisterUser extends Service
{
    public $rules = array('username' => 'required|alpha_numeric|max_len,100', 'passcode' => 'required|min_len,8|max_len,72');
    public $filters = array('username' => 'trim');
    public $descriptions = array('username' => 'Letters and digits, up to 100', 'passcode' => 'The password: 8 to 72 characters');

    public function handle(array $input, Context $context)
    {
        if (!in_array($context->channel, array('web', 'cli'), true)) {
            throw ServiceError::forbidden('Sign up on the sign-up page');
        }
        if (Database::toArray(Database::execute('SELECT id FROM users WHERE username = ?', array($input['username'])))) {
            throw ServiceError::conflict('The username already exists in the system');
        }
        $users = new User('users');
        $user = $users->create(array('username' => $input['username'], 'passcode' => User::hashPassword($input['passcode']), 'permissions' => 0));
        return CaligrafyUsers::profile($user);
    }
}
