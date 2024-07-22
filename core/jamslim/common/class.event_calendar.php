<?php
 
class event_calendar{
	var $date = '';
	var $dateoffset=0; //offset for date in hours
	var $linkurl= '';//link for dates with events
	var $events = array(); //associative array of event count with yyyy-mm-dd as key
	var $cal_events = array(); //associative array of event count with yyyy-mm-dd as key
	
	var $tablestyle='calendar-table';//table name for css style 
	var $width=300; //width of month column
	var $SLIM;
	var $ROUTE;
	var $METRICS;
	var $CALENDAR;
	var $ADMIN_URL;
	var $USE_COLORS=false;//get from appvars
	var $LANGUAGE;
	var $EVENTS_STATUS=1;//set to 0 for admin
	var $MINI_CALENDAR=true;
	var $LARGE_LINK=true;// for mini calendars
	var $CALENDAR_MONTHS=1;
	var $ADD_CONTROLS=false;
	var $CALENDAR_SIZE;
	var $DEFAULT_VIEW;
	
	function __construct($slim=null){
		$this->SLIM=$slim;
		$this->LANGUAGE=$slim->language->get('_LANG');
		$this->ROUTE=$slim->router->get('route');
		$op=$this->SLIM->options->get('application');
		if($cc=issetCheck($op,'Calendar Colours')){
			$this->USE_COLORS=((int)$cc['OptionValue'])?true:false;
		}
		if($cc=issetCheck($op,'Calendar Months')){
			$this->CALENDAR_MONTHS=(int)$cc['OptionValue'];
		}
		$this->ADMIN_URL=URL.'admin/events/';
		$this->CALENDAR=new ev_cal();
		$this->CALENDAR->ADMIN_URL=$this->ADMIN_URL;
		$this->CALENDAR->USE_COLORS=$this->USE_COLORS;
		$this->CALENDAR->DATE_LANG=$slim->language_dates;
		$this->CALENDAR->LARGE_LINK=$this->LARGE_LINK;
		$this->date_metrics();
	}
	
	function render($what=false,$vars=false){
		$this->CALENDAR->MINI_CALENDAR=$this->MINI_CALENDAR;
		switch($what){
			case 'calendar12':
			case 'calendar8':
			case 'calendar6':
			case 'calendar3':
			case 'calendar4':
			case 'calendar2':
				if($vars) $this->set('date',$vars);
				$ct=(int)str_replace('calendar','',$what);
				$out=$this->month_calendar($ct);
				break;
			case 'event_list':
				$out=$this->renderEventsList();
				break;
			case 'year_plan':
				if($vars) $this->set('date',$vars);
				$out=$this->renderYearPlan($vars);
				break;
			case 'ics':
				$out=$this->renderICS();
				break;
			default://curent month calendar
				if($vars) $this->set('date',$vars);
				$out=$this->month_calendar();
		}
		return $out;
	}
	function set($what=false,$vars=false){
		switch($what){
			case 'date':
				$this->date=$vars;
				$this->date_metrics();
				break;
			case 'url':
				$this->linkurl=$vars;
				break;
			case 'events':
				//$this->setEvents($vars);
		}
	}
	function date_metrics(){
		$datestr=$this->date;
		$linkurl=$this->linkurl;
		if(empty($datestr)||$datestr===''){ 
			$this->curr_date();
		}
		$date=strtotime($this->date);
		$month=date('m', $date);
		$year=date('Y', $date);
		$first=strtotime($year."-".$month."-01");
		$this->METRICS=array(
			'date'=>strtotime($this->date),			
			'month' => $month,
			'month3'=> date('M', $date),
			'year' => $year,
			'month_start_day'=> date('D', $first),
			'num_days_current'=>$this->days_in_month(),
			'last_day_of_month'=>$year.'-'.$month.'-'.date('t',$first)
		);		
	}
	function raw_calendar(){		
		$linkurl=$this->linkurl;
		$this->setEventsData();
		$this->CALENDAR->METRICS=$this->METRICS;
		$this->CALENDAR->EVENTS=$this->cal_events;
		$this->CALENDAR->LINK_URL=$this->linkurl;
		$this->CALENDAR->LANG=$this->LANGUAGE;
		$this->CALENDAR->DATE_LANG->LANG=$this->LANGUAGE;
		$calendar=$this->CALENDAR->render();
		return $calendar;
	}
	

	//offset in hours
	function curr_date() {
		$offset=$this->dateoffset;
		$currentDate=date('Y-m-d',time()+$offset*3600);
		$this->date=$currentDate;
	}
	
	function days_in_month(){
		$date=$this->date;
		$monthstartdate=date('Y-m-01',strtotime($date));
		$nextmonthstartdate=date('Y-m-01',strtotime($monthstartdate)+32*24*3600);
		$timedifference=strtotime($nextmonthstartdate)-strtotime($monthstartdate);
		$daysinmonth=$timedifference/(24*3600);
		return round($daysinmonth);
	}
	
