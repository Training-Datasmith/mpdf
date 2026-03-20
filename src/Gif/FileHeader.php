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
class File_Header
{
    public $m_lp_ver;
    public $m_n_width;
    public $m_n_height;
    public $m_b_global_clr;
    public $m_n_color_res;
    public $m_b_sorted;
    public $m_n_table_size;
    public $m_n_bg_color;
    public $m_n_pixel_ratio;
    /**
     * @var \Mpdf\Gif\ColorTable
     */
    public $m_color_table;
    public function __construct()
    {
        unset($this->m_lp_ver);
        unset($this->m_n_width);
        unset($this->m_n_height);
        unset($this->m_b_global_clr);
        unset($this->m_n_color_res);
        unset($this->m_b_sorted);
        unset($this->m_n_table_size);
        unset($this->m_n_bg_color);
        unset($this->m_n_pixel_ratio);
        unset($this->m_color_table);
    }
    public function load($lp_data, &$hdr_len)
    {
        $hdr_len = 0;
        $this->m_lp_ver = substr($lp_data, 0, 6);
        if ($this->m_lp_ver != 'GIF87a' && $this->m_lp_ver != 'GIF89a') {
            return false;
        }
        $this->m_n_width = $this->w2i(substr($lp_data, 6, 2));
        $this->m_n_height = $this->w2i(substr($lp_data, 8, 2));
        if (!$this->m_n_width || !$this->m_n_height) {
            return false;
        }
        $b = ord(substr($lp_data, 10, 1));
        $this->m_b_global_clr = $b & 0x80 ? true : false;
        $this->m_n_color_res = ($b & 0x70) >> 4;
        $this->m_b_sorted = $b & 0x8 ? true : false;
        $this->m_n_table_size = 2 << ($b & 0x7);
        $this->m_n_bg_color = ord(substr($lp_data, 11, 1));
        $this->m_n_pixel_ratio = ord(substr($lp_data, 12, 1));
        $hdr_len = 13;
        if ($this->m_b_global_clr) {
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