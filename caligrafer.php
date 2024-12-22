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

require_once "framework/core/Caligrafer.php";

// load external vendors
require_once 'vendor/autoload.php';
       
// load environment variables
if (!file_exists('.env')) {
	system("cp framework/settings/.env.example .env");
}
$dotenv = Dotenv\Dotenv::createUnsafeMutable(__DIR__);
$dotenv->load();

$restricted = ['app', 'bot', '__bots__', 'css', 'facedetect', 'fonts', 'images', 'js', 'ml', 'resources', 'uploads'];

Caligrafer::run();

$defaultMsg = "\n Available Functions: 
				\n - initialize: Initializes and signs your project
				\n - server <start | stop>: Starts a Caligrafy local LAMP server with phpmyadmin (requires PHP, Composer and Docker)
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
           $keys = Caligrafer::generateKeys(); 
           $appKey = isset($keys['APP_KEY'])? $keys['APP_KEY'] : null;
           $apiKey = isset($keys['API_KEY'])? $keys['API_KEY'] : null;
		   $file = '.env';
		   $input = "APP_KEY=".$appKey."\n"."API_KEY=".$apiKey."\n"."APP_ROOT=".$appRoot."\n". file_get_contents($file);
		   $vueInput = "VITE_APP_KEY=".$appKey."\n"."VITE_API_KEY=".$apiKey."\n";
		   file_put_contents($file, $input);
		   file_put_contents(LIB_PATH . 'app/' . $file, $vueInput);
		   if (!is_dir("./application")) {
			system('cp -r framework/settings/application ./application', $retValue);
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
				system('docker-compose up --build -d dev-box', $retValue);
				if ($retValue) {
					print(" You don't have a server running.\n Start your local apache server if you have one. \n If not, check the Caligrafy documentation to run docker. \n\n");
				} else {
					print("\n\nCaligrafy Server successfully started.\n\n Hostname: http://localhost:8080 \n phpmyadmin: http://localhost:8077/ \n mysql username: root \n mysql password: root \n\n");
				}
			} 
			elseif (isset($argv[2]) && strtolower($argv[2]) == "stop") {
				system('docker-compose stop', $retValue);
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





