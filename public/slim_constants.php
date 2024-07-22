<?php
//constants - slim_constants.php
// # This file must be in the public root folder
// TODO get rid of this and work with namespaces

// set a constant that holds the project's folder path, like "/var/www/".
// DIRECTORY_SEPARATOR adds a slash to the end of the path
define('ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR);
// set a constant that holds the site id.
define('SLIM_SITE_ID',1);
define('SLIM_SITE_NAME','ukka_man');
// set a constant that holds the project's "application" folder, like "/var/www/application".
define('APP', ROOT . 'app' . DIRECTORY_SEPARATOR);
// set a constant that holds the project's "core" folder, like "/var/www/core".
define('CORE', ROOT . 'core' . DIRECTORY_SEPARATOR);
define('LIB_ROOT', ROOT . 'core' . DIRECTORY_SEPARATOR);
// set a constant that holds the project's "vendor" folder, like "/var/www/vendor".
define('VENDOR', CORE . 'vendor' . DIRECTORY_SEPARATOR);
// set a constant that holds the project's "cache" folder, like "/var/www/core/cache".
define('CACHE', APP . 'cache' . DIRECTORY_SEPARATOR);
// set a constant that holds the project's "template" folder, like "/var/www/core/template".
define('TEMPLATES', APP . 'templates' . DIRECTORY_SEPARATOR);
// set a constant that holds the autoloaders root.
define('SLIM_ROOT',dirname(__DIR__));

if (file_exists(CORE. 'slimAutoloader.php')) {
    require CORE.'slimAutoloader.php';
}else{
	die('Autoloader not loaded...');
}
