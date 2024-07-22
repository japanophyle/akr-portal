<?php

class slim_checkout_cart{
	private $SLIM;
	private $CART;
	private $CART_REF;
	private $CART_LOG_ID;
	private $CART_USER;
	private $USER;
	private $ROUTE;
	private $AJAX;
	private $ADMIN_PAGE;
	private $PUBLIC_PAGE;
	private $SITE;
	private $ACTION;
	private $OUTPUT;
	private $METHOD;
	private $REQUEST;
	private $PAYMENT_POWER;
	
	function __construct($slim=null){
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		$this->ROUTE=$slim->router->get('route');
		$this->AJAX=$slim->router->get('ajax');
		$this->METHOD=$slim->router->get('method');
		$this->REQUEST=($this->METHOD==='POST')?$_POST:$_GET;
		$this->PUBLIC_PAGE=URL.'page/checkout/';
		$this->ADMIN_PAGE=URL.'admin/cart/builder/';
		$this->SITE=($this->ROUTE[0]==='admin')?'admin':'public';
		$this->PAYMENT_POWER=$slim->Options->getSiteOptions('payment_power',true);
		$this->init();
	}
	
	private function init(){
		$this->ACTION=issetCheck($this->ROUTE,3);
		$this->CART_REF=issetCheck($this->ROUTE,2);
		if($this->METHOD==='POST'){
			$this->ACTION=issetCheck($this->REQUEST,'action');
			$this->CART_REF=issetCheck($this->REQUEST,'cart_ref');
		}
		if(!$this->USER['id']){
			$this->ACTION='no_access';
		}else if(!$this->CART_REF){
			$this->ACTION='no_cart_ref';
		}
		if(!$this->PAYMENT_POWER) $this->ACTION='no_power';
	}
	
	function Process(){
		switch($this->ACTION){
			case 'no_cart_ref':
				$this->OUTPUT=[
					'title'=>'My Cart',
					'html'=>msgHandler('Sorry, no cart reference supplied...',false,false),
					'cart_ref'=>$this->CART_REF
				];
				break;
			case 'no_access':
				$this->OUTPUT=[
					'title'=>'My Cart',
					'html'=>msgHandler('Sorry, you cannot access that content right now...',false,false),
					'cart_ref'=>$this->CART_REF
				];
				break;
			case 'no_power':
				$this->OUTPUT=[
					'title'=>'Checkout',
					'html'=>msgHandler('Sorry, online payments are not available right now...',false,false),
					'cart_ref'=>$this->CART_REF
				];
				break;
			case 'pay_now':
				$this->renderGateway();
				break;
			default:
				$this->renderCart();
		}
		return $this->OUTPUT;
	}
	
