<?php

namespace Mpdf\Tag;

class Columns extends Tag
{
    /**
     * @param string $tag
     * @return \Mpdf\Tag\Tag
     */
    private function get_tag_instance($tag)
    {
        $class_name = \Mpdf\Tag::get_tag_class_name($tag);
        if (class_exists($class_name)) {
            return new $class_name($this->mpdf, $this->cache, $this->css_manager, $this->form, $this->otl, $this->table_of_contents, $this->size_converter, $this->color_converter, $this->image_processor, $this->language_to_font);
        }
        return null;
    }
    public function open($attr, &$ahtml, &$ihtml)
    {
        if (isset($attr['COLUMN-COUNT']) && ($attr['COLUMN-COUNT'] || $attr['COLUMN-COUNT'] === '0')) {
            // Close any open block tags
            for ($b = $this->mpdf->blklvl; $b > 0; $b--) {
                if ($t = $this->get_tag_instance($this->mpdf->blk[$b]['tag'])) {
                    $t->close($ahtml, $ihtml);
                }
            }
            if (!empty($this->mpdf->textbuffer)) {
                //Output previously buffered content
                $this->mpdf->printbuffer($this->mpdf->textbuffer);
                $this->mpdf->textbuffer = [];
            }
            if (!empty($attr['VALIGN'])) {
                if ($attr['VALIGN'] === 'J') {
                    $valign = 'J';
                } else {
                    $valign = $this->get_align($attr['VALIGN']);
                }
            } else {
                $valign = '';
            }
            if (!empty($attr['COLUMN-GAP'])) {
                $this->mpdf->set_columns($attr['COLUMN-COUNT'], $valign, $attr['COLUMN-GAP']);
            } else {
                $this->mpdf->set_columns($attr['COLUMN-COUNT'], $valign);
            }
        }
        $this->mpdf->ignorefollowingspaces = true;
    }
    public function close(&$ahtml, &$ihtml)
    {
    }
}