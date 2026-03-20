<?php

namespace Mpdf\Tag;

class T_Head extends Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        $this->mpdf->lastoptionaltag = 'THEAD';
        // Save current HTML specified optional endtag
        $this->css_manager->tb_cs_slvl++;
        $this->mpdf->tablethead = 1;
        $this->mpdf->tabletfoot = 0;
        $properties = $this->css_manager->merge_css('TABLE', 'THEAD', $attr);
        if (isset($properties['FONT-WEIGHT'])) {
            $this->mpdf->thead_font_weight = '';
            if (strtoupper($properties['FONT-WEIGHT']) === 'BOLD') {
                $this->mpdf->thead_font_weight = 'B';
            }
        }
        if (isset($properties['FONT-STYLE'])) {
            $this->mpdf->thead_font_style = '';
            if (strtoupper($properties['FONT-STYLE']) === 'ITALIC') {
                $this->mpdf->thead_font_style = 'I';
            }
        }
        if (isset($properties['FONT-VARIANT'])) {
            $this->mpdf->thead_font_sm_caps = '';
            if (strtoupper($properties['FONT-VARIANT']) === 'SMALL-CAPS') {
                $this->mpdf->thead_font_sm_caps = 'S';
            }
        }
        if (isset($properties['VERTICAL-ALIGN'])) {
            $this->mpdf->thead_valign_default = $properties['VERTICAL-ALIGN'];
        }
        if (isset($properties['TEXT-ALIGN'])) {
            $this->mpdf->thead_textalign_default = $properties['TEXT-ALIGN'];
        }
    }
    public function close(&$ahtml, &$ihtml)
    {
        $this->mpdf->lastoptionaltag = '';
        unset($this->css_manager->tablecascade_css[$this->css_manager->tb_cs_slvl]);
        $this->css_manager->tb_cs_slvl--;
        $this->mpdf->tablethead = 0;
        $this->mpdf->tabletheadjustfinished = true;
        $this->mpdf->reset_styles();
        $this->mpdf->thead_font_weight = '';
        $this->mpdf->thead_font_style = '';
        $this->mpdf->thead_font_sm_caps = '';
        $this->mpdf->thead_valign_default = '';
        $this->mpdf->thead_textalign_default = '';
    }
}