	function checkDuration($start=false,$end=false){
		if(!$start && !$end) return false;
		$out=[];
		$st=strtotime($start);
		$en=strtotime($end);
		$chk=($en-$st)/(24*3600);
		if($chk>0){
			for($x=1;$x<=$chk;$x++){
				$out[]=date('Y-m-d',strtotime($start.' +'.$x.'day'));
			}
		}
		return $out;
	}
	function next_month(){
		$date=$this->date;
		$monthstartdate=date('Y-m-01',strtotime($date));
		$nextmonthstartdate=date('Y-m-01',strtotime($monthstartdate)+32*24*3600);
		$this->date=$nextmonthstartdate;
		$this->date_metrics();
	}

	function month_calendar($ct=1){
		$this->CALENDAR->LINEAR=false;
		$conv=array(0=>4,1=>12,2=>6,3=>4,4=>3,6=>6,8=>3,12=>4);
		$col_width=$conv[$ct];		
		$x=1;
		while($x<=$ct){
			$cal[$x]=$this->raw_calendar();
			if($x<$ct) $this->next_month();
			$x++;
		}
		$thismonthcalendar=$this->raw_calendar();
		$calendar='<div id="'.$this->tablestyle.'" class="grid-x" data-equalizer data-equalize-on="medium"><div class="cell medium-'.$col_width.' calendar-wrap" data-equalizer-watch>';
		$calendar.=implode('</div><div class="cell medium-'.$col_width.' calendar-wrap" data-equalizer-watch>',$cal).'</div>';
		$calendar.='</div>';
		return $calendar;
	}
	
	private function setEventsData(){
		$db=$this->SLIM->db->Events();
		$fdt=strtotime($this->date.' - 30 days');//allow for events started in previous month
		$first=date('Y-m-01',$fdt);		
		$recs=$db->where('EventDate >= ?',$first);
		$recs->and('EventDate <= ?',$this->METRICS['last_day_of_month']);
		if($this->EVENTS_STATUS) $recs->and('EventPublic',1)->and('EventStatus',1);
		if(count($recs)>0){
			$recs=renderResultsORM($recs,'EventID');
			foreach($recs as $i=>$v){
				$dur=$this->checkDuration($v['EventDate'],$v['EventDuration']);
				$v['EventColor']=$this->SLIM->options->get('event_color',$v['EventType']);
				$tm=explode(' ',$v['EventDate']);
				if($dur){//add days to events
					foreach($dur as $d){
						$this->cal_events[$d][$i]=$v;
					}
				}
				$this->cal_events[$tm[0]][$i]=$v;
				$this->events[$tm[0]][$i]=$v;
			}
		}
		ksort($this->events);
	}
	
	private function renderEventsList(){
		$out=false;
		foreach($this->events as $date=>$recs){
			foreach($recs as $i=>$v){
				$start=$this->CALENDAR->DATE_LANG->langDate($v['EventDate']);
				$end=$this->CALENDAR->DATE_LANG->langDate($v['EventDuration']);
				if($end && $end!=$start) $start.=' - '.$end;
				$color=($this->USE_COLORS)?$v['EventColor']:'ahk-red';
				$out.='<li><span class="title text-title">'.$v['EventName'].'</span><span class="dates text-subtitle">'.$start.'</span><button class="button small button-'.$color.' loadME expanded" data-ref="'.URL.'page/event/view/'.$i.'">'.$this->SLIM->language->getStandard('details').'</li>';
			}
		}
		if($out) $out='<ul class="events_list">'.$out.'</ul>';
		return $out;
	}
	
