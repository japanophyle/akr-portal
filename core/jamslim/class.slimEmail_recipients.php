<?php

class slimEmail_recipients{
	private $SLIM;
	private $SELECTED_RECIPIENTS;
	private $RECIPIENTS;
	private $USER;
	public $AJAX;
	public $SESSION_NAME='email_recipients';
	public $MODE='admin';
	public $POST=false;
	public $PERMLINK;

	function __construct($slim=null){
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		$this->AJAX=$slim->router->get('ajax');
		$this->PERMLINK=URL.'admin/mailer/';
	}
	
	function get($what=false,$vars=false){
		
		switch($what){
			case 'selected':
				return $this->getSelectedRecipients();
				break;
			case 'recipients':
				return $this->RECIPIENTS;
				break;
			default:
				if($what){
					return $this->getRecipients($what,$vars);
				}
		}
		return false;
	}
	function set($reset=false){
		if(!$reset) $this->POST=$_POST;
		$this->setSelectedRecipients($reset);
	}
	function addSelected($what=false,$args=false){
		$r=$this->getRecipients($what,$args);
		$url=$this->PERMLINK;
		if($r){
			$chk=$this->addRecipients($r,true);
			$msg='Okay, the selected recipients list has been updated.';
			$state=200;
			$mt='success';
			$url.=(isset($this->POST['ID']))?'writer/'.$this->POST['ID']:'messages';
		}else{	
			$state=500;
			$msg='Sorry, I could not find any recipient details...';
			$mt='alert';
			$url.='messages';
		}
		if($this->AJAX){
			$out=array('status'=>$state,'message'=>$msg,'message_type'=>$mt,'type'=>'message');
			jsonResponse($out);
		}else{
			setSystemResponse($url,$msg);
		}
		die;
	}
	
	function add($data=false,$selected=false){
		return $this->addRecipients($data,$selected);
	}
	
	private function storeSelected(){
		setMySession($this->SESSION_NAME,$this->SELECTED_RECIPIENTS);
	}

	private function getRecipients($what=false,$vars=false){
		if($what==='members') $what=$vars;
		switch($what){
			case 'active':
			case 'inactive':
			case 'type':
			case 'disabled':
			case 'inactive':
			case 'grade':
			case 'dojo':
			case 'dojo_active':
			case 'ins':
			case 'member':
				return $this->getMembers($what,$vars);
				break;
			case 'subscriptions':
			case 'subs_unpaid':
				return $this->getSubscriptions($what,$vars);
				break;
			case 'sales':
			case 'unpaid_sales':
			case 'product':
				return $this->getSales($what,$vars);
				break;
			case 'admins':
			case 'leaders':
			case 'users':
			case 'user':
				return $this->getUsers($what,$vars);
				break;
			case 'unselected':
				return $this->getUnselected();
				break;
			case 'messages':
				return $this->getPrevious($vars);
				break;
			case 'event':
				return $this->getRollcall($vars);
				break;
			default:
				return false;
		}
	}
	
	private function getUnselected(){
		$sel=$this->getSelectedRecipients();
		$ins=array_keys($sel);
		$recipients=[];
		$rp=$this->getMembers('not_ins',$ins);
		if($rp){
			foreach($rp as $i=>$v){
				$recipients[$i]=$v;
			}
		}
		return $recipients;		
	}
	
