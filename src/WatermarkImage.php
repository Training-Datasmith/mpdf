<?php

namespace Mpdf;

class Watermark_Image implements \Mpdf\Watermark
{
    const SIZE_DEFAULT = 'D';
    const SIZE_FIT_PAGE = 'P';
    const SIZE_FIT_FRAME = 'F';
    const POSITION_CENTER_PAGE = 'P';
    const POSITION_CENTER_FRAME = 'F';
    /** @var string */
    private $path;
    /** @var mixed */
    private $size;
    /** @var mixed */
    private $position;
    /** @var float */
    private $alpha;
    /** @var bool */
    private $behind_content;
    /** @var string */
    private $alpha_blend;
    public function __construct($path, $size = self::SIZE_DEFAULT, $position = self::POSITION_CENTER_PAGE, $alpha = -1, $behind_content = false, $alpha_blend = 'Normal')
    {
        $this->path = $path;
        $this->size = $size;
        $this->position = $position;
        $this->alpha = $alpha;
        $this->behind_content = $behind_content;
        $this->alpha_blend = $alpha_blend;
    }
    public function get_path()
    {
        return $this->path;
    }
    public function get_size()
    {
        return $this->size;
    }
    public function get_position()
    {
        return $this->position;
    }
    public function get_alpha()
    {
        return $this->alpha;
    }
    public function is_behind_content()
    {
        return $this->behind_content;
    }
    public function get_alpha_blend()
    {
        return $this->alpha_blend;
    }
}