<?php

class slimOptions{
	var $OPTIONS=[];
	var $RESULTS;
	var $DOJO_LOCK=[];//holds club ids for locking the results
	var $RESULTS_FORMAT;//json,array,default
	var $DB;
	private $SORT_KEYS;
	private $SLIM;
	function __construct($slim=null){
		$this->SLIM=$slim;
		$this->DB= $slim->db;
		$this->SORT_KEYS=array(
			'locations_all'=>'LocationName ASC',
			'locations'=>'LocationName ASC',
			'dojos'=>'LocationName ASC',
			'grades'=>'sortkey DESC',
			'events'=>'OptionName ASC',
			'membertype'=>'OptionName ASC',
			'reasons'=>'OptionName ASC',
		);
		$this->DOJO_LOCK=issetCheck($this->SLIM->user,'dojo_lock',array());
		if($this->DOJO_LOCK==='all') $this->DOJO_LOCK=array();
	}
	public function get($what=false,$args=false){
		switch($what){
			case 'site':
				return $this->getSiteOptions($args);
				break;
			case 'event_color':
				return $this->getEventColor($args);
				break;
			case 'help':
				return $this->getHelpBot($args);
				break;
			case 'field_options':
				return $this->getFieldOptions($args);
				break;
			case 'asset_source':
				return $this->getAssetSource($args);
				break;
			case 'member_info': case 'member_user':
				return $this->getOptions($what,$args);
				break;
			case 'shinsa_ref':case 'teaching_grades':
				return $this->getShinsaRefInfo($args);
				break;
			case 'user':
				return $this->SLIM->user;
				break;
			case 'currencies':
				return [0=>'USD',1=>'USD',2=>'EUR',3=>'YEN'];
				break;
			default:
				if(!issetCheck($this->OPTIONS,$what)){//check options cache
					$this->getOptions($what,$args);
				}
				if(issetCheck($this->OPTIONS,$what)){
					if($what==='grade_sort' && issetCheck($args,'ins')){
						$this->getOptions($what,$args);
						$o=$this->OPTIONS[$what];
					}else{
						$o=$this->OPTIONS[$what];
						if($args){
							if(is_array($args)){
								switch($what){
									//add if $what uses an array
								}							
							}else{
								if($what==='super' && $args=='all') return $o;
								if($what==='grade_by_value') return current($o);
								return issetCheck($o,$args);
							}
						}
					}
					return $o;
				}
		}
		return false;
	}
	
	public function sendMail($email=false){
		return $this->SLIM->Mailer->Process($email);
	}
	
