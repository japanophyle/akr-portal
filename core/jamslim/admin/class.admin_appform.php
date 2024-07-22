<?php

class admin_appform{
	private $SLIM;
	private $PERMBACK;
	private $PERMLINK;
	private $GET;
	private $API;
	private $PAGE='signup';
	private $REPORT_REF='active';
	private $OUTPUT=array('status'=>500,'message'=>false,'data'=>false,'content'=>false,'title'=>'Membership Applications');
	private $USE_DASHBOARD=true;
	private $LIB;
	private $DEBUG=false;
	private $DEV_MODE=false;
	private $R4;
	private $ID;
	private $ADMIN_EMAIL;
	private $MAIL_BOT;
	private $NOTIFY=0;
	private $TEST_FORM;
	private $SELECT_LOCATIONS;
	private $GENDERS=[];
	
	public $ROUTE;
	public $AJAX;
	public $REQUEST;
	public $USER;
	public $METHOD;
	public $SECTION;
	public $ACTION;
	public $PLUG;
	public $ADMIN;
	public $LEADER;
	
	function __construct($slim){
		if(!$slim){
		  throw new Exception(__METHOD__.': no slim object!!');
		}
		$app='appform';
		$this->SLIM=$slim;
		$this->PERMBACK=URL.'admin/';
		$this->PERMLINK=$this->PERMBACK.$app.'/';
		$this->LIB=new slimSignupForm($slim);
		$this->LIB->PERMLINK=$this->PERMLINK;
		$this->LIB->PERMBACK=$this->PERMBACK;
		$this->TEST_FORM=$this->LIB->TEST_FORM;
		$this->SELECT_LOCATIONS=$this->LIB->get('select_locations');
		$this->ADMIN_EMAIL=$slim->Options->getSiteOptions('email_administrator',true);
		$this->MAIL_BOT=$slim->Options->getSiteOptions('email_mailbot',true);
		$this->GENDERS=$slim->Options->get('gender');
		$r=$slim->db->Options->select('id,OptionValue')->where('OptionName','signup admin notifications');
		if($r=renderResultsORM($r)){
			$r=current($r);
			$this->NOTIFY=(int)$r['OptionValue'];
		}
	}
	private function init(){
		$this->AJAX=$this->SLIM->router->get('ajax');
		if(!$this->METHOD){
			$this->METHOD=$this->SLIM->router->get('method');
			if(!$this->METHOD) $this->METHOD='GET';
			$this->REQUEST=($this->METHOD==='POST')?$this->SLIM->router->get('post'):$this->SLIM->router->get('get');
			$this->ROUTE=$this->SLIM->router->get('route');
			$this->USER=$this->SLIM->user;			
			$this->PLUG=issetCheck($this->SLIM->AdminPlugins,'appform');
		}
		$this->PAGE=issetCheck($this->ROUTE,1,'signup');
		$this->R4=issetCheck($this->ROUTE,4);
		if($this->METHOD==='POST'){
			$this->ACTION=issetCheck($this->REQUEST,'action');
			$this->ID=issetCheck($this->REQUEST,'id');
			if($this->ID==='new') $this->ACTION='new';
		}else{
			$this->ACTION=issetCheck($this->ROUTE,2);
			$this->ID=($this->ACTION==='new')?'new':issetCheck($this->ROUTE,3);
		}
	}
	function Process(){
		$this->init();
		if($this->METHOD==='POST') $this->renderPost();
		switch($this->ACTION){
			case 'view':
				$this->renderViewForm($this->ID);
				break;
			case 'edit_form':
				$this->renderEditForm($this->ID);
				break;
			case 'delete_rec':
			case 'delete_rec_now':
				return $this->renderDeleteFormRec();
				break;			
			case 'edit_status':
				$this->renderEditFormRec($this->ID);
				break;
			case 'process':
				$this->renderProcessForm(['ID'=>$this->ID,'action'=>$this->R4]);
				break;
			case 'download':
				$this->renderDownloadForm($this->ID);
				break;
			case 'public_form':
				$this->renderPublicForm();
				break;
			case 'new':
				$this->renderSubmitForm();
				break;
			default:
				$this->renderDashboard($this->ACTION);
		}
		return $this->renderOutput();		
	}
	function renderPost(){
		switch($this->REQUEST['action']){
			case 'update_signup':
				$rsp=$this->LIB->updateForm($this->REQUEST);
				break;
			case 'update_signup_rec':
				$rsp=$this->LIB->updateRecord($this->REQUEST);
				break;
			case 'add_signup':
			case 'submit_signup':
				$rsp=$this->LIB->addForm($this->REQUEST);
				break;
			default:
				$rsp=['status'=>500,'message'=>'Sorry, you can\'t do that...'];
		}
		if($this->AJAX){
			return $rsp;
		}
		setSystemResponse($this->PERMLINK,$rsp['message']);
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
		$but['submit_form']='<button class="button button-olive small loadME" title="submin a form" data-ref="'.$this->PERMLINK.'new"><i class="fi-plus"></i> Submit Form</button>';
		$but['public_form']='<button class="button button-navy small loadME" title="view the publiv form" data-ref="'.$this->PERMLINK.'public_form"><i class="fi-clipboard"></i> Public Form</button>';
		$b=[];$out='';
		switch($this->ACTION){
			default:
				if($this->USER['access']>=$this->SLIM->AdminLevel){
					$b=['submit_form','public_form'];
				}else if($this->USER['access']==$this->SLIM->LeaderLevel){
					$b=['submit_form'];
				}
		}
		if($b){
			foreach($b as $i){
				$out.='<li>'.$but[$i].'</li>';
			}
		}
		return $out;
	}
	private function renderDashboard($what=false){
		if(!$this->USE_DASHBOARD){
			$this->OUTPUT['content']=msgHandler('Hmmm... nothing to see here.<br/>Please select an option from the main navigation...',false,false);
			return;
		}
		if($this->USER['access']>=25){
			$recs=$this->LIB->get('all');
		}else{
			$lock=issetCheck($this->USER,'dojo_lock');
			$recs=$this->LIB->get('dojo_data',$lock);
		}
		$ct=0;
		if($recs){
			$colors=['submit'=>'orange','rejected'=>'maroon','approved'=>'dark-green','process_error'=>'red'];
			foreach($recs as $i=>$v){
				$date=explode(' ',$v['LogDate']);
				$date_sort=strtotime($v['LogDate']);
				$dojo=$this->LIB->get('dojo',$v['DojoID']);
				$controls='<button class="button button-dark-blue small loadME" data-ref="'.$this->PERMLINK.'view/'.$i.'"><i class="fi-eye"></i> View</button>';
				$rows[$i]=[
					'ID'=>$i,
					'Name'=>$v['Name'],
					'Email'=>$v['Email'],
					'Dojo'=>$dojo,
					'Date'=>$date[0],
					'Status'=>'<span class="text-'.$colors[$v['Status']].'">'.$v['Status'].'</span>',
					'Controls'=>$controls
				];
				$ct++;
			}
			$content=$this->renderReportTable($rows,$ct);
		}else{
			$content=msgHandler('No Membership Applications found.',false,false);
		}
		$this->OUTPUT['title']='Membership Applications';
		$this->OUTPUT['content']=$content;
	}
	private function renderPublicForm(){
		$title='Public Form';
		$opt=$this->SLIM->options->get('application','membership_signup_public');
		$suri=$this->PERMBACK.'option/edit/'.$opt['id'];
		$state=((int)$opt['OptionValue'])?'<p class="text-dark-green">The public form is active.</p>':'<p class="text-maroon">The public form is not active.</p>';
		$buttons='<button class="button button-dark-blue loadME" data-ref="'.$suri.'"><i class="fi-wrench"></i> Manage the public form status</button>';
		$buttons.='<button class="button button-olive gotoME" data-ref="'.URL.'page/signup"><i class="fi-arrow-right"></i> View form on public site</button>';
		$content='<div class="callout primary text-center"><h3 class="text-dark-blue">How do you want to proceed?</h3>'.$state.'		
			<div class="button-group expanded">'.$buttons.'</div>
		</div>';
		if($this->AJAX){
			echo renderCard_active($title,$content,$this->SLIM->closer);
			die;
		}		
		$this->OUTPUT['title']='Process Form';
		$this->OUTPUT['content']=$content;		
	}
	private function renderSubmitForm(){
		$LIB=new slimSignupForm($this->SLIM);
		$LIB->PERMLINK=$this->PERMLINK;
		$LIB->PERMBACK=$this->PERMBACK;
		if($this->DEV_MODE){
			$LIB->set('form_data',$this->TEST_FORM);
		}
		$content='<div class="label expanded text-center bg-navy">This form will be submitted to the Administrator for approval.</div>';
		$content.=$LIB->get('new');
		$this->OUTPUT['title']='Submit Form';
		$this->OUTPUT['content']=$content;		
		if($this->AJAX){
			echo '<style>.reveal .card-section.main {max-height: 75vh;overflow-Y: auto;} .reveal .card-section .label{margin-bottom:0;}</style>';
			echo renderCard_active($this->OUTPUT['title'],$content,$this->SLIM->closer);
			die;
		}
	}	
	private function renderProcessForm($args){
		if(is_array($args)){
			$id=issetCheck($args,'ID');
			$action=issetCheck($args,'action');
			if(trim($action)==='') $action='render';
		}else{
			$id=(int)$args;
			$action='render';
		}
		$title='Process Form #'.$id;		
		$rec=$this->LIB->get('id',$id);
		if(!$rec){
			$action='not_found';
			$title='Process Form #'.$id.' - not found';
		}else if($rec['Status']!=='submit'){
			$action='processed';
			$title='Process Form #'.$id.' - '.$rec['Name'];
		}
		switch($action){
			case 'render':
				$dojo=$this->LIB->get('dojo_long',$rec['DojoID']);
				$buttons='<button class="button secondary" data-close ><i class="fi-x-circle"></i> Cancel</button>';
				$buttons.='<button class="button button-maroon gotoME" data-ref="'.$this->PERMLINK.'process/'.$id.'/reject"><i class="fi-x"></i> Reject</button>';
				$buttons.='<button class="button button-olive gotoME" data-ref="'.$this->PERMLINK.'process/'.$id.'/approve"><i class="fi-arrow-right"></i> Approve</button>';
				$deets='<strong>Name</strong>: '.$rec['Name'].'<br/>';
				$deets.='<strong>Dojo</strong>: '.$dojo.'<br/>';
				$deets.='<strong>Date</strong>: '.validDate($rec['LogDate'],'F j, Y').'<br/>';
				$content='<div class="callout primary text-center"><h3>How do you want to proceed?</h3>
					<p class="text-dark-green">'.$deets.'</p>
					<p class="text-dark-blue"><em><strong>Approve</strong>: create member records and account<br/><strong>Reject</strong>: set form status to "rejected".</em></p>
					<div class="button-group expanded">'.$buttons.'</div>
				</div>';
				break;
			case 'approve':
				$rsp=$this->approveForm($rec);
				$msg='Sorry, I could not approve that application...';
				if($rsp['status']==200){
					$msg='Okay, the application has been appoved.';
					$this->notify('approved',$rec);
				}else{
					$msg=issetCheck($rsp,'message',$msg);
				}
				setSystemResponse($this->PERMLINK,$msg);			
				break;
			case 'reject':
				$rec['Status']='rejected';
				$rsp=$this->LIB->updateForm($rec);
				$msg='Sorry, I could not reject that application...';
				if($rsp['status']==200){
					$msg='Okay, the application has been rejected.';
					$this->notify('rejected',$rec);
				}
				setSystemResponse($this->PERMLINK,$msg);
				break;
			case 'processed':
				$content=msgHandler('Sorry, that application form has already been processed...',false,false);
				if($this->USER['access']>=25){
					$content.='<button class="button expanded loadME" data-ref="'.$this->PERMLINK.'edit_status/'.$id.'"><i class="fi-wrench"></i> Change Form Status</button>';
				}
				break;
			default:
				$title='Whoops!';
				$content=msgHandler('Sorry, you can\'t do that "'.$action.'"...',false,false);				
		}
		if($this->AJAX){
			echo renderCard_active($title,$content,$this->SLIM->closer);
			die;
		}		
		$this->OUTPUT['title']='Process Form';
		$this->OUTPUT['content']=$content;
	}	
	private function renderReportTable($rows,$count=0){
		$args['data']['data']=$rows;
		$args['before']='filter';
		$table=dataTable($args);		
		return $table;
	}
	private function renderViewForm($ref=0){
		$can_add=hasAccess($this->USER,'members','create');
		$content=$this->LIB->get('view',$ref);
		$rec=$this->LIB->get('current');
		if(!$rec){
			$title='Record not found';
			$content=msgHandler('Sorry, I can\'t find a record with that ID['.$ref.']...',false,false);
		}else{
			$title='Viewing Form #'.$ref.' - '.$rec['Name'];
		}
		if($this->AJAX){
			$buttons='<button class="button loadME" data-ref="'.$this->PERMLINK.'edit_form/'.$ref.'"><i class="fi-clipboard"></i> Edit Form</button>';
			if($can_add) $buttons.='<button class="button loadME button-navy" data-ref="'.$this->PERMLINK.'edit_status/'.$ref.'"><i class="fi-pencil"></i> Edit Log Record</button>';
			$buttons.='<button class="button button-lavendar gotoME" data-ref="'.$this->PERMLINK.'download/'.$ref.'"><i class="fi-page-pdf"></i> Download PDF</button>';
			if($rec['MemberID']) $buttons.='<button class="button button-green text-black loadME" data-ref="'.$this->PERMBACK.'/member/edit/'.$rec['MemberID'].'"><i class="fi-torso"></i> Member Record</button>';
			if($can_add) $buttons.='<button class="button button-olive loadME" data-ref="'.$this->PERMLINK.'process/'.$ref.'"><i class="fi-arrow-right"></i> Process</button>';
			$content='<div style="max-height:55vh;overflow-y:auto;">'.$content.'</div>';
			$content.='<div class="button-group expanded">'.$buttons.'</div>';
			echo renderCard_active($title,$content,$this->SLIM->closer);
			die;
		}
		return ['title'=>$title,'content'=>$content];
	}
	private function renderEditForm($ref=0){
		$content=$this->LIB->get('edit',$ref);
		$rec=$this->LIB->get('current');
		if(!$rec){
			$title='Record not found';
			$content=msgHandler('Sorry, I can\'t find a record with that ID['.$ref.']...',false,false);
		}else{
			$title='Edit Form #'.$ref.' - '.$rec['Name'];
		}
		if($this->AJAX){
			echo renderCard_active($title,$content,$this->SLIM->closer);
			die;
		}
		return ['title'=>$title,'content'=>$content];
	}
	private function renderEditFormRec($ref=0){
		$content=$this->LIB->get('edit_rec',$ref);
		$rec=$this->LIB->get('current');
		if(!$rec){
			$title='Record not found';
			$content=msgHandler('Sorry, I can\'t find a record with that ID['.$ref.']...',false,false);
		}else{
			$title='Edit Form Log Record#'.$ref.' - '.$rec['Name'];
		}
		if($this->AJAX){
			echo renderCard_active($title,$content,$this->SLIM->closer);
			die;
		}
		return ['title'=>$title,'content'=>$content];
	}
	private function renderDeleteFormRec(){
		$contents=$this->LIB->get($this->ACTION,$this->ID);
		if($this->ACTION==='delete_rec_now'){
			setSystemResponse($this->PERMLINK,$contents['content']);	
			die($contents['content']);		
		}else if($this->AJAX){
			echo renderCard_active($contents['title'],$contents['content'],$this->SLIM->closer);
			die;
		}
		return $contents;
	}
	private function renderDownloadForm($ref=0){
		$content=$this->LIB->get('download',$ref);
		$title='PDF Error';
		$content=msgHandler('Sorry, I could not produce a PDF for record #'.$ref.'...',false,false);
		if($this->AJAX){
			echo renderCard_active($title,$content,$this->SLIM->closer);
			die;
		}
		return ['title'=>$title,'content'=>$content];
	}
	private function getData($id=false){
		$data=[];
		$select=false;
		$db=$this->SLIM->db->SignupLog;
		if((int)$id){
			$recs=$db->where('ID',$id);
		}else if($id==='all'){
			$recs=$db->where('ID >=?',1);
			$select='ID,Name,Email,LogDate,Status';
		}else{
			return $data;
		}
		if($select) $recs->select($select);
		$rez=renderResultsORM($recs,'ID');
		if($rez && $id!=='all'){
			$rez=current($rez);
			$rez['FormData']=compress($rez['FormData'],false);
		}
		return $rez;		
	}
	private function memberExists($email=false){
		$exists=[];
		$email=trim($email);
		if($email!==''){
			$rec=$this->SLIM->db->Members->where('Email',$email);
			if(count($rec)){
				$rec=renderResultsORM($rec);
				$exists=current($rec);
			}
		}
		return $exists;
	}
	private function userExists($email=false){
		$exists=[];
		$email=trim($email);
		if($email!==''){
			$rec=$this->SLIM->db->Users('Email',$email);
			if(count($rec)){
				$rec=renderResultsORM($rec);
				$exists=current($rec);
			}
		}
		return $exists;
	}
	private function makeMemberAccount($fdata){
		$setCurrentGrade=function($d){
			$grade=$loc=null;
			$date=false;
			for($x=9;$x>=1;$x--){
				$_loc=issetCheck($d['exam'.$x],'location');
				if(!(int)$_loc){
					if(trim($_loc)==='') $_loc=null;
				}
				$_date=issetCheck($d['exam'.$x],'date');
				if($_loc || trim($_date)!==''){
					$grade=$x;
					$date=$_date;
					break;
				}
			}
			$d['CurrentGrade']=$grade;
			$d['CGradedate']=$date;
			return $d;
		};

		$err=$log=[];
		$state=500;
		$exists=$this->memberExists($fdata['Email']);
		if(!$exists){
			$fdata=$setCurrentGrade($fdata);
			$gradename=$this->LIB->get('grade_name',$fdata['CurrentGrade']);
			$gradeloc=$this->getCurrentGradeLocation($fdata);
			$dojo=$this->LIB->get('dojo_code',$fdata['DojoID']);
			$member=[
				'FirstName'=>$fdata['FirstName'],
				'LastName'=>$fdata['LastName'],
				'Birthdate'=>$fdata['Birthdate'],
				'Sex'=>issetCheck($this->GENDERS,$fdata['Sex'],'Maile'),
				'DojoID'=>$fdata['DojoID'],
				'Dojo'=>$dojo,
				'DateJoined'=>date('Y-m-d'),
				'MemberTypeID'=>1,
				'Address'=>$fdata['Address'],
				'Town'=>issetCheck($fdata,'Town',''),
				'City'=>$fdata['City'],
				'Country'=>$fdata['Country'],
				'PostCode'=>$fdata['PostCode'],
				'MobilePhone'=>$fdata['MobilePhone'],			
				'Language'=>$fdata['Language'],
				'Email'=>$fdata['Email'],
				'Zasha'=>$fdata['zasha'],
				'CurrentGrade'=>$fdata['CurrentGrade'],
				'CGradedate'=>$fdata['CGradedate'],
				'CGradeName'=>$gradename,
				'TimeStamp'=>date('Y-m-d H:i:s'),
				'Disable'=>0,
				'CGradeLoc1'=>$gradeloc[0],
				'CGradeLoc2'=>$gradeloc[1],
			];
			if($this->DEBUG){
				preME($member);
				$member_id=9999;
			}else{
				$db=$this->SLIM->db->Members;
				$chk=$db->insert($member);
				$member_id=($chk)?$db->insert_id():0;
			}
			if($member_id){
				$state=200;				
				//meta
				$meta=issetCheck($fdata,'meta',[]);
				$this->addMetaRecords($member_id,$meta);
				
				//grade log
				$grades=$this->addGradeLogs($fdata,$member_id);
				if($grades['status']==500){
					$err=$grades['errors'];
					$log=$grades['log'];
				}
			}
		}else{
			$member_id=$exists['MemberID'];
			$err[]='email already in database [ref: '.$member_id.']';
			$state=500;
			if($this->DEBUG){
				$state=200;
				preME($exists);
			}
		}
		return ['status'=>$state,'id'=>$member_id,'errors'=>$err,'log'=>$log];
	}
	private function getCurrentGradeLocation($fdata){
		$out=[];
		for($x=9;$x>=1;$x--){
			$val=$fdata['exam'.$x];
			if((int)$val['location']){
				$loc=$this->LIB->get('location',$val['location']);
				if($loc){
					$out=explode(', ',$loc);
				}
			}else if(trim($val['location'])!==''){
				$out=[$val['location'],'-'];
			}
			if($out) break;
		}
		if(!$out) $out=['Unknown','NA'];
		return $out;
	}
	private function addMetaRecords($id=0,$meta=[]){
		if(!$id) return;
		$data=['citizenship'=>'','date_began_kyudo'=>'','payment_method'=>'','archery_member_id'=>'','teaching_rank'=>'','years_practiced'=>'','practice_location'=>''];
		$db=$this->SLIM->db->Meta;
		foreach($data as $i=>$v){
			$val=issetCheck($meta,$i,$v);
			$db->insert(['metaValue'=>$val,'MetaItemID'=>$id,'MetaType'=>'member','MetaKey'=>$i]);
		}
	}
	private function addGradeLogs($fdata,$member_id){
		$err=$log=[];
		//grade log
		if(!$this->DEBUG) $db=$this->SLIM->db->GradeLog;
		for($x=1;$x<=9;$x++){
			$val=$fdata['exam'.$x];
			$numeric=is_numeric($val['location']);
			$loc=($numeric)?(int)$val['location']:trim($val['location']);
			if($loc && $loc!==''){
				$tm=strtotime(str_replace('/','-',$val['date']));
				if($tm){
					$grades=['GradeSet'=>$x,'GradeDate'=>date('Y-m-d',$tm),'MemberID'=>$member_id];
					if($numeric){
						$grades['LocationID']=$loc;
					}else{
						$grades['Location']=$loc;
					}
					if($this->DEBUG){
						$log['grades'][]=$grades;
					}else{
						$chk=$db->insert($grades);
						if(!$chk){
							$err[]='Error adding grade log '.$x;
							$log['grades'][]=$grades;
						}
					}
				}
			}
		}
		if($this->DEBUG) preME($log);
		$state=($err)?500:200;		
		return ['status'=>$state,'errors'=>$err,'log'=>$log];
	}
	private function makeUserAccount($fdata){
		$err=$log=[];
		$user=[
			'Name'=>$fdata['Name'],
			'Username'=>$fdata['uname'],
			'Access'=>20,
			'Status'=>1,
			'Email'=>$fdata['Email'],
			'Password'=>$fdata['upass'],
			'MemberID'=>$fdata['MemberID'],
			'DojoLock'=>serialize([$fdata['DojoID']]),
		];
		$exists=$this->userExists($user['Email']);
		if($exists){
			$log['user']=$user;
			$log['new_user']=current($exists);				
			$user_id=$log['new_user']['id'];
		}else{
			$MU = new makeUser;
			$new_user = $MU->Process($user);
			if($this->DEBUG){
				$log['user']=$user;
				$log['new_user']=$new_user;
				$user_id=9999;
			}else{
				$user_id=0;
				if(is_array($new_user)){					
					$db=$this->SLIM->db->Users;
					$chk=$db->insert($new_user);
					if($chk){
						$user_id=$db->insert_id();
					}else{
						$err[]='insert user error';
						$log['user']=$user;
						$log['new_user']=$new_user;
					}
				}else{
					$err[]='no user created.';
					$log['user']=$user;
					$log['new_user']=$new_user;
				}
			}
		}
		if($this->DEBUG) preME($log);
		$state=($err)?500:200;		
		return ['status'=>$state,'user_id'=>$user_id,'errors'=>$err,'log'=>$log];
		
	}
	private function approveForm($rec){
		$fdata=$rec['FormData'];
		$fdata=$this->LIB->checkGrade($fdata);
		$member_id=$user_id=0;
		//make member account & add grade log
		$member_account=$this->makeMemberAccount($fdata);
		$member_id=$member_account['id'];
		$err=$member_account['errors'];
		$log=$member_account['log'];
		if($member_account['status']==200){			
			//make user account
			$fdata['Name']=$rec['Name'];
			$fdata['MemberID']=$member_id;
			$user=$this->makeUserAccount($fdata);
			if($user['status']==500){
				$err+=$user['errors'];
				$log+=$user['log'];
			}else{
				$user_id=$user['user_id'];
			}			
			//update form log
			$state=($err)?'process_error':'approved';
			$rec['Status']=$state;
			$rec['MemberID']=$member_id;
			$rec['UserID']=$user_id;
			if($this->DEBUG){
				preME($rec);				
				$rsp=$this->LIB->updateRecord($rec,true);
				preME($rsp);
				preME('DEBUG MODE: done',2);
			}else{
				$rsp=$this->LIB->updateRecord($rec);
			}
		}else{
			$rsp=['status'=>500,'message'=>'Sorry, a member record already exists...'];
		}
		if($err){
			$log['form']=$rec;
			$log['errors']=$err;
			$fpath=CACHE.'log/signup_process_error_'.$rec['ID'].'_'.date('YmdHis').'.json';			
			file_put_contents($fpath,json_encode($log));					
		}
		return $rsp;
	}
	private function notify($type=null,$log=[]){
		switch($type){
			case 'rejected':
				$msg='<h1>Hello {name},</h1><p>This is just a quick note to let you know that unforunately your application for membership dated {start} was not successful this time.</p><p>If you would like to enquire about this outcome, please contact us via this email: '.$this->ADMIN_EMAIL.'</p><p>Regards<br/><strong>Team AKR</strong></p>';
				$admin_msg='<h1>Hello Admin,</h1><p>This is just a quick note to let you know that the application for membership below has been rejected.</p><p><strong>{name}<br/>{member_email}<br/>{start}</strong></p><p>If you would like to enquire about this outcome, please contact us via this email: '.$this->ADMIN_EMAIL.'</p><p>Regards<br/><strong>Team AKR</strong></p>';
				$subject='Membership Application Unsuccessful';
				break;
			case 'approved':
				$msg='<h1>Hello {name},</h1><p>This is just a quick note to let you know that your application for membership dated {start} has been successful.</p><p>One of our team will be in contact soon. Please contact us via this email: '.$this->ADMIN_EMAIL.' if you have not heard from us in the next few days.</p><p>Regards<br/><strong>Team AKR</strong></p>';
				$admin_msg='<h1>Hello Admin,</h1><p>This is just a quick note to let you know that the application for membership below has been approved.</p><p><strong>{name}<br/>{member_email}<br/>{start}</strong></p><p>If you would like more information, please contact us via this email: '.$this->ADMIN_EMAIL.'</p><p>Regards<br/><strong>Team AKR</strong></p>';
				$subject='Membership Application Approved';
				break;
			default:
				return;
		}
		//produce pdf
		$subject_pre=$this->SLIM->config['SITE_SHORT_NAME'].': '.$subject;
		$dojo=$this->LIB->get('club',$log['FormData']['DojoID']);
		$pdf=$this->LIB->get('pdf_public',$log);//'membership_application_'.$log['ID'];
		//common parts
		$parts['event_name']=$this->SLIM->language->getStandardContent('membership_form');
		$parts['event_date']=$parts['start']=validDate($log['LogDate']);
		$parts['name']=$parts['member_name']=$log['Name'];
		$parts['member_email']=$log['Email'];
		$parts['url']=URL;		
		
		$send['header']=$this->SLIM->EmailParts['header'];//$this->SLIM->language->getStandardContent('email_header');
		$send['footer']=$this->SLIM->EmailParts['footer'];//$this->SLIM->language->getStandardContent('email_footer');
		$send['from']=$this->MAIL_BOT;
		$send['attachments'][]=$pdf;

		//send user email
		$msg=replaceMe($parts,$msg);
		$send['to']=$log['Email'];
		$send['subject']=$subject_pre;
		$send['message'][0]=strip_tags($msg,'<a><br><br/>');
		$send['message'][1]=$msg;
		$chk=$this->SLIM->Mailer->Process($send);
		
		//send admin email
		$msg=replaceMe($parts,$admin_msg);
		$send['to']=($dojo['Email']==='???')?$this->ADMIN_EMAIL:$dojo['Email'];
		$send['subject']=$subject_pre;
		$send['message'][0]=strip_tags($msg,'<a><br><br/>');
		$send['message'][1]=$msg;
		$chk=$this->SLIM->Mailer->Process($send);
		
	}
}
