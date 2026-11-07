<?php
/**
 * Queue.php
 * Background jobs: a capability run later, outside the request, with retries
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Queue (2.18.0)
 *
 *     Queue::push('send_welcome_email', ['user_id' => $id]);                       // as soon as a worker is free
 *     Queue::push('build_report', ['month' => '2026-11'], ['delay' => 600, 'attempts' => 5, 'timeout' => 120]);
 *
 * A job is a capability and its input, kept in the caligrafy_jobs table (php caligrafer.php platform install, or the
 * deploy step, creates it). A worker runs each one in its own process, stopped at its timeout, with full access, as a
 * scheduled job (Context::system('queue', '<capability> #<id>')): check who may ask for the work before pushing it.
 *
 * Workers: php caligrafer.php queue work (a loop, on your computer or your own server), or the scheduler: a job of
 * application/config/schedule.php on the framework's capability queue_work, every minute, which Caligrafy Cloud runs
 * too. A failed run is tried again after 30 s, 1 min, 2 min... until its attempts are used; an input the capability
 * refuses (invalid, not found) fails at once. Jobs are kept 7 days after they finish.
 */
class Queue
{
    const TABLE = 'caligrafy_jobs';
    const DEFAULT_ATTEMPTS = 3;
    const DEFAULT_TIMEOUT = 60;
    const MAX_TIMEOUT = 900;
    const RETRY_SECONDS = 30;
    const KEEP_DAYS = 7;
    const NAME = '/^[a-z0-9_-]{1,40}$/';

