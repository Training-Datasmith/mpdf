<?php

namespace Mpdf\Tag;

use Mpdf\Mpdf;
class Dot_Tab extends Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        $objattr = [];
        $objattr['type'] = 'dottab';
        $dots = str_repeat('.', 3) . '  ';
        // minimum number of dots
        $objattr['width'] = $this->mpdf->get_string_width($dots);
        $objattr['margin_top'] = 0;
        $objattr['margin_bottom'] = 0;
        $objattr['margin_left'] = 0;
        $objattr['margin_right'] = 0;
        $objattr['height'] = 0;
        $objattr['colorarray'] = $this->mpdf->colorarray;
        $objattr['border_top']['w'] = 0;
        $objattr['border_bottom']['w'] = 0;
        $objattr['border_left']['w'] = 0;
        $objattr['border_right']['w'] = 0;
        $objattr['vertical_align'] = 'BS';
        // mPDF 6 DOTTAB
        $properties = $this->css_manager->merge_css('INLINE', 'DOTTAB', $attr);
        if (isset($properties['OUTDENT'])) {
            $objattr['outdent'] = $this->size_converter->convert($properties['OUTDENT'], $this->mpdf->blk[$this->mpdf->blklvl]['inner_width'], $this->mpdf->font_size, false);
        } elseif (isset($attr['OUTDENT'])) {
            $objattr['outdent'] = $this->size_converter->convert($attr['OUTDENT'], $this->mpdf->blk[$this->mpdf->blklvl]['inner_width'], $this->mpdf->font_size, false);
        } else {
            $objattr['outdent'] = 0;
        }
        $objattr['fontfamily'] = $this->mpdf->font_family;
        $objattr['fontsize'] = $this->mpdf->font_size_pt;
        $e = Mpdf::OBJECT_IDENTIFIER . "type=dottab,objattr=" . serialize($objattr) . Mpdf::OBJECT_IDENTIFIER;
        /* -- TABLES -- */
        // Output it to buffers
        if ($this->mpdf->table_level) {
            if (!isset($this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['maxs'])) {
                $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['maxs'] = $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['s'];
            } elseif ($this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['maxs'] < $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['s']) {
                $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['maxs'] = $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['s'];
            }
            $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['s'] = 0;
            // reset
            $this->mpdf->_save_cell_text_buffer($e);
        } else {
            /* -- END TABLES -- */
            $this->mpdf->_save_text_buffer($e);
        }
        // *TABLES*
    }
    public function close(&$ahtml, &$ihtml)
    {
    }
}