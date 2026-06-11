<?php

/**
 * Custom receipt-template metadata is stored in the template_name as a trailing marker:
 *   "My Template (Personalizado) [__CUSTOM store=2 base=4]"
 * This keeps DB schema unchanged while allowing us to:
 * - enforce 1 custom copy per (store, base_template)
 * - show a Reset button (re-copy from global/base)
 */
function postemplate_build_custom_marker($store_id, $base_template_id)
{
	$store_id = (int)$store_id;
	$base_template_id = (int)$base_template_id;
	return "[__CUSTOM store={$store_id} base={$base_template_id}]";
}

function postemplate_strip_custom_marker($template_name)
{
	if (!$template_name) {
		return $template_name;
	}
	return preg_replace('/\s*\[__CUSTOM\s+store=\d+\s+base=\d+\]\s*$/i', '', $template_name);
}

function postemplate_parse_custom_marker($template_name)
{
	$meta = array(
		'is_custom' => false,
		'store_id' => null,
		'base_id' => null,
		'display_name' => postemplate_strip_custom_marker($template_name),
	);

	if (!$template_name) {
		return $meta;
	}

	if (preg_match('/\[__CUSTOM\s+store=(\d+)\s+base=(\d+)\]\s*$/i', $template_name, $m)) {
		$meta['is_custom'] = true;
		$meta['store_id'] = (int)$m[1];
		$meta['base_id'] = (int)$m[2];
	}

	return $meta;
}

/**
 * Older custom templates used names like: "X (Personalizado - Loja 242)".
 * If $store_id is provided, we only match that store.
 */
function postemplate_is_legacy_custom_template_name($template_name, $store_id = null)
{
	if (!$template_name) {
		return false;
	}

	if ($store_id !== null) {
		$store_id = (int)$store_id;
		if (stripos($template_name, 'Personalizado - Loja ' . $store_id) !== false) {
			return true;
		}
		return (bool)preg_match('/\(\s*Personalizado\s*-\s*(Loja|Store)\s*' . $store_id . '\s*\)/i', $template_name);
	}

	if (stripos($template_name, 'Personalizado - Loja') !== false) {
		return true;
	}
	return (bool)preg_match('/\(\s*Personalizado\s*-\s*(Loja|Store)\s*\d+\s*\)/i', $template_name);
}

function get_postemplates($data = array(), $store_id = null) 
{
	$model = registry()->get('loader')->model('postemplate');
	return $model->getTemplates($data, $store_id);
}

function get_the_postemplate($id, $field = null) 
{
	$model = registry()->get('loader')->model('postemplate');
	$postemplate = $model->getTemplate($id);
	if ($field && isset($postemplate[$field])) {
		return $postemplate[$field];
	} elseif ($field) {
		return null; // it's equivalent to return;
	}
	return $postemplate;
}
