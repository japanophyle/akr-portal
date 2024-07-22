<?php

class status_colors{
	private $COLORS=[
		'page_status'=>['published'=>'dark-green','draft'=>'orange','disabled'=>'dark-gray'],
		'event_status'=>[1=>'dark-green',0=>'dark-gray'],
		'active_status'=>['active'=>'dark-green','inactive'=>'dark-gray'],
		'member_status'=>['active'=>'dark-green','inactive'=>'dark-gray','pending'=>'orange'],
	];
	
	function render($what,$value,$text=false){
		$cols=issetCheck($this->COLORS,$what);
		if(is_string($value)) $value=strtolower($value);
		if(!$text) $text=ucME($value);
		if($cols){
			if($c=issetCheck($cols,$value)){
				$text='<span class="text-'.$c.'">'.$text.'</span>';
			}
		}
		return $text;
	}
}
