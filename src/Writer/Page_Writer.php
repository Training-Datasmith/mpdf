<?php

namespace Mpdf\Writer;

use Mpdf\Strict;
use Mpdf\Mpdf;
use Mpdf\Form;
final class Page_Writer
{
    use Strict;
    /**
     * @var \Mpdf\Mpdf
     */
    private $mpdf;
    /**
     * @var \Mpdf\Form
     */
    private $form;
    /**
     * @var \Mpdf\Writer\BaseWriter
     */
    private $writer;
    /**
     * @var \Mpdf\Writer\MetadataWriter
     */
    private $metadata_writer;
    public function __construct(Mpdf $mpdf, Form $form, Base_Writer $writer, Metadata_Writer $metadata_writer)
    {
        $this->mpdf = $mpdf;
        $this->form = $form;
        $this->writer = $writer;
        $this->metadata_writer = $metadata_writer;
    }
    public function write_pages()
    {
        $nb = $this->mpdf->page;
        $filter = $this->mpdf->compress ? '/Filter /FlateDecode ' : '';
        if ($this->mpdf->def_orientation === 'P') {
            $defw_pt = $this->mpdf->fw_pt;
            $defh_pt = $this->mpdf->fh_pt;
        } else {
            $defw_pt = $this->mpdf->fh_pt;
            $defh_pt = $this->mpdf->fw_pt;
        }
        $annotid = 3 + 2 * $nb;
        // Active Forms
        $totaladdnum = 0;
        for ($n = 1; $n <= $nb; $n++) {
            if (isset($this->mpdf->page_links[$n])) {
                $totaladdnum += count($this->mpdf->page_links[$n]);
            }
            /* -- ANNOTATIONS -- */
            if (isset($this->mpdf->page_annots[$n])) {
                foreach ($this->mpdf->page_annots[$n] as $k => $pl) {
                    if (!empty($pl['opt']['popup']) || !empty($pl['opt']['file'])) {
                        $totaladdnum += 2;
                    } else {
                        $totaladdnum++;
                    }
                }
            }
            /* -- END ANNOTATIONS -- */
            /* -- FORMS -- */
            if (count($this->form->forms) > 0) {
                $this->form->count_page_forms($n, $totaladdnum);
            }
            /* -- END FORMS -- */
        }
        /* -- FORMS -- */
        // Make a note in the radio button group of the obj_id it will have
        $ctr = 0;
        if (count($this->form->form_radio_groups)) {
            foreach ($this->form->form_radio_groups as $name => $frg) {
                $this->form->form_radio_groups[$name]['obj_id'] = $annotid + $totaladdnum + $ctr;
                $ctr++;
            }
        }
        /* -- END FORMS -- */
        // Select unused fonts (usually default font)
        $unused = [];
        foreach ($this->mpdf->fonts as $fk => $font) {
            if (isset($font['type']) && $font['type'] === 'TTF' && !$font['used']) {
                $unused[] = $fk;
            }
        }
        for ($n = 1; $n <= $nb; $n++) {
            $thispage = $this->mpdf->pages[$n];
            if (isset($this->mpdf->orientation_changes[$n])) {
                $h_pt = $this->mpdf->page_dim[$n]['w'] * Mpdf::SCALE;
                $w_pt = $this->mpdf->page_dim[$n]['h'] * Mpdf::SCALE;
                $owidth_pt_lr = $this->mpdf->page_dim[$n]['outer_width_TB'] * Mpdf::SCALE;
                $owidth_pt_tb = $this->mpdf->page_dim[$n]['outer_width_LR'] * Mpdf::SCALE;
            } else {
                $w_pt = $this->mpdf->page_dim[$n]['w'] * Mpdf::SCALE;
                $h_pt = $this->mpdf->page_dim[$n]['h'] * Mpdf::SCALE;
                $owidth_pt_lr = $this->mpdf->page_dim[$n]['outer_width_LR'] * Mpdf::SCALE;
                $owidth_pt_tb = $this->mpdf->page_dim[$n]['outer_width_TB'] * Mpdf::SCALE;
            }
            // Remove references to unused fonts (usually default font)
            foreach ($unused as $fk) {
                if ($this->mpdf->fonts[$fk]['sip'] || $this->mpdf->fonts[$fk]['smp']) {
                    foreach ($this->mpdf->fonts[$fk]['subsetfontids'] as $k => $fid) {
                        $thispage = preg_replace('/\s\/F' . $fid . ' \d[\d.]* Tf\s/is', ' ', $thispage);
                    }
                } else {
                    $thispage = preg_replace('/\s\/F' . $this->mpdf->fonts[$fk]['i'] . ' \d[\d.]* Tf\s/is', ' ', $thispage);
                }
            }
            // Clean up repeated /GS1 gs statements
            // For some reason using + for repetition instead of {2,20} crashes PHP Script Interpreter ???
            $thispage = preg_replace('/(\/GS1 gs\n){2,20}/', "/GS1 gs\n", $thispage);
            $thispage = preg_replace('/(\s*___BACKGROUND___PATTERNS' . $this->mpdf->uniqstr . '\s*)/', ' ', $thispage);
            $thispage = preg_replace('/(\s*___HEADER___MARKER' . $this->mpdf->uniqstr . '\s*)/', ' ', $thispage);
            $thispage = preg_replace('/(\s*___PAGE___START' . $this->mpdf->uniqstr . '\s*)/', ' ', $thispage);
            $thispage = preg_replace('/(\s*___TABLE___BACKGROUNDS' . $this->mpdf->uniqstr . '\s*)/', ' ', $thispage);
            // mPDF 5.7.3 TRANSFORMS
            while (preg_match('/(\% BTR(.*?)\% ETR)/is', $thispage, $m)) {
                $thispage = preg_replace('/(\% BTR.*?\% ETR)/is', '', $thispage, 1) . "\n" . $m[2];
            }
            // Page
            $this->writer->object();
            $this->writer->write('<</Type /Page');
            $this->writer->write('/Parent 1 0 R');
            if (isset($this->mpdf->orientation_changes[$n])) {
                $this->writer->write(sprintf('/MediaBox [0 0 %.3F %.3F]', $h_pt, $w_pt));
                // If BleedBox is defined, it must be larger than the TrimBox, but smaller than the MediaBox
                $bleed_margin = $this->mpdf->page_dim[$n]['bleedMargin'] * Mpdf::SCALE;
                if ($bleed_margin && ($owidth_pt_tb || $owidth_pt_lr)) {
                    $x0 = $owidth_pt_tb - $bleed_margin;
                    $y0 = $owidth_pt_lr - $bleed_margin;
                    $x1 = $h_pt - $owidth_pt_tb + $bleed_margin;
                    $y1 = $w_pt - $owidth_pt_lr + $bleed_margin;
                    $this->writer->write(sprintf('/BleedBox [%.3F %.3F %.3F %.3F]', $x0, $y0, $x1, $y1));
                }
                $this->writer->write(sprintf('/TrimBox [%.3F %.3F %.3F %.3F]', $owidth_pt_tb, $owidth_pt_lr, $h_pt - $owidth_pt_tb, $w_pt - $owidth_pt_lr));
                if ($this->mpdf->display_default_orientation) {
                    if ($this->mpdf->def_orientation === 'P') {
                        $this->writer->write('/Rotate 270');
                    } else {
                        $this->writer->write('/Rotate 90');
                    }
                }
            } else {
                // elseif($wPt != $defwPt || $hPt != $defhPt) {
                $this->writer->write(sprintf('/MediaBox [0 0 %.3F %.3F]', $w_pt, $h_pt));
                $bleed_margin = $this->mpdf->page_dim[$n]['bleedMargin'] * Mpdf::SCALE;
                if ($bleed_margin && ($owidth_pt_tb || $owidth_pt_lr)) {
                    $x0 = $owidth_pt_lr - $bleed_margin;
                    $y0 = $owidth_pt_tb - $bleed_margin;
                    $x1 = $w_pt - $owidth_pt_lr + $bleed_margin;
                    $y1 = $h_pt - $owidth_pt_tb + $bleed_margin;
                    $this->writer->write(sprintf('/BleedBox [%.3F %.3F %.3F %.3F]', $x0, $y0, $x1, $y1));
                }
                $this->writer->write(sprintf('/TrimBox [%.3F %.3F %.3F %.3F]', $owidth_pt_lr, $owidth_pt_tb, $w_pt - $owidth_pt_lr, $h_pt - $owidth_pt_tb));
            }
            $this->writer->write('/Resources 2 0 R');
            // Important to keep in RGB colorSpace when using transparency
            if (!$this->mpdf->PDFA && !$this->mpdf->PDFX) {
                if ($this->mpdf->restrict_color_space === 3) {
                    $this->writer->write('/Group << /Type /Group /S /Transparency /CS /DeviceCMYK >> ');
                } elseif ($this->mpdf->restrict_color_space === 1) {
                    $this->writer->write('/Group << /Type /Group /S /Transparency /CS /DeviceGray >> ');
                } else {
                    $this->writer->write('/Group << /Type /Group /S /Transparency /CS /DeviceRGB >> ');
                }
            }
            $annotsnum = 0;
            $embeddedfiles = [];
            // mPDF 5.7.2 /EmbeddedFiles
            if (isset($this->mpdf->page_links[$n])) {
                $annotsnum += count($this->mpdf->page_links[$n]);
            }
            if (isset($this->mpdf->page_annots[$n])) {
                foreach ($this->mpdf->page_annots[$n] as $k => $pl) {
                    if (!empty($pl['opt']['file'])) {
                        $embeddedfiles[$annotsnum + 1] = true;
                    }
                    // mPDF 5.7.2 /EmbeddedFiles
                    if (!empty($pl['opt']['popup']) || !empty($pl['opt']['file'])) {
                        $annotsnum += 2;
                    } else {
                        $annotsnum++;
                    }
                    $this->mpdf->page_annots[$n][$k]['pageobj'] = $this->mpdf->n;
                }
            }
            // Active Forms
            $formsnum = 0;
            if (count($this->form->forms) > 0) {
                foreach ($this->form->forms as $val) {
                    if ($val['page'] == $n) {
                        $formsnum++;
                    }
                }
            }
            if ($annotsnum || $formsnum) {
                $s = '/Annots [ ';
                for ($i = 0; $i < $annotsnum; $i++) {
                    if (!isset($embeddedfiles[$i])) {
                        $s .= $annotid + $i . ' 0 R ';
                    }
                    // mPDF 5.7.2 /EmbeddedFiles
                }
                $annotid += $annotsnum;
                /* -- FORMS -- */
                if (count($this->form->forms) > 0) {
                    $this->form->add_form_ids($n, $s, $annotid);
                }
                /* -- END FORMS -- */
                $s .= '] ';
                $this->writer->write($s);
            }
            $this->writer->write('/Contents ' . ($this->mpdf->n + 1) . ' 0 R>>');
            $this->writer->write('endobj');
            // Page content
            $this->writer->object();
            $p = $this->mpdf->compress ? gzcompress($thispage) : $thispage;
            $this->writer->write('<<' . $filter . '/Length ' . strlen($p) . '>>');
            $this->writer->stream($p);
            $this->writer->write('endobj');
        }
        $this->metadata_writer->write_annotations();
        // mPDF 5.7.2
        // Pages root
        $this->mpdf->offsets[1] = $this->mpdf->buffer->get_length();
        $this->writer->write('1 0 obj');
        $this->writer->write('<</Type /Pages');
        $kids = '/Kids [';
        for ($i = 0; $i < $nb; $i++) {
            $kids .= 3 + 2 * $i . ' 0 R ';
        }
        $this->writer->write($kids . ']');
        $this->writer->write('/Count ' . $nb);
        $this->writer->write(sprintf('/MediaBox [0 0 %.3F %.3F]', $defw_pt, $defh_pt));
        $this->writer->write('>>');
        $this->writer->write('endobj');
    }
}