	function renderYearPlan($date=false){
		if(!$date) $date=date('Y-m-01',time());
		$this->date=$date;
		$this->date_metrics();
		$this->CALENDAR->LINEAR=true;
		$out=[];
		$controls='<button class="button button-navy small gotoME" title="back" data-ref="'.$this->ADMIN_URL.'events_planner/?pyear='.($this->METRICS['year']-1).'"><i class="fi-arrow-left"></i></button>';
		$ys=$this->METRICS['year'];
		for($ct=0;$ct<=11;$ct++){
			$cal=$this->raw_calendar();
			if($ct==0) $out[0]='<td><strong class="text-gray">Month</strong></td>'.$cal['days_head'];
			$out[]='<tr><td class="sticky-label"><strong class="text-gray">'.strip_tags($cal['month_head']).'</strong></td>'.$cal['days'].'</tr>';
			$this->next_month();
		}
		if($ys!==$this->METRICS['year']) $ys.='/'.$this->METRICS['year'];
		$head=$out[0];
		unset($out[0]);
		$controls.='<button class="button button-navy small gotoME" title="forwards" data-ref="'.$this->ADMIN_URL.'events_planner/?pyear='.($this->METRICS['year']).'"><i class="fi-arrow-right"></i></button>';
		$plan='<table class="calendar planner"><thead><tr>'.$head.'</tr></thead><tbody>'.implode("\n",$out).'</tbody></table>';
		return renderCard_active(lang('12 Month Planner').' - '.$ys,'<div class="planner-ui">'.$plan.'</div>',$controls);
	}	
	private function renderICS(){
		$cal=$this->month_calendar($this->CALENDAR_MONTHS);
		$data=[];
		foreach($this->cal_events as $date=>$events){
			$t=strtotime($date);			
			foreach($events as $i=>$v){
				if((int)$i){
					$v['cal_date']=$date;
					$v['cal_time']=$t;
					$data[$i]=$v;
				}
			}
		}
		$ICS=new slim_ics($this->SLIM);
		$out=$ICS->get('download',$data);
	}

}

class ev_cal{
	var $LANG='en';
	var $LINEAR=false;
	var $FIRST_DAY='monday';//sunday or monday
	var $TEMPLATE=array(
		'TD'=>'<td {props}>',
		'CTD'=>'</td>',
		'TH'=>'<th {props}>',
		'CTH'=>'</th>',
		'ETD'=>'<td>&nbsp;</td>',
		'ROW'=>'<tr>',
		'CROW'=>'</tr>',
		'NROW'=>'</tr><tr>',
		'MONTH'=>'<th colspan="7" {mprops}>{month3} - {year}</th>',
		'CALENDAR'=>'<table class="calendar mini_calendar"><thead><tr>{month_head}</tr></thead><tbody><tr>{days_head}</tr>{days}</tbody></table>'
	);
	var $USE_COLORS;
	var $METRICS;
	var $EVENTS;
	var $LINK_URL;
	var $TODAY;
	var $ADMIN_URL;
	var $DATE_LANG;
	var $MINI_CALENDAR=true;
	var $LARGE_LINK=true;
	
	private $DAY_ORDER=array(0,1,2,3,4,5,6);
	
	function __construct(){
		$this->TODAY=date('Y-m-d');
		$this->ADMIN_URL=URL.'events/';
	}
	
	function render(){
		$calendar=$this->TEMPLATE['CALENDAR'];
		if(!$this->MINI_CALENDAR) $calendar=str_replace('calendar mini_calendar','calendar',$calendar);
		$o['month_head']=$this->renderMonthHead();
		$o['days_head']=$this->renderDaysHead();
		$o['days']=$this->renderDays();
		if($this->LINEAR){
			return $o;
		}else{
			return replaceMe($o,$calendar);
		}
	}
	
