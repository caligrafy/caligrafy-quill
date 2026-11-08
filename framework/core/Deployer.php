<?php
/**
 * Deployer.php
 * What a project runs when it starts in production (php caligrafer.php deploy, called by the production image)
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Deployer
 * 1. waits for the database (a new server starts its database at the same time as the app)
 * 2. creates the framework tables (framework/settings/platform.sql, safe to run again)
 * 3. runs every application/models/<folder>/create_db.sql that is new or changed since its last run, and records it in
 *    caligrafy_scripts. A script that drops, deletes or truncates is never run again once it ran: run it by hand
 *    (php caligrafer.php sql <file>) when you mean it. seed.sql and other scripts are never run. A statement that adds
 *    a column, an index or a foreign key already there (the script's earlier run added it) is skipped, so a script
 *    keeps its ALTER TABLE ... ADD statements.
 * 4. runs the migrations of application/migrations that never ran (Migrations, 2.18.0)
 * Without DB_ACTIVATE=true there is nothing to do.
 */
class Deployer
{
    const SCRIPTS_TABLE = 'caligrafy_scripts';
    const DESTRUCTIVE = '/\b(DROP\s+(TABLE|DATABASE|SCHEMA|VIEW|COLUMN)|DELETE\s+FROM|TRUNCATE)\b/i';

    private $root;
    private $wait;
    private $lines = array();

    /**
     * @param string $root the project folder
     * @param int $wait seconds to wait for the database
     */
    public function __construct($root = '.', $wait = 60)
    {
        $this->root = rtrim($root, '/');
        $this->wait = (int) $wait;
    }

    /**
     * @return int exit code: 0 when the project is ready, 1 when the database could not be prepared
     */
    public function run()
    {
        print("\n Caligrafy deploy\n");
        $this->schedule();
        $cached = self::cacheEnv($this->root);
        $this->line($cached ? 'OK' : 'SKIP', 'Settings', $cached ? '.env compiled to .env.php (read without parsing on every request)' : 'no .env to compile');
        if (strtolower((string) getenv('DB_ACTIVATE')) !== 'true') {
            $this->line('SKIP', 'Database', 'DB_ACTIVATE is not true: nothing to prepare');
            return $this->done(0);
        }
        if (!$this->connect()) {
            $this->line('FAIL', 'Database', 'not reachable after ' . $this->wait . ' s: check the DB_* settings');
            return $this->done(1);
        }
        try {
            $this->sqlFile($this->root . '/framework/settings/platform.sql');
            Database::execute('CREATE TABLE IF NOT EXISTS `' . self::SCRIPTS_TABLE . '` (`path` VARCHAR(255) NOT NULL, `sha256` CHAR(64) NOT NULL, '
                . '`ran_at` DATETIME NOT NULL, PRIMARY KEY (`path`)) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4');
            $this->line('OK', 'Framework tables', 'tokens, audit log, job runs, jobs, cache, migrations, scripts');
            foreach ($this->scripts() as $path) {
                $this->script($path);
            }
            $this->migrations();
        } catch (\Throwable $e) {
            $this->line('FAIL', 'Database', $e->getMessage());
            return $this->done(1);
        }
        return $this->done(0);
    }

