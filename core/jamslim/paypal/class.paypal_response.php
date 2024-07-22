<?php
// this class handles the user response when returning from paypals site
class paypal_response{
	private $SLIM;
	private $USER_ID=0;
	private $IPN_ID=0;
	private $IPN_RECORD;//loaded from database
	private $CART_REF;
	private $CART_VALS;
	private $CART_USER;
	private $STATUS;
	private $CART;
	private $PDF_PATH;
	private $SETUP;
	private $ADMIN_EMAIL;
	private $CS_EMAIL;
	private $MAILBOT;
	private $LOG;	
	private $RETURN_URL;
	private $METHOD;
	private $REQUEST;
	private $POST_IPN;//paypal IPN
	private $TXN;
	private $OUTPUT;
	private $DEBUG=false;
	
	public $NOTIFY=false;
	public $NOTIFY_FAILS=false;
	public $POST;
	public $IPN_STATUS;
	public $MSG=[];
	public $OUTPUT_HTML;
	public $IPN_POSTBACK;
	
	function __construct($slim=null){
		if(!$slim) throw new Exception('no slim object!!');
		$this->SLIM=$slim;
		$this->METHOD=$slim->router->get('method');
		$this->REQUEST=($this->METHOD==='POST')?$_POST:$_GET;
		$this->SETUP=$this->SLIM->config;
		$this->LOG=new jamLog(CACHE.'log/paypal_response_'.date('Y-m').'.log',true);
		$this->initVars();
	}
    function initVars(){		
		$dev_url=issetCheck($this->SETUP,'DEV_URL');
        if($tmp=$this->SLIM->Options->getSiteOptions('email_administrator',true)){
			$this->ADMIN_EMAIL = $tmp;
		}
        if($tmp=$this->SLIM->Options->getSiteOptions('email_customer_services',true)){
			$this->CS_EMAIL = $tmp;
		}
        if($tmp=$this->SLIM->Options->getSiteOptions('email_mailbot',true)){
			$this->MAILBOT = $tmp;
		}
		$this->RETURN_URL=URL;
		if($this->METHOD==='POST'){
			$act=issetCheck($this->REQUEST,'action');
			if($act==='ipn_process'){
				$this->POST_IPN=$this->REQUEST['ipn_data'];
				$this->IPN_STATUS=$this->REQUEST['ipn_status'];
			}
		}
	}
	function Process($act=false){
		$this->LOG->log(__METHOD__.'('.$act.')');
		switch($act){
			case 'paypalgood':
			case 'paypalfail':
			case 'gatewayerror':
				$this->renderResponse($act);
				break;
			case 'ipn_process':
				$this->renderIpnResponse();
				break;
			case 'success':
			case 'fail'://back from paypal
				$this->POST=$this->REQUEST;//$_POST;
				$this->STATUS=$act;
				$this->renderReturn();
				break;
			default:
				if(!$this->POST) $this->POST=$this->REQUEST;//$_POST;
				$this->checkResponse();
				$this->setOutput();
		}
		$this->LOG->save();
		return $this->OUTPUT;
	}
	function checkResponse(){// the initial response from paypal via https
		$this->LOG->log(__METHOD__.'()');
		$this->getIpnData();
		$attach=$this->PDF_PATH;
        if(!empty($this->POST)){
		    if($this->TXN){
			   if($this->STATUS==='success'){
				   //update sales rec.
				   $sales_id=$this->updateSalesRecords();
				   if($sales_id['id']){
					   if($this->NOTIFY){
						   $attach=$this->renderPDF($this->CART_REF);
						   //notify admin			       
						   $subject='new_sale';
						   $args['cartVals']=$this->CART_VALS;
						   $args['sales_id']=$sales_id['id'];
						   $args['sales_ref']=$sales_id['code'];
						   $this->emailAdmin($subject,$args,$attach);
						   //notify user
						   $this->emailUser($subject,$args,$attach);
					   }
					   return;
				   }else{
					   //no sale found, check logs  
					   $this->STATUS==='fail'; 
				   }
			   }else{
				   //report errors at end of function
			   }			   
		   }else{
			   $this->LOG->log('The payment details recieved seem to be invalid.');
		   }
	    }else{
			$this->LOG->log('No payment details recieved from Paypal.');
		}
	    if($this->NOTIFY_FAILS){
	        $subject='sale_failed';
	        $args['cartVals']=$this->CART_VALS;
	        $args['sales_ref']=$this->CART_REF;
	        $this->emailAdmin($subject,$args,$attach);
	    }   
	}
   function getIpnData(){
	    //extract info from IPN Response
        if(!empty($this->POST)) {
		   $txn=issetCheck($this->POST,'txn_id',false);
		   $custom=issetCheck($this->POST,'custom',false);
		   $this->LOG->log('getIpnData() > $POST '.json_encode($this->POST));
		   //preME($txn,2);
		   if($txn){
			   $this->TXN=$txn;
			   $custom=explode('|',$custom);
			   $this->IPN_ID=issetCheck($custom,0);
			   $this->CART_REF=issetCheck($custom,1);
			   $this->USER_ID=issetCheck($custom,2);
			   $this->CART_USER=$this->getCartUser();
			   $this->checkValues();//this will set the STATUS
			   if($this->CART_REF){
				   $this->PDF_PATH=$this->renderPDF($this->CART_REF);
			   }
			   //log results in DB
			   $this->updateCartLog();
		   }
		}	   
   }
	private function checkValues(){
		$this->LOG->log(__METHOD__.'()');
		$msg=[];
		$data=$this->POST;
		$totals=$this->getCartValues();
		//check data keys are set
		if(!array_key_exists('mc_gross',$data)){
			$data['mc_gross']=0;
			$data['payment_status']='cancelled';
			$data['mc_currency']=false;
			$data['txn_id']=false;
		}
		//check for duplicate txn_id ??
		if($this->checkTxnExists())	$msg[]='This transaction ref already exists.';
		//completed
		if($data['payment_status']!=='Completed') $msg[]='The transaction was not completed. The status reads: '.$data['payment_status'];
		//value
		if($totals['value']!=$data['mc_gross'])	$msg[]='The amount paid does not seem to be correct. Cart Value: &pound;'.$totals['value'].'<br>Paid Value: &pound;'.$data['mc_gross'];
		
		if($msg){
			$this->STATUS='fail';
			foreach($msg as $m) $this->LOG->log($m);
		}else{
			$this->STATUS='success';
		}
		$this->CART_VALS=$totals;		
    }
	private function checkTxnExists(){
		//check for duplicate txn_id
		$rec=($this->TXN)?$this->SLIM->db->cart_log->select('TID')->where('txn_id',$this->TXN):[];
		if(count($rec)){
			return true;
		}else{
			return false;
		}
	}
	private function getCartUser(){
		$user=[];
		if($this->USER_ID){
			$rec=$this->SLIM->db->Members->where('MemberID',$this->USER_ID)->select('MemberID,FirstName,LastName,City,Country,Email,LandPhone,Dojo,CGradeName');
			$rec=renderResultsORM($rec);
			if($rec) $user=current($rec);
		}
		return $user;
	}
	private function getCartValues($list=false){
		$li='';
		$cart=[];
		$calc=new tabCalc;
		if(!$this->CART) $this->loadCart();
		$cart_items=($this->CART)?issetCheck($this->CART,'product',[]):[];
		foreach($cart_items as $i=>$v){
			if((int)$i){
				$title=$v;
				$price=$this->CART['price'][$i];
				$qty=$this->CART['qty'][$i];
				$calc->add($price,$i,$qty);
				$q=(int)$qty;
				$p=toPennies($price);
				$li.='<li>'.$title.' x '.$q.' @ '.toPounds($p,true).'</li>';
				$cart[$i]=array('name'=>$title,'qty'=>$q,'price'=>$p);
			}
		}
		$tot=$calc->totals();
		$paid=toPennies(number_format($this->POST['mc_gross'],2));
		if($list){
			$balance=($tot['balance']-$paid);
			$li.='<li><strong class="text-black">Total Value: '.toPounds($tot['balance'],true).'</strong></li>';
			$li.='<li><strong class="text-dark-blue">Total Paid: '.toPounds($paid,true).'</strong></li>';
			if($balance){
				$li.='<li><strong class="text-red">Balance: '.toPounds($balance,true).'</strong></li>';
			}else{
				$li.='<li><span class="text-olive">Balance: £0.00</span></li>';
			}	
			return($list==='cart')?$cart:$li;
		}else{
			return array('qty'=>$tot['qty'],'total'=>toPounds($tot['total']),'value'=>toPounds($tot['balance']),'list'=>$li,'cart'=>$cart);
		}
	}
 	private function renderPDF($ref=false){
		if(!$ref) return false;
		//check if exists
		$test=CACHE.'pdf/invoice_'.$ref.'.pdf';
		if(file_exists($test)) return $test;
		//render new pdf
		$path=false;
		$invoice=$this->SLIM->Sales->getInvoiceRecord('ref',$ref);
		if(!$invoice){
			$this->LOG->log('no invoice found '.$ref);
		}else{
			$INV=new slimInvoiceRender($this->SLIM);
			$path=$INV->render($invoice,'file');
		}
		if(!file_exists($path)) $path=false;
		return $path;
	}
  
