<?php 
class slimArrayMenu{
   private $SLIM;
   private $DATA;
   private $MENU;
   private $LEFT;//topbar
   private $RIGHT;//topbar
   private $SIDE;//off-canvass   
   private $LEVELS=array();
   private $MCOUNT=1;
   private $NAV_CLASS;
   private $MONTH=0;
   private $YEAR=0;
   private $ADMIN=false;
   private $LOGIN=true;
   private $USER;
   private $ADD_SEARCH=false;
   private $ACTIVE;
   private $OPTIONS;
   private $titleHTML;
   
   public $NAV_TEMPLATE='default';
   public $BASE_SLUG='page';
   public $DEVICE;
   public $RESPONSE;

   function __construct($slim,$data,$active_id='nothing'){
	   $this->SLIM=$slim;
	   $this->DEVICE='classic';
	   $this->USER=$slim->user;
	   if($slim->user['access']>=$this->SLIM->AdminLevel) $this->ADMIN=true;
       $this->ACTIVE=array('id'=>(is_numeric($active_id)?(int)$active_id:$active_id),'L1'=>false,'L2'=>false,'L3'=>false);
	   $this->setVars($data);
	   $this->YEAR=(int)date('Y');
	   $this->MONTH=(int)date('n');
   }
   
   public function rebuildMenu($render=true){
	  $this->buildMenu();
	  $this->buildMenuArray();
	  $this->renderTopBar($render);
	  $this->RESPONSE['response']=200;
   }
   
   private function error($msg,$err=500){
       $this->RESPONSE['message']=$msg;
	   $this->RESPONSE['response']=$err;
	   die($msg);
   }
   
   private function buildMenu(){
	   $menu=$this->renderLine('closer',false);
	   if($this->LOGIN && !$this->USER['access']){
		   $this->DATA[]=array('label'=>'Login','url'=>'login' ,'link_class'=>'loadME');
	   }
	   if($this->ADD_SEARCH) $this->DATA[]=array('label'=>'Search','url'=>'search' ,'link_class'=>'loadME');
       foreach($this->DATA as $mid=>$recs){
		   $subs=issetCheck($recs,'subs');
		   unset($recs['subs']);
		   if($subs){
	          $subs=$this->renderRows($subs,1,$recs);
		      $args=array('rows'=>$subs,'class_ul'=>$this->getNavClass('submenu'));
		      $subs=$this->renderBlock($args);
		      $recs['class_li']=$this->getNavClass('main_has_submenu');
		   }
		   $menu.=$this->renderLine($recs,$subs);
	   }
	   $args=array('rows'=>$menu,'class_ul'=>'left');
	   $this->MENU=$this->renderBlock($args);
	   $this->RIGHT=$menu.$this->SLIM->memberNav;
	   $this->SIDE=$this->setSide();
   }
   
   public function fixArray(){
	    $parents=$kids=[];
	    $data=$this->DATA;
	    $pos=0;
		foreach($data as $pid=>$nav){
			$parent=(int)$nav['parent'];
			if($parent>0){
				$kids[$parent][$pid]=$nav;
			}else{
				$nav['position']=$pos;
			    $parents[$pid]=$nav;
			    $pos++;
			}				
		}
		foreach($kids as $parent=>$nav){
			$pos=0;
			foreach($nav as $i=>$v){
				$nav[$i]['position']=$pos;
				$pos++;
			}
		    $parents[$parent]['subs']=$nav;	
		}
		$this->setVars($parents);
   }
   private function buildMenuArray(){
	   $this->RESPONSE['data']=$this->DATA;
   }
   
   //setters
   private function addParents($data=false){
	   if(is_array($data)){
		   foreach($data as $pid=>$nav){
			   if(!$item=$this->DATA[$pid]){
				   $this->DATA[$pid]=$nav;
			   }
		   }
	   }
   }
   private function addChildren($data,$parent){
       $menu_parent=$this->DATA[$parent];
       if($menu_parent){
		   if(is_array($data)){
			   $this->DATA[$parent]['subs']=$data;		   
		   }
	   }else{
		   $this->error('Sorry, the parent['.$parent.'] does not exist');
	   }
   }
   private function findParent($id=false){
		if($id){
			foreach($this->DATA as $i=>$v){
				if($v['id']==$id) return $i;
			}
		}
		return false;
   }
   
