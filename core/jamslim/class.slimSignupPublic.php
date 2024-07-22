<?php
//new members application form
class slimSignupPublic{
	private $SLIM;
	private $USER;
	private $AJAX;
	private $ROUTE;
	private $PRODUCTS;
	private $DOJOS;
	private $GRADES;
	private $ZASHA;
	private $LANGUAGES;
	private $PDF_LOGO;
	private $NOT_FILLED;
	private $USE_CAPTCHA=true;
	private $LIB;
	private $REQUIRED=[
		'uname','upass','uconfirm','FirstName','LastName','Email','Address','City','PostCode','Country','MobilePhone','Birthdate','Language','DojoID'
	];
	private $TEST_FORM;
	private $OUTPUT=array('icon'=>false,'info'=>false,'nav'=>false,'content'=>false);
	private $PERMBACK;
	private $PERMLINK;
	private $MEMBER_REC;
	private $SESSION_REC;
	private $DEV_MODE=false;
	private $POWER=false;
	private $ADMIN_EMAIL=false;
	private $METHOD;
	private $REQUEST;
	private $POST;
	private $MAIL_BOT;
	public $PERMBASE;
	
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
		if(!$this->USER['id']){
			if(!isset($this->USER['token'])){
				$this->USER['token']=TextFun::getToken_api('guest');
				setMySession('token',$this->USER['token']);
			}
		}				
		$this->AJAX=$slim->router->get('ajax');
		$this->ROUTE=$slim->router->get('route');
		$this->METHOD=$slim->router->get('method');
		$this->REQUEST=($this->METHOD==='POST')?$_POST:$_GET;
		$this->ADMIN_EMAIL=$slim->Options->get('admin_email');
		$this->MAIL_BOT=$slim->Options->get('mailbot');
		$pow=$slim->Options->get('application','membership_signup_public');
		$this->POWER=(int)$pow['OptionValue'];
		$this->PERMBACK=URL.'page/';
		$this->PERMLINK=URL.'page/'.issetCheck($this->ROUTE,1);
		$this->init();
	}
	private function init(){
		//check Member
		if(!$this->POWER) return;
		if($this->USER['id']>0){
			$MDB = new slim_db_members($this->SLIM);
			$r=$MDB->get('member',(int)$this->USER['MemberID']);
			$this->MEMBER_REC=$r;
		}
		if($sesh=getMySession()){
			$this->SESSION_REC=issetCheck($sesh,'signup_form');
		}
		
		$this->LIB=new slimSignupForm($this->SLIM);
		$this->LIB->PERMLINK=$this->PERMLINK;
		$this->LIB->PERMBACK=$this->PERMBACK;
		$this->TEST_FORM=$this->LIB->TEST_FORM;
		if($this->SESSION_REC){
			$this->LIB->set('form_data',$this->SESSION_REC);
		}else if($this->DEV_MODE){
			$this->LIB->set('form_data',$this->TEST_FORM);
		}
		if($this->DEV_MODE){
			$this->USER['membership_signup']=false;
		}
		$this->LIB->set('user',$this->USER);
	}
	
	function render($what=false,$vars=false){
		$title=$this->SLIM->language->getStandard('membership_form');		
		if(!$this->POWER){
			return [
				'title'=>$title,
				'content'=>msgHandler($this->SLIM->language->getStandardPhrase('content_not_available'),false,false)
			];
		}
		if($this->METHOD=='POST') $this->renderPost($this->REQUEST);
		if($this->MEMBER_REC){
			$output=[
				'title'=>$title,
				'content'=>msgHandler($this->SLIM->language->getStandardPhrase('member_exists'),false,false)
			];
		}else if(issetCheck($this->USER,'membership_signup')){
			$output=[
				'title'=>$title,
				'content'=>msgHandler($this->SLIM->language->getStandardPhrase('membership_already_signedup'),false,false)
			];
		}else{
			$r2=issetCheck($this->ROUTE,2);
			switch($r2){
				case 'review':
					$c=$this->renderReviewForm($r2);
					break;
				case 'confirmed':
					$c=$this->logRegistration();
					break;
				default:
					$c=$this->renderForm();
			}					
			$output=['title'=>$title, 'content'=>$c];
		}
		return $output;
	}
	function renderPost($post=[]){
		$action=issetCheck($post,'action');
		$rcap=issetCheck($post,'recaptcha_response');
		unset($post['action'],$post['recaptcha_response']);
		$this->POST=$post;
		if($action==='submit_signup' && $this->USE_CAPTCHA){
			$chk=$this->LIB->get('recaptcha',$rcap);
			if(!$chk['status']) $action='recaptcha_fail';
		}
		switch($action){
			case 'submit_signup':
			case 'submit_signup_now':
				setMySession('signup_form',$this->POST);
				$chk=$this->checkSubmission();
				switch($chk){
					case 'complete':
						$email=issetCheck($post,'Email');
						$member=$this->memberExists($email);
						if($member){
							$msg=$this->SLIM->language->getStandardPhrase('member_exists');
							$rsp=array('status'=>500,'message'=>$msg,'type'=>'redirect','url'=>$this->PERMBACK.'home');
						}else{
							$this->SESSION_REC=$post;
							$msg=$this->SLIM->language->getStandardPhrase('confirm_membership_form');
							$rsp=array('status'=>200,'message'=>$msg,'type'=>'redirect','url'=>$this->PERMLINK.'/review');
						}
						break;
					case 'incomplete':
						$msg=$this->SLIM->language->getStandardPhrase('incomplete_form').$this->NOT_FILLED;
						$rsp=array('status'=>500,'message'=>$msg,'type'=>'redirect','url'=>$this->PERMLINK);
						break;
					default://invalid
						$msg=$this->SLIM->language->getStandardPhrase('invalid_form');
						$rsp=array('status'=>500,'message'=>$msg,'type'=>'redirect','url'=>$this->PERMBACK);
						break;
				}
				break;
			case 'recaptcha_fail':
				$rsp=array('status'=>500,'message'=>'Sorry, you seem to be a bot!<br/>Maybe try again...','type'=>'message','url'=>$this->PERMBACK);
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
			setSystemResponse($rsp['url'],$rsp['message']);
		}
	}
	private function checkSubmission($type='post'){
		$data=($type==='session')?$this->SESSION_REC:$this->POST;
		$chk=$this->checkToken($data['token']);
		if($chk){
			foreach($this->REQUIRED as $r){
				if(!issetCheck($data,$r)){
					$this->NOT_FILLED=' - '.$r;
					return 'incomplete';
				}
			}
			if($data['upass']!==$data['uconfirm']){
				$this->NOT_FILLED=' - '.$r;
				return 'incomplete';
			}
			$data=$this->LIB->checkGrade($data);
			if($type==='session'){
				$this->SESSION_REC=$data;
			}else{
				$this->POST=$data;
			}
			return 'complete';
		}else{
			return 'invalid';
		}
	}
	private function checkToken($tkn){
		return ($tkn===$this->USER['token'])?true:false;
	}
	private function renderForm(){
		$content=$this->LIB->get('public');
		$flags=$language='';
		$pow=$this->SLIM->language->get('_POWER');
		if($pow){
			$langs=$this->SLIM->language->get('_LANGS');
			$lang=$this->SLIM->language->get('_LANG');
			foreach($langs as $i=>$v){
				if($i!==$lang){
					$flags.='<span class="flag-box tiny"><span class="flag '.$i.'"></span></span>';
				}	
			}
			$language='<button class="button expanded button-olive loadME" href="'.URL.'page/lang/select/signup">'.$flags.'&nbsp; '.$this->SLIM->language->getStandard('select_language').'</button>';
		}
		return $language.$content;
	}
	private function renderReviewForm($mode=false,$download=false){
		$alert='';
		$msg=$this->SLIM->Options->get('application','signup review notice');
		if($msg){
			if(trim($msg['OptionValue'])!==''){
				$alert=msgHandler(html_entity_decode($msg['OptionValue']),'warning',false);
			}
		}
		$content=$this->LIB->get('review');
		return $alert.$content;
	}
	private function memberExists($email=false){
		$exists=false;
		$email=trim($email);
		if($email!==''){
			$DB = $this->SLIM->db->Members();
			$rec=$DB->where('Email',$email)->select('MemberID,FirstName,LastName,Dojo,Disable');
			$rec=renderResultsORM($rec);
			if(count($rec)) $exists=current($rec);
		}
		return $exists;
	}
	private function userExists($email=false){
		$exists=false;
		$email=trim($email);
		if($email!==''){
			$DB=$this->SLIM->db->Users();
			$rec=$DB->where('Email',$email)->limit(1);
			$rec=renderResultsORM($rec);
			if(count($rec)) $exists=current($rec);
		}
		return $exists;
	}
	private function regExists($email=false){
		$exists=false;
		$email=trim($email);
		if($email!==''){
			$DB=$this->SLIM->db->SignupLog();
			$rec=$DB->where('Email',$email)->limit(1);
			$rec=renderResultsORM($rec);
			if(count($rec)) $exists=$rec;
		}
		return $exists;
	}
	private function logRegistration(){
		$valid=$this->checkSubmission('session');
		$email=issetCheck($this->SESSION_REC,'Email');
		$member=$this->memberExists($email);
		$user=$this->userExists($email);
		$reg=$this->regExists($email);
		$state=500;
		if($valid==='incomplete'){
			$msg=$this->SLIM->language->getStandardPhrase('incomplete_form').$this->NOT_FILLED;
			$url=$this->PERMLINK;
		}else if($valid==='invalid'){
			$msg=$this->SLIM->language->getStandardPhrase('invalid_form');
			$url=$this->PERMLINK;
		}else if($reg){
			$msg=$this->SLIM->language->getStandardPhrase('membership_already_signedup');
			$url=$this->PERMBACK.'home';
		}else if($member){
			$msg=$this->SLIM->language->getStandardPhrase('member_exists');
			$url=$this->PERMBACK.'home';
		}else{
			$user=$this->userExists($this->SESSION_REC['Email']);
			$log=[
				'Name'=>$this->SESSION_REC['FirstName'].' '.$this->SESSION_REC['LastName'],
				'Email'=>$this->SESSION_REC['Email'],
				'DojoID'=>$this->SESSION_REC['DojoID'],
				'MemberID'=>0,
				'UserID'=>issetCheck($user,'id',0),
				'LogDate'=>date('y-m-d H:i:s'),
				'FormData'=>compress($this->SESSION_REC),
				'Status'=>'submit'
			];
			if($this->DEV_MODE){
				$log['ID']=1;
			}else{
				$DB=$this->SLIM->db->SignupLog();
				$DB->insert($log);
				$ref=$DB->insert_id();
				$log['ID']=$ref;
			}
			
			//send emails
			$this->sendMessages($log);
			//respond
			$msg=$this->SLIM->language->getStandardPhrase('membership_signup_success');
			$url=$this->PERMBACK.'home';
			//session
			$_SESSION['jamSlim']['userArray']['membership_signup']=1;
			unset($_SESSION['jamSlim']['userArray']['signup_form']);
		}
		if($this->AJAX){		
			return array('status'=>$state,'message'=>$msg,'url'=>$url);
		}
		setSystemResponse($url,$msg);
		die;
	}

	private function sendMessages($log){
		//produce pdf
		$subject_pre=$this->SLIM->config['SITE_SHORT_NAME'].': ';
		$log['FormData']=compress($log['FormData'],false);
		$dojo=$this->LIB->get('club',$log['FormData']['DojoID']);
		$pdf=$this->LIB->get('pdf_public',$log);
		//common parts
		$parts['event_name']=$this->SLIM->language->getStandardContent('membership_form');
		$parts['event_date']=$parts['start']=validDate($log['LogDate']);
		$parts['name']=$parts['member_name']=$log['Name'];
		$parts['member_email']=$log['Email'];
		$parts['url']=URL;		
		
		$send['header']=$this->SLIM->EmailParts['header'];//$this->SLIM->language->getStandardContent('email_header');
		$send['footer']=$this->SLIM->EmailParts['footer'];//$this->SLIM->language->getStandardContent('email_footer');
		$send['from']=$this->MAIL_BOT;
		$send['attachments'][]=$pdf;
		
		//send user email
		$msg=$this->SLIM->language->getStandardContent('logged_membership_email');
		$msg=replaceMe($parts,$msg);
		$send['to']=$log['Email'];
		$send['subject']=$subject_pre.$this->SLIM->language->getStandard('membership_application_confirmed');
		$send['message'][0]=strip_tags($msg,'<a><br><br/>');
		$send['message'][1]=$msg;
		$chk=$this->SLIM->Mailer->Process($send);
		
		//send admin email
		$msg=$this->SLIM->language->getStandardContent('notify_membership_application_email');
		$msg=replaceMe($parts,$msg);
		$send['to']=($dojo['Email']==='???')?$this->ADMIN_EMAIL:$dojo['Email'];
		$send['subject']=$subject_pre.$this->SLIM->language->getStandard('membership_application_recieved');
		$send['message'][0]=strip_tags($msg,'<a><br><br/>');
		$send['message'][1]=$msg;
		$chk=$this->SLIM->Mailer->Process($send);
	}
}	
