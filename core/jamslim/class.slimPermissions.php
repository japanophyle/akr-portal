<?php

class slimPermissions{
	private $ROLES;
	private $BITS;
	private $SLIM;
	private $MAX_PERM=0;
	
	function __construct($slim=null){
		$this->SLIM=$slim;
		$this->BITS=array('create'=>1,'read'=>2,'update'=>4,'delete'=>8,'all'=>16);
		$this->MAX_PERM=16;
		$this->init();
	}
	
	private function init(){
		$r[0]=array(
			'name'=>'guest',
			'perms'=>array(
				'clubs'=>0,
				'members'=>0,
				'events'=>0,
				'options'=>0,
				'locations'=>0,
				'pages'=>0,
			)
		);
		$r[20]=array(
			'name'=>'user',
			'perms'=>array(
				'clubs'=>0,
				'members'=>0,
				'events'=>0,
				'options'=>0,
				'locations'=>0,
				'pages'=>0,
			)
		);
		$r[21]=array(
			'name'=>'leader',
			'perms'=>array(
				'clubs'=>2,
				'members'=>2,
				'events'=>0,
				'options'=>0,
				'locations'=>0,
				'pages'=>0,
			)
		);
		$r[25]=array(
			'name'=>'admin',
			'perms'=>array(
				'clubs'=>16,
				'members'=>16,
				'events'=>16,
				'options'=>16,
				'locations'=>16,
				'pages'=>16,
			)
		);
		$r[30]=array(
			'name'=>'super',
			'perms'=>array(
				'clubs'=>16,
				'members'=>16,
				'events'=>16,
				'options'=>16,
				'locations'=>16,
				'pages'=>16,
			)
		);
		$this->ROLES=$r;
	}
	public function get($what=false,$vars=false){
		switch($what){
			case 'roles':
				$out=$this->getRoles($vars);
				break;
			case 'role':
				$out=$this->getRole($vars);
				break;
			case 'bits':
				$out=$this->getBitValues($vars);
				break;
			case 'decimal':
				$out=$this->getDecimalValue($vars);
				break;		
			case 'decimals':
				$out=$this->getDecimalValue_array($vars);
				break;		
			case 'permissions':
				$out=$this->getPermissionValues($vars);
				break;		
			case 'perm_bits':
				$out=$this->BITS;
				break;
			case 'max_perm':
				$out=$this->MAX_PERM;
				break;
			case 'edit_perms':
				$out=$this->renderEditPermissions($vars);
				break;
		}
		return $out;
	}
	private function getRole($id=0){
		if(!$chk=issetCheck($this->ROLES,$id)){
			$chk=$this->ROLES[0];
		}
		return $chk;
	}
	private function getRoles($all=false){
		$out=[];
		if(!$all){
			foreach($this->ROLES as $i=>$v) $out[$i]=$v['name'];
		}else{
			$out=$this->ROLES;
		}
		return $out;
	}
	private function getBitValues($decimal=0){
		$bin = decbin($decimal);
		$total = strlen($bin);
		$stock = array();	   
		for ($i = 0; $i < $total; $i++) {
			if ($bin[$i] != 0) {
				$bin_2 = str_pad($bin[$i], $total - $i, 0);
				array_push($stock, bindec($bin_2));
			}
		}
		return $stock;
	}
	private function getDecimalValue($values=[]){
		$dec=0;
		if(is_array($values)){
			foreach($values as $i=>$v){
				if($chk=issetCheck($this->BITS,$i)){
					if($v) $dec+=(int)$chk;
				}
			}
			if($dec && $dec>$this->MAX_PERM) $dec=$this->MAX_PERM;	
		}
		return $dec;
	}
	private function getDecimalValue_array($values=[]){
		//flatten the array after editing
		$out=[];
		if(is_array($values)){
			foreach($values as $i=>$v){
				$dec=0;
				foreach($v as $x) $dec+=(int)$x;
				if($dec && $dec>$this->MAX_PERM) $dec=$this->MAX_PERM;	
				$out[$i]=$dec;
			}
		}
		return $out;
	}
	private function getPermissionValues($values=[]){
		$perms=array();
		$bits=array_flip($this->BITS);
		if(is_array($values)){
			foreach($values as $i=>$v){
				if($chk=$this->getBitValues($v)){
					foreach($chk as $x){
						if($b=issetCheck($bits,$x)) $perms[$i][$b]=1;
					}
				}
			}			
		}
		return $perms;		
	}
	
	private function renderEditPermissions($data=false){
		$perms=issetCheck($data['attr_ar'],'data');
		if(!issetVar($perms)){
			$access=(int)issetCheck($data,'access');
			$perms=$this->get('role',$access);
			$perms=$perms['perms'];
		}else{
			//ensure we have a full array
			foreach($this->ROLES[0]['perms'] as $i=>$v){
				$perms[$i]=issetCheck($perms,$i,0);
			}
		}
		$tr='';
		ksort($perms);
		foreach($perms as $permname=>$dec){
			$td='<td>'.ucME($permname).'</td>';
			$bits=$this->get('bits',$dec);
			foreach($this->BITS as $i=>$v){
				$tik=(in_array($v,$bits))?'checked':''; 				
				$chkbox='<div class="checkboxTick"><input type="checkbox" title="'.$i.'" value="'.$v.'" id="cbk_'.$permname.$v.'" name="Permissions['.$permname.'][]" '.$tik.'/><label for="cbk_'.$permname.$v.'"></label></div>';
				$td.='<td>'.$chkbox.'</td>';
			}
			$tr.='<tr>'.$td.'</tr>';
		}
		$th='<tr><th>Section</th><th>'.implode('</th><th>',array_keys($this->BITS)).'</th></tr>';
		$table='<table class="checkboxSelector text-left" id="selectPermissions">'.$th.$tr.'</table>';
		$table.='<script>checkboxSelector("#selectPermissions");</script>';	
		return $table;
	}
	
}
