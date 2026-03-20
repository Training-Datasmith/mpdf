<?php

namespace Mpdf\Tag;

class T_Body extends Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        $this->mpdf->tablethead = 0;
        $this->mpdf->tabletfoot = 0;
        $this->mpdf->lastoptionaltag = 'TBODY';
        // Save current HTML specified optional endtag
        $this->css_manager->tb_cs_slvl++;
        $this->css_manager->merge_css('TABLE', 'TBODY', $attr);
    }
    public function close(&$ahtml, &$ihtml)
    {
        $this->mpdf->lastoptionaltag = '';
        unset($this->css_manager->tablecascade_css[$this->css_manager->tb_cs_slvl]);
        $this->css_manager->tb_cs_slvl--;
    }
}