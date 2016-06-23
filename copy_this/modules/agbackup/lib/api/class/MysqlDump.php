<?php

class MysqlDump
{
	private $mysql, $host;

	public function __construct($host, $port, $user, $pass, $database = '')
	{
		$this->mysql = new StormPDO('mysql:'. ($database ? 'dbname='. $database .';' : '') .'host='. $host .';port='. $port, $user, $pass);

		// Export data in utf8 for consistency with non-latin text
		$this->mysql->exec("SET NAMES 'utf8'");

		$this->host = $host;
	}

	public function select_db($database)
	{
		$this->mysql->exec("USE `". $database ."`");
	}

	public function get_databases()
	{
		return $this->mysql->query("SHOW DATABASES")->fetchAllColumn(0);
	}

	public function get_tables()
	{
		// For corrupted tables (and maybe views) 'SHOW TABLES' gives incorrect results
		//return $this->mysql->query("SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'BASE TABLE'")->fetchAllColumn(0);
		return $this->mysql->query("SHOW TABLE STATUS WHERE `Comment` NOT LIKE 'VIEW'")->fetchAllColumn(0);
	}


	public function table_has_rows($table)
	{
		$query = $this->mysql->query("SELECT 1 FROM `". $table ."` LIMIT 1");
		$hasRow = false;

		foreach ($query as $d)
		{
			$hasRow = true;
		}

		return $hasRow;
	}

	public function get_views()
	{
		//return $this->mysql->query("SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'VIEW'")->fetchAllColumn(0);
		return $this->mysql->query("SHOW TABLE STATUS WHERE `Comment` LIKE 'VIEW'")->fetchAllColumn(0);
	}

	public function get_procedures()
	{
		return $this->mysql->query("SHOW PROCEDURE STATUS")->fetchAllColumn(1);
	}

	public function get_functions()
	{
		return $this->mysql->query("SHOW FUNCTION STATUS")->fetchAllColumn(1);
	}

	public function get_table_schema($table)
	{
		return $this->mysql->query("SHOW CREATE TABLE `". $table ."`")->fetchColumn(1) . ';';
	}

	public function get_view_schema($view)
	{
		return $this->mysql->query("SHOW CREATE VIEW `". $view ."`")->fetchColumn(1) . ';';
	}

	public function get_procedure_schema($procedure)
	{
		return $this->mysql->query("SHOW CREATE PROCEDURE `". $procedure ."`")->fetchColumn(2) . ';';
	}

	public function get_function_schema($function)
	{
		return $this->mysql->query("SHOW CREATE FUNCTION `". $function ."`")->fetchColumn(2) . ';';
	}

	public function get_fields($table)
	{
		return $this->mysql->query("SHOW COLUMNS FROM `". $table ."`")->fetchAll();
	}

	public function get_database_character_set()
	{
		//show variables like "character_set_database";
		//show variables like "collation_database";
		return $this->get_variable('character_set_database');
	}

	public function get_variable($name)
	{
		$result = $this->mysql->query("SHOW VARIABLES LIKE '". $name ."'")->fetch();

		if ($result === false)
			return false;

		return $result->Value;
	}

	private function get_type($field)
	{
		$end = strpos($field->Type, '(');

		if ($end > 0)
		{
			return strtolower(trim(substr($field->Type, 0, $end)));
		}
		else
		{
			return strtolower(trim($field->Type));
		}
	}

	private function should_quote($field)
	{
		return !in_array($this->get_type($field),
			array('tinyint', 'smallint', 'mediumint', 'int', 'bigint')
		);
	}

	private function write_data($data, $file = null)
	{
		if ($file === null)
		{
			return $data;
		}
		else
		{
			fwrite($file, $data);
		}
	}

