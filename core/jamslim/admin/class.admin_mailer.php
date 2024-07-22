<?php
class admin_mailer {

	private $SLIM;
	private $DATA;
	private $OUTPUT;
	private $PERMLINK;
	private $PERMBACK;
	private $ID;
	private $LIB;
	private $LIST_COUNT=0;
	private $OPTIONS;
		
	public $AJAX;
	public $REQUEST;
	public $USER;
	public $METHOD;
	public $SECTION;
	public $ACTION;
	public $PLUG;
	public $ADMIN;
	public $LEADER;
	public $ROUTE;	
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->LIB=$slim->Emailer;
	}
	function Process(){
		$this->init();
		if($this->METHOD==='POST'){
			$this->LIB->PostMan($this->REQUEST);
		}
		$this->OUTPUT['content']=$this->LIB->render($this->ACTION);
		$this->OUTPUT['title']='Mailer';
		return $this->renderOutput();
	}
	private function renderOutput(){
		$keys=['title','content','icon','menu'];
		if(is_array($this->OUTPUT)){
			$out=$this->OUTPUT;
			foreach($keys as $k){
				if(!isset($this->OUTPUT[$k])){
					switch($k){
						case 'icon':
							$v='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
							break;
						case 'menu':
							$v=['right'=>$this->renderContextMenu()];
							break;
						default:
							$v='';
					}
					$out[$k]=$v;
				}
			}
		}else if(!$this->OUTPUT||$this->OUTPUT===''){
			$out=msgHandler('Sorry, no output was generated...',false,false);
		}else{
			$out=$this->OUTPUT;
		}
		if($this->AJAX){
			if(is_array($out)){
				jsonResponse($out);
			}else{
				echo $out;
			}
			die;
		}
		return $out;
	}
	private function renderContextMenu(){
		$but['search']='<button class="button small button-navy loadME" title="search for members" data-ref="'.$this->PERMLINK.'search_members" type="button"><i class="fi-torso"></i> Search</button>';
		$but['quick']='<button class="button small button-dark-blue loadME" title="save changes" data-ref="'.$this->PERMLINK.'quick_lists" type="button"><i class="fi-torso"></i> Quick Lists</button>';
		$but['help']='<button class="small button button-dark-green" data-open="writer_help"><i class="fi-first-aid"></i> Email Help</button>';
		$b=[];$out='';
		switch($this->ACTION){
			default:
				$b=['search'];
				if(!$this->LEADER) $b[]='quick';
		}
		if($b){
			foreach($b as $i){
				$out.='<li>'.$but[$i].'</li>';
			}
		}
		return $out;
	}
	private function init(){
		$this->AJAX=$this->SLIM->router->get('ajax');
		$this->PERMBACK=URL.'admin/';
		$this->PERMLINK=URL.'admin/mailer/';
		$this->LIB->PERMLINK=$this->PERMLINK;
		$this->LIB->PERMBACK=$this->PERMBACK;
		if(!$this->METHOD){
			$this->METHOD=$this->SLIM->router->get('method');
			if(!$this->METHOD) $this->METHOD='GET';
			$this->REQUEST=($this->METHOD==='POST')?$this->SLIM->router->get('post'):$this->SLIM->router->get('get');
			$this->ROUTE=$this->SLIM->router->get('route');
			$this->USER=$this->SLIM->user;			
			$this->PLUG=issetCheck($this->SLIM->AdminPlugins,'mailer');
		}
		if($this->METHOD==='POST'){
			$this->ACTION=issetCheck($this->REQUEST,'action');
			$this->ID=issetCheck($this->REQUEST,'id');
			if($this->ID==='new') $this->ACTION='new';
		}else{
			$this->ACTION=issetCheck($this->ROUTE,2,'messages');
			$this->ID=($this->ACTION==='new')?'new':issetCheck($this->ROUTE,3);
		}
		if($this->METHOD!=='POST') $this->initData();
	}
	private function initData(){
		
	}
}
