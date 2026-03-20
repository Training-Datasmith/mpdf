<?php

namespace Mpdf\Tag;

class Toc_Page_Break extends Form_Feed
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        list($isbreak, $toc_id) = $this->table_of_contents->open_tag_tocpagebreak($attr);
        $this->toc_id = $toc_id;
        if ($isbreak) {
            return;
        }
        parent::open($attr, $ahtml, $ihtml);
    }
}