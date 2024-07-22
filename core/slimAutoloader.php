<?php
class slimAutoLoader{
    public static $loader;
    private $routes;

    public static function init($routes=false){
        if (self::$loader == NULL){
            self::$loader = new self($routes);
        }

        return self::$loader;
    }

    public function __construct($routes=false){
		$this->routes = array(
			'pimple/',
			'orm/',
			'jamslim/',
			'jamslim/common/',
			'jamslim/database/',
			'jamslim/content/',
			'jamslim/shortcode/',
			'jamslim/admin/',
			'jamslim/pdf/',
			'jamslim/paypal/',
			'jamslim/dev/',
			'vendor/',
		);
		if(defined('PUBLIC_SITE')){
			
		}
		if(defined('ADMIN_SITE')){
			//admin paths
			//$this->routes[]='admin/';
			//$this->routes[]='admin/adminLib/';			
		}
		if(defined('UTIL_SITE')){
			//$this->routes[]='tools/';
		}
		if(is_array($routes)) $this->routes+=$routes;
        spl_autoload_register(array($this, "autoload"));
    }

    public function autoload($class_name){
		//preME($this->routes,2);
		foreach( $this->routes as $dir ) {
			if(strpos($dir,'Google/')!==false){
				$path=LIB_ROOT.$dir.$class_name.'.php';
			}else if(strpos($dir,'vendor/')!==false && $dir!=='vendor/'){
				$path=LIB_ROOT.$dir.$class_name.'.php';
			}else{
				$path=LIB_ROOT.$dir.'class.'.$class_name.'.php';
			}
			if (file_exists($path)) {
				require_once($path);
				return;
			}
		}
    }
}

slimAutoLoader::init();
