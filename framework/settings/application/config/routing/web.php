<?php
/**
 * web.php
 * Web Routes are defined in this file
 * @author Dory A.Azar
 * @copyright 2019
 * @version 1.0
 *
*/

use Caligrafy\Auth;
use Caligrafy\Route;
use \Exception as Exception;

try {
    
    // API ACCESS - add comments if you don't want to allow API access on routes
    Auth::activateAPI();
    
    
    // AUTHENTICATION - Remove comment if you have an authentication implemented
    // Auth::authentication('User', 'users');
    
    // ROUTE DEFINITION: get, post, put and delete
    Route::get('/', function() { return view('default/index'); });
    Route::get('/helloworld', function() { echo "Hello World"; });
	
    // Auth Routes - Uncomment only if AUTHENTICATION activated above
    // Route::get('/home', 'AuthController@home');
    // Route::get('/login', 'AuthController');
    // Route::get('/logout', 'AuthController@logout');
	// Route::get('/notAuthorized', 'AuthController@notAuthorized');
    // Route::get('/register', 'AuthController@registerForm');
    // Route::post('/login', 'AuthController@login');
    // Route::post('/register', 'AuthController@register');

    // User Administration Routes - Uncomment only if AUTHENTICATION activated above
	// Route::get('/admin', 'AdminController');
	// Route::get('/users', 'AdminController');
	// Route::get('/users/{id}/edit', 'AdminController@editUserForm');
	// Route::put('/users/{id}/edit', 'AdminController@updateUser');
	// Route::delete('/users/{id}/edit', 'AdminController@deleteUser');


    // ROUTING TO JAVASCRIPT APPS
    // Make sure you create a JS app or a Vue app in Caligrafer first
    Route::get('/apps/{appName}', 'ClientController'); // you can specify any path as long as it ends with appName

    
    // BOT PAGE ROUTES
    Route::get('/bot/{appName}', 'ClientController');  // A botSkillId needs to be provided in the URL. For Example: ?botSkillId=62196ce3-0b22-4aea-b2f2-d39730c03797
    
    // BOT DATA ROUTES
    // routes need to start with reserved path __bots__ 
    Route::post('/__bots__/{appName}/communicate', 'WatsonController@communicate');
    Route::post('/__bots__/{appName}/{appId}', 'WatsonController@connect');
    Route::delete('/__bots__/{appName}', 'WatsonController@end');   

    /* ML Routes
     *
     * If you don't specify an up, it will go to the face, body detector.
     * Specify an app as a URI Parameter ?app=<name of app>
     * 
     * There are 3 possible apps: featureextractor, neuralnetwork and posedetector
    */
    Route::get('/ml/{appName}', 'ClientController'); 
    

    // MUST NOT BE REMOVED - Routes need to be run after being defined 
    Route::run(); 
}
catch (Exception $e) {
    
    // Error handling 
    echo $e->getMessage();
}