	private function getOptions($what=false,$vars=false){
		$lock=0;//1==locationID,2=DojoID,3=ClubID
		$this->RESULTS=null;
		switch($what){
			case 'public_footer_text':case 'public_footer_Logo':
				$rec=$this->DB->Options->where('OptionName',$what);
				if($rec=renderResultsORM($rec,'id')){
					$rec=current($rec);
					$this->OPTIONS[$what]=$rec;
				}
				return;
				break;			
			case 'site':
				$d=[];
				$rec=$this->DB->Options->where('OptionGroup','site');
				if(count($rec)){
					$d=renderResultsORM($rec,'id');
				}
				$this->OPTIONS[$what]=$d;
				return;
			case 'sitename':
				$d=[];
				$rec=$this->DB->Options->where(['OptionGroup'=>'site','OptionName'=>'name']);
				if(count($rec)){
					$rec=renderResultsORM($rec,'id');
					if($rec){
						$rec=current($rec);	
						$d=$rec['OptionValue'];
					}
				}
				$this->OPTIONS[$what]=$d;
				return;
			case 'main_menu':
				$d=[];
				$rec=$this->DB->Options->select('id,OptionValue')->where('OptionName','main_menu');
				if(count($rec)){
					$rec=renderResultsORM($rec);
					$rec=current($rec);	
					$d=compress($rec['OptionValue'],false);
				}
				$this->OPTIONS[$what]=$d;
				return;
			case 'page_states':
				$this->OPTIONS[$what]=array('published'=>'dark-green','draft'=>'red-orange','disabled'=>'maroon');
				return;
				break;
			case 'currency':
				$this->OPTIONS[$what]=array(1=>array('value'=>1,'label'=>'USD'),2=>array('value'=>2,'label'=>'EUR'),3=>array('value'=>3,'label'=>'YEN'));
				return;
				break;
			case 'super'://super admin options
				$this->RESULTS=$this->DB->Options()->select('id','OptionName','OptionValue')->where('OptionGroup','super');
				$t=$this->renderResults($what);
				foreach($t as $i=>$v)$this->OPTIONS[$what][$v['OptionName']]=$v;
				return;
				break;
			case 'admin_email':
				$res=$this->DB->Options()->select('id','OptionName','OptionValue')->where('OptionName','email_administrator');
				$t=renderResultsORM($res);
				$email=false;
				if($t){
					$t=current($t);
					$email=$t['OptionValue'];					
				}
				$this->OPTIONS[$what]=$email;
				return;
				break;
			case 'mailbot':
				$res=$this->DB->Options()->select('id','OptionName','OptionValue')->where('OptionName','email_mailbot');
				$t=renderResultsORM($res);
				$email=false;
				if($t){
					$t=current($t);
					$email=$t['OptionValue'];					
				}
				$this->OPTIONS[$what]=$email;
				return;
				break;
			case 'bank_details':
				$res=$this->DB->Options()->select('id','OptionName','OptionValue')->where('OptionName','bank_details');
				$t=renderResultsORM($res);
				$email=false;
				if($t){
					$t=current($t);
					$email=$t['OptionValue'];					
				}
				$this->OPTIONS[$what]=$email;
				return;
				break;
			case 'application'://admin options
				$this->RESULTS=$this->DB->Options()->select('id','OptionName','OptionValue')->where('OptionGroup','application');
				$t=$this->renderResults($what);
				foreach($t as $i=>$v)$this->OPTIONS[$what][$v['OptionName']]=$v;
				return;
				break;
			case 'app_vars':
				$this->OPTIONS[$what]=$this->getAppVars($vars);
				return;
				break;
			case 'location_type':
				$this->OPTIONS[$what]=array(0=>'Event Location',1=>'Dojo');
				return;
				break;
			case 'locations_all':
				$this->RESULTS=$this->DB->Locations()->select("LocationID AS id, LocationID, LocationName, LocationCountry");
				$lock=0;
				break;
			case 'locations':
				$this->RESULTS=$this->DB->Locations()->select("LocationID AS id, LocationID, LocationName, LocationCountry")->where('LocationDojo',0);
				break;
			case 'dojos':
			case 'dojos_name':
			case 'dojos_country':
				$lock=1;
				$this->RESULTS=$this->DB->ClubInfo()->select("ClubID AS id, ClubID, LocationID, ClubName as LocationName, Country as LocationCountry, ShortName");
				$t=[];
				if($what!=='dojos'){
					if($this->DOJO_LOCK) $this->RESULTS->and('ClubID',$this->DOJO_LOCK);
					foreach($this->RESULTS as $i=>$v){
						$t[$v['ClubID']]=$v['ShortName'];
						if($what==='dojos_country') $t[$v['ClubID']].=', '.$v['LocationCountry'];
					}
					$this->OPTIONS[$what]=$t;
				}else{
					if($this->DOJO_LOCK) $this->RESULTS->and('ClubID',$this->DOJO_LOCK);
					foreach($this->RESULTS as $i=>$v){
						$t[$v['ClubID']]=[
							'id'=>$v['ClubID'],
							'ClubID'=>$v['ClubID'],
							'LocationID'=>$v['LocationID'],
							'LocationName'=>$v['LocationName'],
							'LocationCountry'=>$v['LocationCountry'],
							'ShortName'=>$v['ShortName'],					
						];
					}
					$this->OPTIONS[$what]=$t;
				}
				return;
				break;
			case 'location_name':
				$lock=0;
				$vars=(int)$vars;
				$this->RESULTS=$this->DB->Locations()->select("LocationName")->where('LocationID',$vars);
				if(count($this->RESULTS)>0){
					foreach($this->RESULTS as $i=>$v){
						$this->OPTIONS[$what][$vars]=$v['LocationName'];
					}
				}
				return;
				break;
			case 'clubs':
			case 'clubs_all':
			case 'clubs_name':
				$lock=1;
				$sel=($what==='clubs_name')?'ClubID AS id,ClubName,ShortName, LocationID':'ClubID AS id, ClubID, LocationID,ClubName, ShortName, Country, Status';
				$this->RESULTS=$this->DB->ClubInfo()->select($sel);
				if($what!=='clubs_all')	$this->RESULTS->where('Status',1);
				$this->OPTIONS[$what]=renderResultsORM($this->RESULTS,'id');
				return;
				break;
			case 'grades':
			case 'grades_alt':
			case 'grades_val':
				if($what==='grades_val'){
					$key='OptionValue';
				}else{
					$key=($what==='grades_alt')?'OptionID':'id';
				}
				$r=$this->DB->Options()->select("id, OptionID, OptionName, OptionValue, CAST(OptionValue as UNSIGNED) AS sortkey")->where('OptionGroup','grade');
				$this->OPTIONS[$what]=renderResultsORM($r,$key);
				return;
				break;
			case 'grade_name':
				$r=$this->DB->Options()->select("OptionName")->where('OptionID',(int)$vars);
				return renderResultsORM($r);
				break;
			case 'grade_by_value':
				$this->RESULTS=$this->DB->Options()->select("id, OptionID, OptionName, OptionValue")->where('OptionGroup','grade')->and('OptionValue',(int)$vars);
				break;
			case 'active_members':
				$this->RESULTS=$this->DB->Members()->select("MemberID as id, MemberID, CONCAT(FirstName,' ',LastName ,' : ',CGradeName,' : ',Dojo) AS Name")->where('Disable',0)->and('Dead',0)->and('nonuk',0)->and('NOT Dojo',null)->order('FirstName,LastName');
				$lock=2;
				break;
			case 'all_members_select':
				$this->RESULTS=$this->DB->Members()->select("MemberID as id, MemberID, CONCAT(FirstName,' ',LastName ,' : ',CGradeName,' : ',Dojo) AS Name")->order('FirstName,LastName');
				$lock=2;
				break;
			case 'member':
				$this->RESULTS=$this->DB->Members()->select("MemberID, CONCAT(FirstName,' ',LastName) AS Name, CGradeName,Dojo,DojoID")->where('MemberID',(int)$vars);
				$lock=1;
				$r=$this->renderResults();
				return current($r);
				break;
			case 'member_info':
				$this->RESULTS=$this->DB->Members()->select("MemberID, CONCAT(FirstName,' ',LastName) AS Name, CGradeName,Dojo,Email,Disable as Status, MemberTypeID")->where('MemberID',(int)$vars);
				$r=$this->renderResults();
				return current($r);
				break;
			case 'member_user':
				$this->RESULTS=$this->DB->Users()->select("id, Username, Access,Status,DojoLock,Permissions")->where('MemberID',(int)$vars);
				$r=$this->renderResults();
				return current($r);
				break;
			case 'dojo_count_active':
				$this->RESULTS=$this->DB->Members()->select("Dojo,COUNT(MemberID) as Members,DojoID")->where('Disable',0)->and('Dead',0)->and('nonuk',0)->group('DojoID')->order('Dojo');
				$lock=2;
				break;
			case 'metrics_member_count':
				$this->RESULTS=$this->DB->Members()->select("Disable,COUNT(MemberID) as Members")->where('MemberTypeID < ?',6)->and('Email != ?','ann.user@home.com')->group('Disable')->order('Disable');
				$lock=2;
				break;
			case 'metrics_grade_count':
				$lock=2;
				$this->RESULTS=$this->DB->Members()->select("CGradeName,COUNT(CurrentGrade) as Grades,CurrentGrade")->where('Disable',0)->and('Dead',0)->and('nonuk',0)->group('CurrentGrade')->order('CurrentGrade DESC');
				break;
			case 'metrics_gender_count':
				$lock=2;
				$this->RESULTS=$this->DB->Members()->select("Sex,COUNT(*) as Genders")->where('Disable',0)->and('nonuk',0)->group('Sex')->and('Dead',0);
				break;
			case 'metrics_helpdesk_status_count':
				$this->RESULTS=$this->DB->UserRequests()->select("Status as item,COUNT(*) as count");
				if($this->SLIM->user['access']<25) $this->RESULTS->where('UserID',$this->SLIM->user['id']);
				$this->RESULTS->group('Status')->order('Status');
				break;
			case 'metrics_helpdesk_type_count':
				$this->RESULTS=$this->DB->UserRequests()->select("Type as item,COUNT(*) as count");
				if($this->SLIM->user['access']<25) $this->RESULTS->where('UserID',$this->SLIM->user['id']);
				$this->RESULTS->group('Type')->order('Type');
				break;
			case 'metrics_helpdesk_admin_count':
				$this->RESULTS=$this->DB->UserRequests()->select("AdminID as item,COUNT(*) as count");
				if($this->SLIM->user['access']<25) $this->RESULTS->where('UserID',$this->SLIM->user['id']);
				$this->RESULTS->group('AdminID')->order('AdminID');
				break;
			case 'events':
				$this->RESULTS=$this->DB->Options()->select("id, OptionID, OptionName, OptionValue")->where('OptionGroup','eventType');
				$t=$this->renderResults($what);	
				$this->OPTIONS[$what]=rekeyArray($t,'OptionID');
				return;
				break;
			case 'events_info':
				$this->RESULTS=$this->DB->Events()->select("EventID, EventName, EventDate, EventType, EventProduct");
				if(is_numeric($vars)){
					$this->RESULTS->where('EventID',$vars);					
					$t=renderResultsORM($this->RESULTS);
					if($t) $this->OPTIONS[$what][$vars]=current($t);
				}else{
					$t=$this->renderResults($what);	
					$this->OPTIONS[$what]=rekeyArray($t,'EventID');
				}
				return;
				break;
			case 'event_options_basic':
				$this->OPTIONS[$what]=$this->getBasicEventOptions();
				return;
				break;
			case 'event_options_default':
				$this->OPTIONS[$what]=$this->getDefaultEventOptions();
				return;
				break;
			case 'membertype':
				$tmp=$this->DB->Options->select("id, OptionID, OptionName, OptionValue")->where('OptionGroup','memberType')->Order('OptionID');
				$t=renderResultsORM($tmp,'OptionID');
				if($t) $this->OPTIONS[$what]=$t;
				return;
				break;
			case 'reasons':
				$this->RESULTS=$this->DB->Options()->select("id, OptionID, OptionName")->where('OptionGroup','reasons');
				break;
			case 'users':
				$this->RESULTS=$this->DB->Users()->select("id, Username,Name,Access,Status,Email");
				break;
			case 'note_states':
				$this->OPTIONS[$what]=array(0=>'New',1=>'Processing',2=>'Waiting',3=>'Ticket',10=>'Closed');
				return;
			case 'request_states':
				$this->OPTIONS[$what]=array(0=>'New',1=>'Processing',2=>'Admin Waiting',3=>'User Waiting',4=>'Cancelled',50=>'Closed');
				return;
				break;
			case 'request_colors':
				$this->OPTIONS[$what]=array(0=>'red',1=>'green',2=>'orange',3=>'marroon',4=>'gray',50=>'black');
				return;
				break;
			case 'sex':
			case 'gender':
				$this->OPTIONS[$what]=array('Male','Female','Trans','Nonbinary','Other');
				return;
				break;
			case 'active':
				$this->OPTIONS[$what]=array(0=>'Inactive',1=>'Active');
				return;
				break;
			case 'disabled':
				$this->OPTIONS[$what]=array(0=>'Active',1=>'Inactive',2=>'Pending');
				return;
				break;
			case 'offon':
				$this->OPTIONS[$what]=array(0=>'Off',1=>'On');
				return;
			case 'onoff':
				$this->OPTIONS[$what]=array(0=>'Yes',1=>'No');
				return;
				break;
			case 'yesno':
				$this->OPTIONS[$what]=array(0=>'No',1=>'Yes');
				return;
			case 'noyes':
				$this->OPTIONS[$what]=array(0=>'Yes',1=>'No');
				return;
				break;
			case 'forms_received':
				$this->OPTIONS[$what]=array(0=>'No',1=>'Yes');//array(0=>'Pending',1=>'Received');
				return;
				break;
			case 'attending':
				$this->OPTIONS[$what]=array(0=>'No',1=>'Yes');//array(0=>'Absent',1=>'Present');
				return;
				break;
			case 'room_types':
				$this->OPTIONS[$what]=array(0=>'None',1=>'Single',2=>'Double',3=>'Single (en suite)',4=>'Double (en suite)');//array(0=>'Absent',1=>'Present');
				return;
				break;
			case 'user_access':
				$this->OPTIONS[$what]=$this->SLIM->user['access'];
				return;
				break;
			case 'payment_method':
				$this->RESULTS=$this->DB->Options()->select("id, OptionID, OptionName")->where('OptionGroup','payment_method');
				$res=$this->renderResults();
				$tmp=[];
				foreach($res as $i=>$v)	$tmp[$v['OptionID']]=$v['OptionName'];
				$this->OPTIONS[$what]=$tmp;
				return;
				break;
			case 'product_types':// product group
				$this->RESULTS=$this->DB->Options()->select("id, OptionID, OptionValue")->where('OptionGroup','productType');
				$res=$this->renderResults();
				$tmp=[0=>'Single Item / Misc.'];
				foreach($res as $i=>$v)	$tmp[$v['OptionID']]=$v['OptionValue'];
				$this->OPTIONS[$what]=$tmp;
				return;
				break;
			case 'product_categories':// product group	
				$this->OPTIONS[$what]=array(0=>'No Category',1=>'Product',2=>'Membership',3=>'Event',4=>'Fee',5=>'Other');
				return;
			case 'products':
				$prods=$this->DB->Items()->where('ItemType','product')->order('ItemTitle');
				$prods=renderResultsORM($prods,'ItemID');
				$this->OPTIONS[$what]=$prods;
				return;
			case 'access_levels':
			case 'access_levels_name':
				$t=issetcheck($this->SLIM->config,'USER_ACCESS');
				if($what==='access_levels_name'){
					foreach($t as $i=>$v) $n[$i]=$v['label'];
					$t=$n;
				}
				$this->OPTIONS[$what]=$t;
				return;
				break;
			case 'db_tables':
				$this->OPTIONS[$what]=$this->SLIM->settings['DB_TABLES'];
				return;
				break;
			case 'grade_sort':
				$this->OPTIONS[$what]=$this->realGradeSort($vars);
				return;
				break;
			case 'alt_field_labels'://alternative field lables
				$this->OPTIONS[$what]=$this->getAltLabels();
				return;
				break;
			case 'zasha':
				$this->OPTIONS[$what]=array(0=>'Zasha',1=>'Rissha');
				return;
				break;
			case 'languages':
				$this->OPTIONS[$what]=array('en'=>'English','fr'=>'French','de'=>'German');
				return;
				break;
			case 'departs':
				$this->OPTIONS[$what]=array('Freitag'=>'Friday','Samstag'=>'Saturday','Sonntag'=>'Sunday');
				return;
				break;
			case 'form_defs':
				$this->OPTIONS[$what]=$this->SLIM->EventForms->PATTERN_INFO;
				return;
				break;
			case 'pdf_logo'://for FPDF
				$protocol='https:';
				$logo=$this->SLIM->EmailParts['logo'];
				$this->OPTIONS[$what]=$logo;
				return;
			case 'expire_lists':
				$d['expired']='Expired';
				$d['expire_60']='Expires in 60 Days';
				$d['expire_30']='Expires in 30 Days';
				$d['expire_15']='Expires in 15 Days';
				$d['expire_7']='Expires in 7 Days';
				$d['expire_next_60']='Expires within next 60 Days';
				$d['expire_next_30']='Expires within next 30 Days';
				$d['expire_next_15']='Expires within next 15 Days';
				$d['expire_next_7']='Expires within next 7 Days';
				$this->OPTIONS[$what]=$d;
				return;
				break;
			
		}
        $this->OPTIONS[$what]=$this->renderResults($what,$lock);				
	}
	
