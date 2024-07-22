<?php

class slimForms{
	var $REQUIRED=array();//validate these fields
	var $DATA=false;
	var $TABLE=false;//table info array
	var $LABEL_WRAP=true;
	var $AJAX=false;
    var $ACTION='view';//current route action, controls output view 
    var $IS_NEW=false;//flag indicating a "new/add" or "update'" form
    var $_HIDDEN=false;
	private $DB;//the db object
	private $JFORM;//jamForm object;
	private $FORM=array();//array of rendered form inputs
	private $FIELDS=array();//db field info
	private $FIELD_GROUPS;//holds the field groups
	private $OPTIONS;//array of options
	private $obOPTIONS;//holds the optons object
	private $tag;
    private $xhtml;
    private $SLIM;
    private $EDITOR_CLASS='cl-edit';
    private $EDITORS=array('OtherInfo','EventNotes','LogDetail','Reason','Notes','NoteMessage','NoteResponse');//editoer fields
    private $TEXTAREAS=array('Address');//textarea fields - use database longtext types to avoid using this array
    private $HIDDEN=array('MemberID','MembersID','MembersLogID','TrailID','ID','AdminID','Processed');//hidden fields
    private $LABELS;//=array('Birthdate'=>'DOB','Disable'=>'Status','Dead'=>'Sleep','CGradeName'=>'Current Grade','Sex'=>'Gender','LocationDOJO'=>'Type','LocationCountry'=>'Country','DojoID'=>'Dojo');//alternative labels
    private $CUSTOM=array('Permissions'=>'renderEditPermissions','DojoLock'=>'renderDojoSelector','Details'=>'renderDetails','reply'=>'renderHelpReply','update_status'=>'renderHelpUpdateStatus','Notes'=>'renderNotes','EventOptions'=>'renderEventOptions','EventForm'=>'renderEventForm','EventContent'=>'renderEventContent','memberFees'=>'renderFeesLog','Product'=>'renderEventProducts');
    private $SELECT_NOHEAD=array('Disable');//stop header "please select" value for combos
    private $BASIC_NEW=array('members');
    private $BUTTONS;//array of form control buttons
    private $EVENTS_ITEM;//holds an EventItem Object
    private $SELECT_TEXTVAL=array('Sex');//select options which use the option text instead of the key 
    private $GROUP_DATA_MAP;
	private $USER;
	private $SETI;
	
	function __construct($slim=null,$xhtml = true){
		$this->SLIM=$slim;
		$this->xhtml = $xhtml;
		$this->obOPTIONS=$slim->Options;
		$this->LABELS=$this->obOPTIONS->get('alt_field_labels');
		$this->DB=$slim->ezpdo;//alias for PDO//new ezPDO($this->PDO,$this->obOPTIONS);
		$this->JFORM = new jamForm;
		$this->JFORM->EDITOR_CLASS=$this->EDITOR_CLASS;
	}
	
	function Process(){
		$this->setFields();
		if($this->FIELDS){
			foreach($this->FIELDS as $i=>$v){
				$inp=$this->renderFormPart($v);
				$this->FORM[$v['name']]=$inp;
			}
		}else{
			preME('no fields??',2);
		}
	}
    function get($what=false,$args=false){
		$output=false;
		switch($what){
			case 'fields':
				$output=$this->FIELDS;
				break;
			case 'alt_labels':
				$output=$this->LABELS;
				break;
			case 'grid_columns':
				$output=$this->gridColumns();
				break;
			case 'buttons':
				$output=$this->BUTTONS;
				if(!$output) $output=' ';
				break;
			default:
				if($what && $args){
					if(method_exists($this,$what)){
						$output=$this->JFORM->get($what,$args);
					}else{
						//nothing
					}
				}
		}
		if(!$output) {
			$output=':: invalid form element -> '.$what.' ::';
		}
		return $output;
	}
	
