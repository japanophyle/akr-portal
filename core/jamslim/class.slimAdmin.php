<?php
// for admin pages
class slimAdmin{
	private $SLIM;
	private $METHOD;
	private $SECTION;
	private $AJAX;
	private $REQUEST;
	private $USER;
	private $ADMIN;
	private $LEADER;
	private $SITE_MODE='online';
	private $PAGE_SLUG;
	private $PAGE_BACKGROUND=false;
	private $PERMLINK;
	private $CONTENT;// page contents object
	private $SKIP_SLUGS; //from config
	private $FAUX_PAGES; //from config
	private $ROUTE;
	private $DEVICE='classic';
	private $METATAGS;
	private $PLUGINS=array();
	private $globalJS=false;
	private $ASSETS;
	private $ASSET_VER_ADMIN=4;
	private $ASSET_VER_PUBLIC=4;
	private $ACTION;
	private $PERMPUB;
	private $CONTENT_TYPES;
	private $ARTICLE_TYPES;
	private $MSG;
	
	private $PARTS=array(
		'TPL_MAIN_CSS'=>'',
		'TPL_METATAGS'=>'',
		'TPL_PUBLIC_COPY'=>'',
		'TPL_USER_BAR'=>'',
		'TPL_PUBLIC_NAME'=>'',
		'TPL_MAIN_NAV'=>'',
		'TPL_MOBILE_NAV'=>'',
		'TPL_CONTENT'=>'',
		'TPL_MESSAGE'=>'',
		'TPL_JS_TEMPLATES'=>'',
		'TPL_OC_MENU'=>'',	
		'HERO_IMAGE'=>'',
		'TPL_SOCIAL'=>'',
		'TPL_FOOTER'=>'',
		'TPL_ICON'=>'',
		'TPL_BACKGROUND'=>'',
		'TPL_STYLES'=>'',
		'TPL_TITLE'=>'',
		'SITE_TITLE'=>'MemberMe.2'
	);
	private $PLUGS;
	var $CACHE;

	
	function __construct($slim=null){
		//route admin/section/action/id/args...
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->PLUGS=$slim->AdminPlugins;
		$this->METHOD=$slim->router->get('method');
		if(!$this->METHOD) $this->METHOD='GET';
		$this->REQUEST=($this->METHOD==='POST')?$slim->router->get('post'):$slim->router->get('get');
		
		$this->ROUTE=$slim->router->get('route');
		$this->DEVICE=$slim->router->get('device');
		$this->SECTION=issetCheck($this->ROUTE,1);
		$this->ACTION=issetCheck($this->ROUTE,2);
		if($this->METHOD==='POST') $this->ACTION=issetCheck($this->REQUEST,'action',$this->ACTION);
		$this->AJAX=$slim->router->get('ajax');
		$this->USER=$slim->user;
		
		if($this->USER['access']>=$slim->AdminLevel) $this->ADMIN=true;
		if(!$this->ADMIN && $this->USER['access']==$slim->LeaderLevel) $this->LEADER=true;	
		$perm=$slim->router->get('permlinks');
		$this->PERMPUB=URL.'page/';
		$this->PERMLINK=URL.'admin/';		
		//check for temp login
		$tmplogin=$slim->TempLogin->get('status');
		if($tmplogin==='yes'){
			if(!in_array($this->ACTION,['tmplogin','canceltmplogin'])){
				setSystemResponse($this->PERMLINK.'member/tmplogin','You are logged in as a member!');
			}
		}
		if(!$this->AJAX && !$this->PAGE_SLUG) $this->PAGE_SLUG='dashboard';
		$slim->assets->set('template','tpl.admin.html','main');
		$this->ASSET_VER_ADMIN=issetCheck($slim->config,'ASSET_VER_ADMIN',$this->ASSET_VER_ADMIN);
		$this->ASSET_VER_PUBLIC=issetCheck($slim->config,'ASSET_VER_PUBLIC',$this->ASSET_VER_PUBLIC);
		if(!$this->AJAX){
			$slim->assets->set('styles','<link rel="stylesheet" href="assets/css/jamslim.min.css?v='.$this->ASSET_VER_PUBLIC.'" />','main');
			$slim->assets->set('styles','<link rel="stylesheet" href="assets/css/jamslim.admin.css?v='.$this->ASSET_VER_ADMIN.'" />','match');
			$slim->assets->set('script','<script src="assets/js/jamslim.min.js?v='.$this->ASSET_VER_PUBLIC.'"></script>','main');
			$slim->assets->set('script','<script src="assets/js/admin/trumbowyg.jamPlugins.min.js?v='.$this->ASSET_VER_ADMIN.'"></script>','tr_media');
			$this->SLIM->assets->set('script', '<script src="assets/js/admin/dropzone.min.js"></script>','dropzone'); 
			$this->SLIM->assets->set('styles', '<link rel="stylesheet" href="assets/css/dropzone.min.css" />','dropzone');
		}
		$this->CONTENT_TYPES = $slim->DataBank->get('content_types',array('key'=>false, 'raw'=>1, 'rev'=>1));
        $this->ARTICLE_TYPES = $slim->DataBank->get('content_types','article');
        $this->CONTENT=$slim->PageContent;
	}
	public function getAdminBar($modal=true){
		return $this->renderAdminBar($modal);
	}
	private function getPlugin($name=false){
		$plug=false;
		if($name){
			$plug=issetCheck($this->PLUGINS,$name);
			if(!$plug){
				$plug=$this->loadPlugin($name);
			}
		}
		return $plug;
	}
	private function loadPlugin($name){
		try{
			$o=$this->PLUGS[$this->SECTION];
			if($this->USER['access']>=$o['access']){
				$p='admin_'.$name;
				$plug = new $p($this->SLIM);
				$plug->USER=$this->USER;
				$plug->ADMIN=$this->ADMIN;
				$plug->LEADER=$this->LEADER;
				$plug->ROUTE=$this->ROUTE;
				$plug->METHOD=$this->METHOD;
				$plug->REQUEST=$this->REQUEST;
				$plug->SECTION=$this->SECTION;
				$plug->ACTION=$this->ACTION;
				$plug->AJAX=$this->AJAX;
				$plug->PLUG=$this->PLUGS[$this->SECTION];
				$this->PLUGINS[$name]=$plug;
				return $plug;
			}else{
				$m=['status'=>500,'message'=>'Sorry, You don\'t have access to that section ['.$name.']...','type'=>'message'];
				if($this->AJAX){
					jsonResponse($m);
				}else{
					setSystemResponse($this->PERMLINK,$m['message']);
				}
				die;
			}
		}catch(Exception $e){
			if(ENVIRONMENT!=='live') preME($e,2);
			$this->PLUGINS[$name]='error';
			return 'error';
		}			
	}
	function render(){
		if($this->METHOD==='POST'){
			$this->renderPost();
		}else{
			if(!$this->SECTION||$this->SECTION===''){
				$this->getDashboard();
			}else if(in_array($this->SECTION,array('sidebar'))){
				$this->getManager('items');
			}else if(array_key_exists($this->SECTION,$this->PLUGS)){
				$this->getManager($this->SECTION);
			}else if($this->SECTION==='colors'){	
				$this->renderColorPicker();
			}else{
				$this->PARTS['TPL_CONTENT']=msgHandler('Sorry, I cant find that section ['.$this->SECTION.']...',false,false);
				setSystemResponse($this->PERMLINK,'Sorry, I cant find that section ['.$this->SECTION.']...');
				die;
			}
			$this->SLIM->display->merge($this->PARTS);	
		}
		if($this->AJAX){
			$title=(trim($this->PARTS['TPL_TITLE'])!=='')?$this->PARTS['TPL_TITLE']:$this->PARTS['SITE_TITLE'];
			$content=(trim($this->PARTS['TPL_MESSAGE'])!=='')?msgHandler($this->PARTS['TPL_MESSAGE'],false,false):'';
			$content.=$this->PARTS['TPL_CONTENT'];
			echo renderCard_active($title,$content,$this->SLIM->closer);
			die;	
		}
	}
	private function getDashboard(){
		$dash= new slimAdminDashboard($this->SLIM);
		$content=$dash->render();
		$this->SECTION='dash';
		$this->PARTS['TPL_TITLE']='Admin Dashboard';
		$this->PARTS['TPL_CONTENT']=$content['content'];//$this->renderAdminBar(false);
		$this->PARTS['TPL_ICON']='<i class="fi-widget icon-x1b"></i>';
		$this->PARTS['TPL_MAIN_NAV']=false;
		$this->finalFix();		
	}
	private function getManager($plug=false){
		$MGR=$this->getPlugin($plug);
		if($MGR){
			$out=$MGR->Process();
			$this->PARTS['TPL_TITLE']=$out['title'];
			$this->PARTS['TPL_CONTENT']=$out['content'];
			$this->PARTS['TPL_ICON']=$out['icon'];
			$this->PARTS['TPL_MAIN_NAV']=$out['menu'];
		}
		$this->finalFix();		
	}
	private function manage_files(){
		$FM=$this->SLIM->Filemanager;
		$FM->render();
	}

