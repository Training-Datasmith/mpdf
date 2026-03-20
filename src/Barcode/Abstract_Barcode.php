<?php

declare (strict_types=1);
namespace Mpdf\Barcode;

abstract class Abstract_Barcode
{
    /**
     * @var mixed[]
     */
    protected $data;
    /**
     * @return mixed[]
     */
    public function get_data()
    {
        return $this->data;
    }
    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get_key($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }
    /**
     * @return string
     */
    public function get_checksum()
    {
        return $this->get_key('checkdigit');
    }
    /**
     * Convert binary barcode sequence to barcode array
     *
     * @param string $seq
     * @param mixed[] $barcodeData
     *
     * @return mixed[]
     */
    protected function binseq_to_array($seq, array $barcode_data)
    {
        $len = strlen($seq);
        $w = 0;
        $k = 0;
        for ($i = 0; $i < $len; ++$i) {
            $w += 1;
            if ($i == $len - 1 or $i < $len - 1 and $seq[$i] != $seq[$i + 1]) {
                if ($seq[$i] == '1') {
                    $t = true;
                    // bar
                } else {
                    $t = false;
                    // space
                }
                $barcode_data['bcode'][$k] = ['t' => $t, 'w' => $w, 'h' => 1, 'p' => 0];
                $barcode_data['maxw'] += $w;
                ++$k;
                $w = 0;
            }
        }
        return $barcode_data;
    }
}