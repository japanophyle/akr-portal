<?php

class slimSalesMan{
	
	private $SLIM;
	private $LANG;
	private $AJAX;
	private $ROUTE;
	private $OUTPUT;
	private $USER;
	private $POST;
	private $ARGS;//id or ref for current item - from ROUTES 2 & 4
	private $ADMIN_URL;
	private $ACTION;
	
	private $PRODUCT_REF;//current product
	private $PRODUCT_GROUP_REF;//current group id
	private $GROUP;// item group??
	private $DOJOS;
	private $GROUPS;// list of product groups
	private $CATEGORIES;// list of categories
	private $PRODUCTS;// list of products in current group
	private $SALES;//list of sales for current products - from events log
	private $SUMMARY;// liast of totals for current products
	private $CRUNCH;// raw results from the crucher.
	private $NAV;//an array of buttons for the top bar
	private $REPORTING;//flag for download report
	private $Q_REPORTS=array('unpaid','overpaid','by_event','by_member','by_dojo','dojo','event','member');// quick reports - for formatting
	private $INV_REF_TYPE='id'; //flag for getting the invoice number. can be 'id' or 'ref'.
	private $PROD_ID=array('membership'=>[],'ikyf'=>[]);
	private $CURRENCIES=array(0=>'CHF',1=>'CHF',2=>'EUR');
	private $STATES; //from Sales

	function __construct($slim){
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		$this->LANG=$slim->language->get('_LANG');
		$this->AJAX=$slim->router->get('ajax');
		$this->ROUTE=$slim->router->get('route');
		$this->POST=$slim->router->get('post');
		$this->CATEGORIES=$this->SLIM->Products->get('categories','all');
		$this->GROUPS=$this->SLIM->Products->get('groups','all');
		$this->DOJOS=$this->SLIM->Options->get('dojos');
		$this->ADMIN_URL=URL.'admin/';
		$this->STATES=$this->SLIM->Sales->getState(false,'all');
		$this->initProducts();
	}
	
