<?php

class slimSignupForm {
	private $SLIM;
	private $ROUTE;
	private $AJAX;
	private $OUTPUT=array('status'=>500,'message'=>false,'data'=>false,'content'=>false,'title'=>'Membership Applications');
	private $FORM;
	private $FORM_STATES=['submit'=>'orange','rejected'=>'maroon','approved'=>'dark-green','process_error'=>'red'];
	private $FORM_DATA=[];
	private $FORM_REC;
	private $DOJOS;
	private $GRADES;
	private $ZASHA;
	private $LANGUAGES;
	private $PARTS;
	private $PATTERNS;
	private $PATTERN_INFO;
	private $PDF_LOGO;
	private $MODE;
	private $LOCATIONS;
	private $PUBLIC_USER;
	private $USE_CAPTCHA=true;
	private $SELECT_LOCATIONS=false;
	public $TEST_FORM=[
		'uname'=>'FredyS',
		'upass'=>'starman',
		'uconfirm'=>'starman',
		'FirstName'=>'Fredy',
		'LastName'=>'Star',
		'Email'=>'fredystar@home.com',
		'Address'=>'22 Balsen Road',
		'City'=>'Bern',
		'PostCode'=>'SN3 321',
		'Country'=>'Switzerland',
		'MobilePhone'=>'0122 125 4567',
		'Birthdate'=>'1975-06-10',
		'Sex'=>'male',
		'Language'=>'en',
		'DojoID'=>24,
		//'exam1'=>['location'=>4,'date'=>'2019-01-26'],
		//'exam2'=>['location'=>37,'date'=>'2021-03-01'],
		'meta_citizenship'=>'Swiss',
		'meta_date_began_kyudo'=>'2016-06-16',
		'meta_payment_method'=>3
			
	];
	public $PERMBACK;
	public $PERMLINK;
	public $LANG;
	
	
	function __construct($slim){
		if(!$slim){
		  throw new Exception(__METHOD__.': no slim object!!');
		}
		$app='signup';
		$this->SLIM=$slim;
		$this->AJAX=$this->SLIM->router->get('ajax');
		$this->ROUTE=$this->SLIM->router->get('route');
		$this->PERMLINK=URL.implode('/',$this->ROUTE).'/';
		$this->PERMBACK=URL.$app.'/';
		$this->ZASHA=$slim->Options->get('zasha');
		$this->LANGUAGES=$slim->language->get('_LANGS');
		$this->PDF_LOGO=$slim->Options->get('pdf_logo');
		$this->MODE=($this->ROUTE[0]==='admin')?'admin':'public';
		//locations
		$this->LOCATIONS=[];
		$d=$this->SLIM->Options->get('locations');
		foreach($d as $i=>$v){
			$this->LOCATIONS[$i]=$v['LocationName'].', '.$v['LocationCountry'];
		}
		//grades
		$grades=$this->SLIM->Options->get('grades');
		$grades=array_reverse($grades,true);
		$this->GRADES=rekeyArray($grades,'OptionValue');
		//dojo
		$d=$slim->options->get('dojos');
		foreach($d as $i=>$v){
			$dojo['short'][$i]=$v['ShortName'].', '.$v['LocationCountry'];
			$dojo['long'][$i]=$v['LocationName'].' ['.$v['ShortName'].']';
		}
		$this->DOJOS=$dojo;
		$this->initFormParts();
		//$this->FORM_DATA=$this->TEST_FORM;//for testing
	}
	private function initFormParts(){
		//setup form parts
		$formparts['account']=array(
			'title'=>'login',
			'template'=>'app/app.form_part_account.html',
			'parts'=>array(
				'uname'=>'username',
				'upass'=>'password',
				'uconfirm'=>'confirm_password',
			)
		);
		$formparts['personal_reg']=array(
			'title'=>'personal',
			'template'=>'app/app.form_part_personal_signup.html',
			'parts'=>array(
				'FirstName'=>'first_name',
				'LastName'=>'last_name',
				'Email'=>'email',
				'Birthdate'=>'dob',
				'Language'=>'language',
				'Address'=>'address',
				'Town'=>'town',
				'City'=>'city',
				'Country'=>'country',
				'PostCode'=>'post_code',
				'MobilePhone'=>'phone',
				'Sex'=>'gender',
				'meta_citizenship'=>'citizenship',
				'meta_date_began_kyudo'=>'date_began_kyudo',
				'meta_payment_method'=>'payment_method'
			)
		);
		$formparts['dojo']=array(
			'title'=>'dojo_and_form',
			'template'=>'app/app.form_part_dojo_form.html',
			'parts'=>array(
				'DojoID'=>'dojo',
				//'CurrentGrade'=>'grade',
				//'CGradedate'=>'grade_date',
				'zasha'=>'zasha',
			)
		);
		$ghp=[];
		foreach($this->GRADES as $i=>$v) $ghp['exam'.$i]=$v['OptionName'];
		$formparts['grade_history']=array(
			'title'=>'grade_history',
			'template'=>'app/app.form_part_exam_history.html',
			'parts'=>$ghp
		);
		$this->PARTS=$formparts;
		$this->PATTERNS=array(
			'signup'=>array('account','personal_reg','dojo','grade_history'),
		);
		$this->PATTERN_INFO=array(
			'signup'=>'Membership Form',
		);
	}	
	function get($what=false,$vars=false){
		switch($what){
			case 'select_locations':
				return $this->SELECT_LOCATIONS;
				break;
			case 'id': case 'all':
				$ref=($what==='id')?(int)$vars:'all';
				return $this->getData($ref);
				break;
			case 'dojo_data':
				return $this->getData($what,$vars);
				break;
			case 'edit':case 'view': case 'public': case 'review': case 'new':
				return $this->renderForm($what,$vars);
				break;
			case 'edit_rec':
				return $this->renderEditRecord($vars);
				break;
			case 'delete_rec':
			case 'delete_rec_now':
				return $this->renderDeleteRecord($what,$vars);
				break;
			case 'dojo':
			case 'dojo_code':
			case 'dojo_long':
				$x=($what==='dojo_long')?'long':'short';
				$res=issetcheck($this->DOJOS[$x],$vars,'???');
				if($what==='dojo_code'){
					$res=explode(',',$res);
					return $res[0];
				}
				return $res;
				break;
			case 'location':
				if($vars){
					return issetCheck($this->LOCATIONS,$vars);
				}
				return false;
				break;
			case 'locations':
				return $this->LOCATIONS;
				break;
			case 'grade':
			case 'grade_name':
				$res=issetcheck($this->GRADES,$vars);
				if($what==='grade_name'){
					return $res['OptionName'];
				}
				return $res;
				break;
			case 'current':
				return $this->FORM_REC;
				break;
			case 'pdf'://???
				$this->FORM_REC=$this->getData($vars);
				if($this->FORM_REC) $this->FORM_DATA=$this->FORM_REC['FormData'];
				return $this->renderForm($what);
				break;
			case 'download':
				$this->FORM_REC=$this->getData($vars);
				if($this->FORM_REC) $this->FORM_DATA=$this->FORM_REC['FormData'];
				return $this->renderPDF($this->FORM_REC,'D');
			case 'pdf_public':
				return $this->renderPDF($vars,'F');
				break;
			case 'club': case'club_info':
				return $this->getClubInfo($vars);
				break;
			case 'form_states':
				return $this->FORM_STATES;
				break;
			case 'recaptcha':
				return $this->recaptcha();
				break;
			
		}
	}
	private function updateFormData($data=[],$id=false){
		$skip=['token','mc_token','user_id','member_id'];
		$out=[];
		if($id){
			$rec=$this->getData($id);
			if($rec){
				$keys=array_keys($rec['FormData']);
				foreach($keys as $k){
					if(!in_array($k,$skip)){
						$val=(array_key_exists($k,$data))?$data[$k]:$rec['FormData'][$k];
						$out[$k]=$val;
					}				
				}
				return $out;			
			}else{
				$out=$data;
				foreach($skip as $k) unset($out[$k]);
			}
		}else{
			$out=$data;
			foreach($skip as $k) unset($out[$k]);
		}
		return $out;
	}
	function addForm($data=false,$debug=false){
		$fdata=issetCheck($data,'FormData',$data);		
		$fdata=$this->updateFormData($fdata);
		$save=[
			'Name'=>$fdata['FirstName'].' '.$fdata['LastName'],
			'Email'=>$fdata['Email'],
			'DojoID'=>$fdata['DojoID'],
			'FormData'=>compress($fdata),
			'UserID'=>0,
			'MemberID'=>0,
			'LogDate'=>date('Y-m-d H:i:s'),
			'Status'=>'submit'
		];
		$state=500;
		$chk=$this->SLIM->db->SignupLog->insert($save);
		$msg='Sorry, the form could not be submitted... Please check the details and try again.';
		if($chk){
			$state=200;
			$msg='Okay, the form has been submitted.';
		}
		return ['status'=>$state,'message'=>$msg];
	}
	function updateForm($data=false,$debug=false){
		$fdata=issetCheck($data,'FormData',$data);		
		$id=issetCheck($data,'ID');
		$fdata=$this->updateFormData($fdata,$id);
		$state=500;
		if(!$id){
			$msg='Sorry, no form ID was supplied...';
		}else{
			unset($data['ID'],$data['action']);			
			$rec=$this->SLIM->db->SignupLog->where('ID',$id);
			if(count($rec)==1){
				$status=issetcheck($data,'Status');
				$update=[
					'Name'=>$fdata['FirstName'].' '.$fdata['LastName'],
					'Email'=>$fdata['Email'],
					'DojoID'=>$fdata['DojoID'],
					'FormData'=>compress($fdata),
				];
				if($status) $update['Status']=$status;
				if($debug){
					preME($update,2);
					$this->SLIM->db_debug();
				}
				$chk=$rec->update($update);
				if(!$chk){
					if(!$this->SLIM->db_error) $chk=true;
				}
				if($chk){
					$state=200;
					$msg='Okay, the record has been updated.';
				}else{
					$msg='Sorry, there was a problem updating the record...';
				}				
			}else{
				$msg='Sorry, I could not find a record with that ID ['.$id.']...';
			}
		}
		return ['status'=>$state,'message'=>$msg];
	}
	function updateRecord($data=false,$debug=false){
		$id=issetCheck($data,'ID');
		$state=500;
		if(!$id){
			$msg='Sorry, no form ID was supplied...';
		}else{
			unset($data['ID'],$data['action']);
			$rec=$this->SLIM->db->SignupLog->where('ID',$id);
			if(count($rec)==1){
				$update=[
					'Name'=>$data['Name'],
					'Email'=>$data['Email'],
					'DojoID'=>(int)$data['DojoID'],
					'MemberID'=>(int)$data['MemberID'],
					'UserID'=>(int)$data['UserID'],
					'LogDate'=>date('Y-m-d H:i:s',strtotime($data['LogDate'])),
					'Status'=>$data['Status'],
				];
				if($debug){
					preME($update);
					$this->SLIM->db_debug;
				}
				$chk=$rec->update($update);
				if(!$chk){
					if(!$this->SLIM->db_error) $chk=true;
				}
				if($chk){
					$state=200;
					$msg='Okay, the record has been updated.';
				}else{
					$msg='Sorry, there was a problem updating the record...';
				}				
			}else{
				$msg='Sorry, I could not find a record with that ID ['.$id.']...';
			}
		}
		return ['status'=>$state,'message'=>$msg];
	}
	function set($what=false,$vars=false){
		switch($what){
			case 'form_data':case 'session_rec':
				$this->FORM_DATA=$vars;
				return true;
				break;
			case 'user'://user session - needed for public interface
				$this->PUBLIC_USER=$vars;
				return true;
				return ;
				break;
		}
	}
	
