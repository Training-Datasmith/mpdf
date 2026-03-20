<?php

namespace Mpdf\Tag;

use Mpdf\Strict;
use Mpdf\Cache;
use Mpdf\Color\Color_Converter;
use Mpdf\Css_Manager;
use Mpdf\Form;
use Mpdf\Image\Image_Processor;
use Mpdf\Language\Language_To_Font_Interface;
use Mpdf\Mpdf;
use Mpdf\Otl;
use Mpdf\Size_Converter;
use Mpdf\Table_Of_Contents;
abstract class Tag
{
    use Strict;
    /**
     * @var \Mpdf\Mpdf
     */
    protected $mpdf;
    /**
     * @var \Mpdf\Cache
     */
    protected $cache;
    /**
     * @var \Mpdf\CssManager
     */
    protected $css_manager;
    /**
     * @var \Mpdf\Form
     */
    protected $form;
    /**
     * @var \Mpdf\Otl
     */
    protected $otl;
    /**
     * @var \Mpdf\TableOfContents
     */
    protected $table_of_contents;
    /**
     * @var \Mpdf\SizeConverter
     */
    protected $size_converter;
    /**
     * @var \Mpdf\Color\ColorConverter
     */
    protected $color_converter;
    /**
     * @var \Mpdf\Image\ImageProcessor
     */
    protected $image_processor;
    /**
     * @var \Mpdf\Language\LanguageToFontInterface
     */
    protected $language_to_font;
    const ALIGN = ['left' => 'L', 'center' => 'C', 'right' => 'R', 'top' => 'T', 'text-top' => 'TT', 'middle' => 'M', 'baseline' => 'BS', 'bottom' => 'B', 'text-bottom' => 'TB', 'justify' => 'J'];
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
    public function get_tag_name()
    {
        $tag = get_class($this);
        return strtoupper(str_replace('Mpdf\Tag\\', '', $tag));
    }
    protected function get_align($property)
    {
        $property = strtolower($property);
        return array_key_exists($property, self::ALIGN) ? self::ALIGN[$property] : '';
    }
    abstract public function open($attr, &$ahtml, &$ihtml);
    abstract public function close(&$ahtml, &$ihtml);
}