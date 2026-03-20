<?php

declare (strict_types=1);
namespace Mpdf\Color;

use Mpdf\Mpdf;
class Color_Converter
{
    public const MODE_GRAYSCALE = 1;
    public const MODE_SPOT = 2;
    public const MODE_RGB = 3;
    public const MODE_CMYK = 4;
    public const MODE_RGBA = 5;
    public const MODE_CMYKA = 6;
    private $mpdf;
    private $color_mode_converter;
    private $color_space_restrictor;
    private $cache;
    public function __construct(Mpdf $mpdf, Color_Mode_Converter $color_mode_converter, Color_Space_Restrictor $color_space_restrictor)
    {
        $this->mpdf = $mpdf;
        $this->color_mode_converter = $color_mode_converter;
        $this->color_space_restrictor = $color_space_restrictor;
        $this->cache = [];
    }
    public function convert($color, array &$pdfa_xwarnings = [])
    {
        $color = strtolower(trim($color));
        if ($color === 'transparent' || $color === 'inherit') {
            return false;
        }
        if (isset(Named_Colors::$colors[$color])) {
            $color = Named_Colors::$colors[$color];
        }
        if (!isset($this->cache[$color])) {
            $c = $this->convert_plain($color, $pdfa_xwarnings);
            $cstr = '';
            if (is_array($c)) {
                $c = array_pad($c, 6, 0);
                $cstr = pack('a1ccccc', $c[0], round($c[1]) & 0xff, round($c[2]) & 0xff, round($c[3]) & 0xff, round($c[4]) & 0xff, round($c[5]) & 0xff);
            }
            $this->cache[$color] = $cstr;
        }
        return $this->cache[$color];
    }
    public function lighten(array $c)
    {
        $this->ensure_binary_color_format($c);
        if ($c[0] == static::MODE_RGB || $c[0] == static::MODE_RGBA) {
            list($h, $s, $l) = $this->color_mode_converter->rgb2hsl(ord($c[1]) / 255, ord($c[2]) / 255, ord($c[3]) / 255);
            $l += (1 - $l) * 0.8;
            list($r, $g, $b) = $this->color_mode_converter->hsl2rgb($h, $s, $l);
            $ret = [3, $r, $g, $b];
        } elseif ($c[0] == static::MODE_CMYK || $c[0] == static::MODE_CMYKA) {
            $ret = [4, max(0, ord($c[1]) - 20), max(0, ord($c[2]) - 20), max(0, ord($c[3]) - 20), max(0, ord($c[4]) - 20)];
        } elseif ($c[0] == static::MODE_GRAYSCALE) {
            $ret = [1, min(255, ord($c[1]) + 32)];
        }
        $c = array_pad($ret, 6, 0);
        return pack('a1ccccc', $c[0], round($c[1]) & 0xff, round($c[2]) & 0xff, round($c[3]) & 0xff, round($c[4]) & 0xff, round($c[5]) & 0xff);
    }
    public function darken(array $c)
    {
        $this->ensure_binary_color_format($c);
        if ($c[0] == static::MODE_RGB || $c[0] == static::MODE_RGBA) {
            list($h, $s, $l) = $this->color_mode_converter->rgb2hsl(ord($c[1]) / 255, ord($c[2]) / 255, ord($c[3]) / 255);
            $s *= 0.25;
            $l *= 0.75;
            list($r, $g, $b) = $this->color_mode_converter->hsl2rgb($h, $s, $l);
            $ret = [3, $r, $g, $b];
        } elseif ($c[0] == static::MODE_CMYK || $c[0] == static::MODE_CMYKA) {
            $ret = [4, min(100, ord($c[1]) + 20), min(100, ord($c[2]) + 20), min(100, ord($c[3]) + 20), min(100, ord($c[4]) + 20)];
        } elseif ($c[0] == static::MODE_GRAYSCALE) {
            $ret = [1, max(0, ord($c[1]) - 32)];
        }
        $c = array_pad($ret, 6, 0);
        return pack('a1ccccc', $c[0], $c[1] & 0xff, $c[2] & 0xff, $c[3] & 0xff, $c[4] & 0xff, $c[5] & 0xff);
    }
    /**
     * @param string $c
     * @return float[]
     */
    public function invert($c)
    {
        $this->ensure_binary_color_format($c);
        if ($c[0] == static::MODE_RGB || $c[0] == static::MODE_RGBA) {
            return [3, 255 - ord($c[1]), 255 - ord($c[2]), 255 - ord($c[3])];
        }
        if ($c[0] == static::MODE_CMYK || $c[0] == static::MODE_CMYKA) {
            return [4, 100 - ord($c[1]), 100 - ord($c[2]), 100 - ord($c[3]), 100 - ord($c[4])];
        }
        if ($c[0] == static::MODE_GRAYSCALE) {
            return [1, 255 - ord($c[1])];
        }
        // Cannot cope with non-RGB colors at present
        throw new \Mpdf\Mpdf_Exception('Trying to invert non-RGB color');
    }
    /**
     * @param string $c Binary color string
     *
     * @return string
     */
    public function col_ato_string($c)
    {
        if ($c[0] == static::MODE_GRAYSCALE) {
            return 'rgb(' . ord($c[1]) . ', ' . ord($c[1]) . ', ' . ord($c[1]) . ')';
        }
        if ($c[0] == static::MODE_SPOT) {
            return 'spot(' . ord($c[1]) . ', ' . ord($c[2]) . ')';
        }
        if ($c[0] == static::MODE_RGB) {
            return 'rgb(' . ord($c[1]) . ', ' . ord($c[2]) . ', ' . ord($c[3]) . ')';
        }
        if ($c[0] == static::MODE_CMYK) {
            return 'cmyk(' . ord($c[1]) . ', ' . ord($c[2]) . ', ' . ord($c[3]) . ', ' . ord($c[4]) . ')';
        }
        if ($c[0] == static::MODE_RGBA) {
            return 'rgba(' . ord($c[1]) . ', ' . ord($c[2]) . ', ' . ord($c[3]) . ', ' . sprintf('%0.2F', ord($c[4]) / 100) . ')';
        }
        if ($c[0] == static::MODE_CMYKA) {
            return 'cmyka(' . ord($c[1]) . ', ' . ord($c[2]) . ', ' . ord($c[3]) . ', ' . ord($c[4]) . ', ' . sprintf('%0.2F', ord($c[5]) / 100) . ')';
        }
        return '';
    }
    /**
     * @param string $color
     * @param string[] $PDFAXwarnings
     *
     * @return bool|float[]
     */
    private function convert_plain($color, array &$pdfa_xwarnings = [])
    {
        $c = false;
        if (preg_match('/^[\d]+$/', $color)) {
            $c = [static::MODE_GRAYSCALE, $color];
            // i.e. integer only
        } elseif (strpos($color, '#') === 0) {
            // case of #nnnnnn or #nnn
            $c = $this->process_hash_color($color);
        } elseif (preg_match('/(rgba|rgb|device-cmyka|cmyka|device-cmyk|cmyk|hsla|hsl|spot)\((.*?)\)/', $color, $m)) {
            // ignore colors containing CSS variables
            if (str_starts_with(mb_strtolower($m[2]), 'var(--')) {
                $m[2] = '0, 0, 0, 100';
            }
            $c = $this->process_mode_color($m[1], explode(',', $m[2]));
        }
        if ($this->mpdf->PDFA || $this->mpdf->PDFX || $this->mpdf->restrict_color_space) {
            return $this->restrict_color_space($c, $color, $pdfa_xwarnings);
        }
        return $c;
    }
    /**
     * @param string $color
     *
     * @return float[]
     */
    private function process_hash_color($color)
    {
        // in case of Background: #CCC url() x-repeat etc.
        $cor = preg_replace('/\s+.*/', '', $color);
        // Turn #RGB into #RRGGBB
        if (strlen($cor) === 4) {
            $cor = '#' . $cor[1] . $cor[1] . $cor[2] . $cor[2] . $cor[3] . $cor[3];
        }
        $r = self::safe_hex_dec(substr($cor, 1, 2));
        $g = self::safe_hex_dec(substr($cor, 3, 2));
        $b = self::safe_hex_dec(substr($cor, 5, 2));
        return [3, $r, $g, $b];
    }
    /**
     * @param $mode
     * @param mixed[] $cores
     * @return bool|float[]
     */
    private function process_mode_color($mode, array $cores)
    {
        $c = false;
        $cores = $this->convert_percent_core_values($mode, $cores);
        switch ($mode) {
            case 'rgb':
                return [static::MODE_RGB, $cores[0], $cores[1], $cores[2]];
            case 'rgba':
                return [static::MODE_RGBA, $cores[0], $cores[1], $cores[2], $cores[3] * 100];
            case 'cmyk':
            case 'device-cmyk':
                return [static::MODE_CMYK, $cores[0], $cores[1], $cores[2], $cores[3]];
            case 'cmyka':
            case 'device-cmyka':
                return [static::MODE_CMYKA, $cores[0], $cores[1], $cores[2], $cores[3], $cores[4] * 100];
            case 'hsl':
                $conv = $this->color_mode_converter->hsl2rgb($cores[0] / 360, $cores[1], $cores[2]);
                return [static::MODE_RGB, $conv[0], $conv[1], $conv[2]];
            case 'hsla':
                $conv = $this->color_mode_converter->hsl2rgb($cores[0] / 360, $cores[1], $cores[2]);
                return [static::MODE_RGBA, $conv[0], $conv[1], $conv[2], $cores[3] * 100];
            case 'spot':
                $name = strtoupper(trim($cores[0]));
                if (!isset($this->mpdf->spot_colors[$name])) {
                    if (isset($cores[5])) {
                        $this->mpdf->add_spot_color($cores[0], $cores[2], $cores[3], $cores[4], $cores[5]);
                    } else {
                        throw new \Mpdf\Mpdf_Exception(sprintf('Undefined spot color "%s"', $name));
                    }
                }
                return [static::MODE_SPOT, $this->mpdf->spot_colors[$name]['i'], $cores[1]];
        }
        return $c;
    }
    /**
     * @param string $mode
     * @param mixed[] $cores
     *
     * @return float[]
     */
    private function convert_percent_core_values($mode, array $cores)
    {
        $ncores = count($cores);
        if (strpos($cores[0], '%') !== false) {
            $cores[0] = (float) $cores[0];
            if ($mode === 'rgb' || $mode === 'rgba') {
                $cores[0] = (int) ($cores[0] * 255 / 100);
            }
        }
        if ($ncores > 1 && strpos($cores[1], '%') !== false) {
            $cores[1] = (float) $cores[1];
            if ($mode === 'rgb' || $mode === 'rgba') {
                $cores[1] = (int) ($cores[1] * 255 / 100);
            }
            if ($mode === 'hsl' || $mode === 'hsla') {
                $cores[1] /= 100;
            }
        }
        if ($ncores > 2 && strpos($cores[2], '%') !== false) {
            $cores[2] = (float) $cores[2];
            if ($mode === 'rgb' || $mode === 'rgba') {
                $cores[2] = (int) ($cores[2] * 255 / 100);
            }
            if ($mode === 'hsl' || $mode === 'hsla') {
                $cores[2] /= 100;
            }
        }
        if ($ncores > 3 && strpos($cores[3], '%') !== false) {
            $cores[3] = (float) $cores[3];
        }
        return $cores;
    }
    /**
     * @param mixed $c
     * @param string $color
     * @param string[] $PDFAXwarnings
     *
     * @return float[]
     */
    private function restrict_color_space($c, $color, array &$pdfa_xwarnings = [])
    {
        return $this->color_space_restrictor->restrict_color_space($c, $color, $pdfa_xwarnings);
    }
    /**
     * @param string $color Binary color string
     */
    private function ensure_binary_color_format($color)
    {
        if (!is_string($color)) {
            throw new \Mpdf\Mpdf_Exception('Invalid color input, binary color string expected');
        }
        if (strlen($color) !== 6) {
            throw new \Mpdf\Mpdf_Exception('Invalid color input, binary color string expected');
        }
        if (!in_array($color[0], [static::MODE_GRAYSCALE, static::MODE_SPOT, static::MODE_RGB, static::MODE_CMYK, static::MODE_RGBA, static::MODE_CMYKA])) {
            throw new \Mpdf\Mpdf_Exception('Invalid color input, invalid color mode in binary color string');
        }
    }
    /**
     * Converts the given hexString to its decimal representation when all digits are hexadecimal
     *
     * @param string $hexString The hexadecimal string to convert
     * @return float|int The decimal representation of hexString or 0 if not all digits of hexString are hexadecimal
     */
    private function safe_hex_dec($hex_string)
    {
        return ctype_xdigit($hex_string) ? hexdec($hex_string) : 0;
    }
}