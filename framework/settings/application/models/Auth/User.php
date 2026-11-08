<?php

use Caligrafy\Database;
use Caligrafy\Model;

class User extends Model {
    public $id;
    public $username;
    public $passcode;
    public $permissions;
    public $created_at;
    public $modified_at;

    /**
     * Finds a user by exact username with a bound query (no LIKE wildcards, no SQL injection)
     * @return User|null
     */
    public function findByUsername($username)
    {
        return $this->findBy('username', $username);
    }

    /**
     * Finds a user by the exact value of a column (Route::auth(['identifier' => 'email']) signs in with it), bound
     * @return User|null
     */
    public function findBy($column, $value)
    {
        $row = Database::table($this->table)->where($column, (string)$value)->first();
        return $row ? $this->arrayToModel($row) : null;
    }

    /**
     * Hashes a plain password for storage
     */
    public static function hashPassword($password)
    {
        return password_hash((string)$password, PASSWORD_DEFAULT);
    }

    /**
     * Checks a plain password against the stored passcode.
     * Passcodes stored by older versions (encryptDecrypt with the APP_KEY) are still accepted:
     * check needsRehash() afterwards to upgrade them to a hash.
     */
    public function verifyPassword($password)
    {
        $stored = (string)$this->passcode;
        if (password_get_info($stored)['algo'] !== null && password_get_info($stored)['algo'] !== 0) {
            return password_verify((string)$password, $stored);
        }
        $legacy = encryptDecrypt('decrypt', $stored);
        return $legacy !== false && is_string($legacy) && hash_equals($legacy, (string)$password);
    }

    /**
     * True when the stored passcode is a legacy encrypted value or a hash made with older options
     */
    public function needsRehash()
    {
        $stored = (string)$this->passcode;
        $info = password_get_info($stored);
        return $info['algo'] === null || $info['algo'] === 0 || password_needs_rehash($stored, PASSWORD_DEFAULT);
    }
}
