<?php

declare (strict_types=1);
namespace Mpdf\File;

interface Local_Content_Loader_Interface
{
    /**
     * @return string|null
     */
    public function load($path);
}