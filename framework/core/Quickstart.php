<?php
/**
 * Quickstart.php
 * One command from a fresh project to a working build-once application (php caligrafer.php quickstart)
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Quickstart
 * Every step can be run again: it only does what is missing.
 * 1. application/ (initialize; it deletes the .git folder of the framework, so it needs --yes when one exists)
 * 2. .env: the database of the Docker server (DB_ACTIVATE=true, DB_HOST=mysql, a database name)
 * 3. the database and the framework tables (tokens, audit log)
 * 4. web.php: Route::capabilities('/capabilities') for the JS client and Route::mcp('/mcp') for AI agents
 * 5. with --example: the Tasks example (capabilities, page, REST routes, table, JS client)
 */
class Quickstart
{
    const EXAMPLE = 'framework/settings/examples/tasks';
    const DEFAULT_DATABASE = 'caligrafy';

    /** @var string the project root */
    protected $root;

    /** @var callable prints one line */
    protected $out;

    public function __construct($root = '.', ?callable $out = null)
    {
        $this->root = rtrim($root, '/');
        $this->out = $out ?? function ($line) { print($line . "\n"); };
    }

    /**
     * Runs the steps
     * @param array $options example (bool), yes (bool: allowed to delete .git), db (bool: false skips the database steps)
     * @return int exit code
     */
    public function run(array $options = array())
    {
        $docker = is_file('/.dockerenv');
        $this->say('');
        $this->say('Caligrafy quickstart' . ($docker ? ' (Docker server)' : ''));

        if (!is_dir($this->root . '/application')) {
            if (is_dir($this->root . '/.git') && empty($options['yes'])) {
                $this->say('  STOP  initialize deletes the .git folder of the framework (your project starts its own history).');
                $this->say('        Run again with --yes to continue, or copy the project elsewhere first.');
                return 1;
            }
            $this->step('Project', $this->php('initialize' . ($docker ? ' off' : '')) === 0 && is_dir($this->root . '/application'),
                'application/ and .env created, keys generated', 'php caligrafer.php initialize failed');
            if (!is_dir($this->root . '/application')) {
                return 1;
            }
        } else {
            $this->say('  OK    Project: application/ already exists');
        }

        $envFile = $this->root . '/.env';
        $env = is_file($envFile) ? file_get_contents($envFile) : '';
        if ($docker) {
            $changes = array('DB_ACTIVATE' => 'true', 'DB_HOST' => 'mysql');
            if (in_array(self::envValue($env, 'DB_DATABASE'), array('', 'databasename'), true)) {
                $changes['DB_DATABASE'] = self::DEFAULT_DATABASE;
            }
            $env = self::envSet($env, $changes);
            file_put_contents($envFile, $env);
            $this->say('  OK    .env: the database of the Docker server (' . self::envValue($env, 'DB_DATABASE') . ')');
        }

        $dbReady = false;
        if (($options['db'] ?? true) && strtolower(self::envValue($env, 'DB_ACTIVATE')) === 'true') {
            $dbReady = $this->createDatabase($env);
            if ($dbReady) {
                $this->step('Framework tables', $this->php('platform install') === 0, 'personal access tokens and the audit log', 'php caligrafer.php platform install failed');
            }
        } else {
            $this->say('  SKIP  Database: set DB_ACTIVATE=true and the DB_* settings in .env, then run quickstart again');
        }

        $routes = $this->root . '/application/config/routing/web.php';
        $source = file_get_contents($routes);
        $updated = self::addRoutes($source, array(
            "Route::capabilities('/capabilities');   // the capabilities for the JS client of the Vue apps",
            "Route::mcp('/mcp');                     // the capabilities as tools for AI agents (personal access tokens)",
        ), 'BUILD ONCE: every capability, for the JS client and AI agents');
        if ($updated !== $source) {
            file_put_contents($routes, $updated);
        }
        $this->say('  OK    Routes: /capabilities (JS client) and /mcp (AI agents)');

        if (!empty($options['example'])) {
            $this->example($dbReady);
        }

        $base = $docker ? 'http://localhost:8080' : '<your site>';
        $this->say('');
        $this->say('Ready. Next:');
        if (!empty($options['example'])) {
            $this->say("  - open $base/tasks: the same tasks over REST ($base/api/tasks) and MCP ($base/mcp)");
        }
        $this->say('  - write a capability: php caligrafer.php service Books/CreateBook --scope books:write');
        $this->say('  - let an AI agent use it: php caligrafer.php token create "my agent" --scopes "*"');
        $this->say("    claude mcp add --transport http myapp $base/mcp --header \"Authorization: Bearer <token>\"");
        $this->say('  - check everything: php caligrafer.php doctor');
        $this->say('');
        return 0;
    }

