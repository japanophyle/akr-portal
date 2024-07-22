<?php
// genral text functions
class text_fun {
	var $PAGE=false;
	var $CID=false;
	var $AID=false;
	var $PERMBASE=false;
	var $CURRENTSITE=false;
	var $DATA=false;
	var $SETUP=false;
	var $SCRUB;

	private function init($args=false){
		if(is_array($args)){
			foreach($args as $i=>$v){
				$k=strtoupper($i);
				if(property_exists($this, $k))$this->$k=$v;
			}
		}
	}
	
	public function _get($function=false,$args=false){
		if($function && method_exists($this,$function)){
			$this->init($args);
			return $this->{$function}($args);
		}else{
			preME($function.' not found',2);
		}
	}

//functions
	function removeShortCodes($args){
		$str=$args['string'];
        $pattern = '/\[::(\S*)::\]/';
        preg_match_all($pattern, $str, $results);
        foreach ($results[0] as $i => $v) {
           $str = str_replace($v, '', $str);
        }
        return $str;
	}
	
	function fixHTML($string){
	   $out=stripslashes($string);
	   //$out=html_entity_decode($out);
	   $out=str_replace("%u2019","'",$out);
	   $out=str_replace("%u2018","'",$out);
	   $out=str_replace("&nbsp;"," ",$out);
	   //$out=str_replace("&ntilde;",chr(153),$out);
	   //from sql
	   $out = str_replace('\n','',$out);
	   $out =str_replace('\\','', $out); 
	   //$out=utf8tohtml($out);
	   if($out=="NULL") $out="";
	   return $out;
	  //return mb_convert_encoding($out,'UTF-8');
	}


	function stripslashes_array($arr){
	  $out=false;
	  foreach($arr as $i=>$v){
		$out[$i]=stripslashes($v);
	  }
	  return $out;
	}

	function encodeHTML($string){
		//$string=utf8_encode($string); //depriecated
	   	$string=mb_convert_encoding($string, "UTF-8", mb_detect_encoding($string));
	   	return base64_encode($string);
	}

	function decodeHTML($string){
	   if($tmp=base64_decode($string,true)){
		  return mb_convert_encoding($string, mb_detect_encoding($string), 'UTF-8');
		  //return utf8_decode($string); //deprecated
	   }else{
		  return $string;
	   }
	}

	function properCase($str){
	   $string = mb_strtolower($str, 'UTF-8');
	   $string = substr_replace($string, mb_strtoupper(substr($string, 0, 1), 'UTF-8'), 0, 1);
	   return $string;
	}

	function pluralizeME($str){
	  // very dirty function to plurlaize a word
	  $last_letter = strtolower($str[strlen($str)-1]);
	  switch($last_letter) {
		 case 'y':
			return substr($str,0,-1).'ies';
		 case 's':
			return $str.'es';
		 default:
			return $str.'s';
	  }
	  return $str;
	}

	function ucME($str){
	   $str=strtolower($str);
	   $str=str_replace('-',' ',$str);
	   return ucwords(str_replace('_',' ',$str));
	}

	function cleanME($args){
	   $val=false;$type='string';
	   extract($args);
	   $clean=(issetOR($val))?$this->SCRUB->forSQL($val, $type):false;
	   return $clean;
	}

	function nl2br2($args){
	   //newline to br - stop using this
	   $string=false;$type='a';
	   extract($args);
	   switch($type){
		  case 'a'://mainly used for email
			$output= str_replace(array("\r", "\n","\r\n"), "<br />", $string);
			break;
		  case 'b'://mainly used for textareas
			$output= str_replace(array("\r\n","\n"), "<br />", $string);
			break;
		  case 'p'://replace double newlines with p's
			$output= "<p>".preg_replace('#(\r\n\r\n)#', "</p><p>", $string)."</p>";
			break;
		  default://basic
			$output= str_replace(array("\n"), "<br />", $string);
			break;
	  }
	  return $output;
	}

	function br2nl( $input ) {
	   return preg_replace('/<br(\s+)?\/?>/i', "\n", $input);
	}

	function fixNewLine($str){
	   return str_Replace('\n',PHP_EOL,$str);	
	}

	function jsonEscape($str){
		$patern[]='/\n/';
		$patern[]='/\r/';
		$patern[]='/\t/';
		$replace[]='\\n';
		$replace[]='';
		$replace[]='\\t';
		$j=preg_replace($patern,$replace,$str);
		return $j;
	}

