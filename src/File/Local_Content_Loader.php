<?php

declare (strict_types=1);
namespace Mpdf\File;

class Local_Content_Loader implements \Mpdf\File\Local_Content_Loader_Interface
{
    public function load($path)
    {
        return file_get_contents($path);
    }
}