   public function addEvents($mevents,$limit,$parent){
	   // this is using the current month only!!  sort range
		if(is_array($mevents)){
			//check menu parent
            $menu_parent=$this->findParent($parent);
            if(!$menu_parent) $this->error('Sorry, the parent['.$parent.'] does not exist');
            $subs = $sort=[];
            $ct=0;
            $event_limit=(int)$this->OPTIONS['events']['menu_events'];
            $menu_limit=(int)$this->OPTIONS['events']['menu_limit'];
            $mdata=is_array($mevents['data'])?$mevents['data']:array();
            foreach ($mdata as $day => $events){
				foreach($events as $i=>$v){
					$uri=($v['display']['event_page_slug'])?$v['display']['event_page_slug']:'events/?e='.$v['data']['eventID'];
					$lbl=$v['display']['event_title'].'<br/>- '.date('jS M Y g.ia',$day);
					$exists=$this->eventExists($lbl,$subs);
					$addthis=false;
					if(!$exists){
						if($event_limit){
							if($event_limit===(int)$v['display']['event_type']){
								$addthis=true;
							}
						}else{
							$addthis=true;
						}
						
						if($addthis){
							$time=$day;
							
							$subs[$ct]=array(
								'label' => $lbl,
								'slug' => $uri,
								'parent' => $parent
							);
							$sort[$ct]=$time;
							$ct++;
						}
						if($ct>=$menu_limit) break;
					}
				}
			    if($ct>=$menu_limit) {
					break;
				}
			}
 			if($subs){
				asort($sort);
				foreach($sort as $i=>$v){
					$sorted[$i]=$subs[$i];
				}
				//hack - scan subs for duplicates
				$sorted=$this->check4Dupes($sorted);
				if(is_array($this->DATA[$menu_parent]['subs'])) $sorted+=$this->DATA[$menu_parent]['subs'];
				$this->DATA[$menu_parent]['subs'] = $sorted;
			}
		}
   }
   private function eventExists($label=false,$data=false){
	   if(is_array($data) && $label){
		   foreach($data as $i=>$v){
			   if($v['label']===$label) return true;   
		   }
	   }
	   return false;
   }
   private function fixRecurDate($old=0,$day=0){
		$n_time=$old;
		if($old && $day){
			$h_time=(int)date('H',$old);
			$m_time=(int)date('i',$old);
			$n_time=mktime($h_time, $m_time, 0, $this->MONTH, $day, $this->YEAR);
		}
		return $n_time;		
   }

   public function addEvents_old($mevents,$limit,$parent){
        if ($mevents['data']) {
           $menu_parent=$this->DATA[$parent];
            if(!$menu_parent) $this->error('Sorry, the parent['.$parent.'] does not exist');
            $subs = false;
			$ct=0;
            foreach ($mevents['data'] as $eid => $event) {
                $eslug=($event->meta['page_slug'])?$event->meta['page_slug']:'events/?e='.$event->eventID;
                if($mevents['limit_event']){
                    if($mevents['limit_event']===$event->eventType){
                        $subs[$eid] = array(
                            'label' => fixHTML($event->eventTitle).'<br/>- '.date('jS M Y g.ia',$event->eventTime_Start),
                            'slug' => $eslug,
                            'parent' => $parent
                        );
                        $ct++;
                    }
                }else{
                    $subs[$eid] = array(
                        'label' => fixHTML($event->eventTitle).'<br/>- '.date('jS M Y g.ia',$event->eventTime_Start),
                        'slug' => $eslug,
                        'parent' => $parent
                    );
                    $ct++;
                }
                if($ct>=$limit)break;
            }
            if ($subs) {
				//hack - scan subs for duplicates
				$subs=$this->check4Dupes($subs);
				$this->DATA[$parent]['subs'] = $subs;
			}
        }	   
   }
   private function check4dupes($data){
		$fixed=$chk=[];
		if($data){
			foreach($data as $i=>$v){
				$chk[$v['label']]=$i;
			}
			foreach($chk as $i=>$v){
				$fixed[$v]=$data[$v];
			}
		}
		return $fixed;
   }
   
