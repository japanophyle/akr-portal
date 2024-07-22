<?php

class slimProducts{
	
	private $SLIM;
	private $PRODUCTS;
	private $PRODUCT_REF;
	private $PRODUCT_GROUP;
	private $GROUP;
	private $GROUPS;// from options
	private $CATEGORIES;// from options
	private $LANG;
	private $AJAX;
	private $ROUTE;
	private $PROD_DB;
	private $TRANS;
	private $OUTPUT;
	private $DEFAULT_REC=array('ItemID'=>0,'ItemTitle'=>'','ItemType'=>0,'ItemOrder'=>0,'ItemSlug'=>'','ItemContent'=>'');
	private $POST;
	private $ARGS;
	private $ACTION;
	private $SUB_ACTION;
	private $CURRENCY;
	private $USER;
	private $REQUEST;
	
	public $ADMIN;
	
	function __construct($slim){
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		$this->LANG=$slim->language->get('_LANG');
		$this->AJAX=$slim->router->get('ajax');
		$this->ROUTE=$slim->router->get('route');
		$this->REQUEST=$slim->router->get('request');
		$this->PROD_DB=$slim->db->Items();
		$this->GROUPS=$slim->Options->get('product_types');
		$this->CATEGORIES=$slim->Options->get('product_categories');
		$this->CURRENCY=$slim->Options->get('currency');
				
		$this->setDefaultRecord();		
		//move to language??
		$this->TRANS['single']=array('en'=>'Seminar + Single Room','fr'=>'Stage chambre simple','de'=>'Seminar + Einzelzimmer');
		$this->TRANS['simpledouble']=array('en'=>'Seminar + Single Room','fr'=>'Stage chambre simple','de'=>'Seminar + Einzelzimmer');
		$this->TRANS['double']=array('en'=>'Seminar + Double Room','fr'=>'Stage chambre double','de'=>'Seminar + Doppelzimmer');
		$this->TRANS['sans-heberg']=array('en'=>'Seminar (No accommodation)','fr'=>'Stage sans héberg','de'=>'Seminar ohne Unterkunft');
		
	}
	public function get($what=false,$ref=false,$vars=false){
		//reset db when using get
		if($what) $this->PROD_DB=$this->SLIM->db->Items();
		switch($what){
			case 'product_group':
				$this->getProductGroup($ref,$vars);
				return $this->GROUP;
				break;
			case 'product':
				$this->getProducts($ref,$vars);
				return $this->PRODUCTS;
				break;
			case 'categories':
				if($ref==='all'){
					return $this->CATEGORIES;
				}else if($ref){
					return issetCheck($this->CATEGORIES,$ref);
				}
				break;
			case 'groups':
				if($ref==='all'){
					return $this->GROUPS;
				}else if($ref){
					return issetCheck($this->GROUPS,$ref);
				}
				break;
		}
	}
	private function getNextProductGroupID(){
		$DB=$this->SLIM->db->Options();
		$id=1;
		$rec=$DB->select('OptionID')->where('OptionGroup','productType')->order('OptionID DESC')->limit(1);
		$rec=renderResultsORM($rec);
		if(count($rec)>0){
			$rec=current($rec);
			$id=((int)$rec['OptionID'])+1;
		}
		return $id;
	}
	private function getProductGroup($what=false,$ref=false){
		if($ref) $this->PRODUCT_GROUP=$ref;
		$DB=$this->SLIM->db->Options();
		if($what==='new'){
			//get next OptionID
			$id=$this->getNextProductGroupID();
			$this->GROUP= array('id'=>0,'OptionID'=>$id,'OptionGroup'=>'productType','OptionName'=>'','OptionDescription'=>'','OptionValue'=>'');
		}else if($what==='all'){
			$rec=$DB->where('OptionGroup','productType');
			$rec=renderResultsORM($rec,'OptionID');
			return $rec;
		}else if((int)$ref||$ref==0){
			$rec=$DB->where('OptionGroup','productType')->and('OptionID',$ref);
			$rec=renderResultsORM($rec);
			$this->GROUP=current($rec);
		}
	}
	private function getProducts($what=false,$ref=false){
		$rec=$this->PRODUCTS=[];
		$db=$this->SLIM->db->Items;
		switch($what){
			case 'new':
				$this->PRODUCT_REF='new';
				$this->PRODUCTS['new']=$this->DEFAULT_REC;
				break;
			case 'id':
				$this->PRODUCT_REF=$ref;
				$rec=$db->where('ItemID',(int)$ref);
				break;
			case 'group':
				$this->PRODUCT_GROUP=$ref;
				$rec=$db->where('ItemGroup',(int)$ref);
				break;
			case 'group_summary':
				$rec=$db->select('ItemID,ItemGroup,ItemGroup as Product,ItemTitle,COUNT(*) as Items')->group('ItemGroup');
				break;
			case 'category':
				$this->PRODUCT_GROUP=$ref;
				$rec=$db->where('ItemCategory',(int)$ref);
				break;
			case 'slug':
				$this->PRODUCT_REF=$ref;
				$rec=$db->where('ItemSlug',$ref);
				break;		
			case 'all':
				$rec=$db->where('ItemType','product');
				break;		
		}
		if($rec){
			if(!in_array($what,array('all','group_summary')))$rec->and('ItemType','product');
			$this->PRODUCTS=renderResultsORM($rec,'ItemID');
		}
	}
	
