<?php
// log even registrations from forms submitted on the public site
// used via slimEventsForms
class slimLogRegistration{
	private $SLIM;
	private $RESPONSE;
	private $CART;
	private $FORM_POST;//data from the posted form
	private $RESET_ANKFID=false;
	private $USER;
	var $CART_ITEMS=array('product_ref'=>false,'product_ref2'=>false,'shinsa'=>false,'AdditionalFee'=>false,'ikyf_reg'=>false);
	var $SUBS_SLUGS=array('membership-annual','ikyf-id-registration','membership-annual-eur','ikyf-id-registration-eur','annual-membership-inactive','annual-membership-inactive-eur');
	var $MEMBER_ID;//from the form.  only members can post forms.
	var $EVENT;
	var $EVENT_ID;
	var $PRODUCTS;
	var $ARGS;
	var $REGISTERED;
	var $DEBUG=false;
	
	function __construct($slim=null){
		if(!$slim){
		  throw new Exception(__METHOD__.': no slim object!!');
		}
		$this->SLIM=$slim;
		$this->USER=$slim->user;
	}
	function log($args=false){
		if(is_array($args)){
			$this->FORM_POST=$args;
			if($this->ready()){
				$rsp= $this->logME();
				if($this->DEBUG) preME($rsp,2);
				return $rsp;
			}
		}else{
			$this->RESPONSE['message']='Sorry, the data must be an array...';
		}
		return $this->RESPONSE;
	}
	private function ready(){
		$this->RESPONSE=array('status'=>500,'data'=>false,'message'=>false);
		$this->CART=array('items'=>$this->CART_ITEMS,'total'=>0,'qty'=>0,'subscription'=>false);

		if(!$this->FORM_POST||empty($this->FORM_POST)){
			$this->RESPONSE['message']='Sorry, the Form data has not been loaded...';
			return false;
		}
		if(!$this->MEMBER_ID){
			$this->RESPONSE['message']='Sorry, the Member ID was not found...';
			return false;
		}
		if($this->REGISTERED){
			$this->RESPONSE['message']='already_registered';
			return false;
		}		
		if(!$this->EVENT){
			if($chk=issetCheck($this->FORM_POST,'event_id')){
				$this->RESPONSE['message']='Sorry, the Event data has not been loaded...';
				return false;
			}
		}
		if(!$this->PRODUCTS){
			$this->RESPONSE['message']='Sorry, the Product data has not been loaded...';
			return false;
		}
		return true;
	}
	
    private function logME(){
		$parts=array_keys($this->CART_ITEMS);
		foreach($parts as $p){
			$this->addProduct($p);
		}
		if($this->CART['qty']>0){
			$chk=$this->saveEventLog();
			$log_id=($chk)?$chk:0;
			$this->saveSales($log_id);
			$this->saveForm($log_id);
			$this->saveSubscription();			
			$this->RESPONSE['status']=200;
		}else{
			$this->RESPONSE['message']='Sorry, no products found...';
		}

		return $this->RESPONSE;
	}
	private function addProduct($what=false){
		if(isset($this->FORM_POST[$what])){
			$prod=issetCheck($this->PRODUCTS,$this->FORM_POST[$what]);
			if($prod) $this->cartAdd($what,$prod);
		}
	}
	
	private function cartAdd($what,$product){
		$this->CART['items'][$what]=$product;
		$this->CART['total']+=(int)$product['ItemPrice'];
		$this->CART['qty']++;
		if($what==='AdditionalFee'){
			//do something
		}		
	}
	private function cartGetItem($what,$part=false){
		$item=issetCheck($this->CART['items'],$what);
		if($item){
			if($part){
				return issetCheck($item,$part);
			}
			return $item;
		}
		return false;
	}
	private function saveEventLog(){
		$log_insert=array(
			'EventLogID'=>0,
			'EventID'=>(int)$this->FORM_POST['event_id'],
			'MemberID'=>$this->MEMBER_ID,
			'Attending'=>1,
			'Forms'=>1,
			'EventCost'=>$this->CART['total'],
			'Room'=>(int)$this->cartGetItem('product_ref','ItemOrder'),
			'Shinsa'=>(int)$this->cartGetItem('shinsa','ItemID'),
			'AdditionalFee'=>(int)$this->cartGetItem('AdditionalFee','ItemID'),
			'ProductID2'=>(int)$this->cartGetItem('product_ref2','ItemID'),
			'ProductID'=>$this->FORM_POST['product_ref'],
		);
		$log=$this->SLIM->db->EventsLog->where('EventID',$log_insert['EventID'])->and('MemberID',$log_insert['MemberID']);
		if(count($log)){//exists
			$log=renderResultsORM($log,'EventLogID');
			$chk=key($log);
		}else{
			$chk=0;
			if($this->DEBUG){
				$chk=1;
				preME($log_insert);
			}else{
				$db=$this->SLIM->db->EventsLog;					
				$log=$db->insert($log_insert);
				if($log) $chk=$db->insert_id();
			}
		}
		return $chk;
	}
	
