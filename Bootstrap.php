<?php

namespace BC_PhalconLessCompiler;

use Phalcon\Http\Request;
use Phalcon\Loader;
use Phalcon\Mvc\Application;

/**
 * Bootstrapper for BradCrumb's PhalconPHP LessCompiler
 * 
 * @author Patrick Langendoen
 * @author Marc-Jan Barnhoorn
 */
class Bootstrap{

	/**
	 * Constructor
	 * @param Application $application
	 */
	public function __construct(Application $application) {
		if (!self::isConsole()) {
			/**
			* Register namespaces
			* @var Loader
			*/
			$loader = new Loader();

			$loader->registerNamespaces(array(
				'BC_PhalconLessCompiler' => __DIR__,
			));

			$loader->register();

			/**
			* Run the Less compiler
			* @var Compiler
			*/
			$request = new Request();
			$compiler = new Compiler($application);
			$compiler->run($request->getQuery(Compiler::QUERY_PARAM_ENFORCEMENT));
		}
	}
	
	/**
	 * Check to see if the running instance is the commandline / console
	 * 
	 * @return boolean
	 */
	public static function isConsole() {
		return !isset($_SERVER['SERVER_SOFTWARE']);
	}
}