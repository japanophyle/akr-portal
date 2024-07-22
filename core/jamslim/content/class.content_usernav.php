<?php
class content_usernav{
	private $SLIM;
	private $ROUTE;
	private $SLUG='home';
	public $DATA;
	
	private $AJAX;
	private $ARGS;
	private $AJAXLOGIN=true;
	private $USER;
	private $HASCART;
	private $BETA_USER_ID;
	private $SITE;
	private $SECTION;
	private $BETA_SWITCH=false;
	private $USE_MY_HOME=true;
	private $USE_CART=false;
	private $BETA_URL;
	private $USE_CHAT=false;
	private $CHAT_POWER;
	private $LEADER;
	private $PERMURL;
	private $ADMIN;
	
	function __construct($slim=null){
		if(!$slim) throw new Exception('no slim object!!');
		$this->SLIM=$slim;
		$this->BETA_URL=($_SERVER['SERVER_NAME']==='jamserver')?'_default.php':'default.php';				
		$this->AJAX=$slim->router->get('ajax');
		$this->ROUTE=$slim->router->get('route');
		$this->PERMURL=URL.implode('/',$this->ROUTE);
		$this->SECTION=issetCheck($this->ROUTE,0,'page');
		$this->SLUG=issetCheck($this->ROUTE,1);
		$this->SITE=($this->ROUTE[0]==='admin')?'admin':'page';
		$this->USER=$slim->user;
		$this->ADMIN=($this->USER['access']>=$slim->AdminLevel)?true:false;
		if(!$this->ADMIN && $this->USER['access']==$slim->LeaderLevel) $this->LEADER=true;	
		//public site edit link
		if(in_array($this->SECTION,array('book','publisher'))){
			$this->SLUG=issetCheck($this->ROUTE,2);
		}else{
			$this->SECTION='page';
		}
		$this->initCart();
		$this->initChat();
	}
	private function initCart(){
		if(!$this->USE_CART) return;
		$this->HASCART=$this->SLIM->Cart->hasCart();
	}
	private function initChat(){
		if(!$this->USE_CHAT) return;
		$this->CHAT_POWER=(int)$this->SLIM->Options->get('site_chat_power','value');
	}
	//common methods
	function render($args=false){
		$this->setArgs($args);
		return $this->renderContent();
	}
	
	private function setArgs($args=false){
		$this->ARGS=$args;
	}
	
