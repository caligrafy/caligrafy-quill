#!/usr/bin/env php
<?php
/**
 * index.php
 * Run time where all magic happens
 * @author Dory A.Azar
 * @copyright 2019
 * @version 1.0
 *
*/

use Caligrafy\Caligrafer;

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// version: read-only, needs neither .env nor vendor/
if (in_array(strtolower($argv[1] ?? ''), ['version', '--version', '-v'])) {
	require_once 'framework/core/Framework.php';
	print("Caligrafy Quill " . Caligrafy\Framework::VERSION . "\n");
	exit;
}

require_once "framework/core/Caligrafer.php";

// load external vendors
require_once 'vendor/autoload.php';
       
// load environment variables
$isDoctor = strtolower($argv[1] ?? '') === 'doctor'; // read-only: never creates .env
if (!file_exists('.env') && !$isDoctor) {
	system("cp framework/settings/.env.example .env");
}
// doctor --env <file> checks another environment file (for example .env.production)
$envAt = array_search('--env', $argv);
$envFile = $envAt !== false && isset($argv[$envAt + 1])? $argv[$envAt + 1] : '.env';
if (is_file($envFile)) {
	$dotenv = Dotenv\Dotenv::createUnsafeMutable(dirname(realpath($envFile)), basename($envFile));
	$dotenv->load();
} elseif ($envAt !== false || !$isDoctor) {
	print("\n Environment file not found: $envFile\n\n");
	exit(1);
}

$restricted = ['app', 'bot', '__bots__', 'css', 'facedetect', 'fonts', 'images', 'js', 'ml', 'resources', 'uploads'];

Caligrafer::run();

$defaultMsg = "\n Available Functions: 
				\n - quickstart [--example] [--yes]: sets up a fresh project in one step (database, framework tables, /capabilities and /mcp); --example adds the Tasks example. With Docker: .bin/caligrafer quickstart
				\n - version: prints the version of Caligrafy Quill
				\n - initialize: Initializes and signs your project
				\n - update [<version>] [--check] [--yes] [--no-composer]: moves the project to another release of Caligrafy Quill (the latest by default) and applies everything: framework/, the release's other files (untouched ones replaced, changed ones kept with a .caligrafy-new), composer.json, .gitignore and new .env settings merged, then upgrade --apply; backups in backups/
				\n - plugin: gives this project's Claude Code plugin its own source name and says where Claude Code reads it
				\n - upgrade [--apply] [--local] [--force <application/path>] [--keep <application/path>]: brings the project up to date (dry run by default). When a newer release of Caligrafy Quill is out, it moves the project to it like update; otherwise (or with --local) it compares application/ with the framework templates and updates the untouched files; also reports .env settings and Bootstrap 3 classes
				\n - doctor [--only openai,groq] [--json] [--env <file>]: read-only health check of the project and of the services configured in .env
				\n - design <list [--json] | show <name> | use <name> | off | status>: picks a design style (DESIGN_STYLE in .env) (framework/design, application/design); empty = Caligrafy's own look
				\n - server <start | stop | rebuild>: Starts a Caligrafy local LAMP server with phpmyadmin (requires PHP, Composer and Docker); rebuild updates the server image
				\n - sql <file.sql>: runs a SQL script on the database of .env
				\n - platform install: creates the framework tables (personal access tokens, audit log)
				\n - token <create | list | revoke>: personal access tokens for REST and MCP clients (scoped, revocable)
				\n - audit [--limit 50] [--capability <name>]: the latest capability calls
				\n - service <Name> [--scope books:write] [--read-only]: creates a capability in application/services/
				\n - client [<file>] [--prefix /capabilities]: writes the JS client of the capabilities (default public/js/capabilities.js)
				\n - capabilities [--json | --markdown]: the capabilities of application/services/ (what REST, MCP and the JS client can call)
				\n - call <capability> ['{\"key\": \"value\"}' | key=value ...] [--token <token>] [--guest]: calls a capability and prints its JSON answer (full local access unless --token or --guest)
				\n - schedule <list [--json] | run <job> [--if-due] | due | work | history [<job>] [--limit 50]>: scheduled jobs of application/config/schedule.php (capabilities on a timer); work runs them every minute while you develop
				\n - notify <keys [--subject mailto:you@example.com] | test <user id> | status <user id>>: notifications (Caligrafy\Notify): keys writes the web push (VAPID) keys to .env once, test sends a test message to a user
				\n - deploy [--wait <seconds>]: prepares the database in production: framework tables and every new or changed application/models/<folder>/create_db.sql (never one that drops or deletes data twice)
				\n - cloud <login | create | deploy | apps | status | logs | extend | credits | secret | delete>: puts the project online at https://<name>.caligrafy.com with Caligrafy Cloud (cloud help)
				\n - mcp [--token <token>]: MCP server over stdio for local AI agents (Claude Code, Cursor...): php caligrafer.php mcp
				\n - generatekeys: generates an APP and an API pair of keys that you can put in your app's environment variable 
				\n - generateapikey: generates an API key that you can provide to any third party desiring to access your services
				\n - create <project_name>: scaffolds a Vue project
				\n - app <project_name>: scaffolds a regular Client HTML/JS project
				\n - bot <bot_name>: creates a bot application
				\n - facedetect <app_name>: creates a face detection application
				\n - ml <app_name>: creates an ml powered application
				
				\n\n";

