<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Service;
use Caligrafy\ServiceError;
use Caligrafy\Tokens;

#[Capability(
    name: 'delete_user',
    description: 'Deletes a person who can sign in, for good. An administrator cannot delete their own account.',
    scope: 'users:write',
    level: CaligrafyUsers::ADMIN_LEVEL,
    destructive: true
)]
class CaligrafyDeleteUser extends Service
{
    public $rules = array('id' => 'required|integer');
    public $descriptions = array('id' => 'The id of the user to delete');

    public function handle(array $input, Context $context)
    {
        CaligrafyUsers::requireAdmin($context);
        $user = User::find($input['id']);
        if (!$user) {
            throw ServiceError::notFound("No user #{$input['id']}");
        }
        if ($context->user && (int) $context->user->id === (int) $user->id) {
            throw ServiceError::forbidden('You cannot delete your own account');
        }
        if (!CaligrafyUsers::mayManage($context, $user->permissions ?? 0)) {
            throw ServiceError::forbidden('You cannot delete someone above your own level');
        }
        User::where('id', $user->id)->delete();
        if (Tokens::installed()) {
            // their tokens stop working with the account
            Database::table(Tokens::TABLE)->where('user_id', $user->id)->whereNull('revoked_at')->update(array('revoked_at' => date('Y-m-d H:i:s')));
        }
        return array('id' => (int) $user->id, 'deleted' => true);
    }
}
