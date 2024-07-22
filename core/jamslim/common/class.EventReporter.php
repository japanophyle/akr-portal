<?php

class EventReporter{
	private $SLIM;
	private $CONTENT_PARTS;
	private $LANG;
	private $LANGS;
	private $EVENT_ID;
	private $EVENT_REC;
	private $EVENT_LOG;
	private $MEMBERS;
	private $RECENTS;
	private $FORM_LOG;
	private $PRODUCT_LOG;
	private $AJAX;
	private $ROUTE;
	private $TABLE;
	private $GRADES;
	private $ZASHA;
	private $USER;
	private $LOCATIONS;
	public $ADMIN;
	public $REPORT_DEFS;
	public $REPORT_ID='basic';
	public $REPORT_SORT='LastName';
	public $REPORT_ORDER='a';
	public $COUNTRY='Switzerland';
	public $OUTPUT;
	
	function __construct($slim){
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		$this->LANG=$slim->language->get('_LANG');
		$this->LANGS=$slim->language->get('_LANGS');
		$this->AJAX=$slim->AppVars->get('ajax');
		$this->ROUTE=$slim->AppVars->get('route');
		$this->ZASHA=$slim->options->get('zasha');
		$grades=$slim->options->get('grades');
		$this->GRADES=rekeyArray($grades,'OptionValue');
		
		$this->REPORT_DEFS['basic']=array(
			'name'=>'Basic Report',
			'map'=>array('MemberID'=>'MemberID','AnkfID'=>'Ankf ID','Dojo'=>'Dojo','LastName'=>'Surname','FirstName'=>'Forename','NameInJapanese2'=>'Surname<br/>(Japanese)','NameInJapanese'=>'Forename<br/>(Japanese)','Sex'=>'Gender','BirthDate'=>'DOB','CGradeName'=>'Grade','CGradedate'=>'Grade Date','zasha'=>'Form','recent_events'=>'Prev. Events')
		);
		$this->REPORT_DEFS['event']=array(
			'name'=>'Event Report',
			'map'=>array('MemberID'=>'MemberID','AnkfID'=>'Ankf ID','Dojo'=>'Dojo','age_at'=>'Age @','Attending'=>'Attending','Room'=>'Room','EventCost'=>'Cost','PaymentAmount'=>'Amount Paid','Balance'=>'Balance','LastName'=>'Surname','FirstName'=>'Forename','NameInJapanese2'=>'Surname<br/>(Japanese)','NameInJapanese'=>'Forename<br/>(Japanese)','Sex'=>'Gender','BirthDate'=>'DOB','CGradeName'=>'Grade','CGradedate'=>'Grade Date','zasha'=>'Form','recent_events'=>'Prev. Events')
		);
		$this->REPORT_DEFS['international_seminar']=array(
			'name'=>'International Seminar',
			'map'=>array('key'=>'key','AnkfID'=>'ID','Country'=>'Country','LastName'=>'Surname (alphabet)','FirstName'=>'First Name (alphabet)','NameInJapanese2'=>'Surname (Japanese)','NameInJapanese'=>'First Name (Japanese)','Sex'=>'Sex(M/F)','BirthDate'=>'Date of Birth(YYYY/M/D)','AgeAt'=>'Age at time of seminar',	'CGradeName'=>'Present Rank(Dan-i)','CGradedate'=>'Date Aquired (Rank)(YYYY/M/D)','Shogo'=>'Present Shogo','ShogoDate'=>'Date Aquired(Shogo)(YYYY/M/D)','Meeting'=>'Meeting (Yes/No)','Accommodation'=>'Accommodation Requested (Yes/No)','Nights'=>'Number of Nights','CheckIn'=>'Check-in Date (M/D)','recent_events'=>'Prev. Events')
		);
		$this->REPORT_DEFS['world_kyudo_taikai']=array(
			'name'=>'World Kyudo Taikai',
			'map'=>array('key'=>'key','AnkfID'=>'ID№','AHK'=>'Organisation Name','LastName'=>'Surname (roman alphabet)','FirstName'=>'First Name (roman alphabet)','skip_1'=>'Surname (Kanji/Chinese)','skip_2'=>'First Name (Kanji/Chinese)',	'NameInJapanese2'=>'Surname (Katakana)','NameInJapanese'=>'First Name (Katakana)','Sex'=>'Sex (M/F)','BirthDate'=>'Date of Birth (YYYY/M/D)','skip_3'=>'Age on April 2018','CGradeName'=>'Present Rank (Dan-i)','CGradedate'=>'Date Aquired (Rank) (YYYY/M/D)','Shogo'=>'Present Shogo','ShogoDate'=>'Date Aquired (Shogo) (YYYY/M/D)','Seminar (A/B)'=>'Seminar (A/B)','Seminar Fee'=>'Seminar Fee','skip_5'=>'Shinsa(Enter category code)','skip_6'=>'Shinsa Fee','skip_7'=>'Taikai - Individual (Yes/No)','skip_8'=>'Taikai Fee','skip_9'=>'Taikai Reception (Yes/No)','skip_10'=>'Reception Fee','skip_11'=>'ID Fee','skip_12'=>'Total Fees','recent_events'=>'Prev. Events')
		);		
	}
	
