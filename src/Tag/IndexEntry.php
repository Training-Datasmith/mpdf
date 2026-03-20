<?php

namespace Mpdf\Tag;

use Mpdf\Mpdf;
class Index_Entry extends Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        if (!empty($attr['CONTENT'])) {
            if (!empty($attr['XREF'])) {
                $this->mpdf->index_entry(htmlspecialchars_decode($attr['CONTENT'], ENT_QUOTES), $attr['XREF']);
                return;
            }
            $objattr = [];
            $objattr['CONTENT'] = htmlspecialchars_decode($attr['CONTENT'], ENT_QUOTES);
            $objattr['type'] = 'indexentry';
            $objattr['vertical-align'] = 'T';
            $e = Mpdf::OBJECT_IDENTIFIER . "type=indexentry,objattr=" . serialize($objattr) . Mpdf::OBJECT_IDENTIFIER;
            if ($this->mpdf->table_level) {
                $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['textbuffer'][] = [$e];
            } else {
                // *TABLES*
                $this->mpdf->textbuffer[] = [$e];
            }
            // *TABLES*
        }
    }
    public function close(&$ahtml, &$ihtml)
    {
    }
}