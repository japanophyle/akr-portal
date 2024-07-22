<?php

class initialAvatar_css{
	var $NAME='Xoo Xoo';
	var $SIZE='medium';
	var $INITIALS;
	var $COLORS;
	
	function __construct(){
		
	}
	
	function identicon($name=false,$size=false){
		if($name) $this->NAME=$name;
		if($size) $this->SIZE=$size;
		return $this->renderIcon();
	}
	
	private function renderIcon(){
		$this->setInitials();
		$this->setColors();
		$bgc=implode(',',$this->COLORS['background']);
		$fgc=implode(',',$this->COLORS['text']);
		return '<div class="initial_avatar '.$this->SIZE.'-avatar" style="background-color:rgb('.$bgc.');"><div class="initials" style="color:rgb('.$fgc.');">'.$this->INITIALS.'</div></div>';
	}
    
    private function setInitials(){
        $return = array();
        $names = explode(' ', $this->NAME);
        if (count($names) > 1) {
            foreach ($names as $name) {
                $return[] = (string) mb_substr(mb_strtoupper($name), 0, 1);
            }
        }else {
            $nameLength = strlen($this->NAME);
            if ($nameLength > 7) {
                $middle = floor(($nameLength / 2));
                return substr($this->NAME, 0, 1) . '' . substr($this->NAME, $middle, 1);
            }
        }
        $this->INITIALS=implode('', $return);
    }
    
    private function setColors(){
		$hash=md5($this->NAME);
		$color = substr($hash, 0, 6);    
        $hexcolor = substr($color, 0, 6);
        $r = hexdec(substr($hexcolor, 0, 2));
        $g = hexdec(substr($hexcolor, 2, 2));
        $b = hexdec(substr($hexcolor, 4, 2));

        $contrast = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

        list($_r, $_g, $_b)=($contrast >= 128) ? array(1, 1, 1) : array(255, 255, 255);
        
        $this->COLORS=array(
			'background'=>array($r,$g,$b),
			'text'=>array($_r,$_g,$_b)
		);
    }
    
}
