<?php
//this page is for processing the https response from paypal
//redirect user to main site pages after processing:
// page/papyalgood/?vars=*** or  page/papyalfail/?vars=***

class slimPayments{
	private $SLIM;
	private $METHOD;
	private $AJAX;
	private $REQUEST;
	private $PERMLINK;
	private $ROUTE;
	
	private $SECTION;
    private $ACTION;
	private $NOTIFY=true;
	private $NOTIFY_FAILS=true;
	private $NOTIFY_STAGE='ipn';
	private $NOTIFY_DEV='roger@jamtechsolutions.co.uk';//for errors
		
	function __construct($slim=null){
		define('MP_RESPONDER',1);
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->METHOD=$slim->router->get('method');
		if(!$this->METHOD) $this->METHOD='GET';
		$this->ROUTE=$slim->router->get('route');
		if($this->ROUTE && $this->ROUTE[0]!=='payments'){
			$rt=array();
			foreach($this->ROUTE as $r){
				if($r==='payments'){
					$rt[]=$r;
				}else if($rt){
					$rt[]=$r;
				}
			}
			if($rt) $this->ROUTE=$rt;
		}
	}
	
	private function setVars(){
		$page=issetCheck($_GET,'page');
		$this->SECTION=issetCheck($this->ROUTE,1,$page);
		if(!$this->SECTION && isset($_POST['payer_id'])) $this->SECTION='ipn';
		$this->ACTION=issetCheck($this->ROUTE,2);
	}
	
	public function render(){
		$this->setVars();
		if($this->METHOD==='POST'){
			// do somthing
		}
		switch($this->SECTION){
			case 'ipn_sim':
				$this->renderIPN_sim();
				die;
				break;		
			case 'ipn':
				$gateway=$this->SLIM->Options->get('site','site_payment_paypal_gateway');
				$gate=issetCheck($gateway,'opt_Value');
				$obj = New paypal_control($this->SLIM);
				$obj->ACTION='ipn';
				$obj->process();
				die;
				break;
			case 'ipn_process':
				$RSP= new paypal_ipn_process($this->SLIM);
				$RSP->Process();
				die;
				break;
			case 'fail':
			case 'success':
			case 'cancel':
				$title='Payments - '.$this->SECTION;
				$RSP= $this->SLIM->Paypal_response;
				$RSP->NOTIFY=$this->NOTIFY;
				$RSP->NOTIFY_FAILS=$this->NOTIFY_FAILS;
				$RSP->OUTPUT_HTML=false;
				$RSP->Process($this->SECTION);
				//should have redirected to the main site response page by now
				break;
			default:
				die($this->SECTION.'... no,no,no!');
		
		}
	}
	private function renderIPN_sim(){
		$template='parts/tpl.ipn_simulator.php';
		$init['post'] = $_POST;
		$init['gate'] = 'paypal';
		$init['page'] = issetCheck($_GET,'page');
		$init['ipn'] = 1;
		//for simulator only
		$init['http_path'] = URL;
		$init['home_page'] = 'page/home';
		$SIM = new ipn_Simulator($this->SLIM,$init);
		$SIM->NOTIFY=$this->NOTIFY;
		$SIM->NOTIFY_FAILS=$this->NOTIFY_FAILS;
		$SIM->NOTIFY_STAGE=$this->NOTIFY_STAGE;
		$output=$SIM->Process();
		
		$title=$output['title'];
		$content=$output['contents'];
		$parts['base_ref']=URL;
		$scripts='';
		if(issetCheck($output,'js')){
			$scripts.=$output['js'];
		}
		if(!$content){
			$content=msgHandler('Sorry, you should not be seeing this... close your eyes!');
		}
		$parts['title']=$title;
		$parts['body']=$content;
		$parts['scripts']=$scripts;

		$_template=file_get_contents(TEMPLATES.$template);
		$HTML=fillTemplate($_template,$parts);
		echo $HTML;
		die;
	}	
}
