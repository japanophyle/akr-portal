<?php
//for members, used on members profile
class slimMember{
	private $SLIM;
	private $USER;
	private $AJAX;
	private $METHOD;
	private $REQUEST;
	private $ROUTE;
	private $PRODUCTS;
	private $DOJOS;
	private $GRADES;
	private $ZASHA;
	private $LANGUAGES;
	private $PDF_LOGO;
	private $PDF_LOGO_TEXT;
	private $POST;	
	private $CATEGORIES;
	private $MEMBERS_DB;
	private $MEMBER_NAV=array(
		'view_my_details'=>array('label'=>'details','icon'=>'torso'),
		'view_my_events'=>array('label'=>'events','icon'=>'calendar'),
		'view_my_grades'=>array('label'=>'grades','icon'=>'trophy'),
		'view_my_sales'=>array('label'=>'sales','icon'=>'shopping-cart'),
	);
	private $VALIDS=array(
		'member'=>array('MemberID','FirstName','LastName','Email','Address','Town','City','PostCode','Country','LandPhone','Birthdate','Sex','Language','zasha'),
		'dojo'=>array('Dojo','DateJoined','CGradeName','CGradedate','CGradeLoc1')
	);
	private $OUTPUT=array('icon'=>false,'info'=>false,'nav'=>false,'content'=>false);
	private $ACTION;
	private $PERMBACK;
	private $PERMLINK;
	private $MEMBER_REC;
	private $MEMBER_META;
	private $META_KEYS;
	private $PAYMENT_METHODS;
	private $EVENT;
	private $MAIL_BOT;
	private $ADMIN_EMAIL;
	private $PAYMENT_POWER;

