<?php
if(!isset($_sesh_started)) session_start();
if(!defined('SLIM_SITE_ID')) define('SLIM_SITE_ID',1);//should be set on the index page
if(!defined('SLIM_SITE_NAME')) define('SLIM_SITE_NAME','tbs5');//should be set on the index page
require SLIM_SITE_NAME.'_reg.php';
require 'cfg.'.SLIM_SITE_NAME.'.php';
if(!$config) die('no "'.SLIM_SITE_NAME.'" config found.');

define('ENVIRONMENT', $config['ENVIRONMENT']);

/**
 * Configuration for: Error reporting
 * Useful to show every little problem during development, but only show hard errors in production
 */
if (ENVIRONMENT == 'development' || ENVIRONMENT == 'dev') {
	$config['URL_PROTOCOL']='//';
	//handled by slim3
    //error_reporting(E_ALL);
    //ini_set("display_errors", 1);
}

/* PUBLIC URL & FILE */
$http_host=issetCheck($_SERVER,'HTTP_HOST','localhost');
define('URL_PUBLIC_FOLDER', 'public');
define('URL_PROTOCOL', $config['URL_PROTOCOL']);
define('URL_DOMAIN', $http_host);
define('URL_SUB_FOLDER', str_replace(URL_PUBLIC_FOLDER, '', dirname($_SERVER['SCRIPT_NAME'])));
define('URL', URL_PROTOCOL . URL_DOMAIN . URL_SUB_FOLDER);
define('PAGE_SLUG', $config['PAGE_SLUG']);
define('FILE_ROOT',ROOT);//swap for ROOT
define('DATA_DIR','data/');
define('CACHE_DIR','cache/');
// used by old mypress classes
define('URLBASE',URL); 
define('MEDIA_ROOT',FILE_ROOT);

/**
 * Configuration for: Database
 * This is the place where you define your database credentials, database type etc.
 */
define('DB_TYPE', $config['DB_TYPE']);
define('DB_HOST', $config['DB_HOST']);
define('DB_NAME', $config['DB_NAME']);
define('DB_USER', $config['DB_USER']);
define('DB_PASS', $config['DB_PASS']);
define('DB_CHARSET', $config['DB_CHARSET']);

/*
 * initialize the main session var.
 * All session info should be held in here.


if(!isset($_SESSION['jamSlim'])){
	$_SESSION['jamSlim']=array(
		'userArray'=>array('name'=>'guest','access'=>0),
		'last_page'=>'home',
		'deviceType'=>'classic'
	);
}
*/