	private function setEvent(){
		$event=[];
		if($this->EVENT_ID){
			$EV= new event_item($this->SLIM);
			$EV->reset($this->EVENT_ID);
			$data=$EV->get('data');
			$this->MEMBERS=issetCheck($data,'Members',[]);
			$this->EVENT_LOG=issetCheck($data,'EventsLog',[]);
			$this->LOCATIONS=issetCheck($data,'Locations',[]);
			$this->RECENTS=issetCheck($data,'recent_member_events',[]);
			unset($data['Members'],$data['EventsLog'],$data['Locations']);
			$event=$data;
		}
		$this->REPORT_DEFS['world_kyudo_taikai']['map']['skip_3']='Age on '.date('M Y',strtotime($event['EventDate']));
		$this->EVENT_REC=$event;
		$this->setEventLog();
	}
	private function setEventLog(){
		$eventlog=[];
		if($this->EVENT_LOG){
			$eventlog=$this->EVENT_LOG;
			if($this->REPORT_SORT){
				//add member , location and recent events info
				foreach(array_keys($eventlog) as $i){
					$v=$eventlog[$i];
					$m=issetCheck($this->MEMBERS,$v['MemberID'],[]);					
					$v+=$m;
					$v['recent_events']=issetCheck($this->RECENTS,$v['MemberID'],'-');
					$v['Attending']=($v['Attending']==1)?'Yes':'No';
					$eventlog[$i]=$v;
				}
				$eventlog=sortArrayBy($eventlog, $this->REPORT_SORT,$this->REPORT_ORDER);
			}
		}
		$this->EVENT_LOG=$eventlog;
		$this->setFormLog();
	}	
	
	private function setFormLog(){
		$this->FORM_LOG=[];
		if($this->EVENT_LOG){
			$keys=array_keys($this->EVENT_LOG);
			$db=$this->SLIM->db->FormsLog();
			$recs=$db->where('EventLogID',$keys)->select('ID,MemberID,EventLogID,FormData');
			if(count($recs)>0){
				foreach($recs as $i=>$v){
					$this->FORM_LOG[$v['EventLogID']]=compress($v['FormData'],false);
				}
			}	
		}
	}	
	
	private function setVars($args=false){
		$this->EVENT_ID=(int)issetCheck($this->ROUTE,3);
		$this->REPORT_ID=issetCheck($this->ROUTE,4,'basic');
		$this->REPORT_SORT=issetCheck($this->ROUTE,5,$this->REPORT_SORT);
		$this->setEvent();
	}
	
	function render(){
		$this->setVars();
		$this->renderLog();
		return $this->OUTPUT;		
	}
	
