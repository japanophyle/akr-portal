<?php

class slimSales{
	
	private $SLIM;
	private $PRODUCTS;
	private $PRODUCT_GROUPS;
	private $PRODUCT_CATS;	
	private $SALES_DATA;//the results of $this->getSales()
	private $CURRENT_REF;
	private $LANG;
	private $AJAX;
	private $ROUTE;
	private $SALES_DB;
	private $TRANS;
	private $OUTPUT;
	private $DOJOS;
	private $DEFAULT_REC=array('ID'=>0,'Ref'=>false,'MemberID'=>0,'ItemID'=>0,'ItemType'=>0,'SoldPrice'=>0,'Currency'=>1,'SalesDate'=>false,'StartDate'=>null,'EndDate'=>null,'Length'=>1,'Paid'=>0,'PaymentDate'=>null,'PaymentRef'=>false,'EventRef'=>0,'EventLogRef'=>0,'Status'=>0,'Notes'=>false);
	private $STATES;
	private $REQUEST;
	private $ARGS;
	private $ACTION;
	private $PROD_ID=array('membership'=>[],'ikyf'=>[]);
	private $DEBUG=array('add'=>false,'delete'=>false,'update'=>false);
	private $TEMP_USER;//for admin use
	private $USER;
	private $PERMBACK;
	
	public $ADMIN;
	public $SITE;
		
	function __construct($slim){
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		$this->TEMP_USER=$slim->TempLogin;
		$this->STATES=$slim->SubscriptionStates;
		$this->STATES[0]=array('name'=>'due','color'=>'red');
		$this->LANG=$slim->language->get('_LANG');
		$this->AJAX=$slim->router->get('ajax');
		$this->ROUTE=$slim->router->get('route');
		$this->REQUEST=$slim->router->get('post');
		$this->DOJOS=$this->SLIM->Options->get('dojos');
		$this->PERMBACK=URL.'sales/';
		$this->PRODUCT_GROUPS=$slim->Options->get('product_types');
		$this->PRODUCT_CATS=$slim->Options->get('product_categories');
		$this->initProducts();
		
		$this->TRANS['single']=array('en'=>'Seminar + Single Room','fr'=>'Stage chambre simple','de'=>'Seminar + Einzelzimmer');
		$this->TRANS['simpledouble']=array('en'=>'Seminar + Single Room','fr'=>'Stage chambre simple','de'=>'Seminar + Einzelzimmer');
		$this->TRANS['double']=array('en'=>'Seminar + Double Room','fr'=>'Stage chambre double','de'=>'Seminar + Doppelzimmer');
		$this->TRANS['sans-heberg']=array('en'=>'Seminar (No accommodation)','fr'=>'Stage sans héberg','de'=>'Seminar ohne Unterkunft');
	}
	public function dev_importEventLog(){
		$EV=$this->SLIM->db->EventsLog();
		$recs=$EV->where('ProductID > ?',0)->or('shinsa > ?',0)->or('AdditionalFee > ?',0);
		$recs=renderResultsORM($recs);
		$inv=1;
		$invb=date('Y').str_pad(date('z'),3,'0',STR_PAD_LEFT);
		foreach($recs as $data){
			$tot=0;
			$prods['prod']=((int)$data['ProductID'])?$this->getProduct($data['ProductID']):false;
			$prods['shinsa']=((int)$data['shinsa'])?$this->getProduct($data['shinsa']):false;
			$prods['fee']=((int)$data['AdditionalFee'])?$this->getProduct($data['AdditionalFee']):false;
			foreach($prods as $i=>$v) {
				if($v) $tot+=$v['ItemPrice'];
			}
			$balance=($tot-$data['PaymentAmount']);
			$status=($balance==0)?5:0;			
			$invref=$invb.str_pad($inv,4,'0',STR_PAD_LEFT);
			foreach($prods as $k=>$prod){
				if($prod){
					$paid=($balance==0)?$prod['ItemPrice']:0;
					$row[]=array(
						'Ref'=>$invref,
						'MemberID'=>$data['MemberID'],
						'ItemID'=>$prod['ItemID'],
						'ItemType'=>$prod['ItemGroup'],
						'SoldPrice'=>$prod['ItemPrice'],
						'Currency'=>$prod['ItemCurrency'],
						'SalesDate'=>null,
						'StartDate'=>null,
						'EndDate'=>null,
						'Length'=>0,
						'Paid'=>$paid,
						'PaymentDate'=>$data['PaymentDate'],
						'PaymentRef'=>false,
						'EventRef'=>$data['EventID'],
						'EventLogRef'=>$data['EventLogID'],
						'Status'=>$status,
						'Notes'=>false
					);
				}
			}
			$inv++;
		}
        if($row){
			//$this->SLIM->db->debug=function($q,$p){preME($q,2);};
			//$chk=$this->SALES_DB->insert_multi($row);
			//preME($chk);
		}
		preME($row,2);
	}
	public function dev_importSubscriptions(){
		$EV=$this->SLIM->db->Subscriptions();
		$recs=$EV->where('ItemID',$this->PROD_ID['membership']);
		$recs=renderResultsORM($recs);
		$inv=155;//update before running
		$invb='2019000';
		foreach($recs as $data){
			$tot=0;
			$prods['prod']=((int)$data['ItemID'])?$this->getProduct($data['ItemID']):false;
			foreach($prods as $i=>$v) {
				if($v) $tot+=$v['ItemPrice'];
			}
			$balance=($tot-$data['Paid']);
			$status=$data['Status'];			
			$invref=$invb.str_pad($inv,4,'0',STR_PAD_LEFT);
			$sdate=($data['PaymentDate'])?$data['PaymentDate']:$data['StartDate'];
			foreach($prods as $k=>$prod){
				if($prod){
					$paid=($balance==0)?$prod['ItemPrice']:0;
					$row[]=array(
						'Ref'=>$invref,
						'MemberID'=>$data['MemberID'],
						'ItemID'=>$prod['ItemID'],
						'ItemType'=>$prod['ItemGroup'],
						'SoldPrice'=>$prod['ItemPrice'],
						'Currency'=>$prod['ItemCurrency'],
						'SalesDate'=>$sdate,
						'StartDate'=>$data['StartDate'],
						'EndDate'=>$data['EndDate'],
						'Length'=>1,
						'Paid'=>$paid,
						'PaymentDate'=>$data['PaymentDate'],
						'PaymentRef'=>$data['PaymentRef'],
						'EventRef'=>$data['EventID'],
						'EventLogRef'=>$data['EventLogID'],
						'Status'=>$status,
						'Notes'=>$data['Notes']
					);
				}
			}
			$inv++;
		}
        if($row){
			//$this->SLIM->db->debug=function($q,$p){preME($q,2);};
			//$chk=$this->SALES_DB->insert_multi($row);
			//preME($chk);
		}
		preME($row,2);
	}
	
