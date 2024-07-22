<?php
//include 'white_screen.php';
// load common functions & classes
require CORE. 'jamslim/slim_functions.php';
require CORE. 'pimple/Container.php';
require CORE. 'orm/NotORM.php';

// load application config (error reporting etc.)
require CORE. 'config/slim_config.php';

//error handlers
set_exception_handler('exception_handler');
set_error_handler('error_handler');

// init contaners;
use Pimple\Container;
$container = new jamDI();
$container['config']=$config;
require CORE . 'orm/orm_containers.php';
require CORE . 'jamslim/slim_containers.php';

//init app
$app = new slimControl($container);