if($argc < 2) {
    print($defaultMsg);
    exit;
}

/**
 * Connects to the database of .env (DB_ACTIVATE must be true)
 */
function caligrafyConnect()
{
    if (!Caligrafy\Database::connect()) {
        throw new Exception('The database is not active: set DB_ACTIVATE=true and the DB_* settings in .env');
    }
}

/**
 * Runs the statements of a .sql file on the database of .env (comments removed, split on ;)
 */
function caligrafySqlFile($path)
{
    if (!is_file($path)) {
        throw new Exception("No such file: $path");
    }
    $sql = preg_replace(array('#/\*.*?\*/#s', '/^\s*--.*$/m'), '', file_get_contents($path));
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        Caligrafy\Database::execute($statement);
    }
}

/**
 * Docker Compose command: the Compose v2 plugin (docker compose) when available, else the legacy docker-compose
 */
function caligrafyCompose()
{
    exec('docker compose version 2>/dev/null', $out, $code);
    return $code === 0 ? 'docker compose' : 'docker-compose';
}

switch(strtolower($argv[1])) {
        
    case 'generatekeys':
        try {
           $keys = Caligrafer::generateKeys(); 
           $appKey = isset($keys['APP_KEY'])? $keys['APP_KEY'] : null;
           $apiKey = isset($keys['API_KEY'])? $keys['API_KEY'] : null;
            print ("\n APP_KEY=".$appKey);
            print("\n API_KEY=".$apiKey);
            print("\n\n");
        } catch (Exception $e) {
            print($e->getMessage());
        }
        break;
    case 'generateapikey':
        $apiKey = Caligrafer::generateApiKey();
        print("\n API_KEY=".$apiKey);
        print("\n\n");
        break;
	case 'initialize':
		try {
		   // any word after initialize (the launcher's "off": Docker serves the project) means a root install; --yes is an option
		   $words = array_values(array_filter(array_slice($argv, 2), function ($a) { return $a !== '' && $a[0] !== '-'; }));
		   $appRoot = $words ? "" : basename(getcwd());
		   print("\n\nPreparing and signing the project for usage... \n");
		   $file = '.env';
		   $current = file_get_contents($file);
		   // keep the keys of a project that is already signed: new keys would invalidate API keys, sessions and stored data
		   $existingAppKey = preg_match('/^APP_KEY=(\S+)/m', $current, $m)? $m[1] : null;
		   $existingApiKey = preg_match('/^API_KEY=(\S+)/m', $current, $m)? $m[1] : null;
		   if ($existingAppKey && $existingApiKey) {
		       $appKey = $existingAppKey;
		       $apiKey = $existingApiKey;
		       $input = $current;
		       print("\n Existing APP_KEY and API_KEY kept");
		   } else {
               $keys = Caligrafer::generateKeys(); 
               $appKey = isset($keys['APP_KEY'])? $keys['APP_KEY'] : null;
               $apiKey = isset($keys['API_KEY'])? $keys['API_KEY'] : null;
		       $current = preg_replace('/^(APP_KEY|API_KEY)=.*\R?/m', '', $current);
		       $input = "APP_KEY=".$appKey."\n"."API_KEY=".$apiKey."\n". $current;
		   }
		   // APP_ROOT always reflects the current folder (or the root-install argument)
		   $input = "APP_ROOT=".$appRoot."\n".preg_replace('/^APP_ROOT=.*\R?/m', '', $input);
		   $vueInput = "VITE_APP_KEY=".$appKey."\n"."VITE_API_KEY=".$apiKey."\n";
		   file_put_contents($file, $input);
		   file_put_contents(LIB_PATH . 'app/' . $file, $vueInput);
		   if (!is_dir("./application")) {
			system('cp -r framework/settings/application ./application', $retValue);
			if ($retValue !== 0 || !is_dir("./application")) {
				print("\n\n Initialization failed: framework/settings/application could not be copied to ./application.\n Check that the framework folder is complete, then run 'php caligrafer.php initialize' again.\n\n");
				exit(1);
			}
			// records the template versions so that 'upgrade' can tell untouched files from customized ones
			Caligrafy\Upgrader::writeManifest('.');
		   }
		   system('chmod -R 777 public/uploads');
		   // the skill for contributors to the framework (.claude/skills/caligrafy-contributor) has no place in a project
		   if (is_dir('.claude/skills/caligrafy-contributor')) {
			system('rm -rf .claude/skills/caligrafy-contributor');
			@rmdir('.claude/skills');
			print("\n Contributor skill removed (.claude/skills/caligrafy-contributor: for the framework repository only)");
		   }
		   // the Claude Code plugin of this project gets its own source name (one shared name breaks every other project)
		   if ($pluginSource = Caligrafy\PluginSource::ensureOwn('.')) {
			print("\n Claude Code plugin source: $pluginSource (enable caligrafy-quill@$pluginSource when Claude Code asks)");
		   }

		   // the .git folder of the framework goes only when someone says so: --yes, or yes to the question in a terminal
		   if (is_dir("./.git")) {
			$deleteGit = in_array('--yes', $argv, true);
			if (!$deleteGit && function_exists('posix_isatty') && @posix_isatty(STDIN)) {
				print("\n This folder has a .git folder (the framework's history, or your project's). Delete it so that your project starts its own history? [y/N] ");
				$deleteGit = strtolower(trim((string) fgets(STDIN))) === 'y';
			}
			if ($deleteGit) {
				system('chmod -R 777 .git');
				system('rm -R .git');
				print("\n .git deleted: git init starts your project's own history");
			} else {
				print("\n .git kept (php caligrafer.php initialize --yes deletes it)");
			}
		   }
		   print("\n Application initialized successfully");
		   print ("\n APP_KEY=".$appKey);
		   print("\n API_KEY=".$apiKey);
		   print("\n\n");
        } catch (Exception $e) {
            print($e->getMessage());
        }
		break;

	case 'created':
		// run by Composer after composer create-project caligrafy/caligrafy-quill <folder>: the one next command
		$here = realpath('.');
		$site = Caligrafy\Quickstart::localSite($here, Caligrafy\Quickstart::stacks());
		$windows = stripos(PHP_OS_FAMILY, 'Windows') === 0;
		$server = !$windows && trim((string) @shell_exec('pgrep -x httpd 2>/dev/null || pgrep -x apache2 2>/dev/null')) !== '';
		$docker = trim((string) @shell_exec('docker info >/dev/null 2>&1 && echo yes')) === 'yes';
		print("\n Caligrafy Quill " . Caligrafy\Framework::VERSION . " is in " . $here . ". One more command sets it up:\n\n");
		print("   cd " . basename($here) . "\n   " . ($windows ? 'php caligrafer.php quickstart --example' : '.bin/caligrafer quickstart --example') . "\n\n");
		if ($site !== null) {
			print(" It is in the web folder of " . $site['name'] . ": with its Apache and MySQL running, it will be at " . $site['url'] . "\n");
		} elseif ($server) {
			print(" A local web server is running, but this folder is not in its web folder (MAMP: /Applications/MAMP/htdocs,\n XAMPP: htdocs, LAMP: /var/www/html): move it there, or use Docker (quickstart --docker).\n");
		}
		if ($docker) {
			print(" Docker is running: " . ($server || $site !== null ? 'add --docker to use it instead' : 'quickstart uses it') . " (http://localhost:8080).\n");
		} elseif (!$server && $site === null) {
			print(" quickstart needs a server: start MAMP, XAMPP or Apache and MySQL (LAMP) with this folder in its web folder,\n or Docker Desktop.\n");
		}
		print(" Then: https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart\n\n");
		exit(0);

	case 'quickstart':
		// php caligrafer.php quickstart [--example] [--yes]: from a fresh project to a working build-once application
		$urlAt = array_search('--url', $argv);
		exit((new Caligrafy\Quickstart('.'))->run(array('example' => in_array('--example', $argv), 'yes' => in_array('--yes', $argv),
			'db' => !in_array('--no-db', $argv), 'dbDefaults' => in_array('--db-defaults', $argv), 'url' => $urlAt !== false ? ($argv[$urlAt + 1] ?? '') : '')));

	case 'sql':
		// php caligrafer.php sql <file.sql>: runs a SQL script on the database of .env
		if (!isset($argv[2])) {
			print("\n Usage: php caligrafer.php sql <file.sql>\n\n");
			exit(1);
		}
		try {
			caligrafyConnect();
			caligrafySqlFile($argv[2]);
			print("\n " . $argv[2] . " run on " . DB_DATABASE . "\n\n");
		} catch (Throwable $e) {
			print("\n " . $argv[2] . " could not be run: " . $e->getMessage() . "\n\n");
			exit(1);
		}
		break;

	case 'platform':
		if (strtolower($argv[2] ?? '') !== 'install') {
			print("\n Usage: php caligrafer.php platform install\n Creates the framework tables (personal access tokens, audit log) from framework/settings/platform.sql\n\n");
			exit(1);
		}
		try {
			caligrafyConnect();
			caligrafySqlFile('framework/settings/platform.sql');
			print("\n Framework tables ready: caligrafy_tokens, caligrafy_audit, caligrafy_job_runs, and the notifications tables\n\n");
		} catch (Throwable $e) {
			print("\n The framework tables could not be created: " . $e->getMessage() . "\n Check DB_ACTIVATE and the DB_* settings in .env (php caligrafer.php doctor --only database)\n\n");
			exit(1);
		}
		break;

	case 'token':
		$sub = strtolower($argv[2] ?? '');
		$option = function ($name) use ($argv) {
			$at = array_search("--$name", $argv);
			return $at !== false ? ($argv[$at + 1] ?? '') : null;
		};
		try {
			caligrafyConnect();
			if (!Caligrafy\Tokens::installed()) {
				print("\n The token table does not exist yet: run php caligrafer.php platform install\n\n");
				exit(1);
			}
			if ($sub === 'create' && isset($argv[3]) && strpos($argv[3], '--') !== 0) {
				$scopes = Caligrafy\Tokens::normalizeScopes($option('scopes') ?? '');
				if (!$scopes) {
					print("\n A token needs scopes: --scopes books:read,books:write (or * for everything)\n\n");
					exit(1);
				}
				$days = $option('expires');
				$expires = $days !== null && $days !== '' ? Caligrafy\Tokens::expiresAt(time() + 86400 * max(1, (int) $days)) : null;
				$user = $option('user');
				$created = Caligrafy\Tokens::create($argv[3], $scopes, $user !== null && $user !== '' ? (int) $user : null, $expires);
				print("\n Token #{$created['id']} \"{$argv[3]}\" created with " . implode(' ', $scopes) . ($expires ? ", expires $expires" : '') . "\n\n"
					. " " . $created['token'] . "\n\n It is shown only now: store it as a secret (Authorization: Bearer <token>).\n\n");
			} elseif ($sub === 'list') {
				$tokens = Caligrafy\Tokens::all();
				print($tokens ? "\n" : "\n No tokens yet: php caligrafer.php token create <name> --scopes <scopes>\n\n");
				foreach ($tokens as $t) {
					$state = $t['revoked_at'] ? 'revoked' : (Caligrafy\Tokens::expired($t['expires_at']) ? 'expired' : 'active');
					printf(" #%-4d %-8s %-30s %s%s%s\n", $t['id'], $state, $t['name'], implode(' ', $t['scopes']),
						$t['user_id'] ? "  user {$t['user_id']}" : '', $t['last_used_at'] ? "  last used {$t['last_used_at']}" : '  never used');
				}
				if ($tokens) print("\n");
			} elseif ($sub === 'revoke' && isset($argv[3])) {
				print(Caligrafy\Tokens::revoke((int) $argv[3]) ? "\n Token #" . (int) $argv[3] . " revoked\n\n" : "\n No active token #" . (int) $argv[3] . "\n\n");
			} else {
				print("\n Usage:\n php caligrafer.php token create <name> --scopes <a:read,b:write | *> [--user <id>] [--expires <days>]\n php caligrafer.php token list\n php caligrafer.php token revoke <id>\n\n");
				exit(1);
			}
		} catch (Throwable $e) {
			print("\n " . $e->getMessage() . "\n\n");
			exit(1);
		}
		break;

	case 'audit':
		try {
			caligrafyConnect();
			$limitAt = array_search('--limit', $argv);
			$capabilityAt = array_search('--capability', $argv);
			$rows = Caligrafy\Audit::latest($limitAt !== false ? (int) ($argv[$limitAt + 1] ?? 50) : 50, $capabilityAt !== false ? ($argv[$capabilityAt + 1] ?? null) : null);
			print($rows ? "\n" : "\n The audit log is empty (or php caligrafer.php platform install was not run)\n\n");
			foreach ($rows as $r) {
				printf(" %s  %-5s %-6s %-12s %-28s %s%s\n", $r['occurred_at'], $r['channel'], $r['actor_type'], $r['actor_id'] ?? '-', $r['capability'], $r['outcome'], $r['detail'] ? "  {$r['detail']}" : '');
			}
			if ($rows) print("\n");
		} catch (Throwable $e) {
			print("\n " . $e->getMessage() . "\n\n");
			exit(1);
		}
		break;

	case 'mcp':
		// MCP over stdio for local agents: one JSON-RPC message per line on stdin, one answer per line on stdout
		// CALIGRAFY_TOKEN (or --token) acts as that token; without one, full local access like any script
		ini_set('display_errors', 'stderr');
		$tokenAt = array_search('--token', $argv);
		$plain = $tokenAt !== false ? ($argv[$tokenAt + 1] ?? '') : (getenv('CALIGRAFY_TOKEN') ?: '');
		try {
			if (Caligrafy\Database::connect() === null && $plain !== '') {
				throw new Exception('A token needs the database: set DB_ACTIVATE=true');
			}
			if ($plain !== '') {
				$token = Caligrafy\Tokens::verify($plain);
				if (!$token) {
					throw new Exception('The token is unknown, revoked or expired');
				}
				$context = Caligrafy\Context::forToken($token, 'mcp');
			} else {
				$context = Caligrafy\Context::local('mcp');
				fwrite(STDERR, "caligrafer mcp: no token, full local access (set CALIGRAFY_TOKEN to limit it)\n");
			}
		} catch (Throwable $e) {
			fwrite(STDERR, 'caligrafer mcp: ' . $e->getMessage() . "\n");
			exit(1);
		}
		$server = new Caligrafy\Mcp\Server($context);
		while (($line = fgets(STDIN)) !== false) {
			if (trim($line) === '') {
				continue;
			}
			$response = $server->handleJson($line);
			if ($response !== null) {
				fwrite(STDOUT, json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
			}
		}
		exit(0);

	case 'service':
		// php caligrafer.php service Books/CreateBook [--scope books:write] [--read-only]
		$path = trim(str_replace('\\', '/', $argv[2] ?? ''), '/');
		if (!preg_match('#^([A-Z][A-Za-z0-9]*/)*[A-Z][A-Za-z0-9]*$#', $path)) {
			print("\n Usage: php caligrafer.php service <Name> [--scope <resource:action>] [--read-only]\n Name in PascalCase, optionally in folders: Books/CreateBook\n\n");
			exit(1);
		}
		$class = basename($path);
		$file = "application/services/$path.php";
		if (!is_dir('application')) {
			print("\n application/ not found: run php caligrafer.php initialize first\n\n");
			exit(1);
		}
		if (file_exists($file)) {
			print("\n $file already exists\n\n");
			exit(1);
		}
		$name = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class));
		$scopeAt = array_search('--scope', $argv);
		$scope = $scopeAt !== false ? ($argv[$scopeAt + 1] ?? '') : '';
		$readOnly = in_array('--read-only', $argv) ? 'true' : 'false';
		$template = file_get_contents('framework/settings/service.php.template');
		$code = strtr($template, array('{{class}}' => $class, '{{name}}' => $name, '{{scope}}' => $scope, '{{readOnly}}' => $readOnly));
		@mkdir(dirname($file), 0755, true);
		file_put_contents($file, $code);
		print("\n Created $file: capability '$name'" . ($scope ? " (scope $scope)" : '') . "\n Fill in \$rules and handle(), then call it from a controller (\$this->call('$name')), REST (Route::capability) or MCP (Route::mcp)\n\n");
		break;

	case 'client':
		// php caligrafer.php client [<file>] [--prefix /capabilities]: the JS client of the capabilities
		$target = isset($argv[2]) && strpos($argv[2], '--') !== 0 ? $argv[2] : 'public/js/capabilities.js';
		$prefixAt = array_search('--prefix', $argv);
		$prefix = $prefixAt !== false ? ($argv[$prefixAt + 1] ?? '/capabilities') : '/capabilities';
		$code = Caligrafy\Client::generate($prefix);
		@mkdir(dirname($target), 0755, true);
		if (file_put_contents($target, $code) === false) {
			print("\n Could not write $target\n\n");
			exit(1);
		}
		print("\n $target written: " . count(Caligrafy\Capabilities::all()) . " capabilities, calls $prefix (Route::capabilities('$prefix') in web.php)\n"
			. " import { createBook, CapabilityError } from './capabilities.js'\n\n");
		break;

	case 'capabilities':
		$list = Caligrafy\Capabilities::describe();
		if (in_array('--json', $argv)) {
			print(json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
		} elseif (in_array('--markdown', $argv)) {
			print("| Capability | What it does | Scope | Inputs |\n| --- | --- | --- | --- |\n");
			foreach ($list as $c) {
				$inputs = array();
				foreach ((array) $c['input']['properties'] as $field => $p) {
					$inputs[] = '`' . $field . '`' . (in_array($field, $c['input']['required'] ?? array(), true) ? ' (required)' : '');
				}
				printf("| `%s`%s | %s | %s | %s |\n", $c['name'], $c['readOnly'] ? ' (read-only)' : ($c['destructive'] ? ' (destructive)' : ''),
					str_replace('|', '\\|', $c['description']), $c['public'] ? 'public' : ($c['scope'] !== '' ? '`' . $c['scope'] . '`' : 'signed in'), implode(', ', $inputs) ?: '-');
			}
		} else {
			print($list ? "\n" : "\n No capabilities yet: php caligrafer.php service <Name> creates one in application/services/\n\n");
			foreach ($list as $c) {
				printf(" %-28s %-14s %-9s %s\n", $c['name'], $c['public'] ? 'public' : ($c['scope'] ?: 'signed in'), $c['readOnly'] ? 'read' : ($c['destructive'] ? 'destroys' : 'writes'), $c['description']);
			}
			if ($list) print("\n");
		}
		break;

	case 'cloud':
		// Caligrafy Cloud: put the project online at https://<name>.caligrafy.com (php caligrafer.php cloud help)
		exit((new Caligrafy\CloudClient('.'))->run(array_slice($argv, 2)));

	case 'deploy':
		// What the production image runs at start: waits for the database, framework tables, new or changed create_db.sql
		$waitAt = array_search('--wait', $argv);
		exit((new Caligrafy\Deployer('.', $waitAt !== false ? (int) ($argv[$waitAt + 1] ?? 60) : 60))->run());

	case 'schedule':
		// Scheduled jobs (framework/core/Scheduler.php): list validates the file (--json is what Caligrafy Cloud reads),
		// run runs one job now, due runs the jobs of the current minute, work runs due every minute, history the runs.
		// Exit codes: 0 ok, 1 a job failed or timed out, 2 the schedule file is invalid, 3 skipped (still running)
		$scheduler = new Caligrafy\Scheduler('.');
		$say = function ($line) { print(" $line\n"); };
		$sub = strtolower($argv[2] ?? '');
		try {
			switch ($sub) {
				case 'list':
					$jobs = $scheduler->jobs();
					if (in_array('--json', $argv, true)) {
						print(json_encode(Caligrafy\Scheduler::listing($jobs), JSON_UNESCAPED_SLASHES) . "\n");
						exit(0);
					}
					if (!$jobs) {
						print("\n No scheduled jobs (" . Caligrafy\Scheduler::FILE . " declares them)\n\n");
						exit(0);
					}
					print("\n");
					foreach ($jobs as $j) {
						printf(" %-24s %-14s %-24s %s, timeout %ds%s\n", $j['name'], $j['every'] ? "every {$j['every']}" : $j['cron'], $j['capability'],
							$j['every'] ? 'UTC' : $j['timezone'], $j['timeout'], $j['enabled'] ? '' : ' (disabled)');
					}
					print("\n");
					exit(0);
				case 'run':
					if (!isset($argv[3])) throw new InvalidArgumentException('Usage: php caligrafer.php schedule run <job> [--if-due]');
					exit($scheduler->run($argv[3], $say, in_array('--if-due', $argv, true)));
				case 'exec':
					// the child process of a run (schedule run): calls the job's capability as the scheduler
					exit($scheduler->execute($argv[3] ?? ''));
				case 'due':
					exit($scheduler->due($say));
				case 'work':
					$scheduler->jobs();
					$scheduler->work($say);
					exit(0);
				case 'history':
					caligrafyConnect();
					$limitAt = array_search('--limit', $argv);
					$job = (isset($argv[3]) && $argv[3][0] !== '-') ? $argv[3] : null;
					$rows = Caligrafy\Scheduler::history($job, $limitAt !== false ? (int) ($argv[$limitAt + 1] ?? 50) : 50);
					print($rows ? "\n" : "\n No runs yet\n\n");
					foreach ($rows as $r) {
						printf(" %s  %-24s %-8s %s\n", $r['started_at'], $r['job'], $r['outcome'], $r['detail'] ?? '');
					}
					if ($rows) print("\n");
					exit(0);
				default:
					print("\n Usage: php caligrafer.php schedule <list [--json] | run <job> | due | work | history [<job>] [--limit 50]>\n"
						. " Jobs are declared in " . Caligrafy\Scheduler::FILE . "\n\n");
					exit(1);
			}
		} catch (InvalidArgumentException $e) {
			fwrite(STDERR, "\n " . str_replace("\n", "\n ", $e->getMessage()) . "\n\n");
			exit(Caligrafy\Scheduler::INVALID);
		} catch (Throwable $e) {
			fwrite(STDERR, "\n schedule: " . $e->getMessage() . "\n\n");
			exit(1);
		}

	case 'notify':
		// Notifications (framework/core/Notify.php): keys writes VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY and VAPID_SUBJECT to
		// .env, never over existing keys (new keys cut off every subscribed browser); test and status for one user
		$sub = strtolower($argv[2] ?? '');
		try {
			if ($sub === 'keys') {
				$env = file_exists('.env') ? file_get_contents('.env') : '';
				if (preg_match('/^VAPID_PRIVATE_KEY=\S+/m', $env)) {
					print("\n The web push keys are already in .env: kept (new keys would cut off every browser that turned push on)\n\n");
					exit(1);
				}
				$subjectAt = array_search('--subject', $argv);
				$subject = $subjectAt !== false ? ($argv[$subjectAt + 1] ?? '') : '';
				if ($subject === '') {
					$from = getenv('MAIL_FROM') ?: getenv('MAIL_USERNAME');
					$subject = filter_var($from, FILTER_VALIDATE_EMAIL) ? 'mailto:' . $from : (getenv('APP_URL') ?: '');
				}
				if (!preg_match('#^(mailto:\S+@\S+|https://\S+)$#', $subject)) {
					print("\n Give the push services a contact: php caligrafer.php notify keys --subject mailto:you@example.com\n\n");
					exit(1);
				}
				$keys = Caligrafy\Notify::keys();
				$set = array('VAPID_PUBLIC_KEY' => $keys['public'], 'VAPID_PRIVATE_KEY' => $keys['private'], 'VAPID_SUBJECT' => $subject);
				foreach ($set as $name => $value) {
					$env = preg_match("/^$name=.*$/m", $env) ? preg_replace("/^$name=.*$/m", "$name=$value", $env) : rtrim($env, "\n") . "\n$name=$value\n";
				}
				file_put_contents('.env', $env);
				print("\n Web push keys written to .env (VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT=$subject). Keep them: new keys cut off every subscribed browser\n\n");
				exit(0);
			}
			if (($sub === 'test' || $sub === 'status') && isset($argv[3])) {
				caligrafyConnect();
				$result = $sub === 'test'
					? Caligrafy\Notify::user($argv[3], array('title' => 'Notifications work', 'body' => 'A test from php caligrafer.php notify test.', 'url' => '/', 'tag' => 'notify-test'))
					: Caligrafy\Notify::status($argv[3]);
				print(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
				exit(0);
			}
			print("\n Usage: php caligrafer.php notify <keys [--subject mailto:you@example.com] | test <user id> | status <user id>>\n\n");
			exit(1);
		} catch (Throwable $e) {
			fwrite(STDERR, "\n notify: " . $e->getMessage() . "\n\n");
			exit(1);
		}

	case 'call':
		// Calls one capability from the command line and prints the same JSON as REST: {"data": ...} or {"error": ...}
		// Inputs: one JSON object ('{"id": 3}') or key=value pairs. Full local access by default, like any script;
		// --token <token> (or CALIGRAFY_TOKEN) calls it as that token, --guest as a visitor who is not signed in
		$name = $argv[2] ?? '';
		if ($name === '' || $name[0] === '-') {
			print("\n Usage: php caligrafer.php call <capability> ['{\"key\": \"value\"}' | key=value ...] [--token <token>] [--guest]\n"
				. " Lists the capabilities: php caligrafer.php capabilities\n\n");
			exit(1);
		}
		$input = array();
		$plain = getenv('CALIGRAFY_TOKEN') ?: '';
		$guest = false;
		for ($i = 3; $i < count($argv); $i++) {
			$arg = $argv[$i];
			if ($arg === '--token') { $plain = $argv[++$i] ?? ''; continue; }
			if ($arg === '--guest') { $guest = true; continue; }
			if (substr(ltrim($arg), 0, 1) === '{') {
				$json = json_decode($arg, true);
				if (!is_array($json)) {
					fwrite(STDERR, "caligrafer call: the input is not a JSON object: $arg\n");
					exit(1);
				}
				$input = array_merge($input, $json);
			} elseif (strpos($arg, '=') !== false) {
				list($key, $value) = explode('=', $arg, 2);
				$input[$key] = $value;
			} else {
				fwrite(STDERR, "caligrafer call: '$arg' is neither JSON nor key=value\n");
				exit(1);
			}
		}
		try {
			if ($plain !== '') {
				if (Caligrafy\Database::connect() === null) {
					throw new Exception('A token needs the database: set DB_ACTIVATE=true');
				}
				$token = Caligrafy\Tokens::verify($plain);
				if (!$token) {
					throw new Exception('The token is unknown, revoked or expired');
				}
				$context = Caligrafy\Context::forToken($token, 'cli');
			} else {
				$context = $guest ? Caligrafy\Context::guest('cli') : Caligrafy\Context::cli();
			}
			$result = array('data' => Caligrafy\Capabilities::call($name, $input, $context));
			$status = 0;
		} catch (Caligrafy\ServiceError $e) {
			$result = $e->toArray();
			$status = 1;
		} catch (Throwable $e) {
			fwrite(STDERR, 'caligrafer call: ' . $e->getMessage() . "\n");
			exit(1);
		}
		print(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
		exit($status);

	case 'update':
		// Moves the project to another release (the latest by default): framework files, merged settings, then upgrade --apply
		$target = null;
		foreach (array_slice($argv, 2) as $arg) {
			if ($arg !== '' && $arg[0] !== '-') { $target = $arg; break; }
		}
		exit((new Caligrafy\Updater('.'))->run($target, in_array('--check', $argv), in_array('--yes', $argv), !in_array('--no-composer', $argv)));

	case 'plugin':
		// The Claude Code plugin of this project: its own source name, and where Claude Code reads it on this computer
		$renamed = Caligrafy\PluginSource::ensureOwn('.');
		$name = Caligrafy\PluginSource::name('.');
		if ($name === null) {
			print("\n No .claude-plugin/marketplace.json: this project has no Claude Code plugin source (php caligrafer.php update brings it)\n\n");
			exit(1);
		}
		print("\n Plugin source: $name" . ($renamed ? ' (renamed from the shared "caligrafy")' : '') . ", plugin caligrafy-quill@$name\n");
		exit(Caligrafy\PluginSource::connect('.', function ($line) { print(" $line\n"); }));

	case 'upgrade':
		$apply = in_array('--apply', $argv);
		$forceAt = array_search('--force', $argv);
		$force = $forceAt !== false? ($argv[$forceAt + 1] ?? '') : null;
		$keep = array();
		foreach ($argv as $i => $arg) {
			if ($arg === '--keep' && isset($argv[$i + 1])) $keep[] = $argv[$i + 1];
		}
		// first, a newer release of Caligrafy Quill: upgrade brings the project to it (php caligrafer.php update), which
		// updates application/ at the end. --local compares application/ with this release's templates only
		if (!in_array('--local', $argv) && $force === null && !$keep) {
			list($newer, $why) = (new Caligrafy\Updater('.'))->newerRelease();
			if ($newer) {
				print("\n Caligrafy Quill $newer is out (this project runs " . Caligrafy\Framework::VERSION . ").\n");
				exit((new Caligrafy\Updater('.'))->run($newer, !$apply, in_array('--yes', $argv), !in_array('--no-composer', $argv)));
			}
			print($why ? "\n Could not check for a newer release ($why): comparing with this release's templates.\n"
				: "\n Caligrafy Quill " . Caligrafy\Framework::VERSION . " is the latest release.\n");
		}
		exit((new Caligrafy\Upgrader('.', $apply, $force, $keep))->run());

	case 'doctor':
		$onlyAt = array_search('--only', $argv);
		$only = $onlyAt !== false? ($argv[$onlyAt + 1] ?? '') : null;
		exit((new Caligrafy\Doctor('.', $only, $envFile))->run(in_array('--json', $argv)));

	case 'design':
		// Design styles: DESIGN_STYLE in .env, <application/design or framework/design/...>/<name>/DESIGN.md, public/css/designs/<name>.css
		exit(Caligrafy\Designs::cli(array_slice($argv, 2), '.', $envFile));

	case 'create':
		if (isset($argv[2]) && !in_array(strtolower($argv[2]), $restricted)) {
			system('cp -r framework/librairies/app ./public/'.$argv[2], $retValue);
			print("\n Project created in the public folder.\n Type cd public/".$argv[2]. " to access it anytime\n\n");
			chdir("public/".$argv[2]);
			// npm install only where Node.js is available (the Docker dev-box has none): never claim it worked when it did not
			$npm = trim((string) shell_exec('command -v npm 2>/dev/null'));
			$installed = false;
			if ($npm !== '') {
				print("\n Installing packages...\n\n");
				system('npm install', $npmStatus);
				$installed = $npmStatus === 0;
			}
			if ($installed) {
				print("\n VueJS project created and its packages installed.\n");
			} else {
				print("\n VueJS project created, but its packages are NOT installed: ".($npm === ''? "npm is not available here (the Docker dev-box has no Node.js)" : "npm install failed").".\n"
					." Install them with Node.js 20.19+ or 22.12+ on your computer: cd public/".$argv[2]." && npm install\n");
			}
			print("\n - To run the development server: cd public/".$argv[2]." && npm run dev"
				."\n - To build it for production: npm run build (in the same folder)\n\n");
		} else {
			print("\n The project could not be created. Please make sure you have node.js with npm installed and that the name does not conflict with existing public folders. \n\n");
		}
		break;

    case (strtolower($argv[1]) == 'bot' || strtolower($argv[1]) == 'ml' || strtolower($argv[1]) == 'facedetect' || strtolower($argv[1]) == 'app'):
		if (isset($argv[2]) && !in_array(strtolower($argv[2]), $restricted)) {
			system('cp -r framework/plugins/' . $argv[1] .' ./public/' . $argv[2], $retValue);
			print("\n Project created in the public folder.\n Type cd public/".$argv[2]. " to access it anytime\n\n");
			chdir("public/".$argv[2]);
		} else {
			print("\n The project could not be created. Please make sure you have node.js with npm installed and that the name does not conflict with existing public folders. \n\n");
		}
		break;

	case 'server':
		if (isset($argv[3])) {
			if (isset($argv[2]) && strtolower($argv[2]) == "start") {
				// builds the image only the first time (or after 'server rebuild'): later starts take seconds
				// with the scheduler, which runs the jobs of application/config/schedule.php every minute (schedule work)
				system(caligrafyCompose() . ' up -d dev-box scheduler', $retValue);
				if ($retValue) {
					print(" You don't have a server running.\n Start your local apache server if you have one. \n If not, check the Caligrafy documentation to run docker. \n\n");
				} else {
					print("\n\nCaligrafy Server successfully started.\n\n Hostname: http://localhost:8080 \n phpmyadmin: http://localhost:8077/ \n mysql username: root \n mysql password: root \n\n");
				}
			} 
			elseif (isset($argv[2]) && strtolower($argv[2]) == "rebuild") {
				// rebuilds the image (after a Dockerfile change or to update the system packages), then starts it
				system(caligrafyCompose() . ' build --pull dev-box', $retValue);
				if (!$retValue) system(caligrafyCompose() . ' up -d dev-box', $retValue);
				print($retValue ? "\n\nThe Caligrafy server image could not be rebuilt.\n\n" : "\n\nCaligrafy Server rebuilt and started: http://localhost:8080\n\n");
			}
			elseif (isset($argv[2]) && strtolower($argv[2]) == "stop") {
				system(caligrafyCompose() . ' stop', $retValue);
				print("\n\nCaligrafy Server stopped.\n\n");
			}
		}
		elseif (isset($argv[2]) && strtolower($argv[2]) == "stop") {
			print("\n\nYou are using a local Apache server.\nYou need to turn it off using LAMP/MAMP interface or the apache command line.\n\n");
		}
		break;
		
    default:
        print($defaultMsg);
}