	function dev_fixSubsStatus(){
		$EV=$this->SLIM->db->Subscriptions();
		$recs=$EV->where('ItemID',$this->PROD_ID['ikyf']);
		//$recs=$EV->where('ItemID',$this->PROD_ID['membership']);
		$recs=renderResultsORM($recs);
		$test=[];
		foreach($recs as $i=>$v){
			$upd=$this->SLIM->db->Sales()->where('MemberID',$v['MemberID'])->and('ItemID',$v['ItemID'])->and('StartDate',$v['StartDate']);
			if(count($upd)>0){
				//$chk=$upd->update(array('Status'=>$v['Status']));
				$test[$i]='Set Sales id:'.$upd[0]['ID'].' to '.$v['Status'].' ?';
			}
		}
		preME($test,2);
	}

	private function resetDB(){
		$this->SALES_DB=$this->SLIM->db->Sales();
	}
	public function getNextRef($date=false){
		//do this at the last moment before adding a record;
		$time=($date)?strtotime($date):time();
		$year=date('Y',$time);
		$day=str_pad(date('z',$time),3,'0',STR_PAD_LEFT);
		$this->resetDB();
		$chk=$this->SALES_DB->select('Ref')->where('Ref LIKE ?',$year.$day.'%')->order('Ref DESC')->limit(1);
		$chk=renderResultsORM($chk);
		if(count($chk)>0){
			$chk=current($chk);
			$t=str_replace($year.$day,'',$chk['Ref']);
			$t=((int)$t + 1);
			$next=$year.$day.str_pad($t,4,'0',STR_PAD_LEFT);
		}else{
			$next=$year.$day.'0001';
		}
		return $next;
	}
	private function initProducts(){
		$prods=$this->SLIM->db->Items()->where('ItemType','product')->order('ItemTitle')->select('ItemID,ItemTitle,ItemPrice,ItemCategory,ItemGroup,ItemSlug,ItemStatus,ItemCurrency,ItemContent,ItemShort');
		$prods=renderResultsORM($prods,'ItemID');
		// these must have the correct spellings!! case sensative
		$subs_grp=array_search('Subscriptions',$this->PRODUCT_GROUPS);
		$ikyf_grp=array_search('IKYF seminar',$this->PRODUCT_GROUPS);
		foreach($prods as $i=>$v){
			$this->PRODUCTS[$i]=$v;
			if($v['ItemGroup']==$subs_grp){
				$this->PROD_ID['subscription'][$i]=$i;
			}else if($v['ItemGroup']==$ikyf_grp){
				$this->PROD_ID['ikyf'][$i]=$i;
			}
			if($v['ItemCategory']==2) $this->PROD_ID['membership'][$i]=$i;
		}
	}
	private function getDojoMembers($args=[]){
		$out=[];
		if($args){
			foreach($args as $i){
				$chk=$this->SLIM->db->Members()->select("MemberID")->where('DojoID',(int)$i);
				$chk=renderResultsORM($chk);
				foreach($chk as $x=>$y)	$out[$y['MemberID']]=$y['MemberID'];
			}
		}
		return $out;
	}
	private function getDojo($ref=false,$return=false,$by=false){
		if($ref){
			switch($by){
				case 'country':
				case 'name':
					$k=($by==='country')?'LocationCountry':'ShortName';
					foreach($this->DOJOS as $i=>$v){
						if(trim($v[$k])===$ref){
							if($return){
								$out=issetCheck($v,$return);
							}else{
								$out=$v;
							}
							return $out;
						}
					}
					break;
				default://by id
					$out=issetCheck($this->DOJOS,$ref);
					if($return){
						$out=issetCheck($out,$return);
					}
					return $out;
			}
		}
		return false;
	}
	private function getEvent($id){
		$EV=$this->SLIM->db->Events();
		$rec=$EV->select('EventID,EventType,EventName,EventDate,EventAddress,EventProduct,EventCurrency,EventStatus')->where('EventID',$id);
		$rec=renderResultsORM($rec);
		$rec=current($rec);
		return $rec;
	}
	private function getEventLog($id){
		$EV=$this->SLIM->db->EventsLog();
		$rec=$EV->where('EventLogID',$id);
		$rec=renderResultsORM($rec);
		$rec=current($rec);
		return $rec;
	}
	private function calcInvoice($data=[],$paid=0){
		$tot=array('qty'=>0,'count'=>0,'value'=>0,'paid'=>0);
		foreach($data as $i=>$v){
			$tot['count']+=1;
			$tot['qty']+=1;
			$tot['value']+=(int)$v['SoldPrice'];
			$tot['paid']+=(int)$v['Paid'];
		}
		if($paid){
			if($paid!=$tot['paid']) $tot['paid']=$paid;
		}
		return $tot;
	}
	private function getExpiredKeys($prods=[]){
		$hasRenewed=function($member,$item,$actives){
			foreach($actives as $i=>$v){
				if($v['MemberID']==$member){
					if($v['ItemID']==$item) return true;
				}
			}
			return false;			
		};
		$keys=[];
		$actives=$this->SALES_DB->select('ID,MemberID,ItemID')->where('Status',1)->and('ItemID',$prods)->order('EndDate ASC');
		$actives=renderResultsORM($actives,'ID');
		$this->resetDB();
		$cref=$this->getState('name','expired');
		$expired=$this->SALES_DB->select('ID,MemberID,ItemID')->where('Status',$cref)->and('ItemID',$prods)->order('EndDate ASC');
		$expired=renderResultsORM($expired,'ID');
		$this->resetDB();
		foreach($expired as $i=>$v){
			$chk=$hasRenewed($v['MemberID'],$v['ItemID'],$actives);
			if(!$chk){
				$keys[]=$i;
			}
		}
		return $keys;		
	}
	public function getSubscriptions($what=false,$ref=false,$new_status=false){
		$data=[];
		switch($what){
			case 'member':
				$data=$this->getSales('member_subs',$ref,true);
				break;
			case 'dojo':
				$data=$this->getSales('dojo_subs',$ref,true);
				break;
			default:
				$return=true;
				if(in_array($new_status,array(2,3,4,8))) $return=$new_status;
				$data=$this->getSales($what,$ref,$return);
		}
		if(!in_array($what,['status_summary_subs']) && $data){
			//do something
		}
		if(!$data) $data=[];
		return $data;
	}
	public function getInvoiceRecord($what=false,$ref=false){
		$paid_amount=0;
		$elog=false;
		if(in_array($what,['id','log','ref'])){
			if($tmp=$this->getSales($what,$ref,true)){
				$tmp=current($tmp);
				$elog=$tmp['EventLogRef'];
				$ref=$tmp['Ref'];
			}else{
				$ref=0;
			}
		}
		if($ref==0) return array();
		$data=$this->getRecords('ref',$ref,true);
		if($elog){
			$data['elog']=$this->getEventLog($elog);
			$paid_amount=$data['elog']['PaymentAmount'];
		}		
		$calc=$this->calcInvoice($data['log'],$paid_amount);
		$data['metrics']=$calc;
		return $data;
	}
	public function getRecords($what=false,$ref=false,$summary=false){
		$this->resetDB();
		$out=$event=$prods=$members=$dojos=[];
		$dojos[0]=array('id'=>0,'LocationID'=>0,'LocationName'=>'- no dojo -','LocationCountry'=>false);
		$data= $this->getSales($what,$ref,true);
		if(!$data) $data=[];
		foreach($data as $i=>$v){
			$member=$this->getMember($v['MemberID']);
			if(!$member){
				$member=['MemberID'=>$v['MemberID'],'Dojo'=>'','Name'=>'?? id: '.$v['MemberID'],'CGradeName'=>''];
				$dojo=['id'=>0];
			}else{
				$dojo=$this->getDojo($member['Dojo'],false,'name');
			}
			if($what==='by_dojo' && trim($member['Dojo'])===''){
				$member['Dojo']='- no dojo -';
				$dojo['id']=0;
			}
			if((int)$v['ItemID']){
				$prods[$v['ItemID']]=$this->getProduct($v['ItemID']);
				$event[$v['EventRef']]=$this->getEvent($v['EventRef']);
				$members[$v['MemberID']]=$member;
				if(!isset($dojos[$dojo['id']]))$dojos[$dojo['id']]=$dojo;
				$v['ItemName']=$this->getProduct($v['ItemID'],'name');
				$v['MemberName']=$member['Name'];
				$v['StatusName']=$this->STATES[$v['Status']]['name'];
				$v['StartDate']=validDate($v['StartDate']);
				$v['EndDate']=validDate($v['EndDate']);
				$v['SalesDate']=validDate($v['SalesDate']);
				$v['PaymentDate']=validDate($v['PaymentDate']);
				$v['DojoID']=$dojo['id'];
				$out[$i]=$v;
			}
		}
		return array('log'=>$out,'events'=>$event,'products'=>$prods,'members'=>$members,'dojos'=>$dojos,'elog'=>false);
	}
	public function getProductSales($product_id=false){
		$data=[];
		if($product_id || $this->PRODUCTS){
			if(is_numeric($product_id)){
				$data[$product_id]=$this->getSales('product',$product_id,true);
			}else{
				$_data=(is_array($product_id))?$product_id:array_keys($this->PRODUCTS);
				foreach($_data as $k){
					$data[$k]=$this->getSales('product',$k,true);
				}
			}
		}
		return $data;	
	}

