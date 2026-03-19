<?php

declare(strict_types=1);

namespace Mpdf\File;

interface LocalContentLoaderInterface
{
    /**
     * @return string|null
     */
    public function load($path);

}
