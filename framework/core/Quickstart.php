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
 * 2. .env: the database of the Docker server (DB_ACTIVATE=true, DB_HOST=mysql, a database name), or, on a local server
 *    (MAMP, XAMPP, LAMP), the address of the project in its web folder (APP_ROOT) and its MySQL settings (asked with the
 *    stack's defaults; without a terminal to confirm them, skipped unless --db-defaults)
 * 3. the database and the framework tables (tokens, audit log)
 * 4. web.php: Route::capabilities('/capabilities') for the JS client and Route::mcp('/mcp') for AI agents
 * 5. with --example: the Tasks example (capabilities, page, REST routes, table, JS client)
 */
class Quickstart
{
    const EXAMPLE = 'framework/settings/examples/tasks';

    /*
     * Local servers quickstart knows: their web folder, the address of that folder and their MySQL defaults.
     * A project inside one of these folders is served at <url>/<its path in the folder>.
     */
    const LOCAL_STACKS = array(
        array('name' => 'MAMP', 'root' => '/Applications/MAMP/htdocs', 'url' => 'http://localhost:8888',
            'db' => array('host' => '127.0.0.1', 'port' => '8889', 'user' => 'root', 'password' => 'root')),
        array('name' => 'XAMPP', 'root' => '/Applications/XAMPP/xamppfiles/htdocs', 'url' => 'http://localhost',
            'db' => array('host' => '127.0.0.1', 'port' => '3306', 'user' => 'root', 'password' => '')),
        array('name' => 'XAMPP', 'root' => '/opt/lampp/htdocs', 'url' => 'http://localhost',
            'db' => array('host' => '127.0.0.1', 'port' => '3306', 'user' => 'root', 'password' => '')),
        array('name' => 'LAMP', 'root' => '/var/www/html', 'url' => 'http://localhost',
            'db' => array('host' => 'localhost', 'port' => '3306', 'user' => 'root', 'password' => '')),
        array('name' => 'MAMP', 'root' => 'C:/MAMP/htdocs', 'url' => 'http://localhost',
            'db' => array('host' => '127.0.0.1', 'port' => '3306', 'user' => 'root', 'password' => 'root')),
        array('name' => 'XAMPP', 'root' => 'C:/xampp/htdocs', 'url' => 'http://localhost',
            'db' => array('host' => '127.0.0.1', 'port' => '3306', 'user' => 'root', 'password' => '')),
    );
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
        $base = $docker ? 'http://localhost:8080' : '<your site>';
        if (!$docker) {
            list($env, $base) = $this->localServer($env, $options);
            file_put_contents($envFile, $env);
        }
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
        if (!$docker && !$dbReady && strtolower(self::envValue($env, 'DB_ACTIVATE')) === 'true' && !empty($this->askedDatabase)) {
            // the settings quickstart wrote do not work: turn the database off again so that the pages still open
            file_put_contents($envFile, $env = self::envSet($env, array('DB_ACTIVATE' => 'false')));
            $this->say('        .env: DB_ACTIVATE=false again. Fix the DB_* settings in .env, set DB_ACTIVATE=true, then run quickstart again');
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
            "Route::page('/tasks', 'Tasks/index');   // the web page (public/js/tasks.js)",
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

    /** @var bool whether quickstart wrote the database settings of .env in this run */
    protected $askedDatabase = false;

    /**
     * A local server (MAMP, XAMPP, LAMP): the project's address and APP_ROOT from its place in the web folder, and the
     * MySQL settings when .env has none yet
     * @return array [the new .env, the base address of the project]
     */
    protected function localServer($env, array $options)
    {
        $site = self::localSite((string) realpath($this->root), self::stacks());
        if (!empty($options['url'])) {
            $path = trim((string) parse_url($options['url'], PHP_URL_PATH), '/');
            $site = array('name' => $site['name'] ?? 'your server', 'url' => rtrim($options['url'], '/'), 'appRoot' => $path,
                'db' => $site['db'] ?? null);
        }
        if ($site === null) {
            $this->say('  NOTE  Local server: this folder is not in the web folder of MAMP, XAMPP or LAMP. Move it there (for example');
            $this->say('        /Applications/MAMP/htdocs), give its address with --url http://localhost/<folder>, or use Docker:');
            $this->say('        .bin/caligrafer quickstart --docker');
            $base = '<your site>';
        } else {
            $env = self::envSet($env, array('APP_ROOT' => $site['appRoot']));
            $base = $site['url'];
            $this->say("  OK    Local server: {$site['name']}, $base (APP_ROOT=" . ($site['appRoot'] === '' ? 'empty' : $site['appRoot']) . ')');
        }
        $configured = strtolower(self::envValue($env, 'DB_ACTIVATE')) === 'true'
            && !in_array(self::envValue($env, 'DB_DATABASE'), array('', 'databasename'), true);
        if (!$configured && ($options['db'] ?? true) && !$this->interactive() && empty($options['dbDefaults'])) {
            // no one to confirm: never write database settings that could reach another database on this computer
            $this->say('  SKIP  Database: no terminal to confirm the MySQL settings. Run quickstart in a terminal, set the DB_* settings');
            $this->say('        in .env (DB_ACTIVATE=true), or pass --db-defaults to use the server\'s defaults');
        } elseif (!$configured && ($options['db'] ?? true)) {
            $defaults = $site['db'] ?? array('host' => '127.0.0.1', 'port' => '3306', 'user' => 'root', 'password' => '');
            $name = strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', basename((string) realpath($this->root)))) ?: self::DEFAULT_DATABASE;
            $settings = array(
                'DB_ACTIVATE' => 'true',
                'DB_HOST' => $this->ask('MySQL host', $defaults['host']),
                'DB_PORT' => $this->ask('MySQL port', $defaults['port']),
                'DB_USERNAME' => $this->ask('MySQL user', $defaults['user']),
                'DB_PASSWORD' => $this->ask('MySQL password', $defaults['password'], true),
                'DB_DATABASE' => $this->ask('Database name', substr($name, 0, 64)),
            );
            $env = self::envSet($env, $settings);
            $this->askedDatabase = true;
            $this->say('  OK    .env: the MySQL of ' . ($site['name'] ?? 'this computer') . " ({$settings['DB_HOST']}:{$settings['DB_PORT']}, database {$settings['DB_DATABASE']})");
        }
        return array($env, $base);
    }

    /**
     * LOCAL_STACKS with what MAMP is really set to on this computer, when it is installed: its web folder (Apache's
     * DocumentRoot), its address (Listen) and its MySQL port (--port). Many installs serve another folder than htdocs
     * and use 80 and 3306 instead of 8888 and 8889
     */
    public static function stacks($mamp = '/Applications/MAMP')
    {
        $stacks = self::LOCAL_STACKS;
        $httpd = @file_get_contents("$mamp/conf/apache/httpd.conf");
        $mysql = @file_get_contents("$mamp/bin/startMysql.sh");
        foreach ($stacks as $i => $stack) {
            if ($stack['root'] !== '/Applications/MAMP/htdocs') continue;
            $stacks[$i]['root'] = "$mamp/htdocs";
            if ($httpd && preg_match('/^\s*DocumentRoot\s+"?([^"\r\n]+?)"?\s*$/m', $httpd, $m)) {
                $stacks[$i]['root'] = rtrim($m[1], '/');
            }
            if ($httpd && preg_match('/^\s*Listen\s+(?:[\d.]+:)?(\d+)/m', $httpd, $m)) {
                $stacks[$i]['url'] = 'http://localhost' . ($m[1] === '80' ? '' : ':' . $m[1]);
            }
            if ($mysql && preg_match('/--port=(\d+)/', $mysql, $m)) {
                $stacks[$i]['db']['port'] = $m[1];
            }
        }
        return $stacks;
    }

    /**
     * Where a folder is served by a known local server: ['name', 'url', 'appRoot', 'db'], or null outside their web folders
     * @param string $dir the real path of the project
     * @param array $stacks LOCAL_STACKS
     */
    public static function localSite($dir, array $stacks)
    {
        $dir = rtrim(str_replace('\\', '/', (string) $dir), '/');
        foreach ($stacks as $stack) {
            $root = rtrim(str_replace('\\', '/', (string) (realpath($stack['root']) ?: $stack['root'])), '/');
            if ($dir === $root || strpos(strtolower($dir) . '/', strtolower($root) . '/') === 0) {
                $relative = trim(substr($dir, strlen($root)), '/');
                return array('name' => $stack['name'], 'url' => $stack['url'] . ($relative === '' ? '' : '/' . $relative),
                    'appRoot' => $relative, 'db' => $stack['db']);
            }
        }
        return null;
    }

    /**
     * Whether someone can answer at the terminal
     */
    protected function interactive()
    {
        return defined('STDIN') && function_exists('stream_isatty') && @stream_isatty(STDIN);
    }

    /**
     * A setting the person may change: asked when the terminal can answer, else the default
     */
    protected function ask($question, $default, $secret = false)
    {
        if (!$this->interactive()) {
            return $default;
        }
        $shown = $secret ? ($default === '' ? 'none' : 'default of the server') : $default;
        print("  ?     $question [$shown]: ");
        if ($secret) @system('stty -echo 2>/dev/null');
        $answer = fgets(STDIN);
        if ($secret) { @system('stty echo 2>/dev/null'); print("\n"); }
        $answer = trim((string) $answer);
        return $answer === '' ? $default : $answer;
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
        $tries = is_file('/.dockerenv') ? 30 : 3;   // the Docker database may still be starting; a local one is up or not
        for ($i = 0; $i < $tries; $i++) {
            try {
                $pdo = new \PDO($dsn, self::envValue($env, 'DB_USERNAME'), self::envValue($env, 'DB_PASSWORD'), array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 3));
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4");
                $this->say("  OK    Database: $name ready on $host");
                return true;
            } catch (\PDOException $e) {
                $error = $e->getMessage();
                if ($i < $tries - 1) sleep(2);
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