	public function getNewRecord(){
		return $this->DEFAULT_REC;
	}
	public function checkSubscription($what=false,$id=0){
		$this->resetDB();
		switch($what){
			case 'membership':
				$rec=$this->SALES_DB->where('MemberID',$id)->and('ItemID',$this->PROD_ID['membership'])->order('EndDate DESC')->limit(1);
				if(count($rec)>0){
					$rec=renderResultsORM($rec);
					return current($rec);
				}
				break;
			case 'ikyf':
				$rec=$this->SALES_DB->where('MemberID',$id)->and('ItemID',$this->PROD_ID['ikyf'])->order('EndDate DESC')->limit(1);
				if(count($rec)>0){
					$rec=renderResultsORM($rec);
					return current($rec);
				}
				break;
		}
		return false;
	}
	private function getSales($what=false,$ref=false,$return=false){
		$this->resetDB();
		$this->CURRENT_REF=$ref;
		$rec=[];
		switch($what){
			case 'new':
				$this->CURRENT_REF='new';
				$this->SALES_DATA['new']=$this->DEFAULT_REC;
				if($return) return $this->SALES_DATA;
				break;
			case 'id':
				$rec=$this->SALES_DB->where('ID',(int)$ref);
				break;
			case 'type':
				$rec=$this->SALES_DB->where('ItemType',(int)$ref);
				break;
			case 'product':
				$rec=$this->SALES_DB->where('ItemID',$ref);
				break;		
			case 'ref':
				$rec=$this->SALES_DB->where('Ref',$ref);
				break;		
			case 'notify':
				$rec=$this->SALES_DB->where('Notify',$ref)->and('Status',2);
				break;		
			case 'member':
				$rec=$this->SALES_DB->where('MemberID',$ref)->and('ItemID > ?',0);
				break;
			case 'member_subs':
				$rec=$this->SALES_DB->where('MemberID',$ref)->and('ItemID',$this->PROD_ID['membership'])->order('EndDate DESC');
				break;
			case 'by_member':
			case 'by_dojo':
				$rec=$this->SALES_DB->where('MemberID > ?',0)->and('ItemID > ?',0);
				break;
			case 'dojo':
			case 'dojo_subs':
				$members=$this->getDojoMembers(array($ref));
				$rec=$this->SALES_DB->where('MemberID',$members);
				if($what==='dojo_subs') $rec->where('ItemID',$this->PROD_ID['membership'])->order('EndDate DESC');
				break;
			case 'event':
				if($ref){
					$rec=$this->SALES_DB->where('EventRef',$ref);
				}else{
					$rec=$this->SALES_DB->where('EventRef > ?',0);
				}
				break;
			case 'log':
				$rec=$this->SALES_DB->where('EventLogRef',$ref);
				break;
			case 'membership':
			case 'ikyf':
				$rec=$this->SALES_DB->where('ItemID',$this->PROD_ID[$what]);
				break;
			case 'unpaid':
				$rec=$this->SALES_DB->where('Paid < SoldPrice');
				break;
			case 'overpaid':
				$rec=$this->SALES_DB->where('Paid > SoldPrice');
				break;
			case 'current'://will find the most recent item
				$rec=$this->SALES_DB->where('MemberID',$ref)->order('EndDate DESC')->limit(1);
				break;
			case 'current_membership':
				$rec=$this->SALES_DB->where('MemberID',$ref)->and('ItemID',$this->PROD_ID['membership'])->order('EndDate DESC')->limit(1);
				break;
			case 'current_ikyf':
				$rec=$this->SALES_DB->where('MemberID',$ref)->and('ItemID',$this->PROD_ID['ikyf'])->order('EndDate DESC')->limit(1);
				break;
			case 'start':
				$rec=$this->SALES_DB->where('StartDate',$ref);
				break;		
			case 'end':
				$rec=$this->SALES_DB->where('EndDate',$ref);
				break;		
			case 'active':
			case 'disabled':
			case 'expired':
			case 'cancelled':
			case 'status':
			case 'renewed':
				$this->CURRENT_REF=($what==='status')?$ref:$this->getState('name',$what);
				$rec=$this->SALES_DB->where('Status',$this->CURRENT_REF)->order('EndDate ASC');
				break;
			case 'subs_active':
			case 'subs_disabled':
			case 'subs_expired':
			case 'subs_cancelled':
			case 'subs_status':
			case 'subs_renewed':
			case 'subs_unpaid':
			case 'subs_renewed_pending':
				$tmp=$this->PROD_ID['subscription'];
				if($what==='subs_unpaid'){
					$rec=$this->SALES_DB->where('Paid',0)->where('Status',1)->and('ItemID',$tmp)->order('EndDate ASC');
				}else if($what==='subs_unpaid_all'){
					$rec=$this->SALES_DB->where('Paid',0)->where('Status <> ?',0)->and('ItemID',$tmp)->order('EndDate ASC');
				}else if($what==='subs_expired'){
					$keys=$this->getExpiredKeys($tmp);
					$rec=$this->SALES_DB->where('ID',$keys)->order('EndDate ASC');					
				}else{
					$w=str_replace('subs_','',$what);
					$this->CURRENT_REF=($what==='subs_status')?$ref:$this->getState('name',$w);
					$rec=$this->SALES_DB->where('Status',$this->CURRENT_REF)->and('ItemID',$tmp)->order('EndDate ASC');
				}
				break;
			case 'status_summary':
				$rec=$this->SALES_DB->select('Status,COUNT(*) AS Cnt')->group('Status')->order('Status');
				if($this->USER['access']<25){
					$members=$this->getDojoMembers($this->USER['dojo_lock']);
					$rec->and('MemberID',$members);
				}
				return renderResultsORM($rec);
				break;
			case 'subs_disabled':
				$tmp=$this->PROD_ID['subscription'];
				$this->CURRENT_REF=($what==='status')?$ref:$this->getState('name',$what);
				$rec=$this->SALES_DB->where('Status',$this->CURRENT_REF)->and('ItemID',$tmp)->order('EndDate ASC');
				break;
			case 'status_summary_subs':
			case 'status_summary_membership':
			case 'status_summary_ikyf':
				$this->CURRENT_REF=false;
				if($what==='status_summary_subs'){
					$tmp=$this->PROD_ID['subscription'];
				}else{
					$w=str_replace('status_summary_','',$what);
					$tmp=$this->PROD_ID[$w];	
				}
				$rec=$this->SALES_DB->select('Status,COUNT(*) AS Cnt')->where('ItemID',$tmp)->group('Status')->order('Status');
				if($this->USER['access']<25){
					$members=$this->getDojoMembers($this->USER['dojo_lock']);
					$rec->and('MemberID',$members);
				}
				return renderResultsORM($rec);
				break;
			case 'expire_<':
				$rec=$this->SALES_DB->where('EndDate < ? ',$ref)->and('Status',1);
				$r=(int)$return;
				if(in_array($r,array(2,3,4,8))){//update status
					if(count($rec)>0){
						//update sales status
						$rec->update(array('Status'=>$r));
						//reset ANKF/IKF ID
						$this->resetREG_ID($rec);
					}
				}
				break;		
			case 'expire_7':
			case 'expire_15':
			case 'expire_30':
			case 'expire_60':
				$tmp=explode('_',$what);
				$target=date('Y-m-d',strtotime('+ '.$tmp[1].' days'));
				$rec=$this->SALES_DB->where('EndDate',$target)->and('Status',1);
				if($ref){
					if($this->USER['access']<25){
						$members=$this->getDojoMembers($this->USER['dojo_lock']);
						$rec->and('MemberID',$members);
					}
					return count($rec);
				}
				break;		
			case 'expire_next_7':
			case 'expire_next_15':
			case 'expire_next_30':
			case 'expire_next_60':
				$tmp=explode('_',$what);
				$target=date('Y-m-d',strtotime('+ '.$tmp[2].' days'));
				$rec=$this->SALES_DB->where('EndDate >= ?',date('Y-m-d'))->and('EndDate <= ?',$target)->and('Status',1);
				if($ref){
					if($this->USER['access']<25){
						$members=$this->getDojoMembers($this->USER['dojo_lock']);
						$rec->and('MemberID',$members);
					}
					return count($rec);
				}
				break;		
			case 'all':
				$this->CURRENT_REF=false;
				$rec=$this->SALES_DB;
				break;		
		}
		if(count($rec)){
			if($this->SITE==='public'){
				$rec->and('MemberID',$this->USER['MemberID']);
			}else if($this->USER['access'] < $this->SLIM->AdminLevel){
				$members=(issetCheck($this->USER,'dojo_lock'))?$this->getDojoMembers($this->USER['dojo_lock']):[];
				if($members) $rec->and('MemberID',$members);
			}
			$rec=renderResultsORM($rec,'ID');
			if($return) return $rec;
			$this->SALES_DATA=$rec;
		}		
	}
	
