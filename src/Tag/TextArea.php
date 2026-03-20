<?php

namespace Mpdf\Tag;

use Mpdf\Mpdf;
class Text_Area extends Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
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
        if (isset($attr['DISABLED'])) {
            $objattr['disabled'] = true;
        }
        if (isset($attr['READONLY'])) {
            $objattr['readonly'] = true;
        }
        if (isset($attr['REQUIRED'])) {
            $objattr['required'] = true;
        }
        if (isset($attr['SPELLCHECK']) && strtolower($attr['SPELLCHECK']) === 'true') {
            $objattr['spellcheck'] = true;
        }
        if (isset($attr['TITLE'])) {
            $objattr['title'] = $attr['TITLE'];
            if ($this->mpdf->only_core_fonts) {
                $objattr['title'] = mb_convert_encoding($objattr['title'], $this->mpdf->mb_enc, 'UTF-8');
            }
        }
        if ($this->mpdf->use_active_forms) {
            if (isset($attr['NAME'])) {
                $objattr['fieldname'] = $attr['NAME'];
            }
            $this->form->form_element_spacing['textarea']['outer']['v'] = 0;
            $this->form->form_element_spacing['textarea']['inner']['v'] = 0;
            if (isset($attr['ONCALCULATE'])) {
                $objattr['onCalculate'] = $attr['ONCALCULATE'];
            } elseif (isset($attr['ONCHANGE'])) {
                $objattr['onCalculate'] = $attr['ONCHANGE'];
            }
            if (isset($attr['ONVALIDATE'])) {
                $objattr['onValidate'] = $attr['ONVALIDATE'];
            }
            if (isset($attr['ONKEYSTROKE'])) {
                $objattr['onKeystroke'] = $attr['ONKEYSTROKE'];
            }
            if (isset($attr['ONFORMAT'])) {
                $objattr['onFormat'] = $attr['ONFORMAT'];
            }
        }
        $this->mpdf->inline_properties['TEXTAREA'] = $this->mpdf->save_inline_properties();
        $properties = $this->css_manager->merge_css('', 'TEXTAREA', $attr);
        if (isset($properties['FONT-FAMILY'])) {
            $this->mpdf->set_font($properties['FONT-FAMILY'], '', 0, false);
        }
        if (isset($properties['FONT-SIZE']) && $properties['FONT-SIZE'] !== 'auto') {
            $mmsize = $this->size_converter->convert($properties['FONT-SIZE'], $this->mpdf->default_font_size / Mpdf::SCALE);
            $this->mpdf->set_font_size($mmsize * Mpdf::SCALE, false);
        }
        if (isset($properties['COLOR'])) {
            $objattr['color'] = $this->color_converter->convert($properties['COLOR'], $this->mpdf->pdfa_xwarnings);
        }
        $objattr['fontfamily'] = $this->mpdf->font_family;
        $objattr['fontsize'] = $this->mpdf->font_size_pt;
        if ($this->mpdf->use_active_forms) {
            if (isset($properties['TEXT-ALIGN'])) {
                $objattr['text_align'] = $this->get_align($properties['TEXT-ALIGN']);
            } elseif (isset($attr['ALIGN'])) {
                $objattr['text_align'] = $this->get_align($attr['ALIGN']);
            }
            if (isset($properties['OVERFLOW']) && strtolower($properties['OVERFLOW']) === 'hidden') {
                $objattr['donotscroll'] = true;
            }
            if (isset($properties['BORDER-TOP-COLOR'])) {
                $objattr['border-col'] = $this->color_converter->convert($properties['BORDER-TOP-COLOR'], $this->mpdf->pdfa_xwarnings);
            }
            if (isset($properties['BACKGROUND-COLOR'])) {
                $objattr['background-col'] = $this->color_converter->convert($properties['BACKGROUND-COLOR'], $this->mpdf->pdfa_xwarnings);
            }
        }
        $this->mpdf->set_line_height('', $this->form->textarea_lineheight);
        $w = 0;
        $h = 0;
        if (isset($properties['WIDTH'])) {
            $w = $this->size_converter->convert($properties['WIDTH'], $this->mpdf->blk[$this->mpdf->blklvl]['inner_width'], $this->mpdf->font_size, false);
        }
        if (isset($properties['HEIGHT'])) {
            $h = $this->size_converter->convert($properties['HEIGHT'], $this->mpdf->blk[$this->mpdf->blklvl]['inner_width'], $this->mpdf->font_size, false);
        }
        if (isset($properties['VERTICAL-ALIGN'])) {
            $objattr['vertical-align'] = $this->get_align($properties['VERTICAL-ALIGN']);
        }
        $colsize = 20;
        //HTML default value
        $rowsize = 2;
        //HTML default value
        if (isset($attr['COLS'])) {
            $colsize = (int) $attr['COLS'];
        }
        if (isset($attr['ROWS'])) {
            $rowsize = (int) $attr['ROWS'];
        }
        $charsize = $this->mpdf->get_char_width('w', false);
        if ($w) {
            $colsize = round(($w - $this->form->form_element_spacing['textarea']['outer']['h'] * 2 - $this->form->form_element_spacing['textarea']['inner']['h'] * 2) / $charsize);
        }
        if ($h) {
            $rowsize = round(($h - $this->form->form_element_spacing['textarea']['outer']['v'] * 2 - $this->form->form_element_spacing['textarea']['inner']['v'] * 2) / $this->mpdf->lineheight);
        }
        $objattr['type'] = 'textarea';
        $objattr['width'] = $colsize * $charsize + $this->form->form_element_spacing['textarea']['outer']['h'] * 2 + $this->form->form_element_spacing['textarea']['inner']['h'] * 2;
        $objattr['height'] = $rowsize * $this->mpdf->lineheight + $this->form->form_element_spacing['textarea']['outer']['v'] * 2 + $this->form->form_element_spacing['textarea']['inner']['v'] * 2;
        $objattr['rows'] = $rowsize;
        $objattr['cols'] = $colsize;
        if ($properties['FONT-SIZE'] === 'auto' && $this->mpdf->use_active_forms) {
            $objattr['use_auto_fontsize'] = true;
        }
        $this->mpdf->specialcontent = serialize($objattr);
        if ($this->mpdf->table_level) {
            // *TABLES*
            $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['s'] += $objattr['width'];
            // *TABLES*
        }
        // *TABLES*
    }
    public function close(&$ahtml, &$ihtml)
    {
        $this->mpdf->ignorefollowingspaces = false;
        $this->mpdf->specialcontent = '';
        if ($this->mpdf->inline_properties['TEXTAREA']) {
            $this->mpdf->restore_inline_properties($this->mpdf->inline_properties['TEXTAREA']);
        }
        unset($this->mpdf->inline_properties['TEXTAREA']);
    }
}