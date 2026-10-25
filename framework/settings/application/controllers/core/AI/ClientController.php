<?php

use Caligrafy\Controller;

class ClientController extends Controller {

    /* 
     */

    public function index()
    {   
        // Get the app name
        $appName = $this->request->fetch->appName?? end($this->request->uriComponents);
        
        // Validate that the appName is not overriden by URL parameters
        $appName = $appName === end($this->request->uriComponents)? array('appName' => $appName) : array('appName' => end($this->request->uriComponents));
        
        // Get the app root: the markup file name (without .php) inside public/<appName>/
        // views/Client/app.pug only accepts letters, digits, - and _ for both names
        $appFile = $this->request->fetch->app?? 'index';
        $app = array('app' => is_string($appFile) && preg_match('/^[A-Za-z0-9_-]+$/', $appFile)? $appFile : '');

        $options = array_merge($app, $this->request->parameters, $appName);

        return view('Client/app', $options);
    }

}