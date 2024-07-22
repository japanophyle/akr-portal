<?php

class shortcode_signup extends slimShortCoder{
	function __construct($slim=false){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
	}
	
	//required function
	function getReplace($args=false){
		$app= new slimSignupPublic($this->SLIM);		
		$out=$app->render();
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

}
