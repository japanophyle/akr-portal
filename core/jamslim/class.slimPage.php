<?php
// for public pages
class slimPage{
	private $SLIM;
	private $METHOD;
	private $AJAX;
	private $REQUEST;
	private $USER;
	private $ADMIN;
	private $LEADER;
	private $ACCESS='guest';
	private $SITE_MODE='online';
	private $PAGE_SLUG;
	private $PAGE_BACKGROUND=false;
	private $PERMLINK;
	private $CONTENT;// page contents object
	private $SIDEBAR;//sidebar contents html
	private $SKIP_SLUGS; //from config
	private $FAUX_PAGES; //from config
	private $IS_FAUX=false;
	private $ROUTE;
	private $DEVICE='classic';
	private $METATAGS;
	private $POST;
	private $ASSETS;
	private $SITE_NAME;
	private $OFFLINE;
	private $ASSET_VER_PUBLIC=4;
	private $LANGUAGE;
	private $ACTION;
	private $CONTENT_TYPES;
	private $ARTICLE_TYPES;	
	private $globalJS=false;
	private $CACHE;
	private $MSG;
	private $ITEM_SLUG;
	
	private $PARTS=array(
		'TPL_MAIN_CSS'=>'',
		'TPL_METATAGS'=>'',
		'TPL_PUBLIC_COPY'=>'',
		'TPL_USER_BAR'=>'',
		'TPL_PUBLIC_NAME'=>'',
		'TPL_PUBLIC_NAV'=>'',
		'TPL_CONTENT'=>'',
		'TPL_MESSAGE'=>'',
		'TPL_JS_TEMPLATES'=>'',
		'TPL_OC_MENU'=>'',	
		'HERO_IMAGE'=>'',
		'TPL_SOCIAL'=>'',
		'TPL_COPY'=>'',
		'TPL_LOGO'=>'',
		'TPL_SIDEBAR'=>'',// for including in
		'TPL_PAGE_TITLE'=>'',
		'TPL_FOOT_TITLE'=>'AKR Members'
	);
	private $ADMIN_PLUGS;
	
	function __construct($slim=null){
		define('MP_PUBLIC',1);
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$slim->assets->set('template',$slim->config['TEMPLATES']['public'],'main');//overide template
		$this->METHOD=$slim->router->get('method');
		$this->FAUX_PAGES=$slim->config['PUBLIC']['faux'];
		$this->SKIP_SLUGS=$slim->config['PUBLIC']['skip_slugs'];
		if(!$this->METHOD) $this->METHOD='GET';
		$this->ROUTE=$slim->router->get('route');
		if($this->ROUTE && $this->ROUTE[0]!=='page'){
			$rt=array();
			foreach($this->ROUTE as $r){
				if($r==='page'){
					$rt[]=$r;
				}else if($rt){
					$rt[]=$r;
				}
			}
			if(!$rt){
				$rt=array('page','home');
				$url=URL.implode('/',$rt);
				setSystemResponse($url);
			}
			$this->ROUTE=$rt;
		}
		$this->PARTS['TPL_BODY_CLASS']=issetCheck($this->ROUTE,1);
		$this->DEVICE=$slim->router->get('device');
		$this->PAGE_SLUG=issetCheck($this->ROUTE,1);
		$this->AJAX=$slim->router->get('ajax');
		$this->USER=$slim->user;
        $this->LANGUAGE=$slim->language->get('_LANG');
        $this->ASSET_VER_PUBLIC=issetCheck($slim->config,'ASSET_VER_PUBLIC',$this->ASSET_VER_PUBLIC);
		if(!$this->USER) $this->USER=array('id'=>0,'access'=>0);
		if($this->USER['access']>=$slim->AdminLevel) $this->ADMIN=true;
		if(!$this->ADMIN && $this->USER['access']==$slim->LeaderLevel) $this->LEADER=true;
		$this->LANGUAGE=issetCheck($this->SLIM->user,'language',$this->LANGUAGE);
		if($this->ADMIN) $this->ADMIN_PLUGS=$slim->AdminPlugins;
        $this->OFFLINE=($this->ADMIN)?0:(int)$slim->Options->getSiteOptions('offline','value');
        if($this->OFFLINE) $this->PAGE_SLUG='offline';
		$this->CONTENT=$slim->PageContent;
		$this->REQUEST=($this->METHOD==='POST')?$slim->router->get('post'):$slim->router->get('get');
		$this->ACTION=issetCheck($this->REQUEST,'action');
		$perm=$slim->router->get('permlinks');
		$this->PERMLINK=$perm['link'];
		if(!$this->AJAX && !$this->PAGE_SLUG) $this->PAGE_SLUG='home';
		$cf=$slim->config['PUBLIC'];
		$this->SKIP_SLUGS=issetCheck($cf,'skip_slugs');
		$this->CONTENT_TYPES = $slim->DataBank->get('content_types',array('key'=>false, 'raw'=>1, 'rev'=>1));
        $this->ARTICLE_TYPES = $slim->DataBank->get('content_types','article');
        $this->SITE_NAME=$slim->Options->get('sitename');
 	}
	
