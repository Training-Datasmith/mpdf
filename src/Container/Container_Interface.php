<?php

declare (strict_types=1);
namespace Mpdf\Container;

interface Container_Interface
{
    public function get($id);
    public function has($id);
}