  	protected function realGradeSort($args=false,$active=false){
		$ins=issetCheck($args,'ins');
		$active=issetCheck($active,'active');
		$this->RESULTS=$this->DB->Members()->select('MemberID,CGradeName,CurrentGrade,CGradeDate, BirthDate');
		if($ins){
			$this->RESULTS->where('MemberID',$ins);
		}else if($active){
			$this->RESULTS->where('Disable',0)->and('Dead',0);
		}
		$this->RESULTS->order('CurrentGrade DESC,CGradeDate ASC,BirthDate ASC');
		$r=$this->renderResults();
		$ct=1;
		foreach($r as $v){
			$v['gradeSortkey']=$ct;
			$out[$v['MemberID']]=$v;
			$ct++;
		}
		return $out;		
	}
	
    protected function formatResults($set=false){
		$ordered=$this->sortResults($set);
		$res=renderResultsORM($ordered);
		if($this->RESULTS_FORMAT==='json') $res=json_encode($res);
		return $res;
	}
	
	protected function sortResults($set=false){
		if($sortkey=issetCheck($this->SORT_KEYS,$set)){
			return $this->RESULTS->order($sortkey);
		}else{
			return $this->RESULTS;
		}
	}
	
	protected function fixUnicode($data=array()) {
		$fixed=array();
		foreach($data as $rec){
			$_rec=array();
			foreach($rec as $i=>$v){
				$_rec[$i]=utf8_decode($v);
			}
			$fixed[]=$_rec;
		}
		return $fixed;
	}
    protected function renderResults($set=false,$lock=0){
		//dojo lock
		if(!empty($this->DOJO_LOCK) && $lock>0){
			$this->doDojoLock($set,$lock);
		}
		if($this->RESULTS && !is_string($this->RESULTS)){
			return $this->formatResults($set);
		}else{
			return $this->RESULTS;
		}
	}
	
