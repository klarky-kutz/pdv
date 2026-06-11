<?php
function get_store_id_by_code($code) 
{
	$model = registry()->get('loader')->model('store');
	return $model->getStoreIdByCode($code);
}

function store($field = null) 
{
	global $store;

	if (!$store) {
		return null;
	}

	if (!$field) {
		return $store->getAll();
	}
	return $store->get($field);
}

function store_id() 
{
	global $store;
	if (!isset($store) || !is_object($store)) {
		return null;
	}
	return $store->get('store_id');
}

function is_multistore()
{
	global $store;
	$store = $store->getStore($store_id);
	return $store->isMultiStore();
}

function store_field($index, $store_id = null) 
{
	$store_id = $store_id ? $store_id : store_id();
	$store = registry()->get('loader')->model('store');
	$store = $store->getStore($store_id);
	return isset($store[$index]) ? $store[$index] : null;
}

function get_stores($all = false) 
{
	global $user;
	if ($all || user_group_id() == 1) {
		$storeModel = registry()->get('loader')->model('store');
		return $storeModel->getStores();
	} else {
		return $user->getBelongsStore(user_id());
	}
}

function get_store_ids($data = array()) 
{
	$storeModel = registry()->get('loader')->model('store');
	return $storeModel->getStoreIDs();
}

function get_preference($index = null, $store_id = null)
{
	$store_id = $store_id ? $store_id : store_id();
	$storeModel = registry()->get('loader')->model('store');
	$store = $storeModel->getStore($store_id);
	if ($store) {
		$preference = valid_unserialize($store['preference']);
	} else {
		$preference = array();
	}
	return isset($preference[$index]) ? $preference[$index] : null;
}

function get_all_preference($store_id = null) 
{
	$store_id = $store_id ? $store_id : store_id();
	$storeModel = registry()->get('loader')->model('store');
	$store = $storeModel->getStore($store_id);
	$preference = valid_unserialize($store['preference']);
	return $preference;
}

function get_cashiers($store_id = null) 
{
	$store_id = $store_id ? $store_id : store_id();
	$storeModel = registry()->get('loader')->model('store');
	return $storeModel->getCashiers($store_id);
}

function get_salesmans($store_id = null) 
{
	$store_id = $store_id ? $store_id : store_id();
	$storeModel = registry()->get('loader')->model('store');
	return $storeModel->getSalesmans($store_id);
}

function get_printers($store_id = null) 
{
	$store_id = $store_id ? $store_id : store_id();
	$printer_model = registry()->get('loader')->model('printer');
	return $printer_model->getPrinters();
}