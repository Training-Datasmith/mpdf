<?php

declare (strict_types=1);
namespace Mpdf\Language;

interface Script_To_Language_Interface
{
    public function get_language_by_script($script);
    public function get_language_delimiters($language);
}