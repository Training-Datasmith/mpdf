<?php

namespace Mpdf\Tag;

class Form_Feed extends Tag
{
    public $toc_id;
    public function open($attr, &$ahtml, &$ihtml)
    {
        $tag = $this->get_tag_name();
        if (isset($attr['SHEET-SIZE'])) {
            // Convert to same types as accepted in initial mPDF() A4, A4-L, or array(w,h)
            $prop = preg_split('/\s+/', trim($attr['SHEET-SIZE']));
            if (count($prop) == 2) {
                $newformat = [$this->size_converter->convert($prop[0]), $this->size_converter->convert($prop[1])];
            } else {
                $newformat = $attr['SHEET-SIZE'];
            }
        } else {
            $newformat = '';
        }
        $save_blklvl = $this->mpdf->blklvl;
        $save_blk = $this->mpdf->blk;
        $save_silp = $this->mpdf->save_inline_properties();
        $save_ilp = $this->mpdf->inline_properties;
        $save_bflp = $this->mpdf->inline_bdf;
        $save_bflpc = $this->mpdf->inline_bd_fctr;
        // mPDF 6
        $mgr = $mgl = $mgt = $mgb = $mgh = $mgf = '';
        if (isset($attr['MARGIN-RIGHT'])) {
            $mgr = $this->size_converter->convert($attr['MARGIN-RIGHT'], $this->mpdf->w, $this->mpdf->font_size, false);
        }
        if (isset($attr['MARGIN-LEFT'])) {
            $mgl = $this->size_converter->convert($attr['MARGIN-LEFT'], $this->mpdf->w, $this->mpdf->font_size, false);
        }
        if (isset($attr['MARGIN-TOP'])) {
            $mgt = $this->size_converter->convert($attr['MARGIN-TOP'], $this->mpdf->w, $this->mpdf->font_size, false);
        }
        if (isset($attr['MARGIN-BOTTOM'])) {
            $mgb = $this->size_converter->convert($attr['MARGIN-BOTTOM'], $this->mpdf->w, $this->mpdf->font_size, false);
        }
        if (isset($attr['MARGIN-HEADER'])) {
            $mgh = $this->size_converter->convert($attr['MARGIN-HEADER'], $this->mpdf->w, $this->mpdf->font_size, false);
        }
        if (isset($attr['MARGIN-FOOTER'])) {
            $mgf = $this->size_converter->convert($attr['MARGIN-FOOTER'], $this->mpdf->w, $this->mpdf->font_size, false);
        }
        $ohname = $ehname = $ofname = $efname = '';
        if (isset($attr['ODD-HEADER-NAME'])) {
            $ohname = $attr['ODD-HEADER-NAME'];
        }
        if (isset($attr['EVEN-HEADER-NAME'])) {
            $ehname = $attr['EVEN-HEADER-NAME'];
        }
        if (isset($attr['ODD-FOOTER-NAME'])) {
            $ofname = $attr['ODD-FOOTER-NAME'];
        }
        if (isset($attr['EVEN-FOOTER-NAME'])) {
            $efname = $attr['EVEN-FOOTER-NAME'];
        }
        $ohvalue = $ehvalue = $ofvalue = $efvalue = 0;
        if (isset($attr['ODD-HEADER-VALUE']) && ($attr['ODD-HEADER-VALUE'] == '1' || strtoupper($attr['ODD-HEADER-VALUE']) === 'ON')) {
            $ohvalue = 1;
        } elseif (isset($attr['ODD-HEADER-VALUE']) && ($attr['ODD-HEADER-VALUE'] == '-1' || strtoupper($attr['ODD-HEADER-VALUE']) === 'OFF')) {
            $ohvalue = -1;
        }
        if (isset($attr['EVEN-HEADER-VALUE']) && ($attr['EVEN-HEADER-VALUE'] == '1' || strtoupper($attr['EVEN-HEADER-VALUE']) === 'ON')) {
            $ehvalue = 1;
        } elseif (isset($attr['EVEN-HEADER-VALUE']) && ($attr['EVEN-HEADER-VALUE'] == '-1' || strtoupper($attr['EVEN-HEADER-VALUE']) === 'OFF')) {
            $ehvalue = -1;
        }
        if (isset($attr['ODD-FOOTER-VALUE']) && ($attr['ODD-FOOTER-VALUE'] == '1' || strtoupper($attr['ODD-FOOTER-VALUE']) === 'ON')) {
            $ofvalue = 1;
        } elseif (isset($attr['ODD-FOOTER-VALUE']) && ($attr['ODD-FOOTER-VALUE'] == '-1' || strtoupper($attr['ODD-FOOTER-VALUE']) === 'OFF')) {
            $ofvalue = -1;
        }
        if (isset($attr['EVEN-FOOTER-VALUE']) && ($attr['EVEN-FOOTER-VALUE'] == '1' || strtoupper($attr['EVEN-FOOTER-VALUE']) === 'ON')) {
            $efvalue = 1;
        } elseif (isset($attr['EVEN-FOOTER-VALUE']) && ($attr['EVEN-FOOTER-VALUE'] == '-1' || strtoupper($attr['EVEN-FOOTER-VALUE']) === 'OFF')) {
            $efvalue = -1;
        }
        if (isset($attr['ORIENTATION']) && (strtoupper($attr['ORIENTATION']) === 'L' || strtoupper($attr['ORIENTATION']) === 'LANDSCAPE')) {
            $orient = 'L';
        } elseif (isset($attr['ORIENTATION']) && (strtoupper($attr['ORIENTATION']) === 'P' || strtoupper($attr['ORIENTATION']) === 'PORTRAIT')) {
            $orient = 'P';
        } else {
            $orient = $this->mpdf->cur_orientation;
        }
        $pagesel = '';
        if (!empty($attr['PAGE-SELECTOR'])) {
            $pagesel = $attr['PAGE-SELECTOR'];
        }
        // mPDF 6 pagebreaktype
        $pagebreaktype = $this->mpdf->default_pagebreak_type;
        if ($tag === 'FORMFEED') {
            $pagebreaktype = 'slice';
        }
        // can be overridden by PAGE-BREAK-TYPE
        $startpage = $this->mpdf->page;
        if (isset($attr['PAGE-BREAK-TYPE'])) {
            if (strtolower($attr['PAGE-BREAK-TYPE']) === 'cloneall' || strtolower($attr['PAGE-BREAK-TYPE']) === 'clonebycss' || strtolower($attr['PAGE-BREAK-TYPE']) === 'slice') {
                $pagebreaktype = strtolower($attr['PAGE-BREAK-TYPE']);
            }
        }
        if ($tag === 'TOCPAGEBREAK') {
            $pagebreaktype = 'cloneall';
        } elseif ($this->mpdf->col_active) {
            $pagebreaktype = 'cloneall';
        } elseif ($mgr !== '' || $mgl !== '' || $mgt !== '' || $mgb !== '' || $mgh !== '' || $mgf !== '' || $ohname !== '' || $ehname !== '' || $ofname !== '' || $efname !== '' || $ohvalue || $ehvalue || $ofvalue || $efvalue || $orient != $this->mpdf->cur_orientation || $newformat || $pagesel) {
            $pagebreaktype = 'cloneall';
        }
        // mPDF 6 pagebreaktype
        $this->mpdf->_pre_forced_pagebreak($pagebreaktype);
        $this->mpdf->ignorefollowingspaces = true;
        $resetpagenum = '';
        $pagenumstyle = '';
        $suppress = '';
        if (isset($attr['RESETPAGENUM'])) {
            $resetpagenum = $attr['RESETPAGENUM'];
        }
        if (isset($attr['PAGENUMSTYLE'])) {
            $pagenumstyle = $attr['PAGENUMSTYLE'];
        }
        if (isset($attr['SUPPRESS'])) {
            $suppress = $attr['SUPPRESS'];
        }
        $type = '';
        if ($tag === 'TOCPAGEBREAK') {
            $type = 'NEXT-ODD';
        } elseif (isset($attr['TYPE'])) {
            $type = strtoupper($attr['TYPE']);
        }
        if ($type === 'E' || $type === 'EVEN') {
            $this->mpdf->add_page($orient, 'E', $resetpagenum, $pagenumstyle, $suppress, $mgl, $mgr, $mgt, $mgb, $mgh, $mgf, $ohname, $ehname, $ofname, $efname, $ohvalue, $ehvalue, $ofvalue, $efvalue, $pagesel, $newformat);
        } elseif ($type === 'O' || $type === 'ODD') {
            $this->mpdf->add_page($orient, 'O', $resetpagenum, $pagenumstyle, $suppress, $mgl, $mgr, $mgt, $mgb, $mgh, $mgf, $ohname, $ehname, $ofname, $efname, $ohvalue, $ehvalue, $ofvalue, $efvalue, $pagesel, $newformat);
        } elseif ($type === 'NEXT-ODD') {
            $this->mpdf->add_page($orient, 'NEXT-ODD', $resetpagenum, $pagenumstyle, $suppress, $mgl, $mgr, $mgt, $mgb, $mgh, $mgf, $ohname, $ehname, $ofname, $efname, $ohvalue, $ehvalue, $ofvalue, $efvalue, $pagesel, $newformat);
        } elseif ($type === 'NEXT-EVEN') {
            $this->mpdf->add_page($orient, 'NEXT-EVEN', $resetpagenum, $pagenumstyle, $suppress, $mgl, $mgr, $mgt, $mgb, $mgh, $mgf, $ohname, $ehname, $ofname, $efname, $ohvalue, $ehvalue, $ofvalue, $efvalue, $pagesel, $newformat);
        } else {
            $this->mpdf->add_page($orient, '', $resetpagenum, $pagenumstyle, $suppress, $mgl, $mgr, $mgt, $mgb, $mgh, $mgf, $ohname, $ehname, $ofname, $efname, $ohvalue, $ehvalue, $ofvalue, $efvalue, $pagesel, $newformat);
        }
        /* -- TOC -- */
        if ($tag === 'TOCPAGEBREAK') {
            if ($this->toc_id) {
                $this->table_of_contents->m_TOC[$this->toc_id]['TOCmark'] = $this->mpdf->page;
            } else {
                $this->table_of_contents->to_cmark = $this->mpdf->page;
            }
        }
        /* -- END TOC -- */
        // mPDF 6 pagebreaktype
        $this->mpdf->_post_forced_pagebreak($pagebreaktype, $startpage, $save_blk, $save_blklvl);
        $this->mpdf->inline_properties = $save_ilp;
        $this->mpdf->inline_bdf = $save_bflp;
        $this->mpdf->inline_bd_fctr = $save_bflpc;
        // mPDF 6
        $this->mpdf->restore_inline_properties($save_silp);
    }
    public function close(&$ahtml, &$ihtml)
    {
    }
}