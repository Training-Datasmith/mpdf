<?php

namespace Mpdf\Tag;

use Mpdf\Mpdf;
use Mpdf\Utils\Numeric_String;
class Hr extends Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        // Added mPDF 3.0 Float DIV - CLEAR
        if (isset($attr['STYLE'])) {
            $properties = $this->css_manager->read_inline_css($attr['STYLE']);
            if (isset($properties['CLEAR'])) {
                $this->mpdf->clear_floats(strtoupper($properties['CLEAR']), $this->mpdf->blklvl);
            }
            // *CSS-FLOAT*
        }
        $this->mpdf->ignorefollowingspaces = true;
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
        $properties = $this->css_manager->merge_css('', 'HR', $attr);
        if (isset($properties['MARGIN-TOP'])) {
            $objattr['margin_top'] = $this->size_converter->convert($properties['MARGIN-TOP'], $this->mpdf->blk[$this->mpdf->blklvl]['inner_width'], $this->mpdf->font_size, false);
        }
        if (isset($properties['MARGIN-BOTTOM'])) {
            $objattr['margin_bottom'] = $this->size_converter->convert($properties['MARGIN-BOTTOM'], $this->mpdf->blk[$this->mpdf->blklvl]['inner_width'], $this->mpdf->font_size, false);
        }
        if (isset($properties['WIDTH'])) {
            $objattr['width'] = $this->size_converter->convert($properties['WIDTH'], $this->mpdf->blk[$this->mpdf->blklvl]['inner_width']);
        } elseif (isset($attr['WIDTH']) && $attr['WIDTH'] != '') {
            $objattr['width'] = $this->size_converter->convert($attr['WIDTH'], $this->mpdf->blk[$this->mpdf->blklvl]['inner_width']);
        }
        if (isset($properties['TEXT-ALIGN'])) {
            $objattr['align'] = $this->get_align($properties['TEXT-ALIGN']);
        } elseif (isset($attr['ALIGN']) && $attr['ALIGN'] != '') {
            $objattr['align'] = $this->get_align($attr['ALIGN']);
        }
        if (isset($properties['MARGIN-LEFT']) && strtolower($properties['MARGIN-LEFT']) === 'auto') {
            $objattr['align'] = 'R';
        }
        if (isset($properties['MARGIN-RIGHT']) && strtolower($properties['MARGIN-RIGHT']) === 'auto') {
            $objattr['align'] = 'L';
            if (isset($properties['MARGIN-RIGHT']) && strtolower($properties['MARGIN-RIGHT']) === 'auto' && isset($properties['MARGIN-LEFT']) && strtolower($properties['MARGIN-LEFT']) === 'auto') {
                $objattr['align'] = 'C';
            }
        }
        if (isset($properties['COLOR'])) {
            $objattr['color'] = $this->color_converter->convert($properties['COLOR'], $this->mpdf->pdfa_xwarnings);
        } elseif (isset($attr['COLOR']) && $attr['COLOR'] != '') {
            $objattr['color'] = $this->color_converter->convert($attr['COLOR'], $this->mpdf->pdfa_xwarnings);
        }
        if (isset($properties['HEIGHT'])) {
            $objattr['linewidth'] = $this->size_converter->convert($properties['HEIGHT'], $this->mpdf->blk[$this->mpdf->blklvl]['inner_width'], $this->mpdf->font_size, false);
        }
        /* -- TABLES -- */
        if ($this->mpdf->table_level) {
            $objattr['W-PERCENT'] = 100;
            if (isset($properties['WIDTH']) && Numeric_String::contains_percent_char($properties['WIDTH'])) {
                $properties['WIDTH'] = Numeric_String::remove_percent_char($properties['WIDTH']);
                // make "90%" become simply "90"
                $objattr['W-PERCENT'] = $properties['WIDTH'];
            }
            if (isset($attr['WIDTH']) && Numeric_String::contains_percent_char($attr['WIDTH'])) {
                $attr['WIDTH'] = Numeric_String::remove_percent_char($attr['WIDTH']);
                // make "90%" become simply "90"
                $objattr['W-PERCENT'] = $attr['WIDTH'];
            }
        }
        /* -- END TABLES -- */
        $objattr['type'] = 'hr';
        $objattr['height'] = $objattr['linewidth'] + $objattr['margin_top'] + $objattr['margin_bottom'];
        $e = Mpdf::OBJECT_IDENTIFIER . "type=image,objattr=" . serialize($objattr) . Mpdf::OBJECT_IDENTIFIER;
        /* -- TABLES -- */
        // Output it to buffers
        if ($this->mpdf->table_level) {
            if ($this->mpdf->cell) {
                if (!isset($this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['maxs'])) {
                    $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['maxs'] = $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['s'];
                } elseif ($this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['maxs'] < $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['s']) {
                    $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['maxs'] = $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['s'];
                }
                $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['s'] = 0;
                // reset
                $this->mpdf->_save_cell_text_buffer($e, $this->mpdf->HREF);
            }
        } else {
            /* -- END TABLES -- */
            $this->mpdf->_save_text_buffer($e, $this->mpdf->HREF);
        }
        // *TABLES*
    }
    public function close(&$ahtml, &$ihtml)
    {
    }
}