	function render(){
		if($this->METHOD==='POST'){
			$this->renderPost();
		}else if($this->OFFLINE){
			include 'offline.php';
			die;			
		}else{
			switch($this->ACTION){
				default://public page
					$this->process();
					$this->renderGet();
			}
			$this->SLIM->display->merge($this->PARTS);
		}
		if($this->AJAX){
			$title=$this->SLIM->assets->get('title','modal');
			if(!$title||$title==='') $title=$this->SITE_NAME;
			$content=str_replace('{TPL_PAGE_TITLE}',$this->PARTS['TPL_PAGE_TITLE'],$this->PARTS['TPL_CONTENT']);
			echo renderCard_active($title,$content,$this->SLIM->closer);
			die;	
		}
	}
	private function renderGet(){
		
	}
	public function renderPost($action=false,$switch=false){
		 if(!$switch) $switch=$action;
         switch ($switch) {
            case 'signup':
            case 'addcomment':
            case 'Checkout'://checkout
            case 'Pay Now'://checkout
            case 'Pay Online'://checkout
            case 'authorise'://authorise user accounts :shortcoded
            case 'updatestatus'://update order status user accounts :shortcoded
            case 'Send':// happens via shortcode
            case 'Back':
            case 'Edit Form':
            case 'ez_checkout':
            case 'registration_form':
            case 'update_my_details':
            case 'submit_signup':
                $msg = false;
                break;
            case 'Submit Form'://mail contact forms
            case 'Submit Application Form'://new product forms
                $msg = false;
                break;
            default:
                $msg = 'Sorry, I dont know what to do...' . $switch;
        }       
        $this->MSG = $msg;
        $this->process();
        $this->SLIM->display->merge($this->PARTS);
 	}
	
