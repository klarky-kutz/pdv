<?php
function get_unit_id_by_code($id) 
{
	
	$model = registry()->get('loader')->model('unit');
	return $model->getUnitIdByCode($id);
}

function get_units() 
{
	$model = registry()->get('loader')->model('unit');
	return $model->getUnits();
}

function get_the_unit($id, $field = null) 
{
	$model = registry()->get('loader')->model('unit');
	$unit = $model->getUnit($id);
	if ($field && isset($unit[$field])) {
		return $unit[$field];
	} elseif ($field) {
		return null; // it's equivalent to return;
	}
	return $unit;
}

/**
 * Get default unit ID (first available unit)
 */
function get_default_unit_id() 
{
	$units = get_units();
	if (!empty($units) && isset($units[0]['unit_id'])) {
		return $units[0]['unit_id'];
	}
	return 1; // Fallback
}
