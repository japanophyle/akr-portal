<?php

class slim_db_common{
	var $SLIM;
	var $ID=0;
	var $RESPONSE=['status'=>500,'data'=>[]];
	var $DB;
	var $EZPDO;
	var $TABLE;
	var $FIELDS;
	var $PRIMARY;
	var $OPTIONS;
	var $ERR=[];
	var $DOJO_LOCK=[];
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->EZPDO=$slim->ezPDO;
		$this->DB=$slim->db;
		$this->setFields();
		$dl=issetCheck($slim->user,'dojo_lock');
		if($dl) foreach($dl as $d) $this->DOJO_LOCK[]=$d;
	}
	function init(){
		$this->RESPONSE=['status'=>500,'data'=>[]];
		$this->ERR=[];
	}
	function setFields(){
		$this->FIELDS=$this->EZPDO->getFields($this->TABLE);
		$this->PRIMARY=$this->EZPDO->getPrimary($this->TABLE);
	}
	function validField($fld=''){
		$chk=issetCheck($this->FIELDS,$fld);
		if($chk) return true;
		return false;
	}
	function validateData($data){
		$valid=[];
		$isNull=function($s){$_s=strtolower(trim($s));return ($_s==='null')?NULL:$s;};
		foreach($data as $i=>$v){
			if($fop=issetCheck($this->FIELDS,$i)){
				switch($fop['type']){
					case 'int':
					case 'tinyint':
						$valid[$i]=(int)$v;
						break;
					case 'decimal':
						if(is_float($v)){
							$valid[$i]=number_format($v,2);
						}else if(is_numeric($v)){
							$valid[$i]=number_format((float)$v,2);
						}
						break;
					case 'varchar':
						if(is_string($v)) $valid[$i]=$isNull($v);
						break;
					case 'datetime':
						$fmt=($fop['name']==='LastAction')?'Y-m-d H:i:s':'Y-m-d 00:00:00';
						$valid[$i]=validDate($v,$fmt);
						break;
					default:
						$valid[$i]=$isNull($v);
				}
			}
		}
		return $valid;		
	}
	public function getDojoName($ref){
		$o=$this->SLIM->Options->get('dojos_name',$ref);
		if(is_array($o)){
			return issetCheck($o,$ref);
		}
		return $o;
	}
}
