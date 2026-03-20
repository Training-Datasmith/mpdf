<?php

declare (strict_types=1);
namespace Mpdf\Barcode;

interface Barcode_Interface
{
    /**
     * @return string
     */
    public function get_type();
    /**
     * @return mixed[]
     */
    public function get_data();
    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get_key($key);
    /**
     * @return string
     */
    public function get_checksum();
}