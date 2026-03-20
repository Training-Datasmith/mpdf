<?php

namespace Mpdf\Tag;

class Ttz extends Substitute_Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        $this->mpdf->ttz = true;
        $this->mpdf->inline_properties['TTZ'] = $this->mpdf->save_inline_properties();
        $this->mpdf->set_css(['FONT-FAMILY' => 'czapfdingbats', 'FONT-WEIGHT' => 'normal', 'FONT-STYLE' => 'normal'], 'INLINE');
    }
}