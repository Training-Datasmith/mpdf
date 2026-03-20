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
class Gif
{
    public $m_gfh;
    public $m_lp_data;
    public $m_img;
    public $m_b_loaded;
    public function __construct()
    {
        $this->m_gfh = new File_Header();
        $this->m_img = new Image();
        $this->m_lp_data = '';
        $this->m_b_loaded = false;
    }
    public function clear_data()
    {
        $this->m_lp_data = '';
        unset($this->m_img->m_data);
        unset($this->m_img->m_lzw->Next);
        unset($this->m_img->m_lzw->Vals);
        unset($this->m_img->m_lzw->Stack);
        unset($this->m_img->m_lzw->Buf);
    }
    public function load_file(&$data, $i_index)
    {
        if ($i_index < 0) {
            return false;
        }
        $this->m_lp_data = $data;
        // GET FILE HEADER
        $len = 0;
        if (!$this->m_gfh->load($this->m_lp_data, $len)) {
            return false;
        }
        $this->m_lp_data = substr($this->m_lp_data, $len);
        do {
            $img_len = 0;
            if (!$this->m_img->load($this->m_lp_data, $img_len)) {
                return false;
            }
            $this->m_lp_data = substr($this->m_lp_data, $img_len);
        } while ($i_index-- > 0);
        $this->m_b_loaded = true;
        return true;
    }
}