<?php

class slimLogger{
	private $SLIM;
    private $LOG = false;
    private $LOGFILE = false;//the logfile name or title
    private $LOGFILE_ROOT=false;
    private $LOGFILE_STAMP = false;//a suffix to the lofgile
    private $LEVELS = array(
        'EMERGENCY' => 0,
        'ALERT'     => 1,
        'CRITICAL'  => 2,
        'ERROR'     => 3,
        'WARNING'   => 4,
        'NOTICE '   => 5,
        'INFO'      => 6,
        'DEBUG'     => 7
    );
    private $LEVEL = 'ERROR';
    private $LOG_FORMAT=false;//template for log formatting
    private $STAMP_FORMAT='Y-m-d G:i:s.u';
    private $WRITE_TO='log';//file, log,console
    private $AJAX;
    private $BAR;
    private $AUTO_LOG=0;//turns file logging on/off from super option "Logging"
    
    function __construct($slim=null,$args=false){
		if(!$slim) throw new Exception('the slim object is missing...');
		$this->SLIM=$slim;
		$this->BAR=$slim->debugger;
		$this->AJAX=$slim->AppVars->get('ajax');
		$p=$this->SLIM->options->get('super','Logging');
		$this->AUTO_LOG=(int)issetCheck($p,'OptionValue');
		$this->LOGFILE_ROOT=CACHE.'log/';
		$this->setVars($args);
	}
	
	function setVars($args=false){
		$this->LEVEL=issetCheck($args,'base_level','ERROR');
		$this->LOGFILE=issetCheck($args,'log_file','general');
		$this->WRITE_TO=issetCheck($args,'write_to','debugbar');
		if($this->AJAX) $this->WRITE_TO='debugbar';
		$today = date('Y_m_d');
		$this->LOGFILE_STAMP = '_'.date('Y_m_d').'.log';
	}
   
	private function formatMessage($level, $message, $context){
        if ($this->LOG_FORMAT) {
            $parts = array(
                'date'          => $this->getTimestamp(),
                'level'         => strtoupper($level),
                'level-padding' => str_repeat(' ', 9 - strlen($level)),
                'priority'      => $this->LEVELS[$level],
                'message'       => $message,
                'context'       => json_encode($context),
            );
            $message = $this->LOG_FORMAT;
            foreach ($parts as $part => $value) {
                $message = str_replace('{'.$part.'}', $value, $message);
            }

        } else {
            $message = "[{$this->getTimestamp()}] [{$level}] {$message}";
        }

        if (!empty($context)) {
			if(is_array($context)){
				$message .= PHP_EOL.$this->indent($this->contextToString($context));
			}else{
				$message .= PHP_EOL.$context;
			}
        }
        return $message.PHP_EOL;
    }
    
    public function autoLog(){
		$out=false;
		$log='';
		if($this->AUTO_LOG){
			if(!empty($this->LOG)){
				foreach($this->LOG as $k=>$logs){
					$log.=implode('',$logs);
				}
				$out=$this->write_to_file($log);
			}
		}
		return $out;
	}

    public function log($level, $message, array $context = array()){
        if ($this->LEVELS[$level]<=$this->LEVELS[$this->LEVEL]) {
           return;
        }
        $message = $this->formatMessage($level, $message, $context);
        if($this->AUTO_LOG){
			$this->write_to_log($message);
		}
        switch($this->WRITE_TO){
			case 'file':
				if(!$this->AUTO_LOG) $this->write_to_file($message);
				break;
			case 'console':
				$this->write_to_console($message);
				break;
			case 'debugbar':
				$this->write_to_debugbar($message);
				break;
			default:
				if(!$this->AUTO_LOG) $this->write_to_log($message);
		}
    }
    
    public function get(){
		switch($this->WRITE_TO){
			case 'file':
				$log=file_get_contents($this->LOGFILE_ROOT.$this->LOGFILE.$this->LOGFILE_STAMP);
				if(!$log) $log='Sorry, I could not find the log file ['.$this->LOGFILE.$this->LOGFILE_STAMP.'].';
				break;
			case 'console':
				$log='<script>'.implode("\n",$this->LOG[$this->LOGFILE]).'</script>';
				break;
			case 'debugbar':
				$log=($this->AJAX)?$this->BAR->get('console_debug'):$this->BAR->get('debug');
				break;
			default:
				$log=$this->LOG[$this->LOGFILE];
		}
		return $log;
	}
	
	public function set($what=false,$vars=false){
		if($what){
			$what=strtoupper($what);
			if(in_array($what,array('SLIM','LEVELS','LOG'))){
				return false;
			}else if(property_exists($this,$what)){
				$this->$what=$vars;
				return true;
			}
		}
		return false;
	}
	
	private function write_to_log($message){
		$this->LOG[$this->LOGFILE][]=$message;
		return 'logged';
	}
	private function write_to_debugbar($message){
		$this->SLIM->debugger->set('log',$message);
		return 'logged';
	}
	
    private function write_to_file($message){
        try{
			$output = 'failed';
			$c=file_put_contents($this->LOGFILE_ROOT.$this->LOGFILE.$this->LOGFILE_STAMP, $message, FILE_APPEND | LOCK_EX);
			if($c!==false) $output = 'logged';
        } catch (Exception $e) {
			throw new RuntimeException("Sorry, I can't open (" . $this->LOGFILE.$this->LOGFILE_STAMP . ") ". $e->getMessage());
        }
        return $output;
    }

	private function write_to_console($data){
		if (is_array($data) || is_object($data)) {
			$output = "console.log('PHP: ".json_encode($data)."');";
		} else {
			$output = "console.log('PHP: ".$data."');";
		}
		$this->LOG[$this->LOGFILE][]=$output;
	}    
	private function contextToString($context){
        $export = '';
        foreach ($context as $key => $value) {
            $export .= "{$key}: ";
            $export .= preg_replace(array(
                '/=>\s+([a-zA-Z])/im',
                '/array\(\s+\)/im',
                '/^  |\G  /m'
            ), array(
                '=> $1',
                'array()',
                '    '
            ), str_replace('array (', 'array(', var_export($value, true)));
            $export .= PHP_EOL;
        }
        return str_replace(array('\\\\', '\\\''), array('\\', '\''), rtrim($export));
    }
    
    private function indent($string, $indent = '    '){
        return $indent.str_replace("\n", "\n".$indent, $string);
    }
    
    private function getTimestamp(){
        $originalTime = microtime(true);
        $micro = sprintf("%06d", ($originalTime - floor($originalTime)) * 1000000);
        $date = new DateTime(date('Y-m-d H:i:s.'.$micro, $originalTime));
        return $date->format($this->STAMP_FORMAT);
    }

}
