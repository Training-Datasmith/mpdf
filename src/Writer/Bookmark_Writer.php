<?php

namespace Mpdf\Writer;

use Mpdf\Strict;
use Mpdf\Mpdf;
final class Bookmark_Writer
{
    use Strict;
    /**
     * @var \Mpdf\Mpdf
     */
    private $mpdf;
    /**
     * @var \Mpdf\Writer\BaseWriter
     */
    private $writer;
    public function __construct(Mpdf $mpdf, Base_Writer $writer)
    {
        $this->mpdf = $mpdf;
        $this->writer = $writer;
    }
    public function write_bookmarks()
    {
        $nb = count($this->mpdf->b_moutlines);
        if ($nb === 0) {
            return;
        }
        $bmo = $this->mpdf->b_moutlines;
        $this->mpdf->b_moutlines = [];
        $lastlevel = -1;
        for ($i = 0; $i < count($bmo); $i++) {
            if ($bmo[$i]['l'] > 0) {
                while ($bmo[$i]['l'] - $lastlevel > 1) {
                    // If jump down more than one level, insert a new entry
                    $new = $bmo[$i];
                    $new['t'] = "[" . $new['t'] . "]";
                    // Put [] around text/title to highlight
                    $new['l'] = $lastlevel + 1;
                    $lastlevel++;
                    $this->mpdf->b_moutlines[] = $new;
                }
            }
            $this->mpdf->b_moutlines[] = $bmo[$i];
            $lastlevel = $bmo[$i]['l'];
        }
        $nb = count($this->mpdf->b_moutlines);
        $lru = [];
        $level = 0;
        foreach ($this->mpdf->b_moutlines as $i => $o) {
            if ($o['l'] > 0) {
                $parent = $lru[$o['l'] - 1];
                // Set parent and last pointers
                $this->mpdf->b_moutlines[$i]['parent'] = $parent;
                $this->mpdf->b_moutlines[$parent]['last'] = $i;
                if ($o['l'] > $level) {
                    // Level increasing: set first pointer
                    $this->mpdf->b_moutlines[$parent]['first'] = $i;
                }
            } else {
                $this->mpdf->b_moutlines[$i]['parent'] = $nb;
            }
            if ($o['l'] <= $level and $i > 0) {
                // Set prev and next pointers
                $prev = $lru[$o['l']];
                $this->mpdf->b_moutlines[$prev]['next'] = $i;
                $this->mpdf->b_moutlines[$i]['prev'] = $prev;
            }
            $lru[$o['l']] = $i;
            $level = $o['l'];
        }
        // Outline items
        $n = $this->mpdf->n + 1;
        foreach ($this->mpdf->b_moutlines as $i => $o) {
            $this->writer->object();
            $this->writer->write('<</Title ' . $this->writer->utf16big_endian_text_string($o['t']));
            $this->writer->write('/Parent ' . ($n + $o['parent']) . ' 0 R');
            if (isset($o['prev'])) {
                $this->writer->write('/Prev ' . ($n + $o['prev']) . ' 0 R');
            }
            if (isset($o['next'])) {
                $this->writer->write('/Next ' . ($n + $o['next']) . ' 0 R');
            }
            if (isset($o['first'])) {
                $this->writer->write('/First ' . ($n + $o['first']) . ' 0 R');
            }
            if (isset($o['last'])) {
                $this->writer->write('/Last ' . ($n + $o['last']) . ' 0 R');
            }
            if (isset($this->mpdf->page_dim[$o['p']]['h'])) {
                $h = $this->mpdf->page_dim[$o['p']]['h'];
            } else {
                $h = 0;
            }
            $this->writer->write(sprintf('/Dest [%d 0 R /XYZ 0 %.3F null]', 1 + 2 * $o['p'], ($h - $o['y']) * Mpdf::SCALE));
            if (isset($this->mpdf->bookmark_styles) && isset($this->mpdf->bookmark_styles[$o['l']])) {
                // font style
                $bms = $this->mpdf->bookmark_styles[$o['l']]['style'];
                $style = 0;
                if (strpos($bms, 'B') !== false) {
                    $style += 2;
                }
                if (strpos($bms, 'I') !== false) {
                    $style += 1;
                }
                $this->writer->write(sprintf('/F %d', $style));
                // Colour
                $col = $this->mpdf->bookmark_styles[$o['l']]['color'];
                if (isset($col) && is_array($col) && count($col) == 3) {
                    $this->writer->write(sprintf('/C [%.3F %.3F %.3F]', $col[0] / 255, $col[1] / 255, $col[2] / 255));
                }
            }
            $this->writer->write('/Count 0>>');
            $this->writer->write('endobj');
        }
        // Outline root
        $this->writer->object();
        $this->mpdf->outline_root = $this->mpdf->n;
        $this->writer->write('<</Type /BMoutlines /First ' . $n . ' 0 R');
        $this->writer->write('/Last ' . ($n + $lru[0]) . ' 0 R>>');
        $this->writer->write('endobj');
    }
}