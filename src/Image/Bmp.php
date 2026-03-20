<?php

declare (strict_types=1);
namespace Mpdf\Image;

use Mpdf\Mpdf;
class Bmp
{
    /**
     * @var Mpdf
     */
    private $mpdf;
    public function __construct(Mpdf $mpdf)
    {
        $this->mpdf = $mpdf;
    }
    public function _get_bm_pimage($data, $file)
    {
        // Adapted from script by Valentin Schmidt
        // http://staff.dasdeck.de/valentin/fpdf/fpdf_bmp/
        $bf_off_bits = $this->_fourbytes2int_le(substr($data, 10, 4));
        $width = $this->_fourbytes2int_le(substr($data, 18, 4));
        $height = $this->_fourbytes2int_le(substr($data, 22, 4));
        $flip = $height < 0;
        if ($flip) {
            $height = -$height;
        }
        $bi_bit_count = $this->_twobytes2int_le(substr($data, 28, 2));
        $bi_compression = $this->_fourbytes2int_le(substr($data, 30, 4));
        $info = ['w' => $width, 'h' => $height];
        if ($bi_bit_count < 16) {
            $info['cs'] = 'Indexed';
            $info['bpc'] = $bi_bit_count;
            $pal_str = substr($data, 54, $bf_off_bits - 54);
            $pal = '';
            $cnt = strlen($pal_str) / 4;
            for ($i = 0; $i < $cnt; $i++) {
                $n = 4 * $i;
                $pal .= $pal_str[$n + 2] . $pal_str[$n + 1] . $pal_str[$n];
            }
            $info['pal'] = $pal;
        } else {
            $info['cs'] = 'DeviceRGB';
            $info['bpc'] = 8;
        }
        if ($this->mpdf->restrict_color_space == 1 || $this->mpdf->PDFX || $this->mpdf->restrict_color_space == 3) {
            if ($this->mpdf->PDFA && !$this->mpdf->pdf_aauto || $this->mpdf->PDFX && !$this->mpdf->pdf_xauto) {
                $this->mpdf->pdfa_xwarnings[] = "Image cannot be converted to suitable colour space for PDFA or PDFX file - {$file} - (Image replaced by 'no-image'.)";
            }
            return ['error' => "BMP Image cannot be converted to suitable colour space - {$file} - (Image replaced by 'no-image'.)"];
        }
        $bi_x_pels_per_meter = $this->_fourbytes2int_le(substr($data, 38, 4));
        // horizontal pixels per meter, usually set to zero
        //$biYPelsPerMeter=$this->_fourbytes2int_le(substr($data,42,4));	// vertical pixels per meter, usually set to zero
        $bi_x_pels_per_meter = round($bi_x_pels_per_meter / 1000 * 25.4);
        //$biYPelsPerMeter=round($biYPelsPerMeter/1000 *25.4);
        $info['set-dpi'] = $bi_x_pels_per_meter;
        switch ($bi_compression) {
            case 0:
                $str = substr($data, $bf_off_bits);
                break;
            case 1:
                # BI_RLE8
                $str = $this->rle8_decode(substr($data, $bf_off_bits), $width);
                break;
            case 2:
                # BI_RLE4
                $str = $this->rle4_decode(substr($data, $bf_off_bits), $width);
                break;
        }
        $bmpdata = '';
        $pad_cnt = (4 - ceil($width / (8 / $bi_bit_count)) % 4) % 4;
        switch ($bi_bit_count) {
            case 1:
            case 4:
            case 8:
                $w = floor($width / (8 / $bi_bit_count)) + ($width % (8 / $bi_bit_count) ? 1 : 0);
                $w_row = $w + $pad_cnt;
                if ($flip) {
                    for ($y = 0; $y < $height; $y++) {
                        $y0 = $y * $w_row;
                        for ($x = 0; $x < $w; $x++) {
                            $bmpdata .= $str[$y0 + $x];
                        }
                    }
                } else {
                    for ($y = $height - 1; $y >= 0; $y--) {
                        $y0 = $y * $w_row;
                        for ($x = 0; $x < $w; $x++) {
                            $bmpdata .= $str[$y0 + $x];
                        }
                    }
                }
                break;
            case 16:
                $w_row = $width * 2 + $pad_cnt;
                if ($flip) {
                    for ($y = 0; $y < $height; $y++) {
                        $y0 = $y * $w_row;
                        for ($x = 0; $x < $width; $x++) {
                            $n = ord($str[$y0 + 2 * $x + 1]) * 256 + ord($str[$y0 + 2 * $x]);
                            $b = ($n & 31) << 3;
                            $g = ($n & 992) >> 2;
                            $r = ($n & 31744) >> 7;
                            $bmpdata .= chr($r) . chr($g) . chr($b);
                        }
                    }
                } else {
                    for ($y = $height - 1; $y >= 0; $y--) {
                        $y0 = $y * $w_row;
                        for ($x = 0; $x < $width; $x++) {
                            $n = ord($str[$y0 + 2 * $x + 1]) * 256 + ord($str[$y0 + 2 * $x]);
                            $b = ($n & 31) << 3;
                            $g = ($n & 992) >> 2;
                            $r = ($n & 31744) >> 7;
                            $bmpdata .= chr($r) . chr($g) . chr($b);
                        }
                    }
                }
                break;
            case 24:
            case 32:
                $byte_cnt = $bi_bit_count / 8;
                $w_row = $width * $byte_cnt + $pad_cnt;
                if ($flip) {
                    for ($y = 0; $y < $height; $y++) {
                        $y0 = $y * $w_row;
                        for ($x = 0; $x < $width; $x++) {
                            $i = $y0 + $x * $byte_cnt;
                            # + 1
                            $bmpdata .= $str[$i + 2] . $str[$i + 1] . $str[$i];
                        }
                    }
                } else {
                    for ($y = $height - 1; $y >= 0; $y--) {
                        $y0 = $y * $w_row;
                        for ($x = 0; $x < $width; $x++) {
                            $i = $y0 + $x * $byte_cnt;
                            # + 1
                            $bmpdata .= $str[$i + 2] . $str[$i + 1] . $str[$i];
                        }
                    }
                }
                break;
            default:
                return ['error' => 'Error parsing BMP image - Unsupported image biBitCount'];
        }
        if ($this->mpdf->compress) {
            $bmpdata = gzcompress($bmpdata);
            $info['f'] = 'FlateDecode';
        }
        $info['data'] = $bmpdata;
        $info['type'] = 'bmp';
        return $info;
    }
    /**
     * Read a 4-byte integer from string
     *
     * @param $s
     * @return int
     */
    private function _fourbytes2int_le($s)
    {
        return (ord($s[3]) << 24) + (ord($s[2]) << 16) + (ord($s[1]) << 8) + ord($s[0]);
    }
    /**
     * Read a 2-byte integer from string
     *
     * @param $s
     * @return int
     */
    private function _twobytes2int_le($s)
    {
        return (ord(substr($s, 1, 1)) << 8) + ord(substr($s, 0, 1));
    }
    /**
     * Decoder for RLE8 compression in windows bitmaps
     *
     * @see http://msdn.microsoft.com/library/default.asp?url=/library/en-us/gdi/bitmaps_6x0u.asp
     * @param $str
     * @param $width
     * @return string
     */
    private function rle8_decode($str, $width)
    {
        $line_width = $width + (3 - ($width - 1) % 4);
        $out = '';
        $cnt = strlen($str);
        for ($i = 0; $i < $cnt; $i++) {
            $o = ord($str[$i]);
            if ($o === 0) {
                # ESCAPE
                $i++;
                switch (ord($str[$i])) {
                    case 0:
                        # NEW LINE
                        $pad_cnt = $line_width - strlen($out) % $line_width;
                        if ($pad_cnt < $line_width) {
                            $out .= str_repeat(chr(0), $pad_cnt);
                            # pad line
                        }
                        break;
                    case 1:
                        # END OF FILE
                        $pad_cnt = $line_width - strlen($out) % $line_width;
                        if ($pad_cnt < $line_width) {
                            $out .= str_repeat(chr(0), $pad_cnt);
                            # pad line
                        }
                        break 2;
                    case 2:
                        # DELTA
                        $i += 2;
                        break;
                    default:
                        # ABSOLUTE MODE
                        $num = ord($str[$i]);
                        for ($j = 0; $j < $num; $j++) {
                            $out .= $str[++$i];
                        }
                        if ($num % 2) {
                            $i++;
                        }
                }
            } else {
                $out .= str_repeat($str[++$i], $o);
            }
        }
        return $out;
    }
    /**
     * Decoder for RLE4 compression in windows bitmaps
     *
     * @see http://msdn.microsoft.com/library/default.asp?url=/library/en-us/gdi/bitmaps_6x0u.asp
     * @param $str
     * @param $width
     * @return string
     */
    private function rle4_decode($str, $width)
    {
        $w = floor($width / 2) + $width % 2;
        $line_width = $w + (3 - ($width - 1) / 2 % 4);
        $pixels = [];
        $cnt = strlen($str);
        for ($i = 0; $i < $cnt; $i++) {
            $o = ord($str[$i]);
            if ($o === 0) {
                # ESCAPE
                $i++;
                switch (ord($str[$i])) {
                    case 0:
                        # NEW LINE
                        while (count($pixels) % $line_width !== 0) {
                            $pixels[] = 0;
                        }
                        break;
                    case 1:
                        # END OF FILE
                        while (count($pixels) % $line_width !== 0) {
                            $pixels[] = 0;
                        }
                        break 2;
                    case 2:
                        # DELTA
                        $i += 2;
                        break;
                    default:
                        # ABSOLUTE MODE
                        $num = ord($str[$i]);
                        for ($j = 0; $j < $num; $j++) {
                            if ($j % 2 === 0) {
                                $c = ord($str[++$i]);
                                $pixels[] = ($c & 240) >> 4;
                            } else {
                                $pixels[] = $c & 15;
                                //FIXME: undefined var
                            }
                        }
                        if ($num % 2) {
                            $i++;
                        }
                }
            } else {
                $c = ord($str[++$i]);
                for ($j = 0; $j < $o; $j++) {
                    $pixels[] = $j % 2 === 0 ? ($c & 240) >> 4 : $c & 15;
                }
            }
        }
        $out = '';
        if (count($pixels) % 2) {
            $pixels[] = 0;
        }
        $cnt = count($pixels) / 2;
        for ($i = 0; $i < $cnt; $i++) {
            $out .= chr(16 * $pixels[2 * $i] + $pixels[2 * $i + 1]);
        }
        return $out;
    }
}