	protected function doDojoLock($set=false,$lock=0){
		$set_and=function($l){
			switch($l){
				case 1:$this->RESULTS->and('LocationID',$this->DOJO_LOCK);break;
				case 2:$this->RESULTS->and('DojoID',$this->DOJO_LOCK); break;
				case 3:$this->RESULTS->and('LocationID',$this->DOJO_LOCK);break;
			}
		};
		$set_where=function($l){
			switch($l){
				case 1:$this->RESULTS->where('LocationID',$this->DOJO_LOCK);break;
				case 2:$this->RESULTS->where('DojoID',$this->DOJO_LOCK);break;
				case 3:$this->RESULTS->where('LocationID',$this->DOJO_LOCK);break;
			}
		};
		if(in_array($set,array('clubs'))){
			$set_where($lock);
		}else{
			$set_and($lock);
		}
	}

	protected function getFieldOptions($table=false){
		//option for table fields
		$opts=$out=[];
		switch($table){
			case 'Members':
				$opts=array('Sex'=>'sex','Dojo'=>'dojos','Disable'=>'disabled','Dead'=>'yesno','nonuk'=>'yesno');
				break;
			case 'Users':
				$opts=array('Access'=>'user_access','Status'=>'yesno');
				break;
			case 'GradeLog':
				$opts=array('LocationID'=>'locations','GradeSet'=>'grades');
				break;			
			case 'Events':
				$opts=array('EventAddress'=>'locations_all','EventType'=>'events','EventOptions'=>'event_options_basic','EventStatus'=>'active','EventPublic'=>'yesno');
				break;
			case 'EventsLog':
				$opts=array('Forms'=>'yesno','Attending'=>'yesno','FormsSent'=>'yesno','Paid'=>'yesno','Room'=>'room_types');
				break;						
		}
		if($opts){
			foreach($opts as $i=>$v){
				$out[$i]=$this->get($v);
			}
		}
		return $out;
	}

