<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

#[Capability(
    name: 'get_user',
    description: 'Gets one person who can sign in: id, username, permissions, created_at and modified_at. Never the password.',
    scope: 'users:read',
    level: CaligrafyUsers::ADMIN_LEVEL,
    readOnly: true
)]
class CaligrafyGetUser extends Service
{
    public $rules = array('id' => 'required|integer');
    public $descriptions = array('id' => 'The id of the user');

    public function handle(array $input, Context $context)
    {
        CaligrafyUsers::requireAdmin($context);
        $user = (new User('users'))->find($input['id']);
        if (!$user) {
            throw ServiceError::notFound("No user #{$input['id']}");
        }
        return CaligrafyUsers::profile($user);
    }
}
