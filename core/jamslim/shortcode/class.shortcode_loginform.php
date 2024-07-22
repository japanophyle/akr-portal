<?php

class shortcode_loginform extends slimShortCoder{

	function __construct($slim=false){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
	}
	
	//required function
	public function getReplace($args){
		$LG = new content_loginform($this->SLIM);
        $cnt = $LG->render();
		$out['rp']=$cnt['content'];
		$out['js']=issetCheck($cnt,'js');
		$out['jqd']=false;
		return $out;
	}
}