	private function saveProductGroup($id=false,$rec=[]){
		$id=(int)$id;
		$out=$update=[];
		if($rec){
			foreach($rec as $i=>$v){
				switch($i){
					case 'ID':
						break;
					case 'action':
						$action=$v;
						break;
					default:
						$update[$i]=$v;
				}
				
			}
			if($update){
				$DB=$this->SLIM->db->Options();
				if($id>0 && $action==='update_product_group'){
					$rec=$DB->where('id',$id);
					$out=$rec->update($update);
				}elseif($action==='add_product_group'){
					$update['id']=$id;
					$update['OptionID']=$this->getNextProductGroupID();
					$update['OptionGroup']='productType';
					if(!$update['OptionName'] || trim($update['OptionName'])===''){
						$update['OptionName']=slugME($update['OptionValue']);
					}
					if(!isset($update['OptionDescription'])) $update['OptionDescription']='';
					$out=$DB->insert($update);
				}
			}
		}
		return $out;		
	}
	
	private function saveProduct($id=false,$rec=[],$data=false){
		$id=(int)$id;
		$out=$update=[];
		if($rec && $data){
			foreach($rec as $i=>$v){
				$vx=issetCheck($data,$i,$v);
				switch($i){
					case 'ItemPrice':
						$vx=toPennies($vx);
						break;
					case 'ItemSlug':
						if(!$vx||$vx===''||$vx==='-') $vx=$data['ItemTitle'];
						$vx=slugME($vx);
						break;
				}
				$update[$i]=$vx;
			}
			if($update){
				if($id>0){
					$r=$this->PROD_DB->where('itemID',$id);
					$out=$r->update($update);
				}else{
					$update['ItemType']='product';
					$out=$this->PROD_DB->insert($update);
				}
			}
		}
		return $out;		
	}
	
	private function translateProduct($slug=false){
		if($slug){
			foreach($this->TRANS as $x=>$t){
				if(strpos($slug,$x)!==false){
					if($x==='sans-heberg'){						
						$title=$t[$this->LANG];
					}else{
						$title=$t[$this->LANG];
					}
					return $title;
				}
			}
		}
	}
	private function setDefaultRecord(){
		$db=$this->SLIM->db->Items();
		$rec=$db->limit(1);
		$rec=renderResultsORM($rec);
		$keys=array_keys(current($rec));
		$defs=array('ItemID'=>0,'ItemTitle'=>'','ItemType'=>0,'ItemOrder'=>0,'ItemSlug'=>'-','ItemContent'=>'','ItemDate'=>date('Y-m-d'));
		$out=[];
		foreach($keys as $k){
			$out[$k]=issetCheck($defs,$k,'');
		}
		$this->DEFAULT_REC=$out;
	}
	private function setVars(){
		$this->ACTION=issetCheck($this->ROUTE,1);
		$this->ARGS=issetCheck($this->ROUTE,2);
		if($this->ARGS=='group'){
			$this->ACTION='group_product';
			$this->ARGS=issetCheck($this->ROUTE,3);
			$this->ACTION=issetCheck($this->ROUTE,4,$this->ACTION);
		}
	}
	public function render(){
		$this->setVars();
		switch($this->ACTION){
			case 'edit_product_group'://edit product
				$this->getProductGroup('id',$this->ARGS);
				$this->renderEditProductGroup();
				break;
			case 'new_product_group'://new product 
				$this->getProductGroup('new');
				$this->renderEditProductGroup();
				break;
			case 'edit_product'://edit item
				$this->getProducts('id',$this->ARGS);
				$this->renderEditProduct();
				break;
			case 'new_product'://new item
				$this->getProducts('new');
				$this->renderEditProduct();
				break;
			case 'group_product':
				$this->getProducts('group',$this->ARGS);
				$this->renderTable();
				break;
			default:
				$this->ACTION='group_summary';
				$this->getProducts('group_summary');
				$this->renderTable();
		}
		return $this->OUTPUT;
	}
	
