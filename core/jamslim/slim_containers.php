<?php
//common containers
//base page slugs
$container['BASE_SLUGS']=array('admin'=>'admin','public'=>'page');

$container['ezPDO']=function($c){
	return new ezPDO($c->pdo,$c->Options);
};
$container['router']=function($c){
	return new slimRouter($c);
};
$container['AppVars']=function($c){
	//alias
	return $c->router;
};
$container['HelpBot']=function($c){
	return new slimHelpbot($c);//generic object
};
$container['language'] = function($c) {
    return new slimLanguage($c);
};
$container['language_dates'] = function($c) {
    return new slimLanguage_dates($c);
};
$container['Logger']=function($c){
	return new Logger($c);
};
$container['Cron']=function($c){
	return new slimCron($c);
};
$container['user']=function($c){
	return getMySession();
};
$container['session']=function(){
	return issetCheck($_SESSION,'jamSlim',[]);
};
$container['Options']=function($c){
	return new slimOptions($c);
};
$container['options']=function($c){
	//alias
	return $c->Options;
};
$container['Template']=function($c){
	return new slimTemplates($c);
};
$container['DataBank']=function($c){
	return new slimDataBank($c);
};
$container['Events']=function($c){
	return new slimEvents($c);
};
$container['EventContent']=function($c){
	return new event_content($c);
};
$container['EventsLib']=function($c){
	return new slim_db_events($c);
};
$container['EventForms']=function($c){
	return new slimEventForms($c);
};
$container['EventReporter']=function($c){
	return new EventReporter($c);
};
$container['Books']=function($c){
	return new slimBooks($c);
};
$container['Products']=function($c){
	return new slimProducts($c);
};
$container['Categories']=function($c){
	return new slimCategory($c);
};
$container['Permissions']=function($c){
	return new slimPermissions($c);
};
$container['Login']=function($c){
	return new slimLogin($c);
};
$container['Media']=function($c){
	return new slimMedia($c);
};
$container['Download']=function($c){
	return new slimDownload($c);
};
$container['jamForm']=function(){
	return new jamForm;
};
$container['Subscriptions']=function($c){
	return new slim_db_subscriptions($c);
};

$container['Users']=function($c){
	return new slimUsers($c);
};
$container['Image']=function($c){
	return new slimImage($c);
};
$container['TextFun']=function($c){
	return new text_fun;
};
$container['Sales']=function($c){
	return new slimSales($c);
};
$container['SalesMan']=function($c){
	return new slimSalesMan($c);
};
$container['user_roles']=function($c){
 	$ob = new slimPermissions($c);
	return $ob;
};
$container['bouncer']=function($c){
 	$ob = new slimBouncer($c);
	return $ob;
};

//emails
$container['Emailer']=function($c){
	//interface for editing & sending emails
	return new slimEmail($c);
};
$container['Recipients']=function($c){
	//interface for editing & sending emails
	return new slimEmail_recipients($c);
};
$container['Mailer']=function($c){
	//sends the email using PHP mailer
	return new slimMailer($c);
};
$container['EmailParts']=array(
	'logo'=>URL.'gfx/akr/akr_logo_badge_email.png',
	'header'=>'<p>American Kyudo Renmei<br/>USA<br/><a href="https://members.kyudousa.com">https://members.kyudousa.com</a></small></p>',
	'content'=>null,			
	'footer'=>'<p>Sent by AKR, USA</p><p>Web: <a href="https://members.kyudousa.com">https://members.kyudousa.com</a></p>',
	'pdf_header'=>'American Kyudo Renmei<br/>USA'
);

//public
$container['idIcon']=function(){
	//return new initialAvatar;
	return new initialAvatar_css;
};
$container['Members']=function($c){
	//return new slimMyHome($c);
	return new slimMember($c);
};
$container['MembersLib']=function($c){
	return new slim_db_members($c);
};
$container['memberNav']='';
$container['Page']=function($c){
	return new slimPage($c);
};
$container['Demo']=function($c){
	return new slimDemoPage($c);
};
$container['PageContent']=function($c){
	return new slimPageContent($c);
};
$container['ShortCoder']=function($c){
	return new slimShortCoder($c);
};
$container['Dom']=function($c){
	return new slimHtmlDom($c);
};
$container['Postman']=function($c){
	return new slimPostman($c);
};
$container['Infobox']=function($c){
	return new slimInfobox($c);
};
$container['GMap']=function($c){
	return new google_maps_buddir($c);
};
$container['Cart']=function($c){
	//generate tbs pdf form from ref number
	return new cart($c);
};
$container['Payments']=function($c){
	//ipn & payments responder
	return new slimPayments($c);
};
$container['PostageRates']=function($c){
	return new slimPostageRates($c);
};

