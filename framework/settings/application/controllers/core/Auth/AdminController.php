<?php
use Caligrafy\Controller;

class AdminController extends Controller {
	
	public function index()
	{
		guard('permissions', 0, '/notAuthorized');
		$this->associate('User', 'users');
		$users = $this->all(array('order' => 'order by modified_at DESC'));
		return view('Auth/users', array('users' => $users, 'authorized' => authorized(), 'admin' => true));

	}
	
	// Edit user Form
	public function editUserForm()
	{
		guard('permissions', 0, '/notAuthorized');
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
		guard('permissions', 0, '/notAuthorized');
		$this->associate('User', 'users');
		$user = $this->find();
		
		if (!$user) {
			redirect('/admin');
			exit;
		}
		
		$parameters = $this->request->parameters;
        $userInput = (Object)$parameters;
            
		// Input validation
		$validate = $this->validator->check($parameters, array('username' => 'required|alpha_numeric|max_len, 100',
												   'passcode' => 'alpha_numeric|max_len,20',
												   'confirmpasscode' => 'alpha_numeric|max_len,20',
												   'permissions' => 'numeric|max_len,1'
												  ));
		
		//confirm password
        if ($parameters['passcode'] != $parameters['confirmpasscode']) {
            return view('Auth/manageusers', array('error' => true, 'status' => 'danger', 'message_header' => 'Whoops, something is not right', 'message' => 'The passwords you entered don\'t match. Make sure that the password and the confirm password fields match.', 'errors' => ['passcode' => 'passwords don\'t match'], 'user' => $userInput));
            exit;
        }
		
        
		// invalid inputs
		if ($validate !== true) {
			return view('Auth/manageusers', array('put' => true, 'error' => true, 'status' => 'danger', 'message_header' => 'Whoooops, something went wrong', 'message' => 'Some of the inputs are invalid. Make sure all the required inputs are entered properly', 'errors' => $validate, 'user' => $userInput));
			exit;
		}

        $user->username = $parameters['username']?? $user->username;
        $user->passcode = $parameters['passcode']? encryptDecrypt('encrypt', $parameters['passcode']) : $user->passcode;
		$user->permissions = $parameters['permissions']?? $user->permissions;
		$user->modified_at = date(now());
        $this->save($user);

        redirect('/users');
		
	}
	
	//Delete User
	public function deleteUser()
	{
		guard('permissions', 0, '/notAuthorized');
        $this->associate('User', 'users');
		$user = $this->find()?? null;
        $this->delete();
        redirect('/users');
	}
	
	
}