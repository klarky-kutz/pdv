<?php
/*
| ----------------------------------------------------------------------------
| PRODUCT NAME: 	Modern POS - Point of Sale with Stock Management System
| ----------------------------------------------------------------------------
| AUTHOR:			ITsolution24.com
| ----------------------------------------------------------------------------
| EMAIL:			itsolution24bd@gmail.com
| ----------------------------------------------------------------------------
| COPYRIGHT:		RESERVED BY ITsolution24.com
| ----------------------------------------------------------------------------
| WEBSITE:			http://ITsolution24.com
| ----------------------------------------------------------------------------
*/
class ModelTagconverter extends Model 
{
	public function convert($tags, $dataArr, $message) 
	{
    	if(count($tags)) {
			foreach ($tags as $tag) {
				$rtag = my_str_replace(array('[',']'), '', $tag);
				if(isset($dataArr[$rtag])) {
					$message = my_str_replace($tag, $dataArr[$rtag], $message);
				} else {
					$message = my_str_replace($tag, ' ', $message);
				}
			}
		}
		return $message;
	}
}