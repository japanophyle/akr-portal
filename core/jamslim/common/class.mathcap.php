<?php
class mathcap {
	private $SLIM;
    private $primes=[2,3,5,7,11,13,17,19,23,29,31,37,41,43,47,53,59,61,67,71,73,79,83,89,97,101,103,107,109,113,127,131,137,139,149,151,157,163,167,173,179,181,191,193,197,199,211,223,227,229];
    private $defprime=47;
    private $number_names=['zero','one','two','three','four','five','six','seven','eight','nine','ten'];
    private $captype='click';//default==numbers, text=text,click=oneclick button
 
    function __construct($slim){
		if(!$slim) throw new Exception(__METHOD__.': no slim object!!');
		$this->SLIM=$slim;
	}
	function generate($name) {
		$a = mt_rand(1,10); // generates the random number
		$b = mt_rand(1,10); // generates the random number
		$total=($a + $b);
		$code = $this->encode($total);
		switch($this->captype){
			case 'text':
				$name_a=$this->number_names[$a];
				$name_b=$this->number_names[$b];
				$part='<span class="text-dark-blue">'.$name_a.' plus '.$name_b.'</span>';
				break;
			case 'click':
				$key=base64_encode($name.'::'.$total);
				$part='<span class="button getME" data-ref="'.URL.'page/captcha/mc/'.$key.'" ><i class="fi-social-android icon-x2"></i><br/>Not a Bot</span>';
				break;
			default:
				$part='<span class="text-dark-blue">'.$a.' + '.$b.'</span>';
		}
		if($this->captype==='click'){
			$html='<div class="grid-x"><div class="cell small-6">'.$this->SLIM->language->getStandardPhrase('check_bot_button').'</div><div class="cell small-6">'.$part.'</div></div>';
		}else{
			$html='<label><i class="fi-shield icon-x1b"></i> Calculate this: '.$part.' = <input type="input" name="mathcap_answer" size="2" required/></label>';
		}
		$html='<div id="mathcaptcha_'.$name.'" ><div class="text-center callout primary">'.$html.'</div></div>';
		$sname=$this->setSesssionName($name);
		$_SESSION[$sname]=$code;		
		return $html;
	}
	function formScript($form_id){
		return '$("#'.$form_id.'").on("keyup keypress",function(e){if(e.which == 13 && !$(e.target).is("textarea")){e.preventDefault();return false;}});';		
	}
	function check($answer,$name) {
		$sname=$this->setSesssionName($name);
		$cpt=issetCheck($_SESSION,$sname);
		$result_encoded = $this->encode($answer);		
		if($result_encoded === $cpt){
			if($this->captype==='click'){
				$cancel=$this->SLIM->language->getStandard('cancel');
				$submit=$this->SLIM->language->getStandard('submit');
				$url=URL.'page/signup/cancel';
				$controls='<br/><div class="button-group expanded"><button class="button button-red gotoME" data-ref="'.$url.'/cancel"><i class="fi-x"></i> '.$cancel.'</button> <button class="button button-olive" type="submit" name="mc_token" value="'.$result_encoded.'"><i class="fi-check"></i> '.$submit.'</button></div>';
				return [
					'status'=>200,
					'content'=>$controls,
					'target'=>'#mathcaptcha_'.$name,
					'message'=>$this->SLIM->language->getStandardPhrase('not_a_bot'),
					'type'=>'success',
				];
			}else{
				$this->unsetCodes();
				return true;
			}
		}else if($this->captype==='click'){
			return [
				'status'=>500,
				'message'=>$this->SLIM->language->getStandardPhrase('are_a_bot'),
				'mtype'=>'alert',
				'type'=>'message'
			];
		}
		return false;  
	}
	function check_token($answer,$name) {
		$sname=$this->setSesssionName($name);
		$cpt=issetCheck($_SESSION,$sname);
		$this->unsetCodes();
		if($answer===$cpt){
			return true;
		}
		return false;
	}
	private function encode($input) {
		return md5($input.date("H").$this->defprime);
	}
	private function unsetCodes($sname=false){
		$sname=$this->setSesssionName($sname);
		unset($_SESSION[$sname]);
	}
	private function setSesssionName($name=false){
		if($name && is_string($name)){
			$sname='mathcap_'.trim($name);
		}else{
			$sname='mathcap';
		}
		return $sname;
	}
	

}