	private function renderLog(){
		if($this->EVENT_LOG){
			$this->TABLE=array('header'=>false,'rows'=>false);
			$this->setTableHeaders();
			$this->setTableRows();
			$this->renderOutput();
		}else{
			$this->OUTPUT['content']=msgHandler('No members are registered for this event: <strong>'.$this->EVENT_REC['EventName'].'</strong>.',false,false);
		}
	}
	private function renderOutput(){
		$title='Event Report: <span class="subheader">'.$this->REPORT_DEFS[$this->REPORT_ID]['name'].'</span>';
		$controls='<button class="button button-navy gotoME" data-ref="'.URL.'events/editlog/'.$this->EVENT_ID.'/?list=event"><i class="fi-torso"></i> Manage Rollcall</button>';
		$controls.='<button class="button button gotoME" data-ref="'.URL.'events/edit/'.$this->EVENT_ID.'"><i class="fi-pencil"></i> Manage Event</button>';
		
		$args['headers']='<th>'.implode('</th><th>',array_values($this->TABLE['headers'])).'</th>';
		$args['rows']=implode('',$this->TABLE['rows']);
		$args['selector']=$this->renderSelector();
		$args['sorter']=$this->renderSorter();
		$args['controls']='';
		$tpl=file_get_contents(TEMPLATES.'app/app.report_grid.html');
		$table=replaceMe($args,$tpl);
		$this->OUTPUT['content']=$table;
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['title_controls']=$controls;
		$this->SLIM->assets->set('script','<script src="assets/js/admin/ui_mgrid.min.js"></script>','mgrid');
		$this->SLIM->assets->set('js','JQD.ext.initMGrid("report_mgrid");','init_mgrid');	
	}
	private function renderSelector(){
		$o='';
		foreach($this->REPORT_DEFS as $i=>$v){
			$s=($i===$this->REPORT_ID)?'selected':'';
			$o.='<option value="'.$i.'" '.$s.'>'.$v['name'].'</option>';
		}
		$this->SLIM->assets->set('js','$("#report_list").on("change",function(){var v=$(this).val(); var u="'.URL.'admin/events/report/'.$this->EVENT_ID.'/"+v; JQD.utils.setLocation(u);});','select_me');
		return '<div class="input-group"><div class="input-group-label">Reports</div><select class="input-group-field" id="report_list">'.$o.'</select></div>';
	}
	private function renderSorter(){
		$o='';
		$url=URL.'admin/events/report/'.$this->EVENT_ID.'/'.$this->REPORT_ID;		
		$sorts=array('LastName'=>'Surname','grade_sortkey'=>'Grade','BirthDate'=>'Age');
		foreach($sorts as $i=>$v){
			$s=($i===$this->REPORT_SORT)?'selected':'';
			$o.='<option value="'.$i.'" '.$s.'>'.$v.'</option>';
		}
		$this->SLIM->assets->set('js','$("#report_sort").on("change",function(){var v=$(this).val(); var u="'.$url.'/"+v; JQD.utils.setLocation(u);});','sort_me');
		return '<div class="input-group"><div class="input-group-label">Sort By</div><select class="input-group-field" id="report_sort">'.$o.'</select></div>';
	}
	private function setTableHeaders(){
		$map=issetCheck($this->REPORT_DEFS[$this->REPORT_ID],'map');
		if(!$map){
			$t=current($this->EVENT_LOG);
			foreach($t as $i=>$v){
				$map[$i]=camelTo($i);
			}
		}
		$this->TABLE['headers']=$map;		
	}
	