	private function process(){
		$this->SLIM->assets->set('styles','<link rel="stylesheet" href="assets/css/jamslim.min.css?v='.$this->ASSET_VER_PUBLIC.'" />','main');
		$this->SLIM->assets->set('styles','<link rel="stylesheet" href="assets/css/jamslim.mnkr_public.min.css?v='.$this->ASSET_VER_PUBLIC.'" />','match');
		$this->SLIM->assets->set('script','<script src="assets/js/jamslim.min.js?v='.$this->ASSET_VER_PUBLIC.'"></script>','main');
		$this->CONTENT->init($this->PAGE_SLUG,'slug');
		switch($this->PAGE_SLUG){
			case 'postage_location':
				$CT=new cart($this->SLIM);
				$o['html']=$CT->setPostageLocation($this->ROUTE[2]);
				$o['status']=200;
				echo jsonResponse($o);
				die;
				break;
			case 'search':
				$this->renderSearch();
				if($this->AJAX) return;
				break;
			case 'my-homex'://for testing only!!
				$test=$this->SLIM->Members->render();
				preME($test,2);
				break;
			case 'signupx'://for testing only!!
				$app=new slimSignupPublic($this->SLIM);
				$test=$app->render();
				preME($test,2);
				break;
				
			default:
		}			
		$this->renderMainContent();
		$this->renderExtraContent();
		if(!$this->AJAX){
			$this->renderSocialContent();
			$this->renderFooterContent();
			$this->renderMessages();
			$this->finalFix();
			$this->renderNavigation();
		}else{
			$this->PARTS['TPL_CONTENT']=$this->swapTags($this->PARTS['TPL_CONTENT']);
			$title=$this->renderAssets('title');
			$this->PARTS['TPL_PAGE_TITLE']=issetCheck($title,'page','');
		}
	}
	function finalFix(){
		//finalise the output parts
		$this->PARTS['capjs']=$this->PARTS['capcss']=$this->PARTS['adminjs']=$this->PARTS['admincss'] = false;		
		//html parts
		$content=$this->swapTags($this->PARTS['TPL_CONTENT']);
		$title=$this->renderAssets('title');
		$title=($title)?$title['page']:false;
		$this->PARTS['TPL_CONTENT']=str_replace('{TPL_PAGE_TITLE}',$title,$content);
		if(is_array($this->PARTS['social_content'])){
			foreach($this->PARTS['social_content'] as $i=>$v){
				$this->PARTS['social_content'][$i]=$this->swapTags($v);
			}
			$this->PARTS['TPL_SOCIAL']=$this->PARTS['social_content']['SOCIAL_BAR2'];
		}
		$this->PARTS['TPL_FOOTER']='<a href="https://www.kyudousa.com/">American Kyudo Renmei</a><p>We are an organization devoted to the instruction and practice of Kyudo, traditional Japanese archery at various locations across the United States</p>';//html_entity_decode($this->SLIM->Options->get('public_footer_text','OptionValue'));
		//assets parts
		$this->PARTS['js']=$this->renderAssets('js');
		$this->PARTS['jqd']=$this->renderAssets('jqd');
		$this->PARTS['site_css']=$this->renderAssets('site_css');
		$this->PARTS['site_stylesheet']=$this->renderAssets('style');
		$this->PARTS['script']=$this->renderAssets('script');
		$this->PARTS['globalJS'] = $this->globalJS;
		$this->setGoogleAssets();
				
		//misc parts
		$this->PARTS['TPL_PAGE_TITLE']=issetCheck($this->ASSETS['title'],'page','');
		$this->PARTS['dash']=$this->CONTENT->get('dashboard');
		$this->PARTS['TPL_TITLE'] = $this->SITE_NAME;
	    $this->PARTS['TPL_SLOGAN'] = $this->SLIM->Options->getSiteOptions('slogan','value');
	    $this->PARTS['TPL_FOOTER_SLOGAN'] = $this->SLIM->Options->getSiteOptions('footer_slogan','value');
		$this->PARTS['TPL_COPY']='<p>'.$this->SITE_NAME.' &copy; '.date('Y').'<br/>Powered by <span class="link-green">jamSlim</span></p>';
		$this->PARTS['pageclass'] = ($this->PAGE_SLUG === 'bdr-home') ? 'home' : '';
		$this->PARTS['preload'] = $this->CONTENT->preLoadImages();
		$this->PARTS['script_version']=$this->SLIM->Options->getSiteOptions('script_version','value');
		$this->PARTS['menu_type'] =($this->DEVICE=='classic')?'on-canvas':'off-canvas';
		$this->setMetaTags();
		$this->setLastPage();
	}
    private function renderAssets($what=false){
		if(!$this->ASSETS){
			$this->ASSETS=$this->SLIM->assets->get();
		}
		$asset=issetCheck($this->ASSETS,$what);
		switch($what){
			case 'site_css':
				$tmp=false;
				if(is_array($asset)) $tmp=implode("\n",$asset);
				$asset=$this->SLIM->Options->getSiteOptions('site_css_overide','value');
				if($asset) $tmp.=issetCheck($asset,'opt_Value');
				$asset=$tmp;
				break;
			case 'stylex':
				if($asset){
					if(!is_array($asset)) $asset=(array)$asset;
					$tmp=false;
					foreach($asset as $a) $tmp.='<link rel="stylesheet" href="'.$a.'" media="screen">';
					$asset=$tmp;
				}
				break;
			case 'jqd':
				if(!$this->AJAX){
					if(is_array($asset)) $asset=implode(';',$asset);
					$asset='JQD.go({'.$asset.'});';
					$this->SLIM->assets->set('jqd',$asset,'all');
				}else{
					$asset=false;
				}					
				break;
			case 'style':
			case 'js':
			case 'script':
				if(is_array($asset)){
					//preME($asset,2);
					$asset=implode("\n",$asset);
				}else if(!is_string($asset)){
					$asset=false;
				}
				break;
		}
		return $asset;
	}    
	private function setGoogleAssets(){
		//google parts
		$ga_id=$this->SLIM->Options->get('google_analytics_key','value');
		if($ga_id){
			$ga['top']='<script async src="https://www.googletagmanager.com/gtag/js?id='.$ga_id.'"></script>';
			$ga['top'].='<script>window.dataLayer = window.dataLayer || [];  function gtag(){dataLayer.push(arguments);}  gtag("js", new Date());  gtag("config", "'.$ga_id.'");</script>';
			$ga['bottom']=false;
		}else{
			$ga=false;
		}
		$this->PARTS['gatop']=$this->PARTS['gabottom']=false;
		if(ENVIRONMENT!=='live') return;
		if(is_array($ga)){
			$this->PARTS['gatop'] = $ga['top'];
			$this->PARTS['gabottom'] = $ga['bottom'];
		}		
	}
    function swapTags($content = false,$sidebar=false) {
       if (!$content) return false;
        $content = fixHTML($content);
        $scan=$this->SLIM->ShortCoder->shortCodeScan($content);
        $out=$content;
        if($scan && isset($scan[2])){
			foreach($scan[2] as $i=>$code){
				$coder='shortcode_'.$code;
				try{
					$sc = new $coder($this->SLIM);
					$tg=array(0=>$scan[0][$i], 1=>$scan[1][$i]);
					$out = $sc->ProcessTag($out,$sidebar,$tg);
				}catch(Error $e){
					if($this->USER['access']==5) preME($e,2);
					$rp='<div class="callout bg-red">**  swapTags error: no shortcode class found ['.$scan[2][$i].']... **</div>';
					$out=str_replace($scan[0][$i],$rp,$out);
				}
			}
		}
        return $out;
    }
	