	public function get_table_data($table, $file = null, $maxLength = 50000)
	{
		$queryStart = 'INSERT INTO `'. $table .'` ';

		// Get insert fields
		$fields = $this->get_fields($table);

		$fieldNames = array();
		foreach ($fields as $field)
		{
			$fieldNames[] = '`'. $field->Field .'`';
		}

		$queryStart .= '(' . implode(', ', $fieldNames) . ')';

		// Get data
		$queryStart .= " VALUES \n";

		$result = $this->write_data($queryStart, $file);

		$query = $this->mysql->query("SELECT * FROM `". $table ."`");
		$first = true;
		$dataSize = strlen($queryStart);
		foreach ($query as $row)
		{
			$data = array();
			foreach ($fields as $field)
			{
                $fieldData = $row->{$field->Field};

                if (is_null($fieldData))
                {
                	// NULL
                	$data[] = 'NULL';
                }
                else if (!$this->should_quote($field))
                {
                	// Number
                	$data[] = $fieldData;
                }
                else
                {
                	// String & others
	                $search = array("\x00", "\x0a", "\x0d", "\x1a", "\t");
	            	$replace = array('\0', '\n', '\r', '\Z', '\t');
	                $fieldData = str_replace($search, $replace, addslashes($fieldData));

	                $data[] = '\''. $fieldData .'\'';
            	}
			}

			$dataString = '';

			$dataString .= '(';
			$dataString .= implode(', ', $data);
			$dataString .= ')';

			$dataSize += strlen($dataString);

			if ($dataSize >= $maxLength && !$first)
			{
				$result .= $this->write_data(";\n", $file);
				$result .= $this->write_data($queryStart, $file);
				$result .= $this->write_data($dataString, $file);
				$dataSize = strlen($queryStart) + strlen($dataString);
			}
			else
			{
				if (!$first)
					$result .= $this->write_data(",\n", $file);

				$result .= $this->write_data($dataString, $file);
			}

			$first = false;
		}

		$query->closeCursor();

		if ($first)
		{
			return '';
		}

		$result .= $this->write_data(';', $file);

		if ($file === null)
			return $result;
	}

	public function get_dump($file = null, $tables = null, $drop_tables = false, $export_more = false)
	{
		// Export date/time as UTC to handle differences when importing
		$this->mysql->exec("SET time_zone = \"+00:00\"");

					// General info
		$result =	$this->write_data("-- SmartBackup database export\n" .
					"-- Version: 1.0.1\n" .
					"-- \n" .
					"-- Host: ". $this->host ."\n" .
					"-- MySQL version: ". $this->get_variable('version') . "\n" .
					"-- Server OS: ". $this->get_variable('version_compile_os') ."\n" .
					"-- \n" .
					"-- Time of dump: ". strftime('%Y-%m-%d %H:%M:%S') . "\n\n" .

					// Handle auto_increment columns with a value of '0'
					"SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n" .
					// Export date/time as UTC to handle differences when importing
					"SET time_zone = \"+00:00\";\n\n" .

					// Handle different character sets & save the old ones to be restored later
					"/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n" .
					"/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n" .
					"/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n" .
					"/*!40101 SET NAMES utf8 */;\n\n" .

					// Disable foreign key checks
					"/*!40014 SET FOREIGN_KEY_CHECKS=0 */;\n\n", $file);

		// Export tables
		if ($tables === null)
		{
			$tables = $this->get_tables();
		}

		foreach ($tables as $table)
		{
			$result .=	$this->write_data("-- Structure for table `" . $table . "`\n", $file);

			if ($drop_tables)
			{
				$result .=	$this->write_data("DROP TABLE IF EXISTS `" . $table ."`;\n", $file);
			}

			$result .=	$this->write_data($this->get_table_schema($table) . "\n\n", $file);

			if ($this->table_has_rows($table))
			{
				$result .=	$this->write_data("-- Data for table `" . $table . "`\n" .
							// Disable keys for faster import
							"/*!40000 ALTER TABLE `" . $table . "` DISABLE KEYS */;\n", $file);

				$result .= $this->get_table_data($table, $file);

							// Enable keys
				$result .=	$this->write_data("\n/*!40000 ALTER TABLE `" . $table . "` ENABLE KEYS */;\n\n\n", $file);
			}
			else
			{
				$result .=	$this->write_data("-- No rows in table `" . $table . "`\n\n\n", $file);
			}
		}

		if ($export_more)
		{
			// Export views
			$views = $this->get_views();
			foreach ($views as $view)
			{
				$result .=	$this->write_data("-- View `" . $view . "`\n", $file);

				if ($drop_tables)
				{
					$result .=	$this->write_data("DROP VIEW IF EXISTS `" . $view ."`;\n", $file);
				}

				$result .=	$this->write_data($this->get_view_schema($view) . "\n\n\n", $file);
			}

			// Export functions
			$functions = $this->get_functions();
			foreach ($functions as $function)
			{
				$result .=	$this->write_data("-- Function `" . $function . "`\n", $file);

				if ($drop_tables)
				{
					$result .=	$this->write_data("DROP FUNCTION IF EXISTS `" . $function ."`;\n", $file);
				}

				$result .=	$this->write_data($this->get_function_schema($function) . "\n\n\n", $file);
			}

			// Export procedures
			$procedures = $this->get_procedures();
			foreach ($procedures as $procedure)
			{
				$result .=	$this->write_data("-- Procedure `" . $procedure . "`\n", $file);

				if ($drop_tables)
				{
					$result .=	$this->write_data("DROP PROCEDURE IF EXISTS `" . $procedure ."`;\n", $file);
				}

				$result .=	$this->write_data($this->get_procedure_schema($procedure) . "\n\n\n", $file);
			}
		}

					// Enable foreign key checks
		$result .=	$this->write_data("/*!40014 SET FOREIGN_KEY_CHECKS=1 */;\n\n" .

					// Restore original character set
					"/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n" .
					"/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n" .
					"/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n", $file);

		if ($file === null)
			return $result;
	}

