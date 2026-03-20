<?php

namespace Mpdf\Writer;

use Mpdf\Strict;
use Mpdf\Fonts\Font_Cache;
use Mpdf\Mpdf;
use Mpdf\Tt_Font_File;
class Font_Writer
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
    /**
     * @var \Mpdf\Fonts\FontCache
     */
    private $font_cache;
    /**
     * @var string
     */
    private $font_descriptor;
    public function __construct(Mpdf $mpdf, Base_Writer $writer, Font_Cache $font_cache, $font_descriptor)
    {
        $this->mpdf = $mpdf;
        $this->writer = $writer;
        $this->font_cache = $font_cache;
        $this->font_descriptor = $font_descriptor;
    }
    public function write_fonts()
    {
        foreach ($this->mpdf->font_files as $fontkey => $info) {
            // TrueType embedded
            if (isset($info['type']) && $info['type'] === 'TTF' && !$info['sip'] && !$info['smp']) {
                $used = true;
                $as_subset = true;
                foreach ($this->mpdf->fonts as $k => $f) {
                    if (isset($f['fontkey']) && $f['fontkey'] === $fontkey && $f['type'] === 'TTF') {
                        $used = $f['used'];
                        if ($used) {
                            $n_chars = (ord($f['cw'][0]) << 8) + ord($f['cw'][1]);
                            $usage = (int) (count($f['subset']) * 100 / $n_chars);
                            $fsize = $info['length1'];
                            // Always subset the very large TTF files
                            if ($fsize > $this->mpdf->max_ttf_filesize * 1024) {
                                $as_subset = true;
                            } elseif ($usage < $this->mpdf->percent_subset) {
                                $as_subset = true;
                            }
                        }
                        $this->mpdf->fonts[$k]['asSubset'] = $as_subset;
                        break;
                    }
                }
                if ($used && !$as_subset) {
                    // Font file embedding
                    $this->writer->object();
                    $this->mpdf->font_files[$fontkey]['n'] = $this->mpdf->n;
                    $originalsize = $info['length1'];
                    if ($this->mpdf->repackage_ttf || $this->mpdf->fonts[$fontkey]['TTCfontID'] > 0 || $this->mpdf->fonts[$fontkey]['useOTL'] > 0) {
                        // mPDF 5.7.1
                        // First see if there is a cached compressed file
                        if ($this->font_cache->has($fontkey . '.ps.z') && $this->font_cache->json_has($fontkey . '.ps.json')) {
                            $font = $this->font_cache->load($fontkey . '.ps.z');
                            $originalsize = $this->font_cache->json_load($fontkey . '.ps.json');
                            // sets $originalsize (of repackaged font)
                        } else {
                            $ttf = new Tt_Font_File($this->font_cache, $this->font_descriptor);
                            $font = $ttf->repackage_ttf($this->mpdf->font_files[$fontkey]['ttffile'], $this->mpdf->fonts[$fontkey]['TTCfontID'], $this->mpdf->debugfonts, $this->mpdf->fonts[$fontkey]['useOTL']);
                            // mPDF 5.7.1
                            $originalsize = strlen($font);
                            $font = gzcompress($font);
                            unset($ttf);
                            $this->font_cache->binary_write($fontkey . '.ps.z', $font);
                            $this->font_cache->json_write($fontkey . '.ps.json', $originalsize);
                        }
                    } elseif ($this->font_cache->has($fontkey . '.z')) {
                        $font = $this->font_cache->load($fontkey . '.z');
                    } else {
                        $font = file_get_contents($this->mpdf->font_files[$fontkey]['ttffile']);
                        $font = gzcompress($font);
                        $this->font_cache->binary_write($fontkey . '.z', $font);
                    }
                    $this->writer->write('<</Length ' . strlen($font));
                    $this->writer->write('/Filter /FlateDecode');
                    $this->writer->write('/Length1 ' . $originalsize);
                    $this->writer->write('>>');
                    $this->writer->stream($font);
                    $this->writer->write('endobj');
                }
            }
        }
        foreach ($this->mpdf->fonts as $k => $font) {
            // Font objects
            $type = $font['type'];
            $name = $font['name'];
            if ($type === 'TTF' && (!isset($font['used']) || !$font['used'])) {
                continue;
            }
            // @log Writing fonts
            if (isset($font['asSubset'])) {
                $as_subset = $font['asSubset'];
            } else {
                $as_subset = '';
            }
            if ($type === 'Type0') {
                // Adobe CJK Fonts
                $this->mpdf->fonts[$k]['n'] = $this->mpdf->n + 1;
                $this->writer->object();
                $this->writer->write('<</Type /Font');
                $this->write_type0($font);
            } elseif ($type === 'core') {
                // Standard font
                $this->mpdf->fonts[$k]['n'] = $this->mpdf->n + 1;
                if ($this->mpdf->PDFA || $this->mpdf->PDFX) {
                    throw new \Mpdf\Mpdf_Exception('Core fonts are not allowed in PDF/A1-b or PDFX/1-a files (Times, Helvetica, Courier etc.)');
                }
                $this->writer->object();
                $this->writer->write('<</Type /Font');
                $this->writer->write('/BaseFont /' . $name);
                $this->writer->write('/Subtype /Type1');
                if ($name !== 'Symbol' && $name !== 'ZapfDingbats') {
                    $this->writer->write('/Encoding /WinAnsiEncoding');
                }
                $this->writer->write('>>');
                $this->writer->write('endobj');
            } elseif ($type === 'TTF' && ($font['sip'] || $font['smp'])) {
                // TrueType embedded SUBSETS for SIP (CJK extB containing Supplementary Ideographic Plane 2)
                // Or Unicode Plane 1 - Supplementary Multilingual Plane
                if (!$font['used']) {
                    continue;
                }
                $ssfaid = 'AA';
                $ttf = new Tt_Font_File($this->font_cache, $this->font_descriptor);
                $subset_count = count($font['subsetfontids']);
                for ($sfid = 0; $sfid < $subset_count; $sfid++) {
                    $this->mpdf->fonts[$k]['n'][$sfid] = $this->mpdf->n + 1;
                    // NB an array for subset
                    $subsetname = 'MPDF' . $ssfaid . '+' . $font['name'];
                    if (function_exists('str_increment')) {
                        $ssfaid = str_increment($ssfaid);
                    } else {
                        $ssfaid++;
                    }
                    /* For some strange reason a subset ($sfid > 0) containing less than 97 characters causes an error
                    	  so fill up the array */
                    for ($j = count($font['subsets'][$sfid]); $j < 98; $j++) {
                        $font['subsets'][$sfid][$j] = 0;
                    }
                    $subset = $font['subsets'][$sfid];
                    unset($subset[0]);
                    $ttfontstream = $ttf->make_subset_sip($font['ttffile'], $subset, $font['TTCfontID'], $this->mpdf->debugfonts, $font['useOTL']);
                    // mPDF 5.7.1
                    $ttfontsize = strlen($ttfontstream);
                    $fontstream = gzcompress($ttfontstream);
                    $widthstring = '';
                    $to_unistring = '';
                    foreach ($font['subsets'][$sfid] as $cp => $u) {
                        $w = $this->mpdf->_get_char_width($font['cw'], $u);
                        if ($w !== false) {
                            $widthstring .= $w . ' ';
                        } else {
                            $widthstring .= round($ttf->default_width) . ' ';
                        }
                        if ($u > 65535) {
                            $utf8 = chr(($u >> 18) + 240) . chr(($u >> 12 & 63) + 128) . chr(($u >> 6 & 63) + 128) . chr(($u & 63) + 128);
                            $utf16 = mb_convert_encoding($utf8, 'UTF-16BE', 'UTF-8');
                            $l1 = ord($utf16[0]);
                            $h1 = ord($utf16[1]);
                            $l2 = ord($utf16[2]);
                            $h2 = ord($utf16[3]);
                            $to_unistring .= sprintf("<%02s> <%02s%02s%02s%02s>\n", strtoupper(dechex($cp)), strtoupper(dechex($l1)), strtoupper(dechex($h1)), strtoupper(dechex($l2)), strtoupper(dechex($h2)));
                        } else {
                            $to_unistring .= sprintf("<%02s> <%04s>\n", strtoupper(dechex($cp)), strtoupper(dechex($u)));
                        }
                    }
                    // Additional Type1 or TrueType font
                    $this->writer->object();
                    $this->writer->write('<</Type /Font');
                    $this->writer->write('/BaseFont /' . $subsetname);
                    $this->writer->write('/Subtype /TrueType');
                    $this->writer->write('/FirstChar 0 /LastChar ' . (count($font['subsets'][$sfid]) - 1));
                    $this->writer->write('/Widths ' . ($this->mpdf->n + 1) . ' 0 R');
                    $this->writer->write('/FontDescriptor ' . ($this->mpdf->n + 2) . ' 0 R');
                    $this->writer->write('/ToUnicode ' . ($this->mpdf->n + 3) . ' 0 R');
                    $this->writer->write('>>');
                    $this->writer->write('endobj');
                    // Widths
                    $this->writer->object();
                    $this->writer->write('[' . $widthstring . ']');
                    $this->writer->write('endobj');
                    // Descriptor
                    $this->writer->object();
                    $s = '<</Type /FontDescriptor /FontName /' . $subsetname . "\n";
                    foreach ($font['desc'] as $kd => $v) {
                        if ($kd === 'Flags') {
                            $v |= 4;
                            $v &= ~32;
                        }
                        // SYMBOLIC font flag
                        $s .= ' /' . $kd . ' ' . $v . "\n";
                    }
                    $s .= '/FontFile2 ' . ($this->mpdf->n + 2) . ' 0 R';
                    $this->writer->write($s . '>>');
                    $this->writer->write('endobj');
                    // ToUnicode
                    $this->writer->object();
                    $to_uni = "/CIDInit /ProcSet findresource begin\n";
                    $to_uni .= "12 dict begin\n";
                    $to_uni .= "begincmap\n";
                    $to_uni .= "/CIDSystemInfo\n";
                    $to_uni .= "<</Registry (Adobe)\n";
                    $to_uni .= "/Ordering (UCS)\n";
                    $to_uni .= "/Supplement 0\n";
                    $to_uni .= ">> def\n";
                    $to_uni .= "/CMapName /Adobe-Identity-UCS def\n";
                    $to_uni .= "/CMapType 2 def\n";
                    $to_uni .= "1 begincodespacerange\n";
                    $to_uni .= "<00> <FF>\n";
                    // $toUni .= sprintf("<00> <%02s>\n", strtoupper(dechex(count($font['subsets'][$sfid])-1)));
                    $to_uni .= "endcodespacerange\n";
                    $to_uni .= count($font['subsets'][$sfid]) . " beginbfchar\n";
                    $to_uni .= $to_unistring;
                    $to_uni .= "endbfchar\n";
                    $to_uni .= "endcmap\n";
                    $to_uni .= "CMapName currentdict /CMap defineresource pop\n";
                    $to_uni .= "end\n";
                    $to_uni .= "end\n";
                    $this->writer->write('<</Length ' . strlen($to_uni) . '>>');
                    $this->writer->stream($to_uni);
                    $this->writer->write('endobj');
                    // Font file
                    $this->writer->object();
                    $this->writer->write('<</Length ' . strlen($fontstream));
                    $this->writer->write('/Filter /FlateDecode');
                    $this->writer->write('/Length1 ' . $ttfontsize);
                    $this->writer->write('>>');
                    $this->writer->stream($fontstream);
                    $this->writer->write('endobj');
                }
                // foreach subset
                unset($ttf);
            } elseif ($type === 'TTF') {
                // TrueType embedded SUBSETS or FULL
                $this->mpdf->fonts[$k]['n'] = $this->mpdf->n + 1;
                if ($as_subset) {
                    $ssfaid = 'A';
                    $ttf = new Tt_Font_File($this->font_cache, $this->font_descriptor);
                    $fontname = 'MPDFA' . $ssfaid . '+' . $font['name'];
                    $subset = $font['subset'];
                    unset($subset[0]);
                    $ttfontstream = $ttf->make_subset($font['ttffile'], $subset, $font['TTCfontID'], $this->mpdf->debugfonts, $font['useOTL']);
                    $ttfontsize = strlen($ttfontstream);
                    $fontstream = gzcompress($ttfontstream);
                    $code_to_glyph = $ttf->code_to_glyph;
                    unset($code_to_glyph[0]);
                } else {
                    $fontname = $font['name'];
                }
                // Type0 Font
                // A composite font - a font composed of other fonts, organized hierarchically
                $this->writer->object();
                $this->writer->write('<</Type /Font');
                $this->writer->write('/Subtype /Type0');
                $this->writer->write('/BaseFont /' . $fontname . '');
                $this->writer->write('/Encoding /Identity-H');
                $this->writer->write('/DescendantFonts [' . ($this->mpdf->n + 1) . ' 0 R]');
                $this->writer->write('/ToUnicode ' . ($this->mpdf->n + 2) . ' 0 R');
                $this->writer->write('>>');
                $this->writer->write('endobj');
                // CIDFontType2
                // A CIDFont whose glyph descriptions are based on TrueType font technology
                $this->writer->object();
                $this->writer->write('<</Type /Font');
                $this->writer->write('/Subtype /CIDFontType2');
                $this->writer->write('/BaseFont /' . $fontname . '');
                $this->writer->write('/CIDSystemInfo ' . ($this->mpdf->n + 2) . ' 0 R');
                $this->writer->write('/FontDescriptor ' . ($this->mpdf->n + 3) . ' 0 R');
                if (isset($font['desc']['MissingWidth'])) {
                    $this->writer->write('/DW ' . $font['desc']['MissingWidth'] . '');
                }
                if (!$as_subset && $this->font_cache->has($font['fontkey'] . '.cw')) {
                    $w = $this->font_cache->load($font['fontkey'] . '.cw');
                    $this->writer->write($w);
                } else {
                    $this->write_tt_font_widths($font, $as_subset, $as_subset ? $ttf->max_uni : 0);
                }
                $this->writer->write('/CIDToGIDMap ' . ($this->mpdf->n + 4) . ' 0 R');
                $this->writer->write('>>');
                $this->writer->write('endobj');
                // ToUnicode
                $this->writer->object();
                $to_uni = "/CIDInit /ProcSet findresource begin\n";
                $to_uni .= "12 dict begin\n";
                $to_uni .= "begincmap\n";
                $to_uni .= "/CIDSystemInfo\n";
                $to_uni .= "<</Registry (Adobe)\n";
                $to_uni .= "/Ordering (UCS)\n";
                $to_uni .= "/Supplement 0\n";
                $to_uni .= ">> def\n";
                $to_uni .= "/CMapName /Adobe-Identity-UCS def\n";
                $to_uni .= "/CMapType 2 def\n";
                $to_uni .= "1 begincodespacerange\n";
                $to_uni .= "<0000> <FFFF>\n";
                $to_uni .= "endcodespacerange\n";
                $to_uni .= "1 beginbfrange\n";
                $to_uni .= "<0000> <FFFF> <0000>\n";
                $to_uni .= "endbfrange\n";
                $to_uni .= "endcmap\n";
                $to_uni .= "CMapName currentdict /CMap defineresource pop\n";
                $to_uni .= "end\n";
                $to_uni .= "end\n";
                $this->writer->write('<</Length ' . strlen($to_uni) . '>>');
                $this->writer->stream($to_uni);
                $this->writer->write('endobj');
                // CIDSystemInfo dictionary
                $this->writer->object();
                $this->writer->write('<</Registry (Adobe)');
                $this->writer->write('/Ordering (UCS)');
                $this->writer->write('/Supplement 0');
                $this->writer->write('>>');
                $this->writer->write('endobj');
                // Font descriptor
                $this->writer->object();
                $this->writer->write('<</Type /FontDescriptor');
                $this->writer->write('/FontName /' . $fontname);
                foreach ($font['desc'] as $kd => $v) {
                    if ($as_subset && $kd === 'Flags') {
                        $v |= 4;
                        $v &= ~32;
                    }
                    // SYMBOLIC font flag
                    $this->writer->write(' /' . $kd . ' ' . $v);
                }
                if ($font['panose']) {
                    $this->writer->write(' /Style << /Panose <' . $font['panose'] . '> >>');
                }
                if ($as_subset) {
                    $this->writer->write('/FontFile2 ' . ($this->mpdf->n + 2) . ' 0 R');
                } elseif ($font['fontkey']) {
                    // obj ID of a stream containing a TrueType font program
                    $this->writer->write('/FontFile2 ' . $this->mpdf->font_files[$font['fontkey']]['n'] . ' 0 R');
                }
                $this->writer->write('>>');
                $this->writer->write('endobj');
                // Embed CIDToGIDMap
                // A specification of the mapping from CIDs to glyph indices
                if ($as_subset) {
                    $cidtogidmap = str_pad('', 256 * 256 * 2, "\x00");
                    foreach ($code_to_glyph as $cc => $glyph) {
                        $cidtogidmap[$cc * 2] = chr($glyph >> 8);
                        $cidtogidmap[$cc * 2 + 1] = chr($glyph & 0xff);
                    }
                    $cidtogidmap = gzcompress($cidtogidmap);
                } else if ($this->font_cache->has($font['fontkey'] . '.cgm')) {
                    $cidtogidmap = $this->font_cache->load($font['fontkey'] . '.cgm');
                } else {
                    $ttf = new Tt_Font_File($this->font_cache, $this->font_descriptor);
                    $char_to_glyph = $ttf->get_ctg($font['ttffile'], $font['TTCfontID'], $this->mpdf->debugfonts, $font['useOTL']);
                    $cidtogidmap = str_pad('', 256 * 256 * 2, "\x00");
                    foreach ($char_to_glyph as $cc => $glyph) {
                        $cidtogidmap[$cc * 2] = chr($glyph >> 8);
                        $cidtogidmap[$cc * 2 + 1] = chr($glyph & 0xff);
                    }
                    unset($ttf);
                    $cidtogidmap = gzcompress($cidtogidmap);
                    $this->font_cache->binary_write($font['fontkey'] . '.cgm', $cidtogidmap);
                }
                $this->writer->object();
                $this->writer->write('<</Length ' . strlen($cidtogidmap) . '');
                $this->writer->write('/Filter /FlateDecode');
                $this->writer->write('>>');
                $this->writer->stream($cidtogidmap);
                $this->writer->write('endobj');
                // Font file
                if ($as_subset) {
                    $this->writer->object();
                    $this->writer->write('<</Length ' . strlen($fontstream));
                    $this->writer->write('/Filter /FlateDecode');
                    $this->writer->write('/Length1 ' . $ttfontsize);
                    $this->writer->write('>>');
                    $this->writer->stream($fontstream);
                    $this->writer->write('endobj');
                    unset($ttf);
                }
            } else {
                throw new \Mpdf\Mpdf_Exception(sprintf('Unsupported font type: %s (%s)', $type, $name));
            }
        }
    }
    private function write_tt_font_widths(&$font, $as_subset, $max_uni)
    {
        $character = ['startcid' => 1, 'rangeid' => 0, 'prevcid' => -2, 'prevwidth' => -1, 'interval' => false, 'range' => []];
        $font_cache_filename = $font['fontkey'] . '.cw127.json';
        if ($as_subset && $this->font_cache->json_has($font_cache_filename)) {
            $character = $this->font_cache->json_load($font_cache_filename);
            $character['startcid'] = 128;
        }
        // for each character
        $cwlen = $as_subset ? $max_uni + 1 : strlen($font['cw']) / 2;
        for ($cid = $character['startcid']; $cid < $cwlen; $cid++) {
            if ($cid == 128 && $as_subset && !$this->font_cache->has($font_cache_filename)) {
                $character = ['rangeid' => $character['rangeid'], 'prevcid' => $character['prevcid'], 'prevwidth' => $character['prevwidth'], 'interval' => $character['interval'], 'range' => $character['range']];
                $this->font_cache->json_write($font_cache_filename, $character);
            }
            $character1 = isset($font['cw'][$cid * 2]) ? $font['cw'][$cid * 2] : '';
            $character2 = isset($font['cw'][$cid * 2 + 1]) ? $font['cw'][$cid * 2 + 1] : '';
            if ($character1 === "\x00" && $character2 === "\x00") {
                continue;
            }
            $w1 = $character1 === '' ? 0 : ord($character1);
            $w2 = $character2 === '' ? 0 : ord($character2);
            $width = ($w1 << 8) + $w2;
            if ($width === 65535) {
                $width = 0;
            }
            if ($as_subset && $cid > 255 && (!isset($font['subset'][$cid]) || !$font['subset'][$cid])) {
                continue;
            }
            if ($as_subset && $cid > 0xffff) {
                continue;
            }
            // mPDF 6
            if (!isset($font['dw']) || isset($font['dw']) && $width != $font['dw']) {
                if ($cid === $character['prevcid'] + 1) {
                    // consecutive CID
                    if ($width === $character['prevwidth']) {
                        if (isset($character['range'][$character['rangeid']][0]) && $width === $character['range'][$character['rangeid']][0]) {
                            $character['range'][$character['rangeid']][] = $width;
                        } else {
                            array_pop($character['range'][$character['rangeid']]);
                            // new range
                            $character['rangeid'] = $character['prevcid'];
                            $character['range'][$character['rangeid']] = [];
                            $character['range'][$character['rangeid']][] = $character['prevwidth'];
                            $character['range'][$character['rangeid']][] = $width;
                        }
                        $character['interval'] = true;
                        $character['range'][$character['rangeid']]['interval'] = true;
                    } else {
                        if ($character['interval']) {
                            // new range
                            $character['rangeid'] = $cid;
                            $character['range'][$character['rangeid']] = [];
                            $character['range'][$character['rangeid']][] = $width;
                        } else {
                            $character['range'][$character['rangeid']][] = $width;
                        }
                        $character['interval'] = false;
                    }
                } else {
                    // new range
                    $character['rangeid'] = $cid;
                    $character['range'][$character['rangeid']] = [];
                    $character['range'][$character['rangeid']][] = $width;
                    $character['interval'] = false;
                }
                $character['prevcid'] = $cid;
                $character['prevwidth'] = $width;
            }
        }
        $w = $this->write_font_ranges($character['range']);
        $this->writer->write($w);
        if (!$as_subset) {
            $this->font_cache->binary_write($font['fontkey'] . '.cw', $w);
        }
    }
    private function write_font_ranges(&$range)
    {
        // optimize ranges
        $prevk = -1;
        $nextk = -1;
        $prevint = false;
        foreach ($range as $k => $ws) {
            $cws = count($ws);
            if ($k == $nextk and !$prevint and (!isset($ws['interval']) or $cws < 4)) {
                if (isset($range[$k]['interval'])) {
                    unset($range[$k]['interval']);
                }
                $range[$prevk] = array_merge($range[$prevk], $range[$k]);
                unset($range[$k]);
            } else {
                $prevk = $k;
            }
            $nextk = $k + $cws;
            if (isset($ws['interval'])) {
                if ($cws > 3) {
                    $prevint = true;
                } else {
                    $prevint = false;
                }
                unset($range[$k]['interval']);
                --$nextk;
            } else {
                $prevint = false;
            }
        }
        // output data
        $w = '';
        foreach ($range as $k => $ws) {
            if (count(array_count_values($ws)) === 1) {
                // interval mode is more compact
                $w .= ' ' . $k . ' ' . ($k + count($ws) - 1) . ' ' . $ws[0];
            } else {
                // range mode
                $w .= ' ' . $k . ' [ ' . implode(' ', $ws) . ' ]' . "\n";
            }
        }
        return '/W [' . $w . ' ]';
    }
    private function write_font_widths(&$font, $cidoffset = 0)
    {
        ksort($font['cw']);
        unset($font['cw'][65535]);
        $rangeid = 0;
        $range = [];
        $prevcid = -2;
        $prevwidth = -1;
        $interval = false;
        // for each character
        foreach ($font['cw'] as $cid => $width) {
            $cid -= $cidoffset;
            if (!isset($font['dw']) || isset($font['dw']) && $width != $font['dw']) {
                if ($cid === $prevcid + 1) {
                    // consecutive CID
                    if ($width === $prevwidth) {
                        if ($width === $range[$rangeid][0]) {
                            $range[$rangeid][] = $width;
                        } else {
                            array_pop($range[$rangeid]);
                            // new range
                            $rangeid = $prevcid;
                            $range[$rangeid] = [];
                            $range[$rangeid][] = $prevwidth;
                            $range[$rangeid][] = $width;
                        }
                        $interval = true;
                        $range[$rangeid]['interval'] = true;
                    } else {
                        if ($interval) {
                            // new range
                            $rangeid = $cid;
                            $range[$rangeid] = [];
                            $range[$rangeid][] = $width;
                        } else {
                            $range[$rangeid][] = $width;
                        }
                        $interval = false;
                    }
                } else {
                    // new range
                    $rangeid = $cid;
                    $range[$rangeid] = [];
                    $range[$rangeid][] = $width;
                    $interval = false;
                }
                $prevcid = $cid;
                $prevwidth = $width;
            }
        }
        $this->writer->write($this->write_font_ranges($range));
    }
    // from class PDF_Chinese CJK EXTENSIONS
    public function write_type0(&$font)
    {
        // Type0
        $this->writer->write('/Subtype /Type0');
        $this->writer->write('/BaseFont /' . $font['name'] . '-' . $font['CMap']);
        $this->writer->write('/Encoding /' . $font['CMap']);
        $this->writer->write('/DescendantFonts [' . ($this->mpdf->n + 1) . ' 0 R]');
        $this->writer->write('>>');
        $this->writer->write('endobj');
        // CIDFont
        $this->writer->object();
        $this->writer->write('<</Type /Font');
        $this->writer->write('/Subtype /CIDFontType0');
        $this->writer->write('/BaseFont /' . $font['name']);
        $cidinfo = '/Registry ' . $this->writer->string('Adobe');
        $cidinfo .= ' /Ordering ' . $this->writer->string($font['registry']['ordering']);
        $cidinfo .= ' /Supplement ' . $font['registry']['supplement'];
        $this->writer->write('/CIDSystemInfo <<' . $cidinfo . '>>');
        $this->writer->write('/FontDescriptor ' . ($this->mpdf->n + 1) . ' 0 R');
        if (isset($font['MissingWidth'])) {
            $this->writer->write('/DW ' . $font['MissingWidth'] . '');
        }
        $this->write_font_widths($font, 31);
        $this->writer->write('>>');
        $this->writer->write('endobj');
        // Font descriptor
        $this->writer->object();
        $s = '<</Type /FontDescriptor /FontName /' . $font['name'];
        foreach ($font['desc'] as $k => $v) {
            if ($k !== 'Style') {
                $s .= ' /' . $k . ' ' . $v . '';
            }
        }
        $this->writer->write($s . '>>');
        $this->writer->write('endobj');
    }
}