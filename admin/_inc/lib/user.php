<?php
/*
| -----------------------------------------------------
| PRODUCT NAME: 	Modern POS - Point of sale with Stock Management System
| -----------------------------------------------------
| AUTHOR:			ITsolution24.com
| -----------------------------------------------------
| EMAIL:			contact@itsolution24.com
| -----------------------------------------------------
| COPYRIGHT:		RESERVED BY ITsolution.com
| -----------------------------------------------------
| WEBSITE:			http://itsolution24.com
| -----------------------------------------------------
*/
class User 
{
	private $db;
	private $request;
	private $session;
	private $id;
	private $group_id;
	private $username;
	private $permission = array();
	private $preference = array();

	public function __construct($registry)
	{
		$this->db = registry()->get('db');
		$this->request = registry()->get('request');
		$this->session = registry()->get('session');

        $user = '';
        if (isset($this->request->get['api_key'])) {
            $statement = $this->db->prepare("SELECT * FROM `users` LEFT JOIN `user_to_store` as `u2s` ON (`users`.`id` = `u2s`.`user_id`) WHERE `id` = ? AND `u2s`.`status` = ?");
            $statement->execute(array(1, 1));
            $user = $statement->fetch(PDO::FETCH_ASSOC);
        }
        if (isset($this->session->data['id'])) {
            $statement = $this->db->prepare("SELECT * FROM `users` LEFT JOIN `user_to_store` as `u2s` ON (`users`.`id` = `u2s`.`user_id`) WHERE `id` = ? AND `u2s`.`status` = ?");
            $statement->execute(array((int)$this->session->data['id'], 1));
            $user = $statement->fetch(PDO::FETCH_ASSOC);
        }
        if ($user) {
            $this->id = $user['id'];
            $this->username = $user['username'];
            $this->group_id = $user['group_id'];
            $this->preference = valid_unserialize($user['preference']);
            
            // CORREÇÃO: Sincroniza tenant_id na sessão a cada requisição (modo SaaS multi-tenant)
            if (isset($user['tenant_id']) && $user['tenant_id']) {
                $_SESSION['tenant_id'] = (int)$user['tenant_id'];
                
                // CORREÇÃO: Verifica status da assinatura do tenant
                // Bloqueia acesso se o tenant estiver com pagamento pendente ou inativo
                $statement = $this->db->prepare("SELECT `subscription_status` FROM `tenants` WHERE `tenant_id` = ?");
                $statement->execute(array((int)$user['tenant_id']));
                $tenant = $statement->fetch(PDO::FETCH_ASSOC);
                
                if ($tenant && isset($tenant['subscription_status'])) {
                    $blockedStatuses = ['pending', 'inactive', 'canceled'];
                    if (in_array($tenant['subscription_status'], $blockedStatuses, true)) {
                        // Força logout e armazena mensagem para exibição
                        $_SESSION['access_blocked_reason'] = 'subscription_' . $tenant['subscription_status'];
                        $this->logout();
                        return;
                    }
                }
            } else {
                // Se não houver tenant_id no usuário, remove da sessão (modo single-tenant)
                if (isset($_SESSION['tenant_id'])) {
                    unset($_SESSION['tenant_id']);
                }
            }

            $statement = $this->db->prepare("UPDATE `users` SET `ip` = ? WHERE `id` = ?");
            $statement->execute(array( get_real_ip(), $user['id']));

            $statement = $this->db->prepare("SELECT `permission` FROM `user_group` WHERE `group_id` = ?");
            $statement->execute(array($user['group_id']));
            $user_group = $statement->fetch(PDO::FETCH_ASSOC);

            // Verificar se o grupo existe antes de tentar acessar permissões
            if ($user_group && isset($user_group['permission'])) {
                $permissions = valid_unserialize($user_group['permission']);
                if (is_array($permissions)) {
                    foreach ($permissions as $key => $value) {
                        $this->permission[$key] = $value;
                    }
                }
            }

            $statement = $this->db->prepare("DELETE FROM `login_logs` WHERE `created_at` < ?");
            $statement->execute(array(date('Y-m-d H:i:s', strtotime('-30 day'))));
        } else {
            $this->logout();
        }
	}

