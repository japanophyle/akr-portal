<?php

class slimPageContent{
	private $SLIM;
	private $DATA;
	private $META;
	private $META_DATA;
	private $SLUG;
	private $ID;
	private $DB;
	private $USER;
	private $ADMIN=false;
	private $ARGS;//from get function
	private $SITEOPTS;
	private $TEMPLATE_PARTS;
	private $TEMPLATE_PATH;
	private $BOOK_PARENT;//from $parents
	private $BLOG_PARENT;//from $parents
	private $TESTIMONIAL_PARENT;//from $parents
	private $PRODUCT_PARENT;//from $parents
	private $EVENTS_PARENT;//from $parents
	private $SKIP_SLUGS; //from config
	private $FAUX_PAGES; //from config
	private $IMG;//main image object
	private $PRELOAD=array();
	private $USE_CAPTIONS=false;
	private $OUTPUT;
	private $IS_FAUX=false;
	private $LANGUAGE;
	var $NAVIGATION_TYPE;
	
	var $REFRESH_CACHE;
	var $PREVIEW;
	var $MAIN_IMAGE;//array for main image info
	var $MAINPAGE;
	
	var $SCRIPT=false;
	var $JQD=false;
	var $JS=false;
	var $STYLES=false;
	var $IS_PARENT;
		
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->DB=$slim->db;
		$this->USER=$slim->user;
		$this->FAUX_PAGES=$slim->config['PUBLIC']['faux'];
		$this->SKIP_SLUGS=$slim->config['PUBLIC']['skip_slugs'];
		$this->SITEOPTS=$slim->Options->get('site','value');
		$this->TEMPLATE_PATH=TEMPLATES;
		$this->TEMPLATE_PARTS=TEMPLATES.'parts/';
		$this->LANGUAGE=$slim->language->get('_LANG');
		$parents=$slim->config['PARENTS'];
		if($parents){
			foreach($parents as $i=>$v) $this->$i=$v;
		}
		if($this->USER['access']>=$slim->AdminLevel) $this->ADMIN=true;
		$this->IMG=new image;
	}
	
	function init($var=false){
		if($var){
			if($var==='new'){
			
			}else if($var==='admin'){
				
			}else{
				$rec=$this->DB->Items();
				if(is_string($var) && $var!==''){
					$this->IS_FAUX=$this->isFauxPage($var);
					if($this->IS_FAUX){
						$this->SLUG=$var;
						$rec=false;
					}else{
						$where['ItemSlug']=$var;
						$rec->where($where);
					}
				}else if(is_numeric($var) && $var>0){
					$where['ItemID']=$var;
					$rec->where($where);
				}else{
					$rec=false;
				}
				if($rec){
					$rec=renderResultsORM($rec,'ItemID');
					if($rec){
						$this->DATA=current($rec);
						$this->ID=$this->DATA['ItemID'];
						$this->SLUG=$this->DATA['ItemSlug'];
						$this->setMetadata($this->ID);
					}
				}
			}
		}
	}
	function get($what=false,$args=false){
		switch($what){
			case 'data':
				return $this->DATA;
				break;
			case 'meta_data':
				return $this->META_DATA;
				break;
			case 'main_image'://returns a random image
				$tmp= new content_main($this->SLIM);
				return $tmp->render('main_image');
				break;
			case 'meta_content':
				if(!$this->META) $this->getMetaContents();
				return $this->META;
				break;
			case 'edit_link':
				$edit=false;
				if($this->ADMIN && $this->DATA){
					$type=($this->DATA['ItemType']==='article')?'page':$this->DATA['ItemType'];
					$edit='<button type="button" class="button small button-purple gotoME" data-ref="'.URL.'admin/'.$type.'/edit/'.$this->DATA['ItemID'].'" title="edit this"><i class="fi-pencil"></i></button>';
				}
				return $edit;
				break;

		}
		$what='content_'.$what;
		$widget=$this->getContents($what,$args);
		if($widget){
			if(is_string($widget)) $widget=trim($widget); 
			return $widget;
		}
		if(method_exists($this,$what)){
			$this->ARGS=$args;
			return call_user_func(array($this,$what));
		}else{
			return msgHandler('Sorry, I dont know what "'.$what.'" is ...');
		}
	}
	
	private function getContents($what,$args=false){
		//hack for php < 7
		$cons=['content_adminbar','content_article','content_events_calendar',
			'content_faux',
			'content_homepage',
			'content_loginform',
			'content_main',
			'content_main_nav',
			'content_sidebar',
			'content_social',
			'content_usernav',
		];
		if(!in_array($what,$cons)) return false;
		// end hack
		$W=false;
		try{
			$W = new $what($this->SLIM,$this->SLUG);
		}catch(Error $e){
			return false;
		}
		$W->DATA=$this->DATA;
		return $W->render($args);
	}	

    function content_messages() {
		$MSG=issetCheck($this->ARGS,'MSG');
		if($MSG && !is_array($MSG)) $MSG=(array)$MSG;
        $sms=getRequestVars('m');
        if ($sms){
			$msg['logout'] = 'Okay, I have logged you out.';
			$msg['login'] = 'Okay, I have logged you in';
			$msg['loginError'] = 'Sorry, I could not log you in.';
			$msg['loginDisabled'] = 'Sorry, your account has been disabled.';
			$msg['optsaved'] = 'The test options have been saved and the page has been refreshed.';
			$msg['opterror'] = 'Sorry, I could not save the test options, they should still have been applied.';
			$msg['optreset'] = 'The test options have been reset and the page has been refreshed.';
			if($f=issetCheck($msg,$sms))$MSG[]=$f;
		}
		if(issetCheck($this->OUTPUT,'message')) $MSG[]=$this->OUTPUT['message'];
        if (is_array($MSG)) $MSG=implode('<br/>',$MSG);
        return ($MSG) ? msgHandler($MSG) : false;
    }
    
	function content_site_logo(){
		$logo='AHK/SKV';
		if($this->NAVIGATION_TYPE=='offcanvas'){
			return '<button class="right-off-canvas-toggle">'.$logo.'</button>';
		}else if($this->NAVIGATION_TYPE=='topbar'){
			return '<button data-toggle="offCanvasLeft" class="hide-for-large">'.$logo.'</button><span class="show-for-large show-on-sticky title-bar-title">'.$logo.'</span>';
		}else{
			return $logo;
		}	
	}
    
    function getListTemplate($sidebar){
        if ($sidebar) {
            $tpl['main'] = '<a title="click to view" class="hotLink column" href="{url}"><span class="row collapse">{row}</span></a>';
            $tpl['col_2'] = '<strong class="hotTitle">{title}</strong><span class="medium-4 columns sbImage">{image}</span><span class="medium-8 columns sbText ">{blurb}</span>';
            $tpl['col_1'] = '<strong class="hotTitle">{title}</strong><span class="small-12 columns sbText">{blurb}</span>';
            $tpl['mainb'] = '<a title="click to viewb" class="hotLink figureslide column" href="{url}">{row}</a>';
            $tpl['col_2b'] = '<span class="figure cap-bot">{image}<span class="figcaption"><strong class="hotTitle">{title}</strong><span class="sbText ">{blurb}</span></span></span>';
        } else {
            $tpl['main'] = '<div class="homeBlock"><div class="small-12 columns title"><span>{title}</span></div>{row}</div>';
            $tpl['col_2'] = '<div class="small-12 columns image">{image}</div><div class="small-12 columns slideContent">{blurb}</div>';
            $tpl['col_1'] = '<div class="small-12 columns slideContent">{blurb}</div>';
        }
        return $tpl;
	}
	
	function renderSidebarItem($content=false,$title=false,$active=false){
		return '<li class="accordion-item '.$active.'" data-accordion-item><a href="#" class="accordion-title sidebar-title">'.$title.'</a><div class="sidebar-content sidebar-item accordion-content" data-tab-content>'.$content.'</div></li>'."\n";
	}

