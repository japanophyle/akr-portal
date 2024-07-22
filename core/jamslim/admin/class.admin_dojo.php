<?php 
class admin_dojo{
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
	private $DOJO_LOCK=[];
	
	public  $ADMIN;
	public  $LEADER;
	public $AJAX;
	public $REQUEST;
	public $USER;
	public $METHOD;
	public $SECTION;
	public $ACTION;
	public $PLUG;
	public $ROUTE;
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->LIB=new slim_db_clubs($slim);
		$this->DEFAULT_REC=$this->LIB->get('new');
		$this->OPTIONS['Status']=$slim->Options->get('active');
		$this->OPTIONS['LocationID']=$slim->Options->get('dojos');
		$this->OPTIONS['AffiliateID']=$slim->Options->get('clubs_name');
		$this->IS_SUPER=($slim->user['access'] > $slim->AdminLevel)?true:false;
		$this->DOJO_LOCK=issetCheck($slim->user,'dojo_lock',[]);
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
			case 'view': case 'club': case 'view_location': 
				$this->renderViewItem();
				break;
			case 'dash':
				$this->renderDashboard();
				break;
			case 'fix':
				$this->renderFixLocations();
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
				$chk=$this->LIB->updateRecord($data,$data['id']);
				$state=$chk['status'];
				$msg=$chk['message'];
				break;
			case 'add':
				$data=$this->cleanInput();
				$chk=$this->LIB->addRecord($data);
				$state=$chk['status'];
				$msg=$chk['message'];
				break;
			default:
				$msg='Sorry, that action is not possible...';
				$state=500;		
		}
		if($this->AJAX){
			$o=array('status'=>$state,'message'=>$msg,'refresh'=>$url);
			echo jsonResponse($o);
		}else{
			setSystemResponse($url,$msg);
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
		$this->PERMLINK=$this->PERMBACK.'dojo/';
		$this->PLUG=issetCheck($this->SLIM->AdminPlugins,$this->SECTION);
		//init data
		if(!$this->ACTION && !$this->METHOD==='POST'){
			$this->DATA=$this->getClubs('all');
		}else{
			if($this->ACTION==='new'){
				$this->DATA=$this->getClubs('new');
				$this->ID='new';
			}else if((int)$this->ID){
				$key=($this->ACTION==='club')?'club':'id';
				$this->DATA=$this->getClubs($key);
			}else if(in_array($this->ACTION,['list','select'])){
				$this->DATA=$this->getClubs('all');
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
	private function renderDashboard(){
		$dojos=$this->OPTIONS['LocationID'];
		$dashlinks='';
		foreach($dojos as $i=>$v){
			$but['color']='navy';
			$but['icon']='target';
			$but['href']=$this->PERMBACK.'member/dojo/'.$i;
			$but['caption']=$v['LocationName'];
			$dashlinks.=$this->SLIM->zurb->adminButton($but);
		}
		$close=($this->AJAX)?$this->SLIM->closer:'';
		$title='Dojos';
		$content=renderCard_active($title,$dashlinks,$close);
		if($this->AJAX){
			echo $content;
			die;
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;		
	}
	private function renderListItems(){
		$count=0;
		if($this->DATA){
			$can_update=($this->LEADER)?hasAccess($this->USER,'clubs','update'):true;
			$tbl=[];
			foreach($this->DATA as $i=>$dat){
				$loc_id=$dat['ClubID'];
				$lead_id=$dat['LeaderID'];
				$dat=$this->formatData($dat);
				$members='<button class="button button-dark-blue small loadME" data-ref="'.$this->PERMBACK.'member/dojo/'.$loc_id.'"><i class="fi-male-female"></i> Members</button>';
				$mode=($can_update)?'edit':'view';
				$modei=($can_update)?'<i class="fi-pencil"></i> Edit':'<i class="fi-eye"></i> View Info.';
				$state=($dat['Status']==='Active')?'<span class="text-dark-green">Active</span>':'<span class="text-gray">Disabled</span>';
				$tbl[$i]=array(
					'Ref'=>$loc_id,
					'Name'=>$dat['ClubName'],
					'Code'=>$dat['ShortName'],
					'Leader'=>'<span class="link-dark-blue loadME" data-ref="'.$this->PERMBACK.'member/view/'.$lead_id.'">'.$dat['Leader'].'</span>',
					'Affiliate'=>$dat['AffiliateID'],
					'Status'=>$state,
					'Controls'=>$members.'<button class="button button-dark-purple small loadME" data-ref="'.$this->PERMLINK.$mode.'/'.$i.'">'.$modei.'</button>'
				);
				$count++;
			}
			$args['data']['data']=$tbl;
			$args['before']='filter';
			$list=dataTable($args);
		}else{
			$list=msgHandler('No dojo records found...',false,false);
		}
		$icon='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
		$this->OUTPUT=array(
			'title'=>'Dojos: <span class="subheader">('.$count.')</span>',
			'content'=>$list,
			'icon'=>$icon,
			'menu'=>array('right'=>$this->renderContextMenu()),
		);
	}
	private function getClubs($type=false){
		$ref=(in_array($type,['id','club']))?$this->ID:false;
		return $this->LIB->get($type,$ref);
	}
	private function getLeader($what=false,$id=0){
		$out=false;
		$lib=new slim_db_members($this->SLIM);
		switch($what){
			case 'details':
				if(!$id) return '- no id -';
				$member=$lib->get('details',$id);
				$out=($member)?$member['Normalname']:'';
				break;
			case 'select':
				$members=$lib->get('selector');
				$out.='<option value="0">- Not Set -</option>';		
				foreach($members as $i=>$v){
					$sel=($i==$id)?'selected':'';
					$out.='<option value="'.$i.'" '.$sel.'>'.$v.'</option>';
				}
				break;
		}
		return $out;
	}
	private function renderViewItem(){
		$item=($this->DATA)?$this->DATA:false;
		$title='Dojo Info. #'.$this->ID;
		$button=false;
		$can_update=($this->LEADER)?hasAccess($this->USER,'clubs','update'):true;
		if($item){
			$item=$this->formatData($item);
			$short=trim($item['ShortName']);
			if($short!=='') $title='Dojo Info. #'.$short;
			$thumb='<i class="fi-target text-navy icon-x3"></i>';
			$tpath=TEMPLATES.'parts/tpl.dojo-view.html';
			$tpl=false;
			if(file_exists($tpath))	$tpl=file_get_contents($tpath);
			$state=($item['Status']==='Active')?'<span class="text-olive">Active</span>':'<span class="text-maroon">'.$item['Status'].'</span>';
			$item['Status']=$state;
			$item['image']=$thumb;
			if($tpl){
				$content=replaceMe($item,$tpl);
				if($can_update) $button='<button title="edit this '.$this->SECTION.'" class="button button-dark-purple loadME" data-ref="'.$this->PERMLINK.'edit/'.$item['ClubID'].'"><i class="fi-pencil"></i></button> ';
			}else{
				$content=msgHandler('Sorry, no dojo template found...');
			}
		}else{
			$content=msgHandler('Sorry, I can\'t find a dojo with that ID ['.$this->ID.'] ...',false,false);			
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
			$data['action']=($this->ACTION==='new')?'add':'update';
			$data['submit']='<i class="fi-check"></i> '.ucwords($data['action']);
			$data['id']=$this->ID;
			$data['url']=($this->AJAX)?$this->PERMLINK:$this->PERMLINK.'edit/'.$this->ID;
			$data['controls']=($this->ACTION==='new')?'':$this->renderItemControls();
			if($this->ACTION==='new')$data['url']=$this->PERMLINK;
			$form=replaceME($data,$tpl);
			$uname=fixHTML($data['ClubName']);
		}else{
			$uname='???';
			$form=msgHandler('Sorry, I could not find the record...',false,false);	
		}	
		$icon='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
		$this->OUTPUT=array(
			'title'=>'Edit '.ucME($this->SECTION).': <span class="text-dark-purple">#'.$uname.'</span>',
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
		$controls.='<button class="button button-dark-blue loadME" data-ref="'.$this->PERMBACK.'member/dojo/'.$this->DATA['LocationID'].'"><i class="fi-male-female"></i> View Dojo Members</button>';
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
		$out='';$first=false;
		switch($what){
			case 'LocationID':
			case 'AffiliateID':
				$opts=$this->OPTIONS[$what];
				$first=($what==='AffiliateID')?'None':'- Not Set -';
				$first='<option value="0">'.$first.'</option>';
				break;
			case 'Status':
				$opts=$this->OPTIONS[$what];
				if($what==='Access' && !$this->IS_SUPER) unset($opts[5]);
				break;
			default:
				$opts=false;
		}
		if($opts){
			if($first) $out=$first;
			foreach($opts as $i=>$v){
				switch($what){
					case 'LocationID':
						$sel=($i==$var)?'selected':'';
						$out.='<option value="'.$i.'" '.$sel.'>'.$v['LocationName'].' - '.$v['LocationCountry'].'</option>';
						break;
					case 'AffiliateID':
						$sel=($i==$var)?'selected':'';
						$out.='<option value="'.$i.'" '.$sel.'>'.$v['ClubName'].' ('.$v['ShortName'].')</option>';
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
				case 'LeaderID':
					if($mode==='view'){
						$val=$this->getLeader('details',$v);
					}else if($mode==='edit'){
						//do something
					}else{
						$val=(int)$v;
					}
					break;
					break;
				case 'Status':
				//case 'LocationID':
				case 'AffiliateID':
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
	private function renderDojoLock($arg=''){
		if(is_string($arg) && $arg!==''){
			if($arg==='all'){
				$arg='';
			}else{
				$arg=unserialize($arg);
			}
		}
		if(!$arg||$arg==='') $arg=[];
		$dojos=$this->SLIM->Options->get('dojos');
		$o='';
		foreach($dojos as $i=>$v){
			$lbl=$v['LocationName'];
			$sel=(in_array($i,$arg))?'checked':'';
			$o.='<div class="cell"><span class="checkboxLabel">&nbsp;&nbsp;'.$lbl.'</span><div class="checkboxTick"><input type="checkbox" title="'.$lbl.'" value="'.$i.'" id="cbk_DojoLock'.$i.'" name="DojoLock[]" '.$sel.'><label for="cbk_DojoLock'.$i.'"></label></div></div>';
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
			case 'LocationID':
				if($val>0){
					$r=issetCheck($this->OPTIONS[$what],$val);
					if($r) $val=$r['LocationName'].' - '.$r['LocationCountry'];
				}else{
					$val='- Not Set -';
				}
				break;
			case 'AffiliateID':
				if($val>0){
					$r=issetCheck($this->OPTIONS[$what],$val);
					if($r) $val=$r['ClubName'].' ('.$r['ShortName'].')';
				}else{
					$val='None';
				}
				break;
			case 'Status':
				$val=issetCheck($this->OPTIONS[$what],$val);
				break;
		}
		return $val;		
	}
	private function renderContextMenu(){
		$but['back']='<button class="button small button-dark-purple backME" title="back to list" type="button"><i class="fi-arrow-left"></i> Back</button>';
		$but['new']='<button class="button small button-dark-blue loadME" title="add a new record" data-ref="'.$this->PERMLINK.'new" type="button"><i class="fi-plus"></i> New</button>';
		$but['save']='<button class="button small button-olive submitME" title="save changes" data-ref="ajaxform" type="button"><i class="fi-check"></i> Update</button>';
		$but['fix']='<button class="button small button-orange text-black gotoME" title="fix dojo locations" data-ref="'.$this->PERMLINK.'fix" type="button"><i class="fi-widget"></i> Fix Locations</button>';
		$b=$out=false;
		switch($this->ACTION){
			case 'edit':
				$b=array('back','new','save');
				break;
			case 'fix':
				
				break;
			default:
				if($this->ADMIN) $b=array('new');
				if($this->IS_SUPER) $b[]='fix';
		}
		if($b){
			foreach($b as $i){
				$out.='<li>'.$but[$i].'</li>';
			}
		}
		return $out;
	}
	
	private function renderFixLocations(){
		$fix=new fixDojoLocations($this->SLIM);
		$out=$fix->Process();
		$this->OUTPUT['title']=$out['title'];
		$this->OUTPUT['content']=$out['content'];
		$this->OUTPUT['menu']=$out['menu'];		
	}
	
}
