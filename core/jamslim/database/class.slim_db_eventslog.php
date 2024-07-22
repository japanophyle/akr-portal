<?php

class slim_db_eventslog extends slim_db_common{
	public $TABLE='EventsLog';
	public $DOJO_MEMBERS;// array of member ids
	
	//custom vars
	private $SELECTION=array(
		'basic'=>'*',
		'table'=>'EventLogID AS id,EventLogID, EventID, MemberID,Forms, Attending,ProductID,EventCost,Room,PaymentAmount'
	);
	private $LOG_KEYS=[];
	private $ADMIN_URL;
	private $LOG_LIST;

	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->EZPDO=$slim->ezPDO;
		$this->DB=$slim->db;
		$this->setFields();
		$this->ADMIN_URL=URL.'admin/';
	}
	public function get($what=false,$args=null){
		switch($what){
			case 'event':
			case 'event_report':
			case 'has_product':
			case 'unpaid':
			case 'overpaid':
			case 'product':
			case 'member':
			case 'member_report':
				$data=$this->getLogs($what,$args);
				break;
			case 'log':
				$data=$this->getLog($args);
				break;
			case 'download':
				$this->getDownload($what,$args);
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
	
	public function getDownload($what=false,$args=false){
		if($what && $args){
			$data=$this->getLogs($what,$args);
			if($data){
				$c=current($data);
				$data[0]=array_keys($c);
				ksort($data);
				$this->SLIM->Download->go($data,'Event_'.$args.'_Rollcall.xlsx');
			}else{
				echo 'Sorry, no data found...';
			}
			die;
		}else{
			die('Invalid Download Request');
		}		
	}
	
    public function getLogs($list=false,$ref=false) {
		$this->init();
		$this->LOG_LIST=$list;
	    $recs=$this->DB->EventsLog();
	    if($list==='member') $recs->select("EventLogID AS id,EventLogID, EventID, MemberID,Forms, Attending,ProductID,EventCost,Room,PaymentAmount");
        switch($list){
			case 'has_product':
				$recs->where('ProductID > ?',0)->order('EventID ASC,ProductID ASC');
				break;
			case 'event':
			case 'download':
			case 'event_report':
				$recs->where('EventID',$ref);
				break;
			case 'product':
				$recs->where('ProductID',$ref);
				break;
			case 'member':
				$recs->where('MemberID',$ref);
				break;
			case 'member_report':
				$recs->where('MemberID',$ref)->and('ProductID > 0');
				break;
			case 'unpaid':
				$recs->where('EventCost > ?',0)->and('PaymentAmount < EventsLog.EventCost');
				break;
			case 'overpaid':
				$recs->where('EventCost > ?',0)->and('PaymentAmount > EventsLog.EventCost');
				break;
		}
		if($this->DOJO_MEMBERS){
			$recs->and('MemberID',$this->DOJO_MEMBERS);
		}
		$rez=renderResultsORM($recs,'EventLogID');
        if(in_array($list,['event','download'])) $rez=$this->getLogDetails($rez,$ref);
        return $rez;
    }

    public function getLog($id=0) {
		if((int)$id){
			$this->init();
			$recs=$this->DB->EventsLog()->where("EventLogID", $id);
			$recs=renderResultsORM($recs,'EventLogID');
			$dsp=current($recs);
			$dsp['Reason']=fixNL($dsp['Reason']);
			//get member info
			$recs=$this->DB->Members()->select("FirstName, LastName, Dojo,CGradeName")->where("MemberID", $dsp['MemberID']);
			$recs=renderResultsORM($recs);
			$dsp['member']=current($recs);			
			//get event info
			$recs=$this->DB->Events()->select("EventName,EventType,EventProduct,EventDate, EventAddress, EventOptions, EventProductLimit2")->where("EventID", $dsp['EventID']);
			$recs=renderResultsORM($recs);
			$dsp['event']=current($recs);
			//get sales ref
			$recs=$this->DB->Sales()->select('Ref,SalesDate,EventLogRef')->where('EventLogRef',$id)->limit(1);
			$dsp['invoice']=renderResultsORM($recs,'Ref');
			//get event products
			if($dsp['event']){
				$recs=$this->DB->Items()->select("ItemID, ItemGroup, ItemTitle,ItemShort,ItemOrder,ItemPrice,ItemQty,ItemCategory,ItemStatus,ItemCurrency")->where("ItemGroup", $dsp['event']['EventProduct']);
				$tmp=renderResultsORM($recs,'ItemID');
				$dsp['products']=$tmp;
			}			
			//add log values for forms
			foreach($this->LOG_KEYS as $i=>$v) $dsp[$i]=issetCheck($dsp,$v,0);
			//edit member button
			$dsp['buttons']='<button class="button button-navy float-left loadME" data-ref="'.$this->ADMIN_URL.'member/edit/'.$dsp['MemberID'].'"><i class="fi-torso"></i> Edit Member</button>';
			$r=array('status'=>200,'data'=>$dsp,'ref'=>$id);
        }else{
			$r['message']='Sorry, no ID supplied...';
		}
        return $r;
    }  
    //by member  
    public function getLogsByMember($id=0) {
		$this->init();
        $recs=$this->DB->EventsLog()->select("*")->where("MemberID",$id)->order('EventID DESC');
        return renderResultsORM($recs,'EventID');
    }
    //by event  
    public function getLogsByEvent($id=0) {
		$this->init();
        $recs=$this->DB->EventsLog()->select("*")->where("EventID",$id)->order('EventID DESC');
        return renderResultsORM($recs,'EventID');
    }
	public function getSalesBalance($data=false){
		if(!$data) return [];
		return $this->SLIM->SalesMan->getBalance($data['data']['EventCost'],$data['data']['PaymentAmount']);
	}
	private function getEventReportData($id=0){
		$data=array();
		if($id>0){
			$event=$this->SLIM->Events->get('event',$id);
			if($event['status']==200){
				$EV=new EventItem($this->SLIM->Options,$event['data']);
				$d=$EV->render('report',2);
				if($d['status']==200) $data=$d['data']['rows'];
			}
		}
		return $data;		
	}
    
    public function getLogDetails($log=[],$event_id=0){
		$ins=$map=$new=array();
		//members
		if($log){
			foreach($log as $i=>$v){
				$ins[$v['MemberID']]=$v['MemberID'];
				$map[$v['MemberID']]=array('event'=>$i,'sort'=>0);
			}
			if(!empty($ins)){
				$recs=$this->DB->Members()->select("MemberID,FirstName,LastName,CGradeName,Dojo,Sex,Birthdate,DateJoined,CurrentGrade,CGradedate,CGradeLoc1,CGradeLoc2,NameInJapanese,NameInJapanese2,zasha,AnkfID,Country,Email")->where("MemberID", $ins)->order('CurrentGrade DESC');
				$members=renderResultsORM($recs,'MemberID');
				//sort log
				if($members){
					$gsort=$this->SLIM->Options->get('grade_sort',array('ins'=>array_keys($members)));
					$paid=$this->SLIM->Options->get('yesno');
					$ct=1;
					$recent_events=$this->getMemberRecentEvents(array_keys($members));
					foreach($members as $i=>$v){
						$k=issetCheck($map[$i],'event');
						if($k){
							$new[$k]=array(
								'id'=>$k,
								'EventLogID'=>$log[$k]['EventLogID'],
								'EventID'=>$log[$k]['EventID'],
								'MemberID'=>$v['MemberID'],
								'Member'=>$v['FirstName'].' '.$v['LastName'].'<br/><small>'.$v['Dojo'].'</small>',
								'Grade'=>$v['CGradeName'],
								'Shogo'=>'',
								'ShogoDate'=>'',
								'GradeID'=>$v['CurrentGrade'],
								'NIJ2'=>$v['NameInJapanese'],
								'NIJ'=>$v['NameInJapanese2'],
								'zasha'=>$v['zasha'],
								'AnkfID'=>$v['AnkfID'],
								'Forms'=>($log[$k]['Forms']>0)?'Received':'Pending',
								'Attending'=>($log[$k]['Attending']>0)?'Present':'Absent',
								'Room'=>$log[$k]['Room'],
								'EventCost'=>$log[$k]['EventCost'],
								'PaymentAmount'=>$log[$k]['PaymentAmount'],
								'Balance'=>0,
								'Paid'=>$paid[(int)$log[$k]['Paid']],
								'sortkey_grade'=>$gsort[$i]['gradeSortkey'],
								'FirstName'=>$v['FirstName'],
								'LastName'=>$v['LastName'],
								'Birthdate'=>validDate($v['Birthdate']),
								'Gender'=>$v['Sex'],
								'DateJoined'=>validDate($v['DateJoined']),
								'GradeDate'=>validDate($v['CGradedate']),
								'GradeLocaton'=>$v['CGradeLoc1'].' ('.$v['CGradeLoc2'].')',
								'Dojo'=>$v['Dojo'],
								'Country'=>$v['Country'],
								'Email'=>$v['Email'],
								'recent_events'=>issetCheck($recent_events,$i),								
							);
						}
					}
					if(!empty($new)) $log=$new;
				}
			}
		}
		//event info
		if($event_id>0){
			// do something
		}
		return $log;		
	}
	private function getMemberRecentEvents($member_ids=0){
		$data=[];
		if($member_ids){
			$DB = new slim_db_events($this->SLIM);
			$data=$DB->getMemberRecentEvents($member_ids);
			if($data && $this->LOG_LIST==='download'){
				//remove html tags
				foreach(array_keys($data) as $i){
					$v=strip_tags($data[$i]);
					$data[$i]=$v;
				}
			}
		}
		return $data;
	}	
    //Add Members: add members to an event
    public function doAddMembers($id=0,$post=false){
		//not used??
		if($id>0 && is_array($post['member'])){
			$this->init();
			//check if exists
			$this->RESULTS=$this->DB->EventsLog()->select("EventID,MemberID")->where("MemberID",$id)->and('EventID',$id);
			$exists=$this->renderResults();
			$response=false;
			$ct=1;
			$rec=$this->DB->EventsLog();
			foreach($post['member'] as $mid){
				$found=false;
				if($exists){
					foreach($exists as $i=>$v){
						if($v['MemberID']==$mid){
							$found=true;
							break;
						}
					}
				}
				if(!$found){
					//validate post
					$result=$rec->insert(array('EventID'=>$id,'MemberID'=>$mid,'Attending'=>1));
					if($result){						
						$response['message']='Okay, '.$ct.' members have been added to the event...';
						$response['status']=200;
						$ct++;
					}else{
						$response['message']='Sorry, there was a problem adding member['.$mid.'] the event...';
						$response['status']=201;
						break;
					}					
				}
			}
		}else{
			$response['message']='Sorry, the details supplied were invalid...';
			$response['status']=501;
		}
		if($response['status']==200){
			$response['type']='redirect';
			$response['url']=$this->ADMIN_URL.'events/editlog/'.$id.'/?list=event';
		}else{
			$response['type']='message';
		}
		return $response;
	}
	
	function deleteLog($id=0,$action=false){
		if($id>0){
			$this->init();
			$recs=$this->DB->EventsLog()->select("EventID,MemberID")->where("EventLogID",$id);
			$res=renderResultsORM($recs);
			if($res){
				$delete=$this->DB->EventsLog("EventLogID",$id)->delete();
				if($delete){
					$delete_sales=false;
					if($action==='sales'){
						$delete_sales=$this->DB->Sales("EventLogRef",$id)->delete();
						if($delete_sales) $delete_sales='[including '.$delete_sales.' sales record(s)]';
					}
					$response['status']=200;
					$response['type']='redirect';
					$response['url']=$this->ADMIN_URL.'events/editlog/'.$res[0]['EventID'].'/?list=event';
					$response['message']='Okay, the event record'.$delete_sales.' has been removed.';
					
				}else{
					$response['status']=201;
					$response['type']='message';
					$response['message']='Sorry, there was a problem removing the record...';
				}				
			}else{
				$response['status']=500;
				$response['type']='message';
				$response['message']='Sorry, that record does not seem to exisit...';
			}
		}else{
			$response['status']=500;
			$response['type']='message';
			$response['message']='Sorry, I need a "Log ID" to complete the request.';			
		}
		return $response;
	}	
	
	public function updateLog($post=false,$id=0){
		$response['ref']=$id;
		$response['type']='message';
			
		if($id>0 && is_array($post) && !empty($post)){
			$this->init();
			$rec=$this->DB->EventsLog()->where("EventLogID", $id);
			$rez=renderResultsORM($rec);
			if($rez) $rez=current($rez);
			//fix payment vars
			if(isset($post['PaymentDate'])){
				if($post['PaymentDate']==0){
					if($post['PaymentAmount']>0){
						$post['PaymentDate']=date('Y-m-d');
					}else{
						unset($post['PaymentDate']);
					}
				}
			}
			if(isset($post['PaymentAmount'])) $post['PaymentAmount']=toPennies($post['PaymentAmount']);

			//check charge changes and update cost
			$post=$this->recalc($post,$rez);
			
			//validate post
			foreach(array_keys($post) as $i){
				$chk=issetCheck($this->LOG_KEYS,$i);
				if($chk)$post[$chk]=$post[$i];
			}
			$update=$this->validateData($post);
			if(is_array($update)){
				$result=$rec->update($update);
				if($result){
					$ars['Forms']=$this->SLIM->Options->get('forms_received');
					$ars['Attending']=$this->SLIM->Options->get('yesno');
					$ars['Forms Sent']=$this->SLIM->Options->get('yesno');
					$ars['Room']=$this->SLIM->Options->get('room_types');
					$ars['Paid']=$this->SLIM->Options->get('yesno');	
					foreach($ars as $set=>$ops){
						if(array_key_exists($set,$update)){	
							$q=$update[$set];					
							$update[$set]=	issetCheck($ops,$q,'-');
						}
					}
					$response['message']='Okay, the record has been updated...';
					$response['status']=200;
					$response['update']=$update;
				}else{
					$response['message']='It does not seem like you have made any changes...';
					$response['status']=201;
				}				
			}else{
				$response['message']='Okay, but nothing was updated...';
				$response['status']=201;
			}
		}else{
			$response['message']='Sorry, the details received are invalid...';
		}
		return $response;
	}
	
	private function checkRoomChange($new,$old,$event_id){
		$out=false;
		if((int)$old!=(int)$new){
			$event=$this->SLIM->Options->get('events_info',$event_id);
			$prods=$this->SLIM->Products->get('product','group',$event['EventProduct']);
			foreach($prods as $i=>$v){
				if($v['ItemOrder']==$new){
					$out=array('room'=>$new,'cost'=>$v['ItemPrice'],'id'=>$i);
					break;
				}
			}
		}
		return $out;

	}
	private function checkProductChange($new,$old){
		$out=[];		
		if((int)$old!=(int)$new){
			$prods=$this->SLIM->Products->get('product','id',$new);
			$out=array('product'=>$new,'cost'=>$prods[$new]['ItemPrice']);
		}else if($old){
			$prods=$this->SLIM->Products->get('product','id',$old);
			$out=array('product'=>$old,'cost'=>$prods[$old]['ItemPrice']);
		}
		return $out;
	}
	private function checkAdditionalFee($new,$old){
		$out=[];
		if((int)$old!=(int)$new){
			$prods=$this->SLIM->Products->get('product','id',$new);
			$out=array('product'=>$new,'cost'=>$prods[$new]['ItemPrice']);
		}
		return $out;
	}
	
	private function checkSales($ref=0,$post=false){
		//this adds sales records if they are missing .
		//this usually happens when adding members to an event manually  (ie. not via a public form).
		if($ref){
			$pfields=array('ProductID','Shinsa','AdditionalFee','ProductID2');
			$data=$this->get('log',$ref);
			if($data && $data['status']==200){
					//are there any sales records?
					$chk=$this->SLIM->Sales->getInvoiceRecord('log',$ref);
					if(empty($chk)){//add sales records
						$inv=false;
						$def=$this->SLIM->Sales->getNewRecord();
						$order_ref=$this->SLIM->Sales->getNextRef();
						$date=date('Y-m-d');
						foreach($pfields as $p){
							$pid=(int)issetCheck($data['data'],$p);
							if($pid){
								$prod=$this->SLIM->Products->get('product','id',$pid);
								if($prod){
									$prod=current($prod);
									$tmp=$def;
									$tmp['MemberID']=$data['data']['MemberID'];
									$tmp['ItemID']=$pid;
									$tmp['ItemType']=$prod['ItemCategory'];
									$tmp['SoldPrice']=$prod['ItemPrice'];
									$tmp['EventRef']=$data['data']['EventID'];
									$tmp['EventLogRef']=$ref;
									$tmp['Currency']=$prod['ItemCurrency'];
									$tmp['Ref']=$order_ref;
									$tmp['SalesDate']=$date;
									$tmp['Ref']=$data['invoice']['Ref'];
									$tmp['Length']=($prod['ItemGroup']==0)?$prod['ItemQty']:0;
									$inv['items'][]=$tmp;
								}
							}
						}
						if($inv){
							$inv['action']='add_payment';
							$chk=$this->SLIM->Sales->saveRecord(0,$inv);
						}
					}else{//is this needed??
						//do we need to add anything?
						$add=$del=false;
						foreach($pfields as $p){
							$pid=(int)issetCheck($chk['elog'],$p);
							if($pid){
								$test=issetCheck($chk['products'],$pid);
								if(!$test){//add item?
									$add[]=$pid;
								}
							}
						}
						//do we need to remove anything?
						foreach($chk['log'] as $i=>$v){
							$pid=$v['ItemID'];
							$ok=false;
							foreach($pfields as $p){
								if((int)$chk['elog'][$p]==$pid){
									$ok=true;
									break;
								}
							}
							if(!$ok){
								$del[]=$i;
							}
						}
						if($add){
							$inv=false;
							$def=$this->SLIM->Sales->getNewRecord();
							foreach($add as $a){
								$prod=$this->SLIM->Products->get('product','id',$a);
								if($prod){
									$prod=current($prod);
									$tmp=$def;
									$tmp['MemberID']=$data['data']['MemberID'];
									$tmp['ItemID']=$a;
									$tmp['ItemType']=$prod['ItemCategory'];
									$tmp['SoldPrice']=$prod['ItemPrice'];
									$tmp['EventRef']=$data['data']['EventID'];
									$tmp['EventLogRef']=$ref;
									$tmp['Currency']=$prod['ItemCurrency'];
									$tmp['Ref']=$data['data']['invoice']['Ref'];
									$tmp['SalesDate']=$data['data']['invoice']['SalesDate'];
									$tmp['Length']=($prod['ItemGroup']==0)?$prod['ItemQty']:0;
									$inv['items'][]=$tmp;
								}
							}
							if($inv){
								$inv['action']='add_payment';
								$chk=$this->SLIM->Sales->saveRecord(0,$inv);
							}
						}
						if($del){
							foreach($del as $d){
								$r=$this->SLIM->db->Sales->where('ID',$d);
								if(count($r)){
									$r->delete();
								}
							}
						}
					}
			}
		}
	}
	private function recalc($post,$rec){
		$evCost=0;$updated=false;
		$oldCost=$rec['EventCost'];
		if(isset($post['Room'])){
			$old=(int)$rec['Room'];
			$old_room=(int)$rec['ProductID'];
			$prods=$this->checkRoomChange($post['Room'],$old,$rec['EventID']);
			if($prods){
				$post['Room']=$prods['room'];
				$evCost+=$prods['cost'];
				$post['ProductID']=$prods['id'];
				$updated=true;
			}
		}else if(isset($post['ProductID'])){
			$old=(int)$rec['ProductID'];
			$prods=$this->checkProductChange($post['ProductID'],$old);
			if($prods) $evCost+=$prods['cost'];
			$updated=true;
		}
		if(isset($post['ProductID2'])){
			$old=(int)$rec['ProductID2'];
			$prods=$this->checkProductChange($post['ProductID2'],$old);
			if($prods) $evCost+=$prods['cost'];
			$updated=true;
		}		
		if(isset($post['Shinsa'])){
			$old=(int)$rec['Shinsa'];
			$prods=$this->checkProductChange($post['Shinsa'],$old);
			if($prods) $evCost+=$prods['cost'];
			$updated=true;
		}
		if(isset($post['AdditionalFee'])){
			$old=(int)$rec['AdditionalFee'];
			$prods=$this->checkProductChange($post['AdditionalFee'],$old);
			if($prods) $evCost+=$prods['cost'];
			$updated=true;
		}
		if($updated){
			if($evCost!==$oldCost){
				$post['EventCost']=$evCost;
				$_post=$post;
				if(!isset($_post['MemberID'])) $_post['MemberID']=$rec['MemberID'];
				if(!isset($_post['EventID'])) $_post['EventID']=$rec['EventID'];
				$_post['PayDay']=$rec['PaymentDate'];
				if(isset($_post['Room'])){
					unset($_post['Room']);
					if(isset($_post['ProductID'])) $_post['ProductID_x']=$old_room;
				}
				$_post['action']='update_payment_elog';
				$chk=$this->SLIM->Sales->saveRecord($rec['EventLogID'],$_post);
				if(!$chk) return false;
			}
		}else{
			//skip
		}
		unset($post['ProductID2_x'],$post['ProductID_x'],$post['Shinsa_x'],$post['AdditionalFee_x']);
		return $post;		
	}
	
}
