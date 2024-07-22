<?php 

class admin_zipper{
	private $SLIM;
	private $LIB;
	private $OUTPUT;
	
	public $PLUG;
	public $AJAX;
	public $REQUEST;
	public $USER;
	public $METHOD;
	public $SECTION;
	public $ACTION;
	public $ADMIN;
	public $LEADER;
	public $ROUTE;	
		
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->LIB= new dev_zipper($slim);
	}
	
	function Process(){
		$this->OUTPUT=$this->LIB->Process();
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
