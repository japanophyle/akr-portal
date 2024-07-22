<?php 
class Assets{
	// a static class to collect data
	private static $instance = NULL;
	private static $ASSETS=array('css'=>false,'js'=>false,'scripts'=>false,'styles'=>false,'message'=>false,'log'=>false);
	
	private function __construct(){}
	private function __clone(){}
	
	public static function getInstance() {
		if (!isset(self::$instance)) {
			self::$instance = new Assets;	
		}
		return self::$instance;
	}
	
	public static function get($what=false){
		if($what){
			return issetCheck(self::$ASSETS,$what);
		}else{
			return self::$ASSETS;
		}
	} 
	
	public static function set($what=false,$vars=false){
		if($what){
			self::$ASSETS[$what]=$vars;
			return true;
		}else{
			return false;
		}
	} 
	
	public static function add($what=false,$vars=false){
		if($what && $vars){
			$chk=issetCheck(self::$ASSETS,$what);
			if($chk){
				self::$ASSETS[$what][]=$vars;				
			}else{
				self::set($what,array($vars));
			}
		}
	} 

}
