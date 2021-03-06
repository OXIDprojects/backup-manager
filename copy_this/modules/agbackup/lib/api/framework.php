<?php
/**
 * Storm framework v2.1
 * 
 * @author Stormbreaker
 * @copyright 2011
 */
class Storm
{
	private static $Initialized = false, $DocRoot, $Container, $AutoloadRegistered = false;
	public static $AbsolutePath, $RelativePath, $CorePath, $ClassesPath, $ComponentsPath, $ModelsPath;
	public static $LoadedComponents = array();
	
	public static function Init($CorePath = 'core', $ClassPath = 'class', $ComponentPath = 'component', $ModelPath = 'model')
	{
		self::$CorePath = $CorePath;
		self::$ClassesPath = $ClassPath;
		self::$ComponentsPath = $ComponentPath;
		self::$ModelsPath = $ModelPath;
		
		if (!isset($_SERVER['DOCUMENT_ROOT']))
			self::$DocRoot = dirname(__FILE__);
		else
			self::$DocRoot = self::FixPath($_SERVER['DOCUMENT_ROOT']);
	
		self::$AbsolutePath = dirname(__FILE__);
		self::$RelativePath = self::ToRelative(self::$AbsolutePath);
		
		if ( !self::$AutoloadRegistered )
		{
			spl_autoload_register(array('Storm', 'LoadClass'));
			spl_autoload_register(array('Storm', 'LoadModel'));
			spl_autoload_register(array('Storm', 'LoadCore'));
			
			self::$AutoloadRegistered = true;
		}

		set_include_path(get_include_path() . PATH_SEPARATOR . self::ToAbsolute('class'));
		
		register_shutdown_function( array('Storm', 'UnloadAll') );
		
		self::$Initialized = true;
	}
	
	public static function SetContainer($container)
	{
		self::$Container = $container;
		self::$Container->callLoad();		
	}
	
	public static function RequireFile($path)
	{
		$path = Storm::FixPath(self::$AbsolutePath ."/". self::$ClassesPath ."/". $path);
		
		require_once $path;
	}
	
	public static function LoadClass($name)
	{
		if ( class_exists($name, false) )
			return;
        
		$name = preg_replace("/^\\\?class\\\(.*)/", "$1", $name);
		
		$path = Storm::FixPath(self::$AbsolutePath ."/". self::$ClassesPath ."/". $name .".php");
		if ( is_file($path) )
			require_once $path;
        elseif ( is_file(Storm::FixPath(self::$AbsolutePath ."/". self::$ClassesPath ."/". $name .".class.php")) )
            require_once Storm::FixPath(self::$AbsolutePath ."/". self::$ClassesPath ."/". $name .".class.php");
	}
	public static function LoadCore($name)
	{
		if ( class_exists($name, false) )
			return;
		
		$path = Storm::FixPath(self::$AbsolutePath ."/". self::$CorePath ."/". $name .".php");
		if ( is_file($path) )
			require_once $path;
        elseif ( is_file(Storm::FixPath(self::$AbsolutePath ."/". self::$CorePath ."/". $name .".class.php")) )
            require_once Storm::FixPath(self::$AbsolutePath ."/". self::$CorePath ."/". $name .".class.php");
	}
	public static function LoadModel($realname)
	{
		if ( class_exists($realname, false) )
			return;
		
		$name = preg_replace("/^\\\?model\\\(.*)/", "$1", $realname);
		
		$path = Storm::FixPath(self::$AbsolutePath ."/". self::$ModelsPath ."/". $name .".php");
		if ( is_file($path) )
		{
			require_once $path;
			
			if (self::$Container)
				return self::$Container->get($realname);
			else
				return new $realname();
		}
	}
	
	public static function LoadComponent($name)
	{
		if ( self::IsLoadedComponent($name) )
			return;
		
		if (file_exists(realpath(self::$AbsolutePath .'/'. self::$ComponentsPath .'/'. $name .'/'. $name .'.php')))
			require_once realpath(self::$AbsolutePath .'/'. self::$ComponentsPath .'/'. $name .'/'. $name .'.php');
		else if (file_exists(realpath(self::$AbsolutePath .'/'. self::$ComponentsPath .'/'. $name .'.php')))
			require_once realpath(self::$AbsolutePath .'/'. self::$ComponentsPath .'/'. $name .'.php');
		
		self::$LoadedComponents[$name] = new StormLoader($name);
	}
	
	public static function IsLoadedComponent($name)
	{
		if ( isset(self::$LoadedComponents[$name]) )
			return true;
		else
			return false;
	}
	
	public static function UnloadAll()
	{
		$ar = array_reverse(self::$LoadedComponents, true);
		
		foreach ( $ar as $loader )
			$loader->Unload();
		
		unset($ar);
		self::$LoadedComponents = array();
	}
	
	public static function FixPath($path)
	{
		return preg_replace("/\\".DIRECTORY_SEPARATOR."$/", '', str_replace(array('\\', '/', '//', '\\\\'), DIRECTORY_SEPARATOR, $path));
	}
	
	public static function ToRelative($path)
	{
 		return str_replace(str_replace("\\", "/", self::$DocRoot), "", str_replace("\\", "/", $path));
	}
	
	public static function ToAbsolute($path)
	{
		return Storm::FixPath(Storm::$AbsolutePath . '/' . str_replace(Storm::$RelativePath, "", $path));
	}
}
?>