	function purifyHTML($dirty){
		//use for saving any html, but mainly for wysiwig
		$purifier = new HTMLPurifier();
		if(is_array($dirty)){
		   foreach($dirty as $i=>$v) $clean[$i]=$purifier->purify($v);
		}else{
		   $clean = $purifier->purify($dirty);
		}
		return $clean;
	}

	function RandomString($length = 8){ 
	   srand(date('s')); 
	   $possible_charactors = 'abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	   $string = ''; 
	   while(strlen($string) < $length){ 
			$string .= substr($possible_charactors, rand()%(strlen($possible_charactors)),1); 
	   } 
	   return $string; 
	}

	function setToken($name = 'token') {
		//$token = md5(uniqid(rand(), true));
		$token = RandomString(15);
		$_SESSION[$name] = $token;
		return $token;
	}

	function sanitise($var=false,$type=false){
		//strips input vars... dont use for content management!!
		$var=$type=false;
		extract($args);
		switch($type){
			case "cur":
			  if(settype($var,'float')){
				 return $var;
			  }else{
				 return 0;
			  }
			  break;
			case "date":
			  if($isDate($var)){
				 return formatDate($var,"d/m/Y");
			  }else{
				 return 0;
			  }
			  break;
			case "int":
			  return intval($var);
			  break;
			default:
			  $pt=array('/(\s+){2,}/','/[^A-Za-z0-9.#\\-$]\s/');
			  $rp=array('$1','');
			  $var=($var=="NULL" || is_null($var))?"":preg_replace($pt,$rp,$var);
			  return $var;
		}
	}

	function limitText($args){
		 $text=false;$wordCount=25;$trail=false;
		 extract($args);
		 $clean=strip_tags($text,'<span><br>');
		 $wordArray = explode(" ", $clean);
		 $count=count($wordArray);
		 if($count<($wordCount/2)){
			$output=$clean;
		 }else if($count<=$wordCount){
			$output=$clean;
		 }else{
			$output=array_slice($wordArray,0, $wordCount);
			$output=implode(" ", $output);
			if($trail)$output.='...';
		 }
		 return $output;
	}
	
	function limitChar($args){
		 $text=false;$charCount=50;$trail=false;
		 extract($args);
		 $clean=strip_tags($text);
		 $count=strlen($clean);
		 if($count<=$charCount){
			$output=$clean;
		 }else{
			$output=substr($clean,0, $charCount);
			if($trail)$output.='...';
		 }
		 return $output;
	}

	function slugMe($args) {
		$str=false; $replace=array(); $delimiter='-';$maxLength=200; $checkDB=true;
		extract($args);
		// use $replace array is for replacing char other than space
		// create slug
		if( !empty($replace) ) {
			$str = str_replace((array)$replace, ' ', $str);
		}
		$clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
		$clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
		$clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);
			$clean = strtolower(trim(substr($clean, 0, $maxLength), '-'));
		
