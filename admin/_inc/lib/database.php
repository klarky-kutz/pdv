<?php
/*
| -----------------------------------------------------
| PRODUCT NAME: 	MODERN POS
| -----------------------------------------------------
| AUTHOR:			ITSOLUTION24.COM
| -----------------------------------------------------
| EMAIL:			info@itsolution24.com
| -----------------------------------------------------
| COPYRIGHT:		RESERVED BY ITSOLUTION24.COM
| -----------------------------------------------------
| WEBSITE:			http://itsolution24.com
| -----------------------------------------------------
*/
class Database extends PDO
{
	public $log = NULL;
	public $db = NULL;
	public $statement = NULL;
	public $option = NULL;

   	public function __construct($dsn, 
                               $username=null, 
                               $password=null, 
                               $driver_options=array())
   	{
   		$this->log = new Log('sql.txt');
   		$default_options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ];
        $options = array_replace($default_options, $driver_options);
      	parent::__construct($dsn, $username, $password, $options);
   	}

   	#[\ReturnTypeWillChange]
   	public function prepare(string $statement, array $option = [])
	{
		$this->statement = $statement;
		$this->option = $option;
		$this->db = parent::prepare($this->statement, $this->option);
		return($this);
	}

	public function bindValue($param, $value, $type = PDO::PARAM_STR)
	{
		return $this->db->bindValue($param, $value, $type);
	}

	public function execute($args = null)
	{
		if (SYNCHRONIZATION) {
			if (
				(
				
				strlen(strstr($this->statement,'INSERT'))>0 
				|| strlen(strstr($this->statement,'UPDATE'))>0 
				|| strlen(strstr($this->statement,'DELETE'))>0
				
				)&&(

				!strlen(strstr($this->statement,"UPDATE `users` SET `ip` = ? WHERE `id` = ?")) > 0

				)
			) {
				
			    $this->log->simplyWrite($this->statement.'|'.serialize($args));
			}
		}
		
		$this->db->execute($args);
	}

	public function fetch($mode = null)
	{
		// Permite uso sem argumento, como PDOStatement::fetch()
		if ($mode === null) {
			return $this->db->fetch();
		}
		return $this->db->fetch($mode);
	}

	public function fetchAll($mode = null)
	{
		// Permite uso sem argumento, como PDOStatement::fetchAll()
		if ($mode === null) {
			return $this->db->fetchAll();
		}
		return $this->db->fetchAll($mode);
	}

	public function fetchColumn($column_number = 0)
	{
		return $this->db->fetchColumn($column_number);
	}

	public function rowCount() 
	{
		return $this->db->rowCount();
	}

	public function lastInsertId(?string $name = null): string|false
	{
		return parent::lastInsertId($name);
	}
}