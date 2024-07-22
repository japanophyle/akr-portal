<?php
class slim_db_events extends slim_db_common{
	var $TABLE='Events';
	private $ADMIN_URL;
	private $DOWNLOAD;

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
				$data=$this->getEvent($args);
				break;
			case 'events_address':
				$data=$this->getEventsByAddress($args);
				break;
			case 'events_type':
				$data=$this->getEventsByType($args);
				break;
			case 'event_info':
				$data=$this->getEventInfo($args);
				break;
			case 'list':case 'datatable':
				$data=$this->getEvents($args);
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
	public function getEvents($list=false) {
		$this->init();
        $recs=$this->DB->Events()->select("EventID AS id,EventID, EventName, EventType, EventDate,EventAddress,EventStatus");
        switch($list){
			case 'ukka':
				$recs->where('EventType',array(8,10));
				break;
			case 'lks':
				$recs->where('EventType',array(1,9));
				break;
			case 'ikyf':
				$recs->where('EventType',11);
				break;
			case 'active':
				$recs->where('EventStatus',1);
				break;
			default:
				if(is_numeric($list) && $list>0){
					$recs->where('EventType',$list);
				}
		}
		$recs->order('EventDate DESC','EventID DESC');
        $rez=renderResultsORM($recs);
        $recs=$this->DB->EventsLog()->select('EventID, COUNT(MemberID) as cnt')->group('EventID');
        $cnt=renderResultsORM($recs,'EventID');
        foreach($rez as $i=>$v){
			if($chk=issetCheck($cnt,$v['EventID'])){
				$rez[$i]['Rollcall']=$chk['cnt'];
			}else{
				$rez[$i]['Rollcall']=0;
			}
		}
        return $rez;
    }

