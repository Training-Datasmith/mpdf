<?php

namespace Mpdf\Tag;

use Mpdf\Css\Border;
class Tr extends Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        $this->mpdf->lastoptionaltag = 'TR';
        // Save current HTML specified optional endtag
        $this->css_manager->tb_cs_slvl++;
        $this->mpdf->row++;
        $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['nr']++;
        $this->mpdf->col = -1;
        $properties = $this->css_manager->merge_css('TABLE', 'TR', $attr);
        // write pagebreak markers into row list, so _tableWrite can respect it
        if (isset($properties['PAGE-BREAK-BEFORE']) && strtoupper($properties['PAGE-BREAK-BEFORE']) === 'AVOID' && !$this->mpdf->col_active && !$this->mpdf->keep_block_together && !isset($attr['PAGEBREAKAVOIDCHECKED'])) {
            $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['pagebreak-before'][$this->mpdf->row] = 'avoid';
        }
        if (isset($properties['PAGE-BREAK-AFTER']) && strtoupper($properties['PAGE-BREAK-AFTER']) === 'AVOID' && !$this->mpdf->col_active && !$this->mpdf->keep_block_together && !isset($attr['PAGEBREAKAVOIDCHECKED'])) {
            $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['pagebreak-before'][$this->mpdf->row + 1] = 'avoid';
        }
        if (!$this->mpdf->simple_tables && (!isset($this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['borders_separate']) || !$this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['borders_separate'])) {
            if (!empty($properties['BORDER-LEFT'])) {
                $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['trborder-left'][$this->mpdf->row] = $properties['BORDER-LEFT'];
            }
            if (!empty($properties['BORDER-RIGHT'])) {
                $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['trborder-right'][$this->mpdf->row] = $properties['BORDER-RIGHT'];
            }
            if (!empty($properties['BORDER-TOP'])) {
                $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['trborder-top'][$this->mpdf->row] = $properties['BORDER-TOP'];
            }
            if (!empty($properties['BORDER-BOTTOM'])) {
                $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['trborder-bottom'][$this->mpdf->row] = $properties['BORDER-BOTTOM'];
            }
        }
        if (isset($properties['BACKGROUND-COLOR'])) {
            $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['bgcolor'] = $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['bgcolor'] ? $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['bgcolor'] : [];
            $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['bgcolor'][$this->mpdf->row] = $properties['BACKGROUND-COLOR'];
        } elseif (isset($attr['BGCOLOR'])) {
            $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['bgcolor'] = $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['bgcolor'] ? $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['bgcolor'] : [];
            $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['bgcolor'][$this->mpdf->row] = $attr['BGCOLOR'];
        }
        /* -- BACKGROUNDS -- */
        if (isset($properties['BACKGROUND-GRADIENT']) && !$this->mpdf->kwt && !$this->mpdf->col_active) {
            $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['trgradients'][$this->mpdf->row] = $properties['BACKGROUND-GRADIENT'];
        }
        // FIXME: undefined variable $currblk
        if (!empty($properties['BACKGROUND-IMAGE']) && !$this->mpdf->kwt && !$this->mpdf->col_active) {
            $ret = $this->mpdf->set_background($properties, $currblk['inner_width']);
            if ($ret) {
                $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['trbackground-images'][$this->mpdf->row] = $ret;
            }
        }
        /* -- END BACKGROUNDS -- */
        if (isset($properties['TEXT-ROTATE'])) {
            $this->mpdf->trow_text_rotate = $properties['TEXT-ROTATE'];
        }
        if (isset($attr['TEXT-ROTATE'])) {
            $this->mpdf->trow_text_rotate = $attr['TEXT-ROTATE'];
        }
        if ($this->mpdf->tablethead) {
            $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['is_thead'][$this->mpdf->row] = true;
        }
        if ($this->mpdf->tabletfoot) {
            $this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['is_tfoot'][$this->mpdf->row] = true;
        }
    }
    public function close(&$ahtml, &$ihtml)
    {
        if ($this->mpdf->table_level) {
            // If Border set on TR - Update right border
            if (isset($this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['trborder-left'][$this->mpdf->row])) {
                $c =& $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col];
                if ($c) {
                    if ($this->mpdf->pack_table_data) {
                        $cell = $this->mpdf->_unpack_cell_border($c['borderbin']);
                    } else {
                        $cell = $c;
                    }
                    $cell['border_details']['R'] = $this->mpdf->border_details($this->mpdf->table[$this->mpdf->table_level][$this->mpdf->tbctr[$this->mpdf->table_level]]['trborder-right'][$this->mpdf->row]);
                    $this->mpdf->set_border($cell['border'], Border::RIGHT, $cell['border_details']['R']['s']);
                    if ($this->mpdf->pack_table_data) {
                        $c['borderbin'] = $this->mpdf->_pack_cell_border($cell);
                        unset($c['border'], $c['border_details']);
                    } else {
                        $c = $cell;
                    }
                }
            }
            $this->mpdf->lastoptionaltag = '';
            unset($this->css_manager->tablecascade_css[$this->css_manager->tb_cs_slvl]);
            $this->css_manager->tb_cs_slvl--;
            $this->mpdf->trow_text_rotate = '';
            $this->mpdf->tabletheadjustfinished = false;
        }
    }
}