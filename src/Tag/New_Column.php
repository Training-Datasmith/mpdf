<?php

namespace Mpdf\Tag;

class New_Column extends Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        $this->mpdf->ignorefollowingspaces = true;
        $this->mpdf->new_column();
        $this->mpdf->column_adjust = false;
        // disables all column height adjustment for the page.
    }
    public function close(&$ahtml, &$ihtml)
    {
    }
}