	function setMetaTags(){
		$tags=$this->CONTENT->getMetaContents();
		if(is_array($tags)) $tags=implode("\n",$tags);
		$this->PARTS['TPL_METATAGS']=$tags;
/*
		$_desc=$_keyw=false;
		$meta=$this->CONTENT->get('meta_content');//META;
		$desc=issetCheck($this->METATAGS,'site');
		$keyw=issetCheck($this->METATAGS,'keywords');
		$title=$this->PARTS['TPL_FOOT_TITLE'];//$this->SITE_NAME;
		if(issetCheck($meta,'metatags')){
			$_desc=issetCheck($meta['metatags'],'description');
			$_keyw=issetCheck($meta['metatags'],'keywords');
			$tmp=issetCheck($meta['metatags'],'title');
			if($tmp) $title.=': '.substr($tmp,0,50);
		}
		$tags='<title>'.$title."</title>\n";
		$robots='Index, Follow';
		if($this->USER['access']>0) $robots='NoIndex, NoFollow';
		//remove defaults
		if($desc==='this is the text') $desc='';
		if($keyw==='keywords,go,here') $keyw='';
		//add page meta
		if($_desc && $_desc!=='NULL') $desc.=' '.$_desc;
		if($_keyw && $_keyw!=='NULL') $keyw.=','.$_keyw;
		//render
		if($desc && $desc!=='') $tags.='<meta name="description" content="'.$desc.'">'."\n";
		if($keyw && $keyw!=='') $tags.='<meta name="keywords" content="'.$keyw.'">'."\n";
		$tags.='<meta name="robots" content="'.$robots.'">'."\n";
		$this->PARTS['TPL_METATAGS']=$tags;
*/
	}
	
	function renderNavigation(){
		$user=$this->CONTENT->get('usernav');
		$menu=$this->CONTENT->get('main_nav');
		$admin=$this->renderAdminBar();
		$radio='';
		$this->PARTS['TPL_USER_BAR']=$user;
		$this->PARTS['TPL_RADIO_BAR']=$radio;
		$this->PARTS['TPL_MAIN_NAV']=$menu['right'];
		$this->PARTS['TPL_ADMIN_BAR']=$admin;
		//off canvas menu
		$this->PARTS['TPL_MOBILE_NAV']=$menu['section'];
	}
	private function renderAdminBar(){
		return $this->SLIM->Adminbar->render();
	}
	private function renderRadioBar(){
		$event=new event_item($this->SLIM);
		$bar=$event->get('next',true);
		return $bar;
	}
	function renderSearch(){
		$form='<div class="callout primary">Search our backlist by entering a keyword from the title, author or publisher.<form class="searchForm" method="get" action="'.URL.'page/backlist"><input type="hidden" name="act" value="search"/><div class="input-group"><input class="input-group-field" name="find" type="text" placeholder="Title, Author, Publisher"><div class="input-group-button"><button type="submit" class="button button-olive"><i class="fi-magnifying-glass icon-x1b"></i></button></div></div></form></div>';
		$this->PARTS['TPL_CONTENT']=$form;
		$this->PARTS['TPL_TITLE']="Book Search";
		$this->SLIM->assets->set('title','Book Search','modal');
	}
	function renderMainContent(){
		//main content
		$options['ITEM_SLUG']=$this->PAGE_SLUG;
		$options['BLOG_PARENT']=false;
		$main=$this->CONTENT->get('main',$options);	
		$htm=$img='';
		if(is_string($main)){
			$htm=$main;
		}else{
			foreach($main as $i=>$m){
				$class=($i=='main')?'page-content':'';
				if($i==='main_image'){
					$img=$m;
				}else if($i=='banner' && $m){
					$htm.='<div class="'.$class.'">'.$m.'</div>';
				}else{
					$htm.='<div class="'.$class.'">'.$m.'</div>';
				}
			}
		}
		if(!$this->AJAX && $img==='') $img=$this->CONTENT->get('main_image');	
		$this->PARTS['TPL_CONTENT']=$htm;
		$this->PARTS['HERO_IMAGE']=$img;
		$this->renderBackgroundImage();
	}
	
