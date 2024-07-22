<?php
class content_main_nav{
	private $SLIM;
	private $SLUG;
	private $EVENTS_PARENT;
	private $EVENTS;
	private $LIMIT=5;
	private $ID;
	public $DATA;
	
	function __construct($slim=null,$slug=false){
		if(!$slim) throw new Exception('no slim object!!');
		if(!$slug){
			$slug=$slim->router->get('page_slug');
		}
		if(!$slug) throw new Exception('no page slug found!!');
		$this->SLIM=$slim;
		$this->SLUG=$slug;
	}
	
	function render($args=false){
		$this->setArgs($args);
		return $this->renderNav();
	}
	
	private function setArgs($args=false){
		$this->EVENTS_PARENT=issetCheck($args,'EVENTS_PARENT');
		$this->LIMIT=issetCheck($args,'LIMIT',$this->LIMIT);
		if(!$this->EVENTS_PARENT){
			$this->EVENTS_PARENT=$this->SLIM->config['PARENTS']['EVENTS_PARENT'];
		}
		$this->ID=issetCheck($args,'ID');
        $this->setEvents();
	}
	
	private function renderNav() {
		$opts['ITEM_SLUG']=$this->SLUG;
		$opts['MAINPAGE']='home';
		$url=URL;
        $navdata = $this->SLIM->Options->get('main_menu'); 
        $MENU = new slimArrayMenu($this->SLIM,$navdata,$this->SLUG);
        $MENU->DEVICE=$this->SLIM->router->get('device');
        $MENU->NAV_TEMPLATE=$this->SLIM->config['NAVIGATION_TYPE'];
        $MENU->addEvents($this->EVENTS,$this->LIMIT,$this->EVENTS_PARENT);
        $MENU->rebuildMenu(false);//false returns data
        $m=$MENU->RESPONSE['data'];
        if(!$m) $m=' ';
        return $m;
    }
    
    private function setEvents(){
		$this->EVENTS=false;
		return;
		if(!function_exists('getForthcomingEvents')) include_once CORE.'jamslim/slim_functions_events.php';
		$this->EVENTS=getForthcomingEvents(1,'all');
	}
}