		// check if the slug is already in database, if so add numer to the end.
		$rx='('.$clean.')(-\d+)?'; //regex
		$sql="SELECT itm_Slug FROM myp_items WHERE itm_Slug RLIKE '$rx' ORDER BY itm_Slug DESC LIMIT 1";
		if($checkDB){
			$chk=runQuery($sql,'var');
			if($chk){// already exists
			  $tmp=explode($delimiter,$chk);
			  $tmp=array_reverse($tmp);
			  $num=(is_int($tmp[0]))?$tmp[0]+1:1;
			  $slug=$clean.'-'.$num;
				  $done=false;
				  while(!$done){
					   if($chk2=slugExists($slug)){
						  $num++;
						  $slug=$clean.'-'.$num;
					   }else{
						   $done=true;
						   $clean=$slug;
					   }
				  }
				  
			}
		}
		return $clean;
	}

	function slugExists($slug){
		$sql="SELECT itm_Slug FROM myp_items WHERE itm_Slug ='$slug' LIMIT 1";
		$chk=runQuery($sql,'var');
		return $chk;
		
	}

	function encodeSalt($args){
	   $var=false;$type=1;
	   extract($args);
	   $HASHER= new PasswordHash(8, TRUE);
	   if(!$var) die('Sorry, nothing to encodeSalt..');
	   $var=trim($var);
	   $hash = $HASHER->HashPassword($var);
	   $check = $HASHER->CheckPassword($var, $hash);
	   if($check){
		 $out=$hash;
	   }else{
		 $out=false;
	   }
	   return $out;
	}
	function generate_salt($max = 15) {
	   //This function generates a password salt as a string of x (default = 15) characters
		$characterList = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*?";
		$i = 0;
		$salt = "";
		while ($i < $max) {
			$salt .= $characterList[mt_rand(0, (strlen($characterList) - 1))];
			$i++;
		}
		return $salt;
	}

	function utf8tohtml($args) {
		$utf8=$encodeTags=false;
		extract($args);
		$result = '';
		for ($i = 0; $i < strlen($utf8); $i++) {
			$char = $utf8[$i];
			$ascii = ord($char);
			if ($ascii < 128) {
				// one-byte character
				$result .= ($encodeTags) ? htmlentities($char) : $char;
			} else if ($ascii < 192) {
				// non-utf8 character or not a start byte
			} else if ($ascii < 224) {
				// two-byte character
				$result .= htmlentities(substr($utf8, $i, 2), ENT_QUOTES, 'UTF-8');
				$i++;
			} else if ($ascii < 240) {
				// three-byte character
				$ascii1 = ord($utf8[$i+1]);
				$ascii2 = ord($utf8[$i+2]);
				$unicode = (15 & $ascii) * 4096 +
						   (63 & $ascii1) * 64 +
						   (63 & $ascii2);
				$result .= "&#$unicode;";
				$i += 2;
			} else if ($ascii < 248) {
				// four-byte character
				$ascii1 = ord($utf8[$i+1]);
				$ascii2 = ord($utf8[$i+2]);
				$ascii3 = ord($utf8[$i+3]);
				$unicode = (15 & $ascii) * 262144 +
						   (63 & $ascii1) * 4096 +
						   (63 & $ascii2) * 64 +
						   (63 & $ascii3);
				$result .= "&#$unicode;";
				$i += 3;
			}
		}
		return $result;
	}

	function strpos_arr($args) {
		$haystack=$needle=false;
		extract($args);
		if(!is_array($needle)) $needle = array($needle);
		foreach($needle as $find) {
		  if(($pos = strpos($haystack, $find))!==false) return $pos;
		}
		return false;
	}

	function strpos_arr2($args) {
		$haystack=$needle=false;
		extract($args);
		if(!is_array($needle)) $needle = array($needle);
		if(!is_array($haystack)) $haystack = array($haystack);
		foreach($haystack as $stack) {
		  foreach($needle as $find){
			 $pos = strpos($stack,$find);
			 //echo "$stack => $find = $pos <br>";
			 if($pos!==false) return $pos+1;
		  }
		}
		return false;
	}

	function strFind($args){
		$haystack=$needles=false;
		extract($args);
		//search $haystack for $needles, then return all found $needles
		if(!is_array($needles)) $needles = array($needles);
		$found=false;
		foreach($needles as $n){
			if(strpos($haystack,$n)!==false) $found[]=$n;
		}
		return $found;
	}
	function generate_password($args) {
		$length = 12; $special_chars = true; $extra_special_chars = false;
		extract($args); 
		$password = '';
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		if ( $special_chars )$chars .= '!@#$%^&*()';
		if ( $extra_special_chars )	$chars .= '-_ []{}<>~`+=,.;:/?|';
		for ( $i = 0; $i < $length; $i++ ) $password .= substr($chars, rand(0, strlen($chars) - 1), 1);

		return $password;
	}
	
	function veriCode($args=false){
		//generate email verification code
		$strA=issetCheck($args,'email');//can use any string
		$strB=issetCheck($args,'salt');//can use any string
		if(!$strA||$strA==='') $strA=$this->generate_password(array('special_chars'=>true));
		if(!$strB||$strB==='') $strB=$this->RandomString(16);
		$encoded = urlencode($strA).$strB;
		$hash = md5($encoded);
		return $hash;		
	}

	function generate_readable_password($args){
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

	function quickHash($args){
		$info=$encdata = false;
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

	function Packer($args){
		$data=false;$type='pack';$method='S';
		extract($args);
		if(!$data) return false;
		if($type!=='pack' && !$method){
			$method=(strpos($data,'{')===0)?'J':'S';
		}
		if($method==='S'){
			$out=($type==='pack')?base64_encode(serialize($data)):unserialize(base64_decode($data));
		}else{//json
			$out=($type==='pack')?json_encode($data):json_decode($data,1);			
		}
		return $out;
	}
}
