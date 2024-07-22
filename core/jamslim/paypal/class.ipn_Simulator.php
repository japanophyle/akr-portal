<?php

class ipn_Simulator {
	private $SLIM;
	var $IPN_DATA=[];
	var $IPN_RESPONSE=false;
	var $OPTIONS=false;
	var $HTTP_PATH=false;
	var $RESET_URL=false;
	var $HOME_PAGE=false;
	var $OUTPUT=[];
	var $POST=false;

	var $PAYPAL_URL = false;
    var $MERCHANT_CODE=false;
    var $MERCHANT_EMAIL=false;
    var $MERCHANT_CANCEL=false;
    var $MERCHANT_SUCCESS=false;
    var $MERCHANT_IPN=false;
    var $MERCHANT_CURRENCY='GBP';
    var $MERCHANT_SHIPPING=1;
    
    var $NOTIFY_STAGE='ipn';
    var $NOTIFY_FAILS=true;
	var $NOTIFY=true;
	var $IPN;
	private $RESPONDER;
	private $lastError;
	
    function __construct($slim=null,$options = false) {
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
        $this->OPTIONS = $options;
        $this->initVars();
    }
   
    function initVars(){
		$dev_url=issetCheck($this->SLIM->config,'DEV_URL','jamserver');
		$this->HTTP_PATH=$this->OPTIONS['http_path'];
        $this->HOME_PAGE=$this->OPTIONS['http_path'].$this->OPTIONS['home_page'];
        $this->RESET_URL=$this->OPTIONS['http_path'].'page/payments/ipn_sim/?act=start';
        $this->OUTPUT['js']='';        
        $this->POST=$this->OPTIONS['post'];

		$MERCHANT=$this->SLIM->Options->getPaypalMerchant('local');

		$this->PAYPAL_URL = URL.'payments/ipn_sim';
		$this->MERCHANT_CODE = $MERCHANT['code'];
		$this->MERCHANT_EMAIL = $MERCHANT['email'];
		$this->MERCHANT_CANCEL = URL.'payments/cancel';
		$this->MERCHANT_SUCCESS = URL.'payments/success';
		$this->MERCHANT_IPN = URL.'payments/ipn_sim';
		$this->MERCHANT_CURRENCY =  'GBP';
	}

    function Process() {
       if ($this->MERCHANT_CODE) {
            if($this->POST){
				if(issetCheck($this->POST,'txn_id')){
					$test=$this->validateIPN();
					if($test){
						$echo='SUCCESS';
					} else {
						$echo='FAILURE<br/>'.$this->IPN_RESPONSE;
					}
					if($this->NOTIFY_STAGE==='ipn') $this->sendReport();
					echo $echo;
					die();										
				}else{//show payment gateway
					$this->setIPN($this->POST);
                    $this->paymentGateway();
                }
            }else{
				echo 'no post data found';
			}
        }else {
            echo 'no merchant! - '.$this->OPTIONS['gate'];
        }
        $this->OUTPUT['title']='Paypal Payment Simulator';
        return $this->OUTPUT;
    }

    function paymentGateway() {
        $tr='';
        // generate the post string from the _POST vars
        $http=$this->HTTP_PATH;
        $ps = '<input type="hidden" name="cmd" value="_send-validate"/>';
        $ipn = $this->IPN;
        $this->OUTPUT['nav']='<li class="menu-item"><a href="'.$this->RESET_URL.'">Start Sim</a></li><li class="menu-item"><a href="'.$this->HOME_PAGE.'">Homepage</a></li>';
        foreach ($ipn as $field => $value) {
            $tr.= '<tr><th style="text-align:left;">' . $field . '</th><td>' . $value . '</td></tr>';
            $ps.='<input type="hidden" name="' . $field . '" value="' . $value . '"/>';
        }
        $form = '<form id="payform" method="post" target="poster" action="' . $this->PAYPAL_URL . '">' . $ps . '<input class="button small round success" type="submit" value="Make Payment"/> <button type="button" class="button small round _return" href="' .$this->MERCHANT_SUCCESS. '">return Success</button> <button type="button" class="button small round alert _return" href="' .$this->MERCHANT_CANCEL. '">return Failed</button></form>';
        $out= '<h2>Payment Gateway Simulator</h2><table id="simulator" ><tr><td>';
        $out.= '<iframe id="poster" name="poster" src=""></iframe>';
        $out.= $form . '</td><td>';
        $out.= '<div style="height:450px; overflow:auto;"><table>' . $tr . '</table></div>';
        $out.=  '</td><tr></table>';
        $this->OUTPUT['contents']=$out;
        $this->OUTPUT['js']='$("._return").on("click",function(e){e.preventDefault(); var rurl=$(this).attr("href");  $("#payform").attr({"action":rurl,target:"_self"}).submit();});'; 
    }

