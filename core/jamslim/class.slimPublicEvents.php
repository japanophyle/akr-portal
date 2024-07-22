<?php

class slimPublicEvents{
	var $SLIM;
	var $ROUTE;
	var $USER;
	var $REQUEST;
	var $METHOD;
	var $PERMBACK;
	var $PERMLINK;
	var $E_CONTENT;
	var $MEMBERS_ONLY=true;
	var $JFORM;
	var $AJAX;
	var $USE_WIZARD;
	var $GET;
	var $PAGE;
	var $TITLE;
	var $CAT;
	var $CATEGORIES;
	var $PRODUCTS;
	var $EVENT_ID;
	var $OUTPUT;
	
	
	function __construct($slim){
		if(!$slim) die(__METHOD__.': no slim object supplied.');
		$this->SLIM=$slim;
		$this->E_CONTENT=$this->SLIM->EventContent;
		$this->JFORM=$slim->EventForms;
		$this->USER=$this->SLIM->user;
		$this->ROUTE=$this->SLIM->router->get('route');
		$this->PERMBACK=URL.'page';
		$this->PERMLINK=URL.implode('/',$this->ROUTE).'/';
		$this->METHOD=$this->SLIM->router->get('method');
		$this->REQUEST=($this->METHOD==='POST')?$_POST:$_GET;
		$this->AJAX=$this->SLIM->router->get('ajax');
		$this->USE_WIZARD=0;
	}
	function init($vars=false){
		if(is_array($vars)){
			foreach($vars as $i=>$v){
				$k=strtoupper($i);
				$this->$k=$v;
			}
		}
	}
	
	function render($what){
		if($this->METHOD==='POST') $this->renderPost();
		switch($what){
			case 'day':
			case 'event_day':
				return $this->renderDay();
				break;
			case 'register_confirm':
				return $this->renderConfirmForm('review_form');
				break;
			case 'reg_form_edit':
				return $this->renderConfirmForm('edit_review_form');
				break;
			case 'confirmed':
				return $this->renderConfirmForm('confirmed');
				break;
			case 'events':
				return $this->renderEvents();
				break;
			case 'calendar':
				return $this->renderCalendar();
				break;
			case 'ics':
				return $this->renderICS();
				break;
			case 'cancel_reg':case 'cancel_reg_now':
				return $this->renderCancelForm($what);
				break;
			default:
				return $this->renderEvent();
		}
	}
	private function renderPost(){
		$action=issetcheck($this->REQUEST,'action');
		switch($action){
			default:
				$res=$this->SLIM->PublicForms->renderPost($this->REQUEST);
		}
	}