	function renderFormPart($v){
		$inp=false;
		$no_label=false;
		$required=false;
		switch($v['type']){
			case 'datetime':
				$ftype='date'; 
				$type='text';
				if($v['name']==='GradeDate') $required=true;
				break;
			default:
				$ftype='input'; 
				$type=($v['key']==='PRI')?'hidden':'text';
				if($type==='hidden') $no_label=true;
		}
		$args=array(
			'type'=>$type,
			'name'=>$v['name'],					
			'attr_ar'=>array(
				'placeholder'=>$v['label'],
				'value'=>($this->DATA)?issetCheck($this->DATA,$v['name']):false,					
			)
		);
		if($required) $args['attr_ar']['required']='required';


		//remove hashed password from view
		if($args['name']==='Password') $args['attr_ar']['value']='';
		
		if(in_array($args['name'],$this->HIDDEN)){
			if($this->TABLE['table']==='Users' && $args['name']==='MemberID'){
				//convert to select
				$ftype='Select';
				$args['option_list']=$v['options'];
			}else{
				$args['type']=$ftype='Hidden';
				$no_label=true;
			}
		}else{
			if(in_array($args['name'],$this->EDITORS)){
				$ftype='Editor';
				$args['value']=$args['attr_ar']['value'];
				$args['attr_ar']['id']='editor_'.$v['name'];
				unset($args['attr_ar']['value']);
				$no_label=true;
			}else if(in_array($args['name'],$this->TEXTAREAS)){
				$ftype='Textarea';
				$args['value']=$args['attr_ar']['value'];
				unset($args['attr_ar']['value']);
			}else if($v['options']){
				$ftype='Select';
				$args['option_list']=$v['options'];
				if(in_array($args['name'],$this->SELECT_TEXTVAL)) $args['textVal']=1;
			}
		}

		if($this->ACTION==='view') $ftype='Text';
		if($this->TABLE['table']==='Options'){
			$inp=$this->renderSettings($ftype,$args);
		}elseif($func=issetCheck($this->CUSTOM,$args['name'])){
			$inp=call_user_func_array(array($this,$func),array($ftype,$args));
		}else{
			if($this->LABEL_WRAP && !$no_label){
				$lbl=issetCheck($this->LABELS,$v['name']);
				if($lbl) $v['label']=$lbl;
				if($this->ACTION==='view'){
					//dates
					if(in_array($args['name'],array('EventDate','EventDuration','EventRegDate','Birthdate','DateJoined','CGradeDate'))){
						$val=validDate($args['attr_ar']['value']);
					}else{
						$sel=$args['attr_ar']['value'];
						$ary=issetCheck($args,'option_list');
						if($ary){
							$val=issetCheck($ary,$sel);
							if(is_array($val)){
								$val=issetCheck($val,'OptionName',$val['LocationName']);
							}
						}else{
							if(!$sel) $sel=$args['value'];
							$val=$sel;
						}
					}
				
					$inp='<tr><td class="text-dark-blue">'.$v['label'].'</td><td>'.$val.'</td</tr>';
				}else{
					$inp=$this->JFORM->get($ftype,$args);
					$wrp=array('label'=>$v['label'],'input'=>$inp);
					$inp=$this->JFORM->get('LabelWrap',$wrp);
					if($args['name']==='Room'){
					}
				}
			}else{
				$inp=$this->JFORM->get($ftype,$args);
			}
		}
		return $inp;		
	}
	
	function setFields(){
		if($this->TABLE && $this->DB){
			$this->FIELDS=$this->DB->getFields($this->TABLE['table']);
			$this->OPTIONS=$this->DB->FIELD_OPTIONS;
			$this->setExtraOptions($this->TABLE['table']);
			//add fields not in database without options
			switch($this->TABLE['table']){
				case 'UserRequests':
					$this->FIELDS['reply']=array(
						'name'=>'reply',
						'type'=>'editor',
						'label'=>'Reply',
						'options'=>false
					);
					$this->HIDDEN[]='Type';
					$this->HIDDEN[]='Ref';
					break;
				case 'EventsLog':
					$tmp=issetCheck($this->DATA['event'],'EventOptions');
					$basic=$this->obOPTIONS->get('event_options_basic');
					$def=$this->obOPTIONS->get('event_options_default');
					$tmp=eventOptionsMap($tmp,$basic,$def);
					if(is_array($tmp)){
						foreach($tmp['log'] as $i=>$v){
							if((int)$v)	$this->FIELDS[ucME($i)]=array(
								'name'=>ucME($i),
								'type'=>'int',
								'label'=>ucME($i),
								'options'=>false
							);
						}
					}
					break;
			}
			//add option to Fields
			foreach($this->OPTIONS as $i=>$v){
				if(issetCheck($this->FIELDS,$i)) $this->FIELDS[$i]['options']=$v;
				//add fields not in database with options
				switch($i){
					case 'set_as_active':// only for new records
						if($this->TABLE['table']==='GradeLog' && $this->DATA['GradeLogID']==0){
							$this->FIELDS[$i]=array(
								'name'=>$i,
								'type'=>'int',
								'label'=>ucME($i),
								'options'=>$v
							);
						}
						break;
					case 'update_status'://helpdesk
						$this->FIELDS[$i]=array(
							'name'=>$i,
							'type'=>'int',
							'label'=>'Status',
							'options'=>$v
						);
						break;					
				}
			}
			$this->setFieldGroup();
		}
	}
	