	private function renderTable(){
		$row=[]; $by_group=false;
		$parts=array('ItemID','ItemTitle','ItemGroup','ItemCategory','ItemCurrency','ItemPrice','ItemStatus','Controls');
		if($this->ACTION==='group_summary'){
			$parts=array('ProductName','Product','Items','Controls');
			$by_group=true;
			$groups_data=$this->getProductGroup('all');
		}
		$new_label='Product';
		foreach($this->PRODUCTS as $i=>$v){
			$td=[];
			foreach($parts as $p){
				$k=str_replace('Item','',$p);
				switch($p){
					case 'Controls':
						if($by_group){
							$td[$k]='<td><button class="button small button-navy gotoME" data-ref="'.URL.'events/products/group/'.$v['ItemGroup'].'"><i class="fi-arrow-right"></i> Edit Items</button></td>';
						}else{
							$td[$k]='<td><button class="button small loadME" data-ref="'.URL.'products/edit_product/'.$i.'"><i class="fi-pencil"></i> Edit</button></td>';
						}
						break;
					case 'ProductName':
						$cat=issetCheck($groups_data,$v['ItemGroup']);
						if($cat){
							$cat=issetCheck($cat,'OptionName','???');							
						}else{
							$cat='no-group';
						}
						$td[$k]='<td>'.$cat.'</td>';
						break;
					case 'Product':
					    $used[]=$v[$p];
						$cat=issetCheck($this->GROUPS,$v[$p],'Single Items / Misc.');
						$td[$k]='<td>'.$cat.'</td>';
						break;					
					case 'ItemGroup':
						$cat=issetCheck($this->GROUPS,$v[$p],'No Group');
						$td[$k]='<td>'.$cat.'</td>';
						break;
					case 'ItemCategory':
						$cat=issetCheck($this->CATEGORIES,$v[$p],'?('.$v[$p].')');
						$td[$k]='<td>'.$cat.'</td>';
						break;
					case 'ItemPrice':
						$td[$k]='<td>'.toPounds($v[$p]).'</td>';
						break;
					case 'ItemCurrency':
						$td[$k]='<td>'.$this->CURRENCY[$v[$p]]['label'].'</td>';
						break;
					case 'ItemTitle':
						$td[$k]='<td>'.$v[$p].'<br/><small class="text-navy">'.$v['ItemShort'].'</small></td>';
						break;
					default:
						$td[$k]='<td>'.$v[$p].'</td>';
				}
			}			
			if($td) $row[$i]=implode('',$td);
		}
		if($this->ACTION==='group_summary'){
			//check for groups with no products
			foreach($groups_data as $i=>$v){
				if(!in_array($i,$used)){
					$but='<button class="button small button-navy gotoME" data-ref="'.URL.'events/products/group/'.$v['OptionID'].'"><i class="fi-arrow-right"></i> Edit Items</button>';
					$row[]='<td>'.$v['OptionName'].'</td><td>'.$v['OptionValue'].'</td><td>0</td><td>'.$but.'</td>';
				}
			}			
		}
		if(!$row) $row[]='<td colspan="7">'.msgHandler('No records found...',false,false).'</td>';
		if($by_group){
			$thead[]='<th data-sort="string">Code</th>';
			$thead[]='<th data-sort="string">Product Group</th>';
			$thead[]='<th data-sort="int">Products</th>';
			$title='Products';
			$controls=$htitle=false;
			$controls.='<button class="button button-olive loadME" data-ref="'.URL.'events/products/group/new/new_product_group" title="add a new product"><i class="fi-plus"></i> New Product</button>';
		}else{
			$thead[]='<th data-sort="int">ID</th>';
			$thead[]='<th data-sort="string">Title</th>';
			$thead[]='<th data-sort="string">Group</th>';
			$thead[]='<th data-sort="string">Category</th>';
			$thead[]='<th data-sort="string">Currency</th>';
			$thead[]='<th data-sort="float">Price</th>';
			$thead[]='<th data-sort="string">Status</th>';
			$title='Items for product: <span class="subheader text-green">'.issetCheck($this->GROUPS,$this->PRODUCT_GROUP,'Single Items / Misc.').'</span>';
			$htitle='<h5>'.$title.'</h5>';
			$controls='<button class="button button-purple loadME" data-ref="'.URL.'events/products/group/'.$this->PRODUCT_GROUP.'/edit_product_group" title="edit the settings for this product"><i class="fi-wrench"></i> Product Settings</button>';
			$controls.='<button class="button button-olive loadME" data-ref="'.URL.'events/products/group/'.$this->PRODUCT_GROUP.'/new_product" title="add a new item to this product"><i class="fi-plus"></i> New Item</button>';
		}
		$thead[]='<th>Controls</th>';
		$filter='<div id="filter">'.$this->SLIM->zurb->inlineLabel('Filter','<input id="dfilter" class="input-group-field" type="text"/>');
		$filter.='<div class="metrics">'.(count($row)).' Record(s)</div></div>';
		$table='<table id="dataTable" class="row_hilight"><thead><tr>'.implode('',$thead).'</tr></thead><tbody><tr>'.implode('</tr><tr>',$row).'</tr></tbody></table>';
		if($controls){
			$controls='<div class="button-group float-right small">'.$controls.'</div>';
			$this->SLIM->topbar->setInfoBarControls('right',array($controls),true);
		}
		$this->SLIM->assets->set('js','my_table','JQD.ext.initMyTable("#dfilter","#dataTable");');
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$htitle.$filter.$table;		
	}
	
