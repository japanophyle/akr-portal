<?php
class arraySearch{
    var $RESULTS;
	var $ERR;
	var $NEEDLES;
	var $NEEDLE;
	var $HAYSTACK;
	var $RESPONSE;
	var $MATCH_TYPE;
	var $SEARCH_TYPE;
	
    function __construct(){}
	
	function find($needle,$hay,$return,$type,$match=false){
	   $this->NEEDLES=issetVar($needle);
	   $this->HAYSTACK=issetVar($hay);
	   $this->RESPONSE=issetVar($return,'value');
	   $this->MATCH_TYPE=issetVar($match,'standard');
	   $this->SEARCH_TYPE=issetVar($type,'walk');
	   $this->initSearch();
	   return $this->RESPONSE;
	}
	
	function initSearch(){
       if(!$this->NEEDLES) $this->ERR[]='no needle supplied';
       if(!$this->HAYSTACK) $this->ERR[]='no haystack supplied';
       if(!is_array($this->HAYSTACK)) $this->ERR[]='haystack is not an array';
	   if(!$this->ERR){
	      if(is_array($this->NEEDLES)){
		     foreach($this->NEEDLES as $n){
			    $this->NEEDLE=$n;
				if($this->SEARCH_TYPE==='walk'){
				   $this->array_walkup($this->NEEDLE, $this->HAYSTACK);
				}else{
			       $this->searchArray($this->HAYSTACK);
				}
			 }
		  }else{
		     $this->NEEDLE=$this->NEEDLES;
			 if($this->SEARCH_TYPE=='walk'){
			    $this->array_walkup($this->NEEDLE, $this->HAYSTACK);
			 }else{
	            $this->searchArray($this->HAYSTACK);
			 }
		  }
	   }
	   if(!$this->ERR){
	      $this->RESPONSE=array('response'=>200,'data'=>$this->RESULTS);  
	   }else{
	      $m=msgHandler(implode('<br/>',$this->ERR));
	      $this->RESPONSE=array('response'=>500,'message'=>$m,'data'=>$this->RESULTS);  
	   }
	}
	   
    function searchArray($array) {
       foreach ($array as $key => $val){
	      if(is_array($val)){
		     $this->searchArray($val);//recursive
	      }else{
             $this->checkValue($val,$key);
	      }
       }
    }

   function array_walkup( $desired_value, array $array, array $keys=array() ) {
       if (in_array($desired_value, $array)) {
          array_push($keys, array_search($desired_value, $array));
          return $keys;
       }
       foreach($array as $key => $value) {
          if (is_array($value)) {
            $k = $keys;
            $k[] = $key;
            if ($find = $this->array_walkup( $desired_value, $value, $k )) {
                $this->RESULTS[]=$find;
            }
          }
       }
       return false;
    }
	
    function checkValue($val,$key){
       switch($this->MATCH_TYPE){
	      case 'like':
		     if (strstr($val,$this->NEEDLE)) $this->RESULTS[]=($this->RESPONSE=='value')?$val:$key;
			 break;
		  case 'nocase':
		     $n=strtolower($this->NEEDLE);
			 $v=strtolower($val);
             if($v == $n) $this->RESULTS[]=($this->RESPONSE=='value')?$val:$key;
		     break;
		 default://standard
            if ($val == $this->NEEDLE) $this->RESULTS[]=($this->RESPONSE=='value')?$val:$key;
      }
   }
}
