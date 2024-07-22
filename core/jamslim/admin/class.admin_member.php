<?php 
class admin_member{
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
	private $USE_DASHBOARD=true;
	private $FILTER;
	private $FIND;
	private $META_KEYS;
	private $META;	
	private $CHIP_SEARCH=true;
	private $GRADE_LOCATION='Location';
	public $CONFIRM_UPDATES;
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
		$this->LIB=new slim_db_members($slim);
		$this->DEFAULT_REC=$this->LIB->get('new');
		$this->META_KEYS=$this->LIB->get('meta_keys');
		$this->OPTIONS['Access']=$slim->Options->get('access_levels');
		$this->OPTIONS['Status']=$slim->Options->get('active');
		$o=['MemberTypeID'=>'membertype','DojoID'=>'dojos','Language'=>'languages','Dead'=>'yesno','zasha'=>'yesno','Disable'=>'disabled','Sex'=>'sex','nonuk'=>'yesno',
			'GradeSet'=>'grades_val','LocationID'=>'locations','Clubs'=>'clubs_name'
		];
		foreach($o as $i=>$v) $this->OPTIONS[$i]=$slim->Options->get($v);
		$this->IS_SUPER=($slim->user['access']==30)?true:false;
	}
	
	function Process(){
		$this->init();
		if($this->METHOD==='POST'){
			$this->doPost();
		}
		switch($this->ACTION){
			case 'fixup':
				$this->renderFixup();
				break;
			case 'list_menu':
				$this->renderListMenu();
				break;
			case 'dojo_menu':
				$this->renderDojoMenu();
				break;
			case 'grade_menu':
				$this->renderGradeMenu();
				break;
			case 'gender_menu':
				$this->renderGenderMenu();
				break;
			case 'edit':
				$this->renderEditItem();
				break;				
			case 'view':
				$this->renderViewItem();
				break;				
			case 'new':
				$this->renderNewItem();
				break;				
			case 'newmemb':
				$this->renderNewSignup();
				break;				
			case 'tmplogin':
			case 'canceltmplogin':
				$this->renderTmpLogin();
				break;
			case 'gradelog':case 'eventlog':case 'memberlog':
				$this->renderLogItem();
				break;
			case 'download':
				$this->renderDownloadData();
				break;
			case 'search':
			case 'search_form':
				$this->renderFindMembers();
				break;
			default:
				$this->renderListItems();
		}
		return $this->renderOutput();
	}
	private function doPost(){
		$url=$this->PERMLINK;
		$refresh=$mtype=false;
		$act=$this->REQUEST['action'];
		switch($act){
			case 'update':
			case 'add':
				$data=$this->cleanInput();
				$chk=($this->REQUEST['action']==='add')?$this->LIB->addRecord($data,$data['id']):$this->LIB->updateRecord($data,$data['id']);
				$state=$chk['status'];
				$msg=$chk['message'];
				$mtype=$chk['message_type'];
				if($this->REQUEST['action']==='add' && $state==200){
					if(isset($chk['id'])){
						$refresh=true;
					}
				}
				break;
			case 'update_memberlog':case 'update_gradelog':
			case 'add_memberlog':case 'add_gradelog':
				$rsp=$this->saveLogItem();
				$msg=$rsp['message'];
				$state=$rsp['status'];
				$mtype=$rsp['mtype'];
				if(strpos($act,'gradelog')) $this->updateMemberGrade();
				break;
			case 'search':
				if($this->CHIP_SEARCH){
					$this->FIND=issetCheck($this->REQUEST,'findME');
					$this->renderFindMembers();					
				}
				//will have already responded
				$msg='Sorry, that action is not possible...';
				$mtype='alert';
				$state=500;		
				break;
			default:
				$msg='Sorry, that action is not possible...';
				$mtype='alert';
				$state=500;		
		}
		if($this->AJAX){
			$o=['status'=>$state,'message'=>$msg,'message_type'=>$mtype,'type'=>'message'];
			if($refresh) $o['redirect']=$url;
			echo jsonResponse($o);
		}else{
			setSystemResponse($url,$msg);
		}
		die;			
	}
	private function cleanInput(){
		$act=$this->REQUEST['action'];
		$clean=array();
		$clean['id']=(int)issetCheck($this->REQUEST,'id');
		$clean['meta']=[];
		foreach($this->DEFAULT_REC as $i=>$v){
			if(array_key_exists($i,$this->REQUEST)){
				$clean[$i]=$this->REQUEST[$i];
			}
		}
		if(isset($this->REQUEST['meta'])){
			foreach($this->META_KEYS as $key){
				$clean['meta'][$key]=issetCheck($this->REQUEST['meta'],$key);
			}
		}
		if($act==='update') $clean['id']=$clean['MemberID'];
		return $clean;
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
	private function renderContextMenu(){
		$r3=issetCheck($this->ROUTE,3);
		$can_add=hasAccess($this->USER,'members','create');
		$tog_active=$this->PERMLINK.$this->ACTION;
		if($this->ACTION==='dojo') $tog_active.='/'.$this->ID;
		$statTog=($this->FILTER==='active')?'/all':'/active';
		$statTxt=($this->FILTER==='active')?'All':'Active';
		$dlink=$this->ACTION.'/'.$this->ID.'/'.$this->FILTER;
		
		$but['back']='<button class="button small button-dark-purple backME" title="back to list" type="button"><i class="fi-arrow-left"></i> Back</button>';
		$but['new']='<button class="button small button-dark-blue loadME" title="add a new record" data-ref="'.$this->PERMLINK.'new" type="button"><i class="fi-plus"></i> New</button>';
		$but['newmemb']='<button class="button small button-dark-blue loadME" title="submit a new member form" data-ref="'.$this->PERMLINK.'newmemb" type="button"><i class="fi-plus"></i> New Member</button>';
		$but['save']='<button class="button small button-olive submitME" title="save changes" data-ref="ajaxform" type="button"><i class="fi-check"></i> Update</button>';
		$but['download_pdf']='<button class="button small button-navy gotoME" title="download PDF" data-ref="'.$this->PERMLINK.'download/'.$this->ID.'" type="button"><i class="fi-download"></i> Download</button>';
		$but['download_data']='<button class="button small button-lavendar gotoME" title="download data" data-ref="'.$this->PERMLINK.'download/'.$dlink.'" type="button"><i class="fi-download"></i> Download</button>';
		$but['menu']='<button class="button small button-navy loadME" title="Member Lists" data-ref="'.$this->PERMLINK.'list_menu/'.$r3.'" type="button"><i class="fi-list"></i> Member Lists</button>';
		$but['edit']='<button class="button small button-dark-blue loadME" title="edit payment record" data-ref="'.$this->PERMLINK.'edit_payment/'.$this->ID.'/list" type="button"><i class="fi-pencil"></i> Edit</button>';
		$but['dojo_details']='<button class="button small button-blue text-navy loadME" title="view dojo record" data-ref="'.$this->PERMBACK.'dojo/club/'.$this->ID.'" type="button"><i class="fi-target"></i> Dojo Info.</button>';
		$but['active']='<button class="button small button-navy gotoME" title="show '.$statTxt.' records" data-ref="'.$tog_active.$statTog.'" type="button"><i class="fi-torso"></i> '.$statTxt.' Records</button>';
		$but['email']=($this->USER['access']>=25)?'<button class="button small button-navy gotoME" title="email members" data-ref="'.$this->PERMBACK.'mailer/add/dojo/'.$this->ID.'" type="button"><i class="fi-mail"></i> Email Members</button>':'';
		$b=[];$out='';
		switch($this->ACTION){
			case 'edit':
				$b=['back','new','save'];
				break;
			case 'edit_record'://viewing invoice
				$b=['back','download_pdf','edit','new'];
				break;
			case 'dojo':
				$b=['dojo_details','download_data','email','active','menu','new'];
				break;
			case 'grades':
			case 'list':
				$b=['download_data','active','menu','new'];
				if(in_array($r3,['former','inactive','banned'])) unset($b[0]);
				break;
			default:
				$b=['menu','new'];
		}
		if($b){
			foreach($b as $i){
				if($i==='new' && !$can_add) $i='newmemb';
				$out.='<li>'.$but[$i].'</li>';
			}
		}
		return $out;
	}
	private function init(){
		if(!$this->METHOD) $this->METHOD='GET';
		if(!$this->REQUEST) $this->REQUEST=($this->METHOD==='POST')?$this->SLIM->router->get('post'):$this->SLIM->router->get('get');
		if(!$this->ROUTE) $this->ROUTE=$this->SLIM->router->get('route');
		if(!$this->SECTION) $this->SECTION=issetCheck($this->ROUTE,1);
		if(!$this->AJAX) $this->AJAX=$this->SLIM->router->get('ajax');
		if(!$this->USER) $this->USER=$this->SLIM->user;			
		if(!$this->ACTION) $this->ACTION=issetCheck($this->ROUTE,2,'list');
		if($this->METHOD==='POST') $this->ACTION=issetCheck($this->REQUEST,'action');
		if(!$this->PLUG) $this->PLUG=issetCheck($this->SLIM->AdminPlugins,$this->SECTION);
		$this->ID=issetCheck($this->ROUTE,3);
		$this->FILTER=issetCheck($this->ROUTE,4);
		if($this->ID==='active'){
			$this->FILTER=$this->ID;
			$this->ID=false;
		}
		$this->PERMBACK=URL.'admin/';
		$this->PERMLINK=$this->PERMBACK.'member/';
		//init data
		if(!$this->ACTION && !$this->METHOD==='POST'){
			$this->DATA=$this->getMembers('all');
		}else{
			if($this->ACTION==='new'){
				$this->DATA=$this->getMembers('new');
				$this->ID='new';
			}else if($this->ACTION==='newmemb'){
				$this->DATA=$this->getMembers('new');
				$this->ID='new';
			}else if($this->ACTION==='search'){
				if($this->FIND=issetCheck($this->REQUEST,'findME')){
					$this->DATA=$this->getMembers('search',$this->FIND);
				}				
			}else if($this->ACTION==='download'){
				$this->DATA=[];				
			}else if(in_array($this->ACTION,['gradelog','eventlog','memberlog'])){
				$this->DATA=[];
			}else if($this->ACTION==='dojo'){
				if(!$this->FILTER) $this->FILTER='active';
				$this->DATA=$this->getMembers('dojo');
			}else if($this->ACTION==='grades'){
				if(!$this->FILTER) $this->FILTER='active';
				$this->DATA=$this->getMembers('grade');
			}else if((int)$this->ID){
				$this->ID=(int)$this->ID;
				$this->DATA=$this->getMembers('id');
			}else if(in_array($this->ACTION,array('list','select'))){
				switch($this->ACTION){
					case 'list':
						$w=($this->ID)?$this->ID:'active';
						break;
					default:
						$w='all';					
				}
				if(!$this->FILTER) $this->FILTER=$w;
				$this->DATA=$this->getMembers($w);
			}
		}		
	}
	private function renderViewItem(){
		$item=($this->DATA)?$this->DATA:false;
		$title='Member Info. #'.$this->ID;
		$button=false;
		if($item){
			$can_update=($this->LEADER)?hasAccess($this->USER,'members','update'):true;
			$item=$this->formatData($item,'view');
			$tpath=TEMPLATES.'parts/tpl.member-view.html';
			$tpl=false;
			if(file_exists($tpath))	$tpl=file_get_contents($tpath);
			$thumb='<i class="fi-torso text-navy icon-x3"></i>';
			$fill=array(
				'image'=>$thumb,
				'name'=>$this->LIB->get('fullname',$item),
				'type'=>$item['MemberTypeID'],
				'email'=>$item['Email'],
				'phone'=>$item['MobilePhone'].' / '.$item['LandPhone'],
				'address'=>str_replace(PHP_EOL,', ',$item['Address']),
				'joined'=>$item['DateJoined'],
				'birthday'=>$item['Birthdate'].' ['.getAge($item['Birthdate']).']',
				'gender'=>$item['Sex'],
				'grade'=>$item['CGradeName'],
				'grade_date'=>$item['CGradedate'].' ('.$item['CGradeLoc1'].')',
			);
			if($tpl){
				$content=replaceMe($fill,$tpl);
				if($can_update) $button='<button title="edit this '.$this->SECTION.'" class="button button-dark-purple loadME" data-reload="1"  data-size="large" data-ref="'.$this->PERMLINK.'edit/'.$this->ID.'"><i class="fi-pencil"></i></button> ';
			}else{
				$content=msgHandler('Sorry, no member template found...');
			}
		}else{
			$content=msgHandler('Sorry, I can\'t find a member with that ID...',false,false);			
		}
		$out=renderCard_active($title,$content,$button.$this->SLIM->closer);
		if($this->AJAX){
			echo $out;
			die;
		}
		return $out;	
	}
	private function renderEditItem(){
		$data=$this->getMember();
		$user=$this->getUserInfo($this->ID);
		$logs=['MembersLog','GradeLog','EventsLog','SalesLog'];
		$tabs=['Details'=>[],'Contact'=>[],'Grades'=>[],'Events'=>[],'Sales'=>[],'Log'=>[],'Other'=>[]];
		$contacts=['Email','Address','City','PostCode','Country','LandPhone','MobilePhone'];
		$others=['Language','AnkfID','zasha','NameInJapanese','NameInJapanese2','archery_member_id','payment_method'];
		$infos=['CGradeName','CGradedate','CGradeLoc1'];
		$skip=['Dead','nonuk','Age','Town'];
		//render tabs
		if($data['status']==200){
			$ginfo=[];
			$member=$this->formatData($data['data'],'edit');
			foreach($member as $i=>$v){
				if(in_array($i,$skip)){
					continue;
				}else if(in_array($i,$logs)){
					$log=$this->renderLog($i,$data['data'][$i]);
					$tabs[$log['tab']]=$log['content'];
				}else if(in_array($i,$infos)){
					$ginfo[$i]=$v;
				}else if(in_array($i,$others)){
					$tabs['Other'][$i]=$v;
				}else if(in_array($i,$contacts)){
					$tabs['Contact'][$i]=$v;
				}else{
					$tabs['Details'][$i]=$v;
				}
			}
			foreach(array_keys($tabs) as $t){
				if(in_array($t,['Details','Contact','Other'])){
					$tmp='';
					switch($t){
						case 'Details':
							$tmp.=implode('',$tabs[$t]);
							break;
						case 'Contact':
							foreach($contacts as $k) $tmp.=$tabs[$t][$k];
							break;
						case 'Other':
							foreach($others as $k) $tmp.=$tabs[$t][$k];
							break;
					}							
					$tabs[$t]=$tmp;	
				}
			}
			$tabs=$this->SLIM->zurb->tabs(['id'=>'edit_member','tabs'=>$tabs]);
			$tabs.='<input type="hidden" name="action" value="update"/>';
			$controls='<button class="button button-teal loadME" data-size="large" data-reload="true" data-ref="'.$this->PERMLINK.'edit/'.$this->ID.'"><i class="fi-refresh"></i> Reload Record</button>';
			if($user) $controls.='<button class="button button-navy loadME" data-size="medium" data-ref="'.$this->PERMBACK.'user/edit/'.$user['id'].'"><i class="fi-torso"></i> View User Record</button>';
			$controls.='<button title="use this to view the users profile page on the public site" class="button button-yellow text-black gotoME" data-ref="'.$this->PERMLINK.'tmplogin/'.$this->ID.'"><i class="fi-social-android"></i> Login as this Member</button>';
			$controls.='<button class="button button-olive" type="submit"><i class="fi-check"></i> Update</button>';
			$content='<form class="ajaxForm" method="post" action="'.$this->PERMLINK.'">'.$tabs.'<div class="button-group small expanded">'.$controls.'</div></form>';
			$uname=$data['data']['FirstName'].' '.$data['data']['LastName'].' - '.$data['data']['Age'].' ['.$data['data']['CGradeName'].']';
		}else{
			$content=msgHandler('Sorry, no records found for ref:'.$data['ref'],false,false);
			$uname=$this->DATA['FirstName'].' '.$this->DATA['LastName'].' [???]';
		}
		$this->OUTPUT['title']='Edit Member #'.$this->ID.' <em class="text-dark-blue">'.$uname.'</em>';
		$this->OUTPUT['content']=$content;		
		if($this->AJAX){
			echo '<style>.reveal .card-section.main {max-height: 100%;overflow-Y: auto;}</style>';
			echo renderCard_active($this->OUTPUT['title'],$content,$this->SLIM->closer);
			echo '<script>$(".reveal .card-section.main").foundation();</script>';
			die;
		}
	}
	private function renderNewSignup(){
		$LIB=new slimSignupForm($this->SLIM);
		$LIB->PERMLINK=$this->PERMLINK;
		$LIB->PERMBACK=$this->PERMBACK;
		$content='<div class="label expanded text-center bg-navy">This form will be submitted to the Administrator for approval.</div>';
		$content.=$LIB->get('new');
		$this->OUTPUT['title']='Signup Form';
		$this->OUTPUT['content']=$content;		
		if($this->AJAX){
			echo '<style>.reveal .card-section.main {max-height: 75vh;overflow-Y: auto;} .reveal .card-section .label{margin-bottom:0;}</style>';
			echo renderCard_active($this->OUTPUT['title'],$content,$this->SLIM->closer);
			die;
		}
	}
	private function renderNewItem(){
		$can_add=hasAccess($this->USER,'members','create');
		if($can_add){
			$parts=['FirstName','LastName','Email','Birthdate','MemberTypeID','DojoID','DateJoined','Disable'];
			$data=$this->formatData($this->DATA,'edit');
			$note='<p class="label expanded text-center bg-yellow text-black">You can add more deatails once the initial record has been created.</p>';
			$form='<input type="hidden" name="action" value="add"/><input type="hidden" name="id" value="new"/>';
			foreach($parts as $p) $form.=issetCheck($data,$p);
			$controls='<button class="button button-olive" type="submit"><i class="fi-plus"></i> Add Now</button>';
			$content='<form class="ajaxForm" method="post" action="'.$this->PERMLINK.'">'.$note.'<div class="tabs-content">'.$form.'</div><div class="button-group small expanded">'.$controls.'</div></form>';
			$title='#New Member';
		}else{
			$content=msgHandler('Sorry, you dont have access to add members.',false,false);
			$title='No Access';
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;		
		if($this->AJAX){
			echo '<style>.reveal .card-section.main {max-height: 100%;overflow-Y: auto;} .reveal .card-section .label{margin-bottom:0;}</style>';
			echo renderCard_active($this->OUTPUT['title'],$content,$this->SLIM->closer);
			die;
		}
	}
	private function renderTmpLogin(){
		$APP=$this->SLIM->TempLogin;
		$msg=false;
		switch($this->ACTION){
			case 'canceltmplogin':
			    $msg=$APP->get('logout');
			    //should have redirected
				break;
			case 'tmplogin':
				if($this->DATA){
					//get user ID - for temp login
					$user=$this->getUserInfo($this->DATA['MemberID']);
					if(isset($user['id'])){
						$o=array('id'=>$user['id'],'name'=>$user['Name'],'uname'=>$user['Username'],'member_id'=>$this->DATA['MemberID'],'dojo_id'=>$this->DATA['DojoID']);
						$msg=$APP->get('login',$o);
					}else{
						$msg='Sorry, no user record found for Member #'.$this->DATA['MemberID'];
					}
				}
				break;
		}

		if($msg && trim($msg)!=='') $msg=msgHandler($msg,false,false);	
		$content=$APP->get('dash');
		$this->OUTPUT['title']='Member: <span class="subheader">'.$content['title'].'</span>';
		$this->OUTPUT['content']=$msg.$content['content'];
		if($this->AJAX){
			echo '<style>.reveal .card-section.main {max-height: 100%;overflow-Y: auto;} .reveal .card-section .label{margin-bottom:0;}</style>';
			echo renderCard_active($this->OUTPUT['title'],$content,$this->SLIM->closer);
			die;
		}
	}
	private function renderLog($ref,$data=[]){
		if(!$data) $data=[];
		$renderControl=function($u,$type='edit'){
			$icon='pencil';
			$color='navy';
			$label='Edit';
			if($type==='view'){
				$icon='eye';
				$color='dark-blue';
				$label='View';
			}
			return '<button class="button small button-'.$color.' loadME" data-ref="'.$u.'"><i class="fi-'.$icon.'"></i> '.$label.'</i>';
		};
		$log=$add_url=$add_url2='';
		$name=str_replace('log','',strtolower($ref));
		switch($name){
			case 'members': 
				$name='log';
				$thead='<th>Subject</th><th>Date</th><th>Details</th><th>Control</th>';
				$add_url=$this->PERMLINK.'memberlog/new/'.$this->ID;
				break;
			case 'grade': 
				$name='grades'; 
				$thead='<th>Grade</th><th>Date</th><th>Location</th><th>Control</th>';
				$add_url=$this->PERMLINK.'gradelog/new/'.$this->ID;
				break;
			case 'events':
				$thead='<th>Event</th><th>Date</th><th>Attending</th><th>Control</th>';
				break;
			case 'sales':
				$thead='<th>Item</th><th>Date</th><th>Value</th><th>Paid</th><th>Control</th>';
				$add_url=$this->PERMBACK.'sales/member/'.$this->ID;
				$add_url2=$this->PERMBACK.'subscription/subs_member/'.$this->ID;
				break;				
		}
		foreach($data as $i=>$v){
			switch($name){
				case 'grades':
					$date=validDate($v['GradeDate']);
					$grade=$this->getOption('GradeSet',$v['GradeSet']);
					if($this->GRADE_LOCATION==='LocationID'){
						$location=((int)$v['LocationID'])?$this->getOption('LocationID',$v['LocationID']):['LocationName'=>''];//$this->SLIM->Options->get('location_name',$v['LocationID']);
					}else{
						$location=['LocationName'=>$v['Location']];
					}
					$control=$renderControl($this->PERMLINK.'gradelog/edit/'.$v['GradeLogID']);
					$log.='<tr><td>'.$grade['OptionName'].'</td><td>'.$date.'</td><td>'.$location['LocationName'].'</td><td>'.$control.'</td></tr>';
					break;
				case 'events':
					$date=validDate($v['EventDate']);
					$control=$renderControl($this->PERMBACK.'events/view/'.$v['EventID'],'view');
					$attend=((int)$v['Attending'])?'<span class="text-dark-green">Yes</span>':'<span class="text-gray">No</span>';
					$paid=((int)$v['Paid'])?'<span class="text-dark-green">Yes</span>':'<span class="text-maroon">No</span>';
					$log.='<tr><td>'.$v['EventName'].'</td><td>'.$date.'</td><td>'.$attend.'</td><td>'.$control.'</td></tr>';
					break;
				case 'log':
					$date=validDate($v['LogDate']);
					$control=$renderControl($this->PERMLINK.'memberlog/edit/'.$v['MembersLogID'],'edit');
					$log.='<tr><td>'.$date.'</td><td>'.$v['LogSubject'].'</td><td>'.truncateME($v['LogDetail']).'</td><td>'.$control.'</td></tr>';
					break;
				case 'sales':
					if(!is_array($v)) break;
					foreach($v as $x=>$y){
						$date=validDate($y['SalesDate']);
						$item=$this->SLIM->Options->getProductInfo('product_name',$y['ItemID']);
						$control=$renderControl($this->PERMBACK.'sales/edit_record/'.$x,'view');
						$value=toPounds($y['SoldPrice']);
						$paid=((int)$y['Paid'])?'<span class="text-dark-green">Yes</span>':'<span class="text-maroon">No</span>';
						$log.='<tr><td>'.$item.'</td><td>'.$date.'</td><td>'.$value.'</td><td>'.$paid.'</td><td>'.$control.'</td></tr>';
					}
					break;				
				default:
					preME([$name,$v],2);
			}
		}
		$add=(in_array($name,['grades','log']))?'<button class="button button-navy loadME small expanded" data-ref="'.$add_url.'"><i class="fi-plus"></i> Add A New Record</button>':'';
		if($name==='sales'){
			$add='<button class="button button-navy gotoME small expanded" data-ref="'.$add_url2.'"><i class="fi-pencil"></i> Manage Subscription</button>';
		}
		if($log!==''){
			$log='<table class="dataTable"><thead>'.$thead.'</thead><tbody>'.$log.'</tbody></table>';
		}else{
			$log=msgHandler('No records found.',false,false);
		}
		return ['tab'=>ucwords($name),'content'=>$add.$log];
	}
	private function renderLogItem(){
		$mode=$this->ID;
		$id=issetCheck($this->ROUTE,4);
		if($mode==='new'){
			$title='New '.ucwords(str_replace('log','',$this->ACTION)).' Log ';
			$rec=[];
			switch($this->ACTION){
				case 'gradelog':
					$fields=explode(',','GradeSet,'.$this->GRADE_LOCATION.',GradeDate,OtherInfo');
					break;
				case 'memberlog':
					$fields=explode(',','LogSubject,LogDetail');
					break;
				default:
					
			}
			if($fields){
				foreach($fields as $f) $rec[$f]='';
				$format=$this->formatData($rec,'edit');
				$form='<input type="hidden" name="action" value="add_'.$this->ACTION.'">'.PHP_EOL;
				$form.='<input type="hidden" name="MemberID" value="'.$id.'">'.PHP_EOL;
				$form.=implode(PHP_EOL,$format);
				$controls='<button class="button button-olive" type="submit"><i class="fi-plus"></i> Add</button>';
				$content='<form class="ajaxForm" method="post" action="'.$this->PERMLINK.'">'.$form.'<div class="button-group small expanded">'.$controls.'</div></form>';
			}else{
				$content=msgHandler('Sorry, I don\'t know what "'.$this->ACTION.'" is...',false,false);			
			}			
		}else{
			$db=$this->SLIM->db;
			$title='Edit '.ucwords(str_replace('log','',$this->ACTION)).' Log #'.$id;
			switch($this->ACTION){
				case 'gradelog':
					$rec=$db->GradeLog->select('GradeLogID,MemberID,GradeSet,'.$this->GRADE_LOCATION.',GradeDate,OtherInfo')->where('GradeLogID',$id);
					break;
				case 'memberlog':
					$rec=$db->MembersLog->select('MembersLogID,MemberID,LogDate,LogSubject,LogDetail')->where('MembersLogID',$id);
					break;
				default:
					$rec=[];
			}
			if($rec){
				$rec=renderResultsORM($rec);
				$rec=current($rec);
				$member=$this->SLIM->Options->getMember($rec['MemberID']);
				$title.=' <em class="text-dark-blue">'.$member['Name'].'</em>';
				$format=$this->formatData($rec,$mode);
				$form='<input type="hidden" name="action" value="update_'.$this->ACTION.'">'.PHP_EOL;
				$form.='<input type="hidden" name="id" value="'.$id.'">'.PHP_EOL;
				$form.=implode(PHP_EOL,$format);
				$controls='<button class="button button-olive" type="submit"><i class="fi-check"></i> Update</button>';
				$content='<form class="ajaxForm" method="post" action="'.$this->PERMLINK.'">'.$form.'<div class="button-group small expanded">'.$controls.'</div></form>';
			}else{
				$content=msgHandler('Sorry, I could not find that record...',false,false);			
			}
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;		
		if($this->AJAX){
			echo '<style>.reveal .card-section.main {max-height: 100%;overflow-Y: auto;}</style>';
			echo renderCard_active($this->OUTPUT['title'],$content,$this->SLIM->closer);
			die;
		}
	}
	private function saveLogItem(){
		$rq=explode('_',$this->ACTION);
		$act=$rq[0];
		$log=$rq[1];
		$post=$this->REQUEST;
		$id=(int)issetCheck($post,'id');
		$db=false;
		$rec=[];
		unset($post['id'],$post['action']);
		switch($log){
			case 'gradelog':
				$db=$this->SLIM->db->GradeLog;
				unset($post['GradeLogID']);
				if($id) $rec=$db->where('GradeLogID',$id);
				break;
			case 'memberlog':
				$db=$this->SLIM->db->MembersLog;
				if($id){
					$rec=$db->where('MembersLogID',$id);
				}else{
					$post['LogDate']=date('Y-m-d H:i:s');
				}
				break;
		}
		$state=500;
		$mtype='alert';		
		if($db){			
			$res=($rec)?$rec->update($post):$db->insert($post);
			if($res){
				$actx=($act==='add')?'added.':'updated.';
				$msg='Okay the record has been '.$actx;
				$mtype='success';
			}else if(!$this->SLIM->db_error){
				$msg='Okay but no chenges were made.';
				$mtype='primary';
			}else{
				$actx=($act==='add')?'adding':'updating';
				$msg='Sorry, there was a problem '.$actx.' the record.';
			}
		}else{
			$msg='Sorry, I cam\'t do that...';
		}
		return ['status'=>$state,'message'=>$msg,'mtype'=>$mtype,'close'=>true];
	}	
	private function renderDojoMenu(){
		$dojos=$this->OPTIONS['DojoID'];
		$dashlinks='';
		foreach($dojos as $i=>$v){
			$but['color']='navy';
			$but['icon']='target';
			$but['href']=$this->PERMLINK.'dojo/'.$i;
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
	private function renderGradeMenu(){
		$grades=$this->OPTIONS['GradeSet'];
		$dashlinks=[];
		foreach($grades as $i=>$v){
			$but['color']='gold';
			$but['icon']='trophy';
			$but['href']=$this->PERMLINK.'grades/'.$i;
			$but['caption']=$v['OptionName'];
			$dashlinks[$v['sortkey']]=$this->SLIM->zurb->adminButton($but);
		}
		ksort($dashlinks);
		$dashlinks=implode('',$dashlinks);
		$close=($this->AJAX)?$this->SLIM->closer:'';
		$title='Grades';
		$content=renderCard_active($title,$dashlinks,$close);
		if($this->AJAX){
			echo $content;
			die;
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;		
	}
	private function renderGenderMenu(){
		$grades=$this->OPTIONS['Sex'];
		$dashlinks=[];
		$icons=['torso text-dark-blue','torso-female text-dark-blue','torsos-all-female text-lavendar','torsos-all text-orange','torsos-all text-maroon'];
		foreach($grades as $i=>$v){
			$but['color']='';
			$but['icon']=$icons[$i];
			$but['href']=$this->PERMLINK.'list/'.strtolower($v).'/active';
			$but['caption']=$v;
			$dashlinks[$i]=$this->SLIM->zurb->adminButton($but);
		}
		ksort($dashlinks);
		$dashlinks=implode('',$dashlinks);
		$close=($this->AJAX)?$this->SLIM->closer:'';
		$title='Gender';
		$content=renderCard_active($title,$dashlinks,$close);
		if($this->AJAX){
			echo $content;
			die;
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;		
	}

	private function renderListMenu(){
		$what=issetCheck($this->ROUTE,3);
		if(!$what || !is_string($what)) $what='active';
		$_data['active']=array('color'=>'dark-green','caption'=>'Active<br/>Members','content'=>'view active members','href'=>$this->PERMLINK.'list/active','icon'=>'check');
		$_data['inactive']=array('color'=>'gray','caption'=>'Inactive<br/>Members','content'=>'view inactive members','href'=>$this->PERMLINK.'list/inactive','icon'=>'x');
		$_data['former']=array('color'=>'maroon','caption'=>'Former<br/>Members','content'=>'view former members','href'=>$this->PERMLINK.'list/former','icon'=>'alert');
		$_data['banned']=array('color'=>'red','caption'=>'Banned<br/>Members','content'=>'view banned members','href'=>$this->PERMLINK.'list/banned','icon'=>'prohibited');
		$_data['dojo']=array('color'=>'navy','caption'=>'By<br/>Dojo','content'=>'view members by dojo','href'=>$this->PERMLINK.'dojo_menu','icon'=>'target','load'=>true);
		$_data['grade']=array('color'=>'gold','caption'=>'By<br/>Grade','content'=>'view members by grade','href'=>$this->PERMLINK.'grade_menu','icon'=>'trophy','load'=>true);
		$_data['grade']=array('color'=>'dark-blue','caption'=>'By<br/>Gender','content'=>'view members by gender','href'=>$this->PERMLINK.'gender_menu','icon'=>'torsos-male-female','load'=>true);
		$dashlinks='';
		foreach($_data as $i=>$v){
			$color=issetCheck($v,'color','navy');
			$but['color']=$color;
			$but['icon']=issetCheck($v,'icon','widget');
			$but['href']=issetCheck($v,'href','#nogo');
			$but['caption']=issetCheck($v,'caption','okay');
			$but['content']=issetCheck($v,'content','???');
			$but['load']=issetCheck($v,'load');
			$dashlinks.=$this->SLIM->zurb->adminButton($but);
		}
		$close=($this->AJAX)?$this->SLIM->closer:'';
		$title='Members Lists';
		$content=renderCard_active($title,$dashlinks,$close);
		if($this->AJAX){
			echo $content;
			die;
		}
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;		
	}
	private function renderListName(){
		$name='';
		$filt=($this->FILTER==='active')?' - Active':' - All';
		$filtb=($this->FILTER==='active')?'Active - ':'All - ';
		if($this->ACTION==='dojo'){
			$d=$this->getOption('DojoID',$this->ID);
			if($d) $name=$d['LocationName'].$filt;
		}else if($this->ACTION==='grades'){
			$d=$this->getOption('GradeSet',$this->ID);
			if($d) $name=$d['OptionName'].$filt;
		}else{
			$name=($this->ID)?ucME($this->ID).' ':'';
			if($this->ACTION==='list') $name=$filtb.$name;
		}
		return $name;
	}	
	private function renderListItems(){
		$count=0;
		$sort=1;
		$listname='';
		if($this->DATA){
			$can_update=($this->LEADER)?hasAccess($this->USER,'members','update'):true;
			$tbl=[];
			$gsort=['Grade'=>[]];
			$listname=$this->renderListName();
			foreach($this->DATA as $i=>$dat){
				$dat=$this->formatData($dat,'view');
				$mode=($can_update)?'edit':'view';
				$modei=($can_update)?'<i class="fi-pencil"></i> Edit':'<i class="fi-eye"></i> View Info.';
				$bd=explode(' ',$dat['Birthdate']);
				$tbl[$i]=array(
					'ID'=>$i,
					'Name'=>$this->LIB->get('fullname',$dat),
					'Gender'=>$dat['Gender'],
					'Birthdate'=>$bd[0],
					'Grade'=>$dat['Grade'],
					'Grade Date'=>$dat['GradeDate'],
					'Dojo'=>$dat['DojoID'],
					'Status'=>$dat['Disable'],
					'Controls'=>'<button class="button button-dark-purple small loadME" data-size="large" data-ref="'.$this->PERMLINK.$mode.'/'.$i.'">'.$modei.'</button>'
				);
				$gsort['Grade'][$i]=$sort;
				$count++;
				$sort++;
			}
			$args['data']['data']=$tbl;
			$args['sort_data']=$gsort;
			$args['before']='filter';
			$list=dataTable($args);
		}else{
			$list=msgHandler('No member records found...',false,false);
		}
		$icon='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
		$this->OUTPUT=array(
			'title'=>$listname.' Members: <span class="subheader">('.$count.')</span>',
			'content'=>$list,
			'icon'=>$icon,
			'menu'=>array('right'=>$this->renderContextMenu()),
		);
		if($this->AJAX){
			$full='<div class="button-group small expanded"><button class="button gotoME button-dark-blue small" data-ref="'.$this->PERMLINK.'dojo/'.$this->ID.'"><i class="fi-male-female"></i> View Fullscreen</button>';
			if($this->USER['access']>=25) $full.='<button class="button small button-navy gotoME" title="email members" data-ref="'.$this->PERMBACK.'mailer/add/dojo/'.$this->ID.'" type="button"><i class="fi-mail"></i> Email Members</button>';
			$full.='</div>';

			echo renderCard_active($this->OUTPUT['title'],$this->OUTPUT['content'].$full,$this->SLIM->closer);
			echo '<script>$(".reveal .modal-body").foundation();</script>';
			die;
		}
	}
	private function formatData($data,$mode=false){
		$fix=array();
		foreach($data as $i=>$v){
			$val=$v;
			switch($i){
				case 'Access':
				case 'Status':
				case 'MemberTypeID': case 'DojoID': case 'Language': case 'Dead': case 'zasha': case 'Disable': case 'Sex': case 'nonuk':
				case 'LocationID':case 'GradeSet':
					if($mode==='view'){
						$tmp=$this->getOption($i,$v);
						
						if($i==='DojoID'){
							$val=issetCheck($tmp,'ShortName',$v);
						}else if(is_array($tmp)){
							$val=issetCheck($tmp,'OptionName',$v);
							if($val===$v) $val=issetCheck($tmp,$v,$v);
							if($i==='Sex') $fix['Gender']=$val;
						}else{
							$val=($tmp)?$tmp:$v;
							if($i==='Disable'){
								$val=$this->SLIM->StatusColor->render('member_status',$val);
							}
						}
					}else if($mode==='edit'){
						$val=$this->getSelectOptions($i,$v);
						$val=$this->renderFormPart($i,$val);
					}else{
						$val=($i==='Language')?$v:(int)$v;
					}
					break;
				case 'Birthdate': case 'DateJoined':case 'CGradedate':case 'GradeDate':
					if($mode){
						if(!is_string($val)) $val.='';
						$tmp=explode(' ',$val);
						$val=$tmp[0];
						if($mode==='edit') $val=$this->renderFormPart($i,$val);
					}
					break;
				case 'MembersLog': case 'SalesLog': case 'EventsLog':case 'GradeLog':
					//skip
					break;
				case 'Meta':
				    $meta=$this->LIB->get('meta_render',['meta'=>$v,'mode'=>$mode]);
				    $fix+=$meta;
					$i=false;
					break;					
				default:
					if($mode==='edit') $val=$this->renderFormPart($i,$val);
			}
			if($i) $fix[$i]=$val;
		}
		return $fix;
	}
	private function renderFormPart($i,$v){
		$form=[];
		$label=camelTo($i);
		$hide=false;
		switch($i){
			case 'MemberID':case 'GradeLogID':case 'MembersLogID':
				//hidden					
				$val='<input type="hidden" name="'.$i.'" value="'.$v.'"/>';
				$hide=true;
				break;
			case 'helpbot_help': case 'helpbot_trigger': case 'TimeStamp': case 'TimeStamp': case 'PGrade': case 'PGradeDate': case 'CurrentGrade': case 'Dues': case 'CGradeLoc2':
			case 'MembersLog': case 'SalesLog': case 'EventsLog':case 'GradeLog':
			case 'CGradeName': case 'CGradeLoc1':case 'CGradedate': case 'Dojo':
				//skip
				return '';
				break;
			case 'CGradeNamex':
				//disabled or viewable only
				$val='<input type="text" disabled value="'.$v.'"/>';
				$label=str_replace('C ','',$label);
				$val='<input type="text" disabled value="'.$v.'"/>';
				break;
			case 'Access':
			case 'Status':
			case 'MemberTypeID': case 'DojoID': case 'Language': case 'Dead': case 'zasha': case 'Disable': case 'Sex': case 'nonuk':
			case 'LocationID':case 'GradeSet':
				//select
				$val='<select name="'.$i.'">'.$v.'</select>';
				$label=str_replace(' ID','',$label);
				if($label==='Disable') $label='Status';
				break;
			case 'Birthdate': case 'DateJoined':case 'CGradedate':case 'GradeDate':
				//date
				$val='<input type="date" name="'.$i.'" value="'.$v.'"/>';
				break;
			case 'LogDate':
				//datetime
				$val='<input type="text" value="'.$v.'" disabled/>';
				break;
			case 'Address':case 'OtherInfo':case 'LogDetail':
				//textarea
				$val='<textarea name="'.$i.'" rows="4">'.$v.'</textarea>';
				break;
			default:
				//text
				if(is_array($v)) preME([$i,$v],2);
				if($label==='Ankf ID') $label='IKYF ID';
				$val='<input type="text" name="'.$i.'" value="'.$v.'"/>';
		 }
		 $part=($hide)?$val:'<label for="'.$i.'">'.$label.'</label>'.$val;
	     return $part;	
	}
	private function getUserInfo($mid=0){
		if(!$mid) return [];
		$rec=$this->SLIM->db->Users->select('id,Name,Username')->where('MemberID',$mid);
		if($rec=renderResultsORM($rec)){
			$rec=current($rec);
			return $rec;
		}
		return [];		
	}
	private function getMetaInfo($mid=0){
		if(!$mid) return [];
		$recs=$this->SLIM->db->Meta->select('MetaID,MetaKey,MetaValue')->where(['MetaType'=>'member','MetaItemID'=>$mid]);
		if($recs=renderResultsORM($recs,'MetaID')){
			return $recs;
		}
		return [];		
	}
	private function getOption($key,$val=false){
		$o=issetCheck($this->OPTIONS,$key);
		if($o){
			if($val|| is_numeric($val)){
				return issetCheck($o,$val);
			}
		}
		return $o;
	}
	private function getSelectOptions($key,$val=false){
		$h='';
		$name_val=($key==='Sex')?true:false;
		if($o=issetCheck($this->OPTIONS,$key)){
			if($key==='LocationID') $h='<option>* not set *</option>';
			foreach($o as $i=>$v){
				$lbl=$v;
				if(is_array($v)){
					$lbl=issetCheck($v,'OptionName');
					if(!$lbl) $lbl=issetCheck($v,'LocationName');
					if(is_array($lbl)) preME([$i,$lbl],2);
					if($key==='DojoID'){
						$lbl=$v['ShortName'].' - '.$lbl;
					}
				}

				$value=($name_val)?$lbl:$i;
				$sel=($val==$value)?'selected':'';
				$h.='<option value="'.$value.'" '.$sel.'>'.$lbl.'</option>';				
			}
		}else{
			$h='<option>no options for '.$key.'</option>';
		}
		return $h;
	}
	private function renderDownloadData(){
		$type=issetcheck($this->ROUTE,3);
		$ref=issetcheck($this->ROUTE,4);
		$filter=issetcheck($this->ROUTE,5,'active');
		if(!in_array($type,['id','dojo','grade'])) $ref=false;
		$this->LIB->DOWNLOAD=true;
		$data=$this->LIB->get($type,$ref,$filter);
		if($data){
			$filename=($type==='list')?'all_members':'members_by_'.$type.'_'.$ref;
			$filename.='_'.$filter;
			$this->SLIM->Download->go($data,$filename);
		}
		die;
		
	}
	private function getMembers($type=false){
		if($this->FIND){
			$ref=$this->FIND;
			$filter=false;
		}else{
			$ref=(in_array($type,['id','dojo','grade']))?$this->ID:false;		
			$filter=($type!==$this->FILTER)?$this->FILTER:false;
		}
		return $this->LIB->get($type,$ref,$filter);
	}
    public function getMember($id=0) {
		if(!$id) $id=$this->ID;
		$r['ref']=$id;
		$r['locked']=0;
		if((int)$id){
			if($this->CONFIRM_UPDATES){
				/*
				$bk=$this->checkBackup($id);
				if($bk){
					if($this->USER['access']>=25){
						return $this->renderConfimUserChanges($id);
					}else{
						$r['locked']=1;
					}
				}
				*/
			}
			$dsp=$this->DATA;
			if($info=$this->LIB->get('info',$id)){
				$dsp+=$info;
			}
			//set dojo name
			if((int)$dsp['DojoID']>0){
				if(!$dsp['Dojo']||$dsp['Dojo']===''){
					$dsp['Dojo']=$this->SLIM->Options->get('dojos_name',$dsp['DojoID']);
				}
			}
			//set grade name
			if(!$dsp['CGradeName']||$dsp['CGradeName']===''){
				$mgd=(int)$dsp['CurrentGrade'];
				if($mgd){
					$gd=$this->SLIM->Options->get('grade_name',$mgd);
					if($gd) $dsp['CGradeName']=$gd['OptionName'];
				}else{
					$dsp['CGradeName']='mu-dan';
				}
			}
			//set age
			$dsp['Age']=getAge($dsp['Birthdate']);
			//help bot
			if($help=$this->SLIM->Options->get('help','edit_member')){
				$dsp['helpbot_help']=$help['help'];
				$dsp['helpbot_trigger']=$help['trigger'];
			}else{
				$dsp['helpbot_help']=$dsp['helpbot_trigger']=false;				
			}
			$r['data']=$dsp;
			$r['status']=200;
        }else if ($id==='new'){
			$fields=$this->LIB->get('fields');
			$dsp['id']=0;//for js
			$valid=array('MemberID', 'FirstName', 'LastName', 'DojoID','Sex','DateJoined','Address');
			foreach($fields as $i=>$v){
				if(in_array($i,$valid)){
					if($v['type']==='int'||$v['type']==='tinyint'){
						$val=0;
					}else if($v['type']==='datetime'){
						$val=date('Y-m-d 00:00:00');
					}else{
						$val='';
					}
					$dsp[$i]=$val;
				}
			}
			$r['data']=$dsp;
			$r['status']=200;
		}else{
			$r['message']='Sorry, no ID supplied...';
		}
        return $r;
    }

	private function renderSearch_form($hidden=false){
		if($this->CHIP_SEARCH){
			$form='<form class="ajaxForm" method="get" action="'.$this->PERMLINK.'search/"><input type="hidden" name="action" value="search"/><div class="input-group"><input class="input-group-field" id="searcher" name="findME" type="text" placeholder="keyword Name or Email(eg. stephan)"><div class="input-group-button"><button type="submit" class="submitSearch button button-dark-blue"><i class="fi-magnifying-glass"></i></button></div></div></form>';
		}else{
			$form='<form method="get" action="'.$this->PERMLINK.'search/"><div class="input-group"><input class="input-group-field" id="searcher" name="findME" type="text" placeholder="keyword Name or Email(eg. stephan)"><div class="input-group-button"><button type="submit" class="submitSearch button button-dark-blue"><i class="fi-magnifying-glass"></i></button></div></div></form>';
		}
		$form.='<script>setTimeout(function(){document.getElementById("searcher").focus();},1000);</script>';
		if($hidden){
			$contents=renderCard_active('Search: <span class="subheader">Users</span>',$form,$this->SLIM->closer);
			$anim='data-animation-in="slide-in-up" data-animation-out="slide-out-down"';
			$form='<div id="user-search" class="reveal" data-reveal '.$anim.'>'.$contents.'</div>';
		}
		return $form;
	}
	
	private function renderFindMembers(){
		if($this->FIND){
			//determine search prams
			if($this->CHIP_SEARCH){
				$this->renderChipItems();
			}else{
				$this->renderListItems();
			}
			// not ajax
			$table=$this->OUTPUT['content'];
			$ct=0;
			$title='Members: <span class="subheader">Search Results</span>';
			$out['item_title']='Search Results';
			$content='';
			$content.='<h3>Results for: <span class="subheader">'.$this->FIND.' ('.$ct.')</span></h3>';
			$content.=$table;
		}else{
			$title='Find Members';
			$content=$this->renderSearch_form();
			if($this->AJAX){
				echo renderCard_active($title,$content,$this->SLIM->closer);
				die;
			}
		}
		$menu=$this->renderContextMenu();
		$this->OUTPUT['title']=$title;
		$this->OUTPUT['content']=$content;
		$this->OUTPUT['menu']=array('right'=>$menu);
	}
	private function updateMemberGrade($mid=0){
		$done=false;
		if(!$mid) $mid=issetCheck($this->REQUEST,'MemberID',0);
		if(!$mid) return $done;
		$rec=$this->SLIM->db->GradeLog->select('GradeLogID,MemberID,GradeSet,Location,Location2,GradeDate')->where('MemberID',$mid)->order('GradeSet DESC,GradeDate DESC')->limit(1);
		$rec=renderResultsORM($rec);
		if($rec){
			$rec=current($rec);
			$gd='mu-dan';
			$grades=$this->LIB->get('grades');
			$g=issetCheck($grades,$rec['GradeSet']);
			if($g) $gd=$g['OptionName'];
			$update=['CGradeName'=>$gd,'CurrentGrade'=>$rec['GradeSet'],'CGradeLoc1'=>$rec['Location'],'CGradeLoc2'=>$rec['Location2'],'CGradedate'=>$rec['GradeDate']];
			if($member=$this->SLIM->db->Members->where('MemberID',$mid)){
				$chk=$member->update($update);
				if($chk) $done=true;
			}
		}
		return $done;		
	}
	private function renderChipItems(){
		$count=0;
		$sort=1;
		$listname=$js='';
		if($this->DATA){
			$can_update=($this->LEADER)?hasAccess($this->USER,'members','update'):true;
			$tbl=[];
			$gsort=['Grade'=>[]];
			$listname=$this->renderListName();
			foreach($this->DATA as $i=>$dat){
				$dat=$this->formatData($dat,'view');
				$mode=($can_update)?'edit':'view';
				$modei=($can_update)?'<i class="fi-pencil"></i>':'<i class="fi-eye"></i>';
				$tbl[$i]='<a title="'.$mode.' this" class="chip button-white text-black loadME" data-ref="'.$this->PERMLINK.$mode.'/'.$i.'">'.$this->LIB->get('fullname',$dat).' ('.$dat['DojoID'].') '.$modei.'</a>';
				$count++;
			}
			$filter='<div class="input-group"><span class="input-group-label">Filter</span><input class="input-group-field filter" type="text" id="chip_filter"></div>';
			$list='<div class="chip-section">'.$filter.'<div id="chips" class="chip-cloud">'.implode('',$tbl).'</div></div>';
			$js='myFilter("#chip_filter","#chips .chip");';
		}else{
			$list=$this->renderSearch_form();
			$list.=msgHandler('No records found...',false,false);
		}
		$icon='<i class="fi-'.$this->PLUG['icon'].' icon-x1b"></i>';
		$this->OUTPUT=array(
			'title'=>'Members Search Results: <span class="subheader">('.$count.')</span>',
			'content'=>$list,
			'icon'=>$icon,
			'menu'=>array('right'=>$this->renderContextMenu()),
		);
		if($this->AJAX){
			$c=renderCard_active($this->OUTPUT['title'],$this->OUTPUT['content'],$this->SLIM->closer);
			$c.='<script>$(".reveal .modal-body").foundation();'.$js.'</script>';
			if($this->METHOD==='POST'){
				$o=['status'=>200,'contents'=>$c,'element'=>'#zurbModal' ,'type'=>'swap','message'=>'Search complete, '.$count.' found','message_type'=>'success'];
				echo jsonResponse($o);
			}else{
				echo $c;
			}
			die;
		}else{
			$this->SLIM->assets->set('js',$js,'chip_filter');
		}
	}
	
	private function renderFixup(){
		//fix grade log locations
		$locs=$this->SLIM->db->GradeLog->where("LocationID > 0 AND (Location = '' OR Location IS NULL)")->select('GradeLogID,LocationID,Location');
		$locs=renderResultsORM($locs,'GradeLogID');
		$ct=0;
		foreach($locs as $i=>$v){
			if($name=$this->getOption('LocationID',$v['LocationID'])){
				$upd=['Location'=>$name['LocationName'],'Location2'=>$name['LocationCountry']];
				$rec=$this->SLIM->db->GradeLog->where('GradeLogID',$i);
				if(count($rec)==1){
					if($chk=$rec->update($upd)) $ct++;
				}else{
					preME([$v,$upd,(string)$rec],2);
				}
			}
		}
		preME($ct.' of '.count($locs).' locations updated',2);
	}

}
