<?php
class content_events_calendar{
	private $SLIM;
	private $SLUG;
	public $DATA;
	
	private $AJAX;
	private $ARGS;
	
	private $ACT;
	private $DATE;
	
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
		$o=$this->renderContent();
		if(!$o||$o==='') $o=' ';
		return $o;
	}
	
	private function setArgs($args=false){
		$this->ACT=issetCheck($args,0,'widget');
		$this->DATE=issetCheck($args,1);
	}
	
	// custom methods
    function renderContent() {
		$MPE = new slimEvents_public($this->SLIM);
		if($this->ACT==='mini_calendar'){
			$opts['action']='mini_calendar';
			$opts['date']=$this->DATE;
		}else{	
			$opts['action']=$this->ACT;
		}
		$widget = $MPE->Process($opts);
		$output['content'] = $widget['main_content'];
		$output['js'] = $widget['js'];
		$output['jqd'] = issetCheck($widget,'jqd');
		return $output;
    }
}
