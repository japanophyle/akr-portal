<?php

class subscriptions_new{
	private $SLIM;
	private $PRODUCTS;
	private $MEMBER;
	private $DOJO;
	private $SUBS;
	private $OUTPUT;
	private $DEFAULT_REC=array('ID'=>0,'MemberID'=>0,'ItemID'=>0,'StartDate'=>false,'EndDate'=>null,'Length'=>1,'Paid'=>0,'PaymentDate'=>null,'PaymentRef'=>false,'Status'=>0,'Notes'=>false);
	private $PERMBACK;
	private $PERMLINK;
	private $AJAX;
	private $ROUTE;
	
	function __construct($slim){
		$this->SLIM=$slim;
		$this->PERMBACK=URL.'admin/';
		$this->PERMLINK=$this->PERMBACK.'subscription/';
		$this->AJAX=$slim->router->get('ajax');
		$this->ROUTE=$slim->router->get('route');
	}
	
	private function init($member_id){
		$this->MEMBER=$this->SLIM->Options->getMember($member_id);
		$this->DOJO=$this->SLIM->Options->get('clubs_name',$this->MEMBER['DojoID']);
		$this->SUBS=$this->DEFAULT_REC;
		$this->SUBS['MemberID']=$member_id;
		$prods=$this->SLIM->Options->getSubscriptionProducts();
		$plock=[0,$this->MEMBER['DojoID']];
		foreach($prods as $i=>$v){
			$lock=(int)$v['ItemContent'];
			if(in_array($lock,$plock)) $this->PRODUCTS[$i]=$v;
		}
	}
	
	function render($what=false,$vars=false){
		switch($what){
			case 'new':
				$r3=issetCheck($this->ROUTE,3);
				if($r3){
					$out=$this->renderForm($r3);	
				}else{
					$out=$this->renderSelectMember();
				}
				break;
			case 'select_member':case 'new':
				$out=$this->renderSelectMember();
				break;
			case 'new_select_products':
				$out=$this->renderForm($vars);			
				break;
			default:
				$out='nothing';
		}
		return $out;
	}
	
	private function renderSelectMember(){
		$chk=$this->SLIM->Options->get('all_members_select');
		$out=[];
		foreach($chk as $i=>$v){
			if($v['Name'] && trim($v['Name'])!=='') $out[$i]=['Ref'=>$i,'member'=>$v['Name'],'selectl'=>'<button class="button small button-lavendar gotoME" data-ref="'.$this->PERMLINK.'new_select_products/'.$i.'"><i class="fi-plus"></i> Select</button>'];
		}
		$args['data']['data']=$out;
		$args['before']='filter';
		$tid='mbr';
		$list=dataTable($args,'large',$tid);
		$title='New Subscription: <span class="subheader">Select a Member</span>';
		if($this->AJAX){
			echo renderCard_active($title,$list,$this->SLIM->closer);
			echo '<script>JQD.ext.initMyTable("#'.$tid.'_filter","#'.$tid.'");</script>';
			die;
		}
		return ['title'=>$title,'content'=>$list];
	}
	private function renderSelectProducts(){
		$out=[];
		$tid='prod';
		$title='Select a Product';
		$js='<script>JQD.ext.initMyTable("#'.$tid.'_filter","#'.$tid.'");</script>';
		foreach($this->PRODUCTS as $i=>$v){
			$price=toPounds($v['ItemPrice']);
			$button='<button type="button" class="button small selectProduct button-purple" data-id="'.$i.'" data-ref="'.$i.'" data-price="'.$price.'" data-alt="'.$v['ItemTitle'].'" data-target="addProduct"><i class="fi-plus"></i> select</button>';
			$out[$i]=['Ref'=>$i,'Item'=>$v['ItemTitle'],'Price'=>$price,'Select'=>$button];
		}
		$args['data']['data']=$out;
		$args['before']='filter';
		$list=dataTable($args,'large',$tid);
		$out=renderCard_active($title,$list,$this->SLIM->closer);
		if($this->AJAX){
			echo $out.$js;
			die;
		}
		$this->SLIM->assets->set('js','JQD.ext.initMyTable("#'.$tid.'_filter","#'.$tid.'");','prodselect');
		return $out;
	}
	private function renderForm($member_id=0){
		if(!$member_id){
			$member_id=(int)issetCheck($this->ROUTE,3);
			if(!$member_id) return $this->renderSelectMember();
		}
		$this->init($member_id);
		$parts=$this->MEMBER;
		$parts['product_selector']=$this->renderSelectProducts();
		$parts['dojo_name']=$this->DOJO['ClubName'].' - '.$this->DOJO['ShortName'];
		$parts['url']=$this->PERMLINK;
		//render form
		$tpl=file_get_contents(TEMPLATES.'parts/tpl.subscription-new.html');
		$title='New Subscription: <span class="text-navy">Select Products</span>';
		$tpl=replaceMe($parts,$tpl);
		$this->SLIM->assets->set('script','<script src="assets/js/admin/jqSubscriptionsForm.js?v=1"></script>','subsform');
		$this->SLIM->assets->set('js','jqActiveInvoice.go();','subsform');
		return ['title'=>$title,'content'=>$tpl];
	}
}
