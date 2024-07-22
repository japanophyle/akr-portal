<?php

class slimLogin {
	private $SLIM;
	private $ROLES;
	private $AJAX;
    var $LOGINQ = "SELECT * FROM Users WHERE Username='{uname}' AND Password='{upass}' LIMIT 1";
    var $LOGINQ2 = "SELECT * FROM Users WHERE Username='{uname}' LIMIT 1";
    var $SIGNUP = false;
    var $REMINDER = true;
    var $HOMEURL = false;
    var $FORMURL = false;
    var $USE_SSL = false;
    var $SSL_LOGIN_URL = false;
    var $SALT_KEY='Salt';
    var $REMINDURL;
    var $AdminMail;

    function __construct($slim=null) {
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->ROLES=$slim->user_roles;
        $this->USE_SSL = issetCheck($slim->config,'USE_SSL_LOGIN');
        $this->HOMEURL = URL.'page/home';
        $this->REMINDURL =  URL.'page/login-reminder';
        $this->FORMURL = URL.'page/login';
        $this->SSL_LOGIN_URL = URL.'page/login';
        $this->AdminMail = $this->SLIM->Options->getSiteOptions('email_administrator','value');
        $this->AJAX=$slim->router->get('ajax');
     }
	function Process($action=false,$post=false){
		if($action==='login'){
			$name=issetCheck($post,'username');
			$pass=issetCheck($post,'userpwd');
			$co=$this->doLogin($name,$pass);
			return $co[0];
		}else{
			return $this->doLogout();
		}
	}
    function loginForm() {
		$id='login_'.time();
        $login = "<li><label class='text-gray'><i class='fi-torso'></i> ".$this->SLIM->language->getStandard('username')."</label><input tabIndex=\"1\" id=\"{$id}\" class=\"giant\" type=\"text\" name=\"username\"></li>";
        $login.="<li><label class='text-gray'><i class='fi-lock'></i> ".$this->SLIM->language->getStandard('password')."</label><input tabIndex=\"2\" class=\"giant\" type=\"password\" name=\"userpwd\"></li>";
        $login.="<li>";
        $login.="<div class=\"button-group expanded small radius\">";
        $act=($this->AJAX)?'data-close':'onclick="javascript:history.back();"';
        $login.="<button class=\"button secondary\" ".$act." type=\"button\" tabIndex=\"3\"><i class=\"fi-x-circle\"></i> ".$this->SLIM->language->getStandard('cancel')."</button>";
        if ($this->SIGNUP)
            $login.='<li><p class="giant" >If you don\'t have an account, click the "New Member" tab above.</p></li>';
        if ($this->REMINDER)
            $login.='<button type="button" tabIndex=\"4\" class="button button-yellow text-black loadME" data-ref="' . $this->REMINDURL . '" ><i class="fi-mail"></i> '.$this->SLIM->language->getStandard('reset password').'</button>';
        $login.="<button class=\"button button-olive\" type=\"submit\" tabIndex=\"3\"><i class=\"fi-check\"></i> ".$this->SLIM->language->getStandard('login')."</button>";
        $login.="</div></li>";
        $output['login'] = '<ul class="login">'.$login.'</ul>';
        if ($this->SIGNUP) {
            $output['new_member'] = $this->signupForm();
        }
        $output['js']='<script>setTimeout(function(){document.getElementById("'.$id.'").focus();},1000);</script>';
        return $output;
    }

    function logoutForm() {
        $logout = '<li><p class="h4 text-center callout warning" >'.$this->SLIM->language->getStandardPhrase('log_me_out').'</p></li>';
        $logout.="<li><div class=\"button-group expanded small radius\">";
        $act=($this->AJAX)?'data-close':'onclick="javascript:history.back();"';
        $logout.="<button class=\"button secondary\" ".$act." type=\"button\"><i class=\"fi-x-circle\"></i> ".$this->SLIM->language->getStandard('cancel')."</button>";
        $logout.="<button class=\"button button-yellow text-black\" type=\"submit\"><span><i class=\"fi-x\"></i> ".$this->SLIM->language->getStandard('logout')."</span></button>";
        $logout.="</div></li>";
        $output['logout'] = '<ul class="login">'.$logout.'</ul>';
        return $output;
    }
    function resetForm() {
		// use class reset_password.php
		$reset='<input type="hidden" name="action" value="reset_my_password"/>';
		$reset.='<div class="callout warning" style="margin-bottom:0;">';
		$reset.=$this->SLIM->language->getStandardPhrase('reset_info');
		$reset.='<label>'.$this->SLIM->language->getStandard('email').'<input type="email" name="umail" placeholder="me@home.com" required=""/></label>';
		$reset.='</div><div class="button-group expanded">';
		$reset.='<button class="button secondary " data-close><i class="fi-x"></i> '.$this->SLIM->language->getStandard('cancel').'</button>';
		$reset.='<button type="submit" class="button button-olive"><i class="fi-mail"></i>  '.$this->SLIM->language->getStandard('send_request').'</button>';
		$reset.='</div>';
        $output['reset'] = $reset;
        return $output;
    }

