<?php
class admin_product {

	private $SLIM;
	private $DATA;
	private $OUTPUT;
	private $PERMLINK;
	private $PERMBACK;
	private $ID;
	private $FIELDS;
	private $SHOW_DELETED;
	private $CATEGORIES;
	private $LIST_COUNT=0;
	private $USE_CATEGORIES=false;
	private $OPTIONS;
		
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
		$this->FIELDS=$slim->ezPDO->getFields('Items');
		$this->OPTIONS['ItemGroup']=$slim->Options->get('product_types');
		$this->OPTIONS['ItemCategory']=$slim->Options->get('product_categories');
		$this->OPTIONS['ItemCurrency']=$slim->Options->get('currency');
		$this->OPTIONS['ItemStatus']=$slim->Options->get('active');
		$this->OPTIONS['ItemOrder']=$slim->Options->get('room_types');
		$this->OPTIONS['dojos']=$slim->Options->get('clubs_name');
		$this->OPTIONS['ItemContent']=[0=>'Any'];
		$n=$this->OPTIONS['dojos'];
		foreach($n as $i=>$v) $this->OPTIONS['ItemContent'][$i]=$v['ClubName'].' ('.$v['ShortName'].')';
	}
	function Process(){
		$this->init();
		if($this->METHOD==='POST'){
			$this->doPost();
		}
		switch($this->ACTION){
			case 'edit': case 'new':
				$this->renderEditItem();
				break;
			case 'view':
				$this->renderViewItem();
				break;
			case 'group':
				$this->renderGroups();
				break;
			case 'dojo':
				$this->renderDojos();
				break;
			case 'category':
				$this->renderCategories();
				break;
			case 'delete':
			case 'delete_now':
				$this->renderDeleteItem();
				break;
			case 'cart':
				$this->renderProductCarts();
				break;
			default:
				$this->renderListItems();
				break;				
		}
		return $this->renderOutput();
	}
	private function init(){
		$this->AJAX=$this->SLIM->router->get('ajax');
		$this->PERMBACK=URL.'admin/';
		$this->PERMLINK=URL.'admin/product/';
		if(!$this->METHOD){
			$this->METHOD=$this->SLIM->router->get('method');
			if(!$this->METHOD) $this->METHOD='GET';
			$this->REQUEST=($this->METHOD==='POST')?$this->SLIM->router->get('post'):$this->SLIM->router->get('get');
			$this->ROUTE=$this->SLIM->router->get('route');
			$this->USER=$this->SLIM->user;			
			$this->PLUG=issetCheck($this->SLIM->AdminPlugins,'product');
		}
		if($this->METHOD==='POST'){
			$this->ACTION=issetCheck($this->REQUEST,'action');
			$this->ID=issetCheck($this->REQUEST,'id');
			$sect=issetCheck($this->REQUEST,'section');
			if($this->ID==='new' && $sect!=='prod_cart') $this->ACTION='new';
		}else{
			$this->ACTION=issetCheck($this->ROUTE,2);
			$this->ID=($this->ACTION==='new')?'new':issetCheck($this->ROUTE,3);
			if($this->ACTION==='category') $this->ACTION='category';
		}
		if($this->METHOD!=='POST') $this->initData();
	}
	private function initData(){
		if(!$this->ACTION){
			$o['ITEMTYPE']=$this->SECTION;
			$this->DATA=$this->SLIM->DataBank->get('item_type',$o);
		}else if($this->ACTION==='cart'){
			//no data required
		}else if($this->ACTION==='by_group'){
			$args['ITEMTYPE']=$this->SECTION;
			$args['ITEMGROUP']=$this->ID;
			$this->DATA=$this->SLIM->DataBank->get('item_type',$args);
		}else if($this->ACTION==='group'){
			$w=($this->ID)?['id'=>$this->ID]:['OptionGroup'=>'productType'];
			if($this->ID==='menu') $w=['OptionGroup'=>'productType'];
			$data=$this->SLIM->db->Options()->select("id, OptionID, OptionName, OptionValue")->where($w);
			$data=renderResultsORM($data,'id');
			$this->DATA=($this->ID==='new')?[0=>['id'=>0,'OptionID'=>0,'OptionGroup'=>'productType','OptionName'=>'','OptionValue'=>'']]:$data;
		}else if($this->ACTION==='by_category'){
			$args['ITEMTYPE']=$this->SECTION;
			$args['ITEMCATEGORY']=$this->ID;
			$this->DATA=$this->SLIM->DataBank->get('item_type',$args);
		}else if($this->ACTION==='category'){
			$this->DATA=$this->OPTIONS['ItemCategory'];
		}else if($this->ACTION==='dojo'){
			$this->DATA=$this->OPTIONS['dojos'];
		}else if($this->ACTION==='by_dojo'){
			$this->DATA=$this->SLIM->Options->getDojoProducts($this->ID);
		}else if($this->ACTION==='list_all'){
			$o['ItemType']=$this->SECTION;
			$o['order']='ItemTitle ASC';
			$this->DATA=$this->SLIM->DataBank->get('item_type',$o);
		}else if($this->ID==='new'){
			$args['ItemType']=$this->SECTION;
			$this->DATA=$this->SLIM->DataBank->get('item_new',$args);
		}else if((int)$this->ID && $this->ID<10000){
			$o['ITEMID']=$this->ID;
			$this->DATA=$this->SLIM->DataBank->get('item_id',$o);
		}else{
			$o['ItemSlug']=$this->ID;
			$db=$this->SLIM->db->Items;
			$chk=$db->select('ItemID')->where('ItemSlug',$this->ID)->limit(1);
			$url=$this->PERMLINK;
			if(count($chk)>0){
				$url.='edit/'.$chk[0]['ItemID'];
				$msg=false;
			}else{
				$msg='Sorry, I could not find "'.$this->ID.'"...';
			}
			setSystemResponse($url,$msg);
			die($msg);
		}
	}
	private function getCatName($ref=false){
		$chk=issetCheck($this->OPTIONS['ItemCategory'],$ref,$ref);
		return $chk;
	}
	private function getGroupName($ref=false){
		$chk=issetCheck($this->OPTIONS['ItemGroup'],$ref,$ref);
		return $chk;
	}
	private function getDojoName($ref=false){
		$chk=issetCheck($this->OPTIONS['dojos'],$ref);
		if($chk){
			$ref=$chk['ClubName'];
		}
		return $ref;
	}
	private function doPost(){
		$url=$this->PERMLINK;
		switch($this->ACTION){
			case 'new':
			case 'update':
				$rsp=$this->saveRecord();
				break;
			case 'add_category':
			case 'update_category':
				$rsp=$this->saveCategory();
				$url.='category';
				break;
			case 'new_prod_cart':
			case 'update_prod_cart':
			case 'add_cart_items':
			case 'remove_cart_items':
				$rsp=$this->renderProductCarts();
				break;
			default:
				$rsp=array('status'=>500,'message'=>'Sorry, the requested action was invalid...' ,'type'=>'alert');
		}
		if($this->AJAX){
			echo jsonResponse($rsp);
			die;
		}
		setSystemResponse($url,$rsp['message']);
		die($rsp['message']);	
	}
	private function renderOutput(){
		$keys=['title','content','icon','menu'];
		if(is_array($this->OUTPUT)){
			$out=$this->OUTPUT;
			foreach($keys as $k){
				if(!isset($this->OUTPUT[$k])){
					switch($k){
						case 'icon':
							$v='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
							break;
						case 'menu':
							$v=['right'=>$this->renderContextMenu()];
							break;
						default:
							$v='';
					}
					$out[$k]=$v;
				}
			}
		}else if(!$this->OUTPUT||$this->OUTPUT===''){
			$out=msgHandler('Sorry, no output was generated...',false,false);
		}else{
			$out=$this->OUTPUT;
		}
		if($this->AJAX){
			if(is_array($out)){
				jsonResponse($out);
			}else{
				echo $out;
			}
			die;
		}
		return $out;
	}
	private function renderContextMenu(){
		$but['back']='<button class="button small button-dark-purple backME" title="back to list" type="button"><i class="fi-arrow-left"></i> Back</button>';
		$but['new']='<button class="button small button-dark-blue loadME" title="add a new product" data-ref="'.$this->PERMLINK.'new" type="button"><i class="fi-plus"></i> New</button>';
		$but['newgroup']='<button class="button small button-dark-blue loadME" title="add a new group" data-ref="'.$this->PERMLINK.'group/new" type="button"><i class="fi-plus"></i> New</button>';
		$but['save']='<button class="button small button-olive submitME" title="save changes" data-ref="ajaxform" type="button"><i class="fi-check"></i> Update</button>';
		$but['menu']='<button class="button small button-navy loadME" title="events menu" data-size="small" data-ref="'.$this->PERMLINK.'events_menu/" type="button"><i class="fi-list"></i> Events Menu</button>';
		$but['prods']='<button class="button small button-navy gotoME" title="list all products" data-ref="'.$this->PERMLINK.'" type="button"><i class="fi-list"></i> Products</button>';
		$but['cats']='<button class="button small button-navy loadME" title="products by category" data-ref="'.$this->PERMLINK.'category/" type="button"><i class="fi-list"></i> By Category</button>';
		$but['groups']='<button class="button small button-navy loadME" title="products by group" data-ref="'.$this->PERMLINK.'group/menu/" type="button"><i class="fi-list"></i> By Groups</button>';
		$but['dojo']='<button class="button small button-navy loadME" title="products by dojo" data-ref="'.$this->PERMLINK.'dojo/menu/" type="button"><i class="fi-list"></i> By Dojo</button>';
		$but['edit']='<button class="button small button-dark-blue loadME" title="edit payment record" data-ref="'.$this->PERMLINK.'edit_payment/'.$this->ID.'/list" type="button"><i class="fi-pencil"></i> Edit</button>';
		$but['event']='<button class="button small button-dark-blue loadME" title="edit event" data-size="large" data-ref="'.$this->PERMLINK.'edit/'.$this->ID.'" type="button"><i class="fi-calendar"></i> Event #'.$this->ID.'</button>';
		$but['download']='<button class="button small button-purple loadME" title="download" data-ref="'.$this->PERMLINK.'rollcall/'.$this->ID.'/download" type="button"><i class="fi-download"></i> Download</button>';
		$but['cart']='<button class="button small button-blue text-black gotoME" title="manage carts" data-ref="'.$this->PERMLINK.'cart/carts" type="button"><i class="fi-shopping-bag"></i> Carts</button>';
		$b=[];$out='';
		switch($this->ACTION){
			case 'edit':
				$b=['back','new','save'];
				break;
			case 'category':
			case 'by_category':
				$b=['back','cats','groups','prods','new'];
				break;
			case 'group':
				$b=['back','cats','groups','prods','newgroup'];
				break;
			case 'by_group':
				$b=['back','cats','groups','prods','new'];
				break;
			default:
				$b=['cats','groups','dojo','cart','new'];
		}
		if($b){
			foreach($b as $i){
				$out.='<li>'.$but[$i].'</li>';
			}
		}
		return $out;
	}
	private function formatData($data,$mode='view'){
		$fix=[];
		foreach($data as $i=>$v){
			$val=$v;
			switch($i){
				case 'ItemContent':
					if($mode==='edit'){
						$opt='';
						foreach($this->OPTIONS[$i] as $x=>$y){
							$sel=($v==$x)?' selected':'';
							$opt.='<option value="'.$x.'"'.$sel.'>'.$y.'</option>';
						}
						$val=$opt;
					}else{
						$t=issetCheck($this->OPTIONS[$i],$v);
						$val=($t)?$t:$v;
					}
					break;
				case 'ItemShort':
					$val=fixHTML($val);						
					break;
				case 'ItemTitle':
					$val=fixHTML($val);
					break;
				case 'ItemDate':
					$fmt='Y-m-d';
					if($mode==='view'){
						$fmt=($i==='ItemDate')?'Y-m-d':false;
					}
					$val=validDate($val,$fmt);
					break;
				case 'ItemPrice':
					$val=toPounds($val);
					break;					
				case 'ItemCurrency':
					if($mode==='edit'){
						$opt='';
						foreach($this->OPTIONS[$i] as $x=>$y){
							$sel=($v==$x)?' selected':'';
							$opt.='<option value="'.$x.'"'.$sel.'>'.$y['label'].'</option>';
						}
						$val=$opt;
					}else{
						$t=issetCheck($this->OPTIONS[$i],$v);
						$val=($t)?$t['label']:$v;
					}
					break;
				case 'ItemCategory': case 'ItemStatus':case 'ItemOrder': case 'ItemGroup':
					if($mode==='edit'){
						$opt='';
						foreach($this->OPTIONS[$i] as $x=>$y){
							$tv=($i==='ItemStatus')?strtolower($y):$x;
							$sel=($v==$tv)?' selected':'';
							$opt.='<option value="'.$tv.'"'.$sel.'>'.$y.'</option>';
						}
						$val=$opt;
					}else{
						$val=issetCheck($this->OPTIONS[$i],$v,$v);
					}
					break;					
			}
			$fix[$i]=$val;
		}
		return $fix;
	}
	private function cleanRequest(){
		$tmp=$meta=[];
		$keys=array_keys($this->FIELDS);
		foreach($this->REQUEST as $i=>$v){
			if(in_array($i,$keys)) {
				//perform any required formatting
				$tmp[$i]=$v;
			}else if($i==='meta'){
				$meta=$v;
			}						
		}
		if($tmp && $this->ID==='new'){
			$slug=issetCheck($tmp,'ItemSlug');
			if(!$slug||$slug===''){
				$slugx=$tmp['ItemTitle'];
				if($cur=issetCheck($this->OPTIONS['ItemCurrency'],$tmp['ItemCurrency'])){
					$slugx.=' '.$cur['label'];
				}
				$tmp['ItemSlug']=slugMe($slugx);
			}
		}
		return $tmp;		
	}
	
	private function renderListItems(){
		$count=0;
		if($this->DATA){
			$tbl=[];
			$STATE=$this->SLIM->StatusColor;
			foreach($this->DATA as $i=>$v){
				$dat=$this->formatData($v);
				$controls='<button class="button button-dark-blue small loadME" data-ref="'.$this->PERMBACK.'sales/product/'.$i.'"><i class="fi-shopping-cart"></i> Sales</button>';
				$controls.='<button class="button button-dark-purple small loadME" data-ref="'.$this->PERMLINK.'edit/'.$i.'"><i class="fi-pencil"></i> Edit</button>';
				$tbl[$i]=array(
					'ID'=>$i,
					'Item'=>$dat['ItemTitle'],
					'Category'=>$dat['ItemCategory'],
					'Group'=>$dat['ItemGroup'],
					'Price'=>$dat['ItemPrice'],
					'Currency'=>$dat['ItemCurrency'],
					'Date'=>$dat['ItemDate'],
					'Status'=>$STATE->render('active_status',$dat['ItemStatus']),
					'Controls'=>$controls
				);					
				$count++;
			}
			$args['data']['data']=$tbl;
			$args['before']='filter';
			$list=dataTable($args);
		}else{
			$list=msgHandler('No records found...',false,false);
		}
		$uname='Products - All';
		if($this->ACTION==='by_category') $uname='Products By Category #'.$this->getCatName($this->ID);
		if($this->ACTION==='by_group') $uname='Products By Group #'.$this->getGroupName($this->ID);
		if($this->ACTION==='by_dojo') $uname='Products By Dojo #'.$this->getDojoName($this->ID);
		$this->OUTPUT['title']=$uname.': <span class="subheader">('.$count.')</span>';
		$this->OUTPUT['content']=$list;
		if($this->AJAX){
			echo renderCard_active($this->OUTPUT['title'],$this->OUTPUT['content'],$this->SLIM->closer);
			echo '<script>$(".reveal .modal-body").foundation();</script>';
			die;
		}	
	}
	private function renderEditItem(){
		if($this->DATA){
			$data=current($this->DATA);
			$data=$this->formatData($data,'edit');
			$controls='';
			if((int)$this->ID){
				$tplf=($this->AJAX)?$this->SECTION.'-edit-modal.html':$this->SECTION.'-edit.html';
				$url=($this->AJAX)?$this->PERMLINK:$this->PERMLINK.'edit/'.$this->ID;
				$action='update';
				$submit='<i class="fi-check"></i> '.ucwords($action);
				$uname=fixHTML($data['ItemTitle']);
				$title='Edit '.ucME($this->SECTION);
			}else{
				$tplf=$this->SECTION.'-new-modal.html';
				$url=$this->PERMLINK;
				$action='add';
				$submit='<i class="fi-plus"></i> '.ucwords($action);
				$uname='New';
				$title='Add '.ucME($this->SECTION);
			}	
			$tpl=file_get_contents(TEMPLATES.'parts/tpl.'.$tplf);
			$data['action']=$action;
			$data['submit']=$submit;
			$data['id']=$this->ID;
			$data['url']=$url;
			$data['controls']=$controls;
			$form=replaceME($data,$tpl);
		}else{
			$uname='???';
			$form=msgHandler('Sorry, I could not find the record...',false,false);	
		}	
		$this->OUTPUT['title']=$title.': <span class="text-dark-purple">#'.$uname.'</span>';
		$this->OUTPUT['content']=$form;
		if($this->AJAX){
			$this->OUTPUT['content']=renderCard_active($this->OUTPUT['title'],$form,$this->SLIM->closer);
		}
	}
	private function renderViewItem(){
		$title='View Product #'.$this->ID;
		if($this->DATA){
			$data=current($this->DATA);
			$data=$this->formatData($data,'view');
			$tpath=TEMPLATES.'parts/tpl.product-view.html';
			$tpl=false;
			if(file_exists($tpath))	$tpl=file_get_contents($tpath);			
			$thumb='<i class="fi-price-tag text-navy icon-x3"></i>';
			$fill=array(
				'image'=>$thumb,
				'title'=>$data['ItemTitle'],
				'slug'=>$data['ItemSlug'],
				'group'=>$data['ItemGroup'],
				'category'=>$data['ItemCategory'],
				'price'=>$data['ItemPrice'].' ['.$data['ItemCurrency'].']',
				'date'=>$data['ItemDate'],
				'status'=>$data['ItemStatus'],
				'info'=>'<div>'.$data['synopsis'].'</div>'
			);
			if($tpl){
				$content=replaceMe($fill,$tpl);
				$button='<button title="edit this '.$this->SECTION.'" class="button button-dark-purple loadME" data-reload="1"  data-ref="'.$this->PERMLINK.'edit/'.$this->ID.'"><i class="fi-pencil"></i></button> ';
			}else{
				$button='';
				$content=msgHandler('Sorry, no product template found...');
			}
		}else{
			$content=msgHandler('Sorry, I can\'t find a dojo with that ID...',false,false);			
		}
		if($this->AJAX){
			echo renderCard_active($title,$content,$button.$this->SLIM->closer);
			die;
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;
	}
	private function renderDeleteItem(){
	
	}
	private function renderProductCarts(){
		$lib=new product_carts($this->SLIM);
		if($this->METHOD==='POST'){
			$lib->METHOD='POST';
			$lib->REQUEST=$this->REQUEST;
			$lib->ACTION=$this->ACTION;
		}
		$out=$lib->render();
		foreach($out as $i=>$v){
			$this->OUTPUT[$i]=$v;
		}		
	}
	private function renderGroups(){
		if($this->DATA){
			if($this->ID==='menu'){
				$dashlinks='';
				foreach($this->DATA as $i=>$v){
					$but['color']='navy';
					$but['icon']=$this->PLUG['icon'];
					$but['href']=$this->PERMLINK.'by_group/'.$v['OptionID'];
					$but['caption']=$v['OptionValue'];
					$but['title']=$v['OptionName'];
					$dashlinks.=$this->SLIM->zurb->adminButton($but);
				}
				$but['color']='dark-purple';
				$but['icon']='wrench';
				$but['href']=$this->PERMLINK.'group/';
				$but['caption']='Manage Groups';
				$but['title']='edit';
				$dashlinks.=$this->SLIM->zurb->adminButton($but);
				$title='Products By Group';
				$content=$dashlinks;			
			}else if($this->ID){
				$tpl=file_get_contents(TEMPLATES.'parts/tpl.prodcat-edit-modal.html');
				$action=($this->ID==='new')?'add_category':'update_category';
				$submit=($this->ID==='new')?'<i class="fi-plus"></i> Add':'<i class="fi-pencil"></i> Edit';
				$d=current($this->DATA);
				$d['OptionGroup']='productType';
				$d['action']=$action;
				$d['submit']=$submit;
				$d['url']=$this->PERMLINK.'category';
				$d['controls']='';
				$content=replaceME($d,$tpl);
				//render form
				$title='Edit Product Group: <span class="text-dark-purple">#'.$this->ID.'</span>';
			}else{
				//render table
				$control='<button class="button button-dark-blue small gotoME" data-ref="'.$this->PERMLINK.'by_group/0"><i class="fi-price-tag"></i> Products</button>';
				$control.='<button class="button button-dark-purple small" disabled><i class="fi-pencil"></i> Edit</button>';
				$data[0]=['Ref'=>0,'Category'=>'Single Item / Misc.','Code'=>'misc','Controls'=>$control];
				foreach($this->DATA as $i=>$v){
					$control='<button class="button button-dark-blue small gotoME" data-ref="'.$this->PERMLINK.'by_group/'.$v['OptionID'].'"><i class="fi-price-tag"></i> Products</button>';
					$control.='<button class="button button-dark-purple small loadME" data-ref="'.$this->PERMLINK.'group/'.$i.'"><i class="fi-pencil"></i> Edit</button>';
					$data[$i]=['Ref'=>$v['OptionID'],'Category'=>$v['OptionValue'],'Code'=>$v['OptionName'],'Controls'=>$control];
				}
				$args['data']['data']=$data;
				$args['before']='filter';
				$content=dataTable($args);
				$title='Product Groups';
			}
		}else{
			$title='Product Groups';
			$content=msgHandler('Sorry, no records found...',false,false);	
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;
		if($this->AJAX){
			$this->OUTPUT['content']=renderCard_active($title,$content,$this->SLIM->closer);
		}
	}
	private function renderDojos(){
		$close=($this->AJAX)?$this->SLIM->closer:'';
		if($this->DATA){
			if($this->ID==='menu'){
				$dashlinks='';
				foreach($this->DATA as $i=>$v){
					$but['color']='navy';
					$but['icon']='target';
					$but['href']=$this->PERMLINK.'by_dojo/'.$i;
					$but['caption']=$v['ShortName'];
					$but['title']=$v['ClubName'];
					$dashlinks.=$this->SLIM->zurb->adminButton($but);
				}
				$title='Products By Dojo';
				$content=$dashlinks;
			}else if($this->ID){
				//should not get here				
			}
		}else{
			$title='Product Categories';
			$content=msgHandler('Sorry, no records found...',false,false);	
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;
		if($this->AJAX){
			$this->OUTPUT['content']=renderCard_active($title,$content,$close);
		}
	}
	private function renderCategories(){
		$close=($this->AJAX)?$this->SLIM->closer:'';
		if($this->DATA){
			$dashlinks='';
			foreach($this->DATA as $i=>$v){
				$but['color']='navy';
				$but['icon']=$this->PLUG['icon'];
				$but['href']=$this->PERMLINK.'by_category/'.$i;
				$but['caption']=$v;
				$but['title']=$v;
				$dashlinks.=$this->SLIM->zurb->adminButton($but);
			}
			$title='Products By Category';
			$content=$dashlinks;
		}else{
			$title='Product Categories';
			$content=msgHandler('Sorry, no records found...',false,false);	
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;
		if($this->AJAX){
			$this->OUTPUT['content']=renderCard_active($title,$content,$close);
		}
	}
	private function nextCategoryID(){
		$w=['OptionGroup'=>'productType'];
		$data=$this->SLIM->db->Options()->select("id, OptionID")->where($w)->order('OptionID DESC')->limit(1);
		$data=renderResultsORM($data,'id');
		$data=current($data);
		$next=((int)$data['OptionID'])+1;
		return $next;
	}
	private function saveRecord(){
		$mtype='alert';
		$state=500;
		$id=0;
		if($post=$this->cleanRequest()){
			$db=$this->SLIM->db->Items;
			if(isset($post['ItemPrice'])) $post['ItemPrice']=toPennies($post['ItemPrice']);
			if($this->ACTION==='new'){
				$post['ItemType']='product';				
				$chk=$db->insert($post);
				if($chk){
					$msg='Okay, the record has been added.';
					$state=200;
					$mtype='success';
					$id=$db->insert_id();
				}else if($err=$this->SLIM->db_error){
					$msg='Sorry, there was a problem adding the record: '.$err;
				}
			}else{
				$id=$this->ID;
				$rec=$db->where(['ItemID'=>$id,'ItemType'=>'product']);
				if(count($rec)==1){
					$chk=$rec->update($post);
					if($chk){
						$msg='Okay, the record has been updated.';
						$state=200;
						$mtype='success';
					}else if($err=$this->SLIM->db_error){
						$msg='Sorry, there was a problem updating the record: '.$err;
					}else{
						$msg='Okay, but no changes were made...';
					}
				}else{
					$msg='Sorry, I could not find the record to update...';
				}
			}
		}else{
			$msg='Sorry, no valid details were recieved...';
		}
		return array('status'=>$state,'message'=>$msg,'type'=>$mtype,'id'=>$id);			
	}
	private function saveCategory(){
		//not used??
		$mtype='alert';
		$state=500;
		$id=0;
		if($post=$this->REQUEST){
			$id=$post['id'];
			$u=['id','action','tbl','section'];
			foreach($u as $s) unset($post[$s]);
			if(trim($post['OptionValue'])==''){
				$msg='The category name seems to be empty...';
			}else{				
				if(trim($post['OptionName'])=='') $post['OptionName']=strtolower($post['OptionValue']);
				$db=$this->SLIM->db->Options;
				if($this->ACTION==='add_category'){
					$post['OptionID']=$this->nextCategoryID();
					$post['OptionDescription']='';
					$chk=$db->insert($post);
					if($chk){
						$msg='Okay, the record has been added.';
						$state=200;
						$mtype='success';
						$id=$db->insert_id();
					}else if($err=$this->SLIM->db_error){
						$msg='Sorry, there was a problem adding the record: '.$err;
					}
				}else{
					$rec=$db->where(['id'=>$id,'OptionGroup'=>'productType']);
					if(count($rec)==1){
						$chk=$rec->update($post);
						if($chk){
							$msg='Okay, the record has been updated.';
							$state=200;
							$mtype='success';
						}else if($err=$this->SLIM->db_error){
							$msg='Sorry, there was a problem updating the record: '.$err;
						}else{
							$msg='Okay, but no changes were made...';
						}
					}else{
						$msg='Sorry, I could not find the record to update...';
					}
				}
			}
		}else{
			$msg='Sorry, no valid details were recieved...';
		}
		return array('status'=>$state,'message'=>$msg,'type'=>$mtype,'id'=>$id);			
	}
	

}
