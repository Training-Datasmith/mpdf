<?php

namespace Mpdf\Tag;

class Br extends Tag
{
    public function open($attr, &$ahtml, &$ihtml)
    {
        // Added mPDF 3.0 Float DIV - CLEAR
        if (isset($attr['STYLE'])) {
            $properties = $this->css_manager->read_inline_css($attr['STYLE']);
            if (isset($properties['CLEAR'])) {
                $this->mpdf->clear_floats(strtoupper($properties['CLEAR']), $this->mpdf->blklvl);
            }
            // *CSS-FLOAT*
        }
        // mPDF 6 bidi
        // Inline
        // If unicode-bidi set, any embedding levels, isolates, or overrides started by
        // the inline box are closed at the br and reopened on the other side
        $blockpre = '';
        $blockpost = '';
        if (isset($this->mpdf->blk[$this->mpdf->blklvl]['bidicode'])) {
            $blockpre = $this->mpdf->_set_bidi_codes('end', $this->mpdf->blk[$this->mpdf->blklvl]['bidicode']);
            $blockpost = $this->mpdf->_set_bidi_codes('start', $this->mpdf->blk[$this->mpdf->blklvl]['bidicode']);
        }
        // Inline
        // If unicode-bidi set, any embedding levels, isolates, or overrides started by
        // the inline box are closed at the br and reopened on the other side
        $inlinepre = '';
        $inlinepost = '';
        $i_bdf = [];
        if (count($this->mpdf->inline_bdf)) {
            foreach ($this->mpdf->inline_bdf as $k => $ib) {
                foreach ($ib as $ib2) {
                    $i_bdf[$ib2[1]] = $ib2[0];
                }
            }
            if (count($i_bdf)) {
                ksort($i_bdf);
                for ($i = count($i_bdf) - 1; $i >= 0; $i--) {
                    $inlinepre .= $this->mpdf->_set_bidi_codes('end', $i_bdf[$i]);
                }
                for ($i = 0; $i < count($i_bdf); $i++) {
                    $inlinepost .= $this->mpdf->_set_bidi_codes('start', $i_bdf[$i]);
                }
            }
        }
        /* -- TABLES -- */
        if ($this->mpdf->table_level) {
            if ($this->mpdf->blockjustfinished) {
                $this->mpdf->_save_cell_text_buffer($blockpre . $inlinepre . "\n" . $inlinepost . $blockpost);
            }
            $this->mpdf->_save_cell_text_buffer($blockpre . $inlinepre . "\n" . $inlinepost . $blockpost);
            if (!isset($this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['maxs'])) {
                $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['maxs'] = $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['s'];
            } elseif ($this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['maxs'] < $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['s']) {
                $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['maxs'] = $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['s'];
            }
            $this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['s'] = 0;
            // reset
        } else {
            /* -- END TABLES -- */
            if (count($this->mpdf->textbuffer)) {
                $this->mpdf->textbuffer[count($this->mpdf->textbuffer) - 1][0] = preg_replace('/ $/', '', $this->mpdf->textbuffer[count($this->mpdf->textbuffer) - 1][0]);
                if (!empty($this->mpdf->textbuffer[count($this->mpdf->textbuffer) - 1][18])) {
                    $this->otl->trim_ot_ldata($this->mpdf->textbuffer[count($this->mpdf->textbuffer) - 1][18], false);
                }
                // *OTL*
            }
            $this->mpdf->_save_text_buffer($blockpre . $inlinepre . "\n" . $inlinepost . $blockpost);
        }
        // *TABLES*
        $this->mpdf->ignorefollowingspaces = true;
        $this->mpdf->blockjustfinished = false;
        $this->mpdf->linebreakjustfinished = true;
    }
    public function close(&$ahtml, &$ihtml)
    {
    }
}