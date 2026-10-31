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
				\n - version: prints the version of Caligrafy Quill
				\n - initialize: Initializes and signs your project
				\n - upgrade [--apply] [--force <application/path>]: compares application/ with the framework templates (dry run by default) and updates the untouched files
				\n - doctor [--only openai,groq] [--json] [--env <file>]: read-only health check of the project and of the services configured in .env
				\n - server <start | stop | rebuild>: Starts a Caligrafy local LAMP server with phpmyadmin (requires PHP, Composer and Docker); rebuild updates the server image
				\n - platform install: creates the framework tables (personal access tokens, audit log)
				\n - token <create | list | revoke>: personal access tokens for REST and MCP clients (scoped, revocable)
				\n - audit [--limit 50] [--capability <name>]: the latest capability calls
				\n - service <Name> [--scope books:write] [--read-only]: creates a capability in application/services/
				\n - client [<file>] [--prefix /capabilities]: writes the JS client of the capabilities (default public/js/capabilities.js)
				\n - capabilities [--json | --markdown]: the capabilities of application/services/ (what REST, MCP and the JS client can call)
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
		   $appRoot = isset($argv[2])? "" : basename(getcwd()); 
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

		   if (is_dir("./.git")) {
			system('chmod -R 777 .git');
			system('rm -R .git');
		   }
		   print("\n Application initialized successfully");
		   print ("\n APP_KEY=".$appKey);
		   print("\n API_KEY=".$apiKey);
		   print("\n\n");
        } catch (Exception $e) {
            print($e->getMessage());
        }
		break;

	case 'platform':
		if (strtolower($argv[2] ?? '') !== 'install') {
			print("\n Usage: php caligrafer.php platform install\n Creates the framework tables (personal access tokens, audit log) from framework/settings/platform.sql\n\n");
			exit(1);
		}
		try {
			caligrafyConnect();
			$sql = preg_replace('/^\s*--.*$/m', '', file_get_contents('framework/settings/platform.sql'));
			foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
				Caligrafy\Database::execute($statement);
			}
			print("\n Framework tables ready: caligrafy_tokens, caligrafy_audit\n\n");
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
				$expires = $days !== null && $days !== '' ? date('Y-m-d H:i:s', time() + 86400 * max(1, (int) $days)) : null;
				$user = $option('user');
				$created = Caligrafy\Tokens::create($argv[3], $scopes, $user !== null && $user !== '' ? (int) $user : null, $expires);
				print("\n Token #{$created['id']} \"{$argv[3]}\" created with " . implode(' ', $scopes) . ($expires ? ", expires $expires" : '') . "\n\n"
					. " " . $created['token'] . "\n\n It is shown only now: store it as a secret (Authorization: Bearer <token>).\n\n");
			} elseif ($sub === 'list') {
				$tokens = Caligrafy\Tokens::all();
				print($tokens ? "\n" : "\n No tokens yet: php caligrafer.php token create <name> --scopes <scopes>\n\n");
				foreach ($tokens as $t) {
					$state = $t['revoked_at'] ? 'revoked' : ($t['expires_at'] && strtotime($t['expires_at']) <= time() ? 'expired' : 'active');
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

	case 'upgrade':
		$apply = in_array('--apply', $argv);
		$forceAt = array_search('--force', $argv);
		$force = $forceAt !== false? ($argv[$forceAt + 1] ?? '') : null;
		exit((new Caligrafy\Upgrader('.', $apply, $force))->run());

	case 'doctor':
		$onlyAt = array_search('--only', $argv);
		$only = $onlyAt !== false? ($argv[$onlyAt + 1] ?? '') : null;
		exit((new Caligrafy\Doctor('.', $only, $envFile))->run(in_array('--json', $argv)));

	case 'create':
		if (isset($argv[2]) && !in_array(strtolower($argv[2]), $restricted)) {
			system('cp -r framework/librairies/app ./public/'.$argv[2], $retValue);
			print("\n Project created in the public folder.\n Type cd public/".$argv[2]. " to access it anytime\n\n");
			chdir("public/".$argv[2]);
			print("\n Installing packages...\n\n");
			system('npm install');
			print("\nVueJS project successfully created and all packages have been installed.
					\n - You first need to go to the project folder by typing: 'cd public/".$argv[2]."'
					\n Then,
					\n - To run local server, type: 'npm run dev' or 'npm run serve'
					\n - To run a build, type: 'npm run build'  
					\n\n");
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
				system(caligrafyCompose() . ' up -d dev-box', $retValue);
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





