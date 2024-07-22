<?php
class shortcode_jotform_embed extends slimShortCoder{
	private $EMBED_CODE;
	private $EMBED_TITLE;
	private $EMBED_HEIGHT=25;
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
	}
	
	//required function
	function getReplace($args=false){
		$this->EMBED_CODE=issetCheck($args['args'],0);
		$this->EMBED_HEIGHT=issetCheck($args['args'],2,$this->EMBED_HEIGHT);
		$this->EMBED_TITLE=issetCheck($args['args'],1);
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
		if($this->EMBED_CODE){
			$u='https://form.jotform.com/'.$this->EMBED_CODE.'?&amp;isIframeEmbed=1';
			$o='<iframe id="JotFormIFrame='.$this->EMBED_CODE.'" title="'.$this->EMBED_CODE.'" style="height:100%; min-height:'.$this->EMBED_HEIGHT.'rem; width:100%;" onload="window.parent.scrollTo(0,0)" allowtransparency="true" allow="geolocation; microphone; camera; fullscreen" frameborder="0" scrolling="auto" src="'.$u.'" ></iframe>';
			$this->SLIM->assets->add('script','<script src="https://cdn.jotfor.ms/s/umd/latest/for-form-embed-handler.js"></script>','jotform');
			$this->SLIM->assets->add('js','window.jotformEmbedHandler("iframe[id=\'JotFormIFrame-'.$this->EMBED_CODE.'\'", "https://form.jotform.com/")','jotform-'.$this->EMBED_CODE);
			return $o;
		}else{
			return msgHandler('Sorry, a jotform code was not provided.',false,false);
		}
	}
}
