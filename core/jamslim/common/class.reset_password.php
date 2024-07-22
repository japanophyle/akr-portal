<?php

class reset_password{
	private $SLIM;
	private $METHOD;
	private $REQUEST;
	private $HASH;
	private $AJAX;
	private $DEBUG=false;
	private $ROUTE;
	
	function __construct($slim=null){
		if(!$slim){
		  throw new Exception(__METHOD__.': no slim object!!');
		}
		$this->SLIM=$slim;
		$this->METHOD=$slim->router->get('method');
		$this->AJAX=$slim->router->get('ajax');
		$this->REQUEST=($this->METHOD==='POST')?$_POST:$_GET;
		$this->ROUTE=$slim->router->get('route');
		$this->HASH=issetCheck($this->ROUTE,2);
	}
	
	function render(){
		if($this->METHOD==='POST'){
			if($this->REQUEST['action']==='reset_password'){
				$out=$this->resetPasswordChange();
			}else{
				$out=$this->resetPasswordRequest();
			}
			if($this->AJAX){
				echo jsonResponse($out);
			}else{
				setSystemResponse($out['url'],$out['message']);
			}
			die;			
		}else{
			if($this->HASH){
				$out=$this->resetPasswordChangeForm();	
			}else{
				$out=$this->resetPasswordRequestForm();
			}
		}
		return $out;
	}
	private function resetPasswordChangeForm(){
		$user=$this->checkUserHash();
		if($user){
			if($user['token_expired']){
				$form='<div class="callout alert" style="margin-bottom:0;">'.$this->SLIM->language->getStandardPhrase('token_expired').'</div>';
				$form.='<div class="button-group expanded">';
				$form.='<button type="button" class="button button-maroon gotoME" data-ref="'.URL.'page/login-reminder"><i class="fi-arrow-left"></i>  '.$this->SLIM->language->getStandard('reset password').'</button>';
				$form.='</div>';
			}else{
				$form='<input type="hidden" name="action" value="reset_password"/><input type="hidden" name="utoken" value="'.$user['Token'].'"/>';
				$form.='<div class="callout primary" style="margin-bottom:0;">';
				$form.='<p>'.$this->SLIM->language->getStandardPhrase('reset_info').'</p>';
				$form.='<label><i class="fi-mail"></i> '.$this->SLIM->language->getStandard('email').'<input type="email" name="umail" placeholder="me@home.com" required=""/></label>';
				$form.='<label><i class="fi-lock"></i> Enter your new password<input type="password" name="upass_a" required=""/></label>';
				$form.='<label><i class="fi-lock"></i> Confirm the new password<input type="password" name="upass_b" required=""/></label>';
				$form.='</div><div class="button-group expanded">';
				$form.='<button type="submit" class="button button-olive"><i class="fi-check"></i>  '.$this->SLIM->language->getStandard('update password').'</button>';
				$form.='</div>';
			}
		}else{
			$form=msgHandler('Sorry, the code supplied is invalid or has expired.',false,false);
		}
        return ['status'=>200,'content'=>$form,'title'=>$this->SLIM->language->getStandard('reset password')];
 	}
 	private function validateResetPost(){
		$out=['user'=>[],'msg'=>false,'newpass'=>false];
		$this->HASH=issetcheck($this->REQUEST,'utoken');
		$user=$this->checkUserHash();
		$now=date('Y-m-d H:i:s');
		if($this->METHOD!=='POST'){
			$out['msg']=$this->SLIM->language->getStandardPhrase('invalid_method');
			return $out;
		}
		if(!$user){
			$out['msg']=$this->SLIM->language->getStandardPhrase('invalid_form');
			return $out;
		}
		if($user['Email']!==$this->REQUEST['umail']){
			$out['msg']=$this->SLIM->language->getStandardPhrase('email_not_matched');
			return $out;
		}
		if($user['TokenExpire'] < $now){
			$out['msg']=$this->SLIM->language->getStandardPhrase('token_expired');
			return $out;
		}
		//check passwords match 
		$test=false;
		$pa=trim($this->REQUEST['upass_a']);
		$pb=trim($this->REQUEST['upass_b']);
		if($pa && $pa !==''){
			if($pb && $pb !==''){
				if($pa === $pb) $test=true;
			}
		}
		if(!$test){
			$out['msg']=$this->SLIM->language->getStandardPhrase('passwords_not_matched');
			return $out;
		}
		$out['newpass']=$pb;
		$out['user']=$user;
		return $out;
	}
	private function resetPasswordChange(){
		$state=500;
		$url=URL.'page/home';
		$type='message';
		$msg=$newpass=false;
		$out=$user=[];
		$valid=$this->validateResetPost();
		extract($valid);
		if($newpass){
			$hash=TextFun::quickHash(['info'=>$newpass.$user['Salt']]);
			$update=['Password'=>$hash,'Token'=>NULL,'TokenExpire'=>NULL];
			$rec=$this->SLIM->db->Users->where('id',$user['id']);
			if(count($rec)==1){
				if($this->DEBUG){
					$test=1;
				}else{
					$test=$rec->update($update);
				}
				if($test){
					$state=200;
					$msg=$this->SLIM->language->getStandardPhrase('password_success');
				}else{
					$state=201;
					$msg=$this->SLIM->language->getStandardPhrase('password_no_change');
				}
				//login user 
				if(!$this->DEBUG){
					$login=$this->SLIM->Login->doLogin($user['Username'],$newpass);
					if(issetCheck($login,1)) $url=URL.'page/my-home';
				}
				
				$out=array('status'=>$state,'message'=>$msg);
			}else{
				$msg=$this->SLIM->language->getStandardPhrase('record_not_found');
				$out=array('status'=>$state,'message'=>$msg);
			}			
		}else{
			$out=array('status'=>$state,'message'=>$msg);
		}
		$mtype=($state!==500)?'success':'alert';		
		$out['url']=$url;
		$out['title']='';
		$out['mtype']=$mtype;
		return $out;
	}
	private function resetPasswordRequestForm(){
 		$reset='<input type="hidden" name="action" value="reset_my_password"/>';
		$reset.='<div class="callout warning" style="margin-bottom:0;">';
		$reset.=$this->SLIM->language->getStandardPhrase('reset_info');
		$reset.='<label>'.$this->SLIM->language->getStandard('email').'<input type="email" name="umail" placeholder="me@home.com" required=""/></label>';
		$reset.='</div><div class="button-group expanded">';
		if($this->AJAX){
			$reset.='<button class="button secondary " data-close><i class="fi-x"></i> '.$this->SLIM->language->getStandard('cancel').'</button>';
		}else{
			$reset.='<button class="button secondary gotoME" data-ref="'.URL.'page/home"><i class="fi-x"></i> '.$this->SLIM->language->getStandard('cancel').'</button>';
		}
		$reset.='<button type="submit" class="button button-olive"><i class="fi-mail"></i>  '.$this->SLIM->language->getStandard('send_request').'</button>';
		$reset.='</div>';
        return ['status'=>200,'content'=>$reset,'title'=>$this->SLIM->language->getStandardPhrase('reset password')];
 	}
	private function resetPasswordRequest(){
		$state=500;
		$close=$new=false;
		$url=URL.'page/home';
		$type='message';
		if($this->REQUEST){
			$email=issetCheck($this->REQUEST,'umail');
			if($email){
				$email=cleanME($email,'email');
				$rec=$this->SLIM->db->Users->where('Email',$email)->limit(1);
				$rec=renderResultsORM($rec);
				$user=($rec)?current($rec):[];
				if($user){//has user account
					if($user['Status']==1){
						$token = TextFun::getToken_api();
						$expire = (time()+(60 * 30));
						$expired=0;
						$_expired=issetCheck($user,'TokenExpire');
						if($_expired) $expired=strtotime($_expired);
						$name=$user['Name'];
						$id=(int)$user['id'];
						if($expired < time()){
							$state=200;
							$up=array('Token'=>$token,'TokenExpire'=>date('Y-m-d H:i:s',$expire));
							$rec=$this->SLIM->db->Users()[$id];
							$chk=$rec->update($up);
							$close=true;
						}else{
							$state=201;
							$msg=$this->SLIM->language->getStandardPhrase('link_already_sent');
						}
					}else{
						$msg=$this->SLIM->language->getStandardPhrase('account_closed');
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
		$mtype=($state===200)?'success':'alert';
		return ['status'=>$state,'message'=>$msg,'type'=>$type,'mtype'=>$mtype,'close'=>$close,'url'=>$url,'title'=>''];
	}
	private function getResetMessage($args,$lang=false,$new=false){
		switch($lang){
			case 'en':
				if($new) $new='<br/></br/>Your username is this email address.<br/>';
				$msg['subject'] = "Password Update Request";
				$msg['content'] = '<h2>Hello {name},</h2><br/>Recently a request was submitted to reset a password for your account at our website. If this was a mistake, just ignore this email and nothing will happen.{new}<br/><br/>To reset your password, visit the following link:<br/><a href="{resetPassLink}">{resetPassLink}</a><br/><br/><strong>Note:</strong> This link will expire today at '.date('H:i',$args['expire']).'<br/><br/><br/>Regards,<br/>Team AHK';
				break;
			case 'fr':
				if($new) $new='<br/>Ton nom d’utilisateur est ton adresse e-mail.<br/>';
				$msg['subject'] = "demande de réinitialisation de ton mot de passe";
				$msg['content'] = '<h2>Cher membre,</h2><br/>Notre site Web nous signale une demande de réinitialisation de ton mot de passe.<br/><br/>Si c’est une erreur, tu peux ignorer ce mail. Il ne se passera rien d’autre.<br/><br/>{new}Pour réinitialiser le mot de passe, il faut cliquez sur ce lien et suivre les instructions:<br/><a href="{resetPassLink}">{resetPassLink}</a><br/><br/>Attention : ce lien expire à '.date('H:i',$args['expire']).'<br/><br/><br/>Salutations amicales<br/>Secrétariat de l’AHK';
				break;
			default:
				if($new) $new='<br/>Dein Username ist deine Mailadresse.<br/>';
				$msg['subject'] = "Fordern Sie Ihr Passwort zurück";
				$msg['content'] = '<h2>Liebes Mitglied,</h2><br/>Auf unserer Webseite ist die Aufforderung, das Passwort zurückzusetzen, eingegangen.<br/><br/>Falls es sich um eine Fehler handelt, kannst du dieses Mail ignorieren. Es passiert weiter nichts.<br/><br/>Um das Passwort zurückzusetzen, klicke auf diesen Link und folge den Anweisungen:<br/><a href="{resetPassLink}">{resetPassLink}</a><br/><br/>Bitte beachte, dass dieser Link um '.date('H:i',$args['expire']).' verfällt.<br/><br/><br/>Freundliche Grüsse<br/>Sekretariat SKV';
		}
		$args['new']=$new;
		$msg['content']=replaceME($args,$msg['content']);
		return $msg;
	}
	private function sendResetMessage($email,$name,$token,$expire,$new=false){
		$resetPassLink = URL.'page/reset-password/'.$token;
		$args=array('name'=>$name,'expire'=>$expire,'resetPassLink'=>$resetPassLink);
		$lang=$this->SLIM->language->get('_LANG');
		$header=$this->SLIM->language->getStandardContent('email_header');
		$footer=$this->SLIM->language->getStandardContent('email_footer');
		$message=$this->getResetMessage($args,$lang,$new);
		$mbot=$this->SLIM->Options->get('mailbot');
		$to =$email;
		$Mail=array(
			'to'=>$to,
			'from'=>'mailbot<'.$mbot.'>',
			'name'=>$name,
			'subject'=>$message['subject'],
			'message'=>$message['content'],
			'header'=>$this->SLIM->EmailParts['header'],
			'footer'=>$this->SLIM->EmailParts['footer'],
		);

		$this->SLIM->Mailer->Process($Mail);
		return;
	}
	private function fixSectionTags($str=false){
		$pattern = "=^<p>(.*)</p>$=i";
		preg_match($pattern, $str, $matches);
		return issetCheck($matches,1);
	}
	private function checkUserHash($hash=false){
		if(!$hash) $hash=$this->HASH;
		if($hash){
			$user=[];
			$rec=$this->SLIM->db->Users->where('Token',$hash);
			if(count($rec)){
				$rec=renderResultsORM($rec);
				$user=current($rec);
				$expired=0;
				$_expired=issetCheck($user,'TokenExpire');
				if($_expired) $expired=strtotime($_expired);				
				$user['token_expired']=($expired < time())?true:false;
			}
			return $user;	
		}
		return [];
	}	

}
