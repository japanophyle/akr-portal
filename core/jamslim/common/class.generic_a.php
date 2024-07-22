<?php

Class generic_a {

	protected $PARTS = array();
	public function __construct($init=false){
		$this->init($init);
	}
	public function init($parts=false){
		if(is_array($parts)){
			$this->PARTS=$parts;
			return true;
		}
		return false;		
	}
	public function set($what=false, $vars=false,$key=false){
		if($what){
			if($key){
				if(!is_string($key))return false;
				if(!isset($this->PARTS[$what])){
					$this->PARTS[$what]=[];
				}else if(!is_array($this->PARTS[$what])){
					$this->PARTS[$what]=[];
				}
				
				$this->PARTS[$what][$key] = $vars;
			}else{
				$this->PARTS[$what] = $vars;
			}
			return true;
		}
		return false;		
	}
	
	public function get($what=false, $key=false){
		if($what){
			if($key){
				return issetCheck($this->PARTS[$what],$key);
			}else{
				return issetCheck($this->PARTS,$what);
			}
		}else{
			return $this->PARTS;
		}
	}

	public function add($what=false,$vars=false,$key=false){
		if($what && $vars){
			if(isset($this->PARTS[$what])){//exists
				if($key){//add to array
					if(is_array($this->PARTS[$what])){
						$this->PARTS[$what][$key]=$vars;
						return true;
					}
				}else{//add value
					$this->PARTS[$what]=$vars;	
				}
			}else{//not exists
				$this->PARTS[$what]=($key)?array($key=>$vars):$vars;
			}
		}
	}
	 
	public function remove($what=false,$key=false){
		if($what && $key){
			if(is_array($this->PARTS[$what])){
				if(isset($this->PARTS[$what][$key])){
					unset($this->PARTS[$what][$key]);
					return true;
				}
			}
		}
		return false;
	}
	
	public function merge($arr=false){
		if(is_array($arr)){
			foreach($arr as $i=>$v){
				$chk=issetCheck($this->PARTS,$i);
				if(!$chk){
					$chk=$v;
				}elseif(is_array($chk)){
					if(is_array($v)){
						$chk+=$v;
					}else{
						$chk[]=$v;
					}
				}else{//assume string
					if(is_array($v)){
						preME(array($chk,$v),2);
						$chk.=implode(' ',$v);
					}else{
						$chk.=$v;
					}
				}			
				$this->PARTS[$i]=$chk;
			}
		}
		return false;
	}

}
