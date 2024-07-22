<?php
class shortcode_google_embed extends slimShortCoder{
	private $EMBED_URL;
	private $EMBED_TITLE;
	private $EMBED_HEIGHT=25;
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
	}
	
	//required function
	function getReplace($args=false){
		$this->EMBED_URL=issetCheck($args['args'],0);
		$this->EMBED_HEIGHT=issetCheck($args['args'],1,$this->EMBED_HEIGHT);
		$this->EMBED_TITLE=issetCheck($args['args'],2);
		if($this->EMBED_URL){
			if(strpos($this->EMBED_URL,'google.com')===false){
				$this->EMBED_URL=null;
			}else if(strpos($this->EMBED_URL,'embedded=true')===false){
				$e=(strpos($this->EMBED_URL,'?')===false)?'?embedded=true':'&embedded=true';
				$this->EMBED_URL=$this->EMBED_URL.$e;
			}
		}
		$content['cnt']=$this->renderBlock();
		$content['script']=false;
		$content['jqd']=false;
		$content['js']=false;
		$content['rp'] = $content['cnt'];
		$content['find'] = '<p>' . $this->FIND . '</p>';
		return $content;
	}	
	private function renderBlock(){
		$frame=$this->renderFrame();
		$t=($this->EMBED_TITLE)?'<h3>'.$this->EMBED_TITLE.'</h3>':'';
		return $t.'<div class="grid-x grid-padding-x block"><div class="cell">'.$frame.'</div></div>';
	}
	private function renderFrame(){
		if($this->EMBED_URL){
			$o='<iframe style="height:100%; min-height:'.$this->EMBED_HEIGHT.'rem; width:100%;" sandbox="allow-scripts allow-popups allow-forms allow-same-origin allow-popups-to-escape-sandbox allow-downloads allow-modals allow-storage-access-by-user-activation" frameborder="0" aria-label="Calendar, Practice Calendar" src="'.$this->EMBED_URL.'" allowfullscreen=""></iframe>';
			return $o;
		}else{
			return msgHandler('Sorry, a google url was not provided.',false,false);
		}
	}
}
