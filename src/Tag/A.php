<?php

namespace Mpdf\Tag;

use Mpdf\Mpdf;
class A extends Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        if (isset($attr['NAME']) && $attr['NAME'] != '') {
            $e = '';
            /* -- BOOKMARKS -- */
            if ($this->mpdf->anchor2Bookmark) {
                $objattr = [];
                $objattr['CONTENT'] = htmlspecialchars_decode($attr['NAME'], ENT_QUOTES);
                $objattr['type'] = 'bookmark';
                if (!empty($attr['LEVEL'])) {
                    $objattr['bklevel'] = $attr['LEVEL'];
                } else {
                    $objattr['bklevel'] = 0;
                }
                $e = Mpdf::OBJECT_IDENTIFIER . "type=bookmark,objattr=" . serialize($objattr) . Mpdf::OBJECT_IDENTIFIER;
            }
            /* -- END BOOKMARKS -- */
            if ($this->mpdf->table_level) {
                // *TABLES*
                $this->mpdf->_save_cell_text_buffer($e, '', $attr['NAME']);
                // *TABLES*
            } else {
                // *TABLES*
                $this->mpdf->_save_text_buffer($e, '', $attr['NAME']);
                //an internal link (adds a space for recognition)
            }
            // *TABLES*
        }
        if (isset($attr['HREF'])) {
            $this->mpdf->inline_properties['A'] = $this->mpdf->save_inline_properties();
            $properties = $this->css_manager->merge_css('INLINE', 'A', $attr);
            if (!empty($properties)) {
                $this->mpdf->set_css($properties, 'INLINE');
            }
            $this->mpdf->HREF = $attr['HREF'];
            // mPDF 5.7.4 URLs
        }
    }
    public function close(&$ahtml, &$ihtml)
    {
        $this->mpdf->HREF = '';
        if (isset($this->mpdf->inline_properties['A'])) {
            $this->mpdf->restore_inline_properties($this->mpdf->inline_properties['A']);
        }
        unset($this->mpdf->inline_properties['A']);
    }
}