	private function get_next_sql_query($sql, &$start = 0)
	{
		$pos = $start;
		$length = strlen($sql);

		$last = '';
		$in_block = '';
		$begin_block_nest = 0;

		while ($pos < $length)
		{
			$char = $sql[$pos];

			// Single quoted string
			if ($char === '\'' && $last != '\\')
			{
				if ($in_block === '\'')
				{
					$in_block = '';
				}
				else if ($in_block === '')
				{
					$in_block = '\'';
				}
			}
			// Double-quoted string
			else if ($char === '\"' && $last != '\\')
			{
				if ($in_block === '\"')
				{
					$in_block = '';
				}
				else if ($in_block === '')
				{
					$in_block = '\"';
				}
			}
			// One-line comments '-- '
			else if ($last === '-' && ($char === ' ' || $char === "\t") && $sql[$pos - 2] === '-' && $in_block === '')
			{
				$in_block = '--';
			}
			// One-line comments '#'
			else if ($char === '#' && $in_block === '')
			{
				$in_block = '#';
			}
			// Break out of one-line comments
			else if ($char === "\n" || $char === "\r")
			{
				if ($in_block === '--' || $in_block === '#')
				{
					$in_block = '';
				}
			}
			// Start multiline comment
			else if ($last === '/' && $char === '*' && $in_block === '')
			{
				$in_block = '/*';
			}
			// End multiline comment
			else if ($in_block === '/*' && $last === '*' && $char === '/')
			{
				$in_block = '';
			}
			// Begin procedure/function BEGIN ... END block
			else if ($char === 'N' && ($in_block === '' || $in_block === 'BEGIN') && substr($sql, $pos - 4, 4) === 'BEGI')
			{
				$in_block = 'BEGIN';
				$begin_block_nest++;
			}
			// End procedure/function BEGIN ... END block
			else if ($in_block === 'BEGIN' && substr($sql, $pos - 2, 2) === 'EN' && $char === 'D' &&
					($sql[$pos - 3] === ' ' || $sql[$pos - 3] === "\n" || $sql[$pos - 3] === "\r" || $sql[$pos - 3] === "\t")
				)
			{
				$begin_block_nest--;

				if ($begin_block_nest === 0)
				{
					$in_block = '';
				}
			}
			else if ($char === ';' && $in_block === '')
			{
				break;
			}

			$last = $char;

			$pos++;
		}

		if ($pos === $start)
			return false;
		$result = substr($sql, $start, $pos - $start);
		$start = $pos + 1;

		return $result;
	}

	public function execute($sql)
	{
		$rows = 0;
		$pos = 0;
		while ($query = trim($this->get_next_sql_query($sql, $pos)))
		{
			$rows += $this->mysql->exec($query);
		}

		return $rows;
	}
}