<?php
class slimTemplates{
	private $SLIM;
    protected $templatePath=false; //string
    protected $attributes=array(); //array
    protected $template=false; //string
    protected $mode='php';//template replace mode. php (<?=$foo ? >) or brace ({foo})

    function __construct($slim){
        $this->SLIM=$slim;
        $this->setTemplatePath(TEMPLATES);
        $this->setTemplate($slim->config['TEMPLATES']['public']);
    }

    public function render($data=false,$template=false,$mode=false){
		if($mode) $this->setMode($mode);
		if($template) $this->setTemplate($template);
		if($data) $this->addAttribute($data);
		if($this->mode=='brace'){
			return $this->fill();
		}else{
			return $this->fetch();
		}		
    }
    
    public function get($what=false,$vars=false){
		switch($what){
			case 'template':
				return $this->getTemplate($vars);
				break;
			case 'path':
				return $this->templatePath.$this->template;
				break;
			case 'render':
				return $this->render($vars);
				break;
			default:
				if($what){
					$t=issetCheck($this->attributes,$what);
					if($t && $vars)	$t=issetCheck($t,$vars);
					return $t;
				}
		}
		return false;
	}
    public function set($what=false,$vars=false){
		switch($what){
			case 'template':
				$this->setTemplate($vars);
				break;
			case 'path':
				$this->setTemplatePath($vars);
				break;
			case 'data':
				$this->setAttributes($vars);
				break;
			case 'mode':
				$this->setMode($vars);
				break;
			default://set attribute
				$this->addAttribute($what,$vars);
		}
	}
   
    private function setMode($mode=false){
		$modes=array('php','brace');
        if(!in_array($mode,$modes)) $mode='php';
        $this->mode = $mode;
    }

    private function setAttributes($attributes=false,$reset=false){
		if($reset) $this->attributes=array();
        if($attributes && is_array($attributes)){
			$this->addAttribute($attributes);
		}
    }
    private function setTemplatePath($p=false){
		$this->templatePath=$p;
	}
    private function setTemplate($t=false){
		$this->template=$t;
	}

    private function addAttribute($key=false, $value=false){
		if($key){
			if(is_array($key)){
				foreach($key as $i=>$v){
					if(is_string($i)) $this->attributes[$i] = $v;
				}
			}else if(is_string($key) && $key!==''){
				$this->attributes[$key] = $value;
			}
		}
    }

    
    private function prepFetch(){
		if (isset($this->attributes['template'])) {
            throw new Exception(__METHOD__.": Duplicate template key found");
        }
        $p=$this->get('path');
        if (!is_file($p)) {
            throw new Exception(__METHOD__.": cannot render `{$p}` because the template does not exist");
        }
 	}
	private function getTemplate($path=null){
		$tpl='';
		if(!$path)$path=$this->get('path');
		if(trim($path)!==''){
			if(file_exists($path)){
				$tpl=file_get_contents($path);
			}
		}
		return $tpl;
	}
	private function fill(){
		$this->prepFetch();
		$tpl=$this->getTemplate();
		$output=replaceMe($this->attributes,$tpl);
		return $output;		
	}
	
    private function fetch(){
		$this->prepFetch();
		$tpl=$this->get('path');
		
        try {
            ob_start();
            $this->protectedIncludeScope($tpl, $this->attributes);
            $output = ob_get_clean();
        } catch(Throwable $e) { // PHP 7+
            ob_end_clean();
            throw $e;
        } catch(Exception $e) { // PHP < 7
            ob_end_clean();
            throw $e;
        }

        return $output;
    }

    private function protectedIncludeScope ($template=false,$data=false){
		if(is_array($data))  extract($data);
        include func_get_arg(0);
    }
}