    /**
     * The Tasks example: files that do not exist yet, the table, the routes, the JS client
     */
    protected function example($dbReady)
    {
        $source = $this->root . '/' . self::EXAMPLE;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        $added = 0;
        $kept = 0;
        foreach ($files as $file) {
            $relative = substr((string) $file, strlen($source) + 1);
            $target = $this->root . '/' . $relative;
            if (file_exists($target)) {
                $kept++;
                continue;
            }
            @mkdir(dirname($target), 0755, true);
            copy((string) $file, $target);
            $added++;
        }
        $this->say("  OK    Example: $added file(s) added" . ($kept ? ", $kept already there (kept)" : '') . ' (application/services/Tasks, models/Tasks, views/Tasks, public/js/tasks.js)');

        if ($dbReady) {
            $this->step('Example table', $this->php('sql ' . escapeshellarg('application/models/Tasks/create_db.sql')) === 0, 'tasks', 'the tasks table could not be created');
        }

        $routes = $this->root . '/application/config/routing/web.php';
        $current = file_get_contents($routes);
        $updated = self::addRoutes($current, array(
            "Route::get('/tasks', function () { return view('Tasks/index'); });   // the web page (public/js/tasks.js)",
            "Route::capability('GET', '/api/tasks', 'list_tasks');                // REST",
            "Route::capability('GET', '/api/tasks/{id}', 'get_task');",
            "Route::capability('POST', '/api/tasks', 'create_task');",
            "Route::capability('PUT', '/api/tasks/{id}', 'update_task');",
            "Route::capability('DELETE', '/api/tasks/{id}', 'delete_task');",
        ), 'TASKS EXAMPLE: one set of capabilities (application/services/Tasks) served to every channel');
        if ($updated !== $current) {
            file_put_contents($routes, $updated);
        }
        $this->step('Example client', $this->php('client public/js/capabilities.js') === 0, 'public/js/capabilities.js generated', 'php caligrafer.php client failed');
    }

    /**
     * Creates the database of .env when it does not exist (waits up to 60 s for the server to accept connections)
     */
    protected function createDatabase($env)
    {
        $name = self::envValue($env, 'DB_DATABASE');
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
            $this->say("  FAIL  Database: DB_DATABASE '$name' must be letters, digits and _");
            return false;
        }
        $driver = self::envValue($env, 'DB_CONNECTION') ?: 'mysql';
        $host = self::envValue($env, 'DB_HOST');
        $port = self::envValue($env, 'DB_PORT');
        $dsn = "$driver:host=$host" . ($port !== '' ? ";port=$port" : '');
        $error = '';
        for ($i = 0; $i < 30; $i++) {
            try {
                $pdo = new \PDO($dsn, self::envValue($env, 'DB_USERNAME'), self::envValue($env, 'DB_PASSWORD'), array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 3));
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4");
                $this->say("  OK    Database: $name ready on $host");
                return true;
            } catch (\PDOException $e) {
                $error = $e->getMessage();
                sleep(2);
            }
        }
        $this->say("  FAIL  Database: could not reach $host ($error). Is the server running? php caligrafer.php doctor --only database");
        return false;
    }

    /**
     * Inserts lines before Route::run() in a web.php, under a comment, unless every line is already there
     * @param string $source the content of web.php
     * @param array $lines the route lines (without indentation)
     * @param string $comment one line that says what they are
     * @return string the new content (the same when nothing was missing)
     */
    public static function addRoutes($source, array $lines, $comment)
    {
        $missing = array_values(array_filter($lines, function ($line) use ($source) {
            $code = trim(explode('//', $line, 2)[0]);
            return strpos(preg_replace('/\s+/', '', $source), preg_replace('/\s+/', '', $code)) === false;
        }));
        if (!$missing) {
            return $source;
        }
        if (!preg_match('/^([ \t]*)Route::run\(\);/m', $source, $m, PREG_OFFSET_CAPTURE)) {
            throw new \RuntimeException('Route::run(); not found in web.php');
        }
        $indent = $m[1][0];
        $block = $indent . '// ' . $comment . "\n";
        foreach ($missing as $line) {
            $block .= $indent . $line . "\n";
        }
        $at = $m[0][1];
        // keep the comment that introduces Route::run() above it
        $lineStart = strrpos(substr($source, 0, $at), "\n", -2);
        $previous = $lineStart === false ? '' : trim(substr($source, $lineStart, $at - $lineStart));
        if (strpos($previous, '//') === 0) {
            $at = $lineStart + 1;
        }
        return substr($source, 0, $at) . $block . "\n" . substr($source, $at);
    }

    /**
     * Sets KEY=value lines in the content of a .env (replaces the line, or adds it at the end)
     */
    public static function envSet($env, array $values)
    {
        foreach ($values as $key => $value) {
            $line = $key . '=' . $value;
            if (preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $env)) {
                $env = preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', str_replace('$', '\\$', $line), $env, 1);
            } else {
                $env = rtrim($env, "\n") . "\n" . $line . "\n";
            }
        }
        return $env;
    }

    /**
     * The value of a KEY in the content of a .env (without an inline # comment), '' when it is not set
     */
    public static function envValue($env, $key)
    {
        if (!preg_match('/^' . preg_quote($key, '/') . '=(.*)$/m', (string) $env, $m)) {
            return '';
        }
        return trim(preg_replace('/\s+#.*$/', '', $m[1]), " \t\"'");
    }

    protected function php($arguments)
    {
        passthru(escapeshellarg(PHP_BINARY) . ' caligrafer.php ' . $arguments . ' > /dev/null 2>&1', $code);
        return $code;
    }

    protected function step($name, $ok, $success, $failure)
    {
        $this->say(($ok ? '  OK    ' : '  FAIL  ') . $name . ': ' . ($ok ? $success : $failure));
    }

    protected function say($line)
    {
        ($this->out)($line);
    }
}
