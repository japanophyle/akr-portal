<?php

class slim_db_clubs extends slim_db_common{
	var $TABLE='ClubInfo';
	
	//custom vars
	private $SELECTION=array(
		'basic'=>'ClubID as id, ClubID, ClubName,ShortName, Leader,LeaderID,Country,LocationID,AffiliateID,Status',
		'table'=>'ClubID as id, ClubID, ClubName as Dojo, Leader, Country,AffiliateID, Status'
	);
	var $IS_DOJO=false;

	function __construct($slim=false){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
	}
	public function get($what=false,$args=null){
		switch($what){
			case 'new':
				$data=$this->getClub('new');
				break;
			case 'club':
				$data=$this->getClubsBy('LocationID',$args);
				if($data) $data=current($data);
				break;
			case 'id':
				$data=$this->getClub($args);
				break;
			case 'clubs':case 'all';
				$o=($what==='all')?[]:['ClubID'=>$args];
				$data=$this->getClubs($o);
				break;
			case 'by':
				$f=issetCheck($args,'field');
				$v=issetCheck($args,'value');
				$data=$this->getClubsBy($f,$v);
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
    private function getClubs($args=[]){
		$this->init();
		$table=$vars=false;
		$type='all';
		extract($args);
		$select=($table)?$this->SELECTION['table']:$this->SELECTION['basic'];
        $recs=$this->DB->ClubInfo->select($select);
        switch($type){
			case 'search':
				if(strlen($vars)>2){
					$vars="%$vars%";
					$w='ClubName LIKE ? OR ClubAddress LIKE ?';
					$recs->where($w,$vars,$vars);
				}else{
					return [];
				}				
				break;
			case 'name':
				$recs->where("ClubName", $vars);
				break;
			case 'club': case 'location':
				$recs->where("LocationID", $vars);
				break;			
			case 'all':
				$recs=$this->setDojoLock($recs);
			default:
			
		}
        $rez=renderResultsORM($recs,'id');
        if(!$rez) $rez=[];
        return $rez;
    }
    private function getClub($ref=0){
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
		if(!$ref) return [];
        $opts['dbo']=$this->DB->ClubInfo;
        $opts['table']=$this->TABLE;
        $opts['primary']=$this->PRIMARY;
		$opts['fields']=$this->FIELDS;
		$opts['id']=$ref;			
		$ob=new slim_db_record($opts);
        return $ob->get('display');
    }

    //by any
    private function getClubsBy($what=false,$g=false){
		$this->init();
		if($what && $g){
			if($what==='location'){
				$recs=$this->DB->ClubInfo->where('LocationID',$g)->order('ClubID');
				$recs=$this->setDojoLock($recs);
				return renderResultsORM($recs,'ClubID');
			}else if($this->validField($what)){
				$recs=$this->DB->ClubInfo->where($what,$g)->order('ClubID');
				$recs=$this->setDojoLock($recs);
				return renderResultsORM($recs,'ClubID');
			}
			$this->ERR[]=__METHOD__.': fieldname is not valid.['.$what.']';
		}
		return [];			
    }
	public function updateRecord($post=false,$id=0){
		$response=array('message'=>'* club update error *','message_type'=>'alert','update'=>false,'status'=>500,'ref'=>$id);
		if($id>0 && is_array($post) && !empty($post)){
			$rec=$this->DB->ClubInfo->where("ClubID", $id);						
			//validate post
			$update=$this->validateData($post);
			$update=$this->checkLocationRecord($update);
			if(!isset($update['LeaderID'])||$update['LeaderID']==0) $update['LeaderID']=$this->getLeaderID($update['Leader']);
			if(is_array($update)){
				$result=$rec->update($update);
				if($result){
					//update items mapped by location ID
					$this->updateLocationRecords($update);
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
	private function checkLocationRecord($data=[]){
		if($data){
			$short=trim(issetCheck($data,'ShortName'));
			if($short===''){	
				$short=$this->getAcronym($data['ClubName']);
				if($short!==''){
					$data['ShortName']=$short;
				}
			}
		}
		return $data;
	}
	private function updateLocationRecords($data=false){
		if($data){
			if((int)$data['LocationID'] && issetCheck($data,'ShortName')){
				//member dojo
				$recs=$this->DB->Members->where('DojoID',$data['LocationID'])->where('Dojo != ?',$data['ShortName']);
				if(count($recs)){
					$update=array('Dojo'=>$data['ShortName']);
					$recs->update($update);
				}
			}
		}			
	}

 	public function addRecord($post=false){
		$response=array('message'=>'* add club error *','message_type'=>'alert','update'=>false,'status'=>500,'ref'=>0);
		if(is_array($post) && !empty($post)){
			//check user exists
			if($this->checkExists($post)){
				$response['message']='Sorry, the club already exists...';
			}else{
				$add=$this->validateData($post);
				$add=$this->formatData($add,'sql');
				if(!isset($add['ShortName'])) $add['ShortName']=$this->getAcronym($add['ClubName']);
				if(!isset($add['LeaderID'])||$add['LeaderID']==0) $add['LeaderID']=$this->getLeaderID($add['Leader']);
				if($add && is_array($add)){					
					$chk=$this->DB->ClubInfo->insert($add);
					$response['message']='Okay, the record has been added...';
					$response['status']=200;
					$response['message_type']='success';
				}else{
					$response['message']='Sorry, there was a problem addinf the record... please check your details then try agian.';
				}
			}		
		}else{
			$response['message']='Sorry, the details received are invalid...';
		}
 		return $response;
	}
	public function checkExists($args=false){
		if($args){
			if($v=issetCheck($args,'ClubName')){
				$chk = $this->getClubsBy('ClubName',$v);
				if($chk) return true;
			}
		}			
		return false;
	}
    private function setDojoLock($recs){
		if(!empty($this->DOJO_LOCK)){
			$recs->and('LocationID',$this->DOJO_LOCK);
		}
		return $recs;
	}
	private function getLeaderInfo($id=0){
		$rec=$this->SLIM->db->Users->select('id,Name,Email')->where('MemberID',$id);
		$rec=renderResultsORM($rec);
		if($rec) $rec=current($rec);
		return $rec;
	}
	private function getLocationInfo($id=0){
		$rec=$this->SLIM->db->Locations->where('LocationID',$id);
		$rec=renderResultsORM($rec);
		if($rec) $rec=current($rec);
		return $rec;
	}
 	private function getAcronym($str=''){
		$a='';
		$words = preg_split("/(\s|\-|\.)/", $str);
		foreach($words as $w) $a .= substr($w,0,1);
		return strtoupper($a);
	}
	private function getLeaderID($name=''){
		$id=0;
		$name=(trim($name));
		if($name==='') return $id;
		$rec=$this->SLIM->db->Users->where('Name',$name)->select('id,Name,MemberID')->limit(1);
		if(count($rec)){
			$rec=renderResultsORM($rec,'MemberID');
			$id=key($rec);
		}
		return $id;
	}
	private function formatData($data=[],$for='sql'){
		if(!$data || !is_array($data)) return [];
		$fix=[];
		foreach($this->FIELDS as $f){
			$val=issetCheck($data,$f['name']);
			switch($f['name']){
				case 'LeaderID':
					$fix[$f['name']]=$val;
					if($val=$this->getLeaderInfo($val)){
						$fix['Leader']=$val['Name'];
						if(!isset($data['Email'])) $fix['Email']=$val['Email'];
					}
					break;				
				case 'LocationID':
					$fix[$f['name']]=$val;	
					if($val=$this->getLocationInfo($val)){
						$fix['ShortName']=$val['LocationName'];
						$fix['Country']=$val['LocationCountry'];
					}
					break;				
				default:
					if(strlen($val)) $fix[$f['name']]=$val;				
			}
		}
		return $fix;
	}
}

