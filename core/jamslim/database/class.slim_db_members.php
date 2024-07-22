<?php

class slim_db_members extends slim_db_common{
	var $TABLE='Members';
	
	//custom vars
	private $SELECTION=[
		'default'=>"MemberID AS id, MemberID, FirstName, LastName, DojoID, CGradeName as Grade,CGradeDate AS GradeDate,Birthdate,Sex AS Gender,CurrentGrade,Disable",
		'report_gen'=>"MemberID AS id, MemberID, FirstName",
		'info'=>"MemberID AS id, MemberID, FirstName, LastName, DojoID,CGradeName",
		'grade_dump'=>"MemberID AS id, MemberID, FirstName, LastName, Dojo, CGradeName as Grade,CurrentGrade,CGradeDate,Birthdate",
		'full_report'=>"MemberID AS id, MemberID, FirstName, LastName, DojoID, CGradeName as Grade,CGradeDate AS GradeDate,AnkfID,zasha,NameInJapanese,NameInJapanese2,Birthdate,Sex AS Gender,Language,Email,LandPhone AS Phone,Address,City,PostCode",
	];
	private $META_KEYS=['citizenship','date_began_kyudo','payment_method','archery_member_id','teaching_rank','years_practiced','practice_location'];
	private $PRODUCTS;
	private $PROD_ID;
	private $STATUS_FILTER;
	private $SHINSA_REF;
	private $GRADES;
	private $SUBS_GROUP_ID;
	private $MEMB_CAT_ID;
	var $DOWNLOAD;//download flag
	var $ACTIVE_ONLY=true;
	var $GRADE_DUMP=false;//used for checking the grade sort
	var $MEMBER_SORT='GradeSort';
	var $MEMBER_ORDER=1;
	var $CONFIRM_UPDATES;
	var $FULLNAME_FORMAT=['LastName','FirstName'];	

	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
		$this->SHINSA_REF=$slim->Options->get('shinsa_ref','strip');
		$this->GRADES=$slim->Options->get('grades_val');
	}
	public function get($what=false,$args=null,$filter=false){
		$this->STATUS_FILTER=$filter;
		switch($what){
			case 'new':
				$data=$this->getMember('new');
				break;
			case 'fullname':
				$data=$this->getFullname($args);
				break;
			case 'details':
				$data=$this->getMemberDetails($args);
				break;			
			case 'selector':
				$data=$this->getMemberSelector();
				break;			
			case 'member': case 'id': 
				$data=$this->getMember($args);
				break;
			case 'members':
				$data=$this->getMembers($args);
				break;
			case 'active': case'inactive': case'former': case'banned':case 'all':
				$data=$this->getMembers(['list'=>$what]);
				break;
			case 'list'://list all members
				$filter=($filter!=='inactive')?'active':'inactive';
				$data=$this->getMembers(['list'=>$filter]);
				break;
			case 'info':
				$data=$this->getMemberInfo($args);
				break;
			case 'by_name':
				$f=issetCheck($args,'FirstName');
				$l=issetCheck($args,'LastName');
				$whr=[];
				if(trim($f)!=='') $whr['FirstName']=$f;
				if(trim($l)!=='') $whr['LastName']=$l;
				$data=($whr)?$this->getMembersByName($whr):[];
				break;
			case 'by':
				$f=issetCheck($args,'field');
				$v=issetCheck($args,'value');
				$data=$this->getMembersBy($f,$v);
				break;
			case 'male': case 'female': case 'nogender': case 'other':case 'trans':case 'nonbinary':
				$tmp=($what==='nogender')?'0':ucwords($what);
				$data=$this->getMembersBy('Sex',$tmp);
				break;
			case 'dojo':
				$data=$this->getMembersBy('DojoID',$args);
				break;
			case 'grade':
				$data=$this->getMembersBy('CurrentGrade',$args);
				break;
			case 'grades':
				$data=$this->GRADES;
				break;
			case 'fields':
				$data=$this->FIELDS;
				break;
			case 'primary':
				$data=$this->PRIMARY;
				break;
			case 'meta_keys':
				$data=$this->META_KEYS;
				break;
			case 'meta_data':
				$data=$this->getMemberMeta($args);
				break;
			case 'meta_render':
				$data=$this->renderMetaValues($args);
				break;
			case 'current_membership':
				$data=$this->getCurrentMembership($args);
				break;
			case 'current_ikyf':
				$data=$this->getCurrentIKYF($args);
				break;
			case 'current_akr':
				$data=$this->getCurrentAKR($args);
				break;
			case 'subs_group_id':
				$data=$this->SUBS_GROUP_ID;
				break;
			case 'memb_cat_id':
				$data=$this->MEMB_CAT_ID;
				break;
			case 'search':
				$data=$this->getMembers(['list'=>'search','vars'=>$args]);
				break;
			default:
				$data=[];				
		}
		if(!$data){
			if($this->ERR){
				$data=$this->ERR;
			}
		}
		return $data;
	}
    private function getMembers($args=[]){
		$this->init();
		$full=array('active_full','dojo_active_full','members_inactive_full','inactive_full');
		$table=$vars=$fields=false; $list='all';
		extract($args);
		//select
		if($this->GRADE_DUMP){
			$select=$this->SELECTION['grade_dump'];
		}else if($fields){
			$select=$fields;
		}else if(in_array($list,$full)){
			$select=$this->SELECTION['full_report'];
			$list=str_replace('_full','',$list);
		}else{
			$select=issetCheck($this->SELECTION,$list,$this->SELECTION['default']);
			if($this->DOWNLOAD) $select.=',MemberTypeID';
		}
		//run
        $recs=$this->DB->Members->select($select);
        switch($list){
			case 'search':
				if(strlen($vars)>2){
					$vars="%$vars%";
					$w='FirstName LIKE ? OR LastName LIKE ? OR Email LIKE ?';
					$recs->where($w,$vars,$vars,$vars);
				}else{
					return [];
				}				
				break;
			case 'active':
				$recs->where('Disable',0)->and('Dead',0);
				break;
			case 'former':
				$recs->where('MemberTypeID',6);
				break;
			case 'banned':
				$recs->where('MemberTypeID',7);
				break;
			case 'type':
				$vars=(int)$vars;
				if(!$vars) $vars=1;
				$recs->where('MemberTypeID',$vars);
				break;
			case 'disabled':
			case 'inactive':
				$recs->where('Disable',1);
				break;
			case 'pending':
				$recs->where('Disable',2);
				break;
			case 'dead':
			case 'sleeping':
				$recs->where('Dead',1);
				break;
			case 'nostatus':
				$recs->where('Disable IS NULL');
				break;
			case 'uk':
				$recs->where('nonuk',0);
				if($this->ACTIVE_ONLY) $recs->and('Disable',0)->and('Dead',0);
				break;
			case 'male':
			case 'female':
			case 'nogender':
				if($list==='nogender') $list=null;
				$recs->where('Sex',$list)->and('Disable',0)->and('Dead',0);
				break;
			case 'grade':
				if($vars==='nograde') $vars=null;
				$recs->where('CurrentGrade',$vars)->and('Disable',0)->and('Dead',0);
				break;
			case 'name':
				$recs->where(array('FirstName'=>$vars['FirstName'],'LastName'=>$vars['LastName']));
				break;
			case 'nonuk':
				$recs->where('nonuk',1);
				if($this->ACTIVE_ONLY) $recs->and('Disable',0)->and('Dead',0);
				break;
			case 'email':
				$recs->where('Email',$vars);
				break;
			case 'info':
				$recs->where("MemberID", (int)$vars);
				return;
				break;
			case 'dojo':
			case 'dojo_active':
				$vars=(int)$vars;
				if(!empty($this->DOJO_LOCK)){
					if(in_array($vars,$this->DOJO_LOCK)){
						$recs->where("DojoID", $vars);
					}else{
						return false;
					}
				}else{
					$recs->where("DojoID", $vars);
				}
				if($list==='dojo_active'||$this->ACTIVE_ONLY) $recs->and('Disable',0)->and('Dead',0);
				break;
			case 'search':
				if(strlen($vars)>2){
					$vars="%$vars%";
					$w='FirstName LIKE ? OR LastName LIKE ? OR Email LIKE ?';
					$recs->where($w,$vars,$vars,$vars);
					if($this->ACTIVE_ONLY) $recs->and('Disable',0)->and('Dead',0);
				}else{
					return false;
				}
				break;
			case 'report':
			case 'report_gen':
				if(is_array($vars)){
					//build 'AND' query
					$ct=0;
					foreach($vars as $i=>$v){
						if($ct==0){
							$recs->where($i,$v);
						}else{
							$recs->and($i,$v);
						}
						$ct++;
					}
					//set options
					if(is_array($opts)){
						foreach($opts as $i=>$v){
							switch($i){
								case 'sortby':
									$desc=(issetCheck($opts,'sortdesc'))?' DESC':'';
									$recs->order($v.$desc);
									break;
								case 'groupby':
									$recs->group($v);
									break;								
							}
						}
					}
				}else{
					return false;
				}
				break;
			case 'all':
			default://all
				if($list && $list!=='all'){
					return false;
				}else{
					//ignore
				}
		}
 		//dojo lock
 		$recs=$this->setDojoLock($recs,$list);
		//filter by status -acive/inactive
		$recs=$this->setStatusFilter($recs);
		//sorting
		if($this->MEMBER_SORT){
			if($this->MEMBER_SORT==='GradeSort'){
				$recs->order('CurrentGrade DESC, CGradedate ASC, Birthdate ASC');
			}else{
				$recs->order($this->MEMBER_SORT.' '.$this->MEMBER_ORDER);
			}
		}
        $rez=renderResultsORM($recs,'id');
        if(!$rez) return [];
 		//fix field values
 		$rez=$this->fixFieldValues($rez);
		if($this->DOWNLOAD){
			return $this->formatDownload($rez);
		}else{
			return $rez;
		}
    }
    private function formatDownload($rez=[]){
		$rez=$this->fixFieldValues($rez);
		$dojos=$this->SLIM->Options->get('dojos');
		$member_types=$this->SLIM->Options->get('membertype');
		$ct=1;
		$download=$head=[];
		foreach(array_keys($rez) as $i){
			$v=$rez[$i];
			$dj=issetCheck($dojos,$v['DojoID']);
			$type=issetCheck($member_types,$v['MemberTypeID']);
			$v['GradeSort']=$ct;
			$v['DojoName']=($dj)?$dj['LocationName']:'???';
			$v['Status']=($v['Disable'])?'Inactive':'Active';
			$v['MemberType']=($type)?$type['OptionName']:'???'.$v['MemberTypeID'];
			unset($v['id'],$v['Disable'],$v['MemberTypeID']);
			if(!$head){//add header row
				foreach(array_keys($v) as $k) $head[]=camelTo($k);
				$download[0]=$head;
			}
			$download[$i]=$v;
			$ct++;			
		}
		return $download;		
	}
    private function fixFieldValues($rez=[]){
		foreach(array_keys($rez) as $i){
			$v=$rez[$i];
			if(array_key_exists('Grade',$v)){
				if(!$v['Grade'] && $v['CurrentGrade']){
					$d='mu-dan';
					$g=issetCheck($this->GRADES,$v['CurrentGrade']);
					if($g) $d=$g['OptionName'];
					$rez[$i]['Grade']=$d;
				}
			}
			$dates=['GradeDate','CGradedate','Birthdate','DateJoined'];	
			foreach($dates as $d){		
				if(array_key_exists($d,$v)){
					$chk=issetCheck($v,$d);
					if($chk=issetCheck($v,$d)) $rez[$i][$d]=validDate($chk);
				}
			}
			if(array_key_exists('Gender',$v)){
				if(!$v['Gender']||$v['Gender']==='null') $rez[$i]['Gender']='- not set -';
			}
			//add missing
			if(!array_key_exists('Grade',$v)) $rez[$i]['Grade']=$v['CGradeName'];
			if(!array_key_exists('GradeDate',$v))$rez[$i]['GradeDate']=$v['CGradedate'];
			if(!array_key_exists('Gender',$v)) $rez[$i]['Gender']=$v['Sex'];				
		}
		return $rez;
	}
    private function getMember($ref=0){
		$this->init();
		if($ref==='new'){
			$dsp['id']=0;//for js
			foreach($this->FIELDS as $i=>$v){
				if($v['type']==='int'||$v['type']==='tinyint'){
					$val=0;
				}else if($v['type']==='datetime'){
					$val=date('Y-m-d 00:00:00');
				}else{
					$val='';
				}
				$dsp[$i]=$val;
			}
			return $dsp;
		}
		$ref=(int)$ref;
		$r['ref']=$ref;
		$r['locked']=0;
		if(!$ref) return [];
		if($this->CONFIRM_UPDATES){
			/*
			$bk=$this->checkBackup($ref);
			if($bk){
				if($this->USER['access']>=25){
					return $this->renderConfimUserChanges($ref);
				}else{
					$r['locked']=1;
				}
			}
			*/
		}
        $opts['dbo']=$this->DB->Members;
        $opts['table']=$this->TABLE;
        $opts['primary']=$this->PRIMARY;
		$opts['fields']=$this->FIELDS;
		$opts['id']=$ref;			
		$ob=new slim_db_record($opts);
        return $ob->get('display');
    }
    private function getMemberSelector(){
		$recs=$this->DB->Members->select('MemberID,FirstName,LastName,CGradeName,Dojo')->order('CurrentGrade DESC,Birthdate ASC');
 		//dojo lock
 		$recs=$this->setDojoLock($recs);
		$recs=renderResultsORM($recs,'MemberID');
		$o=[];
		if($recs){
			foreach($recs as $i=>$v){
				$o[$i]=$this->getFullname($v).' : '.$v['CGradeName'].' : '.$v['Dojo'];
			}
		}
		return $o;
	}
    private function getMemberDetails($id=0){
		$m=$this->getMember($id);
		$o=[];
		if($m){
			$f=$this->fixFieldValues([$id=>$m]);
			$m=current($f);
			$keys=['FirstName', 'LastName','Email','Birthdate','Dojo','CGradeName'];
			foreach($keys as $k){
				$o[$k]=issetCheck($m,$k);
				switch($k){
					case 'LastName':
						$o['Fullname']=$this->getFullname($o);
						$o['SelectName']=$o['Fullname'].' : '.$m['CGradeName'].' : '.$m['Dojo'];
						$o['Normalname']=$o['FirstName'].' '.$o['LastName'];
						break;
					case 'BirthDate':
						$o['Age']=getAge($o[$k]);
						break;
				}
				
			}
		}
		return $o;
	}
	private function getFullname($rec=[]){
		$name='';
		if(!$rec || !is_array($rec)) return $name;
		foreach($this->FULLNAME_FORMAT as $k){
			$name.=issetCheck($rec,$k).' ';
		}
		return trim($name);
	}
	private function getMemberInfo($id=0){
		if(!$id) return [];
		$dsp['MembersLog']=$this->getMemberLog($id);
		$dsp['GradeLog']=$this->getMemberGrades($id);
		$dsp['EventsLog']=$this->getMemberEvents($id);
		$dsp['SalesLog']=$this->getMemberSales($id);
		$dsp['Meta']=$this->getMemberMeta($id);
		return $dsp;
	}
   public function getMemberEvents($id=0){
		$out=[];
		if($id){
			//event log
			$eid=array();
			$recs=$this->DB->EventsLog->where("MemberID", $id)->order('EventID DESC');
			if($elog=renderResultsORM($recs,'EventLogID')){
				//events
				foreach($elog as $i=>$v) $eid[$v['EventID']]=$v['EventID'];
				$recs=$this->DB->Events->where("EventID", $eid);
				$events=renderResultsORM($recs,'EventID');
				if($events){
					foreach($elog as $i=>$v){
						$event=$events[$v['EventID']];
						$id=$v['EventLogID'];
						$out[$id]['EventLogID']=$v['EventLogID'];
						$out[$id]['EventID']=$event['EventID'];
						$out[$id]['EventName']=$event['EventName'];
						$out[$id]['EventDate']=$event['EventDate'];
						$out[$id]['EventType']=$event['EventType'];
						$out[$id]['EventCost']=$v['EventCost'];
						$out[$id]['Attending']=$v['Attending'];
						$out[$id]['Paid']=$v['Paid'];
						$out[$id]['PaymentAmount']=$v['PaymentAmount'];
						$out[$id]['PaymentDate']=$v['PaymentDate'];
						$out[$id]['Shinsa']=$v['Shinsa'];
						$out[$id]['ProductID']=$v['ProductID'];
						$out[$id]['AdditionalFee']=$v['AdditionalFee'];
					}
				}
			}
		}
		return $out;
	}
    public function getMemberGrades($id=0){
		$out=[];
		if($id){
			$res=$this->DB->GradeLog->where("MemberID", $id)->order('GradeDate DESC');
			$out=renderResultsORM($res,'GradeLogID');
		}
		return $out;
	}
    public function getMemberLog($id=0){
		$out=[];
		if($id){
			$res=$this->DB->MembersLog->where("MemberID", $id)->order('LogDate DESC');
			$out=renderResultsORM($res,'MembersLogID');
		}
		return $out;
	}		
    public function getMemberSales($id=0){
		$out=[];
		if($id){
			//sales log
			$eid=array();
			$res=$this->DB->Sales->where("MemberID", $id)->order('SalesDate DESC');
			if($elog=renderResultsORM($res)){
				//invoices
				foreach($elog as $i=>$v){
					$out[$v['Ref']][$v['ID']]=$v;
				}
			}
		}
		return $out;
	}
	private function getMemberMeta($id=0){
		$recs=$meta=[];
		if($id){
			$recs=$this->DB->Meta->select('MetaID,MetaKey,MetaValue')->where(['MetaType'=>'member','MetaItemID'=>$id]);
			$recs=renderResultsORM($recs,'MetaKey');
		}
		foreach($this->META_KEYS as $k){
			$tmp=['MetaID'=>0,'MetaKey'=>$k,'MetaValue'=>''];
			$meta[$k]=issetCheck($recs,$k,$tmp);
		}
		return $meta;		
	}
	private function renderMetaValues($args=[]){
		$meta=[];$mode=null;
		extract($args);
		$out=[];
		foreach($meta as $i=>$v){
			switch($i){
				case 'teaching_rank': case 'payment_method':
					$val=(int)$v['MetaValue'];
					if($mode==='edit'){
						$o='<option value="0">None</option>';
						$d=($i==='payment_method')?$this->SLIM->Options->get('payment_method'):$this->SHINSA_REF;
						$o.=renderSelectOptions($d,$val);
						$x='<label >'.ucME($i).'</label><select name="meta['.$i.']">'.$o.'</select>';
					}else{
						$x=($val)?$this->SHINSA_REF[$val]['MetaValue']:'None';
					}
					$out[$i]=$x;
					break;
				default:
					$type='text';
					if(strpos($i,'date')!==false) $type='date';
					$out[$i]=($mode==='edit')?'<label >'.ucME($i).'</label><input type="'.$type.'" name="meta['.$i.']" value="'.$v['MetaValue'].'"/>':$v['MetaValue'];
			}
		}
		return $out;
	}
	public function saveMemberMeta($meta,$id){
		$current=$this->getMemberMeta($id);	
		$done=0;	
		foreach($this->META_KEYS as $key){
			$c=issetCheck($current,$key,[]);
			$v=issetCheck($meta,$key,'-null');
			if($v==='-null'){
				$v=($c)?$c['MetaValue']:'';
			}
			$mid=(isset($c['MetaID']))?(int)$c['MetaID']:0;
			$db=$this->DB->Meta;
			if($mid){
				$rec=$db->where('MetaID',$mid);
				$chk=$rec->update(['metaValue'=>$v]);
			}else{
				$chk=$db->insert(['metaValue'=>$v,'MetaItemID'=>$id,'MetaType'=>'member','MetaKey'=>$key]);
			}
			if($chk) $done++;
		}
		return $done;
	}
	private function setSubsProducts(){
		if($this->PRODUCTS) return;
		$prods=$this->SLIM->Options->get('products');
		if(!$prods) return;
		$subs_group=$memb_cat=$fee_cat=0;
		$groups=$this->SLIM->Options->get('product_types');
		$cats=$this->SLIM->Options->get('product_categories');
		foreach($groups as $i=>$v){
			if($v==='Subscriptions'){
				$subs_group=$this->SUBS_GROUP_ID=$i;
				break;
			}			
		}
		foreach($cats as $i=>$v){
			if($v==='Membership'){
				$memb_cat=$this->MEMB_CAT_ID=$i;
			}else if($v==='Fee'){
				$fee_cat=$i;
			}
			if($memb_cat && $fee_cat) break;
		}
		foreach($prods as $i=>$v){
			$cat=(int)$v['ItemCategory'];
			$grp=(int)$v['ItemGroup'];
			if($cat>0){
				$this->PRODUCTS[$i]=$v;
				if($grp==$subs_group){//is subscription product
					if($cat==$memb_cat){
						if($v['ItemSlug']==='akr-membership'){
							$this->PROD_ID['akr'][$i]=$i;
						}else{
							$this->PROD_ID['membership'][$i]=$i;							
						}
					}else if($cat==$fee_cat){
						if($v['ItemSlug']==='ikyf-id-registration'){
							$this->PROD_ID['ikyf'][$i]=$i;
						}else{						
							$this->PROD_ID['fee'][$i]=$i;
						}
					}					
				}
			}
		}		
	}
    private function getCurrentMembership($id=0){
		$this->setSubsProducts();
		$out=[];
		if(!$this->PROD_ID) return $out;
		if($id){
			$keys=$this->PROD_ID['membership'];
			if(isset($this->PROD_ID['fee'])) $keys+=$this->PROD_ID['fee'];
			//sales log
			$res=$this->DB->Sales->where("MemberID", $id)->where('ItemID',$keys)->order('EndDate DESC');
			if($elog=renderResultsORM($res)){
				$out=$elog;
			}
		}
		return $out;
	}
    private function getCurrentIKYF($id=0){
		$this->setSubsProducts();
		$out=[];
		if(!$this->PROD_ID) return $out;
		if($id){
			//sales log
			$res=$this->DB->Sales->where("MemberID", $id)->where('ItemID',$this->PROD_ID['ikyf'])->order('EndDate DESC')->limit(1);
			if($elog=renderResultsORM($res)){
				$out=current($elog);
			}
		}
		return $out;
	}
    private function getCurrentAKR($id=0){
		$this->setSubsProducts();
		$out=[];
		if(!$this->PROD_ID) return $out;
		if($id){
			//sales log
			$res=$this->DB->Sales->where("MemberID", $id)->where('ItemID',$this->PROD_ID['akr'])->order('EndDate DESC')->limit(1);
			if($elog=renderResultsORM($res)){
				$out=current($elog);
			}
		}
		return $out;
	}

    //by any
    private function getMembersBy($what=false,$g=false,$fields='default'){
		$this->init();
		$recs=null;
		if($what && $g!==false){
			if($what==='location'){
				$recs=$this->DB->Members->where('LocationID',$g)->order('MemberID');
			}else if($this->validField($what)){
				$recs=$this->DB->Members->where($what,$g);
			}
			if($recs){
				if($fields){
					$s=issetCheck($this->SELECTION,$fields,$this->SELECTION['default']);
					if($this->DOWNLOAD) $s.=',MemberTypeID';
					$recs->select($s);
				}
				$recs=$this->setDojoLock($recs);
				$recs=$this->setStatusFilter($recs);
				if($this->MEMBER_SORT){
					if($this->MEMBER_SORT==='GradeSort'){
						$recs->order('CurrentGrade DESC, GradeDate ASC, Birthdate ASC');
					}else{
						$recs->order($this->MEMBER_SORT.' '.$this->MEMBER_ORDER);
					}
				}
				$recs=renderResultsORM($recs,'MemberID');
				if($this->DOWNLOAD){
					return $this->formatDownload($recs);
				}
				return $this->fixFieldValues($recs);
			}
			$this->ERR[]=__METHOD__.': fieldname is not valid.['.$what.']';
		}
		return [];			
    }
    private function getMembersByName($where=[]){
		$this->init();
		$recs=null;
		if($recs=$this->DB->Members->where($where)){
			$recs=renderResultsORM($recs,'MemberID');
			return $this->fixFieldValues($recs);	
		}
		return [];
    }
    private function setStatusFilter($recs){
		if($this->STATUS_FILTER==='active'){
			$recs->where('Disable',0)->and('Dead',0)->and('nonuk',0);
		}
		return $recs;
	}
    private function setDojoLock($recs,$list=false){
		if(!empty($this->DOJO_LOCK) && !in_array($list,array('dojo','dojo_active')) ){
			$recs->and('DojoID',$this->DOJO_LOCK);
		}
		return $recs;
	}
	public function updateRecord($post=false,$id=0){
		$response=array('message'=>'* club update error *','message_type'=>'alert','update'=>false,'status'=>500,'ref'=>$id);
		if($id>0 && is_array($post) && !empty($post)){
			$meta=issetCheck($post,'meta',[]);
			unset($post['meta']);
			$rec=$this->DB->Members->where("MemberID", $id);						
			//validate post
			$update=$this->validateData($post);
			if(is_array($update)){
				//update dojo name
				$dn=(isset($post['DojoID']))?$this->getDojoName($post['DojoID']):'';
				if($dn!=='') $update['Dojo']=$dn;
				$meta_save=$this->saveMemberMeta($meta,$id);
				$result=$rec->update($update);
				if($result || $meta_save){
					$response['message']='Okay, the record has been updated...';
					$response['status']=200;
					$response['message_type']='success';
					$response['update']=$update;
				}else{
					$response['message']='It does not seem like you have made any changes...';
					$response['status']=201;
					$response['message_type']='primary';
				}				
			}else{
				$response['message']='Okay, but nothing was updated...';
				$response['status']=201;
				$response['message_type']='primary';
			}
		}else{
			$response['message']='Sorry, the details received are invalid...';
		}
		return $response;
	}
 	
 	public function addRecord($post=false){
		$response=array('message'=>'* add member error *','message_type'=>'alert','update'=>false,'status'=>500,'ref'=>0,'type'=>'message');
		if(is_array($post) && !empty($post)){
			//ensure we have name & email
			$flag=0;			
			if(trim($post['Email'])===''){
				$flag=1;
			}else if(trim($post['FirstName'].$post['LastName'])===''){
				$flag=2;			
			}
			if($flag){
				$response['message']=($flag==2)?'Sorry, the members name seems to be empty...':'Sorry, the members email seems to be empty...';
				$response['status']=500;
				$response['message_type']='warning';				
				return $response;
			}
			//check user exists
			if($this->checkExists(['Email'=>$post['Email']])){
				$response['message']='Sorry, the member already exists...';
			}else{
				$add=$this->validateData($post);
				if(is_array($add)){
					$dn=$this->getDojoName($post['DojoID']);
					if($dn!=='') $add['Dojo']=$dn;
					$row=$this->DB->Members->insert($add);
					$response['message']='Okay, the record has been added...';
					$response['status']=200;
					$response['message_type']='success';
					$response['id']=$this->DB->Members->insert_id();
				}else{
					$response['message']='Sorry, the details seem to be invalid...';
				}
			}		
		}else{
			$response['message']='Sorry, the details received are invalid...';
		}
 		return $response;
	}
	public function checkExists($args=[]){
		if($args){
			foreach($args as $i=>$v){
				$chk = $this->getMembersBy($i,$v);
				if($chk) return true;
			}
		}			
		return false;
	}
 
}

