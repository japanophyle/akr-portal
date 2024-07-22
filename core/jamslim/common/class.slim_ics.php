<?php

/**
 * slim_ics.php
 * =======
 * Use this class to create an .ics file.
 *
 * Usage
 * -----
 * Basic usage - generate ics file contents (see below for available properties):
 *   $ics = new slim_ics($slim);
 * 	 $ics->init($event_id);
 *   $ics_file_contents = $ics->get('ics');
 *
 * Setting properties after instantiation
 *   $ics = new slim_ics($slim);
 *   $ics->set('summary', 'My awesome event');
 *
 * You can also set multiple properties at the same time by using an array:
 *   $ics->set(array(
 *     'dtstart' => 'now + 30 minutes',
 *     'dtend' => 'now + 1 hour'
 *   ));
 *
 * Available properties
 * --------------------
 * description
 *   String description of the event.
 * dtend
 *   A date/time stamp designating the end of the event. You can use either a
 *   DateTime object or a PHP datetime format string (e.g. "now + 1 hour").
 * dtstart
 *   A date/time stamp designating the start of the event. You can use either a
 *   DateTime object or a PHP datetime format string (e.g. "now + 1 hour").
 * location
 *   String address or description of the location of the event.
 * summary
 *   String short summary of the event - usually used as the title.
 * url
 *   A url to attach to the the event. Make sure to add the protocol (http://
 *   or https://).
 */

class slim_ics {
	const DT_FORMAT = 'Ymd\THis\Z';

	protected $properties = [];
	private $available_properties = [
		'description',
		'dtend',
		'dtstart',
		'rrule',
		'location',
		'summary',
		'url'
	];
	private $SLIM;
	private $ROUTE;
	private $AJAX;
	private $EVENT_ID;
	private $EVENT_DATA;
	private $OPTIONS;
	private $REQUEST;
	private $PERMLINK;
	private $FILENAME;

	function __construct($slim=null){
		if(!$slim) throw new Exception('no slim object!!');
		$this->SLIM=$slim;
        $this->ROUTE = $slim->router->get('route');
        $this->AJAX = $slim->router->get('ajax');
        $this->PERMLINK=$slim->router->get('permlinks','base').'page/';
        $this->REQUEST=$slim->router->get('get');
		$this->OPTIONS['eventType']=$slim->Options->get('events');
		$this->OPTIONS['eventType'][9]=array('label'=>'Bank Holiday','color'=>'#800080');
		$this->OPTIONS['locations']=array(1=>array('label'=>'The Buddhist Society','address'=>'58 Eccleston Square, London, SW1V 1PH'));
		$this->OPTIONS['eventRecur']=array(0 => '* No *', 1 => 'Annually (by date)', 2 => 'Monthly (by date)', 3 => 'Weekly (by day)',4 => 'Annually (by day)', 5 => 'Monthly (by day)',6=>'Daily (not weekends)',7=>'Daily (all days)');
		$this->OPTIONS['rRule']=array(
			0 => false, 
			1 => ['Annually (by date)','FREQ=YEARLY;INTERVAL={interval};BYMONTH={month};BYMONTHDAY={month_day};UNTIL={end}'], 
			2 => ['Monthly (by date)','FREQ=MONTHLY;INTERVAL={interval};BYMONTH={month};BYMONTHDAY={month_day};UNTIL={end}'],
			3 => ['Weekly (by day)','FREQ=WEEKLY;INTERVAL={interval};BYDAY={day};UNTIL={end}'],
			4 => ['Annually (by day)','FREQ=YEARLY;INTERVAL={interval};BYMONTH={month};BYDAY={st_day};UNTIL={end}'], 
			5 => ['Monthly (by day)','FREQ=MONTHLY;INTERVAL={interval};BYMONTH={month};BYDAY={st_day};UNTIL={end}'],
			6 => ['Daily (not weekends)','FREQ=DAILY;INTERVAL=1;BYDAY=MO,TU,WE,TH,FR;UNTIL={end}'],
			7 => ['Daily (all days)','FREQ=DAILY;INTERVAL=1;UNTIL={end}']
		);
	}
	
	private function init($ref=false) {
		$this->properties=[];
		$this->EVENT_ID=$this->EVENT_DATA=false;
		if(is_numeric($ref)){
			$this->setEvent($ref);			
		}else if(is_array($ref)){
			$this->setEvents($ref);
		}
	}
    
    function get($what=false,$vars=false){
		switch($what){
			case 'download':
				$this->init($vars);
				$this->renderDownload();
				break;
			case 'ics':
				$this->init($vars);
				return $this->to_string();
			case 'props':
				return $this->properties;
			case 'data':
				return $this->EVENT_DATA;
			default:
				return false;
		}
	}
	function set($key, $val = false) {
		if (is_array($key)) {
			foreach ($key as $k => $v){
				$this->set($k, $v);
			}
		} else {
			if (in_array($key, $this->available_properties)) {
				$this->properties[$key] = $this->sanitize_val($val, $key);
			}
		}
	}

