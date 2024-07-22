<?php
class content_main{
	private $SLIM;
	private $SLUG;
	public $DATA;
	
	private $FAUX_PAGES;
	private $EVENT_ID=0;
	private $AJAX;
	private $IS_FAUX;
	private $IS_PARENT;
	private $ADMIN;
	private $ACCESS;
	private $IMAGES=['akr_kyudo_bows_down','akr_kyudo_waiting','akr_kyudojo_above','mato_sand_banner_crop','yumi_banner','yumi_hand_banner'];
	private $PRELOAD;
	private $JQD=[];
	private $ID;
	
	function __construct($slim=null,$slug=false){
		if(!$slim) throw new Exception('no slim object!!');
		if(!$slug){
			$slug=$slim->router->get('page_slug');
		}
		if(!$slug) throw new Exception('no page slug found!!');
		$this->SLIM=$slim;
		$this->SLUG=$slug;
		$this->AJAX=$slim->router->get('ajax');
		$this->FAUX_PAGES=$slim->config['PUBLIC']['faux'];
		$this->IS_FAUX=$this->isFauxPage($this->SLUG);
		if($slim->user['access']>=3) $this->ADMIN=true;
	}
	//common methods
	function render($args=false){
		if($args==='main_image') return $this->getMainImage();
		$this->setArgs($args);
		return $this->renderContent();
	}
	
	private function setArgs($args=false){
		if(isset($args['ITEM_SLUG'])){
			$this->SLUG=$args['ITEM_SLUG'];
			$this->IS_FAUX=$this->isFauxPage($this->SLUG);
		}
		$this->EVENT_ID=(int)issetCheck($args,'EVENT_ID');
		if(!$this->IS_FAUX && !$this->DATA){
			$this->getData();
		}else if($this->DATA){
			if(!isset($this->DATA['meta'])){
				$this->DATA['meta']=(is_null($this->DATA['ItemShort']))?[]:json_decode($this->DATA['ItemShort'],1);
			}
		}
	}
	