    public function getEvent($id=0) {
		if((int)$id){
			$this->init();
			$recs=$this->DB->Events->where("EventID", $id);
			$dsp=renderResultsORM($recs,'EventID');
			if($dsp){
				$dsp=current($dsp);
			}else{
				$r['message']='Sorry, no record found for "'.$id.'"...';
				$r['status']=500;
				return $r;
			}
			//set options
			$dsp['EventOptions']=$this->setEventOptions($dsp['EventOptions']);
			$dsp['EventRooms']=$this->setEventRooms($dsp['EventRooms']);

			$dsp['EventNotes']=fixNL($dsp['EventNotes']);
			//get event log info
			$recs=$this->DB->EventsLog()->select("EventLogID,MemberID, Attending, Reason, Forms,FormsSent,Paid,Room,EventCost,PaymentAmount,Shinsa")->where("EventID", $dsp['EventID']);
			$dsp['EventsLog']=renderResultsORM($recs,'EventLogID');
			//get members info
			$dsp['Members']=false;
			$ins=$map=$new=array();
			if($dsp['EventsLog']){
				$dojos=$this->SLIM->Options->get('dojos_name');
				foreach($dsp['EventsLog'] as $i=>$v){
					$ins[$v['MemberID']]=$v['MemberID'];
					$map[$v['MemberID']]=array('event'=>$i,'sort'=>0);
				}
				$recs=$this->DB->Members()->select("MemberID,FirstName,LastName,CGradeName,DojoID,Sex,BirthDate,CurrentGrade,CGradedate,Country,NameInJapanese2,NameInJapanese,AnkfID,zasha")->where("MemberID", $ins)->order('CurrentGrade DESC');
				$dsp['Members']=renderResultsORM($recs,'MemberID');				
				//sort events into grade
				if($dsp['Members']){
					//add members dojo name
					foreach($dsp['Members'] as $i=>$v) $dsp['Members'][$i]['Dojo']=issetCheck($dojos,$v['DojoID'],'-');				
					$gsort=$this->SLIM->Options->get('grade_sort',array('ins'=>array_keys($dsp['Members'])));
					foreach($gsort as $i=>$v){
						$k=issetCheck($map[$i],'event');
						if($k){
							$new[$k]=$dsp['EventsLog'][$k];
							$new[$k]['gradeSortkey']=$v['gradeSortkey'];
						}
					}
					$dsp['EventsLog']=$new;
				}
				//get members recent Events
				$dsp['recent_member_events']=$this->getMemberRecentEvents($ins);
			}
			//get location info
			$recs=$this->DB->Locations()->select("LocationName,LocationCountry")->where("LocationID", $dsp['EventAddress']);
			$dsp['Locations']=renderResultsORM($recs,'LocationID');
			$r=array('status'=>200,'data'=>$dsp,'ref'=>$id);
		}else if($id==='new'){// not used??
			$this->init();
			$fields=[];//$this->getFields();
			$dsp['id']=0;//for js
			foreach($fields as $i=>$v){
				if($v['type']==='int'||$v['type']==='tinyint'){
					$val=0;
				}else if($v['type']==='datetime'){
					$val=date('Y-m-d 00:00:00');
				}else{
					$val='';
				}
				$dsp[$i]=$val;
			}
			$r=array('status'=>200,'data'=>$dsp,'ref'=>$id);
        }else{
			$r['message']='Sorry, no ID supplied...';
		}
        return $r;
    }  
	//event details - used when editing roll call
    public function getEventDetails($id=0,$log=false) {
		throw new Exception(__METHOD__ .' not used ??');
		/*
		if((int)$id){
			$this->init();
			$this->RESULTS=$this->DB->Events()->where("EventID", $id);
			$this->initItemObj();
			$dsp=$this->ITEM_OBJ->get('display');
			//set options
			$dsp['EventOptions']=$this->setEventOptions($dsp['EventOptions']);
			$dsp['EventRooms']=$this->setEventRooms($dsp['EventRooms']);
			$dsp['EventLimit']=$this->setEventLimit($dsp['EventLimit']);
			$dsp['EventShinsa']=$this->setShinsa($dsp['EventShinsa']);
			$dsp['EventNotes']=$this->fixNL($dsp['EventNotes']);
			//get event log info
			$dsp['EventsLog']=$log;//($tmp)?rekeyArray($tmp,'EventLogID'):false;
			//get location info
			$this->RESULTS=$this->DB->Locations()->select("LocationName,LocationCountry")->where("LocationID", $dsp['EventAddress']);
			$this->initItemObj();
			$dsp['Locations']=$this->ITEM_OBJ->get('display');
			$r=array('status'=>200,'data'=>$dsp,'ref'=>$id);
        }else{
			$r['message']='Sorry, no event ID supplied...';
		}
        return $this->getResponse($r);
		*/
	}	
    
    //by Type 
    public function getEventsByType($id=0){
		$this->init();
        $recs=$this->DB->Events->where("EventType",$id)->order('EventName ASC');
        return renderResultsORM($recs,'EventID');
    }
    //by Address  
    public function getEventsByAddress($id=0){
		$this->init();
        $recs=$this->DB->Events->where("EventAddress",$id)->order('EventName ASC');
        return renderResultsORM($recs,'EventID');
    }
    //Info
    public function getEventInfo($id=0){
		if($id>0){
			$this->init();
			$recs=$this->DB->Events->select("EventID, EventName, EventType, EventDate,EventAddress,EventNotes,EventStatus")->where("EventID",$id);
			$res=renderResultsORM($recs,'EventID');
			if($res){
				$rec=current($res);
				$rec['EventNotes']=fixNL($rec['EventNotes']);
				return $rec;
			}
		}
		return [];
    }
	public function add($post=false){
		$response=['message'=>'* event add error *','url'=>URL.'events/','status'=>500,'ref'=>0,'type'=>'message'];
		if(is_array($post) && !empty($post)){
			$this->init();
			$rec=$this->DB->Events();
			//fix options
			$post['EventOptions']=$this->setEventOptions($post,1);
			$post['EventRooms']=$this->setEventRooms($post,1);
			$post['EventLimit']=$this->setEventLimit($post,1);
			$post['Shinsa']=$this->setShinsa($post,1);
			//validate post
			$add=$this->validateData($post);
			if(is_array($add)){
				$result=$rec->insert($add);
				if($result){
					$response['message']='Okay, the record has been added...';
					$response['status']=200;
					$response['rowid']=$rec->insert_id();
					$response['url']=URL.'events/?list=all';
					$response['type']='refresh';
				}else{
					$response['message']='Sorry, there was a problem adding the record...';
					$response['status']=201;
				}				
			}else{
				$response['message']='Sorry, the details received are invalid...';
				$response['status']=201;
			}
		}else{
			$response['message']='Sorry, the details received are invalid...';
		}
		return $response;
	}

