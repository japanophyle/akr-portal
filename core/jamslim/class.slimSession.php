<?php

class slimSession extends generic_a{
	private $DEFAULTS=array();
	private $NAME='jamSlim';

	function __construct(){
		$this->init();		
	}
	public static function Singleton(){return SingletonBase::Singleton(__CLASS__);}
	
	function session($action=false,$args=false){
		switch($action){
			case 'kill':
				$this->killSession();
				break;
			case 'restart':
				$this->killSession();
				$this->startSession();
				break;
			case 'regen':
				$this->regenSessionID();
				break;
			case 'login':
				$this->loginSession($args);
				break;
			case 'check':
				return $this->checkUserSession();
				break;
			case 'save':
				$this->setSession();
				break;
			case 'set':
				$this->setVar($args);
				$this->setSession();
				break;
			case 'get':
				return $this->get($args);
				break;
			case 'empty':
				$this->resetSession();
				break;
			case 'get_system_msg':
				return $this->getSystemMessage($args);
				break;
		}
			
	}
	
	function init($args=false){
		$this->DEFAULTS=array(
			'userArray'=>array('id'=>0,'name'=>'guest','uname'=>'guest','access'=>0,'permissions'=>array(),'expire'=>0),
			'last_page'=>'home',
			'deviceType'=>'classic',
			'sysMSG'=>false,
			'cart'=>false
		);
		
		//load session
		$this->getSession();
	}
	
	private function getSession(){
		$chk=issetCheck($_SESSION,$this->NAME);
		if(!$chk||$chk==='') $chk=$this->DEFAULTS;
		if($chk){
		 	foreach($chk as $i=>$v){
				$xx=$this->set($i,false,$v);
			}
		}
	}
	
	private function setSession(){
		$_SESSION[$this->NAME]=$this->get('all');
	}
	
	private function setVar($args=[]){
		$key=false;$value=false;
		extract($args);
		if($key){
			$this->set($key,false,$value);
			$this->setSession();
		}
	}
	
	private function killSession(){
		$id=session_name();
		unset($_SESSION[$this->NAME]);
        if (ini_get("session.use_cookies")) {
			$params = session_get_cookie_params();
			setcookie($id, '', time() - 42000,
				$params["path"], $params["domain"],
				$params["secure"], $params["httponly"]
			);
		}else{
			setcookie($id, '', time()-3600,'/', '', 0, 0);			
		}
		session_destroy();
		$this->resetSession();
	}
	
	private function resetSession(){
		$this->PARTS = array();
		$this->setSession();
	}
	
	private function startSession(){
		session_start();	
	}
	
	private function regenSessionID(){
		session_regenerate_id(TRUE);
	}
	
	private function checkUserSession() {
		//keeps session alive
		$sesh = $this->get('userArray');
		if ($sesh>0){
			$now = time();
			if ($now > $sesh['expire']) {
				$this->session('restart');
				$this->set('sysMSG',false,'Sorry, your session expired!... please login.');
				$this->setSession();
				setSystemResponse(URL.'page/home');
				die("Your session has expired! <a href='" . URL.'login' . "'>Login here</a>");
			} else {
				$sesh['expire'] = time() + (30 * 60);
				$this->set("userArray",false,$sesh);
				$this->setSession();
			}
		}
		return $sesh;
	}
	
	private function loginSession($args=false){
		$this->session('restart');
		$this->session('regen');
		$_SESSION = array();
		foreach($this->DEFAULTS as $i=>$v){
			$val=issetCheck($args,$i,$v);
			$this->set($i,false,$val);
		}
		$this->setSession();
	}
	
	private function getSystemMessage($msg_name=false){
		if(!$msg_name||$msg_name==='') $msg_name='sysMSG';
		$msg=$this->get($msg_name);
		if($msg){
			//$this->set($msg_name,false,false);
			//$this->setSession();
		}
		return $msg;		
	}

}
