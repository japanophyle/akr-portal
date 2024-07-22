<?php

class shortcode_homepage extends slimShortCoder{
	private $USER;
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
		$this->USER=$slim->user;
		$this->ROUTE=$slim->router->get('route');
	}
	
	//required function
	public function getReplace($args){
		$slug=issetCheck($this->ROUTE,1,'home');
		$args['shortcode']=true;
		$content=$this->renderHome();
		$out['rp']=$out['cnt']=$content['content'];
		$out['js']=issetCheck($content,'js');
		$out['jqd']=issetCheck($content,'jqd');
		$this->SLIM->assets->set('title','','page');// no title on homepage
		return $out;
	}
	
	private function renderHome(){
		$flags='';
		$langs=$this->SLIM->language->get('_LANGS');
		foreach($langs as $i=>$v){
			if($i!==$this->LANGUAGE){
				$flags.='<span class="flag-box tiny"><span class="flag '.$i.'"></span></span>';
			}	
		}
		if($this->USER['access']==0||$this->USER['access']>=$this->SLIM->AdminLevel){
			$subtitle=$this->SLIM->language->getStandard('welcome');
			$blurb='<p>'.$this->SLIM->language->getStandardPhrase('welcome_info').'</p>';
			//$blurb.='<a class="button expanded button-olive loadME" href="'.URL.'page/lang/select">'.$flags.'&nbsp; '.$this->SLIM->language->getStandard('select_language').'</a> ';          				
		}else{
			$subtitle=ucwords($this->SLIM->language->getStandard('hello')).' '.$this->USER['name'];
			$blurb='';
		}
		$content='
		<div class="cell width-normal">
			<div class="block">
			<h3 class="text-title">'.$this->SLIM->language->getStandardPhrase('site_name').'</h3>
			<div class="text">
				<h4 class="text-subtitle">'.$subtitle.'</h4>
				'.$blurb.'
			</div>
			</div>			
		</div>';
		$content.=$this->renderEvents();
		return ['content'=>$content];		
	}
	private function renderEvents($page=false){
		if(!$page) $page='events';
		$this->SLIM->PublicEvents->ROUTE=$this->ROUTE;
		$this->SLIM->PublicEvents->USER=$this->USER;
		$this->SLIM->PublicEvents->GET=[];
		$this->SLIM->PublicEvents->PAGE=$page;
		$this->SLIM->PublicEvents->AJAX=$this->AJAX;
		return $this->SLIM->PublicEvents->render($page);
	}	
}
