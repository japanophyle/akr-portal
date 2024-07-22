<?php
// --> app config - index.php
// load slim constants
require 'slim_constants.php';
require 'slim_init.php';

$container['Uploader']=function($c){
	return new file_uploader($c);	
};

//set route actions - GET
$app->setRoute('GET','*',function($c){
	$ajax=$c->router->get('ajax');
	if(!$c->user || $c->user['access']<3){
		$msg='Sorry, you don\'t have access to that...';
	}else{
		$msg='Sorry, the GET method is not allowed in the uploader...';
	}
	$result=array('status'=>500,'message'=>$msg,'message_type'=>'alert');
	
	if($ajax){
		jsonResponse($result);
	}else{
		setSystemResponse(URL,$result['message']);
	}
});

$app->setRoute('POST','*',function($c){
	if(!$c->user || $c->user['access']<3){
		setSystemResponse(URL,'Sorry, you don\'t have access to that...');
	}	
	$currentDIR=issetCheck($_POST,'dir');// 'content/library/'
	//$allowedExtensions = array("jpeg", "jpg","gif","png","bmp","doc","pdf","txt","docx","xls","xlsx");
	$sizeLimit = 10 * 1024 * 1024;
	$field_name=isset($_POST['imgUrl'])?'imgUrl':'userImage';
	//preME($field_name,2);
	if($field_name==='imgUrl'){
		$opts=array();
		$uploader=new cropUpload($c,$opts);
		$uploader->Process();	
		die;
	}
	$c->Uploader->set('size_limit',$sizeLimit);
	if($currentDIR) $c->Uploader->set('media_root',$currentDIR);
	if($field_name) $c->Uploader->set('field_name',$field_name);
	
	$result = $c->Uploader->handleUpload($currentDIR);
	if(issetCheck($result,'error')){
		$state=500;
		$message=$result['error'];
		$type='alert';
		$content=false;
	}else{
		$state=200;
		$message=issetCheck($result,'message');
		$type=issetCheck($result,'message_type','success');
		$content=issetCheck($result,'content');
	}
	$rsp=array('status'=>$state,'message'=>$message,'type'=>$type,'content'=>$content);
	// to pass data through iframe you will need to encode all html tags
	//echo htmlspecialchars(json_encode($result), ENT_NOQUOTES);
	jsonResponse($rsp);
});		

//run
$app->run();