	// custom methods
    function renderContent(){
        $loginloader = URL.'page/login';
        $myHome=URL.'page/my-home';
        $cartloader = URL.'page/cart&amp;act=ez_show';;
        $popClass = '';
        $link=[];
        $nav=$dev_switch=$beta_switch=false;
        $str_my_account=$this->SLIM->language->lang('my_account');
        $str_public_site=$this->SLIM->language->lang('public_site');
          if($this->AJAXLOGIN){
            $loginloader = URL.'page/login';
            $popClass =  'loadME';
        }
        $login = '<button title="login" class="' . $popClass . '" href="' . $loginloader . '">Login</button>';
        if($this->HASCART){
             $link[]='<button id="cartbutton" class="link-gbm-blue gotoME" data-ref="' . URL . 'page/my-cart"><i class="fi-shopping-cart"></i> My Cart</a>';
 			 $username= '<strong>Guest User</strong>';
        }
        if($this->USER['id']){
			$username= 'Logged in as <strong>' . $this->USER['name'] . '</strong>';
			if($this->ADMIN){
				$link[]='<button data-open="admin-bar" data-size="large" class="link-gbm-blue"><i class="fi-widget"></i> Admin Menu</button>';
				$link[]='<button data-size="medium" class="link-gbm-blue loadME" data-ref="'.URL.'admin/member/search" title="find members"><i class="fi-torso"></i> Search</button>';
				if($this->SITE==='admin'){
				    $link[]='<button title="home page" class="link-gbm-blue gotoME" data-ref="' . URL . 'page/home"><i class="fi-home"></i> '.$str_public_site.'</button>';
				}else{
					switch($this->SECTION){
						case 'page': case 'book':
							if($this->SLUG){
								$link[]='<button title="edit '.$this->SECTION.'" class="link-gbm-blue gotoME" data-ref="' . URL . 'admin/'.$this->SECTION.'/edit/'.$this->SLUG.'"><i class="fi-pencil"></i> Edit</button>';
							}
							break;
					}				
				    $link[]=$this->renderLangButton();
				}
				if($this->BETA_SWITCH) {
					//$link[]='<a title="leave the beta site" class="button-orange" href="' .URL.$this->BETA_URL.'"><i class="fi-shuffle"></i> Alpha Site</a>';
				}
			}else if($this->LEADER){
				$link[]='<button data-open="admin-bar" data-size="large" class="link-gbm-blue"><i class="fi-widget"></i> Admin Menu</button>';
				$link[]='<button data-size="medium" class="link-gbm-blue loadME" data-ref="'.URL.'admin/member/search" title="find members"><i class="fi-torso"></i> Search</button>';
				if($this->SITE==='admin')  $link[]='<button title="home page" class="link-gbm-blue gotoME" data-ref="' . URL . 'page/home"><i class="fi-home"></i> '.$str_public_site.'</button>';
			}
			if($this->USER['access']>=1){
				$dojo_id=$this->renderMyDojoLink();
				if($this->USE_MY_HOME) $link[]='<button style="padding:.7rem" data-ref="' . URL . 'page/my-home" class="link-olive gotoME"><i class="fi-torso"></i> '.$str_my_account.'</button>';
				if($dojo_id!=='') $link[]=$dojo_id;
			}
			if($this->USE_CHAT &&($this->CHAT_POWER || $this->ADMIN)){
				$link[]='<button title="visit the chat rooms" style="padding:.7rem" class="link-yellow gotoME" data-ref="' . URL . 'chatter"><i class="fi-comment"></i> Chatter</button>';
			}
		    $link[]='<button title="logout" class="link-gbm-blue loadME" data-ref="' . URL . 'page/login"><i class="fi-lock"></i> Logout</button>';
		}
        if($link){
			$unav='<ul class="menu align-right"><li>'.implode('</li><li>',$link).'</li></ul>';
			$nav='<nav class="user-bar"><div class="grid-x"><div class="cell medium-4 columns show-for-medium"><div class="uname text-gbm-blue">'.$username.'</div></div><div class="cell medium-8">'.$unav.'</div></div></nav>';
        }
        if(!$nav||$nav==='') $nav=' ';
        return $nav;		
	}
	private function renderLangButton(){
		$pow=$this->SLIM->language->get('_POWER');
		$mode=$this->SLIM->language->get('_MODE');
		$b64=base64_encode($this->PERMURL);
		if(!$pow) return '';
		if($mode==='edit'){
			$button='<button style="padding:.7rem" class="link-blue gotoME" data-ref="'.URL.'admin/lang" title="view the language database"><i class="fi-comments"></i> Language DB</button>';
			$button.='<button style="padding:.7rem" class="link-purple gotoME" data-ref="'.URL.'page/lang/mode/translate?u='.$b64.'" title="turn off language edit mode"><i class="fi-comment-quotes"></i> Language</button>';
		}else{		
			$button='<button style="padding:.7rem" class="link-lavendar gotoME" data-ref="'.URL.'page/lang/mode/edit?u='.$b64.'" title="turn on language edit mode"><i class="fi-comment-quotes"></i> Language</button>';
		}
		return $button;		
	}

	private function renderMyDojoLink($menu=false){
		//check dojo lock
		$DL=issetCheck($this->USER,'dojo_lock');
		$DJ=(int)issetCheck($this->USER,'DojoID',0);
		$link='';
		if($DL || $this->ADMIN){
			if($menu){
				foreach($DL as $d){
					$link='<button style="padding:.7rem" data-ref="' . URL . 'page/my-dojo/'.$d.'" class="link-gold gotoME"><i class="fi-target"></i> My Dojo</button>';
				}
			}else{
				$link='<button style="padding:.7rem" data-ref="' . URL . 'page/my-dojo/menu" class="link-gold loadME"><i class="fi-target"></i> My Dojo</button>';
			}			
		}else if($DJ){
			$link='<button style="padding:.7rem" data-ref="' . URL . 'page/my-dojo" class="link-gold gotoME"><i class="fi-target"></i> My Dojo</button>';
		}
		return $link;		
	}
}
