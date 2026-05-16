<?php
if (!function_exists('utf8_strtoupper'))
{
	function utf8_strtoupper($string) {
		return mb_strtoupper($string ?: '');
	}
}

if (!function_exists('utf8_strtolower'))
{
	function utf8_strtolower($string) {
		return mb_strtolower($string ?: '');
	}
}

if (!function_exists('utf8_ucfirst'))
{
	function utf8_ucfirst($string) {
		return mb_convert_case($string, MB_CASE_TITLE, "UTF-8");
	}
}
if (!function_exists('trans'))
{
	function trans($key, $case = '')
	{
		$ignore_prefix = array('_', 'menu', 'label', 'text', 'button', 'title', 'success', 'hint', 'placeholder', 'error_', 'btn_', 'nav_', 'label_', 'text_', 'button_', 'hint_', 'placeholder_', 'tab_');
		
		$key = str_replace($ignore_prefix, ' ', $key);

		switch ($case) {
			case 'UC': //UPPERCASE
				$key = utf8_strtoupper($key);
				break;
			case 'LC': // LOWERCASE
				$key = utf8_strtolower($key);
				break;
			case 'SC': //SENTENCECASE
				$key = preg_replace_callback('/((?:^|[!.?])\s*)(\p{Ll})/u', function($match) { return $match[1].utf8_strtoupper($match[2], 'UTF-8'); }, $key);
				break;
			case 'WC': //WORDCASE
				// TODO:
			default:
				$key = utf8_ucfirst($key);
				break;
		}

		return trim($key);
	}
}?>
<div class="table-responsive">
<table class="table table-bordered table-striped table-condensed">
<thead>
	<tr class="active">
		<th class="text-center"> <?php echo trans('text_shortcut'); ?></th>
		<th> <?php echo trans('text_description'); ?></th>
	</tr>
</thead>
<tbody>
	<tr>
		<td class="text-center">
			<kbd>Alt + P</kbd>
		</td>
		<td> <?php echo trans('text_focus_product_searchbox'); ?></td>
	</tr>
	<tr>
		<td class="text-center">
			<kbd>Alt + C</kbd>
		</td>
		<td> <?php echo trans('text_focus_customer_searchbox'); ?></td>
	</tr>
	<tr>
		<td class="text-center">
			<kbd>Alt + A</kbd>
		</td>
		<td> <?php echo trans('text_customer_add'); ?></td>
	</tr>
	<tr>
		<td class="text-center">
			<kbd>Alt + I</kbd>
		</td>
		<td> <?php echo trans('text_focus_discount_input_field'); ?></td>
	</tr>
	<tr>
		<td class="text-center">
			<kbd>Alt + T</kbd>
		</td>
		<td> <?php echo trans('text_focus_tax_input_field'); ?></td>
	</tr>
	<tr>
		<td class="text-center">
			<kbd>Alt + S</kbd>
		</td>
		<td> <?php echo trans('text_focus_shipping_charge_field'); ?></td>
	</tr>
	<tr>
		<td class="text-center">
			<kbd>Alt + O</kbd>
		</td>
		<td> <?php echo trans('text_focus_others_charge_field'); ?></td>
	</tr>
	<tr>
		<td class="text-center">
			<kbd>Alt + H</kbd>
		</td>
		<td> <?php echo trans('text_holding_order'); ?></td>
	</tr>
	<tr>
		<td class="text-center">
			<kbd>Alt + Z</kbd>
		</td>
		<td> <?php echo trans('text_pay_now'); ?></td>
	</tr>
</tbody>
</table>
</div>