<?php
function product_name($name, $size) 
{
    if (!$size) { $size = 42; }
    return character_limiter($name, ($size-5));
}

function drawLine($size) 
{
    $line = '';
    for ($i = 1; $i <= $size; $i++) {
        $line .= '-';
    }
    return $line."\n";
}

function printLine($str, $size, $sep = ":", $space = NULL) 
{
    $size = $space ? $space : $size;
    $lenght = strlen($str);
    list($first, $second) = explode(":", $str, 2);
    $line = $first . ($sep == ":" ? $sep : '');
    for ($i = 1; $i < ($size - $lenght); $i++) {
        $line .= ' ';
    }
    $line .= ($sep != ":" ? $sep : '') . $second;
    return $line;
}

function printText($text, $size) 
{
    $line = wordwrap($text, $size, "\\n");
    return $line;
}

function taxLine($name, $code, $qty, $amt, $tax, $size) 
{
    return printLine(printLine(printLine(printLine($name . ':' . $code, 16, '') . ':' . $qty, 22, '') . ':' . $amt, 33, '') . ':' . $tax, $size, '');
}

function character_limiter($str, $n = 500, $end_char = '&#8230;') 
{
    if (utf8_strlen($str) < $n) {
        return $str;
    }
    $str = preg_replace('/ {2,}/', ' ', my_str_replace(array("\r", "\n", "\t", "\x0B", "\x0C"), ' ', $str));
    if (utf8_strlen($str) <= $n) {
        return $str;
    }

    $out = '';
    foreach (explode(' ', my_trim($str)) as $val) {
        $out .= $val.' ';
        if (utf8_strlen($out) >= $n) {
            $out = my_trim($out);
            return (utf8_strlen($out) === utf8_strlen($str)) ? $out : $out.$end_char;
        }
    }
}

function word_wrap($str, $charlim = 76) 
{
    is_numeric($charlim) OR $charlim = 76;
    $str = preg_replace('| +|', ' ', $str);
    if (strpos($str, "\r") !== FALSE) {
        $str = my_str_replace(array("\r\n", "\r"), "\n", $str);
    }
    $unwrap = array();
    if (preg_match_all('|\{unwrap\}(.+?)\{/unwrap\}|s', $str, $matches)) {
        for ($i = 0, $c = count($matches[0]); $i < $c; $i++)
        {
            $unwrap[] = $matches[1][$i];
            $str = my_str_replace($matches[0][$i], '{{unwrapped'.$i.'}}', $str);
        }
    }

    $str = wordwrap($str, $charlim, "\n", FALSE);
    $output = '';
    foreach (explode("\n", $str) as $line) {
        if (utf8_strlen($line) <= $charlim) {
            $output .= $line."\n";
            continue;
        }
        $temp = '';
        while (utf8_strlen($line) > $charlim) {
            if (preg_match('!\[url.+\]|://|www\.!', $line)) {
                break;
            }
            $temp .= my_mb_substr($line, 0, $charlim - 1);
            $line = my_mb_substr($line, $charlim - 1);
        }
        if ($temp !== '') {
            $output .= $temp."\n".$line."\n";
        } else {
            $output .= $line."\n";
        }
    }

    if (count($unwrap) > 0) {
        foreach ($unwrap as $key => $val) {
            $output = my_str_replace('{{unwrapped'.$key.'}}', $val, $output);
        }
    }

    return $output;
}