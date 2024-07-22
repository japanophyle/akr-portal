<?php
class Register {
    private $reg = array(
		'URL_TYPE'=>'clean',// always clean??
		'URL_PROTOCOL'=>'https://',//the default protocol
		'HOME_PAGE'=>'home',
		'ADMIN_PAGE'=>'admin',
		'PAGE_SLUG'=>'page',
		'ADMIN_SLUG'=>'admin',
		'ENVIRONMENT'=>'development',//live or development
		'SITE_NAME'=>'MemberME.2',
		'NAVIGATION_TYPE'=>'topbar',
		'SITE_VIEW'=>'public',
		'ROUTE_OFFSET'=>0,//change to 2 if using ngrok
		'SITE_LANGUAGE'=>'eng',
		'SITE_SHORT_NAME'=>'ME2',
		'MAILBOT'=>false,
		'ADMINBOT'=>false,
		'ASSET_VER_PUBLIC'=>1,
		'ASSET_VER_ADMIN'=>1,
		'DB'=>array(
			'DB_TYPE'=>'mysql',
			'DB_HOST'=> 'localhost',
			'DB_NAME'=> 'ukka_man',
			'DB_USER'=> 'pmauser',
			'DB_PASS'=> 'daze',
			'DB_CHARSET'=> 'utf8'
		),
		'DB_TABLES'=>array(//table alias
			'users'=>array('table'=>'myp_users','label'=>'Members','key'=>'user','single'=>'Member'),
			'page'=>array('table'=>'myp_items','label'=>'Pages','key'=>'page','single'=>'Page'),
			'options'=>array('table'=>'myp_options','label'=>'Options','key'=>'options','single'=>'Option'),
			'course'=>array('table'=>'myp_study_groups','label'=>'Classes','key'=>'course','single'=>'Class'),
			'resources'=>array('table'=>'myp_resources','label'=>'Resources','key'=>'resources','single'=>'Resource'),
		),
		'MERCHANT'=>array(
			'id' => false,
			'code' => '???',
			'email' => ''
		),
		'USER_ACCESS'=>array(
			0=>array('label'=>'Guest','color'=>'gray'),
			20=>array('label'=>'User','color'=>'aqua'),
			21=>array('label'=>'Leader','color'=>'blue'),
			25=>array('label'=>'Admin','color'=>'green'),
			30=>array('label'=>'Super','color'=>'lavendar')
		),
		'TEMPLATES'=>array(
			'public'=>'tpl.mnkr_public.html',
			'admin'=>'tpl.admin.html'
		),
		'PARENTS'=>array(
			'BOOK_PARENT'=>2, //this is the itm_ID for the 'books' page, ** think of way to set atuomatically **
			'BLOG_PARENT'=>93, //this is the itm_ID for the 'vlog' page, ** think of way to set atuomatically **
			'TESTIMONIAL_PARENT'=>1,//this is the itm_ID for the 'testimnials' page, ** think of way to set atuomatically **
			'PRODUCT_PARENT'=>3062,//this is the itm_ID for the 'products' page, ** think of way to set atuomatically **
			'EVENTS_PARENT'=>2540 //this is the itm_ID for the 'events' page, ** think of way to set atuomatically **
		),
		'DEV_URL'=>'jamserver/ukka_man2/',
		'TIME_ZONE'=>'UTC',
		'PUBLIC'=>array(
			// slug of pages not to be cached or set as the last page visited
			'skip_slugs'=>array('login', 'my-home', 'payments', 'paypalfail', 'paypalgood', 'feeder', 'verify', 'sign-up', 'home', 'status', 'login-reminder', 'login-reset', 'search-results'),
			'sitemap' => array (
				0 => 'checkout',
				1 => 'login',
				2 => 'my-cdb',
				3 => 'my-home',
				4 => 'cart',
				5 => 'verify',
				6 => 'status',
				7 => 'home',
			),
			'cache' => array (
				0 => 'checkout',
				1 => 'login',
				2 => 'my-tbs',
				3 => 'my-home',
				4 => 'cart',
				5 => 'verify',
				6 => 'status',
				7 => 'library-catalogue',
				8 => 'audio-library',
				9 => 'site-search',
				10 => 'search-results',
				11 => 'events',
				12 => 'documents',
				13 => 'offers',
				14 => 'files',
				15 => 'tutorials',
				16 => 'mailinglist',
				17 => 'user-groups',
				18 => 'my-classes',
				19 => 'shop',
				20 => 'mailer',
		   ),
			'post' =>array (
				0 => 'login',
				1 => 'logout',
				2 => 'ez_add',
				3 => 'remind',
				4 => 'remindv',
				5 => 'sitesearch',
				6 => 'dosearch',
			),
			'faux' =>array (
				0 => 'payments',
				1 => 'paypalfail',
				2 => 'paypalgood',
				3 => 'feeder',
				4 => 'login-reminder',
				5 => 'login-reset',
				6 => 'search-results',
				7 => 'checkout',
				8 => 'verify',
				9 => 'gatewayerror',
				10=> 'status',
				11=> 'unsubscribe',
				12=> 'response',
				13=> 'mailer',
				14=> 'login',
				15=> 'logout',
				16=> 'postage_zones',
				17=> 'media-view',
				18=> 'video',
				19=> 'tours',
				20=> 'listen',
				21=> 'captcha',
				22=> 'lang',
				23=> 'dojo',
				24=> 'reset-password',
				25=> 'my-dojo',
			),
			'never_cache' =>array (
				0 => 'payments',
				1 => 'paypalfail',
				2 => 'paypalgood',
				3 => 'feeder',
				4 => 'login-reminder',
				5 => 'login-reset',
				6 => 'search-results',
				7 => 'checkout',
				8 => 'login',
				9 => 'my-cdb',
				10 => 'my-home',
				11 => 'cart',
				12 => 'verify',
				13 => 'status',
				14 => 'library-catalogue',
				15 => 'audio-library',
				16 => 'site-search',
				18 => 'events',
				19 => 'society-news',
				20 => 'documents',
				21 => 'files',
				22 => 'offers',
				23 => 'tutorials',
				24 => 'user-groups',
				25 => 'mailinglist',
				26 => 'my-classes',
				27 => 'shop',
				28=> 'unsubscribe',
				29 => 'mailer'
		  ),
		)
	);


