<?php
class admin_option {

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
	private $HIDE_ALT_ID=['application','site'];
	private $HIDE_VALUE=['reason'];
	private $SUPERS=['grade','super'];
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
		$this->FIELDS=$slim->ezPDO->getFields('Options');
		$this->OPTIONS=[
			'default'=>'active',
			'Site Status'=>'yesno',
			'offline'=>'yesno',
			'member_sorting_order'=>[0=>'Ascending',1=>'Descending'],
			'Data Backup Frequency'=>[1=>1,2=>2,3=>3,4=>4,5=>5,6=>6],
			'Calendar Months'=>[1=>1,2=>2,4=>4,6=>6,8=>8,12=>12],
			'Update Notifications'=>'noyes',
			'script_version'=>'text',
		];
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
			case 'delete': case 'delete_now':
			case 'delete_location': case 'delete_location_now':
				$this->renderDeleteItem();
				break;
			case 'edit_location': case 'new_location':
				$this->renderEditItem();
				break;
			case 'group':
				$this->renderGroups();
				break;
			case 'backup_db':
			case 'backup_db_now':
			case 'backup_log':
				$this->renderBackupDB();
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
		$this->PERMLINK=URL.'admin/option/';
		if(!$this->METHOD){
			$this->METHOD=$this->SLIM->router->get('method');
			if(!$this->METHOD) $this->METHOD='GET';
			$this->REQUEST=($this->METHOD==='POST')?$this->SLIM->router->get('post'):$this->SLIM->router->get('get');
			$this->ROUTE=$this->SLIM->router->get('route');
			$this->USER=$this->SLIM->user;			
			$this->PLUG=issetCheck($this->SLIM->AdminPlugins,'option');
		}
		if($this->METHOD==='POST'){
			$this->ACTION=issetCheck($this->REQUEST,'action');
			$this->ID=issetCheck($this->REQUEST,'id');
			if($this->ID==='new') $this->ACTION='new';
		}else{
			$this->ACTION=issetCheck($this->ROUTE,2,'group');
			$this->ID=($this->ACTION==='new')?'new':issetCheck($this->ROUTE,3);
			if($this->ACTION==='group' && !$this->ID) $this->ID='application';
		}
		if($this->METHOD!=='POST') $this->initData();
	}
	private function initData(){
		if(!$this->ACTION){
			$this->ID='application';
			$this->DATA=$this->getOption('group',$this->ID);
		}else if(in_array($this->ACTION,['backup_db','backup_db_now','backup_log'])){
			$this->DATA=[];
		}else if($this->ACTION==='list_all'){
			$this->DATA=$this->getOption('all');
		}else if($this->ACTION==='group'){
			$this->DATA=($this->ID==='locations')?$this->getLocations():$this->getOption($this->ACTION,$this->ID);
		}else if(in_array($this->ACTION,['edit_location','new_location','delete_location','delete_location_now'])){
			$this->DATA=$this->getLocations('id',$this->ID);
		}else if($this->ID==='new'){
			$r3=issetCheck($this->ROUTE,3);
			$this->DATA=[0=>['id'=>0,'OptionID'=>0,'OptionGroup'=>'','OptionName'=>'','OptionDescription'=>'','OptionValue'=>'']];
			if($r3==='locations'){
				$this->DATA=[0=>['id'=>0,'LocationName'=>'','LocationCountry'=>'','LocationDOJO'=>0]];
			}
		}else if((int)$this->ID){
			$this->DATA=$this->getOption('id',$this->ID);
		}else{
			$this->DATA=$this->getOption('name',$this->ID);
			$url=$this->PERMLINK;
			if(count($this->DATA)>0){
				$url.='edit/'.$this->DATA[0]['id'];
				$msg=false;
			}else{
				$msg='Sorry, I could not find "'.$this->ID.'"...';
			}
			setSystemResponse($url,$msg);
			die($msg);
		}
	}
	private function getOption($what,$ref=false){
		$db=$this->SLIM->db->Options;
		switch($what){
			case 'all':
				$recs=$db->order('OptionGroup ASC, OptionName');
				break;
			case 'id':
				$recs=$db->where('id',$ref);
				break;
			case 'name':
				$recs=$db->where('OptionName',$ref)->select('id,OptionID,OptionGroup')->limit(1);
				break;
			case 'group':
				$recs=($ref==='menu')?$db->select('id,OptionID,OptionGroup')->group('OptionGroup'):$db->order('OptionName')->where('OptionGroup',$ref);
				break;
			default:
				$recs=false;			
		}
		if($recs) $recs=renderResultsORM($recs,'id');
		if(!$recs){
			$recs=[];
		}else if($ref!=='menu'){
			$recs=$this->hideOptions($recs);
		}
		return $recs;
	}
	private function hideOptions($recs){
		if($this->USER['access']>=$this->SLIM->SuperLevel) return $recs;
		$hide=['Data Backup Date','Data Backup Email','Data Backup Format','Data Backup Frequency','Helper Bar'];
		$out=[];
		foreach($recs as $i=>$v){
			if(!in_array($v['OptionName'],$hide)) $out[$i]=$v;
		}
		return $out;
	}
	private function getLocations($what=false,$ref=false){
		$db=$this->SLIM->db->Locations;
		if($ref){
			$fld=($what==='id')?'LocationID':$what;
			$recs=$db->where($fld,$ref);
		}else{
			$recs=$db->where('LocationID > ?',0);
		}
		if($recs){
			$recs=renderResultsORM($recs,'LocationID');
		}else{
			$recs=[];
		}
		return $recs;		
	}
	private function doPost(){
		$url=$this->PERMLINK;
		switch($this->ACTION){
			case 'new':
			case 'update':
				$rsp=$this->saveRecord();
				$url.='group/'.$rsp['group'];
				break;
			case 'update_location':
			case 'add_location':
				$rsp=$this->saveLocation();
				$url.='group/locations';
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
		$nurl=$this->PERMLINK.'new/';
		if(isset($this->ROUTE[3])) $nurl.=$this->ROUTE[3];
		$but['back']='<button class="button small button-dark-purple backME" title="back to list" type="button"><i class="fi-arrow-left"></i> Back</button>';
		$but['new']='<button class="button small button-dark-blue loadME" title="add a new option" data-ref="'.$nurl.'" type="button"><i class="fi-plus"></i> New</button>';
		$but['backup']='<button class="button small button-aqua text-black loadME" title="backup database" data-size="small" data-ref="'.$this->PERMLINK.'backup_db/" type="button"><i class="fi-database"></i> Backup</button>';
		$but['prods']='<button class="button small button-navy gotoME" title="list all products" data-ref="'.$this->PERMLINK.'" type="button"><i class="fi-list"></i> Products</button>';
		$but['cats']='<button class="button small button-navy loadME" title="products by category" data-ref="'.$this->PERMLINK.'category/" type="button"><i class="fi-list"></i> By Category</button>';
		$but['groups']='<button class="button small button-navy loadME" title="products by group" data-ref="'.$this->PERMLINK.'group/menu/" type="button"><i class="fi-list"></i> Other Groups</button>';
		$but['edit']='<button class="button small button-dark-blue loadME" title="edit payment record" data-ref="'.$this->PERMLINK.'edit_payment/'.$this->ID.'/list" type="button"><i class="fi-pencil"></i> Edit</button>';
		$but['event']='<button class="button small button-dark-blue loadME" title="edit event" data-size="large" data-ref="'.$this->PERMLINK.'edit/'.$this->ID.'" type="button"><i class="fi-calendar"></i> Event #'.$this->ID.'</button>';
		$but['download']='<button class="button small button-purple loadME" title="download" data-ref="'.$this->PERMLINK.'rollcall/'.$this->ID.'/download" type="button"><i class="fi-download"></i> Download</button>';
		$b=[];$out='';
		switch($this->ACTION){
			case 'edit':
				$b=['back','new'];
				break;
			default:
				$b=['backup','groups','new'];
		}
		if($b){
			foreach($b as $i){
				$out.='<li>'.$but[$i].'</li>';
			}
		}
		return $out;
	}
	private function formatData($data,$mode='view'){
		$fix=$data;
		$json=['member event','member registration'];
		$comp=['renewal_message_1','Compile CSS','Compile JS','util_zipper'];
		$fmt=(in_array($data['OptionName'],$json))?'json':false;
		if(!$fmt) $fmt=(in_array($data['OptionName'],$comp))?'compress':'text';
		switch($fmt){
			case 'json':
				$fix['OptionValue']=json_decode($fix['OptionValue'],1);
				break;
			case 'comp':
				$fix['OptionValue']=compress($fix['OptionValue'],false);
				break;			
			default:
				
		}
		if($mode=='edit'){
			$groups=$this->getOption('group','menu');
			$o='';
			foreach($groups as $g) $o.='<option value="'.$g['OptionGroup'].'"/>';
			$fix['groups']=$o;
			if(in_array($fix['OptionGroup'],['memberType','grade','payment_method','eventType','productType'])){
				$fix['OptionValue']='<input type="text" name="OptionValue" value="'.$fix['OptionValue'].'"/>';
			}else if($fix['OptionGroup']==='reason'){
				$fix['OptionValue']='<span class="label">* value not used for this option*</span><input type="hidden" name="OptionValue" value=""/>';
			}else if($this->ID==='new'){	
				$fix['OptionValue']='<textarea rows="8" name="OptionValue" ></textarea>';
			}else if(is_numeric($fix['OptionValue'])){
				$ox=issetCheck($this->OPTIONS,$fix['OptionName'],$this->OPTIONS['default']);
				if($ox==='text'){
					$fix['OptionValue']='<input type="text" name="OptionValue" value="'.$fix['OptionValue'].'"/>';
				}else{	
					$o=(is_array($ox))?$ox:$this->SLIM->Options->get($ox);
					$op=$this->renderSelectOptions($fix['OptionValue'],$o);
					$fix['OptionValue']='<select name="OptionValue">'.$op.'</select>';
				}
			}else if(is_string($fix['OptionValue'])){
				$txt=trim($fix['OptionValue']);
				$fix['OptionValue']='<textarea rows="8" name="OptionValue" >'.$txt.'</textarea>';
				if(strlen($txt)<200){
					$fix['OptionValue']='<input type="text" name="OptionValue" value="'.$txt.'"/>';
				}
			}
		}
		return $fix;
	}
	private function renderSelectOptions($key,$o,$add_default=false){
		$h='';
		if($o && is_array($o)){
			if($add_default) $h='<option>* not set *</option>';
			foreach($o as $i=>$v){
				$lbl=$v;
				if(is_array($v)){
					$lbl=issetCheck($v,'OptionName');
					if(!$lbl) $lbl=issetCheck($v,'LocationName');
					if(is_array($lbl)) preME([$i,$lbl],2);
				}
				$sel=($key==$i)?'selected':'';
				$h.='<option value="'.$i.'" '.$sel.'>'.$lbl.'</option>';
			}
		}else{
			$h='<option>no options for '.$key.'</option>';
		}
		return $h;
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
		if($tmp && $this->ID==='new'){
			$grp=issetCheck($tmp,'OptionGroup');
			if(trim($grp)===''){
				$tmp['OptionGroup']='application';
			}
		}
		return $tmp;		
	}
	
	private function renderListItems(){
		$count=0;
		if($this->DATA){
			$tbl=[];
			$super_only=['default_language','language_options','medialib_thumbnails','payment_gateway','payment_power'];
			$skip=['mailbot','admin_email','Site Status','Update Notifications'];
			foreach($this->DATA as $i=>$v){
				if($this->ID==='locations'){
					if(!(int)$v['LocationDOJO']) continue;
					$controls='<button class="button button-dark-purple small loadME" data-ref="'.$this->PERMLINK.'edit_location/'.$i.'"><i class="fi-pencil"></i> Edit</button>';
					$tbl[$i]=array(
						'ID'=>$i,
						'Name'=>$v['LocationName'],
						'Country'=>$v['LocationCountry'],
						'Dojo'=>((int)$v['LocationDOJO'])?'Yes':'No',
						'Controls'=>$controls
					);
				}else{
					if(!isset($v['OptionName'])) continue;
					if(in_array($v['OptionName'],$skip)) continue;
					if($this->USER['access'] < $this->SLIM->SuperLevel){
						if(in_array($v['OptionName'],$super_only)) continue;
					}
					$dat=$this->formatData($v);
					$val=truncateME($dat['OptionValue'],25,true);
					$controls='<button class="button button-dark-purple small loadME" data-ref="'.$this->PERMLINK.'edit/'.$i.'"><i class="fi-pencil"></i> Edit</button>';
					$tbl[$i]=array(
						'ID'=>$i,
						'Ref'=>$dat['OptionID'],
						'Name'=>$dat['OptionName'],
						'Group'=>$dat['OptionGroup'],
						'Value'=>$val,
						'Controls'=>$controls
					);
					if(in_array($dat['OptionGroup'],$this->HIDE_ALT_ID)) unset($tbl[$i]['Ref']);
					if(in_array($dat['OptionGroup'],$this->HIDE_VALUE)) unset($tbl[$i]['Value']);
				}					
				$count++;
			}
			$args['data']['data']=$tbl;
			$args['before']='filter';
			$list=dataTable($args);
		}else{
			$list=msgHandler('No records found...',false,false);
		}
		$uname='App Settings - All';
		if($this->ACTION==='group') $uname='App Settings By Group #'.ucME($this->ID);
		$this->OUTPUT['title']=$uname.': <span class="subheader">('.$count.')</span>';
		$this->OUTPUT['content']=$list;
		if($this->AJAX){
			echo renderCard_active($this->OUTPUT['title'],$this->OUTPUT['content'],$this->SLIM->closer);
			echo '<script>$(".reveal .modal-body").foundation();</script>';
			die;
		}	
	}
	private function renderEditLocationItem(){
		if($this->DATA){
			$data=current($this->DATA);
			$data=$this->formatData($data,'edit');
			$controls='';
			if((int)$this->ID){
				$tplf=($this->AJAX)?$this->SECTION.'-edit-modal.html':$this->SECTION.'-edit.html';
				$url=($this->AJAX)?$this->PERMLINK:$this->PERMLINK.'edit/'.$this->ID;
				$action='update';
				$submit='<i class="fi-check"></i> '.ucwords($action);
				$uname=fixHTML($data['OptionName']);
				$title='Edit '.ucME($this->SECTION);
				//set values
				$alt=$value='';
				if(!in_array($data['OptionGroup'],$this->HIDE_ALT_ID)) $alt='<label>Alt. Ref.:<input placeholder="22" type="number" name="OptionID" value="'.$data['OptionID'].'" /></label>';
				if(!in_array($data['OptionGroup'],$this->HIDE_VALUE)) $value='<label>Value:'.$data['OptionValue'].'</label>';
                $data['OptionValue']=$alt.$value;
			}else{
				$tplf=$this->SECTION.'-new-modal.html';
				$url=$this->PERMLINK;
				$action='add';
				$submit='<i class="fi-plus"></i> '.ucwords($action);
				$uname='New';
				$title='Add '.ucME($this->SECTION);
				if(isset($this->ROUTE[3])) $data['OptionGroup']=$this->ROUTE[3];
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
	private function renderEditItem(){
		if($this->DATA){
			$data=current($this->DATA);
			$tplf=($this->AJAX)?$this->SECTION.'-edit-modal.html':$this->SECTION.'-edit.html';
			$controls=($this->USER['access']>=$this->SLIM->AdminLevel)?'<button class="button button-maroon loadME" data-ref="'.$this->PERMLINK.'delete/'.$this->ID.'"><i class="fi-x-circle"></i> Delete</button>':'';
			if($this->ACTION==='edit_location'){
				$tplf='location-edit-modal.html';
				$url=($this->AJAX)?$this->PERMLINK:$this->PERMLINK.'edit_location/'.$this->ID;
				$action='update_location';
				$submit='<i class="fi-check"></i> '.ucMe($action);
				$uname=fixHTML($data['LocationName']);
				$title='Edit '.ucME($this->SECTION);
				$data['LocationDOJO']=renderSelectOptions([0=>'No',1=>'Yes'],$data['LocationDOJO']);
				$controls=($this->USER['access']>=$this->SLIM->AdminLevel)?'<button class="button button-maroon loadME" data-ref="'.$this->PERMLINK.'delete_location/'.$this->ID.'"><i class="fi-x-circle"></i> Delete</button>':'';
			}else if((int)$this->ID){
				$data=$this->formatData($data,'edit');
				$url=($this->AJAX)?$this->PERMLINK:$this->PERMLINK.'edit/'.$this->ID;
				$action='update';
				$submit='<i class="fi-check"></i> '.ucwords($action);
				$uname=fixHTML($data['OptionName']);
				$title='Edit '.ucME($this->SECTION);
				//set values
				$alt=$value='';
				if(!in_array($data['OptionGroup'],$this->HIDE_ALT_ID)) $alt='<label>Alt. Ref.:<input placeholder="22" type="number" name="OptionID" value="'.$data['OptionID'].'" /></label>';
				if(!in_array($data['OptionGroup'],$this->HIDE_VALUE)) $value='<label>Value:'.$data['OptionValue'].'</label>';
                $data['OptionValue']=$alt.$value;
			}else{
				$r3=issetCheck($this->ROUTE,3);
				$tplf=$this->SECTION.'-new-modal.html';
				$url=$this->PERMLINK;
				$action='add';
				$submit='<i class="fi-plus"></i> '.ucwords($action);
				$uname='New';
				$title='Add '.ucME($this->SECTION);
				if($r3) $data['OptionGroup']=$r3;
				if($r3==='locations'){
					$tplf='location-edit-modal.html';
					$action='add_location';
					$title='Add Location';
					$this->ID='new_location';
					$data['LocationDOJO']=renderSelectOptions([0=>'No',1=>'Yes'],$data['LocationDOJO']);
				}else{
					$data=$this->formatData($data,'edit');					
				}
			}
			if(in_array($this->ACTION,['edit_location','new'])){
				
			}else{
				if(in_array($this->DATA[$this->ID]['OptionGroup'],['application','site','super'])) $controls='';
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
	private function renderDeleteItem(){
		$debug=false;
		$title='Delete Option #'.$this->ID;
		if(!$this->DATA){
			$content=msgHandler('Sorry, no records found for ref:'.$this->ID,false,false);
		}else if($this->ACTION==='delete_now'){
			$url=$this->PERMLINK.'group/'.$this->DATA[$this->ID]['OptionGroup'];
			$db=$this->SLIM->db->Options;
			$rec=$db->where('id',$this->ID);
			if(count($rec)==1){
				$chk=($debug)?1:$rec->delete();
				if($chk){
					$msg='Okay, the option has been deleted.';
				}else{
					$msg='Sorry, I ccould not delete the option...';
				}
			}else{
				$msg='Sorry, I could not find a record to delete...';
			}
			setSystemResponse($url,$msg);
			die($msg);
		}else if($this->ACTION==='delete_location_now'){
			$url=$this->PERMLINK.'group/locations';
			$title='Delete Location #'.$this->ID;
			$db=$this->SLIM->db->Locations;
			$rec=$db->where('LocationID',$this->ID);
			if(count($rec)==1){
				$chk=($debug)?1:$rec->delete();
				if($chk){
					$msg='Okay, the location has been deleted.';
				}else{
					$msg='Sorry, I could not delete the location...';
				}
			}else{
				$msg='Sorry, I ccould not find a record to delete...';
			}
			setSystemResponse($url,$msg);
			die($msg);			
		}else{
			$act=($this->ACTION==='delete_location')?'delete_location_now':'delete_now';
			$name=($this->ACTION==='delete_location')?'<em>Name:</em> '.ucME($this->DATA[$this->ID]['LocationName']).'<br/><em>Country:</em> '.$this->DATA[$this->ID]['LocationCountry']:'<em>Name:</em> '.ucME($this->DATA[$this->ID]['OptionName']).'<br/><em>Value:</em> '.$this->DATA[$this->ID]['OptionValue'];
			$content='<div class="callout primary text-center"><p class="h3 text-dark-blue">Do you want to delete this option?</p><p><strong>'.$name.'</strong></p><p><strong class="text-maroon">Note that deleting items which are in use can lead to problems with the data structure.</strong></p></div>';
			$content.='<div class="button-group expanded"><button class="button secondary" data-close><i class="fi-x-circle"></i> No, maybe later.</button><button class="button small button-red gotoME small" data-ref="'.$this->PERMLINK.$act.'/'.$this->ID.'"><i class="fi-check"></i> Yes, do it now.</button></div>';
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;		
		if($this->AJAX){
			echo '<style>.reveal .card-section.main {max-height: 100%;overflow-Y: auto;}</style>';
			echo renderCard_active($this->OUTPUT['title'],$content,$this->SLIM->closer);
			die;
		}
	}
	private function renderGroups(){
		if($this->DATA){
			if($this->ID==='menu'){
				$dashlinks='';
				$skip=['json_forms','system','mail_message','navigation'];
				foreach($this->DATA as $i=>$v){
					if(in_array($v['OptionGroup'],$skip)) continue;
					$but['color']='navy';
					if(in_array($v['OptionGroup'],$this->SUPERS)){
						if($this->USER['access']<$this->SLIM->SuperLevel) continue;
						$but['color']='maroon';
					}
					$lbl=ucME($v['OptionGroup']);
					$but['icon']=$this->PLUG['icon'];
					$but['href']=$this->PERMLINK.'group/'.$v['OptionGroup'];
					$but['caption']=$lbl;
					$but['title']=$lbl;
					$dashlinks.=$this->SLIM->zurb->adminButton($but);
				}
				//locations
				if($this->USER['access']>=$this->SLIM->AdminLevel){
					$but['color']='navy';
					$but['icon']=$this->PLUG['icon'];
					$but['href']=$this->PERMLINK.'group/locations';
					$but['caption']='Locations';
					$but['title']='Locations';
					$dashlinks.=$this->SLIM->zurb->adminButton($but);
				}
				$title='App Settings By Group';
				$content=$dashlinks;			
			}else if($this->ID){
				$this->renderListItems();
				return;
			}
		}else{
			$title='App Settings By Group';
			$content=msgHandler('Sorry, no records found...',false,false);	
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;
		if($this->AJAX){
			$this->OUTPUT['content']=renderCard_active($title,$content,$this->SLIM->closer);
		}
	}
	private function renderBackupDB(){
		switch($this->ACTION){
			case 'backup_db_now':
				$msg='Sorry, you can\'t do that...';
				if($this->SLIM->user['access']>=25||defined('CRON_BOT')){
					$log=['date'=>date('Y-m-d H:i:s')];
					$dumper=$this->SLIM->backup_db;
					$dumper->start(CACHE.'sql_backup/sql_dump_'.time().'.sql');
					$log+=$dumper->log;
					if(count($log)){	
						//set date stamp
						$rec=$this->SLIM->db->Options()->where('OptionName','Data Backup Date')->limit(1);
						$rec->update(array('OptionValue'=>json_encode($log)));
						$msg='Okay, the data has been backed up.';
					}else{
						$msg='Sorry, the data has been not been backed up due to an unknown error...';	
					}
				}
				setSystemResponse($this->PERMLINK,$msg);
				break;
			case 'backup_log':
				$title='View Backup Log';
				$rec=$this->SLIM->db->Options->where('OptionName','Data Backup Date');
				$rec=renderResultsORM($rec);
				$rec=current($rec);
				$log=json_decode($rec['OptionValue'],1);
				$dl='';
				$ct=1;
				foreach($log as $i=>$v){
					if($i==='date'){
						$dl.='<dt>Last Database Backup On :'.$v.'</dt>';
					}else{
						$dl.='<dd>'.$i.' ('.$v.' records).</dd>';
						$ct++;
					}						
				}
				$dl.='<dd class="label bg-dark-green">'.$ct.' tables backed up.</dd>';
				$content='<div class="callout"><dl>'.$dl.'</dl></div>';
				break;
			default:			
				$title='Backup DB';
				$content='<div class="callout primary text-center"><div class="h3 text-dark-blue">Do you want to backup the database?</div></div>';
				$content.='<div class="button-group expanded"><button class="button button-dark-blue loadME" data-ref="'.$this->PERMLINK.'backup_log"><i class="fi-eye"></i> View Log</button>';
				$content.='<button class="button button-olive gotoME" data-ref="'.$this->PERMLINK.'backup_db_now"><i class="fi-check"></i> Yes, do it now!</button></div>';
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;
		if($this->AJAX){
			$this->OUTPUT['content']=renderCard_active($title,$content,$this->SLIM->closer);
		}		
	}
	private function nextOptionID($group=false){
		$next=0;
		if($group){
			$w=['OptionGroup'=>$group];
			$data=$this->SLIM->db->Options()->select("id, OptionID")->where($w)->order('OptionID DESC')->limit(1);
			$data=renderResultsORM($data,'id');
			$data=current($data);
			$next=((int)$data['OptionID'])+1;
		}
		return $next;
	}
	private function saveRecord(){
		$mtype='alert';
		$state=500;
		$id=0;
		$group='application';
		if($post=$this->cleanRequest()){
			$group=issetCheck($post,'OptionGroup',$group);
			$db=$this->SLIM->db->Options;
			if($this->ACTION==='new'){
				unset($post['id']);
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
				$rec=$db->where('id',$id);
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
		return array('status'=>$state,'message'=>$msg,'type'=>$mtype,'id'=>$id,'group'=>$group);			
	}
	private function saveLocation(){
		$mtype='alert';
		$state=500;
		$id=0;
		$group='locations';
		$post=$this->REQUEST;
		unset($post['id'],$post['section'],$post['tbl'],$post['action']);
		if(trim($post['LocationName'])!=='' && trim($post['LocationCountry'])!==''){
			$db=$this->SLIM->db->Locations;
			if($this->ACTION==='add_location'){
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
				$rec=$db->where('LocationID',$id);
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
		return array('status'=>$state,'message'=>$msg,'type'=>$mtype,'id'=>$id,'group'=>$group);			
	}

}