	private function to_string() {
		if(!$this->EVENT_DATA) return false;
		$rows=$this->build_calendar();
		return implode("\r\n", $rows);
	}
    private function renderDownload(){
		$str=$this->to_string();
		if($str && $str!==''){			
			header('Content-Type: text/calendar; charset=utf-8');
			header('Content-Disposition: attachment; filename='.$this->FILENAME.'.ics');
			echo $str;
			die;
		}else{
			$u=$this->PERMLINK.'home';
			setSystemResponse($u,'Sorry, no events found to download...');
		}
	}
	private function build_props($data){
		// Build ICS properties
		$props = array();
		$ics_props[] = 'BEGIN:VEVENT';
		
		foreach($this->properties as $k => $v) {
			if($k==='rrule'){
				$metrics=$this->SLIM->JCAL_Functions->date_metrics($data['cal_date']);
				$ro=array(
					'interval'=>1,
					'day'=>strtoupper(substr($metrics['dayname'],0,2)),
					'month'=>(int)$metrics['month'],
					'month_day'=>(int)$metrics['day'],
					'end'=>$this->properties['dtend']
				);
				$props['RRULE']=replaceME($ro,$v);
			}else{
				$props[strtoupper($k . ($k === 'url' ? ';VALUE=URI' : ''))] = $v;
			}
		}

		// Set some default values
		$props['DTSTAMP'] = $this->format_timestamp('now');
		$props['UID'] = uniqid();

		// Append properties
		foreach ($props as $k => $v) {
			$ics_props[] = "$k:$v";
		}

		// Build ICS properties - add footer
		$ics_props[] = 'END:VEVENT';

		return $ics_props;
	}
	
	private function build_calendar($events=false){
		if(!$events) $events=$this->EVENT_DATA;
		if(!$events) return false;
		$ics=array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//hacksw/handcal//NONSGML v1.0//EN',
			'CALSCALE:GREGORIAN'
		);
		foreach($events as $e){
			foreach($e as $v) $ics[]=$v;
		}
		$ics[]='END:VCALENDAR';
		return $ics;
	}

	private function sanitize_val($val, $key = false) {
		switch($key) {
			case 'dtend':
			case 'dtstamp':
			case 'dtstart':
				$val = $this->format_timestamp($val);
				break;
			case 'rrule':
				//skip
				break;
			default:
				$val = $this->escape_string($val);
		}
		return $val;
	}

	private function format_timestamp($timestamp) {
		$dt = new DateTime($timestamp);
		return $dt->format(self::DT_FORMAT);
	}

	private function escape_string($str) {
		return preg_replace('/([\,;])/','\\\$1', $str);
	}
	
	private function setEvent($id=0){
		if($id){
			$db=$this->SLIM->db->myp_events;
			$rec=$db->where('eventID',$id);
			$rec=renderResultsORM($rec);
			if($rec){
				$this->EVENT_ID=$id;
				$this->EVENT_DATA=$this->fixEventRecord(current($rec));
				$this->FILENAME='AKR_Event_'.$id;
				$this->setProps();
			}
		}
	}
	private function setEvents($args=[]){
		if($args){
			$recs=$events=[];			
			foreach($args as $i=>$v){
				$recs[$i]=$this->fixEventRecord($v);
			}
			if($recs){
				foreach($recs as $i=>$v){
					$this->setProps($v);					
					$events[$i]=$this->build_props($v);
				}
				if($events){
					$c=current($args);
					$this->FILENAME='AKR_Events_'.date('M_Y',$c['cal_time']);
					$this->EVENT_DATA=$events;
				}
			}
		}
	}
	
	private function fixEventRecord($data=false){
		if($data){
			$fix=$data;
			$start=explode(' ',$data['EventDate']);
			$end=explode(' ',$data['EventDuration']);
			$time=(int)issetCheck($data,'eventTime_Start');
			$fix['start']=$start[0];//date('Y-m-d',$data['eventDate_Start']);
			$fix['end']=$end[0];//date('Y-m-d',$data['eventDate_End']);
			$fix['time']=$start[1];//date('H:i',$time);
			//$fix['eventData']=unserialize($data['eventData']);
			$data=$fix;
		}
		return $data;
	}
	private function setProps($edata=false){
		if(!$edata) $edata=$this->EVENT_DATA;
		foreach($this->available_properties as $p){
			switch($p){
				case 'description':
					$tmp=$this->OPTIONS['eventType'][$edata['EventType']];
					$v=issetCheck($tmp,'label');
					break;
				case 'dtend':
					$v=$edata['end'];
					break;
				case 'dtstart':
					$v=$edata['start'];
					break;
				case 'location':
					$v=$edata['EventLocation'];
					break;
				case 'summary':
					$v=$edata['EventName'];
					break;
				case 'rrule':
					$recur=(int)issetCheck($edata,'EventRecur');
					$v=false;
					if($recur){
						$v=$this->OPTIONS['rRule'][$recur][1];
					}
					break;
				case 'url':
					$v=$this->PERMLINK;
					$v.='event/'.$edata['EventID'];
					break;
				default:
					$v=false;
			}
			if($v) $this->set($p,$v);
		}
	}
}
