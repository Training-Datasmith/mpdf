<?php

namespace Mpdf\Tag;

class Watermark_Text extends Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        $txt = '';
        if (!empty($attr['CONTENT'])) {
            $txt = htmlspecialchars_decode($attr['CONTENT'], ENT_QUOTES);
        }
        $alpha = -1;
        if (isset($attr['ALPHA']) && $attr['ALPHA'] > 0) {
            $alpha = $attr['ALPHA'];
        }
        $this->mpdf->set_watermark_text($txt, $alpha);
    }
    public function close(&$ahtml, &$ihtml)
    {
    }
}