    /**
     * Compiles .env to .env.php (2.19.0): the values Dotenv reads from it, as a PHP array that index.php includes
     * (OPcache keeps it) instead of parsing .env on every request. Same owner, group and permissions as .env; written
     * to a temporary file, then renamed. index.php uses it only while .env still has the content it was compiled from (its sha1).
     * @return bool false without a .env
     */
    public static function cacheEnv($root = '.')
    {
        $root = rtrim($root, '/');
        $env = $root . '/.env';
        if (!is_file($env)) {
            return false;
        }
        $source = (string) file_get_contents($env);
        $values = array('sha1' => sha1($source), 'values' => \Dotenv\Dotenv::parse($source));   // used while .env is unchanged
        $target = $root . '/.env.php';
        $tmp = $target . '.' . getmypid() . '.tmp';
        if (file_put_contents($tmp, "<?php\n// Compiled from .env by php caligrafer.php env cache (or the deploy step): never edit it, never commit it\nreturn " . var_export($values, true) . ";\n") === false) {
            return false;
        }
        @chmod($tmp, fileperms($env) & 0777);
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            @chown($tmp, fileowner($env));
            @chgrp($tmp, filegroup($env));
        }
        if (!@rename($tmp, $target)) {
            @unlink($tmp);
            return false;
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($target, true);
        }
        return true;
    }

    /**
     * Prints the scheduled jobs (application/config/schedule.php) on one line of the start-up log, where Caligrafy
     * Cloud reads them: "caligrafy-schedule <the listing of schedule list --json>", or "caligrafy-schedule-invalid
     * <the problems>" (the jobs then do not run). Without the file the listing is empty.
     */
    public function schedule()
    {
        try {
            $jobs = (new Scheduler($this->root))->jobs();
            print(' caligrafy-schedule ' . json_encode(Scheduler::listing($jobs), JSON_UNESCAPED_SLASHES) . "\n");
            return true;
        } catch (\Throwable $e) {
            print(' caligrafy-schedule-invalid ' . str_replace("\n", ' ', $e->getMessage()) . "\n");
            return false;
        }
    }

    /**
     * application/models/<folder>/create_db.sql files, in folder order
     */
    public function scripts()
    {
        $found = glob($this->root . '/application/models/*/create_db.sql') ?: array();
        sort($found);
        return array_map(function ($file) { return substr($file, strlen($this->root) + 1); }, $found);
    }

    /**
     * Whether a script drops, deletes or truncates data
     */
    public static function destructive($sql)
    {
        return (bool) preg_match(self::DESTRUCTIVE, preg_replace(array('#/\*.*?\*/#s', '/^\s*--.*$/m'), '', (string) $sql));
    }

    private function script($path)
    {
        $content = (string) file_get_contents($this->root . '/' . $path);
        $hash = hash('sha256', $content);
        $rows = Database::toArray(Database::execute('SELECT `sha256` FROM `' . self::SCRIPTS_TABLE . '` WHERE `path` = ?', array($path)));
        $last = $rows[0]['sha256'] ?? null;
        if ($last === $hash) {
            $this->line('OK', $path, 'up to date');
            return;
        }
        if ($last !== null && self::destructive($content)) {
            $this->line('WARN', $path, 'changed, but it drops or deletes data: not run (php caligrafer.php sql ' . $path . ' runs it)');
            return;
        }
        $this->sqlFile($this->root . '/' . $path);
        Database::execute('REPLACE INTO `' . self::SCRIPTS_TABLE . '` (`path`, `sha256`, `ran_at`) VALUES (?, ?, ?)', array($path, $hash, date('Y-m-d H:i:s')));
        $this->line('OK', $path, $last === null ? 'run' : 'changed: run again');
    }

    /**
     * The migrations of application/migrations (2.18.0) that never ran on this database, after the create_db.sql scripts
     */
    private function migrations()
    {
        $migrations = new Migrations($this->root);
        if (!$migrations->files()) {
            return;
        }
        $ran = $migrations->run(function ($line) {
            if (strpos($line, 'WARN') === 0) $this->line('WARN', 'Migrations', substr($line, 5));
        });
        $this->line('OK', 'Migrations', $ran ? count($ran) . ' run: ' . implode(', ', $ran) : 'up to date');
    }

    private function connect()
    {
        $until = time() + $this->wait;
        do {
            try {
                if (Database::connect()) return true;
            } catch (\Throwable $e) {
                // the database is still starting
            }
            if (time() >= $until) return false;
            sleep(2);
        } while (true);
    }

    private function sqlFile($file)
    {
        $sql = preg_replace(array('#/\*.*?\*/#s', '/^\s*--.*$/m'), '', (string) file_get_contents($file));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            try {
                Database::execute($statement);
            } catch (\Throwable $e) {
                // ALTER TABLE ... ADD a column, an index or a foreign key that the last run added: already done
                if (!self::alreadyApplied($e)) throw $e;
            }
        }
    }

    /**
     * Whether a statement failed only because what it adds is already there (MySQL and MariaDB: duplicate column 1060,
     * duplicate key name 1061, duplicate foreign key 1826), so a changed create_db.sql can keep its ALTER TABLE ... ADD
     * statements and run again
     */
    public static function alreadyApplied(\Throwable $e)
    {
        $code = ($e instanceof \PDOException && isset($e->errorInfo[1])) ? (int) $e->errorInfo[1] : 0;
        return in_array($code, array(1060, 1061, 1826), true)
            || (bool) preg_match('/\b(Duplicate column name|Duplicate key name|Duplicate foreign key constraint name)\b/i', $e->getMessage());
    }

    private function line($status, $name, $message)
    {
        $this->lines[] = array($status, $name, $message);
        printf("  %-4s  %s: %s\n", $status, $name, $message);
    }

    private function done($code)
    {
        print("\n");
        return $code;
    }
}
