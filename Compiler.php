<?php

namespace BC_PhalconLessCompiler;

use Phalcon\Mvc\Application;
use Phalcon\DI\FactoryDefault;
use Phalcon\Version;
use Phalcon\Config;

/**
 * Actual compiler class BradCrumb's PhalconPHP LessCompiler
 * 
 * @author Patrick Langendoen <github-bradcrumb@patricklangendoen.nl>
 * @author Marc-Jan Barnhoorn <github-bradcrumb@marc-jan.nl>
 * @copyright 2014 (c), Patrick Langendoen & Marc-Jan Barnhoorn
 * @license http://opensource.org/licenses/GPL-3.0 GNU GENERAL PUBLIC LICENSE
 */
class Compiler{

	/**
	 * Compiler options
	 * @var Phalcon\Config
	 */
	protected $_config;

	/**
	 * Application's registered modules
	 * @var array
	 */
	protected $_modules;

	/**
	 * Name of the method all modules need to implement to be able to configure this plugin on module level
	 * @var constant
	 */
	const MODULE_CONFIG_METHOD = 'getPhalconLessCompilerConfig';

	/**
	 * Name of the request parameter which makes it possible to force the compiler to compile
	 * @var constant
	 */
	const QUERY_PARAM_ENFORCEMENT = 'forceCompiling';

	/**
	 * Status whether component is enabled or disabled
	 * @var boolean
	 */
	public $enabled = true;

	/**
	 * Force the compiler to always compile all files
	 * @var boolean
	 */
	protected $_forceCompiling = false;

	/**
	 * Minimum required PHP version
	 * @var string
	 */
	protected static $_minVersionPHP = '5.3.3';

	/**
	 * Minimum required PhalconPHP version
	 * @var string
	 */
	protected static $_minVersionPhalconPHP = '1.3.3';

	/**
	 * Location of the lessc.inc.php file
	 * @var string
	 */
	protected static $_lessPHPfile;

	/**
	 * Minimum required Lessc version
	 * @var string
	 */
	protected static $_minVersionLessc = '1.7';

	/**
	 * Contains the indexed folders consisting of less-files
	 * @var array
	 */
	protected $_lessFolders;

	/**
	 * Contains the folders with processed css files
	 * @var array
	 */
	protected $_cssFolders;

	/**
	 * Name of the default key for arrays (source- and targetfolders ie)
	 * @var string
	 */
	const DEFAULT_KEY = '__default';

	/**
	 * Constructor
	 * @param Application $application
	 */
	public function __construct(Application $application) {
		$this->_initConfig($application);

		// Is the LessCompiler plugin enabled
		$this->enabled = $this->_getConfigurationValue('enabled', $this->enabled) || $this->_getConfigurationValue('autoRun', false);

		if ($this->enabled) {
			$this->_verifyPHPVersion();
			$this->_verifyPhalconVersion();
			$this->_verifyLessPHP();
		}
	}

	/**
	 * Initialize the configuration
	 * @param Application $application
	 * @return void
	 */
	protected function _initConfig(Application $application) {
		// Get this module's specific configuration
		$this->_config = require_once __DIR__ . DIRECTORY_SEPARATOR . 'config/config.php';

		// Get the configuration from the Dependency Injector
		$global = $application->getDi()->getShared(strtolower(__NAMESPACE__));

		// Merge both configs
		$this->_config->merge($global);

		// Get all registered and dispatched modules
		$this->_modules = $application->getModules();

		// Retrieve all module specific configuration by checking whether the required method has been implemented
		$moduleConfig = array('modules' => array());

		foreach ($this->_modules as $moduleName => $module) {
			require_once $module['path'];
			$moduleInstance = new $module['className'];

			if (method_exists($moduleInstance, self::MODULE_CONFIG_METHOD)) {
				$this->_config->merge($moduleInstance->{self::MODULE_CONFIG_METHOD}());
			}
		}
	}

