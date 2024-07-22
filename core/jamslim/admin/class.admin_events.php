<?php

class admin_events{
	private $SLIM;
	private $CALENDAR;
	private $PARTS;
	private $PART;
	private $LIB;
	
	private $PERMBACK;	
	private $PERMLINK;
	private $OUTPUT=array('status'=>500,'message'=>false,'data'=>false,'content'=>false,'title'=>'Events Manager');
	private $FORMS;//list of available forms
	private $CDATE=false;//current event date from $get
	private $PYEAR=false;//planner date from $get
	private $REF;
	private $OPTIONS=[];
	
	public $AJAX;
	public $REQUEST;
	public $USER;
	public $METHOD;
	public $SECTION;
	public $ACTION;
	public $PLUG;
	public $ROUTE;
	public $ADMIN;
	public $LEADER;

	
	function __construct($slim){
		if(!$slim){
		  throw new Exception(__METHOD__.': no slim object!!');
		}
		$this->SLIM=$slim;
		$this->LIB=$slim->EventsLib;
		$this->PERMBACK=URL.'admin/';
		$this->PERMLINK=$this->PERMBACK.'events/';
		$this->CALENDAR=$this->SLIM->Calendar;
		$this->CALENDAR->EVENTS_STATUS=0;
		$this->OPTIONS['EventStatus']=$slim->Options->get('active');
		$this->OPTIONS['EventType']=$slim->Options->get('events');
		$this->OPTIONS['EventAddress']=$slim->Options->get('locations');
		$this->OPTIONS['currency']=$slim->Options->get('currency');
	}
	
