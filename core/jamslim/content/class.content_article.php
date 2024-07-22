<?php
class content_article{
	private $SLIM;
	private $SLUG;
	public $DATA;
	private $ID;
	private $ADMIN;
	
	private $AJAX;
	private $PRODUCT_PARENT; //from config PARENTS
	private $BLOG_PARENT; //from config PARENTS
	private $TESTIMONIAL_PARENT; //from config PARENTS
	private $ARTICLE_DATA=array();//is this stil used??
	
	function __construct($slim=false,$slug=false){
		if(!$slim) throw new Exception('no slim object!!');
		if(!$slug){
			$slug=$slim->router->get('page_slug');
		}
		if(!$slug) throw new Exception('no page slug found!!');
		preME($slug,4);
		$this->SLIM=$slim;
		$this->SLUG=$slug;
		$this->AJAX=$slim->router->get('ajax');
		$this->ADMIN=($slim->user['access']>=3)?true:false;
		$parents=$slim->config['PARENTS'];
		if($parents){
			foreach($parents as $i=>$v) $this->$i=$v;
		}
	}
	//common methods
	function render($args=false){
		$this->setArgs($args);
		return $this->renderContent();
	}
	
	private function setArgs($args=false){
		if(!$this->DATA) $this->getData();
		if(!$this->ID) $this->ID=$this->DATA['ItemID'];
		$this->BOX_TYPE=issetCheck($args,'box_type');
	}
	
	// custom methods
    function renderContent(){
        $out = ' ';
        switch($this->SLUG){
			case 'book':
				$out=$this->renderBook();
				break;
			default:
				$out=$this->renderPage();
		}	
        return $out;
	}
	
	private function renderPage(){
		$_tpl=($this->SLUG==='home')?'page-1col':'page';
		$show_sidebar=(int)$this->SLIM->PageContent->findMeta('page_sidebar');
		$src=$this->SLIM->PageContent->findMeta('page_main_image');
		if($src) $src='<div class="main-image css-slide" style="background-image:url('.$src.');"></div>';
		if(!$show_sidebar) $_tpl='page-1col';
		$tpl=file_get_contents(TEMPLATES.'parts/tpl.'.$_tpl.'.html');
		if($this->SLUG==='home') $tpl='[::static_hero::]'.$tpl;
		$parts=array('title'=>'','sub_title'=>'','content'=>'','sidebar'=>'');
		$edit=$this->editLink();
		$parts['title']=($this->SLUG==='home')?$edit:fixHTML(issetCheck($this->DATA,'ItemTitle')).' '.$edit;
		$parts['content']=html_entity_decode(fixHTML(issetCheck($this->DATA,'ItemContent')));
		$parts['content'].=$this->SLIM->PageContent->get('child_links');
		$parts['sidebar']=($show_sidebar)?$this->SLIM->PageContent->get('sidebar'):'';
		$parts['main_image']=$src;
		return replaceMe($parts,$tpl);
	}

   function getArticleList($title = 'Article Links',$box_type=false) {
	    if(!$this->ARTICLE_DATA) return false;
		$box_type=(5==$box_type)?'infobox':false;
        $list = false;
        if(!$title) $title='Article Links';
        $navdata = $this->SLIM->DataBank->get('navigation',false); 
        foreach ($this->ARTICLE_DATA as $rec) {
            if ($rec['ItemStatus'] == 'published'){
				$details = '<strong>' . $rec['ItemTitle'] . '</strong>';
				$blurb=html_entity_decode(fixHTML($rec['ItemContent']));
				//strip shortcodes
				$pattern = '/\[::(\S*)::\]/';
				preg_match_all($pattern, $blurb, $strip);
				foreach($strip as $i=>$v) $blurb=str_replace($v,'',$blurb);
				$blurb=limitText($blurb, 60, true);
				$url = array('parent' => $this->SLUG, 'page' => $rec['ItemSlug'], 'base' => $this->MAINPAGE, 'lead' => 1);
				$ref = formatURL($url);
				$meta=$this->SLIM->DataBank->get('meta_by_id',$id);
				$src= $this->SLIM->PageContent->getImageSRC($meta['rticle_main_image']);
				 if(strpos($src['image'],'tbs_logo_badge')!==false){
					$src= $this->SLIM->PageContent->getImageSRC($meta['page_main_image']);
				}
				$img=(strpos($src['image'],'tbs_logo_badge')!==false)?false:$src['image'];
				$more=($box_type=='infobox')?'':'<br/><br/><span class="alert-box message blue_grad radius float-right">Click for more details</span>';
				$link_args=array(
					'title'=>$rec['ItemTitle'].'<br/><br/>',
					'text'=>$blurb.$more,
					'src'=> $img,
					'url'=> $ref,
					'link_type'=>2
				);
				$content=($box_type=='infobox')?makeInfobox($link_args):makeHotBox($link_args);
                $list[$rec['ItemID']] = array('ref' => $ref, 'content' => $content);
            }
        }
        $parent=0;
        if ($list){
			$m=($parent > 0)?$navdata[$parent][$this->ITEM_ID]['subs']:$navdata[$this->ITEM_ID]['subs'];
           foreach ($m as $id => $vd){// makes list match navigation
                if ($vd['parent'] == $this->ITEM_ID && $list[$id]) $sorted[$id] = $list[$id];
            }
            $sorted['icon'] = false;
            $sorted['listclass']='item';
            $sorted['linkclass']='row';
        }
        return ($box_type=='infobox')?makeInfobox_list($sorted):makeHotlist($sorted);
    }

	private function getData(){
		$rez=$this->SLIM->PageContent->get('data');
		if($rez){
			$this->DATA=$rez;
			$this->ID=$this->DATA['ItemID'];
		}else{
			throw new Exception(__CLASS__.': no page data found for "'.$this->SLUG.'"');
		}		
	}
	private function editLink(){
		$edit=false;
		if($this->ADMIN && $this->DATA){
			$type=($this->DATA['ItemType']==='article')?'page':$this->DATA['ItemType'];
			$edit='<button type="button" class="button small button-purple gotoME" data-ref="'.URL.'admin/'.$type.'/edit/'.$this->DATA['ItemID'].'" title="edit this"><i class="fi-pencil"></i></button>';
		}
		return $edit;		
	}	
}
