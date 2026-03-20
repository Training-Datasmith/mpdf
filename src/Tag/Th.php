<?php

namespace Mpdf\Tag;

class Th extends Td
{
    public function close(&$ahtml, &$ihtml)
    {
        $this->mpdf->set_style('B', false);
        parent::close($ahtml, $ihtml);
    }
}