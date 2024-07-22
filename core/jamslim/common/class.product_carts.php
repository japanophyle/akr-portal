<?php

class product_carts{
	private $SLIM;
	private $DATA;
	private $OUTPUT=['title'=>'','content'=>'','menu'=>[]];
	private $PERMLINK;
	private $PERMBACK;
	private $ID;
	private $DEFREC=['ItemID'=>0,'ItemTitle'=>'','ItemContent'=>'','ItemShort'=>'','ItemOrder'=>0,'ItemStatus'=>0];
	private $PRODUCTS=[];
	private $OPTIONS=[];
	private $SITE='public';
	private $SUBS_GROUP_ID;
	private $MEMB_CAT_ID;
	private $FEE_CAT_ID;
	private $SUBSCRIPTION_ONLY=true;
		
	public $AJAX;
	public $REQUEST;
	public $USER;
	public $METHOD;
	public $SECTION;
	public $ACTION;
	public $PLUG;
	public $ADMIN;
	public $LEADER;
	public $ROUTE;	

	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->ROUTE=$slim->router->get('route');
		if($this->ROUTE[0]==='admin'){
			$this->SITE='admin';
			$this->PERMBACK=URL.'admin/product/';
			$this->PERMLINK=$this->PERMBACK.'cart/';
		}
		$this->init();
	}
	
	private function init(){
		$this->getSubsCat();
		$this->PRODUCTS=$this->SLIM->DataBank->get('item_type',['ITEMTYPE'=>'product']);
		$this->OPTIONS['ItemOrder']=$this->SLIM->Options->get('dojos');
		$this->OPTIONS['ItemStatus']=$this->SLIM->Options->get('active');
		$this->ACTION=issetCheck($this->ROUTE,3);
		$this->ID=issetCheck($this->ROUTE,4,0);
		if($this->ACTION==='new') $this->ID='new';
	}
	
	function render($what=false,$args=null){
		if(!$what) $what=$this->ACTION;
		if($this->METHOD==='POST') $this->renderPost();
		switch($what){
			case 'carts':
				$this->renderCarts();
				break;
			case 'edit':
			case 'new':
				if(!$args) $args=$this->ID;
				$this->renderEditCart($args);
				break;
			case 'select_products':
				$this->renderSelectProducts();
				break;
			case 'remove_cart_item':
			case 'remove_cart_item_now':
				if(!$args) $args=$this->ID;
				$this->renderRemoveCartProduct($args);
				break;				
			case 'delete_cart':
			case 'delete_cart_now':
				if(!$args) $args=$this->ID;
				$this->renderDeleteCart($args);
				break;				
			default:
				$this->OUTPUT['content']=msgHandler('Sorry, I don\'t know what you want...',false,false);
		}
		$this->OUTPUT['menu']['right']=$this->renderContextMenu();
		return $this->OUTPUT;
	}
	function renderPost($data=[]){
		if(!$data) $data=$this->REQUEST;
		switch($this->ACTION){
			case 'new_prod_cart':
			case 'update_prod_cart':
				$rsp=$this->saveCarts($data);
				break;
			case 'add_cart_items':
			case 'remove_cart_items':
				$rsp=$this->saveCartProducts($data);
				break;
			default:
				$rsp=['url'=>$this->PERMLINK,'message'=>'Sorry, I don\'t know what to do...'];
		}
		if($this->AJAX){
			echo jsonResponse($rsp);
			die;
		}
		setSystemResponse($rsp['url'],$rsp['message']);
		die($rsp['message']);	
	}
	private function renderDeleteCart($cart_id=0){
		if($cart_id){
			if($this->ACTION==='delete_cart_now'){
				$rec=$this->SLIM->db->Items->where(['ItemID'=>$cart_id,'ItemType'=>'prod_cart']);
				if(count($rec)==1){
					$chk=$rec->delete();
					$msg=($chk)?'Okay, the cart has been deleted.':'Sorry, I could not delete the cart.';
				}else{
					$msg='Sorry, I could not find cart #'.$cart_id.'.';
				}
				$rsp=['url'=>$this->PERMLINK.'carts','message'=>$msg];			
			}else{
				$cart=$this->getCarts($cart_id);
				$prod=$this->renderCartProducts($cart['ItemShort'],'view');
				$content='<div class="callout warning text-center"><p class="h3">Do you want to delete this cart?</p><p><strong>'.$cart['ItemTitle'].'</strong><br/>'.$prod.'</p></div>';
				$content.='<div class="button-group expanded"><button class="button secondary" data-close><i class="fi-x-circle"></i> No, maybe later.</button><button class="button small button-red gotoME small" data-ref="'.$this->PERMLINK.'delete_cart_now/'.$cart_id.'"><i class="fi-check"></i> Yes, do it now.</button></div>';
				echo '<style>.reveal .card-section.main {max-height: 100%;overflow-Y: auto;}</style>';
				echo renderCard_active('Delete Cart #'.$cart_id,$content,$this->SLIM->closer);
				die;				
			}
		}else{
			$rsp=['url'=>$this->PERMLINK,'message'=>'Sorry, no cart ID supplied...'];
		}
		setSystemResponse($rsp['url'],$rsp['message']);
		die($rsp['message']);	
	}
	private function renderRemoveCartProduct($cart_id=0){
		$ref=issetcheck($this->ROUTE,5);
		if($cart_id && $ref){
			if($this->ACTION==='remove_cart_item_now'){			
				$data=['cart_id'=>$cart_id,'add_this'=>[$ref]];
				$rsp=$this->saveCartProducts($data);
			}else{
				$prod=issetCheck($this->PRODUCTS,$ref);
				$name=($prod)?$prod['ItemTitle']:'?? no product '.$ref.' found ??';
				$content='<div class="callout primary text-center"><p class="h3 text-dark-blue">Do you want to remove this product?</p><p><strong>'.$name.'</strong></p></div>';
				$content.='<div class="button-group expanded"><button class="button secondary" data-close><i class="fi-x-circle"></i> No, maybe later.</button><button class="button small button-red gotoME small" data-ref="'.$this->PERMLINK.'remove_cart_item_now/'.$cart_id.'/'.$ref.'"><i class="fi-check"></i> Yes, do it now.</button></div>';
				echo '<style>.reveal .card-section.main {max-height: 100%;overflow-Y: auto;}</style>';
				echo renderCard_active('Remove Item #'.$ref,$content,$this->SLIM->closer);
				die;				
			}
		}else if($cart_id){
			$rsp=['url'=>$this->PERMLINK.'edit/'.$cart_id,'message'=>'Sorry, no product ID supplied...'];
		}else{
			$rsp=['url'=>$this->PERMLINK,'message'=>'Sorry, no cart ID supplied...'];
		}
		setSystemResponse($rsp['url'],$rsp['message']);
		die($rsp['message']);	
	}
	private function saveCartProducts($data=[]){
		$id=issetCheck($data,'cart_id');
		$new=issetCheck($data,'add_this');
		$url=$this->PERMLINK;
		if($id && $new){
			$url.='edit/'.$id;
			$cart=$this->getCarts($id);
			$old=json_decode($cart['ItemShort'],1);
			foreach($new as $i){
				if($this->ACTION==='remove_cart_item'){
					if($k=array_search($i,$old)){
						unset($old[$k]);
					}
				}else{
					if(!in_array($i,$old)) $old[]=$i;
				}
			}
			$update=['ItemShort'=>json_encode($old)];
			$rec=$this->SLIM->db->Items->where('ItemID',$id);
			$chk=$rec->update($update);
			$msg=($chk)?'Okay, the cart has been updated.':'OK, but no changes have been made.';			
		}else{
			$msg='Sorry, the data supplied was not valid... please try again.';
		}
		return ['message'=>$msg,'url'=>$url];
	}
	private function saveCarts($data=[]){
		$save=[];
		foreach($this->DEFREC as $k=>$def){
			$val=issetCheck($data,$k,$def);
			switch($k){
				case 'ItemShort':
					if(is_array($val)){						
						$val=json_encode($val);
					}else{
						$val=json_encode([]);
					}
					if($this->ACTION!=='update_prod_cart') $save[$k]=$val;
					break;
				default:
					$save[$k]=$val;
			}
		}
		$url=$this->PERMLINK;
		if($save){
			unset($save['ItemID']);
			$db=$this->SLIM->db->Items;
			if($this->ACTION==='new_prod_cart'){
				$save['ItemType']='prod_cart';
				$chk=$db->insert($save);
				if($chk){
					$msg='Okay, the cart has been added.';
					$url.='edit/'.$db->insert_id();
				}else{
					$msg='Sorry, there was a problem adding the cart.';					
				}
			}else{
				$id=issetCheck($data,'id');
				$url.='edit/'.$id;
				$rec=$db->where('ItemID',$id);
				$msg='Sorry, there was a problem updating the cart.';	
				if(count($rec)==1){
					$chk=$rec->update($save);
					$msg=($chk)?'Okay, the cart has been updated.':'OK, but no changes were made.';
				}
			}			
		}else{
			$msg='Sorry, nothing was saved or updated.';
		}
		return ['message'=>$msg,'url'=>$url];
	}
	private function getSubsCat(){
		$memb_cat=$fee_cat=0;
		$groups=$this->SLIM->Options->get('product_types');
		$cats=$this->SLIM->Options->get('product_categories');
		foreach($groups as $i=>$v){
			if($v==='Subscriptions'){
				$this->SUBS_GROUP_ID=$i;
				break;
			}			
		}
		foreach($cats as $i=>$v){
			if($v==='Membership'){
				$memb_cat=$this->MEMB_CAT_ID=$i;
			}else if($v==='Fee'){
				$fee_cat=$this->FEE_CAT_ID=$i;
			}
			if($memb_cat && $fee_cat) break;
		}		
	}
	private function getCarts($ref=0){
		$db=$this->SLIM->db->Items;
		$whr=['ItemType'=>'prod_cart'];
		if($ref) $whr['ItemID']=$ref;
		$recs=$db->where($whr);
		$recs=renderResultsORM($recs,'ItemID');
		if($recs){
			if($ref) $recs=current($recs);
		}else{
			$recs=[];
		}
		return $recs;		
	}
	private function formatData($data=[],$mode='edit'){
		$fixed=[];
		foreach($data as $i=>$v){
			switch($i){
				case 'ItemShort':
					if($mode==='list'){
						$items=(trim($v)!=='')?json_decode($v,1):[];
						$fixed[$i]=count($items);
					}else{
						$fixed[$i]=$this->renderCartProducts($v);
					}
					break;
				case 'ItemOrder':
				case 'ItemStatus':
					$fixed[$i]=$this->renderSelectOptions($i,$v,$mode);
					break;
				case 'ItemDate':
					if($mode==='list'){
						$val=explode(' ',$v);
						$fixed[$i]=$val[0];
					}else{
						$fixed[$i]=$v;
					}
					break;
				default:
					$fixed[$i]=$v;
			}
		}
		return $fixed;
	}
	private function renderCartProducts($val,$mode='table'){
		$prods=json_decode($val,1);
		if(!is_array($prods)) $prods=[];
		$tbl='';
		foreach($prods as $p){
			$pd=issetCheck($this->PRODUCTS,$p);
			if($pd){
				if($mode==='table'){
					$tbl.='<tr><td>'.$p.'</td><td>'.$pd['ItemTitle'].'</td><td>'.toPounds($pd['ItemPrice']).'</td><td><button class="button small button-red loadME" data-ref="'.$this->PERMLINK.'remove_cart_item/'.$this->ID.'/'.$p.'"><i class="fi-x"></i> Remove</button></td></tr>';
				}else{
					$tbl.='#'.$p.': '.$pd['ItemTitle'].' - '.toPounds($pd['ItemPrice']).'<br/>';
				}
			}
		}
		if($tbl!==''){
			if($mode==='table'){
				$tbl='<table class="dataTable"><thead><tr><th>ID</th><th>Name</th><th>Price</th><th>Controls</th></tr></thead><tbody>'.$tbl.'</tbody></table>';
			}else{
				//skip;
			}
		}else{
			$tbl=msgHandler('Threr are no products are in this cart.',false,false);
		}
		return $tbl;
	}
	private function renderSelectOptions($fld,$val,$mode='edit'){
		$opt='';
		$options=issetCheck($this->OPTIONS,$fld,[]);
		if($mode==='list'){
			$op=issetCheck($options,$val);
			switch($fld){
				case 'ItemOrder':
					$opt=($op)?$op['ShortName']:'?? no dojo '.$val.' ??';
					break;
				case 'ItemStatus':
					$opt=($op==='active')?'<span class="text-dark-green">'.$op.'</span>':'<span class="text-gray">'.$op.'</span>';
					break;
				default:
					$opt=$val;
			}
		}else{
			foreach($options as $x=>$y){
				$sel=($val==$x)?' selected':'';
				switch($fld){
					case 'ItemOrder':
						if($opt==='') $opt.='<option value="0" >- Any -</option>';
						$lbl=$y['LocationName'].' ('.$y['ShortName'].')';
						break;
					case 'ItemStatus':
						$lbl=$y;
						break;
					default:
						$lbl='???';
				}
				$opt.='<option value="'.$x.'"'.$sel.'>'.$lbl.'</option>';
			}
		}
		return $opt;		
	}
	private function renderSelectProducts(){
		$prods=[];
		foreach($this->PRODUCTS as $i=>$v){
			if($v['ItemStatus']==='active'){
				if($this->SUBSCRIPTION_ONLY){
					if($v['ItemGroup']==$this->SUBS_GROUP_ID && in_array($v['ItemCategory'],[$this->FEE_CAT_ID,$this->MEMB_CAT_ID])){
						$prods[$i]=['ID'=>$i,'Name'=>$v['ItemTitle'],'Price'=>toPounds($v['ItemPrice']),'Select'=>'<input type="checkbox" name="add_this[]" value="'.$i.'"/>'];
					}
				}else if(in_array($v['ItemGroup'],[0,$this->SUBS_GROUP_ID])){
					$prods[$i]=['ID'=>$i,'Name'=>$v['ItemTitle'],'Price'=>toPounds($v['ItemPrice']),'Select'=>'<input type="checkbox" name="add_this[]" value="'.$i.'"/>'];
				}
			}
		}
		$args['data']['data']=$prods;
		$args['before']='filter';
		$tid='prods';
		$form='<form action="'.$this->PERMLINK.'" method="POST"><input type="hidden" name="action" value="add_cart_items"/><input type="hidden" name="cart_id" value="'.$this->ID.'"/>';
		$form.=dataTable($args,'large',$tid);
		$form.='<button class="button expanded button-lavendar" type="submit"><i class="fi-plus"></i> Add Selected Products</button></form>';
		echo renderCard_Active('Select Products',$form,$this->SLIM->closer);
		echo '<script>JQD.ext.initMyTable("#'.$tid.'_filter","#'.$tid.'");</script>';
		die;
	}
	private function renderCarts(){
		$data=$this->getCarts();
		$ct=0;
		if($data){
			$tbl=[];
			foreach($data as $i=>$v){
				$dat=$this->formatData($v,'list');
				$controls='<button class="button button-dark-purple small gotoME" data-ref="'.$this->PERMLINK.'edit/'.$i.'"><i class="fi-pencil"></i> Edit</button>';
				$tbl[$i]=array(
					'ID'=>$i,
					'Name'=>$dat['ItemTitle'],
					'Products'=>$dat['ItemShort'],
					'Dojo'=>$dat['ItemOrder'],
					'Date'=>$dat['ItemDate'],
					'Status'=>$dat['ItemStatus'],
					'Controls'=>$controls
				);
				$ct++;					
			}
			$args['data']['data']=$tbl;
			$args['before']='filter';
			$content=dataTable($args);			
		}else{
			$content=msgHandler('No records found.',false,false);
		}
		$this->OUTPUT['title']='Product Carts ['.$ct.']';
		$this->OUTPUT['content']=$content;
	}
	
	private function renderEditCart($ref=null){
		$data=($ref==='new')?$this->DEFREC:$this->getCarts($ref);
		if($data){
			$sc=($ref==='new')?msgHandler('You will be able to select products once the new cart has been saved.','warning',false):'<button class="button button-lavendar expanded loadME" data-ref="'.$this->PERMLINK.'select_products/'.$ref.'"><i class="fi-list"></i> Select</button>';
			$selector=renderCard_active('Select Products',$sc,false);
			if($ref!=='new' && $this->SUBSCRIPTION_ONLY) $selector.=msgHandler('Note that this cart only works with subscription products, and there should only be one active cart per dojo.<br/><small>(<em>Products must have the group "subscriptions" and the category "membership" or "fee" to be used in this cart.</em>)</small>',false,false);
			$tpl=file_get_contents(TEMPLATES.'parts/tpl.prod_cart-edit.html');
			$data=$this->formatData($data);
			$data['url']=$this->PERMLINK;
			$data['action']=($ref==='new')?'new_prod_cart':'update_prod_cart';
			$data['submit']=($this->ACTION==='new')?'<i class="fi-plus"></i> Save':'<i class="fi-check"></i> Update';
			$data['id']=$ref;
			$data['sidebar']=$selector;
			$content=replaceME($data,$tpl);			
		}else{
			$content=msgHandler('Sorry, no cart found with that ID.',false,false);
		}
		$this->OUTPUT['title']='Product Carts: Edit #'.$ref;
		$this->OUTPUT['content']=$content;
	}
	private function renderContextMenu(){
		$but['back']='<button class="button small button-dark-purple backME" title="back to list" type="button"><i class="fi-arrow-left"></i> Back</button>';
		$but['new']='<button class="button small button-dark-blue gotoME" title="add a new product" data-ref="'.$this->PERMLINK.'new" type="button"><i class="fi-plus"></i> New</button>';
		$lbl=($this->ACTION==='new')?'<i class="fi-plus"></i> Save':'<i class="fi-check"></i> Update';
		$but['save']='<button class="button small button-olive submitME" title="save changes" data-ref="product_cart" type="button">'.$lbl.'</button>';
		$but['delete']='<button class="button small button-maroon loadME" title="delete cart" data-ref="'.$this->PERMLINK.'delete_cart/'.$this->ID.'" type="button"><i class="fi-x"></i> Delete</button>';
		$b=[];$out='';
		switch($this->ACTION){
			case 'edit':
				$b=['back','new','delete','save'];
				break;
			case 'new':
				$b=['back','save'];
				break;
			default:
				$b=['back','new'];
		}
		if($b){
			foreach($b as $i){
				$out.='<li>'.$but[$i].'</li>';
			}
		}
		return $out;
	}
	
}
