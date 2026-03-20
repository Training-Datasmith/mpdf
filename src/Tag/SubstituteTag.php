<?php

namespace Mpdf\Tag;

abstract class Substitute_Tag extends Tag
{
    public function close(&$ahtml, &$ihtml)
    {
        $tag = $this->get_tag_name();
        if ($this->mpdf->inline_properties[$tag]) {
            $this->mpdf->restore_inline_properties($this->mpdf->inline_properties[$tag]);
        }
        unset($this->mpdf->inline_properties[$tag]);
        $ltag = strtolower($tag);
        $this->mpdf->{$ltag} = false;
    }
}