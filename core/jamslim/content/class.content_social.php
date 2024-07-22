<?php
class content_social{
	private $SLIM;
	private $SLUG;
	public $DATA;
	
	private $AJAX;
	private $ARGS;
	
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
    function renderContent(){
        $skips = $this->SLIM->config['PUBLIC']['never_cache'];
        $perm = $this->SLIM->router->get('permlinks');
        
        $skips[] = 'login';
        $skips[] = 'cart';
        $skips[] = 'my-home';
        $parts=false;
        $static=false;
        if (in_array($this->SLUG, $skips)) {
            $static=true;
        }
        $SOC = new slim_social_icons($this->SLIM);
        $args=['url'=>$perm['link'],'slug'=>$this->SLUG];
        $output=$SOC->get('all',$args);
        $social['SOCIAL_BAR'] = ''; //floating bar
        $social['SOCIAL_BAR1'] = ($this->SLUG === 'home') ? '' : $output['footer'];
        $social['SOCIAL_BAR2'] = $output['footer'];
        return $social;
	}
}