	function Process(){
		$this->CDATE=issetCheck($this->REQUEST,'cdate');
		$this->PYEAR=issetCheck($this->REQUEST,'pyear',date('Y'));
		$this->REF=issetCheck($this->ROUTE,3);
		switch($this->ACTION){
			case 'fixup':
				$this->renderFixup();
				break;
			case 'update_calendar_options':
			case 'calendar_options':
				$this->renderCalendarOptions();
				break;
			case 'update_calendar_options_admin':
			case 'calendar_options_admin':
				$this->renderCalendarOptions(true);
				break;
			case 'event_day':
				$this->renderDayEvents();
				break;
			case 'event_new':
				$this->renderNewEvent();
				break;
			case 'submitted_form':
				$this->renderSubmittedForms($this->REF);
				break;
			case 'preview_form':
				$this->renderPreviewForm($this->REF);
				break;
			case 'mailer':
				$this->renderMailer($this->REF);
				break;
			case 'events_menu':
				$this->renderEventsMenu();
				break;
			case 'edit':
				$this->renderEditEvent($this->REF);
				break;
			case 'view':
				$this->renderViewEvent($this->REF);
				break;
			case 'delete':case 'delete_now':
				$this->renderDeleteEvent($this->REF);
				break;
			case 'add': case 'add_event':
				$this->renderAddEvent();
				break;
			case 'update': case 'event_update':
				$this->renderUpdateEvent();
				break;
			case 'edit_contents':
				$part=issetcheck($this->ROUTE,4);
				$this->renderEditEventContent($this->REF,$part);
				break;				
			case 'rollcall':
			case 'edit_rollcall':
			case 'update_rollcall':
			case 'delete_rollcall':
			case 'delete_rollcall_now':
				$this->renderEventRollcall($this->REF);
				break;
			default:
				$this->dashboard($this->ACTION);
		}
		return $this->renderOutput();
	}
	private function renderOutput(){
		$keys=['title','metrics','content','icon','menu'];
		if(is_array($this->OUTPUT)){
			$out=$this->OUTPUT;
			foreach($keys as $k){
				if(!isset($this->OUTPUT[$k])){
					switch($k){
						case 'icon':
							$v='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
							break;
						case 'menu':
							$v=['right'=>$this->renderContextMenu()];
							break;
						default:
							$v='';
					}
					$out[$k]=$v;
				}
			}
		}else if(!$this->OUTPUT||$this->OUTPUT===''){
			$out=msgHandler('Sorry, no output was generated...',false,false);
		}else{
			$out=$this->OUTPUT;
		}
		if($this->AJAX){
			if(is_array($out)){
				jsonResponse($out);
			}else{
				echo $out;
			}
			die;
		}
		return $out;
	}
	private function renderContextMenu(){
		$but['back']='<button class="button small button-dark-purple backME" title="back to list" type="button"><i class="fi-arrow-left"></i> Back</button>';
		$but['new']='<button class="button small button-dark-blue loadME" title="add a new event" data-ref="'.$this->PERMLINK.'event_new" type="button"><i class="fi-plus"></i> New</button>';
		$but['save']='<button class="button small button-olive submitME" title="save changes" data-ref="ajaxform" type="button"><i class="fi-check"></i> Update</button>';
		$but['menu']='<button class="button small button-navy loadME" title="events menu" data-size="small" data-ref="'.$this->PERMLINK.'events_menu/" type="button"><i class="fi-list"></i> Events Menu</button>';
		$but['edit']='<button class="button small button-dark-blue loadME" title="edit payment record" data-ref="'.$this->PERMLINK.'edit_payment/'.$this->REF.'/list" type="button"><i class="fi-pencil"></i> Edit</button>';
		$but['event']='<button class="button small button-dark-blue loadME" title="edit event" data-size="large" data-ref="'.$this->PERMLINK.'edit/'.$this->REF.'" type="button"><i class="fi-calendar"></i> Event #'.$this->REF.'</button>';
		$but['download']='<button class="button small button-purple gotoME" title="download" data-ref="'.$this->PERMLINK.'rollcall/'.$this->REF.'/download" type="button"><i class="fi-download"></i> Download</button>';
		$but['email']=($this->ACTION==='rollcall')?'<button class="button small button-navy gotoME" title="email this list" data-ref="'.$this->PERMBACK.'mailer/add/event/'.$this->REF.'" type="button"><i class="fi-mail"></i> Email</button>':'';
		$but['settings']='<button class="button button-purple small loadME" data-ref="'.$this->PERMLINK.'calendar_options_admin"><i class="fi-widget"></i> Calendar Settings</button>';
		$b=[];$out='';
		switch($this->ACTION){
			case 'edit':
				$b=['back','new','save'];
				break;
			case 'group_summary':
				$b[]='new';
				break;
			case 'edit_record'://viewing invoice
				$b=['back','download','edit','new'];
				break;
			case 'rollcall':
				$b=['back','event','email','download'];
				break;
			case 'events_planner':case 'events_list':
				$b=['menu','settings','new'];
				break;
			default:
				$b[]='back';
				$b[]='settings';
				$b[]='menu';
				$b[]='new';
		}
		if($b){
			foreach($b as $i){
				$out.='<li>'.$but[$i].'</li>';
			}
		}
		return $out;
	}
	private function renderEventsMenu(){
		if($this->SLIM->user['access']>=25){
			$_data['events_planner']=array('color'=>'olive','caption'=>'Events<br/>Planner','content'=>'view events planner','href'=>$this->PERMLINK.'events_planner','icon'=>'calendar');
			$_data['events_list']=array('color'=>'olive','caption'=>'Events<br/>List','content'=>'view events as list','href'=>$this->PERMLINK.'events_list/all','icon'=>'results');
			$_data['submitted']=array('color'=>'olive','caption'=>'Submitted<br/>Forms','content'=>'view forms submitted by members','href'=>$this->PERMLINK.'submitted','icon'=>'page-multiple');
			$_data['products']=array('color'=>'olive','caption'=>'Manage<br/>Products','content'=>'manage form products','href'=>$this->PERMBACK.'product','icon'=>'shopping-cart');
			$_data['sales']=array('color'=>'olive','caption'=>'Sales by<br/>Product','content'=>'manage products sold','href'=>$this->PERMBACK.'sales','icon'=>'dollar-bill');
		}
		$_data['by_event']=array('color'=>'olive','caption'=>'Sales by<br/>Event','content'=>'manage products sold by grouped by event','href'=>$this->PERMBACK.'sales/by_event','icon'=>'trophy');
		$dashlinks='';
		$title='Events Menu';
		foreach($_data as $i=>$v){
			$color='navy';
			$but['color']=$color;
			$but['icon']=issetCheck($v,'icon','widget');
			$but['href']=issetCheck($v,'href','#nogo');
			$but['caption']=issetCheck($v,'caption','');
			$but['title']=issetCheck($v,'content','');
			$dashlinks.=$this->SLIM->zurb->adminButton($but);
		}
		if($this->AJAX){
			echo renderCard_active($title,$dashlinks,$this->SLIM->closer);
			die;
		}
		return $this->renderAdminCard($title,$dashlinks);
	}	
	private function dashboard($what=false){
		if(!$what) $what='event_planner';
		$content=$title=false;
		$pdate=$this->PYEAR.date('-m-d');
		$whr=$what;
		//set title
		if($this->ROUTE[1]==='sales'){
			$whr=$this->REF;
			if(!$whr){
				$whr='sales';
			}else if($whr==='event'){
				$whr='by_event';
			}else if($whr==='product'||$whr==='group'){
				$whr='sales';
			}else if($whr==='member'){
				$whr='by_member';
			}else if($whr==='dojo'){
				$whr='by_dojo';
			}
			$title='Product Sales: <span class="subheader">'.ucME($whr).'</span>';
		}else{
			$title='Events Manager: <span class="subheader">'.ucME($what).'</span>';
		}
		//set content
		switch($what){
			case 'events_list':
				$t=$this->renderTable($this->REF);
				$content=$t['table'];
				break;
			case 'submitted':
				$content=$this->renderSubmittedForms();
				break;
			case 'products':
				$content=$this->renderProducts();
				break;
			case 'sales':
				$content=$this->renderSales();
				break;
			case 'report':
				$content=$this->renderReports();
				break;
			default:
				$content=$this->CALENDAR->render('year_plan',$pdate);
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;		
	}	
	private function renderAdminCard($title,$content,$buttons=false,$ibar=false,$before=false,$after=false){
		return $before.renderCard_active($title,$content,$buttons,false,false,false,$ibar).$after;
	}
	private function renderDayEvents(){
		$events=false;
		$add='<button class="button button-olive expanded loadME" data-ref="'.$this->PERMLINK.'event_new/?cdate='.$this->CDATE.'"><i class="fi-plus"></i> Add an Event</button>';
		if($this->CDATE){
			$strdate=validDate($this->CDATE,'D jS F Y');
			$cdate=$this->CDATE.' 00:00:00';
			$db=$this->SLIM->db->Events();
			$recs=$db->where('EventDate',$cdate)->or('EventDuration',$cdate);
			if(count($recs)<1){// search for events
				$db=$this->SLIM->db->Events();
				$recs=$db->where('EventDate <= ?',$cdate)->and('EventDuration >= ?',$cdate);
			}
			if(count($recs)>0){
				$recs=renderResultsORM($recs,'EventID');
				foreach($recs as $i=>$v){
					$start=validDate($v['EventDate'],'D jS F Y');
					$end=validDate($v['EventDuration'],'D jS F Y');
					if($end && $end!=$start) $start.=' - '.$end;
					$edit='<button class="button small button-dark-blue loadME small" data-ref="'.$this->PERMLINK.'edit/'.$i.'"><i class="fi-pencil"></i> Edit Details</button>';
					$rollcall='<button class="button small button-navy gotoME small" data-ref="'.$this->PERMLINK.'rollcall/'.$i.'"><i class="fi-torso"></i> Manage Rollcall</button>';
					$events.='<tr><td class="small-9">'.$v['EventName'].'<br/><span class="dates text-olive">'.$start.'</span></td><td><div class="button-group">'.$edit.$rollcall.'</div></td></tr>';
				}
			}
		}
		if(!$events){
			$events=msgHandler(lang('Sorry, no events found...'),'alert',false);
		}else{
			$events='<table class="events_list">'.$events.'</table>';
		}
		if($this->AJAX){
			echo renderCard_active('Events: '.$strdate,"<div class=\"callout secondary\">$events $add</div>",$this->SLIM->closer);
			die;
		}
		return $events.$add;		
	}
	private function renderEditEvent($ref){
		$data=$this->LIB->get('event',$ref);
		$title='Edit Event #'.$ref;
		if(isset($data['status']) && $data['status']==200){
			$data=$data['data'];
			$title.=': <small class="text-dark-blue">'.$data['EventName'].'</small>';
			$item= new event_item($this->SLIM,$data);
			$content=$item->renderEditEvent();			
		}else{
			$content=msgHandler('Sorry, no records found for ref:'.$ref,false,false);
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;		
		if($this->AJAX){
			echo '<style>.reveal .card-section.main {max-height: 100%;overflow-Y: auto;}</style>';
			echo renderCard_active($this->OUTPUT['title'],$content,$this->SLIM->closer);
			echo '<script>$(".reveal .card-section.main").foundation();</script>';
			die;
		}
	}
	private function renderViewEvent($ref){
		$data=$this->LIB->get('event',$ref);
		$title='View Event #'.$ref;
		if($data['status']==200){
			$data=$data['data'];
			$title.=': <small class="text-dark-blue">'.$data['EventName'].'</small>';
			$item= new event_item($this->SLIM,$data);
			$content=$item->renderViewEvent();			
		}else{
			$content=msgHandler('Sorry, no records found for ref:'.$data['ref'],false,false);
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;		
		if($this->AJAX){
			echo '<style>.reveal .card-section.main {max-height: 100%;overflow-Y: auto;}</style>';
			echo renderCard_active($this->OUTPUT['title'],$content,$this->SLIM->closer);
			die;
		}
	}
	private function renderUpdateEvent(){
		if(!$this->REF && $this->METHOD==='POST') $this->REF=issetCheck($this->REQUEST,'id');
		$rsp=$this->LIB->update($this->REQUEST,$this->REF);
		if($this->AJAX){
			echo jsonResponse($rsp);
			die;
		}
		setSystemResponse($rsp['url'],$rsp['message']);
	}
	private function renderAddEvent(){
		$rsp=$this->LIB->add($this->REQUEST);
		if($this->AJAX){
			echo jsonResponse($rsp);
			die;
		}
		setSystemResponse($rsp['url'],$rsp['message']);
	}
	private function renderDeleteEvent($ref){
		$data=$this->LIB->get('event',$ref);
		$title='Delete Event #'.$ref;
		if(!$data){
			$content=msgHandler('Sorry, no records found for ref:'.$ref,false,false);
		}else if($this->ACTION==='delete_now'){
			$rsp=$this->LIB->deleteEvent($ref);
			if($this->AJAX){
				echo jsonResponse($rsp);
				die;
			}
			setSystemResponse($this->PERMLINK,$rsp['message']);
			die($rsp['message']);
		}else{
			$date=explode(' ',$data['data']['EventDate']);
			$name=$data['data']['EventName'].'<br/>'.$date[0];
			$content='<div class="callout primary text-center"><p class="h3 text-dark-blue">Do you want to delete this event?</p><p><strong>'.$name.'</strong></p></div>';
			$content.='<div class="button-group expanded"><button class="button secondary" data-close><i class="fi-x-circle"></i> No, maybe later.</button><button class="button small button-red gotoME small" data-ref="'.$this->PERMLINK.'delete_now/'.$ref.'"><i class="fi-check"></i> Yes, do it now.</button></div>';
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;		
		if($this->AJAX){
			echo '<style>.reveal .card-section.main {max-height: 100%;overflow-Y: auto;}</style>';
			echo renderCard_active($this->OUTPUT['title'],$content,$this->SLIM->closer);
			die;
		}
	}
	private function renderEditEventContent($ref,$part){
		$data=$this->LIB->get('event',$ref);
		if($data['status']==200){
			$data=$data['data'];
			$item= new event_item($this->SLIM,$data);
			$rez=$item->renderEventContent_edit($part);
			extract($rez);
		}else{
			$title='Edit Event #'.$ref.' Content ('.$part.')';
			$content=msgHandler('Sorry, no records found for ref:'.$data['ref'],false,false);
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;		
		if($this->AJAX){
			echo '<style>.reveal .card-section.main {max-height: 100%;overflow-Y: auto;}</style>';
			echo renderCard_active($this->OUTPUT['title'],$content,$this->SLIM->closer);
			echo '<script>$(".reveal .card-section.main").foundation();</script>';
			die;
		}
	}
	private function renderEventRollcall($ref){
		switch($this->ACTION){
			case 'delete_rollcall':case 'delete_rollcall_now':
				$parts=$this->renderEventRollcall_delete($ref);
				extract($parts);
				break;
			case 'update_rollcall':
				$db= new slim_db_eventslog($this->SLIM);
				$rsp=$db->updateLog($this->REQUEST,$this->REQUEST['id']);
				if($this->AJAX){
					$rsp['close']=1;
					if($rsp['status']==200) $rsp['message_type']='success';
					echo jsonResponse($rsp);
				}else{
					setSystemResponse($rsp['url'],$rsp['message']);
				}
				die;
				break;
			case 'edit_rollcall':
				$db= new slim_db_eventslog($this->SLIM);
				$data=$db->get('log',$ref);
				$title='Rollcall #'.$ref;
				if($data['status']==200){
					$data=$data['data'];
					$title.=' - '.$data['member']['FirstName'].' '.$data['member']['LastName'].' / '.$data['member']['Dojo'];
				}
				$tabs=['Details'=>'','Notes'=>''];
				$parts=['Forms','Attending','ProductID','Shinsa','Reason'];
				foreach($parts as $p){
					$val=$data[$p];
					if(in_array($p,['Forms','Attending'])){
						$opts=$this->SLIM->Options->get('yesno');
						$o='';
						foreach($opts as $i=>$v){
							$sel=($i==$val)?'selected':'';
							$o.='<option value="'.$i.'" '.$sel.'>'.$v.'</option>';
						}
						$tabs['Details'].='<label>'.ucME($p).'</label><select name="'.$p.'">'.$o.'</select>';
					}else if($p==='Reason'){
						$tabs['Notes']='<textarea rows="15" name="'.$p.'">'.fixNL($val).'</textarea>';
					}else if($p==='ProductID'){
						$opts=$data['products'];
						$o='<option>None Selected</option>';
						foreach($opts as $i=>$v){
							$sel=($i==$val)?'selected':'';
							$currency=issetCheck($this->OPTIONS['currency'],$v['ItemCurrency']);
							$o.='<option value="'.$i.'" '.$sel.'>'.$v['ItemTitle'].' / '.$currency['label'].' '.toPounds($v['ItemPrice']).'</option>';
						}
						$tabs['Details'].='<label>Item Sold</label><select name="'.$p.'">'.$o.'</select>';
					}else if($p==='Shinsa'){
						$opts=$data['products'];
						$o='<option>None Selected</option>';
						foreach($opts as $i=>$v){
							if($v['ItemCategory']==4 && strpos($v['ItemTitle'],'Shinsa')!==false){
								$sel=($i==$val)?'selected':'';
								$currency=issetCheck($this->OPTIONS['currency'],$v['ItemCurrency']);
								$o.='<option value="'.$i.'" '.$sel.'>'.$v['ItemTitle'].' / '.$currency['label'].' '.toPounds($v['ItemPrice']).'</option>';
							}
						}
						$tabs['Details'].='<label>Shinsa Fee</label><select name="'.$p.'">'.$o.'</select>';
					}
				}
				$tabs=$this->SLIM->zurb->tabs(['id'=>'edit_rollcall','tabs'=>$tabs]);
				$tabs.='<input type="hidden" name="action" value="update_rollcall"/>';
				$tabs.='<input type="hidden" name="id" value="'.$ref.'"/>';
				$controls='<button class="button button-red loadME" data-ref="'.URL.'admin/events/delete_rollcall/'.$ref.'"><i class="fi-x-circle"></i> Delete</button>';
				$controls.='<button class="button button-olive" type="submit"><i class="fi-check"></i> Update</button>';
				$content='<form class="ajaxForm" method="post" action="'.URL.'admin/events">'.$tabs.'<div class="button-group small expanded">'.$controls.'</div></form>';
				break;
			default:
				$r4=issetcheck($this->ROUTE,4);
				if($r4==='download'){
					$db= new slim_db_eventslog($this->SLIM);
					$db->get('download',$ref);
					die('ok');
				}
				$data=$this->LIB->get('event',$ref);
				$title='Event #'.$ref.' Rollcall';
				if(isset($data['status']) && $data['status']==200){
					$data=$data['data'];
					$title.=': <small class="text-dark-blue">'.$data['EventName'].'</small>';
					$item= new event_item($this->SLIM,$data);
					$content=$item->Render('rollcall','admin');
				}else{
					$content=msgHandler('Sorry, no records found for ref:'.$ref,false,false);
				}
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;		
		if($this->AJAX){
			echo '<style>.reveal .card-section.main {max-height: 100%;overflow-Y: auto;}</style>';
			echo renderCard_active($this->OUTPUT['title'],$content,$this->SLIM->closer);
			echo '<script>$(".reveal .card-section.main").foundation();</script>';
			die;
		}
	}
	private function renderEventRollcall_delete($ref){
		if($this->ACTION==='delete_rollcall_now'){
			$rec=$this->SLIM->db->EventsLog->where('EventLogID',$ref);
			$msg='Sorry, I could not delete the record...';
			$rc_ref=0;
			if(count($rec)==1){
				$rc_ref=$rec[0]['EventID'];
				$chk=$rec->delete();
				if($chk){
					$msg='Okay, but only the rollcall record was deleted.';
					$rec=$this->SLIM->db->FormsLog->where('EventLogID',$ref);
					if(count($rec)==1){
						$chk=$rec->delete();
						if($chk){
							$msg='Okay, the rollcall and form records have been deleted.';
						}
					}
				}
			}
			$data=issetCheck($_SESSION['userArray'],'reg_form',[]);
			if($data) unset($_SESSION['userArray']['reg_form']);
			setSystemResponse($this->PERMLINK.'rollcall/'.$rc_ref,$msg);
		}else{
			$content='<div class="callout primary text-center"><p class="h3 text-dark-blue">Do you want to delete rollcall record #'.$ref.'?</p><p class="text-maroon"><em>Note that this will delete the rollcall record and submitted form.</em></p></div>';
			$content.='<div class="button-group expanded"><button class="button secondary" data-close><i class="fi-x-circle"></i> No, maybe later.</button><button class="button small button-red gotoME small" data-ref="'.$this->PERMLINK.'delete_rollcall_now/'.$ref.'"><i class="fi-check"></i> Yes, do it now.</button></div>';
		}
		$output['title']='Delete Rollcall #'.$ref;
		$output['content']=$content;		
		return $output;			
	}
	private function renderNewEvent(){
		$date=($this->CDATE)?$this->CDATE:date('Y-m-d');
		$strdate=validDate($date,'D jS F Y');
		$parts=array(
			'EventName'=>'',
			'EventType'=>$this->SLIM->options->get('events'),
			'EventDate'=>$date,
			'EventDuration'=>$date,
			'EventLocation'=>'',
			'EventStatus'=>0,
			'form_url'=>$this->PERMLINK.'add'
		);
		$tmp=false;
		foreach($parts['EventType'] as $i=>$v) $tmp.='<option value="'.$i.'">'.$v['OptionName'].'</option>';
		$parts['EventType']=$tmp;
		$tmp=false;
		
		$tpl='<form class="ajaxForm" id="formX" method="post" action="{form_url}">
<label>Event Name <input type="text" name="EventName" placeholder="Event Name" value=""></label>
<label>Event Type <select name="EventType" placeholder="Event Type"><option value="null">- not set -</option>{EventType}</select></label>
<label>Start Date <input type="date" name="EventDate" id="date_EventDate" placeholder="Event Date" value="{EventDate}"></label>
<label>End Date <input type="date" name="EventDuration" id="date_EventDuration" class="" placeholder="End Date" value="{EventDuration}"></label>
<label>Location <input type="text" name="EventLocation" placeholder="Event Location" value=""></label>
<input type="hidden" name="action" value="add_event"/><input type="hidden" name="id" value="new"/><input type="hidden" name="EventStatus" value="{EventStatus}"/>
<button type="submit" class="button button-olive expanded"><i class="fi-check"></i> Add Event</button>
</form>';

		$content=replaceME($parts,$tpl);
		if($this->AJAX){
			echo renderCard_active('New Event: '.$strdate,$content,$this->SLIM->closer,false,false,false,'<span class="text-white">&nbsp;Set the basic event here, other options can be added later.</span>');
			die;			
		}
		return $content;
	}
	private function renderCalendarOptions($admin=false){
		if($this->METHOD==='POST') return $this->saveCalendarOptions($admin);
		$o=array('Calendar Colours'=>false,'Calendar Months'=>false,'Calendar Power'=>false,'Calendar ICS'=>false);
		$data=$this->SLIM->options->get('application');
		foreach($data as $i=>$v){
			$opt='';
			if(array_key_exists($v['OptionName'],$o)){
				switch($v['OptionName']){
					case 'Calendar Colours':case 'Calendar Power':case 'Calendar ICS':
						$lbl=($v['OptionName']==='Calendar Colours')?'Calendar Event Colours':$v['OptionName'];
						$sel=((int)$v['OptionValue']>0)?'selected="selected"':'';
						$opt='<option value="0">No</option><option value="1" '.$sel.'>Yes</option>';
						$o[$v['id']]='<label>'.$lbl.'<select name="opt['.$v['id'].']">'.$opt.'</select></label>';
						break;
					default:
						$vars=array(0=>'no calendar',2=>'2 Months',3=>'3 Months',4=>'4 Months',6=>'6 months',8=>'8 Months',12=>'12 Months');
						$val=(int)$v['OptionValue'];
						foreach($vars as $x=>$y){
							$sel=($val==$x)?'selected="selected"':'';
							$opt.='<option value="'.$x.'" '.$sel.'>'.$y.'</option>';
						}
						$o[$v['id']]='<label>'.$v['OptionName'].'<select name="opt['.$v['id'].']">'.$opt.'</select></label>';
				}
			}
		}
		$content=implode('',$o);
		$sub_act=($admin)?'update_calendar_options_admin':'update_calendar_options';
		$fclass=($admin)?'xform':'xForm';
		$control='<button class="button button-olive expanded" type="submit"><i class="fi-check"></i> Save</button>';
		if($this->AJAX){
			$control='<div class="modal-footer">'.$control.'</div>';
			$form='<span class="label bg-navy expanded text-center">These settings affect the public site calendar (except for the colours)</span><form id="formX" class="'.$fclass.'" action="'.$this->PERMLINK.'calendar_options" method="post"><input type="hidden" name="action" value="'.$sub_act.'"/><div class="tabs-content">'.$content.'</div>'.$control.'</form>';
			echo renderCard_active('Calendar Settings',$form,$this->SLIM->closer);
			die;
		}
		$output['title']='Calendar Settings';
		$output['content']='<div class="callout" id="member">'.$content.$control.'</div>';
		return $output;		
	}
	private function saveCalendarOptions($admin=false){
		$post=$this->REQUEST;
		$url=($admin)?$this->PERMLINK:URL.'page';
		$mtype='alert';
		$close=0;
		$state=500;
		if($post && in_array($post['action'],['update_calendar_options','update_calendar_options_admin'])){
			$DB=$this->SLIM->db;
			$_chk=0;
			foreach($post['opt'] as $i=>$v){
				$u['OptionValue']=$v;
				$rec=$DB->Options()->where('id',$i);
				$chk=$rec->update($u);
				$_chk+=(int)$chk;
			}
			if($_chk>0){
				$state=200;
				$mtype='success';
				$msg='Okay the settings have been updated.';
				$close=1;
			}else{
				$msg='okay, but no changes have been made...';
				$state=201;
				$mtype='primary';
			}
		}else{
			$msg='Sorry, the details seem to be invalid...';
		}
		$resp=array('status'=>$state,'message'=>$msg,'type'=>'redirect','message_type'=>$mtype,'url'=>$url,'close'=>$close);
		if($this->AJAX){
			jsonResponse($resp);
		}else{
			setSystemResponse($url,$msg);
		}
		die;
	}
	
	private function renderSales(){
		$contents=$this->SLIM->SalesMan->render();
		if($this->AJAX){
			echo renderCard_active($contents['title'],$contents['content'],$this->SLIM->closer);
			die;			
		}
		$this->OUTPUT['title']=$contents['title'];
		$metrics=issetCheck($contents,'metrics');
		return $metrics.$contents['content'];
	}
	private function renderProducts(){
		$contents=$this->SLIM->Products->render();
		if($this->AJAX){
			echo renderCard_active($contents['title'],$contents['content'],$this->SLIM->closer);
			die;			
		}
		return $contents['content'];
	}
	private function renderReports(){
		$contents=$this->SLIM->EventReporter->render();
		if($this->AJAX){
			echo renderCard_active($contents['title'],$contents['content'],$this->SLIM->closer);
			die;			
		}
		$this->OUTPUT['title_controls']=issetCheck($contents,'title_controls');
		return $contents['content'];
	}
	private function getTableData($ref){
		$data=$this->LIB->get('list',$ref);
		$count=0;
		$tbl=[];
		if($data){
			if(is_array($data)){
				foreach($data as $i=>$dat){
					$dat=$this->formatData($dat);
					$rclr=($dat['Rollcall'] > 0)?'dark-green':'gray';
					$sclr=($dat['EventStatus']==='Active')?'olive':'gray';
					$rollcall='<span class="text-'.$rclr.'" data-ref="'.$this->PERMLINK.'rollcall/'.$i.'">'.$dat['Rollcall'].'</span>';
					$state='<span class="text-'.$sclr.'">'.$dat['EventStatus'].'</span>';
					$control='<button class="button button-navy small gotoME" data-ref="'.$this->PERMLINK.'rollcall/'.$i.'"><i class="fi-torso"></i> Rollcall</button>';
					$control.='<button class="button button-dark-purple small loadME" data-size="large" data-ref="'.$this->PERMLINK.'edit/'.$i.'"><i class="fi-pencil"></i> Edit</button>';
					$tbl[$i]=array(
						'Ref'=>$i,
						'Name'=>$dat['EventName'],
						'Type'=>$dat['EventType'],
						'Date'=>$dat['EventDate'],
						'Rollcall'=>$rollcall,
						'Status'=>$state,
						'Controls'=>$control
					);
					$count++;
				}
			}else{
				
			}
		}
		return ['count'=>$count,'table'=>$tbl];
	}
	private function renderTable($ref='all'){
		$data=$this->getTableData($ref);
		$count=$data['count'];
		if($count){
			$args['data']['data']=$data['table'];
			$args['before']='filter';
			$list=dataTable($args);
		}else{
			$list=msgHandler('No event records found...',false,false);
		}
		$data['table']=$list;
		return $data;
	}
	private function renderPreviewForm($id=0){
		$contents['title']='Event Form Preview';
		$pattern=issetCheck($this->ROUTE,4);
		$form=$this->SLIM->EventForms->renderPreviewForm($pattern,$id);
		$contents['content']='<div class="tabs-content">'.$form.'</div>';
		if($this->AJAX){
			echo renderCard_active($contents['title'],$contents['content'],$this->SLIM->closer);
			die;			
		}
		return $contents['content'];
	}	
	private function renderMailer($id){
		$add=issetCheck($this->ROUTE,3);
		$this->SLIM->Email->setMode();
		$msg=false;
		if($add==='add'){
			$recipients=$this->getEventMembersInfo($id,array('name','email'));
			if($recipients){
				$this->SLIM->Email->addRecipients($recipients,true);
				$msg='Okay, '.count($recipients).' members have been added to the email recipients list.';
			}
		}
		setSystemResponse($this->PERMBACK.'mailer',$msg);
	}
	private function renderSubmittedForms($id=0){
		$opt=issetCheck($this->ROUTE,4);
		$is_log_id=($opt==='logid')?true:false;
		$rw=[];
		$th=$filter=$modal_info=false;
		$content=msgHandler(lang('Sorry, no forms found...'),'alert',false);
		$forms=$this->getForms($id,$is_log_id);
		if($forms){
			if($id){
				$id=$forms['ID'];//ensure we have the form id
				$EF=$this->SLIM->EventForms;
				$EF->MODE='admins';
				$EF->FORM_REC=$forms;
				$EF->SESSION_REC=$forms['FormData'];
				if($opt==='download'){
					$content=$EF->get('pdf_form','admin');
					die('oops!');
				}else{
					$content=$EF->get('admin_form');
					$content.='<div class="button-group expanded"><button class="button small button-dark-blue gotoME" data-ref="'.$this->PERMLINK.'submitted_form/'.$id.'/download"><i class="fi-download"></i> Download</button></div>';
					$modal_info=false;					
				}				
			}else{
				$events=$this->getFormEvents($forms);
				$th='<tr><th>ID</th><th data-sort="string">Member</th><th>Event</th><th>Date</th><th>Status</th><th>Controls</th></tr>';
				foreach($forms as $i=>$v){
					$event=issetCheck($events,$v['EventLogID']);
					$event_name=($event)?$event['EventName']:'?? event: '.$v['EventLogID'].' not found ??';
					$rw[]='<tr>
						<td>'.$v['ID'].'</td>
						<td>'.$v['MemberName'].'</td>
						<td>'.$event_name.'</td>
						<td>'.validDate($v['LogDate']).'</td>
						<td>'.$v['FormStatus'].'</td>
						<td><button class="button small loadME button-dark-purple" data-ref="'.$this->PERMLINK.'submitted_form/'.$v['ID'].'"><i class="fi-eye"></i> View</button></td>
					</tr>';
				}
				$filter='<div id="filter">'.$this->SLIM->zurb->inlineLabel('Filter','<input id="dfilter" class="input-group-field" type="text"/>');
				$filter.='<div class="metrics">'.count($rw).' Record(s)</div></div>';
				$modal_info='<span class="text-white">&nbsp;Set the basic event here, other options can be added later.</span>';
				if(!$rw){
					$content=msgHandler(lang('Sorry, no forms found...'),'alert',false);
				}else{
					$content='<table id="dataTable" class="dataTable"><thead>'.$th.'</thead><tbody>'.implode('',$rw).'</tbody></table>';
				}
			}
		}
		if($this->AJAX){
			echo renderCard_active('Submitted Forms',$content,$this->SLIM->closer,false,false,false,$modal_info);
			die;			
		}
		$this->SLIM->assets->set('js','JQD.ext.initMyTable("#dfilter","#dataTable");','my_table');

		return '<h3>Submitted Forms</h3>'.$filter.'<div class="tablewrap">'.$content.'</div>';
	}
	
	private function getFormEvents($data=false){
		if(is_array($data)){
			$ins=$ins2=[];
			foreach($data as $i=>$v) $ins[]=$v['EventLogID'];
			$DB=$this->SLIM->db->EventsLog();
			$recs=$DB->select('EventID,EventLogID')->where('EventLogID',$ins);
			$elogs=renderResultsORM($recs);
			foreach($elogs as $rec) $ins2[$rec['EventID']]=$rec['EventID'];
			$DB=$this->SLIM->db->Events();
			$recs=$DB->select('EventID,EventName')->where('EventID',$ins2);
			$events=renderResultsORM($recs,'EventID');
			foreach($elogs as $i=>$v){
				$out[$v['EventLogID']]=$events[$v['EventID']];
			}
			return $out;			
		}
		return false;
	}
	
	private function getForms($id=false,$is_log_id=false){
		$DB=$this->SLIM->db->FormsLog();
		if($id){
			$recs=($is_log_id)?$DB->where('EventLogID',$id):$DB->where('ID',$id);
		}else{
			$recs=$DB->select('ID,MemberID,MemberName,EventLogID,LogDate,FormStatus')->order('LogDate DESC');
		}
		if(count($recs)>0){
			$recs=renderResultsORM($recs,'ID');
			if($id){
				$recs=current($recs);
				$recs['FormData']=compress($recs['FormData'],false);
			}
		}else{
			$recs=[];
		}
		return $recs;
	}
	
	private function getEventMembersInfo($event_id=0){
		$members=[];
		if($event_id){
			$log=$this->SLIM->EventsLogAPI->get('event',$event_id);
			foreach($log as $i=>$v){
				$email=issetCheck($v,'Email');
				if($email){
					$members[$v['MemberID']]=array(
						'id'=>$v['MemberID'],
						'name'=>$v['FirstName'].' '.$v['LastName'],
						'email'=>$v['Email'],
						'dojo'=>$v['Dojo'],
						'grade'=>$v['Grade'],
					);
				}
			}
		}
		return $members;
	}
	private function getOption($what=false,$val=false){
		switch($what){
			case 'EventAddress':
				$r=issetCheck($this->OPTIONS[$what],$val);
				if($r) $val=$r['LocationName'].' - '.$r['LocationCountry'];
				break;
			case 'EventType':
				$r=issetCheck($this->OPTIONS[$what],$val);
				if($r) $val=$r['OptionName'];
				break;
			case 'EventStatus':
				$val=issetCheck($this->OPTIONS[$what],$val);
				break;
		}
		return $val;		
	}
	private function getSelect($what=false,$var=false){
		$out='';
		switch($what){
			case 'EventAddress':
			case 'EventStatus':
			case 'EventType':
				$opts=$this->OPTIONS[$what];
				break;
			default:
				$opts=false;
		}
		if($opts){
			foreach($opts as $i=>$v){
				switch($what){
					case 'LocationID':
						$sel=($i==$var)?'selected':'';
						$out.='<option value="'.$i.'" '.$sel.'>'.$v['LocationName'].' - '.$v['LocationCountry'].'</option>';
						break;
					case 'Status':
						$sel=($i==$var)?'selected':'';
						$out.='<option value="'.$i.'" '.$sel.'>'.$v.'</option>';
						break;
				}						
			}
		}
		return $out;
	}
	private function formatData($data,$mode='view'){
		$fix=array();
		foreach($data as $i=>$v){
			$val=$v;
			switch($i){
				case 'EventAddress':
				case 'EventStatus':
				case 'EventType':
					if($mode==='view'){
						$val=$this->getOption($i,$v);
					}else if($mode==='edit'){
						$val=$this->getSelect($i,$v);
					}else{
						$val=(int)$v;
					}
					break;
				case 'EventDate':
					$val=validDate($v);
					break;
			}
			$fix[$i]=$val;
		}
		return $fix;
	}
	private function renderFixup(){
		//fix event locations
		$locs=$this->SLIM->db->Events->where("EventAddress > 0 AND (EventLocation = '' OR EventLocation IS NULL)")->select('EventID,EventAddress,EventLocation');
		$locs=renderResultsORM($locs,'EventID');
		$ct=0;
		foreach($locs as $i=>$v){
			if($name=$this->getOption('EventAddress',$v['EventAddress'])){
				$upd=['EventLocation'=>$name];
				$rec=$this->SLIM->db->Events->where('EventID',$i);
				if(count($rec)==1){
					if($chk=$rec->update($upd)) $ct++;
				}else{
					preME([$v,$upd,(string)$rec],2);
				}
			}
		}
		preME($ct.' of '.count($locs).' locations updated',2);
	}
}	
