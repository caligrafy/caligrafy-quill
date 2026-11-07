<?php
/**
 * Migrations.php
 * Database changes as numbered SQL files, each run once, in order
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Migrations
 * application/migrations/<number>_<name>.sql: php caligrafer.php migrate runs, in number order, the files that never
 * ran on this database, and records each one in caligrafy_migrations (its name, its sha256, when). A file is never run
 * twice: change the database with a new file, not by editing one that ran (status shows it as "changed", and deploy
 * warns). The deploy step (php caligrafer.php deploy) runs them after the create_db.sql scripts. Forward only: undo a
 * change with a new migration.
 *
 * The statements of a file are split on ; outside quotes and comments. A file stops at its first failing statement:
 * it is not recorded, the statements before it stay applied (MySQL commits a CREATE or ALTER at once), so write each
 * file so that it can run again (IF NOT EXISTS), or keep one change per file.
 */
class Migrations
{
    const FOLDER = 'application/migrations';
    const TABLE = 'caligrafy_migrations';
    const PATTERN = '/^(\d{1,14})_([A-Za-z0-9_]+)\.sql$/';

    private $root;

    public function __construct($root = '.')
    {
        $this->root = rtrim($root, '/');
    }

    /**
     * The migration files, in order: name => path (relative to the project)
     */
    public function files()
    {
        $found = array();
        foreach (glob($this->root . '/' . self::FOLDER . '/*.sql') ?: array() as $file) {
            $name = basename($file);
            if (!preg_match(self::PATTERN, $name, $m)) {
                throw new \InvalidArgumentException("$name: a migration is named <number>_<name>.sql, e.g. 20261107120000_add_books_isbn.sql (php caligrafer.php migrate make add_books_isbn)");
            }
            $found[$name] = array((int) $m[1], self::FOLDER . '/' . $name);
        }
        uksort($found, function ($a, $b) use ($found) {
            return $found[$a][0] <=> $found[$b][0] ?: strcmp($a, $b);
        });
        $numbers = array();
        foreach ($found as $name => $entry) {
            if (isset($numbers[$entry[0]])) {
                throw new \InvalidArgumentException("$name and {$numbers[$entry[0]]} have the same number: give one of them another");
            }
            $numbers[$entry[0]] = $name;
        }
        return array_map(function ($entry) { return $entry[1]; }, $found);
    }

    /**
     * Creates the table that records the migrations that ran
     */
    public static function install()
    {
        Database::execute('CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (`name` VARCHAR(190) NOT NULL, `sha256` CHAR(64) NOT NULL, '
            . '`ran_at` DATETIME NOT NULL, PRIMARY KEY (`name`)) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4');
    }

    /**
     * Every migration with its state: ran, pending, or changed (it ran, then its file was edited)
     * @return array of ['name', 'path', 'state', 'ran_at']
     */
    public function status()
    {
        self::install();
        $ran = array();
        foreach (Database::toArray(Database::execute('SELECT `name`, `sha256`, `ran_at` FROM `' . self::TABLE . '`')) as $row) {
            $ran[$row['name']] = $row;
        }
        $list = array();
        foreach ($this->files() as $name => $path) {
            $hash = hash('sha256', (string) file_get_contents($this->root . '/' . $path));
            $state = !isset($ran[$name]) ? 'pending' : ($ran[$name]['sha256'] === $hash ? 'ran' : 'changed');
            $list[] = array('name' => $name, 'path' => $path, 'state' => $state, 'ran_at' => $ran[$name]['ran_at'] ?? null);
        }
        return $list;
    }

    /**
     * Runs the pending migrations, in order; stops at the first that fails (the exception names it)
     * @return array the names that ran
     */
    public function run(?callable $say = null)
    {
        $say = $say ?: function ($line) {};
        $done = array();
        foreach ($this->status() as $m) {
            if ($m['state'] === 'changed') {
                $say("WARN {$m['name']}: changed after it ran, not run again (write a new migration for the change)");
            }
            if ($m['state'] !== 'pending') {
                continue;
            }
            $sql = (string) file_get_contents($this->root . '/' . $m['path']);
            foreach (self::statements($sql) as $i => $statement) {
                try {
                    Database::execute($statement);
                } catch (\Throwable $e) {
                    throw new \RuntimeException("{$m['name']}, statement " . ($i + 1) . ': ' . $e->getMessage(), 0, $e);
                }
            }
            Database::execute('INSERT INTO `' . self::TABLE . '` (`name`, `sha256`, `ran_at`) VALUES (?, ?, ?)', array($m['name'], hash('sha256', $sql), date('Y-m-d H:i:s')));
            $say("OK   {$m['name']}");
            $done[] = $m['name'];
        }
        return $done;
    }

    /**
     * Writes a new, empty migration: application/migrations/<YYYYMMDDHHMMSS>_<name>.sql
     * @return string its path
     */
    public function make($name, $at = null)
    {
        $name = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', (string) $name), '_'));
        if ($name === '') {
            throw new \InvalidArgumentException('Usage: php caligrafer.php migrate make <name>, e.g. add_books_isbn');
        }
        $folder = $this->root . '/' . self::FOLDER;
        if (!is_dir($folder) && !mkdir($folder, 0775, true)) {
            throw new \RuntimeException("$folder could not be created");
        }
        $path = self::FOLDER . '/' . date('YmdHis', $at ?? time()) . "_$name.sql";
        if (file_exists($this->root . '/' . $path)) {
            throw new \RuntimeException("$path already exists");
        }
        file_put_contents($this->root . '/' . $path, "-- $name\n-- Runs once (php caligrafer.php migrate, and at every deploy). Never edit it after it ran: write a new one.\n\n");
        return $path;
    }

    /**
     * The statements of a SQL script: split on ; outside quotes ('...', "...", `...`) and comments (--, #, /* *\/),
     * comments removed, empty statements dropped
     */
    public static function statements($sql)
    {
        $sql = (string) $sql;
        $out = array();
        $current = '';
        $quote = null;
        $n = strlen($sql);
        for ($i = 0; $i < $n; $i++) {
            $c = $sql[$i];
            $next = $i + 1 < $n ? $sql[$i + 1] : '';
            if ($quote !== null) {
                $current .= $c;
                if ($c === '\\' && $quote !== '`' && $next !== '') {
                    $current .= $next;
                    $i++;
                } elseif ($c === $quote) {
                    if ($next === $quote) {          // '' inside '...'
                        $current .= $next;
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
                $current .= $c;
            } elseif (($c === '-' && $next === '-' && ($i + 2 >= $n || ctype_space($sql[$i + 2]))) || $c === '#') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $n : $end - 1;
            } elseif ($c === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $n : $end + 1;
                $current .= ' ';
            } elseif ($c === ';') {
                $out[] = trim($current);
                $current = '';
            } else {
                $current .= $c;
            }
        }
        $out[] = trim($current);
        return array_values(array_filter($out, function ($s) { return $s !== ''; }));
    }
}