	private function getMembers($what,$vars){
		$recipients=[];
		$mtypes=$this->SLIM->options->get('membertype');
		$recs=$this->SLIM->db->Members()->select("MemberID, CONCAT(FirstName,' ',LastName) AS Name, CGradeName,Dojo,Email,Disable as Status, MemberTypeID")->order('Name ASC');
		switch($what){
			case 'dojo':
				$recs->where('DojoID',(int)$vars)->and('Disable',0)->and('Dead',0);
				break;
			case 'grade':
				if($vars==='nograde') $vars=null;
				$recs->where('CurrentGrade',$vars)->and('Disable',0)->and('Dead',0);
				break;
			case 'type':
				$vars=(int)$vars;
				if(!$vars) $vars=1;
				$recs->where('MemberTypeID',$vars);
				break;
			case 'inactive':
				$recs->where('Disable',1);
				break;
			case 'active':
				$recs->where('Disable',0)->and('Dead',0);
				break;
			case 'ins':
			case 'member':
				$recs->where('MemberID',$vars);
				break;
			case 'not_ins':
				if(is_array($vars) && !empty($vars)){
					$recs->where('MemberID NOT',$vars)->and('Disable',0)->and('Dead',0);
				}else{
					$recs->where('Disable',0)->and('Dead',0);
				}
				break;
			default:
				$recs=false;
		}
		if($this->USER['access'] == $this->SLIM->LeaderLevel){
			$dlock=issetCheck($this->USER,'dojo_lock',[0]);;
			$recs->where("DojoID", $dlock);
		}
		if($recs){
			$recs=renderResultsORM($recs,'MemberID');
			foreach($recs as $i=>$v){
				$tp=issetCheck($mtypes,$v['MemberTypeID']);				
				$recipients[$i]=array(
					'id'=>$i,
					'name'=>$v['Name'],
					'email'=>$v['Email'],
					'dojo'=>$v['Dojo'],
					'grade'=>$v['CGradeName'],
					'type'=>($tp)?$tp['OptionName']:'??'.$v['MemberTypeID'],
					'status'=>((int)$v['Status'])?'Disabled':'Enabled'
				);
			}
		}
		return $recipients;
	}
	private function getPrevious($message_id=0){
		$recipients=[];
		if($message_id){
			$rec=$this->SLIM->db->Emailer();
			$rec->select('ID,Recipients')->where('ID',$message_id);
			$rez=renderResultsORM($rec,'ID');
			if($cur=issetCheck($rez,$message_id)){
				$ins=json_decode($cur['Recipients'],1);
				$recipients=$this->getMembers('ins',$ins);
			}
		}
		return $recipients;
	}
	private function getSubscriptions($what=false,$ref=false){
		$recipients=[];
		$SLS=$this->SLIM->Sales;
		switch($what){
			case 'unpaid':
				$act='subs_unpaid';
				break;
			default:
				$act=($ref)?$ref:'subs_active';
		}
		$data=$SLS->getSubscriptions($act,false,true);
		if($data){
			$ins=$map=[];
			foreach($data as $x=>$y){
				$ins[$y['MemberID']]=$y['MemberID'];
				$map[$y['MemberID']][$x]=$y;
			}
			$rp=$this->getMembers('ins',$ins);
			foreach($rp as $i=>$v){
				$v['item']=$map[$i];
				$recipients[$i]=$v;
			}
		}
		return $recipients;
	}
	private function getSales($what=false,$ref=false){
		$recipients=[];
		$rid=false;
		$SLS=$this->SLIM->Sales;
		switch($what){
			case 'unpaid_sales':
				$act='unpaid';
				break;
			case 'product':
				$act=$what;
				$rid=$ref;
				break;
			default:
				$act='subs_active';
		}
		$d=$SLS->getRecords($act,$rid,false);
		if($data=issetCheck($d,'members')){
			$ins=$map=[];
			foreach($data as $i=>$v){
				$map[$i]['item']=$this->salesItemInfo($i,$d['log']);
				$ins[$i]=$i;
			}
			$rp=$this->getMembers('ins',$ins);
			foreach($rp as $i=>$v){
				$item=issetCheck($map,$i);
				$v['item']=($item)?implode('',$item['item']):'';
				$recipients[$i]=$v;
			}
		}
		return $recipients;
	}
	private function getRollcall($ref){
		$recipients=[];
		$data=$this->SLIM->db->EventsLog->select('EventLogID,MemberID,EventCost,Paid')->where('EventID',$ref);
		$event=$this->SLIM->db->Events->select('EventID,EventName,EventDate')->where('EventID',$ref);
		if($data=renderResultsORM($data,'MemberID')){
			$event=renderResultsORM($event);
			$event=current($event);
			$ins=$map=[];
			foreach($data as $i=>$v){
				$v['EventName']=$event['EventName'];
				$v['EventDate']=$event['EventDate'];
				$map[$i]['item']=$this->eventItemInfo($i,$v);
				$ins[$i]=$i;
			}
			$rp=$this->getMembers('ins',$ins);
			foreach($rp as $i=>$v){
				$item=issetCheck($map,$i);
				$v['item']=($item)?implode('',$item['item']):'';
				$recipients[$i]=$v;
			}
		}
		return $recipients;
	}
	private function salesItemInfo($mid,$data){
		$sls=[];
		foreach($data as $i=>$v){
			if($mid==$v['MemberID']){
				$tmp='<p><strong>'.$v['ItemName'].'</strong><ul>';
				$tmp.='<li>'.toPounds($v['SoldPrice'],$v['Currency']).'</li>';
				if(issetCheck($v['SalesDate'])) $tmp.='<li>'.$v['SalesDate'].'</li>';
				if(issetCheck($v['Ref']))$tmp.='<li><em>ref: '.$v['Ref'].'</em></li>';
				$tmp.='</ul></p>';
				$sls[$i]=$tmp;	
			}
		}
		return $sls;
	}
	private function eventItemInfo($mid,$data){
		$sls=[];
		if($mid==$data['MemberID']){
			$tmp='<p>Event: <strong>'.$data['EventName'].'</strong><ul>';
			$tmp.='<li>Cost: '.toPounds($data['EventCost']).'</li>';
			$tmp.='<li>Date: '.date('Y-m-d',strtotime($data['EventDate'])).'</li>';
			$paid=((int)$data['Paid'])?'Yes':'No';
			$tmp.='<li><em>Paid: '.$paid.'</em></li>';
			$tmp.='</ul></p>';
			$sls[$mid]=$tmp;	
		}
		return $sls;
	}	
	private function getUsers($what=false,$ref=false){
		$recipients=[];
		$DB=$this->SLIM->db->Users();
		$recs=$DB->select('id,MemberID');
		switch($what){
			case 'admins':
				$recs->where('Access',25);
				break;
			case 'leaders':
				$recs->where('Access',21);
				break;
			case 'users':
				$recs->where('Access',20);
				break;
			case 'user':
				$recs->where('id',$ref);
				break;
			default:
				$recs=false;				
		}
		$recs=$this->setDojoLock_users($recs);
		if($recs){
			$recs=renderResultsORM($recs,'id');
			$ins=[];
			foreach($recs as $i=>$v) $ins[$v['MemberID']]=$v['MemberID'];
			$recipients=$this->getMembers('ins',$ins);
		}
		return $recipients;
	}
	private function setDojoLock_users($recs){
        if($this->USER['access'] == $this->SLIM->LeaderLevel){
			$dlock=issetCheck($this->USER,'dojo_lock',[0]);
			$locks=[];
			foreach($dlock as $i=>$v){
				$locks[]="DojoLock LIKE '%:\"{$v}%'";
			}
			if($locks){
				$locks=implode(' OR ',$locks);
				$recs->and($locks);
			}
		}
		return $recs;		
	}