	public function login($username, $password) 
	{
		$statement = $this->db->prepare("SELECT * FROM `users` LEFT JOIN `user_to_store` as u2s ON (`users`.`id` = `u2s`.`user_id`) WHERE (`email` = ? OR `mobile` = ?) ");

        $statement->execute(array($username, $username));
		$the_user = $statement->fetch(PDO::FETCH_ASSOC);

		if ($the_user) {
            if (!password_verify($password, $the_user['password'])) {
                return false;
            }
            
            // CORREÇÃO: Verifica status da assinatura ANTES de permitir login
            if (isset($the_user['tenant_id']) && $the_user['tenant_id']) {
                $statement = $this->db->prepare("SELECT `subscription_status`, `company_name` FROM `tenants` WHERE `tenant_id` = ?");
                $statement->execute(array((int)$the_user['tenant_id']));
                $tenant = $statement->fetch(PDO::FETCH_ASSOC);
                
                if ($tenant && isset($tenant['subscription_status'])) {
                    $blockedStatuses = ['pending', 'inactive', 'canceled'];
                    if (in_array($tenant['subscription_status'], $blockedStatuses, true)) {
                        // Não permite login e retorna mensagem específica
                        $_SESSION['login_error'] = 'Acesso bloqueado. Seu pagamento está pendente de confirmação. Aguarde a aprovação ou entre em contato com o suporte.';
                        return false;
                    }
                }
            }
            
			unset($this->session->data['email']);
			unset($this->session->data['username']);
			unset($this->session->data['ref_url']);
			$this->session->data['id'] = $the_user['id'];
			$this->id = $the_user['id'];
			$this->username = $the_user['username'];
			$this->group_id = $the_user['group_id'];
			
			// CORREÇÃO: Sincroniza tenant_id na sessão (modo SaaS multi-tenant)
			if (isset($the_user['tenant_id']) && $the_user['tenant_id']) {
				$_SESSION['tenant_id'] = (int)$the_user['tenant_id'];
			} else {
				// Se não houver tenant_id no usuário, remove da sessão (modo single-tenant)
				unset($_SESSION['tenant_id']);
			}

			$statement = $this->db->prepare("SELECT `permission` FROM `user_group` WHERE `group_id` = ?");
			$statement->execute(array((int)$the_user['group_id']));
			$the_user_group = $statement->fetch(PDO::FETCH_ASSOC);

			// Verificar se o grupo existe antes de tentar acessar permissões
			if ($the_user_group && isset($the_user_group['permission'])) {
				$permissions = valid_unserialize($the_user_group['permission']);

				if (is_array($permissions)) {
					foreach ($permissions as $key => $value) {
						$this->permission[$key] = $value;
					}
				}
			}
			return true;
		}
		return false;
	}

	public function logout() 
	{
		unset($this->session->data['id']);
		$this->id = '';
		$this->username = '';
		
		// CORREÇÃO: Limpa tenant_id da sessão no logout
		if (isset($_SESSION['tenant_id'])) {
			unset($_SESSION['tenant_id']);
		}
	}

	public function hasPermission($key, $value) 
	{
		if (isset($this->permission[$key])) {
			return isset($this->permission[$key][$value]);
		} else {
			return false;
		}
	}

	public function isLogged() 
	{
		return $this->id;
	}

	public function getId() 
	{
		return $this->id;
	}

	public function getUserName($id = null, $field = 'username') 
	{
		if ($id) {
			$statement = $this->db->prepare("SELECT * FROM `users` WHERE `id` = ?");
			$statement->execute(array((int)$id));
			$user = $statement->fetch(PDO::FETCH_ASSOC);
			return isset($user[$field]) ? $user[$field] : null;
		}
		return $this->username;
	}
	
	public function getGroupId() 
	{
		return $this->group_id;
	}	

	public function getRole()
	{
		$statement = $this->db->prepare("SELECT `name` FROM `user_group` WHERE `group_id` = ?");
		$statement->execute(array((int)$this->getGroupId()));
		
		return $statement->fetch(PDO::FETCH_ASSOC)['name'];
	}

	public function updatePreference($preference, $user_id)
	{
		if ($user_id) {
			$statement = $this->db->prepare("UPDATE `users` SET `preference` = ? WHERE `id` = ? ");
			$statement->execute(array(serialize($preference), $user_id));
		}
	}

	public function getPreference($index, $default = null) 
	{
		return isset($this->preference[$index]) ? $this->preference[$index] : $default;
	}

	public function getAllPreference()
	{
		return $this->preference;
	}

	public function getBelongsStore($user_id = null)
	{
		$user_id = $user_id ? $user_id : $this->getId();

		$statement = $this->db->prepare("SELECT `s`.* FROM `stores` s LEFT JOIN `user_to_store` u2s ON (`s`.`store_id` = `u2s`.`store_id`) WHERE `user_id` = ?");
		$statement->execute(array($user_id));
		return $statement->fetchAll(PDO::FETCH_ASSOC);

	}

	public function countBelongsStore($user_id = null)
	{
		$user_id = $user_id ? $user_id : $this->getId();
		
		$statement = $this->db->prepare("SELECT * FROM `user_to_store` WHERE `user_id` = ?");
		$statement->execute(array($user_id));

		return $statement->rowCount();

	}

	public function getSingleStoreId($user_id = null)
	{
		$user_id = $user_id ? $user_id : $this->getId();
		$statement = $this->db->prepare("SELECT * FROM `user_to_store` WHERE `user_id` = ?");
		$statement->execute(array($user_id));
		$store = $statement->fetch(PDO::FETCH_ASSOC);

		if ($store['store_id']) {
			return $store['store_id'];
		}
		return false;
	}
}