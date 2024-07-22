<?php

class paypal_control{
	private $SLIM;
	private $ROUTE;
	private $METHOD;
	private $LIB;
	private $IGNORE_TYPES=['subscr_eot','subscr_signup','subscr_cancel','subscr_modify','subscr_failed'];
	private $MERCHANT=[
		'id'=>0,
		'code'=>'',
		'email'=>'',
	];

	public $ACTION;
	public $REQUEST;
	
	function __construct($slim){
		$this->SLIM=$slim;
		$this->ROUTE=$slim->router->get('route');
		$this->METHOD=$slim->router->get('method');
		$this->REQUEST=($this->METHOD==='POST')?$_POST:$_GET;
		$act=issetCheck($this->ROUTE,1);
		if($this->METHOD==='POST'){
			$act=issetCheck($this->REQUEST,'action');
			if(!$act && issetCheck($this->REQUEST,'mc_gross')) $act='ipn';
		}
		$this->ACTION=$act;
		$gateway=$slim->Options->getSiteOptions('payment_gateway','OptionValue');
		$this->MERCHANT=$slim->Options->getPaypalMerchant($gateway);
		$this->MERCHANT['return']=URL.'payments/success';
		$this->MERCHANT['cancel_return']=URL.'payments/cancel';
		$this->MERCHANT['notify_url']=URL.'payments/ipn';
		$tmp=$slim->Options->get('site','payment_gateway');
		$this->LIB=new paypal($tmp['OptionValue']);
	}
	
	function process(){
		switch($this->ACTION){
			case 'pay_now':
				return $this->renderVirtualCart();
				break;
			case 'ipn':
				return $this->renderIPN();
				break;
			case 'success':
			case 'fail':
			case 'cancel':
			case 'ipn_process'://sales & email
				$rsp = new paypal_response($this->SLIM);
				return $rsp->Process($this->ACTION);	
				break;
			default:
				return null;
		}
	}
	
	private function renderVirtualCart(){
		if(!$this->REQUEST || !is_array($this->REQUEST)){
			return msgHandler ('Sorry, the cart seem to be empty...',false,false);
		}
		foreach($this->REQUEST as $i=>$v){
			if($i==='action') continue;
			$this->LIB->add_field($i,$v);
		}
		foreach($this->MERCHANT as $i=>$v){
			switch($i){
				case 'host': case 'id': case 'code':
					//skip
					break;
				case 'email':
					$this->LIB->add_field('business',$v);
					break;
				default:
					$this->LIB->add_field($i,$v);
			}			
		}
		return $this->LIB->get('vcart');
	}
	private function renderIPN(){
		$chk=$this->LIB->processIpn();
		echo $this->LIB->get('ipn_status');
		$this->sendReport($chk);
	}
	private function sendReport($state){
		$ipn_data=$this->LIB->get('ipn_data');
		if(!$ipn_data) return;
		$txn_type=issetCheck($ipn_data,'txn_type');
		if(in_array($txn_type,$this->IGNORE_TYPES)) return;
		//call processor via curl to avoid paypal IPN response error during processing
		$post=[
			'action'=>'ipn_process',
			'state'=>$state,
			'ipn_data'=>$this->LIB->get('ipn_data'),
			'ipn_status'=>$this->LIB->get('ipn_status'),
			'response_status'=>$this->LIB->get('response_status'),
		];
		$c=new slim_curl;
		$c->go(URL.'payments/ipn_process',$post);			
	}
}