	function setFieldGroup(){
		$groups['members']=array(
			'Member'=>array('FirstName','LastName','Birthdate','Sex','DojoID','DateJoined','MemberTypeID','Disable'),
			'Contact'=>array('Address','Town','City','PostCode','Country','LandPhone','MobilePhone','Email'),
			'Fees'=>array('memberID'),
			'Grade'=>array('GradeSet','GradeDate','LocationID','OtherInfo','GradeLogID','MemberID'),
			'Log'=>array('MembersLogID','LogDate','LogSubject','LogDetail','MembersID'),
			'Events'=>array('EventID','EventLogID','EventName','EventType','EventDate','Attending'),
			'Other'=>array('NameInJapanese2','NameInJapanese','AnkfID','zasha')
		);
		$groups['grades']=array(
			'Details'=>array('GradeSet','GradeDate','LocationID','GradeLogID','MemberID','set_as_active'),
			'Notes'=>array('OtherInfo')
		);
		$groups['events']=array(
			'Details'=>array('EventName','EventType','EventDate','EventDuration','EventAddress','EventID','EventPublic','EventStatus'),
			'Notes'=>array('EventNotes'),
			'Rollcall'=>array('EventLogID','MemberID','Attending','Forms','FormsSent','Room'),
			'Options'=>array('EventOptions'),
			'Form'=>array('EventForm'),
			'Content'=>array('EventContent'),
		);
		if($this->ACTION==='view'){
			unset($groups['events']['Notes'],$groups['events']['Rollcall'],$groups['events']['Options'],$groups['events']['Form']);
		}
		$groups['feeslog']=array(
			'Details'=>array('ItemID','StartDate','EndDate','Status'),
		);
		$groups['memberslog']=array(
			'Details'=>array('LogDate','LogSubject','MembersID'),
			'Notes'=>array('LogDetail'),
		);
		$groups['eventslog']=array(
			'Details'=>array('EventLogID','MemberID'),
			'Notes'=>array('Reason')
		);
		if($this->TABLE['key']==='eventslog'){
			if(is_array($this->DATA['event']['_options'])){
				foreach($this->DATA['event']['_options']['log'] as $i=>$v){
					if((int)$v){//skip payment items
						if(!in_array($i,array('payment_amount','cost','payment','balance'))){
							$groups['eventslog']['Details'][]=ucME($i);
						}
					}
				}
				if(issetCheck($this->DATA['event'],'EventProductLimit2')){
					$groups['eventslog']['Details'][]='EventProductLimit2';
				}
				
			}
		}
		$groups['clubs']=array(
			'Details'=>array('ClubName','ShortName','LeaderID','Address','Country','PhoneNumber','Email','Website','LocationID','Status'),
			'Notes'=>array('Notes')
		);
		$groups['users']=array(
			'Details'=>array('Name','Username','Access','Status','Email','Password','MemberID'),
			'DojoLock'=>array('DojoLock'),
			'Permissions'=>array('Permissions'),
		);
		if($this->DATA['ID']>0){
			$groups['help']=array(
				'Request'=>array('UserID', 'Subject', 'Date','Time','Status'),
				'Details'=>array('Details'),
				'Log'=>array('Notes'),
				'Reply'=>array('update_status')
			);
			$this->HIDDEN[]='ID';
			$this->HIDDEN[]='Type';
			$this->HIDDEN[]='Ref';
		}else{
			$groups['help']=array(
				'Request'=>array('ID', 'UserID','AdminID', 'Date','Time','Type'),
				'Message'=>array('Subject','reply')
			);
			$this->HIDDEN[]='UserID';
		}
		$groups['helpdesk']=array(
			'Details'=>array('NoteID', 'NoteSubject', 'NoteUser', 'NoteOpened','NoteClosed', 'NoteStatus','NoteTicket'),
			'Message'=>array('NoteMessage'),
			'Reply'=>array('NoteResponse'),
		);
		$relations['members']=array('Log'=>'MembersLog','Grade'=>'GradeLog','Events'=>'EventsLog','Fees'=>'Subscriptions');	
		$relations['grades']=array('MemberID'=>'Members');
		$relations['events']=array('Rollcall'=>'EventsLog');
		$relations['memberslog']=array();
		$relations['users']=array();
		$relations['eventslog']=array('Attending'=>'Attend','Forms'=>'forms_received');
		$relations['clubs']=array();
		$relations['feeslog']=array();
		$this->FIELD_GROUPS=issetCheck($groups,$this->TABLE['key']);
		$this->GROUP_DATA_MAP=$relations;
	}
	
