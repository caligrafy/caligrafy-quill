<?php
/**
 * Audit.php
 * Audit log of capability calls (caligrafy_audit)
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Audit
 * One row per capability call that changes data, and per refused call: when, who (a user, a token, the CLI),
 * through which channel, which capability and the outcome. Inputs are never stored.
 * Without the table (php caligrafer.php platform install) nothing is written and nothing fails.
 */
class Audit
{
    const TABLE = 'caligrafy_audit';

    /**
     * Records one call
     * @param Context $context who called and through which channel
     * @param string $capability the capability name
     * @param string $outcome ok, invalid, unauthenticated, forbidden, not_found, conflict, error
     * @param string|null $detail a short note (an error message), at most 500 characters
     * @return bool whether the row was written
     */
    public static function record(Context $context, $capability, $outcome, $detail = null)
    {
        try {
            Database::execute('INSERT INTO ' . self::TABLE . ' (occurred_at, actor_type, actor_id, channel, capability, outcome, detail) VALUES (?, ?, ?, ?, ?, ?, ?)',
                array(date('Y-m-d H:i:s'), $context->actorType(), $context->actorId(), $context->channel, (string) $capability, (string) $outcome,
                    $detail === null ? null : mb_substr((string) $detail, 0, 500)));
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The latest rows, newest first
     * @param int $limit how many
     * @param string|null $capability only this capability
     */
    public static function latest($limit = 50, $capability = null)
    {
        $limit = max(1, min(1000, (int) $limit));
        $sql = 'SELECT * FROM ' . self::TABLE . ($capability ? ' WHERE capability = ?' : '') . " ORDER BY id DESC LIMIT $limit";
        return Database::toArray(Database::execute($sql, $capability ? array($capability) : array())) ?: array();
    }
}
