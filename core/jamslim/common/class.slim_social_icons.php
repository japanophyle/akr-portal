<?php 

class slim_social_icons{
	private $SLIM;
	private $ROUTE;
	private $ICONS;
	private $ICON_SET='si';
	private $PERMLINK;
	private $SLUG;
	private $PARTS = ['tw', 'fb', 'rd'];
	private $OUTPUT;
	
    function __construct($slim){
		if(!$slim) throw new Exception('Sorry the slim object is missing...');
		$this->SLIM=$slim;
		$this->ROUTE=$slim->router->get('route');
		$r2=issetCheck($this->ROUTE,2,'home');
		$this->SLUG=issetCheck($this->ROUTE,3,$r2);
		$this->PERMLINK=$slim->router->get('permlinks','link');
		$this->initIcons();
		$this->initParts();
	}
	
	private function initIcons(){
        $soc['tw'] = array('url' => 'https://twitter.com/intent/tweet?text=', 'icon' => 'twitter','type'=>'share','label'=>'Twitter');
        $soc['fb'] = array('url' => 'http://www.facebook.com/sharer.php?u=', 'icon' => 'facebook','type'=>'share','label'=>'Facebook');
        $soc['gs'] = array('url' => 'https://plus.google.com/share?url=', 'icon' => 'google','type'=>'share','label'=>'Googler');
        $soc['gp'] = array('url' => 'https://plusone.google.com/_/+1/confirm?hl=en&amp;url=', 'icon' => 'google-plus','type'=>'share','label'=>'Google+');
        $soc['pt'] = array('url' => 'http://pinterest.com/pin/create/button/?url=', 'icon' => 'pinterest','type'=>'share','label'=>'Pintrest');
        $soc['su'] = array('url' => 'http://stumbleupon.com/submit?url=', 'icon' => 'stumble','type'=>'share','label'=>'StumbleUpon');
        $soc['dl'] = array('url' => 'http://del.icio.us/post?url=', 'icon' => 'delicious','type'=>'share','label'=>'Delicious');
        $soc['dg'] = array('url' => 'http://digg.com/submit?phase=2&amp;url=', 'icon' => 'digg','type'=>'share','label'=>'Digg');
        $soc['rd'] = array('url' => 'http://www.reddit.com/submit?v=5&amp;noui&amp;jump=close&amp;url=', 'icon' => 'reddit','type'=>'share','label'=>'Reddit');
 		$soc['sc']= array('url' => 'https://soundcloud.com/', 'label' => 'Soundcloud', 'icon' =>'soundcloud','type'=>'link');
		$soc['sp']= array('url' => 'https://open.spotify.com/', 'label' => 'Spotify', 'icon' =>'spotify','type'=>'link');
		$soc['ig']= array('url' => 'https://www.instagram.com/', 'label' => 'Instagram', 'icon' =>'instagram','type'=>'link','title'=>'Follow Us');
		$soc['em']= array('url' => 'mailto:info@danceradioshows.com', 'label' => 'Email', 'icon' =>'email','type'=>'link','title'=>'Contact Us');
		$this->ICONS=$soc;
	}
	private function initParts(){
		$parts=$this->SLIM->Options->get('site_social_icons','value');
		$def=[];
		if($parts){
			$parts=compress($parts,false);
			foreach($parts as $i=>$v){
				if((int)$v['status']) $def[]=$i;
			}
			$this->PARTS=$def;
		}else{
			foreach($this->ICONS as $i=>$v){
				$def[$i]=[
					'name'=>$v['label'],
					'status'=>(in_array($i,$this->PARTS))?1:0,
					'url_plus'=>''
				];
			}
			saveOption('site_social_icons',compress($def));
		}			
	}
	
	function get($what=false,$vars=false){
		$this->PARTS=issetCheck($vars,'parts',$this->PARTS);
		$this->PERMLINK=issetCheck($vars,'url',$this->PERMLINK);
		$this->SLUG=issetCheck($vars,'slug',$this->SLUG);
		switch($what){		
			case 'footer':
				$this->renderFooter();
				break;
			case 'connect':
				$this->renderConnect();
				break;
			case 'all':
				$this->renderFooter();
				$out['footer']=$this->OUTPUT;
				$this->renderConnect();
				$out['connect']=$this->OUTPUT;
				$this->OUTPUT=$out;
				break;
			case 'icons':
				return $this->ICONS;
				break;
			default:
				$this->OUTPUT=false;
		}
		return $this->OUTPUT;			
	}
	private function setUrl($code,$part){
		if($part['type']==='link'){
			$title=issetCheck($part,'title','Listen on '.$part['label']);
			$href = $part['url'];
		}else{
			$url=rawurlencode($this->PERMLINK);
			$title= 'Share on '.$part['label'];
			$href = ($code === 'tw') ? $this->SLUG . '&amp;url=' . $url : $url;
			$href = str_replace('/page//', '/page/', $href);
			$href = $part['url'].$href;
		}
		return ['title'=>$title,'href'=>$href];
	}
	private function renderFooter(){
		$foot=false;
		foreach($this->PARTS as $p){
			$part=issetCheck($this->ICONS,$p);
			if($part){
				$url=$this->setURL($p,$part);
				$foot.='<li class="social-icon"><a target="_blank" title="'.$url['title'].'" href="'.$url['href'].'" class=""><i class="'.$this->ICON_SET.'-'.$part['icon'].' icon-x2"/></i></a></li>';
			}
		}
		if($foot){
			$foot='<ul class="social-list">'.$foot.'</ul>';
		}
		$this->OUTPUT=$foot;
	}
	private function renderConnect(){
		$foot=false;
		foreach($this->PARTS as $p){
			$part=issetCheck($this->ICONS,$p);
			if($part){
				$url=$this->setURL($p,$part);
				$foot.='<li><a target="_blank" title="'.$url['title'].'" href="'.$url['href'].'" class="link-gbm-blue"><i class="'.$this->ICON_SET.'-'.$part['icon'].' icon-x3"/></i> <span class="h3">'.$part['label'].'</span></a></li>';
			}
		}
		if($foot){
			$foot='<ul class="connect-list">'.$foot.'</ul>';
		}
		$this->OUTPUT=$foot;
	}
	
}