	public function saveRecord($id=false,$data=false){
		$chk=$this->saveSubscription($id,$this->DEFAULT_REC,$data);
		$state=500;
		$close=false;
		$msg_type='alert';
		if(is_array($chk)){
			$chk['type']='message';
			if($chk['status']==200){
				$chk['close']=true;
				$msg_type='success';
			}
			$chk['message_type']=$msg_type;
			return $chk;
		}
		if($chk){
			$msg='Okay, the record has been added.';
			$close=true;
			$state=200;
			$msg_type='success';
		}else{
			$err=$this->SLIM->pdo->errorInfo();
			if(issetCheck($err,2)){
				$msg='Sorry, there was a problem saving the record...['.$err[0].']';
			}else{
				$msg='Okay, but no changes have been made...';
				$state=200;
				$close=true;
				$msg_type='primary';
			}
		}
		return array('status'=>$state,'message'=>$msg,'message_type'=>$msg_type,'close'=>$close,'type'=>'message');
	}
	private function saveSubscription($id=false,$rec=false,$data=false){
		$id=(int)$id;
		$out=null;
		if(isset($data['action'])){
			switch($data['action']){
				case 'update_payment':
				case 'update_payment_elog':
				case 'update_subscription':
					$out=$this->updateRecords($id,$rec,$data);
					break;
				case 'add_payment':
					$out=$this->addRecords($data);
					break;
			}
		}
		return $out;
	}
	private function validateSalesRecord($_data,$next_ref=false){
		$tmp=[];
		$keys=array('Ref','MemberID','ItemID','ItemType','SoldPrice','Currency','SalesDate','StartDate','EndDate','Length','Paid','PaymentDate','PaymentRef','EventRef','EventLogRef','Status','Notes');					
		foreach($keys as $key){
			if(array_key_exists($key,$_data)){
				$val=$_data[$key];
				switch($key){
					case 'SoldPrice':case 'Paid':
						$val=toPennies($val);
						break;
					case 'SalesDate':
						if(!$val||$val==='') $val=date('Y-m-d');
						break;
					case 'StartDate': case 'EndDate': case 'PaymentDate':
						if(!$val||$val==='') $val=null;
						break;
					case 'MemberID':
						if(!$val||$val==='') $val=$this->USER['MemberID'];
						break;
					case 'Ref':
						if(!$val||$val==='') $val=$next_ref;
						break;
					case 'Length':
						if(!$val||$val==='')$val=0;
						break;
					case 'ItemID':
						//ensure product info is set
						if((int)$val>0){
							$prod=$this->getProduct($_data['ItemID']);
							if(!isset($_data['ItemType'])) $_data['ItemType']=$prod['ItemGroup'];
							if(!isset($_data['SoldPrice'])) $_data['SoldPrice']=$prod['ItemPrice'];
							if(!isset($_data['Currency'])) $_data['Currency']=$prod['ItemCurrency'];				
						}
						break;					
				}
				$tmp[$key]=$val;
			}
		}
		return $tmp;
	}
	private function addRecords($data){
		$row=[];$chk=null;
		$next_ref=$this->getNextRef();
		if(is_array($data)){
			if(issetCheck($data,'items')){
				foreach($data['items'] as $d){
					$valid=$this->validateSalesRecord($d,$next_ref);
					if(!issetCheck($valid,'Ref')) $valid['Ref']=$next_ref;
					if(!issetCheck($valid,'SalesDate')) $valid['SalesDate']=date('Y-m-d');
					if($valid) $row[]=$valid;
				}
			}else if(issetCheck($data,'products')){//from admin subcriptions
				$now=date('Y-m-d');
				foreach($data['products'] as $i=>$v){
					$valid=$this->DEFAULT_REC;
					$valid['Ref']=$next_ref;
					$valid['MemberID']=issetCheck($data,'MemberID',0);
					$valid['ItemID']=$i;
					$valid['SoldPrice']=toPennies($v);
					$valid['SalesDate']=$now;
					$valid['StartDate']=issetCheck($data,'StartDate',$now);
					$valid['EndDate']=date('Y-m-d',strtotime($valid['StartDate'].' + 1 year'));
					$valid['Status']=1;
					$row[]=$valid;
				}
			}else{
				$valid=$this->validateSalesRecord($data,$next_ref);
				if(!issetCheck($valid,'Ref')) $valid['Ref']=$next_ref;
				if(!issetCheck($valid,'SalesDate')) $valid['SalesDate']=date('Y-m-d');
				if($valid) $row[]=$valid;
			}
			if($row){
				$this->resetDB();
				if($this->DEBUG['add']) $this->SLIM->db->debug=function($q,$p){preME($q,2);};
				$chk=$this->SALES_DB->insert_multi($row);
				if($chk){
					$chk=['status'=>200,'ref'=>$next_ref,'id'=>$this->SALES_DB->insert_id()];
				}
			}
		}
		return $chk;
	}
	
