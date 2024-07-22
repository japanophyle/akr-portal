<?php

class jamExport {
	var $DATA=[];
	var $FILENAME='data_file';
	var $EXPORT_TYPE='excel';
	var $DOWNLOAD=true;
	var $OUTPUT;
	var $CONTENT_TYPE='application/vnd.ms-excel';
	var $EXT='.xls';
	var $CLEAN_NULLS=true;	
	
	function Process($args){
		throw new Exception('jamExport Depricated. use slimDownload.');
		foreach($args as $i=>$v){
			$k=strtoupper($i);
			if(isset($this->$k)) $this->$k=$v;
		}
		if(!is_array($this->DATA)) die('Sorry, there is no data to export or it is invalid');
		if($this->CLEAN_NULLS) $this->replaceNulls();
		switch($this->EXPORT_TYPE){
			case 'csv':
				$this->CONTENT_TYPE='text/csv';
				$this->formatData_csv();
				$this->EXT='.csv';
				break;
			case 'excel':
				$this->CONTENT_TYPE='application/vnd.ms-excel';
				$this->formatData_excel();
				$this->EXT='.xls';
				break;
		}
		if($this->DOWNLOAD){
			$this->doDownload();
		}else{			
			$this->doSave();
		}
	}
	//csv   
	function cleanData_csv(&$str) {
		if(is_array($str)) $str=implode('||',$str);
		if(is_null($str)) $str='';
		$str=str_replace('&pound;','',$str);
		$str=str_replace('&nbsp;',' ',$str);
		if($str == 't') $str = 'TRUE';
		if($str == 'f') $str = 'FALSE';
		if(preg_match("/^0/", $str) || preg_match("/^\+?\d{8,}$/", $str) || preg_match("/^\d{4}.\d{1,2}.\d{1,2}/", $str)) {
			$str = "'$str";
		}
		if(strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"';
		if(strpos($str,',')!=false) $str='"'.$str.'"';
	}
	function formatData_csv(){
		$fieldnames=$rows=false;
		foreach($this->DATA as $row) { 
			if(!$fieldnames) $fieldnames='"'.implode('","', array_keys($row)) . "\"\r\n";
			array_walk($row, array($this,'cleanData_csv')); 
			$rows.=implode(",", array_values($row)) . "\r\n"; 
		}
		$this->OUTPUT=$fieldnames.$rows;	
	}
	
	//excel
	function cleanData_excel(&$str) {
		if(is_array($str)) $str=implode(', ',$str);
		if(is_null($str)) $str='';
		$str = preg_replace("/\t/", "\\t", $str);
		$str = preg_replace("/\r?\n/", "\\n", $str);
		if(strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"';
	}

	function formatData_excel(){
		$fieldnames=$rows=false;
		foreach($this->DATA as $row) {
			if(is_object($row)) $row=(array)$row;
			if(!$fieldnames) $fieldnames=implode("\t", array_keys($row)) . "\r\n";
			array_walk($row, array($this,'cleanData_excel')); 
			$rows.=implode("\t", array_values($row)) . "\r\n"; 
		}
		$this->OUTPUT=$fieldnames.$rows;	
	}
	//common
	function replaceNulls(){
		$d=array();
		foreach($this->DATA as $id=>$row) {
			foreach($row as $i=>$v){
				$val=(in_array($v,array('NULL','Null','null')))?'':$v;
				$d[$id][$i]=$val;
			}
		}
		$this->DATA=$d;
	}
	
	function doDownload(){
		if(!$this->OUTPUT) die('nothing to export...');		
		header('Content-Disposition: attachment; filename="'.$this->FILENAME.$this->EXT.'"'); 
		header("Content-Type: ".$this->CONTENT_TYPE);
		echo $this->OUTPUT;
		exit;		
	}
	
	function doSave(){
		$chk=file_put_contents($this->FILENAME,$this->OUTPUT);
		if(!$chk) die('problem saving file: '.$this->FILENAME);
	}

}
