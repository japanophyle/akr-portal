<?php

class slimPublicForms{
	private $SLIM;
	private $USER;
	private $AJAX;
	private $ROUTE;
	private $PRODUCTS;
	private $DOJOS;
	private $GRADES;
	private $ZASHA;
	private $LANGUAGES;	
	private $LANG;
	private $CATEGORIES;
	private $CAT=3;//default category
	private $PDF_LOGO;
	private $DEPARTS;
	private $MEMBERS_DB;
	private $EVENT;
	private $POST;

	private $VALIDS=array(
		'member'=>array('MemberID','FirstName','LastName','Email','Address','Town','City','PostCode','Country','LandPhone','MobilePhone','Birthdate','Sex','Language','zasha'),
		'dojo'=>array('Dojo','DateJoined','CGradeName','CGradedate','CGradeLoc1')
	);
	private $OUTPUT;
	public $PERMBACK;
	public $MEMBER_REC;
	public $EVENT_ID;
	public $SESSION_REC;

	function __construct($slim=null){
		if(!$slim){
		  throw new Exception(__METHOD__.': no slim object!!');
		}
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		//check for temp login
		if($this->USER['access']>=25){
			$tmp_login=slimSession('get','temp_login');
			if($tmp_login) $this->USER=$tmp_login;
		}
		$this->ZASHA=$slim->options->get('zasha');
		$this->DEPARTS=$slim->options->get('departs');
		$this->LANGUAGES=$slim->language->get('_LANGS');
		$this->PDF_LOGO=$slim->options->get('pdf_logo');
		$this->LANG=$slim->language->get('_LANG');
		$this->AJAX=$slim->AppVars->get('ajax');
		$this->ROUTE=$slim->AppVars->get('route');
		$this->initSesseionRec();
		$this->initGrades();
		$this->initDojos();
		$this->initProducts();
		$this->PERMBACK=URL.'page/';
		
		//$this->initMember();
	}
	private function initSesseionRec(){
		$data=issetCheck($this->USER,'reg_form',[]);
		if($data){
			$data['category']=(int)	$data['category'];
			$data['member_id']=(int)$data['member_id'];
			$data['event_id']=(int) $data['event_id'];
			//hack for testing
			//if($data['event_id']==0) $data['event_id']=105;
			//end hack
			$this->EVENT_ID=$data['event_id'];
			$this->SESSION_REC=$data;
		}		
	}
	function render($what=false,$vars=false){
		switch($what){
			case 'view_my_form':
				return $this->renderMyForm($vars);
				break;
			case 'review_form':
				return $this->SLIM->EventForms->get('review_form',$vars);
				break;
			case 'edit_review_form':
				return $this->SLIM->EventForms->get('review_form_edit',$vars);
				break;
			case 'confirmed':
				return $this->SLIM->EventForms->get('confirmed',$vars);
				break;
			default:
				return $this->renderOutput(array('title'=>'???','content'=>msgHandler('Sorry, bad request...'.$what,false,false)));
		}
	}
	private function renderOutput($output=false,$form=false){
		$p['content']='<div class="tabs-content">{content}</div>';
		$p['fcontrols']='<div class="modal-footer">div class="button-group expanded">{fcontrols}</div></div>';
		$out='';
		if($this->AJAX && is_array($output)){
			$content='<div class="modal-body">';
			if($form){
				$content.='<form id="form1" method="post" action="'.$form['form_url'].'">';
				$content.=$form['hidden'];
			}
			foreach($p as $i=>$v){
				$content.=str_replace('{'.$i.'}',$output[$i],$v);
			}
			if($form) $content.='</form>';
			$content.='</div>';
			$out=renderCard_active($this->SLIM->language->getStandard('registration_details'),$content,$this->SLIM->closer);
		}else{
			foreach($p as $i=>$v) $out.=$output[$i];
			if($form){
				$content='<form id="form1" method="post" action="'.$form['form_url'].'">';
				$content.=$form['hidden'];
				$content.=$out;
				$out=$content.'</form>';
			}
		}
		return $out;
	}
	function renderPost($post){
		//preME($post,2);
		$action=issetCheck($post,'action');
		unset($post['action']);
		$this->POST=$post;
		switch($action){
			case 'registration_form':
				setMySession('reg_form',$post);
				$url=$this->PERMBACK.'event/register_confirm';
				$msg=$this->SLIM->language->getStandardPhrase('confirm_details');
				$rsp=array('status'=>500,'message'=>$msg,'type'=>'redirect','url'=>$url);
				break;
			case 'submit_reg_review':
				$old=compress($post['reg_form'],false);
				foreach($post as $i=>$v){
					if($i!=='reg_form')	$old[$i]=$v;
				}
				setMySession('reg_form',$old);
				$url=$this->PERMBACK.'event/register_confirm';
				$msg=$this->SLIM->language->getStandardPhrase('confirm_details');
				$rsp=array('status'=>500,'message'=>$msg,'type'=>'redirect','url'=>$url);
				break;
			default:
				$url=$this->PERMBACK;
				$msg=$this->SLIM->language->getStandardPhrase('no_can_do');
				$rsp=array('status'=>500,'message'=>$msg,'type'=>'message','url'=>$url);
		}
		if($this->AJAX){
			jsonResponse($rsp);
			die;
		}else{
			setSystemResponse($url,$msg);
		}
	}
	private function initGrades(){
		$grades=$this->SLIM->options->get('grades');
		$grades=array_reverse($grades,true);
		$this->GRADES=rekeyArray($grades,'OptionValue');
	}
	private function initDojos(){
		$d=$this->SLIM->options->get('dojos');
		foreach($d as $i=>$v){
			$dojo[$i]=$v['LocationName'];
			if($c=issetCheck($v,'LocationCountry')) $dojo[$i].=', '.$c;
		}
		$this->DOJOS=$dojo;
	}	
	private function initProducts(){
		$this->CATEGORIES=$this->SLIM->options->get('product_types');
		$prods=$this->SLIM->db->Items()->where('ItemType','product')->order('ItemTitle');
		$prods=renderResultsORM($prods,'ItemID');
		$this->PRODUCTS=$prods;
	}
	