	/**
	 * Return the configuration value for a specific key
	 *
	 * @param string $key
	 * @param $defaultReturnValue
	 * @return misc
	 */
	protected function _getConfigurationValue($key, $defaultReturnValue = null) {
		return array_key_exists($key, $this->_config)? $this->_config[$key]: $defaultReturnValue;
	}

	/**
	 * Check the PHP version
	 *
	 * @throws Exception when PHP version is not compatible
	 * @return void
	 */
	protected function _verifyPHPVersion() {
		if (PHP_VERSION < self::$_minVersionPHP) {
			throw new \Exception(sprintf('PHP version %s or higher is required!', self::$_minVersionPHP));
		}
	}

	/**
	 * Check the PhalconPHP version
	 *
	 * @throws Exception when PhalconPHP version is not compatible
	 * @return void
	 */
	protected function _verifyPhalconVersion() {
		if (Version::get() < self::$_minVersionPhalconPHP) {
			throw new \Exception(sprintf('PhalconPHP version %s or higher is required!', self::$_minVersionPhalconPHP));
		}
	}

	/**
	 * Check existance of GP Easy LessPHP and it's version
	 *
	 * @throws Exception when LeafoPHP can not be found or the version is not compatible
	 * @return void
	 */
	protected function _verifyLessPHP() {
		if (!($path = $this->_getConfigurationValue('pathToLessphp', false))) {
			throw new \Exception('Path to LessPHP vendor module has not been configured');
		}

		$file = 'lessc.inc.php';
		self::$_lessPHPfile = $path . DIRECTORY_SEPARATOR . $file;
	
		if (!file_exists(self::$_lessPHPfile)) {
			if (!file_exists(self::$_lessPHPfile)) {
				throw new \Exception(sprintf('%s does not exist!', $file));
			}
		}

		require_once $path . DIRECTORY_SEPARATOR . $file;

		if (\lessc::$VERSION < self::$_minVersionLessc) {
			throw new \Exception(sprintf('LessPHP version %s or higher is required!', self::$_minVersionLessc));
		}
	}

	/**
	 * Returns the Lessc file location
	 *
	 * @return string
	 */
	public static function getLessPHPFile() {
		return self::$_lessPHPfile;
	}

	/**
	 * Set folders
	 * 
	 * @throws Exception 
	 * @return void
	 */
	protected function _setFolders() {
		$sourceFolder = $this->_getConfigurationValue('sourceFolder', false);
		$targetFolder = $this->_getConfigurationValue('targetFolder', false);

		if ($sourceFolder && $targetFolder) {
			if ($sourceFolder instanceof Config) {
				if (!$targetFolder instanceof Config) {
					throw new \Exception('When you supply a sourcefolders array, you should also supply a targetfolders array with corresponding keys');
				}

				foreach ($sourceFolder as $fIndex => $fValue) {
					if (is_string($fValue) && is_dir($fValue) && array_key_exists($fIndex, $targetFolder) && is_dir($targetFolder[$fIndex])) {
						$this->_lessFolders[$fIndex] = realpath($fValue) . DIRECTORY_SEPARATOR;
						$this->_cssFolders[$fIndex] = realpath($targetFolder[$fIndex]) . DIRECTORY_SEPARATOR;
					} elseif ((is_array($fValue) || $fValue instanceof Config) && $fValue) {
						if (substr($targetFolder[$fIndex],-4) != '.css') {
							throw new \Exception('When you supply an array of sourcefolders for a specific key, the targetfolder should be a string which represents a css filename');
						}

						$path = str_replace(basename($targetFolder[$fIndex]), null, $targetFolder[$fIndex]);
						$this->_cssFolders[$fIndex] = realpath($path) . DIRECTORY_SEPARATOR . basename($targetFolder[$fIndex]);
						
						foreach ($fValue as $subFolderValue) {
							if (is_string($subFolderValue) && is_dir($subFolderValue)) {
								$this->_lessFolders[$fIndex][] = realpath($subFolderValue) . DIRECTORY_SEPARATOR;
							}
						}
					}
				}

			} elseif (is_string($sourceFolder) && is_dir($sourceFolder) && 
					  is_string($targetFolder) && is_dir($targetFolder)) {
				$this->_lessFolders[self::DEFAULT_KEY] = realpath($sourceFolder) . DIRECTORY_SEPARATOR;
				$this->_cssFolders[self::DEFAULT_KEY] = realpath($targetFolder) . DIRECTORY_SEPARATOR;
			}
		}

		foreach ((array)$this->_cssFolders as $folder) {
			if (is_dir($folder) && !is_writable($folder)) {
				throw new \Exception(sprintf('"%s" is not writable!', $folder));
			}
		}
	}