    function SignupForm($refill = false) {
        $details = ($refill) ? $refill[0] : false;
        $errors = ($refill) ? $refill[1] : false;
        $output='';
        $privacyBlurb = "The details you provide will be used for the pimary purpose of administering this website.";
        $privacyBlurb.="&nbsp;&nbsp;We may also contact you to gather feedback on our services or products.<br/><br/>We shall <strong style='color:#FCE329'>never</strong> give your details to any third party for marketing or any other reason, unless ordered to by the governing law body.</br></br>If you do not want us to contact you, please select the relevant option below.<br/><br/>You can find out more about our <a target='_blank' href='{URL}page/privacy-policy'>privacy policy here</a>";
        $frm['newname'] = array('label' => 'Full Name', 'desc' => 'Enter your full name here.');
        $frm['newemail'] = array('label' => 'Email', 'desc' => 'This will need to be validated.');
        $frm['newusername'] = array('label' => 'Username', 'desc' => 'You will use this to login.');
        $frm['newpwd'] = array('label' => 'Password', 'desc' => 'Passwords should be at least 8 characters long and be alpha-numeric.  Passwords are case sensative.');
        $frm['newpwd_b'] = array('label' => 'Password Check', 'desc' => 'Enter your password again.');
        $frm['contactme'] = array('label' => 'Privacy', 'desc' => $privacyBlurb);
        foreach ($frm as $i => $v) {
            $err = ($errors[$i]) ? '<span class="error">' . $errors[$i] . '</span>' : '';
            $errc = ($errors[$i]) ? 'error' : '';
            switch ($i) {
                case 'contactme':
                    $args['name'] = 'fax';
                    $args['opt'][0]['value'] = 'no';
                    $args['opt'][0]['label'] = 'Please don\'t bother me.';
                    $args['opt'][1]['value'] = 'yes';
                    $args['opt'][1]['label'] = 'Sure!, keep me informed.';
                    $chk = ($details[$i] == 'no') ? 0 : 1;
                    $args['opt'][$chk]['checked'] = true;
                    $out = togSwitch($args);
                    $output.="<li><label>{$v['label']}: <small>{$v['desc']}</small></label>$out $err</li>";
                    break;
                default:
                    $type = (in_array($i, array('newpwd', 'newpwd_b'))) ? 'password' : 'text';
                    $output.="<li><label>{$v['label']}: <small>{$v['desc']}</small></label><input class=\"giant $errc\" type=\"$type\" name=\"$i\" value=\"" . $details[$i] . "\">$err</li>";
            }
        }

        $output.="<li>";
        $output.="<div class=\"fcontrols\">";
        $output.="<button id=\"cClose\" class=\"button small radius popin-close\" name=\"eAction\" value=\"Cancel\" type=\"button\"><span>Cancel</span></button>";
        $output.="<button id=\"lSubmit\" class=\"button small radius green right\" name=\"eAction\" value=\"Signup\" type=\"submit\"><span>Create Account</span></button>";
        $output.="</div><input type=\"hidden\" name=\"action\" value=\"signup\"></li>";
        return $output;
    }