	private function updateRecords_elog($id,$rec,$data){
		$rec=$this->getRecords('log',$id);
		$out=0;
		$new=false;	
		$prod_keys=array('ProductID','ProductID2','Shinsa','AddionalFee');
		$nulls=array('StartDate','EndDate','PaymentDate','SalesDate');
		$elog_update=[];
		foreach($prod_keys as $pk){
			$done=false;
			if(isset($data[$pk.'_x'])){
				if($data[$pk]!=$data[$pk.'_x']){
					$old=$data[$pk.'_x'];
					$new=$data[$pk];
					//find old record
					foreach($rec['log'] as $i=>$v){
						if($v['ItemID']==$old){							
							$prod=$this->getProduct($new);
							if($prod){
								$cur=$v;
								$cur['ItemID']=$prod['ItemID'];
								$cur['SoldPrice']=$prod['ItemPrice'];
								$cur['ItemType']=$prod['ItemGroup'];
								$cur['Currency']=$prod['ItemCurrency'];
								if(!$cur['SalesDate']){
									if($cur['PaymentDate']){
										$cur['SalesDate']=$cur['PaymentDate'];
									}else{
										$cur['SalesDate']=issetCheck($data,'sales_date',$data['PayDay']);
									}									
								}
								if(!$cur['Ref'])$cur['Ref']=issetCheck($data,'sales_ref');
								//null the dates
								foreach($nulls as $n){
									if(!$cur[$n]) $cur[$n]=null;
								}								
								if(is_null($cur['StartDate'])) $cur['EndDate']=null;
								$cur['Status']=$this->setRecordStatus($cur,$cur['Paid']);
								$elog_update[$pk]=$new;
								unset($cur['ItemName'],$cur['MemberName'],$cur['DojoID'],$cur['StatusName'],$cur['ID']);
								$out+=$this->doUpdate($i,$cur);
								$done=true;
								break;
							}
						}
					}
					if(!$done){//add new record
						if($new){
							$prod=$this->getProduct($new);
							if($prod){
								$cur=$this->DEFAULT_REC;
								$cur['ItemID']=$prod['ItemID'];
								$cur['SoldPrice']=$prod['ItemPrice'];
								$cur['ItemType']=$prod['ItemGroup'];
								$cur['Currency']=$prod['ItemCurrency'];
								$cur['ItemName']=$prod['ItemTitle'];
								$cur['Status']=0;
								$cur['Paid']=0;
								$tmp=current($rec['log']);
								if($tmp){
									$cur['Ref']=$tmp['Ref'];
									$cur['MemberID']=$tmp['MemberID'];
									$cur['EventRef']=$tmp['EventRef'];
									$cur['EventLogRef']=$tmp['EventLogRef'];
									$cur['SalesDate']=$tmp['SalesDate'];
								}else{
									$cur['MemberID']=$data['MemberID'];
									$cur['EventRef']=$data['EventID'];
									$cur['EventLogRef']=$id;
									$cur['SalesDate']=$data['PayDay'];
								}
								if(!$cur['Ref']||$cur['Ref']==='') $cur['Ref']=issetCheck($data,'sales_ref');
								if(!$cur['SalesDate']) $cur['SalesDate']=issetCheck($data,'sales_date');
								//null the dates
								foreach($nulls as $n){
									if(!$cur[$n]) $cur[$n]=null;
								}
								if(is_null($cur['StartDate'])) $cur['EndDate']=null;								
								$elog_update[$pk]=$new;
								unset($cur['ItemName'],$cur['MemberName'],$cur['DojoID'],$cur['StatusName'],$cur['ID']);
								$out+=$this->addRecords($cur);
							}
						}else{//check for removed items
							//find old record
							foreach($rec['log'] as $i=>$v){
								if($v['ItemID']==$old){
							preME(array($new,$pk,$old,$v),2);
									$cur=$this->DEFAULT_REC;
									$cur['Status']=0;
									$cur['Paid']=0;
									$out+=$this->doUpdate($i,$cur);
									$elog_update[$pk]=0;
								}
							}
						}						
					}
				}
			}
		}
		if($out){
			$this->updatePaymentRecords_elog($id,$elog_update);			
		}
		return $out;		
	}
	private function updatePaymentRecords_elog($id=false,$elog_update=false){
		if($id){			
			//fix paid values by invoice - sales db
			$rec=$this->getRecords('log',$id);
			$rec['elog']=$this->getEventLog($id);
			$chk=$this->updatePaidAmounts($rec['elog']['PaymentAmount'],$rec['elog']);
			//update eventlog
			if($elog_update){
				$db=$this->SLIM->db->EventsLog();
				$ev=$db->where('EventLogID',$id);
				if(count($ev)>0){
					$ev->update($elog_update);
				}
			}
		}
	}
	private function updateRecords($id,$rec,$data){
		$out=array('status'=>500,'message'=>'Sorry, problem updating sales...','type'=>'message','message_type'=>'alert');

		if($data['action']==='update_payment_elog'){
			$chk=$this->updateRecords_elog($id,$rec,$data);
			if($chk>0){
				$out['status']=200;
				$out['message']='Okay, the records have been updated.';
				$out['message_type']='success';
			}
			return $out;
		}else if($data['action']==='update_subscription'){
			return $this->updateSubscription($id,$rec,$data);
		}
		if($paid=issetCheck($data,'PaymentAmount')){//from $post
			$paid=toPennies($paid);
		}else{
			$paid=$rec['metrics']['paid'];
		}
		$match=$allocated=[];
		$prods=count($rec['log']);
		$log_id=0;
		$paid_state='due';
		$sum=$msum=$paid;
		$pay_day=validDate($data['PaymentDate']);
		if(!$pay_day) $pay_day=null;
		$update=array(
			'Paid'=>$paid,
			'PaymentDate'=>$pay_day,
			'PaymentRef'=>$data['PaymentRef'],
			'Status'=>0
		);
		//check for matching price
		$allocated=$this->updatePaidAmounts($paid,$rec['log'],false);
		$inv_status=$this->setInvoiceStatus($allocated['value'],$paid);
		$rollcall_update=array(
			'Paid'=>($inv_status>0)?1:0,
			'PaymentAmount'=>$paid,
		);

		$chk=0;
		foreach($rec['log'] as $i=>$v){
			if(!$log_id) $log_id=issetCheck($v,'EventLogRef');
			$go=true;
			$update['Paid']=$allocated['items'][$i]['Paid'];
			$update['Status']=$this->setRecordStatus($v,$update['Paid']);
			//do update
			if($go)	{
				$chk+=$this->doUpdate($i,$update);
			}
		}
		if($chk>0){
			//update rollcall paymentAmount
			if($log_id){
				$db=$this->SLIM->db->EventsLog();
				$ev=$db->where('EventLogID',$log_id);
				if(count($ev)>0) $ev->update($rollcall_update);
			}
			$out['status']=200;
			$out['message']='Okay, the records have been updated.';
			$out['message_type']='success';
		}
		return $out;	
	}
	private function updateSubscription($id,$default,$data=false){
		if(is_array($data)){
			$ref=issetCheck($data,'Ref');
			$update=$this->validateSalesRecord($data,$ref);
			$rec=$this->SLIM->db->Sales->where('ID',$id);
			if(count($rec)>0){
				$chk=$rec->update($update);
				if($chk){
					$state=200;
					$msg='Okay, the records have been updated.';
					$mtype='success';
				}else{
					$err=$this->SLIM->db_error;
					$state=($err)?500:200;
					$msg=($err)?'Sorry, there was a problem: '.$err:'Okay, but it does not look like you have made any changes...';
					$mtype=($err)?'alert':'primary';
				}
			}else{
				$state=500;
				$msg='Sorry, I can\'t find a record to update...';
				$mtype='alert';
			}
		}else{
			$state=500;
			$msg='Sorry, no data recieved...';
			$mtype='alert';
		}
		return array('status'=>$state,'message'=>$msg,'message_type'=>$mtype);
	}
	private function updatePaidAmounts($total_paid=0,$log=false,$process=true){
		$chk=0;
		$data=array('value'=>0,'items'=>false);
		if(is_array($log)){
			foreach($log as $i=>$v){
				if($total_paid){
					if($v['SoldPrice']<=$total_paid){
						$tmp=$v['SoldPrice'];
					}else{
						$tmp=$total_paid;
					}
					$total_paid=($total_paid-$tmp);
				}else{
					$tmp=0;
				}
				if($process){				
					$SLS=$this->SLIM->db->Sales();
					$upd=$SLS->where('ID',$i);
					if(count($upd)>0){
						$chk+=$upd->update(array('Paid'=>$tmp));
					}
				}else{
					$v['Paid']=$tmp;
					$data['value']+=$v['SoldPrice'];
					$data['items'][$i]=$v;
				}				
			}
		}
		return ($process)?$chk:$data;
	}
	