	private function renderEditProductGroup(){
		if($this->ARGS==='new'){
			$title='New Product';
			$faction='add_product_group';
			$button='<i class="fi-plus"></i> Add Product';
			$info='<div class="callout primary">Enter the settings for a new product (or product group).<br/>You can add the actual products later.</div>';
		}else{
			$faction='update_product_group';
			$button='<i class="fi-check"></i> Update Product';
			$title='Edit Product: <span class="subheader text-navy">'.$this->GROUP['OptionValue'].'</span>';
			$info='<div class="callout primary">Edit the settings for this product (or product group).</div>';
		}
		if(is_numeric($this->ARGS) && $this->ARGS==0){
			$out=msgHandler('This product does not have a have any settings...',false,false);
		}else{
			$form=$info;
			$form.='<label>Product/Group Name<input type="text" name="OptionValue" value="'.$this->GROUP['OptionValue'].'"/></label>';
			$form.='<label>Description <input type="text" name="OptionDescription" value="'.$this->GROUP['OptionDescription'].'"/></label>';
			$disable=((int)$this->GROUP['OptionID']>0)?'disabled="disabled"':'';
			$form.='<label>Index ID  <small class="text-maroon">A number used for sorting and indexing. This is generated by the system.</small><input type="number" name="OptionID" value="'.$this->GROUP['OptionID'].'" '.$disable.'/></label>';
			$disable=(trim($this->GROUP['OptionName'])!=='')?'disabled="disabled"':'';
			$form.='<label>Code <small class="text-maroon">A unique code for this item. Cannot be changed once set. If left empty, the system will generate a code using the product name.</small><input type="text" name="OptionName" value="'.$this->GROUP['OptionName'].'" '.$disable.'/></label>';
			if($this->AJAX) $form='<div class="modal-body">'.$form.'</div>';
			$tpl=file_get_contents(APP.'templates/app.form_lang_standards_ajax.html');
			$args=array(
				'form_url'=>URL.'api/products/group',
				'form_action'=>$faction,
				'form_parts'=>$form,
				'form_button'=>$button,
				'id'=>$this->GROUP['id']
			);
			$out=replaceMe($args,$tpl);
		}
		if($this->AJAX){
			echo renderCard_active($title,$out,$this->SLIM->closer);
			die;
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$out;	
		
	}
	private function renderEditProduct(){
		if($this->PRODUCT_REF==='new'){
			$faction='add_product';
			$button='<i class="fi-plus"></i> Add Item';
			$title='New Item';
		}else{
			$faction='update_product';
			$button='<i class="fi-check"></i> Update Item';
			$title='Edit Item';
		}
		//form fields
		$hidden='';
		$parts['Details']=array('ItemTitle','ItemCurrency','ItemPrice','ItemQty','ItemGroup','ItemCategory','ItemOrder','ItemSlug','ItemStatus');
		$parts['Info']=array('ItemShort');		
		$parts=$this->renderTabs($parts);
		if($this->AJAX) $parts='<div class="modal-body">'.$parts.'</div>';
		$tpl=file_get_contents(APP.'templates/app.form_lang_standards_ajax.html');
		$args=array(
			'form_url'=>URL.'api/products',
			'form_action'=>$faction,
			'form_parts'=>$hidden.$parts,
			'form_button'=>$button,
			'id'=>$this->PRODUCT_REF
		);
		$out=replaceMe($args,$tpl);
		if($this->AJAX){
			echo renderCard_active($title,$out,$this->SLIM->closer);
			die;
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$out;	
	}
	private function renderTabs($data){
		$nav=$panels='';
		$active='is-active';
		$tab_id='product-edit';
		$ct=0;
		$product=current($this->PRODUCTS);
		foreach($data as $i=>$v){
			$tmp='';
			$nav.='<li class="tabs-title '.$active.'"><a href="#panel_'.$ct.'" aria-selected="'.$active.'">'.$i.'</a></li>';
			foreach($v as $x){
				$k=str_replace('Item','',$x);
				switch($x){
					case 'ItemOrder':
						$rm=array('0'=>'none',1=>'single room',2=>'double room',3=>'single room (en-suite)',4=>'double room (en-suite)');
						$opts='<option value="0">None</option>';
						foreach($rm as $ci=>$cv){
							$sel=($product[$x]==$ci)?'selected':'';
							$opts.='<option value="'.$ci.'" '.$sel.'>'.ucwords($cv).'</option>';
						}
						$tmp.='<label>Acommadation<select name="'.$x.'">'.$opts.'</select></label>';
						break;
					case 'ItemShort':
						$tmp.='<label>Short Description</label><textarea name="'.$x.'" id="edit-'.$x.'" class="qedit" >'.$product[$x].'</textarea>';
						break;
					case 'ItemPrice':
						$tmp.='<label>'.$k.': <em class="text-dark-blue">The Format must be "0.00"</em><input type="text" name="'.$x.'" value="'.toPounds($product[$x]).'"/></label>';
						break;
					case 'ItemSlug':
						$disable=($product[$x] && $product[$x]!=='')?'disabled':'';
						$tmp.='<label>'.$k.': <em class="text-dark-blue">This is used as an alternative id on the public site. If empty, it will be generated from the title</em><br/><em class="text-maroon"><strong>Note:</strong> You should not really change it once it has been set.</em><input type="text" name="'.$x.'" value="'.$product[$x].'" '.$disable.'/></label>';
						break;				
					case 'ItemGroup':
						$pchk=($product[$x])?$product[$x]:$this->ARGS;
						$dis=($pchk)?'dabled="disabled"':'';
						if($this->ACTION==='new_product'){
							$inp='<input type="hidden" name="'.$x.'" value="'.$pchk.'"/><div class="faux-input">'.$this->GROUPS[$pchk].'</div>';
						}else{
							$opts='<option value="0">No Group</option>';
							foreach($this->GROUPS as $ci=>$cv){
								$sel=($pchk==$ci)?'selected':'';
								$opts.='<option value="'.$ci.'" '.$sel.'>'.$cv.'</option>';
							}
							$imp='<select name="'.$x.'" '.$dis.'>'.$opts.'</select>';
						}
						$tmp.='<label>'.$k.': <em class="text-dark-blue">This is for linking products to to event types. The groups are used to control the products that show on a form.</em>'.$inp.'</label>';
						break;				
					case 'ItemCategory':
						$opts='';
						foreach($this->CATEGORIES as $ci=>$cv){
							$sel=($product[$x]==$ci)?'selected':'';
							$opts.='<option value="'.$ci.'" '.$sel.'>'.$cv.'</option>';
						}
						$tmp.='<label>'.$k.': <select name="'.$x.'">'.$opts.'</select></label>';
						break;				
					case 'ItemCurrency':
						$opts='';
						foreach($this->CURRENCY as $ci=>$cv){
							$sel=($product[$x]==$ci)?'selected':'';
							$opts.='<option value="'.$ci.'" '.$sel.'>'.$cv['label'].'</option>';
						}
						$tmp.='<label>'.$k.': <select name="'.$x.'">'.$opts.'</select></label>';
						break;				
					case 'ItemStatus':
						$sel=($product[$x]==='active')?'selected':'';
						$opts='<option value="inactive">Disabled</option><option value="active" '.$sel.'>Active</option>';
						$tmp.='<label>'.$k.': <em class="text-dark-blue">Disabled items are not visible on the public site.</em><select name="'.$x.'">'.$opts.'</select></label>';
						break;				
					case 'ItemQty':
						$tmp.='<label>Unit Qty.<input type="number" name="'.$x.'" value="'.(int)$product[$x].'"/></label>';
						break;
					default:
						$tmp.='<label>'.$k.'<input type="text" name="'.$x.'" value="'.$product[$x].'"/></label>';
				}
			}			
			$panels.='<div class="tabs-panel '.$active.'" id="panel_'.$ct.'">'.$tmp.'</div>';
			$active='';
			$ct++;
		}
		$tabs='<ul class="tabs" data-tabs id="'.$tab_id.'-tabs">'.$nav.'</ul><div class="tabs-content" data-tabs-content="'.$tab_id.'-tabs">'.$panels.'</div>';
		if($this->AJAX) $tabs.='<script>jQuery("#'.$tab_id.'-tabs").foundation();JQD.ext.initEditor(".modal-body .qedit");</script>';
		return $tabs;
	}
	public function Postman($post=false){	
		$state=500;$msg_type='alert';$close=false;
		$action=issetCheck($post,'action');
		$id=issetCheck($post,'ID');
		switch($action){
			case 'add_product_group':
				$id=0;
				$chk=$this->saveProductGroup($id,$post);
				if($chk){
					$msg='Okay, the product has been added.';
					$state=200;
					$close=true;
					$msg_type='success';
				}else{
					$msg='Sorry, there was problem adding the product...';
				}
				break;
			case 'update_product_group':
				$chk=$this->saveProductGroup($id,$post);
				if($chk){
					$msg='Okay, the product has been updated.';
					$state=200;
					$close=true;
					$msg_type='success';
				}else{
					$msg='Sorry, there was problem adding the product...';
				}
				break;
			case 'add_product':
				$id=0;
				$rec=$this->DEFAULT_REC;
				$chk=$this->saveProduct($id,$rec,$post);
				if($chk){
					$msg='Okay, the item has been added.';
					$state=200;
					$close=true;
					$msg_type='success';
				}else{
					$msg='Sorry, there was problem adding the item...';
				}
				break;
			case 'update_product':
				if($id){
					$this->getProducts('id',$id);
					$rec=current($this->PRODUCTS);
					if($rec){
						$chk=$this->saveProduct($id,$rec,$post);
						if($chk){
							$msg='Okay, the item has been updated.';
							$state=200;
							$close=true;
							$msg_type='success';
						}else{
							$msg='Sorry, there was problem updating the item...';
						}
					}else{
						$msg='Sorry, I can\'t find that record ['.$id.']...';
					}
				}else{
					$msg='Sorry, incomplete data supplied...';
				}
				break;
			default:
				$msg='Sorry, I don\'t know what "'.$action.'" is...';
		}
		$out=array('status'=>$state,'message'=>$msg,'message_type'=>$msg_type,'close'=>$close,'type'=>'message');
		if($this->AJAX){
			jsonResponse($out);
			die;
		}else{
			return $out;
		}
	}
	
}
