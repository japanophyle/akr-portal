<?php
//include 'white_screen.php';
require 'slim_constants.php';
require 'slim_init.php';		
$_user=$container->user;
$_route=$container->router->get('route');
$_act=issetCheck($_GET,'page');
$site_mode='development';
if($_user && $_user['access']>=3) $site_mode='default';
if($site_mode==='demo'){
	$inc='demo.php';
}else{
	switch($_route[0]){
		case 'chatter':
			$inc='chatbox2.php';
			break;
		case 'chat':
			$inc='chatbox.php';
			break;
		case 'offline':
			$inc='offline.php';
			break;
		default:
			$inc='default.php';
	}
}
require_once $inc;
