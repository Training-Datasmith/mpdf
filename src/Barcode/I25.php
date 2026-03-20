<?php

declare (strict_types=1);
namespace Mpdf\Barcode;

/**
 * Interleaved 2 of 5 barcodes.
 * Compact numeric code, widely used in industry, air cargo
 * Contains digits (0 to 9) and encodes the data in the width of both bars and spaces.
 */
class I25 extends \Mpdf\Barcode\Abstract_Barcode implements \Mpdf\Barcode\Barcode_Interface
{
    /**
     * @param string $code
     * @param float $topBottomMargin
     * @param float $printRatio
     * @param bool $checksum
     */
    public function __construct($code, $top_bottom_margin, $print_ratio, $checksum = false, $quiet_zone_left = null, $quiet_zone_right = null)
    {
        $this->init($code, $print_ratio, $checksum);
        $this->data['nom-X'] = 0.381;
        // Nominal value for X-dim (bar width) in mm (2 X min. spec.)
        $this->data['nom-H'] = 10;
        // Nominal value for Height of Full bar in mm (non-spec.)
        $this->data['lightmL'] = $quiet_zone_left !== null ? $quiet_zone_left : 10;
        // LEFT light margin =  x X-dim (spec.)
        $this->data['lightmR'] = $quiet_zone_right !== null ? $quiet_zone_right : 10;
        // RIGHT light margin =  x X-dim (spec.)
        $this->data['lightTB'] = $top_bottom_margin;
        // TOP/BOTTOM light margin =  x X-dim (non-spec.)
    }
    /**
     * @param string $code
     * @param float $printRatio
     * @param bool $checksum
     */
    private function init($code, $print_ratio, $checksum)
    {
        $chr = ['0' => '11221', '1' => '21112', '2' => '12112', '3' => '22111', '4' => '11212', '5' => '21211', '6' => '12211', '7' => '11122', '8' => '21121', '9' => '12121', 'A' => '11', 'Z' => '21'];
        $checkdigit = '';
        if ($checksum) {
            // add checksum
            $checkdigit = $this->checksum($code);
            $code .= $checkdigit;
        }
        if (strlen($code) % 2 != 0) {
            // add leading zero if code-length is odd
            $code = '0' . $code;
        }
        // add start and stop codes
        $code = 'AA' . strtolower($code) . 'ZA';
        $bararray = ['code' => $code, 'maxw' => 0, 'maxh' => 1, 'bcode' => []];
        $k = 0;
        $clen = strlen($code);
        for ($i = 0; $i < $clen; $i = $i + 2) {
            $char_bar = $code[$i];
            $char_space = $code[$i + 1];
            if (!isset($chr[$char_bar]) or !isset($chr[$char_space])) {
                // invalid character
                throw new \Mpdf\Barcode\Barcode_Exception(sprintf('Invalid I25 barcode value "%s"', $code));
            }
            // create a bar-space sequence
            $seq = '';
            $chrlen = strlen($chr[$char_bar]);
            for ($s = 0; $s < $chrlen; $s++) {
                $seq .= $chr[$char_bar][$s] . $chr[$char_space][$s];
            }
            $seqlen = strlen($seq);
            for ($j = 0; $j < $seqlen; ++$j) {
                if ($j % 2 == 0) {
                    $t = true;
                    // bar
                } else {
                    $t = false;
                    // space
                }
                $x = $seq[$j];
                if ($x == 2) {
                    $w = $print_ratio;
                } else {
                    $w = 1;
                }
                $bararray['bcode'][$k] = ['t' => $t, 'w' => $w, 'h' => 1, 'p' => 0];
                $bararray['maxw'] += $w;
                ++$k;
            }
        }
        $bararray['checkdigit'] = $checkdigit;
        $this->data = $bararray;
    }
    /**
     * Checksum for standard 2 of 5 barcodes.
     *
     * @param string $code
     * @return int
     */
    private function checksum($code)
    {
        $len = strlen($code);
        $sum = 0;
        for ($i = 0; $i < $len; $i += 2) {
            $sum += $code[$i];
        }
        $sum *= 3;
        for ($i = 1; $i < $len; $i += 2) {
            $sum += $code[$i];
        }
        $r = $sum % 10;
        if ($r > 0) {
            return 10 - $r;
        }
        return $r;
    }
    /**
     * @inheritdoc
     */
    public function get_type()
    {
        return 'I25';
    }
}