	private function getData($id=false,$ref=false){
		$data=[];
		$select=false;
		$db=$this->SLIM->db->SignupLog;
		if((int)$id){
			$recs=$db->where('ID',$id);
		}else if($id==='all'){
			$recs=$db->where('ID >=?',1);
			$select='ID,Name,Email,DojoID,LogDate,Status';
		}else if($id==='dojo_data'){
			$select='ID,Name,Email,DojoID,LogDate,Status';
			$recs=$db->where('DojoID',$ref);
		}else{
			return $data;
		}
		if($select) $recs->select($select);
		$rez=renderResultsORM($recs,'ID');
		if($rez && !in_array($id,['all','dojo_data'])){
			$rez=current($rez);
			$rez['FormData']=compress($rez['FormData'],false);
		}
		return $rez;		
	}
	private function deleteData($id){
		$res=['status'=>500,'message'=>'Sorry, I could not delete record #'.$id];
		$rec=$this->SLIM->db->SignupLog->where('ID',$id)->limit(1);
		if(count($rec)){
			$chk=$rec->delete();
			if($chk) $res=['status'=>200,'message'=>'Okay, record #'.$id.' has been deleted.'];
		}
		return $res;
	}
	private function renderEditRecord($ref){
		$this->FORM_REC=$this->getData($ref);
		if($this->FORM_REC){
			$cancel='Cancel';//$this->SLIM->language->getStandard('cancel');
			$submit='Update';//$this->SLIM->language->getStandard('submit');
			$url=$this->PERMLINK;
			$form='<label>Name <input type="text" name="Name" value="'.$this->FORM_REC['Name'].'"/></label>';
			$form.='<label>Email <input type="text" name="Email" value="'.$this->FORM_REC['Email'].'"/></label>';
			$form.='<label>Date & Time <input type="datetime-local" name="LogDate" value="'.$this->FORM_REC['LogDate'].'"/></label>';
			$form.='<label>Dojo <select name="DojoID">'.$this->renderForm_select('dojo',$this->FORM_REC['DojoID']).'</select></label>';
			$form.='<label>Member ID <small>set on approval</small> <input type="number" name="MemberID" value="'.$this->FORM_REC['MemberID'].'"/></label>';
			$form.='<label>User ID <small>set on approval</small> <input type="number" name="UserID" value="'.$this->FORM_REC['UserID'].'"/></label>';
			$form.='<label>Status <select name="Status">'.$this->renderForm_select('status',$this->FORM_REC['Status']).'</select></label>';
			$hidden='<input type="hidden" name="action" value="update_signup_rec"/>';
			$hidden.='<input type="hidden" name="ID" value="'.$ref.'"/>';
			$delete=($this->FORM_REC['Status']!=='approved')?'<button type="button" class="button button-maroon loadME" data-ref="'.$url.'delete_rec/'.$ref.'" type="submit"><i class="fi-x"></i> Delete</button>':'';
			$controls='<div class="button-group expanded"><button type="button" class="button button-gray" data-close><i class="fi-x-circle"></i> '.$cancel.'</button>'.$delete.'<button class="button button-olive" type="submit"><i class="fi-check"></i> '.$submit.'</button></div>';
			$output=msgHandler('Note that changes made here are for this form record only and <strong>will not</strong> affect any other records in the database.',false,false);
			$output.='<form id="form1" class="ajax_Form" method="post" action="'.$url.'"><div style="max-height:45vh;overflow-y:auto;">';
			$output.=$hidden;
			$output.=$form;
			$output.='</div>';
			$output.=$controls;
			$output.='</form>';
		}else{
			$output=msgHandler('Sorry, no record found with ID#'.$ref.'...',false,false);
		}
		return $output;		
	}
	private function renderDeleteRecord($action=null,$ref=0){
		$this->FORM_REC=$this->getData($ref);
		if($this->FORM_REC){
			if($action==='delete_rec_now'){
				$r=$this->deleteData($ref);
				$content=$r['message'];
			}else if($this->FORM_REC['Status']==='approved'){
				$club=$this->getClubInfo($this->FORM_REC['DojoID']);
				$content='<div class="callout warning text-center"><p class="h4">You cannot delete application record #'.$ref.' as it has already been approved.</p><p><strong>Name:</strong> '.$this->FORM_REC['Name'].'<br/><strong>Dojo:</strong> '.$club['ClubName'].'<br/><strong>Date:</strong> '.$this->FORM_REC['LogDate'].'<br/><strong>Status:</strong> '.$this->FORM_REC['Status'].'<br/></p>';
				$content.='<div class="button-group expanded"><button type="button" class="button secondary" data-close><i class="fi-x-circle"></i> Close</button></div></div>';
			}else{
				$club=$this->getClubInfo($this->FORM_REC['DojoID']);
				$content='<div class="callout warning text-center"><p class="h4">Do you want to delete this application record #'.$ref.'?</p><p><strong>Name:</strong> '.$this->FORM_REC['Name'].'<br/><strong>Dojo:</strong> '.$club['ClubName'].'<br/><strong>Date:</strong> '.$this->FORM_REC['LogDate'].'<br/><strong>Status:</strong> '.$this->FORM_REC['Status'].'<br/></p>';
				$content.='<div class="button-group expanded"><button type="button" class="button secondary" data-close><i class="fi-x-circle"></i> No, maybe later</button><button type="button" class="button button-red gotoME" data-ref="'.$this->PERMLINK.'delete_rec_now/'.$ref.'"><i class="fi-x"></i> Yes, do it now!</button></div></div>';
			}
		}else{
			$output=msgHandler('Sorry, no record found with ID#'.$ref.'...',false,false);
		}
		return ['title'=>'Delete Record?','content'=>$content];		
	}
	private function renderForm($mode=false,$ref=false){
		$loaded=false;
		if($this->FORM_REC && $this->FORM_DATA){
			$loaded=($this->FORM_REC['ID']==$ref)?true:false;
		}
		if(!$loaded && (int)$ref){
			$this->FORM_REC=$this->getData($ref);
			if($this->FORM_REC) $this->FORM_DATA=$this->FORM_REC['FormData'];
		}
		$this->FORM=[];
		if(in_array($mode,['edit','view','new'])){
			$this->USE_CAPTCHA=false;
		}else{//public render
			if(issetCheck($ref,'uname')) $this->FORM_DATA=$ref;
		}
		switch($mode){
			case 'view':case 'review':
				return $this->renderViewForm($mode);
				break;
			case 'edit':
				return $this->renderEditForm($ref);
				break;
			case 'new'://for use via admin area
				$this->PUBLIC_USER['token']='admin_form';
				return $this->renderPublicForm();
				break;
			default:
				return $this->renderPublicForm();
		}				
	}	
	private function renderPublicForm(){
		foreach($this->PATTERNS['signup'] as $part_id){
			$this->renderPart($part_id);
		}
		$this->FORM=implode('',$this->FORM);
		$cancel=$this->SLIM->language->getStandard('cancel');
		$submit=$this->SLIM->language->getStandard('submit');
		$url=$this->PERMLINK;
		$captcha=$this->recaptcha();//$this->getCaptcha();
		$hidden='<input type="hidden" name="action" value="submit_signup"/>';
		$hidden.='<input type="hidden" name="token" value="'.$this->PUBLIC_USER['token'].'"/>';
		$controls='<div class="button-group expanded"><button class="button button-red gotoME" data-ref="'.$url.'/cancel"><i class="fi-x"></i> '.$cancel.'</button> <button class="button button-olive" type="submit"><i class="fi-check"></i> '.$submit.'</button></div>';
		$output='<div class="callout"><form id="form1" method="post" action="'.$url.'">';
		$output.=$hidden;
		$output.=$this->FORM;
		$output.=($this->USE_CAPTCHA)?$captcha['cap'].$controls:$controls;
		$output.='</form></div>';
		if($this->USE_CAPTCHA){
			$this->SLIM->assets->set('js',$captcha['js'],'recaptcha');
			$this->SLIM->assets->set('script',$captcha['script'],'recaptcha');
			if($captcha['css']) $this->SLIM->assets->set('site_css',$captcha['css'],'recaptcha');
		}
		return $output;
	}
	private function renderEditForm($ref=false){
		foreach($this->PATTERNS['signup'] as $part_id){
			$this->renderPart($part_id);
		}		
		$this->FORM=implode('',$this->FORM);
		$token=issetCheck($this->FORM_DATA,'token','');
		$cancel='Cancel';//$this->SLIM->language->getStandard('cancel');
		$submit='Update';//$this->SLIM->language->getStandard('submit');
		$url=$this->PERMLINK;
		$hidden='<input type="hidden" name="action" value="update_signup"/>';
		$hidden.='<input type="hidden" name="token" value="'.$token.'"/>';
		$hidden.='<input type="hidden" name="ID" value="'.$ref.'"/>';
		$controls='<div class="button-group expanded"><button type="button" class="button button-gray" data-close><i class="fi-x"></i> '.$cancel.'</button> <button class="button button-olive" type="submit"><i class="fi-check"></i> '.$submit.'</button></div>';
		$output='<form id="form1" class="ajax_Form" method="post" action="'.$url.'"><div style="max-height:55vh;overflow-y:auto;">';
		$output.=$hidden;
		$output.=$this->FORM;
		$output.='</div>';
		$output.=$controls;
		$output.='</form>';
		return $output;
	}
	
