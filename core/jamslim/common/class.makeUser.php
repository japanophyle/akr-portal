<?php

class makeUser{
	var $ACCESS=array(20=>'User',21=>'Leader',25=>'Admin',30=>'Super');//get from registry or slim
	var $USER=array('Name'=>'','Username'=>'','Password'=>'','Salt'=>'','Access'=>0,'Status'=>1,'Permissions'=>'','DojoLock'=>'','MemberID'=>0,'Email'=>'');
	var $REQ;
	var $PERMISSIONS;//array of roles from slimPermissions;
	var $MEMBER_ID=0;
	var $REGISTER;
	
	function __construct($user=false){
		global $REG;
		$this->REGISTER=$REG;
		if(is_array($user)) $this->Process($user);
	}
		
	function Process($user=false){
		if($this->REQ){
			$this->makeSalt();
			$this->makePasswordHash();
			$this->setAccess();
			$this->setMemberID();
			$this->setDojoLock();
			return $this->USER;
		}else if(is_array($user)){
			$this->REQ=$user;
			foreach($this->USER as $i=>$v){
				if($vv=issetCheck($user,$i)) $this->USER[$i]=$vv;
			}
			$this->makeSalt();
			$this->makePasswordHash();
			$this->setAccess();
			$this->setMemberID();
			return $this->USER;
		}else{
			return 'Sorry, the details supplied were invalid...';
		}
	}
	function makePassword(){
		$opt['length'] = 8; 
		$this->USER['Password']=TextFun::generate_readable_password($opt);
	}
	function setToken(){
		$expire = (time()+(60 * 30));
		$this->USER['Token'] = TextFun::getToken_api();
		$this->USER['TokenExpire'] = date('Y-m-d H:i:s',$expire);
	}
	
	private function setDojoLock(){
		if(is_array($this->USER['DojoLock'])){
			$this->USER['DojoLock']=serialize($this->USER['DojoLock']);
		}else if(is_numeric($this->USER['DojoLock'])){
			$this->USER['DojoLock']=serialize(array((int)$this->USER['DojoLock']));
		}
	}
	private function makeSalt(){
		$opt['length'] = 8; 
		$opt['special_chars']=true;
		$this->USER['Salt']=TextFun::generate_string($opt);
	}
	
	private function makePasswordHash(){
		$hash=$this->USER['Password'].$this->USER['Salt'];
		$this->USER['Password']=TextFun::quickHash(array('info'=>$hash));
	}
	
	private function setAccess(){
		$levels=$this->REGISTER->get('USER_ACCESS');
		$lv=(int)$this->USER['Access'];
		if(issetCheck($levels,$lv)){
			//already set
		}else{
			$lv=20;
		}
		$this->USER['Access']=$lv;
		$this->setPermissions($lv);
	}
	
	private function setPermissions($level=0){
		//only if not set
		if(!issetCheck($this->USER,'Permissions')){
			if($this->PERMISSIONS){
				$chk=issetCheck($this->PERMISSIONS,$level);
				if(!$chk) $chk=$this->PERMISSIONS[0];
				$this->USER['Permissions']=$chk;
			}
		}
	}
    function setMemberID(){
		if($this->USER['MemberID']<1){
			$this->USER['MemberID']=(int)$this->MEMBER_ID;
		}
 	}
	
}
