<?php
class content_sidebar{
	private $SLIM;
	private $SLUG;
	public $DATA=false;//external data
	
	private $AJAX;
	private $ARGS;
	private $SIDEBAR_DATA=array();
	private $SIDEBAR=0;
	private $SIDEBAR_ID=0;
	private $TITLE;
	private $ID=0;
	private $LAYOUT;
	
	function __construct($slim=null,$slug=false){
		if(!$slim) throw new Exception('no slim object!!');
		if(!$slug){
			$slug=$slim->router->get('page_slug');
		}
		if(!$slug) throw new Exception('no page slug found!!');
		$this->SLIM=$slim;
		$this->SLUG=$slug;
		$this->AJAX=$slim->router->get('ajax');
		$this->getData();
	}
	//common methods
	function render($args=false){
		if($this->SLUG==='login') return ' ';
		$this->ARGS=$args;
		$this->setArgs();
		$o=$this->renderContent();
		if(!$o||$o==='') $o=' ';
		return $o;
	}
	
	private function setArgs(){
		if($this->ARGS){
			$this->ID=(int)issetCheck($this->ARGS,'id');//main page id
			$this->SIDEBAR=issetCheck($this->ARGS,'sidebar');
			$this->SIDEBAR_ID=($this->SIDEBAR)?issetCheck($this->SIDEBAR,'id'):0;
			$this->TITLE=issetCheck($this->ARGS,'title');
			$this->LAYOUT=issetCheck($this->ARGS,'layout');
		}else if($this->DATA){
			$this->ID=(int)issetCheck($this->DATA,'itm_ID');//main page id
		}
	}
	
	// custom methods
    function renderContent() {
		if($this->AJAX) return false;
		if(!$this->SLUG || $this->SLUG==='checkout' || $this->SLUG==='home') return false;
		$show_sidebar=$this->SLIM->PageContent->findMeta('page_sidebar');
		if($show_sidebar===false) $show_sidebar=1;
		if(!(int)$show_sidebar) return '';
		$out=$this->site_search();
		$out.=$this->news_ticker(); 
        $out.=$this->sidebar_contents();
         if (issetVar($out)) {
            $out = '<div class="sidebar">'. $out . '</div>';
            //add extra content to side bar
        }
		return $out;
	}
	private function site_search(){
		return false;
	}
	private function news_ticker(){
		return false;
		$tick = new content_ticker($this->SLIM);
		return $tick->render(); 
	}
	private function sidebar_contents(){
		$out=$page=$public=false;
		$admin=($this->SLIM->user['access']>=3)?true:false;
		$admin_url=URL.'admin/sidebar/edit/';
		foreach($this->SIDEBAR_DATA as $i=>$v){
			$img_src=issetCheck($v,'main_image');
			$edit=false;
			$edit=($admin)?' <span class="link-dark-green gotoME" data-ref="'.$admin_url.$i.'" title="edit this"><i class="fi-pencil"></i></span>':false;
			if($v['itm_Parent']==0){
				$public.=$this->renderItem($v['itm_Title'].$edit,$v['itm_Content'],$img_src);
			}else if($v['itm_Parent']==$this->ID){
				$page.=$this->renderItem($v['itm_Title'].$edit,$v['itm_Content'],$img_src);
			}
		}
		$out=$page.$public;
		return $out;
	}
	private function renderItem($title,$content,$image=false){
		$content=html_entity_decode($content);
		if(strpos($content,'[::booklist(')!==false){
			$out=fixHTML($content);
		}else{
			$img=($image)?'<div class="sidebar-main-image"><img src="'.$image.'"/></div>':'';					
			$out='<div class="sidebar-item"><div class="h4 text-gbm-blue">'.fixHTML($title).'</div>'.$img.fixHTML($content).'</div>';
		}
		return $out;	
	}
	
	private function recent_books(){
		//used for testing
		$book = new Booklist_widget($this->SLIM);
		$a=array('name'=>'recent','sidebar'=>1);
        $out=$book->get('list',$a);
        return $out;
	}
	
	private function getData(){
		$rez=$this->SLIM->DataBank->get('sidebar');
		if($rez){
			$keys=array_keys($rez);
			$meta=$this->SLIM->db->myp_meta->where('meta_item_id',$keys);
			if($meta=renderResultsORM($meta)){
				foreach($meta as $m){
					if($m['meta_key']==='sidebar_main_image'){
						if($m['meta_value'] && $m['meta_value']!==''){
							$rez[$m['meta_item_id']]['main_image']=$m['meta_value'];
						}
					}
				}
			}
			$this->SIDEBAR_DATA=$rez;
		}		
	}
}
