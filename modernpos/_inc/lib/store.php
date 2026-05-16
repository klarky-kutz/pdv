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
class Store 
{
	private $registry;
	private $request;
	private $db;
	private $session;
	private $data;

	public function __construct($registry) 
	{
		$this->registry = $registry;

		$this->request = $this->registry->get('request');

		$this->db = registry()->get('db');

		$this->session = registry()->get('session');

		// CORREÇÃO: Não define store_id = 1 automaticamente
		// Permite que o sistema determine o store correto baseado no usuário logado
		if (!isset($this->session->data['store_id']) && isset($this->session->data['id'])) {
			// Tenta obter o store_id correto do usuário via user_to_store
			$statement = $this->db->prepare("SELECT `store_id` FROM `user_to_store` WHERE `user_id` = ? AND `status` = 1 ORDER BY `store_id` ASC LIMIT 1");
			$statement->execute(array((int)$this->session->data['id']));
			$user_store = $statement->fetch(PDO::FETCH_ASSOC);
			
			if ($user_store && isset($user_store['store_id'])) {
				$this->session->data['store_id'] = (int)$user_store['store_id'];
			} else {
				// Fallback: primeira loja disponível do tenant do usuário
				$statement = $this->db->prepare("SELECT s.store_id FROM `stores` s INNER JOIN `users` u ON s.tenant_id = u.tenant_id WHERE u.id = ? ORDER BY s.store_id ASC LIMIT 1");
				$statement->execute(array((int)$this->session->data['id']));
				$tenant_store = $statement->fetch(PDO::FETCH_ASSOC);
				
				if ($tenant_store && isset($tenant_store['store_id'])) {
					$this->session->data['store_id'] = (int)$tenant_store['store_id'];
				}
			}
		}

		if (isset($this->session->data['store_id'])) {

			$store_id = $this->session->data['store_id'];

			$statement = $this->db->prepare("SELECT * FROM `stores` WHERE `store_id` = ?");
			$statement->execute(array($store_id));
			$this->data = $statement->fetch(PDO::FETCH_ASSOC);

			if (isset($this->data['store_id'])) {
				$this->session->data['store_id'] = $this->data['store_id'];
			}
		}
	}

	public function openTheStore($store_id = 1) 
	{
		$store_id = $store_id ? (int)$store_id : 1;
		$statement = $this->db->prepare("SELECT * FROM `stores` WHERE `store_id` = ?");
		$statement->execute(array($store_id));
		$store = $statement->fetch(PDO::FETCH_ASSOC);
		if (isset($store['store_id'])) {
			unset($this->session->data['store_id']);
			$this->session->data['store_id'] = $store['store_id'];

			// Garantir que exista pelo menos o cliente balcão (customer_id = 1)
			// e que esteja vinculado à loja atual (evita tela de clientes vazia e
			// erros no POS que assume customer_id=1 por padrão).
			try {
				$statement = $this->db->prepare("SELECT `customer_id` FROM `customers` WHERE `customer_id` = 1 LIMIT 1");
				$statement->execute();
				$walking_customer_exists = (int)$statement->fetchColumn();
				if (!$walking_customer_exists) {
					$statement = $this->db->prepare("INSERT INTO `customers` (`customer_id`, `customer_name`, `customer_email`, `created_at`) VALUES (1, 'Walking Customer', 'wc@itsolution24.com', NOW())");
					$statement->execute();
				}

				$statement = $this->db->prepare("SELECT `c2s_id` FROM `customer_to_store` WHERE `customer_id` = 1 AND `store_id` = ? LIMIT 1");
				$statement->execute(array((int)$store['store_id']));
				$walking_customer_linked = (int)$statement->fetchColumn();
				if (!$walking_customer_linked) {
					$statement = $this->db->prepare("INSERT INTO `customer_to_store` (`customer_id`, `store_id`, `sort_order`) VALUES (1, ?, 1)");
					$statement->execute(array((int)$store['store_id']));
				}
			} catch (Throwable $e) {
				// Silencioso: não deve impedir o usuário de trocar de loja.
			}
		}
	}

	public function setStore($store_id)
	{
		$store_id = $store_id ? (int)$store_id : 1;

		$statement = $this->db->prepare("SELECT * FROM `stores` WHERE `store_id` = ?");
		$statement->execute(array($store_id));
		$this->data = $statement->fetch(PDO::FETCH_ASSOC);
	}

	public function getAll()
	{
		return $this->data;
	}

	public function get($key) 
	{
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}

	public function isMultiStore()
	{
		$statement = $this->db->prepare("SELECT * FROM `stores`");
		$statement->execute();

		return $statement->rowCount();
	}

	public function getSql() 
	{
		$statement = $this->db->prepare("SHOW TABLES");
		$statement->execute();
		$tables = $statement->fetchAll(PDO::FETCH_NUM);

		$output = '';

		foreach ($tables as $table) {

		  $table = $table[0];

		  $output .= 'TRUNCATE TABLE `' . $table . '`;' . "\n\n";

		  $statement = $this->db->prepare("SELECT * FROM `" . $table . "`");
		  $statement->execute();
		  $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

		  foreach ($rows as $result) {
		    $fields = '';

		    foreach (array_keys($result) as $value) {
		      $fields .= '`' . $value . '`, ';
		    }

		    $values = '';

		    foreach (array_values($result) as $value) {
		      $value = my_str_replace(array("\x00", "\x0a", "\x0d", "\x1a"), array('\0', '\n', '\r', '\Z'), $value);
		      $value = my_str_replace(array("\n", "\r", "\t"), array('\n', '\r', '\t'), $value);
		      $value = my_str_replace('\\', '\\\\',  $value);
		      $value = my_str_replace('\'', '\\\'',  $value);
		      $value = my_str_replace('\\\n', '\n',  $value);
		      $value = my_str_replace('\\\r', '\r',  $value);
		      $value = my_str_replace('\\\t', '\t',  $value);

		      $values .= '\'' . $value . '\', ';
		    }

		    $output .= 'INSERT INTO `' . $table . '` (' . preg_replace('/, $/', '', $fields) . ') VALUES (' . preg_replace('/, $/', '', $values) . ');' . "\n";
		  }

		  $output .= "\n\n";
		} 

		return $output;
	}
}