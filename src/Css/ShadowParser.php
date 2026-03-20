<?php

declare (strict_types=1);
namespace Mpdf\Css;

use Mpdf\Color\Color_Converter;
use Mpdf\Mpdf;
use Mpdf\Size_Converter;
class Shadow_Parser
{
    /**
     * @var Mpdf
     */
    private $mpdf;
    /**
     * @var SizeConverter
     */
    private $size_converter;
    /**
     * @var ColorConverter
     */
    private $color_converter;
    public function __construct(Mpdf $mpdf, Size_Converter $size_converter, Color_Converter $color_converter)
    {
        $this->mpdf = $mpdf;
        $this->size_converter = $size_converter;
        $this->color_converter = $color_converter;
    }
    /**
     * Normalize shadow colors.
     *
     * Replaces commas in color functions (rgb, hsl, etc.) with placeholders
     * to prevent splitting multiple shadows on those commas.
     *
     * @param string $value Shadow property value
     * @return string Normalized shadow property value
     */
    public function normalize_shadow_colors($value)
    {
        $c = preg_match_all('/(rgba|rgb|device-cmyka|cmyka|device-cmyk|cmyk|hsla|hsl)\(.*?\)/', $value, $x);
        // mPDF 5.6.05
        for ($i = 0; $i < $c; $i++) {
            $col = preg_replace('/,\s/', '*', $x[0][$i]);
            $value = str_replace($x[0][$i], $col, $value);
        }
        return $value;
    }
    /**
     * Parse box-shadow CSS property.
     *
     * Converts box-shadow CSS property string into array format used internally.
     * Handles multiple shadows, inset shadows, blur, spread, and colors.
     *
     * @param string $value Box-shadow property value
     * @return array Array of shadow definitions
     */
    public function parse_box_shadow($value)
    {
        $sh = [];
        $ss = explode(',', $this->normalize_shadow_colors($value));
        foreach ($ss as $s) {
            $box_shadow = $this->parse_single_box_shadow($s);
            if ($box_shadow) {
                array_unshift($sh, $box_shadow);
            }
        }
        return $sh;
    }
    /**
     * Parse a single box-shadow definition.
     *
     * Helper method for setCSSboxshadow to parse individual shadow components
     * (inset, x, y, blur, spread, color).
     *
     * @param string $s Shadow definition string
     * @return array|null Parsed shadow array or null if invalid
     */
    protected function parse_single_box_shadow($s)
    {
        $box_shadow = ['inset' => false, 'blur' => 0, 'spread' => 0];
        if (stripos($s, 'inset') !== false) {
            $box_shadow['inset'] = true;
            $s = preg_replace('/\s*inset\s*/', '', $s);
        }
        $p = explode(' ', trim($s));
        if (isset($p[0])) {
            $parent_width = 0;
            if (isset($this->mpdf->blk[$this->mpdf->blklvl - 1]['inner_width'])) {
                $parent_width = isset($this->mpdf->blk[$this->mpdf->blklvl - 1]['inner_width']);
            } elseif (isset($this->mpdf->blk[0]['inner_width'])) {
                $parent_width = $this->mpdf->blk[0]['inner_width'];
            }
            $box_shadow['x'] = $this->size_converter->convert(trim($p[0]), $parent_width, $this->mpdf->font_size, false);
        }
        if (isset($p[1])) {
            $parent_width = 0;
            if (isset($this->mpdf->blk[$this->mpdf->blklvl - 1]['inner_width'])) {
                $parent_width = isset($this->mpdf->blk[$this->mpdf->blklvl - 1]['inner_width']);
            } elseif (isset($this->mpdf->blk[0]['inner_width'])) {
                $parent_width = $this->mpdf->blk[0]['inner_width'];
            }
            $box_shadow['y'] = $this->size_converter->convert(trim($p[1]), $parent_width, $this->mpdf->font_size, false);
        }
        if (isset($p[2])) {
            if (preg_match('/^\s*[\.\-0-9]/', $p[2])) {
                $parent_width = 0;
                if (isset($this->mpdf->blk[$this->mpdf->blklvl - 1]['inner_width'])) {
                    $parent_width = isset($this->mpdf->blk[$this->mpdf->blklvl - 1]['inner_width']);
                } elseif (isset($this->mpdf->blk[0]['inner_width'])) {
                    $parent_width = $this->mpdf->blk[0]['inner_width'];
                }
                $box_shadow['blur'] = $this->size_converter->convert(trim($p[2]), $parent_width, $this->mpdf->font_size, false);
            } else {
                $box_shadow['col'] = $this->color_converter->convert(preg_replace('/\*/', ',', $p[2]), $this->mpdf->pdfa_xwarnings);
            }
        }
        if (isset($p[3])) {
            if (preg_match('/^\s*[\.\-0-9]/', $p[3])) {
                $parent_width = 0;
                if (isset($this->mpdf->blk[$this->mpdf->blklvl - 1]['inner_width'])) {
                    $parent_width = isset($this->mpdf->blk[$this->mpdf->blklvl - 1]['inner_width']);
                } elseif (isset($this->mpdf->blk[0]['inner_width'])) {
                    $parent_width = $this->mpdf->blk[0]['inner_width'];
                }
                $box_shadow['spread'] = $this->size_converter->convert(trim($p[3]), $parent_width, $this->mpdf->font_size, false);
            } else {
                $box_shadow['col'] = $this->color_converter->convert(preg_replace('/\*/', ',', $p[3]), $this->mpdf->pdfa_xwarnings);
            }
        }
        if (isset($p[4])) {
            $box_shadow['col'] = $this->color_converter->convert(preg_replace('/\*/', ',', $p[4]), $this->mpdf->pdfa_xwarnings);
        }
        if (empty($box_shadow['col'])) {
            $box_shadow['col'] = $this->color_converter->convert('#888888', $this->mpdf->pdfa_xwarnings);
        }
        return isset($box_shadow['y']) ? $box_shadow : null;
    }
    /**
     * Parse text-shadow CSS property.
     *
     * Converts text-shadow CSS property string into array format used internally.
     * Handles multiple shadows, blur, and colors.
     *
     * @param string $value Text-shadow property value
     * @return array Array of text shadow definitions
     */
    public function parse_text_shadow($value)
    {
        $sh = [];
        $ss = explode(',', $this->normalize_shadow_colors($value));
        foreach ($ss as $s) {
            $text_shadow = $this->parse_single_text_shadow($s);
            if ($text_shadow) {
                array_unshift($sh, $text_shadow);
            }
        }
        return $sh;
    }
    /**
     * Parse a single text-shadow definition.
     *
     * Helper method for setCSStextshadow to parse individual shadow components
     * (x, y, blur, color).
     *
     * @param string $s Shadow definition string
     * @return array|null Parsed shadow array or null if invalid
     */
    protected function parse_single_text_shadow($s)
    {
        $text_shadow = ['blur' => 0];
        $p = explode(' ', trim($s));
        if (isset($p[0])) {
            $text_shadow['x'] = $this->size_converter->convert(trim($p[0]), $this->mpdf->font_size, $this->mpdf->font_size, false);
        }
        if (isset($p[1])) {
            $text_shadow['y'] = $this->size_converter->convert(trim($p[1]), $this->mpdf->font_size, $this->mpdf->font_size, false);
        }
        if (isset($p[2])) {
            if (preg_match('/^\s*[\.\-0-9]/', $p[2])) {
                $text_shadow['blur'] = $this->size_converter->convert(trim($p[2]), isset($this->mpdf->blk[$this->mpdf->blklvl]['inner_width']) ? $this->mpdf->blk[$this->mpdf->blklvl]['inner_width'] : 0, $this->mpdf->font_size, false);
            } else {
                $text_shadow['col'] = $this->color_converter->convert(preg_replace('/\*/', ',', $p[2]), $this->mpdf->pdfa_xwarnings);
            }
        }
        if (isset($p[3])) {
            $text_shadow['col'] = $this->color_converter->convert(preg_replace('/\*/', ',', $p[3]), $this->mpdf->pdfa_xwarnings);
        }
        if (empty($text_shadow['col'])) {
            $text_shadow['col'] = $this->color_converter->convert('#888888', $this->mpdf->pdfa_xwarnings);
        }
        return isset($text_shadow['y']) ? $text_shadow : null;
    }
}