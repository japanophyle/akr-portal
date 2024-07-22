<?php

class Generic{
	private $DATA=array();
	
	function __construct($data=false){
		$this->init($data);
	}
	
	protected function init($data=false){
		if(is_array($data)) $this->DATA=$data;
	}
	
	function get($what=false,$var=false){
		switch($what){
			case 'all':
				return $this->DATA;
				break;
			default:
				$chk=issetCheck($this->DATA,$what);
				if($var && $chk) $chk=issetCheck($chk,$var);
				return $chk;
		}				
	}
	
	function set($what=false,$key=false,$var=false){
		if($what && $what!==''){
			if($key && $key!==''){
				$this->DATA[$what][$key]=$var;
				return true;
			}else{
				$this->DATA[$what]=$var;
				return true;
			}
		}
		return false;
	}
	
	function add($what=false,$key=false,$var=false){
		if($what && $what!==''){
			$this->DATA[$what][$key]=$var;
			return true;
		}
		return false;
	}
}
