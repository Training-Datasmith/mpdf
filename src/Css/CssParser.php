<?php

declare (strict_types=1);
namespace Mpdf\Css;

use Mpdf\Asset_Fetcher;
use Mpdf\Cache;
use Mpdf\Color\Color_Converter;
use Mpdf\Mpdf;
use Mpdf\Size_Converter;
use Mpdf\Utils\Arrays;
use Mpdf\Utils\Path;
class Css_Parser
{
    /**
     * @var Mpdf
     */
    private $mpdf;
    /**
     * @var CssLoader
     */
    private $css_loader;
    /**
     * @var MediaQueryProcessor
     */
    private $media_query_processor;
    /**
     * @var CommentParser
     */
    private $comment_parser;
    /**
     * @var InlineStyleParser
     */
    private $inline_style_parser;
    /**
     * @var SelectorParser
     */
    private $selector_parser;
    /**
     * @var NormalizeProperties
     */
    private $normalize_properties;
    /**
     * @var ShadowParser
     */
    private $shadow_parser;
    /**
     * CSS for simple selectors.
     *
     * Stores CSS properties for simple selectors (depth 1).
     * Format:
     * [
     *   'P' => [
     *     'COLOR' => '#FF0000',
     *     'FONT-SIZE' => '12pt',
     *   ],
     *   'CLASS>>MYCLASS' => [
     *     'BORDER' => '1px solid black',
     *   ],
     *   ...
     * ]
     *
     * @var array
     */
    private $css = [];
    /**
     * CSS for cascaded selectors.
     *
     * Stores CSS properties for nested/cascaded selectors (depth > 1).
     * Format is a nested array mirroring the selector hierarchy.
     * Example for "DIV.myclass P":
     * [
     *   'DIV' => [
     *     'CLASS>>MYCLASS' => [
     *       'P' => [
     *         'COLOR' => '#0000FF',
     *         'depth' => 3
     *       ]
     *     ]
     *   ]
     * ]
     *
     * @var array
     */
    private $cascade_css = [];
    /**
     * @var array An index used to filter redundant class names before passing to Arrays::allUniqueSortedCombinations
     */
    private $used_class_names = [];
    /**
     * @var int Maximum number of classes found in a single selector
     */
    private $max_class_depth = 1;
    public function __construct(Mpdf $mpdf, Cache $cache, Size_Converter $size_converter, Color_Converter $color_converter, Asset_Fetcher $asset_fetcher)
    {
        $this->mpdf = $mpdf;
        $this->normalize_properties = new Normalize_Properties($mpdf, $size_converter, $color_converter);
        $this->css_loader = new Css_Loader($mpdf, $asset_fetcher, $cache);
        $this->media_query_processor = new Media_Query_Processor($mpdf);
        $this->comment_parser = new Comment_Parser();
        $this->inline_style_parser = new Inline_Style_Parser($this->normalize_properties);
        $this->selector_parser = new Selector_Parser($mpdf);
        $this->shadow_parser = new Shadow_Parser($mpdf, $size_converter, $color_converter);
    }
    /**
     * Read and parse CSS from HTML content.
     *
     * @param string $html HTML content containing CSS
     * @return string
     */
    public function parse($html)
    {
        $this->css = [];
        $this->cascade_css = [];
        $ind = 0;
        $css = '';
        $html = $this->media_query_processor->filter_by_media_query($html, '/<style[^>]*media=["\']([^"\'>]*)["\'].*?<\/style>/is');
        $html = $this->media_query_processor->filter_by_media_query($html, '/<link[^>]*media=["\']([^"\'>]*)["\'].*?>/is');
        $html = $this->comment_parser->remove_comments_from_style_blocks($html);
        $html = $this->comment_parser->remove_html_comments($html);
        $external_css = $this->css_loader->extract_external_stylesheet_urls($html);
        $external_css_count = count($external_css);
        while ($external_css_count) {
            $path = htmlspecialchars_decode($external_css[$ind]);
            $path = Path::relative_to_absolute_path($path, $this->mpdf->basepath);
            if (strpos($path, '//') === false) {
                // mPDF 5.7.3
                $path = preg_replace('/\.css\?.*$/', '.css', $path);
            }
            $stylesheet_css = $this->css_loader->load_stylesheet($path);
            if ($stylesheet_css) {
                $css .= $this->css_loader->process_external_css_imports($stylesheet_css, $path, $external_css, $external_css_count);
            }
            $external_css_count--;
            $ind++;
        }
        // CSS as <style> in HTML document
        $regexp = '/<style.*?>(.*?)<\/style>/si';
        if (preg_match_all($regexp, $html, $css_block)) {
            $css .= ' ' . $this->css_loader->resolve_background_urls(implode(' ', $css_block[1]));
        }
        $css = preg_replace('|/\*.*?\*/|s', ' ', $css);
        $css = preg_replace('/[\s\n\r\t\f]/s', ' ', $css);
        $css = $this->media_query_processor->process_media_queries($css);
        $css = $this->css_loader->process_data_uri_images($css);
        $css = preg_replace('/(<\!\-\-|\-\->)/s', ' ', $css);
        $css = $this->inline_style_parser->process_urls_in_css($css);
        $this->process_css_string($css);
        // Remove CSS (tags and content), if any (it can be <style> or <style type="txt/css">)
        $html = preg_replace('/<style.*?>(.*?)<\/style>/si', '', $html);
        return $html;
    }
    /**
     * @return array
     */
    public function get_css()
    {
        return $this->css;
    }
    /**
     * @return array
     */
    public function get_cascade_css()
    {
        return $this->cascade_css;
    }
    /**
     * @return array
     */
    public function get_used_class_names()
    {
        return array_keys($this->used_class_names);
    }
    /**
     * @return int
     */
    public function get_max_class_depth()
    {
        return $this->max_class_depth;
    }
    /**
     * @param string $css
     * @return void
     */
    private function process_css_string($css)
    {
        preg_match_all('/(.*?)\{(.*?)\}/', $css, $styles);
        $count = count($styles[1]);
        for ($i = 0; $i < $count; $i++) {
            $class_properties = $this->parse_css_properties($styles[2][$i]);
            $tag_name = strtoupper(trim($styles[1][$i]));
            $tags = explode(',', $tag_name);
            foreach ($tags as $tag) {
                $this->process_css_selector($tag, $class_properties);
            }
        }
    }
    /**
     * Process a CSS selector.
     *
     * @param string $selector Selector string
     * @param array $classProperties CSS properties
     * @return void
     */
    private function process_css_selector($selector, $class_properties)
    {
        // store classes in an index for faster lookups
        if (strpos($selector, '.') !== false && preg_match_all('/\.([a-zA-Z0-9_\-]+)/', $selector, $matches)) {
            foreach ($matches[1] as $class_name) {
                $this->used_class_names[$class_name] = true;
            }
            $class_count = count($matches[1]);
            if ($class_count > $this->max_class_depth) {
                $this->max_class_depth = $class_count;
            }
        }
        if (preg_match('/NTH-CHILD\((\s*(([\-+]?\d*)N(\s*[\-+]\s*\d+)?|[\-+]?\d+|ODD|EVEN)\s*)\)/', $selector, $m)) {
            $selector = preg_replace('/NTH-CHILD\(.*\)/', 'NTH-CHILD(' . str_replace(' ', '', $m[1]) . ')', $selector);
        }
        $tags = preg_split('/\s+/', trim($selector));
        $level = count($tags);
        if (trim($tags[0]) === '@PAGE') {
            $tag = $this->selector_parser->parse_page_selector($tags);
            if ($tag && isset($this->css[$tag])) {
                $this->css[$tag] = Arrays::unique_recursive_merge($this->css[$tag], $class_properties);
            } elseif ($tag) {
                $this->css[$tag] = $class_properties;
            }
            return;
        }
        if ($level === 1) {
            $tag = $this->selector_parser->parse_simple_selector($tags);
            if ($tag && isset($this->css[$tag])) {
                $this->css[$tag] = Arrays::unique_recursive_merge($this->css[$tag], $class_properties);
            } elseif ($tag) {
                $this->css[$tag] = $class_properties;
            }
            return;
        }
        $cascade = $this->selector_parser->parse_cascaded_selector($tags);
        if (empty($cascade)) {
            return;
        }
        $cascade_css =& $this->cascade_css;
        foreach ($cascade as $tag) {
            $cascade_css =& $cascade_css[$tag];
        }
        $cascade_css = Arrays::unique_recursive_merge($cascade_css, $class_properties);
        $cascade_css['depth'] = $level;
    }
    /**
     * Parse CSS property string into an array.
     *
     * @param string $rawStyles CSS style string (e.g. "color: red; font-size: 12px")
     * @return array Associative array of CSS properties
     */
    public function parse_css_properties($raw_styles)
    {
        $class_properties = [];
        $styles = explode(';', trim($raw_styles));
        foreach ($styles as $style) {
            if (empty(trim($style))) {
                continue;
            }
            // Changed to allow style="background: url('http://www.bpm1.com/bg.jpg')"
            $tmp = explode(':', $style, 2);
            $property = strtoupper(trim($tmp[0]));
            $value = isset($tmp[1]) ? $tmp[1] : '';
            $value = str_replace('%ZZ', ';', $value);
            // restore URL placeholder
            $value = preg_replace('/\s*!important/i', '', $value);
            $value = trim($value);
            if (empty($property)) {
                continue;
            }
            if (strlen($value) === 0) {
                continue;
            }
            // Ignores -webkit-gradient so doesn't override -moz-
            if (($property === 'BACKGROUND-IMAGE' || $property === 'BACKGROUND') && stripos($value, '-webkit-gradient') !== false) {
                continue;
            }
            $class_properties[$property] = $value;
        }
        return $this->normalize_properties->normalize($class_properties);
    }
    /**
     * Parse inline CSS style attribute.
     *
     * @param string $html CSS string from style attribute
     * @return array Parsed CSS properties
     */
    public function parse_inline_css($html)
    {
        return $this->inline_style_parser->parse($html);
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
        return $this->shadow_parser->parse_box_shadow($value);
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
        return $this->shadow_parser->parse_text_shadow($value);
    }
}