	private function allocatePayment($paid,$log){
		$out=$match=false;
		$msum=$value=0;
		$sum=$paid;
		$count=count($log);
		$ct=1;
		if($count==1){
			$i=key($log);
			$v=current($log);
			$out[$i]['Paid']=$paid;
			$value=$v['SoldPrice'];
			return array('match'=>$match,'value'=>$value,'paid'=>$paid,'diff'=>($value-$paid),'items'=>$out);	
		}
		//get total and matched value		
		foreach($log as $i=>$v){
			$value+=$v['SoldPrice'];
			if(!$match){
				if($paid==$v['SoldPrice'] && $paid!=$v['Paid']){
					$match=$i;
					$state=$v['Status'];
					$msum=($sum-$v['SoldPrice']);
				}
			}
		}
		//allocate payment
		foreach($log as $i=>$v){
			$is_sub=false;
			$state=0;
			if($paid==$value){//set all to paid
				$out[$i]['Paid']=$v['SoldPrice'];
			}else if($match){
				if($match==$i){
					$out[$i]['Paid']=$v['SoldPrice'];
				}else{
					if($msum>=$v['SoldPrice']){
						$out[$i]['Paid']=$v['SoldPrice'];
						$msum=($msum-$v['SoldPrice']);
					}else{
						$out[$i]['Paid']=$msum;
						$msum=0;
					}
				}
			}else{				
				if($ct==$count){//is last item?
					$out[$i]['Paid']=$sum;
					$sum=0;
				}else if($sum>=$v['SoldPrice']){
					$out[$i]['Paid']=$v['SoldPrice'];
					$sum=($sum-$v['SoldPrice']);
				}else{
					$out[$i]['Paid']=$sum;
					$sum=0;
				}
			}
			$ct++;
		}

		return array('match'=>$match,'value'=>$value,'paid'=>$paid,'diff'=>($value-$paid),'items'=>$out);
		
	}
	private function doUpdate($id,$update){
		$out=false;
		$rec=$this->SLIM->db->Sales()->where('ID',$id);
		if(count($rec)>0){
			if($this->DEBUG['update']) $this->SLIM->db->debug=function($q,$p){preME($q,2);};
			$out=$rec->update($update);
			if($out){
				//skip
			}else{
				$err=$this->SLIM->pdo->errorinfo();
				if(!$err[2]) $out=1;//no change
			}
		}
		return $out;
	}
	public function removeRecords($what=false,$ref=false,$action=false){
		$debug=false;
		$curr=[];
		$out=array('status'=>500,'message'=>false,'type'=>'message','message_type'=>'alert','close'=>true);
		if(hasAccess($this->SLIM->user,'events','delete')){
			$delete_log=false;
			if($action==='elog'){
				$rec=$this->getInvoiceRecord($what,$ref);
				$curr=current($rec['log']);
				$delete_log=(int)issetCheck($curr,'EventLogRef');
			}
			$chk=$this->doDelete($what,$ref,$debug);
			if($chk){
				if($delete_log){
					$tmp=$this->SLIM->db->EventsLog()->where('EventLogID',$delete_log);
					$deleted=($debug)?count($tmp):$tmp->delete();
					if($deleted) $deleted=' - including the rollcall ref:'.$delete_log;
				}
				$out['status']=200;
				$out['message']='Okay, '.$chk.' record(s) has been deleted '.$deleted.'.';
				$out['message_type']='success';
			}
		}else{
			$out['message']='Sorry, you don\'t have access for that action...';
		}
		if($this->AJAX){
			jsonResponse($out);
		}else{
			$last=$this->SLIM->session['last_route'];			
			$url=URL.implode('/',$last);
			setSystemResponse($url,$out['message']);
		}
		die;
	}
	private function doDelete($what,$ref,$debug=true){
		$out=false;
		if($what && $ref){
			$rec=$this->SLIM->db->Sales();
			switch($what){
				case 'ID': 
				case 'Ref':
				case 'EventLogRef':
				case 'EventRef':
				case 'MemberID':
					$rec->where($what,$ref);
					if($this->DEBUG['delete']) $this->SLIM->db->debug=function($q,$p){preME($q,2);};
					$out=($debug)?count($rec):$rec->delete();
					break;
				default:
			}
		}
		return $out;
	}
		
