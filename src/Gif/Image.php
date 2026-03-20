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
class Image
{
    public $m_disp;
    public $m_b_user;
    public $m_b_trans;
    public $m_n_delay;
    public $m_n_trans;
    public $m_lp_comm;
    public $m_gih;
    public $m_data;
    public $m_lzw;
    public function __construct()
    {
        unset($this->m_disp);
        unset($this->m_b_user);
        unset($this->m_b_trans);
        unset($this->m_n_delay);
        unset($this->m_n_trans);
        unset($this->m_lp_comm);
        unset($this->m_data);
        $this->m_gih = new Image_Header();
        $this->m_lzw = new Lzw();
    }
    public function load($data, &$dat_len)
    {
        $dat_len = 0;
        while (true) {
            $b = ord($data[0]);
            $data = substr($data, 1);
            $dat_len++;
            switch ($b) {
                case 0x21:
                    // Extension
                    $len = 0;
                    if (!$this->skip_ext($data, $len)) {
                        return false;
                    }
                    $dat_len += $len;
                    break;
                case 0x2c:
                    // Image
                    // LOAD HEADER & COLOR TABLE
                    $len = 0;
                    if (!$this->m_gih->load($data, $len)) {
                        return false;
                    }
                    $data = substr($data, $len);
                    $dat_len += $len;
                    // ALLOC BUFFER
                    $len = 0;
                    if (!$this->m_data = $this->m_lzw->de_compress($data, $len)) {
                        return false;
                    }
                    $data = substr($data, $len);
                    $dat_len += $len;
                    if ($this->m_gih->m_b_interlace) {
                        $this->de_interlace();
                    }
                    return true;
                case 0x3b:
                // EOF
                default:
                    return false;
            }
        }
        return false;
    }
    public function skip_ext(&$data, &$ext_len)
    {
        $ext_len = 0;
        $b = ord($data[0]);
        $data = substr($data, 1);
        $ext_len++;
        switch ($b) {
            case 0xf9:
                // Graphic Control
                $b = ord($data[1]);
                $this->m_disp = ($b & 0x1c) >> 2;
                $this->m_b_user = $b & 0x2 ? true : false;
                $this->m_b_trans = $b & 0x1 ? true : false;
                $this->m_n_delay = $this->w2i(substr($data, 2, 2));
                $this->m_n_trans = ord($data[4]);
                break;
            case 0xfe:
                // Comment
                $this->m_lp_comm = substr($data, 1, ord($data[0]));
                break;
            case 0x1:
                // Plain text
                break;
            case 0xff:
                // Application
                break;
        }
        // SKIP DEFAULT AS DEFS MAY CHANGE
        $b = ord($data[0]);
        $data = substr($data, 1);
        $ext_len++;
        while ($b > 0) {
            $data = substr($data, $b);
            $ext_len += $b;
            $b = ord($data[0]);
            $data = substr($data, 1);
            $ext_len++;
        }
        return true;
    }
    public function w2i($str)
    {
        return ord(substr($str, 0, 1)) + (ord(substr($str, 1, 1)) << 8);
    }
    public function de_interlace()
    {
        $data = $this->m_data;
        for ($i = 0; $i < 4; $i++) {
            switch ($i) {
                case 0:
                    $s = 8;
                    $y = 0;
                    break;
                case 1:
                    $s = 8;
                    $y = 4;
                    break;
                case 2:
                    $s = 4;
                    $y = 2;
                    break;
                case 3:
                    $s = 2;
                    $y = 1;
                    break;
            }
            for (; $y < $this->m_gih->m_n_height; $y += $s) {
                $lne = substr($this->m_data, 0, $this->m_gih->m_n_width);
                $this->m_data = substr($this->m_data, $this->m_gih->m_n_width);
                $data = substr($data, 0, $y * $this->m_gih->m_n_width) . $lne . substr($data, ($y + 1) * $this->m_gih->m_n_width);
            }
        }
        $this->m_data = $data;
    }
}