 	public function getMemberRecentEvents($member_id=false,$as_data=false){
		$data=$html=$parents=[];
		$getParent=function($i){
			$p=$this->DB->Events->select('EventID,EventName,EventDate,EventType')->where('EventID',$i);
			$p=renderResultsORM($p);
			return ($p)?current($p):[];
		};
		$ids=(is_numeric($member_id))?[(int)$member_id]:$member_id;
		foreach($ids as $id){
			$events=$this->DB->EventsLog->where('MemberID',$id)->order('EventID DESC')->limit(3);
			$events=renderResultsORM($events,'EventLogID');
			if($events){
				$tmp=[];				
				foreach(array_keys($events) as $i){
					$v=$events[$i];
					$parent=issetCheck($parents,$v['EventID']);
					if(!$parent){
						$parent=$getParent($v['EventID']);
						$parents[$v['EventID']]=$parent;
					}
					$tmp[]='['.date('d/m/Y',strtotime($parent['EventDate'])).'] '.$parent['EventName'];
					$v+=$parent;
					$events[$i]=$v;
				}
				$html[$id]='<div style=" overflow:auto; min-width:15rem; max-height:5rem; font-size:0.7rem">'.implode(', <br/>',$tmp).'</div>';
				$data[$id]=$events;
			}else{
				$html[$id]=' - ';
				$data[$id]=[];
			}
		}
		return ($as_data)?$data:$html;
	}	
   //Add Members: get data for searching functions
    public function getAddMembers($id=0){
		$this->init();
		$recs=$this->DB->Members->select("MemberID,FirstName,LastName,CGradeName,Dojo,Sex,BirthDate,CurrentGrade,zasha");
		$recs->where('Disable',0)->order('CurrentGrade DESC');			
		if($id>0){
			$event=$this->getEvent($id);
			$members=array_keys($event['data']['Members']);
			if($members){
				$recs->where("MemberID NOT", $members)->and('Dead',0);
			}else{
				$recs->where('Dead',0);
			}
		}else{
			$recs->where('Dead',0);	
		}
		return renderResultsORM($recs,'MemberID');			
	}
	