	function __construct($slim=null){
		if(!$slim){
		  throw new Exception(__METHOD__.': no slim object!!');
		}
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		//check for temp login - not needed here??
		if($this->USER['access']>=$slim->AdminLevel){
			//$tmp_login=slimSession('get','temp_login');
			//if($tmp_login) $this->USER=$tmp_login;
		}
		$this->MAIL_BOT=$slim->Options->getSiteOptions('email_mailbot',true);
		$this->ADMIN_EMAIL=$slim->Options->getSiteOptions('email_administrator',true);
		$this->PAYMENT_POWER=$slim->Options->getSiteOptions('payment_power',true);	
		$this->ZASHA=$slim->Options->get('zasha');
		$this->PAYMENT_METHODS=$slim->Options->get('payment_method');
		$this->PDF_LOGO=$slim->EmailParts['logo'];
		$this->PDF_LOGO_TEXT=$slim->EmailParts['pdf_header'];
		$this->LANGUAGES=$slim->language->get('_LANGS');
		$this->AJAX=$slim->router->get('ajax');
		$this->ROUTE=$slim->router->get('route');
		$this->METHOD=$slim->router->get('method');
		$this->REQUEST=($this->METHOD==='POST')?$_POST:$_GET;
		$this->initMembersDB();
		$this->initDojos();
		$this->PERMBACK=URL.'page/';
		$this->PERMLINK=$this->PERMBACK.'my-home/';
		$this->initMember();
	}
	function render($what=false,$vars=false){
		if($this->METHOD==='POST') $this->renderPost($this->REQUEST);
		if(!$what) $what=issetCheck($this->ROUTE,2,'view_my_details');
		$this->memberNav($what,$vars);
		$this->ACTION=$what;
		switch($what){
			case 'view_my_details':
				return $this->viewMyDetails();
				break;
			case 'view_my_events':
				return $this->viewMyEvents();
				break;
			case 'view_my_grades':
				return $this->viewMyGrades();
				break;
			case 'view_my_sales':
				return $this->viewMySales();
				break;
			case 'view_my_invoice':
				$this->viewMyInvoice($vars);
				break;
			case 'view_my_form':
				$this->viewMyForm($vars);
				break;
			case 'update_my_password':
				$this->viewMyPasswordForm();
				break;
			case 'resetPassword':
				return $this->viewResetPassword();
				break;
			case 'navbar':
				return issetCheck($this->OUTPUT,'controls');
				break;
			case 'membership_subs':
				$this->membershipSubs($vars);
				break;
			case 'akr_subs':
				$this->akrSubs($vars);
				break;
			default:
				return $this->renderOutput(array('title'=>'???','content'=>msgHandler('Sorry, bad request...'.$what,false,false)));
		}
	}
	private function renderOutput($output=false){
		//final formatting for non-modal output
		if(!$this->AJAX && is_array($output)){
			//do something
		}
		return $output;
	}
	function renderPost($post){
		$action=issetCheck($post,'action');
		unset($post['action']);
		$this->POST=$post;
		switch($action){
			case 'registration_form':
				setMySession('reg_form',$post);
				$url=$this->PERMLINK.'event/register_confirm';
				$msg=$this->SLIM->language->getStandardPhrase('confirm_details');				
				$rsp=array('status'=>500,'message'=>$msg,'type'=>'redirect','url'=>$url);
				break;
			case 'submit_now':
			case 'event_registration':
				$rsp=$this->logRegistration();
				$msg=$rsp['message'];
				$url=$rsp['url'];
				break;
			case 'update_my_details':
				$rsp=$this->updateDetails();
				$msg=$rsp['message'];
				$url=$rsp['url'];
				break;
			case 'update_my_password':
				$rsp=$this->updatePassword();
				$msg=$rsp['message'];
				$url=$rsp['url'];
				break;
			case 'reset_my_password':
				$rsp=$this->resetPasswordRequest();
				$msg=$rsp['message'];
				$url=$rsp['url'];
				break;
			case 'reset_password':
				$rsp=$this->resetPasswordSubmit();
				$msg=$rsp['message'];
				$url=$rsp['url'];
				break;
			default:
				$url=$this->PERMLINK;
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
	private function checkToken(){
		return ($this->POST['token']===$this->USER['token'])?true:false;
	}
	private function updateDetails(){
		$state=500;
		$url=false;
		$type='message';
		if($this->POST){
			$test=$this->checkToken();
			if($test){
				if($this->USER['MemberID']==$this->POST['MemberID']){
					$chk=$this->doUpdateMember();
					$url=$this->PERMLINK.'view_my_details';
					if($chk['status']!=500){
						$msg=$chk['message'];
						$state=$chk['status'];
					}else{
						$msg=$this->SLIM->language->getStandardPhrase('details_error');
					}
				}else{
					$msg=$this->SLIM->language->getStandardPhrase('not_autorised');
					$type='redirect';
					$url=$this->PERMLINK.'home';
				}
			}else{
				$msg=$this->SLIM->language->getStandardPhrase('invalid_form');
			}
		}else{
			$msg=$this->SLIM->language->getStandardPhrase('no_information');
		}
		return array('status'=>$state,'message'=>$msg,'type'=>$type,'url'=>$url);
	}
	
	private function doUpdateMember(){
		$update=$test=[];
		foreach($this->POST as $i=>$v){
			if($i==='meta'){
				$update[$i]=$v;
			}else{
				if($i==='Form') $i='zasha';
				if($i==='Gender')$i='Sex';
				if(in_array($i,$this->VALIDS['member'])){
					//clean the input here
					$type='text';				
					if($i==='Birthdate') $type='date';
					if(in_array($i,array('zasha','MemberID'))) $type='int';
					$update[$i]=cleanME($v,$type);
				}
			}
		}
		if($update){
			$test=$this->MEMBERS_DB->updateRecord($update,$this->USER['MemberID']);
			//update user account name & email
			$name=$update['FirstName'];
			if($update['LastName'] && $update['LastName']!=='') $name.=' '.$update['LastName'];
			$user=array('Name'=>$name,'Email'=>$update['Email']);
			$DB=$this->SLIM->db->Users();
			$res=$DB->where('id',$this->USER['id']);
			$res->update($user);
		}else{
			$test['status']=500;
		}
		return $test;
	}
	private function updatePassword(){
		$state=500;
		$url=$this->PERMLINK.'view_my_details';
		$type='message';
		if($this->POST){
			$test=$this->checkToken();
			if($test){
				$test=false;
				if($this->USER['MemberID']==$this->POST['MemberID']){
					$pa=trim($this->POST['upass_a']);
					$pb=trim($this->POST['upass_b']);
					if($pa && $pa !==''){
						if($pb && $pb !==''){
							if($pa === $pb) $test=true;
						}
					}
				}
				if($test){
					$update=$pb;
				}else{
					$msg=$this->SLIM->language->getStandardPhrase('passwords_not_matched');
				}				
			}else{
				$msg=$this->SLIM->language->getStandardPhrase('invalid_form');
			}
		}else{
			$msg=$this->SLIM->language->getStandardPhrase('invalid_form');
		}
		if($update){
			$DB=$this->SLIM->db->Users();
			$rec=$DB->where('id',$this->USER['id']);
			if(count($rec)>0){
				$hash=TextFun::quickHash(array('info'=>$update.$rec[$this->USER['id']]['Salt']));
				$update=array('Password'=>$hash);
				$test=$rec->update($update);
				if($test){
					$state=200;
					$msg=$this->SLIM->language->getStandardPhrase('password_success');
				}else{
					$state=201;
					$msg=$this->SLIM->language->getStandardPhrase('password_no_change');
				}
				$test=array('status'=>$state,'message'=>$msg);
			}else{
				$test=array('status'=>$state,'message'=>$msg);
			}			
		}else{
			$test=array('status'=>$state,'message'=>$msg);
		}
		$test['url']=$url;
		return $test;
	}
	
	private function resetPasswordRequest(){
		$state=500;
		$close=$new=false;
		$url=$this->PERMLINK.'home';
		$type='message';
		if($this->POST){
			$email=issetCheck($this->POST,'umail');
			if($email){
				$email=cleanME($email,'email');
				$user=$this->getUser('email',$email);
				if($user){//has user account?
					if($user['Status']==1){
						$token = TextFun::getToken_api();
						$expire = (time()+(60 * 30));
						$expired=0;
						$_expired=issetCheck($user,'TokenExpire');
						if($_expired) $expired=strtotime($_expired);
						$name=$user['Name'];
						$id=(int)$user['id'];
						if((int)$expired < time()){// this must be used when the site is live
							$state=200;
							$up=array('Token'=>$token,'TokenExpire'=>date('Y-m-d H:i:s',$expire));
							$DB=$this->SLIM->db;
							$TB=$DB->Users()[$id];
							$chk=$TB->update($up);
							$close=true;
						}else{
							$state=201;
							$msg=$this->SLIM->language->getStandardPhrase('link_already_sent');
						}
					}else{
						$msg=$this->SLIM->language->getStandardPhrase('account_closed');
					}
				}else{//has member account
					$this->getMember('email',$email);
					if($this->MEMBER_REC  && (int)$this->MEMBER_REC['Disable']<1){//make user account
						$user=array(
							'Name'=>$this->MEMBER_REC['FirstName'].' '.$this->MEMBER_REC['LastName'],
							'Username'=>$email,
							'Access'=>20,
							'Status'=>1,
							'DojoLock'=>(int)$this->MEMBER_REC['DojoID'],
							'MemberID'=>$this->MEMBER_REC['MemberID'],
							'Email'=>$email,
						);
						$NU=new makeUser($user);
						$NU->makePassword();
						$NU->setToken();
						$user=$NU->Process();
						$DB=$this->SLIM->db;
						$TB=$DB->Users();
						$chk=$TB->insert($user);
						if($chk){
							$close=true;
							$state=200;
							$token = $user['Token'];
							$expire = $user['TokenExpire'];
							$name = $user['Name'];
							$new=true;
						}
					}
				}
				if($state==200){
					$this->sendResetMessage($email,$name,$token,$expire,$new);
					$msg=$this->SLIM->language->getStandardPhrase('instructions_sent').' "'.$email.'".';
				}else if($state!==201){
					$msg=$this->SLIM->language->getStandardPhrase('no_email_found');
				}
			}else{
				$msg=$this->SLIM->language->getStandardPhrase('no_email');
			}
		}else{
			$msg=$this->SLIM->language->getStandardPhrase('no_email');
		}
		return array('status'=>$state,'message'=>$msg,'type'=>$type,'close'=>$close,'url'=>$url);
	}
	private function resetPasswordSubmit(){
		$state=500;
		$url=$this->PERMLINK.'home';
		$type='message';
		if($this->POST){
			$user=$this->checkUserHash($this->POST['utoken']);
			if($user){
				if(!(int)$user['token_expired']){
					if($user['Email']===$this->POST['umail']){
						$test=false;
						$pa=trim($this->POST['upass_a']);
						$pb=trim($this->POST['upass_b']);
						if($pa && $pa !==''){
							if($pb && $pb !==''){
								if($pa === $pb) $test=true;
							}
						}
					}
					if($test){
						$update=$pb;
					}else{
						$msg=$this->SLIM->language->getStandardPhrase('password_not_matched');
					}				
				}else{
					$msg=$this->SLIM->language->getStandardPhrase('link_expired');
					$type='redirect';
				}
			}else{
				$msg=$this->SLIM->language->getStandardPhrase('invalid_link');
				$type='redirect';
			}
		}else{
			$msg=$this->SLIM->language->getStandardPhrase('no_details_found');
		}
		if($update){
			$DB=$this->SLIM->db->Users();
			$rec=$DB->where('id',$user['id']);
			if(count($rec)>0){				
				$hash=TextFun::quickHash(array('info'=>$update.$rec[$user['id']]['Salt']));
				$update=array('Password'=>$hash);
				$test=$rec->update($update);
				if($test){
					$state=200;
					$msg=$this->SLIM->language->getStandardPhrase('password_success');
				}else{
					$state=201;
					$msg=$this->SLIM->language->getStandardPhrase('password_no_change');
				}
				$test=array('status'=>$state,'message'=>$msg);
				$type='redirect';
			}else{
				$test=array('status'=>$state,'message'=>$msg);
			}			
		}else{
			$test=array('status'=>$state,'message'=>$msg);
		}
		$test['url']=$url;
		$test['type']=$type;
		return $test;
	}
	
	private function sendResetMessage($email,$name,$token,$expire,$new=false){
		$resetPassLink = URL.'page/resetPassword/'.$token;
		$args=array('name'=>$name,'expire'=>$expire,'resetPassLink'=>$resetPassLink);
		$lang=$this->SLIM->language->get('_LANG');
		$message=$this->getResetMessage($args,$lang,$new);
		$mail=array(
			'to'=>$email,
			'name'=>$name,
			'subject'=>$message['subject'],
			'message'=>$message['content'],
		);
		$this->sendMessage($mail);
		return;
	}
	private function sendMessage($email=[]){
		$header=$this->SLIM->EmailParts['header'];//$this->SLIM->language->getStandardContent('email_header');
		$footer=$this->SLIM->EmailParts['header'];//$this->SLIM->language->getStandardContent('email_footer');
		$name=issetCheck($email,'name','Member');
		$Mail=array(
			'to'=>$email['to'],
			'from'=>'mailbot<'.$this->MAIL_BOT.'>',
			'name'=>$name,
			'subject'=>$email['subject'],
			'message'=>$email['message'],
			'header'=>$this->fixSectionTags($header),
			'footer'=>$this->fixSectionTags($footer)
		);
		if(isset($email['attachments'])) $Mail['attachments']=$email['attachments'];
		$res=$this->SLIM->Mailer->Process($Mail);
		return $res;		
	}
	function fixSectionTags($str=false){
		$pattern = "=^<p>(.*)</p>$=i";
		preg_match($pattern, $str, $matches);
		return issetCheck($matches,1);
	}
	private function getResetMessage($args,$lang=false,$new=false){
		switch($lang){
			case 'en':
				if($new) $new='<br/></br/>Your username is this email address.<br/>';
				$msg['subject'] = "Password Update Request";
				$msg['content'] = '<h2>Hello {name},</h2><br/>Recently a request was submitted to reset a password for your account at our website. If this was a mistake, just ignore this email and nothing will happen.{new}<br/><br/>To reset your password, visit the following link: <a href="{resetPassLink}">{resetPassLink}</a><br/><br/><strong>Note:</strong> This link will expire today at '.date('H:i',$args['expire']).'<br/><br/><br/>Regards,<br/>Team AHK';
				break;
			case 'fr':
				if($new) $new='<br/>Ton nom d’utilisateur est ton adresse e-mail.<br/>';
				$msg['subject'] = "demande de réinitialisation de ton mot de passe";
				$msg['content'] = '<h2>Cher membre,</h2><br/>Notre site Web nous signale une demande de réinitialisation de ton mot de passe.<br/><br/>Si c’est une erreur, tu peux ignorer ce mail. Il ne se passera rien d’autre.<br/><br/>{new}Pour réinitialiser le mot de passe, il faut cliquez sur ce lien et suivre les instructions : <a href="{resetPassLink}">{resetPassLink}</a><br/><br/>Attention : ce lien expire à '.date('H:i',$args['expire']).'<br/><br/><br/>Salutations amicales<br/>Secrétariat de l’AHK';
				break;
			default:
				if($new) $new='<br/>Dein Username ist deine Mailadresse.<br/>';
				$msg['subject'] = "Fordern Sie Ihr Passwort zurück";
				$msg['content'] = '<h2>Liebes Mitglied,</h2><br/>Auf unserer Webseite ist die Aufforderung, das Passwort zurückzusetzen, eingegangen.<br/><br/>Falls es sich um eine Fehler handelt, kannst du dieses Mail ignorieren. Es passiert weiter nichts.<br/><br/>Um das Passwort zurückzusetzen, klicke auf diesen Link und folge den Anweisungen: <a href="{resetPassLink}">{resetPassLink}</a><br/><br/>Bitte beachte, dass dieser Link um '.date('H:i',$args['expire']).' verfällt.<br/><br/><br/>Freundliche Grüsse<br/>Sekretariat SKV';
		}
		$args['new']=$new;
		$msg['content']=replaceME($args,$msg['content']);
		return $msg;
	}
	private function initGrades(){
		$grades=$this->SLIM->Options->get('grades');
		$grades=array_reverse($grades,true);
		$this->GRADES=rekeyArray($grades,'OptionValue');
	}
	private function initDojos(){
		$recs=$this->SLIM->db->ClubInfo->select("ClubID AS id,ClubName, ShortName,Country");
		$d=renderResultsORM($recs,'id');
		foreach($d as $i=>$v){
			$dojo[$i]=$v['ShortName'];
			if($c=issetCheck($v,'Country')) $dojo[$i].=', '.$c;
		}
		$this->DOJOS=$dojo;
	}	
	private function initProducts(){
		$this->CATEGORIES=$this->SLIM->Options->get('product_types');
		$prods=$this->SLIM->db->Items()->where('ItemType','product')->order('ItemTitle');
		$prods=renderResultsORM($prods,'ItemID');
		$this->PRODUCTS=$prods;
	}
	
	private function initMembersDB(){
		$this->MEMBERS_DB = new slim_db_members($this->SLIM);
		$this->META_KEYS=$this->MEMBERS_DB->get('meta_keys');
	}
	
	private function initMember(){
		$memID=(int)issetCheck($this->USER,'MemberID');
		$this->getMember('id',$memID);
		$grade=issetCheck($this->MEMBER_REC,'CGradeName','- no grade?? -');
		$icon=$this->SLIM->idIcon->identicon($this->USER['name']);
		$dojo=issetCheck($this->MEMBER_REC,'DojoID');
		$dojo=issetCheck($this->DOJOS,$dojo,'- no dojo?? -');
		$this->OUTPUT['title']='My Account';//$this->SLIM->language->getStandard('my_account');
		$this->OUTPUT['icon']=$icon;
		$this->OUTPUT['info']='<h3>'.$this->USER['name'].'</h3><ul><li>'.$grade.'</li><li>'.$dojo.'</li></ul>';
	}
	
	private function getMember($what=false,$vars=null){
		switch($what){
			case 'id':
				$r=(int)$vars;
				$this->MEMBER_REC=$this->MEMBERS_DB->get('member',$r);
				$this->MEMBER_META=$this->MEMBERS_DB->get('meta_data',$r);
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
	private function checkUserHash($hash=false){
		if($hash){
			$user=$this->getUser('token',$hash);
			if($user){
				$expired=0;
				$_expired=issetCheck($user,'TokenExpire');
				if($_expired) $expired=strtotime($_expired);				
				$user['token_expired']=($expired < time())?true:false;
			}
			return $user;	
		}
		return false;
	}	
	private function getOtherInfo($mode=''){
		$row=[];
		$skip=['archery_member_id','teaching_rank','years_practiced','practice_location'];
		foreach($this->MEMBER_META as $i=>$v){
			if(in_array($i,$skip)) continue;
			$name=ucME($i);//$this->SLIM->language->getStandard($slug);
			$val=$v['MetaValue'];
			if($mode==='edit'){
				switch($i){
					case 'date_began_kyudo':
						$val='<input type="date" name="meta['.$i.']" value="'.$val.'"/>';
						break;
					case 'payment_method':
						$o=renderSelectOptions($this->PAYMENT_METHODS,$val,false,true);
						$val='<select name="meta['.$i.']">'.$o.'</select>';
						break;						
					default:
						$val='<input type="text" name="meta['.$i.']" value="'.$val.'"/>';
				}
			}else{
				if($i==='payment_method'){
					$val=issetCheck($this->PAYMENT_METHODS,$val,'None');
				}
			}
			$row[]='<tr><td>'.$name.'</td><td>'.$val.'</td></tr>';
		}
		return $row;
	}
	private function getSubscription($id=0){
		$row=$missing=[];
		if($id){
			$subs=$this->MEMBERS_DB->get('current_membership',$id);
			$missing=$this->getMissingSubs();
			if($subs){
				$now=time();
				$cost=$paid=$items=[];
				$end_date=$start_date=$subs_block='';
				foreach($subs as $i=>$v){
					if(!isset($items[$v['ItemID']])){
						$prod=$this->SLIM->Options->getProductInfo('all',$v['ItemID']);
						$v['ItemTitle']=$prod['ItemTitle'];
						$v['paid_state']=($v['Paid']<$v['SoldPrice'])?'red':'olive';
						$subs_block.=$this->renderSubsBlock($v);
						$items[$v['ItemID']]=1;
					}
				}
				$row[]='<tr><td>'.$this->SLIM->language->getStandard('dojo').'</td><td class="text-dark-blue">'.$subs_block.'</td></tr>';
			}
		}
		if($missing){
			$b='<i class="fi-alert"></i> '.count($missing).' subscriptions to be added...';
			$b.='<button class="button small button-dark-blue loadME expanded" data-ref="'.$this->PERMLINK.'membership_subs"><i class="fi-plus"></i> '.$this->SLIM->language->getStandard('add subscriptions').'</button>';
			$row[]='<tr><td colspan="2">'.msgHandler($b,'warning',false).'</td></tr>';
		}
		if(!$row){
			$no_subscription='<i class="fi-alert"></i> No subscriptions found...';
			$no_subscription.=' <button class="button small button-dark-blue loadME expanded" data-ref="'.$this->PERMLINK.'membership_subs"><i class="fi-plus"></i> '.$this->SLIM->language->getStandard('new subscription').'</button>';
			$row[]='<tr><td colspan="2">'.msgHandler($no_subscription,'warning',false).'</td></tr>';
		}
		return $row;		
	}
	private function renderSubsBlock($data=[]){
		if(!$data) return '';
		$now=time();
		$end=strtotime($data['EndDate']);
		$state=($end>=$now)?'success':'alert';		
		$paid=($data['paid_state']==='red')?' <span class="loadME" data-ref="'.$this->PERMLINK.'view_my_invoice/'.$data['Ref'].'" title="view invoice"><i class="fi-info"></i></span>':'';
		$but=($state==='alert')?'<button class="button small button-dark-blue loadME expanded" data-ref="'.$this->PERMLINK.'membership_subs"><i class="fi-plus"></i> '.$this->SLIM->language->getStandard('renew subscription').'</button>':'';
		$status=($state==='alert')?'<br/><span class="text-maroon">Expired</span>':'<br/><span class="text-dark-green">Active</span>';
		return '<p class="callout '.$state.'"><strong>'.$data['ItemTitle'].' - $'.toPounds($data['SoldPrice']).'</strong><br/>'.validDate($data['StartDate']).' to '.validDate($data['EndDate']).'<br/>Paid: <span class="text-'.$data['paid_state'].'">$'.toPounds($data['Paid']).$paid.'</span>'.$status.'</p>';
	}
	private function getSubscriptionCart(){
		$did=issetCheck($this->USER,'DojoID');
		$subs_group_id=$this->MEMBERS_DB->get('subs_group_id');//not used yet
		$whr=['ItemType'=>'prod_cart','ItemOrder'=>$did,'ItemStatus'=>1];
		$cart=$this->SLIM->db->Items->where($whr)->limit(1);
		$prods=[];
		if(count($cart)){
			$cart=renderResultsORM($cart);
			$cart=current($cart);
			$pid=json_decode($cart['ItemShort'],1);
			$recs=$this->SLIM->db->Items->where('ItemID',$pid);
			$prods=renderResultsORM($recs,'ItemID');			
		}
		return $prods;
	}
	private function getSubscriptionProducts(){
		$prodx=$this->SLIM->Options->getSubscriptionProducts();
		$dprods=(isset($this->USER['DojoID']))?$this->SLIM->Options->getDojoProducts($this->USER['DojoID']):[];
		$subs_group_id=$this->MEMBERS_DB->get('subs_group_id');
		$memb_cat_id=$this->MEMBERS_DB->get('memb_cat_id');
		$prods=[];
		foreach($prodx as $i=>$v){
			if($v['ItemSlug']==='akr-membership'){
				$prods[$i]=$v;
			}else if(!$dprods && $v['ItemCurrency']==1){//fallback for old ahk records
				if(strpos($v['ItemSlug'],'inactive')!==false) continue;
				if(!$v['ItemContent'] && $v['ItemCategory']==$memb_cat_id)	$prods[$i]=$v;
			}else if(array_key_exists($i,$dprods)){
				if($v['ItemStatus']!=='active') continue;
				if($v['ItemGroup']!=$subs_group_id) continue;
				$prods[$i]=$v;
			}
		}
		return $prods;
	}
	private function getMissingSubs(){
		$subs=$this->MEMBERS_DB->get('current_membership',$this->USER['MemberID']);
		$akr=$this->MEMBERS_DB->get('current_akr',$this->USER['MemberID']);
		if($akr) $subs[]=$akr;
		$prods=$this->getSubscriptionCart();
		$date=date('Y-m-d');
		$missing=[];
		foreach($prods as $i=>$v){
			$sub=null;
			foreach($subs as $y){
				if($i==$y['ItemID']){
					$sub=$y;
					break;
				}
			}
			if($sub){
				$state=($sub['EndDate']>$date)?'active':'renew';
				if($state==='renew'){
					$v['subs_state']='renew';
					$missing[$i]=$v;
				}
			}else{//missing
				$v['subs_state']='new';
				$missing[$i]=$v;
			}				
		}
		return $missing;
	}
	private function getIKYF($id=0){
		$row=[];
		if($id){
			$subs=$this->MEMBERS_DB->get('current_ikyf',$id);
			if($subs){
				$prod=$this->SLIM->Options->getProductInfo('all',$subs['ItemID']);
				$subs['ItemTitle']=$prod['ItemTitle'];
				$subs['paid_state']=($subs['Paid']<$subs['SoldPrice'])?'red':'olive';
				$subs_block=$this->renderSubsBlock($subs);
				$row[]='<tr><td>IKYF</td><td class="text-dark-blue">'.$subs_block.'</td></tr>';
			}
			//ankf id
			$ikf_id=($this->MEMBER_REC['AnkfID']==='' || !$this->MEMBER_REC['AnkfID'])?' - ':$this->MEMBER_REC['AnkfID'];	
			$row[]='<tr><td>'.$this->SLIM->language->getStandard('ikyf id').'</td><td class="text-dark-blue">'.$ikf_id.'</td></tr>';
		}		
		if(!$row){
			$msg=$this->SLIM->language->getStandardPhrase('no_details_found');
			$row[]='<tr><td colspan="2">'.msgHandler('<i class="fi-alert"></i> '.$msg,false,false).'</td></tr>';
		}
		return $row;		
	}
	private function getAKR($id=0){
		$row=[];
		$subs_block='';
		if($id){
			$subs=$this->MEMBERS_DB->get('current_akr',$id);
			if($subs){
				$prod=$this->SLIM->Options->getProductInfo('all',$subs['ItemID']);
				$subs['ItemTitle']=$prod['ItemTitle'];
				$subs['paid_state']=($subs['Paid']<$subs['SoldPrice'])?'red':'olive';
				$subs_block.=$this->renderSubsBlock($subs);
				$row[]='<tr><td>AKR</td><td class="text-dark-blue">'.$subs_block.'</td></tr>';
			}else{
				$no_subscription='<i class="fi-alert"></i> No AKR subscription found...';
				$no_subscription.=' <button class="button small button-dark-blue loadME expanded" data-ref="'.$this->PERMLINK.'akr_subs"><i class="fi-plus"></i> '.$this->SLIM->language->getStandard('new subscription').'</button>';
				$row[]='';
			}
			//akr id
			$meta=issetCheck($this->MEMBER_META,'archery_member_id',[]);
			$akr_id=($meta)?$meta['MetaValue']:' - ';
			if(trim($akr_id)==='') $akr_id=' - ';
		}		
		if(!$row){
			$msg=$this->SLIM->language->getStandardPhrase('no_details_found');
			$row[]='<tr><td colspan="2">'.msgHandler('<i class="fi-alert"></i> '.$msg,false,false).'</td></tr>';
		}
		return $row;		
	}
	private function getEventForm($log_id=0){
		$form=null;
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

	private function getEventLog(){
		if(!isset($this->MEMBER_REC['MemberID'])) return [];
		return $this->MEMBERS_DB->getMemberEvents($this->MEMBER_REC['MemberID']);
	}	
	private function getSalesLog(){
		if(!isset($this->MEMBER_REC['MemberID'])) return [];
		return $this->MEMBERS_DB->getMemberSales($this->MEMBER_REC['MemberID']);
	}
	private function getGradeLog(){
		if(!isset($this->MEMBER_REC['MemberID'])) return [];
		return $this->MEMBERS_DB->getMemberGrades($this->MEMBER_REC['MemberID']);
	}
	
	private function getEvent($event_id=0){
		$event=null;
		$rec=$this->SLIM->db->Events()->where('EventID',$event_id);
		$event=renderResultsORM($rec);
		if(is_array($event)) $event=$event[0];
		return $event;
	}

	private function logRegistration(){
		//not used.. check slimEventForms
		$rsp=array(
			'status'=>500,
			'message'=>'Sorry, there was a problem... an "us" problem.<br/>Please try again later or contact our team via info@kyudouse.com',
			'type'=>'redirect',
			'url'=>URL.'page/home'
		);
		return $rsp;
	}
	
	private function memberTemplate(){
		$tpl='<div id="member" class="callout" ><div class="grid-x grid-margin-x" data-equalizer data-equalize-on="medium" >
				<div class="cell small-4 large-2 data-equalizer-watch">
					{member_icon}
				</div>
				<div class="cell small-8 medium-auto data-equalizer-watch">
					{member_info}
				</div>
				<div class="cell medium-3 data-equalizer-watch show-for-large">
					<div class="stacked-medium button-group">
						{member_controls}
					</div>
				</div>
		  </div>
		  <div class="grid-x grid-margin-x">
				<div class="cell auto">
					{member_content}
				</div>
		  </div></div>';
		foreach($this->OUTPUT as $i=>$v){
			$tpl=str_replace('{member_'.$i.'}',$v,$tpl);
		}
		return $tpl;		
	}

	private function memberNav($what=false,$args=false){
		if($what==='navbar'){
			if((int)issetCheck($this->USER,'MemberID')){
				if($chk=issetCheck($this->MEMBER_NAV,$this->ROUTE[1])){
					$what=$this->ROUTE[1];
				}
			}else{
				$this->OUTPUT['controls']=false;
				return;
			}
		}
		$chk=issetCheck($this->MEMBER_NAV,$what);
		if(!$chk)$what='';//'view_my_details';
		$n=$m=false;
		$m='<li class="menu-label"><span class="menu-text text-ahk-red hide-for-large"><i class="fi-torso"></i> '.$this->SLIM->language->getStandard('my_account').'</span></li>';
		foreach($this->MEMBER_NAV as $i=>$v){
			$href=$this->PERMLINK.$i;
			$class=($what===$i)?'text-yellow':'';
			$icon='<i class="fi-'.$v['icon'].'"></i> ';
			$m.='<li><a class="'.$class.' hide-for-large" href="'.$href.'">'.$this->SLIM->language->getStandard($v['label']).'</a></li>';
			$class=($what!==$i)?'secondary':'button-ahk-red';	
			$n.='<button class="button gotoME '.$class.'" data-ref="'.$href.'">'.$icon.$this->SLIM->language->getStandard($v['label']).'</button>';
		}
		$this->OUTPUT['controls']=$n;
		$this->SLIM->memberNav=$m;
	}
	
	private function viewMyDetails(){
		$member=$this->getMember('id',$this->USER['MemberID']);
		$row=[];$hclass=false;
		if($this->MEMBER_REC){
			$mode=issetCheck($this->ROUTE,3);
			foreach($this->VALIDS['member'] as $x=>$i){
				$v=issetCheck($this->MEMBER_REC,$i);
				if($i==='Sex') $i='Gender';
				if($i==='Birthdate') $v=validDate($v);
				if($i==='zasha'){
					$i='Form';
					$v=$this->ZASHA[(int)$v];
				}
				if($i==='Country') if($v==='0') $v=$this->SLIM->language->getStandard('Switzerland');
				if($i==='Language') $v=$this->LANGUAGES[$v];
				if($mode==='edit'){
					if($i=='MemberID'){
						$row['member'][]='<input type="hidden" name="'.$i.'" value="'.$v.'"/>';
					}else{
						if(in_array($i,array('Gender','Language','Form'))){
							$fld=$this->getSelectOptions($i,$v);
						}else if($i==='Birthdate'){
							$fld='<input type="date" name="'.$i.'" value="'.$v.'" />';
						}else if($i==='Address'){
							$fld='<small>'.$this->SLIM->language->getStandardPhrase('comma_seperate').'.</small><textarea rows="3" name="'.$i.'">'.$v.'</textarea>';
						}else{
							$fld='<input type="text" name="'.$i.'" value="'.$v.'" />';							
						}
						$slug=($i==='LandPhone')?'Phone':camelTo($i);
						$slug=strtolower(str_replace(' ','_',$slug));
						$row['member'][]='<tr><td>'.$this->SLIM->language->getStandard($slug).'</td><td>'.$fld.'</td></tr>';
					}
				}else{
					$slug=($i==='LandPhone')?'Phone':camelTo($i);
					$slug=strtolower(str_replace(' ','_',$slug));
					if($i!=='MemberID') $row['member'][]='<tr><td>'.$this->SLIM->language->getStandard($slug).'</td><td class="text-dark-blue">'.$v.'</td></tr>';
				}
			}
			$row['other']=$this->getOtherInfo($mode);
			$row['subscription']=$this->getSubscription($this->USER['MemberID']);
			$row['ikyf']=$this->getIKYF($this->USER['MemberID']);
			$row['akr']=$this->getAKR($this->USER['MemberID']);
			foreach($this->VALIDS['dojo'] as $x=>$i){
				$v=issetCheck($this->MEMBER_REC,$i);
				if($i==='CGradeName') $i='Grade';
				if($i==='CGradedate'){
					$i='GradeDate';
					$v=validDate($v);
				}
				if($i==='CGradeLoc1') $i='GradeLocation';
				if($i==='DateJoined') $v=validDate($v);
				$slug=camelTo($i);
				$slug=strtolower(str_replace(' ','_',$slug));
				$row['dojo'][]='<tr><td>'.$this->SLIM->language->getStandard($slug).'</td><td class="text-dark-blue">'.$v.'</td></tr>';
			}
			$row['user'][]='<tr><td>'.$this->SLIM->language->getStandard('username').'</td><td class="text-dark-blue">'.$this->USER['uname'].'</td></tr>';
			$row['user'][]='<tr><td>'.$this->SLIM->language->getStandard('password').'</td><td><button class="button button-purple loadME small expanded" data-ref="'.$this->PERMLINK.'update_my_password"><i class="fi-lock"></i> '.$this->SLIM->language->getStandard('update_password').'</button></td></tr>';
		}
		if($row){
			$button='<button class="button button-navy loadME" data-ref="'.$this->PERMLINK.'view_my_details/edit"><i class="fi-pencil"></i> '.$this->SLIM->language->getStandard('edit_details').'</button>';
			$tlc=($this->AJAX && $mode==='edit')?implode('',$row['member']).implode('',$row['other']):implode('',$row['member']);
			$t1='<table class="stack-for-small"><tbody>'.$tlc.'</tbody></table>';
			$icon='<i class="fi-'.$this->MEMBER_NAV[$this->ACTION]['icon'].'"></i> ';
			if($mode==='edit'){
				$button=($this->AJAX)?$this->SLIM->closer:false;
				$hclass='bg-olive text-white';
				$submit='<button class="button button-olive expanded" type="submit"><i class="fi-check"></i> '.$this->SLIM->language->getStandard('update_details').'</button>';
				$t1='<form name="form1" action="'.$this->PERMLINK.'view_my_details" method="post"><input type="hidden" name="action" value="update_my_details"/><input type="hidden" name="token" value="'.$this->USER['token'].'"/><div class="tabs-content">'.$t1.'</div>'.$submit.'</form>';
				$t1=renderCard_active($icon.$this->SLIM->language->getStandard('my_details'),$t1,$button);
				$out=$t1;
				if($this->AJAX){
					echo $out;
					die;
				}
			}else{
				$t1=renderCard_active($icon.$this->SLIM->language->getStandard('my_details'),$t1,$button,false,false,false,false,$hclass);
				$t2='<table style="width:100%;"><tbody>'.implode('',$row['dojo']).'</tbody></table>';
				$t2=renderCard_active($this->SLIM->language->getStandard('dojo_and_grade'),$t2);
				$t3='<table class="stack-for-small" ><tbody>'.implode('',$row['user']).'</tbody></table>';
				$t3=renderCard_active($this->SLIM->language->getStandard('login'),$t3);
				$t4='<table style="width:100%;"><tbody>'.implode('',$row['subscription']).implode('',$row['akr']).'</tbody></table>';
				$t4=renderCard_active($this->SLIM->language->getStandard('subscriptions'),$t4);
				$t5='<table style="width:100%;"><tbody>'.implode('',$row['ikyf']).'</tbody></table>';
				$t5=renderCard_active($this->SLIM->language->getStandard('IKYF'),$t5);
				$t6='<table style="width:100%;"><tbody>'.implode('',$row['other']).'</tbody></table>';
				$t6=renderCard_active($this->SLIM->language->getStandard('Other_Info'),$t6);
				//$t7='<table style="width:100%;"><tbody>'.implode('',$row['akr']).'</tbody></table>';
				//$t7=renderCard_active($this->SLIM->language->getStandard('AKR Subscription'),$t7);
				$t7='';				
				$out='<div class="grid-x grid-margin-x"><div class="cell large-7">'.$t1.$t6.'</div><div class="cell auto">'.$t2.$t4.$t7.$t5.$t3.'</div></div>';
			}			
		}else{
			$out=msgHandler($this->SLIM->language->getStandard('no_details_found'),false,false);
			$out=renderCard_active($this->SLIM->language->getStandard('my_details'),$out);
		}
		$this->OUTPUT['content']=$out;
		return $this->renderOutput(array('title'=>'My Account','content'=>$this->memberTemplate()));
	}
	private function viewMyPasswordForm(){
		$button=$this->SLIM->closer;
		$hclass='';
		$row['pass'][]='<tr><td>'.$this->SLIM->language->getStandard('password').'</td><td><input name="upass_a" type="password" ></td></tr>';
		$row['pass'][]='<tr><td>'.$this->SLIM->language->getStandard('confirm_password').'</td><td><input name="upass_b" type="password" ></td></tr>';
		$submit='<button class="button button-olive expanded" type="submit"><i class="fi-check"></i> '.$this->SLIM->language->getStandard('update_password').'</button>';
		$t1='<table style="width:100%;"><tbody>'.implode('',$row['pass']).'</tbody></table>';
		$t1='<form name="form1" action="'.$this->PERMLINK.'view_my_details" method="post"><input type="hidden" name="MemberID" value="'.$this->USER['MemberID'].'"/><input type="hidden" name="action" value="update_my_password"/><input type="hidden" name="token" value="'.$this->USER['token'].'"/>'.$t1.$submit.'</form>';
		$out=renderCard_active($this->SLIM->language->getStandard('my_password'),$t1,$button,false,false,false,false,$hclass);
		if($this->AJAX){
			echo $out; 
			die;
		}
		return $out;
	}
	private function getSelectOptions($key,$selected=false){
		$data=[];$opts=false;
		$ov='i';
		switch($key){
			case 'Gender':
				$data=$this->SLIM->Options->get('gender');
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
	private function getEventSales($event_log_ref=0){
		$sales=$this->getSalesLog();
		$out=[];
		$tots['qty']=$tots['value']=$tots['paid']=0;
		foreach($sales as $inv=>$items){
			foreach($items as $item_id=>$rec){
				if($rec['EventLogRef']==$event_log_ref){
					$out[$item_id]=$rec;
					$tots['qty']++;
					$tots['value']+=$rec['SoldPrice'];
					$tots['paid']+=$rec['Paid'];
				}
			}
		}
		if($out) $out['Totals']=$tots;
		return $out;		
	}
	private function viewMyEvents(){
		$events=$this->getEventLog();
		$row=[];
		if($events){
			foreach($events as $i=>$v){
				$sales=$this->getEventSales($i);
				if($sales){
					$c_rec=current($sales);
					$cur=issetCheck($c_rec,'Currency');
					$paid_ch=toPounds($sales['Totals']['paid'],$cur);
					$paid='<span class="text-maroon" title="'.$paid_ch.'">'.$this->SLIM->language->getStandard('no').'</span>';
					if((int)$sales['Totals']['paid']>0){
						if($sales['Totals']['paid']==$sales['Totals']['value']){
							$paid='<span class="text-olive" title="'.$paid_ch.'">'.$this->SLIM->language->getStandard('yes').'</span>';
						}else if($sales['Totals']['paid']<$sales['Totals']['value']){
							$paid='<span class="text-orange" title="'.$paid_ch.'">'.$this->SLIM->language->getStandard('no').'</span>';
						}else if($sales['Totals']['paid']>$sales['Totals']['value']){
							$paid='<span class="text-blue" title="'.$paid_ch.'">'.$this->SLIM->language->getStandard('refund').'</span>';
						}
					}else{
						if($sales['Totals']['paid']==$sales['Totals']['value']) $paid='<span class="text-gray" title="-">'.$this->SLIM->language->getStandard('no').'</span>';
					}
					$form_button=' <button class="button small loadME button-purple" data-ref="'.$this->PERMLINK.'view_my_form/'.$i.'"><i class="fi-eye"></i> '.$this->SLIM->language->getStandard('view_form').'</button>';
					$details='<div><strong>'.$this->SLIM->language->getStandard('cost').': </strong>'.toPounds($sales['Totals']['value'],$cur).', <strong>'.$this->SLIM->language->getStandard('paid').': </strong>'.$paid.' '.$form_button.'</div>';
					$row[]='<tr class="table-expand-row" data-open-details><td>'.$v['EventName'].'</td><td>'.validDate($v['EventDate']).'</td><td><span class="expand-icon"></span></td></tr>';
					$row[]='<tr class="table-expand-row-content"><td colspan="8" class="table-expand-row-nested">'.$details.'</td></tr>';
				}else{
					$paid='<span class="text-gray" title="-">'.$this->SLIM->language->getStandard('no').'</span>';
					$form_button=' <button class="button small button-purple loadME" data-ref="'.$this->PERMLINK.'view_my_form/'.$i.'"><i class="fi-eye"></i> '.$this->SLIM->language->getStandard('view_form').'</button>';
					$details='<div><strong>'.$this->SLIM->language->getStandard('cost').': </strong> 0.00, <strong>'.$this->SLIM->language->getStandard('paid').': </strong>'.$paid.' '.$form_button.'</div>';
					$row[]='<tr class="table-expand-row" data-open-details><td>'.$v['EventName'].'</td><td>'.validDate($v['EventDate']).'</td><td><span class="expand-icon"></span></td></tr>';
					$row[]='<tr class="table-expand-row-content"><td colspan="8" class="table-expand-row-nested">'.$details.'</td></tr>';
				}
			}
		}
		if($row){
			$out='<div class="table-scroll"><table class="table-expand"><thead><tr>
			<th>'.$this->SLIM->language->getStandard('event').'</th>
			<th>'.$this->SLIM->language->getStandard('date').'</th>
			<th>'.$this->SLIM->language->getStandard('info.').'</th>';
			
			$out.='</tr></thead>';
			$out.='<tbody>'.implode('',$row).'</tbody></table></div>';
			$this->SLIM->assets->set('js','initTableExpander(false);','expander');
		}else{
			$out=msgHandler($this->SLIM->language->getStandardPhrase('no_events_found'),false,false);
		}
		$icon='<i class="fi-'.$this->MEMBER_NAV[$this->ACTION]['icon'].'"></i> ';
		$this->OUTPUT['content']=renderCard_active($icon.$this->SLIM->language->getStandard('my_events'),$out);		
		return $this->renderOutput(array('title'=>'My Account','content'=>$this->memberTemplate()));
	}
	private function viewMySales(){
		$this->initProducts();
		$data=$this->getSalesLog();
		$row=[];
		if($data){
			foreach($data as $ref=>$recs){
				foreach($recs as $i=>$v){
					$prod=issetCheck($this->PRODUCTS,$v['ItemID']);
					$cur=issetCheck($prod,'ItemCurrency',1);
					$price=toPounds($v['SoldPrice'],$cur);
					$paid_x=toPounds($v['Paid'],$cur);
					$color=($v['Paid']==$v['SoldPrice'])?'olive':'maroon';
					$lbl=($v['Paid']==$v['SoldPrice'])?'yes':'no';
					$paid='<span class="text-'.$color.'" title="'.$paid_x.'">'.$this->SLIM->language->getStandard($lbl).'</span>';					
					$date=validDate($v['SalesDate']);
					$info='';
					if($info && $info!=='') $info='<br/>'.$info;
					$row[]='<tr><td>'.$prod['ItemTitle'].'</td><td>'.$price.'</td><td>'.$date.'</td><td>'.$paid.'</td><td><button class="button small button-dark-blue loadME" data-ref="'.$this->PERMLINK.'view_my_invoice/'.$ref.'"><i class="fi-eye"></i> '.$this->SLIM->language->getStandard('view invoice').'</button></td></tr>';
				}
			}
		}
		if($row){
			$out='<div class="table-scroll"><table class="table-expand"><thead><tr>
			<th>'.$this->SLIM->language->getStandard('item').'</th>
			<th>'.$this->SLIM->language->getStandard('price').'</th>
			<th>'.$this->SLIM->language->getStandard('date').'</th>
			<th>'.$this->SLIM->language->getStandard('paid').'</th>
			<th>'.$this->SLIM->language->getStandard('controls').'</th>';
			
			$out.='</tr></thead>';
			$out.='<tbody>'.implode('',$row).'</tbody></table></div>';
			$this->SLIM->assets->set('js','initTableExpander(false);','expander');
		}else{
			$out=msgHandler($this->SLIM->language->getStandardPhrase('no_sales_found'),'primary',false);
		}
		$icon='<i class="fi-'.$this->MEMBER_NAV[$this->ACTION]['icon'].'"></i> ';
		$this->OUTPUT['content']=renderCard_active($icon.$this->SLIM->language->getStandard('my_sales'),$out);		
		return $this->renderOutput(array('title'=>'My Account','content'=>$this->memberTemplate()));
	}	
	private function viewMyInvoice($ref=false,$action='view'){
		if(!$ref) $ref=issetCheck($this->ROUTE,3);
		$download=false;
		if($ref==='download'){
			$download='download';
			$ref=issetCheck($this->ROUTE,4);
		}else if($action==='file'){
			$download='file';
		}
		if($ref){
			$this->SLIM->Sales->SITE='public';
			$invoice=$this->SLIM->Sales->getInvoiceRecord('ref',$ref);
			$member=($invoice)?issetCheck($invoice['members'],$this->USER['MemberID']):false;
			//check it belongs to this user
			if(!$member || !$invoice){
				$content=msgHandler($this->SLIM->language->getStandardPhrase('no_invoice_found'),false,false);
			}else{
				$INV=new slimInvoiceRender($this->SLIM);
				$content=$INV->render($invoice,$download);
			}
		}else{
			$content=msgHandler($this->SLIM->language->getStandardPhrase('no_form_id'),false,false);
		}
		if($download==='download'){
			$down['html']=$content;
			$down['title']=$this->SLIM->language->getStandard('registration_form');// pdf title for header
			$down['sub_title']='Event: '.$this->EVENT['EventName'].' on '.validDate($this->EVENT['EventDate'],'F j, Y');
			$down['user']=$this->USER['name'];// user name for pdf header
			$down['logo']=array('logo'=>$this->PDF_LOGO,'text'=>$this->PDF_LOGO_TEXT);// array of address & image for pdf header
			$down['date']=time();// date being published for pdf header
			$down['reference_code']='AKRi_'.$ref; // additional text?? for pdf header
			$down['docname']=slugMe($this->EVENT['EventName'].'_form.pdf'); // for downloading
			$down['render']=($action==='file')?'F':'D';
			$this->SLIM->PDF->render($down);
			die;			
		}else if($this->AJAX){
			$content='<div class="modal-body"><div class="tabs-content">'.$content.'</div><div class="modal-footer"><div class="button-group expanded"><button class="button small button-dark-blue gotoME" data-ref="'.$this->PERMLINK.'view_my_form/'.$ref.'/download"><i class="fi-download"></i> '.$this->SLIM->language->getStandard('download').'</button></div></div></div>';
			echo renderCard_active($this->SLIM->language->getStandard('registration_details'),$content,$this->SLIM->closer);
			die;
		}else{
			return $content;
		}
	}

	private function viewMyGrades(){
		$this->initGrades();
		$data=$this->getGradeLog();
		$row=[];
		if($data){
			foreach($data as $i=>$v){
				$grade=$this->GRADES[$v['GradeSet']];
				$location=$v['Location'];
				$gradeDate=validDate($v['GradeDate']);
				$info=$v['OtherInfo'];
				if($info && $info!=='') $info='<br/>'.$info;
				$details='<div><strong>'.$this->SLIM->language->getStandard('location').': </strong>'.$location.'<br/><strong>'.$this->SLIM->language->getStandard('comments').': </strong>'.$info.'</div>';
				$row[]='<tr class="table-expand-row" data-open-details><td>'.$grade['OptionName'].'</td><td>'.$gradeDate.'</td><td><span class="expand-icon"></span></td></tr>';
				$row[]='<tr class="table-expand-row-content"><td colspan="8" class="table-expand-row-nested">'.$details.'</td></tr>';
			}
		}
		if($row){
			$out='<div class="table-scroll"><table class="table-expand"><thead><tr>
			<th>'.$this->SLIM->language->getStandard('grade').'</th>
			<th>'.$this->SLIM->language->getStandard('date').'</th>
			<th>'.$this->SLIM->language->getStandard('info.').'</th>';
			$out.='</tr></thead>';
			$out.='<tbody>'.implode('',$row).'</tbody></table></div>';
			$this->SLIM->assets->set('js','initTableExpander(false);','expander');
		}else{
			$out=msgHandler($this->SLIM->language->getStandardPhrase('no_grade_history'),'primary',false);
		}
		$icon='<i class="fi-'.$this->MEMBER_NAV[$this->ACTION]['icon'].'"></i> ';
		$this->OUTPUT['content']=renderCard_active($icon.$this->SLIM->language->getStandard('my_grades'),$out);		
		return $this->renderOutput(array('title'=>'My Account','content'=>$this->memberTemplate()));
	}	
	private function viewMyForm($form_id=0){
		if(!$form_id) $form_id=(int)issetCheck($this->ROUTE,3);
		$download=issetCheck($this->ROUTE,4);
		$download=($download==='download')?true:false;
		if($form_id){
			$events=$this->getEventLog();
			$this->EVENT=issetCheck($events,$form_id);
			$this->initProducts();
			$data=$this->getEventForm($form_id);
			if($data){
				$data['product_ref']=issetCheck($this->EVENT,'ProductID',$data['product_ref']);
				$shinsa=issetCheck($data,'shinsa');
				$data['shinsa']=issetCheck($this->EVENT,'Shinsa',$shinsa);
				$data['member_paid']=issetCheck($this->EVENT,'PaymentAmount',0);
				$data['member_paid_date']=issetCheck($this->EVENT,'PaymentDate','-');
				$data['EventCost']=issetCheck($this->EVENT,'EventCost',0);
				
				$this->SLIM->EventForms->SESSION_REC=$data;
				$this->SLIM->EventForms->EVENT_ID=$data['event_id'];
				$this->SLIM->EventForms->FORM_ID=$form_id;
				$this->SLIM->EventForms->REGISTERED=true;
				$tpl=($download)?'pdf_form':'my_form';
				$tpl_act=($download)?'admin':$download;
				$content=$this->SLIM->EventForms->get($tpl,$tpl_act);
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
			$down['logo']=array('logo'=>$this->PDF_LOGO,'text'=>$this->PDF_LOGO_TEXT);// array of address & image for pdf header
			$down['date']=time();// date being published for pdf header
			$down['reference_code']='WFRM_'.$form_id; // fadditional text?? for pdf header
			$down['docname']=slugMe($this->EVENT['EventName'].'_form.pdf'); // for downloading
			$down['render_type']='D';
			$this->SLIM->PDF->render($down);
			die;			
		}else if($this->AJAX){
			$content='<div class="modal-body"><div class="tabs-content">'.$content.'</div><div class="modal-footer"><div class="button-group expanded"><button class="button small button-dark-blue gotoME" data-ref="'.$this->PERMLINK.'view_my_form/'.$form_id.'/download"><i class="fi-download"></i> '.$this->SLIM->language->getStandard('download').'</button></div></div></div>';
			echo renderCard_active($this->SLIM->language->getStandard('registration_details'),$content,$this->SLIM->closer);
			die;
		}else{
			return $content;
		}
	}
	private function renderReviewForm($data=false,$review=false){
		$prod=$this->PRODUCTS[$data['participation']];
		$price=((int)$prod['ItemPrice']/100);
		$_paid=(int)issetCheck($data,'member_paid',0);
		$paid=($_paid)?($_paid/100):'-';
		$event_date=$this->SLIM->language_dates->langDate($this->EVENT['EventDate'],'mn y');	
		$form['title']=$this->EVENT['EventName'].': '.$event_date;//date('M. Y',strtotime($this->EVENT['EventDate']));
		$form['member_name']=$data['prenom'].' '.$data['nom'];
		$form['member_email']=$data['email'];
		$form['member_zasha']=($data['zasha']==1)?'Zasha (Sitting)':'Rissha (Standing)';
		$form['member_notes']=$data['remarque'];
		$form['member_age']=$data['annee'];
		$form['member_sem_date']=$this->CATEGORIES[$data['category']];
		$form['member_reg_date']=date('Y-m-d');//for posting
		$form['member_dojo']=issetCheck($this->DOJOS,$data['dojo'],'no dojo?');		
		$form['member_grade']=$this->GRADES[$data['grade']]['OptionName'].' - '.$data['grade_annee'];
		$form['member_item']=$prod['ItemShort'].' = CHF '.$price.'.-';
		$form['member_price']='CHF '.$price.'.-';
		$form['member_paid']='CHF '.$paid.'.-';
		$form['member_paid_date']='';
		$form['member_depart']=$data['depart'];
		$form['member_arrive']=$data['arrivee'];
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
		
		$form['form_url']=$this->PERMLINK;
		$form['edit_url']=$this->PERMLINK.'/reg_form_edit';
		$form['cancel_url']=$this->PERMLINK;
		$form['reg_form']=json_encode($data);
		$tpl_f=($review)?'pdf':'view';
		$tpl=file_get_contents(TEMPLATES.'app/app.form_'.$tpl_f.'.html');
		return replaceME($form,$tpl);		
	}
	private function viewResetPassword(){
		$hash=issetCheck($this->ROUTE,2);
		$msg=false;
		if($hash){
			$rec=$this->checkUserHash($hash);
			if($rec){
				//check expire
				if(!$rec['token_expired']){
					$fill=array('FORM_URL'=>URL.'page/resetpassword','MESSAGE'=>'','USER_NAME'=>$rec['Name'],'USER_ID'=>$rec['id'],'USER_TOKEN'=>$hash);
					$out['content']=$this->SLIM->view->fetch("tpl.magglingen_reset_pass.html", $fill);
					$out['title']=$this->SLIM->language->getStandard('reset_password');
					return $out;						
				}else{
					$msg=$this->SLIM->language->getStandardPhrase('link_expired');
				}						
			}else{
				$msg=$this->SLIM->language->getStandard('invalid_link');
			}
		}
		if($this->AJAX){			
			$out=array('status'=>200, 'type'=>'redirect','url'=>URL.'page/home','message'=>$msg);
			jsonResponse($out);
		}else{
			setSystemResponse(URL.'page/home',$msg);
		}
		die;
	}
	private function membershipSubs(){
		$ref=issetCheck($this->ROUTE,3);
		$add=$list=[];
		$total=0;
		$prods=$this->getMissingSubs();
		$sdate=$date=date('Y-m-d');
		$edate=date('Y-m-d',strtotime($sdate.' + 1 year'));
		$title='Add Membership Subscriptions';
		if($prods && $ref==='go'){		
			//add
			foreach($prods as $i=>$prod){
				$add[$prod['ItemID']]=[
					'Ref' =>'',
					'MemberID' => $this->USER['MemberID'],
					'ItemID' => $prod['ItemID'],
					'ItemType' =>$prod['ItemGroup'], 
					'SoldPrice' => $prod['ItemPrice'],
					'Currency' => 1,
					'SalesDate' => $date ,
					'StartDate' =>  $sdate ,
					'EndDate' => $edate,
					'Length' => 1,
					'Status' => 1
				];
				$list[]='<li>1 x '.$prod['ItemTitle'].' @ $'.toPounds($prod['ItemPrice']).'</li>';
				$total+=$prod['ItemPrice'];
			}			
			$data=['items'=>$add,'action'=>'add_payment'];
			$dojo=$this->SLIM->Options->get('clubs_name',$this->USER['DojoID']);
			$res=$this->SLIM->Sales->saveRecord(false,$data);
			if($res['status']==200){
				$pdf=false;
				$oref=issetCheck($res,'ref');//new order ref from $res
				if($oref) $pdf=$this->viewMyInvoice($oref,'file');
				$msg='Okay, the subscriptions have been added.';
				//send email
				$mail=array(
					'to'=>$this->USER['Email'],
					'name'=>$this->USER['name'],
					'subject'=>'New Annual Membership Subscription',
					'message'=>'<h1>Hello '.$this->USER['name'].'</h1><p>This is a quick note to confirm that your new membership subscriptions have been registered to your account.</p><ul>'.implode('',$list).'<li><strong>Total: $'.toPounds($total).'</strong></li></ul><p>These subscriptions run from '.$sdate.' until '.$edate.'<p><strong>Please remember to arrange payment for these subscriptions.</strong><br/><br/> Team AKR</p>',
				);
				if($pdf && $pdf!=='') $mail['attachments']=[$pdf];
				$this->sendMessage($mail);
				//send admin email
				$mail=array(
					'to'=>$this->ADMIN_EMAIL,
					'name'=>'Admin',
					'subject'=>'New Annual Membership Subscription',
					'message'=>'<h1>Hello Admin</h1><p>This is a quick note to inform you that a new membership subscriptions have been registered at the website.</p><p><strong>'.$this->USER['name'].' ('.$this->USER['Email'].')<br/>Grade: '.$this->USER['GradeName'].'<br/>Dojo: '.$dojo['ClubName'].'</strong></p><ul>'.implode('',$list).'<li><strong>Total: $'.toPounds($total).'</strong></li></ul><p>These subscriptions run from '.$sdate.' until '.$edate.'<br/><br/> Mailbot</p>',
				);
				$this->sendMessage($mail);
			}else{
				$msg='Sorry, there was a problem adding the subscription. Please try again or contact our team for help at .'.$this->ADMIN_EMAIL;
			}
			setSystemResponse($this->PERMLINK,$msg);
		}else if(!$prods){
			$msg='There are no subscription pakages for your dojo at this time.<br/>You can try again later, or contact your dojo leader to resolve this issue.<br/><br/>';
			$msg.='<button class="button button-gray small expanded" data-close><i class="fi-x-circle"></i> Close</button>';
			$content=msgHandler($msg,'warning',false);
		}else{
			$items='';
			foreach($prods as $i=>$v){
				$total+=$v['ItemPrice'];
				$items.=$v['ItemTitle'].' $'.toPounds($v['ItemPrice']).'<br/>';
			}
			$content=msgHandler('<div class="text-center"><span class="h3">Do you want to add new<br/>annual membership subscriptions?</span><br/><br/>'.$items.'starting: <strong>'.$sdate.'</strong><br/>total cost: <strong>$'.toPounds($total).'</strong></div>',false,false);
			$controls='<button class="button secondary" data-close><i class="fi-x-circle"></i> No, maybe later</button>';
			$controls.='<button class="button button-olive gotoME" data-ref="'.$this->PERMLINK.'membership_subs/go"><i class="fi-check"></i> Yes, do it now</button>';
			$content.='<div class="button-group small expanded">'.$controls.'</div>';
		}
		if($this->AJAX){
			echo renderCard_active($title,$content,$this->SLIM->closer);
			die;
		}
		return $content;
	}
	private function akrSubs(){
		$ref=issetCheck($this->ROUTE,3);
		$add=[];
		$subs=$this->MEMBERS_DB->get('current_akr',$this->USER['MemberID']);
		$date=date('Y-m-d');
		$state='nothing';
		$title='Add AKR Subscription';
		if($subs){
			$state=($subs['EndDate']>$date)?'active':'renew';
			if($state==='renew'){
				$chk=date('Y-m-d',strtotime('- 1 year'));
				if($subs['EndDate']>=$chk){
					$sdate=date('Y-m-d',strtotime($subs['EndDate'].' + 1 day'));
				}else{
					$sdate=$date;
				}				
			}
		}else{
			$sdate=$date;
			$state='new';
		}
		$edate=date('Y-m-d',strtotime($sdate.' + 1 year'));
		//product
		$prodx=$this->SLIM->Options->getSubscriptionProducts();
		if(!$prodx) throw new Exception('No subscription product found.');
		$prods=[];
		foreach($prodx as $i=>$v){
			if($v['ItemSlug']==='akr-membership'){
				$prods=[$i=>$v];
				break;
			}
		}
		if($state==='active'){
			$e=date('Y-m-d',strtotime($subs['EndDate']));			
			$content=msgHandler('You already have an active AKR subscription which expires on '.$e.'.',false,false);
		}else if($ref==='go'){		
			//add
			foreach($prods as $i=>$prod){
				$add[$prod['ItemID']]=[
					'Ref' =>'',
					'MemberID' => $this->USER['MemberID'],
					'ItemID' => $prod['ItemID'],
					'ItemType' =>$prod['ItemGroup'], 
					'SoldPrice' => $prod['ItemPrice'],
					'Currency' => 1,
					'SalesDate' => $date ,
					'StartDate' =>  $sdate ,
					'EndDate' => $edate,
					'Length' => 1
				];
			}			
			$data=['items'=>$add,'action'=>'add_payment'];
			$dojo=$this->SLIM->Options->get('clubs_name',$this->USER['DojoID']);
			$res=$this->SLIM->Sales->saveRecord(false,$data);
			if($res['status']==200){
				$oref=$res['ref'];//new order ref from $res
				$pdf=$this->viewMyInvoice($oref,'file');
				$msg='Okay, the subscription has been added.';
				//send email
				$price=toPounds($prod['ItemPrice']);
				$mail=array(
					'to'=>$this->USER['Email'],
					'name'=>$this->USER['name'],
					'subject'=>'New AKR Membership Subscription',
					'message'=>'<h1>Hello '.$this->USER['name'].'</h1><p>This is a quick note to confirm that your new AKR subscription has been registered to your account.</p><p><strong>1 x AKR Membership Subscription @ $'.$price.'<br/>Starting: '.$sdate.'<br/>Ending: '.$edate.'</strong></p><p>Please remember to arrange payment for this subscription.<br/><br/> Team AKR</p>',
					'attachments'=>[$pdf]
				);
				$this->sendMessage($mail);
				//send admin email
				$mail=array(
					'to'=>$this->ADMIN_EMAIL,
					'name'=>'Admin',
					'subject'=>'New AKR Membership Subscription',
					'message'=>'<h1>Hello Admin</h1><p>This is a quick note to inform you that a new AKR subscription has been registered at the website.</p><p><strong>'.$this->USER['name'].' ('.$this->USER['Email'].')<br/>Grade: '.$this->USER['GradeName'].'<br/>Dojo: '.$dojo['ClubName'].'<br/><br/>1 x AKR Membership Subscription @ $'.$price.'<br/>Starting: '.$sdate.'<br/>Ending: '.$edate.'</strong></p><p><br/><br/> Mailbot</p>',
					'attachments'=>[$pdf]
				);
				$this->sendMessage($mail);
			}else{
				$msg='Sorry, there was a problem adding the subscription. Please try again or contact our team for help at .'.$this->ADMIN_EMAIL;
			}
			setSystemResponse($this->PERMLINK,$msg);
		}else{
			$cost=0;$items='';
			foreach($prods as $i=>$v){
				$cost+=$v['ItemPrice'];
				$items.=$v['ItemTitle'].' $'.toPounds($v['ItemPrice']).'<br/>';
			}
			$content=msgHandler('<div class="text-center"><span class="h3">Do you want to add new<br/>AKR membership subscription?</span><br/><br/>'.$items.'starting: <strong>'.$sdate.'</strong><br/>total cost: <strong>$'.toPounds($cost).'</strong></div>',false,false);
			$controls='<button class="button secondary" data-close><i class="fi-x-circle"></i> No, maybe later</button>';
			$controls.='<button class="button button-olive gotoME" data-ref="'.$this->PERMLINK.'akr_subs/go"><i class="fi-check"></i> Yes, do it now</button>';
			$content.='<div class="button-group small expanded">'.$controls.'</div>';
		}
		if($this->AJAX){
			echo renderCard_active($title,$content,$this->SLIM->closer);
			die;
		}
		return $content;
	}
}