	private function renderEvents(){
		$cal_months=$this->SLIM->options->get('application','Calendar Months');
		$cal_power=$this->SLIM->options->get('application','Calendar Power');
		$cal_ics=$this->SLIM->options->get('application','Calendar ICS');
		$cc=(int)issetCheck($cal_months,'OptionValue',3);
		$cal_power=(int)issetCheck($cal_power,'OptionValue');
		$cal_ics=(int)issetCheck($cal_ics,'OptionValue');
		$cal_months_code='calendar'.$cc;
		$date=issetCheck($this->REQUEST,'cdate',date('Y-m-d'));
		$cal=$this->SLIM->Calendar;
		$cal->set('url',$this->PERMBACK.'/event/day/');
		$cal->set('date',$date);
		$calendar=$cal->render($cal_months_code);
		$events=$cal->render('event_list');
		if(!$events) $events=msgHandler('No events happening. Please check again later.',false,false);
		$edit=($this->USER['access']>=25)?'<button class="button hx-button button-purple small loadME" data-ref="'.URL.'admin/events/calendar_options"><i class="fi-widget"></i> Calendar Settings</button>':'';
		if($cal_ics) $edit.='<button class="button hx-button  button-navy small gotoME" data-ref="'.$this->PERMBACK.'/event/ics" title="download events to your device calendar"><i class="fi-rss"></i> Events Data</button>';
		if(!$cal_power){
			$msg=$this->SLIM->language->getStandardPhrase('no_event_power');
			if($edit!=='') $msg.='<br/>'.$edit;
			$out='<div class="panel text-center">'.msgHandler($msg,false,false).'</div>';
		}else{
			$out='<h3 class="sub-title">'.$this->SLIM->language->getStandard('events').$edit.'</h3><div class="grid-x grid-margin-x">				
				<div class="cell large-6">
					<div class="block">
						'.$events.'
					</div>
				</div>
				<div class="cell auto">
					<div class="block">
					'.$calendar.'
					</div>
				</div>
			</div>';
		}
		return $out;
	}
	private function renderEvent(){
		$id=issetCheck($this->ROUTE,3);
		$event=false;
		$register=$edit='';
		if($id){
			$rec=$this->getEvent($id,1);
			if($rec){
				$member_id=issetCheck($this->USER,'MemberID',0);
				$registered=$this->isRegistered($id,$member_id);
				$closed=$this->registrationClosed($rec);
				$subscribed=$this->hasSubscription();
				$start=$this->SLIM->language_dates->langDate($rec['EventDate']);
				$end=$this->SLIM->language_dates->langDate($rec['EventDuration']);
				$this->E_CONTENT->loadEvent($rec);
				$content=($this->AJAX)?$this->E_CONTENT->getContent():$this->E_CONTENT->getContent();
				$edit=($this->USER['access']>=$this->SLIM->AdminLevel)?'<button class="button small button-dark-blue loadME small" data-ref="'.URL.'admin/events/edit/'.$id.'"><i class="fi-pencil"></i> Edit Event</button>':'';
				if($end!==$start) $start.=' - '.$end;
				if($this->MEMBERS_ONLY && $this->USER['access']< $this->SLIM->UserLevel){
					$register='<button class="button loadME expanded button-ahk-red" data-ref="'.URL.'page/login"><i class="fi-torso"></i> '.$this->SLIM->language->getStandardPhrase('login_tip').'</button>';
				}else if($closed){
					$register=msgHandler('<i class="fi-information"></i> '.$this->SLIM->language->getStandardPhrase('registration_is_now_closed'),'primary',false);
				}else if(!$subscribed){
					$but='<button class="button small gotoME" data-ref="'.URL.'page/my-home"><i class="fi-torso"></i> '.$this->SLIM->language->getStandard('my_home').'</button>';
					$register=msgHandler('<i class="fi-alert"></i> '.$this->SLIM->language->getStandardPhrase('membership_has_expired').' '.$but,'warning',false);
				}else if($registered){
					$but='<button class="button small gotoME" data-ref="'.URL.'page/my-home/view_my_events"><i class="fi-torso"></i> '.$this->SLIM->language->getStandard('my_events').'</button>';
					$register=msgHandler('<i class="fi-check"></i> '.$this->SLIM->language->getStandardPhrase('already_registered').' '.$but,'success',false);
				}else{
					$register='';
					if($this->AJAX){
						if($this->MEMBERS_ONLY && $this->USER['access']>=$this->SLIM->UserLevel){
							if($registered){
								$but='<button class="button gotoME expanded button-ahk-red" data-ref="'.URL.'page/event/register/'.$id.'"><i class="fi-pencil"></i> '.$this->SLIM->language->getStandardPhrase('already_registered').'</button>';
							}else{
								$but='<button class="button gotoME expanded button-ahk-red" data-ref="'.URL.'page/event/register/'.$id.'"><i class="fi-pencil"></i> '.$this->SLIM->language->getStandard('register_now').'</button>';
							}
						}else{
							$but='<button class="button loadME expanded button-ahk-red" data-ref="'.URL.'page/login"><i class="fi-torso"></i> '.$this->SLIM->language->getStandardPhrase('login_tip').'</button>';
						}
						$register=($rec['EventForm'])?$but:'';
					}
				}
				$event='<dl class="cal_event"><dt>'.$rec['EventName'].'</dt>';
				$event.='<dd><span>'.$this->SLIM->language->getStandard('date').':</span> '.$start.'</dd>';
				$event.='<dd>'.$content.'</dd>';
				$event.='</dl>';
			}
		}
		if(!$event){
			$event=msgHandler($this->SLIM->language->get('Sorry, no event found...'),'alert',false);
		}else if(!$this->AJAX){
			//add form
			if(!$registered) {
				$event.=$this->renderEventForm($rec['EventForm'],$id,$rec['EventName']);
			}
		}
		if($this->AJAX){			
			$register='<div class="modal-footer">'.$register.'</div>';
			echo renderCard_active($this->SLIM->language->getStandard('event'),"<div class=\"tabs-content\"><div class=\"callout\">$event</div></div>$register",$this->SLIM->closer);
			die;
		}
		$output['title']=$this->SLIM->language->getStandard('event_registration');
		$output['content']='<div class="block" id="member">'.$edit.$event.$register.'</div>';
		return $output;
	}
	private function renderCancelForm($act=''){
		if($act==='cancel_reg_now'){
			$data=issetCheck($_SESSION['userArray'],'reg_form',[]);
			if($data) unset($_SESSION['userArray']['reg_form']);
			setSystemResponse($this->PERMBACK.'home','Okay, the event registration has been cancelled.');
		}else{
			$ref=issetCheck($this->ROUTE,3);
			$content='<div class="callout primary text-center"><p class="h3 text-dark-blue">Do you want to cancel this event registration?</p></div>';
			$content.='<div class="button-group expanded"><button class="button secondary" data-close><i class="fi-x-circle"></i> No, maybe later.</button><button class="button small button-red gotoME small" data-ref="'.$this->PERMBACK.'/event/cancel_reg_now/'.$ref.'"><i class="fi-check"></i> Yes, do it now.</button></div>';
		}
		$output['title']='Cancel Registration';
		$output['content']=$content;		
		if($this->AJAX){
			echo '<style>.reveal .card-section.main {max-height: 100%;overflow-Y: auto;}</style>';
			echo renderCard_active($output['title'],$output['content'],$this->SLIM->closer);
			die;
		}			
		return $output;	
	}
	private function renderConfirmForm($mode='review_form'){
		$this->TITLE=($mode)?ucME($mode):'Confirm Details';
		$registered=false;
		$data=$this->SLIM->PublicForms->SESSION_REC;
		if($data){
			$registered=($data['member_id']>0)?$this->isRegistered($data['event_id'],$data['member_id']):false;
		}
		if($registered){
			$content=msgHandler('You are already registered for this event','success',false);
		}else{
			$content=$this->SLIM->PublicForms->render($mode);
		}
		if(!$this->AJAX) $content='<div class="callout review-wrap">'.$content.'</div>';
		return array('title'=>'Review Form','content'=>$content);
	}

