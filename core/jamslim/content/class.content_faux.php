<?php
class content_faux{
	private $SLIM;
	private $SLUG;
	public $DATA;
	public $CARTID;
	private $AJAX;
	private $ARGS;
	private $LAYOUT='tpl.main_sidebar.php';
	private $OUTPUT;
	
	function __construct($slim=null,$slug=false){
		if(!$slim) throw new Exception('no slim object!!');
		if(!$slug){
			$slug=$slim->router->get('page_slug');
		}
		if(!$slug) throw new Exception('no page slug found!!');
		$this->SLIM=$slim;
		$this->SLUG=$slug;
		$this->AJAX=$slim->router->get('ajax');
	}
	//common methods
	function render($args=false){
		$this->setArgs($args);
		return $this->renderContent();
	}
	
	private function setArgs($args=false){
		$this->ARGS=$args;
	}
	
	// custom methods
    function renderContent() {
        //this creates a fake page from a data array, like from the payment responder.
        $args['type'] = $this->SLUG;
        $out=$img=false;
		if($this->SLUG === 'search-results') $this->LAYOUT='tpl.main_single.php';
		$FX = new slimFaux($this->SLIM);
		$faux = $FX->Process($args);
		$this->SLIM->assets->set('js',$faux['js'],$this->SLUG);
		$this->SLIM->assets->set('jqd',$faux['jqd'],$this->SLUG);
		unset($faux['js'],$faux['jqd']);
		foreach ($faux as $i => $v) $this->OUTPUT[$i] = $v; //what for??
		if ($this->SLUG === 'paypalgood') {// remove cart
			unset($_SESSION['ezcart']);
			if($this->CARTID)unset($_SESSION[$this->CARTID]);
		}
		$faux['mainTitle']=$this->SLIM->language->getStandard($faux['mainTitle']);
		if($this->AJAX){
			echo renderCard_active($faux['mainTitle'],$faux['mainContent'],$this->SLIM->closer);
			die;
		}else{
			$out='<h3 class="text-gbm-blue">'.$faux['mainTitle'].'</h2>'.$faux['mainContent'];
			$out='<div class="grid-x widthlock"><div class="cell">'.$out.'</div></div>';
			$img=issetCheck($faux,'mainImage');				
		}      
         if(!$out||$out==='') $out=' ';
        return ($img)?['content'=>$out,'image'=>$img]:$out;
    }
}
