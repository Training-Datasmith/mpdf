<?php

namespace Mpdf\Tag;

use Mpdf\Mpdf;
use Mpdf\Utils\Utf_String;
abstract class Inline_Tag extends Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        $tag = $this->get_tag_name();
        /* -- ANNOTATIONS -- */
        if ($this->mpdf->title2annots && isset($attr['TITLE'])) {
            $objattr = [];
            $objattr['margin_top'] = 0;
            $objattr['margin_bottom'] = 0;
            $objattr['margin_left'] = 0;
            $objattr['margin_right'] = 0;
            $objattr['width'] = 0;
            $objattr['height'] = 0;
            $objattr['border_top']['w'] = 0;
            $objattr['border_bottom']['w'] = 0;
            $objattr['border_left']['w'] = 0;
            $objattr['border_right']['w'] = 0;
            $objattr['CONTENT'] = $attr['TITLE'];
            $objattr['type'] = 'annot';
            $objattr['POS-X'] = 0;
            $objattr['POS-Y'] = 0;
            $objattr['ICON'] = 'Comment';
            $objattr['AUTHOR'] = '';
            $objattr['SUBJECT'] = '';
            $objattr['OPACITY'] = $this->mpdf->annot_opacity;
            $objattr['COLOR'] = $this->color_converter->convert('yellow', $this->mpdf->pdfa_xwarnings);
            $annot = Mpdf::OBJECT_IDENTIFIER . "type=annot,objattr=" . serialize($objattr) . Mpdf::OBJECT_IDENTIFIER;
        }
        /* -- END ANNOTATIONS -- */
        // mPDF 5.7.3 Inline tags
        if (!isset($this->mpdf->inline_properties[$tag])) {
            $this->mpdf->inline_properties[$tag] = [$this->mpdf->save_inline_properties()];
        } else {
            $this->mpdf->inline_properties[$tag][] = $this->mpdf->save_inline_properties();
        }
        if (isset($annot)) {
            // *ANNOTATIONS*
            if (!isset($this->mpdf->inline_annots[$tag])) {
                $this->mpdf->inline_annots[$tag] = [];
            }
            // *ANNOTATIONS*
            $this->mpdf->inline_annots[$tag][] = $annot;
        }
        // *ANNOTATIONS*
        $properties = $this->css_manager->merge_css('INLINE', $tag, $attr);
        if (!empty($properties)) {
            $this->mpdf->set_css($properties, 'INLINE');
        }
        // mPDF 6 Bidirectional formatting for inline elements
        $bdf = false;
        $bdf2 = '';
        $popd = '';
        // Get current direction
        $currdir = 'ltr';
        if (isset($this->mpdf->blk[$this->mpdf->blklvl]['direction'])) {
            $currdir = $this->mpdf->blk[$this->mpdf->blklvl]['direction'];
        }
        if ($this->mpdf->table_level && isset($this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['direction']) && $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['direction'] === 'rtl') {
            $currdir = 'rtl';
        }
        if (isset($attr['DIR']) && $attr['DIR'] != '') {
            $currdir = strtolower($attr['DIR']);
        }
        if (isset($properties['DIRECTION'])) {
            $currdir = strtolower($properties['DIRECTION']);
        }
        // mPDF 6 bidi
        // cf. http://www.w3.org/TR/css3-writing-modes/#unicode-bidi
        if ($tag === 'BDO') {
            if (isset($attr['DIR']) && strtolower($attr['DIR']) === 'rtl') {
                $bdf = 0x202e;
                $popd = 'RLOPDF';
            } elseif (isset($attr['DIR']) && strtolower($attr['DIR']) === 'ltr') {
                $bdf = 0x202d;
                $popd = 'LROPDF';
            }
            // U+202D LRO
        } elseif ($tag === 'BDI') {
            if (isset($attr['DIR']) && strtolower($attr['DIR']) === 'rtl') {
                $bdf = 0x2067;
                $popd = 'RLIPDI';
            } elseif (isset($attr['DIR']) && strtolower($attr['DIR']) === 'ltr') {
                $bdf = 0x2066;
                $popd = 'LRIPDI';
            } else {
                $bdf = 0x2068;
                $popd = 'FSIPDI';
            }
            // U+2068 FSI
        } elseif (isset($properties['UNICODE-BIDI']) && strtolower($properties['UNICODE-BIDI']) === 'bidi-override') {
            if ($currdir === 'rtl') {
                $bdf = 0x202e;
                $popd = 'RLOPDF';
            } else {
                $bdf = 0x202d;
                $popd = 'LROPDF';
            }
            // U+202D LRO
        } elseif (isset($properties['UNICODE-BIDI']) && strtolower($properties['UNICODE-BIDI']) === 'embed') {
            if ($currdir === 'rtl') {
                $bdf = 0x202b;
                $popd = 'RLEPDF';
            } else {
                $bdf = 0x202a;
                $popd = 'LREPDF';
            }
            // U+202A LRE
        } elseif (isset($properties['UNICODE-BIDI']) && strtolower($properties['UNICODE-BIDI']) === 'isolate') {
            if ($currdir === 'rtl') {
                $bdf = 0x2067;
                $popd = 'RLIPDI';
            } else {
                $bdf = 0x2066;
                $popd = 'LRIPDI';
            }
            // U+2066 LRI
        } elseif (isset($properties['UNICODE-BIDI']) && strtolower($properties['UNICODE-BIDI']) === 'isolate-override') {
            if ($currdir === 'rtl') {
                $bdf = 0x2067;
                $bdf2 = 0x202e;
                $popd = 'RLIRLOPDFPDI';
            } else {
                $bdf = 0x2066;
                $bdf2 = 0x202d;
                $popd = 'LRILROPDFPDI';
            }
            // U+2066 LRI  // U+202D LRO
        } elseif (isset($properties['UNICODE-BIDI']) && strtolower($properties['UNICODE-BIDI']) === 'plaintext') {
            $bdf = 0x2068;
            $popd = 'FSIPDI';
            // U+2068 FSI
        } else {
            if (isset($attr['DIR']) && strtolower($attr['DIR']) === 'rtl') {
                $bdf = 0x202b;
                $popd = 'RLEPDF';
            } elseif (isset($attr['DIR']) && strtolower($attr['DIR']) === 'ltr') {
                $bdf = 0x202a;
                $popd = 'LREPDF';
            }
            // U+202A LRE
        }
        if ($bdf) {
            // mPDF 5.7.3 Inline tags
            if (!isset($this->mpdf->inline_bdf[$tag])) {
                $this->mpdf->inline_bdf[$tag] = [[$popd, $this->mpdf->inline_bd_fctr]];
            } else {
                $this->mpdf->inline_bdf[$tag][] = [$popd, $this->mpdf->inline_bd_fctr];
            }
            $this->mpdf->inline_bd_fctr++;
            if ($bdf2) {
                $bdf2 = Utf_String::code2utf($bdf);
            }
            $this->mpdf->ot_ldata = [];
            if ($this->mpdf->table_level) {
                $this->mpdf->_save_cell_text_buffer(Utf_String::code2utf($bdf) . $bdf2);
            } else {
                $this->mpdf->_save_text_buffer(Utf_String::code2utf($bdf) . $bdf2);
            }
            $this->mpdf->bi_directional = true;
        }
    }
    public function close(&$ahtml, &$ihtml)
    {
        $tag = $this->get_tag_name();
        $annot = false;
        // mPDF 6
        $bdf = false;
        // mPDF 6
        // mPDF 5.7.3 Inline tags
        if ($tag === 'PROGRESS' || $tag === 'METER') {
            if (!empty($this->mpdf->inline_properties[$tag])) {
                $this->mpdf->restore_inline_properties($this->mpdf->inline_properties[$tag]);
            }
            unset($this->mpdf->inline_properties[$tag]);
            if (!empty($this->mpdf->inline_annots[$tag])) {
                $annot = $this->mpdf->inline_annots[$tag];
            }
            // *ANNOTATIONS*
            unset($this->mpdf->inline_annots[$tag]);
            // *ANNOTATIONS*
        } else {
            if (isset($this->mpdf->inline_properties[$tag]) && count($this->mpdf->inline_properties[$tag])) {
                $tmp_props = array_pop($this->mpdf->inline_properties[$tag]);
                // mPDF 5.7.4
                $this->mpdf->restore_inline_properties($tmp_props);
            }
            if (isset($this->mpdf->inline_annots[$tag]) && count($this->mpdf->inline_annots[$tag])) {
                // *ANNOTATIONS*
                $annot = array_pop($this->mpdf->inline_annots[$tag]);
                // *ANNOTATIONS*
            }
            // *ANNOTATIONS*
            if (isset($this->mpdf->inline_bdf[$tag]) && count($this->mpdf->inline_bdf[$tag])) {
                // mPDF 6
                $bdfarr = array_pop($this->mpdf->inline_bdf[$tag]);
                $bdf = $bdfarr[0];
            }
        }
        /* -- ANNOTATIONS -- */
        if ($annot) {
            // mPDF 6
            if ($this->mpdf->table_level) {
                // *TABLES*
                $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['textbuffer'][] = [$annot];
                // *TABLES*
            } else {
                // *TABLES*
                $this->mpdf->textbuffer[] = [$annot];
            }
            // *TABLES*
        }
        /* -- END ANNOTATIONS -- */
        // mPDF 6 bidi
        // mPDF 6 Bidirectional formatting for inline elements
        if ($bdf) {
            $popf = $this->mpdf->_set_bidi_codes('end', $bdf);
            $this->mpdf->ot_ldata = [];
            if ($this->mpdf->table_level) {
                $this->mpdf->_save_cell_text_buffer($popf);
            } else {
                $this->mpdf->_save_text_buffer($popf);
            }
        }
    }
}