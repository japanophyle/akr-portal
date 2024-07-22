<?php

class slimNavigation{
	private $SLIM;
	private $ROUTE;
	private $APP_VARS;
	private $USER;
	private $OPTIONS;
	private $MENUS;
	private $MENU_TYPE='sidebar';
	private $OFF_CANVAS=true;
	private $MENU_DATA;
	private $INFO_BAR;
	private $sMENU;
	
	function __construct($slim=null){
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		$this->OPTIONS=$slim->options;
		$this->sMENU=$slim->topbar_data;
		$this->init();
	}
	
	private function init(){		
		$this->MENUS['userbar']=array('left'=>'','right'=>array());
		$this->MENUS['main']=array('left'=>array(),'right'=>array());
		$this->MENUS['main']['right'][]=($this->USER['access']<20)?'<li><a href="'.URL.'login" class="button warning">Login</a></li>':'';
		$this->MENU_DATA=$this->sMENU->get('all');
	}
	function addMenuItems($bar=false,$item=false){
		switch($bar){
			case 'user':
				if($item) $this->MENUS['userbar']['right'][]=$item;
				break;
			case 'info':
			
				break;
			case 'main':
				$menu=issetCheck($item,'menu');
				$slug=issetCheck($item,'slug');
				$data=issetCheck($item,'data');
				if($menu && $slug && $data){
					$this->MENU_DATA[$menu][$slug]=$data;
				}
		}
	}
	private function setRoute($vars=false){
		if(is_array($vars)){
			$this->ROUTE=issetCheck($vars,'route');
			$this->MENU_TYPE=issetCheck($vars,'menu_type','topbar');
			$this->APP_VARS=$vars;
			$this->setActive();
		}
	}
	function setInfoBarControls($what=false,$vars=false,$reset=false){
		switch($what){
			case 'left':
			case 'right':
				if(is_array($vars)){
					if($reset) $this->MENUS['main'][$what]=[];
					foreach($vars as $i=>$v) $this->MENUS['main'][$what][]=$v;
				}
				break;
		}
	}
	private function setActive(){
		$setClass=function($r,$h,$s=false,$i=-1){
			$d=false;
			if($r===$h['href']){
				if($i>-1){
					$this->MENU_DATA[$this->ROUTE[0]][$this->ROUTE[0]]['class'].=' is-active';
					$this->MENU_DATA[$this->ROUTE[0]][$this->ROUTE[0]]['submenu'][$i]['class'].=' is-active';
					$this->MENUS['active']=ucMe($s).': <span class="subheader">'.$h['label'].'</span>';
					$d=true;
				}else if($s){
					$this->MENU_DATA[$this->ROUTE[0]][$this->ROUTE[1]]['class'].=' is-active';
					$this->MENUS['active']=ucMe($s);//$h['label'];
					$d=true;
				}else{
					$this->MENU_DATA[$this->ROUTE[0]][$this->ROUTE[0]]['class'].=' is-active';
					$this->MENUS['active']=$h['label'];//$h['label'];
					$d=true;
				}
			}
			return $d;
		};
		$done=false;
		if($this->ROUTE){
			$rt=$this->ROUTE;
			unset($rt[3],$rt[4],$rt[5]);
			$rtc=count($rt);
			$rt=implode('/',$rt);
			//check basic
			$R0=$this->ROUTE[0];
			$R1=issetCheck($this->ROUTE,1);
			if($R1==='sales' && $R0==='events'){
				$R1=$this->ROUTE[0];
				$R0=$this->ROUTE[1];
			}
			if($set=issetCheck($this->MENU_DATA,$R0)){
				foreach($set as $slug=>$vars){
					if($subs=issetCheck($vars,'submenu')){
						foreach($subs as $i=>$v){
							if($done=$setClass($rt,$v,$slug,$i)){								
								break;
							}
						}
					}else{
						if($R1){
							if($R0!=='sales' && $done=$setClass($rt,$vars,$slug)){
								break;
							}else{
								$this->MENU_DATA[$R0][$R0]['class'].=' is-active';
							}
						}else{
							if($done=$setClass($rt,$vars)){
								break;
							}
						}
					}
				}
			}
		}else{//assume dashboard
			$slug='dashboard';
			$this->MENU_DATA[$slug][$slug]['class'].=' is-active';
		}
		return $done;
	}
	function Render($app_vars=false,$what=false){
		$this->setRoute($app_vars);
		switch($what){
			case 'topbar':
			case 'sidebar':
				$this->MENU_TYPE=$what;
				break;
			default:				
		}
		return $this->renderOutput();
	}
	
