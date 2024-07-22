<?php

class admin_subscription{
	
	private $SLIM;
	private $PRODUCTS;
	private $SUBSCRIPTIONS;
	private $SUBS_REF;
	private $LANG;
	private $TRANS;
	private $OUTPUT;
	private $DEFAULT_REC=array('ID'=>0,'MemberID'=>0,'ItemID'=>0,'StartDate'=>false,'EndDate'=>null,'Length'=>1,'Paid'=>0,'PaymentDate'=>null,'PaymentRef'=>false,'Status'=>0,'Notes'=>false);
	private $EXCLUDED_MEMBERS;
	private $STATES;
	private $ARGS;
	private $PROD_ID=array('membership'=>[],'ikyf'=>[]);
	private $TEMP_USER;//for admin use
	private $PERMLINK;
	private $PERMBACK;
	private $EDIT_MODE='sales';
	private $DOJOS;
	private $ONE_ROW_PER_ORDER=false;
	private $SUBS_GROUP_ID;
	private $MEMB_CAT_ID;
	
	public $ROUTE;
	public $ADMIN;
	public $LEADER;
	public $PLUG;
	public $AJAX;
	public $REQUEST;
	public $USER;
	public $METHOD;
	public $SECTION;
	public $ACTION;

	function __construct($slim){
		$this->SLIM=$slim;
		$this->TEMP_USER=$slim->TempLogin;
		$this->STATES=$slim->SubscriptionStates;
		$this->LANG=$slim->language->get('_LANG');
		$this->initProducts();
		$this->PERMBACK=URL.'admin/';
		$this->PERMLINK=$this->PERMBACK.'subscription/';
		$this->DOJOS=$slim->Options->get('dojos');
		$this->TRANS['single']=array('en'=>'Seminar + Single Room','fr'=>'Stage chambre simple','de'=>'Seminar + Einzelzimmer');
		$this->TRANS['simpledouble']=array('en'=>'Seminar + Single Room','fr'=>'Stage chambre simple','de'=>'Seminar + Einzelzimmer');
		$this->TRANS['double']=array('en'=>'Seminar + Double Room','fr'=>'Stage chambre double','de'=>'Seminar + Doppelzimmer');
		$this->TRANS['sans-heberg']=array('en'=>'Seminar (No accommodation)','fr'=>'Stage sans héberg','de'=>'Seminar ohne Unterkunft');		
	}
	private function initProducts(){
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
				$this->MEMB_CAT_ID=$i;
				break;
			}
		}
		$prods=$this->SLIM->Options->getSubscriptionProducts('product_types','Subscriptions');
		foreach($prods as $i=>$v){
			$this->PRODUCTS[$i]=$v;
			if($v['ItemCategory']==$this->MEMB_CAT_ID){
				$this->PROD_ID['membership'][$i]=$i;
			}else if(strpos($v['ItemSlug'],'ikyf')!==false){
				$this->PROD_ID['ikyf'][$i]=$i;
			}
		}
	}
	public function getRecords($what=false,$ref=false,$for_email=false,$notify=0){
		$data= $this->getSubscriptions($what,$ref,true);
		$out=false;
		foreach($data as $i=>$v){
			$member=$this->getMember($v['MemberID']);
			if($for_email){
				$product=$this->getProduct($v['ItemID'],'name_price');
				$start=validDate($v['StartDate']);
				$end=validDate($v['EndDate']);
				$chk=(int)$v['Notify'];
				if(!$chk || $chk>$notify){
					$out[$i]=array('name'=>$member['Name'],'email'=>$member['Email'],'subs_id'=>$i,'item'=>$product,'start'=>$start,'end'=>$end,'member_id'=>$v['MemberID'],'language'=>$v['Language']);
				}				
			}else{
				$v['ItemName']=$this->getProduct($v['ItemID'],'name');
				$v['MemberName']=$member['Name'];
				$v['StatusName']=$this->STATES[$v['Status']]['name'];
				$v['StartDate']=validDate($v['StartDate']);
				$v['EndDate']=validDate($v['EndDate']);
				$out[$i]=$v;
			}
		}
		return $out;
	}
	public function getNewRecord(){
		return $this->DEFAULT_REC;
	}
	public function checkSubscription($what=false,$ref=0){
		$SLS=$this->SLIM->Sales;
		if($ref==0){
			//check for temp login
			if($this->TEMP_USER){
				$ref=$this->TEMP_USER['MemberID'];
			}
		}
		$data=$SLS->checkSubscription($what,$ref);
		return $data;
	}
	private function getSubscriptions($what=false,$ref=false,$return=false){
		$data=$this->SLIM->Sales->getSubscriptions($what,$ref,$return);
		if($return) return $data;
		$this->SUBSCRIPTIONS=$data;
	}
	public function saveRecord($id=false,$data=false){
		return $this->saveSubscription($id,false,$data);
	}
	private function saveSubscription($id=false,$rec=false,$data=false){
		$SLS=$this->SLIM->Sales;
		if($data['action']==='add_subscription'){
			$data['action']='add_payment';
			$data['Paid']=toPennies($data['Paid']);
		}
		$res=$SLS->saveRecord($id,$data);
		return $res;
	}
	private function saveSubscriptions($id=false,$rec=false,$data=false){
		$SLS=$this->SLIM->Sales;
		if($data['action']==='add_subscription'){
			$data['action']='add_payment';
			$data['Paid']=0;
		}
		$res=$SLS->saveRecord($id,$data);
		return $res;
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
	private function getMember($id=0,$what=false){
		$out=[];
		if($id){
			$chk=$this->SLIM->db->Members()->select("MemberID, CONCAT(FirstName,' ',LastName) AS Name, CGradeName,Dojo,Email,Language")->where('MemberID',(int)$id);
			$chk=renderResultsORM($chk);
			if($chk){
				$chk=current($chk);
				if($what){
					if($what==='name'){ $what='Name';
						$out=$chk['Name'];
					}else if($what==='name_info'){
						$out=$chk['Name'].' / '.$chk['CGradeName'].' / '.$chk['Dojo'];
					}
				}else{
					$out=$chk;
				}
			}else{
				$out='?? member '.$id.' not found ??';
			}
		}else{
			$get=($what==='all')?'all_members_select':'active_members';
			$chk=$this->SLIM->Options->get($get);
			foreach($chk as $i=>$v){
				if($v['Name'] && trim($v['Name'])!=='') $out[$i]=$v['Name'];
			}
		}
		return $out;
	}
	private function getExcludedMembers(){
		if(!$this->EXCLUDED_MEMBERS){
			$chk=$this->SLIM->db->Members()->select("MemberID, CONCAT(FirstName,' ',LastName) AS Name, CGradeName,Dojo,Email,MemberTypeID,Disable")->where('MemberTypeID',array(5,6,7));
			$chk=renderResultsORM($chk,'MemberID');
			if(!$chk) $chk=array();
			$this->EXCLUDED_MEMBERS=$chk;
		}
		return $this->EXCLUDED_MEMBERS;
	}
	private function getProduct($id=0,$what=false){
		$out=null;
		if($id){
			$chk=issetCheck($this->PRODUCTS,$id);
			if($chk){
				if($what){
					if($what==='name') {
						$out=$chk['ItemTitle'];
					}else if($what==='name_price'){
						$out=$chk['ItemTitle'].' / '.toPounds($chk['ItemPrice']);
					}else{
						$out=issetCheck($chk,$what);	
					}
				}else{
					$out=$chk;
				}
			}
		}else{
			foreach($this->PRODUCTS as $i=>$v){
				$out[$i]=$v['ItemTitle'].' / '.toPounds($v['ItemPrice']);
			}
		}
		return $out;
	}
	private function getState($what=false,$ref=false){
		if(is_numeric($ref)){
			$rec=issetCheck($this->STATES,$ref);
			if($what) return issetChec($rec,$what);
			return $rec;
		}else{
			$id=-1;
			foreach($this->STATES as $i=>$v){
				switch($what){
					case 'name':
					case 'color':
						if($ref===$v[$what]){
							$id=$i;
						}
						break;
				}
				if($id>=0) break;
			}
			if($id<0) $id=0;
			return $id;
		}
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
	private function setVars(){
		switch($this->METHOD){
			case 'POST':
				$this->ACTION=issetCheck($this->REQUEST,'action');
				break;
			default:
				$this->ACTION=issetCheck($this->ROUTE,2);
				$acts=['expired','cancelled','disabled','active','renewed','unpaid'];
				if(in_array($this->ACTION,$acts)) $this->ACTION='subs_'.$this->ACTION;
				$this->ARGS=issetCheck($this->ROUTE,3);
		}			
	}
	public function Process(){
		$this->setVars();
		switch($this->ACTION){
			case 'add_subscription':
			case 'update_subscription':
			case 'subs_renew_expired_now':
				$this->Postman();
				break;
			case 'edit_subscription':
			case 'view_subscription':
				$this->getSubscriptions('id',$this->ARGS);
				$this->renderEditSubscription();
				break;
			case 'edit':
			case 'edit_payment':
				$t=($this->EDIT_MODE==='sales')?'ref':'id';
				$this->getSubscriptions($t,$this->ARGS);
				$this->renderEditPayment();
				break;
			case 'view':
			case 'view_payment':
				$this->getSubscriptions('id',$this->ARGS);
				$this->renderViewPayment();
				break;
			case 'new':
			case 'new_select_products':
				$test = new subscriptions_new($this->SLIM);
				$this->OUTPUT=$test->render($this->ACTION);
				break;
			case 'subs_expired':
			case 'subs_cancelled':
			case 'subs_disabled':
			case 'subs_active':
			case 'subs_renewed':
			case 'subs_unpaid':
				$this->getSubscriptions($this->ACTION);
				if($this->ACTION==='subs_expired'){
					$subs=array();
					$excluded=$this->getExcludedMembers();
					foreach($this->SUBSCRIPTIONS as $i=>$v){
						if(!issetCheck($excluded,$v['MemberID'])) $subs[$i]=$v;
					}
					$this->SUBSCRIPTIONS=$subs;
				}
				$this->renderTable();
				break;
			case 'dojo':
				if(!$this->ARGS || $this->ARGS==='menu'){
					$this->renderDojoMenu();
				}else{
					$this->getSubscriptions('dojo',$this->ARGS);
					$this->renderTable();
				}
				break;
			case 'report':
				$state=($this->ARGS)?str_replace('subs_','',$this->ARGS):'active';
				$this->getSubscriptions($state);
				$this->renderTable();
				break;
			case 'subs_refresh':
				$this->refreshSubscriptions();
				break;
			case 'subs_member':
				$this->getSubscriptions('member',$this->ARGS);
				$this->renderTable();
				break;
			case 'subs_notify':
				$list='notify';
				$ref=0;
				if($this->ARGS==='recipients'){
					$list=issetCheck($this->ROUTE,3,'notify');
				}				
				$this->getSubscriptions($list,$ref);
				$this->renderEmail();
				break;
			case 'member':
				$this->getSubscriptions('member',$this->ARGS);
				$this->renderEditPayment();
				break;
			case 'subs_renew_expired':
				$this->renderRenewExpiredSubscriptions();
				break;
			case 'subs_renewed_pending':
				$this->renderRenewedPending();
				break;
			case 'convert_renewed_pending':
			case 'convert_renewed_pending_now':
				$this->renderRenewedPending_convert();
				break;
			//case 'subs_renew':
			//	$this->getSubscriptions('id',$this->ARGS);
			//	$this->renderRenewSubscription();
			//	break;
			case 'renewal_errors':
				$this->renderRenewalErrors();
				break;
			case 'delete_renewal_errors':
				$this->deleteErrors('bulk_renewals');
				break;
			case 'dev_reset_ankfid':
			case 'dev_reset_ankfid_now':
				$this->dev_resetREG_ID();
				break;
			case 'dashboard':
				$this->renderDashboard();
				break;
			default:
				if(!$this->ACTION) $this->ACTION='subs_active';
				$this->getSubscriptions($this->ACTION);
				$this->renderTable();
		}
		return $this->renderOutput();
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
						case 'title':
							$v=$this->PLUG['label'];
							break;
						default:
							$v='';
					}
					$out[$k]=$v;
				}
			}
		}else if(!$this->OUTPUT||$this->OUTPUT===''){
			$out=[
				'title'=>$this->PLUG['label'],
				'metrics'=>[],
				'content'=>msgHandler('Sorry, no output was generated...',false,false),
				'icon'=>$this->PLUG['icon'],
				'menu'=>['right'=>$this->renderContextMenu()]
			];		
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
		$new_url=$this->PERMLINK.'new';
		$new_load='loadME';
		$email_url='subscriptions/'.$this->ACTION;
		if($r2=issetCheck($this->ROUTE,2)){
			if($r2==='subs_member'){
				if($r3=issetCheck($this->ROUTE,3)){
					$new_url.='/'.$r3;
					$new_load='gotoME';
					$email_url='member/'.$r3;
				}
			}
		}
		$but['back']='<button class="button small button-dark-purple backME" title="back to list" type="button"><i class="fi-arrow-left"></i> Back</button>';
		$but['new']='<button class="button small button-dark-blue '.$new_load.'" title="add a new record" data-ref="'.$new_url.'" type="button"><i class="fi-plus"></i> New</button>';
		$but['save']='<button class="button small button-olive submitME" title="save changes" data-ref="ajaxform" type="button"><i class="fi-check"></i> Update</button>';
		$but['menu']='<button class="button small button-navy loadME" title="lists & actions" data-ref="'.$this->PERMLINK.'dashboard" type="button"><i class="fi-list"></i> Subs. Menu</button>';
		$but['email']='<button class="button small button-navy gotoME" title="email this list" data-ref="'.$this->PERMBACK.'mailer/add/'.$email_url.'" type="button"><i class="fi-mail"></i> Send Email</button>';
		$but['renew']='<button class="button small button-olive submitME" title="renew subscriptions" data-ref="form2" type="button"><i class="fi-check"></i> Renew Now</button>';
		$b=[];$out='';
		switch($this->ACTION){
			case 'edit':
				$b=['back','new','save'];
				break;
			case 'group_summary':
				$b[]='new';
				break;
			case 'edit_record'://viewing invoice
				$b=['back','download','edit','new'];
				break;
			case 'report':
				$b[]='back';
				$b[]='menu';				
				break;
			case 'subs_renew_expired':
				$b[]='back';
				$b[]='menu';
				$b[]='renew';				
				break;
			default:
				$b[]='menu';
				if($this->ADMIN){
					$b[]='email';
					$b[]='new';
				}
		}
		if($b){
			foreach($b as $i){
				$out.='<li>'.$but[$i].'</li>';
			}
		}
		return $out;
	}
	private function refreshSubscriptions(){
		$date=date('Y-m-d');
		$new_state=2;
		$data=$this->getSubscriptions('expire_<',$date,$new_state);
		$ct=(is_array($data))?count($data):0;
		$msg=($ct>0)?'Okay, '.$ct.' records(s) have been updated.':'Okay, all records are up to date.';
		$url=$this->PERMLINK;
		setSystemResponse($url,$msg);
		die('oops!');
	}
	private function dev_resetREG_ID(){
		//resets the ANKF/IKF ID
		$recs=$this->SLIM->db->Sales->select('ID,MemberID,ItemID,EndDate,Status')->where('ItemID',[45,23])->order('Status ASC, EndDate DESC');
		$ct=0;
		$msg='No Records Found...';			
		if($recs=renderResultsORM($recs)){
			$mid=$log=[];
			$today=date('Y-m-d 00:00:00');
			foreach($recs as $rec){
				if($rec['Status']==0) continue;
				if($rec['Status']==1){
					$log[$rec['MemberID']]=$rec['MemberID'];
				}else if($rec['Status']==2){
					if($rec['EndDate'] <  $today){
						if(!isset($log[$rec['MemberID']])){
							$mid[$rec['MemberID']]=$rec['MemberID'];
							$log[$rec['MemberID']]=$rec['MemberID'];
						}
					}
				}
			}
			if($mid){//update members record
				$members=$this->SLIM->db->Members->where('MemberID',array_keys($mid));
				$ct=count($members);
				if($this->ACTION==='dev_reset_ankfid_now'){
					if($ct>0){
						$members->update(['AnkfID'=>0]);
					}
					$msg='Okay, '.$ct.' Member AnkfID(s) have been reset.';
				}else{
					if($ct>0){
						$members->select('MemberID,FirstName,LastName,AnkfID');
						$members=renderResultsORM($members);
						preME($members);
						$msg='Okay, '.$ct.' Member AnkfID(s) will be reset.<br/>';
						$msg.='<a href="'.$this->PERMLINK.'dev_reset_ankfid_now">Reset Now</a>';
					}					
				}
			}
		}
		die($msg);
	}
	private function renderEmail(){
		$list=issetCheck($this->ROUTE,3);
		$notify=0;
		if($list){
			$tmp=explode('_',$list);
			$notify=($tmp[1]==='next')?(int)$tmp[2]:(int)$tmp[1];
		}		
		$data=$this->renderEmailData($notify);
		$this->SLIM->Recipients->add($data,true);
		$this->SLIM->Email->PERMBACK=$this->PERMLINK.'subs_notify/';
		$this->SLIM->Email->MODE='subscription';
		$this->SLIM->Email->ARGS=array($list,$notify);
		$action=issetCheck($this->ROUTE,2,'writer');
		$output=$this->SLIM->Email->render($action);
		$this->OUTPUT['title']=$this->SLIM->Email->TITLE;
		$this->OUTPUT['content'].=$output;	
	}
	private function renderEmailData($notify=0){
		$out=[];
		foreach($this->SUBSCRIPTIONS as $i=>$v){
			$member=$this->getMember($v['MemberID']);
			$product=$this->getProduct($v['ItemID'],'name_price');
			$start=validDate($v['StartDate']);
			$end=validDate($v['EndDate']);
			$chk=(int)$v['Notify'];
			if(!$chk || $chk>$notify){
				$out[$i]=array('name'=>$member['Name'],'email'=>$member['Email'],'subs_id'=>$i,'item'=>$product,'start'=>$start,'end'=>$end);
			}
		}
		return $out;
	}	
	private function renderDisplayData(){
		$out=$parts=[];
		foreach($this->SUBSCRIPTIONS as $i=>$v){
			if(!$parts) $parts=array_keys($v);
			foreach($parts as $p){
				switch($p){
					case 'MemberID':
						$v['MemberName']=$this->getMember($v[$p],'name');
						break;
					case 'ItemID':
						$v['ItemName']=$this->getProduct($v[$p],'name_price');
						break;
					case 'StartDate':case 'EndDate':
						$v[$p]=validDate($v[$p]);
						break;
					case 'Status':
						$cat=$this->STATES[$v[$p]]['name'];
						$col=$this->STATES[$v[$p]]['color'];
						$v['StatusName']='<span class="text-'.$col.'">'.$cat.'</span>';
						break;
					case 'Paid':
						$v[$p]=((int)$v[$p])?toPounds($v[$p]):0;
						break;
				}
			}
			$out[$i]=$v;
		}			
		return $out;
	}
	private function renderReportTable($thead,$trows){
		$args['headers']=implode('',$thead);
		$args['rows']='<tr>'.implode('</tr><tr>',$trows).'</tr>';
		$args['selector']=$this->renderReportSelector();
		$args['sorter']=false;
		$args['controls']='<button class="button button-navy gotoME" data-ref="'.$this->PERMLINK.'"><i class="fi-arrow-left"></i> Subscriptions</button>';
		$tpl=file_get_contents(TEMPLATES.'app/app.report_grid.html');
		$table=replaceMe($args,$tpl);
		$this->SLIM->assets->set('scripts','assets/js/admin/ui_mgrid.min.js','mgrid');
		$this->SLIM->assets->set('js','JQD.ext.initMGrid("report_mgrid");','init_mgrid');
		$this->OUTPUT['title']='Subscriptions (<span class="text-dark-blue">Download</span>)';
		$this->OUTPUT['content']=$table;
	}
	private function renderReportSelector(){
		$report=$this->ARGS;
		$o='';
		if(trim($report)==='') $report='subs_active';
		$opts=array(
			'subs_active'=>'Active Subscriptions',
			'subs_expired'=>'Expired Subscriptions',
			'subs_cancelled'=>'Cancelled Subscriptions',
			'subs_disabled'=>'Disabled Subscriptions',
		);
		if(!array_key_exists($report,$opts)) $report='subs_active';
		foreach($opts as $i=>$v){
			$s=($i===$report)?'selected':'';
			$o.='<option value="'.$i.'" '.$s.'>'.$v.'</option>';
		}
		$this->SLIM->assets->set('js','$("#report_list").on("change",function(){var v=$(this).val(); var u="'.$this->PERMLINK.'report/"+v; JQD.utils.setLocation(u);});','select_me');
		return '<div class="input-group"><div class="input-group-label">Reports</div><select class="input-group-field" id="report_list">'.$o.'</select></div>';
	}

	private function renderTable(){
		$row=$thead=[];
		$menu=$controls='';
		$parts=array('ID'=>'int','MemberID'=>'string','ItemID'=>'string','StartDate'=>'string','EndDate'=>'string','Paid'=>'string','Status'=>'string','Controls'=>false);
		$new_label='Subscription';
		$view_url=($this->EDIT_MODE==='sales')?'view_payment/':'view/';
		$edit_url=($this->EDIT_MODE==='sales')?'edit_payment/':'edit/';
		$subsc=($this->SUBSCRIPTIONS)?$this->SUBSCRIPTIONS:[];
		foreach($subsc as $i=>$v){
			$td=[];
			foreach($parts as $p=>$sk){
				$k=$p;
				if(!isset($thead[$k])){
					$sk=($sk)?'data-sort="'.$sk.'"':'';
					$thead[$k]='<th '.$sk.'>'.camelTo($p).'</th>';
				}
				switch($p){
					case 'Controls':
						if($this->ACTION!=='report'){
							if($this->ACTION==='subs_notify'){
								$box='<div class="checkboxTick"><input id="tick_'.$i.'" type="checkbox" name="send['.$i.']" checked/><label for="tick_'.$i.'"></label></div>';
								$td[$k]='<td>'.$box.'</td>';
							}else{
								$txt='<i class="fi-eye"></i> View';
								$url_txt=$view_url;
								$cls='';
								if($this->USER['access']>=25){
										$txt='<i class="fi-pencil"></i> Edit';
										$url_txt=$edit_url;
										$cls='button-dark-purple';
										if($this->EDIT_MODE==='sales') $i=$v['Ref'];
								}else if($this->USER['access']>=20){									
									if(hasAccess($this->USER,'events','update')){
										$txt='<i class="fi-pencil"></i> Edit';
										$url_txt=$edit_url;
										$cls='button-purple';
									}
								}
								$td[$k]='<td><button class="button small loadME '.$cls.'" data-ref="'.$this->PERMLINK.$url_txt.$i.'">'.$txt.'</button></td>';
							}
						}
						break;
					case 'MemberID':
						$cat=$this->getMember($v[$p],'name');
						if($this->ACTION==='report'){
							$td[$k]='<td>'.$cat.'</td>';
						}else{	
							$td[$k]='<td><a class="link-dark-blue loadME" href="'.$this->PERMBACK.'member/view/'.$v[$p].'">'.$cat.'</a></td>';
						}
						break;
					case 'ItemID':
						$cat=$this->getProduct($v[$p],'name');
						$td[$k]='<td>'.$cat.'</td>';
						break;
					case 'StartDate':case 'EndDate':
						$cat=validDate($v[$p]);
						$tm=strtotime($cat);
						$td[$k]='<td data-sort-value="'.$tm.'">'.$cat.'</td>';
						break;
					case 'Status':
						$cat=$this->STATES[$v[$p]]['name'];
						$col=$this->STATES[$v[$p]]['color'];
						$td[$k]='<td><span class="text-'.$col.'">'.ucME($cat).'</span></td>';
						break;
					case 'Paid':
						$cat=((int)$v[$p])?toPounds($v[$p]):'<span class="text-red">No</span>';
						$td[$k]='<td>'.$cat.'</td>';
						break;
					default:
						$td[$k]='<td>'.$v[$p].'</td>';
				}
			}			
			if($td){
				if($this->ONE_ROW_PER_ORDER){
					$row[$i]=implode('',$td);
				}else{
					$row[]=implode('',$td);
				}
			}			
		}
		if($this->ACTION==='report'){
			unset($thead['Controls']);
			return $this->renderReportTable($thead,$row);
		}else{
			$thead=implode('',$thead);
			$rx=[];
			foreach($row as $i=>$v) $rx[$i]='<tr>'.$v.'</tr>';
			$table=renderDataTable($thead,$rx);
		}
		if($this->USER['access']>=25){
			$nurl=$this->PERMLINK.'new_subscription';
			if($this->ROUTE[1]==='subs_member' && (int)$this->ROUTE[2]>0){
				$nurl.='/?u='.(int)$this->ROUTE[2];
			}
			$controls='<button title="send an email to this list" class="small button button-dark-blue gotoME" data-ref="'.$this->PERMBACK.'mailer/add/subscriptions/'.$this->ACTION.'"><i class="fi-mail"></i> Send Email</button>';
			$controls.='<button class="button button-olive loadME" data-ref="'.$nurl.'" title="add a new subscription"><i class="fi-plus"></i> New Subscription</button>';
			if($this->ACTION==='subs_renewed_pending'){
				$nurl=$this->PERMLINK.'convert_renewed_pending/';
				$controls.='<button class="button button-aqua loadME" data-ref="'.$nurl.'" title="activate all subscriptions"><i class="fi-play"></i> Activate?</button>';
			}
		}
		$content=$table;
		if($controls!==''){
			$controls='<div class="button-group float-right small">'.$controls.'</div>';
		}
		$title=ucME(str_replace('subs_','',$this->ACTION));
		if($this->ACTION==='dojo' && $this->ARGS && $this->ARGS!=='menu') $title.=' - '.$this->DOJOS[$this->ARGS]['LocationName'];
		$this->OUTPUT['title']='Subscriptions (<span class="text-dark-blue">'.$title.'</span>)';
		$CC=issetCheck($this->OUTPUT,'content');
		$this->OUTPUT['content']=$CC.$menu.$content;		
	}
	private function renderEditSubscription(){
		$tpl=TEMPLATES.'app/app.form_view_ajax.html';
		if(!$this->SUBS_REF) $this->SUBS_REF=$this->ARGS;
		switch($this->ACTION){
			case 'edit_subscription':
				unset($this->STATES[5]);
				if($this->USER['access']>=25){
					$faction='update_subscription';
					$button='<i class="fi-check"></i> Update Subscription';
					$title='Edit Subscription: <span class="subheader">#'.$this->SUBS_REF.'</span>';
					$tpl=TEMPLATES.'app/app.form_edit_ajax.html';
				}else{
					$faction='do_nothing';
					$button='<i class="fi-x-circle"></i> close';
					$title='View Subscription: <span class="subheader">#'.$this->SUBS_REF.'</span>';
				}
				break;
			default:
				$faction='do_nothing';
				$button='<i class="fi-x-circle"></i> close';
				$title='View Subscription: <span class="subheader">#'.$this->SUBS_REF.'</span>';
		}
		//form fields
		$hidden='';
		$parts['Details']=array('MemberID','ItemID','StartDate','EndDate','Length','Paid','PaymentDate','PaymentRef','Status');
		$parts['Notes']=array('Notes');		
		$parts=$this->renderTabs($parts);
		if($this->AJAX) $parts='<div class="modal-body">'.$parts.'</div>';
		$tpl=file_get_contents($tpl);
		$args=array(
			'form_url'=>$this->PERMLINK,
			'form_action'=>$faction,
			'form_parts'=>$hidden.$parts,
			'form_button'=>$button,
			'id'=>$this->SUBS_REF
		);
		$out=replaceMe($args,$tpl);
		if($this->AJAX){
			echo renderCard_active($title,$out,$this->SLIM->closer);
			die;
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$out;			
	}
	private function renderEditPayment(){
		if(!$this->SUBS_REF){
			if($this->SUBSCRIPTIONS){
				$this->SUBS_REF=key($this->SUBSCRIPTIONS);
				if($this->EDIT_MODE==='sales') $this->SUBS_REF=$this->SUBSCRIPTIONS[$this->SUBS_REF]['Ref'];
			}
		}
		$tpl=TEMPLATES.'app/app.form_edit_ajax.html';
		if($this->SUBS_REF==='new'){
			$faction='add_subscription';
			$button='<i class="fi-plus"></i> Add Subscription';
			$title='New Subscription';
		}else{
			$act=($this->USER['access']>=25)?'edit_payment':'view_payment';
			$out=$this->SLIM->SalesMan->render($act,$this->SUBS_REF);
		}
		//form fields
		$hidden='';
		$parts['Details']=array('MemberID','ItemID','StartDate','EndDate','Length','Paid','PaymentDate','PaymentRef','Status');
		$parts['Notes']=array('Notes');		
		$parts=$this->renderTabs($parts);
		if($this->AJAX) $parts='<div class="modal-body">'.$parts.'</div>';
		$tpl=file_get_contents($tpl);
		$args=array(
			'form_url'=>$this->PERMLINK,
			'form_action'=>$faction,
			'form_parts'=>$hidden.$parts,
			'form_button'=>$button,
			'id'=>$this->SUBS_REF
		);
		$out=replaceMe($args,$tpl);
		if($this->AJAX){
			echo renderCard_active($title,$out,$this->SLIM->closer);
			die;
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$out;	
	}
	
	private function renderRenewedPending(){
		$this->getSubscriptions('status',8);
		$this->renderTable();
	}
	private function renderRenewedPending_convert($convert=false){
		$out='';
		$title='Activate Pending Subscriptions';
		if(!$convert){
			if($this->ACTION==='convert_renewed_pending_now') $convert=true;
		}
		if($convert){
			$this->getSubscriptions('status',8);
			$note='<p>Activated on '.date('Y-m-d').' by '.$this->USER['name'].'</p>';
			$state=1;
			$ct=0;
			foreach($this->SUBSCRIPTIONS as $i=>$v){
				$v['Notes'].=$note;
				$v['Status']=$state;
				$v['action']='update_subscription';
				unset($v['ID']);
				$chk=$this->saveRecord($i,$v);
				if($chk['status']==200) $ct++;
			}
			$msg=($ct>0)?'Okay, '.$ct.' record(s) have been activated':'Sorry, no pending subscriptions found...';
			setSystemResponse($this->PERMLINK,$msg);
			die;			
		}else{
			$content='<div class="callout primary"><p>Do you want to activate the pending subscriptions?<br/><small class="text-dark-blue">This will only make changes to the status</small></p></div>';
			$content.='<div class="button-group expanded">
<button class="button secondary" data-close ><i class="fi-x"></i> Cancel</button>				
<button class="button button-olive gotoME" data-ref="'.$this->PERMLINK.'convert_renewed_pending_now" ><i class="fi-check"></i> Activate Now</button>				
			</div>';
			$out=renderCard_active($title,$content,$this->SLIM->closer);
		}
		if($this->AJAX){
			echo $out;
			die;
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$out;	
	}
	private function renderRenewExpiredSubscriptions($renew_this=false){
		$this->getSubscriptions('notify',0);
		$title='Renew Expired Membership Subscriptions';
		$note='Renewed from #';
		$def=$this->getNewRecord();
		$def['Status']=8;
		$renewals=$update=$thead=$trow=array();
		$members=array(22,46,51);
		$excluded=$this->getExcludedMembers();
		$content='';
		foreach($this->SUBSCRIPTIONS as $i=>$v){
			if(in_array($v['ItemID'],$members)){// make sure its a subscription item
				$is_excluded=issetCheck($excluded,$v['MemberID']);
				if(!$is_excluded){//check if member is excluded from renewals
					$start=strtotime($v['EndDate'].' + 1 day');
					$_s=date('Y-m-d',$start);
					$end=strtotime($_s.' + 365 days');
					$_e=date('Y-m-d',$end);
					$new=$def;
					$new['MemberID']=$v['MemberID'];
					$new['ItemID']=$v['ItemID'];
					$new['Notes']='<p>'.$note.$i.' ('.$v['Ref'].')</p>';
					$new['StartDate']=$_s;
					$new['EndDate']=$_e;
					if(is_array($renew_this)){
						if(isset($renew_this[$new['MemberID']])){
							$update[$v['MemberID']]=$i;
							$renewals[$v['MemberID']]=$new;
						}
					}else{
						$update[$v['MemberID']]=$i;
						$renewals[$v['MemberID']]=$new;
					}
				}
			}
		}
		if($renew_this){
			$err=array();
			$ok=0;
			$note='<p>Renewal record made on '.date('Y-m-d').' by '.$this->USER['name'].'</p>';
			foreach($renewals as $i=>$v){
				$v['action']='add_payment';
				$save=$this->saveRecord(0,$v);
				if($save['status']==200){
					$ok++;
					$this->setSubscriptionStatus($update[$i],4,$note);
				}else{
					$v['error']=$save['message'];
					$err[$i]=$v;
				}				
			}
			$m=[];
			if($ok){
				$z="<strong>$ok</strong> subscriptions have been renewed.";
				$content.=msgHandler("<strong>$ok</strong> subscriptions have been renewed.",'success',false);
				$m[]=$z;
			}
			if($err){
				$this->saveErrors('bulk_renewals',$err);	
				$z='<strong>'.count($err).'</strong> subscriptions could not be renewed.<br/><a class="button" href="'.$this->PERMLINK.'renewal_errors">View Details</a>';
				$content.=msgHandler($z,'alert',false);
				$m[]=$z;
			}
			if($m) return implode('<br/>',$m);			
		}else{//render table
			$help='<p>Use this page to create new subscription records from those which have recently expired.<br/>The new subscriptions are listed below. Uncheck any items that you <strong>do not want included</strong>.<br/>Click the "Renew Now" button to start the process.</p>';
			$parts=array('ID'=>'int','MemberID'=>'string','ItemID'=>'string','StartDate'=>'date','EndDate'=>'date','Controls'=>false);
			foreach($renewals as $i=>$v){
				$td=[];
				foreach($parts as $p=>$sk){
					$k=$p;
					if(!isset($thead[$k])){
						$sk=($sk)?'data-sort="'.$sk.'"':'';
						$thead[$k]='<th '.$sk.'>'.camelTo($p).'</th>';
					}
					switch($k){
						case 'MemberID':
							$val=$this->getMember($v[$p],'name');
							$td[$k]='<td>'.$val.'</td>';
							break;
						case 'ItemID':
							$val=$this->getProduct($v[$p],'name');
							$td[$k]='<td>'.$val.'</td>';
							break;
						case 'Controls':
							$c='<div class="checkboxTick"><input id="tick_'.$i.'" type="checkbox" name="renewals['.$i.']" checked/><label for="tick_'.$i.'"></label></div>';
							$td[$k]='<td>'.$c.'</td>';
							break;
						case 'Status':
							$td[$k]='<td>'.ucME($v[$p]).'</td>';
							break;
						default:
							$td[$k]='<td>'.$v[$p].'</td>';
					}
				}
				if($td) $trow[$i]=implode('',$td);
			}			
			$filter=msgHandler($help,false,false);
			$filter.='<div id="filter">'.$this->SLIM->zurb->inlineLabel('Filter','<input id="dfilter" class="input-group-field" type="text"/>');
			$filter.='<div class="metrics">'.(count($renewals)).' Record(s)</div></div>';
			$table='<table id="dataTable" class="row_hilight"><thead><tr>'.implode('',$thead).'</tr></thead><tbody><tr>'.implode('</tr><tr>',$trow).'</tr></tbody></table>';
			if($this->USER['access']>=25){
				$etest=$this->loadErrors('bulk_renewals');
				$controls='<button title="send an email to this list" class="small button button-dark-blue gotoME" data-ref="'.$this->PERMBACK.'mailer/add/subscriptions/subs_expired"><i class="fi-mail"></i> Send Email</button>';
				$controls.='<button class="button button-olive submitME" data-ref="form2" ><i class="fi-check"></i> Renew Now</button>';
				if($etest){
					$controls.='<button title="view renewal errors" class="small button button-maroon gotoME" data-ref="'.$this->PERMLINK.'renewal_errors"><i class="fi-alert"></i> Renewal Errors ('.count($etest).')</button>';
				}
			}
			if($controls){
				$controls='<div class="button-group float-right small">'.$controls.'</div>';
				$this->SLIM->topbar->setInfoBarControls('right',array($controls),true);
			}
			$content=$filter.'<form id="form2" method="post" action="'.$this->PERMLINK.'"><input type="hidden" name="action" value="subs_renew_expired_now"/><div class="tablewrap medium">'.$table.'</div></form>';
			$this->SLIM->assets->set('js','JQD.ext.initMyTable("#dfilter","#dataTable");','my_table');
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;	
	}
	private function renderRenewalErrors(){
		$data=$this->loadErrors('bulk_renewals');
		if($data){
			$help='<p>The items below could not be processed.<br/>Refer to the "error" column for more info.<br/>You can delete the error log by clicking the "delete Errors" button.</p>';
			$parts=array('ID'=>'int','MemberID'=>'string','ItemID'=>'string','StartDate'=>'date','EndDate'=>'date','error'=>false);
			foreach($data as $i=>$v){
				$td=[];
				foreach($parts as $p=>$sk){
					$k=$p;
					if(!isset($thead[$k])){
						$sk=($sk)?'data-sort="'.$sk.'"':'';
						$thead[$k]='<th '.$sk.'>'.camelTo($p).'</th>';
					}
					switch($k){
						case 'MemberID':
							$val=$this->getMember($v[$p],'name');
							$td[$k]='<td>'.$val.'</td>';
							break;
						case 'ItemID':
							$val=$this->getProduct($v[$p],'name');
							$td[$k]='<td>'.$val.'</td>';
							break;
						case 'Status':
							$td[$k]='<td>'.ucME($v[$p]).'</td>';
							break;
						case 'Controls':
							$c='<div class="checkboxTick"><input id="tick_'.$i.'" type="checkbox" name="renewals['.$i.']" checked/><label for="tick_'.$i.'"></label></div>';
							$td[$k]='<td>'.$c.'</td>';
							break;
						case 'error':
							$td[$k]='<td><small class="text-maroon">'.$v[$p].'</small></td>';
							break;
						default:
							$td[$k]='<td>'.$v[$p].'</td>';
					}
				}
				if($td) $trow[$i]=implode('',$td);
			}			
			$filter=msgHandler($help,'warning',false);
			$filter.='<div id="filter">'.$this->SLIM->zurb->inlineLabel('Filter','<input id="dfilter" class="input-group-field" type="text"/>');
			$filter.='<div class="metrics">'.(count($data)).' Record(s)</div></div>';
			$table='<table id="dataTable" class="row_hilight"><thead><tr>'.implode('',$thead).'</tr></thead><tbody><tr>'.implode('</tr><tr>',$trow).'</tr></tbody></table>';
			if($this->USER['access']>=25){
				$controls='<button title="delete the error log" class="small button button-red gotoME" data-ref="'.$this->PERMLINK.'delete_renewal_errors"><i class="fi-x"></i> Delete Errors</button>';
			}
			if($controls){
				$controls='<div class="button-group float-right small">'.$controls.'</div>';
				$this->SLIM->topbar->setInfoBarControls('right',array($controls),true);
			}
			
			$content=$filter.'<form id="form2" method="post" action="'.$this->PERMLINK.'"><input type="hidden" name="action" value="subs_renew_expired_now"/><div class="tablewrap medium">'.$table.'</div></form>';
			$this->SLIM->assets->set('js','JQD.ext.initMyTable("#dfilter","#dataTable");','my_table');
		}else{
			$content=msgHandler('No error file found...',false,false);
		}
		$this->OUTPUT['title']='Renewal Errors';
		$this->OUTPUT['content']=$content;	
	}
	private function setSubscriptionStatus($id=0,$state=0,$note=false){
		if($id){
			$rec=$this->getSubscriptions('id',$id,true);
			if($rec){
				$rec=current($rec);
				$rec['Notes'].=$note;
				$rec['Status']=$state;
				$rec['action']='update_subscription';
				unset($rec['ID']);
				$chk=$this->saveRecord($id,$rec);
				return true;
			}
		}
		return false;
	}
	private function saveErrors($name=false,$data=false){
		if($name && is_array($data)){
			$data=json_encode($data);
			$path=CACHE.'errors_'.$name.'.json';
			file_put_contents($path,$data);
		}
	}
	private function loadErrors($name){
		$errs=array();
		if($name){
			$path=CACHE.'errors_'.$name.'.json';
			if(file_exists($path)){
				$t=file_get_contents($path);
				if($t) $errs=json_decode($t,true);
			}
		}
		return $errs;
	}
	private function deleteErrors($name){
		$u=$this->PERMLINK;
		$test=false;
		if($name){
			$path=CACHE.'errors_'.$name.'.json';
			if(file_exists($path)){
				$test=unlink($path);
			}
		}
		$msg=($test)?'Okay, the renewal errors have been deleted':'Okay, but I could not find any renewal errors...';
		setSystemResponse($u,$msg);
		die;
	}
	private function renderEditPayment_old(){
		if($this->SUBS_REF==='new'){
			$faction='add_subscription';
			$button='<i class="fi-plus"></i> Add Subscription';
			$title='New Subscription';
			$tpl=APP.'templates/app.form_lang_standards_ajax.html';
		}else{
			if($this->USER['access']>=25){
				$faction='update_subscription';
				$button='<i class="fi-check"></i> Update Subscription';
				$title='Edit Subscription: <span class="subheader">#'.$this->SUBS_REF.'</span>';
				$tpl=APP.'templates/app.form_lang_standards_ajax.html';
			}else{
				$faction='do_nothing';
				$button='<i class="fi-x-circle"></i> close';
				$title='View Subscription: <span class="subheader">#'.$this->SUBS_REF.'</span>';
				$tpl=APP.'templates/app.form_view_ajax.html';
			}
		}
		//form fields
		$hidden='';
		$parts['Details']=array('MemberID','ItemID','StartDate','EndDate','Length','Paid','PaymentDate','PaymentRef','Status');
		$parts['Notes']=array('Notes');		
		$parts=$this->renderTabs($parts);
		if($this->AJAX) $parts='<div class="modal-body">'.$parts.'</div>';
		$tpl=file_get_contents($tpl);
		$args=array(
			'form_url'=>$this->PERMLINK,
			'form_action'=>$faction,
			'form_parts'=>$hidden.$parts,
			'form_button'=>$button,
			'id'=>$this->SUBS_REF
		);
		$out=replaceMe($args,$tpl);
		if($this->AJAX){
			echo renderCard_active($title,$out,$this->SLIM->closer);
			die;
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$out;	
	}
	private function renderViewPayment(){
		$ref=($this->SUBS_REF)?$this->SUBS_REF:$this->ARGS;
		$faction='do_nothing';
		$button='<i class="fi-x-circle"></i> close';
		$title='View Subscription: <span class="subheader">#'.$ref.'</span>';
		$tpl=TEMPLATES.'app/app.form_view_ajax.html';

		//form fields
		$hidden='';
		$parts['Details']=array('MemberID','ItemID','StartDate','EndDate','Length','Paid','PaymentDate','PaymentRef','Status');
		$parts['Notes']=array('Notes');		
		$parts=$this->renderTabs_view($parts);
		if($this->AJAX) $parts='<div class="modal-body">'.$parts.'</div>';
		$tpl=file_get_contents($tpl);
		$args=array(
			'form_parts'=>$hidden.$parts,
			'form_button'=>$button,
			'id'=>$ref
		);
		$out=replaceMe($args,$tpl);
		if($this->AJAX){
			echo renderCard_active($title,$out,$this->SLIM->closer);
			die;
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$out;	
	}
	private function renderTabs_view($data){
		$nav=$panels='';
		$active='is-active';
		$tab_id='subscriptions-edit';
		$ct=0;
		$row_tpl='<tr><td class="text-dark-blue">{label}</td><td>{content}</td></tr>';
		$product=current($this->SUBSCRIPTIONS);
		foreach($data as $i=>$v){
			$tmp='';
			$nav.='<li class="tabs-title '.$active.'"><a href="#panel_'.$ct.'" aria-selected="'.$active.'">'.$i.'</a></li>';
			foreach($v as $x){
				$k=$x;
				switch($x){
					case 'Paid':
						$price=$this->getProduct($product[$x],'ItemPrice');
						$cls=($product[$x]<$price)?'maroon':'olive';						
						$parts=array(
							'label'=>'Amount Paid',
							'content'=>'<span class="text-'.$cls.'">'.toPounds($product[$x]).'</span>'
						);
						$tmp.=replaceMe($parts,$row_tpl);
						break;
					case 'MemberID':
						$parts=array(
							'label'=>'Member',
							'content'=>$this->getMember($product[$x],'name_info')
						);
						$tmp.=replaceMe($parts,$row_tpl);
						break;				
					case 'ItemID':
						$parts=array(
							'label'=>'Subscription Item',
							'content'=>$this->getProduct($product[$x],'name_price')
						);
						$tmp.=replaceMe($parts,$row_tpl);
						break;				
					case 'Status':
						$cls=$this->STATES[$product[$x]]['color'];
						$parts=array(
							'label'=>$k,
							'content'=>'<span class="text-'.$cls.'">'.$this->STATES[$product[$x]]['name'].'</span>'
						);
						$tmp.=replaceMe($parts,$row_tpl);
						break;				
					case 'StartDate':case 'EndDate':case 'PaymentDate':
						$parts=array(
							'label'=>camelTo($x),
							'content'=>validDate($product[$x])
						);
						$tmp.=replaceMe($parts,$row_tpl);
						break;
					case 'Notes':
						$parts=array(
							'label'=>camelTo($x),
							'content'=>$product[$x]
						);
						$tpl='<tr><td class="text-dark-blue">{label}</td></tr><tr><td><div class="callout">{content}</div></td></tr>';
						$tmp.=replaceMe($parts,$tpl);
						break;
					default:
						$parts=array(
							'label'=>camelTo($x),
							'content'=>$product[$x]
						);
						$tmp.=replaceMe($parts,$row_tpl);
				}
			}			
			$panels.='<div class="tabs-panel '.$active.'" id="panel_'.$ct.'"><div class="tablescroll"><table>'.$tmp.'</table></div></div>';
			$active='';
			$ct++;
		}
		$tabs='<ul class="tabs" data-tabs id="'.$tab_id.'-tabs">'.$nav.'</ul><div class="tabs-content" data-tabs-content="'.$tab_id.'-tabs">'.$panels.'</div>';
		if($this->AJAX) $tabs.='<script>jQuery("#'.$tab_id.'-tabs").foundation();JQD.ext.initEditor(".modal-body .qedit");</script>';
		return $tabs;
	}
	private function renderTabs($data){
		$nav=$panels='';
		$active='is-active';
		$tab_id='subscriptions-edit';
		$ct=0;
		$mbu=(int)issetCheck($_GET,'u');
		$product=current($this->SUBSCRIPTIONS);
		foreach($data as $i=>$v){
			$tmp='';
			$nav.='<li class="tabs-title '.$active.'"><a href="#panel_'.$ct.'" aria-selected="'.$active.'">'.$i.'</a></li>';
			foreach($v as $x){
				$k=$x;
				switch($x){
					case 'Paid':
						$tmp.='<label>Amount Paid: <em class="text-dark-blue">The Format must be "0.00"</em><input type="text" name="'.$x.'" value="'.toPounds($product[$x]).'"/></label>';
						break;
					case 'MemberID':
						if($this->SUBS_REF==='new'){
							$cats=$this->getMember(0,'all');
							$opts='<option value="0">Select a member</option>';
							foreach($cats as $ci=>$cv){
								$sel='';
								if($mbu){
									$sel=($mbu==$ci)?'selected':'';
								}
								$opts.='<option value="'.$ci.'" '.$sel.'>'.ucwords($cv).'</option>';
							}
							$tmp.='<label>Member: <select name="'.$x.'">'.$opts.'</select></label>';
						}else{
							$cat=$this->getMember($product[$x],'name_info');
							$tmp.='<label>Member: <span class="faux-input">'.$cat.'</span></label>';
						}
						break;				
					case 'ItemID':
						if($this->SUBS_REF==='new'){
							$cats=$this->getProduct();
							$opts='<option value="0">Select a product</option>';
							foreach($cats as $ci=>$cv){
								$opts.='<option value="'.$ci.'">'.ucwords($cv).'</option>';
							}
							$tmp.='<label>Subscription Item:: <select name="'.$x.'">'.$opts.'</select></label>';
						}else{
							$cat=$this->getProduct($product[$x],'name_price');
							$tmp.='<label>Subscription Item: <span class="faux-input">'.$cat.'</span></label>';
						}
						break;				
					case 'Status':
						$sel=($product[$x]==='active')?'selected':'';
						$opts='';
						foreach($this->STATES as $ci=>$cv){
							$sel=($product[$x]==$ci)?'selected':'';
							$opts.='<option value="'.$ci.'" '.$sel.'>'.ucwords($cv['name']).'</option>';
						}
						$tmp.='<label>'.$k.': <select name="'.$x.'">'.$opts.'</select></label>';
						break;				
					case 'StartDate':case 'EndDate':case 'PaymentDate':
						$tmp.='<label>'.camelTo($x).'<input type="date" name="'.$x.'" value="'.validDate($product[$x]).'"/></label>';
						break;
					case 'Notes':
						$tmp.='<label>'.camelTo($x).'<textarea rows="15" name="'.$x.'" >'.$product[$x].'</textarea></label>';
						break;
					default:
						$tmp.='<label>'.camelTo($x).'<input type="text" name="'.$x.'" value="'.$product[$x].'"/></label>';
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
	private function getExpiredCount(){
		$subs=$this->getSubscriptions('subs_expired',false,true);
		$exluded=$this->getExcludedMembers();
		$ct=0;
		foreach($subs as $i=>$v){
			if(!issetCheck($exluded,$v['MemberID'])) $ct++;
		}
		return $ct;
	}
	private function renderDashboard($what=false){
		$_data=['lists'=>[],'actions'=>[]];
		$counters=array(0,1,2,3,8);
		$colors=['disabled'=>'gray','active'=>'green','expired'=>'maroon','unpaid'=>'dark-blue'];
		if(!$what||$what==='active') $what='subs_active';
		$stats=$this->getSubscriptions('status_summary_subs',false,true);
		$errs=$this->loadErrors('bulk_renewals');
		foreach($this->STATES as $i=>$v){
			if(in_array($i,$counters)){
				foreach($stats as $stat){
					if((int)$stat['Status']==$i){
						if($i==2){
							$dx=$this->getExpiredCount();
							$count=($dx)?$dx:0;
						}else{
							$count=($stat)?$stat['Cnt']:0;
					    }
						$label=ucME($v['name']);
						if($count>0) $_data['lists']['subs_'.$v['name']]=array('color'=>$colors[$v['name']],'caption'=>$label.'<br/>Subscriptions','content'=>'view all '.$v['name'].' subscriptions','href'=>$this->PERMLINK.$v['name'],'icon'=>$count);
					}
				}
			}
		}
		$stat_unpaid=$this->getSubscriptions('subs_unpaid',true,true);
		$stat_30=$this->getSubscriptions('expire_next_30',true,true);
		if($stat_30){
			$_data['lists']['expire_next_30']=array('color'=>'navy','caption'=>'Expire Within<br/>30 Days','content'=>'view nearly due subscriptions','href'=>$this->PERMLINK.'expire_next_30','icon'=>$stat_30);
			if($what==='expire_next_30') $_data['expire_next_30']['color']='green';
		}
		if($stat_unpaid){
			$_data['lists']['subs_unpaid']=array('color'=>'navy','caption'=>'Unpaid<br/>Subscriptions','content'=>'view unpaid subscriptions','href'=>$this->PERMLINK.'unpaid','icon'=>count($stat_unpaid));
			if($what==='subs_unpaid') $_data['subs_unpaid']['color']='green';
		}
		if($this->USER['access']>=25){
			$_data['actions']['subs_renew_expired']=array('color'=>'dark-green','caption'=>'Renew Expired<br/>Membership','content'=>'renew expired membership subscriptions','href'=>$this->PERMLINK.'subs_renew_expired','icon'=>'page-add');
			$_data['actions']['subs_refresh']=array('color'=>'amber','caption'=>'Refresh<br/>Subscriptions','content'=>'updates subscriptions status','href'=>$this->PERMLINK.'subs_refresh','icon'=>'refresh');
			$_data['actions']['subs_dojo']=array('color'=>'navy','load'=>'loadME','caption'=>'Subscriptions<br/>By Dojo','content'=>'view subscriptions by dojo','href'=>$this->PERMLINK.'dojo/menu','icon'=>'target');
			$_data['actions']['subs_report']=array('color'=>'purple','caption'=>'Subscriptions<br/>Data Download','content'=>'download subscriptions records','href'=>$this->PERMLINK.'report/','icon'=>'download');
			if($errs){
				$_data['lists']['subs_report']=array('color'=>'maroon','caption'=>'Renewal<br/>Errors','content'=>'view the renewal errors','href'=>$this->PERMLINK.'renewal_errors','icon'=>'alert');
			}
		}else{
			unset($_data['lists']['subs_renewed_pending']);
		}
		
		$content='';
		foreach($_data as $set=>$recs){
			$dashlinks='';
			foreach($recs as $i=>$v){
				$color=issetCheck($v,'color','navy');
				$load=issetCheck($v,'load');
				if($what===$i) $color='green';
				$but['color']=$color;
				$but['icon']=issetCheck($v,'icon','widget');
				$but['href']=issetCheck($v,'href','#nogo');
				$but['caption']=issetCheck($v,'caption');
				$but['title']=issetCheck($v,'content');
				if($load) $but['load']=$load;
				$dashlinks.=$this->SLIM->zurb->adminButton($but);
			}
			$content.='<div class="cell medium-6"><div class="panel"><strong>'.ucwords($set).'</strong><br/>'.$dashlinks.'</div></div>';
		}
		$this->OUTPUT['title']='Subscriptions Menu';
		$this->OUTPUT['content']='<div class="grid-x grid-padding-x">'.$content.'</div>';
		if($this->AJAX){
			echo renderCard_active($this->OUTPUT['title'],$this->OUTPUT['content'],$this->SLIM->closer);
			die;
		}
	}	
	public function Postman(){
		$state=500;$msg_type='alert';$close=false;
		$post=$this->REQUEST;
		$id=issetCheck($post,'ID');
		$url=$this->PERMLINK;
		switch($this->ACTION){
			case 'update_selected_recipients':
			case 'save_message':
			case 'save_message_send':
				$this->SLIM->Email->POST=$post;
				$this->SLIM->Email->ACTION=$this->ACTION;
				$this->SLIM->Email->MODE='subscription';
				$res=$this->SLIM->Email->renderPost($post);
				$state=200;
				$msg_type='success';
				break;
			case 'add_subscription'://old single item
				$id=0;
				$rec=$this->DEFAULT_REC;
				$chk=$this->saveSubscriptions($id,$rec,$post);
				if($chk){
					$msg=issetCheck($chk,'message','Okay, the subscription has been added.');
					$state=$chk['status'];
					$close=($chk['status']!==200)?false:true;
					$msg_type=$chk['message_type'];
				}else{
					$msg='Sorry, there was problem adding the subscription...';
				}
				break;
			case 'update_subscription':
				if($id){
					$this->getSubscriptions('id',$id);
					$rec=current($this->SUBSCRIPTIONS);
					if($rec){
						$chk=$this->saveSubscription($id,$rec,$post);
						if($chk){
							$msg=$chk['message'];//'Okay, the subscription has been added.';
							$state=$chk['status'];
							$close=($chk['status']!==200)?false:true;
							$msg_type=$chk['message_type'];
						}else{
							$msg='Okay, but nothing was updated...';
							$state=201;
							$msg_type='primary';
						}
					}else{
						$msg='Sorry, I can\'t find that record ['.$id.']...';
					}
				}else{
					$msg='Sorry, incomplete data supplied...';
				}
				break;
			case 'subs_renew_expired_now':
				$msg=$this->renderRenewExpiredSubscriptions($post['renewals']);
				$url=$this->PERMLINK.'subs_renewed_pending';
				setSystemResponse($url,$msg);
				break;				
			default:
				$msg='Sorry, I don\'t know what "'.$this->ACTION.'" is...';
		}
		$out=array('status'=>$state,'message'=>$msg,'message_type'=>$msg_type,'close'=>$close,'type'=>'message');
		if($this->AJAX){
			jsonResponse($out);
			die;
		}else{
			setSystemResponse($url,$msg);
		}
	}
	private function renderDojoMenu(){
		$dashlinks='';
		foreach($this->DOJOS as $i=>$v){
			$but['color']='navy';
			$but['icon']='target';
			$but['href']=$this->PERMLINK.'dojo/'.$i;
			$but['caption']=$v['ShortName'];
			$but['title']=$v['LocationName'];
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

}
