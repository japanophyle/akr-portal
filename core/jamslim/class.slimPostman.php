<?php
//post actions
class slimPostman{
	private $SLIM;
	private $POST;
	private $ROUTE;
	private $ACTION;
	private $DEVICE;
	
	function __construct($slim=null){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
		$this->setVars();
	}
	
	function Process(){
		 $cacheThis = false;
		 switch ($this->ACTION) {
			case 'mlist_signup':
				$msg=getMlistSignup();
				break;
			case 'remind':
				$msg = $this->SLIM->Mailer->Process($this->ACTION, $this->POST);
				break;
			case 'verify':
				$g = $this->SLIM->router->get('get');
				$mailValidate=issetCheck($g,'pc');
				$msg = $this->SLIM->Mailer->Process($this->ACTION, $mailValidate);
				break;
			case 'paypal':
				$this->SLIM->Payments->render();
				//should have responded
				die;
				break;
            case 'login':
            case 'logout':
				//offline login
				$this->offlineLogin();
                $msg = $this->SLIM->Login->Process($this->ACTION,$this->POST);
                $pg='page/home';
                $jqd=false;
                if($this->ACTION==='login')	{
					$lp=issetCheck($_SESSION,'last_page',$pg);
					if(strpos($lp,'admin/')===0){
						$pg=$lp;
					}else if(strpos($lp,'/')===false){
						$pg='page/'.$lp;
					}else{
						$pg=$lp;
					}
				}
				setSystemResponse(URL.$pg,$msg,false,$jqd);
                break;
			case 'saveMyDetails':
				$this->SLIM->Members->Process();
				//should have responded
				die;
				break;
			case 'mlist_subscribe':
			case 'mlist_unsubscribe':
			case 'mlist_public_submit':
				$this->SLIM->Mailinglist->set('submit', $this->POST);
				break;
			case 'confirm_user'://payment links
				$HF= new hello_form($this->SLIM);
				$HF->Process();
				//should have responded
				die;
				break;
			case 'ez_checkout':
			case 'ez_process':
				$response=$this->SLIM->Cart->Process();				
				$response=json_decode($response);
				setSystemResponse($response->url,$response->message);
				die;
				break;
			case 'paypal_sim':
			    $this->SLIM->Payments->render();
				break;
			case 'send_contact_form':
				$HF=new slim_contact_forms($this->SLIM);
				$HF->get('send_form');
				break;
			case 'updatep':
				$HF=$this->SLIM->Mailinglist;
				$HF->updateUserPanel($this->SLIM->user['id'],$this->POST,URL.'page/my-home');
				break;
			case 'add_textbox_record':case 'update_textbox_record':
			case 'add_textbox_box':case 'update_textbox_box':
				$HF=new slim_text_box($this->SLIM);
				$HF->get('admin');
				break;
			default:
				$this->SLIM->Page->renderPost($this->ACTION);
		}
		// in most cases we should have redirected before this point.
		// other actions are routed through slimPage
	}
	
	private function setVars(){
		$this->POST=$this->SLIM->router->get('post');
		$this->ROUTE=$this->SLIM->router->get('route');
		if($this->POST){
			$this->ACTION=issetCheck($this->POST,'action');	
			if(!$this->ACTION){	
				$this->ACTION=$this->checkPaypal();
			}
		}
	}
	function checkPaypal($test=false){
		//check if form submition is for paypal 
		$switch=$this->ACTION;
		$sim=issetCheck($this->ROUTE,1);
		if($sim==='ipn_sim'){
			$switch='paypal_sim';
		}else{
			if(issetCheck($this->POST,'txn_id')){
				$switch='paypal';
				if(issetCheck($this->POST,'cmd')) $switch='paypal_sim';
			}
		}
		return $switch;
	}
	
	private function offlineLogin(){
		$on=issetCheck($this->POST,'uname_ol');
		$op=issetCheck($this->POST,'pass_ol');
		$ot=issetCheck($this->POST,'token_ol');
		if($on && $op){
			$token=base64_decode($ot);
			$token=explode('_',$token);
			if($token[0]==='mrl'){
				$time=time();
				$ot=(int)$token[1];
				if(($time-$ot)<=60){
					$this->POST['username']=$on;
					$this->POST['userpwd']=$op;
				}
			}
		}
	}

}
