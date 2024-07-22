<?php
class admin_lang {

	private $SLIM;
	private $DATA;
	private $LIB;
	private $OUTPUT;
	private $PERMLINK;
	private $PERMBACK;
	private $ID;
	private $CATEGORIES;
	private $LIST_COUNT=0;
	private $PHRASE;
		
	public $AJAX;
	public $REQUEST;
	public $USER;
	public $METHOD;
	public $SECTION;
	public $ACTION;
	public $PLUG;
	public $ROUTE;
	public $ADMIN;
	public $LEADER;
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->LIB=$slim->language;
		$this->PERMBACK=URL.'admin/';
		$this->PERMLINK=$this->PERMBACK.'lang/';
		$this->ROUTE=$slim->router->get('route');
		$this->ACTION=issetCheck($this->ROUTE,2);
		$this->ID=issetCheck($this->ROUTE,3);
		$this->PHRASE=issetCheck($this->ROUTE,4);
	}
	function Process(){
		if($this->METHOD==='POST'){
			$this->doPost();
		}
		switch($this->ACTION){
			case 'edit_lang':case 'new_lang':
				$this->renderEditItem();
				break;
			case 'sets':
				$this->renderSetNav();
				break;
			case 'csv_update':
				$this->renderCSV();
				break;
			default:
				$this->renderListItems();
				break;				
		}
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
		$but['back']='<button class="button small button-dark-purple backME" title="back to list" type="button"><i class="fi-arrow-left"></i> Back</button>';
		$but['new']='<button class="button small button-dark-blue loadME" title="add a new '.$this->ID.'" data-ref="'.$this->PERMLINK.'new_lang/'.$this->ID.'" type="button"><i class="fi-plus"></i> New</button>';
		$but['csv']='<button class="button small button-aqua text-black loadME" title="update '.$this->ID.' from csv" data-size="small" data-ref="'.$this->PERMLINK.'csv_update/'.$this->ID.'" type="button"><i class="fi-refresh"></i> Update</button>';
		$but['sets']='<button class="button small button-navy loadME" title="translaton sets" data-ref="'.$this->PERMLINK.'sets" type="button"><i class="fi-list"></i> Other Tranlations</button>';
		$but['cats']='<button class="button small button-navy loadME" title="products by category" data-ref="'.$this->PERMLINK.'category/" type="button"><i class="fi-list"></i> By Category</button>';
		$but['groups']='<button class="button small button-navy loadME" title="products by group" data-ref="'.$this->PERMLINK.'group/menu/" type="button"><i class="fi-list"></i> Other Groups</button>';
		$but['edit']='<button class="button small button-dark-blue loadME" title="edit payment record" data-ref="'.$this->PERMLINK.'edit_payment/'.$this->ID.'/list" type="button"><i class="fi-pencil"></i> Edit</button>';
		$but['event']='<button class="button small button-dark-blue loadME" title="edit event" data-size="large" data-ref="'.$this->PERMLINK.'edit/'.$this->ID.'" type="button"><i class="fi-calendar"></i> Event #'.$this->ID.'</button>';
		$but['download']='<button class="button small button-purple loadME" title="download" data-ref="'.$this->PERMLINK.'rollcall/'.$this->ID.'/download" type="button"><i class="fi-download"></i> Download</button>';
		$b=[];$out='';
		switch($this->ACTION){
			case 'edit':
				$b=['back','new'];
				break;
			default:
				$b=['sets','new'];
		}
		if($b){
			foreach($b as $i){
				$out.='<li>'.$but[$i].'</li>';
			}
		}
		return $out;
	}
	private function renderSetNav(){
		$subtitle=ucME($this->ID);
		$control_button='<button class="button button-navy gotoME" data-ref="'.$this->PERMLINK.'lists/{list}" title="{switch_title}"><i class="fi-database"></i> {switch_label}</button>';
		$lists=array_keys($this->LIB->get('_DEFREC'));
		$content='';
		$title='Tranlation Sets';
		foreach($lists as $i){
			$but['color']='navy';
			$but['icon']=$this->PLUG['icon'];
			$but['href']=$this->PERMLINK.'list/'.$i;
			$but['caption']=ucME($i);
			$but['title']='manage the '.$i.' translations';
			$content.=$this->SLIM->zurb->adminButton($but);
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;
		if($this->AJAX){
			$this->OUTPUT['content']=renderCard_active($title,$content,$this->SLIM->closer);
		}
	}
	private function renderListItems(){
		$set=($this->ID)?$this->ID:'words';
		$rt=[false,'lists',$set,$this->PHRASE];
		$rez=$this->LIB->render($rt,$this->AJAX);
		$this->OUTPUT['title']=$rez['title'];
		$this->OUTPUT['content']=$rez['content'];
		if($this->AJAX){
			echo renderCard_active($this->OUTPUT['title'],$this->OUTPUT['content'],$this->SLIM->closer);
			echo '<script>$(".reveal .modal-body").foundation();</script>';
			die;
		}	
	}
	private function renderEditItem(){
		$set=($this->ID)?$this->ID:'words';
		$rt=[false,$this->ACTION,$set,$this->PHRASE];
		$rez=$this->LIB->render($rt,$this->AJAX);
		$this->OUTPUT['title']=$rez['title'];
		$this->OUTPUT['content']=$rez['content'];
		if($this->AJAX){
			echo renderCard_active($this->OUTPUT['title'],$this->OUTPUT['content'],$this->SLIM->closer);
			echo '<script>$(".reveal .modal-body").foundation();</script>';
			die;
		}	
	}
	private function renderCSV(){
		$set=($this->ID)?$this->ID:'words';
		$rt=[false,'csv_update',$set,$this->PHRASE];
		$rez=$this->LIB->render($rt,$this->AJAX);
		preME($rez,2);
	}
	private function doPost(){
		$rsp=$this->LIB->Postman($this->REQUEST);
		$url=$this->PERMLINK;
		if($this->ACTION){
			$url.=$this->ACTION.'/';
			if($this->ID)$url.=$this->ID;
		}
		if($this->AJAX){
			echo jsonResponse($rsp);
			die;
		}
		setSystemResponse($url,$rsp['message']);
		die($rsp['message']);	
	}
}
