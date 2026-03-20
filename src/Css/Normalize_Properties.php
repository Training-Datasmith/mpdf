<?php

declare (strict_types=1);
namespace Mpdf\Css;

use Mpdf\Color\Color_Converter;
use Mpdf\Mpdf;
use Mpdf\Page_Format;
use Mpdf\Size_Converter;
use Mpdf\Utils\Arrays;
use Mpdf\Utils\Utf_String;
class Normalize_Properties
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
    /**
     * @var array
     */
    private $properties = [];
    public function __construct(Mpdf $mpdf, Size_Converter $size_converter, Color_Converter $color_converter)
    {
        $this->mpdf = $mpdf;
        $this->size_converter = $size_converter;
        $this->color_converter = $color_converter;
    }
    /**
     * Process and expand CSS shorthand properties.
     *
     * Takes an array of CSS properties and expands shorthand properties
     * into their individual components (e.g., margin -> margin-top, margin-right,
     * margin-bottom, margin-left). Handles font, background, border, padding,
     * margin, and other composite properties.
     *
     * @param array $prop CSS properties array
     * @return array Expanded CSS properties array
     */
    public function normalize($prop)
    {
        if (!is_array($prop) || count($prop) === 0) {
            return [];
        }
        $this->properties = [];
        foreach ($prop as $k => $v) {
            if ($k !== 'BACKGROUND-IMAGE' && $k !== 'BACKGROUND' && $k !== 'ODD-HEADER-NAME' && $k !== 'EVEN-HEADER-NAME' && $k !== 'ODD-FOOTER-NAME' && $k !== 'EVEN-FOOTER-NAME' && $k !== 'HEADER' && $k !== 'FOOTER') {
                $v = strtolower($v);
            }
            if ($k === 'FONT') {
                $this->process_font_property($v);
            } elseif ($k === 'FONT-FAMILY') {
                $this->process_font_family_property($k, $v);
            } elseif ($k === 'FONT-VARIANT') {
                $this->process_font_variant_property($v);
            } elseif ($k === 'MARGIN') {
                $tmp = $this->expand_shorthand_property($v);
                $this->properties['MARGIN-TOP'] = $tmp['T'];
                $this->properties['MARGIN-RIGHT'] = $tmp['R'];
                $this->properties['MARGIN-BOTTOM'] = $tmp['B'];
                $this->properties['MARGIN-LEFT'] = $tmp['L'];
            } elseif ($k === 'BORDER-RADIUS' || $k === 'BORDER-TOP-LEFT-RADIUS' || $k === 'BORDER-TOP-RIGHT-RADIUS' || $k === 'BORDER-BOTTOM-LEFT-RADIUS' || $k === 'BORDER-BOTTOM-RIGHT-RADIUS') {
                $this->process_border_radius_property($k, $v);
            } elseif ($k === 'PADDING') {
                $tmp = $this->expand_shorthand_property($v);
                $this->properties['PADDING-TOP'] = $tmp['T'];
                $this->properties['PADDING-RIGHT'] = $tmp['R'];
                $this->properties['PADDING-BOTTOM'] = $tmp['B'];
                $this->properties['PADDING-LEFT'] = $tmp['L'];
            } elseif (in_array($k, ['BORDER', 'BORDER-TOP', 'BORDER-RIGHT', 'BORDER-BOTTOM', 'BORDER-LEFT'], true)) {
                $this->process_border_property($k, $v);
            } elseif (in_array($k, ['BORDER-STYLE', 'BORDER-WIDTH', 'BORDER-COLOR', 'BORDER-SPACING'], true)) {
                $this->process_border_shorthand_property($k, $v);
            } elseif ($k === 'TEXT-OUTLINE') {
                $this->process_text_outline_property($v);
            } elseif ($k === 'SIZE' || $k === 'SHEET-SIZE') {
                $this->process_page_size_property($k, $v);
            } elseif (in_array($k, ['BACKGROUND', 'BACKGROUND-IMAGE', 'BACKGROUND-REPEAT', 'BACKGROUND-POSITION'], true)) {
                $this->process_background_property($k, $v);
            } elseif ($k === 'IMAGE-ORIENTATION') {
                $this->process_image_orientation_property($v);
            } elseif ($k === 'TEXT-ALIGN') {
                $this->process_text_align_property($k, $v);
            } elseif ($k === 'LIST-STYLE') {
                $this->process_list_style_property($v);
                if (preg_match('/(inside|outside)/i', $v, $m)) {
                    $this->properties['LIST-STYLE-POSITION'] = strtolower(trim($m[1]));
                }
            } else {
                $this->properties[$k] = $v;
            }
        }
        return $this->properties;
    }
    /**
     * Process FONT shorthand property.
     *
     * Expands the CSS font shorthand into individual components:
     * font-family, font-size, line-height, font-style, font-weight, text-transform.
     *
     * @param string $value Font property value
     * @return void
     */
    protected function process_font_property($value)
    {
        $value = $this->simplify_font_names(trim($value));
        $value = preg_replace('/\s*,\s*/', ',', $value);
        $bits = preg_split('/\s+/', $value);
        $num_of_bits = count($bits);
        if ($num_of_bits < 2) {
            return;
        }
        // Last item is font-family
        $this->properties['FONT-FAMILY'] = $bits[$num_of_bits - 1];
        // Second to last is font-size (possibly with /line-height)
        $fs = $bits[$num_of_bits - 2];
        if (preg_match('/(.*?)\/(.*)/', $fs, $fsp)) {
            $this->properties['FONT-SIZE'] = $fsp[1];
            $this->properties['LINE-HEIGHT'] = $fsp[2];
        } else {
            $this->properties['FONT-SIZE'] = $fs;
        }
        // Check for font-style
        if (preg_match('/(italic|oblique)/i', $value)) {
            $this->properties['FONT-STYLE'] = 'italic';
        } else {
            $this->properties['FONT-STYLE'] = 'normal';
        }
        // Check for font-weight
        if (stripos($value, 'bold') !== false) {
            $this->properties['FONT-WEIGHT'] = 'bold';
        } else {
            $this->properties['FONT-WEIGHT'] = 'normal';
        }
        // Check for small-caps
        if (stripos($value, 'small-caps') !== false) {
            $this->properties['TEXT-TRANSFORM'] = 'uppercase';
        }
    }
    /**
     * Simplify font names by removing quotes.
     *
     * Helper method for processFontProperty to remove quotes from font names
     * to simplify subsequent parsing.
     *
     * @param string $value Font property value
     * @return string Simplified font property value
     */
    protected function simplify_font_names($value)
    {
        // Remove quoted font names and simplify
        preg_match_all('/"(.*?)"/', $value, $ff);
        foreach ($ff[1] as $ffp) {
            $w = preg_split('/\s+/', $ffp);
            $value = preg_replace('/"' . $ffp . '"/', $w[0], $value);
        }
        preg_match_all("/'(.*?)'/", $value, $ff);
        foreach ($ff[1] as $ffp) {
            $w = preg_split('/\s+/', $ffp);
            $value = preg_replace("/'" . $ffp . "'/", $w[0], $value);
        }
        return $value;
    }
    /**
     * Process FONT-VARIANT property.
     *
     * @param string $value Property value
     * @return void
     */
    protected function process_font_variant_property($value)
    {
        if (preg_match('/(normal|none)/', $value, $m)) {
            $this->properties['FONT-VARIANT-LIGATURES'] = $m[1];
            $this->properties['FONT-VARIANT-CAPS'] = $m[1];
            $this->properties['FONT-VARIANT-NUMERIC'] = $m[1];
            $this->properties['FONT-VARIANT-ALTERNATES'] = $m[1];
            return;
        }
        if (preg_match_all('/(no-common-ligatures|\bcommon-ligatures|no-discretionary-ligatures|\bdiscretionary-ligatures|no-historical-ligatures|\bhistorical-ligatures|no-contextual|\bcontextual)/i', $value, $m)) {
            $this->properties['FONT-VARIANT-LIGATURES'] = implode(' ', $m[1]);
        }
        if (preg_match('/(all-small-caps|\bsmall-caps|all-petite-caps|\bpetite-caps|unicase|titling-caps)/i', $value, $m)) {
            $this->properties['FONT-VARIANT-CAPS'] = $m[1];
        }
        if (preg_match_all('/(lining-nums|oldstyle-nums|proportional-nums|tabular-nums|diagonal-fractions|stacked-fractions)/i', $value, $m)) {
            $this->properties['FONT-VARIANT-NUMERIC'] = implode(' ', $m[1]);
        }
        if (preg_match('/(historical-forms)/i', $value, $m)) {
            $this->properties['FONT-VARIANT-ALTERNATES'] = $m[1];
        }
    }
    /**
     * Process FONT-FAMILY property.
     *
     * @param string $propertyKey Property key
     * @param string $value Font family value
     * @return void
     */
    protected function process_font_family_property($property_key, $value)
    {
        /* Normalize the font list */
        $font_list = array_map(function ($font_name) {
            return trim($font_name, " \t\n\r\x00\v\"'");
        }, explode(',', $value));
        foreach ($font_list as $font_name) {
            $font_name = str_replace(' ', '', strtolower($font_name));
            if (in_array($font_name, $this->mpdf->fontdata, true) || in_array($font_name, $this->mpdf->available_unifonts, true) || in_array($font_name, $this->mpdf->sans_fonts, true) || in_array($font_name, $this->mpdf->serif_fonts, true) || in_array($font_name, $this->mpdf->mono_fonts, true) || $this->mpdf->only_core_fonts && in_array($font_name, ['courier', 'times', 'helvetica', 'arial'], true) || in_array($font_name, ['sjis', 'uhc', 'big5', 'gb'], true)) {
                $this->properties[$property_key] = $font_name;
                return;
            }
        }
        $this->properties[$property_key] = $font_list[0];
    }
    /**
     * Process BORDER shorthand and individual border properties.
     *
     * Handles BORDER, BORDER-TOP, BORDER-RIGHT, BORDER-BOTTOM, BORDER-LEFT properties
     * by normalizing them to consistent "width style color" format.
     *
     * @param string $propertyKey Property key (BORDER, BORDER-TOP, etc.)
     * @param string $value Property value
     * @return void
     */
    protected function process_border_property($property_key, $value)
    {
        switch ($property_key) {
            case 'BORDER':
                $value = $value !== '1' ? $this->normalize_border_string($value) : '1px solid #000000';
                $this->properties['BORDER-TOP'] = $value;
                $this->properties['BORDER-RIGHT'] = $value;
                $this->properties['BORDER-BOTTOM'] = $value;
                $this->properties['BORDER-LEFT'] = $value;
                break;
            case 'BORDER-TOP':
                $this->properties['BORDER-TOP'] = $this->normalize_border_string($value);
                break;
            case 'BORDER-RIGHT':
                $this->properties['BORDER-RIGHT'] = $this->normalize_border_string($value);
                break;
            case 'BORDER-BOTTOM':
                $this->properties['BORDER-BOTTOM'] = $this->normalize_border_string($value);
                break;
            case 'BORDER-LEFT':
                $this->properties['BORDER-LEFT'] = $this->normalize_border_string($value);
                break;
        }
    }
    /**
     * Process border shorthand properties (style, width, color).
     *
     * Handles BORDER-STYLE, BORDER-WIDTH, BORDER-COLOR, BORDER-SPACING.
     *
     * @param string $key Property key
     * @param string $value Property value
     * @return void
     */
    protected function process_border_shorthand_property($key, $value)
    {
        if ($key === 'BORDER-STYLE') {
            $e = $this->expand_shorthand_property($value);
            if (empty($e)) {
                return;
            }
            $this->properties['BORDER-TOP-STYLE'] = $e['T'];
            $this->properties['BORDER-RIGHT-STYLE'] = $e['R'];
            $this->properties['BORDER-BOTTOM-STYLE'] = $e['B'];
            $this->properties['BORDER-LEFT-STYLE'] = $e['L'];
        } elseif ($key === 'BORDER-WIDTH') {
            $e = $this->expand_shorthand_property($value);
            if (empty($e)) {
                return;
            }
            $this->properties['BORDER-TOP-WIDTH'] = $e['T'];
            $this->properties['BORDER-RIGHT-WIDTH'] = $e['R'];
            $this->properties['BORDER-BOTTOM-WIDTH'] = $e['B'];
            $this->properties['BORDER-LEFT-WIDTH'] = $e['L'];
        } elseif ($key === 'BORDER-COLOR') {
            $e = $this->expand_shorthand_property($value);
            if (empty($e)) {
                return;
            }
            $this->properties['BORDER-TOP-COLOR'] = $e['T'];
            $this->properties['BORDER-RIGHT-COLOR'] = $e['R'];
            $this->properties['BORDER-BOTTOM-COLOR'] = $e['B'];
            $this->properties['BORDER-LEFT-COLOR'] = $e['L'];
        } elseif ($key === 'BORDER-SPACING') {
            $prop = preg_split('/\s+/', trim($value));
            if (count($prop) === 1) {
                $this->properties['BORDER-SPACING-H'] = $prop[0];
                $this->properties['BORDER-SPACING-V'] = $prop[0];
            } elseif (count($prop) === 2) {
                $this->properties['BORDER-SPACING-H'] = $prop[0];
                $this->properties['BORDER-SPACING-V'] = $prop[1];
            }
        }
    }
    /**
     * Parse CSS background shorthand property.
     *
     * Extracts background color, image, repeat, and position from the
     * background shorthand property. Supports  gradients and url() images.
     *
     * @param string $s Background property value
     * @return array Array with keys 'c' (color), 'i' (image), 'r' (repeat), 'p' (position)
     */
    protected function parse_css_background($s)
    {
        $background = [
            'c' => false,
            // color
            'i' => false,
            // image
            'r' => false,
            // repeat
            'p' => false,
        ];
        if (preg_match('/(-moz-)*(repeating-)*(linear|radial)-gradient\(.*\)/i', $s, $m)) {
            $background['i'] = $m[0];
            return $background;
        }
        if (preg_match('/url\(/i', $s)) {
            // If color, set and strip it off
            if (preg_match('/^\s*(#[0-9a-fA-F]{3,6}|(rgba|rgb|device-cmyka|cmyka|device-cmyk|cmyk|hsla|hsl|spot)\(.*?\)|[a-zA-Z]{3,})\s+(url\(.*)/i', $s, $m)) {
                $background['c'] = strtolower($m[1]);
                $s = $m[3];
            }
            if (preg_match('/url\([\'\"]{0,1}(.*?)[\'\"]{0,1}\)\s*(.*)/i', $s, $m)) {
                $background['i'] = $m[1];
                $s = strtolower($m[2]);
                if (preg_match('/(repeat-x|repeat-y|no-repeat|repeat)/', $s, $m)) {
                    $background['r'] = $m[1];
                }
                // Remove repeat, attachment (discarded) and also any inherit
                $s = preg_replace('/(repeat-x|repeat-y|no-repeat|repeat|scroll|fixed|inherit)/', '', $s);
                $bits = preg_split('/\s+/', trim($s));
                $normalized_position = $this->normalize_background_position($bits);
                if ($normalized_position !== false) {
                    $background['p'] = $normalized_position;
                }
            }
            return $background;
        }
        if (preg_match('/^\s*(#[0-9a-fA-F]{3,6}|(rgba|rgb|device-cmyka|cmyka|device-cmyk|cmyk|hsla|hsl|spot)\(.*?\)|[a-zA-Z]{3,})/i', $s, $m)) {
            $background['c'] = strtolower($m[1]);
        }
        return $background;
    }
    /**
     * Expand 1-4 value CSS property into top/right/bottom/left components.
     *
     * Handles CSS properties that can be specified with 1-4 values following
     * the standard CSS clockwise pattern (top, right, bottom, left).
     * Used for margin, padding, border-width, border-style, and border-color.
     *
     * @param string $value Property value(s) separated by spaces
     * @return array Associative array with keys 'T', 'R', 'B', 'L'
     */
    protected function expand_shorthand_property($value)
    {
        $property = preg_split('/\s+/', trim($value));
        switch (count($property)) {
            case 0:
                return [];
            case 1:
                return ['T' => $property[0], 'R' => $property[0], 'B' => $property[0], 'L' => $property[0]];
            case 2:
                return ['T' => $property[0], 'R' => $property[1], 'B' => $property[0], 'L' => $property[1]];
            case 3:
                return ['T' => $property[0], 'R' => $property[1], 'B' => $property[2], 'L' => $property[1]];
            default:
                // Ignore rule parts after first 4 values (most likely !important)
                return ['T' => $property[0], 'R' => $property[1], 'B' => $property[2], 'L' => $property[3]];
        }
    }
    /**
     * Expand border-radius properties.
     *
     * Processes border-radius CSS properties and expands them into horizontal
     * and vertical components for each corner (TL, TR, BL, BR).
     *
     * @param string $val Border radius value(s)
     * @param string $k Property name (BORDER-RADIUS or specific corner)
     * @return array Array with keys like 'TL-H', 'TL-V', etc.
     */
    protected function expand_border_radius($val, $k)
    {
        if ($k === 'BORDER-RADIUS') {
            return $this->parse_border_radius_shorthand($val);
        }
        return $this->parse_border_radius_corner($val, $k);
    }
    /**
     * Parse individual border-radius corner values.
     *
     * Helper method for expandBorderRadius to parse values for a specific corner.
     *
     * @param string $val Border radius value(s)
     * @param string $k Property name (specific corner)
     * @return array Array with keys like 'TL-H', 'TL-V', etc.
     */
    protected function parse_border_radius_corner($val, $k)
    {
        $b = [];
        $prop = preg_split('/\s+/', trim($val));
        if (count($prop) === 1) {
            $h = $v = $val;
        } else {
            $h = $prop[0];
            $v = $prop[1];
        }
        if ($h === 0 || $v === 0) {
            $h = $v = 0;
        }
        if ($k === 'BORDER-TOP-LEFT-RADIUS') {
            $b['TL-H'] = $h;
            $b['TL-V'] = $v;
        } elseif ($k === 'BORDER-TOP-RIGHT-RADIUS') {
            $b['TR-H'] = $h;
            $b['TR-V'] = $v;
        } elseif ($k === 'BORDER-BOTTOM-LEFT-RADIUS') {
            $b['BL-H'] = $h;
            $b['BL-V'] = $v;
        } elseif ($k === 'BORDER-BOTTOM-RIGHT-RADIUS') {
            $b['BR-H'] = $h;
            $b['BR-V'] = $v;
        }
        return $b;
    }
    /**
     * Parse border-radius shorthand values.
     *
     * Parses the slash syntax (horizontal/vertical) and expands 1-4 values
     * into individual corner components.
     *
     * @param string $val Border radius value(s)
     * @return array Array with keys 'TL-H', 'TR-H', 'BR-H', 'BL-H', 'TL-V', 'TR-V', 'BR-V', 'BL-V'
     */
    protected function parse_border_radius_shorthand($val)
    {
        $border = [];
        $radius = explode('/', trim($val));
        $properties = preg_split('/\s+/', trim($radius[0]));
        // single radius
        if (count($properties) === 1) {
            $border['TL-H'] = $border['TR-H'] = $border['BR-H'] = $border['BL-H'] = $properties[0];
        } elseif (count($properties) === 2) {
            $border['TL-H'] = $border['BR-H'] = $properties[0];
            $border['TR-H'] = $border['BL-H'] = $properties[1];
        } elseif (count($properties) === 3) {
            $border['TL-H'] = $properties[0];
            $border['TR-H'] = $border['BL-H'] = $properties[1];
            $border['BR-H'] = $properties[2];
        } elseif (count($properties) === 4) {
            $border['TL-H'] = $properties[0];
            $border['TR-H'] = $properties[1];
            $border['BR-H'] = $properties[2];
            $border['BL-H'] = $properties[3];
        }
        // Check for two radius e.g. 10px / 20px
        if (count($radius) === 2) {
            $properties = preg_split('/\s+/', trim($radius[1]));
            if (count($properties) === 1) {
                $border['TL-V'] = $border['TR-V'] = $border['BR-V'] = $border['BL-V'] = $properties[0];
            } elseif (count($properties) === 2) {
                $border['TL-V'] = $border['BR-V'] = $properties[0];
                $border['TR-V'] = $border['BL-V'] = $properties[1];
            } elseif (count($properties) === 3) {
                $border['TL-V'] = $properties[0];
                $border['TR-V'] = $border['BL-V'] = $properties[1];
                $border['BR-V'] = $properties[2];
            } elseif (count($properties) === 4) {
                $border['TL-V'] = $properties[0];
                $border['TR-V'] = $properties[1];
                $border['BR-V'] = $properties[2];
                $border['BL-V'] = $properties[3];
            }
            return $border;
        }
        $border['TL-V'] = Arrays::get($border, 'TL-H', 0);
        $border['TR-V'] = Arrays::get($border, 'TR-H', 0);
        $border['BL-V'] = Arrays::get($border, 'BL-H', 0);
        $border['BR-V'] = Arrays::get($border, 'BR-H', 0);
        return $border;
    }
    /**
     * Normalize background position values.
     *
     * Converts background position keywords (top, bottom, left, right, center)
     * to percentage values and validates the format.
     *
     * @param array $bits Position components (1 or 2 values)
     * @return string|false Normalized position string or false if invalid
     */
    protected function normalize_background_position(array $bits)
    {
        $position = '';
        $num_of_bits = count($bits);
        if ($num_of_bits === 1) {
            if (false !== strpos($bits[0], 'bottom')) {
                $position = '50% 100%';
            } elseif (false !== strpos($bits[0], 'top')) {
                $position = '50% 0%';
            } else {
                $position = $bits[0] . ' 50%';
            }
        } elseif ($num_of_bits === 2) {
            // Can be either right center or center right
            if (preg_match('/(top|bottom)/', $bits[0]) || preg_match('/(left|right)/', $bits[1])) {
                $position = $bits[1] . ' ' . $bits[0];
            } else {
                $position = $bits[0] . ' ' . $bits[1];
            }
        }
        if (empty($position)) {
            return false;
        }
        $position = preg_replace('/(left|top)/', '0%', $position);
        $position = preg_replace('/(right|bottom)/', '100%', $position);
        $position = preg_replace('/(center)/', '50%', $position);
        if (!preg_match('/[\-]{0,1}\d+(in|cm|mm|pt|pc|em|ex|px|%)* [\-]{0,1}\d+(in|cm|mm|pt|pc|em|ex|px|%)*/', $position)) {
            return false;
        }
        return $position;
    }
    /**
     * Parse and normalize border shorthand property.
     *
     * Converts border shorthand syntax into standardized "width style color" format.
     * Handles various input formats and orders.
     *
     * @param string $bd Border property value
     * @return string Normalized border string in format "width style color"
     */
    protected function normalize_border_string($bd)
    {
        preg_match_all("/\\((.*?)\\)/", $bd, $m);
        foreach ($m[1] as $i => $value) {
            $sub = str_replace(' ', '', $m[1][$i]);
            $bd = str_replace($m[1][$i], $sub, $bd);
        }
        $prop = preg_split('/\s+/', trim($bd));
        if (count($prop) > 3) {
            return '';
        }
        $parts = $this->parse_border_parts($prop);
        $w = $parts['w'];
        $s = $parts['s'];
        $c = $parts['c'];
        $s = strtolower($s);
        return $w . ' ' . $s . ' ' . $c;
    }
    /**
     * Parse border property parts (width, style, color).
     *
     * Helper method for normalizeBorderString to determine width, style, and color
     * from split border property string.
     *
     * @param array $prop Split border property string
     * @return array Array containing 'w' (width), 's' (style), 'c' (color)
     */
    protected function parse_border_parts(array $prop)
    {
        $width = 'medium';
        $color = '#000000';
        $style = 'none';
        switch (count($prop)) {
            case 1:
                if (in_array($prop[0], $this->mpdf->borderstyles, true) || $prop[0] === 'none' || $prop[0] === 'hidden') {
                    $style = $prop[0];
                } elseif (is_array($this->color_converter->convert($prop[0], $this->mpdf->pdfa_xwarnings))) {
                    $color = $prop[0];
                } else {
                    $width = $prop[0];
                }
                break;
            case 2:
                if (in_array($prop[1], $this->mpdf->borderstyles, true) || $prop[1] === 'none' || $prop[1] === 'hidden') {
                    $width = $prop[0];
                    $style = $prop[1];
                } elseif (in_array($prop[0], $this->mpdf->borderstyles, true) || $prop[0] === 'none' || $prop[0] === 'hidden') {
                    $style = $prop[0];
                    $color = $prop[1];
                } else {
                    $width = $prop[0];
                    $color = $prop[1];
                }
                break;
            case 3:
                if (0 === strpos($prop[0], '#')) {
                    $color = $prop[0];
                    $width = $prop[1];
                    $style = $prop[2];
                } elseif (substr($prop[0], 1, 1) === '#') {
                    $style = $prop[0];
                    $color = $prop[1];
                    $width = $prop[2];
                } elseif (in_array($prop[0], $this->mpdf->borderstyles) || $prop[0] === 'none' || $prop[0] === 'hidden') {
                    $style = $prop[0];
                    $width = $prop[1];
                    $color = $prop[2];
                } else {
                    $width = $prop[0];
                    $style = $prop[1];
                    $color = $prop[2];
                }
                break;
        }
        return ['w' => $width, 's' => $style, 'c' => $color];
    }
    /**
     * Process background related CSS properties.
     *
     * Handles BACKGROUND, BACKGROUND-IMAGE, BACKGROUND-REPEAT, and BACKGROUND-POSITION.
     *
     * @param string $property Property name
     * @param string $value Property value
     * @return void
     */
    protected function process_background_property($property, $value)
    {
        switch ($property) {
            case 'BACKGROUND':
                $bg = $this->parse_css_background($value);
                if ($bg['c']) {
                    $this->properties['BACKGROUND-COLOR'] = $bg['c'];
                } else {
                    $this->properties['BACKGROUND-COLOR'] = 'transparent';
                }
                if ($bg['i']) {
                    $this->properties['BACKGROUND-IMAGE'] = $bg['i'];
                    if ($bg['r']) {
                        $this->properties['BACKGROUND-REPEAT'] = $bg['r'];
                    }
                    if ($bg['p']) {
                        $this->properties['BACKGROUND-POSITION'] = $bg['p'];
                    }
                } else {
                    $this->properties['BACKGROUND-IMAGE'] = '';
                }
                break;
            case 'BACKGROUND-IMAGE':
                if (preg_match('/(-moz-)*(repeating-)*(linear|radial)-gradient\(.*\)/i', $value, $m)) {
                    $this->properties['BACKGROUND-IMAGE'] = $m[0];
                    return;
                }
                if (preg_match('/url\([\'\"]{0,1}(.*?)[\'\"]{0,1}\)/i', $value, $m)) {
                    $this->properties['BACKGROUND-IMAGE'] = $m[1];
                } elseif (strtolower($value) === 'none') {
                    $this->properties['BACKGROUND-IMAGE'] = '';
                }
                break;
            case 'BACKGROUND-REPEAT':
                if (preg_match('/(repeat-x|repeat-y|no-repeat|repeat)/i', $value, $m)) {
                    $this->properties['BACKGROUND-REPEAT'] = strtolower($m[1]);
                }
                break;
            case 'BACKGROUND-POSITION':
                $bits = preg_split('/\s+/', trim($value));
                $normalized_position = $this->normalize_background_position($bits);
                if ($normalized_position !== false) {
                    $this->properties['BACKGROUND-POSITION'] = $normalized_position;
                }
                break;
        }
    }
    /**
     * Process border radius property.
     *
     * @param string $property Property name
     * @param string $value Property value
     * @return void
     */
    protected function process_border_radius_property($property, $value)
    {
        $border_radius = $this->expand_border_radius($value, $property);
        if (isset($border_radius['TL-H'])) {
            $this->properties['BORDER-TOP-LEFT-RADIUS-H'] = $border_radius['TL-H'];
        }
        if (isset($border_radius['TL-V'])) {
            $this->properties['BORDER-TOP-LEFT-RADIUS-V'] = $border_radius['TL-V'];
        }
        if (isset($border_radius['TR-H'])) {
            $this->properties['BORDER-TOP-RIGHT-RADIUS-H'] = $border_radius['TR-H'];
        }
        if (isset($border_radius['TR-V'])) {
            $this->properties['BORDER-TOP-RIGHT-RADIUS-V'] = $border_radius['TR-V'];
        }
        if (isset($border_radius['BL-H'])) {
            $this->properties['BORDER-BOTTOM-LEFT-RADIUS-H'] = $border_radius['BL-H'];
        }
        if (isset($border_radius['BL-V'])) {
            $this->properties['BORDER-BOTTOM-LEFT-RADIUS-V'] = $border_radius['BL-V'];
        }
        if (isset($border_radius['BR-H'])) {
            $this->properties['BORDER-BOTTOM-RIGHT-RADIUS-H'] = $border_radius['BR-H'];
        }
        if (isset($border_radius['BR-V'])) {
            $this->properties['BORDER-BOTTOM-RIGHT-RADIUS-V'] = $border_radius['BR-V'];
        }
    }
    /**
     * Process text outline CSS properties.
     *
     * Handles TEXT-OUTLINE shorthand.
     *
     * @param string $v Property value
     * @return void
     */
    protected function process_text_outline_property($v)
    {
        $prop = preg_split('/\s+/', trim($v));
        if (strtolower(trim($v)) === 'none') {
            $this->properties['TEXT-OUTLINE'] = 'none';
        } elseif (count($prop) === 2) {
            $this->properties['TEXT-OUTLINE-WIDTH'] = $prop[0];
            $this->properties['TEXT-OUTLINE-COLOR'] = $prop[1];
        } elseif (count($prop) === 3) {
            $this->properties['TEXT-OUTLINE-WIDTH'] = $prop[0];
            $this->properties['TEXT-OUTLINE-COLOR'] = $prop[2];
        }
    }
    /**
     * Process page size CSS properties.
     *
     * Handles SIZE and SHEET-SIZE properties.
     *
     * @param string $property Property name
     * @param string $value Property value
     * @return void
     */
    protected function process_page_size_property($property, $value)
    {
        $value = preg_split('/\s+/', trim($value));
        switch ($property) {
            case 'SIZE':
                if (preg_match('/(auto|portrait|landscape)/', $value[0])) {
                    $this->properties['SIZE'] = strtoupper($value[0]);
                } elseif (count($value) === 1) {
                    $this->properties['SIZE']['W'] = $this->size_converter->convert($value[0]);
                    $this->properties['SIZE']['H'] = $this->size_converter->convert($value[0]);
                } elseif (count($value) === 2) {
                    $this->properties['SIZE']['W'] = $this->size_converter->convert($value[0]);
                    $this->properties['SIZE']['H'] = $this->size_converter->convert($value[1]);
                }
                break;
            case 'SHEET-SIZE':
                if (count($value) === 2) {
                    $this->properties['SHEET-SIZE'] = [$this->size_converter->convert($value[0]), $this->size_converter->convert($value[1])];
                } else {
                    if (preg_match('/([0-9a-zA-Z]*)-L/i', $value[0], $m)) {
                        // e.g. A4-L = A$ landscape
                        $ft = Page_Format::get_size_from_name($m[1]);
                        $format = [$ft[1], $ft[0]];
                    } else {
                        $format = Page_Format::get_size_from_name($value[0]);
                    }
                    if ($format) {
                        $this->properties['SHEET-SIZE'] = [$format[0] / Mpdf::SCALE, $format[1] / Mpdf::SCALE];
                    }
                }
                break;
        }
    }
    /**
     * Process image orientation CSS properties.
     *
     * Handles IMAGE-ORIENTATION property.
     *
     * @param string $v Property value
     * @return void
     */
    protected function process_image_orientation_property($v)
    {
        if (!preg_match('/([\-]*[0-9.]+)(deg|grad|rad)/i', $v, $m)) {
            return;
        }
        $angle = (float) $m[1];
        if (strtolower($m[2]) === 'grad') {
            $angle *= 360 / 400;
        } elseif (strtolower($m[2]) === 'rad') {
            $angle = rad2deg($angle);
        }
        while ($angle < 0) {
            $angle += 360;
        }
        $angle %= 360;
        $angle /= 90;
        $angle = round($angle) * 90;
        $this->properties['IMAGE-ORIENTATION'] = $angle;
    }
    /**
     * Process text align CSS properties.
     *
     * Handles TEXT-ALIGN property including decimal alignment.
     *
     * @param string $k Property name
     * @param string $v Property value
     * @return void
     */
    protected function process_text_align_property($k, $v)
    {
        if (preg_match('/["\'](.){1}["\']/i', $v, $m)) {
            $d = array_search($m[1], $this->mpdf->decimal_align);
            if ($d !== false) {
                $this->properties['TEXT-ALIGN'] = $d;
            }
            if (preg_match('/(center|left|right)/i', $v, $m)) {
                $this->properties['TEXT-ALIGN'] .= strtoupper(substr($m[1], 0, 1));
            } else {
                $this->properties['TEXT-ALIGN'] .= 'R';
            }
            // default = R
        } elseif (preg_match('/["\'](\\\\[a-fA-F0-9]{1,6})["\']/i', $v, $m)) {
            $utf8 = Utf_String::code_hex2utf(substr($m[1], 1, 6));
            $d = array_search($utf8, $this->mpdf->decimal_align);
            if ($d !== false) {
                $this->properties['TEXT-ALIGN'] = $d;
            }
            if (preg_match('/(center|left|right)/i', $v, $m)) {
                $this->properties['TEXT-ALIGN'] .= strtoupper(substr($m[1], 0, 1));
            } else {
                $this->properties['TEXT-ALIGN'] .= 'R';
            }
            // default = R
        } else {
            $this->properties[$k] = $v;
        }
    }
    /**
     * Process list style CSS properties.
     *
     * Handles LIST-STYLE property.
     *
     * @param string $v Property value
     * @return void
     */
    protected function process_list_style_property($v)
    {
        if (preg_match('/none/i', $v, $m)) {
            $this->properties['LIST-STYLE-TYPE'] = 'none';
            $this->properties['LIST-STYLE-IMAGE'] = 'none';
        }
        if (preg_match('/(lower-roman|upper-roman|lower-latin|lower-alpha|upper-latin|upper-alpha|decimal|disc|circle|square|arabic-indic|bengali|devanagari|gujarati|gurmukhi|kannada|malayalam|oriya|persian|tamil|telugu|thai|urdu|cambodian|khmer|lao|cjk-decimal|hebrew)/i', $v, $m)) {
            $this->properties['LIST-STYLE-TYPE'] = strtolower(trim($m[1]));
        } elseif (preg_match('/U\+([a-fA-F0-9]+)/i', $v, $m)) {
            $this->properties['LIST-STYLE-TYPE'] = strtolower(trim($m[1]));
        }
        if (preg_match('/url\([\'\"]{0,1}(.*?)[\'\"]{0,1}\)/i', $v, $m)) {
            $this->properties['LIST-STYLE-IMAGE'] = strtolower(trim($m[1]));
        }
    }
}