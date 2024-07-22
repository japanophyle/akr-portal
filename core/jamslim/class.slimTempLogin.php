<?php
class slimTempLogin {
	private $SLIM;
	private $USER;//current user
	private $ROUTE;
	private $POST;
	private $OUTPUT;
	private $VIEW;
	private $MSG;
	private $AJAX;
	private $PERMLINKS;
	private $PERMLINK;
	private $PUBLIC_URL;
	private $SUPERLOGIN;//admin login session
	private $tmpLOGIN;
	private $MEMBER;
	private $OPTIONS=array(
		'super_session'=>'superLogin',
		'login_session'=>'tmpAdminLogin',
	);	
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		$this->ROUTE=$slim->router->get('route');
		$this->AJAX=$slim->router->get('ajax');
		$this->PERMLINKS=$slim->router->get('permlinks');
		$this->PUBLIC_URL=$this->PERMLINKS['base'].'page/';
		$this->PERMLINK=$this->PERMLINKS['link'].'member/';
        $this->SUPERLOGIN = issetCheck($_SESSION,$this->OPTIONS['super_session']);
        $this->tmpLOGIN = issetCheck($_SESSION,$this->OPTIONS['login_session']);//flag for public site classes
	}
	
	function get($what=false,$args=false){
		switch($what){
			case 'status':
				return $this->tmpLOGIN;
				break;
			case 'set':case 'login':
				return $this->initLogin($args);
				break;
			case 'cancel':case 'logout':
				return $this->cancelTempLogin(true);
				break;
			default:
				return $this->renderDash();
		}
		return false;			
	}
    private function initLogin($user=false){
        $this->canceltempLogin();
        if($this->USER['access']<$this->SLIM->AdminLevel) return 'Sorry, you can\'t do that...';
		if(!is_array($user)) return 'Sorry, no user data';
		$chk=array('id','name','uname','member_id','dojo_id');
		foreach($chk as $i){
			if(!isset($user[$i])) return 'Sorry, invalid user data: '.$i.' is missing';
		}
		$this->MEMBER=$user;
		return $this->tempLogin();		
    }
    private function setSuperSession(){
		$_SESSION[$this->OPTIONS['super_session']]=$this->SUPERLOGIN=$this->USER;
	}
    private function tempLogin() {
		$this->tmpLOGIN = false;
        if ($this->MEMBER) {
			$this->setSuperSession();
            $this->USER['id'] = $this->MEMBER['id'];
            $this->USER['name'] = $this->MEMBER['name'];
            $this->USER['uname'] = $this->MEMBER['uname'];
            $this->USER['MemberID'] = $this->MEMBER['member_id']; 
            $this->USER['DojoID'] = $this->MEMBER['dojo_id'];                                
            $_SESSION[$this->OPTIONS['login_session']] = 'yes';
            $_SESSION["userArray"] = $this->USER;
            $this->tmpLOGIN = true;
            return;
        }
        return 'Sorry, the "login as user" has failed...';
    }
    private function cancelTempLogin($respond=false) {
		if($this->SUPERLOGIN){
			if ($this->USER['id'] != $this->SUPERLOGIN['id']) {//switch back
				$this->SUPERLOGIN['expire']=$this->USER['expire'];//to keep the admin logged in
				$this->USER = $this->SUPERLOGIN;
				$_SESSION["userArray"] = $this->USER;
				unset($_SESSION[$this->OPTIONS['super_session']],$_SESSION[$this->OPTIONS['login_session']]);
				if($respond){
					setSystemResponse($this->PERMLINK, 'Okay, welcome back ' . $this->USER['name']);
					die;
				}
			}
		}
		$this->tmpLOGIN = false;
    }
    private function renderDash(){
		$title='Temp. Login';
		if($this->tmpLOGIN && $this->MEMBER){
			$state='success';
			$title.=' Complete';
			$content= 'Great!, you are now logged in as <strong>' . $this->MEMBER['name'] . '</strong>.<br/><em class="text-red">Be sure <strong>NOT</strong> to make any changes while your are logged in as this user.</em><br/>Veiw the main site as this user <a class="button small radius bg-purple" href="' . $this->PUBLIC_URL. 'my-home">View "My Account" page</a>,<br/>Return to the admin area to switch back, or <a class="button small radius primary" href="' . $this->PERMLINK. 'canceltmplogin">click this</a>';
		}elseif($this->MEMBER){
			$state='alert';
			$content = 'The temporary login as ' . $this->MEMBER['name'] . ' has failed...  Please try again.<br/><a class="button purple" href="' . $this->PERMLINK.'tmplogin/'.$this->MEMBER['id'].'">Login as User</a>';
		}elseif($this->tmpLOGIN){
			$state='warning';
			$content = 'You are logged in as <strong>' . $this->USER['name'] . '</strong>.<br/>You will need to cancel the login to continue using the admin area.<br/><a class="button button-maroon" href="' . $this->PERMLINK.'canceltmplogin/">Cancel Temp. Login</a><br/>View the main site as this user.<br/><a class="button small radius bg-purple" href="' . $this->PUBLIC_URL. 'my-home">View "My Account" page</a>';
		}else{
			$state='primary';
			$content = 'The temporary login has been cancelled.<br/><a class="button primary" href="' . $this->PERMLINK.'">Return to the User Dashboard</a>';
		}
		$out['content'] = '<div class="callout '.$state.'"><h3>'.$title.'</h3><p>'.$content.'</p></div>';
        $out['title'] = 'Temporary User Login:';
        return $out;
	}
}
