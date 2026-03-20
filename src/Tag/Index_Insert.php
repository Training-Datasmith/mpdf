<?php

namespace Mpdf\Tag;

class Index_Insert extends Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        $index_collation_locale = '';
        if (isset($attr['COLLATION'])) {
            $index_collation_locale = $attr['COLLATION'];
        }
        $index_collation_group = '';
        if (isset($attr['COLLATION-GROUP'])) {
            $index_collation_group = $attr['COLLATION-GROUP'];
        }
        $usedivletters = 1;
        if (isset($attr['USEDIVLETTERS']) && (strtoupper($attr['USEDIVLETTERS']) === 'OFF' || $attr['USEDIVLETTERS'] == -1 || $attr['USEDIVLETTERS'] === '0')) {
            $usedivletters = 0;
        }
        $links = isset($attr['LINKS']) && (strtoupper($attr['LINKS']) === 'ON' || $attr['LINKS'] == 1);
        $this->mpdf->insert_index($usedivletters, $links, $index_collation_locale, $index_collation_group);
    }
    public function close(&$ahtml, &$ihtml)
    {
    }
}