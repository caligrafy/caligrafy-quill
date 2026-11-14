<?php
/**
 * Setup.php
 * php caligrafer.php snap: sets Snap up in a project, and says what is ready (2.23.0)
 * @copyright 2026
 */

namespace Caligrafy\Snap;

/**
 * Class Setup
 * install: the tables (framework/settings/platform.sql), a starter application/config/snap.php when there is none,
 * then the two lines to add by hand (they are the app's own files): Route::snap(); in web.php, and the routines'
 * job in application/config/schedule.php.
 * status: each of those, the queue (tasks run in it), and the assistant's model (ANTHROPIC_API_KEY).
 */
class Setup
{
    const CONFIG = 'application/config/snap.php';
    const TEMPLATE = 'framework/settings/snap.php.template';
    const ROUTES = 'application/config/routing/web.php';
    const SCHEDULE = 'application/config/schedule.php';

    /**
     * What is ready, and what is not: [label => [ok (bool), detail]]
     */
    public static function status($root = '.')
    {
        $root = rtrim($root, '/') . '/';
        $routes = is_file($root . self::ROUTES) ? (string) file_get_contents($root . self::ROUTES) : '';
        $schedule = is_file($root . self::SCHEDULE) ? (string) file_get_contents($root . self::SCHEDULE) : '';
        $tables = Conversation::installed();
        return array(
            'Tables' => array($tables, $tables ? 'caligrafy_snap_*' : 'missing: php caligrafer.php snap install'),
            'Route' => array((bool) preg_match('/Route::snap\s*\(/', $routes), 'Route::snap(); in ' . self::ROUTES),
            'Card hints' => array(is_file($root . self::CONFIG), self::CONFIG . ' (optional: cards work without it)'),
            'Background tasks' => array(strpos($schedule, "'queue_work'") !== false, "the queue runs them: array('name' => 'queue', 'capability' => 'queue_work', 'every' => '1m', 'timeout' => 55) in " . self::SCHEDULE),
            'Routines' => array(strpos($schedule, "'snap_routines_due'") !== false, "array('name' => 'snap-routines', 'capability' => 'snap_routines_due', 'every' => '1m', 'timeout' => 50) in " . self::SCHEDULE),
            'Assistant model' => array(Planner::on(), Planner::on() ? (getenv('SNAP_MODEL') ?: Planner::MODEL) : 'ANTHROPIC_API_KEY in .env (without it the assistant answers from the cards\' names)'),
        );
    }

    /**
     * Writes the starter application/config/snap.php when there is none
     * @return bool true when it was written
     */
    public static function config($root = '.')
    {
        $root = rtrim($root, '/') . '/';
        if (is_file($root . self::CONFIG) || !is_file($root . self::TEMPLATE) || !is_dir($root . 'application/config')) {
            return false;
        }
        return file_put_contents($root . self::CONFIG, file_get_contents($root . self::TEMPLATE)) !== false;
    }
}
