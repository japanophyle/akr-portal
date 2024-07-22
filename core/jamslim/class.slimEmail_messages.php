<?php

class slimEmail_messages{
	private $SLIM;
	private $USER;
	private $LIMIT_OWNER=0;

	function __construct($slim=null){
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		if($this->USER['access'] < $this->SLIM->SuperLevel) $this->LIMIT_OWNER=$this->USER['id'];
	}
	
	function get($what=false,$vars=false){
		switch($what){
			case 'all': case 'id': case 'messages':
				return $this->getMessage($what,$vars);
				break;
			default:
				return false;
		}
	}
	function set($what=false,$vars=false){
		switch($what){
			case 'new_message':case 'save_message':case 'update_message':
				return $this->setMessage($what,$vars);
				break;
			default:
				return false;
		}
	}
	private function setMessage($ref=false,$args=false){
		$db=$this->SLIM->db->Emailer();
		$chk=$run=false;
		switch($ref){
			case 'new_message':
				if(isset($args['Subject'])){
					$args['Owner']=$this->USER['id'];
					$chk=$db->insert($args);
					$run=1;
				}
				break;
			case 'save_message':
				$id=issetCheck($args,'ID');
				if($id){
					unset($args['ID']);
					$rec=$db->where('ID',$id);
					if(isset($args['Message'])) $args['Message']=base64_encode($args['Message']);
					$chk=$rec->update($args);
					$run=1;
				}
				break;
			case 'update_message':
				$id=issetCheck($args,'ID');
				if($id){
					unset($args['ID']);
					$rec=$db->where('ID',$id);
					if(isset($args['Recipients']) && is_array($args['Recipients'])) $args['Recipients']=json_encode($args['Recipients']);
					$chk=$rec->update($args);
					$run=1;
				}
				break;
				
		}
		if($run && !$chk){
			$err=$this->SLIM->pdo->errorInfo();
			if(issetCheck($err,2)){
				$chk=0;
			}else{
				$chk=1;//no change
			}
		}
		return $chk;
	}	
	
	private function getMessage($ref=false,$args=false){
		$db=$this->SLIM->db->Emailer();
		$recs=[];
		switch($ref){
			case 'id':
				$rec=$db->where('ID',(int)$args);
				break;
			case 'all':
				$sel=($this->LIMIT_OWNER)?'ID,Subject,LastSent,Status,Recipients':'ID,Subject,LastSent,Status,Owner,Recipients';
				$rec=$db->select($sel);
				break;
			default:
				$rec=[];
		}
		if($this->LIMIT_OWNER){
			$rec->where('Owner',$this->LIMIT_OWNER);
		}
		if($rec){
			$recs=renderResultsORM($rec,'ID');
			if($ref==='all'){
				$recs=$this->totalReicipients($recs);
			}else if(!$recs && $args==0){
				$recs[0]=['ID'=>0,'Subject'=>'','LastSent'=>'','Status'=>0,'Message'=>'','Recipients'=>[]];
			}else{
				$msg=(is_null($recs[$args]['Message']))?'':base64_decode($recs[$args]['Message']);
				$msg=html_entity_decode($msg);
				$recs[$args]['Message']=$msg;
			}
		}
		return $recs;		
	}
	
	private function totalReicipients($recs){
		$out=[];
		foreach($recs as $i=>$v){
			$tmp=issetCheck($v,'Recipients');
			if($tmp && $tmp!==''){
				$tmp=json_decode($tmp);
				$c=count($tmp);
				$v['Recipients']=count($tmp);
			}else{
				$v['Recipients']=0;
			}
			$out[$i]=$v;
		}
		return $out;
	}
}