   //getters
   public function getMenuData(){
	   return $this->DATA;
   }
   
   //renders
   private function renderRows($args,$is_sub=false,$label=false){
      $out=false;
      foreach($args as $rec){
		 $_subs=issetCheck($rec,'subs');
		 if($_subs){
			  $_subs=$this->renderRows($_subs,1,$args);
			  $_args=array('rows'=>$_subs,'class_ul'=>$this->getNavClass('submenu_sub'));
			  $_subs=$this->renderBlock($_args);
		 }
	     $rec['class_li']=$this->getNavClass('subMenu_li');
	     $out.=$this->renderLine($rec,$_subs);
	  }
      if($label){
		if(isset($label['slug'])){
			$label='<a href="'.URL.$this->BASE_SLUG.'/'.$label['slug'].'" class="parent-link">'.$label['label'].'</a>';
		}else{
			$label=false;
		}
	  }
      if($is_sub && $this->NAV_TEMPLATE=='oncanvas'){
		  $out=$this->renderOnCanvasLinks($label,$out,count($args));
	  }else if($is_sub && $this->NAV_TEMPLATE=='topbar'){
		  $out=$this->renderTopBarLinks($label,$out,count($args));
	  }
	  return $out;
   }
   
   private function renderLine($args,$subs=false){
	  if($args==='closer'){
		  $line=false;
	  }else{
		  $lc=array('loadME','gotoME');
		  $class_li=$this->getNavClass('li');
		  $class_link=issetCheck($args,'link_class');
		  $line_class=(isset($args['class_li']))?$args['class_li']:$class_li;
		  $link_class[]=($subs)?$this->getNavClass('submenu_link'):false;
		  $link_class[]=$class_link;
		  //set active
		  if(!issetCheck($args,'slug')){
			  $args['slug']=$args['url'];
			  $args['id']=0;
		  }
		  $active=$this->setActiveClass((int)$args['id']);
		  $link_class='class="'.implode(' ',$link_class).'" ';
		  $link_data=(issetCheck($args,'data'))?$args['data']:false;
		  $link_target=issetCheck($args,'target');
		  if($link_target==='_blank')$link_target='target="'.$link_target.'"';
		  $external=(strpos($args['slug'],'http')!==false)?true:false;
		  $uri=($external)?$args['slug']:URL.$this->BASE_SLUG.'/'.$args['slug'];
		  $href='href="'.$uri.'"';
		  foreach($lc as $l){
			  if(strpos($class_link,$l)!==false){
				  $href='data-ref="'.$uri.'"';
				  break;
			  }
		  }
		  $line='<li class="'.$line_class.$active.'"><a '.$href.' '.$link_class.' '.$link_target.' >'.$args['label'].'</a>'.$subs.'</li>';
	  }
	  return $line;
   }
   
   private function renderBlock($args){
      $block='<ul class="'.$args['class_ul'].'">'.$args['rows'].'</ul>';
	  return $block;
   }
   
   private function renderTopBar($render=true){
		$Sdata['left']=$this->setLeft();		
		$Sdata['right']=$this->setRight();
		$data['section']=$this->setSide();
		$data['title']=$this->setTitle();
		if($render){
			$this->RESPONSE['html']=$this->setMain($data);
		}else{
			$this->RESPONSE['data']=($Sdata+=$data);
		}	
   }
   
