<?php

declare (strict_types=1);
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return 0 === strncmp($haystack, $needle, \strlen($needle));
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        if ('' === $needle || $needle === $haystack) {
            return true;
        }
        if ('' === $haystack) {
            return false;
        }
        $needle_length = \strlen($needle);
        return $needle_length <= \strlen($haystack) && 0 === substr_compare($haystack, $needle, -$needle_length);
    }
}