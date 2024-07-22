<?php

class shortcode_myhome extends slimShortCoder{
	function __construct($slim=false){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
	}
	
	//required function
	function getReplace($args=false){
		$page=issetCheck($this->ROUTE,3,'home');
		$out=$this->SLIM->Members->render();
		if(!is_array($out)){
			$out=['content'=>$out];
		}
		$title=issetcheck($out,'title');
		if($title)	$this->SLIM->assets->set('title',$title,'page');
		$content['cnt']=$out['content'];
		$content['script']=issetCheck($out,'scripts');
		$content['jqd']=issetCheck($out,'jqd');
		$content['js']=issetCheck($out,'js');
		$content['rp'] = $content['cnt'];
		$content['find'] = '<p>' . $this->FIND . '</p>';
		return $content;
	}

}