	private function getMember($id=0,$what=false){
		$out=null;
		if($id){
			$chk=$this->SLIM->db->Members()->select("MemberID, CONCAT(FirstName,' ',LastName) AS Name,Address,City,Town,PostCode,Country, CGradeName,Dojo,Email,Language")->where('MemberID',(int)$id);
			$chk=renderResultsORM($chk);
			if($chk){
				$chk=current($chk);
				if($what){
					if($what==='name'){ $what='Name';
						$out=$chk['Name'];
					}else if($what==='name_info'){
						$out=$chk['Name'].' / '.$chk['CGradeName'].' / '.$chk['Dojo'];
					}
				}else{
					$keys=['Address','City','Town','PostCode','Country'];
					$address=[];
					foreach($keys as $k){
						$tmp=trim(issetCheck($chk,$k,''));
						if($tmp!==''){
							if($k==='Address'){
								if(strpos($tmp,',')!==false){
									$tmp=str_replace("&#13;&#10;",'',$tmp);
									$tmpx=explode(',',$tmp);
									$tmpy=[];
									foreach($tmpx as $tx){
										$tx=trim($tx);
										if($tx!=='') $tmpy[]=$tx;
									}
									$tmp=implode('<br/>',$tmpy);
								}else if(strpos($tmp,"&#13;&#10;")){
									$tmp=str_replace("&#13;&#10;",'<br>',$tmp);
								}
							}
							$address[]=$tmp;
						}
					}
					$chk['AddressBlock']=implode("<br/>",$address);
					$out=$chk;
				}
			}
		}else{
			$chk=$this->SLIM->Options->get('active_members');
			foreach($chk as $i=>$v){
				if(trim($v['Name'])!=='') $out[$i]=$v['Name'];
			}
		}
		return $out;
	}
	private function getProduct($id=0,$what=false){
		$out=null;
		if($id){
			$chk=issetCheck($this->PRODUCTS,$id);
			if($chk){
				if($what){
					if($what==='name') {
						$out=$chk['ItemTitle'];
					}else if($what==='name_price'){
						$out=$chk['ItemTitle'].' / '.toPounds($chk['ItemPrice']);
					}else{
						$out=issetCheck($chk,$what);	
					}
				}else{
					$out=$chk;
				}
			}
		}else{
			foreach($this->PRODUCTS as $i=>$v){
				if($v['ItemGroup']==0){//limit to single items
					$out[$i]=$v['ItemTitle'].' / '.toPounds($v['ItemPrice']);
				}
			}
		}
		return $out;
	}
	public function getState($what=false,$ref=false){
		if($ref==='all') return $this->STATES;
		if(is_numeric($ref)){
			$rec=issetCheck($this->STATES,$ref);
			if($what) return issetChec($rec,$what);
			return $rec;
		}else{
			$id=-1;
			foreach($this->STATES as $i=>$v){
				switch($what){
					case 'name':
					case 'color':
						if($ref===$v[$what]){
							$id=$i;
						}
						break;
				}
				if($id>=0) break;
			}
			if($id<0) $id=0;
			return $id;
		}
	}
	private function setInvoiceStatus($value=0,$paid=0){
		$state=0;
		if($value>0){
			if($value==$paid){
				$state=5;
			}else if($paid>$value){
				$state=6;
			}
		}
		return $state;
	}	