	private function initMembersDB(){
		$this->MEMBERS_DB = new MembersMapper($this->SLIM->db,'array');
		$this->MEMBERS_DB->OPTIONS=$this->SLIM->options;		
	}
	
	private function initMember(){
		if($this->USER['id']>0){
			$this->getMember('id',$this->USER['MemberID']);
			if($this->MEMBER_REC){
				$this->SESSION_REC['FirstName']=$this->MEMBER_REC['FirstName'];
				$this->SESSION_REC['LastName']=$this->MEMBER_REC['LastName'];
				$this->SESSION_REC['DojoID']=$this->MEMBER_REC['DojoID'];
				$this->SESSION_REC['Birthdate']=validDate($this->MEMBER_REC['Birthdate']);
				$this->SESSION_REC['CurrentGrade']=$this->MEMBER_REC['CurrentGrade'];
				$this->SESSION_REC['CGradedate']=validDate($this->MEMBER_REC['CGradedate']);
				$this->SESSION_REC['zasha']=$this->MEMBER_REC['zasha'];
				$this->SESSION_REC['Language']=$this->MEMBER_REC['Language'];
				$this->SESSION_REC['Email']=$this->MEMBER_REC['Email'];			
			}
		}
	}
	
	private function getMember($what=false,$vars){
		if(!$this->MEMBERS_DB) $this->initMembersDB();
		switch($what){
			case 'id':
				$r=$this->MEMBERS_DB->getMember((int)$vars);
				if($r['status']==200){
					$this->MEMBER_REC=$r['data'];
				}
				break;
			case 'email':
				$r=$this->MEMBERS_DB->getMembers('email',$vars);
				if(count($r)>0){
					$this->MEMBER_REC=current($r);
				}
				break;
			case 'name':
				$args=array('FirstName'=>$vars['prenom'],'LastName'=>$vars['nom']);
				$r=$this->MEMBERS_DB->getMembers('name',$args);
				if(count($r)>0){
					$this->MEMBER_REC=$r;
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
				$out=false;
		}
		return $out;				
	}
	
	private function getEventForm($log_id=0){
		$form=false;
		if($log_id){
			$DB=$this->SLIM->db->FormsLog();
			$rec=$DB->where('EventLogID',$log_id)->and('MemberID',$this->USER['MemberID']);
			if(count($rec)>0){
				$res=renderResultsORM($rec);
				$rec=current($res);
				$form=compress($rec['FormData'],false);
			}
		}
		return $form;
	}

	private function getEvent($event_id=0){
		$event=false;
		if(!$event_id) $event_id=$this->EVENT_ID;
		$rec=$this->SLIM->db->Events()->where('EventID',$event_id);
		$event=renderResultsORM($rec);
		if(is_array($event)) $event=$event[0];
		return $event;
	}

	private function reviewTemplate(){
		$tpl='<div id="member" class="callout" ><div class="grid-x grid-margin-x"><div class="cell auto">{form_content}</div></div></div>';
		foreach($this->OUTPUT as $i=>$v){
			$tpl=str_replace('{member_'.$i.'}',$v,$tpl);
		}
		return $tpl;		
	}

	private function getSelectOptions($key,$selected=false){
		$data=$opts=false;
		$ov='i';
		switch($key){
			case 'Gender':
				$data=$this->SLIM->options->get('gender');
				$ov='v';
				break;
			case 'Language':
				$data=$this->LANGUAGES;
				break;
			case 'Form':
				$data=$this->ZASHA;
				break;
		}
		if($data){
			foreach($data as $i=>$v){
				$sel=($selected==$v)?'selected':'';
				$val=($ov==='i')?$i:$v;
				$opts.='<option value="'.$val.'" '.$sel.'>'.$v.'</option>';
			}
			$out='<select name="'.$key.'">'.$opts.'</select>';
		}else{
			$out='<span class="faux-input label alert">No options for "'.$key.'"</span>';
		}
		return $out;		
	}
	private function renderMyForm($form_id=0){
		if(!$form_id) $form_id=(int)issetCheck($this->ROUTE,2);
		$download=issetCheck($this->ROUTE,3);
		$download=($download==='download')?true:false;
		if($form_id){
			$events=$this->getEventLog();
			$this->EVENT=issetCheck($events,$form_id);
			$this->initProducts();
			$data=$this->getEventForm($form_id);
			if($data){
				$content=$this->renderReviewForm($data,$download);
			}else{
				$content=msgHandler($this->SLIM->language->getStandardPhrase('no_form_found'),false,false);
			}
		}else{
			$content=msgHandler($this->SLIM->language->getStandardPhrase('no_form_id'),false,false);
		}
		if($download){
			$down['html']=$content;
			$down['title']=$this->SLIM->language->getStandard('registration_form');// pdf title for header
			$down['sub_title']='Event: '.$this->EVENT['EventName'].' on '.validDate($this->EVENT['EventDate'],'F j, Y');
			$down['user']=$this->USER['name'];// user name for pdf header
			$down['logo']=array('logo'=>$this->PDF_LOGO,'text'=>"Association Helvétique\nDe Kyudo,\n2500 BIEL/BIENNE");// array of address & image for pdf header
			$down['date']=time();// date being published for pdf header
			$down['reference_code']='WFRM_'.$form_id; // fadditional text?? for pdf header
			$down['docname']=slugMe($this->EVENT['EventName'].'_form.pdf'); // for downloading
			$down['render_type']='D';
			$this->SLIM->PDF->render($down);
			die;			
		}else if($this->AJAX){
			$content='<div class="modal-body"><div class="tabs-content">'.$content.'</div><div class="modal-footer"><div class="button-group expanded"><button class="button small button-dark-blue gotoME" data-ref="'.$this->PERMBACK.'view_my_form/'.$form_id.'/download"><i class="fi-download"></i> '.$this->SLIM->language->getStandard('download').'</button></div></div></div>';
			echo renderCard_active($this->SLIM->language->getStandard('registration_details'),$content,$this->SLIM->closer);
			die;
		}else{
			return $content;
		}
	}
	private function renderReviewForm($review=false){
		//moved to event forms
		$form=$this->prepReviewData();
		$tpl=file_get_contents(APP.'templates/app.form_view.html');
		$out['title']=$this->SLIM->language->getStandard('review_details');
		$out['content']=replaceME($form,$tpl);
		$edit_form=$this->SLIM->language->getStandard('edit');
		$confirm=$this->SLIM->language->getStandard('confirm');
		$out['fcontrols']='<div class="button-group expanded"><button class="button gotoME" data-ref="'.$form['edit_url'].'"><i class="fi-pencil"></i> '.$edit_form.'</button> <button class="button button-olive gotoME" data-ref="'.$this->PERMBACK.'event/confirmed"><i class="fi-check"></i> '.$confirm.'</button></div>';
		return $this->renderOutput($out);
	}
	private function renderReviewForm_edit(){
		$content=$this->prepReviewData(true);
		$form['form_url']=$content['form_url'].'event';
		$form['hidden']='<input type="hidden" name="action" value="submit_reg_review"/>';
		$out['title']=$this->SLIM->language->getStandard('edit_details');
		$lang['en']='Make your changes then click the "Submit" button to continue.';
		$lang['de']='Nehmen Sie Ihre Änderungen vor und klicken Sie auf "Senden", um fortzufahren.';
		$lang['fr']='Apportez vos modifications puis cliquez sur le bouton "Envoyer" pour continuer.';
		$note=issetCheck($lang,$this->LANG,$lang['en']);
		$tpl=file_get_contents(APP.'templates/app.form_view_edit.html');
		$out['content']=msgHandler('<i class="fi-megaphone"></i> '.$note,'primary',false);
		$out['content'].=replaceME($content,$tpl);
		$cancel=$this->SLIM->language->getStandard('cancel');
		$submit=$this->SLIM->language->getStandard('submit');
		$out['fcontrols']='<div class="button-group expanded"><button class="button button-red gotoME" data-ref="'.$form['edit_url'].'"><i class="fi-x"></i> '.$cancel.'</button> <button class="button button-olive" type="submit"><i class="fi-check"></i> '.$submit.'</button></div>';
		return $this->renderOutput($out,$form);
	}


	private function findFormProduct(){
		$prod_id=issetCheck($this->SESSION_REC,'product_ref');
		$prod=issetCheck($this->PRODUCTS,$prod_id);
		if($prod) $this->CAT=$prod['ItemGroup'];
		return $prod;
	}

	private function prepReviewData($for_form=false){
		if($this->SESSION_REC['member_id']>0){
			$this->initMember();
		}
		$prod=$this->findFormProduct();
		$event=$this->getEvent($this->SESSION_REC['event_id']);
		$price=((int)$prod['ItemPrice']/100);
		$_paid=(int)issetCheck($this->SESSION_REC,'member_paid',0);
		$paid=($_paid)?($_paid/100):'-';
		$event_date=$this->SLIM->language_dates->langDate($event['EventDate'],'mn y');	
		$dojo=issetCheck($this->DOJOS,$this->SESSION_REC['DojoID'],'no dojo?');
		if($for_form){
			$form['title']=$event['EventName'].': '.$event_date;
			$form['form_url']=$this->PERMBACK;
			$form['form_category']= $this->CAT;
			foreach($this->PRODUCTS as $i=>$v){			
				if($v['ItemGroup']==$this->CAT)	 $form['prod_opt'].='<option value="'.$i.'">'.trim($v['ItemShort']).': CHF '.((int)$v['ItemPrice']/100).'.-</option>';
			}
			//review		
			$form['member_name']=$this->renderForm_input('FirstName',$this->SESSION_REC['FirstName']).$this->renderForm_input('LastName',$this->SESSION_REC['LastName']);
			$form['member_email']=$this->renderForm_input('Email',$this->SESSION_REC['Email']);
			$form['member_zasha']=$this->renderForm_select('zasha',$this->SESSION_REC['zasha']);
			$form['member_notes']=$this->SESSION_REC['Remarque'];
			$form['member_dob']=$this->renderForm_input('Birthdate',$this->SESSION_REC['Birthdate'],'date');
			$form['member_age']=$this->renderForm_input('Age',$this->SESSION_REC['Age'],'number');
			$form['member_sem_date']=$this->CAT;//for display
			$form['member_reg_date']=date('Y-m-d');//todays date for posting
			$form['member_dojo']=$this->renderForm_select('DojoID',$this->SESSION_REC['DojoID']);		
			$form['member_grade']=$this->renderForm_select('CurrentGrade',$this->SESSION_REC['CurrentGrade']).$this->renderForm_input('CGradedate',$this->SESSION_REC['CGradedate'],'date');
			$form['member_item']=$prod['ItemShort'].' = CHF '.$price.'.-';
			$form['member_shinsa']=$prod['ItemShort'].' = CHF '.$price.'.-';
			$form['member_price']=false;
			$form['member_paid']=false;
			$form['member_paid_date']=false;
			$form['member_depart']=$this->renderForm_select('Depart',$this->SESSION_REC['Depart']);
			$form['member_arrive']=$this->renderForm_select('Depart',$this->SESSION_REC['Arrive']);
			$form['member_notes']='<textarea name="notes">'.$this->SESSION_REC['Remarque'].'</textarea>';
			$form['event_id']=$this->EVENT_ID;
		}else{
			$form['title']=$event['EventName'].': '.$event_date;
			$form['member_name']=$this->SESSION_REC['FirstName'].' '.$this->SESSION_REC['LastName'];
			$form['member_email']=$this->SESSION_REC['Email'];
			$form['member_zasha']=($this->SESSION_REC['zasha']==1)?'Zasha (Sitting)':'Rissha (Standing)';
			$form['member_notes']=$this->SESSION_REC['Remarque'];
			$form['member_age']=($this->SESSION_REC['Birthdate'])?validDate($this->SESSION_REC['Birthdate']):$this->SESSION_REC['Age'];
			$form['member_sem_date']=$this->CATEGORIES[$this->CAT];//for display
			$form['member_reg_date']=date('Y-m-d');//for posting
			$form['member_dojo']=$dojo;		
			$form['member_grade']=$grades[$this->SESSION_REC['CurrentGrade']]['OptionName'].' - '.validDate($this->SESSION_REC['CGradedate'],'Y');
			$form['member_item']=$prod['ItemShort'].' = CHF '.$price.'.-';
			$form['member_shinsa']=$prod['ItemShort'].' = CHF '.$price.'.-';
			$form['member_price']='CHF '.$price.'.-';
			$form['member_paid']='CHF '.$paid.'.-';
			$form['member_paid_date']='';
			$form['member_depart']=$this->SESSION_REC['Depart'];
			$form['member_arrive']=$this->SESSION_REC['Arrive'];
		}
		//set labels
		foreach($form as $i=>$v){
			$k=str_replace('member_','',$i);
			$form[$i.'_label']=$this->SLIM->language->getStandard($k);
		}
		$form['member_item_label']=$this->SLIM->language->getStandard('participation');
		$form['member_reg_date_label']=$this->SLIM->language->getStandard('registration_date');
		$form['section_label_personal']=$this->SLIM->language->getStandard('personal');
		$form['section_label_dojo']=$this->SLIM->language->getStandard('dojo_and_grade');
		$form['section_label_seminar']=$this->SLIM->language->getStandard('seminar');
		$form['section_label_account']=$this->SLIM->language->getStandard('account');
		$form['section_label_payment']=$this->SLIM->language->getStandard('payment');
		$form['section_label_payment_info']=$this->SLIM->language->getStandard('payment_info.');
		
		$form['form_url']=$this->PERMBACK;
		$form['edit_url']=$this->PERMBACK.'event/reg_form_edit';
		$form['cancel_url']=$this->PERMBACK;
		$form['reg_form']=json_encode($this->SESSION_REC);
		return $form;		
	}
	private function renderForm_input($name=false,$value='',$type='text'){
		$out=false;
		if($name){
			$out='<input name="'.$name.'" value="'.$value.'" type="'.$type.'" />';
		}
		return $out;
	}
	private function renderForm_select($what,$id=false,$name=false){
		$opt='';
		$label_key=false;
		if(!$name) $name=$what;
		switch($what){
			case 'grades': case 'CurrentGrade':
				$data=$this->GRADES;
				$label_key='OptionName';
				break;
			case 'dojo': case 'DojoID':
				$data=$this->DOJOS;
				$opt='<option value="0">No Dojo?</option>';
				break;
			case 'depart': case 'arrive': case 'Depart': case 'Arrive':
				$data=$this->DEPARTS;
				break;
			case 'zasha':
				$data=$this->ZASHA;
				break;
			default:
				$data=false;				
		}
		if(is_array($data)){
			foreach($data as $i=>$v){
				$label=($label_key)?issetCheck($v,$label_key,'???'):$v;
				$selected=($id==$i)?'selected="selected"':'';
				switch($what){
					case 'grades':
						if($v['sortkey']>0) $opt.='<option value="'.$i.'" '.$selected.'>'.$label.'</option>';
						break;
					default:
						$opt.='<option value="'.$i.'" '.$selected.'>'.$label.'</option>';
				}
			}
		}
		return '<select name="'.$name.'">'.$opt.'</select>';		
	}

}