    /**
     * Adds a job
     * @param array $options delay (seconds), attempts (1-20), timeout (seconds), queue (a name, default)
     * @return int the job's id
     */
    public static function push($capability, array $input = array(), array $options = array())
    {
        if (Capabilities::get($capability) === null) {
            throw new \InvalidArgumentException("There is no capability '$capability' to queue");
        }
        $queue = (string) ($options['queue'] ?? 'default');
        if (!preg_match(self::NAME, $queue)) {
            throw new \InvalidArgumentException("Queue name '$queue': lowercase letters, digits, - and _, 40 at most");
        }
        $attempts = max(1, min(20, (int) ($options['attempts'] ?? self::DEFAULT_ATTEMPTS)));
        $timeout = max(1, min(self::MAX_TIMEOUT, (int) ($options['timeout'] ?? self::DEFAULT_TIMEOUT)));
        $json = json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \InvalidArgumentException('The input of a job must be JSON: ' . json_last_error_msg());
        }
        $now = time();
        Database::execute('INSERT INTO `' . self::TABLE . '` (`queue`, `capability`, `input`, `status`, `attempts`, `max_attempts`, `timeout`, `available_at`, `created_at`) '
            . 'VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?)', array($queue, $capability, $json, 'pending', $attempts, $timeout,
            date('Y-m-d H:i:s', $now + max(0, (int) ($options['delay'] ?? 0))), date('Y-m-d H:i:s', $now)));
        return (int) Database::lastInsertId();
    }

    /**
     * Runs jobs: until none is left (once), for $seconds, or forever
     * @param array $options queue (default), seconds (stop starting jobs after), once (stop when empty), sleep (seconds
     *                       between looks when empty), root (the project), say (callable)
     * @return array counts: done, retried, failed
     */
    public static function work(array $options = array())
    {
        $queue = (string) ($options['queue'] ?? 'default');
        $budget = isset($options['seconds']) ? (float) $options['seconds'] : null;
        $once = !empty($options['once']);
        $sleep = max(1, (int) ($options['sleep'] ?? 3));
        $root = $options['root'] ?? '.';
        $say = $options['say'] ?? function ($line) {};
        $started = microtime(true);
        $counts = array('done' => 0, 'retried' => 0, 'failed' => 0);
        self::release();
        self::prune();
        while (true) {
            $left = $budget === null ? null : $budget - (microtime(true) - $started);
            if ($left !== null && $left < 1) {
                break;
            }
            $job = self::reserve($queue);
            if ($job === null) {
                if ($once || ($left !== null && $left <= $sleep)) {
                    break;
                }
                sleep($sleep);
                self::release();
                continue;
            }
            $timeout = (int) $job['timeout'];
            if ($left !== null) {
                $timeout = max(1, min($timeout, (int) floor($left)));
            }
            list($exit, $out) = Scheduler::spawn(array('queue', 'exec', (string) $job['id']), $root, $timeout);
            $outcome = self::finish($job, $exit, $out, $timeout);
            $counts[$outcome]++;
            $say("#{$job['id']} {$job['capability']}: $outcome" . ($outcome !== 'done' ? ' (' . self::lastError($job['id']) . ')' : ''));
        }
        return $counts;
    }

    /**
     * The child process of a job (php caligrafer.php queue exec <id>): calls its capability, prints its answer as JSON
     * @return int 0 done, 1 failed (try again), 2 refused (do not try again)
     */
    public static function execute($id)
    {
        $rows = Database::toArray(Database::execute('SELECT * FROM `' . self::TABLE . '` WHERE `id` = ?', array((int) $id)));
        if (!$rows) {
            print(json_encode(array('error' => array('kind' => 'not_found', 'message' => "No job #$id"))) . "\n");
            return 2;
        }
        $job = $rows[0];
        try {
            $data = Capabilities::call($job['capability'], json_decode((string) $job['input'], true) ?: array(), Context::system('queue', $job['capability'] . ' #' . $job['id']));
            print(json_encode(array('data' => $data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
            return 0;
        } catch (ServiceError $e) {
            print(json_encode($e->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
            return in_array($e->kind, array('invalid', 'not_found', 'forbidden', 'unauthenticated'), true) ? 2 : 1;
        } catch (\Throwable $e) {
            print(json_encode(array('error' => array('kind' => 'error', 'message' => get_class($e) . ': ' . $e->getMessage())), JSON_UNESCAPED_SLASHES) . "\n");
            return 1;
        }
    }

    /**
     * Jobs, newest first
     * @param string|null $status pending, running, done or failed
     */
    public static function jobs($status = null, $limit = 50)
    {
        $sql = 'SELECT `id`, `queue`, `capability`, `status`, `attempts`, `max_attempts`, `available_at`, `finished_at`, `last_error`, `created_at` FROM `' . self::TABLE . '`';
        $args = array();
        if ($status !== null) {
            $sql .= ' WHERE `status` = ?';
            $args[] = $status;
        }
        return Database::toArray(Database::execute($sql . ' ORDER BY `id` DESC LIMIT ' . max(1, min(1000, (int) $limit)), $args));
    }

    /**
     * How many jobs are in each state
     */
    public static function counts()
    {
        $counts = array('pending' => 0, 'running' => 0, 'done' => 0, 'failed' => 0);
        foreach (Database::toArray(Database::execute('SELECT `status`, COUNT(*) AS n FROM `' . self::TABLE . '` GROUP BY `status`')) as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }
        return $counts;
    }

    /**
     * Puts failed jobs back in the queue with new attempts: one id, or all (null)
     * @return int how many
     */
    public static function retry($id = null)
    {
        $sql = 'UPDATE `' . self::TABLE . '` SET `status` = ?, `attempts` = 0, `available_at` = ?, `finished_at` = NULL, `reserved_by` = NULL WHERE `status` = ?';
        $args = array('pending', date('Y-m-d H:i:s'), 'failed');
        if ($id !== null) {
            $sql .= ' AND `id` = ?';
            $args[] = (int) $id;
        }
        Database::execute($sql, $args);
        return (int) self::scalar('SELECT ROW_COUNT() AS v', array());
    }

    /**
     * Takes the next due job of a queue for this worker, or null
     */
    private static function reserve($queue)
    {
        $token = bin2hex(random_bytes(16));
        $now = date('Y-m-d H:i:s');
        Database::execute('UPDATE `' . self::TABLE . '` SET `status` = ?, `reserved_by` = ?, `reserved_at` = ?, `attempts` = `attempts` + 1 '
            . 'WHERE `status` = ? AND `queue` = ? AND `available_at` <= ? ORDER BY `available_at`, `id` LIMIT 1', array('running', $token, $now, 'pending', $queue, $now));
        $rows = Database::toArray(Database::execute('SELECT * FROM `' . self::TABLE . '` WHERE `reserved_by` = ? AND `status` = ?', array($token, 'running')));
        return $rows[0] ?? null;
    }

    /**
     * Records how a run ended: done, retried (pending again, later) or failed
     */
    private static function finish(array $job, $exit, $out, $timeout)
    {
        $now = time();
        if ($exit === 0) {
            Database::execute('UPDATE `' . self::TABLE . '` SET `status` = ?, `finished_at` = ?, `last_error` = NULL, `reserved_by` = NULL WHERE `id` = ?',
                array('done', date('Y-m-d H:i:s', $now), $job['id']));
            return 'done';
        }
        $answer = json_decode(trim((string) $out), true);
        if ($exit === false) {
            $error = 'the job could not start';
        } elseif ($exit === null) {
            $error = "stopped after $timeout seconds";
        } else {
            $error = (string) ($answer['error']['message'] ?? trim((string) $out));
            $error = $error !== '' ? $error : "exit code $exit";
        }
        $final = $exit === 2 || (int) $job['attempts'] >= (int) $job['max_attempts'];
        if ($final) {
            Database::execute('UPDATE `' . self::TABLE . '` SET `status` = ?, `finished_at` = ?, `last_error` = ?, `reserved_by` = NULL WHERE `id` = ?',
                array('failed', date('Y-m-d H:i:s', $now), mb_substr($error, 0, 1000), $job['id']));
            return 'failed';
        }
        $wait = self::RETRY_SECONDS * (2 ** max(0, (int) $job['attempts'] - 1));
        Database::execute('UPDATE `' . self::TABLE . '` SET `status` = ?, `available_at` = ?, `last_error` = ?, `reserved_by` = NULL WHERE `id` = ?',
            array('pending', date('Y-m-d H:i:s', $now + $wait), mb_substr($error, 0, 1000), $job['id']));
        return 'retried';
    }

    /**
     * Jobs left running by a worker that stopped (its process was killed): pending again, or failed when their
     * attempts are used
     */
    private static function release()
    {
        foreach (Database::toArray(Database::execute('SELECT `id`, `attempts`, `max_attempts`, `timeout`, `reserved_at` FROM `' . self::TABLE . '` WHERE `status` = ?', array('running'))) as $job) {
            if (strtotime((string) $job['reserved_at']) + (int) $job['timeout'] + 60 > time()) {
                continue;            // still within its time: another worker may be running it
            }
            $failed = (int) $job['attempts'] >= (int) $job['max_attempts'];
            Database::execute('UPDATE `' . self::TABLE . '` SET `status` = ?, `reserved_by` = NULL, `last_error` = ?' . ($failed ? ', `finished_at` = ?' : '') . ' WHERE `id` = ? AND `status` = ?',
                array_merge(array($failed ? 'failed' : 'pending', 'its worker stopped while it ran'), $failed ? array(date('Y-m-d H:i:s')) : array(), array($job['id'], 'running')));
        }
    }

    private static function prune()
    {
        Database::execute('DELETE FROM `' . self::TABLE . '` WHERE `status` IN (?, ?) AND `finished_at` < ?',
            array('done', 'failed', date('Y-m-d H:i:s', time() - self::KEEP_DAYS * 86400)));
    }

    private static function lastError($id)
    {
        return (string) self::scalar('SELECT `last_error` AS v FROM `' . self::TABLE . '` WHERE `id` = ?', array($id));
    }

    private static function scalar($sql, array $args)
    {
        $rows = Database::toArray(Database::execute($sql, $args));
        return $rows ? reset($rows[0]) : null;
    }
}