	function setExtraOptions($set,$return=false){
		//add extra options		
		$opts=false;
		switch($set){
			case 'Members':
				$opts=array('GradeSet'=>'grades','EventType'=>'events','EventAddress'=>'locations','LocationID'=>'locations','Disable'=>'disabled','DojoID'=>'dojos_country','Dead'=>'yesno','nonuk'=>'yesno','zasha'=>'zasha','MemberTypeID'=>'membertype');
				break;
			case 'MembersGrades': case 'Grades':
				$opts=array('GradeSet'=>'grades','LocationID'=>'locations','set_as_active'=>'yesno');
				break;
			case 'GradeLog':
				$opts=array('set_as_active'=>'yesno','LocationID'=>'locations');
				break;
			case 'Users':
				$opts=array('Access'=>'access_levels_name','Status'=>'active','MemberID'=>'active_members');
				break;
			case 'Locations':
				$opts=array('LocationDOJO'=>'location_type');
				break;
			case 'ClubInfo':
				$opts=array('LocationID'=>'dojos','LeaderID'=>'active_members','Status'=>'active');
				break;
			case 'UserRequests':
				$opts=array('AdminID'=>'users','UserID'=>'users','Status'=>'request_states','update_status'=>'request_states');
				break;
			case 'EventsLog':
				//add the options to $this->DATA temporaraly for usage later
				$tmp=issetCheck($this->DATA['event'],'EventOptions');
				$basic=$this->obOPTIONS->get('event_options_basic');
				$def=$this->obOPTIONS->get('event_options_default');
				$this->DATA['event']['_options']=eventOptionsMap($tmp,$basic,$def);
				// end add
				if(is_array($this->DATA['event']['_options'])){

					foreach($this->DATA['event']['_options']['log'] as $i=>$v){
						if((int)$v){
							switch($i){
								case 'room':
									$opts[ucME($i)]='room_types';
									break;
								case 'payment_amount': case 'cost':
									//skip - text
									$opts[ucME($i)]=false;
									break;
								case 'product':
									$opts['product']='products';
									break;
								default://yesno
									$opts[ucME($i)]='yesno';
							}
						}
					}
				}
				break;
		}
		if($opts){
			$trace=null;
			foreach($opts as $i=>$v){
				$trace[$i]=$this->obOPTIONS->get($v);
			}
			//rekey
			if($tmp=issetCheck($trace,'EventType')){
				$trace['EventType']=rekeyArray($tmp,'OptionID');
			}
			if($tmp=issetCheck($trace,'GradeSet')){
				$trace['GradeSet']=rekeyArray($tmp,'OptionValue');
			}
			if($return){
				return $trace;
			}else{
				foreach($trace as $i=>$v) $this->OPTIONS[$i]=$v;
			}
		}
	}
	
	function gridColumns(){
		$columns['members']=array('id','FirstName','LastName','Dojo','Sex','Birthdate','CGradeName','DateJoined','Disabled');
		$columns['grades']=array('id','MemberID','Location','Location2','GradeDate','GradeSet');
		$columns['membersgrades']=array('id','FirstName','LastName','Location','Location2','GradeDate','GradeSet');
		$cols=issetCheck($columns,$this->TABLE['key']);
		$_cols=false;
		if($cols){
			foreach($cols as $c){
				if($c=='id'){
					switch($this->TABLE['key']){
						case 'members':
							$_cols[$c]=issetCheck($this->FIELDS,'MemberID');
							$_cols[$c]['label']='ID';
							break;
						default:
							$_cols[$c]=array('name'=>'id','type'=>'int','label'=>'ID');								
					}
				}else{
					$_cols[$c]=issetCheck($this->FIELDS,$c);
				}
			}
		}
		return $_cols;		
	}
	
	function renderForm(){
		$this->FORM['hidden']=$this->_HIDDEN;
		if($this->FIELD_GROUPS){
			switch($this->ACTION){
				case 'editlog':
					return $this->renderLogManager();
					break;
				default:
					return $this->renderTabs();
			}
		}else{
			return '<div class="padding">'.implode("\n",$this->FORM).'</div>';
		}
	}
	
