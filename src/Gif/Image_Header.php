<?php

declare (strict_types=1);
namespace Mpdf\Gif;

/**
 * GIF Util - (C) 2003 Yamasoft (S/C)
 *
 * All Rights Reserved
 *
 * This file can be freely copied, distributed, modified, updated by anyone under the only
 * condition to leave the original address (Yamasoft, http://www.yamasoft.com) and this header.
 *
 * @link http://www.yamasoft.com
 */
class Image_Header
{
    public $m_n_left;
    public $m_n_top;
    public $m_n_width;
    public $m_n_height;
    public $m_b_local_clr;
    public $m_b_interlace;
    public $m_b_sorted;
    public $m_n_table_size;
    /**
     * @var \Mpdf\Gif\ColorTable
     */
    public $m_color_table;
    public function __construct()
    {
        unset($this->m_n_left);
        unset($this->m_n_top);
        unset($this->m_n_width);
        unset($this->m_n_height);
        unset($this->m_b_local_clr);
        unset($this->m_b_interlace);
        unset($this->m_b_sorted);
        unset($this->m_n_table_size);
        unset($this->m_color_table);
    }
    public function load($lp_data, &$hdr_len)
    {
        $hdr_len = 0;
        $this->m_n_left = $this->w2i(substr($lp_data, 0, 2));
        $this->m_n_top = $this->w2i(substr($lp_data, 2, 2));
        $this->m_n_width = $this->w2i(substr($lp_data, 4, 2));
        $this->m_n_height = $this->w2i(substr($lp_data, 6, 2));
        if (!$this->m_n_width || !$this->m_n_height) {
            return false;
        }
        $b = ord($lp_data[8]);
        $this->m_b_local_clr = $b & 0x80 ? true : false;
        $this->m_b_interlace = $b & 0x40 ? true : false;
        $this->m_b_sorted = $b & 0x20 ? true : false;
        $this->m_n_table_size = 2 << ($b & 0x7);
        $hdr_len = 9;
        if ($this->m_b_local_clr) {
            $this->m_color_table = new Color_Table();
            if (!$this->m_color_table->load(substr($lp_data, $hdr_len), $this->m_n_table_size)) {
                return false;
            }
            $hdr_len += 3 * $this->m_n_table_size;
        }
        return true;
    }
    public function w2i($str)
    {
        return ord(substr($str, 0, 1)) + (ord(substr($str, 1, 1)) << 8);
    }
}