	private function renderPart($part_id=false,$for_form=true){
		$part=issetCheck($this->PARTS,$part_id);
		if($part){
			$tpl=file_get_contents(APP.'templates/'.$part['template']);
			$disable='';
			if($part_id==='grade_history'){$tpl=$this->renderHistoryInputs($tpl,$part['parts']);}
			foreach($part['parts'] as $i=>$v){
				if($part_id==='grade_history'){
					$rp['member_'.$i.'_label']=$this->SLIM->language->getStandard($v);
					$tmp=$this->prepFormData($i,$for_form);
					$rp['member_'.$i.'_date']=issetCheck($tmp,'date');
					$rp['member_'.$i.'_options']=issetCheck($tmp,'location');
				}else if(strpos($i,'metax_')!==false){
					$test=$this->prepFormData($i,$for_form);
					preME([$i,$v,$for_form,$test],2);
				}else{
					$rp['member_'.$v.'_label']=$this->SLIM->language->getStandard($v);
					$rp['member_'.$v.'_disabled']=$disable;
					$rp['member_'.$v]=$this->prepFormData($i,$for_form);
				}
			}
			$title=$this->SLIM->language->getStandard($part['title']);
			if($for_form){
				$tpl=replaceMe($rp,$tpl);
				$tpl=str_replace('{section_title}',$title,$tpl);
				$this->FORM[$part_id]='<section>'.$tpl.'</section>';
			}else{
				$this->FORM[$part_id]=$rp;
			}
		}
	}
	private function prepFormData($field,$for_form=false,$disabled=false,$as_form=false){
		$rec=$this->FORM_DATA;
		$value=issetCheck($rec,$field);
		switch($field){
			//selects
			case 'DojoID':case 'zasha':case 'Language':case 'CurrentGrade':
				$out=$this->renderForm_select($field,$value,$for_form,$field,$disabled);
				break;
			case 'Sex':
				$out=$this->renderForm_select('gender',$value,$for_form,$field,$disabled);
				break;
			case 'shinsa':
				$out=$this->renderForm_select('shinsa',$value,$for_form,$field,$disabled);
				break;
			//textarea
			case 'comments': case 'notes':
				$out=($as_form)?$this->renderForm_textarea($field,$value,$disabled,$for_form):$value;
				break;
			case 'dob': case 'Birthdate': case 'CGradedate':
				$out=($as_form)?$this->renderForm_input($field,$value,'date',$disabled):$value;
				break;
			case 'upass': case 'password':
				if($as_form){
					$out=$this->renderForm_input($field,$value,'password',$disabled);
				}else{
					$out=$value;
				}
				break;
			default:
				$out=($as_form)?$this->renderForm_input($field,$value,'text',$disabled):$value;
				if(strpos($field,'exam')===0){
					//exam history location options
					if($for_form){
						if(!is_array($out)) $out=['date'=>$out,'location'=>0];
						if($this->SELECT_LOCATIONS){
							$out['location']=$this->renderForm_select('location',$out['location']);
						}else{
							$out['location']=(is_array($value))?$value['location']:$value;
						}
					}else{
						if(!$out) $out=[];
						if($this->SELECT_LOCATIONS){
							$out['location']=issetCheck($this->LOCATIONS,0);
						}else{
							$out['location']=(is_array($value))?$value['location']:$value;
						}
					}
				}else if(strpos($field,'meta_')===0){
					$tf=str_replace('meta_','',$field);
					$value=(isset($rec['meta']))?issetCheck($rec['meta'],$tf):'';
					if($field==='meta_payment_method'){
						$out=$this->renderForm_select('payment_method',$value,$for_form,$field,$disabled);
					}else{
						$out=($as_form)?$this->renderForm_input($field,$value,'text',$disabled):$value;
					}
				}
		}
		return $out;		
	}
	private function renderHistoryInputs($tpl,$parts){
		$his='';
		$ct=$cols=0;
		$chk=key($parts);
		if($chk!=='exam1') $parts=array_reverse($parts);
		foreach($parts as $i=>$v){
			$loc=($this->SELECT_LOCATIONS)?'<select name="'.$i.'[location]">{member_'.$i.'_options}</select>':'<input type="text" placeholder="location" value="{member_'.$i.'_options}" name="'.$i.'[location]"/>';
			$his.='<fieldset><legend>{member_'.$i.'_label}</legend>'.$loc.'<input type="date" value="{member_'.$i.'_date}" name="'.$i.'[date]"/></fieldset>';
			$ct++;
			if($ct==3){
				$tpl=str_replace('{history_col_'.$cols.'}',$his,$tpl);
				$his='';
				$cols++;
				$ct=0;
			}
		}
		return $tpl;
	}
	private function renderForm_input($name=false,$value='',$type='text',$disabled=false,$for_form=true){
		$out=false;
		if($disabled) $disabled='disabled="disabled"';
		if($name){
			if($for_form){
				$out='<input name="'.$name.'" value="'.$value.'" type="'.$type.'" '.$disabled.'/>';
			}else{
				$out=$value;
			}
		}
		return $out;
	}
	private function renderForm_textarea($name=false,$value='',$disabled=false,$for_form=true){
		$out=false;
		if($disabled) $disabled='disabled="disabled"';
		if($name){
			if($for_form){
				$out='<textarea name="'.$name.'" '.$disabled.'>'.$value.'</textarea>';
			}else{
				$out=$value;
			}
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
				$data=$this->DOJOS['long'];
				$opt='<option value="0">No Dojo?</option>';
				break;
			case 'gender': case 'Sex':
				$data=$this->SLIM->Options->get('gender');
				break;
			case 'language': case 'Language':
				$data=$this->SLIM->language->get('_LANGNAMES',$this->LANG);
				break;
			case 'zasha':
				$data=$this->ZASHA;
				break;
			case 'location':
				$data=$this->LOCATIONS;
				$opt='<option value="0"> - </option>';
				break;
			case 'status':
				$data=[];
				foreach($this->FORM_STATES as $i=>$v)$data[$i]=ucME($i);
				break;	
			case 'payment_method':
				$data=$this->SLIM->Options->get('payment_method');
				break;		
			default:
				$data=false;				
		}
		if(is_array($data)){
			if(!$for_form){
				$label=($label_key)?issetCheck($v,$label_key,'???'):'?!?';
				$opt=issetCheck($data,$id);
				if($what==='grades') $opt=$opt[$label_key];
			}else{
				foreach($data as $i=>$v){
					$label=($label_key)?issetCheck($v,$label_key,'???'):$v;
					$selected=($id==$i)?'selected="selected"':false;
					switch($what){
						case 'grades':
							if($v['sortkey']>0) $opt.='<option value="'.$i.'" '.$selected.'>'.$label.'</option>';
							break;
						case 'location':
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
	private function renderPDF($log=false,$render='F'){
		if(!$this->FORM_DATA){
			$this->FORM_DATA=issetCheck($log,'FormData',[]);
			if(!$this->FORM_DATA) return '';
		}
		$down['html']=$this->renderViewForm(false,true);
		$down['title']=$this->SLIM->language->getStandard('membership_form');// pdf title for header
		$down['user']=$log['Name'];
		$down['member_id']=($log['MemberID'])?$log['MemberID']:'-';
		$down['user_id']=($log['UserID'])?$log['UserID']:'-';
		$down['date']=validDate($log['LogDate'],'F j, Y');
		$down['reference_code']='signup_'.$log['ID'];
		$down['sub_title']='Status: '.$log['Status'];
		$down['logo']=array('logo'=>$this->PDF_LOGO,'text'=>$this->SLIM->EmailParts['pdf_header']);// array of address & image for pdf header
		$down['docname']='membership_form_'.$log['ID'].'.pdf'; // for downloading
		
		if($render==='F'){
			$down['docname']=CACHE.'pdf/membership_form_'.$log['ID'].'.pdf';// to file
		}
		$down['render_type']=$render;
		$r=$this->SLIM->PDF->render($down,'signup');
		return $down['docname'];
	}
	
	private function renderViewForm($mode=false,$download=false){
		if(!$this->FORM_DATA){
			return msgHandler('Sorry, I can\'t find any form details. Please try again.',false,false);
		}else if(!isset($this->FORM_DATA['FirstName'])){
			$frm=$this->FORM_DATA;
			$frm['error']='Sorry, the form details seem to be invalid';
			file_put_contents(CACHE.'log/signup_form_error_'.date('ymd-Hi').'.log',json_encode($frm));
			return msgHandler('Sorry, the form details seem to be invalid.<br/>Please try again.',false,false);
		}
		$_tpl=($download)?'app/app.signup_form_pdf.html':'app/app.signup_form_view.html';
		$tpl=file_get_contents(TEMPLATES.$_tpl);		
		$member_name=$this->FORM_DATA['FirstName'].' '.$this->FORM_DATA['LastName'];
		$member_age=getAge($this->FORM_DATA['Birthdate']);
		$member_age_label=$this->SLIM->language->getStandard('age');
		//fix dates for viewing
		$dt=['CGradedate','Birthdate'];
		foreach($dt as $k){
			$tmp_k=trim(issetCheck($this->FORM_DATA,$k,''));
			if($tmp_k!=='') $this->FORM_DATA[$k]=validDate($tmp_k,'F j, Y',$this->FORM_DATA['Language']);
		}
		//fix password for viewing
		if($mode==='review'||$mode==='view'){
			$value=$this->FORM_DATA['upass'];
			$l=strlen($value)-1;
			$tmp='';
			for($x=0;$x<=$l;$x++){
				if($x==0 || $x==$l){
					$tmp.=$value[$x];
				}else{
					$tmp.='*';
				}
			}
			$this->FORM_DATA['upass']=$tmp;
		}

		//submitted form parts
		foreach($this->PATTERNS['signup'] as $part_id){
			$this->renderPart($part_id,false);
			$tpl=replaceME($this->FORM[$part_id],$tpl);
		}
		//add review form parts			
		if($download){
			$this->FORM['section_label_grade_history']=$this->SLIM->language->getStandard('grade_history');
			$this->FORM['section_label_account']=$this->SLIM->language->getStandard('login');
			$this->FORM['section_label_personal_reg']=$this->SLIM->language->getStandard('personal');
			$this->FORM['section_label_dojo']=$this->SLIM->language->getStandard('dojo_and_form');
			//fix exam history dates
			for($x=1;$x<=9;$x++){
				$val=trim(issetCheck($this->FORM['grade_history'],'member_exam'.$x.'_options'));
				if($val!==''){
					$date=issetCheck($this->FORM['grade_history'],'member_exam'.$x.'_date','-');
					if($date!=='-') $this->FORM['grade_history']['member_exam'.$x.'_date']=validDate($date,'F j, Y',$this->FORM_DATA['Language']);
				}
			}
			return $this->FORM;
		}
		$form['section_label_login']=$this->SLIM->language->getStandard('login');
		$form['section_label_personal']=$this->SLIM->language->getStandard('personal');
		$form['section_label_dojo']=$this->SLIM->language->getStandard('dojo_and_form');
		$form['member_reg_date_label']=$this->SLIM->language->getStandard('registration_date');
		$form['section_label_exam_history']=$this->SLIM->language->getStandard('grade_history');
		$form['title']=$this->SLIM->language->getStandard('membership_form');
		$form['form_url']=$this->PERMLINK;
		$form['edit_url']=$this->PERMLINK;
		$form['cancel_url']=$this->PERMLINK.'/cancel';
		$form['confirm_url']=$this->PERMLINK.'/confirmed';

		//exam history
		$eh='';
		for($x=1;$x<=9;$x++){
			$val=issetCheck($this->FORM['grade_history'],'member_exam'.$x.'_date');	
			if(trim($val)!==''){
				$cls='class="text-navy"';
				$date=validDate($val,'F j, Y',$this->FORM_DATA['Language']);
				$val=issetCheck($this->FORM['grade_history'],'member_exam'.$x.'_options','???');	
				$eh.='<tr><td>'.$this->GRADES[$x]['OptionName'].'</td><td '.$cls.'>'.$val.' / '.$date.'</td></tr>';
			}
		}
		$form['member_exam_history']=$eh;
		//finalize
		$content=replaceME($form,$tpl);
		if($download || $mode==='view'){
			return $content;
		}else{
			$edit_form=$this->SLIM->language->getStandard('edit');
			$confirm=$this->SLIM->language->getStandard('confirm');
			if($mode==='edit'){
				$content='<h3>Edit Form</h3><div class="callout">'.$content.'<div class="button-group expanded"><button class="button gotoME" data-ref="'.$form['edit_url'].'"><i class="fi-pencil"></i> '.$edit_form.'</button> <button class="button button-olive gotoME" data-ref="'.$form['confirm_url'].'"><i class="fi-check"></i> '.$confirm.'</button></div></div>';
			}else{
				$content='<h3>'.$this->SLIM->language->getStandard('review_details').'</h3><div class="callout">'.$content.'<div class="button-group expanded"><button class="button gotoME" data-ref="'.$form['edit_url'].'"><i class="fi-pencil"></i> '.$edit_form.'</button> <button class="button button-olive gotoME" data-ref="'.$form['confirm_url'].'"><i class="fi-check"></i> '.$confirm.'</button></div></div>';
			}
			return $content;
		}
	}
	private function getCaptcha($what=false,$answer=false){
		if(!$this->USE_CAPTCHA) return false;
		$cp=new mathcap($this->SLIM);
		$session_name='contact';
		switch($what){
			case 'check':
				$out=$cp->check($answer,$session_name);
				break;
			case 'check_token':
				$out=$cp->check_token($answer,$session_name);
				break;
			default:
				$out=['cap'=>$cp->generate($session_name),'script'=>$cp->formScript('form1')];
		}
		return $out;
	}
	private function recaptcha(){
		if(!$this->USE_CAPTCHA) return false;
		$cp=new recaptcha_v3();
		$rsp=$cp->Process();
		if(isset($rsp['status'])){
			if(!$rsp['status']) file_put_contents(CACHE.'log/recaptcha_fail_'.time().'.log',$rsp['message']);
		}
		return $rsp;
	}
	private function getClubInfo($ref=0){
		if($ref){
			$rec=$this->SLIM->db->ClubInfo->where('LocationID',$ref);
			$rec=renderResultsORM($rec);
			if($rec) return current($rec);
		}
		return ['ClubID'=>0,'ClubName'=>'???','Leader'=>'???','Email'=>'???'];
	}
	function checkGrade($data=[]){
		//set to mudan if no grade found
		$chk=0;
		for($x=1;$x<9;$x++){
			$chk=issetCheck($data['exam'.$x],'location',0);
			if($chk>0) break;
		}
		if(!$chk){
			$date=date('Y-m-d');
			$loc=999;
			if(isset($data['meta'])){
				$start=issetCheck($data['meta'],'date_began_kyudo');
				if(trim($start)!=='') $date=$start;
			}
			$data['exam1']=['location'=>$loc,'date'=>$date];
		}
		return $data;		
	}

}
