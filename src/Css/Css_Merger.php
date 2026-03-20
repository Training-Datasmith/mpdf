<?php

declare (strict_types=1);
namespace Mpdf\Css;

use Mpdf\Color\Color_Converter;
use Mpdf\Css_Manager;
use Mpdf\Exception\InvalidArgumentException;
use Mpdf\Mpdf;
use Mpdf\Utils\Arrays;
class Css_Merger
{
    /**
     * @var \Mpdf\Mpdf
     */
    private $mpdf;
    /**
     * @var CssManager
     */
    private $css_manager;
    /**
     * @var \Mpdf\Css\NormalizeProperties
     */
    private $normalize_properties;
    /**
     * @var \Mpdf\Css\InlineStyleParser
     */
    private $inline_style_parser;
    /**
     * @var \Mpdf\Css\SelectorParser
     */
    private $selector_parser;
    /**
     * @var \Mpdf\Css\InlinePropertyConverter
     */
    private $inline_property_converter;
    /**
     * @var \Mpdf\Color\ColorConverter
     */
    private $color_converter;
    /**
     * @var array
     */
    private $css_properties = [];
    /**
     * @var \Mpdf\Css\BorderMerger
     */
    private $border_merger;
    /**
     * @var bool When true, state outside this object will be modified
     * @internal self::previewBlockCss() uses this property to look ahead without affecting state
     */
    private $side_effects = true;
    public function __construct(Mpdf $mpdf, Normalize_Properties $normalize_properties, Inline_Style_Parser $inline_style_parser, Selector_Parser $selector_parser, Inline_Property_Converter $inline_property_converter, Color_Converter $color_converter, Border_Merger $border_merger)
    {
        $this->mpdf = $mpdf;
        $this->normalize_properties = $normalize_properties;
        $this->inline_style_parser = $inline_style_parser;
        $this->selector_parser = $selector_parser;
        $this->inline_property_converter = $inline_property_converter;
        $this->color_converter = $color_converter;
        $this->border_merger = $border_merger;
    }
    /**
     * Make the CssManager state available to the merger
     *
     * @return void
     * @internal Temporary method. Required until the global CssManager properties/state is refactored
     */
    public function set_css_manager(Css_Manager $css_manager)
    {
        $this->css_manager = $css_manager;
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
    public function merge($inherit, $tag, $attr)
    {
        $this->css_properties = [];
        $attr = is_array($attr) ? $attr : [];
        $classes = [];
        if (isset($attr['CLASS'])) {
            // filter out classes that don't have any CSS applied, which reduces likelyhood of O(2^N) memory issue
            $raw_classes = preg_split('/\s+/', $attr['CLASS']);
            $raw_classes = array_intersect($raw_classes, $this->css_manager->get_used_class_names());
            $max_depth = $this->css_manager->get_max_class_depth();
            $classes = array_map(function ($combination) {
                return implode('.', $combination);
            }, Arrays::all_unique_sorted_combinations($raw_classes, $max_depth));
        }
        if (!isset($attr['ID'])) {
            $attr['ID'] = '';
        }
        $language_code = '';
        if (!isset($attr['LANG'])) {
            $attr['LANG'] = '';
        } else {
            $attr['LANG'] = strtolower($attr['LANG']);
            if (strlen($attr['LANG']) === 5) {
                $language_code = substr($attr['LANG'], 0, 2);
            }
        }
        $this->merge_table_cascading_css($inherit, $tag, $attr, $classes);
        $this->merge_block_cascading_css($inherit, $tag, $attr, $classes);
        $this->merge_inline_attributes($tag, $attr);
        $this->merge_default_css($tag);
        $this->merge_table_specific_css($tag, $attr);
        $this->merge_stylesheet_selectors($tag, $attr, $classes, $language_code);
        $this->merge_tag_specific_selectors($tag, $attr, $classes, $language_code);
        $this->merge_descendant_selectors($inherit, $tag, $attr, $classes);
        $this->merge_inline_style($tag, $attr);
        return $this->css_properties;
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
        $this->side_effects = false;
        $results = $this->merge('BLOCK', $tag, $attr);
        $this->side_effects = true;
        return $results;
    }
    /**
     * Merge table cascading CSS.
     *
     * Handles inheritance and cascading of CSS properties for tables.
     *
     * @param string $inherit Inheritance type (TOPTABLE, TABLE, BLOCK)
     * @param string $tag HTML tag name
     * @param array $attr HTML attributes
     * @param array $classes Array of class names
     * @return void
     */
    protected function merge_table_cascading_css($inherit, $tag, array $attr, $classes)
    {
        if (!in_array($inherit, ['TOPTABLE', 'TABLE'], true) || !$this->side_effects) {
            return;
        }
        if ($inherit === 'TOPTABLE') {
            // Save Cascading CSS e.g. "div.topic p" at this block level
            if (isset($this->mpdf->blk[$this->mpdf->blklvl]['cascadeCSS'])) {
                $this->css_manager->tablecascade_css[0] = $this->mpdf->blk[$this->mpdf->blklvl]['cascadeCSS'];
            } else {
                $this->css_manager->tablecascade_css[0] = $this->css_manager->cascade_css;
            }
        }
        // Cascade everything from last level that is not an actual property, or defined by current tag/attributes
        if (isset($this->css_manager->tablecascade_css[$this->css_manager->tb_cs_slvl - 1]) && is_array($this->css_manager->tablecascade_css[$this->css_manager->tb_cs_slvl - 1])) {
            foreach ($this->css_manager->tablecascade_css[$this->css_manager->tb_cs_slvl - 1] as $k => $v) {
                $this->css_manager->tablecascade_css[$this->css_manager->tb_cs_slvl][$k] = $v;
            }
        }
        $this->merge_full_css_rules($this->css_manager->cascade_css, $this->css_manager->tablecascade_css[$this->css_manager->tb_cs_slvl], $tag, $classes, $attr['ID'], $attr['LANG']);
        // Cascading forward CSS
        if (isset($this->css_manager->tablecascade_css[$this->css_manager->tb_cs_slvl - 1])) {
            $this->merge_full_css_rules($this->css_manager->tablecascade_css[$this->css_manager->tb_cs_slvl - 1], $this->css_manager->tablecascade_css[$this->css_manager->tb_cs_slvl], $tag, $classes, $attr['ID'], $attr['LANG']);
        }
    }
    /**
     * Merge block cascading CSS.
     *
     * Handles inheritance and cascading of CSS properties for block elements.
     *
     * @param string $inherit Inheritance type (TOPTABLE, TABLE, BLOCK)
     * @param string $tag HTML tag name
     * @param array $attr HTML attributes
     * @param array $classes Array of class names
     * @return void
     */
    protected function merge_block_cascading_css($inherit, $tag, array $attr, $classes)
    {
        if ($inherit !== 'BLOCK') {
            return;
        }
        $current_block = isset($this->mpdf->blk[$this->mpdf->blklvl]) ? $this->mpdf->blk[$this->mpdf->blklvl] : [];
        $current_block_has_cascade = isset($current_block['cascadeCSS']) && is_array($current_block['cascadeCSS']);
        $current_block['cascadeCSS'] = $current_block_has_cascade ? $current_block['cascadeCSS'] : [];
        $previous_block_level = $this->get_block_level();
        $previous_block = isset($this->mpdf->blk[$previous_block_level]) ? $this->mpdf->blk[$previous_block_level] : [];
        $previous_block_has_cascade = isset($previous_block['cascadeCSS']) && is_array($previous_block['cascadeCSS']);
        $previous_block['cascadeCSS'] = $previous_block_has_cascade ? $previous_block['cascadeCSS'] : [];
        foreach ($previous_block['cascadeCSS'] as $k => $v) {
            $current_block['cascadeCSS'][$k] = $v;
        }
        // Save Cascading CSS e.g. "div.topic p" at this block level
        $this->merge_full_css_rules($this->css_manager->cascade_css, $current_block['cascadeCSS'], $tag, $classes, $attr['ID'], $attr['LANG']);
        // Cascading forward CSS
        $this->merge_full_css_rules($previous_block['cascadeCSS'], $current_block['cascadeCSS'], $tag, $classes, $attr['ID'], $attr['LANG']);
        // Set the new block info
        if ($this->side_effects) {
            $this->mpdf->blk[$this->mpdf->blklvl] = $current_block;
        }
        // Block properties which are inherited
        if (!empty($previous_block['margin_collapse'])) {
            $this->css_properties['MARGIN-COLLAPSE'] = 'COLLAPSE';
        }
        // custom tag, but follows CSS principle that border-collapse is inherited
        if (!empty($previous_block['line_height'])) {
            $this->css_properties['LINE-HEIGHT'] = $previous_block['line_height'];
        }
        // mPDF 6
        if (!empty($previous_block['line_stacking_strategy'])) {
            $this->css_properties['LINE-STACKING-STRATEGY'] = $previous_block['line_stacking_strategy'];
        }
        if (!empty($previous_block['line_stacking_shift'])) {
            $this->css_properties['LINE-STACKING-SHIFT'] = $previous_block['line_stacking_shift'];
        }
        if (!empty($previous_block['direction'])) {
            $this->css_properties['DIRECTION'] = $previous_block['direction'];
        }
        // mPDF 6  Lists
        if ($tag === 'LI' && !empty($previous_block['list_style_type'])) {
            $this->css_properties['LIST-STYLE-TYPE'] = $previous_block['list_style_type'];
        }
        if (!empty($previous_block['list_style_image'])) {
            $this->css_properties['LIST-STYLE-IMAGE'] = $previous_block['list_style_image'];
        }
        if (!empty($previous_block['list_style_position'])) {
            $this->css_properties['LIST-STYLE-POSITION'] = $previous_block['list_style_position'];
        }
        if (!empty($previous_block['align'])) {
            switch ($previous_block['align']) {
                case 'L':
                    $this->css_properties['TEXT-ALIGN'] = 'left';
                    break;
                case 'J':
                    $this->css_properties['TEXT-ALIGN'] = 'justify';
                    break;
                case 'R':
                    $this->css_properties['TEXT-ALIGN'] = 'right';
                    break;
                case 'C':
                    $this->css_properties['TEXT-ALIGN'] = 'center';
                    break;
            }
        }
        if (!empty($previous_block['bgcolorarray']) && ($this->mpdf->col_active || $this->mpdf->keep_block_together)) {
            // Doesn't officially inherit, but default value is transparent (?=inherited)
            $cor = $previous_block['bgcolorarray'];
            $this->css_properties['BACKGROUND-COLOR'] = $this->color_converter->col_ato_string($cor);
        }
        if (isset($previous_block['text_indent'])) {
            $this->css_properties['TEXT-INDENT'] = $previous_block['text_indent'];
        }
        if (isset($previous_block['InlineProperties'])) {
            $converted = $this->inline_property_converter->convert($previous_block['InlineProperties']);
            $this->css_properties = array_merge($this->css_properties, $converted);
            // mPDF 5.7.1
        }
    }
    /**
     * Merge inline HTML attributes e.g. .. ALIGN="CENTER"
     *
     * Converts HTML attributes to CSS properties.
     *
     * @param string $tag HTML tag name
     * @param array $attr HTML attributes
     * @return void
     */
    protected function merge_inline_attributes($tag, array $attr)
    {
        if (!empty($attr['DIR'])) {
            $this->css_properties['DIRECTION'] = $attr['DIR'];
        }
        if (!empty($attr['LANG'])) {
            $this->css_properties['LANG'] = $attr['LANG'];
        }
        if (!empty($attr['COLOR'])) {
            $this->css_properties['COLOR'] = $attr['COLOR'];
        }
        if ($tag !== 'INPUT') {
            if (!empty($attr['WIDTH'])) {
                $this->css_properties['WIDTH'] = $attr['WIDTH'];
            }
            if (!empty($attr['HEIGHT'])) {
                $this->css_properties['HEIGHT'] = $attr['HEIGHT'];
            }
        }
        if ($tag === 'FONT') {
            if (!empty($attr['FACE'])) {
                $this->css_properties['FONT-FAMILY'] = $attr['FACE'];
            }
            $size = isset($attr['SIZE']) ? $attr['SIZE'] : '';
            if ($size === '+1') {
                $this->css_properties['FONT-SIZE'] = '120%';
            } elseif ($size === '-1') {
                $this->css_properties['FONT-SIZE'] = '86%';
            } elseif ($size === '1') {
                $this->css_properties['FONT-SIZE'] = 'XX-SMALL';
            } elseif ($size === '2') {
                $this->css_properties['FONT-SIZE'] = 'X-SMALL';
            } elseif ($size === '3') {
                $this->css_properties['FONT-SIZE'] = 'SMALL';
            } elseif ($size === '4') {
                $this->css_properties['FONT-SIZE'] = 'MEDIUM';
            } elseif ($size === '5') {
                $this->css_properties['FONT-SIZE'] = 'LARGE';
            } elseif ($size === '6') {
                $this->css_properties['FONT-SIZE'] = 'X-LARGE';
            } elseif ($size === '7') {
                $this->css_properties['FONT-SIZE'] = 'XX-LARGE';
            }
        }
        if (!empty($attr['VALIGN'])) {
            $this->css_properties['VERTICAL-ALIGN'] = $attr['VALIGN'];
        }
        if (!empty($attr['VSPACE'])) {
            $this->css_properties['MARGIN-TOP'] = $attr['VSPACE'];
            $this->css_properties['MARGIN-BOTTOM'] = $attr['VSPACE'];
        }
        if (!empty($attr['HSPACE'])) {
            $this->css_properties['MARGIN-LEFT'] = $attr['HSPACE'];
            $this->css_properties['MARGIN-RIGHT'] = $attr['HSPACE'];
        }
    }
    /**
     * Merge default CSS for the tag.
     *
     * @param string $tag HTML tag name
     * @return void
     */
    protected function merge_default_css($tag)
    {
        if (!isset($this->mpdf->default_css[$tag])) {
            return;
        }
        $zp = $this->normalize_properties->normalize($this->mpdf->default_css[$tag]);
        if (is_array($zp)) {
            // Default overwrites Inherited
            $this->css_properties = array_merge($this->css_properties, $zp);
            // !! Note other way round !!
            $this->merge_border_properties($zp);
        }
    }
    /**
     * Merge table specific CSS (CELLSPACING, CELLPADDING).
     *
     * @param string $tag HTML tag name
     * @param array $attr HTML attributes
     * @return void
     */
    protected function merge_table_specific_css($tag, array $attr)
    {
        if (!in_array($tag, ['TABLE', 'TD', 'TH'], true)) {
            return;
        }
        // cellSpacing overwrites TABLE default but not specific CSS set on table
        if ($tag === 'TABLE') {
            $cell_spacing = isset($attr['CELLSPACING']) ? $attr['CELLSPACING'] : '';
            if ($cell_spacing !== '') {
                $this->css_properties['BORDER-SPACING-H'] = $this->css_properties['BORDER-SPACING-V'] = $cell_spacing;
            }
            return;
        }
        $table_cell = isset($this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]) ? $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]] : [];
        // cellPadding overwrites TD/TH default but not specific CSS set on cell
        $cell_padding = isset($table_cell['cell_padding']) ? $table_cell['cell_padding'] : '';
        if (!empty($cell_padding) || $cell_padding === '0') {
            $this->css_properties['PADDING-LEFT'] = $cell_padding;
            $this->css_properties['PADDING-RIGHT'] = $cell_padding;
            $this->css_properties['PADDING-TOP'] = $cell_padding;
            $this->css_properties['PADDING-BOTTOM'] = $cell_padding;
        }
    }
    /**
     * Merge stylesheet selectors.
     *
     * Applies CSS rules from stylesheets based on tag, class, ID,
     *
     * @param string $tag HTML tag
     * @param array $attr HTML attributes
     * @param array $classes Array of class names
     * @param string $languageCode Short language code (e.g. 'en')
     * @return void
     */
    protected function merge_stylesheet_selectors($tag, array $attr, $classes, $language_code)
    {
        // STYLESHEET TAG e.g. h1  p  div  table
        if (isset($this->css_manager->CSS[$tag])) {
            $zp = $this->css_manager->CSS[$tag];
            if ($tag === 'TD' || $tag === 'TH') {
                $this->set_dominance_from_properties($zp, 9);
            }
            if (is_array($zp)) {
                $this->css_properties = array_merge($this->css_properties, $zp);
                $this->merge_border_properties($zp);
            }
        }
        // STYLESHEET CLASS e.g. .smallone{}  .redletter{}
        foreach ($classes as $class) {
            $zp = [];
            if (!empty($this->css_manager->CSS['CLASS>>' . $class])) {
                $zp = $this->css_manager->CSS['CLASS>>' . $class];
            }
            if ($tag === 'TD' || $tag === 'TH') {
                $this->set_dominance_from_properties($zp, 9);
            }
            if (is_array($zp)) {
                $this->css_properties = array_merge($this->css_properties, $zp);
                $this->merge_border_properties($zp);
            }
        }
        // STYLESHEET nth-child SELECTOR e.g. tr:nth-child(odd)  td:nth-child(2n+1)
        if ($tag === 'TR' || $tag === 'TD' || $tag === 'TH') {
            $regex = '/(([\-+]?\d*)?N([\-+]\d+)?|[\-+]?\d+|ODD|EVEN)/';
            foreach ($this->css_manager->CSS as $key => $selector) {
                if (!preg_match('/' . $tag . '>>SELECTORNTHCHILD>>(.*)/', $key, $m)) {
                    continue;
                }
                $select = false;
                switch ($tag) {
                    case 'TR':
                        $row = $this->mpdf->row;
                        $table_cell = isset($this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]) ? $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]] : [];
                        $thead_count = !empty($table_cell['is_thead']) ? count($table_cell['is_thead']) : 0;
                        $tfoot_count = !empty($table_cell['is_tfoot']) ? count($table_cell['is_tfoot']) : 0;
                        if ($this->mpdf->tabletfoot) {
                            $row -= $thead_count;
                        } elseif (!$this->mpdf->tablethead) {
                            $row -= $thead_count + $tfoot_count;
                        }
                        if (preg_match($regex, $m[1], $a)) {
                            // mPDF 5.7.4
                            $select = $this->selector_parser->matches_nth_child($a, $row);
                        }
                        break;
                    case 'TH':
                    case 'TD':
                        if (preg_match($regex, $m[1], $a)) {
                            // mPDF 5.7.4
                            $select = $this->selector_parser->matches_nth_child($a, $this->mpdf->col);
                        }
                        break;
                }
                if ($select) {
                    $zp = $this->css_manager->CSS[$tag . '>>SELECTORNTHCHILD>>' . $m[1]];
                    if ($tag === 'TD' || $tag === 'TH') {
                        $this->set_dominance_from_properties($zp, 9);
                    }
                    if (is_array($zp)) {
                        $this->css_properties = array_merge($this->css_properties, $zp);
                        $this->merge_border_properties($zp);
                    }
                }
            }
        }
        // STYLESHEET LANG e.g. [lang=fr]{} or :lang(fr)
        if (isset($attr['LANG'])) {
            if (!empty($this->css_manager->CSS['LANG>>' . $attr['LANG']])) {
                $zp = $this->css_manager->CSS['LANG>>' . $attr['LANG']];
                if ($tag === 'TD' || $tag === 'TH') {
                    $this->set_dominance_from_properties($zp, 9);
                }
                if (is_array($zp)) {
                    $this->css_properties = array_merge($this->css_properties, $zp);
                    $this->merge_border_properties($zp);
                }
            } elseif (!empty($this->css_manager->CSS['LANG>>' . $language_code])) {
                $zp = $this->css_manager->CSS['LANG>>' . $language_code];
                if ($tag === 'TD' || $tag === 'TH') {
                    $this->set_dominance_from_properties($zp, 9);
                }
                if (is_array($zp)) {
                    $this->css_properties = array_merge($this->css_properties, $zp);
                    $this->merge_border_properties($zp);
                }
            }
        }
        // STYLESHEET ID e.g. #smallone{}  #redletter{}
        if (!empty($attr['ID']) && !empty($this->css_manager->CSS['ID>>' . $attr['ID']])) {
            $zp = $this->css_manager->CSS['ID>>' . $attr['ID']];
            if ($tag === 'TD' || $tag === 'TH') {
                $this->set_dominance_from_properties($zp, 9);
            }
            if (is_array($zp)) {
                $this->css_properties = array_merge($this->css_properties, $zp);
                $this->merge_border_properties($zp);
            }
        }
    }
    /**
     * Merge tag specific selectors (Tag.Class, Tag#ID, etc.).
     *
     * @param string $tag HTML tag
     * @param array $attr HTML attributes
     * @param array $classes Array of class names
     * @param string $languageCode Short language code (e.g. 'en')
     * @return void
     */
    protected function merge_tag_specific_selectors($tag, array $attr, $classes, $language_code)
    {
        // STYLESHEET CLASS e.g. p.smallone{}  div.redletter{}
        foreach ($classes as $class) {
            $zp = [];
            if (!empty($this->css_manager->CSS[$tag . '>>CLASS>>' . $class])) {
                $zp = $this->css_manager->CSS[$tag . '>>CLASS>>' . $class];
            }
            if ($tag === 'TD' || $tag === 'TH') {
                $this->set_dominance_from_properties($zp, 9);
            }
            if (is_array($zp)) {
                $this->css_properties = array_merge($this->css_properties, $zp);
                $this->merge_border_properties($zp);
            }
        }
        // STYLESHEET LANG e.g. [lang=fr]{} or :lang(fr)
        if (isset($attr['LANG'])) {
            if (!empty($this->css_manager->CSS[$tag . '>>LANG>>' . $attr['LANG']])) {
                $zp = $this->css_manager->CSS[$tag . '>>LANG>>' . $attr['LANG']];
                if ($tag === 'TD' || $tag === 'TH') {
                    $this->set_dominance_from_properties($zp, 9);
                }
                if (is_array($zp)) {
                    $this->css_properties = array_merge($this->css_properties, $zp);
                    $this->merge_border_properties($zp);
                }
            } elseif (!empty($this->css_manager->CSS[$tag . '>>LANG>>' . $language_code])) {
                $zp = $this->css_manager->CSS[$tag . '>>LANG>>' . $language_code];
                if ($tag === 'TD' || $tag === 'TH') {
                    $this->set_dominance_from_properties($zp, 9);
                }
                if (is_array($zp)) {
                    $this->css_properties = array_merge($this->css_properties, $zp);
                    $this->merge_border_properties($zp);
                }
            }
        }
        // STYLESHEET CLASS e.g. p#smallone{}  div#redletter{}
        if (isset($attr['ID']) && !empty($this->css_manager->CSS[$tag . '>>ID>>' . $attr['ID']])) {
            $zp = $this->css_manager->CSS[$tag . '>>ID>>' . $attr['ID']];
            if ($tag === 'TD' || $tag === 'TH') {
                $this->set_dominance_from_properties($zp, 9);
            }
            if (is_array($zp)) {
                $this->css_properties = array_merge($this->css_properties, $zp);
                $this->merge_border_properties($zp);
            }
        }
    }
    /**
     * Merge cascaded CSS properties (BLOCK, INLINE, TABLE).
     *
     * @param string $inherit Inheritance context
     * @param string $tag HTML tag
     * @param array $attr HTML attributes
     * @param array $classes Array of class names
     * @return void
     */
    protected function merge_descendant_selectors($inherit, $tag, $attr, $classes)
    {
        if ($inherit === 'TOPTABLE' || $inherit === 'TABLE') {
            $this->merge_table_descendant_selectors($tag, $attr, $classes);
            return;
        }
        $level = $this->get_block_level($inherit);
        if (!isset($this->mpdf->blk[$level]['cascadeCSS'])) {
            return;
        }
        $cascade_css = $this->mpdf->blk[$level]['cascadeCSS'];
        $this->merge_descendant_css($cascade_css, $tag, $attr, $classes);
        if ($this->side_effects) {
            $this->mpdf->blk[$level]['cascadeCSS'] = $cascade_css;
        }
    }
    /**
     * Merge table cascaded CSS.
     *
     * @param string $tag HTML tag
     * @param array $attr HTML attributes
     * @param array $classes Array of class names
     * @return void
     */
    protected function merge_table_descendant_selectors($tag, array $attr, $classes)
    {
        $node = isset($this->css_manager->tablecascade_css[$this->css_manager->tb_cs_slvl - 1]) ? $this->css_manager->tablecascade_css[$this->css_manager->tb_cs_slvl - 1] : [];
        if (empty($node)) {
            return;
        }
        // don't check for 'depth' and do set border dominance
        $this->set_merged_css($node[$tag], false, 9);
        foreach ($classes as $class) {
            $this->set_merged_css($node['CLASS>>' . $class], false, 9);
        }
        // STYLESHEET nth-child SELECTOR e.g. tr:nth-child(odd)  td:nth-child(2n+1)
        if ($tag === 'TR' || $tag === 'TD' || $tag === 'TH') {
            foreach ($node as $k => $val) {
                if (!preg_match('/' . $tag . '>>SELECTORNTHCHILD>>(.*)/', $k, $m)) {
                    continue;
                }
                $select = false;
                $regex = '/(([\-+]?\d*)?N([\-+]\d+)?|[\-+]?\d+|ODD|EVEN)/';
                if ($tag === 'TR') {
                    $row = $this->mpdf->row;
                    $table = isset($this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]) ? $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]] : [];
                    $table_head_count = isset($table['is_thead']) ? count($table['is_thead']) : 0;
                    $table_foot_count = isset($table['is_tfoot']) ? count($table['is_tfoot']) : 0;
                    if ($this->mpdf->tabletfoot) {
                        $row -= $table_head_count;
                    } elseif (!$this->mpdf->tablethead) {
                        $row -= $table_head_count + $table_foot_count;
                    }
                    if (preg_match($regex, $m[1], $a)) {
                        // mPDF 5.7.4
                        $select = $this->selector_parser->matches_nth_child($a, $row);
                    }
                } elseif (($tag === 'TD' || $tag === 'TH') && preg_match($regex, $m[1], $a)) {
                    $select = $this->selector_parser->matches_nth_child($a, $this->mpdf->col);
                }
                if ($select) {
                    $this->set_merged_css($node[$tag . '>>SELECTORNTHCHILD>>' . $m[1]], false, 9);
                }
            }
        }
        $this->set_merged_css($node['ID>>' . $attr['ID']], false, 9);
        foreach ($classes as $class) {
            $this->set_merged_css($node[$tag . '>>CLASS>>' . $class], false, 9);
        }
        $this->set_merged_css($node[$tag . '>>ID>>' . $attr['ID']], false, 9);
        if ($this->side_effects) {
            $this->css_manager->tablecascade_css[$this->css_manager->tb_cs_slvl - 1] = $node;
        }
    }
    /**
     * Apply descendant CSS rules.
     *
     * @param array $cascadeCSS
     * @param string $tag
     * @param array $classes
     */
    protected function merge_descendant_css($cascade_css, $tag, array $attr, $classes)
    {
        if (empty($cascade_css)) {
            return;
        }
        $this->set_merged_css($cascade_css[$tag]);
        foreach ($classes as $class) {
            $this->set_merged_css($cascade_css['CLASS>>' . $class]);
        }
        $this->set_merged_css($cascade_css['ID>>' . $attr['ID']]);
        foreach ($classes as $class) {
            $this->set_merged_css($cascade_css[$tag . '>>CLASS>>' . $class]);
        }
        $this->set_merged_css($cascade_css[$tag . '>>ID>>' . $attr['ID']]);
    }
    /**
     * Merge CSS properties into target array.
     *
     * Internal method to merge CSS properties from source into target.
     * Used for CSS cascading.
     *
     * @param array $property Source CSS properties
     * @param array $target Target CSS properties (modified by reference)
     * @return void
     */
    protected function merge_css_properties($property, &$target)
    {
        if (empty($property)) {
            return;
        }
        $target = $target ? Arrays::unique_recursive_merge($target, $property) : $property;
    }
    /**
     * Merge Nth-child CSS selectors.
     *
     * Handles :nth-child() pseudo-class logic for TR, TD, and TH tags.
     *
     * @param array $sourceSelectors Source CSS selector array
     * @param array $targetProperties Target CSS properties (passed by reference)
     * @param string $tag HTML tag name
     * @return void
     */
    protected function merge_nth_child_css($source_selectors, &$target_properties, $tag)
    {
        if (!in_array($tag, ['TR', 'TH', 'TD'], true) || empty($source_selectors)) {
            return;
        }
        $regex = '/(([\-+]?\d*)?N([\-+]\d+)?|[\-+]?\d+|ODD|EVEN)/';
        foreach ($source_selectors as $key => $selector) {
            if (!preg_match('/' . $tag . '>>SELECTORNTHCHILD>>(.*)/', $key, $m)) {
                continue;
            }
            $select = false;
            switch ($tag) {
                case 'TR':
                    $row = $this->mpdf->row;
                    $table_cell = isset($this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]) ? $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]] : [];
                    $thead_count = !empty($table_cell['is_thead']) ? count($table_cell['is_thead']) : 0;
                    $tfoot_count = !empty($table_cell['is_tfoot']) ? count($table_cell['is_tfoot']) : 0;
                    if ($this->mpdf->tabletfoot) {
                        $row -= $thead_count;
                    } elseif (!$this->mpdf->tablethead) {
                        $row -= $thead_count + $tfoot_count;
                    }
                    if (preg_match($regex, $m[1], $a)) {
                        // mPDF 5.7.4
                        $select = $this->selector_parser->matches_nth_child($a, $row);
                    }
                    break;
                case 'TH':
                case 'TD':
                    if (preg_match($regex, $m[1], $a)) {
                        // mPDF 5.7.4
                        $select = $this->selector_parser->matches_nth_child($a, $this->mpdf->col);
                    }
                    break;
            }
            if ($select) {
                $this->merge_css_properties($source_selectors[$tag . '>>SELECTORNTHCHILD>>' . $m[1]], $target_properties);
            }
        }
    }
    /**
     * Merge full CSS rules including tag, class, ID, and lang selectors.
     *
     * Applies CSS rules from various selector types (tag, class, ID, language)
     * to the target CSS properties array. Handles CSS cascading and specificity.
     *
     * @param array $p Source CSS selector array
     * @param array $t Target CSS properties (modified by reference)
     * @param string $tag HTML tag name
     * @param array $classes Array of class names
     * @param string $id Element ID
     * @param string $lang Language code
     * @return void
     */
    protected function merge_full_css_rules(array $p, &$t, $tag, $classes, $id, $lang)
    {
        // mPDF 6
        if (isset($p[$tag])) {
            $this->merge_css_properties($p[$tag], $t);
        }
        // STYLESHEET CLASS e.g. .smallone{}  .redletter{}
        foreach ($classes as $class) {
            if (isset($p['CLASS>>' . $class])) {
                $this->merge_css_properties($p['CLASS>>' . $class], $t);
            }
        }
        // STYLESHEET nth-child SELECTOR e.g. tr:nth-child(odd)  td:nth-child(2n+1)
        $this->merge_nth_child_css($p, $t, $tag);
        // STYLESHEET CLASS e.g. [lang=fr]{} or :lang(fr)
        if (isset($lang) && isset($p['LANG>>' . $lang])) {
            $this->merge_css_properties($p['LANG>>' . $lang], $t);
        }
        // STYLESHEET CLASS e.g. #smallone{}  #redletter{}
        if (isset($id) && isset($p['ID>>' . $id])) {
            $this->merge_css_properties($p['ID>>' . $id], $t);
        }
        // STYLESHEET CLASS e.g. .smallone{}  .redletter{}
        foreach ($classes as $class) {
            if (isset($p[$tag . '>>CLASS>>' . $class])) {
                $this->merge_css_properties($p[$tag . '>>CLASS>>' . $class], $t);
            }
        }
        // STYLESHEET CLASS e.g. [lang=fr]{} or :lang(fr)
        if (isset($lang) && isset($p[$tag . '>>LANG>>' . $lang])) {
            $this->merge_css_properties($p[$tag . '>>LANG>>' . $lang], $t);
        }
        // STYLESHEET CLASS e.g. #smallone{}  #redletter{}
        if (isset($id) && isset($p[$tag . '>>ID>>' . $id])) {
            $this->merge_css_properties($p[$tag . '>>ID>>' . $id], $t);
        }
    }
    /**
     * Merge inline style attribute CSS.
     *
     * @param string $tag HTML tag name
     * @param array $attr HTML attributes
     * @return void
     */
    protected function merge_inline_style($tag, array $attr)
    {
        // INLINE STYLE e.g. style="CSS:property"
        if (!isset($attr['STYLE'])) {
            return;
        }
        $zp = $this->inline_style_parser->parse($attr['STYLE']);
        if ($tag === 'TD' || $tag === 'TH') {
            $this->set_dominance_from_properties($zp, 9);
        }
        if (is_array($zp)) {
            $this->css_properties = array_merge($this->css_properties, $zp);
            $this->merge_border_properties($zp);
        }
    }
    /**
     * Merge CSS properties with existing properties.
     *
     * @param array $property Source CSS properties
     * @param bool $strictMode Use default strict mode
     * @param bool|int $borderDominanceLevel Border dominance level (or false)
     * @return void
     */
    protected function set_merged_css(&$property, $strict_mode = true, $border_dominance_level = false)
    {
        if (!isset($property)) {
            return;
        }
        $depth = isset($property['depth']) ? $property['depth'] : 0;
        if ($depth < 2 && $strict_mode) {
            return;
        }
        if ($border_dominance_level) {
            $this->set_dominance_from_properties($property, $border_dominance_level);
        }
        if (is_array($property)) {
            $this->css_properties = array_merge($this->css_properties, $property);
            $this->merge_border_properties($property);
        }
    }
    /**
     * Merge borders into CSS properties.
     *
     * @param array $properties properties to merge
     * @return void
     */
    protected function merge_border_properties($properties)
    {
        $this->border_merger->merge_border_properties($properties, $this->css_properties);
    }
    /**
     * Set border dominance level for table cells.
     *
     * Used in table rendering to determine which cell borders take
     * precedence when cells share borders.
     *
     * @param array $prop CSS properties containing border definitions
     * @param int $val Dominance level value
     * @return void
     */
    public function set_dominance_from_properties($prop, $val)
    {
        if (!$this->side_effects) {
            return;
        }
        $this->border_merger->set_dominance_from_properties($prop, $val);
    }
    /**
     * Set border dominance level for a specific side.
     *
     * @param string $side T|R|B|L
     * @param int $val Dominance value
     * @throws InvalidArgumentException
     */
    public function set_border_dominance($side, $val)
    {
        $this->border_merger->set_border_dominance($side, $val);
    }
    /**
     * Get border dominance level for a specific side.
     *
     * @param string $side T|R|B|L
     * @return int Dominance value
     */
    public function get_border_dominance($side)
    {
        return $this->border_merger->get_border_dominance($side);
    }
    /**
     * @return int
     */
    protected function get_block_level($inherit = 'BLOCK')
    {
        if (!$this->side_effects || $inherit !== 'BLOCK') {
            return $this->mpdf->blklvl;
        }
        return $this->mpdf->blklvl - 1;
    }
}