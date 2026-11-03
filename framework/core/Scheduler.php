<?php
/**
 * Scheduler.php
 * Scheduled jobs: capabilities an app runs on a timer (application/config/schedule.php)
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Scheduler
 * An app declares its jobs in application/config/schedule.php: each one calls one capability with fixed inputs on a
 * fixed rhythm (every: 1m...1d, or a five-field cron in a timezone). The framework validates the file, runs the jobs
 * locally (php caligrafer.php schedule due / work) and prints the listing Caligrafy Cloud runs in production
 * (schedule list --json, version 1).
 *
 * A run: one at a time per job (a database lock), in a child process stopped at its timeout, with full access like the
 * command line (Context::system: actor type schedule, actor id the job's name), recorded in caligrafy_job_runs (kept 30
 * days). Exit codes: 0 ok, 1 the capability failed or timed out, 2 the schedule file is invalid, 3 skipped.
 */
class Scheduler
{
    const FILE = 'application/config/schedule.php';
    const TABLE = 'caligrafy_job_runs';
    const LISTING_VERSION = 1;
    const EVERY = array('1m' => '* * * * *', '5m' => '*/5 * * * *', '10m' => '*/10 * * * *', '15m' => '*/15 * * * *',
        '30m' => '*/30 * * * *', '1h' => '0 * * * *', '6h' => '0 */6 * * *', '12h' => '0 */12 * * *', '1d' => '0 0 * * *');
    const DEFAULT_TIMEOUT = 55;
    const MAX_TIMEOUT = 300;
    const KEEP_DAYS = 30;
    const FIELDS = array('name', 'capability', 'input', 'every', 'cron', 'timezone', 'timeout', 'enabled');

    const OK = 0;
    const FAILED = 1;
    const INVALID = 2;
    const SKIPPED = 3;

    /** @var string the project folder */
    private $root;

