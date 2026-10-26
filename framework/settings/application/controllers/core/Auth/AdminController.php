<?php

// Example controller shipped by Caligrafy. It lives in the Caligrafy\Controllers namespace so that a
// controller of the same name in your application never collides with it. Routes may use the short
// name (resolved by the router) or the fully qualified one ('Caligrafy\Controllers\AIController@query').
namespace Caligrafy\Controllers;

use Caligrafy\Controller;
use User;

class AdminController extends Controller {

	/*
	 * Minimum value of users.permissions needed to manage users.
	 * New users are created with permissions 0, so promote your first administrator in the database:
	 * UPDATE users SET permissions = 1 WHERE username = '<your username>';
	 */
	const ADMIN_PERMISSION = 1;
	
	public function index()
	{
		guard('permissions', self::ADMIN_PERMISSION);
		$this->associate('User', 'users');
		$users = $this->all(array('order' => 'order by modified_at DESC'));
		return view('Auth/users', array('users' => $users, 'authorized' => authorized(), 'admin' => true));

	}
	
	// Edit user Form
	public function editUserForm()
	{
		guard('permissions', self::ADMIN_PERMISSION);
		$this->associate('User', 'users');
		$user = $this->find();
		if (!$user) {
			redirect('/admin');
			exit;
		}
		return view('Auth/manageusers', array('user' => $user, 'put' => true, 'authorized' => authorized(), 'admin' => true));
		
	}
	
	// Update User
	public function updateUser()
	{
		guard('permissions', self::ADMIN_PERMISSION);
		$this->associate('User', 'users');
		$user = $this->find();
		
		if (!$user) {
			redirect('/admin');
			exit;
		}
		
		$parameters = $this->request->parameters;
        $userInput = (Object)array('id' => $user->id, 'username' => $parameters['username']?? $user->username, 'permissions' => $parameters['permissions']?? $user->permissions);
            
		// Input validation
		$validate = $this->validator->check($parameters, array('username' => 'required|alpha_numeric|max_len,100',
												   'passcode' => 'min_len,8|max_len,72',
												   'confirmpasscode' => 'max_len,72',
												   'permissions' => 'integer|min_numeric,0|max_numeric,255'
												  ));
		
		//confirm password
        if (($parameters['passcode']?? '') !== ($parameters['confirmpasscode']?? '')) {
            return view('Auth/manageusers', array('put' => true, 'error' => true, 'status' => 'danger', 'message_header' => 'Whoops, something is not right', 'message' => 'The passwords you entered don\'t match. Make sure that the password and the confirm password fields match.', 'errors' => ['passcode' => 'passwords don\'t match'], 'user' => $userInput, 'authorized' => authorized(), 'admin' => true));
        }
		
        
		// invalid inputs
		if ($validate !== true) {
			return view('Auth/manageusers', array('put' => true, 'error' => true, 'status' => 'danger', 'message_header' => 'Whoooops, something went wrong', 'message' => 'Some of the inputs are invalid. Make sure all the required inputs are entered properly', 'errors' => $validate, 'user' => $userInput, 'authorized' => authorized(), 'admin' => true));
		}

        $user->username = $parameters['username']?? $user->username;
        $user->passcode = !empty($parameters['passcode'])? User::hashPassword($parameters['passcode']) : $user->passcode;
		$user->permissions = isset($parameters['permissions']) && $parameters['permissions'] !== ''? (int)$parameters['permissions'] : $user->permissions;
		$user->modified_at = now();
        $this->save($user);

        redirect('/users');
        exit;
		
	}
	
	//Delete User
	public function deleteUser()
	{
		guard('permissions', self::ADMIN_PERMISSION);
        $this->associate('User', 'users');
        $this->delete();
        redirect('/users');
        exit;
	}
	
	
}
