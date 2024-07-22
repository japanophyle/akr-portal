<?php

class paypal {
    private $HOSTS;
    private $ipn_url;
    private $PLOG;
    private $verificationStatus;
    private $timeout=30;
    private $response;
    private $responseStatus;
    private $postData;
    
    public $useSandbox;
	var $last_error; // holds the last error encountered
	var $ipn_log; // bool: log IPN results to text file?
	var $ipn_log_file; // filename of the IPN log
	var $paypal_log_file; // filename of the error log
	var $ipn_data = []; // array contains the POST values for IPN

	var $cart = []; // array holds the fields to submit to paypal

  
	function __construct($host=false){
		global $container;
		$this->last_error = '';
		$this->ipn_log_file = CACHE.'log/ipn_log_'.date('Y-m').'.log';
		$this->paypal_log_file = CACHE.'log/paypal_log_'.date('Y-m').'.log';
		$this->ipn_log = true;
		$this->PLOG=new jamLog($this->paypal_log_file,true);
		// populate $cart array with a few default values. 
		$this->add_field('rm',2); // Return method = POST
		$this->add_field('cmd','_cart');
		$this->add_field('upload',1);
		$this->HOSTS=$container->Options->getPaypalMerchant('hosts');
		$this->ipn_url=URL.'payments/ipn';
		$this->useSandbox=$host;
	}
//Cart Stuff  
	function add_field($field, $value) {     
		// adds a key=>value pair to the fields array
		if($field==='host') return;
		$this->cart[$field] = $value;
	}
	private function virtual_cart() {
		if(!$this->cart) return msgHandler('Sorry, the cart seems to be empty...',false,false);
		//$cart = '<body onLoad="document.form.submit();">';
		$cart = '<center><h3>Please wait, your order is being processed...</h3>';
		$cart .= '<form method="post" name="form" action="'.$this->getPaypalHost().'">';
		foreach ($this->cart as $name => $value) {
			$cart .='<input type="hidden" name="'.$name.'" value="'.$value.'">';
		}
		$cart.='<button class="button button-olive" type="submit">Click here if you are not redirected after 5 seconds</button>';
		$cart .='</form></center>';
		return $cart;
	}
	private function virtual_cart_dump() { 
		// Used for debugging, this function will output all the cart field/value pairs
		$cart='<h3>Paypal Virtual Cart</h3>';
		if(!$this->cart){
			$cart.=msgHandler('Sorry, the cart seems to be empty...',false,false);
		}else{
			$fields=$this->cart;
			ksort($fields);
			$cart.='<table width="95%" border="1" cellpadding="2" cellspacing="0">
				<tr>
				   <td bgcolor="black"><b><font color="white">Field Name</font></b></td>
				   <td bgcolor="black"><b><font color="white">Value</font></b></td>
				</tr>';

			foreach ($fields as $key => $value) {
				$cart.='<tr><td>'.$key.'</td><td>'.urldecode($value).'&nbsp;</td></tr>';
			}
			$cart.='</table><br>';
		}
		return $cart;
	}
//IPN Stuff
	public function processIpn($postData = null){
		if ($postData === null) {
			$postData = $_POST;
			if(!$postData) $postData=$this->getRawPost();
		}		
		$verified=$this->verify_ipn($postData);
		if(!$verified) $this->PLOG->save();
		return $verified;
	} 
	private function getRawPost(){
		$raw_post_data = file_get_contents('php://input');
		$raw_post_array = explode('&', $raw_post_data);
		$myPost = array();
		foreach ($raw_post_array as $keyval) {
		  $keyval = explode ('=', $keyval);
			if (count($keyval) == 2){
				$myPost[$keyval[0]] = urldecode($keyval[1]);
			}
		}
		return $myPost;
	}
	
	private function verify_ipn($postData = null) {
        if ($postData === null || empty($postData)) {
            $this->PLOG->log('No IPN data to verify');
            $this->verificationStatus = 'NO_DATA';            
            //throw new Exception("No POST data found.", 103);
            return false;
        }
        
		$this->postData = $postData;
        $this->PLOG->log('Verifying IPN');

		$post_string = '';
		$charset=issetCheck($this->postData,'charset');
		foreach ($this->postData as $field=>$value) {
			$this->ipn_data[$field] =($charset && $charset!=='utf-8')? mb_convert_encoding($value, 'utf-8', $charset): $value;
			$post_string .= $field.'='.urlencode($value).'&';
		}
		$this->ipn_data['charset'] = 'utf-8';
		$this->ipn_data['charset_original'] = $charset;
		$post_string.="cmd=_notify-validate"; // append ipn command
		$this->curlPost($post_string);
		$state=false;
        if($this->responseStatus!=200) {
            $this->PLOG->log('Unexpected IPN verification response status: '.$this->responseStatus);
            $this->verificationStatus = 'ERROR';            
            //throw new Exception("Unexpected response status: ".$this->responseStatus, 104);
        }else if(strpos($this->response, "VERIFIED") !== false) {
            $this->verificationStatus = 'VERIFIED';
            $this->PLOG->log('IPN Verified');            
            $state=true;
        }else if(strpos($this->response, "INVALID") !== false) {
            $this->verificationStatus = 'INVALID';
        }else{
            $this->PLOG->log("Unexpected IPN verification response content: \n".$this->response);
            $this->verificationStatus = 'ERROR';
            //throw new \Exception("Unexpected response from PayPal.", 105);
        }
        $this->log_ipn_results();
        return $state;    
	}
  
	private function curlPost($encodedData){
		//use slim_curl
        $c=new slim_curl;
		$c->go($this->getPaypalHost(),$encodedData);
		$this->response = $c->get('response');			
        $this->responseStatus = (int)$c->get('status');
        $err=$c->get('error');
        if($err['no']){
			$this->PLOG->log('cURL error while verifying IPN [errno = '.$err['no'].', error - '.$err['desc'].']');
		}
    }
	private function log_ipn_results() {      
		if(!$this->ipn_log) return; // is logging turned off?
		if(!$this->ipn_data) return;
		// Timestamp
		$text = '['.date('m/d/Y g:i A').'] - ';
		// Success or failure being logged?
		if ($this->verificationStatus==='VERIFIED'){
			$text .= "SUCCESS!\n";
		}else{
			$text .= 'FAIL: '.$this->verificationStatus."\n";
		}
		// Log the POST variables
		$text .= "IPN POST Vars from Paypal:\n";
		foreach ($this->ipn_data as $key=>$value) {
			$text .= "$key=$value, ";
		}
		// Log the response from the paypal server
		$text .= "\nIPN Response from Paypal Server:\n ".$this->response;
		// Write to log
		$lg=new jamLog($this->ipn_log_file,true);
		$lg->log($text);
		$lg->save();
	}
//Common Stuff
	public function get($what=null){
		$out=null;
		switch($what){
			case 'response':
				$out=$this->response;
				break;
			case 'response_status':
				$out=$this->responseStatus;
				break;
			case 'ipn_data':
				$out=$this->ipn_data;
				break;
			case 'vcart':
				$out=$this->virtual_cart();
				break;
			case 'vcart_dump':
				$out=$this->virtual_cart_dump();
				break;
			case 'ipn_status':
				$out=$this->verificationStatus;
				break;
		}
		return $out;
	}
    private function getPaypalHost(){
		if(!array_key_exists($this->useSandbox,$this->HOSTS)) $this->useSandbox='local';
		$h=$this->HOSTS[$this->useSandbox];
		return ($this->useSandbox==='local')?$h:'https://'.$h.'/cgi-bin/webscr';
    }
} 