	private function renderLogManager(){
		//for managing logs - prmarily the events log
		$GMAP=$this->GROUP_DATA_MAP[$this->TABLE['key']];
		$group='Rollcall';
		$map=issetCheck($GMAP,$group);
		$fields=$this->FIELD_GROUPS[$group];
		$dsp['content']=$this->renderMiniTable($map,$group,$fields);
		$href=URL.'api/'.$this->TABLE['key'].'/';
		$dsp['controls']='<button class="button" data-ref="'.$href.'newlog/0" >Add Member</button>';
		$dsp['controls'].='<button class="button success submitME" data-ref="#form1">Update</button>';
		$dsp['title']='Edit '.$group;
		$dsp['card_class']='maxi';
		return renderCard($dsp);
	}
	private function renderTabs(){
		$nav=$panels='';
		$active='is-active';
		$GMAP=$this->GROUP_DATA_MAP[$this->TABLE['key']];
		$ct=0;
		foreach($this->FIELD_GROUPS as $group=>$fields){
			$nav.='<li class="tabs-title '.$active.'"><a href="#panel_'.$group.'" aria-selected="true">'.$group.'</a></li>';
			$tmp='';
			if($map=issetCheck($GMAP,$group)){
				if($group==='Fees'){
					$tmp=$this->renderSubsriptionsTable($map,$group,$fields);
				}else{
					$tmp=$this->renderMiniTable($map,$group,$fields);
				}				
			}else{
				foreach($fields as $f){
					$tmp.=issetCheck($this->FORM,$f);
				}
			}
			if($this->ACTION==='view') $tmp='<div class="tablescroll"><table>'.$tmp.'</table></div>';
			$panels.='<div class="tabs-panel '.$active.'" id="panel_'.$group.'">'.$tmp.'</div>';
			$active='';
			$ct++;
			//check for basic "new item" forms
			if($this->IS_NEW && $ct==1){
				if(in_array($this->TABLE['key'],$this->BASIC_NEW)){
					break;
				}
			}
		}
		$tabs='<ul class="tabs" data-tabs id="'.$this->TABLE['key'].'-tabs">'.$nav.'</ul><div class="tabs-content" data-tabs-content="'.$this->TABLE['key'].'-tabs">'.$panels.'</div>';
		$tabs.=$this->FORM['hidden'];
		if($this->AJAX) $tabs.='<script>jQuery("#'.$this->TABLE['key'].'-tabs").foundation();JQD.ext.initEditor(".modal-body .'.$this->EDITOR_CLASS.'");</script>';
		return $tabs;
	}
//custom form parts	
	private function renderSubsriptionsTable($tablename=false,$group=false,$fields=false){
		$ref=issetCheck($this->DATA,'MemberID',0);
		$new=($this->ACTION==='new')?'':'<button class="button small expanded gotoME" data-ref="'.URL.'subscriptions/subs_member/'.$ref.'">Click here to manage the records.</button>';
		$recs=$this->SLIM->Subscriptions->getRecords('member',$ref);
		$thead='<th>Item</th><th>Start</th><th>Expires</th><th>Status</th>';
		foreach($recs as $i=>$v){
			$state=($v['Status']==1)?'olive':'maroon';
			$row[]='<tr><td>'.$v['ItemName'].'</td><td>'.$v['StartDate'].'</td><td>'.$v['EndDate'].'</td><td><span class="text-'.$state.'">'.$v['StatusName'].'</span></td></tr>';
		}
		if($row){
			$out=$new.'<table class="minidatatable"><thead>'.$thead.'</thead><tbody>'.implode('',$row).'</tbody></table>';
		}else{
			$out=$new.msgHandler('No subscription records found...','primary',false);
		}
		return $out;		
	}
	function renderMiniTable($tablename=false,$group=false,$fields=false){
		$out=false;
		if($tablename){
			$row=false;
			$thead=array();
			if($tablename==='EventsLog'){//add fields from eventOptions
				if(!$this->EVENTS_ITEM){
					$this->EVENTS_ITEM=$EVI=new EventItem($this->obOPTIONS,$this->DATA);
				}else{
					$this->EVENTS_ITEM->Reset($this->DATA);
				}
				$field_parts=$this->EVENTS_ITEM->Render('logfields',$fields);
				$fields=$field_parts['data']['fields'];
			}
			//hack 
			if($xx=array_search('Prev. Events',$fields)){
				unset($fields[$xx]);
				$field_parts['data']['tpl']=str_replace(['Prev. Events','<br/>[::::]'],false,$field_parts['data']['tpl']);
			}
			$eventTypes=issetCheck($this->OPTIONS,'EventType');
			$grades=issetCheck($this->OPTIONS,'GradeSet');
			$attending=$this->obOPTIONS->get('yesno');
			$rooms=$this->obOPTIONS->get('room_types');
			$locations=issetCheck($this->OPTIONS,'LocationID');
			if(!$locations) $locations=issetCheck($this->OPTIONS,'EventAddress');
			$members=issetCheck($this->OPTIONS,'MemberID');
			if(!$members) $members=issetCheck($this->DATA,'Members');
			$href_shim=array('GradeLogID'=>'grades','MembersLogID'=>'memberslog','EventID'=>'events','EventLogID'=>'eventslog');
			if(issetCheck($this->DATA,$tablename)){
				$href='';
				$tpl=file_get_contents(APP.'templates/ng_mini_'.strtolower($group).'.html');
				if($tablename==='EventsLog'){// add optional fields to template
					$tpl=str_replace('[::fields::]',$field_parts['data']['tpl'],$tpl);
				}
				foreach($this->DATA[$tablename] as $i=>$v){
					$tmp=($tpl)?$tpl:'';
					foreach($fields as $x=>$y){
						if($tpl){
							switch($y){
								case 'NameInJapanese': case 'NameInJapanese2': case 'AnkfID':
									$mbr=$this->DATA['Members'][$v['MemberID']];
									$val=issetCheck($mbr,$y,'-');
									if($val==='') $val='-';
									break;
								case 'GradeDate': case 'LogDate': case 'EventDate':
									$val=validDate($v[$y]);
									if(!$val) $val='- no date -';
									break;
								case 'Birthdate_now':
								case 'Birthdate_then':
									$mbr=$this->DATA['Members'][$v['MemberID']];
									$val=validDate($mbr['BirthDate']);
									$tmp=str_replace('[::'.$y.'_state::]','text-dark-blue',$tmp);
									if(!$val){
										$val='- unknown -';
									}else{
										$date=($y==='Birthdate_then')?$this->DATA['EventDate']:false;
										$val=getAge($val,$date);
									}
									break;
								case 'GradeLogID': case 'MembersLogID':									
									$val=$v[$y];
									$href=URL.'api/'.$href_shim[$y].'/edit/'.$val;
									$y='rowID';
									break;
								case 'EventID':	
									$val=$v[$y];
									$href=URL.'api/'.$href_shim[$y].'/view/'.$val;
									$y='rowID';
									break;
								case 'EventLogID':	
									$val=$v[$y];
									if($group==='Events'){
										$href=URL.'api/events/view/'.$v['EventID'];
									}else{
										$href=URL.'api/'.$href_shim[$y].'/edit/'.$val;
									}
									$y='rowID';
									//add controls
									if($this->ACTION==='editlog'){
										$controls='<td><div class="button-group small">';
										$controls.='<button class="button secondary moveME" data-dir="up" title="move up">Up</button>';
										$controls.='<button class="button secondary moveME" data-dir="down" title="move down">down</button>';
										$controls.='<button class="button loadME" data-ref="'.$href.'">Edit</button>';
										$controls.='</div></td>';
										$tmp=str_replace('[::controls::]',$controls,$tmp);
									}else{
										$tmp=str_replace('[::controls::]','',$tmp);
									}
									break;
								case 'EventType':
									$val=issetCheck($eventTypes,$v[$y],'???');
									if(is_array($val)) $val=$val['OptionName'];
									break;
								case 'GradeSet':
									//fix mudan id
									$vy=$v[$y];
									if($vy<1) $vy=1;
									$val=issetCheck($grades,$vy,$vy);
									if(is_array($val)) $val=$val['OptionName'];
									break;
								case 'EventAddress':
								case 'LocationID':
									$vy=$v[$y];
									$val=issetCheck($locations,$vy,$vy);
									if(is_array($val)) $val=$val['LocationName'].', '.$val['LocationCountry'];
									break;
								case 'MemberID':
									$vy=$v[$y];
									$val=issetCheck($members,$vy,$vy);
									if(is_array($val)) {
										$val=$val['FirstName'].' '.$val['LastName'].'<br/><small>'.$val['Dojo'].' / '.$val['CGradeName'].'</small>';
									}									
									break;
								case 'LogDetail':
									$val=fixNL($v[$y]);
									break;
								case 'Attending':case 'Attend':
									$vy=issetCheck($attending,$v[$y],'-');
									$tmp=str_replace('[::'.$y.'_state::]',$vy,$tmp);
									$val=$vy;
									break;
								case 'Room':
									$vy=issetCheck($rooms,$v[$y],'-');
									$tmp=str_replace('[::'.$y.'_state::]',$vy,$tmp);
									$val=$vy;
									break;
								case 'Forms': case 'FormsSent':case 'Forms Sent': case 'Paid':
									$vy=$v[$y];
									$tmp=str_replace('[::'.$y.'_state::]',$vy,$tmp);
									if(is_numeric($vy)){
										$val=((int)$vy)?'Yes':'No';
									}else{
										$val=issetVar($vy,'-');
									}
									break;
								default:
									$val=($v[$y])?$v[$y]:'&nbsp;';
							}
							$tmp=str_replace('[::'.$y.'::]',$val,$tmp);
						}else{
							if(!issetCheck($thead,$x)) $thead[$x]='<th>'.camelTo($y).'</th>';
							$tmp.='<td>'.$v[$y].'</td>';
						}
					}
					$tmp=str_replace('[::href::]',$href,$tmp);
					$row[]=$tmp;
				}
			}
			$new='';
			switch($tablename){
				case 'GradeLog':case 'MembersLog':
					$page=($tablename==='GradeLog')?'grades':'memberslog';
					$ref=issetCheck($this->DATA,'MemberID',0);
					$new=($this->ACTION==='new')?'':'<button class="button small expanded overLoad" data-ref="'.URL.'api/'.$page.'/new/'.$ref.'">Click here to add a new record.</button>';
					break;
				case 'EventsLog':
					$ref=issetCheck($this->DATA,'EventID',0);
					$new=(!$ref||in_array($this->ACTION,array('new','editlog')))?'':'<button class="button small expanded gotoME" data-ref="'.URL.'events/editlog/'.$ref.'/?list=event">Click here to manage the Rollcall</button>';
					break;
			}
			if($row){
				$out=$new.'<table class="minidatatable"><thead>'.implode('',$thead).'</thead><tbody>'.implode('',$row).'</tbody></table>';
			}else{
				$out=$new.msgHandler('No '.camelTo($group).' records found...','primary',false);
			}
		}
		return $out;
	}
	private function renderEventProducts(){
		$pid=$this->DATA['ProductID'];
		$sid=$this->DATA['Shinsa'];
		$aid=$this->DATA['AdditionalFee'];
		$pid2=$this->DATA['ProductID2'];
		$ops='<option value="0" >None</option>';
		$opf=$opa=$op2=false;
		$opf_x=$opa_x=$opa_x=$op2_x=false;
		foreach($this->DATA['products'] as $i=>$v){
			if($v['ItemStatus']){
				$label=$v['ItemTitle'].' / '.toPounds($v['ItemPrice'],$v['ItemCurrency']);
				if($v['ItemCategory']==3){
					$sel=($pid==$v['ItemID'])?'selected':'';
					$ops.='<option value="'.$v['ItemID'].'" '.$sel.'>'.$label.'</option>';
					if($sel=='selected') $ops_x='<input type="hidden" name="ProductID_x" value="'.$v['ItemID'].'"/>';
				}else if($v['ItemCategory']==4){
					$sel=($sid==$v['ItemID'])?'selected':'';
					$opf.='<option value="'.$v['ItemID'].'" '.$sel.'>'.$label.'</option>';
					if($sel=='selected') $opf_x='<input type="hidden" name="Shinsa_x" value="'.$v['ItemID'].'"/>';
				}
			}
		}
		if($aid){
			$ads=$this->getAddionalProducts();
			//$opa='<option value="0" >None</option>';
			foreach($ads as $i=>$v){
				$label=$v['ItemTitle'].' / '.toPounds($v['ItemPrice'],$v['ItemCurrency']);
				$sel=($aid==$v['ItemID'])?'selected':'';
				$opa.='<option value="'.$v['ItemID'].'" '.$sel.'>'.$label.'</option>';
				if($sel=='selected') $opa_x='<input type="hidden" name="AdditionalFee_x" value="'.$v['ItemID'].'"/>';
			} 
		}
		if($pid2){
			$limit=json_decode($this->DATA['event']['EventProductLimit2'],true);
			foreach($this->DATA['products'] as $i=>$v){
				if(in_array($v['ItemID'],$limit)){
					if($v['ItemStatus']){
						$label=$v['ItemTitle'].' / '.toPounds($v['ItemPrice'],$v['ItemCurrency']);
						$sel=($v['ItemID']==$pid2)?'selected':'';
						$op2.='<option value="'.$v['ItemID'].'" '.$sel.'>'.$label.'</option>';
						if($sel=='selected') $op2_x='<input type="hidden" name="ProductID2_x" value="'.$v['ItemID'].'"/>';
					}
				}
			}
		}
		$out='<label>Product Sold<select name="ProductID">'.$ops.'</select></label>'.$ops_x;
		if($op2) $out.='<label>Secondary Product<select name="ProductID2"><option value="0" >None</option>'.$op2.'</select></label>'.$op2_x;
		if($opf) $out.='<label>Shinsa Fee<select name="Shinsa"><option value="0" >None</option>'.$opf.'</select></label>'.$opf_x;
		if($opa) $out.='<label>Additional Fee<select name="AdditionalFee"><option value="0" >None</option>'.$opa.'</select></label>'.$opa_x;
	
		return $out;
	}
	
