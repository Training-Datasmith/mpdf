<?php

namespace Mpdf;

use Mpdf\Strict;
use Mpdf\Color\Color_Converter;
use Mpdf\Image\Image_Processor;
use Mpdf\Language\Language_To_Font_Interface;
class Tag
{
    use Strict;
    /**
     * @var \Mpdf\Mpdf
     */
    private $mpdf;
    /**
     * @var \Mpdf\Cache
     */
    private $cache;
    /**
     * @var \Mpdf\CssManager
     */
    private $css_manager;
    /**
     * @var \Mpdf\Form
     */
    private $form;
    /**
     * @var \Mpdf\Otl
     */
    private $otl;
    /**
     * @var \Mpdf\TableOfContents
     */
    private $table_of_contents;
    /**
     * @var \Mpdf\SizeConverter
     */
    private $size_converter;
    /**
     * @var \Mpdf\Color\ColorConverter
     */
    private $color_converter;
    /**
     * @var \Mpdf\Image\ImageProcessor
     */
    private $image_processor;
    /**
     * @var \Mpdf\Language\LanguageToFontInterface
     */
    private $language_to_font;
    /**
     * @param \Mpdf\Mpdf $mpdf
     * @param \Mpdf\Cache $cache
     * @param \Mpdf\CssManager $cssManager
     * @param \Mpdf\Form $form
     * @param \Mpdf\Otl $otl
     * @param \Mpdf\TableOfContents $tableOfContents
     * @param \Mpdf\SizeConverter $sizeConverter
     * @param \Mpdf\Color\ColorConverter $colorConverter
     * @param \Mpdf\Image\ImageProcessor $imageProcessor
     * @param \Mpdf\Language\LanguageToFontInterface $languageToFont
     */
    public function __construct(Mpdf $mpdf, Cache $cache, Css_Manager $css_manager, Form $form, Otl $otl, Table_Of_Contents $table_of_contents, Size_Converter $size_converter, Color_Converter $color_converter, Image_Processor $image_processor, Language_To_Font_Interface $language_to_font)
    {
        $this->mpdf = $mpdf;
        $this->cache = $cache;
        $this->css_manager = $css_manager;
        $this->form = $form;
        $this->otl = $otl;
        $this->table_of_contents = $table_of_contents;
        $this->size_converter = $size_converter;
        $this->color_converter = $color_converter;
        $this->image_processor = $image_processor;
        $this->language_to_font = $language_to_font;
    }
    /**
     * @param string $tag The tag name
     * @return \Mpdf\Tag\Tag
     */
    private function get_tag_instance($tag)
    {
        $class_name = self::get_tag_class_name($tag);
        if (class_exists($class_name)) {
            return new $class_name($this->mpdf, $this->cache, $this->css_manager, $this->form, $this->otl, $this->table_of_contents, $this->size_converter, $this->color_converter, $this->image_processor, $this->language_to_font);
        }
    }
    /**
     * Returns the fully qualified name of the class handling the rendering of the given tag
     *
     * @param string $tag The tag name
     * @return string The fully qualified name
     */
    public static function get_tag_class_name($tag)
    {
        static $map = ['BARCODE' => 'BarCode', 'BLOCKQUOTE' => 'BlockQuote', 'COLUMN_BREAK' => 'ColumnBreak', 'COLUMNBREAK' => 'ColumnBreak', 'DOTTAB' => 'DotTab', 'FIELDSET' => 'FieldSet', 'FIGCAPTION' => 'FigCaption', 'FORMFEED' => 'FormFeed', 'HGROUP' => 'HGroup', 'INDEXENTRY' => 'IndexEntry', 'INDEXINSERT' => 'IndexInsert', 'NEWCOLUMN' => 'NewColumn', 'NEWPAGE' => 'NewPage', 'PAGEFOOTER' => 'PageFooter', 'PAGEHEADER' => 'PageHeader', 'PAGE_BREAK' => 'PageBreak', 'PAGEBREAK' => 'PageBreak', 'SETHTMLPAGEFOOTER' => 'SetHtmlPageFooter', 'SETHTMLPAGEHEADER' => 'SetHtmlPageHeader', 'SETPAGEFOOTER' => 'SetPageFooter', 'SETPAGEHEADER' => 'SetPageHeader', 'TBODY' => 'TBody', 'TFOOT' => 'TFoot', 'THEAD' => 'THead', 'TEXTAREA' => 'TextArea', 'TEXTCIRCLE' => 'TextCircle', 'TOCENTRY' => 'TocEntry', 'TOCPAGEBREAK' => 'TocPageBreak', 'VAR' => 'VarTag', 'WATERMARKIMAGE' => 'WatermarkImage', 'WATERMARKTEXT' => 'WatermarkText'];
        $class_name = 'Mpdf\Tag\\';
        $class_name .= isset($map[$tag]) ? $map[$tag] : ucfirst(strtolower($tag));
        return $class_name;
    }
    public function open_tag($tag, $attr, &$ahtml, &$ihtml)
    {
        // Correct for tags where HTML5 specifies optional end tags excluding table elements (cf WriteHTML() )
        if ($this->mpdf->allow_html_optional_endtags) {
            if (isset($this->mpdf->blk[$this->mpdf->blklvl]['tag'])) {
                $closed = false;
                // li end tag may be omitted if immediately followed by another li element
                if (!$closed && $this->mpdf->blk[$this->mpdf->blklvl]['tag'] == 'LI' && $tag == 'LI') {
                    $this->close_tag('LI', $ahtml, $ihtml);
                    $closed = true;
                }
                // dt end tag may be omitted if immediately followed by another dt element or a dd element
                if (!$closed && $this->mpdf->blk[$this->mpdf->blklvl]['tag'] == 'DT' && ($tag == 'DT' || $tag == 'DD')) {
                    $this->close_tag('DT', $ahtml, $ihtml);
                    $closed = true;
                }
                // dd end tag may be omitted if immediately followed by another dd element or a dt element
                if (!$closed && $this->mpdf->blk[$this->mpdf->blklvl]['tag'] == 'DD' && ($tag == 'DT' || $tag == 'DD')) {
                    $this->close_tag('DD', $ahtml, $ihtml);
                    $closed = true;
                }
                // p end tag may be omitted if immediately followed by an address, article, aside, blockquote, div, dl,
                // fieldset, form, h1, h2, h3, h4, h5, h6, hgroup, hr, main, nav, ol, p, pre, section, table, ul
                if (!$closed && $this->mpdf->blk[$this->mpdf->blklvl]['tag'] == 'P' && ($tag == 'P' || $tag == 'DIV' || $tag == 'H1' || $tag == 'H2' || $tag == 'H3' || $tag == 'H4' || $tag == 'H5' || $tag == 'H6' || $tag == 'UL' || $tag == 'OL' || $tag == 'TABLE' || $tag == 'PRE' || $tag == 'FORM' || $tag == 'ADDRESS' || $tag == 'BLOCKQUOTE' || $tag == 'CENTER' || $tag == 'DL' || $tag == 'HR' || $tag == 'ARTICLE' || $tag == 'ASIDE' || $tag == 'FIELDSET' || $tag == 'HGROUP' || $tag == 'MAIN' || $tag == 'NAV' || $tag == 'SECTION')) {
                    $this->close_tag('P', $ahtml, $ihtml);
                    $closed = true;
                }
                // option end tag may be omitted if immediately followed by another option element
                // (or if it is immediately followed by an optgroup element)
                if (!$closed && $this->mpdf->blk[$this->mpdf->blklvl]['tag'] == 'OPTION' && $tag == 'OPTION') {
                    $this->close_tag('OPTION', $ahtml, $ihtml);
                    $closed = true;
                }
                // Table elements - see also WriteHTML()
                if (!$closed && ($tag == 'TD' || $tag == 'TH') && $this->mpdf->lastoptionaltag == 'TD') {
                    $this->close_tag($this->mpdf->lastoptionaltag, $ahtml, $ihtml);
                    $closed = true;
                }
                // *TABLES*
                if (!$closed && ($tag == 'TD' || $tag == 'TH') && $this->mpdf->lastoptionaltag == 'TH') {
                    $this->close_tag($this->mpdf->lastoptionaltag, $ahtml, $ihtml);
                    $closed = true;
                }
                // *TABLES*
                if (!$closed && $tag == 'TR' && $this->mpdf->lastoptionaltag == 'TR') {
                    $this->close_tag($this->mpdf->lastoptionaltag, $ahtml, $ihtml);
                    $closed = true;
                }
                // *TABLES*
                if (!$closed && $tag == 'TR' && $this->mpdf->lastoptionaltag == 'TD') {
                    $this->close_tag($this->mpdf->lastoptionaltag, $ahtml, $ihtml);
                    $this->close_tag('TR', $ahtml, $ihtml);
                    $this->close_tag('THEAD', $ahtml, $ihtml);
                    $closed = true;
                }
                // *TABLES*
                if (!$closed && $tag == 'TR' && $this->mpdf->lastoptionaltag == 'TH') {
                    $this->close_tag($this->mpdf->lastoptionaltag, $ahtml, $ihtml);
                    $this->close_tag('TR', $ahtml, $ihtml);
                    $this->close_tag('THEAD', $ahtml, $ihtml);
                    $closed = true;
                }
                // *TABLES*
            }
        }
        if ($object = $this->get_tag_instance($tag)) {
            return $object->open($attr, $ahtml, $ihtml);
        }
    }
    public function close_tag($tag, &$ahtml, &$ihtml)
    {
        if ($object = $this->get_tag_instance($tag)) {
            return $object->close($ahtml, $ihtml);
        }
    }
}