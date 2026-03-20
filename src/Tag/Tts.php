<?php

namespace Mpdf\Tag;

class Tts extends Substitute_Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        $this->mpdf->tts = true;
        $this->mpdf->inline_properties['TTS'] = $this->mpdf->save_inline_properties();
        $this->mpdf->set_css(['FONT-FAMILY' => 'csymbol', 'FONT-WEIGHT' => 'normal', 'FONT-STYLE' => 'normal'], 'INLINE');
    }
}