	private function setRecordStatus($rec=false,$paid=0,$diff=0){
		$state=0;
		$is_sub=false;
		$sold=(int)$rec['SoldPrice'];
		$paid=(int)$paid;
		foreach($this->PROD_ID as $i=>$v){
			if(in_array($rec['ItemID'],$v)) $is_sub=true;
		}
		if($sold==$paid){
			if($is_sub){
				$tm=strtotime($rec['EndDate']);
				if($tm>time()){
					$state=1;
				}else{
					$state=4;
				}
			}else{
				$state=5;
			}
		}else if($paid>$sold){
			$state=6;
		}else if ($paid<$sold){
			$state=0;
		}
		return $state;
	}
	private function setRecordStatus_old($id,$paid=false,$data=false){
		if(!$data){
			$data=$this->getSales('id',$id,true);
			$data=current($data);
		}
		if(!is_numeric($paid)) $paid=$data['Paid'];
		$is_sub=false;
		foreach($this->PROD_ID as $i=>$v){
			if(in_array($id,$v)) $is_sub=true;
		}
		$state=0;
		if($data['SoldPrice']<$paid){

		}else if($data['SoldPrice']==$paid){
			if($is_sub){
				$tm=strtotime($data['EndDate']);
				if($tm>time()){
					$state=1;
				}else{
					$state=4;
				}
			}else{
				$state=5;
			}
		}else if($paid>$data['SoldPrice']){
			$state=6;
		}
		return $state;
	}
	private function translateProduct($slug=false){
		if($slug){
			foreach($this->TRANS as $x=>$t){
				if(strpos($slug,$x)!==false){
					if($x==='sans-heberg'){						
						$title=$t[$this->LANG];
					}else{
						$title=$t[$this->LANG];
					}
					return $title;
				}
			}
		}
	}
	private function setVars(){
		$this->ACTION=issetCheck($this->ROUTE,1);
		$this->ARGS=issetCheck($this->ROUTE,2);
	}
	private function refreshSubscriptions(){
		$date=date('Y-m-d');
		$data=$this->getSales('expire_<',$date,2);
		$ct=(is_array($data))?count($data):0;
		$msg=($ct>0)?'Okay, '.$ct.' records(s) have been updated.':'Okay, all records are up to date.';
		$url=$this->PERMBACK;
		setSystemResponse($url,$msg);
		die('oops!');
	}
	private function resetREG_ID($recs=false){
		//resets the ANKF/IKF ID on expired subscriptions
		if(!is_object($recs)) return;
		$recs=renderResultsORM($recs);
		$today=date('Y-m-d 00:00:00');
		$mid=[];
		foreach($recs as $rec){
			if(in_array($rec['ItemID'],[45,23])){
				if($rec['EndDate'] <  $today){
					$mid[$rec['MemberID']]=$rec['MemberID'];
				}
			}
		}
		if($mid){//update members record			
			$members=$this->SLIM->db->Members->where('MemberID',array_keys($mid));
			if(count($members)>0){
				$members->update(['AnkfID'=>0]);
			}
		}
	}
	public function Postman($post=false){		
		$state=500;$msg_type='alert';$close=false;
		$action=issetCheck($post,'action');
		$id=issetCheck($post,'id');
		switch($action){
			case 'add_record':
				$id=0;
				$rec=$this->DEFAULT_REC;
				$chk=$this->saveSubscription($id,$rec,$post);
				if($chk){
					$msg=$chk['message'];//'Okay, the subscription has been added.';
					$state=$chk['status'];
					$close=($chk['status']!==200)?false:true;
					$msg_type=$chk['message_type'];
				}else{
					$msg='Sorry, there was problem adding the subscription...';
				}
				break;
			case 'update_payment':
			case 'update_record':
				if($id){
					$rec=$this->getInvoiceRecord($post['id_type'],$id);
					if(!empty($rec['log'])){						
						$chk=$this->saveSubscription($id,$rec,$post);
						if($chk){
							$msg=$chk['message'];//'Okay, the subscription has been added.';
							$state=$chk['status'];
							$msg_type=$chk['message_type'];
						}else{
							$msg='Okay, but nothing was updated...';
							$state=201;
							$msg_type='primary';
						}
					}else{
						$msg='Sorry, I can\'t find that record ['.$id.']...';
					}
				}else{
					$msg='Sorry, incomplete data supplied...';
				}
				break;
			default:
				$msg='Sorry, I don\'t know what "'.$action.'" is...';
		}
		$out=array('status'=>$state,'message'=>$msg,'message_type'=>$msg_type,'close'=>$close,'type'=>'message');
		if($this->AJAX){
			jsonResponse($out);
			die;
		}else{
			return $out;
		}
	}
	
}