	private function renderCell($var=false,$props=false){
		$cell=$this->TEMPLATE['TD'].$var.$this->TEMPLATE['CTD'];
		$cell=str_replace('{props}',$props,$cell);
		return $cell;
	}
	private function renderCellHead($var=false,$props=false){
		if($this->LINEAR){
			$cell=$this->TEMPLATE['TH'].$var.$this->TEMPLATE['CTH'];
		}else{
			$cell=$this->TEMPLATE['TD'].$var.$this->TEMPLATE['CTD'];
		}	
		$cell=str_replace('{props}',$props,$cell);
		return $cell;
	}
	private function renderRow($var=false){
		return $this->TEMPLATE['ROW'].$var.$this->TEMPLATE['CROW'];	
	}
	private function renderDaysHead(){
		if($this->FIRST_DAY==='monday')	$this->DAY_ORDER=array(1,2,3,4,5,6,0);
		$days=explode(',',$this->DATE_LANG->DAYS[$this->LANG]);
		$out='';
		foreach($this->DAY_ORDER as $k){
			$d=$days[$k];
			if($this->LANG!=='fr') $d=strtoupper($d);
			$out.=$this->renderCellHead($d,'class="dayname text-gray"');
		}
		if($this->LINEAR){
			$o=$out;
			for($x=1;$x<=4;$x++) $out.=$o;
			$ex=($this->FIRST_DAY==='monday')?$days[1]:$days[0];
			if($this->LANG!=='fr') $ex=strtoupper($ex);
			$out.=$this->renderCellHead($ex,'class="text-gray"');
			$ex=($this->FIRST_DAY==='monday')?$days[2]:$days[1];
			if($this->LANG!=='fr') $ex=strtoupper($ex);
			$out.=$this->renderCellHead($ex,'class="text-gray"');
		}
		return $out;
	}
	private function renderDayCell($curdate,$weekday){
		//make date to check case count
		$title=$ref=$class=$props=false;
		if($curdate<10){
			$thisdate=$this->METRICS['year']."-".$this->METRICS['month']."-0$curdate";
		}else{
			$thisdate=$this->METRICS['year']."-".$this->METRICS['month']."-$curdate";
		}
		$hasevents=$this->hasEvents($thisdate);
		//fill dates in cells		
		if($curdate>0 && $curdate<=$this->METRICS['num_days_current'] && $hasevents){
			$ref=($this->LINEAR)?$this->ADMIN_URL.'event_day/'.$hasevents['url']:$hasevents['url'];
			if(!$this->MINI_CALENDAR && !$this->LINEAR){
				if(count($hasevents['data'])==1){
					$ref=URL.'page/event/view/'.key($hasevents['data']);
				}
			}
			$color=($this->USE_COLORS)?$hasevents['color']:'ahk-red text-white';
			$class='day button-'.$color.' small loadME';
			$title=$hasevents['title'];
			$label=$curdate;
			if(!$this->MINI_CALENDAR){
				$evs=[];
				foreach($hasevents['data'] as $i=>$v) $evs[]=$v['EventName'];
				$label.='<br/>&bull; '.implode('<br/>&bull; ',$evs);
			}
		}elseif($curdate>0 && $curdate<=$this->METRICS['num_days_current']){
			$class='day';
			$label=$curdate;
			if($weekday==5||$weekday==6) $class.=' text-light-blue';
			if($this->LINEAR){
				$title='add an event';
				$class.=' loadME';
				$ref=$this->ADMIN_URL.'event_new/?cdate='.$thisdate;
			}
		}else{
			$class='nday';
			$label='&nbsp;';
		}
		if($thisdate===$this->TODAY) $class.=' today';
		if($ref) $props.=' data-ref="'.$ref.'"';
		if($title) $props.=' title="'.$title.'"';
		if($class) $props.=' class="'.$class.'"';
		return array('props'=>$props,'content'=>$label);
	}
	private function renderDays(){
		$out=$row='';
		$offset=$this->getOffset();
		$weekday=0;
		$dc=0;
		$curdate=1-$offset;
		while($curdate<=$this->METRICS['num_days_current']){
			$daycell=$this->renderDayCell($curdate,$weekday);
			$row.=$this->renderCell($daycell['content'],$daycell['props']);
			$curdate++;
			$weekday++;
			$dc++;
			//start new table-row if required
			if($weekday>6){
				if($this->LINEAR){
					$weekday=0;
				}else{
					$weekday=0;
					$out.=$this->renderRow($row);
					$row='';
				}
			}
		}
		//fill blank cells at the end of month
		$d='&nbsp;';
		if($this->LINEAR){
			if($dc<37){
				while($dc<=36){
					$row.=$this->renderCell($d,'class="nday"');
					$dc++;
				}
			}
			return $row;
		}else{
			if($weekday>0 && $weekday<=6){
				while($weekday<=6){
					$row .=$this->renderCell($d,'class="nday"');
					$weekday++;
				}
				$out.=$this->renderRow($row);
			}
			return $out;
		}
	}
	private function renderMonthHead(){
		$days=explode(',',$this->DATE_LANG->DAYS[$this->LANG]);
		$out=$this->TEMPLATE['MONTH'];
		$months=explode(',',$this->DATE_LANG->MONTHS3[$this->LANG]);
		$m=$months[($this->METRICS['month']-1)];
		if($this->LANG!=='fr') $m=ucwords($m);
		$out=str_replace('{month3}',$m,$out);
		$out=str_replace('{year}',$this->METRICS['year'],$out);
		$opt='class="month text-dark-blue"';
		if($this->LARGE_LINK){
			if($this->MINI_CALENDAR){
				$u=URL.'page/event/calendar/?yr='.$this->METRICS['year'].'&mn='.$this->METRICS['month'];
				$opt='class="month link link-dark-blue gotoME" data-ref="'.$u.'"';
			}
		}
		$out=str_replace('{mprops}',$opt,$out);
		return $out;
	}
	private function getOffset(){
		if($this->FIRST_DAY==='monday'){
			$os=array('Mon','Tue','Wed','Thu','Fri','Sat','Sun');
		}else{
			$os=array('Sun','Mon','Tue','Wed','Thu','Fri','Sat');
		}
		$offset=(int)array_search($this->METRICS['month_start_day'],$os);
		return $offset;
	}
	function hasEvents($date){
		$out=[];
		if($hasevents=issetCheck($this->EVENTS,$date)){
			$ct=count($hasevents);
			$out['title']=($ct>1)?' Events':'Event';
			$out['url']=$this->LINK_URL.'?cdate='.$date;
			$t=current($hasevents);
			$out['color']=$t['EventColor'];
			$out['data']=$hasevents;
		}
		return $out;
	}

}
