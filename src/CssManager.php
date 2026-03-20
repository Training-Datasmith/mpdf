<?php

declare (strict_types=1);
namespace Mpdf;

use Mpdf\Css\Css_Merger;
use Mpdf\Css\Css_Parser;
use Mpdf\Exception\InvalidArgumentException;
use Mpdf\Utils\Arrays;
class Css_Manager
{
    /**
     * @var \Mpdf\Css\CssParser
     */
    private $css_parser;
    /**
     * @var \Mpdf\Css\CssMerger
     */
    private $css_merger;
    /**
     * Main CSS property storage array.
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
    public $CSS = [];
    /**
     * CSS cascade storage for table elements.
     *
     * Stores cascaded CSS properties specifically for table elements (TABLE, THEAD, TBODY, TFOOT, TR, TH, TD).
     * Format is a nested array mirroring the selector hierarchy.
     * Example for "DIV TABLE TD":
     * [
     *   'DIV' => [
     *     'TABLE' => [
     *       'TD' => [
     *         'BORDER' => '1px solid green',
     *         'depth' => 3
     *       ]
     *     ]
     *   ]
     * ]
     *
     * @var array
     */
    public $tablecascade_css = [];
    /**
     * Cascading CSS property storage.
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
    public $cascade_css = [];
    /**
     * @var int Table CSS cascade level counter
     */
    public $tb_cs_slvl = 0;
    /**
     * CssManager constructor.
     *
     * Initializes the CSS manager with required dependencies and sets up
     * internal storage structures for CSS properties and cascading.
     */
    public function __construct(Css_Parser $css_parser, Css_Merger $css_merger)
    {
        $this->css_parser = $css_parser;
        $this->css_merger = $css_merger;
        $this->css_merger->set_css_manager($this);
    }
    /**
     * Read and parse CSS from HTML content.
     *
     * Extracts CSS from style tags, link tags, and @import statements within HTML.
     * Processes external stylesheets, resolves URLs, handles media queries, and
     * parses all CSS rules into the internal CSS storage structure.
     *
     * @param string $html HTML content containing CSS
     * @return string HTML with CSS content removed
     */
    public function read_css($html)
    {
        if (!is_array($this->cascade_css)) {
            $this->cascade_css = [];
        }
        $html = $this->css_parser->parse($html);
        $this->CSS = Arrays::unique_recursive_merge($this->CSS, $this->css_parser->get_css());
        $this->cascade_css = Arrays::unique_recursive_merge($this->cascade_css, $this->css_parser->get_cascade_css());
        return $html;
    }
    /**
     * Parse inline CSS style attribute.
     *
     * @param string $html CSS string from style attribute
     * @return array Parsed CSS properties
     */
    public function read_inline_css($html)
    {
        return $this->css_parser->parse_inline_css($html);
    }
    /**
     * Merge CSS properties for an HTML element.
     *
     * Main method for applying CSS to an element. Combines CSS from multiple sources
     * including default styles, stylesheets, inline styles, and inherited properties.
     * Handles inheritance type (BLOCK, INLINE, TABLE, TOPTABLE) and applies
     * appropriate cascading rules.
     *
     * @param string $inherit Inheritance context (BLOCK, INLINE, TABLE, TOPTABLE)
     * @param string $tag HTML tag name
     * @param array $attr HTML attributes including CLASS, ID, STYLE
     * @return array Merged CSS properties array
     */
    public function merge_css($inherit, $tag, $attr)
    {
        return $this->css_merger->merge($inherit, $tag, $attr);
    }
    /**
     * Preview block-level CSS without creating the block.
     *
     * Looks ahead to determine what CSS would be applied to a block element
     * without actually creating it. Used for planning layout and spacing.
     *
     * @param string $tag HTML tag name
     * @param array $attr HTML attributes array
     * @return array CSS properties that would be applied
     */
    public function preview_block_css($tag, $attr)
    {
        return $this->css_merger->preview_block_css($tag, $attr);
    }
    public function get_used_class_names()
    {
        return $this->css_parser->get_used_class_names();
    }
    public function get_max_class_depth()
    {
        return $this->css_parser->get_max_class_depth();
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
    public function set_css_box_shadow($value)
    {
        return $this->css_parser->parse_box_shadow($value);
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
    public function set_css_text_shadow($value)
    {
        return $this->css_parser->parse_text_shadow($value);
    }
    /**
     * Set border dominance level for a specific side.
     *
     * @param string $side T|R|B|L
     * @param int $val Dominance value
     * @throws InvalidArgumentException
     * @return void
     */
    public function set_border_dominance($side, $val)
    {
        $this->css_merger->set_border_dominance($side, $val);
    }
    /**
     * Get border dominance level for a specific side.
     *
     * @param string $side T|R|B|L
     * @return int Dominance value
     */
    public function get_border_dominance($side)
    {
        return $this->css_merger->get_border_dominance($side);
    }
}