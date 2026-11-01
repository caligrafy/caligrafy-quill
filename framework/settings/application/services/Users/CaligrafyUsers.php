<?php

// Shared by the user capabilities (a framework name, so that it never collides with a class of your application)
class CaligrafyUsers
{
    /*
     * The permissions an administrator needs (users.permissions): the user capabilities and the user administration
     * pages (AdminController) both use it. New users get 0; promote your first administrator in the database:
     * UPDATE users SET permissions = 1 WHERE username = '<your username>';
     */
    const ADMIN_LEVEL = 1;

    public static function profile($user)
    {
        $user = (object) $user;
        return array('id' => (int) $user->id, 'username' => (string) $user->username, 'permissions' => (int) ($user->permissions ?? 0),
            'created_at' => $user->created_at ?? null, 'modified_at' => $user->modified_at ?? null);
    }
}