	private function renderCart(){
		$this->loadCart();
		$html=$currency=null;
		if($this->CART){
			if($this->USER['access']<$this->SLIM->AdminLevel){
				if($this->USER['MemberID']!=$this->CART_USER['MemberID']){
					$html=msgHandler('Sorry, you don\'t have access to that cart.',false,false);
				}
			}
			if($this->CART['metrics']['paid']>=$this->CART['metrics']['value']){
				$html=msgHandler('This invoice [<strong>'.$this->CART_REF.' - '.toPounds($this->CART['metrics']['paid'],true).'</strong>] has already been paid.','success',false);
			}
			if(!$html){
				$cart='';
				$uname=explode(' ',$this->CART_USER['Name']);
				$first=$uname[0];
				unset($uname[0]);
				$last=implode(' ',$uname);
				$hcart='<input type="hidden" name="action" value="pay_now"/><input type="hidden" name="first_name" value="'.$first.'"/><input type="hidden" name="last_name" value="'.$last.'"/><input type="hidden" name="cart_ref" value="'.$this->CART_REF.'"/><input type="hidden" name="member_id" value="'.$this->CART_USER['MemberID'].'"/>';
				foreach($this->CART['log'] as $i=>$v){
					if(!$currency) $currency=$v['Currency'];
					$price=toPounds($v['SoldPrice'],$currency);
					$cart.='<tr><td>'.$v['ItemName'].'</td><td>1</td><td class="text-right">'.$price.'</td><td class="text-right">'.$price.'</td></tr>';
					$hcart.='<input type="hidden" name="product['.$i.']" value="'.$v['ItemName'].'"/><input type="hidden" name="price['.$i.']" value="'.toPounds($v['SoldPrice']).'"/><input type="hidden" name="qty['.$i.']" value="1"/>'."\n";
				}
				$info=$this->renderInfo();
				$head='<tr><th class="text-left">Item</th><th class="text-left">Qty.</th><th class="text-right">Price</th><th class="text-right">Value</th></tr>';
				$ftotal='<tr class="bg-dark-blue text-white"><td><strong class="text-white">Totals</strong></td><td>'.$this->CART['metrics']['qty'].'</td><td> </td><td class="text-right">'.toPounds($this->CART['metrics']['value'],$currency).'</td></tr>';
				$foot='<tr><td colspan="4"><div class="button-group expanded small"><button type="button" class="button button-gray gotoME" data-ref="'.URL.'page/my-home/view_my_sales"><i class="fi-arrow-left"></i> My Sales</button><button type="submit" class="button button-olive"><i class="fi-check"></i> Pay via Paypal</button></div></td></tr>';
				$html=$info.'<form method="POST" action="'.URL.'page/checkout">'.$hcart.'<table class="borders" id="cart">'.$head.$cart.$ftotal.$foot.'</table></form>';
			}
		}else{
			$html=msgHandler('Sorry, I can\'t find a cart with that reference.',false,false);
		}
		$o['title']='My Cart';
		$o['html']=$html;
		$o['cart_ref']=$this->CART_REF;
		$this->OUTPUT=$o;
	}
	private function renderInfo(){
		$event=issetCheck($this->CART,'events');
		$date=[];
		if($event){
			$event=current($event);
			if($event)	$date=explode(' ',$event['EventDate']);
		}
		$member='<strong>Member</strong>: '.$this->CART_USER['Name'].'<br/><strong>Grade</strong>: '.$this->CART_USER['CGradeName'].'<br/><strong>Dojo</strong>: '.$this->CART_USER['Dojo'];
		$events=($event)?'<br/><strong>Event</strong>: '.$event['EventName'].'<br/><strong>Event Date</strong>: '.$date[0]:'';
		return '<div class="block">'.$member.$events.'<br/><strong>Ref:</strong> '.$this->CART_REF.'</div>';
	}
	private function renderGateway(){
		$vcart=[];
		foreach($this->REQUEST as $i=>$v){
			if(in_array($i,['price','qty','cart_ref','member_id'])) continue;
			if($i==='product'){
				$ct=1;
				foreach($v as $x=>$y){
					$vcart['item_number_'.$ct]=$x;
					$vcart['item_name_'.$ct]=$y;
					$vcart['amount_'.$ct]=$this->REQUEST['price'][$x];
					$vcart['quantity_'.$ct]=$this->REQUEST['qty'][$x];
					$ct++;
				}
			}else{
				$vcart[$i]=$v;
			}
		}
		if($vcart){
			$log_id=$this->logCart();	
			$vcart['custom']=$log_id.'|'.$this->CART_REF.'|'.$this->REQUEST['member_id'];
			$pp= new paypal_control($this->SLIM);
			$pp->ACTION=$this->ACTION;
			$pp->REQUEST=$vcart;
			$rsp=$pp->Process();
		}else{
			$rsp=msgHandler('Sorry, there was a problem building the virtual cart.',false,false);
		}
		$o['title']='Paypal Checkout';
		$o['html']='<div class="block">'.$rsp.'</div>';
		$o['cart_ref']=$this->CART_REF;
		$this->OUTPUT=$o;
	}
	private function loadCart(){
		$this->CART=[];
		if($this->CART_REF){
			$data=$this->SLIM->Sales->getInvoiceRecord('ref',$this->CART_REF);
			if(is_array($data) && !empty($data)){
				$this->CART=$data;
				$this->CART_USER=current($data['members']);
			}			
		}
	}
	private function logCart(){
		$ref=$this->REQUEST['cart_ref'];
		$chk=$this->SLIM->db->cart_log->where('cart_ref',$ref);
		if(!count($chk)){
			$data['cart_ref']=$ref;
			$data['create_date']=date('Y-m-d H:i:s');
			$data['cart_data']=json_encode($this->REQUEST);
			$data['user_id']=$this->REQUEST['member_id'];
			$data['status']=2;
			$data['item_name']=implode(', ',$this->REQUEST['product']);
			$db=$this->SLIM->db->cart_log;
			$chk=$db->insert($data);
			$id=$db->insert_id();
		}else{
			$rec=renderResultsORM($chk);
			$rec=current($rec);
			$id=$rec['TID'];
		}
		return $id;
	}
}
