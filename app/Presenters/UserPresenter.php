<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Application\UI\Form;
use App\Model\UserManager;

/**
 * Class UserPresenter
 * prepares data for rendering users overview page and user detail page, 
 * creates components for user management, mediates user addition, edit 
 * and deletion, sign in and sign out.
 */
class UserPresenter extends Nette\Application\UI\Presenter
{
	private $userManager;

	public function __construct(UserManager $userManager)
	{
		$this->userManager = $userManager;
	}
	public function renderDefault(): void
    {
		if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('User:signIn');
		}
		if (!$this->getUser()->isInRole('admin')){
            $this->flashMessage('Only admin user can display list of users.', 'fail');
		}
		else{
			$this->template->users = $this->userManager->getUsers();
		}
	}
	public function renderShow(int $user_id): void
    {
		if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('User:signIn');
		}
		if (!$this->getUser()->isInRole('admin') and $user_id != $this->getUser()->getId()){
            $this->flashMessage('Only admin user can display account detail of another user.', 'fail');
		}
		else{
			$userItem = $this->template->userItem = $this->userManager->getUserByID($user_id);
			if (!$userItem) {
            	$this->error('User not found.');
        	}
		}
		
	}
	public function renderEdit(int $user_id): void
    {
		$this->template->username = $this->userManager->getUserByID($user_id)->username;
	}

	protected function createComponentSignInForm(): Form
	{
		$form = new Form;
		$form->addText('username', 'Username:')
			->setRequired('Username must be filled.');

		$form->addPassword('password', 'Password:')
			->setRequired('Password must be filled.');

		$form->addSubmit('send', 'Log in');

		$form->onSuccess[] = [$this, 'signInFormSucceeded'];
		return $form;
	}
	protected function createComponentCreateUserForm(): Form
	{
		$form = new Form;
		$role = array('admin' => 'admin','view' => 'view');

		$form->addText('username', 'Username:')
			->setRequired('Username must be filled.')
			->addRule($form::MAX_LENGTH, 'Maximum length for username is 100 characters.',100);

		$form->addPassword('password', 'Password:')
			->setRequired('Password must be filled.')
			->addRule($form::MIN_LENGTH, 'Password needs to have at least 12 characters.', 12)
			->addRule($form::PATTERN, 'Password must include at least two numbers.','.*[0-9].*[0-9].*')
			->addRule($form::PATTERN, 'Password must include at least two capital letters.','.*[A-Z].*[A-Z].*')
			->addRule($form::PATTERN, 'Password must include at least three lover case letters.','.*[a-z]*[a-z].*[a-z].*');

		$form->addPassword('password_confirm', 'Password (confirm):')
			->setRequired('Password (confirm) must be filled.')
			->addRule(Form::EQUAL, 'Passwords do not match.', $form['password']);

		$form->addRadioList('role', 'User role:', $role)
            ->setRequired('You must select user role.');

		$form->addSubmit('cancel', 'Cancel')
			->setValidationScope([])
			->onClick[] = function () {$this->redirect('User:default');};

		$form->addSubmit('send', 'Create');

		$form->onSuccess[] = [$this, 'createUserFormSucceeded'];
		return $form;
	}
	protected function createComponentEditUserForm(): Form
	{
		$form = new Form;
		$role = array('admin' => 'admin','view' => 'view');
		
		
		$password = $form->addPassword('password_new', 'New Password:');
		$password_confirm = $form->addPassword('password_new_confirm', 'New Password (confirm):');
		
		$password->addConditionOn($form['password_new_confirm'], Form::FILLED)
				->setRequired('New Password must be filled.');
		
		$password_confirm->addConditionOn($form['password_new'], Form::FILLED)
				->setRequired('New Password (confirm) must be filled.')
				->addRule(Form::EQUAL, 'Passwords do not match.', $form['password_new']);
		
		$form->addRadioList('role', 'User role:', $role);

		$form->addSubmit('cancel', 'Cancel')
			->setValidationScope([])
			->onClick[] = function () {$this->redirect('User:default');};	

		$form->addSubmit('send', 'Edit');

		$form->onSuccess[] = [$this, 'editUserFormSucceeded'];
		return $form;
	}
	protected function createComponentEditUserPasswordForm(): Form
	{
		$form = new Form;
		
		$form->addPassword('password_current', 'Current Password:')
			->setRequired('Password must be filled.');

		$form->addPassword('password_new', 'New Password:')
			->setRequired('New Password must be filled.');
		
		$form->addPassword('password_new_confirm', 'New Password (confirm):')
			->setRequired('New Password (confirm) must be filled.')
			->addRule(Form::EQUAL, 'Passwords do not match.', $form['password_new']);

		$form->addSubmit('cancel', 'Cancel')
			->setValidationScope([])
			->onClick[] = function () {$this->redirect('User:show',$this->getUser()->getId());};	

		$form->addSubmit('send', 'Edit');
		

		$form->onSuccess[] = [$this, 'editUserPasswordFormSucceeded'];
		return $form;
	}
	public function signInFormSucceeded(Form $form, \stdClass $values): void
	{
		try {
			$this->getUser()->login($values->username, $values->password);
			$this->redirect('Homepage:');

		} catch (Nette\Security\AuthenticationException $e) {
			$this->flashMessage('Wrong username or password.','fail');
		}
	}
	public function createUserFormSucceeded(Form $form, \stdClass $values): void
	{
		if (!$this->getUser()->isLoggedIn()) {
            $this->error('You need to sign in to be able to create user.','fail');
		}
		if (!$this->getUser()->isInRole('admin')){
            $this->flashMessage('Only admin user can create user.', 'fail');
		}
		else{
			$username = $this->userManager->getUserName($values->username);
			if($username){
				$this->flashMessage('Username already exists.','fail');
			}
			else{
				$this->userManager->createUser($values->username,$values->password,$values->role);
				$this->flashMessage('User created.','success');
			}
		}
	}
	public function editUserFormSucceeded(Form $form, \stdClass $values): void
	{
		if (!$this->getUser()->isLoggedIn()) {
            $this->error('You need to sign in to be able to edit user.','fail');
		}
		$user = $this->getParameter('user_id');
	
		if (!$this->getUser()->isInRole('admin')){
            $this->flashMessage('Only admin user can edit another user.', 'fail');
		}
		else{
			if(isset($values->password_new) and strlen($values->password_new)>0){
				$this->userManager->changePassword($user,$values->password_new);
			}
			$this->userManager->updateUser($user,$values);
			$this->flashMessage('User edited.','success');
		}
	}
	public function editUserPasswordFormSucceeded(Form $form, \stdClass $values): void
	{
		if (!$this->getUser()->isLoggedIn()) {
            $this->error('You need to sign in to be able to edit user.','fail');
		}
		$user = $this->getParameter('user_id');
		$current_user = $this->getUser()->getId();
		if ($current_user != $user){
            $this->flashMessage('Only admin user can change another user password.', 'fail');
		}
		else{
			$passwordVerify = $this->userManager->verifyPassword($user,$values->password_current);
			if ($passwordVerify === false){
				$this->flashMessage('Current password is invalid.', 'fail');
			}
			else{
				$this->userManager->changePassword($user,$values->password_new);
				$this->flashMessage('Password changed.', 'success');
			}
		}
		
	}
	public function actionSignOut(): void 
	{	
		$this->getUser()->logout();
		$this->flashMessage('Yo are now signed out.','success');
		$this->redirect('Homepage:');
	}
	public function actionEdit(int $user_id): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('User:signIn');
		}
		if (!$this->getUser()->isInRole('admin')){
            $this->flashMessage('Only admin user can edit another user.', 'fail');
        }
        else{
            $user = $this->userManager->getUserByID($user_id);
	        if (!$user) {
		        $this->error('User not found');
	        }
            $this['editUserForm']->setDefaults($user->toArray());
        }
	}
	public function actionChangePassword(int $user_id): void
    {
        if (!$this->getUser()->isLoggedIn()) {
            $this->redirect('User:signIn');
		}
		$current_user = $this->getUser()->getId();
		if ($current_user !== $user_id){
			$this->flashMessage('Only admin user can change another user password.', 'fail');
			$this->redirect('User:error');
        }
        else{
            $user = $this->userManager->getUserByID($user_id);
	        if (!$user) {
		        $this->error('User not found');
	        }
            $this['editUserPasswordForm']->setDefaults($user->toArray());
        }
    }

	public function handleDelete(int $user_id): void
    {
	    if (!$this->getUser()->isLoggedIn()) {
		    $this->redirect('User:signIn');
		}   
		$current_user = $this->getUser()->getId();
		if (!$this->getUser()->isInRole('admin') and $user_id !== $current_user){
            $this->flashMessage('Only admin user can delete another user.', 'fail');
        }
        else{
			$user = $this->userManager->deleteUser($user_id);
			if($user_id === $current_user){
				$this->actionSignOut();

			}
            $this->redirect('User:');
        }
    }
}