	private function getAppVars($vars=false){
		//moved to container
		return $this->SLIM->AppVars->get('all');
	}
	public function getProductInfo($what=false,$id=0){
		if(!$id) return false;
		if(!isset($this->OPTIONS['products'])) $this->getOptions('products');
		$prod=issetCheck($this->OPTIONS['products'],$id);
		if(!$prod) return false;
		switch($what){
			case 'product_name':case 'name':
				return $prod['ItemTitle'];
				break;
			case 'price':
				return toPounds($prod['ItemPrice']);
				break;
			case 'product_status':case 'status':
				return $prod['ItemStatus'];
				break;
			default:
				return $prod;
		}		
	}
	private function getAltLabels(){
		$alt=array(
			'Birthdate'=>'DOB',
			'Disable'=>'Status',
			'Dead'=>'Sleep',
			'CGradeName'=>'Current Grade',
			'Sex'=>'Gender',
			'LocationDOJO'=>'Type',
			'LocationCountry'=>'Country',
			'DojoID'=>'Dojo',
			'AdminID'=>'Admin',
			'EventAddress'=>'Location',
			'set_as_active'=>'Set as Current',
			'NameInJapanese2'=>'Forename In Japanese',
			'NameInJapanese'=>'Surname In Japanese',
			'zasha'=>'Form',
			'EventDate'=>'Start Date',
			'EventDuration'=>'End Date',
			'EventPublic'=>'Show on public calendar',
			'MemberTypeID'=>'Member Type',
			'EventCost'=>'Cost',
			'Payment'=>'Paid',
			'PaymentAmount'=>'Amount Paid'		
		);
		return $alt;		
	}
	private function getBasicEventOptions(){
		$o['log']['forms']=array('label'=>'Forms','required'=>0,'fields'=>array('Forms'));
		$o['log']['forms_sent']=array('label'=>'Forms Sent','required'=>0,'fields'=>array('FormsSent'));
		$o['log']['payment']=array('label'=>'Paid','required'=>0,'fields'=>array('Paid'));
		$o['log']['payment_amount']=array('label'=>'Amount Paid','required'=>0,'fields'=>array('PaymentAmount'));
		$o['log']['attend']=array('label'=>'Attending','required'=>1,'fields'=>array('Attending'));
		$o['log']['room']=array('label'=>'Room','required'=>0,'fields'=>array('Room'));
		$o['log']['cost']=array('label'=>'Cost','required'=>0,'fields'=>array('EventCost'));
		$o['log']['balance']=array('label'=>'Balance','required'=>0,'fields'=>array('Balance'));
		$o['user']['age']=array('label'=>'Age','required'=>0,'fields'=>array('Birthdate_now'));
		$o['user']['age_at']=array('label'=>'Age At Event','required'=>0,'fields'=>array('Birthdate_then'));
		$o['user']['jname_fore']=array('label'=>'Forname In Japanese','required'=>0,'fields'=>array('NameInJapanese2'));
		$o['user']['jname_sur']=array('label'=>'Surname In Japanese','required'=>0,'fields'=>array('NameInJapanese'));
		$o['user']['ankfid']=array('label'=>'ANKF ID','required'=>0,'fields'=>array('AnkfID'));
		$o['user']['zasha']=array('label'=>'Form','required'=>0,'fields'=>array('zasha'));
		$o['user']['recent_events']=array('label'=>'Prev. Events','required'=>0,'fields'=>array('Prev. Events'));
		return $o;
	}
	private function getDefaultEventOptions(){
		//as stored in the database $[foo[set][$bar]=intPosition( from key "requred"). 0 not displayed.
		$o['log']['forms']=1;
		$o['log']['attend']=1;
		return $o;
	}
	private function getEventColor($type=false){
		$colors=array(
			0=>'black text-white',
			1=>'purple text-white',
			4=>'aqua',
			5=>'blue text-white',
			3=>'dark-blue text-white',
			2=>'amber',
			6=>'light-blue text-white',
			7=>'dark-green text-white',
			8=>'orange',
			9=>'olive text-white',
			10=>'lavendar'
		);
		if($type==='all') return $colors;
		$t=(int)$type;
		if($type>10) return 'gray';
		return $colors[$type];
	}
	private function getAssetSource($what=false){
		$out=false;
		if($what){
			$out=$this->SLIM->compressor->getFiles($what);
		}
		return $out;
	}
	function getMember($id=0,$what=false){
		if($id>0){
			$m=$this->getOptions('member',$id);
			if($m){
				if($what){
					return issetCheck($m,$what);
				}else{
					return $m;
				}
			}
		}
		return false;
	}
	function getPDO(){
		return $this->SLIM->pdo;
	}
	function getEZPDO(){
		return $this->SLIM->ezpdo;
	}
	function getORM(){
		return $this->SLIM->db;
	}		
	function getCompressor(){
		return $this->SLIM->compressor;
	}
	function getConfimer(){
		return $this->SLIM->Confirmer;
	}
	function getSlimEventContent(){
		return $this->SLIM->EventContent;
	}
	function getSlim(){
		return $this->SLIM;
	}
	private function getHelpBot($args=false){
		$chk=$this->SLIM->HelpBot->get($args);
		$out=[];
		if($chk['status']==200){
			$out['help']=$chk['data'];
			$chk=$this->SLIM->HelpBot->get('trigger');
			$out['trigger']=$chk['data'];
		}
		return $out;
	}
	function getJForm(){
		return $this->SLIM->jform;
	}	
	function getTableFields($table=false){
		$fields=array();
		if($table){
			$fields=$this->SLIM->ezpdo->getFields($table);
			$fields['primary']=$this->SLIM->ezpdo->getPrimary($table);
		}
		return $fields;
	}
	function getSiteOptions($what=false,$val=false){
		if(!issetCheck($this->OPTIONS,'site')) $this->getOptions('site');
		if(!issetCheck($this->OPTIONS,'site')) return ($val)?false:[];
		if(!$what) return $this->OPTIONS['site'];
		foreach($this->OPTIONS['site'] as $i=>$v){
			if($v['OptionName']===$what){
				return ($val)?$v['OptionValue']:$v;
			}
		}
		//just in case
		return ($val)?false:[];	
	}
	private function getShinsaRefInfo($args=null){
		$strip=$reverse=false;
		if($args==='strip') $strip=true;
		if($args==='reverse') $reverse=true;
		$d=$this->SLIM->ShinsaRef;
		$o=[];
		foreach($d as $i=>$v){
			$k=($reverse)?$i:$v;
			$n=($reverse)?$v:$i;
			if($strip && is_string($n)) $n=str_replace('shinsa-','',$n);
			$o[$k]=$n;
		}
		return $o;
	}
	
