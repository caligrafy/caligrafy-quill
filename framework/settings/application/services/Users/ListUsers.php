<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

// The people who can sign in (the users table of Auth::authentication): administrators only (permissions 1 or more).
// The user administration pages (AdminController) and AI agents use the same capabilities.
#[Capability(
    name: 'list_users',
    description: 'Lists the people who can sign in, newest change first: id, username, permissions (0 for everyone, 1 or more for administrators), created_at and modified_at. Never their passwords.',
    scope: 'users:read',
    level: CaligrafyUsers::ADMIN_LEVEL,
    readOnly: true
)]
class ListUsers extends Service
{
    public function handle(array $input, Context $context)
    {
        return array_map(function ($user) { return CaligrafyUsers::profile($user); },
            (new User('users'))->all(array('order' => 'ORDER BY modified_at DESC, id DESC')) ?: array());
    }
}
