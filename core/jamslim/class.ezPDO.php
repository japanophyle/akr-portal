<?php

class ezPDO {
    // Constructor override.
    private $DBO=false;
    private $RESULTS=false;
    private $ERRORS=array();
    private $LAST=false;
    private $LOG=array();
    private $obOPTIONS;
    private $REAL_ESCAPE;
    var $FIELD_OPTIONS;
    var $PRIMARYS;
    
    public function __construct($pdo=null,$opt_ob=null){
		$this->DBO=($pdo)?$pdo: new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
		$this->DBO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->DBO->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
		$this->REAL_ESCAPE=function_exists("mysql_real_escape_string");// i.e PHP >= v4.3.0
		$this->obOPTIONS=($opt_ob)?$opt_ob:null;
    }
    
    public function runQuery($params=false,$type=false){
		//params can be an sql query, or an array for prepare & execute	
		$results=false;	
		if($params){
			$sql=$this->prepExec($params);
			if(is_array($sql)){
				$results=$sql['error'];
			}else{
				switch($type){
					case 'obj':
						$results=$sql->fetchAll(PDO::FETCH_OBJ);
						break;
					case 'var':
						$results=$sql->fetchColumn();
						break;
					case 'row':
						$results=$sql->fetch();
						break;
					default:
						$results=$sql->fetchAll(PDO::FETCH_ASSOC);
						break;
				}
			}
		}
		return $results;
	}
    
    private function prepExec($querystring){
        // Retrieve all parameters except the querystring.        
        $parameters = func_get_args();
        array_shift($parameters);
        
        // Handle the case of a single array containing all parameters.        
        if (count($parameters) == 1 && is_array($parameters[0])){
            $parameters = $parameters[0];
        }
        $this->LOG[]=$this->LAST=array('query'=>$querystring,'params'=>$parameters);
        
        // If there are any parameters, use a prepared statement.
        try{        
			if (count($parameters)){
				$statement = $this->DBO->prepare($querystring);
				$statement->execute($parameters);
			}else{
				$statement =$this->DBO->query($querystring);
			}        
			// Return the PDOStatement object.        
			return $statement;
		}catch(PDOException $e){
			return $this->setError($e->getMessage());
		}
    }
	
	private function setError($error_message){
		$error=$this->LAST;
		$error['error']=$error_message;
		$error['debug']=debug_backtrace();
		$this->ERRORS[]=$error;
		return $error;
	}   

    // Produce a string to be used in a LIKE query.    
    public function likeify($str, $include_percent_chars = true){
        $str = str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $str);
        return $include_percent_chars ? "%$str%" : $str;
    }
    
	//quick escape
	function escape($str){
		$gt=gettype($str);
		if($this->REAL_ESCAPE){
			$str=mysql_real_escape_string($str);
		}else{
			switch ($gt){
				case 'string' : 
					$str = stripslashes($str);
					$str = str_replace("'","''",$str);
					break;
				case 'boolean' : 
					$str = ($str === FALSE) ? 0 : 1;
					break;
				case 'array' :
					$str = compress($str);
					break;
				case 'integer':
					$str = (int) $str;
					break;
				case 'double': case 'float':
					$str=number_format($str,2);
					break;
				default : //super safe
					$str = 'NULL';
					break;
			}
		}
		return $str;
	}
 	
 	private function prepInsert($data){
		$out=false;
		if(is_array($data)){
			$keys=array_keys($data);
			foreach($keys as $k){
				$bind[]='?';
				$vals[]=$data[$k];
			}
			$out="(".implode(',',$keys).") VALUES(".implode(',',$bind).")";
		}
		return $out;
	}
	
	private function prepUpdate($data){
		$out=false;
		if(is_array($data)){
			$keys=array_keys($data);
			foreach($keys as $k){
				$bind[]=$k.'=?';
			}
			$out=implode(',',$bind);
		}
		return $out;
	}
	
	function getFields($table=false){
		$fields=[];$options=false;
		if($table){
			$t_options=$this->obOPTIONS->get('field_options',$table);
			$q='DESCRIBE '.$table;
			$result=$this->DBO->query($q);
			while ($row = $result->fetch()){
				$type=explode('(',$row['Type']);
				$t_1=issetCheck($type,1);
				$t_1=str_replace(')','',$t_1);
				$options=issetCheck($t_options,$row['Field']);
				$fields[$row['Field']]=array('name'=>$row['Field'],'type'=>$type[0],'len'=>(int)$t_1,'key'=>$row['Key'],'label'=>camelTo($row['Field']),'options'=>$options);
				if($options) $this->FIELD_OPTIONS[$row['Field']]=$options;
			}
		}
		return $fields;
	}
	function getPrimary($table=false){
		$r=$this->getFields($table);
		foreach($r as $i=>$v){
			if($v['key']==='PRI'||$v['key']==='PRIMARY'){
				return $v['name'];
			}
		}
		return false;		
	}
	
	function getlastInsertId() {
		return $this->DBO->lastInsertId();
	}
	
	function getErrors(){
		return $this->ERRORS;
	}
   
}
