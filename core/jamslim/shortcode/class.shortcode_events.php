<?php

class shortcode_events extends slimShortCoder{
	
	function __construct($slim=false){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
	}
	
	//required function
	function getReplace($args=false){
		$out=$this->renderEventContent();
		if(!is_array($out)){
			$out=['content'=>$out];
		}
		$content['cnt']=$out['content'];
		$content['script']=issetCheck($out,'scripts');
		$content['jqd']=issetCheck($out,'jqd');
		$content['js']=issetCheck($out,'js');
		$content['rp'] = $content['cnt'];
		$content['find'] = '<p>' . $this->FIND . '</p>';
		return $content;
	}
	
	private function renderEventContent(){
		$page=issetCheck($this->ROUTE,1,'home');
		$act=issetCheck($this->ROUTE,2,'events');
		$this->SLIM->PublicEvents->ROUTE=$this->ROUTE;
		$this->SLIM->PublicEvents->USER=$this->SLIM->user;
		$this->SLIM->PublicEvents->GET=[];
		$this->SLIM->PublicEvents->PAGE=$page;
		$this->SLIM->PublicEvents->AJAX=$this->AJAX;
		return $this->SLIM->PublicEvents->render($act);		
	}

}