	// custom methods
	private function renderContent(){
		$sidebar=false;
		$out=$layout_tpl=$title=$edit=$main_image=false;
		if($this->SLUG=='homex'){
			$out=false;
		}else if($this->IS_FAUX ){
			$faux=$this->SLIM->PageContent->get('faux',$this->SLUG);
			if(is_array($faux)){
				$out=$faux['content'];
				$main_image=$faux['image'];
			}else{
				$out=$faux;
			}
			$sidebar='';
			$layout_tpl='parts/'.getLayouts('templates', 1);
			$main_image=($main_image)?$main_image:$this->getMainImage();
		}else{
			if($this->DATA){
				$this->checkAccess();
				if(!$this->ACCESS){
					return ['main'=>$this->renderError('no_access'),'main_image'=>$this->getMainImage()];
				}
				$status=$this->DATA['ItemStatus'];
				if($status==='published'||$this->ADMIN){
					$dojoNav=$this->getDojoPageNav();
					$meta=issetCheck($this->DATA,'meta',[]);
					$layout=(int)$this->SLIM->PageContent->findMeta('page_layout_template');
					$layout_tpl=getLayouts('templates', $layout);
					$title=fixHTML($this->DATA['ItemTitle']);
					$out='<h2 class="title">{TPL_PAGE_TITLE}</h2>'.$edit;
					$out.='<div class="block-wrapper">';
					$out.=$dojoNav;
					$main_image=$this->getMainImage($meta);
					if($main_image){
						$this->PRELOAD[]=$main_image;
						$main_image=$main_image;
					}				
					if(issetCheck($meta,'mainPre')) $out.=$meta['mainPre'];
					$out.=(is_null($this->DATA['ItemContent']))?'':html_entity_decode($this->DATA['ItemContent']);
					$out.='</div>';//end block wrapper
					$out=fixHTML($out);
					if($layout===5){
						$sidebar=$this->SLIM->PageContent->get('article',$layout);
						$this->JQD['masonry']='masonry:{container:".infogrid",part_class:".info-box",gutter:0}';
					}
				}else{
					$out=$this->renderError('content_not_available');
					$main_image=$this->getMainImage();
				}
			}else{
				$out=$this->renderError('page_not_found',$this->SLUG);
				$main_image=$this->getMainImage();
			}
		}
		if($this->AJAX){
			if($title){
				$this->SLIM->assets->set('title',$title,'modal');
				$this->SLIM->assets->set('title',$title,'page');
			}
			$out=array('main'=>$out);
		}else if($sidebar){
			$this->SLIM->assets->set('title',$this->DATA['ItemTitle'],'page');
			if($layout_tpl && $layout_tpl!==''){
				$tpl=file_get_contents(TEMPLATES.$layout_tpl);
				$parts=array('main'=>$out,'sidebar'=>$sidebar);
				$out=fillTemplate($tpl,$parts);
			}else{
				$out=array('main'=>$out,'side'=>$sidebar);
			}
		}else{
			$title=issetCheck($this->DATA,'ItemTitle');
			$this->SLIM->assets->set('title',$title,'page');
			$out=array('main'=>$out,'side'=>false,'main_image'=>$main_image);
		}
		return $out;
	} 
	private function renderError($msg,$args=false){
		$msg_arg=($args)?' ['.$args.']':'';
		$lang=$this->SLIM->language->get('_LANG');
		$pg=$this->SLIM->language->getStandardPhrase('error_message_public');
		$msg=$this->SLIM->language->getStandardPhrase($msg).$msg_arg;
		return '<div class="grid-x widthlock"><div class="cell"><h2>'.$pg.'</h2>'.msgHandler($msg,'alert',false).'<button class="button button-olive gotoME" data-ref="'.URL.'page/home"><i class="fi-home"></i> Homepage</button></div></div>';
	}
	private function isFauxPage($slug){
	    $faux=false;
	    if(in_array($slug,$this->FAUX_PAGES)){
			$faux=true;
		}
		return $faux;
	}
 	private function isParent($data=[]){
		$chk=false;
		if($data){
			$t=(int)issetCheck($data,'ItemParent');
			if(!$t){
				//no
			}else if(isset($data['ARTICLE_DATA']) && !count($data['ARTICLE_DATA'])){
				//no
			}else{
				$chk=true;
			}
		}
		$this->IS_PARENT=$chk;
	}
	private function getMainImage($meta=[]){
		shuffle($this->IMAGES);
		$rand='gfx/akr/'.current($this->IMAGES).'.jpg';
		return issetCheck($meta,'page_main_image',$rand);
	}
	
	private function getData(){
		$rec=$this->SLIM->db->Items();
		$rec->where('ItemSlug',$this->SLUG);		
		$rez=renderResultsORM($rec,'ItemID');
		if($rez){
			$this->DATA=current($rez);
			$this->ID=$this->DATA['ItemID'];
			$this->DATA['meta']=json_decode($this->DATA['ItemShort'],1);
		}else{
			//throw new Exception(__CLASS__.': no page data found for "'.$this->SLUG.'"');
		}
	}
	private function checkAccess(){
		$access=(int)$this->DATA['ItemOrder'];
		$dojo=(int)$this->DATA['ItemPrice'];
		$this->ACCESS=true;
		if($access>1){
			if($this->SLIM->user['access']<$access){
				$this->ACCESS=false;
			}
			if($dojo && $this->SLIM->user['access'] < $this->SLIM->AdminLevel){
				$user_dojos=issetCheck($this->SLIM->user,'dojo_lock',[]);
				if(!in_array($dojo,$user_dojos)) $this->ACCESS=false;
			}
		}
	}
	private function getDojoPageNav(){
		$nav='';
		$links=[];
		if((int)$this->DATA['ItemPrice']){
			$recs=$this->SLIM->db->Items->where('ItemPrice',$this->DATA['ItemPrice'])->and('ItemStatus','published')->select('ItemID,ItemSlug,ItemTitle');
			if($recs=renderResultsORM($recs,'ItemID')){
				foreach($recs as $i=>$v){
					if($i!=$this->DATA['ItemID']) $links[]='<li><a href="'.URL.'page/'.$v['ItemSlug'].'"><i class="fi-play"></i> '.$v['ItemTitle'].'</a></li>';
				}
				if($links) $nav='<ul class="menu"><li class="menu-text">Other Pages:</li>'.implode('',$links).'</ul>';
			}
		}
		return $nav;
	}
}
