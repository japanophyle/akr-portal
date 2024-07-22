<?php

class content_adminbar{
	
	private $SLIM;
	private $MODAL;
	private $PLUGS;
	private $ACCESS;
	private $COLORS=['dark-purple'=>'MemberME','olive'=>'Public Site','maroon'=>'Super'];
	private $ADMIN;
	private $LEADER;
	private $LANGUAGE;
	
	function __construct($slim){
		if(!$slim) throw new Exception('no slim object!!');
		$this->SLIM=$slim;
		$this->PLUGS=$slim->AdminPlugins;
		$this->ACCESS=$slim->user['access'];
		$this->ADMIN=($this->ACCESS>=$slim->AdminLevel)?true:false;
		$this->LANGUAGE=$this->SLIM->language->get('_POWER');
		if(!$this->ADMIN && $this->ACCESS==$slim->LeaderLevel) $this->LEADER=true;	
	}
	
	function render($modal=true){
		$this->MODAL=$modal;
		$bar=($this->ADMIN||$this->LEADER)?$this->renderAdminBar():'';
		return $bar;
	}

	private function renderAdminBar(){
		$bar=false;
		if($this->ADMIN||$this->LEADER){
			$menu=$this->renderMenu();
			$title='Admin. Menu';
			if($this->MODAL){
				$bar=renderCard_active($title,$menu,$this->SLIM->closer);
				$anim='data-animation-in="slide-in-up" data-animation-out="slide-out-down"';
				$bar='<div id="admin-bar" class="reveal medium" data-reveal '.$anim.'>'.$bar.'</div>';
			}else{
				$bar=renderCard_active($title,$menu);
			}
		}
		return $bar;
	}
	private function renderMenu(){
		$menus=[];
		foreach($this->PLUGS as $i=>$v){
			if($this->ACCESS>=$v['access']){
				$color=issetCheck($v,'color','dark-purple');
				if($i==='lang'){
					if($this->ACCESS < $this->SLIM->SuperLevel && !$this->LANGUAGE) continue;
				}else if(in_array($i,['page','media'])){
					if($this->ACCESS==$this->SLIM->LeaderLevel){
						$chk=hasAccess($this->SLIM->user,'pages','update');
						if(!$chk) continue;
					}
				}
				if($v['menu']){
					if(!isset($menus[$color]))$menus[$color]='';
					$url=(strpos($v['url'],URL)!==false)?$v['url']:URL.'admin/'.$v['url'];
					$menus[$color].='<button class="button button-'.$color.' gotoME" data-ref="'.$url.'"><i class="fi-'.$v['icon'].' icon-x3"></i><span class="button-text">'.$v['label'].'</span></button>';
				}
			}
		}
		if($menus) $menus=$this->renderSets($menus);
		return $menus;
	}
	private function renderSets($data){
		$out='';
		foreach($data as $i=>$v){
		   $out.='<div class="cell panel"><strong>'.$this->COLORS[$i].'</strong><div class="button-group stacked expanded ui-square-2">'.$v.'</div></div>';
		}
		if($this->ADMIN && $out!=='') $out='<div class="grid-x small-up-2">'.$out.'</div>';
		return $out;
	}
}
