<?php

// Example controller shipped by Caligrafy. It lives in the Caligrafy\Controllers namespace so that a
// controller of the same name in your application never collides with it. Routes may use the short
// name (resolved by the router) or the fully qualified one ('Caligrafy\Controllers\AIController@query').
namespace Caligrafy\Controllers;

use Caligrafy\Controller;
use Caligrafy\ServiceError;

class AdminController extends Controller {

	/*
	 * Minimum value of users.permissions needed to manage users: CaligrafyUsers::ADMIN_LEVEL
	 * (application/services/Users/CaligrafyUsers.php), the level of the user capabilities too.
	 * The pages read and change users through those capabilities (list_users, get_user, update_user, delete_user):
	 * the same rules for this page, REST and AI agents.
	 */
	const ADMIN_PERMISSION = \CaligrafyUsers::ADMIN_LEVEL;
	
	public function index()
	{
		guard('permissions', self::ADMIN_PERMISSION);
		$users = array_map(function ($user) { return (object) $user; }, $this->call('list_users', array()));
		return view('Auth/users', array('users' => $users, 'authorized' => authorized(), 'admin' => true));

	}
	
	// Edit user Form
	public function editUserForm()
	{
		guard('permissions', self::ADMIN_PERMISSION);
		try {
			$user = (object) $this->call('get_user', array('id' => currentBind()['id'] ?? null));
		} catch (ServiceError $e) {
			redirect('/admin');
			exit;
		}
		return view('Auth/manageusers', array('user' => $user, 'put' => true, 'authorized' => authorized(), 'admin' => true));
		
	}
	
	// Update User
	public function updateUser()
	{
		guard('permissions', self::ADMIN_PERMISSION);
		$id = currentBind()['id'] ?? null;
		$parameters = $this->request->parameters;
		$userInput = (Object)array('id' => $id, 'username' => $parameters['username']?? '', 'permissions' => $parameters['permissions']?? '');

		//confirm password (a form check: the capability takes one password)
		if (($parameters['passcode']?? '') !== ($parameters['confirmpasscode']?? '')) {
			return view('Auth/manageusers', array('put' => true, 'error' => true, 'status' => 'danger', 'message_header' => 'Whoops, something is not right', 'message' => 'The passwords you entered don\'t match. Make sure that the password and the confirm password fields match.', 'errors' => ['passcode' => 'passwords don\'t match'], 'user' => $userInput, 'authorized' => authorized(), 'admin' => true));
		}

		try {
			$this->call('update_user', array('id' => $id, 'username' => $parameters['username']?? '', 'passcode' => $parameters['passcode']?? '',
				'permissions' => $parameters['permissions']?? ''));
		} catch (ServiceError $e) {
			if ($e->kind !== 'invalid') {
				redirect('/admin');
				exit;
			}
			// invalid inputs
			return view('Auth/manageusers', array('put' => true, 'error' => true, 'status' => 'danger', 'message_header' => 'Whoooops, something went wrong', 'message' => 'Some of the inputs are invalid. Make sure all the required inputs are entered properly', 'errors' => $e->fields, 'user' => $userInput, 'authorized' => authorized(), 'admin' => true));
		}

		redirect('/users');
		exit;
		
	}
	
	//Delete User
	public function deleteUser()
	{
		guard('permissions', self::ADMIN_PERMISSION);
		try {
			$this->call('delete_user', array('id' => currentBind()['id'] ?? null));
		} catch (ServiceError $e) {
			// not found, or your own account: back to the list
		}
		redirect('/users');
		exit;
	}
	
	
}