	private function setTableRows(){
		$order=array_keys($this->TABLE['headers']);
		$ct=1;
		$skip=array('id','EventLogID','EventID','sortkey_grade','GradeID');
		$rooms=$this->SLIM->options->get('room_types');
		foreach($this->EVENT_LOG as $i=>$v){
			$cell=[];
			$form=issetCheck($this->FORM_LOG,$i);
			if(isset($v['CurrentGrade'])){
				if($v['CurrentGrade']==9||$v['CurrentGrade']==7) $v=$this->getShogo($v);
			}
			foreach($order as $o){
				if(!in_array($o,$skip)){
					$has_data=array_key_exists($o,$v);
					if($o==='Balance'||$has_data){
						switch($o){
							case 'age_at':
								$cell[$o]=getAge($v['BirthDate'],$this->EVENT_REC['EventDate']);
								break;
							case 'zasha':
								$cell[$o]=($v[$o]=='')?'':$this->ZASHA[$v[$o]];
								break;
							case 'Country':
								if(!(int)$v[$o]){
									$cell[$o]=$this->COUNTRY;
								}else{
									$cell[$o]=$this->SLIM->options->get('location_name',$v[$o]);
								}
								break;
							case 'CGradedate': case 'ShogoDate': case 'BirthDate':
								if($this->REPORT_ID!=='basic'){
									$cell[$o]=validDate($v[$o],'Y-n-j');
								}else{
									$cell[$o]=validDate($v[$o]);
								}
								break;
							case 'Room':
								$cell[$o]=$rooms[$v[$o]];
								break;
							case 'PaymentAmount': case'EventCost':
								$cell[$o]=toPounds($v[$o]);
								break;
							case 'Balance':
								$test=((int)$v['EventCost'] - (int)$v['PaymentAmount']);
								if($test==0)$cls='dark-green';
								if($test>0) $cls='maroon';
								if($test<0) $cls='blue';
								$cell[$o]='<span class="text-'.$cls.'">'.toPounds($test).'</span>';
								break;
								break;
							default:
								$cell[$o]=$v[$o];
						}
					}else{
						
						switch($o){
							case 'key':	$cell[$o]=$ct; break;
							case 'AHK': case 'UKKA': $cell[$o]=$o; break;
							case 'age_at':
							case 'AgeAt':
							case 'skip_3':
								//fix skip_3 label
								$cell[$o]=getAge($v['BirthDate'],$this->EVENT_REC['EventDate']);
								break;
							case 'Seminar Fee'://'Seminar Fee' from form?
								$cell[$o]=$this->getFormVars($form,'seminar_fee');
								break;
							case 'skip_11'://ID Fee
								$cell[$o]=$this->getFormVars($form,'id_fee');
								break;
							case 'skip_6'://Shinsa Fee
								$cell[$o]=$this->getFormVars($form,'shinsa_fee');
								break;
							case 'skip_5'://Shinsa(Enter category code)
								$cell[$o]=$this->getFormVars($form,'shinsa');
								break;
							default: $cell[$o]='';
						}
					}
				}
			}
			if(!in_array($o,$skip) && $cell){
				if(!$this->TABLE['rows'])$this->TABLE['rows']=[];
				$this->TABLE['rows'][$i]='<tr><td>'.implode('</td><td>',$cell).'</td></tr>';
				$ct++;
			}
		}
	}
	private function getFormVars($form,$what){
		if(!$form) $form=array();
		switch($what){
			case 'shinsa':case'shinsa_fee':
				$val=(int)issetCheck($form,'shinsa');
				if($val){
					$prod=$this->getProduct($val);
					if($prod){
						if($what==='shinsa_fee'){
							return toPounds($prod['ItemPrice']);
						}else{
							return $prod['ItemTitle'];
						}
					}
				}
				break;
			case'seminar_fee':
				$val=(int)issetCheck($form,'product_ref');
				if($val){
					$prod=$this->getProduct($val);
					if($prod){
						return toPounds($prod['ItemPrice']);
					}
				}
				return toPounds(0);
				break;
			case'id_fee'://ikyf fee
				$val=(int)issetCheck($form,'AdditionalFee');
				if($val){
					$prod=$this->getProduct($val);
					if($prod && strpos($prod['ItemTitle'],'IKYF ID')!==false){
						return toPounds($prod['ItemPrice']);
					}
				}
				return toPounds(0);
				break;
		}
		return false;
	}
	private function getProduct($id=0){
		$prod=[];
		if($id){
			$prod=issetCheck($this->PRODUCT_LOG,$id);
			if(!$prod){
				$prod=$this->SLIM->Products->get('product','id',$id);
				$prod=$this->PRODUCT_LOG[$id]=current($prod);
			}
		}
		return $prod;
	}
	private function getShogo($rec){
		if($this->REPORT_ID!=='basic'){
			$gid=(int)$rec['CurrentGrade'];
			$prev=$this->SLIM->db->GradeLog()->where('MemberID',$rec['MemberID'])->and('GradeSet',($gid-1));
			$prev=renderResultsORM($prev);
			if(!empty($prev)){
				$prev=current($prev);
			}else{
				$prev['GradeDate']='???';
			}
			$grade=$this->GRADES[$gid];
			$tmp=explode('Dan',$grade['OptionName']);
			$rec['Shogo']=ucwords(trim($tmp[1]));
			$rec['ShogoDate']=($rec['Shogo']!=='')?validDate($rec['CGradedate']):'';
			$rec['Grade']=$tmp[0].'Dan';
			$rec['GradeDate']=validDate($prev['GradeDate']);
		}
		return $rec;
	}
	
}
