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
class Color_Table
{
    public $m_n_colors;
    public $m_ar_colors;
    public function __construct()
    {
        unset($this->m_n_colors);
        unset($this->m_ar_colors);
    }
    public function load($lp_data, $num)
    {
        $this->m_n_colors = 0;
        $this->m_ar_colors = [];
        for ($i = 0; $i < $num; $i++) {
            $rgb = substr($lp_data, $i * 3, 3);
            if (strlen($rgb) < 3) {
                return false;
            }
            $this->m_ar_colors[] = (ord($rgb[2]) << 16) + (ord($rgb[1]) << 8) + ord($rgb[0]);
            $this->m_n_colors++;
        }
        return true;
    }
    public function to_string()
    {
        $ret = '';
        for ($i = 0; $i < $this->m_n_colors; $i++) {
            $ret .= chr($this->m_ar_colors[$i] & 0xff) . chr(($this->m_ar_colors[$i] & 0xff00) >> 8) . chr(($this->m_ar_colors[$i] & 0xff0000) >> 16);
            // B
        }
        return $ret;
    }
    public function color_index($rgb)
    {
        $rgb = intval($rgb) & 0xffffff;
        $r1 = $rgb & 0xff;
        $g1 = ($rgb & 0xff00) >> 8;
        $b1 = ($rgb & 0xff0000) >> 16;
        $idx = -1;
        for ($i = 0; $i < $this->m_n_colors; $i++) {
            $r2 = $this->m_ar_colors[$i] & 0xff;
            $g2 = ($this->m_ar_colors[$i] & 0xff00) >> 8;
            $b2 = ($this->m_ar_colors[$i] & 0xff0000) >> 16;
            $d = abs($r2 - $r1) + abs($g2 - $g1) + abs($b2 - $b1);
            if ($idx == -1 || $d < $dif) {
                $idx = $i;
                $dif = $d;
            }
        }
        return $idx;
    }
}