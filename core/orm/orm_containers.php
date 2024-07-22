<?php

//database objects
$container['pdo'] = function ($c) {
    return new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME.";charset=utf8", DB_USER, DB_PASS);    
};
$container['db'] = function ($c) {
 	return new NotORM($c['pdo']);
};
$container['db_error'] =  function($c){
	$err=$c['pdo']->errorInfo();
	return $err[2];	
};
$container['db_debug'] =  function($c){
	//call this before running a query
    $c->db->debug = function($query, $parameters){
        preME([$query,$parameters],2);
    };	
};
//helper functions
if(!function_exists('rekeyArray')){
	function renderResultsORM($results=false,$rekey=false){
		$data=[];
		if(is_object($results)){
			$res=array_map('iterator_to_array', iterator_to_array($results));
			$data=($rekey)?rekeyArray($res,$rekey):$res;
		}else{
			$data='Error: $results must be an ORM object.';
		}
		return $data;
	}
}
if(!function_exists('rekeyArray')){
	function rekeyArray($arr,$new_key){
		$out=[];
		if(is_array($arr) && $new_key!==''){
			foreach($arr as $i=>$v){
				if($k=issetCheck($v,$new_key)){
					$out[$k]=$v;
				}
			}
		}
		return $out;
	}	
}