    public function __construct($root = '.')
    {
        $this->root = rtrim($root, '/');
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The declaration

    /**
     * The jobs of the project's schedule file, validated and completed (an empty list when there is no file)
     * @param bool $capabilities also check that each capability exists
     * @throws \InvalidArgumentException with every problem, one per line
     */
    public function jobs($capabilities = true)
    {
        $file = $this->root . '/' . self::FILE;
        if (!is_file($file)) {
            return array();
        }
        $declared = (static function ($path) { return include $path; })($file);
        if (!is_array($declared)) {
            throw new \InvalidArgumentException(self::FILE . ' must return an array of jobs');
        }
        return self::validate($declared, $capabilities ? function ($name) { return Capabilities::get($name) !== null; } : null);
    }

    /**
     * Validates declared jobs and completes them (cron from every, timezone, timeout, enabled)
     * @param array $declared the array the schedule file returns
     * @param callable|null $exists tells whether a capability exists (null: not checked)
     * @return array the jobs, in the file's order
     * @throws \InvalidArgumentException with every problem, one per line
     */
    public static function validate(array $declared, ?callable $exists = null)
    {
        $problems = array();
        $jobs = array();
        $names = array();
        foreach (array_values($declared) as $i => $job) {
            $at = 'job ' . ($i + 1);
            if (!is_array($job)) {
                $problems[] = "$at: must be an array";
                continue;
            }
            $name = $job['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $at = "job '$name'";
            }
            foreach (array_diff(array_keys($job), self::FIELDS) as $unknown) {
                $problems[] = "$at: unknown field '$unknown' (fields: " . implode(', ', self::FIELDS) . ')';
            }
            if (!is_string($name) || !preg_match('/^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/', $name)) {
                $problems[] = "$at: 'name' must be a slug of a-z, 0-9 and - (at most 40 characters)";
            } elseif (isset($names[$name])) {
                $problems[] = "$at: the name is used twice";
            }
            $names[$name] = true;
            $capability = $job['capability'] ?? null;
            if (!is_string($capability) || $capability === '') {
                $problems[] = "$at: 'capability' is required";
            } elseif ($exists !== null && !$exists($capability)) {
                $problems[] = "$at: there is no capability '$capability' (php caligrafer.php capabilities)";
            }
            $input = $job['input'] ?? array();
            if (!is_array($input) || json_encode($input) === false) {
                $problems[] = "$at: 'input' must be an array that encodes to JSON";
            }
            $every = $job['every'] ?? null;
            $cron = $job['cron'] ?? null;
            if (($every === null) === ($cron === null)) {
                $problems[] = "$at: give 'every' or 'cron', one of them";
            } elseif ($every !== null && !isset(self::EVERY[$every])) {
                $problems[] = "$at: 'every' must be one of " . implode(', ', array_keys(self::EVERY));
            } elseif ($cron !== null) {
                try {
                    self::parse($cron);
                } catch (\InvalidArgumentException $e) {
                    $problems[] = "$at: 'cron' " . $e->getMessage();
                }
            }
            $timezone = $job['timezone'] ?? 'UTC';
            if ($every !== null && isset($job['timezone'])) {
                $problems[] = "$at: 'timezone' is for 'cron' only";
            } elseif (!is_string($timezone) || !in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
                $problems[] = "$at: 'timezone' must be an IANA name such as America/New_York";
            }
            $timeout = $job['timeout'] ?? self::DEFAULT_TIMEOUT;
            if (!is_int($timeout) || $timeout < 1 || $timeout > self::MAX_TIMEOUT) {
                $problems[] = "$at: 'timeout' must be a number of seconds from 1 to " . self::MAX_TIMEOUT;
            }
            $enabled = $job['enabled'] ?? true;
            if (!is_bool($enabled)) {
                $problems[] = "$at: 'enabled' must be true or false";
            }
            $jobs[] = array('name' => $name, 'capability' => $capability, 'input' => is_array($input) ? $input : array(),
                'every' => $every, 'cron' => $every !== null ? (self::EVERY[$every] ?? null) : $cron,
                'timezone' => is_string($timezone) ? $timezone : 'UTC', 'timeout' => $timeout, 'enabled' => $enabled);
        }
        if ($problems) {
            throw new \InvalidArgumentException(self::FILE . " is invalid:\n- " . implode("\n- ", $problems));
        }
        return $jobs;
    }

    /**
     * The listing Caligrafy Cloud reads at deploy time (schedule list --json): the enabled jobs, every as cron
     */
    public static function listing(array $jobs)
    {
        $listed = array();
        foreach ($jobs as $job) {
            if (!$job['enabled']) continue;
            $listed[] = array('name' => $job['name'], 'cron' => $job['cron'], 'timezone' => $job['timezone'], 'timeout' => $job['timeout'],
                'command' => array('php', 'caligrafer.php', 'schedule', 'run', $job['name']));
        }
        return array('version' => self::LISTING_VERSION, 'jobs' => $listed);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Cron

    /**
     * The allowed values of each field of a five-field cron (minute hour day-of-month month day-of-week)
     * @return array five sorted lists of integers (day of week: 0-6, Sunday is 0 or 7)
     * @throws \InvalidArgumentException
     */
    public static function parse($cron)
    {
        if (!is_string($cron)) {
            throw new \InvalidArgumentException('must be a string of five fields');
        }
        $fields = preg_split('/\s+/', trim($cron));
        if (count($fields) !== 5) {
            throw new \InvalidArgumentException("must have five fields (minute hour day month weekday): '$cron'");
        }
        $bounds = array(array(0, 59), array(0, 23), array(1, 31), array(1, 12), array(0, 7));
        $names = array(3 => array('jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'),
            4 => array('sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'));
        $sets = array();
        foreach ($fields as $f => $field) {
            list($low, $high) = $bounds[$f];
            $values = array();
            foreach (explode(',', strtolower($field)) as $part) {
                if (!preg_match('/^(\*|[a-z0-9]+(?:-[a-z0-9]+)?)(?:\/(\d+))?$/', $part, $m)) {
                    throw new \InvalidArgumentException("has an invalid part '$part' in '$cron'");
                }
                $step = isset($m[2]) ? (int) $m[2] : 1;
                if ($step < 1) {
                    throw new \InvalidArgumentException("has a step of 0 in '$cron'");
                }
                if ($m[1] === '*') {
                    $from = $low;
                    $to = $high;
                } else {
                    $ends = explode('-', $m[1]);
                    $from = self::cronValue($ends[0], $names[$f] ?? array(), $f === 3 ? 1 : 0);
                    $to = isset($ends[1]) ? self::cronValue($ends[1], $names[$f] ?? array(), $f === 3 ? 1 : 0) : ($step > 1 ? $high : $from);
                }
                if ($from === null || $to === null || $from < $low || $to > $high || $from > $to) {
                    throw new \InvalidArgumentException("has a value out of range in '$part' of '$cron'");
                }
                for ($v = $from; $v <= $to; $v += $step) {
                    $values[$f === 4 ? $v % 7 : $v] = true;
                }
            }
            $values = array_keys($values);
            sort($values);
            $sets[] = $values;
        }
        return $sets;
    }

    private static function cronValue($value, array $names, $offset)
    {
        if (ctype_digit($value)) return (int) $value;
        $index = array_search($value, $names, true);
        return $index === false ? null : $index + $offset;
    }

    /**
     * Whether a cron is due at a time (to the minute), read in a timezone. When both the day of the month and the
     * day of the week are restricted, either one matches (as in cron)
     */
    public static function isDue($cron, \DateTimeInterface $at, $timezone = 'UTC')
    {
        $sets = self::parse($cron);
        $local = (new \DateTimeImmutable('@' . $at->getTimestamp()))->setTimezone(new \DateTimeZone($timezone));
        list($minute, $hour, $day, $month, $weekday) = array_map('intval', explode(' ', $local->format('i G j n w')));
        if (!in_array($minute, $sets[0], true) || !in_array($hour, $sets[1], true) || !in_array($month, $sets[3], true)) {
            return false;
        }
        $fields = preg_split('/\s+/', trim($cron));
        $dayAny = $fields[2] === '*';
        $weekdayAny = $fields[4] === '*';
        $dayOk = in_array($day, $sets[2], true);
        $weekdayOk = in_array($weekday, $sets[4], true);
        if (!$dayAny && !$weekdayAny) {
            return $dayOk || $weekdayOk;
        }
        return $dayOk && $weekdayOk;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Running

    /**
     * Runs one job now: takes its lock, runs it in a child process (stopped at its timeout), records the run
     * @param string $name the job's name
     * @param callable|null $say prints a line
     * @param bool $ifDue only when the job is due this minute in its timezone (--if-due), else nothing is run or recorded
     * @return int an exit code (OK, FAILED, SKIPPED)
     */
    public function run($name, ?callable $say = null, $ifDue = false)
    {
        $say = $say ?: function ($line) {};
        $job = $this->job($name);
        if ($ifDue && !self::isDue($job['cron'], new \DateTimeImmutable(), $job['timezone'])) {
            return self::OK;   // run every minute by a host that cannot read the job's timezone: not due now, nothing to do
        }
        if (!Database::connect()) {
            throw new \RuntimeException('Scheduled jobs need the database: set DB_ACTIVATE=true and the DB_* settings in .env');
        }
        $started = date('Y-m-d H:i:s');
        $lock = $this->lockName($name);
        if (!self::scalar('SELECT GET_LOCK(?, 0) AS v', array($lock))) {
            $this->record($name, $started, 'skipped', 'the previous run of this job is still running');
            $say("$name: skipped, the previous run is still running");
            return self::SKIPPED;
        }
        try {
            list($outcome, $detail) = $this->child($job);
            $this->record($name, $started, $outcome, $detail);
            $this->prune();
        } finally {
            self::scalar('SELECT RELEASE_LOCK(?) AS v', array($lock));
        }
        $say("$name: $outcome" . ($detail !== '' && $outcome !== 'ok' ? " ($detail)" : ''));
        return $outcome === 'ok' ? self::OK : self::FAILED;
    }

    /**
     * What a run's child process does: calls the job's capability as the scheduler and prints the result as JSON
     * @return int OK or FAILED
     */
    public function execute($name)
    {
        $job = $this->job($name);
        try {
            $data = Capabilities::call($job['capability'], $job['input'], Context::system('schedule', $name));
            print(json_encode(array('data' => $data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
            return self::OK;
        } catch (ServiceError $e) {
            print(json_encode($e->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
            return self::FAILED;
        } catch (\Throwable $e) {
            print(json_encode(array('error' => array('kind' => 'error', 'message' => get_class($e) . ': ' . $e->getMessage())), JSON_UNESCAPED_SLASHES) . "\n");
            return self::FAILED;
        }
    }

    /**
     * The jobs due in a minute (enabled ones only)
     */
    public function dueAt(\DateTimeInterface $at)
    {
        return array_values(array_filter($this->jobs(), function ($job) use ($at) {
            return $job['enabled'] && self::isDue($job['cron'], $at, $job['timezone']);
        }));
    }

    /**
     * Runs every job due in the current minute, side by side, and waits for them
     * @return int OK when every run is ok or skipped, FAILED otherwise
     */
    public function due(?callable $say = null, ?\DateTimeInterface $at = null)
    {
        $say = $say ?: function ($line) {};
        $running = array();
        foreach ($this->dueAt($at ?: new \DateTimeImmutable()) as $job) {
            $process = proc_open(array(PHP_BINARY, 'caligrafer.php', 'schedule', 'run', $job['name']), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, $this->root);
            if (is_resource($process)) {
                $running[$job['name']] = array($process, $pipes);
            }
        }
        $status = self::OK;
        foreach ($running as $name => list($process, $pipes)) {
            $out = trim(stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($process);
            if ($out !== '') $say($out);
            if ($code !== self::OK && $code !== self::SKIPPED) $status = self::FAILED;
        }
        return $status;
    }

    /**
     * Runs schedule due at the start of every minute until stopped (local development)
     */
    public function work(?callable $say = null)
    {
        $say = $say ?: function ($line) {};
        $say('Running the scheduled jobs every minute (Ctrl-C to stop)');
        while (true) {
            sleep(60 - (int) date('s'));
            try {
                $this->due($say);
            } catch (\Throwable $e) {
                $say('schedule: ' . $e->getMessage());
            }
        }
    }

    /**
     * The latest runs, newest first
     */
    public static function history($name = null, $limit = 50)
    {
        $limit = max(1, min(1000, (int) $limit));
        $sql = 'SELECT job, started_at, finished_at, outcome, detail FROM ' . self::TABLE . ($name ? ' WHERE job = ?' : '') . " ORDER BY id DESC LIMIT $limit";
        return Database::toArray(Database::execute($sql, $name ? array($name) : array())) ?: array();
    }

    // ---------------------------------------------------------------------------------------------------------------

    private function job($name)
    {
        foreach ($this->jobs() as $job) {
            if ($job['name'] === $name) return $job;
        }
        throw new \InvalidArgumentException("There is no job '$name' in " . self::FILE . ' (php caligrafer.php schedule list)');
    }

    /**
     * Runs the job's capability in a child process, stopped at the job's timeout
     * @return array outcome (ok, error, timeout) and a detail of at most 500 characters
     */
    private function child(array $job)
    {
        $process = proc_open(array(PHP_BINARY, 'caligrafer.php', 'schedule', 'exec', $job['name']), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, $this->root);
        if (!is_resource($process)) {
            return array('error', 'the job could not start');
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = '';
        $deadline = microtime(true) + $job['timeout'];
        $exit = null;
        while (true) {
            $out .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                $exit = $status['exitcode'];
                break;
            }
            if (microtime(true) >= $deadline) {
                proc_terminate($process, 9);
                break;
            }
            usleep(100000);
        }
        $out .= stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        if ($exit === null) {
            return array('timeout', 'stopped after ' . $job['timeout'] . ' seconds');
        }
        $answer = json_decode(trim($out), true);
        if ($exit === self::OK) {
            return array('ok', '');
        }
        $message = $answer['error']['message'] ?? trim($out);
        return array('error', mb_substr($message !== '' ? $message : "exit code $exit", 0, 500));
    }

    private function record($name, $started, $outcome, $detail)
    {
        try {
            Database::execute('INSERT INTO ' . self::TABLE . ' (job, started_at, finished_at, outcome, detail) VALUES (?, ?, ?, ?, ?)',
                array($name, $started, date('Y-m-d H:i:s'), $outcome, $detail === '' ? null : mb_substr($detail, 0, 500)));
        } catch (\Throwable $e) {
            // without the table (php caligrafer.php platform install) a run is not recorded, and does not fail
        }
    }

    private function prune()
    {
        try {
            Database::execute('DELETE FROM ' . self::TABLE . ' WHERE started_at < ?', array(date('Y-m-d H:i:s', time() - self::KEEP_DAYS * 86400)));
        } catch (\Throwable $e) {
        }
    }

    private function lockName($name)
    {
        // MySQL lock names are server-wide and at most 64 characters: the database and the job, hashed
        return 'cq_job_' . substr(sha1((string) getenv('DB_DATABASE') . '/' . $name), 0, 40);
    }

    private static function scalar($sql, array $args)
    {
        $rows = Database::toArray(Database::execute($sql, $args)) ?: array();
        return $rows ? (int) array_values($rows[0])[0] : 0;
    }
}
