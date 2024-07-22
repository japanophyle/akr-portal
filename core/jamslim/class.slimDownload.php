<?php

class slimDownload{
	private $SLIM;
	private $DATA;
	private $FILENAME;
	private $TYPE;
	private $ERR='';
	
	function __construct($slim){
		$this->SLIM=$slim;
	}
	
	function go($data,$filename,$type='excel'){
		$this->ERR='';
		$this->setType($type);
		$this->setData($data);
		$this->setFilename($filename);
		$this->DATA=$data;
		$this->FILENAME=$filename;
		if($this->ERR===''){
			switch($this->TYPE){
				case 'csv': $this->downloadCsv(); break;
				default: $this->downloadExcel();
			}
		}
		die($this->ERR);
	}
	private function setType($data){
		$this->TYPE=($data==='csv')?'csv':'excel';
	}
	private function setData($data){
		$this->DATA=($data && is_array($data))?$data:[];
		if(!$this->DATA) $this->ERR='No data supplied';		
	}
	private function setFilename($data){
		$fname=(trim($data)!=='')?$data:'';
		if($fname===''){
			$ext=($this->TYPE==='csv')?'.csv':'.xlsx';
			$fname=gmdate('YmdHi') . $ext;
		}
		$this->FILENAME=$fname;
	}
	private function downloadExcel(){
		SimpleXLSXGen::fromArray($this->DATA)->downloadAs($this->FILENAME);
	}
	private function downloadCsv(){
		$csv=SimpleCSV::export($this->DATA);
		if(!$csv || trim($csv)===''){
			$this->ERR='Could not generate the CSV file.';
			return;
		}
		header("Content-type: text/csv");
		header("Content-Disposition: attachment; filename={$this->FILENAME}");
		echo $csv;
	}
}