	function setInfobar($str=false){
		$this->INFO_BAR=$str;
	}
	function setTopButton($html=false,$name=false,$set='right'){
		if(is_array($html) && $name){
			foreach($html as $i=>$v) $this->MENUS['main'][$set][$i]=$v;
		}else if(is_string($html) && $name){
			$this->MENUS['main'][$set][$name]=$html;
		}
	}
	function setTopPager($pager=false){
		if($pager){
			$this->MENUS['main']['right']['pager']=$pager;
		}
	}
	private function renderHelpDesk(){
		$help_states=$this->OPTIONS->get('request_states');
		$help_colors=$this->OPTIONS->get('request_colors');
		$help_metrics=$this->OPTIONS->get('metrics_helpdesk_status_count');
		$help_metrics=rekeyArray($help_metrics,'item');
		$out['help_text']='Helpdesk';
		$out['help_class']='link-yellow';
		$out['help_title']='';
		foreach($help_metrics as $i=>$v){
			if((int)$i<4){
				if($i==2){
					if($this->USER['access']<25){
						$out['help_text'].=' ('.$v['count'].')';
						$out['help_class']='button-'.$help_colors[$i].' text-white';
						$out['help_title']=$help_states[$i];
						break;
					}
				}else if($i==3){
					if($this->USER['access']>=25){
						$out['help_text'].=' ('.$v['count'].')';
						$out['help_class']='button-'.$help_colors[$i].' text-white';
						$out['help_title']=$help_states[$i];
						break;
					}
				}else{
					$out['help_text'].=' ('.$v['count'].')';
					$out['help_class']='button-'.$help_colors[$i].' text-white';
					$out['help_title']=$help_states[$i];
					break;
				}					
			}
		}
		if($out['help_title']!=='') $out['help_title']='Status: '.$out['help_title'];
		return $out;
	}
	private function renderUserIcon(){
		$access_levels=$this->OPTIONS->get('access_levels');
		$icon_color='gray';
		$level=issetCheck($access_levels,$this->USER['access'],array('label'=>'*unknown','color'=>'gray'));
		return '<i class="fi-shield text-'.$level['color'].'" title="'.$level['label'].'"></i>';
	}
	private function renderUserNotice(){
		$notice='<div class="user-notice bg-amber text-black">This is a sample notice...</div>';
		return false;//$notice;
	}
	private function renderUserBarControls($search=false){
		$buttons['dev']='<button class="bar-button link-lavendar gotoME" data-ref="'.URL.'super">Dev Menu</button>';
		$buttons['settings']='<button data-toggle="offCanvas" class="bar-button link-aqua" href="#nogo">Settings</button>';
		$buttons['public']='<button class="bar-button link-lime gotoME" data-ref="'.URL.'page">Public Site</button>';
		$buttons['logout']='<button data-ref="'.URL.'page/login" class="bar-button link-orange loadME">Logout</button>';
		$buttons['cancel_temp']='<button class="bar-button button-red gotoME" data-ref="'.URL.'api/users/temp_login/0/logout">Cancel Temp Login</button>';
		$buttons['canvas_menu']='<button class="bar-button button-aqua" data-toggle="offCanvas_menu"><i class="fi-list show-for-medium"></i> Main Menu</button>';
		$controls=$this->MENUS['userbar']['right'];
		$tmp_login=slimSession('get','temp_login');
		switch($this->USER['access']){
			case 30:
				$buts=array('dev','settings','public','logout');
				if($tmp_login) $buts[]='cancel_temp';
				if($search) $controls[]=$search;
				if($this->OFF_CANVAS) $buts[]='canvas_menu';
				foreach($buts as $b) $controls[]=$buttons[$b];
				break;
			case 25:
				$buts=array('settings','public','logout');
				if($this->OFF_CANVAS) $buts[]='canvas_menu';
				if($tmp_login) $buts[]='cancel_temp';
				if($search) $controls[]=$search;
				foreach($buts as $b) $controls[]=$buttons[$b];
				break;
			default:
				$buts=array('public','logout');
				foreach($buts as $b) $controls[]=$buttons[$b];
		}
		return implode('&nbsp;',$controls);		
	}	
	private function renderUserbar(){
		if($this->USER['access']>=20){
			$helpdesk=$this->renderHelpDesk();
			//icon
			$user_icon=$this->renderUserIcon();
			$user_notice=$this->renderUserNotice();
			$access_levels=$this->OPTIONS->get('access_levels');
			$this->MENUS['userbar']['left']='
			<div class="user-bar-left">
				<div class="grid-x grid-margin-x">
					<div class="cell shrink show-for-large">
						<div class="title">'.$user_icon.' Logged in as '.$this->USER['name'].'</div>
					</div>
					<div class="cell auto">
						'.$user_notice.'
					</div>
				</div>
			</div>';
			$search=$this->SLIM->zurb->search('findME',URL.'members/search/','bg-olive').'&nbsp;';
			$this->MENUS['userbar']['right']=$this->renderuserBarControls($search);
		}else{
			$this->MENUS['userbar']['right']=$this->MENUS['userbar']['right']=false;
		}		
	}
	private function renderInfobar(){
		$str=($this->INFO_BAR && $this->INFO_BAR!=='')?$this->INFO_BAR:'';
		if($str==='') $str=issetCheck($this->MENUS,'active');
		return '<li class="menu-text infobar">'.$str.'<span class="subheader">&nbsp;</span></li>';	
	}
	private function renderMainMenu(){
		if($this->USER['access']>=20){
			$class=($this->MENU_TYPE==='topbar')?'submenu menu vertical':'menu vertical sublevel-1';
			foreach($this->MENU_DATA as $set=>$group){
				$menu=$sb=$active_x=false;
				foreach($group as $slug=>$rec){
					$active=false;
					if(!$active_x && strpos($rec['class'],'is-active')!==false) $active_x=' is-active';
					if(strpos($rec['class'],'is-active')!==false) $active='is-active';
					if($subs=issetCheck($rec,'submenu')){
						if(is_array($subs)){
							$subs=$this->renderSubmenu($subs);
						}else{
							$_group=$group;
							unset($_group[$slug]);
							$subs=$this->renderSubmenu($_group);
							$lineclass=$sb='has-submenu';
							$menu.=$this->renderLine($subs,$active);
							break;
						}
					}
					$link=$this->renderLink($rec['label'],$rec['href'],$rec['class']);
					$lineclass=false;
					if($subs){
						$link.=$this->renderGroup($subs,$class,'data-submenu');
						$lineclass=$sb='has-submenu';
					}
					$menu.=$this->renderLine($link,$active);
				}
				if($sb){
					$link=$this->renderLink(ucME($slug),$rec['href'],$rec['class']);
					$menu=$this->renderGroup($menu,$class);
					$link=$this->renderLink(ucME($set),'#nogo','nogo'.$active_x);
					$menu=$this->renderLine($link.$menu,$sb.$active_x);
				}else{
					$menu=$this->renderLine($menu);
				}
				$this->MENUS['main']['left'][$set]=$menu;
			}
		}		
	}
	private function renderSubmenu($data=false){
		$out=false;
		if(is_array($data)){
			foreach($data as $i=>$v){
				$active=(strpos($v['class'],'is-active')!==false)?'is-active':false;
				$link=$this->renderLink($v['label'],$v['href'],$v['class']);
				$out.=$this->renderLine($link,$active);
			}
		}
		return $out;
	}	

