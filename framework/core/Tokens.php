<?php
/**
 * Tokens.php
 * Personal access tokens for machines and AI agents (REST and MCP)
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Tokens
 * A token is `cq_<id>_<secret>`: shown once when it is created, stored only as a SHA-256 hash in caligrafy_tokens
 * with a name, an optional owner (users.id), its scopes, an optional expiry, the last use and a revocation date.
 *
 * Scopes are `<resource>:<action>` strings (books:read, books:write). A token scope `*` grants everything and
 * `books:*` grants every action on books. Tables: framework/settings/platform.sql (php caligrafer.php platform install).
 */
class Tokens
{
    const TABLE = 'caligrafy_tokens';
    const PREFIX = 'cq_';

    /**
     * Creates a token
     * @param string $name what the token is for ("Claude Code on my laptop")
     * @param array $scopes the scopes it grants
     * @param int|null $userId the user it acts for (null: the application itself)
     * @param string|null $expiresAt 'Y-m-d H:i:s' (null: never expires)
     * @return array ['id' => ..., 'token' => 'cq_...']: the token is never shown again
     */
    public static function create($name, $scopes = array(), $userId = null, $expiresAt = null)
    {
        $name = trim((string) $name);
        if ($name === '' || strlen($name) > 100) {
            throw new \InvalidArgumentException('A token needs a name of 1 to 100 characters');
        }
        $scopes = self::normalizeScopes($scopes);
        $secret = bin2hex(random_bytes(20));
        Database::execute('INSERT INTO ' . self::TABLE . ' (name, token_hash, scopes, user_id, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?)',
            array($name, str_repeat('0', 64), implode(' ', $scopes), $userId, date('Y-m-d H:i:s'), $expiresAt));
        $id = (int) Database::lastInsertId();
        $token = self::PREFIX . $id . '_' . $secret;
        Database::execute('UPDATE ' . self::TABLE . ' SET token_hash = ? WHERE id = ?', array(self::hash($token), $id));
        return array('id' => $id, 'token' => $token);
    }

    /**
     * Checks a token and records its use
     * @param string $token the token as sent (Authorization: Bearer cq_...)
     * @return array|null the token row (id, name, scopes as an array, user_id...) or null when unknown, revoked or expired
     */
    public static function verify($token)
    {
        $id = self::parse($token);
        if ($id === null) {
            return null;
        }
        $rows = Database::toArray(Database::execute('SELECT * FROM ' . self::TABLE . ' WHERE id = ?', array($id)));
        $row = $rows[0] ?? null;
        if (!$row || !hash_equals((string) $row['token_hash'], self::hash($token))) {
            return null;
        }
        if ($row['revoked_at'] !== null || ($row['expires_at'] !== null && strtotime($row['expires_at']) <= time())) {
            return null;
        }
        Database::execute('UPDATE ' . self::TABLE . ' SET last_used_at = ? WHERE id = ?', array(date('Y-m-d H:i:s'), $id));
        $row['scopes'] = self::normalizeScopes($row['scopes']);
        unset($row['token_hash']);
        return $row;
    }

    /**
     * Revokes a token
     * @return bool true when a token was revoked
     */
    public static function revoke($id)
    {
        $rows = Database::toArray(Database::execute('SELECT id FROM ' . self::TABLE . ' WHERE id = ? AND revoked_at IS NULL', array((int) $id)));
        if (!$rows) {
            return false;
        }
        Database::execute('UPDATE ' . self::TABLE . ' SET revoked_at = ? WHERE id = ?', array(date('Y-m-d H:i:s'), (int) $id));
        return true;
    }

    /**
     * Lists the tokens (never their secret)
     * @return array rows, newest first
     */
    public static function all()
    {
        $rows = Database::toArray(Database::execute('SELECT id, name, scopes, user_id, created_at, expires_at, last_used_at, revoked_at FROM '
            . self::TABLE . ' ORDER BY id DESC')) ?: array();
        foreach ($rows as &$row) {
            $row['scopes'] = self::normalizeScopes($row['scopes']);
        }
        return $rows;
    }

    /**
     * Whether the framework tables exist (php caligrafer.php platform install)
     */
    public static function installed()
    {
        try {
            Database::execute('SELECT 1 FROM ' . self::TABLE . ' LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Whether a request carries a token of this format (as opposed to a legacy API key)
     */
    public static function looksLikeToken($token)
    {
        return self::parse($token) !== null;
    }

    /**
     * The id of a well-formed token, null otherwise
     */
    public static function parse($token)
    {
        return is_string($token) && preg_match('/^cq_([1-9][0-9]{0,9})_[0-9a-f]{40}$/', $token, $m) ? (int) $m[1] : null;
    }

    /**
     * Whether granted scopes cover a required scope: exact, `<resource>:*` or `*`. An empty requirement is always met.
     */
    public static function allows($granted, $required)
    {
        $required = trim((string) $required);
        if ($required === '') {
            return true;
        }
        foreach (self::normalizeScopes($granted) as $scope) {
            if ($scope === '*' || $scope === $required) {
                return true;
            }
            if (substr($scope, -2) === ':*' && strpos($required, substr($scope, 0, -1)) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Scopes as a sorted list of unique, valid strings (from an array or a space/comma separated string)
     */
    public static function normalizeScopes($scopes)
    {
        $list = is_array($scopes) ? $scopes : preg_split('/[\s,]+/', (string) $scopes);
        $list = array_values(array_unique(array_filter(array_map('trim', $list), function ($scope) {
            return $scope === '*' || preg_match('/^[a-z][a-z0-9_.-]*:([a-z][a-z0-9_.-]*|\*)$/', $scope);
        })));
        sort($list);
        return $list;
    }

    private static function hash($token)
    {
        return hash('sha256', $token);
    }
}
