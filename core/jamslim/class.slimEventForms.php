<?php

class slimEventForms{
	private $SLIM;
	private $FORM;
	private $PARTS;
	private $PATTERNS;
	private $DATA;
	private $PRODUCTS;
	private $PRODUCTS_ikyf_subs;
	private $DOJOS;
	private $GRADES;
	private $ZASHA;
	private $LANGUAGES;	
	private $LANG;
	private $CATEGORIES;
	private $GROUPS;
	private $CAT=3;//default category
	private $EVENT_PRODUCTS;
	private $EVENT_SHINSA;	
	private $EVENT_PRODUCTS_ALT;
	private $EVENT_PRODUCTS_PART;
	private $EVENT_PRODUCTS_ALT_PART;
	private $LABEL_MAP=array('FirstName'=>'first_name','LastName'=>'last_name','DojoID'=>'dojo','CurrentGrade'=>'grade','CGradedate'=>'grade_date','product_ref'=>'participation','Birthdate'=>'birthday','dojo'=>'dojo');
	private $BANK_INFO;
	private $BANK=[];
	private $SHINSA_ORDER;
	private $CURRENCY=1;
	private $MEMBERS_DB;
	private $DISABLE_PARTS;
	private $PDF_LOGO;
	private $SALES_REF;
	private $ROUTE;
	public $EVENT;
	public $USER;
	public $REGISTERED;
	public $EVENT_ID;
	public $FORM_ID;//for pdf ref
	public $SESSION_REC;
	public $FORM_REC;//hold submitted record for the FormsDB
	public $MEMBER_REC;
	public $PATTERN_INFO;
	private $IKYF_REG;
	private $IKYF_PROD;
	private $AUTO_SHINSA=true;//controls the shinsa selector
	public $MODE;//set to "admins" if we are looking at forms via admin area
	private $MAIL_BOT;
	private $AJAX;
	private $DEPARTS;
	private $REG_CLOSED;
	private $EVENT_LOG_REF;

	function __construct($slim=null){
		if(!$slim){
		  throw new Exception(__METHOD__.': no slim object!!');
		}
		$this->SLIM=$slim;
		$this->SHINSA_ORDER=$slim->ShinsaRef;
		$this->ZASHA=$slim->Options->get('zasha');
		$this->PDF_LOGO=$slim->Options->get('pdf_logo');
		$this->DEPARTS=$slim->Options->get('departs');
		$this->LANG=$slim->language->get('_LANG');
		$this->LANGUAGES=$slim->language->get('_LANGS');
		$this->DISABLE_PARTS=array('personal','dojo');
		$this->ROUTE=$slim->router->get('route');
		$this->AJAX=$slim->router->get('ajax');
		$this->MAIL_BOT=$slim->Options->getSiteOptions('email_mailbot',true);
		$this->BANK_INFO=html_entity_decode($slim->Options->get('bank_details'));
		$this->initParts();
	}
	
	function get($what=false,$vars=false){
		switch($what){
			case 'part':
				return issetCheck($this->PARTS,$vars);
				break;
			case 'parts':
				return $this->PARTS;
				break;
			case 'patterns':
				return $this->PATTERNS;
				break;
			case 'pattern':
				return issetCheck($this->PATTERNS,$vars);
				break;
			case 'form':
				return $this->FORM;
				break;
			case 'review_form':
				$this->initSesseionRec();
				return $this->renderReviewForm();
			case 'review_form_edit':
				$this->initSesseionRec();
				return $this->renderReviewForm('edit');
			case 'confirmed':
				$this->initSesseionRec();
				return $this->logRegistration();
				break;
			case 'my_form':
				return $this->renderReviewForm(false,$vars);
				break;
			case 'admin_form':
				return $this->renderReviewForm('admin',$vars);
				break;
			case 'pdf_form':
				//$vars is an event record or 'admin'(will use SESSION_REC)
				return $this->renderPDF($vars);
				break;			
			case 'my_form_download':
		}				
	}
	
	function renderForm($pattern=false,$data=false){
		$err=false;
		if(!$this->REG_CLOSED){
			$pat=issetCheck($this->PATTERNS,$pattern);
			if(!$this->USER)$this->USER=$this->SLIM->user;
			//check for temp login
			if($this->USER['access']>=25){
				$tmp_login=slimSession('get','temp_login');
				if($tmp_login) $this->USER=$tmp_login;
			}
			if($pat){
				if(is_array($data)){
					$this->DATA=$data;
				}else{
					if(!$this->DATA) $this->initMember();
				}
				$this->renderPattern($pat);
			}else{
				$err=msgHandler('Sorry, no form data found...',false,false);
			}
		}		
		if($err){
			return $err;
		}else if($this->REG_CLOSED){
			return msgHandler('Sorry, registration period has ended...',false,false);
		}else{
			return $this->FORM;
		}
	}
	function renderPreviewForm($pattern=false,$event_id=false){
		if(is_numeric($pattern)){
			$ct=1;
			foreach($this->PATTERNS as $i=>$pat){
				if($ct==$pattern){
					break;
				}
				$ct++;
			}
		}else{
			$pat=issetCheck($this->PATTERNS,$pattern);
		}
		if($this->SLIM->user['access']>=25){			
			if(!$this->USER) $this->USER=$this->SLIM->user;
			$err=false;
			if($pat){
				$this->EVENT=$this->getEvent($event_id);
				$this->getMember('email','ann.user@home.com');
				if(!$this->DATA) $this->initMember('preview');
				$this->renderPattern($pat,'admin');
			}else{
				$err=msgHandler('Sorry, no form pattern data found...',false,false);
			}
			if($err){
				return $err;
			}else{
				return $this->FORM;
			}
		}else{
			return msgHandler('Sorry, you don\'t have access to preview forms...',false,false);
		}
	}
	
