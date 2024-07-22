<?php
class recaptcha_v3{
	private $RECAPTCHA_SERVER_API='https://www.google.com/recaptcha/api';
	private $RECAPTCHA_PRIVATE_KEY='6LehI-UpAAAAAA4_5Wu0hklIb5r8CG6Ou6jGD-ZZ';
	private $RECAPTCHA_PUBLIC_KEY='6LehI-UpAAAAACfzcnZI0tWeRljmKzHpo3XErsMb';

	private $CAPTCHA;
	private $SCRIPT;
	private $BUTTON;
	private $CSS;
	private $JS;
	private $HIDE_BADGE=true;
	private $DEBUG=true;
	
	function __construct(){
		if(ENVIRONMENT!=='live'){
			$this->RECAPTCHA_PRIVATE_KEY='6LcZp-MpAAAAAMtvXcLC29g6WiQtZw0cV3AJXiWG';
			$this->RECAPTCHA_PUBLIC_KEY='6LcZp-MpAAAAAKA5Kgw66xH_B8AZUpKDptgdTg5x';		
		}
	}
	
	function Process(){
		if($_POST){
			$out=$this->check();
		}else{
			$out=$this->render();
		}
		return $out;
	}
	
	private function render(){
		$this->CAPTCHA='<input type="hidden" name="recaptcha_response" id="recaptchaResponse">';
		$this->SCRIPT='<script src="'.$this->RECAPTCHA_SERVER_API.'.js?render='.$this->RECAPTCHA_PUBLIC_KEY.'"></script>';
		//get token
		$this->JS='function getRCToken(go=false){grecaptcha.ready(function(){grecaptcha.execute("'.$this->RECAPTCHA_PUBLIC_KEY.'", { action: "contact" }).then(function (token) {recaptchaResponse.value = token;if(go){$("#form1").submit();}});});} getRCToken();';
		//get token on submit
		$this->JS.=' $("#form1 button[type=submit]").on("click",function(e){e.preventDefault(); getRCToken(true);});';

 		if($this->HIDE_BADGE){
			$this->CSS='.grecaptcha-badge { display: none; }';
			$this->CAPTCHA.='<p class="label bg-navy expanded-zero text-center">To prevent spam, this form is protected by the Google reCAPTCHA tool. The Google <a class="link-gold" href="https://policies.google.com/privacy">Privacy Policy</a> and <a class="link-gold" href="https://policies.google.com/terms">Terms of Service</a> apply.</p>';
		}
		return [
			'cap'=>$this->CAPTCHA,
			'script'=>$this->SCRIPT,
			'js'=>$this->JS,
			'css'=>$this->CSS
		];
	}
	
	private function check(){
		$token=issetCheck($_POST,'recaptcha_response','');
		//verify response								  
		$response = $this->recaptcha_verify($token);
		$status=($this->DEBUG)?true:false;
		$msg=($this->DEBUG)?'verified':'failed - no reponse';
		if($response && $response['success']){
			if($response['score'] >= 0.5){
				$status=true;
				$msg='verified';
			}else{
				$msg='unverified '.$response->score;
			}
		}
		return ['status'=>$status,'message'=>$msg];
	}

    private function recaptcha_verify($token=''){		
		$url = $this->RECAPTCHA_SERVER_API.'/siteverify';
		$params=['secret'=>$this->RECAPTCHA_PRIVATE_KEY,'response'=>$token,'remoteip'=>$_SERVER['REMOTE_ADDR']];
		$http= new HttpPost($url);
		$http->setPostData($params);
		$http->send();
		$ret = $http->getResponse();
		if($this->DEBUG) file_put_contents(CACHE.'log/recatcha_response_'.time().'.log','token: '.$token."\n".$ret);
    	if($ret!==false && $ret!==''){
			$ret=json_decode($ret,1);
		}else{
			$ret=['success'=>false];
		}
    	return $ret;
    }
}