	private function renderGet(){
		
	}
	public function renderPost($action=false,$switch=false){
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
                $msg = false;
                break;
            case 'Submit Form'://mail contact forms
            case 'Submit Application Form'://new product forms
                $msg = false;
                break;
            default://by section
				if(in_array($this->SECTION,array('book','publisher','sidebar','tour'))){
					$this->getManager('items');
				}else if(array_key_exists($this->SECTION,$this->PLUGS)){
					$this->getManager($this->SECTION);
				}else{
					$this->PARTS['TPL_MESSAGE']='Sorry, I cant find that section ['.$this->SECTION.']...';
				}
				//shold have redirected by now...				
                $msg = 'Sorry, I dont know what to do...' . $switch;
        }
        $this->MSG = $msg;
        //should not get here??
        setSystemResponse($this->PERMLINK.$this->SECTION,$msg);
	}
	
	function finalFix(){
		//finalise the output parts
		$this->renderNavigation();
		$this->renderMessages();
		$this->PARTS['capjs']=$this->PARTS['capcss']=$this->PARTS['adminjs']=$this->PARTS['admincss'] = false;		
		//html parts
		$this->PARTS['TPL_SOCIAL']='<p>Powered by <span class="link-green">jamSlim</span></p>';
		$this->PARTS['TPL_FOOTER']=false;
		
		//assets parts
		$this->PARTS['js']=$this->renderAssets('js');
		$this->PARTS['jqd']=$this->renderAssets('jqd');
		$this->PARTS['site_css']=$this->renderAssets('site_css');
		$this->PARTS['TPL_STYLES']=$this->renderAssets('style');
		$this->PARTS['script']=$this->renderAssets('script');
		$this->PARTS['globalJS'] = $this->globalJS;
		$this->setGoogleAssets();
		//misc parts
		$this->PARTS['TPL_LOGO']=$this->PARTS['TPL_ICON'];
		$this->PARTS['TPL_PUBLIC_NAME']=$this->PARTS['TPL_TITLE'];
		$this->PARTS['dash']=$this->CONTENT->get('dashboard');
		$this->PARTS['TPL_COPY']='<p>'.$this->PARTS['TPL_TITLE'].': Admin &copy; '.date('Y').'</p>';
	    $this->PARTS['TPL_SLOGAN'] = '';
	    $this->PARTS['TPL_FOOTER_SLOGAN'] ='';
		$this->PARTS['pageclass'] = '';
		$this->PARTS['preload'] = $this->CONTENT->preLoadImages();
		$this->PARTS['script_version']=$this->SLIM->Options->get('site',array('site_script_version','value'));
		$this->PARTS['menu_type'] =($this->DEVICE=='classic')?'on-canvas':'off-canvas';
		$this->setMetaTags();
		$this->setLastPage();
	}
    private function renderAssets($what=false){
		if(!$this->ASSETS){
			//to do: stop using CONTENT for assets
			$this->ASSETS=(is_array($this->CONTENT->JS))?$this->CONTENT->JS: $this->SLIM->assets->get();
		}
		$asset=issetCheck($this->ASSETS,$what);
		switch($what){
			case 'site_css':
				$asset=$this->SLIM->Options->getSiteOptions('css','value');
				$asset.='#contents{overflow-y:auto;overflow-x:hidden;}';
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
					$asset=implode("\n",$asset);
				}else if(!is_string($asset)){
					$asset=false;
				}
				break;
		}
		return $asset;
	}
	private function setGoogleAssets(){
		//google aparts
		$this->PARTS['gatop'] =$this->PARTS['gabottom'] =false;
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
					preME($e,2);
					$rp='<div class="callout bg-red">**  swapTags error: no shortcode class found ['.$scan[2][$i].']... **</div>';
					$out=str_replace($scan[0][$i],$rp,$out);
				}
			}
		}
        return $out;
    }
	
	function setMetaTags(){
		$_desc=$_keyw=false;
		$meta=$this->CONTENT->get('meta');//META;
		$desc=issetCheck($this->METATAGS,'site');
		$keyw=issetCheck($this->METATAGS,'keywords');
		if(issetCheck($meta,'metatags')){
			$_desc=issetCheck($meta['metatags'],'description');
			$_keyw=issetCheck($meta['metatags'],'keywords');
		}
		$tags='';
		//remove defaults
		if($desc==='this is the text') $desc='';
		if($keyw==='keywords,go,here') $keyw='';
		//add page meta
		if($_desc && $_desc!=='NULL') $desc.=' '.$_desc;
		if($_keyw && $_keyw!=='NULL') $keyw.=','.$_keyw;
		//render
		if($desc && $desc!=='') $tags.='<meta name="description" content="'.$desc.'">'."\n";
		if($keyw && $keyw!=='') $tags.='<meta name="keywords" content="'.$keyw.'">'."\n";
		$this->PARTS['TPL_METATAGS']=$tags;
	}
	
	function renderNavigation(){
		$user=$this->CONTENT->get('usernav');
		$menu=($this->PARTS['TPL_MAIN_NAV']==='')?$this->CONTENT->get('main_nav'):$this->PARTS['TPL_MAIN_NAV'];
		$admin=$this->renderAdminBar();
		$this->PARTS['TPL_USER_BAR']=$user;
		$this->PARTS['TPL_MAIN_NAV']=issetCheck($menu,'right');
		$this->PARTS['TPL_ADMIN_BAR']=$admin;
		//off canvas menu
		$this->PARTS['TPL_MOBILE_NAV']=issetCheck($menu,'section');		
	}
	private function renderColorPicker(){
		$CP=new slimColorPicker($this->SLIM);
		$CP->render();
		die;//should be by ajax only
	}
	private function renderAdminBar($modal=true){
		return $this->SLIM->Adminbar->render();
	}
	function renderMainContent(){
		//main content & sidebars
		$options['ITEM_SLUG']=$this->PAGE_SLUG;
		$options['BLOG_PARENT']=false;	
		$main['main']=$this->CONTENT->get('main',$options);	
		$htm='';
		foreach($main as $i=>$m){
			$class=($i=='main')?'page-content':'';
			if($i=='banner' && $m){
				$htm.='<div class="'.$class.'">'.$m.'</div>';
			}else{
				$htm.='<div class="'.$class.'">'.$m.'</div>';
			}
		}
		$this->PARTS['TPL_CONTENT']=$htm;
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
		if($this->MSG) $options['MSG'].=$this->MSG;
		$this->PARTS['TPL_MESSAGE']=$this->CONTENT->get('messages',$options);
	}
	
	function renderBannerContent(){
		$options=false;		
		$this->PARTS['TPL_BANNER_NAV']=$this->CONTENT->get('bannernav',$options);
		$this->PARTS['TPL_BANNER']=$this->CONTENT->get('banner_logo',$options);
	}

	function renderSocialContent(){
		$options['NEVERCACHE']=true;
		//$options['SOCIAL_PARTS']=$this->SOCIAL_PARTS;
		$options['ITEM_SLUG']=$this->PAGE_SLUG;
		$options['PERMLINK']=$this->PERMLINK;
		$this->PARTS['social_content']=$this->CONTENT->get('social',$options);		
	}
	function loadTemplate($name,$part=true){
		$path=($part)?TEMPLATES.'parts/'.$name:TEMPLATES.$name;
		$tpl=file_get_contents($path);
		if(!$tpl) $tpl=msgHandler('Sorry, template not found: '.$path);
		return $tpl;
	}
    
    function setLastPage() {
		$slug=implode('/',$this->ROUTE);
        $valid = 'admin/';
        if ($slug === 'lastpage') {
            $valid = issetCheck($_SESSION,'last_page', $valid);
        }else if(strpos($slug,$valid)===false){
			return;
		}else{
			$valid=$slug;
		}
		$_SESSION['last_page'] = $valid;
    }
    function checkSlug($slug,$active=true,$default='dashboard') {
		$slug=strtolower($slug);
		if(!$slug || $slug=='' || $slug==='null'){
			return $default; //silent return
		}else if(is_string($slug)){
			return $slug;
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