	private function initParts(){
		$this->BANK=[
			0=>$this->BANK_INFO,
			1=>$this->BANK_INFO,
			2=>$this->BANK_INFO,
			3=>$this->BANK_INFO
		];
		//setup form parts
		$formparts['account']=array(
			'title'=>'login',
			'template'=>'app.form_part_account.html',
			'parts'=>array(
				'username'=>'username',
				'password'=>'password',
				'confirm_password'=>'confirm_password',
			)
		);
		$formparts['personal']=array(
			'title'=>'personal',
			'template'=>'app.form_part_personal.html',
			'parts'=>array(
				'FirstName'=>'first_name',
				'LastName'=>'last_name',
				'Email'=>'email',
				'Birthdate'=>'dob',
				'Language'=>'language',
			)
		);
		$formparts['personal_reg']=array(
			'title'=>'personal',
			'template'=>'app.form_part_personal_reg.html',
			'parts'=>array(
				'FirstName'=>'first_name',
				'LastName'=>'last_name',
				'Email'=>'email',
				'Birthdate'=>'dob',
				'Language'=>'language',
				'Address'=>'address',
				'LandPhone'=>'phone',
				'PostCode'=>'post_code',
				'Sex'=>'gender'
			)
		);
		$formparts['event']=array(
			'title'=>'seminar',
			'template'=>'app.form_part_event.html',
			'parts'=>array(
				'product_ref'=>'product_ref',
				'product_ref2'=>'product_ref2',
				'Arrive'=>'arrive',
				'Depart'=>'depart',
				'Notes'=>'notes',
			)
		);
		$formparts['event_nd']=array(
			'title'=>'seminar',
			'template'=>'app.form_part_event_nd.html',
			'parts'=>array(
				'product_ref'=>'product_ref',
				'product_ref2'=>'product_ref2',
				'ikyf_reg'=>'ikyf_reg',
				'Notes'=>'notes',
			)
		);
		$formparts['event_shinsa']=array(
			'title'=>'seminar',
			'template'=>'app.form_part_event_shinsa.html',
			'parts'=>array(
				'product_ref'=>'product_ref',
				'product_ref2'=>'product_ref2',
				'shinsa'=>'shinsa',
				'Arrive'=>'arrive',
				'Depart'=>'depart',
				'ikyf_reg'=>'ikyf_reg',
				'Notes'=>'notes',
			)
		);
		$formparts['dojo']=array(
			'title'=>'dojo_and_grade',
			'template'=>'app.form_part_dojo_grade.html',
			'parts'=>array(
				'DojoID'=>'dojo',
				'CurrentGrade'=>'grade',
				'CGradedate'=>'grade_date',
				'zasha'=>'zasha',
			)
		);
		$formparts['comments']=array(
			'title'=>'comments',
			'template'=>'app.form_part_comments.html',
			'parts'=>array(
				'comments'=>'comments',
			)
		);
		$formparts['personal_conact']=array(
			'title'=>'personal',
			'template'=>'app.form_part_personal_contact.html',
			'parts'=>array(
				'Name'=>'name',
				'Email'=>'email',
			)
		);
		$this->PARTS=$formparts;
		$this->PATTERNS=array(
			'member_event'=>array('personal','dojo','event'),
			'member_event_shinsa'=>array('personal','dojo','event_shinsa'),
			'non_member_event'=>array('account','personal_reg','dojo','event'),
			'member_account'=>array('account','personal_reg','dojo','comments'),
			'contact_us'=>array('personal_contact','comments'),
		);
		$this->PATTERN_INFO=array(
			'member_event'=>'Event registration form for members',
			'member_event_shinsa'=>'Event (with exam) registration form for members',
			'non_member_event'=>'Event registration form for non-members',
			'member_account'=>'Online account form (for creating logins)',
			'contact_us'=>'General contact form',
		);
		$this->initEvent();
		$this->initGrades();
		$this->initDojos();
		$this->initProducts();
	}
	private function initEvent(){
		$R1=issetCheck($this->ROUTE,1);
		$R2=issetCheck($this->ROUTE,2);
		$R3=(int)issetCheck($this->ROUTE,3);
		if($this->ROUTE[0]==='admin'){
			if($R1==='events'){
				$this->EVENT_ID=$R3;
				$this->EVENT=$this->getEvent($R3);
			}else if($R1==='preview_form' && (int)$R2){
				$this->EVENT=$this->getEvent($R2);
			}else if($R1==='submitted_form' && (int)$R2){
				$this->EVENT_ID=$this->initFormRec($R2);
				if($this->EVENT_ID) $this->EVENT=$this->getEvent($this->EVENT_ID);
			}else if($R2==='register' && $R3){
				$this->EVENT_ID=$R3;
				if($this->EVENT_ID) $this->EVENT=$this->getEvent($this->EVENT_ID);
			}
		}else if($R2==='submitted_form' && $R3){
			$this->EVENT_ID=$this->initFormRec($R3);
			if($this->EVENT_ID) $this->EVENT=$this->getEvent($this->EVENT_ID);
		}else{
			$this->EVENT_ID=$R3;
			$this->EVENT=$this->getEvent($R3);
		}
		$this->REG_CLOSED=$this->registrationClosed($this->EVENT);
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
	private function initFormRec($form_id=0){
		$evid=0;
		if($form_id){
			$rec=$this->SLIM->db->FormsLog();
			$rec->where('ID',$form_id);
			$rec=renderResultsORM($rec);
			$rec=current($rec);
			if(isset($rec['ID'])){
				$rec['FormData']=compress($rec['FormData'],false);
				$evid=$rec['FormData']['event_id'];
				$this->FORM_REC=$rec;
			}
		}
		return $evid;
	}	
	private function initSesseionRec(){
		$data=issetCheck($_SESSION['userArray'],'reg_form',[]);
		if($data){
			$data['category']=(int)	$data['category'];
			$data['member_id']=(int)$data['member_id'];
			$data['event_id']=(int) $data['event_id'];
			//hack for testing
			//if($data['event_id']==0) $data['event_id']=105;
			//end hack
			$this->EVENT_ID=$data['event_id'];
			$this->SESSION_REC=$data;
			if($data['member_id']>0){
				if(!$this->USER) $this->USER=$this->SLIM->user;
				$this->initMember();
			}
		}		
	}
	private function initGrades(){
		$grades=$this->SLIM->Options->get('grades');
		$grades=array_reverse($grades,true);
		$this->GRADES=rekeyArray($grades,'OptionValue');
	}
	private function initDojos(){
		$dojo=[];
		$dl=$this->SLIM->Options->DOJO_LOCK;
		$this->SLIM->Options->DOJO_LOCK=[];
		$d=$this->SLIM->Options->get('dojos');
		$this->SLIM->Options->DOJO_LOCK=$dl;
		if(!$d) $d=[];
		foreach($d as $i=>$v){
			$dojo[$i]=$v['LocationName'];
			if($c=issetCheck($v,'LocationCountry')) $dojo[$i].=', '.$c;
		}
		$this->DOJOS=$dojo;
	}	
	private function initProducts(){
		$this->CATEGORIES=$this->SLIM->Options->get('product_types');
		$this->GROUPS=$this->SLIM->Options->get('product_categories');
		$prods=$this->SLIM->db->Items->where('ItemType','product')->and('ItemStatus','active')->order('ItemTitle');
		if($this->CURRENCY) $prods->and('ItemCurrency',$this->CURRENCY);
		$prods=renderResultsORM($prods,'ItemID');
		$this->PRODUCTS=$prods;
		$ikyf_subs=[];
		foreach($this->PRODUCTS as $i=>$v){
			if(in_array($v['ItemCategory'],[1,4])){
				if(strpos($v['ItemSlug'],'ikyf-')!==false)	$ikyf_subs[]=$v['ItemID'];
			}
		}
		$this->PRODUCTS_ikyf_subs=$ikyf_subs;
	}
	private function initMembersDB(){
		$this->MEMBERS_DB = new slim_db_members($this->SLIM);		
	}
	
	private function initMember($mode=false){
		if($this->USER['id']>0){
			$id=($mode==='admin')?$this->SESSION_REC['member_id']:$this->USER['MemberID'];
			$this->getMember('id',$id);
			if($this->MEMBER_REC){
				//fix form values
				$this->SESSION_REC['Birthdate']=validDate($this->MEMBER_REC['Birthdate']);
				$this->SESSION_REC['CGradedate']=validDate($this->MEMBER_REC['CGradedate']);
				$this->SESSION_REC['FirstName']=$this->MEMBER_REC['FirstName'];
				$this->SESSION_REC['LastName']=$this->MEMBER_REC['LastName'];
				$this->SESSION_REC['DojoID']=$this->MEMBER_REC['DojoID'];
				$this->SESSION_REC['CurrentGrade']=$this->MEMBER_REC['CurrentGrade'];
				$this->SESSION_REC['zasha']=$this->MEMBER_REC['zasha'];
				$this->SESSION_REC['Language']=$this->MEMBER_REC['Language'];
				$this->SESSION_REC['Email']=$this->MEMBER_REC['Email'];
			}else if($mode==='preview'){
				$this->SESSION_REC['Birthdate']='1984-01-01';
				$this->SESSION_REC['CGradedate']='2015-01-01';
				$this->SESSION_REC['FirstName']='Anne';
				$this->SESSION_REC['LastName']='User';
				$this->SESSION_REC['DojoID']=1;
				$this->SESSION_REC['CurrentGrade']=2;
				$this->SESSION_REC['zasha']=0;
				$this->SESSION_REC['Language']='en';
				$this->SESSION_REC['Email']='anne.user@home.com';
			}
		}
	}
	private function memberExists(){
		$member_id=(int)issetCheck($this->SESSION_REC,'member_id');
		if($member_id && !$this->MEMBER_REC){
			$member=($member_id)?$this->getMember('id',$member_id):$this->getMember('email',$this->SESSION_REC['Email']);
			if(!$member) $member=$this->getMember('name',$this->SESSION_REC);
		}
		if(!$this->MEMBER_REC){//add member
			if(!$this->MEMBERS_DB) $this->initMembersDB();
			$fmap=array(
				'member_id' => 'MemberID',
				'FirstName' => 'FirstName',
				'LastName' => 'LastName',
				'Email' => 'Email',
				'Birthdate' => 'Birthdate',
				'DojoID' => 'DojoID',
				'CurrentGrade' => 'CurrentGrade',
				'CGradedate' => 'CGradedate'
			);
			$fields = $this->MEMBERS_DB->getFieldInfo();
			unset($fields['PGradeDate']);
			foreach($fmap as $i=>$v){
				$val=issetCheck($this->SESSION_REC,$i);
				switch($v){
					case 'Birthdate':
						$time=strtotime('-'.$val.' years');
						$val=date('Y-m-d',$time);
						break;
					case 'CGradedate':
						$val.='-01-01 00:00:00';
						break;
					case 'DojoID':
						$fields['Dojo']=$this->DOJOS[$val];
						break;
				}
				$fields[$v]=$val;
			}
			$resp=$this->MEMBERS_DB->add($fields);
			if($resp['status']==200){
				$member['MemberID']=$member_id=$resp['rowid'];
				$this->MEMBER_REC=$fields;
				$this->SESSION_REC['new_member']=1;
				$this->SESSION_REC['member_id']=$resp['rowid'];
			}	
		}else{
			$this->SESSION_REC['member_id']=$member_id=$this->MEMBER_REC['MemberID'];
		}
		return $member_id;
	}	
	private function getEvent($event_id=0){
		$event=[];
		if(!$event_id) $event_id=$this->EVENT_ID;
		$rec=$this->SLIM->db->Events()->where('EventID',$event_id);
		$event=renderResultsORM($rec);
		if($event && is_array($event)){
			$event=current($event);
		 	$this->CURRENCY=$event['EventCurrency'];
		}
		return $event;
	}
		
	private function getMember($what=false,$vars=null){
		if(!$this->MEMBERS_DB) $this->initMembersDB();
		switch($what){
			case 'id':
				$this->MEMBER_REC=$this->MEMBERS_DB->get('member',(int)$vars);
				break;
			case 'email':
				$r=$this->MEMBERS_DB->get('by',['field'=>'Email','value'=>$vars]);
				if(count($r)>0){
					$this->MEMBER_REC=current($r);
				}
				break;
			case 'name':
				$args=array('FirstName'=>$vars['FirstName'],'LastName'=>$vars['LastName']);
				$r=$this->MEMBERS_DB->get('by_name',$args);
				if(count($r)>0){
					$this->MEMBER_REC=current($r);
				}
				break;
		}
	}
	private function getUser($what=false,$vars=false){
		switch($what){
			case 'email': case 'id': case 'token':
				if($what==='email') $what='Email';
				if($what==='token') $what='Token';
				$DB=$this->SLIM->db->Users();
				$rec=$DB->where($what,$vars)->limit(1);
				$rec=renderResultsORM($rec);
				if(!empty($rec)) $out=current($rec);
				break;
			default:
				$out=[];
		}
		return $out;				
	}

	private function renderReviewForm($mode=false,$download=false){
		if(!$this->USER) $this->USER=$this->SLIM->user;
		$tpl_departs='';
		if($download){
			$tpl_departs='';
		}
		$tpl_shinsa_row='';
		$shinsa=false;
		$ikyf_prod=$shinsa_prod=[];
		$_tpl=($mode==='edit')?'app.form_view_edit.html':'app.form_view.html';
		if($download) $_tpl='app.form_pdf.html';

		$tpl=file_get_contents(TEMPLATES.'app/'.$_tpl);
		$pattern=($this->USER['access']<20)?'non_member_event':'member_event';
		if($mode==='admin'){
			$pattern='member_event';
			$this->EVENT_ID=$this->SESSION_REC['event_id'];
			$this->initMember($mode);
		}else{
			if($this->SESSION_REC){
				if($this->SESSION_REC['member_id']>0) $this->initMember();
			}else if($this->USER['id']==0){
				setSystemResponse(URL.'page/home','Sorry, it seems the details have expired...<br/>Please try again.');
			}
		}
		if(!$this->EVENT||$this->EVENT['EventID']!=$this->EVENT_ID){
			$this->EVENT=$this->getEvent($this->EVENT_ID);
			$this->CURRENCY=$this->EVENT['EventCurrency'];
			$this->initProducts();
		}
		//international only
		if($this->EVENT['EventType']==1){
			$ikyf_prod=$this->hasIKYF();
			if(!$this->EVENT_SHINSA){
				$shinsa=$this->setShinsa();
				$shinsa_prod=($shinsa)?issetCheck($this->EVENT_SHINSA,$shinsa):[];
				$tpl_departs='';
			}
		}
		$pattern=issetCheck($this->EVENT,'EventForm',$pattern);
		
		$this->setProduct();
		$prod=$prod2=$prod3=[];
		if(issetCheck($this->SESSION_REC,'product_ref')){
			$prod=issetCheck($this->EVENT_PRODUCTS,$this->SESSION_REC['product_ref']);
			if(!$prod) $prod=issetCheck($this->PRODUCTS,$this->SESSION_REC['product_ref']);
			if(!$prod){
				$prod=$this->SLIM->Products->get('product','id',$this->SESSION_REC['product_ref']);
				$prod=current($prod);
			}
		}

		if(issetCheck($this->SESSION_REC,'product_ref2')){
			$prod2=issetCheck($this->EVENT_PRODUCTS,$this->SESSION_REC['product_ref2']);
			if(!$prod2) $prod2=issetCheck($this->PRODUCTS,$this->SESSION_REC['product_ref2']);
			if(!$prod2){
				$prod2=$this->SLIM->Products->get('product','id',$this->SESSION_REC['product_ref2']);
				$prod2=current($prod2);
			}
			if($prod2['ItemPrice']==0) $prod2=[];
		}
		if(issetCheck($this->SESSION_REC,'ikyf_reg')){
			if($ikyf_prod){
				$prod3=$ikyf_prod;
			}else{
				$prod3=issetCheck($this->EVENT_PRODUCTS,$this->SESSION_REC['ikyf_reg']);
				if(!$prod3) $prod3=issetCheck($this->PRODUCTS,$this->SESSION_REC['ikyf_reg']);
				if(!$prod3){
					$prod3=$this->SLIM->Products->get('product','id',$this->SESSION_REC['ikyf_reg']);
					$prod3=current($prod3);
				}
			}
		}
		//shinsa
		$tpl=$this->getReviewFields($pattern,$tpl,$mode);
		$event=$this->getEvent($this->SESSION_REC['event_id']);
		if(!$this->CURRENCY)$this->CURRENCY=1;
		$price=0;
		if($prod) $price+=(int)$prod['ItemPrice'];
		if($prod2)$price+=(int)$prod2['ItemPrice'];
		if($prod3)$price+=(int)$prod3['ItemPrice'];
		
		if($this->EVENT['EventType']==1){
			if(isset($shinsa_prod['ItemPrice'])){
				$price+=(int)$shinsa_prod['ItemPrice'];
				if($download){
					$form['member_shinsa_label']='Shinsa';
					$form['member_shinsa']='';
				}else{
					$tpl_shinsa_row='<tr><td class="small-2">{member_shinsa_label}:</td><td class="text-navy">{member_shinsa}</td></tr>';
				}
			}
			if($this->IKYF_REG){
				$not_reg=(strpos($this->IKYF_REG,'name="ikyf_reg"')!==false)?true:false;
				if($not_reg){
					if(!$prod3){
						$prod3=$this->IKYF_PROD; 
						$price+=(int)$prod3['ItemPrice'];
					}
					$str=$prod3['ItemTitle'].' / '.toPounds($prod3['ItemPrice'],$this->CURRENCY);
				}else{
					$str=$this->SLIM->language->get('Registered');
				}
				if($download){
					$form['member_ikyf_label']='IKYF ID Reg';
					$form['member_ikyf']=$this->pdfCurrency($str);
				}else{
					$tpl_shinsa_row.='<tr><td class="small-2">IKYF ID Reg:</td><td class="text-navy">'.$str.'</td></tr>';
				}
			}
		}
		$paid=(int)issetCheck($this->SESSION_REC,'member_paid',0);
		$paid_date='-';
		if($paid){
			$paid_date=($this->SESSION_REC['member_paid_date'])?validDate($this->SESSION_REC['member_paid_date']):'-';
		}
		$event_date=$this->SLIM->language_dates->langDate($this->EVENT['EventDate'],'mn y');	
		$dojo=issetCheck($this->DOJOS,$this->SESSION_REC['DojoID'],'no dojo?');
		if($mode==='edit'){
			$disable=($this->MEMBER_REC)?'disabled="disabled"':false;
			$member_name=$this->prepFormData('FirstName','edit',$disable).$this->prepFormData('LastName','edit',$disable);
			$member_age=$this->prepFormData('Birthdate','edit',$disable);
			$member_age_label='birthday';
			$member_item=$this->prepFormData('product_ref','edit');
			if($prod2){
				$member_item2=$this->prepFormData('product_ref2','edit');
			}
			$member_shinsa=$this->prepFormData('shinsa','edit');
			$member_arrive=$this->prepFormData('Arrive','edit');
			$member_depart=$this->prepFormData('Depart','edit');
		}else{
			$member_name=$this->SESSION_REC['FirstName'].' '.$this->SESSION_REC['LastName'];
			$member_age=getAge($this->SESSION_REC['Birthdate']);
			$member_age_label='age';
			$member_item=(trim($prod['ItemShort'])!=='')?$prod['ItemShort']:$prod['ItemTitle'];
			if($prod2){
				$member_item2=(trim($prod2['ItemShort'])!=='')?$prod2['ItemShort']:$prod2['ItemTitle'];
			}
			$member_shinsa='';
			if($shinsa_prod) $member_shinsa=(trim($shinsa_prod['ItemShort'])!=='')?$shinsa_prod['ItemShort']:$shinsa_prod['ItemTitle'];
			$member_arrive=issetCheck($this->SESSION_REC,'Arrive','-');
			$member_depart=issetCheck($this->SESSION_REC,'Depart','-');
		}
		//add template part
		$tpl=str_replace('{arrive_depart}',$tpl_departs,$tpl);
		//add review form parts	
		$form['member_name_label']=$this->SLIM->language->getStandard('name');
		$form['member_name']=$member_name;
		$form['member_age_label']=$this->SLIM->language->getStandard($member_age_label);
		$form['member_age']=$member_age;

		$form['member_item_label']=$this->SLIM->language->getStandard('participation');
		$form['member_item']=$member_item;
		$form['member_item2']='';
		if($prod2){
			$form['member_item2_label']=$this->SLIM->language->getStandard('other_items');
			if($mode!=='edit') $member_item2.=' / '.toPounds($prod2['ItemPrice'],$this->CURRENCY);
			if($download){
				$form['member_item2']=$this->pdfCurrency($member_item2);
			}else{
				$form['member_item2']='<tr><td class="small-2">'.$this->SLIM->language->getStandard('other_items').':</td><td class="text-navy">'.$member_item2.'</td></tr>';
			}
		}
		$form['member_reg_date_label']=$this->SLIM->language->getStandard('registration_date');
		$form['member_reg_date']=date('Y-m-d');
		$form['member_sem_date']=$this->CATEGORIES[$this->CAT];
		
		$form['member_price_label']=$this->SLIM->language->getStandard('price');
		$form['member_price']=toPounds($price,$this->CURRENCY);
		if($download) $form['member_price']=$this->pdfCurrency($form['member_price']);
		$form['member_paid_label']=$this->SLIM->language->getStandard('paid');
		$form['member_paid']=toPounds($paid,$this->CURRENCY);
		if($download) $form['member_paid']=$this->pdfCurrency($form['member_paid']);
		$form['member_paid_date_label']=$this->SLIM->language->getStandard('date_paid');
		$form['member_paid_date']=$paid_date;
		$form['member_shinsa_label']=$this->SLIM->language->getStandard('shinsa');
		$form['member_shinsa']=$member_shinsa;
		$form['member_notes_label']=$this->SLIM->language->getStandard('comments');
		$form['member_arrive']=$member_arrive;
		$form['member_depart']=$member_depart;
		$form['member_arrive_label']=$this->SLIM->language->getStandard('arrive');
		$form['member_depart_label']=$this->SLIM->language->getStandard('depart');
		$form['bank_details']='<div class="grid-container"><div class="cell"><p>'.$this->BANK[$this->CURRENCY].'</p></div></div>';
		if($mode!=='edit'){
			$form['member_shinsa'].=($member_shinsa)?' / '.toPounds($shinsa_prod['ItemPrice'],$this->CURRENCY):'';
			$form['member_item'].=' / '.toPounds($prod['ItemPrice'],$this->CURRENCY);
			if($download) $form['member_item']=$this->pdfCurrency($form['member_item']);
		}
		//shinsa row
		$tpl_shinsa_row=str_replace('{member_shinsa_label}',$form['member_shinsa_label'],$tpl_shinsa_row);
		$tpl_shinsa_row=str_replace('{member_shinsa}',$form['member_shinsa'],$tpl_shinsa_row);
		$form['member_shinsa_row']=$tpl_shinsa_row;
		$form['section_label_personal']=$this->SLIM->language->getStandard('personal');
		$form['section_label_dojo']=$this->SLIM->language->getStandard('dojo_and_grade');
		$form['section_label_seminar']=$this->SLIM->language->getStandard('seminar');
		$form['section_label_account']=$this->SLIM->language->getStandard('account');
		$form['section_label_payment']=$this->SLIM->language->getStandard('payment');
		$form['section_label_payment_info']=$this->SLIM->language->getStandard('payment_info.');
		
		$form['title']=$this->EVENT['EventName'].': '.$event_date;
		$form['form_url']=URL.'page/event';
		$form['form_category']= $this->CAT;
		$form['edit_url']=URL.'page/event/reg_form_edit';
		$form['cancel_url']=URL.'page/event/cancel_reg';
		$form['confirm_url']=URL.'page/event/confirmed';
		$form['reg_form']=compress($this->SESSION_REC);
		if($mode==='edit'){
			$form['hidden']='<input type="hidden" name="action" value="submit_reg_review"/>';
			$form['hidden'].='<input type="hidden" name="reg_form" value="'.$form['reg_form'].'"/>';
		}
		$content=replaceME($form,$tpl);
		if($mode==='admin'){
			if($download){
				$form['member_birthday_label']=$this->SLIM->language->getStandard('birthday');
				$form['member_birthday']=$this->SESSION_REC['Birthdate'];
				$form['member_dojo_label']=$this->SLIM->language->getStandard('dojo');
				$form['member_dojo']=$this->DOJOS[$this->SESSION_REC['DojoID']];
				$form['member_grade_label']=$this->SLIM->language->getStandard('grade');
				$form['member_grade']=$this->GRADES[$this->SESSION_REC['CurrentGrade']]['OptionName'];
				$form['member_grade_date_label']=$this->SLIM->language->getStandard('grade date');
				$form['member_grade_date']=$this->SESSION_REC['CGradedate'];
				$form['member_zasha_label']=$this->SLIM->language->getStandard('zasha');
				$form['member_zasha']=$this->ZASHA[$this->SESSION_REC['zasha']];
				$form['member_email_label']=$this->SLIM->language->getStandard('email');
				$form['member_email']=$this->SESSION_REC['Email'];
				$form['member_language_label']=$this->SLIM->language->getStandard('email');
				$form['member_language']=$this->SESSION_REC['Language'];
				$form['member_id_label']=$this->SLIM->language->getStandard('member id');
				$form['member_id']=$this->SESSION_REC['member_id'];
				$form['member_arrive']=issetCheck($this->SESSION_REC,'Arrive','-');
				$form['member_depart']=issetCheck($this->SESSION_REC,'Depart','-');
				$form['member_notes']=$this->SESSION_REC['Notes'];				
			}	
			
			$output=($download)?$form:$content;
		}else if($mode==='edit'){
			$out['title']=$this->SLIM->language->getStandard('edit_details');
			$lang['en']='Make your changes then click the "Submit" button to continue.';
			$note=msgHandler($lang['en'],'warning',false);
			$out['content']=msgHandler('<i class="fi-megaphone"></i> '.$note,'primary',false);
			$out['content'].=$content;
			$cancel=$this->SLIM->language->getStandard('cancel');
			$submit=$this->SLIM->language->getStandard('submit');
			$out['fcontrols']='<div class="button-group expanded"><button class="button button-red loadME" data-ref="'.$form['cancel_url'].'/'.$this->EVENT_ID.'"><i class="fi-x"></i> '.$cancel.'</button> <button class="button button-olive" type="submit"><i class="fi-check"></i> '.$submit.'</button></div>';
			
			$output='<h3>Edit Details</h3>'.$note.'<form id="form1" method="post" action="'.$form['form_url'].'">';
			$output.=$form['hidden'];
			$output.=$content.$out['fcontrols'];
			$output.='</form>';
		}else{
			$alert='';
			if($download||$this->REGISTERED){
				
			}else{
				$msg=$this->SLIM->Options->get('application','signup review notice');
				if($msg){
					if(trim($msg['OptionValue'])!==''){
						$alert=msgHandler(html_entity_decode($msg['OptionValue']),'warning',false);
					}
				}
			}
			$out['title']=$this->SLIM->language->getStandard('review_details');
			$out['content']='<h3>'.$out['title'].'</h3>'.$alert.$content;
			$cancel=$this->SLIM->language->getStandard('cancel');
			$edit_form=$this->SLIM->language->getStandard('edit');
			$confirm=$this->SLIM->language->getStandard('confirm');
			$out['fcontrols']=($download||$this->REGISTERED)?'':'<div class="button-group expanded"><button class="button button-red loadME" data-ref="'.$form['cancel_url'].'/'.$this->EVENT_ID.'"><i class="fi-x"></i> '.$cancel.'</button><button class="button gotoME" data-ref="'.$form['edit_url'].'"><i class="fi-pencil"></i> '.$edit_form.'</button> <button class="button button-olive gotoME" data-ref="'.$form['confirm_url'].'"><i class="fi-check"></i> '.$confirm.'</button></div>';
			
			$output=$out['content'].$out['fcontrols'];
		}
		return ($download)?$form:$output;
	}
	private function getReviewFields($pattern,$tpl,$for_form=false){
		$pat=issetCheck($this->PATTERNS,$pattern);
		if($for_form==='admin') $for_form=false;
		foreach($pat as $part_id){
			$disable=($this->MEMBER_REC && in_array($part_id,$this->DISABLE_PARTS))?true:false;
			$part=issetCheck($this->PARTS,$part_id);
			foreach($part['parts'] as $i=>$v) {
				$lb=issetCheck($this->LABEL_MAP,$v,camelTo($i));
				
				$rp['member_'.$v.'_label']=$this->SLIM->language->getStandard($lb);
				$rp['member_'.$v.'_disabled']='';//not used in review forms - done via prepFormData
				$rp['member_'.$v]=$this->prepFormData($i,$for_form,$disable);			
				$tpl=replaceMe($rp,$tpl);
			}
			$title=$this->SLIM->language->getStandard($part['title']);
			$tpl=str_replace('{section_label_'.$part_id.'}',$title,$tpl);
		}
		return $tpl;
	}
	private function renderPattern($pattern){
		$this->FORM=[];
		foreach($pattern as $part_id){
			switch($part_id){
				case 'event':
					$this->setProduct();
					$chk=(int)$this->EVENT['EventArrivalDates'];
					if(!$chk) $part_id='event_nd';
					break;
				case 'event_shinsa':
					$this->setProduct();
					$this->setShinsa();
					$this->setIKYF();
					break;
				
			}
			$this->renderPart($part_id);
		}
		$this->FORM['bank_details']='<div class="grid-container"><div class="cell"><p>'.$this->BANK[$this->CURRENCY].'</p></div></div>';
		$this->FORM=implode('',$this->FORM);
	}
	
	private function renderPart($part_id=false){
		$part=issetCheck($this->PARTS,$part_id);
		if($part){
			$tpl=file_get_contents(TEMPLATES.'app/'.$part['template']);
			$disable=($this->MEMBER_REC && in_array($part_id,array('personal','dojo')))?'disabled="disabled"':'';
			foreach($part['parts'] as $i=>$v){
				if($i==='ikyf_reg'){
					$tpl=str_replace('{member_ikyf_reg}',$this->IKYF_REG.'',$tpl);
				}else if($i==='product_ref2'){
					$tpl=str_replace('{member_product_ref2}',$this->EVENT_PRODUCTS_ALT_PART,$tpl);
				}else{
					$lb=issetCheck($this->LABEL_MAP,$i,$v);
					$rp['member_'.$v.'_label']=$this->SLIM->language->getStandard($lb);
					$rp['member_'.$v.'_disabled']=$disable;
					$rp['member_'.$v]=$this->prepFormData($i);	
					$tpl=replaceMe($rp,$tpl);
				}
			}
			$title=$this->SLIM->language->getStandard($part['title']);
			$tpl=str_replace('{section_title}',$title,$tpl);
			$this->FORM[$part_id]='<section>'.$tpl.'</section>';
		}
	}
	private function renderForm_input($name=false,$value='',$type='text',$disabled=false){
		$out=false;
		if($disabled) $disabled='disabled="disabled"';
		if($name){
			$out='<input name="'.$name.'" value="'.$value.'" type="'.$type.'" '.$disabled.'/>';
		}
		return $out;
	}
	private function renderForm_textarea($name=false,$value='',$disabled=false){
		$out=false;
		if($disabled) $disabled='disabled="disabled"';
		if($name){
			$out='<textarea rows="4" name="'.$name.'" '.$disabled.'>'.trim($value).'</textarea>';
		}
		return $out;
	}
	private function renderForm_select($what,$id=false,$for_form=true,$name=false,$disabled=false){
		$opt='';
		$label_key=false;
		if($disabled) $disabled='disabled="disabled"';
		switch($what){
			case 'grade': case 'grades': case 'CurrentGrade':
				$data=$this->GRADES;
				$label_key='OptionName';
				$what='grades';
				break;
			case 'dojo': case 'DojoID':
				$data=$this->DOJOS;
				$opt='<option value="0">No Dojo?</option>';
				break;
			case 'depart': case 'arrive': case 'Depart': case 'Arrive':
				$trans['Friday']=array('fr'=>'Vendredi','de'=>'Freitag');
				$trans['Saturday']=array('fr'=>'Samedi','de'=>'Samstag');
				$trans['Sunday']=array('fr'=>'Dimanche','de'=>'Sonntag');
				foreach($this->DEPARTS as $i=>$v){
					$data[$i]=($this->LANG==='en')?$v:$trans[$v][$this->LANG];
				}
				break;
			case 'zasha':
				$data=$this->ZASHA;
				break;
			case 'products':
				$data=$this->checkSoldOut();
				break;
			case 'products_alt':
				$data=$this->EVENT_PRODUCTS_ALT;
				break;
			case 'shinsa':
				$data[0]='No';
				$tmp=array_flip($this->SHINSA_ORDER);
				$shin=issetCheck($tmp,$this->SESSION_REC['CurrentGrade']);
				$prod=$this->SLIM->Products->get('product','slug',$shin);
				if($prod=current($prod)){
					$id=$prod['ItemID']; //make this the selected option
					$data[$id]=$prod['ItemTitle'].' / '.toPounds($prod['ItemPrice'],$this->CURRENCY);
				}
				break;
			case 'ikyf_reg':
				$prod=$this->PRODUCTS[$id];
				$data[$what]=$prod['ItemTitle'].' / '.toPounds($prod['ItemPrice'],$this->CURRENCY);
				break;
			case 'gender': case 'Sex':
				$data=$this->SLIM->Options->get('gender');
				break;
			case 'language': case 'Language':
				$data=$this->SLIM->language->get('_LANGNAMES',$this->LANG);
				break;
			default:
				$data=false;				
		}
		if(is_array($data)){
			if(!$for_form){
				$label=($label_key)?issetCheck($data,$label_key,'???'):ucME($what);
				$opt=issetCheck($data,$id);
				if($what==='grades') $opt=$opt[$label_key];
				if(in_array($what,['products','products_alt'])){
					$data=current($data);
					$tit=(!$data['ItemShort'])?'':trim($data['ItemShort']);
					if($tit==='') $tit=$data['ItemTitle'];
					$opt=$tit.': '.toPounds($data['ItemPrice'],$this->CURRENCY);
				}
			}else{
				foreach($data as $i=>$v){
					$label=($label_key)?issetCheck($v,$label_key,'???'):$v;
					$selected=($id==$i)?'selected="selected"':false;
					switch($what){
						case 'grades':
							if($v['sortkey']>0) $opt.='<option value="'.$i.'" '.$selected.'>'.$label.'</option>';
							break;
						case 'products':
						case 'products_alt':
							$tit=(!$v['ItemShort'])?'':trim($v['ItemShort']);
							if($tit==='') $tit=$v['ItemTitle'];
							$label=$tit.': '.toPounds($v['ItemPrice'],$this->CURRENCY);
							$opt.='<option value="'.$i.'" '.$selected.'>'.$label.'</option>';
							break;
						default:
							$opt.='<option value="'.$i.'" '.$selected.'>'.$label.'</option>';
					}
				}
			}
		}
		if($for_form==='edit' && $name){
			return '<select name="'.$name.'" '.$disabled.'>'.$opt.'</select>';
		}
		return $opt;		
	}
	private function getShinsaSelect(){
		if($this->AUTO_SHINSA){
			$data[0]='No';
			$tmp=array_flip($this->SHINSA_ORDER);
			$shin=issetCheck($tmp,$this->SESSION_REC['CurrentGrade']);
			$prod=$this->SLIM->Products->get('product','slug',$shin);
			$prod=current($prod);
			$data[$shin]=$prod['ItemTitle'].' / '.toPounds($prod['ItemPrice'],$this->CURRENCY);
		}else{
			$data[0]='None';
			foreach($this->EVENT_SHINSA as $i=>$v){
				$data[$i]=$v['ItemTitle'].' / '.toPounds($v['ItemPrice'],$this->CURRENCY);
			}
		}
		return $data;		
	}
	private function setProduct(){
		$prods=[];
		$this->CAT=issetCheck($this->EVENT,'EventProduct');
		$limit=issetCheck($this->EVENT,'EventProductLimit');
		if(!$this->PRODUCTS) $this->initProducts();
		if($limit){
			$limit=json_decode($limit,1);
			if(!is_array($limit)|| empty($limit)) $limit=false;
		}
		foreach($this->PRODUCTS as $i=>$v){
			if($this->CAT==$v['ItemGroup'] && $v['ItemCategory']!=4){//skip fees
				if($limit){
					if(in_array($i,$limit)) $prods[$i]=$v;
				}else{
					$prods[$i]=$v;
				}
			}
		}
		$this->EVENT_PRODUCTS=$prods;
		$this->setAltProduct();
		$this->setIKYF_sub();
	}
	private function setIKYF_sub(){
		//check if IKYF Subscription is required
		if($this->EVENT['EventType']==1){ //required
			$chk=$this->hasIKYF_sub();
			if(!$chk){//add sub charge to form
				$prod_ref=current($this->PRODUCTS_ikyf_subs);
				if($prod_ref){
					$p=$this->PRODUCTS[$prod_ref];
					$price=toPounds($p['ItemPrice'],$this->CURRENCY);
					$this->IKYF_REG='<label for="ikyf_regx">'.$p['ItemTitle'].'</label>'.$this->renderForm_input('ikyf_regx',$price,'text',true).'<input type="hidden" name="ikyf_reg" value="'.$prod_ref.'"/>';
					$this->IKYF_PROD=$p;
				}
			}
		}
	}
	private function hasIKYF_sub(){
		$sales=issetCheck($this->MEMBER_REC,'SalesLog',[]);
		$sub=false;
		//find active IKYF sub
		foreach($sales as $ref=>$rec){
			foreach($rec as $i=>$v){
				if($v['Status']==1 && in_array($v['ItemID'],$this->PRODUCTS_ikyf_subs)){
					$sub=true;
					break;
				}
			}
			if($sub) break;
		}
		return $sub;
	}	
	
	private function setAltProduct(){
		$prods=[];$ap=false;
		$limit=issetCheck($this->EVENT,'EventProductLimit2');
		if($limit){
			$limit=json_decode($limit,1);
			if(!is_array($limit)|| empty($limit)) $limit=false;
		}
		if($limit){
			foreach($this->PRODUCTS as $i=>$v){
				if($this->CAT==$v['ItemGroup'] && $v['ItemCategory']!=4){//skip fees
					if($limit){
						if(in_array($i,$limit)) $prods[$i]=$v;
					}
				}
			}
			if($prods){
				$this->EVENT_PRODUCTS_ALT=$prods;
				$opt=$this->renderForm_select('products_alt','product_ref2',true,'product_ref2',false);
				$ap='<div class="grid-x grid-padding-x">
					<div class="cell">
						<label for="product_ref2">'.$this->SLIM->language->getStandardPhrase('other_items').'</label>
						<select id="product_ref2" name="product_ref2" >
							'.$opt.'
						</select>
					</div>
				</div>';
				
			}
		}
		$this->EVENT_PRODUCTS_ALT_PART=$ap;		
    }
	private function setShinsa(){
		$prods=[];
		$shinsa=issetCheck($this->EVENT,'EventShinsa');
		$shinsa=json_decode($shinsa,true);
		$selected=(int)issetCheck($this->SESSION_REC,'shinsa');
		if(is_array($shinsa) && !empty($shinsa)){
			foreach($this->PRODUCTS as $i=>$v){
				if(in_array($i,$shinsa)){
					$slug=str_replace('-eur','',$v['ItemSlug']);
					$shin=issetCheck($this->SHINSA_ORDER,$slug);
					$key=($shin)?(int)$shin:0;;
					$sort[$key]=$v;
					if($selected && $i==$selected) $selected=$v;
				}
			}
			ksort($sort);
			foreach($sort as $s) $prods[$s['ItemID']]=$s;
		}
		$this->EVENT_SHINSA=$prods;
		return $selected;
	}
	private function hasIKYF(){
		$pk=array('product_ref','shinsa','product_ref2','AdditionalFee','ikyf_reg');
		foreach($pk as $v){
			$chk=(int)issetCheck($this->SESSION_REC,$v);
			if(in_array($chk,array(23,45))){
				return $this->setIKYF();
			}
		}
	}	
	private function setIKYF(){
		$IKYF=$this->SLIM->checkIKYF;
		$IKYF->MODE=$this->MODE;
		$IKYF->checkSubscription($this->EVENT['EventDate'],$this->EVENT['EventCurrency']);
		$prod=$IKYF->get('product');
		$this->IKYF_REG=$IKYF->get('form_part');
		$this->IKYF_PROD=$prod;
		return $prod;
	}
	
	private function prepFormData($field,$for_form=true,$disabled=false){
		$value=issetCheck($this->SESSION_REC,$field);
		switch($field){
			//selects
			case 'DojoID':case 'zasha':case 'Sex':case 'Language':case 'Arrive':case 'Depart':case 'CurrentGrade':
				$out=$this->renderForm_select($field,$value,$for_form,$field,$disabled);
				break;
			case 'Sex':
				$out=$this->renderForm_select('gender',$value,$for_form,$field,$disabled);
				break;
			case 'product_ref':
				$out=$this->renderForm_select('products',$value,$for_form,$field,$disabled);
				break;
			case 'product_ref2':
				$out=$this->renderForm_select('products_alt',$value,$for_form,$field,$disabled);
				break;
			case 'shinsa':
				$out=$this->renderForm_select('shinsa',$value,$for_form,$field,$disabled);
				break;
			case 'fee':
				$out=$this->renderForm_select('fee',$value,$for_form,$field,$disabled);
				break;
			//textarea
			case 'comments': case 'notes': case 'Notes': case 'Comments':
				$out=($for_form==='edit')?$this->renderForm_textarea($field,$value,$disabled):trim($value);
				break;
			case 'dob': case 'Birthdate': case 'CGradedate': case 'date_paid': 
				$out=($for_form==='edit')?$this->renderForm_input($field,$value,'date',$disabled):$value;
				break;
			default:
				$out=($for_form==='edit')?$this->renderForm_input($field,$value,'text',$disabled):$value;
		}
			
		return $out;		
	}

	private function logRegistration(){
		if(!$this->EVENT){
			$this->EVENT_ID=$this->SESSION_REC['event_id'];
			$this->EVENT=$this->getEvent($this->EVENT_ID);
		}
		if(!$this->EVENT || !$this->SESSION_REC){
			$msg=$this->SLIM->language->getStandardPhrase('register_error');
			setSystemResponse(URL.'page/home',$msg);
		}
		if(!$this->PRODUCTS) $this->initProducts();
		//registration object
		$REG= $this->SLIM->LogRegistration;
		$REG->MEMBER_ID=$this->memberExists();
		$REG->EVENT_ID=$this->EVENT_ID;
		$REG->EVENT=$this->EVENT;
		$REG->PRODUCTS=$this->PRODUCTS;
		$chk=$REG->log($this->SESSION_REC);
		if($chk['status']==200){
			if(issetCheck($chk['data'],'sales')){
				$this->SALES_REF=issetCheck($chk['data']['sales'],'ref');
			}
			$this->EVENT_LOG_REF=issetCheck($chk['data'],'log_id',0);
			$this->sendMessages();
			//clear session form
			setMySession('reg_form',false);
			$msg=$this->SLIM->language->getStandardPhrase('register_success');
			$url=URL.'page/home';
		}else{
			if($chk['message']==='already_registered'){
				$msg=$this->SLIM->language->getStandardPhrase('already_registered');
				$url=URL.'page/home';
			}else{
				if($this->USER['access']>=25){
					$msg=$chk['message'];
				}else{
					$msg=$this->SLIM->language->getStandardPhrase('register_error');
				}
				$url=URL.'page/register_confirm';
			}
		}
		if($this->AJAX){		
			return array('status'=>200,'message'=>$msg,'url'=>$url);
		}
		setSystemResponse($url,$msg);
		die;
	}

	private function sendMessages(){
		$event=$this->getEvent($this->SESSION_REC['event_id']);
		//produce pdf
		$pdf=$this->renderPDF($event);
		
		//common parts
		$parts['event_name']=$event['EventName'].' '.$event['EventLocation'];
		$parts['event_date']=validDate($event['EventDate']);
		$parts['name']=$parts['member_name']=$this->SESSION_REC['FirstName'].' '.$this->SESSION_REC['LastName'];
		$parts['member_email']=$this->SESSION_REC['Email'];
		$parts['url']=URL;		
		
		$send['header']=$this->SLIM->language->getStandardContent('email_header');
		$send['footer']=$this->SLIM->language->getStandardContent('email_footer');
		$send['from']=$this->MAIL_BOT;
		$send['attachments'][]=$pdf;
		
		//send user email
		$msg=$this->SLIM->language->getStandardContent('logged_registration_email');
		$msg=replaceMe($parts,$msg);
		$send['to']=$this->SESSION_REC['Email'];
		$send['subject']='AHK/SKV: '.$this->SLIM->language->getStandard('event_registration_confirmed');
		$send['message'][0]=strip_tags($msg,'<a><br><br/>');
		$send['message'][1]=$msg;
		$chk=$this->SLIM->Mailer->Process($send);
		
		//send admin email
		$msg=$this->SLIM->language->getStandardContent('notify_registration_email');
		$msg=replaceMe($parts,$msg);
		$send['to']='admin@home.com';
		$send['subject']='AHK/SKV: '.$this->SLIM->language->getStandard('event_registration_recieved');
		$send['message'][0]=strip_tags($msg,'<a><br><br/>');
		$send['message'][1]=$msg;
		$chk=$this->SLIM->Mailer->Process($send);
	}
	
	private function renderPDF($event=false,$render='F'){
		$mode=($event==='admin')?'admin':false;
		$down['html']=$this->renderReviewForm($mode,true);
		//remove arrive/depart fields
		$del=['arrive','depart'];
		foreach($del as $d){
			$k1='member_'.$d;
			$k2=$k1.'_label';
			unset($down['html'][$k1],$down['html'][$k2]);
		}
		if($mode==='admin'){
			$event=$this->EVENT;
			$render='D';
			$down['user']=($this->FORM_REC)?$this->FORM_REC['MemberName']:'Member: '.$down['html']['member_name'];
			$down['date']=($this->FORM_REC)?strtotime($this->FORM_REC['LogDate']):strtotime($down['html']['member_reg_date']);
			if($this->FORM_REC){// fadditional text for pdf header	
				$down['reference_code']='WFRM_'.$this->FORM_REC['ID'];
			}else if($this->FORM_ID){
				$down['reference_code']='WFRM_'.$this->FORM_ID;
			}else if($this->SALES_REF){
				$down['reference_code']=$this->SALES_REF; 
			}else{
				$down['reference_code']='-'; 
			}
			$x_shinsa=issetCheck($this->SESSION_REC,'shinsa',0);
			if($x_shinsa==0) $down['html']['member_shinsa']='-';
		}else{
			if(!isset($down['html']['member_grade'])){
				$down['html']['member_grade_label']=$this->SLIM->language->getStandard('grade');
				$down['html']['member_grade']=$this->GRADES[$this->SESSION_REC['CurrentGrade']]['OptionName'].' - '.validDate($this->SESSION_REC['CGradedate']);
				$down['html']['member_dojo_label']=$this->SLIM->language->getStandard('dojo');
				$down['html']['member_dojo']=$this->DOJOS[$this->SESSION_REC['DojoID']];
				$down['html']['member_form_label']=$this->SLIM->language->getStandard('form');
				$down['html']['member_form']=$this->ZASHA[(int)$this->SESSION_REC['zasha']];
			}
			$down['user']=$this->USER['name'];// user name for pdf header
			$down['date']=time();// date being published for pdf header
			if($this->FORM_REC){
				$down['reference_code']='WFRM_'.$this->FORM_REC['ID']; // fadditional text?? for pdf header	
			}else if($this->SALES_REF){
				$down['reference_code']=$this->SALES_REF; 
			}else{
				$down['reference_code']='-'; 
			}	
		}
		$down['title']=$this->SLIM->language->getStandard('registration_form');// pdf title for header
		$down['sub_title']='Event: '.$event['EventName'].' on '.validDate($event['EventDate'],'F j, Y');
		$down['bank_details']=$this->BANK[$this->CURRENCY];
		$down['logo']=array('logo'=>$this->PDF_LOGO,'text'=>"Association Helvétique\nDe Kyudo,\n2500 BIEL/BIENNE");// array of address & image for pdf header
		$dn=($this->FORM_REC)?$this->FORM_REC['ID']:$this->USER['id'];
		$down['docname']=$dn.'-'.slugMe($event['EventName']).'_form.pdf'; // for downloading
		if($render==='F') $down['docname']=CACHE.'pdf/'.$down['docname'];// to file
		$down['render_type']=$render;
		$r=$this->SLIM->PDF->render($down);
		return $down['docname'];
	}
	private function getRoomMetrics(){
		$booked=array(
			1=>array('label'=>'single','qty'=>0),
			2=>array('label'=>'double','qty'=>0),
			3=>array('label'=>'single+','qty'=>0),
			4=>array('label'=>'double+','qty'=>0)
		);
		$rooms=json_decode($this->EVENT['EventRooms'],1);
		$log=$this->getEventsLog($this->EVENT['EventID']);
		$tots=array();
		foreach($log as $i=>$v){
			$r=(int)$v['Room'];
			if($r) $booked[$r]['qty']++;
		}
		foreach($booked as $i=>$v){
			$rm=(int)issetCheck($rooms,$v['label']);
			$rv=($rm-$v['qty']);
			$tots[$i]=array('label'=>$v['label'],'rooms'=>$rm,'booked'=>$v['qty'],'available'=>$rv);
		}
		return $tots;	
	}
	private function checkSoldOut(){
		$metrics=$this->getRoomMetrics();
		$out=[];
		foreach($this->EVENT_PRODUCTS as $pid=>$prod){
			$has_room=(int)$prod['ItemOrder'];
			if($has_room){
				if($metrics[$has_room]['available']>0){
					$out[$pid]=$prod;
				}
			}else{
				$out[$pid]=$prod;
			}
		}
		return $out;		
	}
	private function getEventsLog($id){
		$reg=[];
		$db=$this->SLIM->db->EventsLog();
		$rec=$db->where('EventID',$id);
		if(count($rec)>0){
			$rec=$db->select('EventLogID,EventID,MemberID,EventCost,Paid,Room');
			$reg=renderResultsORM($rec,'EventLogID');
		}
		return $reg;		
	}
	private function pdfCurrency($str=''){
		$rp=['&dollar;'=>'$','&eruo;'=>'€','&yen'=>'¥'];
		foreach($rp as $h=>$s){
			if(strpos($str,$h)!==false){
				$str=str_replace($h,$s,$str);
				break;
			}
		}
		return $str;
	}
}		
