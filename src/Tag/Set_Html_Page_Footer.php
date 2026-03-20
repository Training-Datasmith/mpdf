<?php

namespace Mpdf\Tag;

class Set_Html_Page_Footer extends Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        $tag = $this->get_tag_name();
        $this->mpdf->ignorefollowingspaces = true;
        $pname = '_default';
        if (!empty($attr['NAME'])) {
            $pname = $attr['NAME'];
        } elseif ($tag === 'SETPAGEHEADER' || $tag === 'SETPAGEFOOTER') {
            $pname = '_nonhtmldefault';
        }
        // mPDF 6
        if (!empty($attr['PAGE'])) {
            // O|odd|even|E|ALL|[blank]
            $side = 'odd';
            if (strtoupper($attr['PAGE']) === 'O' || strtoupper($attr['PAGE']) === 'ODD') {
                $side = 'odd';
            } elseif (strtoupper($attr['PAGE']) === 'E' || strtoupper($attr['PAGE']) === 'EVEN') {
                $side = 'even';
            } elseif (strtoupper($attr['PAGE']) === 'ALL') {
                $side = 'both';
            }
        } else {
            $side = 'odd';
        }
        if (!empty($attr['VALUE'])) {
            // -1|1|on|off
            $set = 1;
            if ($attr['VALUE'] == '1' || strtoupper($attr['VALUE']) === 'ON') {
                $set = 1;
            } elseif ($attr['VALUE'] == '-1' || strtoupper($attr['VALUE']) === 'OFF') {
                $set = 0;
            }
        } else {
            $set = 1;
        }
        $write = 0;
        if (!empty($attr['SHOW-THIS-PAGE']) && ($tag === 'SETHTMLPAGEHEADER' || $tag === 'SETPAGEHEADER')) {
            $write = 1;
        }
        if ($side === 'odd' || $side === 'both') {
            if ($set && ($tag === 'SETHTMLPAGEHEADER' || $tag === 'SETPAGEHEADER')) {
                $this->mpdf->set_html_header($this->mpdf->page_htm_lheaders[$pname], 'O', $write);
            } elseif ($set && ($tag === 'SETHTMLPAGEFOOTER' || $tag === 'SETPAGEFOOTER')) {
                $this->mpdf->set_html_footer($this->mpdf->page_htm_lfooters[$pname], 'O');
            } elseif ($tag === 'SETHTMLPAGEHEADER' || $tag === 'SETPAGEHEADER') {
                $this->mpdf->set_html_header('', 'O');
            } else {
                $this->mpdf->set_html_footer('', 'O');
            }
        }
        if ($side === 'even' || $side === 'both') {
            if ($set && ($tag === 'SETHTMLPAGEHEADER' || $tag === 'SETPAGEHEADER')) {
                $this->mpdf->set_html_header($this->mpdf->page_htm_lheaders[$pname], 'E', $write);
            } elseif ($set && ($tag === 'SETHTMLPAGEFOOTER' || $tag === 'SETPAGEFOOTER')) {
                $this->mpdf->set_html_footer($this->mpdf->page_htm_lfooters[$pname], 'E');
            } elseif ($tag === 'SETHTMLPAGEHEADER' || $tag === 'SETPAGEHEADER') {
                $this->mpdf->set_html_header('', 'E');
            } else {
                $this->mpdf->set_html_footer('', 'E');
            }
        }
    }
    public function close(&$ahtml, &$ihtml)
    {
    }
}