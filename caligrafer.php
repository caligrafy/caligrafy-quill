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
				\n - initialize: Initializes and signs your project
				\n - upgrade [--apply] [--force <application/path>]: compares application/ with the framework templates (dry run by default) and updates the untouched files
				\n - doctor [--only openai,groq] [--json] [--env <file>]: read-only health check of the project and of the services configured in .env
				\n - server <start | stop | rebuild>: Starts a Caligrafy local LAMP server with phpmyadmin (requires PHP, Composer and Docker); rebuild updates the server image
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





