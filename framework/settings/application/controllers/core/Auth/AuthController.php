<?php

// Example controller shipped by Caligrafy. It lives in the Caligrafy\Controllers namespace so that a
// controller of the same name in your application never collides with it. Routes may use the short
// name (resolved by the router) or the fully qualified one ('Caligrafy\Controllers\AIController@query').
namespace Caligrafy\Controllers;

use Caligrafy\Controller;
use User;

class AuthController extends Controller {
    
    
    public function index()
    {
        if (!authorized()) {
            return view('Auth/login');
        }
        redirect('/home');
        exit;
    }
    
    public function registerForm()
    {
        if (authorized()) {
            redirect('/home');
            exit;
        }
        return view('Auth/register');
    }
    
    
    public function home()
    {
        if (authorized()) {
            return view('default/index', array('authorized' => authorized()));   
        }
        redirect('/login');
        exit;
    }
    
    /*
     *Register
     */
    public function register()
    {
        $this->associate('User', 'users');
        $parameters = $this->request->parameters;
        $validate = $this->validator->check($parameters, array('username' => 'required|alpha_numeric|max_len,100',
                                                               'passcode' => 'required|min_len,8|max_len,72',
                                                               'confirmpasscode' => 'required|max_len,72'
                                                              ));
        $userInput = (Object)array('username' => $parameters['username']?? '');
   
        
        //confirm password
        if (($parameters['passcode']?? '') !== ($parameters['confirmpasscode']?? '')) {
            return view('/Auth/register', array('error' => true, 'status' => 'danger', 'message_header' => 'Whoops, something is not right', 'message' => 'The passwords you entered don\'t match. Make sure that the password and the confirm password fields match.', 'errors' => ['passcode' => 'passwords don\'t match'], 'user' => $userInput));
        }
        
        // invalid inputs
        if ($validate !== true) {
            return view('/Auth/register', array('error' => true, 'status' => 'danger', 'message_header' => 'Whoops, something is not right', 'message' => 'Some of the inputs are invalid. Make sure all the required inputs are entered properly', 'errors' => $validate, 'user' => $userInput ));
        }
        
        //check if user exists (exact match, bound query)
        if ($this->model->findByUsername($parameters['username'])) {
            return view('/Auth/register', array('error' => true, 'status' => 'danger', 'message_header' => 'Whoops, something is not right', 'message' => 'You are already registered', 'errors' => ['username' => 'The username already exists in the system'], 'user' => $userInput));
        }
        
        $user = new User();
        $user->username = $parameters['username'];
        $user->passcode = User::hashPassword($parameters['passcode']);
        $this->save($user);
        redirect('/login');
        exit;

    }
    
    
    /*
     * Login
     */
    public function login()
    {
        $this->associate('User', 'users');
        $parameters = $this->request->parameters;
        $user = $this->model->findByUsername($parameters['username']?? '');
        if (!$user) {
            // same work as a real check so that response times do not reveal which usernames exist
            password_verify((string)($parameters['passcode']?? ''), '$2y$10$B0NLUgDUK3CbImwrVfa/FuOtSOKrb7DZTGG3USPSF4Comn4cy5RtG');
        }
        if ($user && $user->verifyPassword($parameters['passcode']?? '')) {

            // upgrade passcodes stored by older versions (reversible encryption) to a password hash
            if ($user->needsRehash()) {
                $user->passcode = User::hashPassword($parameters['passcode']);
                $this->save($user, $user->id);
                $user = $this->model->find($user->id);
            }

            authorize($user);
            redirect('/home');
            exit;
        } 
        return view('/Auth/login', array('error' => true, 'status' => 'danger', 'message_header' => 'Whoops, something is not right', 'message' => 'Invalid credentials' ));
    }
    
    /*
     * Logout
     */
    public function logout()
    {
        // Make a call out to the core helpher function that terminates the user info from the session
        logout('/home');
    }
	
	/*
     * Not Authorized Login page
     */
	public function notAuthorized()
	{
		return view('/Auth/login', array('error' => true, 'status'=>'danger', 'message_header' => 'Restricted Page','message' => 'You have not been granted permission to access this page'));
	}
    
    
}
