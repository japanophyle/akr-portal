<?php

class slim_db_meta extends slim_db_common{
	var $TABLE='Meta';
	
	//custom vars
	private $SELECTION=array(
		'basic'=>'MetaID as id, MetaItemID, MetaKey,MetaValue',
		'table'=>'MetaID as id, MetaItemID as Item, MetaKey as Key,MetaValue as Value'
	);

	function __construct($slim=false){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
	}
	public function get($what=false,$args=null){
		$o=['type'=>$what,'vars'=>$args];
		switch($what){
			case 'new':
			case 'key':
			case 'item':
			case 'id':
				$data=$this->getMeta($o);
				break;
			case 'keys':case 'all';
				$o=($what==='all')?[]:['key'=>$args];
				$data=$this->getMeta($o);
				break;
			case 'by':
				$f=issetCheck($args,'field');
				$v=issetCheck($args,'value');
				$data=$this->getMetaBy($f,$v);
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
    private function getMeta($args=[]){
		$this->init();
		$table=$vars=false;
		$type='all';
		extract($args);
		$select=($table)?$this->SELECTION['table']:$this->SELECTION['basic'];
        $recs=$this->DB->Meta->select($select);
        switch($type){
			case 'search':
				if(strlen($vars)>2){
					$vars="%$vars%";
					$w='MetaKey LIKE ? OR MetaItemID LIKE ?';
					$recs->where($w,$vars,$vars);
				}else{
					return [];
				}				
				break;
			case 'item':
				$recs->where("MetaItemID", $vars);
				break;
			case 'key':
				$recs->where("MetaKey", $vars);
				break;			
			case 'all':
			default:
			
		}
        $rez=renderResultsORM($recs,'id');
        if(!$rez) $rez=[];
        return $rez;
    }

    //by any
    private function getMetaBy($what=false,$g=false){
		$this->init();
		if($what && $g){
			if($this->validField($what)){
				return $this->getMeta('by',['field'=>$what,'value'=>$g]);
			}
			$this->ERR[]=__METHOD__.': fieldname is not valid.['.$what.']';
		}
		return [];			
    }
	public function updateRecord($post=false,$id=0){
		$response=array('message'=>'* meta update error *','message_type'=>'alert','update'=>false,'status'=>500,'ref'=>$id);
		if($id>0 && is_array($post) && !empty($post)){
			$rec=$this->DB->Meta->where("MetaID", $id);						
			//validate post
			$update=$this->validateData($post);
			if(is_array($update)){
				$result=$rec->update($update);
				if($result){
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
		$response=array('message'=>'* add meta error *','message_type'=>'alert','update'=>false,'status'=>500,'ref'=>0);
		if(is_array($post) && !empty($post)){
			//check user exists
			if($this->checkExists($post)){
				$response['message']='Sorry, the meta record already exists...';
			}else{
				$add=$this->validateData($post);
				if(is_array($add)){					
					$row=$this->DB->Meta->insert($add);
					$response['message']='Okay, the record has been added...';
					$response['status']=200;
					$response['message_type']='success';
				}else{
					$response['message']='Sorry, the details received seem invalid...';
				}
			}		
		}else{
			$response['message']='Sorry, the details received are invalid...';
		}
 		return $response;
	}
	public function checkExists($args=false){
		if($args){
			$key=issetCheck($args,'MetaItemKey');
			$item=issetCheck($args,'MetaItemID');
			if($item && $key){
				 $recs=$this->DB->Meta->select('MetaID')->where('MetaItemID',$item)->where('MetaItemKey',$key);
				 if(count($recs)) return true;
			}
		}			
		return false;
	}
}