	/**
	 * Run the compiler by event
	 * 
	 * @param boolean $force
	 * @return void
	 */
	public function run($force = false) {
		if ($this->enabled) {
			// Force compiling of Less files?
			$this->_forceCompiling = in_array($force, array(1, '1', 'true', 'yes'));
		
			// Set source-, target- and css folders
			$this->_setFolders();

			// Include Less Parser
			require_once(self::$_lessPHPfile);

			// Use cache?
			$useCache = $this->_forceCompiling?
							false:
							(bool)$this->_config['useCache'];

			if ($useCache) {
				if (!is_dir($this->_config['cacheDirectory'])) {
					throw new \Exception('Specify the cacheDirectory if you want to use caching!');
				} elseif (!is_writable($this->_config['cacheDirectory'])) {
					throw new \Exception('The cacheDirectory should be writable in order to use it!');
				}
			}

			// Order by keys so __default is the first one to be treated
			ksort($this->_lessFolders);

			$config['variables'] = $this->_config['variables']->toArray();
			ksort($config['variables']);
			$this->_config['variables'] = $config['variables'];

			$config['importDirs'] = $this->_config['importDirs']->toArray();
			ksort($config['importDirs']);
			$this->_config['importDirs'] = $config['importDirs'];

			// Convert configuration to array to work with
			$config = $this->_config->toArray();

			$options = $config['options'];
			unset($options['sourceMapToFile']);

			foreach ($this->_lessFolders as $key => $lessFolder) {
				$parser = new \Less_Parser($this->_config['options']);

				// Merge global and $key specific variables and add them to the parser
				$variables = (isset($config['variables'][$key]))?
					array_merge($config['variables']['global'], (array)$config['variables'][$key]):
					$config['variables']['global'];

				// Merge global and $key specific import directories and add them to the parser
				$importDirs = (isset($config['importDirs'][$key]))?
					array_merge($config['importDirs']['global'], (array)$config['importDirs'][$key]):
					$config['importDirs']['global'];

				$parser->ModifyVars($variables);
				$parser->SetImportDirs($importDirs);

				$generateCallback = is_array($lessFolder)?
					'compileMultipleToSingleCss':
					'compileFolder';

				$this->$generateCallback(array(
					'importDirs' => $importDirs,
					'useCache' => $useCache,
					'key' => $key,
					'parser' => $parser,
					'lessFolder' => $lessFolder,
					'variables' => $variables,
					'options' => $options
				));
			}
		}
	}

	/**
	 * Compile Multiple Less files to a single CSS
	 *
	 * @return void
	 */
	public function compileMultipleToSingleCss($opts = array()) {
		extract($opts);
		
		if (!is_string($this->_cssFolders[$key])) {
			throw new LessCompilerException('Target should be a single stylesheet file!');
		}

		$path = $this->_cssFolders[$key];
		$lessFiles = array();

		foreach ($lessFolder as $lessDir) {
			$files = $this->getFilesFromDirectory($lessDir);
			foreach ($files as $file) {
				if (!$file->isDir()) {
					$lessFiles[$file->getPathName()] = null;
				}
			}
		}

		$options = $this->getOptions(array(
			'options' => $options,
			'path' => $path
		));

		$this->compileToCache(array(
			'importDirs' => $importDirs,
			'lessFiles' => $lessFiles,
			'useCache' => $useCache,
			'variables' => $variables,
			'cacheDir' => $this->getCacheDir($useCache, $path),
			'path' => $path,
			'options' => $options
		));
	}

