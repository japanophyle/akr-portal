<?php

class slim_db_cart_log extends slim_db_common{
	var $TABLE='cart_log';
	private $ACCESS;	
    private $STATES=[
		0=>['tclass'=>'text-maroon','label'=>'incomplete'],
		//1=>['tclass'=>'text-orange','label'=>'printed'],
		2=>['tclass'=>'text-orange','label'=>'online payment'],
		3=>['tclass'=>'text-blue','label'=>'ipn processed'],
		4=>['tclass'=>'text-olive','label'=>'completed'],
		5=>['tclass'=>'text-red','label'=>'error'],
	];
	private $SEARCH_COLUMNS=[
		'text'=>['item_name','payer_name','payer_email','txn_id','cart_ref'],
		'date'=>['create_date','payment_date'],
	];
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		parent::__construct($slim);
		$this->ACCESS=$slim->user['access'];
	}
	function get($what=false,$args=null){
		switch($what){
			case 'states':
				if($args) return issetCheck($this->STATES,$args);
				return $this->STATES;
				break;
			case 'new':
				$data=$this->getCart('new');
				break;
			case 'cart': case 'id':
				$data=$this->getCart($args);
				break;
			case 'carts':case 'all';
				$data=$this->getCarts($args);
				break;
			case 'by':
				$f=issetCheck($args,'field');
				$v=issetCheck($args,'value');
				$data=$this->getCartsBy($f,$v);
				break;
			case 'status': case 'cart_ref': case 'txn_id': case 'user_id':
				$data=$this->getCartsBy($what,$args);
				break;
			case 'browse': case 'latest':
				$data=$this->getCartsBrowse($what);
				break;
			case 'search':
				$find=($args)?$args:issetCheck($_GET,'find');
				$data=$this->getCartsSearch($find);
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
    private function getCarts($refs=[]){
		$this->init();
        $recs=$this->DB->cart_log->select("TID, payer_name, item_name,txn_id, cart_ref, create_date, status");
        if($refs) $recs->where($refs);
		$rez=renderResultsORM($recs,'TID');
        if(!$rez) $rez=[];
        return $rez;
    }
    private function getCart($ref=0){
		$this->init();
		if($ref==='new'){
			$dsp['TID']=0;
			foreach($this->FIELDS as $i=>$v){
				if($v['type']==='int'||$v['type']==='tinyint'){
					$val=0;
				}else if($v['type']==='datetime'){
					$val=date('Y-m-d H:i:s');
				}else{
					$val='';
				}
				$dsp[$i]=$val;
			}
			return $dsp;
		}
		$ref=(int)$ref;
		if(!$ref) return [];
        $recs=$this->DB->cart_log->where('TID',$ref);
        $rez=renderResultsORM($recs,'TID');
        return $rez[$ref];
    }
    private function getCartsBy($what=false,$g=false){
		$this->init();
		if($what && $g){
			if($this->validField($what)){
				$recs=$this->DB->cart_log->select('TID, payer_name, item_name,txn_id, cart_ref, create_date, status');
				$recs->where($what,$g)->order('TID');
				return renderResultsORM($recs,'TID');
			}
			$this->ERR[]=__METHOD__.': fieldname is not valid.['.$what.']';
		}
		return [];			
    }
    private function getCartsBrowse($what=false){
  		$recs=$this->DB->cart_log->select('TID, payer_name, item_name,txn_id, cart_ref, create_date, status')->order('TID DESC');
  		if($what==='latest') $recs->limit(25);
  		return renderResultsORM($recs,'TID');
	}
    private function getCartsSearch($find=false){
		$find=trim(strip_tags($find));
		if($find==='') return [];	
		$where=[];
		$ct=0;
		$find="%$find%";
		foreach($this->SEARCH_COLUMNS['text'] as $t) $where[]=$t.' LIKE ?';
		$where=implode(' OR ',$where);
		$recs=$this->DB->cart_log->select('TID, payer_name, item_name,txn_id, cart_ref, create_date, status')->order('TID DESC');
		$recs->where($where,$find,$find,$find,$find,$find);
 		return renderResultsORM($recs,'TID');
	}

	public function updateRecord($post=false,$id=0){
		$response=array('message'=>'* cart log update error *','message_type'=>'alert','update'=>false,'status'=>500,'ref'=>$id,'type'=>'message');
		if($id>0 && is_array($post) && !empty($post)){
			$rec=$this->DB->cart_log->where("TID", $id);
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
		$response=array('message'=>'* add cart log error *','message_type'=>'alert','update'=>false,'status'=>500,'ref'=>0);
		if(is_array($post) && !empty($post)){
			//check if cart exists			
			if($this->checkExists($post)){
				$response['message']='Sorry, the cart already exists...';
			}else{
				$ins=$this->validateData($post);
				if(is_array($ins)){
					$chk=$this->DB->cart_log->insert($ins);
					if($chk){
						$response['message']='Okay, the record has been added...';
						$response['status']=200;
						$response['message_type']='success';
					}else{
						$response['message']='Sorry, there was a problem adding the record...';
					}
				}else{
					$response['message']='Sorry, the details received are invalid...';
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
				if(in_array($i,['TID','cart_ref','txn_id'])){
					$chk = $this->getCartsBy($i,$v);
					if($chk) return true;
				}
			}
		}			
		return false;
	}
}
