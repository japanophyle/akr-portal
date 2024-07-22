<?php 

class admin_items{
	private $SLIM;
	private $DATA;
	private $OUTPUT;
	private $PERMLINK;
	private $PERMBACK;
	private $ID;
	private $FIELDS;
	private $SHOW_DELETED;
	private $HAS_CATS;
	private $CATS;
	private $LIST_COUNT=0;
	private $USE_CATEGORIES=false;
		
	public $AJAX;
	public $REQUEST;
	public $USER;
	public $METHOD;
	public $SECTION;
	public $ACTION;
	public $PLUG;
	public $ROUTE;
	public $ADMIN;
	public $LEADER;
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->FIELDS=$slim->ezPDO->getFields('Items');
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
			case 'select_category':
				$this->renderSelectCategory();
				break;
			//case 'remove_cat':
			//	$this->renderRemoveCategory();
			//	break;
			case 'shortcodes':
				$this->renderShortCodes();
				break;
			case 'cache_slugs':
				$this->cachPageSlugs();
				break;
			case 'search':
				$this->renderSearch();
				break;
			case 'list_status':
				if($this->ID){
					$this->renderListItems();
				}else{
					$this->renderStatusMenu();
				}
				break;
			default:
				$this->renderListItems();
				break;				
		}
		return $this->renderOutput();
	}
	private function doPost(){
		$url=$this->PERMLINK;
		switch($this->ACTION){
			case 'new':
				$rsp=$this->addRecord();
				break;
			case 'update':
				$rsp=$this->updateRecord();
				if($rsp['status']==200) $url.='edit/'.$this->REQUEST['id'];
				break;
			case 'delete':
				$rsp=$this->deleteRecord();
				break;
			case 'update_cats':
			case 'remove_cat_now':
				$rsp=$this->CATS->Process();
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
	private function updateMeta($post=false){
		if(issetCheck($post,'meta')){
			$id=$this->REQUEST['id'];
			$meta=$this->SLIM->DataBank->getItemMeta($id);
			$ct=0;
			$db=$this->SLIM->db->myp_meta;
			foreach($post['meta'] as $i=>$v){
				$val=(isset($meta[$id][$i]))?$meta[$id][$i]:'-!-';
				if($val!=='-!-'){
					if($val['meta_value']!==$v){
						$rec=$db->where('meta_ID',$val['meta_ID']);
						if(count($rec)>0){
							$ct+=$rec->update(array('meta_value'=>$v));
						}
					}
				}else{
					$add=array(
						'meta_item_id'=>$id,
						'meta_key'=>$i,
						'meta_value'=>$v
					);
					$test=$db->insert($add);
					$ct++;
				}
			}
			return $ct;
		}
		return false;
	}
	private function updateRecord(){
		$db=$this->SLIM->db->Items;
		$rec=$db->where('ItemID',$this->REQUEST['id']);
		$mtype='alert';
		$state=500;
		if(count($rec)>0){
			if($post=$this->cleanRequest()){
				$chk=$rec->update($post);
				if($chk){
					//update 
					$msg='Okay, the record has been updated.';
					$state=200;
					$mtype='success';
				}else if($err=$this->SLIM->db_error){
					$msg='Sorry, there was a problem updating the record: '.$err;
				}else{
					$msg='Okay, you do not seem to have made any changes...';
					$state=200;
					$mtype='primary';
				}
			}else{
				$msg='Sorry, no valid details were recieved...';
			}			
		}else{
			$msg='Sorry, I could not find a record to update...';
		}
		return array('status'=>$state,'message'=>$msg,'type'=>$mtype);
	}
	private function addRecord(){
		$mtype='alert';
		$state=500;
		$id=0;
		if($post=$this->cleanRequest()){
			$post[$this->SECTION]['ItemType']=$this->SECTION;
			$db=$this->SLIM->db->Items;
			$chk=$db->insert($post[$this->SECTION]);
			if($chk){
				$msg='Okay, the record has been added.';
				$state=200;
				$mtype='success';
				$id=$db->insert_id();
				$this->addRecord_meta($post,$id);
			}else if($err=$this->SLIM->db_error){
				$msg='Sorry, there was a problem adding the record: '.$err;
			}
		}else{
			$msg='Sorry, no valid details were recieved...';
		}
		return array('status'=>$state,'message'=>$msg,'type'=>$mtype,'id'=>$id);			
	}
	private function addRecord_meta($post=false,$rec_id=0){
		$mtype='alert';
		$state=500;
		$chk=0;
		if(isset($post['meta']) && $rec_id){
			$db=$this->SLIM->db->myp_meta;
			foreach($post['meta'] as $i=>$v){
				$meta=array(
					'meta_item_id'=>$rec_id,
					'meta_key'=>$i,
					'meta_value'=>$v
				);
				$test=$db->insert($meta);
				if($test) $chk++;
			}
		}
		if($chk){
			$msg='Okay, the meta records have been added.';
			$state=200;
			$mtype='success';
		}else if($err=$this->SLIM->db_error){
			$msg='Sorry, there was a problem adding the record: '.$err;
		}
		return array('status'=>$state,'message'=>$msg,'type'=>$mtype);			
	}
	
	private function deleteRecord(){
		//update status to deleted		
	}
	private function cachPageSlugs(){
		$db=$this->SLIM->db->Items;
		$where=array('ItemType'=>array('page','book'),'ItemStatus'=>array('published','draft'));
		$select='ItemID,ItemTitle,ItemSlug,ItemType,ItemStatus';
		$recs=$db->select($select)->where($where);
		$recs=renderResultsORM($recs,'ItemSlug');
		$test=false;
		if($recs){
			$data=array();
			foreach($recs as $i=>$v){
				if($v['ItemType']==='book'){
					$active='published';
				}else{
					$active=($v['ItemStatus']==='published')?'published':false;
				}
				if($active!=='published') continue;
				$data[$i]=array(
					'id'=>$v['ItemID'],
					'title'=>$v['ItemTitle'],
					'active'=>$active
				);
			}
			$data=serialize($data);
			$test=file_put_contents(CACHE.'cache_slugs.php',$data);
		}
		$msg=($test)?'Okay, the slug cache has been updated.':'Sorry, there was a problem updating the slug cache';
		$state=($test)?200:500;
		if($this->AJAX){
			$type=($state===200)?'success':'alert';
			$o=array('status'=>$state,'message'=>$msg,'type'=>$type);
			echo jsonResponse($o);
		}else{
			setSystemResponse($msg,$this->PERMLINK);
		}
		die;
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
				$tmp['ItemSlug']=slugMe($tmp['ItemTitle']);
			}
		}
		if($this->SECTION==='page' && $meta){
			$tmp['ItemShort']=json_encode($meta);
		}
		return $tmp;		
	}
	private function init(){
		$this->AJAX=$this->SLIM->router->get('ajax');
		if(!$this->METHOD){
			$this->METHOD=$this->SLIM->router->get('method');
			if(!$this->METHOD) $this->METHOD='GET';
			$this->REQUEST=($this->METHOD==='POST')?$this->SLIM->router->get('post'):$this->SLIM->router->get('get');
			$this->ROUTE=$this->SLIM->router->get('route');
			$this->USER=$this->SLIM->user;			
		}
		if($this->METHOD==='POST'){
			$this->SECTION=issetCheck($this->REQUEST,'section');
			$this->ACTION=issetCheck($this->REQUEST,'action');
			$this->ID=issetCheck($this->REQUEST,'id');
			if($this->ID==='new') $this->ACTION='new';
		}else{
			$this->SECTION=issetCheck($this->ROUTE,1);
			$this->ACTION=issetCheck($this->ROUTE,2);
			$this->ID=($this->ACTION==='new')?'new':issetCheck($this->ROUTE,3);
		}
		$this->PERMLINK=URL.'admin/'.$this->SECTION.'/';
		$this->PLUG=issetCheck($this->SLIM->AdminPlugins,$this->SECTION);
		//init data
		if(!$this->ACTION && $this->SECTION){
			$o['ItemType']=$this->SECTION;
			$this->DATA=$this->SLIM->DataBank->get('item_type',$o);
		}else{
			if(in_array($this->ACTION,array('shortcodes','cache_slugs'))){
				
			}else if($this->ACTION==='search'){
				$args['find']=issetCheck($this->REQUEST,'findME');
				$args['ItemType']=$this->SECTION;
				$args['limit']=0;
				$args['site']='admin';
				$this->DATA=$this->SLIM->DataBank->get('search_items',$args);
			}else if($this->ACTION==='list_status'){
				if($this->ID){
					$o['ItemType']=$this->SECTION;
					$o['ItemStatus']=str_replace('-',' ',$this->ID);
					$o['order']='ItemTitle ASC';
					$this->DATA=$this->SLIM->DataBank->get('item_type',$o);
				}
			}else if($this->ACTION==='list_all'){
				$o['ItemType']=$this->SECTION;
				$o['order']='ItemTitle ASC';
				$this->DATA=$this->SLIM->DataBank->get('item_type',$o);
			}else if($this->ID==='new'){
				$args['ItemType']=$this->SECTION;
				$this->DATA=$this->SLIM->DataBank->get('item_new',$args);
			}else if($this->ID==='select'){
				$this->ID=issetCheck($this->ROUTE,4);
				$db=$this->SLIM->db->Items_rel;
				$rez=$db->select('itr_ID,cat_ID')->where('ItemID',$this->ID);
				$this->HAS_CATS=renderResultsORM($rez,'cat_ID');
				$this->ACTION='select_'.$this->ROUTE[2];				
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
	}
	private function renderOutput(){
		if(is_array($this->OUTPUT)){
			$out=$this->OUTPUT;
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
	private function renderImage($src=false){
		if(!is_string($src) || trim($src)==='NULL' || $src==='') $src='gfx/noimage.jpg';
		return $src;
	}
	private function renderListItems(){
		$count=0;
		$search=$info=false;
		if($this->DATA){
			$tbl=false;
			foreach($this->DATA as $i=>$v){
				$dat=$this->formatData($v);
				$tmp_buts='';
				$state=$this->itemStateSelect($dat['ItemStatus'],true);
				if($this->SECTION==='book'){
					$img='<img src="'.$this->renderImage(issetCheck($dat['meta'],$this->SECTION.'_main_image')).'" class="thumbnail small" />';
					$tbl[$i]=array(
						'ID'=>$i,
						'Image'=>$img,
						'Title'=>$dat['ItemTitle'],
						'Date'=>$dat['ItemDate'],
						'Status'=>$state,
					);
				}else{
					$tbl[$i]=array(
						'ID'=>$i,
						'Title'=>$dat['ItemTitle'],
						'Date'=>$dat['ItemDate'],
						'Status'=>$state,
					);					
				}
				if($this->SECTION==='page'){
					$url=URL.'page';
				    $tmp_buts.='<button class="button button-yellow small gotoME" data-ref="'.$url.'/'.$dat['ItemSlug'].'"><i class="fi-eye"></i> View</button>';
				}
				$tmp_buts.='<button class="button button-dark-purple small gotoME" data-ref="'.$this->PERMLINK.'edit/'.$i.'"><i class="fi-pencil"></i> Edit</button>';
				$tbl[$i]['Controls']=$tmp_buts;
				$count++;
			}
			$args['data']['data']=$tbl;
			$args['before']='filter';
			$list=dataTable($args);
		}else{
			$list=msgHandler('No records found...',false,false);
		}
		$this->LIST_COUNT=$count;
		if($this->ACTION==='search') return $list;
		//add search
		if($this->SECTION==='book'){
			if(!$this->PLUG){
				$this->PLUG['icon']='book';
				$this->PLUG['label']='';
			}
			$search=$this->renderSearch_form();
			switch($this->ACTION){
				case 'list_all': $info='<h3>All Books</h3>'; break;
				case 'list_status': 
					$l=($this->ID==='published')?'in-stock':$this->ID;
					$info='<h3>Books By Status: <span class="subheader">'.ucMe($l).'</span></h3>'; 
					break;
				default: $info='<h3>Recent Books</h3>';
			}
		}
		$icon='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
		$this->OUTPUT=array(
			'title'=>ucME($this->SECTION).' Records: <span class="subheader">'.strtoupper($this->PLUG['label']).' ('.$count.')</span>',
			'content'=>$search.$info.$list,
			'icon'=>$icon,
			'menu'=>array('right'=>$this->renderContextMenu()),
		);
	}
	private function renderSearch_form(){
		$placeholder['book']='Title, Author or ISBN';
		$form='<form class="searchForm" method="get" action="'.$this->PERMLINK.'search/"><div class="input-group"><input class="input-group-field" name="findME" type="text" placeholder="'.$placeholder[$this->SECTION].'"><div class="input-group-button"><input type="submit" class="submitSearch button button-dark-blue" value="Search"></div></div></form>';
		return $form;
	}
	
	private function renderSearch(){
		//determine search prams
		$find=$this->REQUEST['findME'];
		$table=$this->renderListItems();
		$out['item_title']='Search Results';
		$content=$this->renderSearch_form();
		$content.='<h3>Results for: <span class="subheader">'.$find.' ('.$this->LIST_COUNT.')</span></h3>';
		$content.=$table;
		$icon='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
		$this->OUTPUT=array(
			'title'=>ucME($this->SECTION).' Search Results',
			'content'=>$content,
			'icon'=>$icon,
			'menu'=>array('right'=>$this->renderContextMenu())
		);			
	}
	private function renderViewItem(){
		$item=($this->DATA)?current($this->DATA):false;
		$title=ucME($this->SECTION).' Info. #'.$this->ID;
		$button=false;
		if($item){
			$tpath=TEMPLATES.'parts/tpl.'.$this->SECTION.'-view.html';
			$tpl=false;
			if(file_exists($tpath))	$tpl=file_get_contents($tpath);
			switch($this->SECTION){
				case 'product':
					$item=$this->SLIM->Products->get('id',$this->ID);
					$bits= new resource_bits;
					$group=$bits->get('bit',$item['meta']['product_resource_group']);
					$gname=($group)?$group['name']:'Misc.';
					$price=($item['meta']['product_price']>0)?'&pound;'.toPounds($item['meta']['product_price']):'n/a';
					$fill=array(
						'title'=>$item['product']['ItemTitle'],
						'slug'=>$item['product']['ItemSlug'],
						'status'=>$item['product']['ItemStatus'],
						'ammended'=>validDate($item['product']['itemDate']),
						'renewable'=>($item['renewable'])?'Yes':'No',
						'price'=>$price,
						'group'=>$gname,
						'image'=>$this->SLIM->Options->getProductIcon($item),
						'info'=>fixHTML($item['product']['ItemContent']),	
					);
					break;
				default:
					$fill=false;
			}
			if($fill && $tpl){
				$content=replaceMe($fill,$tpl);
				$button='<button title="edit this '.$this->SECTION.'" class="button button-dark-purple gotoME" data-ref="'.$this->PERMLINK.'/edit/'.$this->ID.'"><i class="fi-pencil"></i></button> ';
			}else{
				$content=($fill)?msgHandler('Sorry, no '.$this->SECTION.' template found...'):msgHandler('Sorry, no '.$this->SECTION.' details found...');
			}
		}else{
			$content=msgHandler('Sorry, I can\'t find an item with that ID...',false,false);			
		}
		$out=renderCard_active($title,$content,$button.$this->SLIM->closer);
		if($this->AJAX){
			echo $out;
			die;
		}
		return $out;	
	}
	private function renderEditItem(){
		$title='Edit ';
		if($this->DATA){			
			$id=key($this->DATA);
			if($id==='new'){
				$title='New ';
				$data=current($this->DATA);
				$data['meta']=array();
			}else{
				$data=current($this->DATA);
			}
			$data=$this->formatData($data,'edit');
			$tpl=file_get_contents(TEMPLATES.'parts/tpl.'.$this->SECTION.'-edit.html');
			preME($data,2);
			if(!isset($data['sub_title'])){
				$data['sub_title']='';
			}
			$img['key']=$this->SECTION.'_main_image';
			$img['base']=FILE_ROOT.'public/';
			
			$src=issetCheck($data['meta'],$img['key']);
			$img['src']=fixHTML($src);
			$img['parent']=$id;
			$doc=issetCheck($data['meta'],'book_document_link');
			$sidebar=$this->renderEditImage($img);
			$sidebar.=$this->renderEditOptions();
			$sidebar.=$this->renderEditCategory();		
			$sidebar.=$this->renderEditMeta();		
			$sidebar.=$this->renderEditdocument($doc);
			$data['submit']='<i class="fi-check"></i> Update';
			$data['action']='update';
			$data['id']=$id;
			$data['status']=$this->itemStateSelect($data['ItemStatus']);
			$data['url']=$this->PERMLINK;
			$data['ItemOrder']=$this->itemAccessSelect($data['ItemOrder']);
			if($this->SECTION==='page'){
				$in_dis=in_array($data['ItemSlug'],['home','checkout','my-cart','my-home']);
				$show_sidebar=isset($data['meta']['page_sidebar'])?(int)$data['meta']['page_sidebar']:1;
				if($in_dis) $show_sidebar=0;
				$data['page_sidebar']=$this->pageSidebarSelect($show_sidebar);
				$data['page_sidebar_disabled']=($in_dis)?'disabled':'';
				$data['meta_title']=issetCheck($data['meta'],'title',$data['ItemTitle']);
				$data['meta_description']=issetCheck($data['meta'],'description');
			}
			if($this->SECTION==='book'){
				$price=issetCheck($data['meta'],'book_price',0);
				$bind=issetCheck($data['meta'],'book_binding');
				$data['meta']['book_price']=str_replace('&pound;','',$price);
				$data['binding']=$this->bookBindingSelect($bind);
				if(!isset($data['meta']['book_subtitle'])) $data['meta']['book_subtitle']='';
				if(!isset($data['meta']['book_author'])) $data['meta']['book_author']='';
				if(!isset($data['meta']['book_publisher'])) $data['meta']['book_publisher']='';
			}
			if($this->SECTION==='tour'){
				if(!isset($data['meta']['ticket_url'])) $data['meta']['ticket_url']='';
			}
			$tpl=str_replace('{sidebar}',$sidebar,$tpl);
			if(is_array($data['meta'])) $data['meta']=implode('<br/>',$data['meta']);
			$form=replaceME($data,$tpl);
			$form=replaceME($data['meta'],$form);
		}else{
			$list=msgHandler('Sorry, I could not find the record...',false,false);	
		}	
		$icon='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
		$this->OUTPUT=array(
			'title'=>$title.ucME($this->SECTION).': <span class="subheader">#'.$id.' - '.fixHTML($data['ItemTitle']).'</span>',
			'content'=>$form,
			'icon'=>$icon,
			'menu'=>array('right'=>$this->renderContextMenu()),		
		);
	}
	private function renderEditImage($args=false){
		if(!is_array($args)) $args=array('src'=>$args);
		$IMG=new image;
		$src=$IMG->_get('getImageSRC',$args);
		$frm['title']='Main Image';
		$controls='<div class="button-group small expanded"><button class="button button-purple loadME" data-ref="'.URL.'admin/media/image/select/?link=imgLink"><i class="fi-photo"></i> Select</button><button class="button loadME" data-ref="'.URL.'admin/media/image/uploader/?link=imgLink"><i class="fi-upload"></i> Upload</button></div>';
		$frm['content']='<div id="main-image"><div id="imgLink_alt"><img src="'.$src['image'].'"></div><input id="imgLink" type="text" name="meta['.$args['key'].']" value="'.$args['src'].'"/>'.$controls.'</div>';
		return renderCard($frm);		
	}
	private function renderEditDocument($args=false){
		if($this->SECTION!=='book') return false;
		$icon=($args && $args!=='')?'check text-dark-green':'x text-red';
		$frm['title']='Document Link &nbsp;<i class="fi-'.$icon.'"></i>';
		$controls='<div class="button-group small expanded"><button class="button button-purple loadME" data-ref="'.URL.'admin/media/document/select/?link=docLink"><i class="fi-photo"></i> Select</button><button class="button loadME" data-ref="'.URL.'admin/document/uploader/?link=docLink"><i class="fi-upload"></i> Upload</button></div>';
		$frm['content']='<div id="main-doc"><input id="docLink" type="text" name="meta[book_document_link]" value="'.$args.'"/>'.$controls.'</div>';
		return renderCard($frm);		
	}
	private function renderEditMeta(){
		if($this->SECTION==='page'){
			$content='<label>Meta Title<input placeholder="my page title" type="text" name="meta[title]" value="{meta_title}" /></label>';
			$content.='<label>Meta Description<textarea rows="8" placeholder="my page description" name="meta[description]">{meta_description}</textarea></label>';
			$frm['title']='SEO Meta';
			$frm['content']=$content;	
			return renderCard($frm);		
		}
		return false;
	}
	private function renderEditOptions(){
		$frm['title']='Options';
		$content=msgHandler('No options found...',false,false);
		switch($this->SECTION){
			case 'page':
			case 'sidebar':
				$content='<label>Status<select name="ItemStatus">{status}</select></label>';
				$content.='<label>Date<input placeholder="item date" type="date" name="ItemDate" value="{ItemDate}" /></label>';
				$content.='<label>Page Access<select name="ItemOrder">{ItemOrder}</select></label>';
				if($this->SECTION==='page'){
					$content.='<label>Show Sidebar<select name="meta[page_sidebar]" {page_sidebar_disabled}>{page_sidebar}</select></label>';
				}
				break;
			case 'book':
				$content='<label>Author<input type="text" placeholder="author" name="meta[book_author]" value="{book_author}"/></label>';
				$content.='<label>Binding<select name="meta[book_binding]">{binding}</select></label>';
				$content.='<label>Publisher<input type="text" placeholder="publisher" name="meta[book_publisher]" value="{book_publisher}"/></label>';
				$content.='<label>Pub. Date<input type="date" placeholder="date published" name="ItemDate" value="{ItemDate}"/></label>';
				$content.='<label>Price<input type="number" min="0.01" step="0.01" placeholder="price" name="meta[book_price]" value="{book_price}"/></label>';
				$content.='<label>Weight (grams)<input type="number" placeholder="weight" name="meta[book_weight]" min="0" value="{book_weight}"/></label>';
				$content.='<label>Status<select name="ItemStatus">{status}</select></label>';
				break;
			default:
				$content='<label>Status<select name="ItemStatus">{status}</select></label>';
		}
		$frm['content']=$content;	
		return renderCard($frm);		
	}
	private function renderEditCategory(){
		if(!$this->USE_CATEGORIES) return false;
		if(in_array($this->SECTION,['sidebar','tour'])) return false;
		$args=array(
			'item_id'=>$this->ID,
			'by'=>'item_id',
		);
		$content=$this->CATS->get('item_cats',$args); //dies
		return $content;
	}
	private function renderSelectCategory(){
		$args=array(
			'has_cats'=>$this->HAS_CATS,
			'item_id'=>$this->ID,
		);
		$this->CATS->get('selector',$args); //dies
		die;
	}
	private function renderShortcodes(){
		$CDX=new shortcode_codex($this->SLIM);
		$args=array('textbox','playlist','sidebar','slideshow','misc');
		$CDX->render($args);
		die;
	}
	
	private function itemStateSelect($state=false,$view=false){
		$states=array('published'=>'dark-green','pending'=>'red-orange','disabled'=>'maroon');
		if($this->SECTION=='book') $states=$this->SLIM->Books->get('states');
		$opt='';
		if($view){
			$c=issetCheck($states,$state,'black');
			$l=($this->SECTION=='book' && $state==='published')?'in_stock':$state;
			$l=ucMe($l);
			$opt='<span class="text-'.$c.'">'.$l.'</span>';
		}else{
			foreach($states as $i=>$v){
				$l=($this->SECTION=='book' && $i==='published')?'in_stock':$i;
				$l=ucMe($l);
				$sel=($i===$state)?'selected':'';
				$opt.='<option value="'.$i.'" '.$sel.'>'.$l.'</option>';
			}
		}
		return $opt;
	}
	private function itemAccessSelect($state=false){
		$states=array(1=>'public',2=>'user',3=>'editor',4=>'admin',5=>'super');
		$opt='';
		foreach($states as $i=>$v){
			$sel=($i===$state)?'selected':'';
			$opt.='<option value="'.$i.'" '.$sel.'>'.ucwords($v).'</option>';
		}
		return $opt;
	}
	private function pageParentSelect($state=false){
		$states=$this->SLIM->DataBank->getParentPages();
		$state=(int)$state;
		$opt='<option value="0">- none -</option>';
		foreach($states as $i=>$v){
			$sel=($i==$state)?'selected':'';
			$opt.='<option value="'.$i.'" '.$sel.'>'.fixHTML($v['ItemTitle']).'</option>';
		}
		return $opt;
	}
	private function bookBindingSelect($state=false){
		$states=array('Paperback','Hardback','Boardback','Spiral','Large Print');
		$opt='';
		foreach($states as $i){
			$sel=($i===$state)?'selected':'';
			$opt.='<option value="'.$i.'" '.$sel.'>'.ucwords($i).'</option>';
		}
		return $opt;
	}
	private function pageSidebarSelect($state=false){
		$states=array(0=>'No',1=>'Yes');
		$opt='';
		foreach($states as $i=>$v){
			$sel=($i===$state)?'selected':'';
			$opt.='<option value="'.$i.'" '.$sel.'>'.ucwords($v).'</option>';
		}
		return $opt;
	}
	
	private function formatData($data,$mode='view'){
		$fix=[];
		foreach($data as $i=>$v){
			$val=$v;
			switch($i){
				case 'ItemContent':
					$fix['synopsis']=truncateME(strip_tags($val),200);
					$val=fixHTML($val);
					break;
				case 'ItemShort':
					$val=fixHTML($val);						
					break;
				case 'book_document_link':
				case 'ItemTitle':
				case 'book_author': case 'book_publisher': case 'book_subtitle':
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
					$val=toPounds(toPennies($val));
					if($mode==='view') $val='&pound;'.$val;
					break;					
			}
			$fix[$i]=$val;
		}
		if($fix) $fix['meta']=[];		
		return $fix;
	}
	
	private function renderStatusMenu(){
		$states=($this->SECTION=='book')?$this->SLIM->Books->get('states'):array('published'=>'dark-green','pending'=>'red-orange','disabled'=>'maroon');
		$menu='<div class="button-group stacked expanded">';
		foreach($states as $i=>$v){
			$label=($this->SECTION=='book' && $i==='published')?'in_stock':$i;
			$label=ucMe($label);
			$i=str_replace(' ','-',$i);
			$menu.='<button class="button button-orange text-black gotoME" title="list books by status" type="button" data-ref="'.$this->PERMLINK.'list_status/'.$i.'"><i class="fi-list icon-x3"></i><br/>'.$label.'</button>';
		}
		$menu.='</div">';
		$this->OUTPUT['title']='Books By Status';
		$this->OUTPUT['content']=$menu;
		if($this->AJAX){
			echo renderCard_active($this->OUTPUT['title'],$this->OUTPUT['content'],$this->SLIM->closer);
			die;
		}
	}
	private function renderContextMenu(){
		$slug=false;
		if($this->ID==='new'){
			$slug='';
		}else if((int)$this->ID){
			if($this->SECTION==='page'){
				$data=issetCheck($this->DATA[$this->ID],$this->SECTION);
				if(!$data) $data=issetCheck($this->DATA[$this->ID],'article');
				$slug=issetCheck($data,'ItemSlug');
			}else{
				$slug=$this->DATA[$this->ID]['ItemSlug'];
			}
		}else{
			$slug=false;
			if($this->ID){
				if(isset($this->DATA[$this->ID])){
					$slug=$this->DATA[$this->ID]['ItemSlug'];
				}
			}
		}
		$but_status='<button class="button small button-orange text-black loadME" title="list records by status" type="button" data-ref="'.$this->PERMLINK.'list_status"><i class="fi-list"></i> By Status</button>';
		$but['back']='<button class="button small button-dark-purple gotoME" data-ref="'.$this->PERMLINK.'" type="button"><i class="fi-list"></i> '.ucME($this->SECTION).' List</button>';
		$but['short']='<button class="button small button-blue loadME" data-ref="'.$this->PERMLINK.'shortcodes" title="shortcodes" type="button"><i class="fi-results"></i> Shortcodes</button>';
		if($slug){
			$url_section=($this->SECTION!=='page')?'page/'.$this->SECTION:$this->SECTION;
			if($this->SECTION!=='sidebar'){
				$v_class=($this->SECTION==='book')?'loadME':'gotoME';
				$but['view']='<button class="button small button-yellow '.$v_class.'" title="view '.$this->SECTION.' on public site" data-ref="'.URL.$url_section.'/'.$slug.'" type="button"><i class="fi-eye"></i> View</button>';
			}
		}
		$but['new']='<button class="button small button-dark-blue gotoME" title="add a new record" data-ref="'.$this->PERMLINK.'new" type="button"><i class="fi-plus"></i> New</button>';
		$but['save']='<button class="button small button-olive submitME" title="save changes" data-ref="'.$this->SECTION.'" type="button"><i class="fi-check"></i> Update</button>';
		if($this->ACTION==='search'){
			$b='<li>'.$but['back'].'</li>';
		}else if($this->ACTION==='list_all'||$this->ACTION==='list_status'){
			$b='<li>'.$but['back'].'</li>';
			if($this->SECTION==='book') $b.='<li>'.$but_status.'</li>';
			$b.='<li>'.$but['new'].'</li>';
		}else if($this->ACTION){
			if($this->SECTION==='book'){
				unset($but['short']);
			}
			$b='<li>'.implode('</li><li>',$but).'</li>';
		}else{
			$but['all']='<button class="button small button-orange text-black gotoME" title="list all books" type="button" data-ref="'.$this->PERMLINK.'list_all"><i class="fi-list"></i> All Books</button>';
			$but['slugs']='<button class="button small button-navy getME" data-ref="'.$this->PERMLINK.'cache_slugs" title="refresh the slug cache" type="button"><i class="fi-refresh"></i> Slug Cache</button>';
			$but['status']=$but_status;
			$b='<li>'.$but['new'].'</li>';
			if($this->SECTION==='page') $b.='<li>'.$but['slugs'].'</li>';
			if($this->SECTION==='book') $b.='<li>'.$but['status'].'</li><li>'.$but['all'].'</li>';
		}
		return $b;
	}	
}