	private function renderLink($label=false,$href='#nogo',$class=false){
		$out=false;
		if($label){
			$trunc=(strlen($label)>16)?' truncate':false;
			$title=($trunc)?' title="'.$label.'""':false;
			$class=$class.$trunc;
			if($class) $class='class="'.$class.'"';
			$out='<a '.$class.' '.$title.' href="'.$href.'">'.$label.'</a>';
		}
		return $out;
	}
	private function renderLine($content=false,$class=false){
		$out=false;
		if($content){
			if($class) $class='class="'.$class.'"';
			$out='<li '.$class.'>'.$content."</li>\n";
		}
		return $out;
	}
	private function renderGroup($content=false,$class=false){
		$out=false;
		if($content){
			if($class) $class='class="'.$class.'"';
			$out='<ul '.$class.'>'.$content.'</ul>';
		}
		return $out;
	}
	
	private function renderOutput(){
		$out=$userbar=false;
		if($this->USER['access']>=20){
			$this->renderUserbar();
			$userbar='<div id="user-bar">'.$this->MENUS['userbar']['left'].$this->MENUS['userbar']['right'].'</div>';
		}
		$this->renderMainMenu();
		$settings_menu=($this->USER['access']>=25)?$this->SLIM->view->fetch('app.settings_menu.html',array('URL'=>URL)):'<p>What are you doing here...</p>';
		$this->SLIM->display->set('TPL_SETTINGS_MENU',false,$settings_menu);

		$menu_left=implode('',$this->MENUS['main']['left']);
		$menu_right=implode('',$this->MENUS['main']['right']);
		$active_text=$this->renderInfobar();
		$site_name=$this->SLIM->AppVars->get('site_name');

		switch($this->MENU_TYPE){
			case 'topbar':
				$topbar=$this->SLIM->view->fetch('topbar.html',array('TPL_USERBAR'=>$userbar,'TPL_MENU_LEFT'=>$menu_left,'TPL_MENU_RIGHT'=>$menu_right,'URL'=>URL));
				$this->SLIM->display->set('TPL_TOPBAR',false,$topbar);
				$out=true;
				break;
			case 'sidebar':
				if(trim($menu_left)!==''){
					$menu_left='<ul class="multilevel-accordion-menu vertical menu" data-accordion-menu>'.$menu_left.'</ul>';
				}else{
					$menu_left=false;
				}
				$topbar=$this->SLIM->view->fetch('topbar.html',array('TPL_USERBAR'=>$userbar,'TPL_MENU_LEFT'=>$active_text,'TPL_MENU_RIGHT'=>$menu_right,'TPL_SITE_NAME'=>$site_name));
				$this->SLIM->display->set('TPL_TOPBAR',false,$topbar);			
				$this->SLIM->display->set('TPL_ACCORDIANBAR',false,$menu_left);
				$this->SLIM->display->set('TPL_USERBAR',false,$userbar);
				$out=true;
			default:
		}
		return $out;
	}

}