	private function addRecipients($data=false,$selected=false){
		$done=false;
		if(is_array($data) && !empty($data)){
			$out=($selected)?$this->getSelectedRecipients():$this->RECIPIENTS;
			foreach($data as $i=>$v){
				$email=issetCheck($v,'email');
				if($email){
					$out[$i]=$v;
				}
			}
			$out=sortArrayBy($out,'name');
			if($selected){
				$this->SELECTED_RECIPIENTS=$out;
				$this->storeSelected();
			}else{
				$this->RECIPIENTS=$out;
			}
			$done=true;
		}
		return $done;
	}

	private function getSelectedRecipients(){
		if(!$this->SELECTED_RECIPIENTS||empty($this->SELECTED_RECIPIENTS)){
			$data=[];
			if(!$data){//try to load from session
				$subs=issetCheck($_SESSION['userArray'],$this->SESSION_NAME);
				if($subs) $data=$subs;
			}
			$sort=($data)?sortArrayBy($data,'name'):[];
			$this->SELECTED_RECIPIENTS=$sort;
		}
		return $this->SELECTED_RECIPIENTS;
	}
	
	private function setSelectedRecipients($empty=false){
		$url=$this->PERMLINK;
		if($empty){
			$this->SELECTED_RECIPIENTS=[];
			$this->storeSelected();
			$msg='Okay, the selected recipients list has been reset.';
			$state=200;
			$mt='success';
			$url.='messages';
		}else if($this->POST){
			$this->getSelectedRecipients();
			$list_type=issetCheck($this->POST,'list_type');
			if($list_type==='selected'){//remove
				$remove=issetCheck($this->POST,'remove',[]);
				foreach($remove as $i=>$v){
					unset($this->SELECTED_RECIPIENTS[$i]);
				}
			}else{//add
				$send=issetCheck($this->POST,'send',[]);
				foreach($send as $i=>$v){
					if($v==='on'){
						if($this->MODE==='subscription'){
							$subs=$this->SLIM->Subscriptions->getRecords('id',$i,true,false);
							$this->SELECTED_RECIPIENTS[$i]=current($subs);
						}
					}
				}
			}
			$this->storeSelected();
			$msg='Okay, the selected recipients list has been updated.';
			$state=200;
			$mt='success';
			$url.=(isset($this->POST['ID']))?'writer/'.$this->POST['ID']:'messages';
		}else{
			$state=500;
			$msg='Sorry, I could not find any recipient details...';
			$mt='alert';
			$url.='messages';
		}
		if($this->AJAX){
			$out=array('status'=>$state,'message'=>$msg,'message_type'=>$mt,'type'=>'message');
			jsonResponse($out);
		}else{
			setSystemResponse($url,$msg);
		}
		die;
	}

}
