<?php

class slimErrors {
	private $MODE;
	private $LOGTO=false;//false,file or console
	private $LOG_FILE;
	private $MODES=array(0=>'silent',1=>'verbose',2=>'fatal',3=>'off');
	private $ETYPE=false;
	private $USER;
	private $ROUTE;
	private $AJAX;
	
	function __construct(){
		global $container;
		$this->USER=$container->user;
		$this->ROUTE=$container->router->get('route');
		$this->AJAX=$container->router->get('ajax');
		//default levels
		if(ENVIRONMENT==='development'||ENVIRONMENT==='dev'){
			$this->MODE='verbose';
		}else{
			$this->MODE='silent';
			$this->LOGTO='file';
		}		
	}
	function log(){
		$ct=func_num_args();
		if($ct==1){
			$this->exception_handler(func_get_arg(0));
		}else if($ct==4){
			$errno=func_get_arg(0);
			$errstr=func_get_arg(1);
			$errfile=func_get_arg(2);
			$errline=func_get_arg(3);
			$this->error_handler($errno, $errstr, $errfile, $errline);
		}else{
			echo "Error: " . func_num_args() . "<br />";
			for($i = 0 ; $i < func_num_args(); $i++) {
				echo "Argument $i = " . func_get_arg($i) . "<br />";
			}
			die;
		}
	}
	function setType($mode=false){
	    $this->ETYPE=($mode==='exception')?$mode:'error';
	}
	function setMode($mode=false){
	    $this->setErrorMode($mode);
	}
	function setLogging($mode=false){
		$mode=(in_array($mode,array('file','console','debug')))?$mode:false;
	    $this->LOGTO=$mode;
	}
	
    private function setErrorMode($m=0){
		$this->MODE=issetCheck($this->MODES,(int)$m);
		switch($this->MODE){
			case 'verbose':
				ini_set('display_errors', 1);
				ini_set('display_startup_errors', 1);
				error_reporting(E_ALL);
				break;
			case 'fatal':
				ini_set('display_errors', 1);
				ini_set('display_startup_errors', 1);
				error_reporting(E_ERROR);
				break;
			case 'off':
				ini_set('display_errors', 0);
				ini_set('display_startup_errors', 0);
				error_reporting(0);
				break;
			default://silent
				ini_set('display_errors', 0);
				ini_set('display_startup_errors', 0);
				error_reporting(E_ERROR);
		}		
	}

	// exception handler
	private function exception_handler($ex){

		//Log the exception to our server's error logs.
		$title='jamSlim Exception';
		$message=$ex->getMessage();
		$details='<li><strong>Code:</strong> '.$ex->getCode().'</li>';
		$details.='<li><strong>Line:</strong> '.$ex->getLine().'</li>';
		$details.='<li><strong>File:</strong> '.$ex->getFile().'</li>';
		//$details.='<li><strong>trace:</strong><code><pre>'.print_r($ex->getTrace(),1).'</pre></code></li>';
		
		if($this->LOGTO) $this->errorLog($title,$message,$details);
		if($this->AJAX){
			$html=$this->toAjax($message,$details);
		}else{
			$html=$this->toHTML($title,$message,$details);
		}
		if(isset($_SERVER['SERVER_PROTOCOL'])){
			header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
		}
		echo $html;
		exit;
	}
	
	// error handler
	private function error_handler($errno, $errstr, $errfile, $errline){
		$title='jamSlim Error';
		$message=$errstr;
		$details='<li><strong>Code:</strong> '.$errno.'</li>';
		$details.='<li><strong>Line:</strong> '.$errline.'</li>';
		$details.='<li><strong>File:</strong> '.$errfile.'</li>';
		
		if($this->LOGTO) $this->errorLog($title,$message,$details);
		if($this->AJAX){
			$html=$this->toAjax($message,$details);
		}else{
			$html=$this->toHTML($title,$message,$details);
		}
		if(isset($_SERVER['SERVER_PROTOCOL'])){
			if(!headers_sent()) header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
		}
		echo $html;
		exit;
	}
	
	private function toAjax($message,$details){
		$err='Error: '.$message.",\n";
		if($this->USER['access']==30){
			if(strpos('<li>',$details)!==false){
				$details=str_replace('</li>',",\n",$details);
				$details=str_replace('<li>','',$details);	
		    }
			$err.=$details;
		}		
		return $err;
	}
	
	private function toHTML($title,$message,$details){
		$logo='<img class="logo" src="gfx/alert.png" style="width:2rem;"> <span id="title" class="text-gbm-blue">Hmmm... Something is not right...</span>';
		$detail='<div class="cell panel"><div class="callout primary widthlock">Details:<ul>'.$details.'</ul></div></div>';
		if(ENVIRONMENT==='development'||ENVIRONMENT==='dev'){
			//show details
		}else if($this->USER['access']<30){
			$detail=false;
		}
		$html='<html>
			<head>
				<title>'.$title.'</title>
				<meta charset="UTF-8">
				<meta name="viewport" content="width=device-width">
				<base href="'.URL.'">
				<link rel="stylesheet" href="assets/css/jamslim.min.css?v=1">
				<link rel="stylesheet" href="assets/css/jamslim.public.css?v=1">
				<style>
				.bg-image {	width: 100%;position: fixed;top: 0;	left: 0;overflow: hidden; min-height:100%;z-index:-1;}
				.bg-image {
					min-height: 100%;
					background-image: url(gfx/target-miss.jpg);
					background-attachment: fixed;
					background-position: top center;
					-webkit-background-size: cover;
					-moz-background-size: cover;
					-o-background-size: cover;
					background-size: cover;
					background-repeat: no-repeat;
					background-color: #fff;
				}
				.panel{opacity:0.8;transition: opacity .25s ease-out,color .25s ease-out;}
				.panel:hover{opacity:0.9}				
				</style>			
			</head>
			<body>
			    <div class="bg-image"></div>
				<div class="top-bar header"><div class="top-bar-left"><div class="infobar title">'.$logo.'</div></div></div>
				<br/>
				<div class="grid-y grid-padding-x">							
				   <div class="cell">'.renderNiceError($message).'</div>
				   '.$detail.'
				</div>
			</body>
		</html>';
		return $html;
	}
	
	private function errorLog($number=0,$str=false,$line=0,$file=false){
		$this->LOG_FILE=CACHE.'log/slim_'.$this->ETYPE.'_'.date('Ymd').'.log';
		$message =  "time: ".date("j M y - H:i:s")."\n";
		$message .= "file: ".print_r( $file, true)."\n";
		$message .= "line: ".print_r( $line, true)."\n";
		$message .= "code: ".print_r( $number, true)."\n";
		$message .= "message: ".print_r( $str, true)."\n";
		$message .= "##############################\n\n";
		
		try{
			$t=file_put_contents($this->LOG_FILE,$message,FILE_APPEND | LOCK_EX);
			if(!$t) die('Could not log error to file: '.$this->LOG_FILE.'. Write Error.');
		}catch(Exception$e){
			die('Could not log error to file: '.$this->LOG_FILE.'. Permission Error.');
		}
	}	
}
