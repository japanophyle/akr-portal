<?php

class TextFun {
	private static function pseudoBytes($length = 1,$secure=true){
		if(function_exists('random_bytes')){
			$len=ceil($length/2);
			$bytes= random_bytes($len);
		}else if(function_exists('openssl_random_pseudo_bytes')){
			$bytes = openssl_random_pseudo_bytes($length);
		}else{
			if(!$secure){
				$min=0;
				$max=9;
				if($length>1){
					$min='1'.str_pad('0',$length);
					$max.=str_pad('9',$length);
				}
				$bytes = mt_rand((int)$min,(int)$max);
			}else{
				throw new Exception ('Insecure server! (random byte generation insecure.)');
			}
		}
		return $bytes;
    }
    
    public static function randomInt($min, $max){
		if ($max <= $min) {
			throw new Exception('Minimum equal or greater than maximum!');
		}
		if ($max < 0 || $min < 0) {
			throw new Exception('Only positive integers supported for now!');
		}
		$difference = $max - $min;
		for ($power = 8; pow(2, $power) < $difference; $power = $power * 2) {
			
		}
		$powerExp = $power / 8;
		do {
			$randDiff = hexdec(bin2hex(self::pseudoBytes($powerExp)));
		} while($randDiff > $difference);
		return $min + $randDiff;		
	}
	
	public static function generate_string($args=[]) {
		$length = 15; $special_chars = true; $extra_special_chars = false;
		extract($args); 
		$password = '';
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		if ( $special_chars )$chars .= '!@#$%^&*()';
		if ( $extra_special_chars )	$chars .= '-_ []{}<>~`+=,.;:/?|';
		$len=strlen($chars)-1;
		for ( $i = 0; $i < $length; $i++ ){
			$r1=self::randomInt(0, $len);
			$password .= substr($chars, $r1, 1);
		}

		return $password;
	}

	public static function generate_readable_password($args=[]){
		$length=12;$symbols=0;
		extract($args); 
		// Password generation
		$conso=array("b","c","d","f","g","h","j","k","l","m","n","p","r","s","t","v","w","x","y","z");
		$vocal=array("a","e","i","o","u");
		$syms = '!@#$%&*-+?';
		$password="";
		//srand ((double)microtime()*1000000);
		$max = $length/2;
		for($i=1; $i<=$max; $i++){
		   $password.=$conso[rand(0,19)];
		   $password.=$vocal[rand(0,4)];
		}
		$newpass = $password;
		//add symbol
		if($symbols){
		  $int = rand(0,strlen($syms)-1);
		  $rand_char = $syms[$int];
		  $pass_length = strlen($newpass);
		  $random_position = rand(0,$pass_length)-1;
		  $newpass = substr_replace($newpass, $rand_char, $random_position, 0);
		}

		return $newpass; 
	}

	public static function quickHash($args=[]){
		$info=false; $encdata = false;
		extract($args);
		$hash = hash('ripemd160', $info);
		//if encrypted data is passed, check it against input ($info)
		if ($encdata) {
			if ($hash===$encdata) {
				return true;
			} else {
				return false;
			}
		} else {
			return $hash;
		} 	
	}
	
	public static function getToken_api($str='token',$len=15) {
		//$token = md5(uniqid(rand(), true));
		$token = $str.self::generate_string(array('length'=>$len));
		$token = self::quickHash(array('info'=>$token));
		return $token;
	}
	public static function getToken($len=15) {
		$token = self::generate_string(array('length'=>$len));
		return $token;
	}
	public static function getSalt($len=15) {
		$salt = self::generate_string(array('length'=>$len));
		return $salt;
	}
	
}