   private function renderOnCanvasLinks($label=false,$rows=false,$count=0){
		$back='<li class="hide-for-large back"><a href="#">Back</a></li>';
		$links=false;
		if($label) $links.=$back.'<li class="hide-for-large"><label>'.$label.'</label></li>';
		if($rows) $links.=$rows;
		if($count>5) $links.=$back;
		return $links;	   
   }
   private function renderTopBarLinks($label=false,$rows=false,$count=0){
		$links=false;
		if($label) $links.='<li class="hide-for-large"><label>'.$label.'</label></li>';
		if($rows) $links.=$rows;
		return $links;	   
   }
   //helpers
   private function getNavClass($name=false){
	    if(!$name) return false;
	    if(!$this->NAV_CLASS) $this->setNavClasses();
	    return issetCheck($this->NAV_CLASS,$name);
   }
   
   private function setNavClasses(){
	    $class['default']['main_has_submenu']='sectionTop has-dropdown not-click';
	    $class['default']['main_no_submenu']='sectionTop';
	    $class['default']['submenu']='dropdown';
	    $class['default']['submenu_li']='subLink2';
	    $class['default']['submenu_link']=false;
	    $class['default']['submenu_li_a']=false;
	    $class['default']['title_area']='title-area';
	    $class['default']['title_logo']='name';
	    $class['default']['title_menu']='toggle-topbar menu-icon';
	    $class['default']['li']='sectionTop';
	    $class['default']['li_a']=false;
	    
	    $class['oncanvas']['main_no_submenu']=false;
	    $class['oncanvas']['main_has_submenu']='has-submenu';
	    $class['oncanvas']['submenu']='right-submenu';
	    $class['oncanvas']['submenu_link']='submenu-link';
	    $class['oncanvas']['submenu_li']=false;
	    $class['oncanvas']['submenu_li_a']='right-';
	    $class['oncanvas']['title_area']='title-area';
	    $class['oncanvas']['title_logo']='name';
	    $class['oncanvas']['title_menu']='toggle-topbar menu-icon';
	    $class['oncanvas']['li']=false;
	    $class['oncanvas']['li_a']=false;

	    $class['topbar']['main_no_submenu']=false;
	    $class['topbar']['main_has_submenu']='has-submenu';
	    $class['topbar']['submenu']='right-submenu';
	    $class['topbar']['submenu_link']='submenu-link';
	    $class['topbar']['submenu_li']=false;
	    $class['topbar']['submenu_li_a']='right-';
	    $class['topbar']['title_area']='title-area';
	    $class['topbar']['title_logo']='name';
	    $class['topbar']['title_menu']='toggle-topbar menu-icon';
	    $class['topbar']['li']=false;
	    $class['topbar']['li_a']=false;
	    
	    $this->NAV_CLASS=$class[$this->NAV_TEMPLATE];	    
   
   }
   
   private function setLeft(){
		$out='';
	    if(!$this->ADMIN){
		   $out='';
		}
		if($out!='') $out=$this->setTemplate('left',$out);
		return $out;
   }
   private function setRight(){
		$out='';
	    if($this->RIGHT){
		   $out=$this->setTemplate('left',$this->RIGHT);
		}
		return $out;
   }
	private function setSection($data){
	    if($data){
			$data=implode('',$data);
			return $this->setTemplate('section',$data);
		}
	}
	private function setSide(){
		$out='';
	    if($this->RIGHT){
		   $out=$this->setTemplate('side',$this->RIGHT);
		}
		return $out;
	}
	private function setTitle(){
		$data='';
		if($this->titleHTML){
			$data='<h1><a href="">'.$this->titleHTML.'</a></h1>';			
		}
		$out=$this->setTemplate('title',$data);
		return $out;
	}
	private function setMain($data){
		if($data){
			$data=implode('',$data);
			return $this->setTemplate('main',$data); 
		}
	}

