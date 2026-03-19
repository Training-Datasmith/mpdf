<?php

declare(strict_types=1);

namespace Mpdf\Language;

interface ScriptToLanguageInterface
{
    public function getLanguageByScript($script);

    public function getLanguageDelimiters($language);

}