	/**
	 * Compile folders
	 * 
	 * @param array $opts
	 * @return void
	 */
	public function compileFolder($opts = array()) {
		extract($opts);
		
		$files = $this->getFilesFromDirectory($lessFolder);
		foreach ($files as $file) {
			if (in_array($file->getPath(), $importDirs)) {
				continue;
			}

			$path = str_ireplace(
						rtrim($lessFolder, DIRECTORY_SEPARATOR),
						null,
						rtrim($file->getRealPath(), DIRECTORY_SEPARATOR)
			);

			$path = rtrim($this->_cssFolders[$key], DIRECTORY_SEPARATOR) . $path;
			if ($file->isDir()) {
				if (!is_dir($path)) {
					mkdir($path, 0777);
				}
			} else {
				$path = str_replace('.less', '.css', $path);
				$options = $this->getOptions(array(
					'options' => $options,
					'path' => $path
				));
				
				$lessFiles = array($file->getRealPath() => null);
				
				$this->compileToCache(array(
					'importDirs' => $importDirs,
					'lessFiles' => $lessFiles,
					'useCache' => $useCache,
					'variables' => $variables,
					'cacheDir' => $this->getCacheDir($useCache, $path),
					'path' => $path,
					'options' => $options
				));
			}
		}
	}

	/**
	 * Return the options for the parser
	 * 
	 * @param array $opts
	 * @return array
	 */
	protected function getOptions($opts = array()) {
		extract($opts);

		$extraOptions = array();

		if ($options['sourceMap']) {
			$extraOptions['sourceMapWriteTo']	= str_ireplace('.css', '.map', $path);
			$extraOptions['sourceMapURL'] 		= str_ireplace(array('.css', $_SERVER['DOCUMENT_ROOT']), array('.map', null), $path);
		}

		$options = array_replace_recursive($options, $extraOptions);

		return $options;
	}

	/**
	 * Compile Cache
	 *
	 * @param array $opts
	 * @return void
	 */
	protected function compileToCache($opts) {
		extract($opts);
	
		$cssFileName = Cache::Check($lessFiles, array_merge($options, array('cache_dir' => $cacheDir)), $useCache, $importDirs, $variables, $path);

		if (is_string($cssFileName)) {
			copy($cacheDir . DIRECTORY_SEPARATOR . $cssFileName, $path);
		}
	}

	/**
	 * Return the name of the directory which will be used for the cache-files and
	 * if the directory doesn't exists, try to create it.
	 * 
	 * @param boolean $useCache
	 * @param string $path
	 * @return string
	 */
	protected function getCacheDir($useCache = false, $path) {
		// Use the global cache directory, if it doesn't exist try to create it
		$cacheDir = $this->_getConfigurationValue(
			'cacheDirectory',
			dirname($path) . DIRECTORY_SEPARATOR . 'cache'
		);
	
		if (!is_dir($cacheDir)) {
			mkdir($cacheDir, 0777);
		}

		return $cacheDir;
	}

	/**
	 * Return array of files wihtin a given directory
	 *
	 * @param string $directory
	 * @return array
	 */
	protected function getFilesFromDirectory($directory) {
		return new \RecursiveIteratorIterator(
				new \RecursiveRegexIterator(
					new \RecursiveDirectoryIterator(
						$directory, \FilesystemIterator::SKIP_DOTS
					),
					'/^(?!.*(\/inc|\.txt|\.cvs|\.svn|\.git|\.map|\.md)).*$/i', \RecursiveRegexIterator::MATCH
				),
			\RecursiveIteratorIterator::SELF_FIRST
		);
	}
}