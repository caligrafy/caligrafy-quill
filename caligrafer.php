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
$dotenv = Dotenv\Dotenv::create(__DIR__);
$dotenv->overload();

$restricted = ['app', 'bot', '__bots__', 'css', 'facedetect', 'fonts', 'images', 'js', 'ml', 'resources', 'uploads'];

Caligrafer::run();

$defaultMsg = "\n Available Functions: 
				\n - initialize: Initializes and signs your project
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
		   $appRoot = basename(getcwd()); 
		   print("\nInitialization will override any work that you have done in your application. 
		   \nPress 'Return' to continue or CTRL Z to abort");
		   $confirmation = readline();

		   print("\n\n Preparing and signing the project for usage (We might need you to authenticate you) \n");
           $keys = Caligrafer::generateKeys(); 
           $appKey = isset($keys['APP_KEY'])? $keys['APP_KEY'] : null;
           $apiKey = isset($keys['API_KEY'])? $keys['API_KEY'] : null;
		   $file = '.env';
		   $input = "APP_KEY=".$appKey."\n"."API_KEY=".$apiKey."\n"."APP_ROOT=".$appRoot."\n". file_get_contents($file);
		   $vueInput = "VUE_APP_APP_KEY=".$appKey."\n"."VUE_APP_API_KEY=".$apiKey."\n";
		   file_put_contents($file, $input);
		   file_put_contents(LIB_PATH . 'app/' . $file, $vueInput);
		   
		   system('rm -R ./application');
		   system('cp -r framework/settings/application ./application', $retValue);
		   system('chmod -R 777 public/uploads');
		   system('chmod -R 777 .git');
		//    system('rm -R .git');

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
					\n - To run local server, type: 'npm run serve'
					\n - To run a build, type: 'npm run build'  
					\n\n");
		} else {
			print("\n The project could not be created. Please make sure you have node.js with npm installed and that the name does not conflict with existing public folders. \n\n");
		}
		break;

    case 'bot' || 'ml' || 'facedetect' || 'app':
		if (isset($argv[2]) && !in_array(strtolower($argv[2]), $restricted)) {
			system('cp -r framework/plugins/' . $argv[1] .' ./public/' . $argv[2], $retValue);
			print("\n Project created in the public folder.\n Type cd public/".$argv[2]. " to access it anytime\n\n");
			chdir("public/".$argv[2]);
		} else {
			print("\n The project could not be created. Please make sure you have node.js with npm installed and that the name does not conflict with existing public folders. \n\n");
		}
		break;

    default:
        print($defaultMsg);
}





