<?php

namespace Mpdf\Tag;

class Tta extends Substitute_Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        $this->mpdf->tta = true;
        $this->mpdf->inline_properties['TTA'] = $this->mpdf->save_inline_properties();
        if (in_array($this->mpdf->font_family, $this->mpdf->mono_fonts)) {
            $this->mpdf->set_css(['FONT-FAMILY' => 'ccourier'], 'INLINE');
        } elseif (in_array($this->mpdf->font_family, $this->mpdf->serif_fonts)) {
            $this->mpdf->set_css(['FONT-FAMILY' => 'ctimes'], 'INLINE');
        } else {
            $this->mpdf->set_css(['FONT-FAMILY' => 'chelvetica'], 'INLINE');
        }
    }
}