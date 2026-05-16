<?php
function valdateMobilePhone($number) 
{
    // Remove caracteres especiais antes de validar (aceita formatos brasileiros como (27) 98820-3550)
    $cleanNumber = preg_replace('/[^0-9]/', '', $number);
    // Valida se tem entre 10 e 11 dígitos (telefone brasileiro com DDD)
    $length = strlen($cleanNumber);
    return $length >= 10 && $length <= 11;
}

// PHP 5.2 and above. built-in function by PHP provides a much more powerful sanitize capability.
function validateString($str)
{
    return filter_var($str, FILTER_UNSAFE_RAW); # only 'String' is allowed eg. '<br>HELLO</br>' => 'HELLO'
}

// Password Strongness Checked
function checkPasswordStrongness($password)
{
    return 'ok';
    $err = '';
    if (strlen($password) < 8) {
        $err = "Your Password Must Contain At Least 8 Characters!";
    }
    elseif(!preg_match("#[0-9]+#",$password)) {
        $err = "Your Password Must Contain At Least 1 Number!";
    }
    elseif(!preg_match("#[A-Z]+#",$password)) {
        $err = "Your Password Must Contain At Least 1 Capital Letter!";
    }
    elseif(!preg_match("#[a-z]+#",$password)) {
        $err = "Your Password Must Contain At Least 1 Lowercase Letter!";
    }
    if (!$err) {
        return 'ok';
    }
    return $err;
}

// PHP 5.2 and above
function validateEmail($email)
{
  return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// PHP 5.2 and above.
function validateInteger($value)
{
    return filter_var($value, FILTER_VALIDATE_INT); # int
}

// PHP 5.2 and above.
function validateFloat($value)
{
    return filter_var($value, FILTER_VALIDATE_FLOAT); // float
}

function validateAlphanumeric($string)
{
    return ctype_alnum($string);
}

function SanitizeAlphanumeric($string)
{
    return preg_replace('/[^a-zA-Z0-9]/', '', $string);
}

function validateExpireDate($date) 
{
    return time() < strtotime($date);
}

function isItValidDate($date) 
{
    if(preg_match("/^(\d{4})-(\d{2})-(\d{2})$/", $date, $matches)) {
        if(checkdate($matches[2], $matches[3], $matches[1])) { 
            return true;
        }
    }
} 

function isItValidTime($input) 
{
    return preg_match("/^([0-1][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/", $input);
} 

// $input is valid HH:MM AM/PM format
function isItValidTime12($input) 
{
    return preg_match("/^(?:0[1-9]|1[0-2]):[0-5][0-9] (am|pm|AM|PM)$/", $input);
}

function compareFloatNumbers($float1, $float2, $operator='=')
{
    // Check numbers to 5 digits of precision
    $epsilon = 0.00001;
    
    $float1 = (float)$float1;
    $float2 = (float)$float2;
    
    switch ($operator)
    {
        // equal
        case "=":
        case "eq":
        {
            if (abs($float1 - $float2) < $epsilon) {
                return true;
            }
            break;  
        }
        // less than
        case "<":
        case "lt":
        {
            if (abs($float1 - $float2) < $epsilon) {
                return false;
            }
            else
            {
                if ($float1 < $float2) {
                    return true;
                }
            }
            break;  
        }
        // less than or equal
        case "<=":
        case "lte":
        {
            if (compareFloatNumbers($float1, $float2, '<') || compareFloatNumbers($float1, $float2, '=')) {
                return true;
            }
            break;  
        }
        // greater than
        case ">":
        case "gt":
        {
            if (abs($float1 - $float2) < $epsilon) {
                return false;
            }
            else
            {
                if ($float1 > $float2) {
                    return true;
                }
            }
            break;  
        }
        // greater than or equal
        case ">=":
        case "gte":
        {
            if (compareFloatNumbers($float1, $float2, '>') || compareFloatNumbers($float1, $float2, '=')) {
                return true;
            }
            break;  
        }
        case "<>":
        case "!=":
        case "ne":
        {
            if (abs($float1 - $float2) > $epsilon) {
                return true;
            }
            break;  
        }
        default:
        {
            die("Unknown operator '".$operator."' in compareFloatNumbers()");   
        }
    }
    
    return false;
}

function url_exist($url)
{
    $url = parse_url($url);
 
    if (!$url)
    {
        return false;
    }
 
    $url = array_map('trim', $url);
    $url['port'] = (!isset($url['port'])) ? 80 : (int)$url['port'];
    $path = (isset($url['path'])) ? $url['path'] : '';
 
    if ($path == '')
    {
        $path = '/';
    }
 
    $path .= (isset($url['query'])) ? '?$url[query]' : '';
 
    if (isset($url['host']) AND $url['host'] != gethostbyname($url['host']))
    {
        if (PHP_VERSION >= 5)
        {
            $headers = get_headers('$url[scheme]://$url[host]:$url[port]$path');
        }
        else
        {
            $fp = fsockopen($url['host'], $url['port'], $errno, $errstr, 30);
 
            if (!$fp)
            {
                return false;
            }
            fputs($fp, 'HEAD $path HTTP/1.1\r\nHost: $url[host]\r\n\r\n');
            $headers = fread($fp, 4096);
            fclose($fp);
        }
        $headers = (is_array($headers)) ? implode('\n', $headers) : $headers;
        return (bool)preg_match('#^HTTP/.*\s+[(200|301|302)]+\s#i', $headers);
    }
    return false;
}

// PHP 5.2 and above.
function validateUrl($url)
{
  return filter_var($url, FILTER_VALIDATE_URL);
}

// Validate Proxy
// This function will let us detect proxy visitors even those that are behind anonymous proxy.
function validateProxy() 
{
    if ($_SERVER['HTTP_X_FORWARDED_FOR']
       || $_SERVER['HTTP_X_FORWARDED']
       || $_SERVER['HTTP_FORWARDED_FOR']
       || $_SERVER['HTTP_VIA']
       || in_array($_SERVER['REMOTE_PORT'], array(8080,80,6588,8000,3128,553,554))
       || fsockopen($_SERVER['REMOTE_ADDR'], 80, $errno, $errstr, 30))
    {
        exit('Proxy detected');
    }
}

function validatePassword($password) 
{
    #must contain 8 characters, 1 uppercase, 1 lowercase and 1 number
    return preg_match('/^(?=^.{8,}$)((?=.*[A-Za-z0-9])(?=.*[A-Z])(?=.*[a-z]))^.*$/', $password);
}

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