	private function renderIpnResponse(){
	    //called from paypal_ipn
	    //make records and send emails
	    //$this->POST should be set already !!
	    $this->LOG->log(__METHOD__.'('.$this->IPN_STATUS.')');
		if(!$this->POST||empty($this->POST)) $this->POST=$this->POST_IPN;
	    if($this->IPN_STATUS==='VERIFIED'){
			$this->STATUS='success';
			$this->checkResponse();
		}else if($this->NOTIFY_FAILS){
		    $this->getIpnData();
			$this->STATUS='fail';
			$attach=$this->PDF_PATH;
			$this->MSG[]=$this->IPN_STATUS;
			$this->LOG->log('IPN Response: '.$this->IPN_POSTBACK);
	        $subject='ipn_failed';
	        $args['cartVals']=$this->CART_VALS;
	        $args['sales_ref']=$this->CART_REF;
	        $this->emailAdmin($subject,$args,$attach);
		}
		$this->setOutput();
	}
	private function setOutput($parts=[]){
		$this->LOG->log(__METHOD__.'('.$this->STATUS.')');
		if($this->OUTPUT_HTML){
			$out=$this->renderHTML($parts);
		}else{
			$out=array('status'=>'success','tid'=>$this->IPN_ID);
		}
		$this->OUTPUT=$out;		
	}
	function returnToSite(){
		$this->LOG->log(__METHOD__.'()');
		$return_method='redirect';//or form
		$frm=false;
		$gets=[];
		switch($this->STATUS){
			case 'success':
			   $url=URL.'page/paypalgood';	
			   break;
		   case 'fail':
			   $url=URL.'page/paypalfail';	
			   break;
			default:
			   $url=URL.'page/gatewayerror';	
			   break;
		}
		if($this->SETUP['DEV_URL'] && strpos($url,$this->SETUP['DEV_URL'])!==false){
			//fix dev url
		}
		$this->LOG->log('Return url: '.$url);
		$this->LOG->save();
		$VARS['h']=$this->SLIM->TextFun->quickHash(array('info'=>$this->CART_REF));
		$VARS['i']=$this->IPN_ID;
		$VARS['noreturn']=1;
		foreach ($VARS as $i => $v){ 
			if($i!=='products') $gets[]="$i=".htmlentities($v);
			$frm.="<input type='hidden' name='".htmlentities($i)."' value='".htmlentities($v)."'>\n";
		}
		if($return_method==='form'){
			//form only works if we have a real ssl - not shared
			$frm='<form action="'.$url.'" method="post" name="auto_frm">'.$frm;
			$frm.='<noscript><input type="submit" value="Click here if you are not redirected un 10 seconds."/></noscript>';
			$frm.='</form><script>document.auto_frm.submit();</script>';
			echo $frm;
		}else{
			if($this->DEBUG){
			    echo $url.'/?'.implode('&',$gets);
			}else{
				header('location:'.$url.'/?'.implode('&',$gets));
			}
		}
		//preME($VARS,2);
		die();			
	}
    function renderReturn(){
	    //setup user redirect if notifying at ipn stage
	    $this->LOG->log(__METHOD__.'()');
		$custom=issetCheck($this->POST,'custom',false);
		if(!$custom) $custom=issetCheck($this->POST,'cm',false);
		$custom=explode('|',$custom);
		$this->IPN_ID=issetCheck($custom,0);
		$this->loadCart();
		$this->returnToSite();
		die;	
    }
   function renderResponse($act=false){
	    $this->LOG->log(__METHOD__.'('.$act.')');
	    //respond to the user
		$hash=issetCheck($_GET,'h');
		$this->IPN_ID=issetCheck($_GET,'i');
		$this->OUTPUT_HTML=true;
		$this->loadCart(true);
		$parts=array(
			'status'=>false,
			'id'=>false,
			'error_account'=>false,
			'error_hash'=>false,
			'cart'=>false,
			'customer_service'=>true,
			'back'=>false,
			'title'=>'some title'
		);
		//check hash
		$_hash=$this->SLIM->TextFun->quickHash(array('info'=>$this->CART_REF,'encoded'=>$hash));
		if(!$_hash){
			$act='badhash';
		}else{
			$this->checkValues('cart',$act);
			if($this->STATUS==='success'){
				$act='paypalgood';
			}else{
				if($act!=='paypalgood'){
					$act='paypalfail';
				}
			}
		}
			
	    switch($act){
			case 'paypalgood':
				$parts['title']='Payment Successful';
				$parts['id']=1;
				$parts['status']=1;
				$parts['ref']=1;
				$parts['cart']=1;
				$this->setOutput($parts);
				break;
			case 'paypalfail':
				$parts['title']='Payment Failed';
				$parts['id']=1;
				$parts['status']=1;
				$parts['ref']=1;
				$parts['cart']=1;
				$parts['error_account']=1;
				$this->setOutput($parts);
				break;
			case 'badhash':
				$parts['title']='Error!';
				$parts['error_hash']=1;
				$this->LOG->log('Sorry, the details supplied are invalid.');
				$this->setOutput($parts);
				break;
			default:
				$parts['title']='Gateway Error';
				$parts['error_account']=1;
				$this->setOutput($parts);				
		}
		$out=array(
			'title'=>$parts['title'],
			'cart'=>$this->CART,
			'html'=>$this->OUTPUT,
			'rec'=>$this->IPN_RECORD,
			'action'=>$act
		);
		$this->OUTPUT=$out;	    
    }
    function renderHTML($parts){
		$out='';
		$cart_ref=$this->CART_REF;
		$email=customerServices('paypal '.$parts['title'].': '.$cart_ref,$this->CS_EMAIL);
		$out.=($this->MSG)?'<p>'.implode('</p><p>',$this->MSG).'</p>':'';
		$class=($parts['title']==='Payment Successful')?'success':'alert';
		$out.='<div class="callout '.$class.'">';
		$status=$this->IPN_RECORD['payment_status'];
		$txn=issetCheck($this->IPN_RECORD,'txn_id');
		if(!$status||$status===''){
			$status=($class==='success')?'Completed':'Not Completed';
		}
		if($parts['status']) $out.="<p>Transaction Status - " .$status . "</p>";
		if($parts['id'] && $txn) $out.="<p>Transaction ID - " . $txn . "</p>";
		if($parts['ref']) $out.="<p>OUR Ref. - <strong>" . $cart_ref . "</strong></p>";
		if($parts['error_account']) $out.="<p>Please check your Paypal account to make sure the payment has not been processed, then try again.</p>";
		if($parts['error_hash']) $out.="<p>Please check your information and try again.</p>";
		$out.='</div><div class="callout">';
		if($parts['cart']){
			$out.='<p>Thank you for your payment:</p><ul>'.$this->getCartValues(true).'</ul><br/>';
			$out.='<p>';
			$out.='You can check status of your orders from your "<strong><a href="'.URL.'page/my-home/view_my_sales">My Account</a></strong>" page.';
			$out.='</p>';
		}
		$out.='</div>';
		if($parts['customer_service'])$out.="<p>Please contact our $email if you need further assistance.</p>";
		if($parts['back']) $out.="<div class='back_btn'><a  href='{$this->RETURN_URL}' class= 'button round small blue_grad'><< Back to site</a></div>";
	    return $out;
   }
	
	
	private function loadCart($ipn_post=false){
		$this->CART=[];
		if(!$this->IPN_ID) return;
		$rec=$this->SLIM->db->cart_log->where('TID',$this->IPN_ID);
		$rec=renderResultsORM($rec,'TID');
		if($rec){
			$rec=current($rec);
			$this->IPN_RECORD=$rec;
			$cart=json_decode($rec['cart_data'],1);
			if($cart && is_array($cart)){
				$this->CART=$cart;
				$this->CART_REF=$rec['cart_ref'];
			}
			if($ipn_post){
				$post=json_decode($rec['payment_detail'],1);
				$this->POST=(is_array($post))?$post:[];
			}
		}
	}
	private function updateCartLog() {
		$this->LOG->log(__METHOD__.'('.$this->IPN_ID.')');
		if($this->DEBUG) return;
		$data['payment_date']=date('Y-m-d H:i:s',strtotime($this->POST['payment_date']));
		$data['payment_detail']=json_encode($this->POST);
		$data['status']=($this->STATUS==='success')?4:5;
		$data['payer_name']=$this->POST['first_name'].' '.$this->POST['last_name'];
		$log=array('payment_type','payment_status','txn_id','mc_gross','mc_currency','residence_country','txn_type','payer_email');
		foreach($log as $k) $data[$k]=issetCheck($this->POST,$k);
		$rec=$this->SLIM->db->cart_log->where('TID',$this->IPN_ID);
		if(count($rec)) $rec->update($data);
	}
	private function updateSalesRecords(){
		$out=['id'=>0,'code'=>$this->CART_REF];
		if($this->STATUS==='success'){
			$sales=$this->SLIM->db->Sales->where('Ref',$this->CART_REF)->select('ID,MemberID,ItemID,SoldPrice,Paid,PaymentDate,PaymentRef,Status');
			if(count($sales)){
				$sales=renderResultsORM($sales,'ID');
				$pdate=date('Y-m-d H:i:s',strtotime($this->POST['payment_date']));
				foreach($sales as $i=>$v){
					if(!$out['id']) $out['id']=$i;
					$upd=['Paid'=>$v['SoldPrice'],'PaymentDate'=>$pdate,'PaymentRef'=>$this->IPN_ID,'Status'=>1];
					$rec=$this->SLIM->db->Sales->where('ID',$i);
					if(!$this->DEBUG && count($rec)==1) $rec->update($upd);
				}			
			}
		}
		return $out;
	}
	function emailAdmin($eml=false,$args=false,$attachments=false){
		$this->LOG->log(__METHOD__.'('.$eml.')');
		$tpl=file_get_contents(TEMPLATES.'parts/tpl.email.admin.transaction.html');
		$margs=array(
		   'name'=>$this->CART_USER['FirstName'].' '.$this->CART_USER['LastName'],
		   'grade'=>$this->CART_USER['CGradeName'],
		   'email'=>$this->CART_USER['Email'],
		   'phone'=>$this->CART_USER['LandPhone'],
		   'dojo'=>$this->CART_USER['Dojo'],
		   'city'=>$this->CART_USER['City'],
		   'country'=>$this->CART_USER['Country'],
		   'txn'=>$this->TXN,
		   'status'=>$this->POST['payment_status'],
		   'value'=>$this->CART_VALS['value'],
		   'items'=>$this->CART_VALS['list'],
		   'sales_ref'=>$this->CART_REF,
		   'body'=>false,
		);
		$send_to=$this->ADMIN_EMAIL;
		switch($eml){
			case 'new_sale':
			   $subject='New payment on website';
			   $body='<p>A new payment has been completed on the website via Paypal:</p>';
			   $margs['body']=$body;
			   break;
			case 'sale_failed':
			   $subject='Failed transaction on website';
			   $body='<p>A transaction has failed on the website via Paypal.<br/>The system reported the following errors:</p>';
			   $body.=$this->LOG->dumpLog().'<hr/>';
			   $margs['body']=$body;
			   $margs['sales_ref']=$this->CART_REF;
			   break;
			case 'ipn_failed':
			   $send_to='roger@jamtechsolutions.co.uk';
			   $subject='IPN Verification Failed on website';
			   $body='<p>An IPN has failed to be verified on the website via Paypal.<br/>The system reported the following errors:</p>';
			   $body.=$this->LOG->dumpLog().'<hr/>';
			   $margs['body']=$body;
			   break;
			default:
			   $margs=false;
		}
		if($margs){						
			$mail['message']=fillTemplate($tpl,$margs);
			$mail['to'] = $send_to;
			$mail['subject'] = $subject;
			$mail['attachments']=[$attachments];
			$this->send_email($mail);
		}
	}

