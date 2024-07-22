<?php

class slim_curl{
	var $FORCE_SSL=true;
	var $IGNORE_ERROR=false;
	var $FOLLOW_LOCATION=false;
	var $VERIFY_PEER=1;
	var $VERIFY_HOST=2;
	var $RETURN_TRANSFER=1;
	var $FORBID_REUSE=1;
	var $TIMEOUT=30;
	var $SEND_HEADER=true;
	private $RESPONSE;
	private $STATUS;
	private $ERR;
	private $HEADER;
	private $URL;
	private $POST;
	
	function go($url=false,$post=false,$ignore_error=false){
		$url=trim($url);
		if(!$url||$url==='') return false;
		if($ignore_error) $this->IGNORE_ERROR=true;
		$this->URL=$url;
		$this->prepPost($post);
		$this->getContents();
		return $this->RESPONSE;
	}
	function get($what=false){
		switch($what){
			case 'response': return $this->RESPONSE; break;
			case 'status': return $this->STATUS; break;
			case 'error': return $this->ERR; break;
			case 'header': return $this->HEADER; break;
			case 'post': return $this->POST; break;
			case 'url': return $this->URL; break;
		}
		return false;			
	}
	private function getContents(){
		if(strpos($this->URL,'jamserver')!==false){
			$this->VERIFY_PEER=$this->VERIFY_HOST=0;
			if(strpos($this->URL,'http')===false){
				$this->URL='http:'.$this->URL;
				$this->FORCE_SSL=false;
			}
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->VERIFY_PEER);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->VERIFY_HOST);
		curl_setopt($ch, CURLOPT_URL, $this->URL);
		if($this->POST){
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->POST);
		}
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->FOLLOW_LOCATION);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->TIMEOUT);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,$this->RETURN_TRANSFER);
		curl_setopt($ch, CURLOPT_HEADER, $this->SEND_HEADER);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, $this->FORBID_REUSE);
		if($this->FORCE_SSL){
			if(extension_loaded('openssl')){
				curl_setopt($ch, CURLOPT_SSLVERSION, 6);
			}
		}
		$res=curl_exec($ch);
		$this->STATUS = strval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		$this->HEADER = curl_getinfo( $ch );
		$this->ERR=['no'=>curl_errno($ch),'str' => curl_error($ch),'desc'=>curl_strerror(curl_errno($ch))];
		curl_close($ch);
		if ($res === false || $this->STATUS == '0') {
			if($this->IGNORE_ERROR){
				$res=false;
			}else{
				throw new Exception("cURL error: [{$this->ERR['no']}] {$this->ERR['desc']}");
			}
        }
        $this->RESPONSE=$res;		
	}
	private function prepPost($array=false){
		$params=null;
		if(is_array($array)){
			if(function_exists('http_build_query')){
				$params=http_build_query($array);
			}else{
				foreach ($array as $key => $value){
					$v=(is_array($value))? implode('|',$value):$value;
					$params[]=$key.'='.urlencode($v);
				}
				$params=implode('&', $params);
			}
		}else if($array && $array!=''){
			$params=(string)$array;
		}
		$this->POST=$params;
	}	
}
