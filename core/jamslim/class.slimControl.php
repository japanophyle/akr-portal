<?php

class slimControl{
	protected $METHOD;
	protected $AJAX;
	protected $SLIM;
	protected $RESPONSE;
	protected $ROUTE;
	protected $USER;
	// $REQUESTS hold the valid routes
	protected $ACTIONS=array('GET'=>array(),'POST'=>array(),'PUT'=>array(),'DELETE'=>array());
	protected $VALID_METHODS=array('GET','POST');
	private $OFFLINE=false;
	
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;//pimple container
	}
	private function setRouterRoutes(){
		//send valid routs to router
		$keys=array();
		foreach($this->VALID_METHODS as $M){
			$k=array_keys($this->ACTIONS[$M]);
			foreach($k as $v){
				if(!in_array($v,$keys)) $keys[]=$v;
			}
		}
		$this->SLIM->router->set('valid_routes',$keys);
	}
	
	private function init(){
		validate_lkey();
		$this->setRouterRoutes();
		$this->METHOD=$this->SLIM->router->get('method');
		if(!$this->METHOD) throw new Exception(__METHOD__.': request method not set!!');
		$this->OFFLINE=$this->SLIM->Options->get('site_offline','value');
		$this->AJAX=$this->SLIM->router->get('ajax');
		$this->USER=$this->SLIM->user;
		if($this->USER['access']>=$this->SLIM->AdminLevel && $this->OFFLINE==='offline') $this->OFFLINE='online';
		if($this->OFFLINE!=='online'){
			if($this->METHOD!=='POST') $this->SLIM->router->set('page','offline');
		}
		$this->ROUTE=$this->SLIM->router->get('route');
	}
	
	public function setRoute($what=false,$path=false,$func=false){
		if(in_array($what,$this->VALID_METHODS)){
			if(is_string($path) && $path!==''){
				if(is_callable($func)){
					$this->ACTIONS[$what][$path]=$func;
				}else{
					throw new Exception(__METHOD__.': $func must be callable');
				}
			}else{
				throw new Exception(__METHOD__.': $path must be string');
			}
		}else{
			throw new Exception(__METHOD__.': the method ['.$what.'] is invalid.');	
		}
	}

	public function run(){
		$this->init();
		if(in_array($this->METHOD,$this->VALID_METHODS)){
			$this->hasActions();
			$r=$this->getRoute_str();
			$this->hasAccess($r);
			$action=$this->getAction($r);
			if($action){
				call_user_func($action,$this->SLIM);
				$this->SLIM->renderOutput;
				die;
			}else{
				$this->notFoundHandler($r);
			}
		}else{
			$this->invalidMethodHandler();
		}
	}
	
	private function hasActions(){
		if(empty($this->ACTIONS[$this->METHOD])){
			throw new Exception(__METHOD__.': request actions for '.$this->METHOD.' have not been set!!');
		}
	}
	
	private function getAction($r){
		$action=issetCheck($this->ACTIONS[$this->METHOD],$r);
		if(!$action){//check for global route actions, ie: foo/var/*
			$_r='';
			foreach($this->ROUTE as $i=>$v){
				$_r.=$v.'/';
				$action=issetCheck($this->ACTIONS[$this->METHOD],$_r.'*');
				if($action) break;
			}
			if(!$action){//check for global action ie: *
				$action=issetCheck($this->ACTIONS[$this->METHOD],'*');
			}
		}
		return $action;
	}

	private function getRoute_str(){
		return implode('/',$this->ROUTE);
	}
	
	private function setResponse(){
		$r=array('status'=>500,'message'=>false,'data'=>false);
		if($this->RESPONSE){
			if(is_string($this->RESPONSE)){
				$r['status']=200;
				$r['data']=$this->RESPONSE;
			}else{
				foreach($this->RESPONSE as $i=>$v){
					$r[$i]=$v;
				}
			}
		}else{
			$r['message']='Sorry, your request was invalid...';
		}
		$this->RESPONSE=$r;
	}
	
	private function hasAccess($path=false){
		if($this->USER['access']>=$this->SLIM->AdminLevel){
			//coolio
			$what='coolio';
		}else{
			$what=$this->ROUTE[0];
		}
		switch($what){
			case 'login':case 'logout':
				$login_message='Sorry, you need to login...';
				setSystemResponse(URL.'page/login',$login_message);
				throw new Exception($login_message);
				break;
			case 'admin':
				if($this->USER['access']<$this->SLIM->LeaderLevel){
					$login_message='Sorry, you dont have access to that...';
					$u=($this->AJAX)?'page/login':'page/home';
					setSystemResponse(URL.$u,$login_message);
					throw new Exception($login_message);					
				}
				break;
			case 'leaders':
				if($this->USER['access']<$this->SLIM->LeaderLevel){
					$login_message='Sorry, you dont have access to that...';
					setSystemResponse(URL.'page/home',$login_message);
					throw new Exception($login_message);					
				}
				break;
			case 'members':
				if($this->USER['access']<$this->SLIM->UserLevel){
					$login_message='Sorry, you dont have access to that...';
					setSystemResponse(URL.'page/home',$login_message);
					throw new Exception($login_message);					
				}
				break;
			case 'coolio':
				//let them through
				break;
			default:
				//check user permission recs
				if(is_array($what)){// group array(id,code,name)					
					if(!(int)$this->USER['permissions']['recs'][$what['code']]){
						$login_message='Sorry, you dont have access to that...';
						setSystemResponse(URL.'page/home',$login_message);
						throw new Exception($login_message);
					}
				}
		}
		
	}
	private function invalidMethodHandler(){
		header($this->SLIM->router->get('server_protocol').' 405 Method Not Allowed ['.$this->METHOD.'] ');
		throw new Exception(__METHOD__.': request method ['.$this->METHOD.'] is invalid!!');
		die();
	}
	private function notFoundHandler($path=false){
		header($this->SLIM->router->get('server_protocol').' 404 Not Found');
		throw new Exception('the requested path ['.$path.'] is invalid!!');
		die();
	}

    public function __call($method, $args){
       if ($this->SLIM->has($method)) {
            $obj = $this->SLIM->get($method);
            if (is_callable($obj)) {
                return call_user_func_array($obj, $args);
            }else{
				return $obj;
			}
       }
       throw new Exception(__METHOD__.": $method is not a valid service.");
    }
	
}
