<?php
if (! function_exists('remove_invisible_characters'))
{
    /**
     * Remove Invisible Characters
     *
     * This prevents sandwiching null characters
     * between ascii characters, like Java\0script.
     *
     * @param   string
     * @param   bool
     * @return  string
     */
    function remove_invisible_characters($str, $url_encoded = true)
    {
        $non_displayables = array();

        // every control character except newline (dec 10),
        // carriage return (dec 13) and horizontal tab (dec 09)
        if ($url_encoded)
        {
            $non_displayables[] = '/%0[0-8bcef]/i'; // url encoded 00-08, 11, 12, 14, 15
            $non_displayables[] = '/%1[0-9a-f]/i';  // url encoded 16-31
            $non_displayables[] = '/%7f/i'; // url encoded 127
        }

        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';   // 00-08, 11, 12, 14-31, 127

        do
        {
        	if ($str) {
            	$str = preg_replace($non_displayables, '', $str, -1, $count);
        	} else {
        		$count = 0;
        	}
        }
        while ($count);

        return $str;
    }
}

if ( ! function_exists('xss_clean'))
{
	/**
	 * XSS Filtering
	 *
	 * @param	string
	 * @param	bool	whether or not the content is an image file
	 * @return	string
	 */
	function xss_clean($str, $is_image = FALSE)
	{
		return (new Security())->xss_clean($str, $is_image);
	}
}

if ( ! function_exists('strip_image_tags'))
{
	/**
	 * Strip Image Tags
	 *
	 * @param	string
	 * @return	string
	 */
	function strip_image_tags($str)
	{
		return (new Security())->strip_image_tags($str);
	}
}

if ( ! function_exists('encode_php_tags'))
{
	/**
	 * Convert PHP tags to entities
	 *
	 * @param	string
	 * @return	string
	 */
	function encode_php_tags($str)
	{
		return str_replace(array('<?', '?>'), array('&lt;?', '?&gt;'), $str);
	}
}

if (! function_exists('escape'))
{
    function escape($data) 
	{
		$find = array(
			"\\",
			"\0",
			"\x1a",
			"'",
			'"',
			'&amp;',
			"\\\\\\'s",
			"\\\\\\'es",
			"\\\\\\'t",
			"\\\\\\'re",
			"\\\\\\'ll",
			"\\\\\\'m",
		);

		$replace = array(
			"\\\\",
			"\\0",
			"\Z",
			"\'",
			'\"',
			'&',
			"'s",
			"'es",
			"'t",
			"'re",
			"'m",
		);
		return str_replace($find, $replace, (new \Security())->xss_clean($data));
	}
}

if (! function_exists('_e'))
{
    function _e($data) 
	{
		echo escape($data);
	}
}

if (! function_exists('my_html_entity_decode'))
{
	function my_html_entity_decode($string, $flags = ENT_QUOTES, $character_set = 'UTF-8') 
	{
		return $string ? html_entity_decode(escape($string), $flags, $character_set) : '';
	}
}

if (! function_exists('escape_form'))
{
    function escape_form($data) 
    {
        return trim($data);
    }
}

if (! function_exists('escape_html'))
{
    function escape_html($var, $double_encode = true)
    {
        if (empty($var))
        {
            return $var;
        }

        if (is_array($var))
        {
            foreach (array_keys($var) as $key)
            {
                $var[$key] = escape_html($var[$key], $double_encode);
            }

            return $var;
        }

        return htmlspecialchars($var, ENT_QUOTES, 'UTF-8', $double_encode);
    }
}

if (! function_exists('remove_tags'))
{
    function remove_tags($str)
    {
        return strip_tags($str);
    }
}

if ( ! function_exists('sanitize_filename'))
{
    /**
     * Sanitize Filename
     *
     * @param   string
     * @return  string
     */
    function sanitize_filename($filename)
    {
        return (new Security())->sanitize_filename($filename);
    }
}

if ( ! function_exists('encode_data'))
{
    function encode_data($data)
    {
        return base64_encode($data);
    }
}

if ( ! function_exists('decode_data'))
{
    function decode_data($data, $strict = false)
    {
        return base64_decode($data, $strict);
    }
}