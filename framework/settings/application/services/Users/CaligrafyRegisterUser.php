<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// Sign-up: the sign-up page of Route::auth (or AuthController@register) calls it, then the person signs in, or is signed
// in at once with Route::auth's signInAfterRegister. Signing in itself is the web session's, which capabilities never touch.
// Web pages and the command line only: over REST or MCP anyone could create accounts in bulk.
#[Capability(
    name: 'register_user',
    description: 'Creates an account that can sign in, with permissions 0. Used by the sign-up page.',
    scope: 'users:write',
    public: true
)]
class CaligrafyRegisterUser extends Service
{
    public $rules = array('username' => 'required|alpha_numeric|max_len,100', 'passcode' => 'required|min_len,8|max_len,72');
    public $descriptions = array('username' => 'Letters and digits, up to 100', 'passcode' => 'The password: 8 to 72 characters');

    public function handle(array $input, Context $context)
    {
        if (!in_array($context->channel, array('web', 'cli'), true)) {
            throw ServiceError::forbidden('Sign up on the sign-up page');
        }
        if (User::where('username', $input['username'])->exists()) {
            throw ServiceError::conflict('The username already exists in the system');
        }
        try {
            $user = User::find(User::insert(array('username' => $input['username'], 'passcode' => User::hashPassword($input['passcode']), 'permissions' => 0)));
        } catch (\Throwable $e) {
            if (CaligrafyUsers::isDuplicate($e)) {
                throw ServiceError::conflict('The username already exists in the system');
            }
            throw $e;
        }
        return CaligrafyUsers::profile($user);
    }
}
