<?php 

class admin_media{
	private $SLIM;
	private $MLIB;
	private $DATA;
	private $OUTPUT;
	private $PERMLINK;
	private $PERMBACK;
	private $MEDIA_TYPE;
	private $ID=0;
	private $FIELDS;
	private $USE_THUMBNAILS=true;//make a setting
		
	public $PLUG;
	public $AJAX;
	public $REQUEST;
	public $USER;
	public $METHOD;
	public $SECTION;
	public $ACTION;
	public $ROUTE;
	public $ADMIN;
	public $LEADER;
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->MLIB=$slim->Media;
		$this->FIELDS=$slim->ezPDO->getFields('Media');
		$this->USE_THUMBNAILS=$slim->Options->getSiteOptions('medialib_thumbnails',true);
	}
	
	function Process(){
		$this->init();
		if($this->METHOD==='POST'){
			$this->doPost();
		}
		switch($this->ACTION){
			case 'edit':
				$this->renderEditItem();
				break;
			//case 'view':
			//	$this->renderViewItem();
			//	break;
			case 'select':
				$this->renderSelector();
				break;
			case 'uploader': case 'new':
				$this->renderUploader();
				break;
			case 'delete': case 'delete_now':
				$this->renderDelete();
				break;
			default:
				$this->renderListItems();
				break;				
		}
		return $this->renderOutput();
	}
	private function init(){
		$this->METHOD=$this->SLIM->router->get('method');
		if(!$this->METHOD) $this->METHOD='GET';
		$this->REQUEST=($this->METHOD==='POST')?$this->SLIM->router->get('post'):$this->SLIM->router->get('get');
		$this->ROUTE=$this->SLIM->router->get('route');
		$this->SECTION=issetCheck($this->ROUTE,1);
		$this->MEDIA_TYPE=issetCheck($this->ROUTE,2,'image');
		$this->ACTION=issetCheck($this->ROUTE,3,'list');
		$this->ID=issetCheck($this->ROUTE,4);
		$this->AJAX=$this->SLIM->router->get('ajax');
		$this->USER=$this->SLIM->user;			
		
		if($this->METHOD==='POST'){
			$this->SECTION=issetCheck($this->REQUEST,'section');
			$this->ACTION=issetCheck($this->REQUEST,'action');
			$this->ID=issetCheck($this->REQUEST,'id');
		}
		$this->PERMBACK=URL.'admin/media/';
		$this->PERMLINK=$this->PERMBACK.$this->MEDIA_TYPE.'/';
		$this->PLUG=issetCheck($this->SLIM->AdminPlugins,$this->SECTION);
		//init data
		if(!$this->ACTION && $this->MEDIA_TYPE){
			$o['mda_type']=$this->MEDIA_TYPE;
			$this->DATA=$this->MLIB->get('media_type',$o);
		}else{
			if($this->ACTION==='new') $this->ID='new';
			if($this->ID==='new'){
				$this->DATA=array(0=>$this->MLIB->get('media_new'));
			}else if((int)$this->ID){
				$o['mda_id']=$this->ID;
				$this->DATA=$this->MLIB->get('media_id',$o);
			}else if(in_array($this->ACTION,array('list','select'))){
				$this->DATA=$this->MLIB->get('media_type',$this->MEDIA_TYPE);
			}
		}		
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
			default:
				if(issetCheck($this->REQUEST,'imgUrl')){
					$rsp=$this->doUpload();
				}else{
					$rsp=array('status'=>500,'message'=>'Sorry, the requested action was invalid...' ,'type'=>'alert');
				}
		}
		if($this->AJAX){
			echo jsonResponse($rsp);
			die;
		}
		setSystemResponse($url,$rsp['message']);
		die($rsp['message']);	
	}
	private function updateRecord(){
		$db=$this->SLIM->db->Media;
		$rec=$db->where('mda_ID',$this->REQUEST['id']);
		$mtype='alert';
		$state=500;
		if(count($rec)>0){
			if($post=$this->cleanRequest()){
				$chk=$rec->update($post);
				if($chk){
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
		if($post=$this->cleanRequest()){
			$db=$this->SLIM->db->Media;
			$chk=$db->insert($post);
			if($chk){
				$msg='Okay, the record has been added.';
				$state=200;
				$mtype='success';
			}else if($err=$this->SLIM->db_error){
				$msg='Sorry, there was a problem adding the record: '.$err;
			}
		}else{
			$msg='Sorry, no valid details were recieved...';
		}
		return array('status'=>$state,'message'=>$msg,'type'=>$mtype);			
	}
	private function deleteRecord(){
		if($this->DATA){
			$db=$this->SLIM->db->Media;
			$r=$db->where('mda_id',$this->ID);
			if(count($r)){
				return $r->delete();
			}
		}
		return false;
	}
	private function cleanRequest(){
		$tmp=[];
		$keys=array_keys($this->FIELDS);
		foreach($this->REQUEST as $i=>$v){
			if(in_array($i,$keys)) {
				//perform any required formatting
				$tmp[$i]=$v;
			}						
		}	
		return $tmp;		
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
	private function renderThumb($data=[]){
		$thm='<i class="fi-page-filled text-dark-purple icon-x2"></i>';
		if($this->MEDIA_TYPE==='image'){
			$thm='<i class="fi-photo text-dark-purple icon-x2"></i>';
			if($data){
				$src=$data['mda_path'].$data['mda_filename'];
				if($this->USE_THUMBNAILS){
					$src=$this->MLIB->getThumb($data['mda_path'],$data['mda_filename']);
				}					
				$thm='<img class="thumbnail small" src="'.$src.'" />';
			}
		}
		return $thm;
	}
	private function renderListItems(){
		$count=0;
		if($this->DATA){
			$tbl=[];
			foreach($this->DATA as $i=>$dat){
				$dat=$this->formatData($dat);
				$thumb=$this->renderThumb($dat);
				$tbl[$i]=array(
					'ID'=>$i,
					'Thumb'=>$thumb,
					'Name'=>$dat['mda_nice_name'].'<br/><small class="text-purple">'.$dat['mda_filename'].'</small>',
					'Date'=>$dat['mda_date'],
					'Controls'=>'<button class="button button-dark-purple small loadME" data-ref="'.$this->PERMLINK.'edit/'.$i.'"><i class="fi-pencil"></i> Edit</button>'
				);
				$count++;
			}
			$args['data']['data']=$tbl;
			$args['before']='filter';
			$list=dataTable($args);
		}else{
			$list=msgHandler('No records found for "'.$this->MEDIA_TYPE.'"...',false,false);
		}
		$icon='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
		$this->OUTPUT=array(
			'title'=>ucwords($this->MEDIA_TYPE).' Library: <span class="subheader">('.$count.')</span>',
			'content'=>$list,
			'icon'=>$icon,
			'menu'=>array('right'=>$this->renderContextMenu()),
		);
	}
	private function getInsertLink(){
		$link=issetCheck($_GET,'link');
		if(!$link){
			$link=($this->MEDIA_TYPE==='document')?'docLink':'imgLink';
		}
		return $link;
	}
	private function getBGLink(){
		$link=issetCheck($_GET,'bg');
		$linktype=issetCheck($_GET,'bg_type','class');
		if($link) $link=($linktype==='id')?'#'.$link:'.'.$link;
		return $link;
	}
	private function renderSelector(){
		$count=0;
		$link=$this->getInsertLink();
		$bg=$this->getBGLink();
		if($this->DATA){
			$tbl=[];
			foreach($this->DATA as $i=>$dat){
				$dat=$this->formatData($dat);
				$thumb=$this->renderThumb($dat);
				$fpath=$dat['mda_path'].$dat['mda_filename'];
				$alt=($this->MEDIA_TYPE==='image')?base64_encode('<img src="'.$fpath.'">'):false;
				$tbl[$i]=array(
					'ID'=>$i,
					'Thumb'=>$thumb,
					'Name'=>$dat['mda_nice_name'].'<br/><small class="text-purple">'.$dat['mda_filename'].'<br/>'.$dat['mda_date'].'</small>',
					'Controls'=>'<button class="button button-lavendar small selectME" data-target="#'.$link.'" data-ref="'.$fpath.'" data-alt="'.$alt.'" data-bg="'.$bg.'"><i class="fi-plus"></i> Select</button>'
				);
				$count++;
			}
			$args['data']['data']=$tbl;
			$args['before']='filter';
			$list=dataTable($args);
			$list.='<script>$(function(){
				JQD.ext.filterThis("#dTable_filter","#dTable tbody tr");				
			});</script>';
		}else{
			$list=msgHandler('No records found for "'.$this->MEDIA_TYPE.'"...',false,false);
		}
		$title=ucwords($this->MEDIA_TYPE).' Selector: <span class="subheader">('.$count.')</span>';
		$icon='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
		$switch_url=URL.'admin/media/'.$this->MEDIA_TYPE.'/uploader/?link='.$link;
		$switch='<button class="button small button loadME" data-ref="'.$switch_url.'" title="upload files">Upload</button>';
		if($this->AJAX){
			$list='<div class="modal-flow">'.$list.'</div>';
			echo renderCard_active($title,$list,$switch.$this->SLIM->closer);
			die;
		}
		$this->OUTPUT=array(
			'title'=>$title,
			'content'=>$list,
			'icon'=>$icon,
			'menu'=>array('right'=>$this->renderContextMenu()),
		);
	}
	private function doUpload(){
		$this->OUTPUT=$this->MLIB->process();
		if($this->AJAX){
			$out=array('status'=>200,'message'=>$this->OUTPUT['content']);
			echo jsonResponse($out);
			die;
		}
	}
	private function renderUploader(){
		$content=$this->MLIB->get('uploader');
		$link=$this->getInsertLink();
		$icon='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
		$switch_url=URL.'admin/media/'.$this->MEDIA_TYPE.'/select/?link='.$link;
		$switch='<button class="button small button-dark-purple loadME" data-ref="'.$switch_url.'" title="'.$this->MEDIA_TYPE.' selector">'.ucwords($this->MEDIA_TYPE).'s</button>';
		if($this->AJAX){
			$list='<div class="modal-flow">'.$content['content'].'</div>';
			echo renderCard_active($content['title'],$list,$switch.$this->SLIM->closer);
			die;
		}
		$this->OUTPUT=array(
			'title'=>$content['title'],
			'content'=>$content['content'],
			'icon'=>$icon,
			'menu'=>array('right'=>$this->renderContextMenu()),
		);
	}
 	private function renderEditItem(){
		if($this->DATA){
			$data=current($this->DATA);
			$data=$this->formatData($data,'edit');
			$id=key($this->DATA);
			$tplname=($this->AJAX)?$this->SECTION.'-modal':$this->SECTION;
			$tpl=file_get_contents(TEMPLATES.'parts/tpl.'.$tplname.'-edit.html');
			$src=$data['mda_path'].$data['mda_filename'];
			$sidebar=$this->renderEditImage($src);
			$data['submit']='<i class="fi-check"></i> Update';
			$data['action']='update';
			$data['id']=$id;
			$data['url']=$this->PERMLINK;
			$data['delete']=$this->PERMLINK.'delete/'.$id;
			$tpl=str_replace('{sidebar}',$sidebar,$tpl);
			$form=replaceME($data,$tpl);
		}else{
			$list=msgHandler('Sorry, I could not find the record...',false,false);	
		}	
		$icon='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
		$this->OUTPUT=array(
			'title'=>'Edit '.ucME($this->MEDIA_TYPE).': <span class="subheader">#'.$id.' - '.fixHTML($data['mda_nice_name']).'</span>',
			'content'=>$form,
			'icon'=>$icon,
			'menu'=>array('right'=>$this->renderContextMenu()),		
		);
		if($this->AJAX){
			$this->OUTPUT['content']=renderCard_active($this->OUTPUT['title'],$form,$this->SLIM->closer);
		}
	}
	private function renderEditImage($args=false,$new=false){
		$frm['title']='Media Preview';
		$controls='';
		if($this->MEDIA_TYPE==='doc'){
			$pv='<div class="thumbnail text-center"><i class="fi-page-filled text-dark-purple icon-x2"></i></div>';
		}else{
			$pv='<img id="media-image" style="max-height:500px;" src="'.$args.'">';
		}
		if($new){
			$controls='<div class="button-group small expanded"><button class="button loadME" data-ref="'.URL.'admin/media/image/uploader"><i class="fi-upload"></i> Upload</button></div>';
			$pv.='<input id="mediaLink" type="text" name="filepath" />';
		}
		$frm['content']='<div id="main-image">'.$pv.$controls.'</div>';
		return ($this->AJAX)?$frm['content']:renderCard($frm);		
	}
	private function renderEditDocument($args=false){
		if($this->MEDIA_TYPE!=='book') return false;
		$icon=($args && $args!=='')?'check text-dark-green':'x text-red';
		$frm['title']='Document Link &nbsp;<i class="fi-'.$icon.'"></i>';
		$controls='<div class="button-group small expanded"><button class="button button-purple loadME" data-link="imgLink" data-ref="'.URL.'admin/documents/select"><i class="fi-photo"></i> Select</button><button class="button loadME" data-ref="'.URL.'admin/docs/uploader"><i class="fi-upload"></i> Upload</button></div>';
		$frm['content']='<div id="main-doc"><input id="docLink" type="text" name="meta[book_document_link]" value="'.$args.'"/>'.$controls.'</div>';
		return renderCard($frm);		
	}
	private function renderDelete(){
		if($this->ACTION==='delete_now'){
			$chk=$this->deleteRecord();
			$o=array(
				'status'=>200,
				'message'=>($chk)?'Okay, the item has been deleted.':'Sorry, I could not delete the item',
				'redirect'=>$this->PERMLINK
			);
			if($this->AJAX){
				echo jsonResponse($o);
			}else{
				setSystemResponse($o['redirect'],$o['message']);
			}
			die;
		}else{
			$content='<p>Are you sure you want to delete this item?</p><p class="text-dark-blue"><strong>'.$this->DATA[$this->ID]['mda_nice_name'].'</strong></p>';
			$content.='<p class="text-maroon"><em>Note that this action cannot be undone and that any links to this item will cease to work.</em></p>';
			$controls='<div class="button-group expanded">
				<button class="button secondary" data-close><i class="fi-x-circle"></i> No, maybe later</button>
				<button class="button button-red gotoME" data-ref="'.$this->PERMLINK.'delete_now/'.$this->ID.'"><i class="fi-x"></i> Yes, imediately!</button>
			</div>';
			$f=array(
				'title'=>'Delete '.$this->MEDIA_TYPE.' #'.$this->ID,
				'content'=>'<div class="callout alert text-center">'.$content.'</div>'.$controls,
			);
			$o=renderCard($f);
			if($this->AJAX){
				echo $o;
				die;
			}else{
				return $o;
			}
		}
	}
	private function renderEditOptions(){
		$frm['title']='Options';
		$content=msgHandler('No options found...',false,false);
		switch($this->MEDIA_TYPE){
			case 'page':
				$content='<label>Status<select name="itm_Active">{status}</select></label>';
				$content.='<label>Date<input placeholder="item date" type="date" name="itm_DAte" value="{itm_Date}" /></label>';
				break;
			case 'book':
				$content='<label>Author<input type="text" placeholder="author" name="meta[book_author]" value="{book_author}"/></label>';
				$content.='<label>Binding<select name="meta[book_binding]">{binding}</select></label>';
				$content.='<label>Publisher<input type="text" placeholder="publisher" name="meta[book_publisher]" value="{book_publisher}"/></label>';
				$content.='<label>Pub. Date<input type="date" placeholder="date published" name="itm_DAte" value="{itm_DAte}"/></label>';
				$content.='<label>Price<input type="number" min="0.01" step="0.01" placeholder="price" name="meta[book_price]" value="{book_price}"/></label>';
				$content.='<label>Status<select name="itm_Active">{status}</select></label>';
				break;
			default:
				$content='<label>Status<select name="itm_Active">{status}</select></label>';
		}
		$frm['content']=$content;	
		return renderCard($frm);		
	}
	private function itemStateSelect($state=false){
		$states=array('published','pending','disabled');
		$opt='';
		foreach($states as $i){
			$sel=($i===$state)?'selected':'';
			$opt.='<option value="'.$i.'">'.ucwords($i).'</option>';
		}
		return $opt;
	}
	private function bookBindingSelect($state=false){
		$states=array('Paperback','Hardback','Boardback','Spiral','Large Print');
		$opt='';
		foreach($states as $i){
			$sel=($i===$state)?'selected':'';
			$opt.='<option value="'.$i.'">'.ucwords($i).'</option>';
		}
		return $opt;
	}
	
	private function formatData($data,$mode='view'){
		$fix=array();
		foreach($data as $i=>$v){
			$val=$v;
			switch($i){
				case 'mda_nice_name':
					$val=fixHTML($val);
					break;
				case 'mda_date':
					$fmt='Y-m-d H:i';
					if($mode==='view')$fmt='Y-m-d';
					$val=validDate($val,$fmt);
					break;
			}
			$fix[$i]=$val;
		}
		return $fix;
	}
	
	private function renderContextMenu(){
		$libname=($this->MEDIA_TYPE==='image')?'document':'image';
		$but['back']='<button class="button small button-dark-purple backME" title="back to list" type="button"><i class="fi-list"></i> Back</button>';
		$but['new']='<button class="button small button-dark-blue gotoME" title="add a new record" data-ref="'.$this->PERMLINK.'new" type="button"><i class="fi-plus"></i> New</button>';
		$but['save']='<button class="button small button-olive submitME" title="save changes" data-ref="ajaxform" type="button"><i class="fi-check"></i> Update</button>';
		$but['switch']='<button class="button small button-lavendar gotoME" title="'.$libname.' library" data-ref="'.$this->PERMBACK.$libname.'" type="button"><i class="fi-list"></i> '.ucwords($libname).'s</button>';
		$b=[];$out=false;
		switch($this->ACTION){
			case 'edit':
				$b=array('back','new','save');
				break;
			case 'new':
				$b=array('back');
				break;		
			default:
				$b=array('new');
		}
		if($b){
			foreach($b as $i){
				$out.='<li>'.$but[$i].'</li>';
			}
		}
		return $out;
	}
	
	
}