    function setIPN($post) {
        $qt=0;
        foreach ($post as $i => $v) {
            if (strpos($i, 'quantity_') === 0) $qt++;
        }
        $totQty = $totVal = 0;
        for ($x = 1; $x <= $qt; $x++) {
            $ipn['item_name' . $x] = $post['item_name_' . $x];
            $ipn['item_number' . $x] = $post['item_number_' . $x];
            $ipn['mc_gross_' . $x] = $post['amount_' . $x];
            $ipn['mc_handling' . $x] = 0.00;
            $ipn['mc_shipping' . $x] = 0.00;
            $ipn['quantity' . $x] = (int) $post['quantity_' . $x];
            $ipn['tax' . $x] = 0.00;

            $transSub[] = $post['item_name_' . $x];
            $totQty+=(int) $post['quantity_' . $x];
            $totVal+=(float) ($post['quantity_' . $x] * $post['amount_' . $x]);
        }

        $ipn['transaction_subject'] = implode(' and ', $transSub);
        $ipn['mc_gross'] = $totVal;
        $ipn['num_cart_items'] = $totQty;
        $ipn['custom'] = $post['custom'];
        $ipn['test_ipn'] = 1;
        $ipn['payment_date'] = date('H:i:s M d, Y') . ' PDT'; //'08:11:15 Jul 12, 2013 PDT';
        $ipn['txn_id'] = 'MYIPN' . date('Ymd-His'); //'85990831XK739241R';
        $ipn['ipn_track_id'] = '3e23d2c5a3c4e';

        $ipn['business'] = 'fonkeyman-facilitator@hotmail.com';
        $ipn['charset'] = 'windows-1252';
        $ipn['first_name'] = $post['first_name'];
        $ipn['last_name'] = $post['last_name'];
        $ipn['mc_currency'] = 'GBP';
        $ipn['mc_fee'] = 1.27;
        $ipn['notify_version'] = 3.7;
        $ipn['payer_email'] = 'jamdevbuy@hmamail.com';
        $ipn['payer_id'] = 'STH8U2Z488PGE';
        $ipn['payer_status'] = 'verified';
        $ipn['payment_fee'] = '';
        $ipn['payment_gross'] = '';
        $ipn['payment_status'] = 'Completed';
        $ipn['payment_type'] = 'instant';
        $ipn['protection_eligibility'] = 'Ineligible';
        $ipn['receiver_email'] = 'fonkeyman-facilitator@hotmail.com';
        $ipn['receiver_id'] = 'VN79NNBLBBZ6W';
        $ipn['residence_country'] = 'GB';
        $ipn['txn_type'] = 'cart';
        $ipn['verify_sign'] = 'ACUe-E7Hjxmeel8FjYAtjnx-yjHAATXgGHOHobdcz6OLJ7WA8L2MXAnN';
        $this->IPN = $ipn;
    }

    function validateIPN() {
         // parse the paypal URL
        $urlParsed = parse_url($this->PAYPAL_URL);
        
        // generate fake response
        foreach ($this->OPTIONS['post'] as $field => $value) {
            $this->IPN_DATA[$field] = $value;
        }
        
        // fraud test 
        $this->testFraud();
         if ( preg_match("/VERIFIED/", $this->IPN_RESPONSE)) {
            // Valid IPN transaction.
            return true;
        } else {
            // Invalid IPN transaction.  Check the log for details.
            $this->lastError = "IPN Validation Failed . $urlParsed[path] : $urlParsed[host]";
             return false;
        }
    }
    
    function testFraud() {
        $post=$this->IPN_DATA;
        $errmsg = false;   // stores errors from fraud checks
        
        //fraud check these values

        // 1. Make sure the payment status is "Completed" 
        if ($post['payment_status'] !== 'Completed') {
            // simply ignore any IPN that is not completed
            $this->IPN_RESPONSE = 'FRAUD WARNING:<br/>Payment not completed';
            exit(0);
        }

        // 2. Make sure seller email matches your primary account email.
        if ($post['receiver_email'] !== $this->MERCHANT_EMAIL) {
            $errmsg .= "'receiver_email' does not match: ";
            $errmsg .= $post['receiver_email'] . "<br/>";
        }

        // 3. Make sure the amount(s) paid match
        if (!$post['mc_gross']|| $post['mc_gross']<1) {
            $errmsg .= "'mc_gross' value is invalid: ";
            $errmsg .= $post['mc_gross'] . "<br/>";
        }

        // 4. Make sure the currency code matches
        if ($post['mc_currency'] != $this->MERCHANT_CURRENCY) {
            $errmsg .= "'mc_currency' does not match: ";
            $errmsg .= $post['mc_currency'] . "<br/>";
        }
        // 5. test transaction id
        if($errmsg){
			$this->IPN_RESPONSE = 'FRAUD WARNING:<br/>' . $errmsg;
		}else{
			$this->IPN_RESPONSE = 'VERIFIED';
		}
    }   
	
	function sendReport(){
		$RSP= new paypal_ipn_process($this->SLIM);
		$RSP->Process();
	}

}
