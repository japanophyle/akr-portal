<?php 
// basic user class
class admin_user{
	private $SLIM;
	private $LIB;
	private $DATA;
	private $OUTPUT;
	private $PERMLINK;
	private $PERMBACK;
	private $MEDIA_TYPE;
	private $ID=0;
	private $DEFAULT_REC;
	private $OPTIONS;
	private $IS_SUPER;
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
		$this->LIB=new slim_db_users($slim);
		$this->DEFAULT_REC=$this->LIB->get('new');
		$this->OPTIONS['Access']=$slim->Options->get('access_levels');
		$this->OPTIONS['Status']=$slim->Options->get('active');
		$this->OPTIONS['Dojo']=$slim->Options->get('dojos');
		$this->IS_SUPER=($slim->user['access']==30)?true:false;
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
			default:
				$this->renderListItems();
				break;				
		}
		return $this->renderOutput();
	}
	private function doPost(){
		$url=$this->PERMLINK;
		switch($this->REQUEST['action']){
			case 'update':
				$data=$this->cleanInput();
				$o=$this->LIB->updateRecord($data,$data['id']);
				break;
			case 'add':
				$data=$this->cleanInput();
				$o=$this->LIB->updateRecord($data,$data['id']);
				break;
			default:
				$o=array('status'=>500,'message'=>'Sorry, that action is not possible...','url'=>$url,'type'=>'message');
				$msg='Sorry, that action is not possible...';
				$state=500;		
		}
		if($this->AJAX){
			$o['close']=1;
			echo jsonResponse($o);
		}else{
			setSystemResponse($o['url'],$o['message']);
		}
		die;			
	}
	private function cleanInput(){
		$clean=array();
		$clean['id']=(int)issetCheck($this->REQUEST,'id');
		foreach($this->DEFAULT_REC as $i=>$v){
			if(array_key_exists($i,$this->REQUEST)){
				$clean[$i]=$this->REQUEST[$i];
			}
		}
		return $clean;
	}
	private function init(){
		$this->METHOD=$this->SLIM->router->get('method');
		if(!$this->METHOD) $this->METHOD='GET';
		$this->REQUEST=($this->METHOD==='POST')?$this->SLIM->router->get('post'):$this->SLIM->router->get('get');
		$this->ROUTE=$this->SLIM->router->get('route');
		$this->SECTION=issetCheck($this->ROUTE,1);
		$this->ACTION=issetCheck($this->ROUTE,2,'list');
		$this->ID=issetCheck($this->ROUTE,3);
		$this->AJAX=$this->SLIM->router->get('ajax');
		$this->USER=$this->SLIM->user;			
		$this->PERMBACK=URL.'admin/';
		$this->PERMLINK=$this->PERMBACK.'user/';
		$this->PLUG=issetCheck($this->SLIM->AdminPlugins,$this->SECTION);
		//init data
		if(!$this->ACTION && !$this->METHOD==='POST'){
			$this->DATA=$this->getUsers('all');
		}else{
			if($this->ACTION==='new'){
				$this->DATA=$this->getUsers('new');
				$this->ID='new';
			}else if((int)$this->ID){
				$this->DATA=$this->getUsers('id');
			}else if(in_array($this->ACTION,array('list','select'))){
				$this->DATA=$this->getUsers('all');
			}
		}		
	}
	private function renderOutput(){
		$keys=['title','metrics','content','icon','menu'];
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
	private function renderListItems(){
		$count=0;
		if($this->DATA){
			$tbl=[];
			$STATE=$this->SLIM->StatusColor;
			foreach($this->DATA as $i=>$dat){
				if($dat['Access']==30 && !$this->IS_SUPER) continue;
				$dojo=issetCheck($dat,'DojoLock');
				$dojo=($dat['Access']>=25)?'All':$this->renderDojoLock_flat($dojo);
				unset($dat['DojoLock']);
				$dat=$this->formatData($dat);
				$thumb='<i class="fi-torso text-'.$dat['Access']['color'].' icon-x2"></i>';
				$tbl[$i]=array(
					'ID'=>$i,
					'Thumb'=>$thumb,
					'Name'=>$dat['Name'].'<br/><small class="text-purple">'.$dat['Email'].'</small>',
					'Username'=>$dat['Username'],
					'Dojo'=>$dojo,
					'Access'=>'<span class="text-'.$dat['Access']['color'].'">'.$dat['Access']['label'].'</span>',
					'Status'=>$STATE->render('active_status',$dat['Status']),
					'Controls'=>'<button class="button button-dark-purple small loadME" data-ref="'.$this->PERMLINK.'edit/'.$i.'"><i class="fi-pencil"></i> Edit</button>'
				);
				$count++;
			}
			$args['data']['data']=$tbl;
			$args['before']='filter';
			$list=dataTable($args);
		}else{
			$list=msgHandler('No user records found...',false,false);
		}
		$icon='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
		$this->OUTPUT=array(
			'title'=>'Users: <span class="subheader">('.$count.')</span>',
			'content'=>$list,
			'icon'=>$icon,
			'menu'=>array('right'=>$this->renderContextMenu()),
		);
	}
	private function getUsers($type=false){
		$ref=($type==='id')?$this->ID:false;
		return $this->LIB->get($type,$ref);
	}
	private function renderViewItem(){
		$item=($this->DATA)?$this->DATA:false;
		$title='User Info. #'.$this->ID;
		$button=false;
		if($item){
			$item=$this->formatData($item);
			$thumb='<i class="fi-torso text-'.$item['Access']['color'].' icon-x3"></i>';
			$tpath=TEMPLATES.'parts/tpl.user-view.html';
			$tpl=false;
			if(file_exists($tpath))	$tpl=file_get_contents($tpath);
			$state=($item['Status']==='Active')?'<span class="text-olive">Active</span>':'<span class="text-maroon">'.$item['Status'].'</span>';
			$fill=array(
				'name'=>$item['Name'],
				'username'=>$item['Username'],
				'type'=>$item['Access']['label'],
				'email'=>$item['Email'],
				'status'=>$state,
				'image'=>$thumb,
			);
			if($tpl){
				$content=replaceMe($fill,$tpl);
				$button='<button title="edit this '.$this->SECTION.'" class="button button-dark-purple loadME" data-ref="'.$this->SLIM->router->get('permlinks','back').$this->SECTION.'/edit/'.$this->ID.'"><i class="fi-pencil"></i></button> ';
			}else{
				$content=msgHandler('Sorry, no user template found...');
			}
		}else{
			$content=msgHandler('Sorry, I can\'t find a user with that ID...',false,false);			
		}
		$out=renderCard_active($title,$content,$button.$this->SLIM->closer);
		if($this->AJAX){
			echo $out;
			die;
		}
		return $out;	
	}
	private function renderEditItem(){
		if($this->DATA){
			$data=$this->formatData($this->DATA,'edit');
			$tplf=($this->AJAX)?$this->SECTION.'-edit-modal.html':$this->SECTION.'-edit.html';
			$tpl=file_get_contents(TEMPLATES.'parts/tpl.'.$tplf);
			$sidebar='';
			$data['action']=($this->ACTION==='new')?'add':'update';
			$data['submit']='<i class="fi-check"></i> '.ucwords($data['action']);
			$data['id']=$this->ID;
			$data['url']=($this->AJAX)?$this->PERMLINK:$this->PERMLINK.'edit/'.$this->ID;
			$data['controls']=($this->ACTION==='new')?'':$this->renderItemControls();
			if($this->ACTION==='new')$data['url']=$this->PERMLINK;
			$tpl=str_replace('{sidebar}',$sidebar,$tpl);
			$form=replaceME($data,$tpl);
			$uname=fixHTML($data['Name']);
		}else{
			$uname='???';
			$form=msgHandler('Sorry, I could not find the record...',false,false);	
		}	
		$icon='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
		$this->OUTPUT=array(
			'title'=>'Edit '.ucME($this->SECTION).': <span class="subheader">#'.$this->ID.' - '.$uname.'</span>',
			'content'=>$form,
			'icon'=>$icon,
			'menu'=>array('right'=>$this->renderContextMenu()),		
		);
		if($this->AJAX){
			$this->OUTPUT['content']=renderCard_active($this->OUTPUT['title'],$form,$this->SLIM->closer);
		}
	}
	private function renderItemControls(){
		$controls='';
		if($this->DATA['MemberID']) $controls.='<button class="button button-navy loadME" data-ref="'.URL.'admin/member/edit/'.$this->DATA['MemberID'].'"><i class="fi-male-female"></i> View member record</button>';
		$controls.='<button class="button button-dark-purple gotoME" data-ref="'.URL.'admin/mailer/add/user/'.$this->ID.'"><i class="fi-mail"></i> Send an email</button>';
		return $controls;
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
	private function getSelect($what=false,$var=false){
		$out='';
		switch($what){
			case 'Access':
			case 'Status':
				$opts=$this->OPTIONS[$what];
				if($what==='Access' && !$this->IS_SUPER) unset($opts[30]);
				break;
			default:
				$opts=[];
		}
		if($opts){
			foreach($opts as $i=>$v){
				switch($what){
					case 'Access':
						$sel=($i==$var)?'selected':'';
						$out.='<option value="'.$i.'" '.$sel.'>'.$v['label'].'</option>';
						break;
					case 'Status':
						$sel=($i==$var)?'selected':'';
						$out.='<option value="'.$i.'" '.$sel.'>'.$v.'</option>';
						break;
				}						
			}
		}
		return $out;
	}
	
	private function formatData($data,$mode='view'){
		$fix=array();
		foreach($data as $i=>$v){
			$val=$v;
			switch($i){
				case 'DojoLock':
					$val=$this->renderDojoLock($v);
					break;
				case 'Permissions':
					$val=$this->renderPermissions($v,$data['Access']);
					break;
				case 'Access':
				case 'Status':
					if($mode==='view'){
						$val=$this->getOption($i,$v);
					}else if($mode==='edit'){
						$val=$this->getSelect($i,$v);
					}else{
						$val=(int)$v;
					}
					break;
			}
			$fix[$i]=$val;
		}
		return $fix;
	}
	private function renderDojoLock_flat($arg=''){
		if(is_string($arg) && $arg!==''){
			if($arg==='all'){
				$arg='';
			}else if(is_numeric($arg)){
				$arg=[(int)$arg];
			}else{
				$arg=unserialize($arg);
			}
		}
		if(!$arg||$arg==='') $arg=[];
		$o=[];
		foreach($this->OPTIONS['Dojo'] as $i=>$v){
			if(in_array($i,$arg)) $o[]=$v['ShortName'];
		}
		return ($o)?implode(', ',$o):'None';
	}
	private function renderDojoLock($arg=''){
		if(is_string($arg) && $arg!==''){
			if($arg==='all'){
				$arg='';
			}else{
				$arg=unserialize($arg);
			}
		}
		if(!$arg||$arg==='') $arg=[];
		$o='';
		foreach($this->OPTIONS['Dojo'] as $i=>$v){
			$lbl=$v['ShortName'];
			$sel=(in_array($i,$arg))?'checked':'';
			$o.='<div class="cell"><span class="checkboxLabel">&nbsp;&nbsp;'.$lbl.'</span><div class="checkboxTick" title="'.$v['LocationName'].'"><input type="checkbox" title="'.$lbl.'" value="'.$i.'" id="cbk_DojoLock'.$i.'" name="DojoLock[]" '.$sel.'><label for="cbk_DojoLock'.$i.'"></label></div></div>';
		}
		return '<div class="grid-x grid-padding-y small-up-3 medium-up-5 large-up-6">'.$o.'</div>';
	}
	private function renderPermissions($arg='',$access=0){
		$arg=unserialize($arg);
		if(!$arg) $arg=[];
		$o=['attr_ar'=>['data'=>$arg],'access'=>$access];
		$p=$this->SLIM->Permissions->get('edit_perms',$o);
		return $p;

	}
	private function getOption($what=false,$val=false){
		switch($what){
			case 'Access':
			case 'Status':
				$val=issetCheck($this->OPTIONS[$what],$val);
				break;
		}
		return $val;		
	}
	private function renderContextMenu(){
		$libname=($this->MEDIA_TYPE==='image')?'document':'image';
		$but['back']='<button class="button small button-dark-purple backME" title="back to list" type="button"><i class="fi-arrow-left"></i> Back</button>';
		$but['new']='<button class="button small button-dark-blue loadME" title="add a new record" data-ref="'.$this->PERMLINK.'new" type="button"><i class="fi-plus"></i> New</button>';
		$but['save']='<button class="button small button-olive submitME" title="save changes" data-ref="ajaxform" type="button"><i class="fi-check"></i> Update</button>';
		$but['switch']='<button class="button small button-lavendar gotoME" title="'.$libname.' library" data-ref="'.$this->PERMBACK.$libname.'" type="button"><i class="fi-check"></i> '.ucwords($libname).'s</button>';
		$b=[];$out=false;
		switch($this->ACTION){
			case 'edit':
				$b=array('back','new','save');
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
