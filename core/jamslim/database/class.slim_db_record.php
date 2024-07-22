<?php

class slim_db_record{

	private $SAVE=array(); //data preped to be saved
	private $DISPLAY=array(); //data preped for display
	private $ID=0;
	private $ACTION=false;
	private $OUTPUT;
	private $DATA=array(); //raw database record;
	private $FIELDS;
	private $PRIMARY='id';
	private $TABLE;//table name
	private $DBO;
	
	function __construct($args){
		foreach($args as $i=>$v){
			$k=strtoupper($i);
			if(property_exists($this,$k)) $this->$k=$v;
		}
		if($this->ID) $this->setRecord();
	}	
	function get($what=false){
		switch($what){
			case 'fields':
				return $this->FIELDS;
				break;
			case 'data':
				return $this->DATA;
				break;
			case 'save':
				return $this->SAVE;
				break;
			case 'display':
				return $this->DISPLAY;
				break;
			default:
				if($what && $what!==''){
					return issetCheck($this->DATA,$what);
				}
		}
		return false;
	}	
	function set($what=false,$var=false){
		//only for data
		if($what && $what!==''){
			if(issetCheck($this->FIELDS,$what)){
				$this->DATA[$what]=$var;
				return true;
			}
		}
		return false;
	}
	function prep($what=false,$var=false){
		//prep for saving
		if($what && $what!==''){
			if(issetCheck($this->FIELDS,$what)){
				$this->SAVE[$what]=$var;
				return true;
			}
		}
		return false;
	}	
	private function setRecord(){
		if(!$this->DBO) return [];
		if(!$this->ID) return [];
		$rec=$this->DBO->where($this->PRIMARY,$this->ID);
		$rec=renderResultsORM($rec,$this->PRIMARY);
		$this->DATA=$rec;
		$this->DISPLAY=current($rec);
	}
}