	function __construct($ureg=[]){
		$this->initReg($ureg);
		$this->setTimeZone();
		//$this->setEnvironment();
	}
	private function initReg($ureg=[]){
		if($ureg && is_array($ureg)){
			foreach($ureg as $i=>$v){
				switch($i){
					case 'DB':
						foreach($v as $x=>$y) $this->reg['DB'][$x]=$y;
						break;
					default:
						$this->set($i,$v);
				}
			}
		}		
	}
	private function setTimeZone(){
		$tz=date_default_timezone_get();
		if($tz!==$this->reg['TIME_ZONE']){
			date_default_timezone_set($this->reg['TIME_ZONE']);
			$tz=date_default_timezone_get();
		}
	}
	public function get($what=false,$key=false){
		if($what){
			switch($what){
				case 'all':
					return $this->reg;
					break;
				case 'config':
					return $this->getConfig();
					break;
				default:
					$rec=$this->issetCheck($this->reg,$what);
					if($key && is_array($rec)) $rec=$this->issetCheck($rec,$key);
					return $rec;
			}
		}
		return false;			
	}
	
	public function set($what=false,$var=false){
		if($what){
			$this->reg[$what]=$var;
			return true;
		}
		return false;			
	}
	
	private function getConfig(){
		$config=array(
			'URL_TYPE'=>$this->get('URL_TYPE'),
			'URL_PROTOCOL'=>$this->get('URL_PROTOCOL'),
			'HOME_PAGE'=>$this->get('HOME_PAGE'),
			'ADMIN_PAGE'=>$this->get('ADMIN_PAGE'),
			'PAGE_SLUG'=>$this->get('PAGE_SLUG'),
			'ENVIRONMENT'=>$this->get('ENVIRONMENT'),
			'TEMPLATES'=>$this->get('TEMPLATES'),
			'PARENTS'=>$this->get('PARENTS'),
			'PUBLIC'=>$this->get('PUBLIC'),
			'NAVIGATION_TYPE'=>$this->get('NAVIGATION_TYPE'),
			'PAYPAL'=>$this->get('PAYPAL'),
			'USER_ACCESS'=>$this->get('USER_ACCESS'),
			'DEV_URL'=>$this->get('DEV_URL'),
			'ROUTE_OFFSET'=>$this->get('ROUTE_OFFSET'),
			'MAILBOT'=>$this->get('MAILBOT'),
			'ADMINBOT'=>$this->get('ADMINBOT'),
			'SITE_SHORT_NAME'=>$this->get('SITE_SHORT_NAME'),
			'ASSET_VER_PUBLIC'=>$this->get('ASSET_VER_PUBLIC'),
			'ASSET_VER_ADMIN'=>$this->get('ASSET_VER_ADMIN'),
		);
		$db=$this->get('DB');
		foreach($db as $i=>$v) $config[$i]=$v;
		return $config;
	}
	
	private function issetCheck($arr=false,$key=false,$default=false) {
		//for arrays only!!
		$return = $default;
		if(is_array($arr) && !empty($arr)){
			if(is_string($key)||is_integer($key)){
				$return = (isset($arr[$key]))? $arr[$key]:$default;
			}
		}
		return $return;
	}		
}
$REG=new Register($ukka_reg);
$config=$REG->get('config');