	private function getAddionalProducts(){
		$PRD=$this->SLIM->Products;
		return $PRD->get('product','group',0);
	}	

	private function renderEditPermissions($ftype=false,$args=false){
		$args['access']=(int)issetCheck($this->DATA,'Access');
		return $this->SLIM->user_roles->get('edit_perms',$args);
	}
	private function renderEventOptions($ftype=false,$args=false){
		if($ftype==='Text') return false;
		if(!$this->EVENTS_ITEM){
			$this->EVENTS_ITEM=$EVI=new EventItem($this->obOPTIONS,$this->DATA);
		}else{
			$this->EVENTS_ITEM->Reset($this->DATA);
		}
		$chk=$this->EVENTS_ITEM->Render('options',$args);
		return $chk['data'];
	}
	private function renderEventForm($ftype=false,$args=false){
		if($ftype==='Text') return false;
		if(!$this->EVENTS_ITEM){
			$this->EVENTS_ITEM=$EVI=new EventItem($this->obOPTIONS,$this->DATA);
		}else{
			$this->EVENTS_ITEM->Reset($this->DATA);
		}
		$chk=$this->EVENTS_ITEM->Render('form_options',$args);
		return $chk['data'];
	}	
	private function renderEventContent($ftype=false,$args=false){
		$mode=($this->ACTION==='view')?'view':'admin';
		if(!$this->EVENTS_ITEM){
			$this->EVENTS_ITEM=$EVI=new EventItem($this->obOPTIONS,$this->DATA);
		}else{
			$this->EVENTS_ITEM->Reset($this->DATA);
		}
		$chk=$this->EVENTS_ITEM->Render('event_content',$mode);
		return $chk['data'];
	}	
	private function renderDetails($ftype=false,$args=false){
		$this->SLIM->helpdesk->set('current',$this->DATA);
		$response=$this->SLIM->helpdesk->get('view_details',$args);
		$this->BUTTONS[]= $response['buttons'];
		return $response['results'];
	}
	private function renderDojoSelector($ftype=false,$args=false){
		$dojos=$this->obOPTIONS->get('dojos_name');
		$d=array();
		foreach($dojos as $i=>$v){
			$d[$i]=array('name'=>$v);
		}
		$opt=array(
			'multi'=>1,
			'filter'=>1,
			'name'=>$args['name'],
			'id'=>'ms'.$args['name'],
			'options'=>$d,
			'selected'=>issetCheck($args['attr_ar'],'value',array())
		);
		$inp='<label>Limit this user to the selected dojo(s)</label><div id="'.$opt['id'].'"></div>';
		$js='<script>JQD.utils.multiSelector('.json_encode($opt).')</script>';
		return $inp.$js;
		
	}
    
