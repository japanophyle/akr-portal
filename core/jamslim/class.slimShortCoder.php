<?php

class slimShortCoder {
	protected $SLIM;
    protected $content;
    protected $assets;
    protected $tags;
    protected $RAW;
    protected $PATTERNS;
    protected $ROUTE;
    protected $AJAX;
    protected $LANGUAGE;
    protected $basicTags;
    protected $complexTags;   
    
    public $ADMIN;
    public $replaceAll=false;
    public $REFRESH_CACHE;
    public $SITEOPTS;
    public $CONTENT_TYPES;
    public $SIDEBAR;
    public $CONTENT;
    public $FIND;
	public $TAG;
	public $USER;

	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->ROUTE=$slim->router->get('route');
		$this->AJAX=$slim->router->get('ajax');
		$this->LANGUAGE=$slim->language->get('_LANG');
		$this->setPatterns();
		$this->init();
	}
	
	protected function setPatterns(){
		$CO='\{::';
		$CE='::\}';
		$this->PATTERNS['brace']='/'.$CO.'(.*?))'.$CE.'/';
		$CO='\[::';
		$CE='::\]';
		$this->PATTERNS['square']='/'.$CO.'(.*?)'.$CE.'/';
	}
    protected function init($ctypes=false, $opts=false) {
        $this->basicTags = array('books', 'publishers');
        $this->complexTags = array('book', 'publisher', 'page');
        
        $this->ADMIN = checkAdmin();
        $this->REFRESH_CACHE = ($this->ADMIN) ? getRequestVars('recache') : false;
        $this->SITEOPTS = $this->SLIM->Options->get('site','value');
 		$this->CONTENT_TYPES = $this->SLIM->DataBank->get('content_types',array('key'=>false, 'raw'=>1, 'rev'=>1));
   }

    public function Process($txt = false,$sidebar=false) {
		//process all tags
        $this->SIDEBAR = $sidebar;
        if (!$txt) return false;
        $this->CONTENT = $txt;
        $this->getTagz();
        $this->swapTagz();
        $this->removeTags();
        return $this->CONTENT;
    }
    public function ProcessTag($txt = false,$sidebar=false,$tags=false){
		//process a single tag
       $this->SIDEBAR = $sidebar;
        if (!$txt) return false;
        $this->CONTENT = $txt;
        if($tags){
			$this->tags=$tags;
			$this->swapTag();
			$this->removeTags();
		}
        return $this->CONTENT;
    }
   
    function shortCodeScan($content){
        //gets all square tags in the string
        $out=[];
        preg_match_all('/\[::(.*?)::\]/', $content, $results);
        if($results){
			$out[0]=$results[0];
			$out[1]=$results[1];
			foreach($results[1] as $code){
				if(strpos($code,'(')){				
					$x1 = explode('(', $code);
					$tag=$x1[0];
				}else{
					$tag=$code;
				}
				$out[2][]=$tag;
			}
		}
        return $out;
	}

    protected function getTagz() {
        //gets all tags in the string
        preg_match_all($this->PATTERNS['square'], $this->CONTENT, $results);
        $this->tags = $results;
    }

    protected function swapTagz() {
       $tags = $this->tags[1];
       foreach ($tags as $i => $v) {
			if($v && $v!=''){
				preg_match($this->PATTERNS['brace'], $v, $result);
				if (!empty($result)) {
					$v = str_replace($result[0], '', $v);
				}
				$this->replaceTag($v);
			}
		}
    }
    protected function swapTag() {
        $tag = $this->tags[1];
		if($tag && $tag!=''){
			return $this->replaceTag($tag);
		}
    }

    protected function removeTags() {
        //removes tags from string - used to clean up unmatched tags
        $tags = $this->tags[0];
        if(is_array($tags)){
			foreach ($tags as $i => $v) {
				$this->CONTENT = str_replace($v, '', $this->CONTENT);
			}
		}
    }

    protected function splitTag($tagName) {
        $tag = $tagName;
        $args = [];
        if (strpos($tagName, '(')) {
            $x1 = explode('(', $tagName);
            $tag = $x1[0];
            $x2 = str_replace(')', '', $x1[1]);
            if (strpos($x2, ',')) {
                $x3 = explode(',', $x2);
                foreach ($x3 as $v)
                    if (issetOR($v))
                        $args[] = $v;
            }else {
                $x2 = trim($x2);
                if (issetOR($x2))
                    $args[] = $x2;
            }
        }
        $out['tag'] = $tag;
        $out['args'] = $args;
        return $out;
    }
    
    protected function doReplace($args){
		if(is_array($args)){
			if ($this->replaceAll) {
				$out = $args['rp'];
			} else {
				if(is_null($args['rp'])) $args['rp']=msgHandler('* no content found - are you sure you can do this? *','warning',false);
				$finds=['<p>' . $this->FIND . '<br></p>','<p>' . $this->FIND . '</p>',$this->FIND];
				$out = str_replace($finds, $args['rp'], $this->CONTENT);
			}
			$this->CONTENT=$out;
			$this->RAW=issetCheck($args,'cnt');
			foreach($args as $i=>$v){
				if($v){
					if(in_array($i,array('js','jqd','css','script'))){
						$this->SLIM->assets->add($i,$this->TAG,$v);
					}
				}
			}
			return true;
		}else{
			return false;
		}
    }
    protected function initReplace($tagName){
		if ($tagName == '') {
            throw new Exception('Shortcoder error: no code supplied');
        }
		$this->FIND='[::' . $tagName . '::]';
		return $this->splitTag($tagName);    
    }

    protected function replaceTag($tagName, $slug = false){
		$js = $jqd = $script = false;
		$init=$this->initReplace($tagName);
		$this->TAG=$init['tag'];
		
		// extended class method must return array
		$chk=$this->getReplace($init);
		if(!$chk||!is_array($chk)){
			throw new Exception('Shortcoder error: invalid response from method ['.$tagName.']<br/>Response: '.$chk);
		}
		return $this->doReplace($chk);
    }
 
	function doShortPro($content = false, $popStyle = false, $blocks = false) {
		$js=$jqd=$tabHeight=$css=false;
		if (!$content)
			$content = msgHandler("Sorry, you should not be here...", false, 4);
		//display 
		if (is_array($content)) {
			if((int)$blocks>1){
				$display['content'] = $content['content'] ;
			}else{
				$content = ($blocks) ? popBlocks($content,$blocks) : popTabs($content);
				$display['content'] = "<div id=\"popHolder\"><div id=\"poplayout\">\n" . $content['content'] . "</div><div id=\"popSplash\"></div>\n</div>\n";
			}
			$js=$content['js'];
			$jqd=$content['jqd'];
		} else {
			$display['content'] = $content;
		}
		if($popStyle && $tabHeight) $popStyle = str_replace('{tabheight}', $tabHeight . 'px', $popStyle);
		return array('content' => $popStyle . $css . $display['content'], 'js' => $js, 'jqd' => $jqd);
	} 
    
    //example function - extend class to replace this.
    public function getReplace($args){
		$out['cnt']='## sample ##';
		$out['rp'] = '<div class="callout bg-red">## sample: this is a holding function which needs to be replaced ('.$this->TAG.') ##</div>';
		$out['find'] = '<p>' . $this->FIND . '</p>';
		return $this->doReplace($out);
    }
 
    
}
