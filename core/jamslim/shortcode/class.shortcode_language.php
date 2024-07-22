<?php
class shortcode_language extends slimShortCoder{
	private $USER;
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
		$this->USER=$slim->user;
	}
	
	//required function
	public function getReplace($args){
		$args['shortcode']=true;
		$content=$this->renderLang();
		$out['rp']=$out['cnt']=$content;
		$out['js']=false;
		$out['jqd']=false;
		return $out;
	}

	public function renderLang(){
		$what=issetCheck($this->ROUTE,2);
		$content=msgHandler($this->SLIM->language->getStandardPhrase('no_content_found'),'alert');
		$langs=$this->SLIM->language->get('_LANGS');
		switch($what){
			case 'mode':
				$lang_mode=issetCheck($this->ROUTE,3,'translate');//default to normal mode
				$url=issetCheck($_GET,'u');
				if($url){
					$url=base64_decode($url);
				}else{
					$url=URL.'page/home';
				}
				$this->SLIM->language->set('MODE',$lang_mode);
				$msg=($lang_mode==='edit')?'Language Edit Mode: <strong>On</strong>':'Language Edit Mode: <strong>Off</strong>';
				setSystemResponse($url,$msg);
				break;
			case 'select':
				$page=issetCheck($this->ROUTE,3,'home');
				$title='select_language';
				$button=[];
				foreach($langs as $i=>$v){
					$flag='<span class="flag-box"><span class="flag '.$i.'"></span></span>';
					$v=strtolower($v);
					$caption=($this->LANGUAGE!=='en')?$this->SLIM->language->getStandard('continue_in_'.$v):'continue in '.$v;
					$button[$i]='<button class="button button-olive gotoME" data-ref="'.URL.'page/lang/switch/'.$i.'/'.$page.'">'.$flag.'<span class="caption">'.$caption.'</span></button>';
				}
				$content='<div class="button-group stacked-for-small">'.implode('',$button).'</div><button class="button secondary expanded" data-close><i class="fi-x"></i> '.$this->SLIM->language->getStandard('cancel').'</button>';
				break;
			case 'switch':
				$lang=issetCheck($this->ROUTE,3);
				$page=issetCheck($this->ROUTE,4,'home');
				if(array_key_exists($lang,$langs)){
					//set user session
					setMySession('language',$lang);
					$this->SLIM->language->set('LANG',$lang);
					$msg=$this->SLIM->language->getStandardPhrase('language_selected');
				}else{
					$msg=$this->SLIM->language->getStandardPhrase('language_not_found');
				}
				setSystemResponse(URL.'page/'.$page,$msg);
				die;
				break;			
		}
		$title=$this->SLIM->language->getStandard($title);
		$out=renderCard_active($title,$content,$this->SLIM->closer);
		echo $out;
		die;
	}

}
