<?php

class slimRouter {
	private $SLIM;
	var $BASE_URL=false;
	var $ROUTES=[];
	var $ROUTE=[];
	var $SITE_ROUTES=[];
	var $GET_VARS;
	var $POST_VARS;
	var $SITE_VARS=false;
	var $PERMBASE=false;
	var $URL_TYPE=false;//normal or clean
	var $URL_PROTOCOL;
	var $PAGE_SLUG=false;
	var $SITE_PATH=false;
	var $SITE_LIVE=false;
	var $VALID_ROUTES=[];//from slimControl
	var $DEVICE='classic';
	var $LAST_PAGE='home';
	var $ROUTE_OFFSET=0;
	var $DEV_URL;
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->SITE_VARS=array('act','page','a');
		$this->GET_VARS=array('a','b','c','i','act','action','api','pc','fill','recache','tt','modal');
		$this->POST_VARS=array('action');
		$this->URL_PROTOCOL=$slim->config['URL_PROTOCOL'];
		$this->URL_TYPE=$slim->config['URL_TYPE'];
		$this->PAGE_SLUG=$slim->config['PAGE_SLUG'];
		$this->ROUTE_OFFSET=issetCheck($slim->config,'ROUTE_OFFSET',0);
		$this->SITE_PATH=false;
		$this->LAST_PAGE=issetCheck($_SESSION,'last_page','home');
		$this->DEVICE=isMobile();
		$this->SITE_LIVE=(ENVIRONMENT && (ENVIRONMENT == 'development' || ENVIRONMENT == 'dev'))?false:true;
		$this->DEV_URL=issetCheck($slim->config,'DEV_URL');//external via ngrox
		$this->PERMBASE = URL;
		if($this->SITE_LIVE && $this->URL_TYPE === 'clean'){
			if($this->SITE_PATH && $this->SITE_PATH!=='/') $this->PERMBASE=str_replace($this->SITE_PATH,'',$this->PERMBASE);
		}
	}
	function get($what=false,$args=false){
		if(!$this->ROUTES) $this->setRoutes();
		switch($what){
			case 'server_protocol':
				return $_SERVER["SERVER_PROTOCOL"];
				break;
			case 'server_name':
				return $_SERVER['SERVER_NAME'];
				break;
			case 'protocol':
				return $this->URL_PROTOCOL;
				break;
			case 'device':
				return $this->DEVICE;
				break;
			case 'page_slug':
				return $this->PAGE_SLUG;
				break;
			case 'last_page':
				return $this->LAST_PAGE;
				break;
			case 'routes':
				return $this->ROUTES;
				break;
			case 'route':
				$rt=[];
				for($x=0;$x<$this->ROUTES['count'];$x++){
					$rt[$x]=$this->ROUTES[$x];
				}
				return $rt;
				break;
			case 'get':
				return $this->ROUTES['get'];
				break;
			case 'post':
				return $this->ROUTES['post'];
				break;
			case 'permlinks':
				if($args) return issetCheck($this->ROUTES['permalink'],$args);
				return $this->ROUTES['permalink'];
				break;
			case 'ajax':
				return $this->ROUTES['is_ajax'];
				break;
			case 'ssl':
				return $this->ROUTES['is_ssl'];
				break;
			case 'method':
				return $this->ROUTES['method'];
				break;
			case 'uri': case 'current_url':
				return $this->BASE_URL;
				break;
		}
	}
	function set($what=false,$vars=false){
		if($what){
			if($what==='valid_routes'){
				$this->VALID_ROUTES=$vars;				
			}else if(array_key_exists($what,$this->ROUTES)){
				$tmp=$this->ROUTES[$what];				
				if(is_array($tmp)){
					if(is_array($vars)){
						foreach($vars as $i=>$v){
							$this->ROUTES[$what][$i]=$v;
						}
					}
				}else{
					$this->ROUTES[$what]=$vars;
				}
			}
		}
	}
	
	function Process($route=false){
		$this->setRoutes();
		if($route){
			$out=false;
			if(is_array($route)){
				foreach($route as $r){
					if(is_string($r)||is_integer($r)){
						if(isset($this->SITE_ROUTES[$r])){
							$out[$r]=$this->SITE_ROUTES[$r];
						}
					}
				}
			}else{
				if(isset($this->SITE_ROUTES[$route])){
					$out=$this->SITE_ROUTES[$route];
				}	
			}
			return $out;
		}else{
			return $this->ROUTES;
		}
	}
	
	function getCurrentUri(){
		$basepath = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -2)) . '/';
		if(strpos($_SERVER['SERVER_NAME'],'jamserver')!==false || strpos($_SERVER['SERVER_NAME'],'192.0.1.24')!==false){
			$uri=substr($_SERVER['REQUEST_URI'], strlen($basepath));
		}else if($_SERVER['SERVER_NAME']===$this->DEV_URL){
			$uri=substr($_SERVER['REQUEST_URI'], strlen($basepath));
		}else{
			$uri = issetCheck($_SERVER,'REQUEST_URI');
		}
		if (strstr($uri, '?')) $uri = substr($uri, 0, strpos($uri, '?'));
		$uri = '/' . trim($uri, '/');
		return $uri;
	}
    function setRoute($key=0,$value=false){
		if(is_numeric($key) && $key>0 && $key<=6){
			$this->ROUTE[$key]=$value;
		}
	}
    function setRoutes(){
		$this->BASE_URL = $this->getCurrentUri();
		$routes = array();
		$ct=0;
		$exp = explode('/', $this->BASE_URL);
		foreach($exp as $route){
		    $test=trim($route);
		    if($test != ''){
		        if($test!=='tbs4'){
		           if($ct>=$this->ROUTE_OFFSET) array_push($routes, $test);// hack until we are live!!!
		        }else{
		            //array_push($routes, $test);
		        }
		        $ct++;
		    }
		}
		if(empty($routes)){//fallback routes
			$routes[0]='page';
			$routes[1]='home';
		}else if($this->URL_TYPE==='normal'){
			$routes[]=getRequestVars($this->PAGE_SLUG);
		}
		$this->setRequestType();
		$this->setRequestMethod();
		$this->setGetVars();
		$this->setPostVars();
		$this->setSiteRoutes($routes);
		$this->ROUTES=$routes;
		$this->ROUTES['count']=$this->SITE_ROUTES['count']=count($routes);
		$this->ROUTES['base_url']=$this->SITE_ROUTES['base_url']=$this->BASE_URL;
		$this->setPermalink();		
		$this->ROUTES+=$this->SITE_ROUTES;
	}
	
	function setSiteRoutes($routes){
		if($this->URL_TYPE==='clean'){
			$this->setSiteRoutes_clean($routes);
		}else{
			$this->setSiteRoutes_normal($routes);
		}
	}
	
	function setSiteRoutes_normal($routes){
		$this->SITE_ROUTES['page']=(isset($routes[1]))?$routes[1]:'home';
	}
	
	function setSiteRoutes_clean($routes){
		$ct=0;
		foreach($routes as $i=>$v){
			if(isset($this->SITE_VARS[$i])){
				$k=$this->SITE_VARS[$i];
				$this->SITE_ROUTES[$k]=$v;
				$ct++;
			}
		}
        if(isset($this->SITE_ROUTES['act']) && !isset($this->SITE_ROUTES['page'])){
		    $this->SITE_ROUTES['page']=false;
			$this->BASE_URL=ltrim($this->BASE_URL,'/');
		}
		if($this->SITE_ROUTES['is_ajax']&&$this->SITE_ROUTES['act']=='page'){
			//trim get vars from base_url and page
			$page=$this->SITE_ROUTES['page'];
			foreach($this->SITE_ROUTES['get'] as $i=>$v){
				$tr="&$i=$v";
				$this->BASE_URL=str_replace($tr,'',$this->BASE_URL);
				$page=str_replace($tr,'',$page);
			}
			$this->SITE_ROUTES['page']=$page;
		}
		if($routes[0]==='page' && $ct>2){
			if($routes[1]!=='forms'){//the "forms" page renders va shortcode
				//swap the page routes
				$this->SITE_ROUTES['page']=$this->SITE_ROUTES['a'];
				$this->SITE_ROUTES['parent']=$routes[1];
			}
		}
		//if still no page set to home if on public site
		if(defined('MP_PUBLIC')){
			if(!$this->SITE_ROUTES['page']||$this->SITE_ROUTES['page']=='') $this->SITE_ROUTES['page']='home';
		}		
	}
	
	function setRequestType(){
		$this->isAjax();
		$this->isSSL();
	}
	function isAjax(){
		$ajax=false;
		$x = empty($_SERVER['HTTP_X_REQUESTED_WITH']);
		$y = empty($_SERVER['HTTP_REFERER']);
		$t = (isset($_SERVER['HTTP_ACCEPT']))?$_SERVER['HTTP_ACCEPT']:false;
		$r=(strpos($t, 'application/json') !== false)?'json':'html';
		if(!$x && !$y){
			if(strpos($_SERVER['HTTP_REFERER'],$_SERVER['SERVER_NAME'])!==false) $ajax=true;
		}else if($r==='json'){
			$ajax=true;
		}
		$this->SITE_ROUTES['output']=$r;
		$this->SITE_ROUTES['is_ajax']=$ajax;
	}
	
	function setRequestMethod(){
		$m=issetCheck($_SERVER,'REQUEST_METHOD');
		$this->SITE_ROUTES['method']=($m==='HEAD')?'GET':$m;
	}
	
	function setGetVars(){
		$get=array();
		foreach($_GET as $i=>$v){
			$get[$i]=$this->sanitize($v);
		}
		$this->SITE_ROUTES['get']=$get;
	}
	function setPostVars(){
		$post=array();
		foreach($_POST as $key => $value){
			if(is_array($value)){
				$val=filter_input(INPUT_POST, $key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			}else{
				$val=filter_input(INPUT_POST, $key, FILTER_SANITIZE_SPECIAL_CHARS );
			}
			$post[$key] = $val;
		}		
		$this->SITE_ROUTES['post']=$post;
	}

	function sanitize($val,$type=false){
		if(!$type) $type=gettype($val);
		switch($type){
			case 'array':
				return filter_var_array($val, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
				break;
			case 'boolean':
				return (!is_bool($val))?false:$val;
				break;
			case 'integer':
				return filter_var($val,FILTER_VALIDATE_INT);
				break;
			case 'float':
			case 'double':
				return filter_var($val,FILTER_VALIDATE_FLOAT);
				break;
			case 'lstr'://for $post textareas - keep tags
				return trim(filter_var($val,FILTER_SANITIZE_SPECIAL_CHARS));
				break;
			case 'object':
			case 'NULL':
			case 'resource':
				return $val;
				break;
			default://assume string - removes tags
				return trim(strip_tags($val));
		}		
	}
	
	function setPermalink(){
		$tt=issetCheck($this->SITE_ROUTES['get'],'tt');
		$cid=issetCheck($this->SITE_ROUTES['get'],'c');
		$aid=issetCheck($this->SITE_ROUTES['get'],'a');
		$bid=issetCheck($this->SITE_ROUTES['get'],'b');
		$page=issetCheck($this->SITE_ROUTES,'page');
		$permBase=$perm['base']=$this->PERMBASE;
  	    $permBase=str_replace('/mypress/','/',$permBase);// hack	
		switch($this->ROUTES[0]){
			case 'admin':
				$permBase.='admin/';
				$perm['back']=$permBase;
				$others=array('tables','navman','siteman','userman','catalogue','salesman');
				if($tt){
					$perm['back'].="?tbl=$tt";
				}else if($cid){	
					$perm['link']=$permBase."?c=$cid";
				}else{
					$perm['link']=$permBase;
				}
				$perm['link'].=($aid>0)?"&amp;a=$aid":"";
				$perm['link'].=($bid>0)?"&amp;b=$bid":"";
				$perm['link'].=($tt)?"&amp;tbl=$tt":"";
				break;
			case 'page':
				$perm['back']=$permBase;
				$args['page']=$page;
				$args['base']=1;
				if($this->URL_TYPE==='clean'){
					$url=(isset($this->SITE_ROUTES['parent']))?str_replace('/'.$this->SITE_ROUTES['parent'],'',$this->SITE_ROUTES['base_url']):$this->SITE_ROUTES['base_url'];
					$perm['link']=$permBase.ltrim($url,'/');
				}else{
					$perm['link']=$permBase.$this->PAGE_SLUG.'='.$page; 
				}
				break;
			case 'junk'://for dev
				$perm['back']=$permBase;
				$args['page']=$page;
				$args['base']=1;
				if($this->URL_TYPE==='clean'){
					$url=(isset($this->SITE_ROUTES['parent']))?str_replace('/'.$this->SITE_ROUTES['parent'],'',$this->SITE_ROUTES['base_url']):$this->SITE_ROUTES['base_url'];
					$perm['link']=$permBase.ltrim($url,'/');
				}else{
					$perm['link']=$permBase.$this->PAGE_SLUG.'='.$page; 
				}
				break;
			default:
				$perm['back']=$permBase;
				$perm['link']=$permBase;
		}
		$this->SITE_ROUTES['permalink']=$perm;
	}
	
	function isSSL(){
		if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
	       $ssl=false;
		}else{			
		   $ssl=true;
		   $this->URL_PROTOCOL='https:';
		}
		$this->SITE_ROUTES['is_ssl']=$ssl;
	}
}
