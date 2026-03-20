<?php

namespace Mpdf\Tag;

use Mpdf\Mpdf;
class Select extends Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        $this->mpdf->lastoptionaltag = '';
        // Save current HTML specified optional endtag
        $this->mpdf->inline_properties['SELECT'] = $this->mpdf->save_inline_properties();
        $properties = $this->css_manager->merge_css('', 'SELECT', $attr);
        if (isset($properties['FONT-FAMILY'])) {
            $this->mpdf->set_font($properties['FONT-FAMILY'], $this->mpdf->font_style, 0, false);
        }
        if (isset($properties['FONT-SIZE'])) {
            $mmsize = $this->size_converter->convert($properties['FONT-SIZE'], $this->mpdf->default_font_size / Mpdf::SCALE);
            $this->mpdf->set_font_size($mmsize * Mpdf::SCALE, false);
        }
        if (isset($attr['SPELLCHECK']) && strtolower($attr['SPELLCHECK']) === 'true') {
            $this->mpdf->selectoption['SPELLCHECK'] = true;
        }
        if (isset($properties['COLOR'])) {
            $this->mpdf->selectoption['COLOR'] = $this->color_converter->convert($properties['COLOR'], $this->mpdf->pdfa_xwarnings);
        }
        $this->mpdf->specialcontent = 'type=select';
        if (isset($attr['DISABLED'])) {
            $this->mpdf->selectoption['DISABLED'] = $attr['DISABLED'];
        }
        if (isset($attr['READONLY'])) {
            $this->mpdf->selectoption['READONLY'] = $attr['READONLY'];
        }
        if (isset($attr['REQUIRED'])) {
            $this->mpdf->selectoption['REQUIRED'] = $attr['REQUIRED'];
        }
        if (isset($attr['EDITABLE'])) {
            $this->mpdf->selectoption['EDITABLE'] = $attr['EDITABLE'];
        }
        if (isset($attr['TITLE'])) {
            $this->mpdf->selectoption['TITLE'] = $attr['TITLE'];
        }
        if (isset($attr['MULTIPLE'])) {
            $this->mpdf->selectoption['MULTIPLE'] = $attr['MULTIPLE'];
        }
        if (isset($attr['SIZE']) && $attr['SIZE'] > 1) {
            $this->mpdf->selectoption['SIZE'] = $attr['SIZE'];
        }
        if ($this->mpdf->use_active_forms) {
            if (isset($attr['NAME'])) {
                $this->mpdf->selectoption['NAME'] = $attr['NAME'];
            }
            if (isset($attr['ONCHANGE'])) {
                $this->mpdf->selectoption['ONCHANGE'] = $attr['ONCHANGE'];
            }
        }
    }
    public function close(&$ahtml, &$ihtml)
    {
        $this->mpdf->ignorefollowingspaces = false;
        $this->mpdf->lastoptionaltag = '';
        $texto = '';
        $ot_ldata = false;
        if (isset($this->mpdf->selectoption['SELECTED'])) {
            $texto = $this->mpdf->selectoption['SELECTED'];
        }
        if (isset($this->mpdf->selectoption['SELECTED-OTLDATA'])) {
            $ot_ldata = $this->mpdf->selectoption['SELECTED-OTLDATA'];
        }
        if ($this->mpdf->use_active_forms) {
            $w = $this->mpdf->selectoption['MAXWIDTH'];
        } else {
            $w = $this->mpdf->get_string_width($texto, true, $ot_ldata);
        }
        if ($w == 0) {
            $w = 5;
        }
        $objattr['type'] = 'select';
        $objattr['text'] = $texto;
        $objattr['OTLdata'] = $ot_ldata;
        if (isset($this->mpdf->selectoption['NAME'])) {
            $objattr['fieldname'] = $this->mpdf->selectoption['NAME'];
        }
        if (isset($this->mpdf->selectoption['READONLY'])) {
            $objattr['readonly'] = true;
        }
        if (isset($this->mpdf->selectoption['REQUIRED'])) {
            $objattr['required'] = true;
        }
        if (isset($this->mpdf->selectoption['SPELLCHECK'])) {
            $objattr['spellcheck'] = true;
        }
        if (isset($this->mpdf->selectoption['EDITABLE'])) {
            $objattr['editable'] = true;
        }
        if (isset($this->mpdf->selectoption['ONCHANGE'])) {
            $objattr['onChange'] = $this->mpdf->selectoption['ONCHANGE'];
        }
        if (isset($this->mpdf->selectoption['ITEMS'])) {
            $objattr['items'] = $this->mpdf->selectoption['ITEMS'];
        }
        if (isset($this->mpdf->selectoption['MULTIPLE'])) {
            $objattr['multiple'] = $this->mpdf->selectoption['MULTIPLE'];
        }
        if (isset($this->mpdf->selectoption['DISABLED'])) {
            $objattr['disabled'] = $this->mpdf->selectoption['DISABLED'];
        }
        if (isset($this->mpdf->selectoption['TITLE'])) {
            $objattr['title'] = $this->mpdf->selectoption['TITLE'];
        }
        if (isset($this->mpdf->selectoption['COLOR'])) {
            $objattr['color'] = $this->mpdf->selectoption['COLOR'];
        }
        if (isset($this->mpdf->selectoption['SIZE'])) {
            $objattr['size'] = $this->mpdf->selectoption['SIZE'];
        }
        $rows = 1;
        if (isset($objattr['size']) && $objattr['size'] > 1) {
            $rows = $objattr['size'];
        }
        $objattr['fontfamily'] = $this->mpdf->font_family;
        $objattr['fontsize'] = $this->mpdf->font_size_pt;
        $objattr['width'] = $w + $this->form->form_element_spacing['select']['outer']['h'] * 2 + $this->form->form_element_spacing['select']['inner']['h'] * 2 + $this->mpdf->font_size * 1.4;
        $objattr['height'] = $this->mpdf->font_size * $rows + $this->form->form_element_spacing['select']['outer']['v'] * 2 + $this->form->form_element_spacing['select']['inner']['v'] * 2;
        $e = Mpdf::OBJECT_IDENTIFIER . "type=select,objattr=" . serialize($objattr) . Mpdf::OBJECT_IDENTIFIER;
        // Output it to buffers
        if ($this->mpdf->table_level) {
            // *TABLES*
            $this->mpdf->_save_cell_text_buffer($e, $this->mpdf->HREF);
            $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['s'] += $objattr['width'];
            // *TABLES*
        } else {
            // *TABLES*
            $this->mpdf->_save_text_buffer($e, $this->mpdf->HREF);
        }
        // *TABLES*
        $this->mpdf->selectoption = [];
        $this->mpdf->specialcontent = '';
        if ($this->mpdf->inline_properties['SELECT']) {
            $this->mpdf->restore_inline_properties($this->mpdf->inline_properties['SELECT']);
        }
        unset($this->mpdf->inline_properties['SELECT']);
    }
}