	function getSubscriptionProducts(){
		$this->getOptions('product_types');
		$subs_key=array_search('Subscriptions',$this->OPTIONS['product_types']);
		$whr=['ItemType'=>'product','ItemGroup'=>$subs_key];
		$prods=$this->DB->Items->where($whr)->order('ItemTitle');
		$prods=renderResultsORM($prods,'ItemID');
		return $prods;
	}
	function getMembershipProducts(){
		$whr=['ItemType'=>'product','ItemCategory'=>2];
		$prods=$this->DB->Items->where($whr)->order('ItemTitle');
		$prods=renderResultsORM($prods,'ItemID');
		return $prods;
	}
	function getDojoProducts($dojo_id=0){
		$prods=[];
		if($dojo_id){
			$whr=['ItemType'=>'product','ItemContent'=>$dojo_id];
			$recs=$this->DB->Items->where($whr)->order('ItemTitle');
			$prods=renderResultsORM($recs,'ItemID');
		}
		return $prods;
	}
	function getPaypalMerchant($what='local'){
		$merchants=[
			'local'=>[
				'id' => 'testMerchant',
				'code' => 'VN79NNBLBBZ6W',
				'email'=>'fonkeyman-facilitator@hotmail.com',
				'host'=>URL.'payments/ipn_sim',
				'currency_code'=>'USD'
			],
			'sandbox'=>[
				'id' => 'jamdev',
				'code' => 'VN79NNBLBBZ6W',
				'email' => 'fonkeyman-facilitator@hotmail.com',
				'host'=>'www.sandbox.paypal.com',
				'currency_code'=>'USD'
			],
			'paypal'=>[
				'id' => false,
				'code' => false,
				'email' => false,
				'host'=>'www.paypal.com',
				'currency_code'=>'USD'
			]
		];
		switch($what){
			case 'all':
				return $merchants;
				break;
			case 'hosts':
				$h=[];
				foreach($merchants as $i=>$v) $h[$i]=$v['host'];
				return $h;
			default:
				return issetCheck($merchants,$what,[]);
		}
	}
}
