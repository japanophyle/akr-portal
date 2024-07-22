<?php

class slimFileMan{
	var $SLIM;
	var $OB;
	var $PERMLINK;
	var $DEFAULT_DIR='..';
	var $USER;
	var $CONFIG;
	
	function __construct($slim=null){
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		$route=$slim->router->get('route');
		$this->PERMLINK=URL.$route[0].'/'.$route[1].'/';
		$root_url=(ENVIRONMENT!=='live')?basename(ROOT).'/':'/';
		$this->CONFIG=array(
			'lang'=>'en',
			'error_reporting'=>true,
			'show_hidden'=>true,
			'app_url'=>$this->PERMLINK,
			'root_path'=>ROOT,
			'root_url'=>$root_url
		);
	}
	function render(){
        define('FM_EMBED', true);
        define('FM_SELF_URL', $this->PERMLINK); // must be set if URL to manager not equal PHP_SELF
		$site_root_url=$this->CONFIG['root_url'];
		$site_root_path=ROOT;
		$site_date_format='Y-m-d H:i';
		$CONFIG=json_encode($this->CONFIG);
        require 'dev/dev.filemanager.php';
        die;		
	}

}
