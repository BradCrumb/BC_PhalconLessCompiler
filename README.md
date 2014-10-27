#PhalconPHP LessCompiler

A Less Compiler for PhalconPHP (http://www.phalconphp.com) based on the PHP Less compiler of http://lessphp.gpeasy.com.

Detailed instructions follow as soon as possible.
In the meanwhile, all you need to do is:

 - run Composer update
 - create a (global) configuration for the Less compiler which returns a Phalcon/Config object

ie. bc_phalconlesscompiler.php:
return new \Phalcon\Config(array(
	'pathToLessphp' => realpath(__DIR__ . '/../plugins/BC_PhalconLessCompiler/vendor/oyejorge/less.php'),
	'cacheDirectory' => realpath(__DIR__ . '/../cache/less'),
	'options' => array(
		'compress' => true,
		'sourceMap' => true,
	),
));

 - assign the configuration of the compiler to the Dependency Injector  

ie.:
$di['bc_phalconlesscompiler'] = function() {
    return require_once 'bc_phalconlesscompiler.php';
};

 - update the bootstrap (ie. index.php) file and register the event.

 ie.:
require_once(__DIR__ . '/../plugins/BC_PhalconLessCompiler/Bootstrap.php');
$eventsManager = new Phalcon\Events\Manager();
$eventsManager->attach("view:beforeRender", new BC_PhalconLessCompiler\Bootstrap($application));