// merchants - paypal
$container['Paypal']=function($c){
	return new paypal($c);
};
$container['Paypal_response']=function($c){
	return new paypal_response($c);
};
$container['Paypal_ipn']=function($c){
	return new paypal_ipn($c);
};
$container['Paypal_merchant']=function($c){
	return new paypal_merchant($c);
};
//event calendar
$container['Calendar']=function($c){
	return new event_calendar($c);
};
$container['JCAL_Functions']=function($c){
	return new jcal_functions($c);
};

//admin
$container['SuperLevel']=30;
$container['AdminLevel']=25;
$container['LeaderLevel']=21;
$container['UserLevel']=20;
$container['Admin']=function($c){
	return new slimAdmin($c);
};
$container['AdminPlugins']=array(
	'dash'=>array('url'=>'','icon'=>'layout','label'=>'Dashboard','access'=>21,'menu'=>true),
	'events'=>array('url'=>'events/','icon'=>'calendar','label'=>'Events','access'=>25,'menu'=>true),
	'member'=>array('url'=>'member/','icon'=>'male-female','label'=>'Member Manager','access'=>21,'menu'=>true),
	'dojo'=>array('url'=>'dojo/','icon'=>'target','label'=>'Dojo','access'=>21,'menu'=>true),
	'subscription'=>array('url'=>'subscription/','icon'=>'results-demographics','label'=>'Subscriptions','access'=>25,'menu'=>true),
	'sales'=>array('url'=>'sales/','icon'=>'shopping-cart','label'=>'Sales Manager','access'=>21,'menu'=>true),
	'product'=>array('url'=>'product/','icon'=>'price-tag','label'=>'Products','access'=>25,'menu'=>true),
	'appform'=>array('url'=>'appform/','icon'=>'clipboard-pencil','label'=>'Application Forms','access'=>21,'menu'=>true),
	'mailer'=>array('url'=>'mailer/','icon'=>'mail','label'=>'Mailer','access'=>21,'menu'=>true),				
	'page'=>array('url'=>'page/','icon'=>'page-copy','label'=>'Pages','access'=>21,'menu'=>true,'color'=>'olive'),
	'navigation'=>array('url'=>'navigation/','icon'=>'compass','label'=>'Navigation Manager','access'=>25,'menu'=>true,'color'=>'olive'),
	'user'=>array('url'=>'user/','icon'=>'torsos','label'=>'User Manager','access'=>21,'menu'=>true,'color'=>'olive'),
	'option'=>array('url'=>'option/','icon'=>'widget','label'=>'App Settings','access'=>25,'menu'=>true),
	'media'=>array('url'=>'media/','icon'=>'photo','label'=>'Image Library','access'=>21,'menu'=>true,'color'=>'olive'),
	'lang'=>array('url'=>'lang/','icon'=>'comment-quotes','label'=>'Language Lib.','access'=>30,'menu'=>true,'color'=>'olive'),
	'files'=>array('url'=>URL.'dev.filemanager2.php','icon'=>'folder','label'=>'File Manager','access'=>30,'menu'=>true,'color'=>'maroon'),
	'zipper'=>array('url'=>'zipper/','icon'=>'archive','label'=>'Zip Manager','access'=>30,'menu'=>true,'color'=>'maroon'),
	'site'=>array('url'=>'site/','icon'=>'wrench','label'=>'Site Options','access'=>30,'menu'=>true,'color'=>'maroon'),
	'cart'=>array('url'=>'cart/','icon'=>'pricetag-multiple','label'=>'Transactions','access'=>30,'menu'=>true,'color'=>'maroon'),
);
$container['Filemanager']=function($c){
	return new slimFileMan($c);
};
//system session message
$container['system_message']=function($c){
	return getSystemResponse();
};
$container['closer']=function($c){
	return '<button class="close-button" data-close type="button"><i class="fi-x-circle icon-x2" ></i></button>';
};
$container['system_reg']=function(){
	return new generic_a(
		array(
			'PUBLIC_STATES' =>false,
			'SELECT_OPTIONS' =>false,
			'ACCESS' =>false,
		)
	);
};