	private function renderEventForm($form_id=0,$event_id=0,$title=false){
		$form=$tp=$o_grade=false;
		$can_apply=true;
		$lang=$this->SLIM->language->get('_LANG');

		$tip=$this->SLIM->language->getStandardPhrase('login_tip');
		$tip='<i class="fi-megaphone"></i> '.$tip;
		$open_grade=$this->SLIM->language->getStandardPhrase('open_to_grade');
		$open_grade='<i class="fi-info"></i> '.$open_grade;
		
		$rec=$this->getEvent($event_id,1);
		$limit=json_decode($rec['EventLimit'],1);

		if($this->USER['access']>19){
			if($limit && !empty($limit)){
				$u_grade=$this->SLIM->options->get('grade_by_value',$this->USER['Grade']);
				if(!in_array($u_grade['OptionID'],$limit)){
					$can_apply=false;
					$o_grade=$open_grade;
					$grades=$this->SLIM->options->get('grades');
					$grades=rekeyArray($grades,'OptionID');
					foreach($limit as $l) $o_grade.='<br/>&bull; '.$grades[$l]['OptionName'];
				}
			}
		}else if(!$this->MEMBERS_ONLY){
			if(is_array($limit)){
				$tp=$open_grade;
				$grades=$this->SLIM->options->get('grades');
				$grades=rekeyArray($grades,'OptionID');
				foreach($limit as $l) $tp.='<br/>&bull; '.$grades[$l]['OptionName'];
			}
		}else{
			$can_apply=false;
			$tp=$tip;
		}
		if($can_apply && $form_id){			
			$login=($this->USER['access']<20)?msgHandler($tp,'bg-lavendar',false):'';
			if($o_grade) $login.=msgHandler($o_grade,'bg-blue',false);
			$this->JFORM->EVENT=$rec;
			$this->JFORM->USER=$this->USER;
			if(!$form_id) $form_id='member_event';
			$pattern=($this->USER['access']<20)?'non_member_event':$form_id;
			$res=$this->JFORM->renderForm($pattern);
			$tpl=file_get_contents(TEMPLATES.'app/app.form_wizard.html');
			$submit=$this->SLIM->language->getStandard('submit');
			$js_submit='false';
			if(!$this->USE_WIZARD){
				//$js_submit='function(e){wizForm.utils.validateAll(e)}';
			}
			$form=array(
				'title'=>$title,
				'sections'=>$res,
				'submit'=>$submit,
				'form_url'=>$this->PERMLINK,
				'form_class'=>'',
				'power'=>$this->USE_WIZARD,
				'on_submit'=>$js_submit,
				'event_id'=>(int)$event_id,
				'member_id'=>$this->USER['id'],
				'form_category'=>0,
			);
			if(!$this->USE_WIZARD){
				//$this->SLIM->assets->set('js','wizForm.go(wizForm_options);','fwiz');
			}
			$_form=replaceME($form,$tpl);
			$form='<h3>'.$this->SLIM->language->getStandard('register').'</h3>'.$login.$_form;
		}else if(!$can_apply){
			if($o_grade)$tp=$o_grade;
			$form=msgHandler($tp,'bg-lavendar',false);
		}
		return $form;
	}
	private function renderCalendar(){
		$yr=issetCheck($_GET,'yr',date('Y'));
		$mn=issetCheck($_GET,'mn',date('m'));
		$cal=$this->SLIM->Calendar;
		$cal->set('url',$this->PERMBACK.'/event/day/');
		$cal->set('date',$yr.'-'.$mn.'-01');
		$cal->MINI_CALENDAR=false;
		$calendar=$cal->render('calendar');	
		return $calendar;
	}
	private function renderICS(){
		$yr=issetCheck($_GET,'yr',date('Y'));
		$mn=issetCheck($_GET,'mn',date('m'));
		$cal=$this->SLIM->Calendar;
		$cal->set('url',$this->PERMBACK.'/event/day/');
		$cal->set('date',$yr.'-'.$mn.'-01');
		$cal->MINI_CALENDAR=false;
		$calendar=$cal->render('ics');	
		return $calendar;
	}
	private function renderDay(){
		$cdate=issetCheck($this->REQUEST,'cdate');
		$events=false;
		if($cdate){
			$strdate=$this->SLIM->language_dates->langDate(validDate($cdate));
			$db=$this->SLIM->db->Events();
			$recs=$db->where('EventDate',$cdate.' 00:00:00')->or('EventDuration',$cdate.' 00:00:00');
			if(count($recs)<1){// search for events
				$db=$this->SLIM->db->Events();
				$recs=$db->where('EventDate <= ?',$cdate.' 00:00:00')->and('EventDuration >= ?',$cdate.' 00:00:00');
			}
			if(count($recs)>0){
				$recs=renderResultsORM($recs,'EventID');
				foreach($recs as $i=>$v){
					$start=$this->SLIM->language_dates->langDate($v['EventDate']);
					$end=$this->SLIM->language_dates->langDate($v['EventDuration']);//validDate($v['EventDuration'],'D jS F Y');
					if($end && $end!=$start) $start.=' - '.$end;
					$events.='<li><span class="title">'.$v['EventName'].'</span><span class="dates text-olive">'.$start.'</span><button class="button small button-ahk-red loadME expanded" data-ref="'.URL.'page/event/view/'.$i.'">'.$this->SLIM->language->get('Details').'</li>';
				}
			}
		}
		if(!$events){
			$events=msgHandler($this->SLIM->language->getStandardPhrase('no_events_found'),'alert',false);
		}else{
			$events='<ul class="events_list">'.$events.'</ul>';
		}
		if($this->AJAX){
			echo renderCard_active($this->SLIM->language->getStandard('events').': '.$strdate,"<div class=\"tabs-content\"><div class=\"callout secondary\">$events</div></div>",$this->SLIM->closer);
			die;
		}
		return $events;		
	}
	private function hasSubscription(){
		$MB= new slim_db_members($this->SLIM);
		$member_id=issetCheck($this->USER,'MemberID',0);
		$subs=$MB->get('current_membership',$member_id);
		return $subs;
	}
	private function isRegistered($event_id=0,$member_id=0){
		if(!$member_id)	$member_id=issetCheck($this->USER,'MemberID',0);
		if((int)$member_id>0){
			$chk=$this->getEventLog($event_id,$member_id);
			return ($chk)?true:false;
		}
		return false;
	}
	private function registrationClosed($rec=false){
		$closed=$then=false;
		$now=new DateTime();
		if(is_array($rec)){
			if($reg=issetCheck($rec,'EventRegDate')){
				$then= new DateTime($reg);
			}else if($start=issetCheck($rec,'EventDate')){
				$then= new DateTime($start);
			}
			if($then){
				if($now>$then) $closed=true;
			}
		}
		return $closed;
	}
	private function getEventLog($event_id=0,$member_id=0){
		$reg=false;
		$db=$this->SLIM->db->EventsLog();
		if($member_id && $event_id){
			$rec=$db->where('MemberID',$member_id)->and('EventID',$event_id);
		}else if($member_id){
			$rec=$db->where('MemberID',$member_id);
		}else if($event_id){
			$rec=$db->where('EventID',$event_id);
		}else{
			$rec=array();
		}
		if(count($rec)>0){
			$rec=$db->select('EventLogID,EventID,MemberID,EventCost,Paid');
			$reg=renderResultsORM($rec,'EventLogID');
		}
		return $reg;		
	}
	private function getEvent($event_id=0,$status=0){
		$event=false;
		$rec=$this->SLIM->db->Events()->where('EventID',$event_id);
		if($status) $rec->and('EventStatus',$status);
		$event=renderResultsORM($rec);
		if(!empty($event)) $event=current($event);
		return $event;
	}

}
