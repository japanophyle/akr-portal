<?php
class admin_page {

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
	private $PAGE_SLUG;
	private $ALLOW_DELETE=true;
		
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
		$this->OPTIONS['ItemStatus']=$slim->Options->get('page_states');
		$this->OPTIONS['ItemOrder']=$slim->Options->get('access_levels_name');
		$this->OPTIONS['ItemPrice']=[0=>'No Lock'];
		$n=$slim->Options->get('clubs_name');
		foreach($n as $i=>$v) $this->OPTIONS['ItemPrice'][$i]=$v['ClubName'].' ('.$v['ShortName'].')';
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
			case 'shortcodes':
				$this->renderShortcodes();
				break;
			case 'delete':
			case 'delete_now':
				$this->renderDeleteItem();
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
		$this->PERMLINK=URL.'admin/page/';
		if(!$this->METHOD){
			$this->METHOD=$this->SLIM->router->get('method');
			if(!$this->METHOD) $this->METHOD='GET';
			$this->REQUEST=($this->METHOD==='POST')?$this->SLIM->router->get('post'):$this->SLIM->router->get('get');
			$this->ROUTE=$this->SLIM->router->get('route');
			$this->USER=$this->SLIM->user;			
			$this->PLUG=issetCheck($this->SLIM->AdminPlugins,'page');
		}
		if($this->METHOD==='POST'){
			$this->ACTION=issetCheck($this->REQUEST,'action');
			$this->ID=issetCheck($this->REQUEST,'id');
			if($this->ID==='new') $this->ACTION='new';
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
		}else if($this->ACTION==='shortcodes'){
			$this->DATA=[];
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
	private function doPost(){
		$url=$this->PERMLINK;
		switch($this->ACTION){
			case 'new':
			case 'update':
				$rsp=$this->saveRecord();
				$url.='edit/'.$rsp['ref'];
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
		$but['new']='<button class="button small button-dark-blue loadME" title="add a new page" data-ref="'.$this->PERMLINK.'new" type="button"><i class="fi-plus"></i> New</button>';
		$but['view']='<button class="button small button-yellow text-black gotoME" title="view page" data-ref="'.URL.'page/'.$this->PAGE_SLUG.'" type="button"><i class="fi-eye"></i> View</button>';
		$but['save']='<button class="button small button-olive submitME" title="save changes" data-ref="page" type="button"><i class="fi-check"></i> Update</button>';
		$but['codex']='<button class="button small button-navy loadME" title="get shortcodes" data-size="small" data-ref="'.$this->PERMLINK.'shortcodes" type="button"><i class="fi-results"></i> Shortcodes</button>';
		
		$but['newgroup']='<button class="button small button-dark-blue loadME" title="add a new group" data-ref="'.$this->PERMLINK.'group/new" type="button"><i class="fi-plus"></i> New</button>';
		$but['cats']='<button class="button small button-navy loadME" title="products by category" data-ref="'.$this->PERMLINK.'category/" type="button"><i class="fi-list"></i> By Category</button>';
		$but['groups']='<button class="button small button-navy loadME" title="products by group" data-ref="'.$this->PERMLINK.'group/menu/" type="button"><i class="fi-list"></i> By Groups</button>';
		$but['edit']='<button class="button small button-dark-blue loadME" title="edit payment record" data-ref="'.$this->PERMLINK.'edit_payment/'.$this->ID.'/list" type="button"><i class="fi-pencil"></i> Edit</button>';
		$but['event']='<button class="button small button-dark-blue loadME" title="edit event" data-size="large" data-ref="'.$this->PERMLINK.'edit/'.$this->ID.'" type="button"><i class="fi-calendar"></i> Event #'.$this->ID.'</button>';
		$but['download']='<button class="button small button-purple loadME" title="download" data-ref="'.$this->PERMLINK.'rollcall/'.$this->ID.'/download" type="button"><i class="fi-download"></i> Download</button>';
		$but['delete']='<button class="button small button-maroon loadME" title="download" data-ref="'.$this->PERMLINK.'delete/'.$this->ID.'" type="button"><i class="fi-x"></i> Delete</button>';
		$b=[];$out='';
		$leader_can_add=($this->LEADER)?hasAccess($this->SLIM->user,'pages','create'):true;
		$leader_can_delete=($this->LEADER)?hasAccess($this->SLIM->user,'pages','delete'):true;
		switch($this->ACTION){
			case 'edit':
				if($this->LEADER){
					$b=['back','view'];
					if($this->ALLOW_DELETE && $leader_can_delete) $b[]='delete';
					if($leader_can_add)$b[]='new';
					$b[]='save';
				}else{
					$b=['back','view','codex','new','save'];
					if($this->ALLOW_DELETE) $b=['back','view','codex','delete','new','save'];
				}
				break;
			default:
				$b=['new'];
				if($this->LEADER && !$leader_can_add) $b=[];
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
					$sn=($val)?trim($val):'';
					$fix['synopsis']=truncateME(strip_tags($sn),200);
					$val=fixHTML($val);
					break;
				case 'ItemShort':
					$sn=($val)?trim($val):'';
					$val=json_decode($sn,1);
					$fix['meta']=$val;	
					if($mode==='edit'){
						
					}else{
					
					}				
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
				case 'ItemOrder':
					if($mode==='edit'){
						$opt='';
						foreach($this->OPTIONS[$i] as $x=>$y){
							$sel=($v==$x)?' selected':'';
							$opt.='<option value="'.$x.'"'.$sel.'>'.$y.'</option>';
						}
						$val=$opt;
					}else{
						$val=issetCheck($this->OPTIONS[$i],$v,$v);
					}
					break;
				case 'ItemPrice'://dojo lock
					if($mode==='edit'){
						$opt='';
						foreach($this->OPTIONS[$i] as $x=>$y){
							$sel=($v==$x)?' selected':'';
							$opt.='<option value="'.$x.'"'.$sel.'>'.$y.'</option>';
						}
						$val=$opt;
					}else{
						$val=issetCheck($this->OPTIONS[$i],$v,$v);
						if(!$this->ACTION && strpos($val,'(')!==false){
							$tmp=explode('(',$val);
							$val=str_replace(')','',$tmp[1]);
						}
					}
					break;
				case 'ItemStatus':
					if($mode==='edit'){
						$opt='';
						foreach($this->OPTIONS[$i] as $x=>$y){
							$sel=($v==$x)?' selected':'';
							$opt.='<option value="'.$x.'"'.$sel.'>'.ucME($x).'</option>';
						}
						$val=$opt;
					}else{
						$val=ucME($val);
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
		if($tmp){
			if($this->ID==='new'){
				$slug=issetCheck($tmp,'ItemSlug');
				if(trim($slug)==='') $tmp['ItemSlug']=slugMe($tmp['ItemTitle']);
			}
			if($meta) $tmp['ItemShort']=json_encode($meta);
		}
		return $tmp;		
	}
	
	private function renderListItems(){
		$count=0;
		$STATE=$this->SLIM->StatusColor;
		if($this->DATA){
			$tbl=[];
			$leader_can_update=($this->LEADER)?hasAccess($this->SLIM->user,'pages','update'):false;
			$can_delete=($this->LEADER)?hasAccess($this->SLIM->user,'pages','delete'):true;
			foreach($this->DATA as $i=>$v){
				if($this->LEADER && $leader_can_update){
					//dojo-lock
					if(!in_array($v['ItemPrice'],$this->USER['dojo_lock'])) continue;
				}
				$dat=$this->formatData($v);
				$controls='<button class="button button-dark-blue small loadME" data-ref="'.URL.'page/'.$v['ItemSlug'].'"><i class="fi-eye"></i> View</button>';
				if($this->ALLOW_DELETE && $can_delete) $controls.='<button class="button button-maroon small loadME" data-ref="'.$this->PERMLINK.'delete/'.$i.'"><i class="fi-x"></i> Delete</button>';
				$controls.='<button class="button button-dark-purple small gotoME" data-ref="'.$this->PERMLINK.'edit/'.$i.'"><i class="fi-pencil"></i> Edit</button>';
				$tbl[$i]=array(
					'ID'=>$i,
					'Page'=>$dat['ItemTitle'],
					'Slug'=>$dat['ItemSlug'],
					'Dojo'=>$dat['ItemPrice'],
					'Access'=>$dat['ItemOrder'],
					'Date'=>$dat['ItemDate'],
					'Status'=>$STATE->render('page_status',$dat['ItemStatus']),
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
		$uname='Pages - All';
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
			$rec=current($this->DATA);
			$rec=$this->formatData($rec,'edit');
			if($this->LEADER) $rec['ItemPrice']=str_replace('<option value="0">No Lock</option>','',$rec['ItemPrice']);
			$meta=issetCheck($rec,'meta',[]);
			unset($rec['meta'],$rec['ItemShort']);
			$img['key']='page_main_image';
			$img['base']=FILE_ROOT.'public/';
			$src=issetCheck($meta,$img['key']);
			$img['src']=fixHTML($src);
			$img['parent']=$this->ID;
			
			$sidebar=$this->renderEditImage($img);
			$sidebar.=$this->renderEditOptions();
			$sidebar.=$this->renderEditMeta();		
			$data['sidebar']=$sidebar;
			$data+=$rec;
			$controls='';
			if((int)$this->ID){
				$this->PAGE_SLUG=$data['ItemSlug'];
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
				$data['optionals']='';
				if($this->LEADER){
					//for members ans above
					$data['optionals'].='<input type="hidden" name="ItemOrder" value="20"/>';
					//dojo lock
					$data['optionals'].='<label>Dojo Lock<select name="ItemPrice">'.$rec['ItemPrice'].'</select></label>';
				}
			}	
			$tpl=file_get_contents(TEMPLATES.'parts/tpl.'.$tplf);
			$data['sidebar']=$sidebar;
			$data['action']=$action;
			$data['submit']=$submit;
			$data['id']=$this->ID;
			$data['url']=$url;
			$data['controls']=$controls;
			$data['meta_title']=issetCheck($meta,'title');
			$data['meta_description']=issetCheck($meta,'description');
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
	private function renderEditImage($args=false){
		if(!is_array($args)) $args=array('src'=>$args);
		$IMG=new image;
		$src=$IMG->_get('getImageSRC',$args);
		$frm['title']='Main Image';
		$controls='<div class="button-group small expanded"><button class="button button-purple loadME" data-ref="'.URL.'admin/media/image/select/?link=imgLink"><i class="fi-photo"></i> Select</button><button class="button loadME" data-ref="'.URL.'admin/media/image/uploader/?link=imgLink"><i class="fi-upload"></i> Upload</button></div>';
		$frm['content']='<div id="main-image"><div id="imgLink_alt"><img src="'.$src['image'].'"></div><input id="imgLink" type="text" name="meta['.$args['key'].']" value="'.$args['src'].'"/>'.$controls.'</div>';
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
		$pad=($this->LEADER)? 'disabled ':'';
		$frm['title']='Options';
		$content='<label>Status<select name="ItemStatus">{ItemStatus}</select></label>';
		$content.='<label>Date<input placeholder="item date" type="date" name="ItemDate" value="{ItemDate}" /></label>';
		$content.='<label>Page Access<select '.$pad.'name="ItemOrder">{ItemOrder}</select></label>';
		$content.='<label>Dojo Lock<select name="ItemPrice">{ItemPrice}</select></label>';
		$frm['content']=$content;	
		return renderCard($frm);		
	}
	private function renderShortcodes(){
		$CDX=new shortcode_codex($this->SLIM);
		$args=array('misc');
		$CDX->render($args);
		die;
	}
	private function itemStateSelect($state=false,$view=false){
		$states=array('published'=>'dark-green','draft'=>'red-orange','disabled'=>'maroon');
		$opt='';
		if($view){
			$c=issetCheck($states,$state,'black');
			$l=ucMe($state);
			$opt='<span class="text-'.$c.'">'.$l.'</span>';
		}else{
			foreach($states as $i=>$v){
				$l=ucMe($i);
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
		$can_delete=($this->LEADER)?hasAccess($this->SLIM->user,'pages','delete'):true;
		if(!$this->ALLOW_DELETE || !$can_delete){
			$content=msgHandler('Sorry, you cannot delete pages right now...',false,false);			
		}else if($this->DATA){
			$dat=current($this->DATA);
			if($this->ACTION==='delete_now'){
				$rec=$this->SLIM->db->Items->where('ItemID',$this->ID)->limit(1);
				$msg='Sorry, I could not delete page #'.$this->ID.'...';
				if(count($rec)==1){
					$chk=$rec->delete();
					if($chk){
						//remove from navigation menu
						$menu=$this->deleteNavPage($this->ID);
						$msg='Okay, page #'.$this->ID.' has been deleted.';
					}
				}
				setSystemResponse($this->PERMLINK,$msg);
				die($msg);
			}else{
				$date=explode(' ',$dat['ItemDate']);
				$content='<div class="callout warning text-center"><p class="h4">Do you want to delete this page #'.$this->ID.'?</p><p><strong>Title:</strong> '.$dat['ItemTitle'].'<br/><strong>Slug:</strong> '.$dat['ItemSlug'].'<br/><strong>Date:</strong> '.$date[0].'<br/><strong>Status:</strong> '.$dat['ItemStatus'].'<br/><span class="label bg-red">Note that this action cannot be undone.</span></p>';
				$content.='<div class="button-group expanded"><button type="button" class="button secondary" data-close><i class="fi-x-circle"></i> No, maybe later</button><button type="button" class="button button-red gotoME" data-ref="'.$this->PERMLINK.'delete_now/'.$this->ID.'"><i class="fi-x"></i> Yes, do it now!</button></div></div>';
			}
		}else{
			$content=msgHandler('Sorry, no page found with ID#'.$this->ID.'...',false,false);
		}
		$this->OUTPUT['title']='Delete Page?';
		$this->OUTPUT['content']=$content;		
		if($this->AJAX){
			echo renderCard_active($this->OUTPUT['title'],$this->OUTPUT['content'],$this->SLIM->closer);
			die;
		}
	}
	private function deleteNavPage($id=0){
		if(!$id) return false;
		$menu=$this->SLIM->Options->get('main_menu');
		$done=false;
		foreach(array_keys($menu) as $k){
			$subs=issetCheck($menu[$k],'subs');
			if($subs){
				if(in_array($id,$subs)){
					unset($menu[$k]['subs'][$id]);
					$done=true;
				}
			}
			if(!$done){
				if($id==$k){
					unset($menu[$k]);
					$done=true;
				}
			}
			if($done) break;
		}
		if($done){
			if($rec=$this->SLIM->db->Options->where('OptionName','main_menu')->limit(1)){
				$upd=['OptionValue'=>compress($menu)];
				$rec->update($upd);
			}
		}		
		return $done;
	}
	private function saveRecord(){
		$mtype='alert';
		$state=500;
		$id=0;
		$post=$this->cleanRequest();
		$db=$this->SLIM->db->Items;
		$cache_slugs=false;
		if(!$post){
			$msg='Sorry, no valid details were recieved...';
		}else if($this->ACTION==='new'){
			$post['ItemType']=$this->SECTION;
			$chk=$db->insert($post);
			if($chk){
				$msg='Okay, the record has been added.';
				$state=200;
				$mtype='success';
				$id=$db->insert_id();
				$cache_slugs=true;
			}else if($err=$this->SLIM->db_error){
				$msg='Sorry, there was a problem adding the record: '.$err;
			}
		}else{
			$rec=$db->where('ItemID',$this->REQUEST['id']);
			if(count($rec)>0){
				$id=$this->REQUEST['id'];
				$chk=$rec->update($post);
				if($chk){
					//update 
					$msg='Okay, the record has been updated.';
					$state=200;
					$mtype='success';
					$cache_slugs=true;
				}else if($err=$this->SLIM->db_error){
					$msg='Sorry, there was a problem updating the record: '.$err;
				}else{
					$msg='Okay, you do not seem to have made any changes...';
					$state=200;
					$mtype='primary';
				}
			}else{
				$msg='Sorry, I could not find a record to update...';
			}			
		}
		if($cache_slugs) $this->cachePageSlugs();
		return array('status'=>$state,'message'=>$msg,'type'=>$mtype,'ref'=>$id);
	}
	private function cachePageSlugs(){
		$recs=$this->SLIM->db->Items->where('ItemType','page')->select('ItemID,ItemSlug,ItemTitle,ItemStatus,ItemOrder,ItemDate');
		$recs=renderResultsORM($recs);
		$slugs=[];
		foreach($recs as $rec){
			$slugs[$rec['ItemSlug']]=[
				'id'=>$rec['ItemID'],
				'title'=>$rec['ItemTitle'],
				'access'=>$rec['ItemOrder'],
				'status'=>$rec['ItemStatus'],
				'date'=>$rec['ItemDate']
			];
		}
		if($slugs){
			$tmp=file_put_contents(CACHE.'cache_slugs.php',compress($slugs));
		}
	}
}
