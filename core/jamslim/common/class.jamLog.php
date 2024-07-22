<?php

class jamLog{
    private $LOG=[];
    private $FILENAME;
    private $APPEND;
    
    function __construct($filename=null,$append=false){
		if(!$filename) throw new Exception('log filename required!');
		$this->FILENAME=$filename;
		$this->APPEND=$append;
    }
    public function getLog(){
		return $this->LOG;
	}
	public function dumpLog(){
		if(!$this->LOG) return '';
		return '<ul><li>'.implode('</li><li>',$this->LOG).'</li></ul>';
	}
    public function log($text=null){
		if($text){
			if(is_array($text)){
				$text=json_encode($text);
			}else if(!is_string($text)){
				return;
			}
		}
		$text=trim($text);
		if($text!==''){
			$this->LOG[]='['.date('m/d/Y g:i:s A').'] - '.$text;
		}
    }
    public function save(){
		if(!$this->LOG) return;
		$log=implode("\n",$this->LOG);
		if($this->APPEND){
			$chk=file_put_contents($this->FILENAME,$log,FILE_APPEND | LOCK_EX);
		}else{
			$chk=file_put_contents($this->FILENAME,$log);
		}
		if($chk) $this->LOG=[];
		return $chk;   
    }
}
