<?php

class slimMyHome {
	private $SLIM;
	private $USER;//current user
	private $MEMBER;//member records
	private $ROUTE;
	private $POST;
	private $OUTPUT;
	private $DOWNLOAD;
	private $myRESOURCES;
	private $SUMMARY;
	private $myDOWNLOADS;
	private $VIEW;
	private $MSG;
	private $AJAX;
	private $PRINT;
	private $ACTION;
	private $USE_SALES;
	private $USE_CLASSES;
	private $USE_PRIVACY=false;
	private $USE_SUBSCRIPTIONS=false;
	private $USE_DOWNLOADS=false;
	private $SHOW_USER_DETAILS=false;
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->USER=$slim->user;
		$this->ROUTE=$slim->router->get('route');
		$this->AJAX=$slim->router->get('ajax');
		$this->setVars();
	}
	function process(){// post actions
		if($this->POST){
			preME($this->ACTION,2);
			switch($this->ACTION){
				case 'updatep':
					$this->updatePrivacy();
					break;
				case 'saveMyDetails':
					$this->updateDetails();
					break;
				default:
					$this->MSG='Sorry,i don\t know what "'.$this->ACTION.'" is...';
			}
		}else{
			$this->MSG='Sorry, no data recieved...';
		}
		setSystemResponse(URL.'page/my-home',$this->MSG);
	}
	function render(){//get actions
		switch ($this->ACTION) {
            case 'viewOrder':
            case 'print':
                $this->viewOrder();
                break;
            default:
				if($this->USER){
					$this->getOrders();
					$this->getResourcelinks();
					$this->renderTabs();
				}else{
					$this->OUTPUT['message']='Sorry, no user found...';
				}
		}
		return $this->OUTPUT;
	}
	
	function get($what=false,$vars=false){
		switch($what){
		
		}
	}
	function set($what=false,$vars=false){
		switch($what){
		
		}
	}
	private function updateDetails(){
		$det=issetCheck($this->POST,'details');
        $valid = array('name_title','upass', 'email', 'phone','address','town','city', 'post_code', 'country' );
        if(!$this->SHOW_USER_DETAILS)  $valid = array('upass', 'email', 'phone' );
        $post=$this->POST;
        $token = $post['token'];
        unset($post['action'], $post['token']);
        $chkForm =  checkToken($token, 'token_myDetails');
 		if($det){
			$update=false;
			$user=$this->SLIM->db->myp_users->where('usr_ID',$this->USER['id']);
			$user=renderResultsORM($user);
			if($user){
				foreach($valid as $i){
					$value=issetCheck($det,$i);
					$k='usr_'.ucME($i);
					switch($i){
						case 'upass'://update password?
							if($value && $value!==''){
								$salt= $this->SLIM->slimText->generate_salt();
								$update['usr_Salt'] = $salt;
								$update['usr_Password']=$this->SLIM->slimText->encodeSalt(array('var'=>trim($value).$salt));
							}
							break;
						case 'name_title':
							$update['usr_Title']=$value;
							break;
						case 'post_code':
							if($i==='post_code') $k='usr_Post_Code';
							$update[$k]=$value;
							break;						
						default:
							$update[$k]=$value;
					}
			    }
				if($update){
					$user=$this->SLIM->db->myp_users->where('usr_ID',$this->USER['id']);
					$chk=$user->update($update);
					if($chk){
						$msg = 'Your details have been updated.';
					}else{
						$msg= 'Okay, but no changes have been made...';
					}
				}else{
					$msg= 'Sorry, I could not find anything to update...';
				}
			}else{
				$msg= 'Sorry, I could not a record for the current user...';
			}
		}
		$this->MSG=$msg;	
	}
	function updatePrivacy(){
		$PRV=$this->SLIM->Mailinglist;
		$PRV->updateUserPanel($this->USER['id'],$this->POST,URL.'page/my-home');
		//should already have redirected
		die('save privacy error');
	}
	private function setVars($args=false){
		if(!$this->USER){
			setSystemResponse(URL,'Sorry, you need to be logged in to view that content...');
		}
		$this->MEMBER=$this->SLIM->Users->get('details',$this->USER['id']);
		$this->myRESOURCES=$this->getResourceLinks();
		$this->ACTION=issetCheck($this->ROUTE,3);
		if($this->ACTION==='view'){
			$this->VIEW = issetCheck($this->ROUTE,4);
			if($this->ROUTE[2]==='sales') $this->ACTION='viewOrder';
		}
 		if($this->ACTION==='print'){
			$this->VIEW = issetCheck($this->ROUTE,4);
		}
        if($_POST){
			$this->ACTION=issetCheck($_POST,'action',$this->ACTION);
			$this->POST=$_POST;
		}
        if (issetCheck($args,'view')) {
            $this->VIEW = $args['view'];
            $this->PRINT = issetCheck($args,'printer');
            $this->ACTION = 'viewOrder';
        }
 	}

    function getSummary() {
        $dload = $purchase = false;
        $dct = $dca = $pct = $pca = $pcv = $pcq = $dcq = 0;
		if(is_array($this->OUTPUT['my_purchases'])){
			foreach ($this->OUTPUT['my_purchases'] as $i => $v) {
				if ($v['sls_Status'] != 'new') {
					$pct++;
					$pcv+=($v['sls_Payment_Value'] + $v['sls_Shipping_Value']);
					if ($v['sls_Status'] === 'complete') $pca++;
					if(isset($v['items'])){
						foreach ($v['items'] as $x => $y) {
							//if() $pcq+=$y
							if (issetCheck($y,'digital')) {
								$dcq+=$y['slo_Item_Qty'];
							} else {
								$pcq+=$y['slo_Item_Qty'];
							}
						}
					}
				}
			}
		}
        $args['physical'] = ': '.$pcq;
        $args['digital'] = ': '.$dcq;
        $args['orders'] = ': '.$pct;
        $args['value'] = ': &pound;'.number_format($pcv, 2);
        return $args;
    }

	private function getDownloads(){
		$this->myDOWNLOADS=false;
		$dl=new slim_downloads($this->SLIM);
		$rsp=$dl->render('my_downloads');
		$this->OUTPUT['my_downloads']=$rsp;
	}
	private function getOrders($render=false){
		$rsp=false;
		if($this->USE_SALES){
			$SLS=new slim_member_sales($this->SLIM,$this->USER['id']);
			$what=($render)?'render':'data';
			$rsp=$SLS->get($what);
		}
		$this->OUTPUT['my_purchases']=$rsp;
	}
	private function viewOrder(){
		$SLS=new slim_member_sales($this->SLIM,$this->USER['id']);
		$SLS->PRINTER=($this->ACTION==='print')?true:false;
		$order=$SLS->get('view',$this->VIEW);
		$out=renderCard_active('View Order: '.$this->VIEW,$order,$this->SLIM->closer);
		if($SLS->PRINTER){
			echo $order;
			die;
		}
		if($this->AJAX){
			echo $out;
			die;
		}
		return $out;
	}
	
	private function getResourceLinks(){
		$out=false;
		if($this->USE_CLASSES){
			$SR=new my_classes($this->SLIM);
			$out=$SR->get('classes',1);
			if(!$out||$out==='') $out=msgHandler('You are not enrolled on any of our courses...',false,false);
		}
		return $out;
	}
	private function getDetails(){
		$SR=new slim_member_details($this->SLIM,$this->USER);
		$SR->SHOW_DETAILS=$this->SHOW_USER_DETAILS;
		$this->SUMMARY=$this->getSummary();
		$SR->SUMMARY=$this->SUMMARY;
		$this->OUTPUT['my_details']=$SR->render();
	}
	private function getSubscriptions(){
		$out=false;
		if($this->USE_SUBSCRIPTIONS){
			$out=$SR=new slim_member_subscriptions($this->SLIM,$this->USER);
		}
		$this->OUTPUT['my_subscriptions']=$out;
	}
	private function getPrivacy(){
		$out=false;
		if($this->USE_PRIVACY){
			$PRV=$this->SLIM->Mailinglist;
			$privacy=$PRV->renderUserPanel($this->USER['id']);
			$out='<div id="Privacy">'.$privacy.'</div>';
		}
		$this->OUTPUT['my_privacy']=$out;
	}
	
    private function renderTabs() {
		//render data
		$this->getDownloads(true);//must be first
        $this->getDetails();
        $this->getPrivacy();
        $this->getOrders(true);
 
        $msg = ($this->MSG) ? showMyMessages($this->MSG) : false;
        if ($this->DOWNLOAD) $this->OUTPUT['download'] = $this->DOWNLOAD;
        if($this->myRESOURCES){
			 $this->OUTPUT['my_courses'] = $this->myRESOURCES;
		}
		
        //render
        $_tabs=array('my_details','my_privacy','my_purchases','my_downloads');
        foreach($_tabs as $t){
			$chk=issetCheck($this->OUTPUT,$t);
			if(!$chk) continue;
			$tabs[$t]=$chk;
		}
        $tmp=$msg.$this->renderSummary();
		$tmp.=renderTabs($tabs);
		$this->OUTPUT=array('content'=>$tmp);        
    }
    
    private function renderSummary(){
		$args = $this->SUMMARY;
        $args['icon'] = $this->renderUserIcon();
        $args['name'] = $this->USER['name'];
        $args['username'] = $this->USER['uname'];
	    $info=($this->USE_SALES)?'<strong class="title">Transactions:</strong><ul class="summary"><li>Total<strong>{orders}</strong></li><li>Value<strong>{value}</strong></li></ul>':false;
	    $tpl='<div class="panel radius" ><div class="grid-x grid-margin-x"><div class="cell medium-2"><img class="avatar show-for-medium" src="{icon}"/></div><div class="cell medium-5"><div class="title">{name}</div><div class="subtitle">{username}</div></div><div class="cell medium-5">'.$info.'</div></div></div>';
	   	$tpl=replaceME($args,$tpl);
	   	return $tpl;
	}
    private function renderUserIcon() {
        $IMG=$this->SLIM->Image;
        $tmpCode=$this->MEMBER['uname'] . '_mp' . $this->MEMBER['details']['user_id'];
		$tmpHash=$IMG->get('getHashFromAvatar',array('data'=>$this->MEMBER['details']['avatar']));
		if($tmpHash){
			return $this->MEMBER['details']['avatar'];
		}
        $src = $IMG->get('getAvatar',array('code'=>$tmpCode));
        if (!$src) $src = 'http://placehold.it/150x150&text=Identicon';
        return $src;
    }
}
