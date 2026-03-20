<?php

namespace Mpdf;

class Page_Box implements \ArrayAccess
{
    private $container = [];
    public function __construct()
    {
        $this->container = ['current' => null, 'outer_width_LR' => null, 'outer_width_TB' => null, 'using' => null];
    }
    /**
     * @return void
     */
    #[\Return_Type_Will_Change]
    public function offsetSet($offset, $value)
    {
        if (!$this->offsetExists($offset)) {
            throw new \Mpdf\Mpdf_Exception('Invalid key to set for PageBox');
        }
        $this->container[$offset] = $value;
    }
    /**
     * @return bool
     */
    #[\Return_Type_Will_Change]
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->container);
    }
    /**
     * @return void
     */
    #[\Return_Type_Will_Change]
    public function offsetUnset($offset)
    {
        if (!$this->offsetExists($offset)) {
            throw new \Mpdf\Mpdf_Exception('Invalid key to set for PageBox');
        }
        $this->container[$offset] = null;
    }
    /**
     * @return mixed
     */
    #[\Return_Type_Will_Change]
    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            throw new \Mpdf\Mpdf_Exception('Invalid key to set for PageBox');
        }
        return $this->container[$offset];
    }
}