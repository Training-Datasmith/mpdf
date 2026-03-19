<?php

declare(strict_types=1);

namespace Mpdf\Container;

interface ContainerInterface
{
    public function get($id);

    public function has($id);

}