	function renderFooterContent(){
		$footer=$this->CONTENT->footer_slogan();
		$footer.=$this->CONTENT->footer_form();
		$footer.=$this->CONTENT->footer_top();
		//$footer.=$this->CONTENT->footer_bottom();
		$this->PARTS['TPL_FOOTER']=$footer;
	}
	
	function renderBackgroundImage($img=false){
		$css=false;
		if($this->PAGE_BACKGROUND){
			$BG=new content_background($this->SLIM,$this->PAGE_SLUG);
			$css=$BG->getBackgroundCSS();
			$img=$BG->getBackgroundImage();
		}
		$this->PARTS['TPL_BACKGROUND']=$css;
	}
	function renderExtraContent(){
		$tpl=false;
		$this->PARTS['TPL_EXTRA']=$tpl;
	}
	
	function renderMessages(){
		$options['MSG']=getSystemResponse();
		$this->PARTS['TPL_MESSAGE']=$this->CONTENT->get('messages',$options);
	}
	
	function renderBannerContent(){
		$options=false;		
		$this->PARTS['TPL_BANNER_NAV']='';//$this->CONTENT->get('bannernav',$options);
		$this->PARTS['TPL_BANNER']=$this->CONTENT->get('banner_logo',$options);
	}

	function renderSocialContent(){
		$c='<p>Powered by <span class="link-green">jamSlim</span></p>';		
		$this->PARTS['social_content']=['SOCIAL_BAR'=>'','SOCIAL_BAR1'=>$c,'SOCIAL_BAR2'=>$c];
	}
	function loadTemplate($name,$part=true){
		$path=($part)?TEMPLATES.'parts/'.$name:TEMPLATES.$name;
		$tpl=file_get_contents($path);
		if(!$tpl) $tpl=msgHandler('Sorry, template not found: '.$path);
		return $tpl;
	}
    
    function setLastPage() {
		$slug=($this->PAGE_SLUG)?$this->PAGE_SLUG:'home';
		if($slug==='library') return;//?? why is it defaulting to library??
        //validate slug & set internal page tracking
        $valid = 'home';
        $skip = array('login', 'my-home', 'payments', 'paypalfail', 'paypalgood', 'feeder', 'verify', 'sign-up', 'home', 'status', 'login-reminder', 'login-reset', 'search-results'); // maybe add checkout pages
        if ($slug === 'lastpage') {
            $slug = issetCheck($_SESSION,'last_page', 'home');
        }else{
            if(in_array($slug, $skip)) {
                //do nothing
            } else {
                if ($chk = $this->checkSlug($slug)){
					//preME($chk,2);
                    $slug = $chk;
                    $_SESSION['last_page'] = $slug;
                  } else {
                    $this->MSG[] = 'Sorry, I could not find that item "' . $slug . '"';
                    $slug='home';
                }
            }
        }
        $this->ITEM_SLUG = $slug;
    }
    function checkSlug($slug,$active=true,$default='home') {
		$slug=strtolower($slug);
		if(!$slug || $slug=='' || $slug==='null'){
			return $default; //silent return
		}else if(is_string($slug)){
			if(in_array($slug,$this->FAUX_PAGES)){
				$this->IS_FAUX=true;
				return $slug;
			}else{
				if(!isset($this->CACHE['slugs'])){
					$c=file_get_contents(CACHE.'cache_slugs.php');
					if(trim($c)!==''){
						$c=compress($c,false);
						$this->CACHE['slugs']=$c;
					}
				}
				$chk=issetCheck($this->CACHE['slugs'],$slug);
				if($active){
					if($chk && $chk['status']==='published') return $slug;
				}else if($chk){
					return $slug;
				}
			}
		}
		$this->MSG='Sorry, I could not find the page ['.$slug.']...';
		return $default;
    }
    
    function checkSlug_useCache($slug=false){
		if(!$slug) return false;
		$chk=issetCheck($this->CACHE['slugs'],$slug);
		if($chk){
			return (int)issetCheck($chk,'use_cache');
		}
		return $chk;		
	}	
}
