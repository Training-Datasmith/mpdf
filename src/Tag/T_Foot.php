<?php

namespace Mpdf\Tag;

// TODO: Extend THEAD instead?
class T_Foot extends Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        $this->mpdf->lastoptionaltag = 'TFOOT';
        // Save current HTML specified optional endtag
        $this->css_manager->tb_cs_slvl++;
        $this->mpdf->tabletfoot = 1;
        $this->mpdf->tablethead = 0;
        $properties = $this->css_manager->merge_css('TABLE', 'TFOOT', $attr);
        if (isset($properties['FONT-WEIGHT'])) {
            $this->mpdf->tfoot_font_weight = '';
            if (strtoupper($properties['FONT-WEIGHT']) === 'BOLD') {
                $this->mpdf->tfoot_font_weight = 'B';
            }
        }
        if (isset($properties['FONT-STYLE'])) {
            $this->mpdf->tfoot_font_style = '';
            if (strtoupper($properties['FONT-STYLE']) === 'ITALIC') {
                $this->mpdf->tfoot_font_style = 'I';
            }
        }
        if (isset($properties['FONT-VARIANT'])) {
            $this->mpdf->tfoot_font_sm_caps = '';
            if (strtoupper($properties['FONT-VARIANT']) === 'SMALL-CAPS') {
                $this->mpdf->tfoot_font_sm_caps = 'S';
            }
        }
        if (isset($properties['VERTICAL-ALIGN'])) {
            $this->mpdf->tfoot_valign_default = $properties['VERTICAL-ALIGN'];
        }
        if (isset($properties['TEXT-ALIGN'])) {
            $this->mpdf->tfoot_textalign_default = $properties['TEXT-ALIGN'];
        }
    }
    public function close(&$ahtml, &$ihtml)
    {
        $this->mpdf->lastoptionaltag = '';
        unset($this->css_manager->tablecascade_css[$this->css_manager->tb_cs_slvl]);
        $this->css_manager->tb_cs_slvl--;
        $this->mpdf->tabletfoot = 0;
        $this->mpdf->reset_styles();
        $this->mpdf->tfoot_font_weight = '';
        $this->mpdf->tfoot_font_style = '';
        $this->mpdf->tfoot_font_sm_caps = '';
        $this->mpdf->tfoot_valign_default = '';
        $this->mpdf->tfoot_textalign_default = '';
    }
}