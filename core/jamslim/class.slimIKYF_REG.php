<?php

class slimIKYF_REG{
	// check IKYF for logged in user
	private $SLIM;
	private $USER;
	private $SUBS_DB;
	private $PRODUCT;
	private $PROD_KEYS;
	private $IKYF_SUBS;
	private $LANGS=array(
		'expired'=>array('en'=>'expired - required','fr'=>'expiré - obligatoire','de'=>'abgelaufen - erforderlich'),
		'registered'=>array('en'=>'registered','fr'=>'inscrit','de'=>'zugelassen'),
		'will_expire'=>array('en'=>'will expire - required','fr'=>'va expirer - obligatoire','de'=>'wird ablaufen - erforderlich'),
		'registration'=>array('en'=>'Registration','fr'=>'enregistrement','de'=>'Anmeldung'),
	);
	var $CURRENCY=1;
	var $FORM_RESPONSE;
	var $IS_VALID;
	var $MODE;// to check if we are accessing from the adminsite
	var $LANG;
	
	function __construct($slim=null){
		if(!$slim)throw new Exception('no slim object!!');
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		$this->PROD_KEYS=array('ikyf-id-registration','ikyf-id-registration-eur');
		$this->LANG=$slim->language->get('_LANG');
		$this->initUser();		
	}
	
	private function initUser(){
		$this->IKYF_SUBS=false;
		if($this->USER){
			$member_id=(int)issetCheck($this->USER,'MemberID');
			if($member_id){
				$this->IKYF_SUBS=$this->SLIM->MembersLib->get('current_ikyf',$member_id);
			}
		}		
	}
	
	private function setProduct(){
		$key=($this->CURRENCY==2)?$this->PROD_KEYS[1]:$this->PROD_KEYS[0];
		$p=$this->SLIM->db->Items->where('ItemSlug',$key);
		$p=renderResultsORM($p,'ItemID');
		$this->PRODUCT=current($p);		
	}
	
	function checkSubscription($future_date=false,$currency=false){
		if($currency) $this->CURRENCY=$currency;
		$this->setProduct();
		$valid=0;
		$inp='<input type="hidden" value="'.$this->PRODUCT['ItemID'].'" name="AdditionalFee"/><input type="text" id="input-ikyf"  value="'.$this->PRODUCT['ItemTitle'].' / '.toPounds($this->PRODUCT['ItemPrice'],$this->CURRENCY).'" disabled />';
		if($this->IKYF_SUBS){
			$chk=current($this->IKYF_SUBS);
			$reg=(is_array($chk))?$chk:$this->IKYF_SUBS;
			$end=strtotime($reg['EndDate']);
			$today=strtotime(date('Y-m-d'));
			$future=($future_date)?strtotime($future_date):false;
			if((int)$reg['Status']!=1 || $end<$today){
				$out=' ('.$this->LANGS['expired'][$this->LANG].')'.$inp;
			}else if($future_date && $end<$future){
				$out=' ('.$this->LANGS['will_expire'][$this->LANG].')'.$inp;
			}else{
				$out=msgHandler('<i class="fi-check"></i> '.$this->LANGS['registered'][$this->LANG].'','success',false);
				$valid=1;
			}
		}else{
			$out=' (required)'.$inp;
		}
		$this->FORM_RESPONSE='<label>IKYF '.$this->LANGS['registration'][$this->LANG].$out.'</label>';
		$this->IS_VALID=$valid;
		return $valid;		
	}
	
	function get($what=false){
		switch($what){
			case 'form_part':
				return $this->FORM_RESPONSE;
				break;
			case 'product':
				return $this->PRODUCT;
				break;
			case 'status':
				return $this->IS_VALID;
		}
	}
}