	function emailUser($eml=false,$args=false,$attachments=false){
		$this->LOG->log(__METHOD__.'('.$eml.')');
		$tpl=file_get_contents(TEMPLATES.'parts/tpl.email.user.transaction.html');
		$name=$this->CART_USER['FirstName'].' '.$this->CART_USER['LastName'];
		$body=$subject=$body2=false;
		switch($eml){
			case 'new_sale':
			   $subject='Your payment at the AKR Members website';
			   $body='<p>Your payment has been verified and we are processing your order.</p><p>You have paid for the following items from AKR ('.URL.'):<br/></p>';
			   break;
		}
		$body2.='<p>If you are experiencing any problems with this process, please contact our customer services team using the details below.</p>';
	    $margs=array(
		   'name'=>$name,
		   'txn'=>$this->TXN,
		   'status'=>$this->POST['payment_status'],
		   'value'=>$this->CART_VALS['value'],
		   'items'=>$this->CART_VALS['list'],
		   'sales_ref'=>$this->CART_REF,
		   'body'=>$body,
		   'extra'=>$body2
	    );
		if($subject){						
			$mail['message']=fillTemplate($tpl,$margs);
			$mail['to'] = $this->CART_USER['Email'];
			$mail['subject'] = $subject;
			$mail['attachments']=[$attachments];
			$this->send_email($mail);
		}
	}
    
    function send_email($args=false){
		if(is_array($args)){
			if(!is_array($args['message'])){
				$message=$args['message'];
				$textmsg=str_replace("\n\n",'</p>',$message);
				$textmsg=str_replace('<p>','',$textmsg);	
				$args['message']=array(
					0=>strip_tags($textmsg),
				    1=>$message
				);
			}
			if(!isset($args['from']))$args['from']=$this->MAILBOT;
			$sent=false;
			try {
				$sent=$this->SLIM->Mailer->Process($args);
				return $sent;
			} catch (Exception $e) {
				$this->MSG[]='Email error: '.$e;
				$this->LOG->log('Email error: '.$e);
			}
		}else{
			$this->MSG[]='Email error: no mail settings';
			$this->LOG->log('Email error: no mail settings');
		}
    }
   function emptySessionVars(){
	    terminatePayment();
   }
}
