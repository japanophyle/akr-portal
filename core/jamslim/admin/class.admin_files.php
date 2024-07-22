<?php 

class admin_files{
	private $SLIM;
	private $LIB;
	private $OUTPUT;
	private $ROUTES;
	
	public $AJAX;
	public $REQUEST;
	public $USER;
	public $METHOD;
	public $SECTION;
	public $ACTION;
	public $PLUG;
	public $ADMIN;
	public $LEADER;
	public $ROUTE;	
		
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->ROUTES=$slim->router->get('routes');
		$this->AJAX=$slim->router->get('ajax');
		$this->LIB= new slimFileMan($slim);
	}
	
	function Process(){
		$this->OUTPUT=$this->LIB->render();
		return $this->renderOutput();
	}

	private function renderOutput(){
		if(is_array($this->OUTPUT)){
			foreach($this->OUTPUT as $i=>$v){
				if($i==='MENU'){
					if($v && !is_array($v)) $v=array('right'=>$v);
				}
				$out[strtolower($i)]=$v;
			}
		}else if(!$this->OUTPUT||$this->OUTPUT===''){
			$out=msgHandler('Sorry, no output was generated...',false,false);
		}else{
			$out=$this->OUTPUT;
		}
		if($this->AJAX){
			if(is_array($out)){
				jsonResponse($out);
			}else{
				echo $out;
			}
			die;
		}
		return $out;
	}

}
