<?php

class slimInvoiceRender{
	private $SLIM;
	private $CURRENCIES=[0=>'USD',1=>'USD',2=>'EUR'];
	private $PERMLINK;
	private $ADMIN_URL;
	private $SITE;
	private $USER;
	private $AJAX;
	private $PAYMENT_POWER;
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->ADMIN_URL=URL.'admin/';
		$this->AJAX=$slim->router->get('ajax');
		$this->CURRENCIES=$slim->Options->get('currencies');
		$this->PAYMENT_POWER=$slim->Options->getSiteOptions('payment_power',true);	
		$route=$slim->router->get('route');
		$this->SITE=($route[0]==='admin')?'admin':'public';
		$this->PERMLINK=URL.$route[0].'/'.$route[1].'/';
		if(isset($route[2]))$this->PERMLINK.=$route[2].'/';
		$this->USER=$slim->user;
	}
	
	function render($data=[],$download=false){
		if($data){
			$mode='view';
			if($download){
				if(in_array($download,['download','view','file'])){
					$mode=$download;
				}else{
					$mode='download';
				}
			}
			return $this->renderInvoice($data,$mode);
		}
		return msgHandler('Sorry, no data supplied...',false,false);
	}
	
	private function getBalance($value=0,$paid=0,$color=true){
		if(is_string($value) && is_numeric($value)){
			$value=(int)str_replace('.','',$value);
		}
		if(is_string($paid) && is_numeric($paid)){
			$paid=(int)str_replace('.','',$paid);
		}
		$balance=($value-$paid);
		if($color){
			if($value==0 && $paid==0){
				$c='gray';
			}else if($balance>0){
				$c='maroon';
			}else if($balance<0){
				$c='red-orange';
			}else{
				$c='dark-green';
			}			
			$balance='<span class="text-'.$c.'">'.toPounds($balance).'</span>';
		}else{
			$balance=toPounds($balance);
		}
		return $balance;
	}

	private function renderInvoice($data,$mode){
		$labels=array('INVOICE','Date','Amount Due','Item','Description','Unit Cost','Quantity','Price','Total','Amount Paid','Balance Due','Terms');
		$thead='<th>Item</th><th>Price</th><th>Qty</th><th>Value</th><th>Paid</th><th>Balance</th><th>Currency</th>';
		$payday=$invno=$invday=$rollcall=$event=$buttons=false;
		$room=false;
		$memb=current($data['members']);
		$member=$memb['Name'].'<br/>'.$memb['AddressBlock'];
		$balance=$this->getBalance($data['metrics']['value'],$data['metrics']['paid']);
		$event_ref=false;
		foreach($data['log'] as $i=>$v){
			$qty=1;
			$name=$v['ItemName'];
			if($room) $name='<br/>Room: '.$room;
			$payday=$v['PaymentDate'];
			$invday=$v['SalesDate'];
			$invno=$v['Ref'];
			$currency=$this->CURRENCIES[(int)$v['Currency']];
			$bal=$this->getBalance($v['SoldPrice'],$v['Paid']);
			$price=toPounds($v['SoldPrice']);
			$value=toPounds(($qty * $v['SoldPrice']));
			$prod=issetCheck($data['products'],$v['ItemID']);
			$desc=($prod)?$prod['ItemShort']:'';
			if(!$event_ref) $event_ref=(int)$v['EventRef'];
			if($event_ref){
				if($desc!=='') $desc.='<br/>';
				$desc.=$data['events'][$event_ref]['EventName'].' / '.validDate($data['events'][$event_ref]['EventDate']);
			}
			if($desc!==''){
				if(in_array($mode,['download','file'])){
					$desc='<br/>'.$desc;
				}else{
					$desc='<br/><em>'.$desc.'</em>';
				}
			}
			if(in_array($mode,['download','file'])){
				$row[]=array($name.$desc,$qty,$price,$value	);				
			}else{
				$row[]='<tr><td>'.$name.$desc.'</td><td class="text-right">'.$price.'</td><td class="text-right">'.$qty.'</td><td class="text-right">'.$value.'</td></tr>';
			}
		}
		if(!$invday){
			if($payday){
				$invday=$payday;
			}else if($event_ref){
				$invday=$data['events'][$event_ref]['EventDate'];
			}
		}
		if($mode==='download'){
			//$row[]=array('Paid','','','',toPounds($data['metrics']['paid']));		
			//$row[]=array('Totals','',$data['metrics']['qty'],toPounds($data['metrics']['value']),$balance);		
		}else{
			//$row[]='<tr class="bg-navy text-white" style="font-weight:bold"><td class="bg-navy">Paid</td><td></td><td></td><td></td><td>'.toPounds($data['metrics']['paid']).'</td></tr>';
			//$row[]='<tr class="bg-light-gray" style="font-weight:bold"><td class="bg-light-gray">Total</td><td></td><td>'.$data['metrics']['qty'].'</td><td>'.toPounds($data['metrics']['value']).'</td><td>'.$balance.'</td></tr>';
		}

		$paid=toPounds($data['metrics']['paid']);
		$buttons='<button class="button button-lavendar gotoME" data-ref="'.$this->PERMLINK.'download/'.$invno.'/ref"><i class="fi-download"></i> Download PDF</button>';
		if($this->SITE==='admin' && $this->USER['access']>=25){
			$edit_url=$this->ADMIN_URL.'sales/edit_payment/'.$invno.'/ref';
			$paid='<a data-tooltip title="click to edit payment" class="loadME link-purple" data-ref="'.$edit_url.'">'.$paid.'</a>';
			$buttons.='<button class="button button-dark-blue loadME" data-ref="'.$this->ADMIN_URL.'sales/edit_payment/'.$invno.'/ref"><i class="fi-pencil"></i> Edit Payment</button>';
		}else if($this->SITE==='public' && $this->PAYMENT_POWER){
			if(($data['metrics']['value']-$data['metrics']['paid'])>0){
				$buttons.='<button type="button" class="button button-blue gotoME" data-ref="'.URL.'page/checkout/'.$invno.'" ><i class="fi-dollar-bill"></i> Payment Now</button>';
			}
		}

		$fields=array(
			'address'=>'AKR<br/>USA',
			'member_info'=>$member,
			'invoice_no'=>$invno,
			'invoice_date'=>validDate($invday),
			'balance'=>$balance,
			'total'=>toPounds($data['metrics']['value']),
			'paid'=>($mode==='download')?toPounds($data['metrics']['paid']):$paid,
			'currency'=>$currency,
			'terms'=>'-',
			'rows'=>$row,
			'status'=>(($data['metrics']['value']-$data['metrics']['paid'])>0)?'Due':'Paid',
			'qty'=>$data['metrics']['qty']
		);
		if($this->SITE==='admin'){
			if($mode!=='download') $fields['member_info']='<a data-tooltip title="click to view details" class="link-dark-blue loadME" data-ref='.$this->ADMIN_URL.'member/view/'.$memb['MemberID'].'">'.$member.'</a>';
		}
		if($mode==='download'){
			$fields['render']='D';
			$fields['docname']=CACHE.'pdf/invoice_'.$invno.'.pdf';
			$this->SLIM->PDF->renderInvoice($fields);
			die;
		}else if($mode==='file'){
			$fields['render']='F';
			$fields['docname']=CACHE.'pdf/invoice_'.$invno.'.pdf';
			$this->SLIM->PDF->renderInvoice($fields);
			return $fields['docname'];
		}
		$fields['rows']=implode('',$row);
		$tpl=($mode==='download')?'invoice_pdf':'invoice_edit';
		$tpl=file_get_contents(TEMPLATES.'app/app.'.$tpl.'.html');
		$inv=replaceMe($fields,$tpl);
		if($this->AJAX){
			$controls='<div class="button-group small expanded">'.$buttons.'</div>';
			echo renderCard_active('Invoice: '.$invno,$inv.$controls,$this->SLIM->closer);
			die;
		}
		return $inv;
	}
}
