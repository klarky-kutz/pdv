<?php
function get_taxrate_id_by_code($id) 
{
	$model = registry()->get('loader')->model('taxrate');
	return $model->getTaxrateIdByCode($id);
}

function get_taxrates() 
{
	$model = registry()->get('loader')->model('taxrate');
	return $model->getTaxrates();
}

function get_the_taxrate($id, $field = null) 
{
	$model = registry()->get('loader')->model('taxrate');
	$taxrate = $model->getTaxrate($id);
	if ($field && isset($taxrate[$field])) {
		return $taxrate[$field];
	} elseif ($field) {
		return null; // it's equivalent to return;
	}
	return $taxrate;
}

/**
 * Get default taxrate ID (first taxrate with 0% or first available)
 */
function get_default_taxrate_id() 
{
	$taxrates = get_taxrates();
	foreach ($taxrates as $taxrate) {
		if (isset($taxrate['taxrate']) && $taxrate['taxrate'] == 0) {
			return $taxrate['taxrate_id'];
		}
	}
	if (!empty($taxrates) && isset($taxrates[0]['taxrate_id'])) {
		return $taxrates[0]['taxrate_id'];
	}
	return 1; // Fallback
}
