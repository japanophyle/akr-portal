<?php

class slimHelpbot{
	private $SLIM;
	private $DEF_RECORD;
	private $DEF_INSTUCTION;
	private $DATA;
	private $TRIGGER;
	private $POWER=false;

    function __construct($slim=null){
		if(!$slim) throw new Exception('the slim object is missing...');
		$this->SLIM=$slim;
		$this->DEF_RECORD=array('name'=>'','link_id'=>false,'auto'=>false,'instructions'=>array());
		$this->DEF_INSTUCTION=array('target_id'=>false,'next'=>true,'prev'=>false,'close'=>true,'content'=>'');
	}
	
	function get($what=false){
		$out=array('status'=>500,'data'=>false);
		if(!$this->POWER) return $out;
		if($what==='trigger'){
			if($this->TRIGGER){
				$out=array('status'=>200,'data'=>$this->TRIGGER);
			}
		}else{
			$data=$this->renderHelp($what);
			if($data) $out=array('status'=>200,'data'=>$data);
		}
		return $out;
	}
	
	private function renderHelp($key=false){
		$out=false;
		if(!issetCheck($this->DATA,$key)){
			$this->setHelpData($key);
		}
		if(issetCheck($this->DATA,$key)){
			$rec=$this->DATA[$key];
			$rows=false;
			foreach($rec['instructions'] as $i=>$v){
				$rows.=$this->renderInstruction($i,$v);
			}
			if($rows) $out.=$this->renderWrap($rec,$rows);
		}
		return $out;
	}
	
	private function renderInstruction($id,$args){
		$tpl=$this->DEF_INSTUCTION;
		$params=false;
		foreach($tpl as $i=>$v){
			$val=issetCheck($args,$i,$v);
			switch($i){
				case 'next': case 'prev':
					if($val) $params.='data-next-text="'.ucME($i).'" ';
					break;
				case 'close':
					if(!$val) $params.='data-closable="false" ';
					break;
				case 'position':
					if(!$val) $params.='data-position="'.$val.'" ';
					break;
				case 'target_id':
					if($val) $params.='data-target="#'.$val.'" ';
					break;
			}
		}
		$out=false;
		if($params){
			$out='<li '.$params.'>'.$args['content'].'</li>';
		}
		return $out;
	}		
	
	private function renderWrap($rec,$rows){
		$params=false;
		if(issetCheck($rec,'auto')) $params.='data-autostart="true" ';
		$id=issetCheck($rec,'link_code','helpbot-'.time());
		$this->TRIGGER='<button type="button" class="button tiny button-dark-green helpbot" title="Help" data-joyride-start="#'.$id.'"><i class="icon-x2 fi-first-aid"></i></button>';
		$out='<ol data-joyride '.$params.' id="'.$id.'">'.$rows.'</ol>';
		return $out;
	}	
	
	private function setHelpData($key){
		$func='help_'.$key;
		if(method_exists($this,$func)){
			$this->$func($key);
		}		
	}
	
	private function help_edit_member($k){
		$inst[]=array('target_id'=>false,'next'=>true,'prev'=>false,'close'=>true,'content'=>'<h4>Add Grade</h4><p>Use this button to add a new grade record.</p>');
		$inst[]=array('target_id'=>false,'next'=>true,'prev'=>false,'close'=>true,'content'=>'<h4>Edit Grade</h4><p>Click on a row to edit a grade record.</p>');
		$help=array('name'=>'','link_id'=>$k.'-joyride','auto'=>false,'instructions'=>$inst);
		$this->DATA[$k]=$help;		
	}
	private function help_edit_log($k){
		$inst[]=array('target_id'=>false,'next'=>true,'prev'=>false,'close'=>true,'content'=>'<h4>Add Grade</h4><p>Use this button to add a new grade record.</p>');
		$inst[]=array('target_id'=>false,'next'=>true,'prev'=>false,'close'=>true,'content'=>'<h4>Edit Grade</h4><p>Click on a row to edit a grade record.</p>');
		$help=array('name'=>'','link_id'=>$k.'-joyride','auto'=>false,'instructions'=>$inst);
		$this->DATA[$k]=$help;		
	}
	private function help_edit_grade($k){
		$inst[]=array('target_id'=>false,'next'=>true,'prev'=>false,'close'=>true,'content'=>'<h4>Add Options</h4><p>If you need to add/edit options to the dropdown lists, you can do this via the "Settings" button at the top of the page.</p>');
		$inst[]=array('target_id'=>false,'next'=>true,'prev'=>false,'close'=>true,'content'=>'<h4>Members Current Grade</h4><p>Click the "Set as Current Grade" button to make this the current grade.</p>');
		$help=array('name'=>'','link_id'=>$k.'-joyride','auto'=>false,'instructions'=>$inst);
		$this->DATA[$k]=$help;		
	}
}