   private function setTemplate($name=false,$data=null){
	    $menutype=$submenutype=false;
	    if($this->NAV_TEMPLATE==='oncanvas'){
			$submenuData=($this->DEVICE=='classic')?'data-responsive-menu="drilldown large-dropdown"':'data-drilldown';
			$submenuClass=($this->DEVICE=='classic')?'':'vertical';
		}else if($this->NAV_TEMPLATE=='topbar'){
			$submenuData=($this->DEVICE=='classic')?'data-responsive-menu="drilldown large-dropdown"':'data-drilldown';
			$submenuClass=($this->DEVICE=='classic')?'':'vertical';			
		}
		$tpl['default']['main']='<nav class="top-bar" data-topbar data-options>{main}</nav>';
		$tpl['default']['left']='<ul class="left">{left}</ul>';
		$tpl['default']['right']='<ul class="right">{right}</ul>';
		$tpl['default']['title']='<ul class="title-area"><li class="name">{title}</li><li class="toggle-topbar menu-icon"><a href="#"><span>&nbsp;</span></a></li></ul>';
		$tpl['default']['section']='<section class="top-bar-section">{section}</section>';
		
		$oc_close='<li class="hide-for-large close" data-toggle="offCanvasRight"><button>Close</button></li>';
		$tpl['oncanvas']['main']='{main}';
		$tpl['oncanvas']['left']='<ul id="main-menu" class="'.$submenuClass.' dropdown menu" '.$submenuData.' >'.$oc_close.'{left}'.$oc_close.'</ul>';
		$tpl['oncanvas']['right']='<ul class="right">{right}</ul>';
		$tpl['oncanvas']['title']=false;
		$tpl['oncanvas']['section']='{section}';
		
		$medi='<ul id="medi-menu" class="show-for-medium-only menu"><li><button class="link-gbm-dark-blue" data-toggle="offCanvas"><i class="fi-list icon-x2"></i></button></li></ul>';
		$tpl['topbar']['main']='{main}';
		$tpl['topbar']['left']=$medi.'<ul id="main-menu" class="'.$submenuClass.' dropdown menu show-for-large" '.$submenuData.' >{left}</ul>';
		$tpl['topbar']['right']='<ul class="right">{right}</ul>';
		$tpl['topbar']['title']=false;
		$tpl['topbar']['section']='{section}';
		$tpl['topbar']['side']='<ul id="mini-menu" class="'.$submenuClass.' dropdown menu" '.$submenuData.' >{side}</ul>';
		
		$out='';
		$template=$tpl[$this->NAV_TEMPLATE];
		if(is_array($data)){
			foreach($data as $i=>$v){
				$out.=str_replace('{'.$i.'}',$v,$template[$i]);
			}
		}else if($name && $template[$name]){
			$out=str_replace('{'.$name.'}',$data,$template[$name]);
		}
		return $out;
   }
    
   private function setActiveClass($id){
	  $class='';
	  if($this->ACTIVE['id']!=='nothing'){
		  if($id===$this->ACTIVE['L1']){
			  $class=' active parent';
		  }else if($id===$this->ACTIVE['L2']){
			  $class=' active';
		  }else if($id===$this->ACTIVE['L3']){
			  $class=' active';
		  }
	  }
	  return $class;
   }
   
   private function setVars($data){
       $this->DATA=(!is_array($data))?array():$data;
        $this->OPTIONS['events']=[];
       $this->titleHTML=false;
       foreach($this->DATA as $id=>$rec){
		   $this->LEVELS[1][]=(int)$id;
		   if(issetCheck($rec,'subs')){
			   foreach($rec['subs'] as $sid=>$srec){
				    $this->LEVELS[2][]=$sid;
				    if($sid===$this->ACTIVE['id']){
						$this->ACTIVE['L2']=(int)$sid;
						$this->ACTIVE['L1']=(int)$id;
					}
					if(issetCheck($srec,'subs') && is_array($srec['subs'])){
						foreach($srec['subs'] as $xid=>$xrec){
							$this->LEVELS[3][]=(int)$xid;
							if($xid===$this->ACTIVE['id']){
								$this->ACTIVE['L3']=(int)$xid;
								$this->ACTIVE['L2']=(int)$sid;
								$this->ACTIVE['L1']=(int)$id;
							}
						}
					}
			   }
		   }
		   //set active parent flags
		   if($id===$this->ACTIVE['id']) $this->ACTIVE['L1']=(int)$id;
		   //set active parent slug
		   if($rec['slug']===$this->ACTIVE['id']) $this->ACTIVE['L1']=(int)$id;			
	   }
   }   
}