    private function renderHelpReply($ftype=false,$args=false){
		$args['attr_ar']['id']=$args['name'];
		return $this->JFORM->get('Editor',$args);
	}
    private function renderHelpUpdateStatus($ftype=false,$args=false){
		$args['attr_ar']['value']=$this->DATA['Status'];
		if($this->USER['access']<25){
			if($this->DATA['Status']>3){
				$out=msgHandler('Sorry, this request has been closed or cancelled and can no longer be updated.','warning',false);
			}else{
				unset($args['option_list'][1],$args['option_list'][2],$args['option_list'][4]);
				if($this->DATA['Status']>0){
					unset($args['option_list'][0]);
					$args['option_list'][3]='New Note';
					$args['attr_ar']['value']=3;
				}
				$out='<label>Set the request status'.$this->JFORM->get('Select',$args).'</label>';
				$args=$this->FIELDS['reply'];
				$args['attr_ar']['id']=$args['name'];
				$out.=$this->JFORM->get('Editor',$args);
				$out.='<input type="hidden" name="action" value="respond"/>';
				$out='<form id="help_response" class="ajaxForm" method="post" action="'.URL.'api/help/respond/'.$this->DATA['ID'].'">'.$out.'</form>';
			}
		}
		return $out;
	}
	private function renderNotes($ftype=false,$args=false){
		//render comments for the helpdesk
		$fill=function($o){
			$t='<div class="comment"><div class="comment-header bg-{type_color} text-gray expanded"><i class="fi-comment text-white"></i> <span class="text-green">{date} / {user}</div><div class="comment-body">{note}</div></div>';
			foreach($o as $i=>$v)$t=str_replace('{'.$i.'}',$v,$t);
			return $t;
		};
		$out='';
		$users=$this->obOPTIONS->get('users');
		if(is_array($args['value'])){
			$type_colors=array('admin'=>'black','user'=>'dark-blue','super'=>'maroon');
			$test=array_reverse($args['value'],true); 
			foreach($test as $i=>$v){
				$v['type_color']=$type_colors[$v['type']];
				$v['date']=date('Y-m-d H:i',$i);
				$v['user']=$users[$v['user']]['Name'];
				$out.=$fill($v);
			}
		}else{
			$o['type_color']='dark-blue';
			$o['date']='-';
			$o['user']=$users[$this->DATA['UserID']]['Name'];
			$o['note']=issetCheck($args,'value');
			if($o['note']=='')$o['note']='- no notes -' ;
			$out=$fill($o);
		}
		return $out;
	}
	
	private function renderSettings($ftype=false,$args=false){
		if(!$this->SETI){
			if($grp=issetCheck($this->DATA,'OptionGroup')){
				if($grp==='super'){
					$this->SETI=new dev_options($this->obOPTIONS->getORM(),false,$this->AJAX,$this->JFORM);
					$this->SETI->setData($this->DATA);
					$this->SETI->setAssets($this->obOPTIONS->get('asset_source','all'));
				}else{
					$this->SETI=new SettingsItems($this->obOPTIONS,$this->DATA,$this->JFORM);
				}
			}else if($list=$this->SLIM->AppVars->get('get','list')){
				if($this->DATA['id']==0){
					$this->DATA['OptionGroup']=$list;
					$ftype='new';
				}
				$this->SETI=new SettingsItems($this->obOPTIONS,$this->DATA,$this->JFORM);
			}
		}
		if($this->SETI){
			$res=$this->SETI->Render($ftype,$args);
			return $res['data'];
		}
		return false;
	}
	
}
