<?php 

class event_item {	
	private $SLIM;
	private $LIB;
	private $ID;
	private $OPTIONS;
	private $SELECT_OPTIONS;
	private $EVENT_OPTIONS=array('basic'=>false,'default'=>false);
	private $DOJOS;
	private $RESPONSE=array('status'=>500,'data'=>false);
	private $AJAX=false;
	private $MEMBER_EVENT_LOG;
	private $CATEGORIES;
	private $FORM_DEFS;
	private $E_CONTENT;
	private $DATA;
	private $DATA_KEYS;
	private $FORM_LOG_DB;
	private $SHINSA_ORDER=array(
		'shinsa-shodan'=>1,
		'shinsa-nidan'=>2,
		'shinsa-sandan'=>3,
		'shinsa-yondan'=>4,
		'shinsa-godan'=>5,
		'shinsa-rokudan'=>6,
		'shinsa-nanadan'=>7,
		'shinsa-renshi'=>8,
		'shinsa-kyoshi'=>9,
	);

	function __construct($slim=null,$data=false){
		if(is_object($slim)){
			$this->SLIM=$slim;
			$this->OPTIONS=$slim->Options;
			$this->EVENT_OPTIONS['basic']=$this->OPTIONS->get('event_options_basic');
			$this->EVENT_OPTIONS['default']=$this->OPTIONS->get('event_options_default');
			$this->SELECT_OPTIONS['EventStatus']=$this->OPTIONS->get('active');
			$this->SELECT_OPTIONS['EventType']=$this->OPTIONS->get('events');
			$this->SELECT_OPTIONS['EventAddress']=$this->OPTIONS->get('locations');
			$this->SELECT_OPTIONS['EventProduct']=$this->OPTIONS->get('products');
			$this->SELECT_OPTIONS['EventPublic']=$this->OPTIONS->get('yesno');
			$this->SELECT_OPTIONS['EventCurrency']=$this->OPTIONS->get('currency');
			$this->DOJOS=$this->OPTIONS->get('dojos_name');
			$this->AJAX=$slim->router->get('ajax');
			$this->LIB=$slim->EventsLib;
			$this->E_CONTENT=$slim->EventContent;
			$this->FORM_LOG_DB=$slim->db->FormsLog;
			if($data) $this->Reset($data);
		}else{
			throw new Exception(__METHOD__.' Error: the slimOptions object is required...');
		}
	}
	function get($what=false,$vars=false,$alt=false){
		$data_key=(in_array($what,array_keys($this->DATA)))?true:false;
		switch($what){
			case 'all': case 'data':
				return $this->DATA;
				break;
			default:
				if($data_key){
					return issetCheck($this->DATA,$what);
				}
		}
		return $this->RESPONSE;
	}
	private function initData($data){
		if(is_numeric($data)){
			$tmp=$this->LIB->get('event',$data);
			if($tmp['status']==200){
				$data=$tmp['data'];
			}else{
				$data=[];
			}
		}
		if($data && is_array($data)){
			$this->DATA=$data;
			$this->DATA_KEYS=array_keys($data);
			$this->ID=$data['EventID'];
			return true;
		}
		return false;
	}
	private function initProducts(){
		if(!$this->CATEGORIES){
			$this->CATEGORIES=$this->OPTIONS->get('product_types');
		}
		if(!$this->FORM_DEFS){
			$this->FORM_DEFS=$this->OPTIONS->get('form_defs');
		}
	}
	public function Reset($data=false){
		if($this->initData($data)){
			$log=$this->get('EventsLog');
			$this->MEMBER_EVENT_LOG=(isset($log['status']))?[]:$log;			
			$this->RESPONSE=array('status'=>500,'data'=>false);
			return true;
		}
		return false;
	}
	public function Render($what=false,$vars=false){
		switch($what){
			case 'logfields':
				$this->renderEventLogFields($vars);
				break;
			case 'options':
				$this->renderEventOptions($vars);
				break;
			case 'form_options':
				$this->renderEventFormOptions($vars);
				break;
			case 'event_content':
				$this->renderEventContent($vars);
				break;
			case 'report':
				$this->renderEventReport($vars);
				break;
			case 'dates':
				$this->renderEventDates($vars);
				break;
			case 'rollcall':
				return $this->renderEventRollcall($vars);
				break;
			default:
				$this->RESPONSE['message']='Sorry, unknown request ['.$what.']';
		}
		return $this->RESPONSE;		
	}
	private function formatData($mode='view'){
		$mode=($mode!=='view')?'edit':'view';
		$out=[];
		foreach($this->DATA_KEYS as $k){
			$val=$this->DATA[$k];
			switch($k){
				case 'EventID': case 'EventProductLimit': case 'Members': case 'Locations': case 'recent_member_events':
					//skip;
					continue 2;
					break;				
				case 'EventOptions':
				
					break;
				case 'EventRooms':
				
					break;
				case 'EventsLog':
				
					break;
				case 'EventNotes':
					if($mode==='edit') $val='<textarea name="'.$k.'" rows="15">'.$val.'</textarea>';
					break;
				case 'EventDate':case 'EventDuration':case 'EventRegDate':
					$val=validDate($val);
					if($mode==='edit') $val='<input type="date" name="'.$k.'" value="'.$val.'"/>';
					break;
				case 'EventType':case 'EventAddress':case 'EventProduct': case'EventProduct2':
				case 'EventPublic':case 'EventCurrency':case 'EventStatus':
					if($mode==='edit'){//select options
						$opts=$this->getSelectOptions($k,$val);
						$val='<select name="'.$k.'">'.$opts.'</select>';
					}else{
						$opt=issetCheck($this->SELECT_OPTIONS,$k);
						if($opt){
							if($tmp=issetCheck($opt,$val)){
								if(is_array($tmp)){
									$tv=issetCheck($tmp,'OptionValue');
									if(!$tv) $tv=issetCheck($tmp,'LocationName');
									if(!$tv) $tv=issetCheck($tmp,'ItemTitle');
									if(!$tv) $tv=issetCheck($tmp,'label');
									if(!$tv) preME([$k,$val,$tmp],2);
								}else{
									$tv=$tmp;
								}
								$val=$tv;
							}
						}else{
							//skip
						}
					}
					break;
				case 'EventCost':
					$val=toPounds($val);
					if($mode==='edit') $val='<input type="currency" name="'.$k.'" value="'.$val.'"/>';
					break;
				default:
				    if(is_array($val)) preME($k,2);
					if($mode==='edit') $val='<input type="text" name="'.$k.'" value="'.$val.'"/>';
					
			}
			$out[$k]=$val;
		}
		return $out;
	}
	public function renderEditEvent(){
		$groups=array(
			'Details'=>array('EventName','EventType','EventDate','EventDuration','EventLocation','EventPublic','EventStatus'),
			'Notes'=>array('EventNotes'),
			'Rollcall'=>array('EventLogID','MemberID','Attending','Forms','FormsSent','Room'),
			'Options'=>array('EventOptions'),
			'Form'=>array('EventForm'),
		);
		$title='Edit Event #'.$this->ID;
		if($this->DATA){
			$title.=': <small class="text-dark-blue">'.$this->DATA['EventName'].'</small>';
			$ginfo=[];
			//render fields
			$data=$this->formatData('edit');
			//render tabs
			$tabs=[];
			foreach($groups as $t=>$g){
				$tmp='';
				switch($t){
					case 'Rollcall':
						$tmp=$this->renderEventRollcall();
						break;					
					case 'Form': 
						$tmp=$this->renderEventFormOptions();
						break;					
					case 'Options': 
						$tmp=$this->renderEventOptions();
						break;					
					case 'Content':
						$tmp=$this->renderEventContent();
						break;					
					default:
						foreach($g as $k){
							$tmp.='<label for="'.$k.'">'.camelTo($k).'</label>'.$data[$k];
						}
				}
				$tabs[$t]=$tmp;
			}
			$tabs=$this->SLIM->zurb->tabs(['id'=>'edit_event','tabs'=>$tabs]);
			$tabs.='<input type="hidden" name="action" value="update"/>';
			$tabs.='<input type="hidden" name="id" value="'.$this->ID.'"/>';
			$controls='<button class="button button-teal loadME" data-reload="true" data-ref="'.URL.'admin/events/edit/'.$this->ID.'"><i class="fi-refresh"></i> Reload Record</button>';
			$controls.='<button class="button button-red loadME" data-ref="'.URL.'admin/events/delete/'.$this->ID.'"><i class="fi-x-circle"></i> Delete</button>';
			$controls.='<button class="button button-dark-blue gotoME" data-ref="'.URL.'admin/events/report/'.$this->ID.'"><i class="fi-clipboard-notes"></i> Report</button>';
			$controls.='<button class="button button-olive" type="submit"><i class="fi-check"></i> Save</button>';
			$content='<form class="ajaxForm" method="post" action="'.URL.'admin/events">'.$tabs.'<div class="button-group small expanded">'.$controls.'</div></form>';
		}else{
			$content=msgHandler('Sorry, no records found for ref:'.$this->DATA['ref'],false,false);
		}
		$out['title']=$title;
		$out['content']=$content;		
		if($this->AJAX){
			echo '<style>.reveal .card-section.main {max-height: 100%;overflow-Y: auto;}</style>';
			echo renderCard_active($out['title'],$content,$this->SLIM->closer);
			echo '<script>$(".reveal .card-section.main").foundation();</script>';
			die;
		}
		return $out;
	}
	public function renderViewEvent(){
		$details=array('EventName','EventType','EventDate','EventDuration','EventAddress','EventPublic','EventStatus');
		$title='View Event #'.$this->ID;
		if($this->DATA){
			$title.=': <small class="text-dark-blue">'.$this->DATA['EventName'].'</small>';
			$ginfo=[];
			//render fields
			$data=$this->formatData('view');
			//render tabs
			$rows='';
			foreach($details as $k) $rows.='<tr><th class="text-dark-blue">'.camelTo($k).'</th><td>'.$data[$k].'</td></tr>';
			$controls='<button class="button button-dark-blue loadME" data-reload="true" data-ref="'.URL.'admin/events/edit/'.$this->ID.'"><i class="fi-pencil"></i> Edit Event</button>';
			$content='<div class="callout"><table class="dataTable"><tbody>'.$rows.'</tbody></table><div class="button-group small expanded">'.$controls.'</div></div>';
		}else{
			$content=msgHandler('Sorry, no records found for ref:'.$this->DATA['ref'],false,false);
		}
		$out['title']=$title;
		$out['content']=$content;		
		if($this->AJAX){
			echo '<style>.reveal .card-section.main {max-height: 100%;overflow-Y: auto;} .reveal .dataTable tbody th{text-align:left; background-color:#e8e8e8;}</style>';
			echo renderCard_active($out['title'],$content,$this->SLIM->closer);
			die;
		}
		return $out;
		
	}
	private function renderEventLogFields($fields=false){
		$chk=$this->get('EventType');
		if(!$chk && $this->MEMBER_EVENT_LOG){
			//for members event log
			$this->renderMemberEventLog();
			return;
		}		
		//for roll call
		$tpl=false;
		$_fields=$sort=[];
		if($fields){
			$data=$this->get('EventOptions');
			$_fields=array('EventLogID','MemberID');
			$field_opts=$this->EVENT_OPTIONS['basic'];
			$ct=0;
			foreach($data as $set=>$vals){
				foreach($vals as $i=>$v){
					if((int)$v){
						$chk=issetCheck($field_opts[$set][$i],'fields');
						if(is_array($chk)){
							$_fields=array_merge($_fields,$chk);
							foreach($chk as $c){
								$sort['sort_'.$ct]=$v;
								$_tpl['sort_'.$ct]='<td class="form-[::'.$c.'_state::]"><small>'.$field_opts[$set][$i]['label'].'</small><br/>[::'.$c.'::]</td>';
								$ct++;
							}
						}
					}					
				}
			}			
		}
		if($_fields) $fields=$_fields;
		asort($sort);
		$tpl='';
		foreach($sort as $i=>$v)$tpl.=$_tpl[$i];
		$this->RESPONSE=array('status'=>200,'data'=> array('fields'=>$fields,'tpl'=>$tpl));
	}
	private function renderMemberEventLog(){
		$current=current($this->MEMBER_EVENT_LOG);
		$fields=array_keys($current);
		$td='';
		foreach($fields as $f=>$vals){
			$td.='<td>[::'.$f.'::]</td>';
		}
		$tpl='<tr id="[::EventLogID::]">'.$td.'</tr>';
		$this->RESPONSE=array('status'=>200,'data'=> array('fields'=>$fields,'tpl'=>$tpl));
	}
	private function renderEventOptionsX($args=false){
		$o=$this->EVENT_OPTIONS['basic'];
		$tr='';
		foreach($o as $set=>$options){
			$td='';
			$th=false;
			foreach($options as $i=>$v){
				$val=$v['required'];
				$tik=(int)issetCheck($args['attr_ar']['value'][$set],$i,$v['required']);
				if($tik)$tik='checked'; 
				$attr=array('id'=>'cbk_'.$set.'_'.$i,'title'=>$v['label'],'checked'=>$tik);
				$tb=array('name'=>'EventOptions['.$set.']['.$i.']','value'=>$val,'attr_ar'=>$attr);
				$chkbox=$this->getTickbox($tb);				
				$td.='<td>'.$chkbox.'</td>';
				$th.='<th>'.$v['label'].'</th>';
			}
			$tr.='<tr><th>-</th>'.$th.'</tr>';
			$tr.='<tr><td><strong>'.ucME($set).'</strong></td>'.$td.'</tr>';
		}
		$th='<tr><th colspan="4">Event Options</th></tr>';
		$table='<table class="checkboxSelector text-left" id="selectEventOptions">'.$th.$tr.'</table>';
		$table.='<script>checkboxSelector("#selectEventOptions");</script>';	
		$this->RESPONSE=array('status'=>200,'data'=> $table);
	}
	private function renderEventRollcall($mode='view'){
		$members=issetCheck($this->DATA,'Members',[]);
		$control='<button class="button button-dark-blue small expanded gotoME" data-ref="'.URL.'admin/events/rollcall/'.$this->ID.'">Click here to manage the Rollcall</button>';
		if($this->MEMBER_EVENT_LOG){
			$rows=[];
			foreach($this->MEMBER_EVENT_LOG as $i=>$v){
				$member=issetCheck($this->DATA['Members'],$v['MemberID'],[]);
				$form=((int)$v['Forms'])?'Yes':'No';
				$attend=((int)$v['Attending'])?'Yes':'No';
				$controls='';
				if($mode==='admin'){
					$disb='<button class="button small button-blue" disabled><i class="fi-eye"></i> Form</button>';
					if($form==='Yes') $disb='<button class="button small button-blue loadME" data-ref="'.URL.'admin/events/submitted_form/'.$i.'/logid/"><i class="fi-eye"></i> Form</button>';
					$controls.=$disb;
					$controls.='<button class="button small button-dark-purple loadME" data-ref="'.URL.'admin/events/edit_rollcall/'.$i.'"><i class="fi-pencil"></i> Edit</button>';
				}
				if($mode==='admin'){
					$name=$member['LastName'].', '.$member['FirstName'];
					if(!$this->AJAX) $name='<span class="link-dark-blue loadME" data-ref='.URL.'admin/member/view/'.$v['MemberID'].'"">'.$name.'</span>';
					$rows[$i]=[
						'Name'=>$name,
						'Grade'=>$member['CGradeName'],
						'Dojo'=>$member['Dojo'],
						'Form'=>$form,
						'Attending'=>$attend,
						'Controls'=>$controls
					];
				}else{
					$rows[$i]='<tr><td>'.$member['LastName'].', '.$member['FirstName'].'</td><td>'.$member['CGradeName'].'</td><td>'.$member['Dojo'].'</td><td>'.$form.'</td><td>'.$attend.'</td></tr>';
				}
			}
			if($mode==='admin'){
				$args['data']['data']=$rows;
				$args['before']='filter';
				$table=dataTable($args);
				$control='';
			}else{
				$thead='<th>Name</th><th>Dojo</th><th>Grade</th><th>Forms</th><th>Attending</th>';
				$table='<table class="minidatatable"><thead>'.$thead.'</thead><tbody>'.implode("\n",$rows).'</tbody></table>';
			}
		}else{
			if($mode==='admin' && !$this->AJAX) $control='';
			$table=msgHandler('No rollcall records found...',false,false);
		}
		return $control.$table;
	}
	private function renderEventOptions($args=false){
		//Grade limit
		$grade='<h5 class="text-gray">Grade Limit</h5>'.$this->renderGradeLimit();
		//rooms
		$rcount=$this->get('EventRooms');
		if(!is_array($rcount)||empty($rcount)){
			$rcount=array('single'=>0,'double'=>0);
		}
		$room_metrics=$this->checkRooms();
		$single='<label><strong>Single Rooms:</strong><input name="EventRooms[single]" type="number" min="0" max="100" value="'.$rcount['single'].'" /></label>';
		$double='<label><strong>Double Rooms:</small></strong><input name="EventRooms[double]" type="number" min="0" max="100" value="'.$rcount['double'].'" /></label>';
		$rooms='<h5 class="text-gray">Rooms</h5><div class="grid-x grid-padding-x"><div class="cell medium-6">'.$single.$double.'</div><div class="cell medium-6">'.$room_metrics.'</div></div>';

		//tracking options for editing
		$o=$this->EVENT_OPTIONS['basic'];
		$td=array();
		$str=array();
		$th=array('set'=>array(),'not_set'=>array());
		$cnt=1;
		foreach($o as $set=>$options){
			foreach($options as $i=>$v){
				$val=($args)?(int)issetCheck($args['attr_ar']['value'][$set],$i):(int)$i;
				$pow=($val>0)?'on':'';
				$tmp='<th><div title="click and drag to move this column" class="some-handle"></div><div title="click to toggle" data-ref="'.$i.'" class="power '.$pow.'"></div></th>';
				if($val>0){
					$th['set']['th'][$cnt]=$tmp;
					$th['set']['td'][$cnt]='<td>'.$v['label'].'</td>';
					$str[$cnt]=$i;
				}else{
					$th['not_set']['th'][$cnt]=$tmp;
					$th['not_set']['td'][$cnt]='<td>'.$v['label'].'</td>';
				}
				$cnt++;
			}
		}
		$thead=issetCheck($th['set'],'th');
		if($thead) $thead=implode('',$thead);
		if(isset($th['not_set']['th']))  $thead.=implode('',$th['not_set']['th']);
		$trow=issetCheck($th['set'],'td');
		if($trow) $trow=implode('',$trow);
		if(isset($th['not_set']['td']))  $trow.=implode('',$th['not_set']['td']);
		
		$table='<link href="assets/css/mgrid.min.css" rel="stylesheet" /><h5 class="text-gray">Reporting Options</h5><label>Select fields to display on this events rollcall.</label><span class="label bg-blue expanded">Drag colums using the striped bar, click the small square to its left to toggle the column on or off</span><div class="ip_grid_wrapper">
		<input type="hidden" id="grid_order" name="EventOptions" value="'.implode(',',$str).'" style="width:100%;"/>
		<table id="options_mgrid" class="ip_grid_sheet handlerTable" data-order="#grid_order" data-power="1" data-handle="some-handle">
		<thead><tr>'.$thead.'</tr></thead><tbody><tr>'.$trow.'</tr></tbody>
		</table></div>';
		$table.='<script>JQD.ext.initMGrid_sort("options_mgrid");</script>';
		return $table.$rooms.$grade;
	}
	private function checkRooms(){
		$booked=array('single'=>0,'double'=>0);
		$rooms=$this->get('EventRooms');
		$log=$this->get('EventsLog');
		foreach($log as $i=>$v){
			$r=(int)$v['Room'];
			if($r==1)$booked['single']++;
			if($r==2)$booked['double']++;
		}
		$out='<table><thead><th>Type</th><th>Booked</th><th>Available</th></thead><tbody>';
		foreach($rooms as $i=>$v){
			$q=($v-$booked[$i]);
			$cls=($q>0)?'dark-green':'marroon';
			$out.='<tr><td>'.ucwords($i).'</td><td>'.$booked[$i].'</td><td class="text-'.$cls.'">'.$q.'</td></tr>';
		}	
		$out.='</tbody></table>';
		return $out;	
	}
	private function renderGradeLimit(){
		$grades=$this->OPTIONS->get('grades');
		$args['name']='EventLimit';
		$args['value']=$this->get('EventLimit');
		if($args['value']) $args['value']=json_decode($args['value'],true);
		$d=array();
		foreach($grades as $i=>$v){
			if($v['OptionID']>0) $d[$v['OptionID']]=array('name'=>$v['OptionName']);
		}
		$opt=array(
			'multi'=>1,
			'filter'=>1,
			'name'=>$args['name'],
			'id'=>'ms'.$args['name'],
			'options'=>$d,
			'selected'=>issetCheck($args,'value',array())
		);
		$inp='<label>Limit this event to the selected grades</label><div id="'.$opt['id'].'"></div>';
		$js='<script>JQD.utils.multiSelector('.json_encode($opt).')</script>';
		return '<div>'.$inp.$js.'</div>';
		
	}
	private function renderProductLimit($cat_id=0,$alt=false){
		$prods=$this->OPTIONS->get('products');
		$args['name']='EventProductLimit'.$alt;
		$args['value']=$this->get('EventProductLimit'.$alt);
		$curr=$this->get('EventCurrency');
		if($args['value']) $args['value']=json_decode($args['value'],true);
		if(!$args['value']) $args['value']=array();
		$d=array();
		foreach($prods as $i=>$v){
			if($v['ItemGroup']==$cat_id && $v['ItemCategory']==3 && $v['ItemCurrency']==$curr) $d[$v['ItemID']]=array('name'=>$v['ItemTitle'].' / '.toPounds($v['ItemPrice'],$curr));
		}
		$opt=array(
			'multi'=>1,
			'filter'=>1,
			'name'=>$args['name'],
			'id'=>'ms'.$args['name'],
			'options'=>$d,
			'selected'=>$args['value']
		);
		$tmp=($alt)?'Secondary Product':'Limit Available Products on Form (leave empty for all)';
		$inp='<label style="margin:0;">'.$tmp.'</label><div id="'.$opt['id'].'"></div>';
		$js='<script>JQD.utils.multiSelector('.json_encode($opt).')</script>';
		return '<div style="margin: 0 0.8rem;">'.$inp.$js.'</div>';
	}	
	private function renderArrivalSelect(){
		$sel=(int)$this->get('EventArrivalDates');
		$opts=array(0=>'No',1=>'Yes');
		$options='';
		foreach($opts as $i=>$v){
			$selected=($sel==$i)?'selected':'';
			$options.='<option value="'.$i.'" '.$selected.'>'.$v.'</option>';
		}		
		$inp='<label >Include arrival & departure dates on the form?<select name="EventArrivalDates">'.$options.'</select></label>';
		return $inp;
	}
	private function renderShinsaSelect($cat_id=0){
		$prods=$this->OPTIONS->get('products');
		$args['name']='EventShinsa';
		$args['value']=$this->get('EventShinsa');
		$curr=$this->get('EventCurrency');
		if($args['value']) $args['value']=json_decode($args['value'],true);
		if(!$args['value']) $args['value']=[];
		$d=$sort=[];
		foreach($prods as $i=>$v){
			if($v['ItemGroup']==$cat_id && $v['ItemCategory']==4 && strpos($v['ItemSlug'],'shinsa-')===0){
				if($v['ItemCurrency']==$curr){
					$slug=str_replace('-eur','',$v['ItemSlug']);
					$shin=issetCheck($this->SHINSA_ORDER,$slug);
					$key=($shin)?(int)$shin:0;
					$sort['shinsa_'.$key]=array('id'=>$v['ItemID'],'name'=>$v['ItemTitle'].' / '.toPounds($v['ItemPrice'],$curr));
				}
			}
		}
		if($sort){
			ksort($sort);
			$d=$sort;
		}
		$opt=array(
			'multi'=>1,
			'filter'=>1,
			'name'=>$args['name'],
			'id'=>'ms'.$args['name'],
			'options'=>$d,
			'selected'=>$args['value']
		);
		$inp='<label style="margin:0;">Select shinsa for this event</label><div id="'.$opt['id'].'"></div>';
		$js='<script>JQD.utils.multiSelector('.json_encode($opt).')</script>';
		return '<div style="margin: 0 0.8rem;">'.$inp.$js.'</div>';
	}
	private function renderEventFormOptions($args=false){
		$this->initProducts();
		$e_type=$this->get('EventType');
		$end_date=$this->get('EventRegDate');
		$cat_id=(int)$this->get('EventProduct');
		$forms[0]=array('label'=>'No Form','value'=>0);
		$cats[0]=array('label'=>'No Product','value'=>0);
		foreach($this->CATEGORIES as $i=>$v) $cats[$i]=array('label'=>$v,'value'=>$i);
		foreach($this->FORM_DEFS as $i=>$v) $forms[$i]=array('label'=>$v,'value'=>$i);
		$form_id=$this->get('EventForm');
		$event_id=(int)$this->get('EventID');
		if(!$form_id || !array_key_exists($form_id,$this->FORM_DEFS)) $form_id='member_event';
		$content='';
		if(!$cat_id) $cat_id=$e_type;
		//select currency
		$currs=$this->OPTIONS->get('currency');
		$attr=array('title'=>'Select the currency for the items on this form');
		$tb=array('name'=>'EventCurrency','value'=>$this->get('EventCurrency'),'attr_ar'=>$attr,'options'=>$currs,'label'=>false);
		$chkboxB='<h5 class="text-gray">Currency</h5>'.$this->getSelect($tb);	
		
		//select product
		$attr=array('title'=>'Controls the product associated with this the form');
		$tb=array('name'=>'EventProduct','value'=>$cat_id,'attr_ar'=>$attr,'options'=>$cats,'label'=>'Product Group');
		$chkboxB.='<h5 class="text-gray">Products</h5>'.$this->getSelect($tb);	
		//product select
		$chkboxB.=$this->renderProductLimit($cat_id);
		
		//select form
		$attr=array('title'=>'Controls the fields that appear on the form','class'=>'input-group-field');
		$tb=array('name'=>'EventForm','value'=>$form_id,'attr_ar'=>$attr,'options'=>$forms,'label'=>'Form Definition');
		$chkboxA_but='<button class="button small expanded overLoad button-purple" data-ref="'.URL.'admin/events/preview_form/'.$event_id.'/'.$form_id.'"><i class="fi-eye"></i> Preview Form</button>';
		$chkboxA='<h5 class="text-gray">Form</h5><div class="input-group">'.$this->getSelect($tb,false).'<div class="input-group-button">'.$chkboxA_but.'</div></div>';
				
		//last reg date
		$fdate='<label>Registration End Date: <small class="text-dark-blue">no registrations on or after the date below</small><input name="EventRegDate" type="date" value="'.validDate($end_date).'"/></label>';		

		//Shinsa select
		$shinsa='<h5 class="text-gray">Shinsa</h5>'.$this->renderShinsaSelect($cat_id);
		
		//arrival and departure dates
		$arrivals='<h5 class="text-gray">Arrival/Departure</h5>'.$this->renderArrivalSelect($cat_id);
		
		//additonal products
		$chkboxD=$this->renderProductLimit($cat_id,2);

		$content.=$chkboxA.$fdate.$arrivals.$chkboxB.$chkboxD.$shinsa;			
		if($cat_id && $form_id){
			//display preview
			$event_id=(int)$this->get('EventID');
			$button='<button class="button button-purple overLoad" data-ref="'.URL.'admin/events/preview_form/'.$event_id.'/'.$form_id.'"><i class="fi-eye"></i> Preview the form</button>';
		}
		$inf='<p class="callout primary"><i class="fi-info"></i> Use the settings below to manage the form associated with this event.</p>';
		$table='<div>'.$inf.$content.'</div>';
		return $table;
	}
	public function renderEventContent($mode='admin'){
		$this->E_CONTENT->ADMIN=($mode==='admin')?true:false;
		$this->E_CONTENT->loadEvent($this->get('all'));
		$m=($mode==='admin')?msgHandler('<i class="fi-info"></i> Click the titles to edit the sections content. Disabled sections have a grey background.','primary',false):'';
		$content=$this->E_CONTENT->getContent();
		return $m.$content;
	}
	public function renderEventContent_edit($part=false){
		$this->E_CONTENT->ADMIN=true;
		$this->E_CONTENT->loadEvent($this->get('all'));
		$content=$this->E_CONTENT->editContent($part);
		return $content;
	}
	private function getSubmittedForms($keys=false){
		if(is_array($keys)){
			$r=$this->FORM_LOG_DB->select('ID,MemberID,EventLogID')->where('EventLogID',$keys);
			$o=renderResultsORM($r,'EventLogID');
		}
		if(!$o) $o=array();
		return $o;
	}
	private function renderEventReport($download=false){
		//for reports
		$res=$this->get('all');
		$trow=$row=$keys=[];
		$ct=0;
		if($res){
			$form_log_keys=array_keys($res['EventsLog']);
			$form_log=$this->getSubmittedForms($form_log_keys);
			foreach($res['EventsLog'] as $i=>$v){
				$has_form=issetCheck($form_log,$v['EventLogID']);
				$member=$res['Members'][$v['MemberID']];
				$this_dojo=issetCheck($this->DOJOS,$member['DojoID']);
				if(!$this_dojo) continue;
				$name=$member['FirstName'].' '.$member['LastName'];
				$_jname=$member['NameInJapanese2'].' '.$member['NameInJapanese'];
				$row[$i]=array(
					'id'=>$i,
					'Name'=>$name,
					'Dojo'=>$this->DOJOS[$member['DojoID']],
					'Grade'=>$member['CGradeName']
				);
				$_row=$sort=array();
				foreach($res['EventOptions'] as $set=>$rec){
					foreach($rec as $x=>$y){
						if((int)$y){
							$r=$this->EVENT_OPTIONS['basic'][$set][$x];
							$sortKey='sort_'.$x;
							$sort[$sortKey]=$y;
							switch($x){
								case 'age_at':
									$_row[$sortKey][$r['label']]=getAge($member['BirthDate'],$res['EventDate']);
									break;
								case 'age':
									$_row[$sortKey][$r['label']]=getAge($member['BirthDate']);
									break;
								case 'forms':
									if($has_form){
										$_row[$sortKey][$r['label']]='Yes';
									}else{
										$_row[$sortKey][$r['label']]='-';
									}
									break;
								case 'room':case 'Room':
									$tmp=$this->OPTIONS->get('room_types');
									$_row[$sortKey][$r['label']]=issetCheck($tmp,$v['Room'],'-');
									break;
								case 'attend':
									$tmp=$this->OPTIONS->get('attending');
									$_row[$sortKey][$r['label']]=issetCheck($tmp,$v['Attending'],'-');
									break;
								case 'payment':
									$tmp=$this->OPTIONS->get('yesno');
									$_row[$sortKey][$r['label']]=issetCheck($tmp,$v['Paid'],'-');
									break;
								case 'forms_sent':
									$_row[$sortKey][$r['label']]='forms sent';
									break;
								case 'jname':
									$jname=issetCheck($member,'NameInJapanese','');
									$_row[$sortKey][$r['label']]=$jname;
									break;
								case 'jname_fore':
									$jname=issetCheck($member,'NameInJapanese2','');
									$_row[$sortKey][$r['label']]=$jname;
									break;
								case 'jname_sur':
									$jname=issetCheck($member,'NameInJapanese','');
									$_row[$sortKey][$r['label']]=$jname;
									break;
								case 'ankfid':
									$_row[$sortKey][$r['label']]=$member['AnkfID'];
									break;
								case 'cost':
									$_row[$sortKey][$r['label']]=((int)$v['EventCost'])?toPounds($v['EventCost']):'-';
									break;
								case 'payment_amount':
									$_row[$sortKey][$r['label']]=((int)$v['PaymentAmount'])?toPounds($v['PaymentAmount']):'-';
									break;
								case 'balance':
									$test=((int)$v['EventCost']+(int)$v['PaymentAmount']);
									if($test==0){
										$val='-';
									}else{
										$test=((int)$v['EventCost']-(int)$v['PaymentAmount']);
										if($test==0)$cls='dark-green';
										if($test>0) $cls='maroon';
										if($test<0) $cls='blue';
										$val='<span class="text-'.$cls.'">'.toPounds($test).'</span>';
									}
									$_row[$sortKey][$r['label']]=$val;
									break;
							}
							$ct++;							
						}
					}
				}
				asort($sort);
				foreach($sort as $si=>$sv){
					$k=(isset($_row[$si]))?key($_row[$si]):false;
					if($k) $row[$i][$k]=$_row[$si][$k];
				}
				if(!$keys) $keys=array_keys($row[$i]);
				
				if(!$download){
					$_t='<td>'.implode('</td><td>',$row[$i]).'</td>';
					$trow[$i]='<tr>'.$_t.'</tr>';
				}
				$row[$i]['gradeSortkey']=$v['gradeSortkey'];
			}
		}
		$d=array(
			'rows'=>(($download)?$row:$trow),
			'head'=>(($download)?$keys:'<th class="{class_header}">'.implode('</th><th class="{class_header}">',$keys).'</th>')
		);
		$this->RESPONSE=array('status'=>200,'data'=>$d);		
	}
	private function renderEventDates($data=false){
		$res=($data)?$data:[];
		if(!$res){
			$res['EventDate']=$this->get('EventDate');
			$res['EventDuration']=$this->get('EventDuration');
		}
		$t1=strtotime($res['EventDate']);
		$t2=strtotime($res['EventDuration']);
		$sdate=date('d/m/Y',$t1).' @ '.date('H:i',$t1);
		$sdate=validDate($res['EventDate'],'d/m/Y');
		$edate='';
		if($t2>$t1){
			$edate=validDate($res['EventDuration'],'d/m/Y');
		}
		$out=array('start'=>$sdate,'end'=>$edate);
		$this->RESPONSE=array('status'=>200,'data'=>$out);
	}

//helpers
	private function getTickbox($args=[]){
		$attr_ar = array();
		$value='';
		$name='???';
		extract($args);
		$str ='<input type="checkbox"  name="'.$name.'" ';
        if ($attr_ar) {
            $str .= $this->addAttributes( $attr_ar );
        }        
        $str .= '/><label for="'.$attr_ar['id'].'"></label>';
        return '<div class="checkboxTick">'.$str.'</div>';
	}
 	private function getSelect($args=[],$wrap=true){
		$s_options='';
		$label=false;
		$attr_ar = array();
		$value='';
		$name='???';
		extract($args);
		foreach($options as $i=>$v){
			$sel=($value==$v['value'])?'selected':'';
			$s_options.='<option value="'.$v['value'].'" '.$sel.'>'.$v['label'].'</option>';
		}
		$str ='<select name="'.$name.'" ';
        if ($attr_ar) {
            $str .= $this->addAttributes( $attr_ar );
        }        
		if(!$label) $label=$attr_ar['title'];
        $str .= '>'.$s_options.'</select>';
        if($wrap){
			return '<label >'.$label.' '.$str.'</label>';
		}
		return $str;
	}
	private function getSelectOptions($key=false,$val=false,$str_start=''){
		$okey=($key==='EventProduct2')?'EventProduct':$key;
		$opt=issetCheck($this->SELECT_OPTIONS,$okey,[]);
		if(!$opt) preME([$key,$this->DATA],2);
		$out=(trim($str_start)!=='')?'<option>'.$str_start.'</option>':'';
		switch($key){
			case 'EventAddress':
				foreach($opt as $i=>$v){
					$sel=($i==$val)?' selected':'';
					$out.='<option value="'.$i.'"'.$sel.'>'.$v['LocationName'].'('.$v['LocationCountry'].')</option>';
				}
				break;
			case 'EventProduct':case 'EventProduct2':
				$curr=$this->get('EventCurrency');
				foreach($opt as $i=>$v){
					if($v['ItemCategory']==3 && $v['ItemCurrency']==$curr){
						$sel=($i==$val)?' selected':'';
						$out.='<option value="'.$i.'"'.$sel.'>'.$v['ItemTitle'].' / '.toPounds($v['ItemPrice'],$curr).'</option>';
					}
				}
				break;
			case 'EventPublic':case 'EventStatus':
				foreach($opt as $i=>$v){
					$sel=($i==$val)?' selected':'';
					$out.='<option value="'.$i.'"'.$sel.'>'.$v.'</option>';
				}
				break;
			case 'EventCurrency':
				foreach($opt as $i=>$v){
					$sel=($i==$val)?' selected':'';
					$out.='<option value="'.$i.'"'.$sel.'>'.$v['label'].'</option>';
				}
				break;
			default:
				foreach($opt as $i=>$v){
					if(!isset($v['OptionName'])) preME([$key,$opt],2);
					$sel=($i==$val)?' selected':'';
					$out.='<option value="'.$i.'"'.$sel.'>'.$v['OptionName'].'</option>';
				}
		}
		return $out;
	}
	
   private function addAttributes( $attr_ar ) {
        $str = '';
        // check minimized (boolean) attributes
        $min_atts = array('checked', 'disabled', 'readonly', 'multiple','required', 'autofocus', 'novalidate', 'formnovalidate'); // html5
        foreach( $attr_ar as $key=>$val ) {
            if ( in_array($key, $min_atts) ) {
                if ( !empty($val) ) { 
                    $str .= " $key=\"$key\"";
                }
            } else {
                $str .= " $key=\"$val\"";
            }
        }
        return $str;
    }	
}
