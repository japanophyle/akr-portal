<?php
// --> app config - index.php
// load slim constants
require_once 'slim_constants.php';
require_once 'slim_init.php';

//set route actions - GET
$app->setRoute('GET','admin/*',function($c){
	$c->Admin->render();
});
$app->setRoute('GET','payments/*',function($c){
	$c->Payments->render();
});
$app->setRoute('GET','demo/*',function($c){
	$c->Demo->render();
});				
$app->setRoute('GET','cron/*',function($c){
	$c->Cron->render();
});
$app->setRoute('GET','oauth2/*',function($c){
	$c->Oauth2->render();
});					
$app->setRoute('GET','*',function($c){
	$c->Page->render();
});		

//set route actions - POST
$app->setRoute('POST','admin/*',function($c){
	$c->Admin->renderPost();
});
$app->setRoute('POST','payments/*',function($c){
	$c->Payments->render();
});
$app->setRoute('POST','demo/*',function($c){
	$c->Demo->renderPost();
});	
$app->setRoute('POST','oauth2/*',function($c){
	$c->Oauth2->render();
});					
$app->setRoute('POST','*',function($c){
	$c->Postman->Process();
});		

//run
$app->run();
