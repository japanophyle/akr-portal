<?php
require_once CORE.'vendor/simple_html_dom.php';
class dom_query{
	private $SLIM;
	private $SOURCE;
	private $SOURCE_TYPE='file';
	private $RESULTS;
	private $OUTPUT;
	private $DQ;
	private $OTYPES=['outer','inner','plain','data'];
	
	function __construct($str=false,$type=false){
		if($str) $this->init($str,$type);	
	}
	
	function init($str=false,$type=false){
		if($str && $str!=='') $this->SOURCE=$str;
		$this->SOURCE_TYPE=($type==='string')?'string':'file';
		if($this->SOURCE){				
			$this->DQ=($this->SOURCE_TYPE==='string')?str_get_html($str):file_get_html($str);
		}
	}	
	function find($what=false){
		$this->RESULTS=false;
		if(!$this->DQ) return false;
		$this->RESULTS=$this->DQ->find($what);
	}
	function getData($args=[]){
		$out=[];
		if($this->RESULTS){
			if(is_array($this->RESULTS)){
				foreach($this->RESULTS  as $i=>$v){
					foreach($args as $find=>$what){
						$f=$v->find($what[0],0);
						$t=issetCheck($what,1,'plaintext');
						$out[$i][$find]=($f)?trim($f->$t):'';
					}
				}
			}else{
				foreach($args as $find=>$what){
					$f=$this->RESULTS->find($what[0],0);
					$t=issetCheck($what,1,'plaintext');
					$out[][$find]=($f)?trim($f->$t):'';
				}
			}
		}else{
			foreach($args as $find=>$what){
				$this->find($what[0]);
				if($this->RESULTS){
					foreach($this->RESULTS  as $i=>$v){
						$t=issetCheck($what,1,'plaintext');
						$out[$i][$find]=trim($v->$t);
					}
				}
			}				
		}
		return $out;
	}
	function get($attr='outer'){
		if(!$this->RESULTS) return false;
		$out=[];
		if(!in_array($attr,$this->OTYPES)) $attr='outer';
		$attr.='text';
		if(is_array($this->RESULTS)){
			foreach($this->RESULTS  as $i=>$v){
				$out[]=$v->$attr;
			}
		}else{
			$out[]=$this->RESULTS->$attr;
		}
		return $out;
	}
	
	function set($what=false,$vars=[]){
		if(!$this->DQ) return false;
		if($f=$this->DQ->find($what)){
			foreach($vars as $i=>$v){
				switch($i){
					case 'class': case 'data': case 'href': case 'src': case 'id': 
						$f->$i=$v;
						break;
				}
			}
		}		
	}
	function toString(){
		return $this->save();
	}
	function save(){
		return $this->RESULTS->save();
	}
}