	private function setVars(){
		if($this->ROUTE[1]==='edit_subscription'){
			$this->ROUTE[3]=$this->ROUTE[2];
			$this->ROUTE[2]='edit_payment';
		}
		$this->ARGS=issetCheck($this->ROUTE,2);
		if($this->POST){
			//should not get here!!
			preME($this->POST,2);
		}
		switch($this->ARGS){
			case 'group':
				$this->ACTION='group_product';
				$this->ARGS=issetCheck($this->ROUTE,3);
				$this->ACTION=issetCheck($this->ROUTE,4,$this->ACTION);
				break;
			case 'product':
			case 'edit_record':
			case 'edit_payment':
			case 'unpaid':
			case 'overpaid':
			case 'by_event':
			case 'by_member':
			case 'by_dojo':
			case 'dojo':
			case 'event':
			case 'member':
				$this->ACTION=$this->ARGS;
				$this->ARGS=issetCheck($this->ROUTE,3);
				break;
			default:
				$this->ACTION=($this->USER['access']<25)?'by_dojo':'group_summary';
		}
		foreach($this->ROUTE as $i=>$v){
			if($v==='report') $this->REPORTING=true;
		}
	}
	public function render(){
		$this->setVars();
		switch($this->ACTION){
			case 'edit_record':
				$this->renderSalesInvoice();
				break;
			case 'edit_payment':
			case 'view_payment':
				$this->renderPaymentRecord();
				break;
			case 'group_product':
			case 'product':
			default:
				$this->renderTable();
		}
		if(!$this->AJAX) $this->renderTopNav();
		return $this->OUTPUT;
	}
	private function getDojoMembers($args=[]){
		$out=[];
		if($args){
			foreach($args as $i){
				$chk=$this->SLIM->db->Members()->select("MemberID")->where('DojoID',(int)$i);
				$chk=renderResultsORM($chk);
				foreach($chk as $x=>$y)	$out[$y['MemberID']]=$y['MemberID'];
			}
		}
		return $out;
	}
	private function getDojo($ref=false,$return=false,$by=false){
		if($ref){
			switch($by){
				case 'country':
				case 'name':
					$k=($by==='country')?'LocationCountry':'LocationName';
					foreach($this->DOJOS as $i=>$v){
						if(trim($v[$k])===$ref){
							if($return){
								$out=issetCheck($v,$return);
							}else{
								$out=$v;
							}
							return $out;
						}
					}
					break;
				default://by id
					$out=issetCheck($this->DOJOS,$ref);
					if($return){
						$out=issetCheck($out,$return);
					}
					return $out;
			}
		}
		return false;
	}
	private function initProducts(){
		$prods=$this->SLIM->db->Items()->where('ItemType','product')->and('ItemGroup',0)->order('ItemTitle')->select('ItemID,ItemTitle,ItemPrice,ItemCategory,ItemGroup,ItemSlug,ItemStatus,ItemCurrency');
		$prods=renderResultsORM($prods,'ItemID');
		foreach($prods as $i=>$v){
			$this->PRODUCTS[$i]=$v;
			if(in_array($v['ItemSlug'],array('membership-annual','membership-annual-eur'))){
				$this->PROD_ID['membership'][$i]=$i;
			}else if(in_array($v['ItemSlug'],array('ikyf-id-registration','ikyf-id-registration-eur'))){
				$this->PROD_ID['ikyf'][$i]=$i;
			}		
		}
	}
	private function getEventInfo($id=0){
		if($id>0){
			$DB=$this->SLIM->db->Events();
			$rec=$DB->select("EventID, EventName, EventDate, EventType")->where('EventID',$id);
			$t=renderResultsORM($rec);
			return current($t);
		}
		return false;
	}
	private function getProduct($ref=false,$id=false,$what='product'){
		$data=[];
		if($what && $what!==''){
			if($ref && $ref!==''){
				$data=$this->SLIM->Products->get($what,$ref,$id);
			}
		}
		return $data;
	}
	private function getProductSales($product_id=false){
		$data=[];
		if($product_id || $this->PRODUCTS){
			$API=$this->SLIM->EventsLogAPI;
			if(is_numeric($product_id)){
				$data[$product_id]=$API->get('product',$product_id);
			}else{
				$_data=(is_array($product_id))?$product_id:array_keys($this->PRODUCTS);
				foreach($_data as $k){
					$data[$k]=$API->get('product',$k);
				}
			}
		}
		return $data;	
	}
	private function getSalesRecord_eventlog($log_id=false){
		$data=$log=[];
		if(is_numeric($log_id) && $log_id>0){
			$API=$this->SLIM->EventsLogAPI;
			$log=$API->get('log',$log_id);
		}
		if($log['status']==200){
			$data=$log['data'];
			$prod=$this->getProduct('id',$data['ProductID']);
			$data['product']=($prod)?current($prod):false;
			if($this->ACTION==='edit_payment') return $data;
			
			//check for a form is attached
			$chk=$this->getSubmittedForm($this->ARGS,'EventLogID');
			$data['submitted_form']=false;
			if($chk){
				$data['submitted_form']=array(
					'id'=>issetCheck($chk,'ID',0),
					'date'=>issetCheck($chk,'LogDate',0),
				);
			}
		}			
		return $data;	
	}
	private function getSalesRecord_report($what=false,$vars=false){
		$data=$log=[];
		$API=$this->SLIM->EventsLogAPI;
		if($this->USER['access']<25){
			$API->DOJO_MEMBERS=$this->getDojoMembers($this->USER['dojo_lock']);
		}
		switch($what){
			case 'unpaid':
				$log=$API->get('unpaid',$vars);
				break;
			case 'overpaid':
				$log=$API->get('overpaid',$vars);
				break;
			case 'event':
				$log=$API->get('event_report',$vars);
				break;
			case 'member':
				$log=$API->get('member_report',$vars);
				break;
			case 'dojo':
			case 'by_event':
			case 'by_member':
			case 'by_dojo':
				$log=$API->get('has_product',$vars);
				break;
			default:
		}
		if($log){
			$out=array('log'=>$log,'products'=>false,'members'=>false,'events'=>false,'dojos'=>false);
			if($what==='dojo') $out['dojos'][$this->ARGS]=$this->getDojo($this->ARGS);
			foreach($log as $i=>$v){
				$p=$this->getProduct('id',$v['ProductID']);
				$out['products'][$v['ProductID']]=current($p);
				$out['members'][$v['MemberID']]=$this->SLIM->options->get('member_info',$v['MemberID']);
				if($this->ACTION==='by_event'){
					if(!isset($out['events'][$v['EventID']])){
						$out['events'][$v['EventID']]=$this->getEventInfo($v['EventID']);
					}
				}else if($this->ACTION==='by_dojo'){
					$member=$out['members'][$v['MemberID']];
					if($dojo=$this->getDojo($member['Dojo'],false,'name')){
						$out['dojos'][$dojo['id']]=$dojo;
						$out['log'][$i]['DojoID']=$dojo['id'];
					}
				}else if($what==='dojo'){
					$member=$out['members'][$v['MemberID']];
					$dojo=$this->getDojo($member['Dojo'],false,'name');
					if($dojo['id']!=$this->ARGS){
						unset($out['log'][$i]);
					}else{
						$out['log'][$i]['DojoID']=$dojo['id'];
					}
				}elseif($this->ACTION==='event'){
					$out['events'][$this->ARGS]=$this->getEventInfo($this->ARGS);
				}
			}
			return $out;	
		}
		return false;			
	}
	private function getSubmittedForm($id=0,$what='ID'){
		if($id>0){
			$DB=$this->SLIM->db->FormsLog();
			$rec=$DB->where($what,$id);
			$rec=renderResultsORM($rec);
			return current($rec);
		}
		return false;
	}
	private function summarizeProductSales($data){
		$totals=[];
		if($this->ACTION==='product'){
			$tots[$this->ARGS]=array('qty'=>0,'value'=>0,'paid'=>0);
			foreach($data[$this->ARGS] as $log_id=>$v){
				$tots[$this->ARGS]['qty']++;
				$tots[$this->ARGS]['value']+=(int)$v['SoldPrice'];
				$tots[$this->ARGS]['paid']+=(int)$v['Paid'];
			}
			$totals=array('summary'=>$tots,'sales'=>$data[$this->ARGS]);
		}else if(in_array($this->ACTION,$this->Q_REPORTS)){
			switch($this->ACTION){
				case 'by_event':
					$_idk='EventRef';
					break;
				case 'by_member':
				case 'dojo':
					$_idk='MemberID';
					break;
				case 'by_dojo':
					$_idk='DojoID';
					break;
				default://unpaid - by product
					$_idk='key';
			}
			foreach($data as $pid=>$v){
				$_id=($_idk==='key')?$pid:$v[$_idk];
				if(!isset($totals[$_id])) $totals[$_id]=array('qty'=>0,'value'=>0,'paid'=>0);
				if(issetCheck($v,'ItemID')){
					$totals[$_id]['qty']++;
					$totals[$_id]['value']+=(int)$v['SoldPrice'];
					$totals[$_id]['paid']+=(int)$v['Paid'];
				}					
			}				
		}else{
			foreach($data as $pid=>$v){
				if(!isset($totals[$pid])) $totals[$pid]=array('qty'=>0,'value'=>0,'paid'=>0);
				foreach($v as $log_id=>$rec){
					if($prod_id=issetCheck($rec,'ItemID')){
						$totals[$prod_id]['qty']++;
						$totals[$prod_id]['value']+=(int)$rec['SoldPrice'];
						$totals[$prod_id]['paid']+=(int)$rec['Paid'];
					}
				}
			}
		}
		return $totals;
	}
	public function getBalance($value=0,$paid=0,$color=true){
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
	private function tableRowSummary($prods=false,$summary=false,$product_group_ref=false){
		if(!$prods) $prods=$this->PRODUCTS;
		if(!$summary) $summary=$this->SUMMARY;
		if(!$product_group_ref) $product_group_ref=$this->PRODUCT_GROUP_REF;
		if(in_array($this->ACTION,$this->Q_REPORTS)){
			$up_data=$prods;
			$prods=($this->ACTION==='by_event'||$this->ACTION==='by_member'||$this->ACTION==='by_dojo')?$summary:$up_data['log'];
		}
		$product_group_ref=(int)$product_group_ref;
		$group_name=issetCheck($this->GROUPS,$product_group_ref,'Single Items');
		$rows=[];
		if(!$prods){
			$rows['error']=msgHandler('No records found...',false,false);;
		}else{
			foreach($prods as $i=>$v){
				$td_extra=false;
				$row_label='?? no label';
				$value=$paid=$balance=$qty=$row_id=0;
				switch($this->ACTION){
					case 'by_member':
						$row_id=$i;
						$event=$up_data['events'][$i];
						$member=$up_data['members'][$i];
						$url=$this->ADMIN_URL.'events/sales/member/'.$i;
						$button_label='View Details';
						$f1=validDate($v['PaymentDate']);
						if(!$f1) $f1='-';
						$date_sort=($v['PaymentDate'])?strtotime($v['PaymentDate']):0;
						$td_extra='<td data-field="PaymentDate" data-sort-value="'.$date_sort.'">'.$f1.'</td>';
						$row_label=$member['Name'];
						$row_label='<a data-tooltip title="click to view/edit the event details" class="link-dark-blue loadME" data-ref="'.$this->ADMIN_URL.'member/edit/'.$i.'">'.$row_label.'</a>';
						//summary parts
						if(isset($summary[$i])) extract($summary[$i]);
						$balance=$this->getBalance($value,$paid);
						$disable=($qty==0)?'disabled="disabled"':'';
						$control='<button class="button small button-navy gotoME" data-ref="'.$url.'" '.$disable.'><i class="fi-arrow-right"></i> '.$button_label.'</button>';
						break;
					case 'by_dojo':
						$row_id=$i;
						$event=$up_data['events'][$i];
						$dojo=$up_data['dojos'][$i];
						$url=$this->ADMIN_URL.'events/sales/dojo/'.$i;
						$button_label='View Details';
						$f1=false;
						$td_extra=false;
						$row_label=$dojo['LocationName'];
						$row_label='<a data-tooltip title="click to view/edit the dojo details" class="link-dark-blue loadME" data-ref="'.$this->ADMIN_URL.'dojo/location/'.$i.'">'.$row_label.'</a>';
						//summary parts
						if(isset($summary[$i])) extract($summary[$i]);
						$balance=$this->getBalance($value,$paid);
						$disable=($qty==0)?'disabled="disabled"':'';
						$control='<button class="button small button-navy gotoME" data-ref="'.$url.'" '.$disable.'><i class="fi-arrow-right"></i> '.$button_label.'</button>';
						break;
					case 'by_event':
						$row_id=$i;
						$event=$up_data['events'][$i];
						$url=$this->ADMIN_URL.'events/sales/event/'.$i;
						$button_label='View Details';
						$f1=validDate($event['EventDate']);
						if(!$f1) $f1='-';
						$date_sort=($event['EventDate'])?strtotime($event['EventDate']):0;
						$td_extra='<td data-field="EventDate" data-sort-value="'.$date_sort.'">'.$f1.'</td>';
						$row_label=$event['EventName'];
						$act=($this->USER['access']>=25)?'edit':'view';
						$row_label='<a data-tooltip title="click to view/edit the event details" class="link-dark-blue loadME" data-ref="'.$this->ADMIN_URL.'events/'.$act.'/'.$i.'">'.$row_label.'</a>';
						//summary parts
						if(isset($summary[$i])) extract($summary[$i]);
						$balance=$this->getBalance($value,$paid);
						$disable=($qty==0)?'disabled="disabled"':'';
						$control='<button class="button small button-navy gotoME" data-ref="'.$url.'" '.$disable.'><i class="fi-arrow-right"></i> '.$button_label.'</button>';
					break;
					case 'unpaid':
					case 'overpaid':
					case 'event':
					case 'member':
					case 'dojo':
						$row_id=$i;
						$prod=$up_data['products'][$v['ItemID']];
						$member=$up_data['members'][$v['MemberID']];
						$url=$this->ADMIN_URL.'events/sales/edit_record/'.$i;
						$button_label='Invoice';
						$form_ref=($this->ACTION==='event')?$up_data['log'][$i]['EventLogRef']:$i;
						$has_form=$this->hasForm($form_ref);
						
						$f1=$member['FirstName'].' '.$member['LastName'];
						$f2=$prod['ItemTitle'];
						if($prod['ItemShort']!==''){
							$f2.='<br><small class="text-navy">'.truncateME(strip_tags($prod['ItemShort'])).'</small>';
						}
						$f1=validDate($v['PaymentDate']);
						$date_sort=($v['PaymentDate'])?strtotime($v['PaymentDate']):0;
						if(!$f1){
							if($this->ACTION==='event'){
								$f1=validDate($has_form['date']);
								$date_sort=($f1)?strtotime($has_form['date']):0;
							}else{
								if($f1=issetCheck($v,'SalesDate')){
									$f1=validDate($f1);
									$date_sort=strtotime($f1);
								}else{
									$date_sort=0;
									$f1='-';
								}
							}
						}
						$f3=toPounds($v['SoldPrice']);
						$td_extra='<td data-field="PaymentDate" data-sort-value="'.$date_sort.'">'.$f1.'</td><td data-field="ItemTitle">'.$f2.'</td><td data-field="ItemPrice">'.$f3.'</td>';
						$row_label=$member['Name'].'<br><small class="text-navy">'.$member['CGradeName'].' / '.$member['Dojo'].'</small>';
						$row_label='<a data-tooltip title="view/edit details" class="link-dark-blue loadME" data-ref="'.$this->ADMIN_URL.'member/edit/'.$v['MemberID'].'">'.$row_label.'</a>';
						if($this->ACTION==='dojo'){
							if(isset($summary[$v['MemberID']])) extract($summary[$v['MemberID']]);
						}else{
							if(isset($summary[$i])) extract($summary[$i]);
						}
						$balance=$this->getBalance($value,$paid);
						$disable=($qty==0)?'disabled="disabled"':'';
						$control='<button class="button small button-dark-blue gotoME" data-ref="'.$url.'" '.$disable.'><i class="fi-eye"></i> '.$button_label.'</button>';
						$control.=$has_form['button'];
						$edit_url=$this->ADMIN_URL.'events/sales/edit_payment/'.$i;
						if($this->USER['access']>=25) $control.='<button class="button button-dark-purple small loadME" data-ref="'.$edit_url.'/list" title="click to edit"><i class="fi-pencil"></i> Edit</button>';

						break;
					case 'group_summary':
						if($summary){
							foreach($summary as $x){
								$qty+=$x['qty'];
								$value+=$x['value'];
								$paid+=$x['paid'];
							}
						}
						$balance=$this->getBalance($value,$paid);
						$button_label='View Sales';
						$row_id=$i;
						$row_label=$group_name;
						$url=$this->ADMIN_URL.'events/sales/group/'.$product_group_ref;
						$disable=($qty==0)?'disabled="disabled"':'';
						$control='<button class="button small button-navy gotoME" data-ref="'.$url.'" '.$disable.'><i class="fi-arrow-right"></i> '.$button_label.'</button>';
						break;
					case 'group_product':
						$row_id=$i;
						$url=$this->ADMIN_URL.'events/sales/product/'.$i;
						$button_label='View Details';
						$td_extra='<td data-field="ItemPrice">'.toPounds($v['ItemPrice']).'</td>';
						$row_label=$v['ItemTitle'];
						if(isset($summary[$i])) extract($summary[$i]);
						$balance=$this->getBalance($value,$paid);
						$row_label.=' <small>('.$this->CURRENCIES[(int)$v['ItemCurrency']].')</small>';
						if($v['ItemShort']!==''){
							$row_label.='<br><small class="text-navy">'.truncateME(strip_tags($v['ItemShort'])).'</small>';
						}
						$disable=($qty==0)?'disabled="disabled"':'';
						$control='<button class="button small button-navy gotoME" data-ref="'.$url.'" '.$disable.'><i class="fi-arrow-right"></i> '.$button_label.'</button>';
						break;
					case 'product':
						$row_id=$i;
						$member=$this->SLIM->options->get('member_info',(int)$v['MemberID']);
						$event=$this->getEventInfo($v['EventRef']);
						$url=$this->ADMIN_URL.'events/sales/edit_record/'.$i;
						$button_label='Invoice';
						$date=($v['SalesDate'])?$v['SalesDate']:$v['PaymentDate'];
						if(!$date){
							if($event){
								$date=$event['EventDate'];
							}else{
								$date=$v['StartDate'];
							}
						}
						$currency=' <small>('.$this->CURRENCIES[(int)$v['ItemCurrency']].')</small>';					
						$td_extra='<td data-sort-value="'.strtotime($date).'">'.validDate($date).'</td>';
						$td_extra.='<td data-field="ItemTitle">'.$this->PRODUCTS[$this->ARGS]['ItemTitle'].$currency.'</td>';
						$td_extra.='<td data-field="EventCost">'.toPounds($v['SoldPrice']).'</td>';
						$row_label='<a class="link-dark-blue loadME" data-ref="'.$this->ADMIN_URL.'member/edit/'.$v['MemberID'].'" title="view this members record" >'.$member['Name'].'</a><br/><small class="text-purple">'.$event['EventName'].'</small>';
						$this->PRODUCTS[$this->ARGS]['ItemTitle'];
						$value=$v['SoldPrice'];
						//hack!!
						if($value>0) $qty=1;
						$paid=$v['Paid'];
						$balance=$this->getBalance($value,$paid);
						if($v['ItemShort']!==''){
							$row_label.='<br><small class="text-navy">'.truncateME(strip_tags($v['ItemShort'])).'</small>';
						}
						$control='<button class="button small button-dark-blue gotoME" data-ref="'.$url.'"><i class="fi-eye"></i> '.$button_label.'</button>';
						$edit_url=$this->ADMIN_URL.'events/sales/edit_payment/'.$i;
						if($this->USER['access']>=25) $control.='<button class="button button-dark-purple small loadME" data-ref="'.$edit_url.'/list" title="click to edit"><i class="fi-pencil"></i> Edit</button>';
						break;
					
				}
				if($this->REPORTING){
					$control='';
				}else{
					$control='<td>'.$control.'</td>';
				}
				$rows[]='<tr id="kcard-'.$row_id.'"><td data-field="id">'.$row_id.'</td><td data-field="row_label">'.$row_label.'</td>'.$td_extra.'<td data-field="Qty">'.$qty.'</td><td data-field="EventCost">'.toPounds($value).'</td><td data-field="PaymentAmount">'.toPounds($paid).'</td><td data-field="Balance">'.$balance.'</td>'.$control.'</tr>';
			}
		}
		if($this->ACTION==='group_summary'){
			return current($rows);
		}
		return $rows;
	}
	private function renderSalesInvoice(){
		$mode=issetCheck($this->ROUTE,4);//for download
		$labels=array('INVOICE','Date','Amount Due','Item','Description','Unit Cost','Quantity','Price','Total','Amount Paid','Balance Due','Terms');
		$thead='<th>Item</th><th>Description</th><th>Price</th><th>Qty</th><th>Value</th><th>Paid</th><th>Balance</th><th>Currency</th>';
		$payday=$invno=$invday=$rollcall=$event=false;
		$room=false;
		$data=$this->crunchData2();
		$memb=current($data['members']);
		$member=$memb['Name'].'<br/>'.$memb['CGradeName'].'<br/>'.$memb['Dojo'];
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
			$desc=$data['product']['ItemShort'];
			if(!$event_ref) $event_ref=(int)$v['EventRef'];
			if($v['EventRef']) $desc.'<br/>'.$data['event']['EventName'].' / '.validDate($data['event']['EventDate']);
			if($mode==='download'){
				$row[]=array($name,$desc,$qty,$price,$value	);				
			}else{
				$row[]='<tr><td>'.$name.'</td><td>'.$desc.'</td><td>'.$price.'</td><td>'.$qty.'</td><td>'.$value.'</td></tr>';
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
		if($this->USER['access']>=25){
			$edit_url=$this->ADMIN_URL.'events/sales/edit_payment/'.$invno.'/ref';
			$paid='<a data-tooltip title="click to edit payment" class="loadME link-purple" data-ref="'.$edit_url.'">'.$paid.'</a>';
		}


		$fields=array(
			'address'=>'AHK / SKV<br/>Basel',
			'member_info'=>($mode==='download')?$member:'<a data-tooltip title="click to view details" class="link-dark-blue loadME" data-ref='.$this->ADMIN_URL.'member/edit/'.$memb['MemberID'].'">'.$member.'</a>',
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
		if($mode==='download'){
			$fields['render']='D';
			$fields['docname']='invoice_'.$invno.'.pdf';
			$this->SLIM->PDF->renderInvoice($fields);
			die;
		}
		$fields['rows']=implode('',$row);
		$tpl=($mode==='download')?'invoice_pdf':'invoice_edit';
		$tpl=file_get_contents(APP.'templates/app.'.$tpl.'.html');
		$inv=replaceMe($fields,$tpl);
		$this->OUTPUT['content']=$inv;

	}
	private function renderSalesInvoice_old(){
		$mode=issetCheck($this->ROUTE,4);//for download
		$data=$this->crunchData();
		$rooms=$this->SLIM->options->get('room_types',$data['Room']);
		$labels=array('INVOICE','Date','Amount Due','Item','Description','Unit Cost','Quantity','Price','Total','Amount Paid','Balance Due','Terms');
		$balance=$this->getBalance($data['EventCost'],$data['PaymentAmount']);
		$total=toPounds($data['EventCost']);
		$date=issetCheck($data['submitted_form'],'LogDate',$data['event']['EventDate']);
		$room=$rooms[$data['Room']];
		$qty=1;
		//product row
		if($mode==='download'){
			$row[]=array(
						$data['product']['ItemTitle'].'<br/>Room: '.$room,
						$data['product']['ItemShort'].'<br/>'.$data['event']['EventName'].' / '.validDate($data['event']['EventDate']),
						1,
						toPounds($data['product']['ItemPrice']),
						toPounds($data['product']['ItemPrice'])
					);
		}else if($mode==='transfer'){
			$row[]=array(
				'Ref'=>false,
				'MemberID'=>$data['MemberID'],
				'ItemID'=>0,
				'ItemType'=>$data['product']['ItemGroup'],
				'SoldPrice'=>$data['product']['ItemPrice'],
				'Currency'=>$data['product']['ItemCurrency'],
				'SalesDate'=>false,
				'StartDate'=>false,
				'EndDate'=>null,
				'Length'=>0,
				'Paid'=>0,
				'PaymentDate'=>null,
				'PaymentRef'=>false,
				'EventRef'=>$data['EventID'],
				'EventLogRef'=>$data['EventID'],
				'Status'=>0,
				'Notes'=>false
			);
		}else{
			$row[]='<tr class="item-row">
						<td class="item-name">'.$data['product']['ItemTitle'].'<br/><small><strong>Room:</strong> '.$room.'</small></td>
						<td class="description">'.$data['product']['ItemShort'].'<br/><small class="text-navy">'.$data['event']['EventName'].' / '.validDate($data['event']['EventDate']).'</small></td>
						<td class="cost">'.toPounds($data['product']['ItemPrice']).'</td>
						<td class="qty">1</td>
						<td class="price">'.toPounds($data['product']['ItemPrice']).'</td>
			</tr>';
		}
		//shinsa row
		if((int)$data['Shinsa']){
			$shinsa=$data['products'][$data['Shinsa']];
			$qty++;
			if($mode==='download'){
				$row[]=array(
						$shinsa['ItemTitle'],
						$shinsa['ItemShort'],
						1,
						toPounds($shinsa['ItemPrice']),
						toPounds($shinsa['ItemPrice'])
					);
			}else if($mode==='transfer'){
				$row[]=array(
					'Ref'=>false,
					'MemberID'=>$data['MemberID'],
					'ItemID'=>0,
					'ItemType'=>$shinsa['ItemGroup'],
					'SoldPrice'=>$shinsa['ItemPrice'],
					'Currency'=>$shinsa['ItemCurrency'],
					'SalesDate'=>false,
					'StartDate'=>false,
					'EndDate'=>null,
					'Length'=>0,
					'Paid'=>0,
					'PaymentDate'=>null,
					'PaymentRef'=>false,
					'EventRef'=>$data['EventID'],
					'EventLogRef'=>$data['EventID'],
					'Status'=>0,
					'Notes'=>false
				);
			}else{
				$row[]='<tr class="item-row">
							<td class="item-name">'.$shinsa['ItemTitle'].'</td>
							<td class="description">'.$shinsa['ItemShort'].'</small></td>
							<td class="cost">'.toPounds($shinsa['ItemPrice']).'</td>
							<td class="qty">1</td>
							<td class="price">'.toPounds($shinsa['ItemPrice']).'</td>
				</tr>';
			}
		}
		//fee row
		if((int)$data['AdditionalFee']){
			$fee=$this->getProduct('id',$data['AdditionalFee']);
			$fee=current($fee);
			$qty++;
			if($mode==='download'){
				$row[]=array(
						$fee['ItemTitle'],
						$fee['ItemShort'],
						1,
						toPounds($fee['ItemPrice']),
						toPounds($fee['ItemPrice'])
					);
			}else if($mode==='transfer'){
				$row[]=array(
					'Ref'=>false,
					'MemberID'=>$data['MemberID'],
					'ItemID'=>0,
					'ItemType'=>$fee['ItemGroup'],
					'SoldPrice'=>$fee['ItemPrice'],
					'Currency'=>$fee['ItemCurrency'],
					'SalesDate'=>false,
					'StartDate'=>false,
					'EndDate'=>null,
					'Length'=>0,
					'Paid'=>0,
					'PaymentDate'=>null,
					'PaymentRef'=>false,
					'EventRef'=>$data['EventID'],
					'EventLogRef'=>$data['EventID'],
					'Status'=>0,
					'Notes'=>false
				);
			}else{
				$row[]='<tr class="item-row">
							<td class="item-name">'.$fee['ItemTitle'].'</td>
							<td class="description">'.$fee['ItemShort'].'</td>
							<td class="cost">'.toPounds($fee['ItemPrice']).'</td>
							<td class="qty">1</td>
							<td class="price">'.toPounds($fee['ItemPrice']).'</td>
				</tr>';
			}
		}
		$price=$data['EventCost'];
		$edit_url=$this->ADMIN_URL.'events/sales/edit_payment/'.$data['EventLogID'];
		$this->NAV['back']='<button class="button button-navy backME" data-ref="" title="back"><i class="fi-arrow-left"></i> Back</button>';
		if($this->USER['access']>=25) $this->NAV['edit']='<button class="button button-dark-purple loadME" data-ref="'.$edit_url.'" title="click to edit"><i class="fi-pencil"></i> Edit</button>';
		$member=$data['member']['FirstName'].' '.$data['member']['LastName'].'<br/>'.$data['member']['CGradeName'].'<br/>'.$data['member']['Dojo'];
		$paid=toPounds($data['PaymentAmount']);
		if($this->USER['access']>=25){
			$paid='<a data-tooltip title="click to edit payment" class="loadME link-purple" data-ref="'.$edit_url.'">'.toPounds($data['PaymentAmount']).'</a>';
		}
		$fields=array(
			'address'=>'AHK / SKV<br/>Basel',
			'member_info'=>($mode==='download')?$member:'<a data-tooltip title="click to view details" class="link-dark-blue loadME" data-ref='.$this->ADMIN_URL.'member/edit/'.$data['MemberID'].'">'.$member.'</a>',
			'invoice_no'=>$data['EventLogID'],
			'invoice_date'=>validDate($date),
			'balance'=>$balance,
			'total'=>$total,
			'paid'=>($mode==='download')?toPounds($data['PaymentAmount']):$paid,
			'terms'=>'-',
			'rows'=>$row,
			'status'=>(($data['EventCost']-$data['PaymentAmount'])>0)?'Due':'Paid',
			'qty'=>$qty
		);
		if($mode==='download'){
			$fields['render']='D';
			$fields['docname']='invoice_'.$this->ARGS.'.pdf';
			$this->SLIM->PDF->renderInvoice($fields);
			die;
		}
		$fields['rows']=implode('',$row);
		$tpl=($mode==='download')?'invoice_pdf':'invoice_edit';
		$tpl=file_get_contents(APP.'templates/app.'.$tpl.'.html');
		$inv=replaceMe($fields,$tpl);
		$this->OUTPUT['content']=$inv;
	}
	private function renderStatusStat($value=0,$paid=0){
		$state=0;
		if($value>0){
			if($value==$paid){
				$state=5;
			}else if($paid>$value){
				$state=6;
			}
		}else if($value==0 && $paid==0){
			$state=7;
		}		
		return '<p class="stat text-'.$this->STATES[$state]['color'].'">'.ucME($this->STATES[$state]['name']).'</p>';
	}
	private function renderPaymentRecord(){
		$chk=issetCheck($this->ROUTE,4);
		$target='#invoice_wrap';
		if(in_array($chk,array('list','ref','id','log'))){
			$target='#kcard-'.$this->ARGS;
			$this->INV_REF_TYPE=($chk==='list')?'id':$chk;
		}
		$payday=$payref=$invno=$rollcall=$event=$member=false;
		$states=$durations=[];
		$room='None';
		$data=$this->crunchData2();
		$balance=$this->getBalance($data['metrics']['value'],$data['metrics']['paid']);
		$total_paid=$data['metrics']['paid'];
		foreach($data['log'] as $i=>$v){
			$payday=$v['PaymentDate'];
			$payref=$v['PaymentRef'];
			$invno=$v['Ref'];
			$currency=($v['Currency']==1)?'CHF':'EUR';
			if($total_paid){
				if($v['SoldPrice']<=$total_paid){
					$v['Paid']=$v['SoldPrice'];
				}else{
					$v['Paid']=$total_paid;
				}
				$total_paid=($total_paid-$v['Paid']);
			}else{
				$v['Paid']=0;
			}
			$bal=$this->getBalance($v['SoldPrice'],$v['Paid']);
			$row[]='<tr><td>'.$v['ItemName'].'</td><td>'.toPounds($v['SoldPrice']).'</td><td>'.toPounds($v['Paid']).'</td><td>'.$bal.'</td><td>'.$currency.'</td></tr>';
			$states[]=$v['Status'];
			if($v['StartDate']){//subscriptions
				$durations[$i]='<strong class="text-gray">'.$v['ItemName'].' Subscription</strong><br/><strong>Start: </strong>'.validDate($v['StartDate']).' <strong>End: </strong>'.validDate($v['EndDate']);
				if($this->USER['access']>=25){
					$durations[$i].='<br/><button class="button small loadME button-dark-purple" data-ref="'.$this->ADMIN_URL.'subscription/edit_subscription/'.$v['ID'].'"><i class="fi-pencil"></i> Edit Subscription</button>';
				}
			}
		}
		//totals row
		$row[]='<tr class="bg-light-gray" style="font-weight:bold"><td class="bg-light-gray">Total</td><td>'.toPounds($data['metrics']['value']).'</td><td>'.toPounds($data['metrics']['paid']).'</td><td>'.$balance.'</td><td></td></tr>';
		//info - members
		if($memb=current($data['members'])){
			$member='<p><a data-tooltip title="click to view details" class="link-dark-blue overLoad" data-ref='.$this->ADMIN_URL.'member/view/'.$memb['MemberID'].'">'.$memb['Name'].'<br/>'.$memb['CGradeName'].'<br/>'.$memb['Dojo'].'</a></p>';
		}
		//info - event
		if($data['elog']){
			$room=($data['elog']['Room']>0)?$this->SLIM->Options->get('room_types',$data['elog']['Room']):'None';			
			$rollcall='<button class="button small button-navy loadME" data-ref="'.$this->ADMIN_URL.'eventslog/edit/'.$data['elog']['EventLogID'].'"><i class="fi-torso"></i> Rollcall Record</button>';
		}
		if($evt=current($data['events'])){
			$event='<p><strong>Name:</strong> <a title="click to view details" class="link-dark-blue overLoad" data-ref='.$this->ADMIN_URL.'events/view/'.$evt['EventID'].'">'.$evt['EventName'].'</a><br/><strong>Date:</strong> '.validDate($evt['EventDate']).'<br/><strong>Room:</strong> '.$room.'<br/>'.$rollcall.'</p>';
		}
		if($durations){
			$event.=implode('<br/>',$durations);
		}
		//payment
		$pref='<label>Payment Ref: <input type="text" name="PaymentRef" value="'.$payref.'"/></label>';
		$pdate='<label>Payment Date: <input type="date" name="PaymentDate" value="'.validDate($payday).'"/></label>';
		$opts=$deleter='';
		$status_stat=$this->renderStatusStat($data['metrics']['value'],$data['metrics']['paid']);
		$form='<input type="hidden" name="target" value="'.$target.'"/><input type="hidden" name="action" value="update_payment"/><input type="hidden" name="id" value="'.$this->ARGS.'"/><input type="hidden" name="id_type" value="'.$this->INV_REF_TYPE.'"/>';
		$form.='<label>Total Amount Paid: <small>(format must be 0.00)</small><input type="text" name="PaymentAmount" value="'.toPounds($data['metrics']['paid']).'"/></label>';
		$form.='<div class="grid-x margin-x"><div class="cell auto">'.$pdate.'</div><div class="cell auto">'.$pref.'</div></div>';
		$form.='<label>Status: <small>Updated by the system</small>'.$status_stat.'</label>';
		
		if(hasAccess($this->USER,'events','delete')){
			$deleter='<button type="submit" class="button button-red loadME" data-ref="'.$this->ADMIN_URL.'sales/delete/'.$invno.'/ref" ><i class="fi-trash"></i> Delete</button>';
		}
		$title='Sales Ref: '.$invno;
		$parts=[
			'action_url'=>$this->ADMIN_URL.'sales/update_payment/'.$this->ARGS,
			'member'=>$member,
			'event'=>$event,
			'table'=>implode('',$row),
			'form'=>$form,
			'delete'=>$deleter,
		];
		$tpl=file_get_contents(TEMPLATES.'parts/tpl.payment-edit-modal.html');
		$content=replaceMe($parts,$tpl);
		if($this->AJAX){
			echo renderCard_active($title,$content,$this->SLIM->closer);
			die;
		}else{
			$this->OUTPUT['title']=$title;
			$this->OUTPUT['content']=renderCard_active($title,$content);	
		}
	}
	
	private function renderTopNav(){
		if(!empty($this->NAV)){
			$controls='<div class="button-group float-right small">'.implode('',$this->NAV).'</div>';
			$this->SLIM->topbar->setInfoBarControls('right',array($controls),true);
		}			
	}
	private function crunchData2(){
		$data=array();
		$summary=$_data=[];
		$SLS=$this->SLIM->Sales;
		if(!$this->REPORTING){
			$url=($this->ACTION==='edit_record')?$this->ADMIN_URL.implode('/',$this->ROUTE).'/download':$this->ADMIN_URL.implode('/',$this->ROUTE).'/report';
			$this->NAV['download']='<button class="button button-lavendar gotoME" data-ref="'.$url.'" ><i class="fi-download"></i> Download</button>';
		}
		switch($this->ACTION){
			case 'event':
				$_data=$SLS->getRecords('event',$this->ARGS);
				$summary=$this->summarizeProductSales($_data['log']);
				$data=$this->tableRowSummary($_data,$summary);
				$this->OUTPUT['title']='Sales for Event: <span class="subheader">'.$_data['events'][$this->ARGS]['EventName'].'</span>';
				$this->NAV['back']='<button class="button button-navy backME" data-ref="#" title="back to list"><i class="fi-arrow-left"></i> Back</button>';
				break;
			case 'by_member':
			case 'by_dojo':
				$_data=$SLS->getRecords($this->ACTION);
				$summary=$this->summarizeProductSales($_data['log']);
				$data=$this->tableRowSummary($_data,$summary);
				$this->OUTPUT['title']=($this->ACTION==='by_dojo')?'Sales by Dojo':'Sales by Member';
				break;
			case 'member':
				$_data=$SLS->getRecords('member',$this->ARGS);
				$summary=$this->summarizeProductSales($_data['log']);
				$data=$this->tableRowSummary($_data,$summary);
				$this->OUTPUT['title']='Sales for Member: <span class="subheader">'.$_data['members'][$this->ARGS]['Name'].'</span>';
				$this->NAV['back']='<button class="button button-navy backME" data-ref="#" title="back to list"><i class="fi-arrow-left"></i> Back</button>';
				break;
			case 'dojo':
				$_data=$SLS->getRecords('dojo',$this->ARGS);
				$summary=$this->summarizeProductSales($_data['log']);
				$data=$this->tableRowSummary($_data,$summary);
				$this->OUTPUT['title']='Sales for Dojo: <span class="subheader">'.$_data['dojos'][$this->ARGS]['LocationName'].'</span>';
				$this->NAV['back']='<button class="button button-navy backME" data-ref="#" title="back to list"><i class="fi-arrow-left"></i> Back</button>';
				break;
			case 'by_event':
				$_data=$SLS->getRecords('event');
				$summary=$this->summarizeProductSales($_data['log']);
				$data=$this->tableRowSummary($_data,$summary);
				$this->OUTPUT['title']='Sales by Event';
				break;
			case 'unpaid':
				$this->OUTPUT['title']='Sales: <span class="subheader text-maroon">Outstanding</span>';
				$_data=$SLS->getRecords('unpaid');
				$summary=$this->summarizeProductSales($_data['log']);
				$data=$this->tableRowSummary($_data,$summary);
				break;
			case 'overpaid':
				$this->OUTPUT['title']='Sales: <span class="subheader text-red-orange">Overpaid / Refunds</span>';
				$_data=$SLS->getRecords('overpaid');
				$summary=$this->summarizeProductSales($_data['log']);
				$data=$this->tableRowSummary($_data,$summary);
				break;
			case 'group_summary':
				$prods=$this->getProduct('group_summary');
				$this->OUTPUT['title']='Sales by Product Groups';
				$x_sum=[];
				foreach($prods as $group){
					$prods=$this->getProduct('group',$group['ItemGroup']);
					$sales=($prods)?$SLS->getProductSales(array_keys($prods)):false;
					$summary=$this->summarizeProductSales($sales);
					$data[]=$this->tableRowSummary($prods,$summary,$group['ItemGroup']);
					foreach($summary as $x=>$y){
						foreach($y as $i=>$v){
							if(!isset($x_sum[$i])) $x_sum[$i]=0;
							$x_sum[$i]+=$v;
						}
					}
				}
				if($x_sum) $summary=array(0=>$x_sum);
				break;
			case 'group_product':
				$prods=$this->getProduct('group',$this->ARGS);
				$sales=($prods)?$SLS->getProductSales(array_keys($prods)):false;
				$_data=$sales;
				$summary=$this->summarizeProductSales($sales);
				$data=$this->tableRowSummary($prods,$summary,$this->ARGS);
				$title=($this->ARGS)?$this->GROUPS[$this->ARGS]:'Single Items';
				$this->OUTPUT['title']='Sales for Product Group: <span class="subheader">'.$title.'</span>';
				$this->NAV['back']='<button class="button button-navy gotoME" data-ref="'.$this->ADMIN_URL.'events/sales/" title="back to products"><i class="fi-arrow-left"></i> Back</button>';
				break;
			case 'product':
				$prods=$this->getProduct('id',$this->ARGS);
				$this->PRODUCTS=$prods;
				$sales=$_data=$SLS->getProductSales($this->ARGS);
				$summary=$this->summarizeProductSales($sales);
				$data=$this->tableRowSummary($summary['sales'],$summary['summary'],$this->ARGS);
				$this->OUTPUT['title']='Sales by Product: <span class="subheader">'.$prods[$this->ARGS]['ItemTitle'].'</span>';
				$this->NAV['back']='<button class="button button-navy gotoME" data-ref="'.$this->ADMIN_URL.'events/sales/group/'.$prods[$this->ARGS]['ItemGroup'].'" title="back to product group"><i class="fi-arrow-left"></i> Back</button>';
				break;
			case 'edit_record':
			case 'edit_payment':
				$t=(strlen($this->ARGS)==11)?'ref':'id';
				$data=$SLS->getInvoiceRecord($t,$this->ARGS);
				if(is_array($data) && !empty($data)){
					$log=current($data['log']);
					$this->OUTPUT['title']='Invoice #'.$log['Ref'].' for : <span class="subheader">'.$log['MemberName'].'</span>';
				}else{
					$this->OUTPUT['title']='No items found...';
				}
				break;
		}
		if($summary) $this->setMetrics($summary);
		$this->CRUNCH=$_data;
		return $data;
	}

	private function crunchData(){
		$data=array();
		$summary=$_data=[];
		if(!$this->REPORTING){
			$url=($this->ACTION==='edit_record')?$this->ADMIN_URL.implode('/',$this->ROUTE).'/download':$this->ADMIN_URL.implode('/',$this->ROUTE).'/report';
			$this->NAV['download']='<button class="button button-lavendar gotoME" data-ref="'.$url.'" ><i class="fi-download"></i> Download</button>';
		}

		switch($this->ACTION){
			case 'event':
				$_data=$this->getSalesRecord_report('event',$this->ARGS);
				$summary=$this->summarizeProductSales($_data['log']);
				$data=$this->tableRowSummary($_data,$summary);
				$this->OUTPUT['title']='Sales for Event: <span class="subheader">'.$_data['events'][$this->ARGS]['EventName'].'</span>';
				$this->NAV['back']='<button class="button button-navy backME" data-ref="#" title="back to list"><i class="fi-arrow-left"></i> Back</button>';
				break;
			case 'by_member':
			case 'by_dojo':
				$_data=$this->getSalesRecord_report($this->ACTION);
				$summary=$this->summarizeProductSales($_data['log']);
				$data=$this->tableRowSummary($_data,$summary);
				$this->OUTPUT['title']=($this->ACTION==='by_dojo')?'Sales by Dojo':'Sales by Member';
				break;
			case 'member':
				$_data=$this->getSalesRecord_report('member',$this->ARGS);
				$summary=$this->summarizeProductSales($_data['log']);
				$data=$this->tableRowSummary($_data,$summary);
				$this->OUTPUT['title']='Sales for Member: <span class="subheader">'.$_data['members'][$this->ARGS]['Name'].'</span>';
				$this->NAV['back']='<button class="button button-navy backME" data-ref="#" title="back to list"><i class="fi-arrow-left"></i> Back</button>';
				break;
			case 'dojo':
				$_data=$this->getSalesRecord_report('dojo',$this->ARGS);
				$summary=$this->summarizeProductSales($_data['log']);
				$data=$this->tableRowSummary($_data,$summary);
				$this->OUTPUT['title']='Sales for Dojo: <span class="subheader">'.$_data['dojos'][$this->ARGS]['LocationName'].'</span>';
				$this->NAV['back']='<button class="button button-navy backME" data-ref="#" title="back to list"><i class="fi-arrow-left"></i> Back</button>';
				break;
			case 'by_event':
				$_data=$this->getSalesRecord_report('by_event');
				$summary=$this->summarizeProductSales($_data['log']);
				$data=$this->tableRowSummary($_data,$summary);
				$this->OUTPUT['title']='Sales by Event';
				break;
			case 'unpaid':
				$this->OUTPUT['title']='Sales: <span class="subheader text-maroon">Outstanding</span>';
				$_data=$this->getSalesRecord_report('unpaid');
				$summary=$this->summarizeProductSales($_data['log']);
				$data=$this->tableRowSummary($_data,$summary);
				break;
			case 'overpaid':
				$this->OUTPUT['title']='Sales: <span class="subheader text-red-orange">Overpaid / Refunds</span>';
				$_data=$this->getSalesRecord_report('overpaid');
				$summary=$this->summarizeProductSales($_data['log']);
				$data=$this->tableRowSummary($_data,$summary);
				break;
			case 'group_summary':
				$prods=$this->getProduct('group_summary');
				$this->OUTPUT['title']='Sales by Product Groups';
				$x_sum=[];
				foreach($prods as $group){
					$prods=$this->getProduct('group',$group['ItemGroup']);
					$sales=($prods)?$this->getProductSales(array_keys($prods)):false;
					$summary=$this->summarizeProductSales($sales);
					$data[]=$this->tableRowSummary($prods,$summary,$group['ItemGroup']);
					foreach($summary as $x=>$y){
						foreach($y as $i=>$v) $x_sum[$i]+=$v;
					}
				}
				if($x_sum) $summary=array(0=>$x_sum);
				break;
			case 'group_product':
				$prods=$this->getProduct('group',$this->ARGS);
				$sales=($prods)?$this->getProductSales(array_keys($prods)):false;
				$_data=$sales;
				$summary=$this->summarizeProductSales($sales);
				$data=$this->tableRowSummary($prods,$summary,$this->ARGS);
				$this->OUTPUT['title']='Sales for Product Group: <span class="subheader">'.$this->GROUPS[$this->ARGS].'</span>';
				$this->NAV['back']='<button class="button button-navy gotoME" data-ref="'.$this->ADMIN_URL.'events/sales/" title="back to products"><i class="fi-arrow-left"></i> Back</button>';
				break;
			case 'product':
				$prods=$this->getProduct('id',$this->ARGS);
				$this->PRODUCTS=$prods;
				$sales=$_data=$this->getProductSales($this->ARGS);
				$summary=$this->summarizeProductSales($sales);
				$data=$this->tableRowSummary($summary['sales'],$summary['summary'],$this->ARGS);
				$this->OUTPUT['title']='Sales by Product: <span class="subheader">'.$prods[$this->ARGS]['ItemTitle'].'</span>';
				$this->NAV['back']='<button class="button button-navy gotoME" data-ref="'.$this->ADMIN_URL.'events/sales/group/'.$prods[$this->ARGS]['ItemGroup'].'" title="back to product group"><i class="fi-arrow-left"></i> Back</button>';
				break;
			case 'edit_record':
			case 'edit_payment':
				$data=$this->getSalesRecord_eventlog($this->ARGS);
				$this->OUTPUT['title']='Invoice #'.$this->ARGS.' for : <span class="subheader">'.$data['member']['FirstName'].' '.$data['member']['LastName'].'</span>';
				break;
		}
		if($summary) $this->setMetrics($summary);
		$this->CRUNCH=$_data;
		return $data;
	}
	
	private function hasForm($log_id=0,$return=false){
		$form_id=$form_date=0;
		if($log_id){
			$DB=$this->SLIM->db->FormsLog();
			$rec=$DB->select('ID,LogDate')->where('EventLogID',$log_id);
			if(count($rec)>0){
				$form_id=$rec[0]['ID'];
				$form_date=$rec[0]['LogDate'];
			}
		}
		$url=($form_id)?$this->ADMIN_URL.'events/submitted_form/'.$form_id:'#nogo';
		$disabled=($form_id)?'loadME':'disabled';
		$title=($form_id)?'view submitted form':'no form';
		$button='<button class="button small button-blue '.$disabled.'" data-ref="'.$url.'" title="'.$title.'" '.$disabled.'><i class="fi-eye"></i> Form</button>';
		if($return=='id'){
			return $form_id;
		}else if($return==='button'){
			return $button;
		}else{
			return array('id'=>$form_id,'button'=>$button,'date'=>$form_date);
		}
	}
	
	private function setMetrics($data){
		$tots=array();
		if($this->ACTION==='product'){
			$data=$data['summary'];
		}
		foreach($data as $i=>$v){
			foreach($v as $x=>$y){
				if(!isset($tots[$x])) $tots[$x]=0;
				$tots[$x]+=$y;
			}
		}
		$balance=($tots['value']-$tots['paid']);
		$cls=($balance>0)?'maroon':'dark-green';
		if($balance<0) $cls='red-orange';
		$met[]='<span class="label">Totals:  Qty. <span class="label-text">'.$tots['qty'].'</span></span>';
		$met[]='<span class="label">Value <span class="label-text">'.toPounds($tots['value']).'</span></span>';
		$met[]='<span class="label">Paid <span class="label-text">'.toPounds($tots['paid']).'</span></span>';
		$met[]='<span class="label">Balance <span class="label-text text-'.$cls.'">'.toPounds($balance).'</span></span>';
		$this->OUTPUT['metrics']='<div class="grid-x sales-info"><div class="cell auto">'.implode('',$met).'</div></div>';
	}
	private function renderTable(){
		$htitle=$filter=$table='';
		$DATA=$this->crunchData2();
		if(isset($DATA['error'])){
			$table=$DATA['error'];
		}else{
			$thead=array('Ref'=>'int','Name'=>'string','Date'=>'date','Item'=>'string','Price'=>'float','Qty.'=>'int','Value'=>'float','Paid'=>'float','Balance'=>'float','Controls'=>false);
			$th='';
			if($this->REPORTING) unset($thead['Controls']);
			foreach($thead as $i=>$v){
				if(($i==='Item' || $i==='Price' || $i==='Date') && $this->ACTION==='group_summary'){
					//skip
					//check $td_extra for details :)
				}elseif(($i==='Item'|| $i==='Date') && ($this->ACTION==='group_product'||$this->ACTION==='by_dojo')){
					//skip;
				}elseif(($i==='Item'|| $i==='Price') && ($this->ACTION==='by_event'||$this->ACTION==='by_member'||$this->ACTION==='by_dojo')){
					//skip;
				}else{
					$th.='<th data-sort="'.$v.'">'.$i.'</th>';
				}
			}
			
			if($this->REPORTING){
				$table=$this->renderReportTable($th,$DATA);
			}else{
				$filter='<div id="filter">'.$this->SLIM->zurb->inlineLabel('Filter','<input id="dfilter" class="input-group-field" type="text"/>');
				$filter.='<div class="metrics">'.(count($DATA)).' Record(s)</div></div>';
				$table='<table id="dataTable" class="row_hilight"><thead><tr>'.$th.'</tr></thead><tbody>'.implode('',$DATA).'</tbody></table>';
				$this->SLIM->assets->set('js','my_table','JQD.ext.initMyTable("#dfilter","#dataTable");');
			}
		}
		$this->OUTPUT['content']=$htitle.$filter.$table;		

	}
	private function renderReportTable($thead,$trows){
		$args['headers']=$thead;
		$args['rows']=implode('',$trows);
		$args['selector']=false;
		$args['sorter']=false;
		$rt=$this->ROUTE;
		array_pop($rt);
		$durl=$this->ADMIN_URL.implode('/',$rt);
		$dlabel='Back to List';
		$args['controls']='<button class="button button-navy gotoME" data-ref="'.$durl.'"><i class="fi-arrow-left"></i> '.$dlabel.'</button>';
		$tpl=file_get_contents(APP.'templates/app.report_grid.html');
		$table=replaceMe($args,$tpl);
		$this->SLIM->assets->set('scripts','mgrid','js/ui_mgrid.min.js');
		$this->SLIM->assets->set('js','init_mgrid','JQD.ext.initMGrid("report_mgrid");');
		return $table;
	}
	private function renderPDF($inv=false){
		$logo=$this->SLIM->options->get('pdf_logo');
		$down['html']=$inv;
		$down['title']=$this->SLIM->language->getStandard('invoice');// pdf title for header
		$down['sub_title']=false;
		$down['logo']=array('logo'=>$logo,'text'=>"Association Helvetique\nDe Kyudo,\n2500 BIEL/BIENNE");// array of address & image for pdf header
		$down['docname']='invoice'.$this->ARGS.'.pdf'; // for downloading
		$down['render_type']='D';
		$r=$this->SLIM->PDF->render($down);
		return $down['docname'];
	}

}
