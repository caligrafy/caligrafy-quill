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
        
        // Get the app root
        $app = array('app' => $this->request->fetch->app?? 'index');

        $options = array_merge($app, $this->request->parameters, $appName);

        return view('Client/app', $options);
    }

}