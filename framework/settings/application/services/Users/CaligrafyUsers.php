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

    /**
     * Managing users: the command line, or a real person (signed in, or the owner of a token) at ADMIN_LEVEL or above.
     * Never the shared legacy API key or a token without an owner: those are not someone.
     */
    public static function requireAdmin(\Caligrafy\Context $context)
    {
        if ($context->actorType() === 'cli') {
            return;
        }
        if ($context->user === null || $context->level() < self::ADMIN_LEVEL) {
            throw \Caligrafy\ServiceError::forbidden('Managing users needs an administrator: signed in, or a token created with --user');
        }
    }

    /**
     * Whether the caller may manage a person with these permissions (never someone above their own level)
     */
    public static function mayManage(\Caligrafy\Context $context, $permissions)
    {
        return $context->actorType() === 'cli' || (int) $permissions <= $context->level();
    }

    /**
     * A username taken at the same moment (the unique index answered): the same refusal as the check before it
     */
    public static function isDuplicate(\Throwable $e)
    {
        return $e instanceof \PDOException && (string) $e->getCode() === '23000';
    }

    public static function profile($user)
    {
        $user = (object) $user;
        return array('id' => (int) $user->id, 'username' => (string) $user->username, 'permissions' => (int) ($user->permissions ?? 0),
            'created_at' => $user->created_at ?? null, 'modified_at' => $user->modified_at ?? null);
    }
}