//display objects
$container['assets']= function($c){
	return new generic_a(array('css'=>false,'js'=>false,'jqd'=>false,'template'=>false,'template_mode'=>false,'title'=>false));
};
$container['display'] = function($c){
	return new generic_a(
		array("URL" =>'',
			'TPL_RADIO_BAR'=>'',
			'TPL_TOPBAR'=>'',
			'TPL_CONTENT'=>'',
			'TPL_MESSAGE'=>'',
			'TPL_DIR'=>'',
			'TPL_JS_TEMPLATES'=>'',
			'TPL_JQD'=>'',
			'TPL_SETTINGS_MENU'=>'',
			'URL'=>URL,
			'TPL_BODY_CLASS'=>''
		)
	);
};
$container['renderOutput']=function($c){
	//render App Output
	$ajax=$c->router->get('ajax');
	$parts=$c->display->get();
	$template=$c->assets->get('template','main');
	$mode=$c->assets->get('template_mode');
	$styles=$c->assets->get('styles');
	$jqd=$c->assets->get('jqd');
	$js=$c->assets->get('js');
	$scripts=$c->assets->get('script');
	$parts['TPL_URL']=URL;
	$parts['TPL_JQD']=(is_array($jqd))?implode("\n",$jqd):'';
	$parts['TPL_JS']=(is_array($js))?implode("\n",$js):'';
	$parts['TPL_STYLES']=(is_array($styles))?implode("\n",$styles):'';
	$parts['TPL_SCRIPTS']=(is_array($scripts))?implode("\n",$scripts):'';
	if($ajax){
		$d=issetCheck($parts,'TPL_CONTENT');
		if(is_array($d)){
			jsonResponse($d);
		}
	}
	echo $c->Template->render($parts,$template,$mode);
	die;
};
//helper functionss
$container['getCurrentUserDetails']=function($c){
	$user=$c->user;
	if($user){
		$args['id']= (int)$user["id"];
		$MD= new slim_member_details($c,$user);
		return $MD->MEMBER;
		
		
		$rec=$c->db->myp_users->select('usr_Email,usr_Phone,usr_Address,usr_Fax,usr_SiteOpts')->where('usr_ID',$user["id"]);
		$rec=renderResultsORM($rec);
		$userd=current($rec);
		$details=false;
		foreach ($userd as $i => $v) {
			$k = str_replace('usr_', '', strtolower($i));
			if($k==='siteopts'){
				$details=unserialize($v);				
			}else{
				if ($k === 'fax') $k = 'privacy';
				$user[$k] = $v;				
			}
		}
		if(!$details || !is_array($details)){
			$tmpName=explode(' ',$user["name"]);
			$first=$tmpName[0];
			unset($tmpName[0]);
			$last=implode(' ',$tmpName);
			$details=array(
				   'name_title'=>0,
				   'first_name'=>$first,
				   'last_name'=>$last,
				   'country'=>'',
				   'post_code'=>'',
			);
		}
		$details['address']=$user['address'];
		$details['full_name']=$details['first_name'].' '.$details['last_name'];
		$details['password']=false;
		$details['user_id']=$user['id'];
		$details['email']=$user['email'];
		$details['phone']=$user['phone'];
		if(!$details['permissions']){
		   $details['permissions']=getUserPermissions($user['access']);
        }
 		if(!$details['avatar']){
           $details['avatar']=getAvatar($user['uname'].'_mp3');
        }
        if(!isset($details['privacy'])){
			$chk=getUserPrivacy($user['id']);
			if(!$chk) $chk=getUserPrivacy($user['email'],'email');
			if(!$chk) $chk=getUserPrivacy($details,'default',true);
			$details['privacy']=$chk;
		}
		$user['details']=$details;
		return $user;
	}
	return false;	    	
};
$container['checkAccess']=function($c){
	$checkAccess=function($what=false,$user=false){
		$CLASSES=array('intro_buddh','first_steps','first_turning','great_way','tibet_buddhism','abhidhamma','meditation','study');
		$out=false;
		if(!$user) return false;
		if($user['access']>=4) return true;
		switch($what){
			case 'student':
				foreach($user['permissions']['recs'] as $i=>$v){
					if(in_array($i,$CLASSES)){
						$o=true;
						break;
					}
				}
				break;
			case 'buddir':case 'bud_dir';
				$o=issetCheck($user['permissions']['recs'],'bud_dir');
				break;
			case 'middleway':
				$o=issetCheck($user['permissions']['recs'],'middleway');
				break;
			case 'member':
				$o=issetCheck($user['permissions']['recs'],'members');
				if(!$o){
					 $o=issetCheck($user['permissions']['perm'],'member');
				}
				break;
			case 'teacher':
				if($user['access']>=3){
					$o=issetCheck($user['permissions']['perm'],'teacher');
				}
				break;
			case 'editor':
				if($user['access']==3){
					$o=issetCheck($user['permissions']['perm'],'editor');
				}
				break;
			case 'owner':
				if($user['access']==2){
					$e=issetCheck($user['permissions']['perm'],'editor');
					$b=issetCheck($user['permissions']['recs'],'bud_dir');
					if($e && $b) $o=true;
				}
		}
		return $o;
	};
	return 	$checkAccess;		
};
$container['ShinsaRef']=function($c){
	//a refernce array for shinsa mapping
	//map grade sorting code to the values below
	return array(
		'shinsa-shodan'=>1,//mudan
		'shinsa-nidan'=>2,//1 dan
		'shinsa-sandan'=>3,//2 dan
		'shinsa-yondan'=>4,//3 dan
		'shinsa-godan'=>5,//4 dan
		'shinsa-renshi'=>6,//5 dan
		'shinsa-rokudan'=>7,//renshi 5 dan
		'shinsa-kyoshi'=>8,//renshi 6 dan
		'shinsa-nanadan'=>9,//kyoshi 6 dan
		'shinsa-hachidan'=>10,//kyoshi 7 dan
	);

};
$container['SubscriptionStates']=function($c){
	//move to db??	
	return array(
		0=>array('name'=>'disabled','color'=>'gray'),
		1=>array('name'=>'active','color'=>'olive'),
		2=>array('name'=>'expired','color'=>'maroon'),
		3=>array('name'=>'cancelled','color'=>'red-orange'),
		4=>array('name'=>'renewed','color'=>'dark-green'),
		5=>array('name'=>'paid','color'=>'dark-green'),
		6=>array('name'=>'refund due','color'=>'red-orange'),
		7=>array('name'=>'no value','color'=>'gray'),
		8=>array('name'=>'renewed_pending','color'=>'purple'),
	);	
};
$container['TempLogin']=function($c){
	return new slimTempLogin($c);
};
$container['IsTempLogin']=function($c){
	//check for temp login by admin
	$user=false;
	if($c->user['access']>=$c->AdminLevel){
		$user=slimSession('get','temp_login');
	}
	return $user;
};
$container['checkIKYF']=function($c){
	return new slimIKYF_REG($c);
};
$container['zurb']=function($c){
	return new Zurb;
};
$container['topbar']=function($c){
	return new slimNavigation($c);
};
$container['topbar_data']=function($c){
	return new slimNavigation_menu($c);
};
$container['backup_db']=function($c){
	$dsn='mysql:host='.DB_HOST.';dbname='.DB_NAME;
	$options['include-tables']=['Members','Users','Sales','Events','Locations','ClubInfo','EventsLog','GradeLog','FormsLog','Language'];
	return new Mysqldump($dsn, DB_USER, DB_PASS,$options);
};
$container['PublicEvents']=function($c){
	return new slimPublicEvents($c);
};
$container['PublicForms']=function($c){
	return new slimPublicForms($c);
};
$container['LogRegistration']=function($c){
	return new slimLogRegistration($c);
};
$container['PDF']=function($c){
	return new slimPDF($c);
};
$container['Adminbar']=function($c){
	return new content_adminbar($c);
};
$container['StatusColor']=function(){
	return new status_colors;
};