	private function saveSales($log_id=0){
		$si=[];$chk=null;
		$sales_insert=$this->SLIM->Sales->getNewRecord();
		unset($sales_insert['ID']);
		$sales_insert['Ref']=$this->SLIM->Sales->getNextRef();
		$sales_insert['SalesDate']=date('Y-m-d');
		$sales_insert['MemberID']=$this->MEMBER_ID;
		$sales_insert['EventRef']=(int)$this->FORM_POST['event_id'];
		$sales_insert['EventLogRef']=$log_id;
		
		$parts=array_keys($this->CART_ITEMS);
		foreach($parts as $p){
			$prod=$this->cartGetItem($p);
			if($prod){
				$sp=(int)$prod['ItemPrice'];
				if(!$sp) continue;
				$sales_insert['SoldPrice']=$sp;
				$sales_insert['ItemID']=(int)$prod['ItemID'];
				$sales_insert['Currency']=$prod['ItemCurrency'];
				$sales_insert['ItemType']=$prod['ItemGroup'];
				if(in_array($p,['AdditionalFee','ikyf_reg'])){
					if(!(int)$this->CART['subscription']){
						$this->CART['subscription']=(int)$prod['ItemID'];
						$sales_insert['StartDate']=date('Y-m-d');
						$sales_insert['EndDate']=date('Y-m-d',strtotime('+364 days'));
					}
				}else{
					//remove start & end dates
					if(isset($sales_insert['StartDate'])) unset($sales_insert['StartDate'],$sales_insert['EndDate']);
				}
				$si[]=$sales_insert;
			}
		}		
		if($si){
			if($this->DEBUG){
				$chk=1;
				preME($si);
			}else{			
				$chk=$this->SLIM->db->Sales()->insert_multi($si);
			}
			$this->RESPONSE['data']=['sales'=>array('ref'=>$sales_insert['Ref']),'log_id'=>$log_id];
		}
		return $chk;
	}
    
    private function saveForm($log_id=0){
		$form_insert=array(
			'ID'=>0,
			'MemberID'=>$this->MEMBER_ID,
			'MemberName'=>$this->FORM_POST['FirstName'].' '.$this->FORM_POST['LastName'],
			'EventLogID'=>$log_id,
			'LogDate'=>date('Y-m-d H:i:s'),
			'FormData'=>compress($this->FORM_POST),
			'FormStatus'=>'submit',
		);
		if($this->DEBUG){
			$chk=1;
			preME($form_insert);
		}else{
			$chk=$this->SLIM->db->FormsLog()->insert($form_insert);
		}
		return $chk;
	}
	private function saveSubscription(){
		//no longer required - use sales db instead
		return;
		$chk=0;
		if($this->CART['subscription']){
			$subs_insert=$this->SLIM->Subscriptions->getNewRecord();
			unset($subs_insert['ID'],$subs_insert['PaymentDate'],$subs_insert['PaymentRef']);
			$subs_insert['MemberID']=$this->MEMBER_ID;
			$subs_insert['ItemID']=$this->CART['subscription']['ItemID'];
			$subs_insert['StartDate']=date('Y-m-d');
			$subs_insert['Notes']='<p>Purchased with seminar registration.</p>';
			$subs_insert['Status']=1;
			if($this->DEBUG){
				$chk=1;
				preME($subs_insert);
			}else{
				$chk=$this->SLIM->Subscriptions->saveRecord(0,$subs_insert);
			}
		}	
		return $chk;
	}
	private function newMember(){
		//not used - members only. code is here for reference
		$new_member=issetCheck($this->FORM_POST,'new_member');
		if($new_member){
			//add grade log
			$grade_insert=array(
				'GradeLogID'=>0,
				'MemberID'=>$this->MEMBER_ID,
				'GradeDate'=>$this->FORM_POST['CGradedate'],
				'LocationID'=>0,
			);
			$chk_a=$this->SLIM->db->GradeLog()->insert($grade_insert);
		}		
	}
}
