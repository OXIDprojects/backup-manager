<?php

class StormPDO extends PDO
{
	const MYSQL_TIMESTAMP = '%Y-%m-%d %H:%M:%S';
	const MYSQL_DATE = '%Y-%m-%d';
	
	public function __construct($dsn, $username = null, $password = null, $driver_options = null)
	{
		parent::__construct($dsn, $username, $password, $driver_options);
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array ('StormPDOStatement', array($this)));
		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
	}
	
	/**
	 * Execute SQL and return num of affected rows
	 * 
	 * @param string Query
	 * @param string Parameters
	 * @return int Num affected rows
	 */
	public function exec($statement, $values = null)
	{
		if ( is_array($values) )
		{
			$stmt = $this->prepare($statement);
			$stmt->execute($values);
			
			return $stmt->rowCount();
		}
		else
			return parent::exec($statement);
	}
	
	/**
	 * Execute query and return StormPDOStatement
	 * 
	 * @param string $statement
	 * @param mixed $var
	 * @param mixed $obj1
	 * @param mixed $obj2
	 * @return
	 */
	public function query($statement, $var = null, $obj1 = null, $obj2 = null)
	{
		if ( is_array($var) )
		{
			$stmt = $this->prepare($statement);
		
			$stmt->execute($var);
			return $stmt;
		}
		else
			return parent::query($statement);
	}
}

class StormPDOStatement extends PDOStatement
{
	protected $pdo;
	/**
	 * PSPDOStatement::__construct()
	 * 
	 * @param mixed $pdo
	 * @return
	 */
	protected function __construct($pdo)
	{
		$this->pdo = $pdo;

		$this->setFetchMode(PDO::FETCH_OBJ);
	}
	
	/**
	 * PSPDOStatement::execute()
	 * 
	 * @param mixed $params
	 * @return
	 */
	public function execute($params = array())
	{
		if ( is_array($params) )
		foreach ( $params as $key => $value )
		{
			if ( is_int($key) )
				$key += 1;
            else
                $key = ':'.$key;
			
			if ( is_array($value) )
				$this->bindValue($key, $value[0], $value[1]);
			elseif ( is_int($value) )
				$this->bindValue($key, $value, PDO::PARAM_INT);
			elseif ( is_bool($value) )
				$this->bindValue($key, $value, PDO::PARAM_BOOL);
			elseif ( is_null($value) )
				$this->bindValue($key, $value, PDO::PARAM_NULL);
			else
				$this->bindValue($key, $value);
		}
		
		parent::execute();
		
		return $this;
	}
	
	/**
	 * PSPDOStatement::fetch()
	 * 
	 * @param mixed $fetch_style
	 * @param mixed $cursor_orientation
	 * @param integer $cursor_offset
	 * @return
	 */
	public function fetch( $fetch_style = PDO::FETCH_OBJ, $cursor_orientation = PDO::FETCH_ORI_NEXT, $cursor_offset = 0 )
	{
		return parent::fetch($fetch_style, $cursor_orientation, $cursor_offset);
	}

	public function fetchAll( $how = NULL, $class_name = NULL, $ctor_args = NULL )
	{
		if ($how == NULL)
			$how = PDO::FETCH_OBJ;

		if ($class_name != null)
			return parent::fetchAll($how, $class_name, $ctor_args);
		else
			return parent::fetchAll($how);
	}
	
	public function fetchAllColumn($index = 0)
	{
		$ar = array();
		
		while ( $c = $this->fetchColumn($index) )
			$ar[] = $c;
			
		return $ar;
	}
}
