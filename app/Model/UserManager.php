<?php
declare(strict_types=1);

namespace App\Model;

use Nette;

/**
 * Class UserManager
 * manages database tables for users and user data, handles selection, insertion,
 * update and deletion of users and user data, performs user authentication.
 */
class UserManager implements Nette\Security\IAuthenticator
{
	use Nette\SmartObject;

	/** @var Nette\Database\Context */
    private $database;
    private $passwords;


	public function __construct(Nette\Database\Context $database, Nette\Security\Passwords $passwords)
	{
        $this->database = $database;
        $this->passwords = $passwords;
    }
    public function authenticate(array $credentials): Nette\Security\IIdentity
	{
		[$username, $password] = $credentials;

		$row = $this->database->table('t_users')
			->where('username', $username)
			->fetch();

		if (!$row) {
			throw new Nette\Security\AuthenticationException('User not found.');
		}

		if (!$this->passwords->verify($password, $row->password)) {
			throw new Nette\Security\AuthenticationException('Invalid password.');
		}

		return new Nette\Security\Identity(
			$row->user_id,
			$row->role, 
			['name' => $row->username]
		);
    }
    public function verifyPassword($user,$password){
        $row = $this->database->table('t_users')
			->where('user_id', $user)
            ->fetch();
        if (!$this->passwords->verify($password, $row->password)) {
            return false;
        }
        return true;
    }
    public function getUsers(){
        return $this->database->table('t_users')
            ->order('username ASC');
    }
    public function getUserByID($user_id){
        return $this->database->table('t_users')
            ->where('user_id = ?',$user_id)
            ->fetch();
    }
	public function getUserName($username){
        return $this->database->table('t_users')
            ->where('username = ?', $username)
            ->fetch();
    }
    public function getActiveSlasGrid($user_id){
        $userExists = $this->database->table('t_user_data')
            ->where('user_id = ?',$user_id)
            ->fetch();
        if(!$userExists){
            return null;
        }
        else{
            $activeSlas = $this->database->table('t_user_data')
                -> select('active_slas_grid')
                -> where('user_id = ?', $user_id)
                -> fetchField();
            if(!$activeSlas){
                return null;
            }
            else{
                return $activeSlas;
            }
            
        }
    }
    public function setActiveSlasGrid($user_id,$data): void
    {
        $userExists = $this->database->table('t_user_data')
            ->where('user_id = ?',$user_id)
            ->fetch();
        if(!$userExists){
            $this->database->table('t_user_data')
                -> insert([
                    'user_id' => $user_id,
                    'active_slas_grid'=> $data
                ]);
        }
        else{
            $this->database->table('t_user_data')
                -> where('user_id = ?', $user_id)
                -> update([
                    'active_slas_grid'=>$data
                ]);
        }
        
    }
    public function getActiveSlasMatrix($user_id){
        $userExists = $this->database->table('t_user_data')
            ->where('user_id = ?',$user_id)
            ->fetch();
        if(!$userExists){
            return null;
        }
        else{
            $activeSlas = $this->database->table('t_user_data')
                -> select('active_slas_matrix')
                -> where('user_id = ?', $user_id)
                -> fetchField();
            if(!$activeSlas){
                return null;
            }
            else{
                return $activeSlas;
            }
            
        }
    }
    public function setActiveSlasMatrix($user_id,$data): void
    {
        $userExists = $this->database->table('t_user_data')
            ->where('user_id = ?',$user_id)
            ->fetch();
        if(!$userExists){
            $this->database->table('t_user_data')
                -> insert([
                    'user_id' => $user_id,
                    'active_slas_matrix'=> $data
                ]);
        }
        else{
            $this->database->table('t_user_data')
                -> where('user_id = ?', $user_id)
                -> update([
                    'active_slas_matrix'=>$data
                ]);
        }
    }
    public function createUser($username,$password,$role): void
    {
        $hash = $this->passwords->hash($password);
        $user = $this->database->table('t_users')->insert([
            'username'=>$username,
            'password'=>$hash,
            'role'=>$role
        ]);
        $this->database->table('t_user_data')->insert([
            'user_id'=> $user->user_id
        ]);
    }
    public function updateUser($user,$values): void
    {
        $this->database->table('t_users')
            ->where('user_id = ?', $user)
            ->update([
                'role'=>$values->role
        ]);
    }
    public function changePassword($user,$password): void
    {
        $hash = $this->passwords->hash($password);
        $this->database->table('t_users')
            ->where('user_id = ?', $user)
            ->update([
                'password'=>$hash
        ]);
    }
    public function deleteUser($user_id): void
	{
        $this->database->table('t_users')
            ->where('user_id',$user_id)
            ->delete();
    }
}