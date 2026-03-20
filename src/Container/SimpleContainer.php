<?php

declare (strict_types=1);
namespace Mpdf\Container;

class Simple_Container implements \Mpdf\Container\Container_Interface
{
    private $services;
    public function __construct(array $services)
    {
        $this->services = $services;
    }
    public function get($id)
    {
        if (!$this->has($id)) {
            throw new \Mpdf\Container\Not_Found_Exception(sprintf('Unable to find service of key "%s"', $id));
        }
        return $this->services[$id];
    }
    public function has($id)
    {
        return isset($this->services[$id]);
    }
    public function get_services()
    {
        return $this->services;
    }
}