    function doLogin($username, $password) {
        $query = $this->LOGINQ2;
        $user = [];
        $cartname='mycart';
        $tmpCart = issetCheck($_SESSION,$cartname);
        $last_page = issetCheck($_SESSION,'last_page');
        if ($chk = $this->login_threshold($username)) {
            return array($chk, $user);
        }
        $db=$this->SLIM->db->Users();       
        $rec=$db->where('Username',$username)->or('Email',$username)->limit(1);
         $rec=renderResultsORM($rec);
         if ($rec) {
			if(is_array($rec)){
				$rec=(object)current($rec);
			}
            $sk=$this->SALT_KEY;
            $salt=(isset($rec->$sk))?$rec->$sk:false;
            if(!$salt) $salt= (isset($rec->Salt))?$rec->Salt:'';
            $chk = TextFun::quickHash(array('info'=>$password.$salt,'encdata'=>$rec->Password));//$HASH->CheckPassword($password.$salt, $rec->Password);
              if ($chk) {
                if ($rec->Status == 2) {//unverified
                    $result = 'Sorry, you need to validate your email address before I can log you in.<br/>Please check your emails, or contact our <a class="mailto" href="mailto:' . urlencode($this->AdminMail) . '" >customer services (' . $this->AdminMail . ')</a>.';
                } else if ($rec->Status == 3) {//unverified
                    $result = 'Sorry, your email has been verified, but the account has not been activated yet. You will recieve an email from us when we have activated it.<br/>If you are still having problems logging in, please contact our <a class="mailto" href="mailto:' . urlencode($this->AdminMail) . '" >customer services (' . $this->AdminMail . ')</a>.';
                } else if ($rec->Status == 1) {//valid
                    $user['id'] = $rec->id;
                    $user['access'] = $rec->Access;
                    $user['name'] = $rec->Name;
                    $user['uname'] = $rec->Username;
                    $user['expire'] = time() + (30 * 60);
                    $user['token']=TextFun::getToken_api($user['uname']);
                    $user['dojo_lock']=$this->setDojoLock($rec->DojoLock);
                    $user['permissions']=$this->setUserPermissions($rec->Permissions,$rec->Access);
                    $user=$this->getMemberRecord($rec->MemberID,$user);
                    session_regenerate_id(TRUE);
                    /* erase data carried over from previous session */
                    $_SESSION = array();
                    $_SESSION["userArray"] = $user;
                    $_SESSION['last_page'] = $last_page;
                    //reinstate order if there was one.
                    if ($tmpCart) {
                        $_SESSION[$cartname] = $tmpCart;
                    }
                    $result = 'Okay, I have logged you in.';
                } else {//other
                    $result = 'Sorry, your account has been disabled.';
                }
             } else {// password failed
                $this->failedLogin('password failed');
                $result = 'Sorry, I could not log you in...';
            }
        } else {//user not found
            $this->failedLogin('not found');
            $result = 'Sorry, I could not log you in.';
        }
        return array($result, $user);
    }

    function login_threshold($user) {
        // this function requires class.jamSecure.php
        if (function_exists('check_failedLogin'))
            return check_failedLogin($user);
    }

    function failedLogin($msg) {
        // this function requires class.jamSecure.php
        if (function_exists('log_failedLogin')) {
            log_failedLogin($msg);
        }
    }

    function isLoggedIn() {
        if (issetOR($_SESSION["userArray"])) {
            return true;
        } else {
            return false;
        }
    }

    function doLogout() {
        unset($_SESSION["userArray"]);
        session_destroy();
        return 'Okay, I have logged you out.';
    }
	function setUserPermissions($perms=false,$access=0){
		$default=$this->ROLES->get('role',$access);
		if(!issetVar($perms)){
			$perms=$default['perms'];
		}else{
			$perms=unserialize($perms);
		}
		return $this->ROLES->get('permissions',$perms);
	}
	function setDojoLock($lock=false){
		$out=false;
		if(issetVar($lock)){
			if($lock==='all'){
				//no lock
			}else{
				$out=unserialize($lock);
			}
		}
		return $out;
	}
   function getMemberRecord($id=0,$user){
		$user['MemberID']=(int)$id;
		if($user['MemberID']>0){
	        $tbl=$this->SLIM->db->Members();
	        $tbl->select('MemberTypeID,DojoID,CurrentGrade AS Grade,CGradeName AS GradeName,Email,Language');
			$rec=$tbl->where('MemberID',$user['MemberID']);
			if(count($rec)>0){
				foreach($rec[0] as $i=>$v){
					if($i==='Email'){
						if(!issetCheck($user,'Email')) $user[$i]=$v;					
					}else{
						$user[$i]=$v;						
					}
				}
			}
		}
		return $user;	
 	}

}

