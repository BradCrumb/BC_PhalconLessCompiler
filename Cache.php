<?php
namespace BC_PhalconLessCompiler;

use BC_PhalconLessCompiler\Compiler as LessCompiler;

require_once(LessCompiler::getLessPHPFile());

/**
 * Cache class for BradCrumb's PhalconPHP LessCompiler which extends the Less Processor of http://lessphp.gpeasy.com/
 * 
 * @author Patrick Langendoen
 * @author Marc-Jan Barnhoorn
 */
class Cache extends \Less_Cache {

/**
 * Save and reuse the results of compiled less files.
 * The first call to Get() will generate css and save it.
 * Subsequent calls to Get() with the same arguments will return the same css filename
 *
 * @param array $less_files Array of .less files to compile
 * @param array $parser_options Array of compiler options
 * @param boolean $use_cache Set to false to regenerate the css file
 * @param array importdirs
 * @param array variables
 * @param location of the targetfile to be written
 * @return string Name of the css file or boolean
 */
	public static function Check( $less_files, $parser_options = array(), $use_cache = true, $importDirs, $variables, $targetFile) {
		if (!$use_cache) {
			$parser_options['import_dirs'] = $importDirs;
			$parser_options['variables'] = $variables;
			return self::Get( $less_files, $parser_options, $use_cache);
		}

		//check $cache_dir
		if(isset($parser_options['cache_dir']) ){
			\Less_Cache::$cache_dir = $parser_options['cache_dir'];
		}

		if(empty(\Less_Cache::$cache_dir) ){
			throw new \Exception('cache_dir not set');
		}

		self::CheckCacheDir();

		// generate name for compiled css file
		$less_files = (array)$less_files;
		$hash = md5(json_encode($less_files));
 		$list_file = \Less_Cache::$cache_dir.'lessphp_'.$hash.'.list';

		if( $use_cache === true &&
			file_exists($targetFile) && 
			self::CheckOptionsFile($parser_options) && 
			self::CheckListFile($list_file)) {
				return true;
		}

		$compiled = self::Cache( $less_files, $parser_options, $importDirs, $variables);
		if( !$compiled ) {
			return false;
		}

		//save the file list
		$cache = implode("\n",$less_files);
		file_put_contents( $list_file, $cache );

		//save the parser options
		file_put_contents(\Less_Cache::$cache_dir . self::CompiledOptionsName($parser_options), json_encode($parser_options));

		//save the css
		$compiled_name = self::CompiledName( $less_files );
		file_put_contents( \Less_Cache::$cache_dir.$compiled_name, $compiled );

		//clean up
		self::CleanCache();

		return $compiled_name;
	}

	/**
	 * Check and touch the parser's options file from cache
	 * 
	 * @param array $parser_options
	 * @return boolean
	 */
	protected static function CheckOptionsFile($parser_options) {
		$filename = \Less_Cache::$cache_dir . self::CompiledOptionsName($parser_options);

		if (file_exists($filename)) {
			@touch($filename);

			return true;
		}

		return false;
	}

	/**
	 * Check and touch the file which holds all files which have been compiled from cache
	 * 
	 * @param string $list_file
	 * @return boolean
	 */
	protected static function CheckListFile($list_file) {
 		if( file_exists($list_file) ){
			$list = explode("\n",file_get_contents($list_file));
			$compiled_name = self::CompiledName($list);
			$compiled_file = \Less_Cache::$cache_dir.$compiled_name;

			if( file_exists($compiled_file)) {
				@touch($list_file);
				@touch($compiled_file);

				return true;
			}
		}

		return false;
	}

	/**
	 * Return the name of the compiled CSS file
	 * 
	 * @param array $files
	 * @return string
	 */
	protected static function CompiledName( $files ){

		//save the file list
		$temp = array(\Less_Version::cache_version);
		foreach($files as $file){
			$temp[] = filemtime($file)."\t".filesize($file)."\t".$file;
		}

		return 'lessphp_'.sha1(json_encode($temp)).'.css';
	}

	/**
	 * Return the of the compiled options file
	 * 
	 * @param array $parser_options
	 * @return string
	 */
	protected static function CompiledOptionsName($parser_options) {
		return 'lessphp_' . sha1(json_encode($parser_options)) . '.conf';
	}

	/**
	 * Parse the Less source and return the result
	 * 
	 * @param array $less_files
	 * @param array $parser_options
	 * @param array $importDirs
	 * @param array $variables
	 * @return string
	 */
	public static function Cache( &$less_files, $parser_options = array(), $importDirs = array(), $variables = array()){
		$parser_options['cache_dir'] = \Less_Cache::$cache_dir;
		$parser = new \Less_Parser($parser_options);

		$parser->SetImportDirs($importDirs);
		$parser->ModifyVars($variables);

		// combine files
		foreach($less_files as $file_path => $uri_or_less ){

			//treat as less markup if there are newline characters
			if( strpos($uri_or_less,"\n") !== false ){
				$parser->Parse( $uri_or_less );
				continue;
			}

			//Cant get the right link to the sourcefile in the map, so this doesn't to sh....
			if ($parser_options['sourceMap']) {
				$parser->setOption('sourceMapRootpath', dirname($file_path));
				$parser->SetOption('sourceMapBasepath', dirname($file_path));
			}

			$parser->ParseFile( $file_path, $uri_or_less );
		}

		$compiled = $parser->getCss();

		$less_files = $parser->allParsedFiles();

		return $compiled;
	}

	/**
	 * Clean the cache files
	 */
	public static function CleanCache(){
		static $clean = false;

		if( $clean ){
			return;
		}

		$files = scandir(\Less_Cache::$cache_dir);
		if( $files ){
			$check_time = time() - (604800 / 7);
			foreach($files as $file){
				if( strpos($file,'lessphp_') !== 0 ){
					continue;
				}
				$full_path = \Less_Cache::$cache_dir.'/'.$file;
				if( filemtime($full_path) > $check_time ){
					continue;
				}
				unlink($full_path);
			}
		}

		$clean = true;
	}
}