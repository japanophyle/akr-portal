<?php

class paypal_ipn_process{
	private $SLIM;
	private $RESPONDER;
	private $METHOD;
	private $REQUEST;
	private $IGNORE_TYPES=['subscr_eot','subscr_signup','subscr_cancel','subscr_modify','subscr_failed'];
	private $KEYS=['TXN_TYPE','TXN_ID','TEST_IPN','PAYMENT_STATUS'];
	private $LOG;
	var $DEBUG=false;
	var $TEST_IPN;//from paypal sandbox
	var $NOTIFY=true;
	var $NOTIFY_FAILS=false;
	var $NOTIFY_STAGE='ipn';
	var $NOTIFY_DEV=false;
	var $POST=false;
	var $PAYMENT_STATUS=false;
	var $IPN_RESPONSE=false;
	var $TXN_TYPE;
	var $TXN_ID;

	function __construct($slim=null){
		if(!$slim) throw new Exception('no slim object!!');
		$this->SLIM=$slim;
		$this->METHOD=$slim->router->get('method');
		$this->LOG=new jamLog(CACHE.'log/ipn_process_'.date('Y_m').'.log',true);
	}

	function Process($args=false){
		$this->LOG->log($this->METHOD);
		$this->setRequest($args);
		if($this->REQUEST){
			$this->LOG->log('TXN: '.$this->TXN_ID);
			if($this->TXN_ID) $this->sendReport();
		}
		$this->LOG->save();
	}
	function setRequest($args=false){
		$this->REQUEST=false;
		if(!$args && $this->METHOD==='POST'){
			$args=$_POST;
			if(isset($args['ipn_data'])) $args=$args['ipn_data'];
			$this->LOG->log('$_POST: '.json_encode($_POST));
		}
		if(!is_array($args)) return;
		$this->REQUEST=$args;
		foreach($this->KEYS as $k){
			$def=$this->$k;
			$this->$k=issetCheck($args,strtolower($k),$def);
		}
	}
	private function sendReport(){
		if(in_array($this->TXN_TYPE,$this->IGNORE_TYPES)) return;
		if(!$this->RESPONDER){
			$this->RESPONDER=$this->SLIM->Paypal_response;
		}
		$this->RESPONDER->NOTIFY=$this->NOTIFY;
		$this->RESPONDER->NOTIFY_FAILS=$this->NOTIFY_FAILS;
		$this->RESPONDER->OUTPUT_HTML=false;
		$this->RESPONDER->POST=$this->REQUEST;
		$this->RESPONDER->IPN_STATUS=($this->PAYMENT_STATUS==='Completed')?'VERIFIED':'FAILED';
		$response=$this->RESPONDER->Process('ipn_response');
		$this->LOG->log('sendReport(ipn_response)');		
		//$response should be an array: status and tid(IPN Record ID)
		if($this->DEBUG) preME($response,2);
	}	
} 