	public function getEventReport($id=0,$download=false){
		if($id>0){
			$this->DOWNLOAD=$download;
			$_res=$this->getEvent($id);
			$res=$_res['data'];
			if($res){
				$EVI=new EventItem($this->OPTIONS,$res);
				$chk=$EVI->Render('report',$download);
				$row=$chk['data']['rows'];
				if($chk['status']!==200) $response['message']=$chk['message'];
				$dates=$EVI->Render('dates');
				if($download){
					$response['status']=200;
					$response['type']='download';
					$response['data']=array(
							'info'=>array(
							'Event Name'=>$res['EventName'],
							'Location'=>implode(', ',$res['Locations']),
							'Start Date'=>$dates['data']['start'],
							'End Date'=>$dates['data']['end'],
							'Members'=>count($res['EventsLog'])
						),
						'rollcall'=>$row
					);
				}else{
					$fill=array(
						'thead'=>$chk['data']['head'],
						'eventName'=>$res['EventName'],
						'eventLocation'=>implode(', ',$res['Locations']),
						'eventDate'=>$dates['data']['start'],
						'info'=>'Event ID: '.$id.'<br/>Start: '.$dates['data']['start'].'<br/>End: '.$dates['data']['end'].'<br/>Rollcall: '.count($res['EventsLog']),
						'notes'=>$res['EventNotes'],
						'rollcall'=>implode('',$row),
						'class_title'=>'text-left bg-black text-white',
						'class_header'=>'text-left bg-gray',	
					);
					$tpl=file_get_contents(APP.'templates/ng_report_events.html');
					foreach($fill as $i=>$v) $tpl=str_replace('{'.$i.'}',$v,$tpl);
					$controls='<div class="fcontrols text-right">
					<button type="button" class="button secondary" data-close ><i class="fi-x-circle"></i> Close</button>
					<button class="button button-purple gotoME" data-ref="'.URL.'api/events/download/'.$id.'"><i class="fi-download"></i> Download</button>
					<button class="button button-dark-blue loadME" data-size="large" data-ref="'.URL.'api/events/edit/'.$id.'"><i class="fi-pencil"></i> Edit</button>
					</div>';
					$fill=array(
						'title'=>'Report View: '.$res['EventName'],
						'content'=>'<div class="container" style="overflow-y:auto;max-height:30rem">'.$tpl.'</div>'.$controls
					);				
					$response['status']=200;
					$response['type']='swap';
					$response['content']=renderCard($fill);
					$response['target']='#main-holder';
				}
			}else{
				$response['status']=500;
				$response['type']='message';
				$response['message']='Sorry, that record does not seem to exisit...';
			}
		}else{
			$response['status']=500;
			$response['type']='message';
			$response['message']='Sorry, I need an "Event ID" to complete the request.';			
		}
		return $response;
	}
	public function update($post=false,$id=0){		
		$response=['message'=>'* event add error *','url'=>URL.'events/','status'=>500,'ref'=>$id,'type'=>'message'];
		if($id>0 && is_array($post) && !empty($post)){
			$this->init();
			$rec=$this->DB->Events->where("EventID", $id);
			//fix options
			$post['EventOptions']=$this->setEventOptions($post,1);
			$post['EventRooms']=$this->setEventRooms($post,1);
			$post['EventLimit']=$this->setEventLimit($post,1);
			$post['EventShinsa']=$this->setShinsa($post,1);
			$post['EventProductLimit']=$this->setProductLimit($post,1);
			$post['EventProductLimit2']=$this->setProductLimit2($post,1);
			//validate post
			$update=$this->validateData($post);
			if(is_array($update)){
				//fix empty time stamps
				if(isset($update['EventDuration']) ){
					if(!$update['EventDuration'] || $update['EventDuration']==='') $update['EventDuration']=null;
				}
				if(isset($update['EventRegDate']) ){
					if(!$update['EventRegDate'] || $update['EventRegDate']==='') $update['EventRegDate']=null;
				}
				$result=$rec->update($update);
				if($result){
					$info=$this->getEventInfo($id);
					$evtypes=$this->SLIM->Options->get('events');
					$locs=$this->SLIM->Options->get('locations');
					$states=$this->SLIM->Options->get('active');
					$update['EventDate']==validDate($update['EventDate']);
					$update['EventType']=$evtypes[$update['EventType']]['OptionName'];
					$update['EventStatus']=$states[$update['EventStatus']];

					$response['message']='Okay, the record has been updated...';
					$response['status']=200;
					$response['update']=$update;
					$response['type']='update';
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
	
	private function setEventOptions($data=false,$tosql=false){
		$fixOn=function($s=false){
			if(is_array($s)){//set "on" to 1 if from post
				foreach($s as $set=>$recs){
					foreach($recs as $i=>$v){
						if($v==="on") $s[$set][$i]=1;
					}
				}
			}
			return $s;			
		};
		if(is_array($data)){
			$o=issetCheck($data,'EventOptions');
			if($o){//set "on" to 1 if from post
				$o=$fixOn($o);
			}else{
				$o=$this->SLIM->Options->get('event_options_default');		
			}
		}else{
			$basic=$this->SLIM->Options->get('event_options_basic');
			$def=$this->SLIM->Options->get('event_options_default');
			$o=eventOptionsMap($data,$basic,$def);
		}
		if($tosql) $o=serialize($o);
		
		return $o;		
	}
	private function setEventRooms($data=false,$tosql=false){
		$rooms=array('single'=>0,'double'=>0);
		if(is_array($data)){
			$o=issetCheck($data,'EventRooms');
			if(is_array($o)){
				$rooms['single']=(int)issetCheck($o,'single');
				$rooms['double']=(int)issetCheck($o,'double');
			}
		}else if(is_string($data)){
			$chk=json_decode($data,true);
			if(is_array($chk) && !empty($chk)) $rooms=$chk;
		}
		if($tosql) $rooms=json_encode($rooms);
		return $rooms;
	}
	private function setEventLimit($data=false,$tosql=false){
		$limit=array();
		if(is_array($data)){
			$o=issetCheck($data,'EventLimit');
			if(is_array($o)){
				$limit=$o;
			}
		}else if(is_string($data)){
			$chk=json_decode($data,true);
			if(is_array($chk) && !empty($chk)) $limit=$chk;
		}
		if($tosql) $limit=json_encode($limit);
		return $limit;
	}
	private function setShinsa($data=false,$tosql=false){
		$shinsa=array();
		if(is_array($data)){
			$o=issetCheck($data,'EventShinsa');
			if(is_array($o)){
				$shinsa=$o;
			}
		}else if(is_string($data)){
			$chk=json_decode($data,true);
			if(is_array($chk) && !empty($chk)) $shinsa=$chk;
		}
		if($tosql) $shinsa=json_encode($shinsa);
		return $shinsa;
	}
	private function setProductLimit($data=false,$tosql=false){
		$limit=array();
		if(is_array($data)){
			$o=issetCheck($data,'EventProductLimit');
			if(is_array($o)){
				$limit=$o;
			}
		}else if(is_string($data)){
			$chk=json_decode($data,true);
			if(is_array($chk) && !empty($chk)) $limit=$chk;
		}
		if($tosql) $limit=json_encode($limit);
		return $limit;
	}
	private function setProductLimit2($data=false,$tosql=false){
		$limit=array();
		if(is_array($data)){
			$o=issetCheck($data,'EventProductLimit2');
			if(is_array($o)){
				$limit=$o;
			}
		}else if(is_string($data)){
			$chk=json_decode($data,true);
			if(is_array($chk) && !empty($chk)) $limit=$chk;
		}
		if($tosql) $limit=json_encode($limit);
		return $limit;
	}
	public function deleteEvent($id=0){
		if($id>0){
			$this->init();
			$recs=$this->DB->Events()->select("EventID AS id,EventName")->where('EventID',$id);
			$recs=renderResultsORM($recs,'id');
			if($recs){
				//remove event log rollcall
				$log=$this->DB->EventsLog()->where("EventID", $id);
				if(count($log)>0) $log->delete();
				//remove event log rollcall
				$delete=$this->DB->Events("EventID",$id)->delete();
				if($delete){
					$response['status']=200;
					$response['type']='redirect';
					$response['url']=URL.'events/list/all';
					$response['message']='Okay, the event has been deleted.';
				}else{
					$response['status']=201;
					$response['type']='message';
					$response['message']='Sorry, there was a problem deleting the event...';
				}				
			}else{
				$response['status']=500;
				$response['type']='message';
				$response['message']='Sorry, that record does not seem to exisit...';
			}
		}else{
			$response['status']=500;
			$response['type']='message';
			$response['message']='Sorry, I need a "Event ID" to complete the request.';			
		}
		return $response;
	}
}
