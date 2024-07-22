<?php

abstract class data_object {
	var $SLIM;
	var $PARTS=array();
	var $DATA=false;
	var $META=false;
	var $TYPE='page';
	var $IMAGE=false;
	var $URL=false;
	var $TBL=false;//the main sql table class
	var $TEMP=false;
	var $SHORT_LIMIT=25;//words in short text
	var $DEFAULT_IMAGE='gfx/noimage.png';
	var $USE_DEFAULT_IMAGE=false;
	var $INIT=false;
	var $READY=false;
	var $MSG;
	var $AJAX=false;
	var $ROUTE=false;
	var $LABELS;
	var $MODE='view';//or save
	var $FIELDS;
	
	function __construct($args=false){
		$this->init($args);		
	}
	
	private function init($args){
		if(is_array($args)){
			$slim=issetCheck($args,'SLIM');
			if(!$slim){
				global $container;
				$slim=$container;
			}else{
				unset($args['SLIM']);
			}
			if($t=issetCheck($args,'table_class')){
				$this->TBL=$slim->db->$t;
				$this->FIELDS=$slim->ezPDO->getFields($t);
				foreach($this->FIELDS as $k) {
					$this->PARTS[$k['name']]=false;
					$this->LABELS[$k['name']]=ucME($k['label']);
				}
			}
			$this->AJAX=$slim->router->get('ajax');
			$this->ROUTE=$slim->router->get('route');
			foreach($args as $i=>$v){
				$k=strtoupper($i);
				$this->$k=$v;
			}
			$this->INIT=true;
			$this->SLIM=$slim;
		}else{
			$this->MSG='Sorry, the int data was invalid';
			$this->INIT=false;
		}
				
	}	
	
	public function get($part=false,$var=false){
		if(!$part){
			return $this->PARTS;
		}else if($part==='keys'){
			return array_keys($this->PARTS);
		}else if($part==='meta'){
			return issetCheck($this->META,$var);
		}else if($part==='fieldlist'){
			return $this->FIELDS;
		}else if($part==='labels'){
			return $this->LABELS;
		}else{
			return issetCheck($this->PARTS,$part);
		}		
	}	
	
	public function set($part=false,$value=false){
		if($part){
			$this->PARTS[$part]=$value;
			return true;
		}else{
			return false;
		}
	}
	
	// setImage can be used for most items main image
	protected function setImage($i=false){
		$IMG=new image;
		$IMG->DEFAULT_IMAGE=($this->USE_DEFAULT_IMAGE)?$this->DEFAULT_IMAGE:'';
		switch($this->PARTS['TYPE']){
			case 'article':
				$ikey='rticle_main_image';
				break;
			default:
				$ikey=$this->PARTS['TYPE'].'_main_image';
		}
		$img=issetCheck($this->META,$ikey);
 		$args['src']=$img;
		$src=$IMG->_get('getImageSRC',$args);
		$credit=$IMG->_get('getImageCredit',$src);
		if(is_array($credit)){
			$src['title']=$credit['title'];
			$src['credit']=$credit['html'];
		}else{
			$src['title']=$src['credit']=false;
		}
		if(!isset($src['id']))$src['id']=0;
		return $src;
	}
	
	//the following functions should be in the child class
	abstract function load();
	abstract function setShort();
	abstract function renderParts();
	
}
