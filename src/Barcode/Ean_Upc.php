<?php

declare (strict_types=1);
namespace Mpdf\Barcode;

/**
 * EAN13 and UPC-A barcodes.
 * EAN13: European Article Numbering international retail product code
 * UPC-A: Universal product code seen on almost all retail products in the USA and Canada
 * UPC-E: Short version of UPC symbol
 */
class Ean_Upc extends \Mpdf\Barcode\Abstract_Barcode implements \Mpdf\Barcode\Barcode_Interface
{
    /**
     * @param string $code
     * @param int $length
     * @param float $leftMargin
     * @param float $rightMargin
     * @param float $xDim
     * @param float $barHeight
     */
    public function __construct($code, $length, $left_margin, $right_margin, $x_dim, $bar_height)
    {
        $this->init($code, $length);
        $this->data['lightmL'] = $left_margin;
        // LEFT light margin =  x X-dim (http://www.gs1uk.org)
        $this->data['lightmR'] = $right_margin;
        // RIGHT light margin =  x X-dim (http://www.gs1uk.org)
        $this->data['nom-X'] = $x_dim;
        // Nominal value for X-dim in mm (http://www.gs1uk.org)
        $this->data['nom-H'] = $bar_height;
        // Nominal bar height in mm incl. numerals (http://www.gs1uk.org)
    }
    /**
     * @param string $code
     * @param int $length
     */
    private function init($code, $length)
    {
        if (preg_match('/[\D]+/', $code)) {
            throw new \Mpdf\Barcode\Barcode_Exception(sprintf('Invalid EAN UPC barcode value "%s"', $code));
        }
        $upce = false;
        $checkdigit = false;
        if ($length == 6) {
            $length = 12;
            // UPC-A
            $upce = true;
            // UPC-E mode
        }
        $data_length = $length - 1;
        // Padding
        $code = str_pad($code, $data_length, '0', STR_PAD_LEFT);
        $code_length = strlen($code);
        // Calculate check digit
        $sum_a = 0;
        for ($i = 1; $i < $data_length; $i += 2) {
            $sum_a += $code[$i];
        }
        if ($length > 12) {
            $sum_a *= 3;
        }
        $sum_b = 0;
        for ($i = 0; $i < $data_length; $i += 2) {
            $sum_b += $code[$i];
        }
        if ($length < 13) {
            $sum_b *= 3;
        }
        $r = ($sum_a + $sum_b) % 10;
        if ($r > 0) {
            $r = 10 - $r;
        }
        if ($code_length == $data_length) {
            // Add check digit
            $code .= $r;
            $checkdigit = $r;
        } elseif ($r !== (int) $code[$data_length]) {
            // Wrong checkdigit
            throw new \Mpdf\Barcode\Barcode_Exception(sprintf('Invalid EAN UPC barcode value "%s"', $code));
        }
        if ($length == 12) {
            // UPC-A
            $code = '0' . $code;
            ++$length;
        }
        if ($upce) {
            // Convert UPC-A to UPC-E
            $tmp = substr($code, 4, 3);
            $prod_code = (int) substr($code, 7, 5);
            // product code
            $invalid_upce = false;
            if ($tmp == '000' or $tmp == '100' or $tmp == '200') {
                // Manufacturer code ends in 000, 100, or 200
                $upce_code = substr($code, 2, 2) . substr($code, 9, 3) . substr($code, 4, 1);
                if ($prod_code > 999) {
                    $invalid_upce = true;
                }
            } else {
                $tmp = substr($code, 5, 2);
                if ($tmp == '00') {
                    // Manufacturer code ends in 00
                    $upce_code = substr($code, 2, 3) . substr($code, 10, 2) . '3';
                    if ($prod_code > 99) {
                        $invalid_upce = true;
                    }
                } else {
                    $tmp = substr($code, 6, 1);
                    if ($tmp == '0') {
                        // Manufacturer code ends in 0
                        $upce_code = substr($code, 2, 4) . substr($code, 11, 1) . '4';
                        if ($prod_code > 9) {
                            $invalid_upce = true;
                        }
                    } else {
                        // Manufacturer code does not end in zero
                        $upce_code = substr($code, 2, 5) . substr($code, 11, 1);
                        if ($prod_code > 9) {
                            $invalid_upce = true;
                        }
                    }
                }
            }
            if ($invalid_upce) {
                throw new \Mpdf\Barcode\Barcode_Exception('UPC-A cannot produce a valid UPC-E barcode');
            }
        }
        // Convert digits to bars
        $codes = ['A' => [
            // left odd parity
            '0' => '0001101',
            '1' => '0011001',
            '2' => '0010011',
            '3' => '0111101',
            '4' => '0100011',
            '5' => '0110001',
            '6' => '0101111',
            '7' => '0111011',
            '8' => '0110111',
            '9' => '0001011',
        ], 'B' => [
            // left even parity
            '0' => '0100111',
            '1' => '0110011',
            '2' => '0011011',
            '3' => '0100001',
            '4' => '0011101',
            '5' => '0111001',
            '6' => '0000101',
            '7' => '0010001',
            '8' => '0001001',
            '9' => '0010111',
        ], 'C' => [
            // right
            '0' => '1110010',
            '1' => '1100110',
            '2' => '1101100',
            '3' => '1000010',
            '4' => '1011100',
            '5' => '1001110',
            '6' => '1010000',
            '7' => '1000100',
            '8' => '1001000',
            '9' => '1110100',
        ]];
        $parities = ['0' => ['A', 'A', 'A', 'A', 'A', 'A'], '1' => ['A', 'A', 'B', 'A', 'B', 'B'], '2' => ['A', 'A', 'B', 'B', 'A', 'B'], '3' => ['A', 'A', 'B', 'B', 'B', 'A'], '4' => ['A', 'B', 'A', 'A', 'B', 'B'], '5' => ['A', 'B', 'B', 'A', 'A', 'B'], '6' => ['A', 'B', 'B', 'B', 'A', 'A'], '7' => ['A', 'B', 'A', 'B', 'A', 'B'], '8' => ['A', 'B', 'A', 'B', 'B', 'A'], '9' => ['A', 'B', 'B', 'A', 'B', 'A']];
        $upce_parities = [];
        $upce_parities[0] = ['0' => ['B', 'B', 'B', 'A', 'A', 'A'], '1' => ['B', 'B', 'A', 'B', 'A', 'A'], '2' => ['B', 'B', 'A', 'A', 'B', 'A'], '3' => ['B', 'B', 'A', 'A', 'A', 'B'], '4' => ['B', 'A', 'B', 'B', 'A', 'A'], '5' => ['B', 'A', 'A', 'B', 'B', 'A'], '6' => ['B', 'A', 'A', 'A', 'B', 'B'], '7' => ['B', 'A', 'B', 'A', 'B', 'A'], '8' => ['B', 'A', 'B', 'A', 'A', 'B'], '9' => ['B', 'A', 'A', 'B', 'A', 'B']];
        $upce_parities[1] = ['0' => ['A', 'A', 'A', 'B', 'B', 'B'], '1' => ['A', 'A', 'B', 'A', 'B', 'B'], '2' => ['A', 'A', 'B', 'B', 'A', 'B'], '3' => ['A', 'A', 'B', 'B', 'B', 'A'], '4' => ['A', 'B', 'A', 'A', 'B', 'B'], '5' => ['A', 'B', 'B', 'A', 'A', 'B'], '6' => ['A', 'B', 'B', 'B', 'A', 'A'], '7' => ['A', 'B', 'A', 'B', 'A', 'B'], '8' => ['A', 'B', 'A', 'B', 'B', 'A'], '9' => ['A', 'B', 'B', 'A', 'B', 'A']];
        $k = 0;
        $seq = '101';
        // left guard bar
        if ($upce && isset($upce_code)) {
            $bararray = ['code' => $upce_code, 'maxw' => 0, 'maxh' => 1, 'bcode' => []];
            $p = $upce_parities[$code[1]][$r];
            for ($i = 0; $i < 6; ++$i) {
                $seq .= $codes[$p[$i]][$upce_code[$i]];
            }
            $seq .= '010101';
            // right guard bar
        } else {
            $bararray = ['code' => $code, 'maxw' => 0, 'maxh' => 1, 'bcode' => []];
            $half_len = ceil($length / 2);
            if ($length == 8) {
                for ($i = 0; $i < $half_len; ++$i) {
                    $seq .= $codes['A'][$code[$i]];
                }
            } else {
                $p = $parities[$code[0]];
                for ($i = 1; $i < $half_len; ++$i) {
                    $seq .= $codes[$p[$i - 1]][$code[$i]];
                }
            }
            $seq .= '01010';
            // center guard bar
            for ($i = $half_len; $i < $length; ++$i) {
                $seq .= $codes['C'][$code[(int) $i]];
            }
            $seq .= '101';
            // right guard bar
        }
        $clen = strlen($seq);
        $w = 0;
        for ($i = 0; $i < $clen; ++$i) {
            $w += 1;
            if ($i == $clen - 1 or $i < $clen - 1 and $seq[$i] != $seq[$i + 1]) {
                if ($seq[$i] == '1') {
                    $t = true;
                    // bar
                } else {
                    $t = false;
                    // space
                }
                $bararray['bcode'][$k] = ['t' => $t, 'w' => $w, 'h' => 1, 'p' => 0];
                $bararray['maxw'] += $w;
                ++$k;
                $w = 0;
            }
        }
        $bararray['checkdigit'] = $checkdigit;
        $this->data = $bararray;
    }
    /**
     * @inheritdoc
     */
    public function get_type()
    {
        return 'EANUPC';
    }
}