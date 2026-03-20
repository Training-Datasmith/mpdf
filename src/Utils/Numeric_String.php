<?php

namespace Mpdf\Utils;

class Numeric_String
{
    public static function contains_percent_char($string)
    {
        return strstr($string, '%');
    }
    public static function remove_percent_char($string)
    {
        return str_replace('%', '', $string);
    }
}