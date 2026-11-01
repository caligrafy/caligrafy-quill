<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

#[Capability(
    name: 'delete_user',
    description: 'Deletes a person who can sign in, for good. An administrator cannot delete their own account.',
    scope: 'users:write',
    level: CaligrafyUsers::ADMIN_LEVEL,
    destructive: true
)]
class DeleteUser extends Service
{
    public $rules = array('id' => 'required|integer');
    public $descriptions = array('id' => 'The id of the user to delete');

    public function handle(array $input, Context $context)
    {
        $user = (new User('users'))->find($input['id']);
        if (!$user) {
            throw ServiceError::notFound("No user #{$input['id']}");
        }
        if ($context->user && (int) $context->user->id === (int) $user->id) {
            throw ServiceError::forbidden('You cannot delete your own account');
        }
        (new User('users'))->delete($user->id);
        return array('id' => (int) $user->id, 'deleted' => true);
    }
}