//sidebars
    function content_child_links(){
		$out=false;
		if($this->DATA){
			$out=$this->getSiblings();
		}
		return $out;
	}
    function content_news($widget='ticker',$sidebar = true) {
        $TWK_newsticker = new NewsTicker();
		$TWK_newsticker->Widget = $widget; //my press QOD widget settings
		$TWK_newsticker->Sidebar = $sidebar; //my press QOD widget settings
		$ticker = $TWK_newsticker->Process('show');
		//$this->addJS($ticker['js']);
        return $ticker['content'];
    }
    
//helpers
    function keyMeta() {
        //puts meta data into a flat array (key=>val)
        $out = [];
        if($this->META_DATA && is_array($this->META_DATA)){
 			foreach ($this->META_DATA as $i => $meta) $out[$i] = fixHTML($meta);
		}
        return $out;
    }
    
	function findMeta($key=false){
		if($key){
			$array=$this->keyMeta();
			return issetCheck($array,$key);
		}
		return false;
	}

    function isFauxPage($slug){
	    $faux=false;
	    if(in_array($slug,$this->FAUX_PAGES)){
			$faux=true;
		}
		return $faux;
	}
	
	function isParent($data=false){
		//not used??
		$chk=false;
		if($data){
			preME($data,2);
			if(!(int)$data['ITEM_DATA']->itm_Parent){
				//no
			}else if(!count($data['ARTICLE_DATA'])){
				//no
			}else{
				$chk=true;
			}
		}
		$this->IS_PARENT=$chk;
	}

    function getEditLink($href = false, $label = ' Edit[+]', $alt = 'Edit This') {
        $link = false;
        if ($this->ADMIN && $href && !$this->REFRESH_CACHE) {
            $link = '<a class="editME jTip" title="' . $alt . '" href="' . $href . '">' . $label . '</a>';
        }
        return $link;
    }
    
    function getMetaByID($id) {
        return $this->SLIM->DataBank->get('meta_by_id',$id);
    }

    function scodePage($slug) {
		$rec=$this->SLIM->DataBank->get('scode_page',$slug);
        $url = array('page' => $rec->ItemSlug, 'base' => $this->MAINPAGE, 'lead' => 1);
        $href = formatURL($url);
        $out = '<a title="click to view" class="hotLink" href="' . $href . '">' . $rec->ItemTitle . '</a>';
        return $out;
    }
    
    function getRelated() {
        if ($r = $this->getSiblings()) {
			return $this->renderSidebarItem($r,'Related');
        }
        return false;
    }
    
    function getSiblings() {
		return false;
        $list = false;
        $parent = (int)issetCheck($this->DATA,'ItemParent');
        if($parent){
            $recs=$this->SLIM->DataBank->get('siblings',$parent);
            if ($recs) {
				foreach ($recs as $rec) {
					$details = fixHTML($rec['ItemTitle']);
					if ($this->ID !== $rec['ItemID']) $list[] = array('ref' => URL.'page/'.$rec['ItemSlug'], 'content' => $details,'icon'=>false);
				}
			}
		}else{
			$recs=$this->SLIM->db->Items->select('ItemID,ItemTitle,ItemSlug')->where('ItemStatus','published')->and('ItemParent',$this->DATA['ItemID']);
			$recs=renderResultsORM($recs,'ItemID');
			if($recs){
				foreach ($recs as $rec) {
					$details = fixHTML($rec['ItemTitle']);
					$list[] = array('ref' => URL.'page/'.$rec['ItemSlug'], 'content' => $details,'icon'=>false);
				}
			}
		}
		if($list){
            $list['listclass']='menu vertical';        
			return makeHotlist($list);
		}
		return false;
    }
    function setMetaData(){
		$data=issetCheck($this->DATA,'ItemShort',[]);
		if($data) $data=json_decode($data,1);
		$this->META_DATA=$data;
	}
    function getMetaContents() {
		if(!$this->META_DATA && $this->ID) $this->setMetaData();
		if(!$this->META_DATA || empty($this->META_DATA)){
			$this->META=false;
			return false;
		}else{
			$this->META_DATA['robots']=($this->SLUG==='my-home')?'noindex, nofollow':'index, follow';
		}

		$output=[];
		foreach ($this->META_DATA as $i => $meta) {
			//meta basics - needed for other meta functions
			switch($i){
				case 'description': case 'title': case 'keywords': case 'robots':
					if(trim($meta)!=='') $output[$i]='<meta name="'.$i.'" content="'.$meta.'" >';
					break;
				default:					
			}
		}
        $this->META=$output;
        return $output;
    }

