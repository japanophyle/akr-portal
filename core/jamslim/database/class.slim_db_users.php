<?php

class slim_db_users extends slim_db_common{
	var $TABLE='Users';
	private $ACCESS;	
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
		$this->ACCESS=$slim->user['access'];
	}
	function get($what=false,$args=null){
		switch($what){
			case 'new':
				$data=$this->getUser('new');
				break;
			case 'user': case 'id':
				$data=$this->getUser($args);
				break;
			case 'users':case 'all';
				$data=$this->getUsers($args);
				break;
			case 'by':
				$f=issetCheck($args,'field');
				$v=issetCheck($args,'value');
				$data=$this->getUsersBy($f,$v);
				break;
			default:
				$data=[];				
		}
		if(!$data){
			if($this->ERR){
				$data=$this->ERR;
			}
		}
		return $data;
	}
	private function setDojoLock($recs){
        if($this->ACCESS==$this->SLIM->LeaderLevel){
			$locks=[];
			foreach($this->DOJO_LOCK as $i=>$v){
				$locks[]="DojoLock LIKE '%:\"{$v}%'";
			}
			if($locks){
				$locks=implode(' OR ',$locks);
				$recs->and($locks);
			}
		}
		return $recs;		
	}
	private function setAccessLevel($recs){
		if($this->ACCESS < $this->SLIM->SuperLevel){
			$recs->and('Access <= ?',$this->ACCESS);
		}
		return $recs;
	}
    private function getUsers($refs=[]){
		$this->init();
        $recs=$this->DB->Users->select("id, Name, Username,Email, Access, Status, MemberID, DojoLock");
        if($refs) $recs->where($refs);
        $recs=$this->setDojoLock($recs);
        $recs=$this->setAccessLevel($recs);
        $rez=renderResultsORM($recs,'id');
        if(!$rez) $rez=[];
        return $rez;
    }
    private function getUser($ref=0){
		$this->init();
		if($ref==='new'){
			$dsp['id']=0;//for js
			foreach($this->FIELDS as $i=>$v){
				if($v['type']==='int'||$v['type']==='tinyint'){
					$val=0;
				}else if($v['type']==='datetime'){
					$val=date('Y-m-d 00:00:00');
				}else{
					$val='';
				}
				$dsp[$i]=$val;
			}
			return $dsp;
		}
		$ref=(int)$ref;
		if(!$ref) return [];
        $recs=$this->DB->Users->where('id',$ref);
        $recs=$this->setDojoLock($recs);
        $recs=$this->setAccessLevel($recs);
        $rez=renderResultsORM($recs,'id');
        return $rez[$ref];
    }

    //by any
    private function getUsersBy($what=false,$g=false){
		$this->init();
		if($what && $g){
			if($this->validField($what)){
				$recs=$this->DB->Users->select('id, Name, Username, Access, Status, MemberID');
				$recs->where($what,$g)->order('id');
				$recs=$this->setDojoLock($recs);
				$recs=$this->setAccessLevel($recs);
				return renderResultsORM($recs,'id');
			}
			$this->ERR[]=__METHOD__.': fieldname is not valid.['.$what.']';
		}
		return [];			
    }
	public function updateRecord($post=false,$id=0){
		$response=array('message'=>'* user update error *','message_type'=>'alert','update'=>false,'status'=>500,'ref'=>$id,'type'=>'message');
		if($id>0 && is_array($post) && !empty($post)){
			$rec=$this->DB->Users->where("id", $id);
			$pw=issetCheck($post,'Password');
			if($pw && $pw!==''){
				//update user password
				$MU = new makeUser();
				$MU->PERMISSIONS=$this->SLIM->Permissions->get('roles');
				$update = $MU->Process($post);
			}else{
				$update=$post;
				//remove password field
				unset($update['Password']);
			}
			//flatten dojo lock
			if($chk=issetCheck($update,'DojoLock')){
				$update['DojoLock']=serialize(array_values($chk));
			}
			//flatten permissions
			if($chk=issetCheck($update,'Permissions')){
				$update['Permissions']=serialize($this->SLIM->Permissions->get('decimals',$chk));
			}
			//validate post
			$update=$this->validateData($update);
			if(is_array($update)){
				$result=$rec->update($update);
				if($result){
					$levels=$this->SLIM->Options->get('access_levels_name');
					$active=$this->SLIM->Options->get('active');
					$update['Status']=issetCheck($active,$update['Status'],'- not set -');
					$update['Access']=issetCheck($levels,$update['Access'],'- not set -');
					$response['message']='Okay, the record has been updated...';
					$response['status']=200;
					$response['message_type']='success';
					$response['update']=$update;
				}else{
					$response['message']='It does not seem like you have made any changes...';
					$response['status']=201;
					$response['message_type']='primary';
				}				
			}else{
				$response['message']='Okay, but nothing was updated...';
				$response['status']=201;
				$response['message_type']='primary';
			}
		}else{
			$response['message']='Sorry, the details received are invalid...';
		}
		return $response;
	}
 	
 	public function addRecord($post=false){
		$response=array('message'=>'* add user error *','message_type'=>'alert','update'=>false,'status'=>500,'ref'=>0);
		if(is_array($post) && !empty($post)){
			//check user exists
			if($this->checkExists($post)){
				$response['message']='Sorry, the user already exists...';
			}else{
				$MU = new makeUser;
				$chk = $MU->Process($post);
				if(is_array($chk)){					
					$row=$this->DB->Users()->insert($chk);
					$response['message']='Okay, the record has been added...';
					$response['status']=200;
					$response['message_type']='success';
				}else{
					$response['message']=$chk;
				}
			}		
		}else{
			$response['message']='Sorry, the details received are invalid...';
		}
 		return $response;
	}
	public function checkExists($args=[]){
		if($args){
			foreach($args as $i=>$v){
				$chk = $this->getUsersBy($i,$v);
				if($chk) return true;
			}
		}			
		return false;
	}
}

