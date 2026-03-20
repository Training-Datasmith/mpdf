<?php

namespace Mpdf;

class Watermark_Text implements \Mpdf\Watermark
{
    /** @var string */
    private $text;
    /** @var int */
    private $size;
    /** @var int */
    private $angle;
    /** @var mixed */
    private $color;
    /** @var float */
    private $alpha;
    /** @var string */
    private $font;
    public function __construct($text, $size = 96, $angle = 45, $color = 0, $alpha = 0.2, $font = null)
    {
        $this->text = $text;
        $this->size = $size;
        $this->angle = $angle;
        $this->color = $color;
        $this->alpha = $alpha;
        $this->font = $font;
    }
    public function get_text()
    {
        return $this->text;
    }
    public function get_size()
    {
        return $this->size;
    }
    public function get_angle()
    {
        return $this->angle;
    }
    public function get_color()
    {
        return $this->color;
    }
    public function get_alpha()
    {
        return $this->alpha;
    }
    public function get_font()
    {
        return $this->font;
    }
}