// footer stuff	
	
	function footer_slogan(){
		$slogan= issetCheck($this->SITEOPTS,'site_footer_slogan');
		if($slogan){
			$slogan='<div class="cell"><div class="zurb-footer-slogan"><p class="tagline text-center">'.fixHTML($slogan).'</p></div></div>';
		}
		return $slogan;
	}
	function footer_form($type=false){
		return false;
		$out=false;
		$base=URL;
		$fid='eqRow';
		$href['newsletter']=formatURL(array('page' => 'mailinglist', 'base' => $base, 'lead' => 1));
		$href['membership']=formatURL(array('page' => 'membership', 'base' => $base, 'lead' => 1));
		$href['donations']=formatURL(array('page' => 'donations', 'base' => $base, 'lead' => 1));
		
		$form['newsletter']['title']='Newsletter';
		$form['newsletter']['body']='Join our mailing list to keep informed of the happenings at The Buddhist Society.';
		$form['newsletter']['foot']='<a class="button radius expand-for-small" href="'.$href['newsletter'].'" >Subscribe Now</a>';
		
		$form['join']['title']='Join Us!';
		$form['join']['body']='Become a member and get a subscription to The Middle Way and access to our events and online features.';
		$form['join']['foot']='<a class="button radius success expand-for-small" href="'.$href['membership'].'">Signup Now</a>';
		
		$form['donate']['title']='Help Us!';
		$form['donate']['body']='Make a donation to help us in the furtherance of our work.';
		$form['donate']['foot']='<a class="button radius success expand-for-small" href="'.$href['donations'].'">Donate Now</a>';
		
		$tpl='<h5 class="hide-for-small">{title}</h5><p data-equalizer-watch>{body}</p>{foot}';

		if($type){
			if($form[$type]){
				$out=$tpl;
				foreach($form[$type] as $i=>$v){
					$out=str_replace('{'.$i.'}',$v,$out);
				}
			}
		}else{
			$tpl_main=$this->loadTemplate('tpl.footer_form.html');
			foreach($form as $set=>$parts){
				$t=$tpl;
				foreach($parts as $i=>$v){
					$t=str_replace('{'.$i.'}',$v,$t);
				}
				$tpl_main=str_replace('{'.$set.'}',$t,$tpl_main);
			}
			$tpl_main=str_replace('{id}',$fid,$tpl_main);
			$out=$tpl_main;			
		}
		return $out;
	}
	
	function footer_top(){
		$out=false;
		$staff=array();
		//text
		$tmp=$this->SLIM->Options->get('site','site_footer_text');
		if($tmp){
			$footer_text=html_entity_decode($tmp['opt_Value']);
		}else{
			$footer_text=msgHandler('No content found...');
		}
		//staff
		$tmp=$this->SLIM->Options->get('site','site_team_contacts');
		if($tmp){
			$rec=compress($tmp['opt_Value'],false);
			if(is_array($rec)) $staff=$rec;
		}
		$_tpl='<div class="media-object staffer" data-open="{staff_id}"><div class="media-object-section"><div class="staff-avatar"><i class="fi-torsos icon-x2 text-gbm-dark-blue"></i></div></div><div class="media-object-section">{content}</div></div>';
		$parts['footer_text']=$footer_text;
		$parts['staff']=false;
		foreach($staff as $i=>$v){
			if(!$v['status']) continue;
			$modal=renderCard_active('Contact: <span class="text-gbm-blue">'.$v['name'].'</span>','<div class="panel"><strong class="subheader">'.$v['title'].'</strong><p>'.str_replace(', ','<br/>',$v['address']).'</p><p><strong><i class="fi-mail text-gbm-dark-blue"></i></strong> '.$v['email'].'<br/><strong><i class="fi-telephone text-gbm-dark-blue"></i></strong> '.$v['phone'].'</p></div>',$this->SLIM->closer);
			$cblock='<div class="staff-name text-gbm-blue">'.$v['name'].'<br/><small class="subheader">'.$v['title'].'</small></div>';
			$cblock.='<div id="staff_'.$i.'" class="reveal" data-reveal data-animation-in="spin-in" data-animation-out="slide-out-down">'.$modal.'</div>';
			$cblock=str_replace('{content}',$cblock,$_tpl);
			$cblock=str_replace('{staff_id}','staff_'.$i,$cblock);
			$parts['staff'].=$cblock;
		}
		$tpl=$this->loadTemplate('tpl.footer_top.html');
		$out=fillTemplate($tpl,$parts);
		return $out;		
	}
	function footer_bottom(){
		$out=false;
		$SC=new content_social($this->SLIM,$this->SLUG);
		$social=$SC->render();
		$parts['social-links']=$social['SOCIAL_BAR2'];
		$parts['site-links']='&copy; '.date('Y').' Dance Radio Shows, All rights reserved.';
		$parts['copy-info']='powered by <span class="jam"><a href="http://www.jamtechsolutions.co.uk">Jamtech Solutions</a></span>';
		$tpl=$this->loadTemplate('tpl.footer_bottom.html');
		$out=fillTemplate($tpl,$parts);
		return $out;		
	}
	function content_sitename(){
		return $this->SITEOPTS['site_name'] . ': ' . fixHTML($this->DATA['itm_Title']);		
	}
	
	function content_slogan(){
		return $this->SITEOPTS['site_footer_slogan'];
	}  
	function loadTemplate($name,$part=true){
		$path=($part)?$this->TEMPLATE_PARTS.$name:$this->TEMPLATE_PATH.$name;
		$tpl=file_get_contents($path);
		if(!$tpl) $tpl=msgHandler('Sorry, template not found: '.$path);
		return $tpl;
	}
    function preLoadImages() {
        $out = $s=false;
        $pl=$this->PRELOAD;
        if (!empty($pl)) {
            foreach ($pl as $k => $src) {
                $s.="<img src='$src' alt='preload_$k'>\n";
            }
            $out = '<div id="preloadImages" class="hide">' . $s . '</div>';
        }
        return $out;
    }
    function getImageSRC($src=false,$base=false,$default=false){
		$args=array('src'=>$src,'base'=>$base,'default'=>$default);
		return $this->IMG->_get('getImageSRC',$args);
	}
    function getImageCredit($id=0,$edit=false){
		$args=array('id'=>$id,'edit'=>$edit);
		return $this->IMG->_get('getImageCredit',$args);
	}	
    function imageSlides($data = false, $caption=false) {
		if(is_array($data)){
			return $this->getOrbit($data);
		}else{
			return $this->getMediaBox($data,$caption);
		}	
    }
	function getImageGallery($data = false, $size = 100, $class = 'mgal') {
		$IMG=new image;
		$args['data']=$data;
		$args['size']=$size;
		$args['class']=$class;
		return $IMG->_get('getImageGallery',$args);
	}
    
	function getOrbit($parts){
		// not used
  		$slides=$bullets=$oclass=false;
 		$autoplay='false';
 		$label=issetCheck($parts,'label','TBS Images');
 		$prevnext='<button class="orbit-previous"><span class="show-for-sr">Previous Slide</span> &#9664;&#xFE0E;</button><button class="orbit-next"><span class="show-for-sr">Next Slide</span> &#9654;&#xFE0E;</button>';
		if(isset($parts['basic'])){
			$slides='<ul class="orbit-container">'.$prevnext.$parts['basic'].'</ul>';
			$bullets='<nav class="orbit-bullets">'.$parts['bullets'].'</nav>';
			$options='data-auto-play="'.$autoplay.'"';
		}else if(isset($parts['images'])){
			foreach($parts['images'] as $x=>$i){
				$slides.='<li class="orbit-slide"><img class="orbit-image" src="'.$i['src'].'" alt="'.$i['caption'].'"><figcaption class="orbit-caption">'.$i['caption'].'/figcaption></li>';
				$bullets.='<button data-slide="'.$x.'"><span class="show-for-sr">View Image '.$x.'</span></button>';
			}
			$slides='<ul class="orbit-container">'.$prevnext.$slides.'</ul>';
			$bullets='<nav class="orbit-bullets">'.$bullets.'</nav>';
		}
		if($slides) $slides='<div class="orbit" role="region" aria-label="'.$label.'" data-orbit '.$options.'>'.$slides.$bullets.'</div>';
		return $slides;
	}
    function getMediaBox($data = false, $caption=false){
        $maxSize['h']=500;
        $maxSize['w']=800;
        if (!$data || $data == '' || $data == 'NULL') return false;
        $this->init();
        $controls = '';
        $slideshow = '';
        $data=$this->getImageSRC($data);
         $img='<img src="'.$data['image'].'" style="height:auto;max-height:'.$maxSize['h'].'px; width:auto" />';
        //image
        $edit = ($this->REFRESH_CACHE || $this->PREVIEW) ? false : true;
		if (isset($data[1])){
			$credit=$this->getImageCredit($data['id'], $edit);
			$caption = $credit['html'];
			$img=str_replace('img src','img alt="'.$credit['title'].'" src',$img);
		}
		//caption
        $caption=issetCheck($this->OUTPUT,'mainImageCaption',$caption);
        if($caption==='* enter a caption *') $caption='';
        if(!$this->USE_CAPTIONS) $caption='';
        
        //box
        $mbox_caption=false;  
        $mbox_image='<div class="media-object-section align-self-center"><div class="thumbnail">'.$img.'</div></div>';
        if($caption && $caption!==''){
			$mbox_caption='<div class="media-object-section align-self-bottom"><div class="caption">'.$caption.'</div></div>';
		}
		return '<div class="media-object stack">'.$mbox_image.$mbox_caption.'</div>';
	}
	function foundationME($args) {
		// simple content formatter for Foundation css
		// use for inner content only as all classes are 'small-x'
		// fields to be swapped must be in $defs array
		$defs = array('img', 'image', 'href', 'content', 'content_2', 'content_3', 'title', 'details', 'rowclass', 'price', 'buynow');
		$t['hotlist_row'] = '<a title="click to view" class="hotLink grid-x grid-margin-x" href="{href}"><span class="cell medium-3">{img}</span><span class="cell medium-9">{details}</span></a>';
		$t['hotlist_row_sidebar'] = '<a title="click to view" class="hotLink grid-x grid-margin-x" href="{href}"><span class="cell medium-3 tiny">{img}</span><span class="cell medium-9">{details}</span></a>';
		$t['hotlist_row_sidebar_blog'] = '<a title="click to view" class="hotLink grid-x grid-margin-x post" href="{href}"><span class="small-12 cell">{img}</span><span class="small-12 cell">{details}</span></a>';
		$t['2col_row'] = '<div class="{rowclass}"><span class="small-6 cell">{content}</span><span class="small-6 cell">{content_2}</span></div>';
		$t['3col_row'] = '<div class="{rowclass}"><span class="small-4 cell">{content}</span><span class="small-4 cell">{content_2}</span><span class="small-4 cell">{content_3}</span></div>';
		$t['4col_row'] = '<div class="{rowclass}"><span class="small-3 cell">{content}</span><span class="small-3 cell">{content_2}</span><span class="small-3 cell">{content_3}</span><span class="small-3 cell">{content_4}</span></div>';
		$t['sidebar_item'] = '<div class="sidebar-title">{title}</div><div class="sidebar-content sidebar-item">{image}<span class="sbText">{content}</span></div>';
		$t['product_detail'] = '<div id="product" class="grid-x grid-margin-x"><span class="small-6 cell price">{price}</span><span class="small-6 cell text-right">{buynow}</span></div>';
		$args['rowclass'] = ($args['position'] == 'sidebar') ? '' : 'row';
		$act = trim($args['action']);
		if ($tmp = $t[$act]) {
			foreach ($defs as $key) {
				$rp = issetCheck($args,$key,'');
				$tmp = str_replace('{' . $key . '}', $rp, $tmp);
			}
			$out = $tmp;
		} else {
			$out = '<p class="alert">Sorry, I dont know what <strong>' . $act . '</strong> is...</p>';
		}
		return $out;
	}	
}
