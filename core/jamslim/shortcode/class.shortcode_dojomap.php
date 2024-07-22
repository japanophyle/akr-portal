<?php

class shortcode_dojomap extends slimShortCoder{
	private $MAP_OPTS;
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
		$this->USER=$slim->user;
		$this->MAP_OPTS=[
			'search'=>'false',
			'toolbox'=>'false',
			'height'=>'500px',
			'width'=>'100%',
			'center_m'=>[37.038,-95.317],
			'center'=>[37.038,-95.317],
			'zoom'=>4,			
		];
	}
	
	//required function
	function getReplace($args=false){
		$content['cnt']=$this->getMap();
		$content['script']=$content['jqd']=$content['js']='';
		$content['rp'] = $content['cnt'];
		$content['find'] = '<p>' . $this->FIND . '</p>';
		return $content;
	}
	
	function getMap(){
		$links=['admin'=>'hTx0NyYrBqaanTjY','edit'=>'vuUqlpT5Wb3aWM','view'=>'Mk33LCYtH6tE'];
		$mode='view';
		if($this->USER['access']==$this->SLIM->AdminLevel){
			$mode='edit';
		}else if($this->USER['access']>$this->SLIM->AdminLevel){
			$mode='admin';
		}
		$link='https://facilmap.org/'.$links[$mode];
		$link.='?search='.$this->MAP_OPTS['search'];
		$link.='&toolbox='.$this->MAP_OPTS['toolbox'];
		$link.='#'.$this->MAP_OPTS['zoom'];
		$link.='/'.$this->MAP_OPTS['center_m'][0].'/'.$this->MAP_OPTS['center_m'][1];
		$link.='/Mpnk/'.$this->MAP_OPTS['center'][0].'%2C%2'.$this->MAP_OPTS['center'][1];		
		return '<div class="block"><iframe style="height:500px; width:100%; border:none;" src="'.$link.'"></iframe></div>';
	}

}
