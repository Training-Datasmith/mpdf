<?php

namespace Mpdf\Writer;

use Mpdf\Strict;
use Mpdf\Form;
use Mpdf\Mpdf;
use Mpdf\Pdf\Protection;
use Mpdf\Psr_Log_Aware_Trait\Psr_Log_Aware_Trait;
use Mpdf\Utils\Pdf_Date;
use Psr\Log\Logger_Interface;
class Metadata_Writer implements \Psr\Log\Logger_Aware_Interface
{
    use Strict;
    use Psr_Log_Aware_Trait;
    /**
     * @var \Mpdf\Mpdf
     */
    private $mpdf;
    /**
     * @var \Mpdf\Writer\BaseWriter
     */
    private $writer;
    /**
     * @var \Mpdf\Form
     */
    private $form;
    /**
     * @var \Mpdf\Pdf\Protection
     */
    private $protection;
    public function __construct(Mpdf $mpdf, Base_Writer $writer, Form $form, Protection $protection, Logger_Interface $logger)
    {
        $this->mpdf = $mpdf;
        $this->writer = $writer;
        $this->form = $form;
        $this->protection = $protection;
        $this->logger = $logger;
    }
    public function write_metadata()
    {
        $this->writer->object();
        $this->mpdf->metadata_root = $this->mpdf->n;
        $z = date('O');
        // +0200
        $offset = substr($z, 0, 3) . ':' . substr($z, 3, 2);
        $creation_date = date('Y-m-d\TH:i:s') . $offset;
        // 2006-03-10T10:47:26-05:00 2006-06-19T09:05:17Z
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xfff) | 0x4000, random_int(0, 0x3fff) | 0x8000, random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff));
        $m = '<?xpacket begin="' . chr(239) . chr(187) . chr(191) . '" id="W5M0MpCehiHzreSzNTczkc9d"?>' . "\n";
        // begin = FEFF BOM
        $m .= ' <x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="3.1-701">' . "\n";
        $m .= '  <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">' . "\n";
        $m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:pdf="http://ns.adobe.com/pdf/1.3/">' . "\n";
        $m .= '    <pdf:Producer>' . htmlspecialchars($this->get_producer_string(), ENT_QUOTES | ENT_XML1) . '</pdf:Producer>' . "\n";
        if (!empty($this->mpdf->keywords)) {
            $m .= '    <pdf:Keywords>' . htmlspecialchars($this->mpdf->keywords, ENT_QUOTES | ENT_XML1) . '</pdf:Keywords>' . "\n";
        }
        $m .= '   </rdf:Description>' . "\n";
        $m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:xmp="http://ns.adobe.com/xap/1.0/">' . "\n";
        $m .= '    <xmp:CreateDate>' . $creation_date . '</xmp:CreateDate>' . "\n";
        $m .= '    <xmp:ModifyDate>' . $creation_date . '</xmp:ModifyDate>' . "\n";
        $m .= '    <xmp:MetadataDate>' . $creation_date . '</xmp:MetadataDate>' . "\n";
        if (!empty($this->mpdf->creator)) {
            $m .= '    <xmp:CreatorTool>' . htmlspecialchars($this->mpdf->creator, ENT_QUOTES | ENT_XML1) . '</xmp:CreatorTool>' . "\n";
        }
        $m .= '   </rdf:Description>' . "\n";
        // DC elements
        $m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n";
        $m .= '    <dc:format>application/pdf</dc:format>' . "\n";
        if (!empty($this->mpdf->title)) {
            $m .= '    <dc:title>
	 <rdf:Alt>
	  <rdf:li xml:lang="x-default">' . htmlspecialchars($this->mpdf->title, ENT_QUOTES | ENT_XML1) . '</rdf:li>
	 </rdf:Alt>
	</dc:title>' . "\n";
        }
        if (!empty($this->mpdf->keywords)) {
            $m .= '    <dc:subject>
	 <rdf:Bag>
	  <rdf:li>' . htmlspecialchars($this->mpdf->keywords, ENT_QUOTES | ENT_XML1) . '</rdf:li>
	 </rdf:Bag>
	</dc:subject>' . "\n";
        }
        if (!empty($this->mpdf->subject)) {
            $m .= '    <dc:description>
	 <rdf:Alt>
	  <rdf:li xml:lang="x-default">' . htmlspecialchars($this->mpdf->subject, ENT_QUOTES | ENT_XML1) . '</rdf:li>
	 </rdf:Alt>
	</dc:description>' . "\n";
        }
        if (!empty($this->mpdf->author)) {
            $m .= '    <dc:creator>
	 <rdf:Seq>
	  <rdf:li>' . htmlspecialchars($this->mpdf->author, ENT_QUOTES | ENT_XML1) . '</rdf:li>
	 </rdf:Seq>
	</dc:creator>' . "\n";
        }
        $m .= '   </rdf:Description>' . "\n";
        if (!empty($this->mpdf->additional_xmp_rdf)) {
            $m .= $this->mpdf->additional_xmp_rdf;
        }
        // This bit is specific to PDFX-1a
        if ($this->mpdf->PDFX) {
            $m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:pdfx="http://ns.adobe.com/pdfx/1.3/" pdfx:Apag_PDFX_Checkup="1.3" pdfx:GTS_PDFXConformance="PDF/X-1a:2003" pdfx:GTS_PDFXVersion="PDF/X-1:2003"/>' . "\n";
        } elseif ($this->mpdf->PDFA) {
            if (strpos($this->mpdf->pdf_aversion, '-') === false) {
                throw new \Mpdf\Mpdf_Exception(sprintf('PDFA version (%s) is not valid. (Use: 1-B, 3-B, etc.)', $this->mpdf->pdf_aversion));
            }
            list($part, $conformance) = explode('-', strtoupper($this->mpdf->pdf_aversion));
            $m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/" >' . "\n";
            $m .= '    <pdfaid:part>' . $part . '</pdfaid:part>' . "\n";
            $m .= '    <pdfaid:conformance>' . $conformance . '</pdfaid:conformance>' . "\n";
            if ($part === '1' && $conformance === 'B') {
                $m .= '    <pdfaid:amd>2005</pdfaid:amd>' . "\n";
            }
            $m .= '   </rdf:Description>' . "\n";
        }
        $m .= '   <rdf:Description rdf:about="uuid:' . $uuid . '" xmlns:xmpMM="http://ns.adobe.com/xap/1.0/mm/">' . "\n";
        $m .= '    <xmpMM:DocumentID>uuid:' . $uuid . '</xmpMM:DocumentID>' . "\n";
        $m .= '   </rdf:Description>' . "\n";
        $m .= '  </rdf:RDF>' . "\n";
        $m .= ' </x:xmpmeta>' . "\n";
        $m .= str_repeat(str_repeat(' ', 100) . "\n", 20);
        // 2-4kB whitespace padding required
        $m .= '<?xpacket end="w"?>';
        // "r" read only
        $this->writer->write('<</Type/Metadata/Subtype/XML/Length ' . strlen($m) . '>>');
        $this->writer->stream($m);
        $this->writer->write('endobj');
    }
    public function write_info()
    {
        $this->writer->write('/Producer ' . $this->writer->utf16big_endian_text_string($this->get_producer_string()));
        if (!empty($this->mpdf->title)) {
            $this->writer->write('/Title ' . $this->writer->utf16big_endian_text_string($this->mpdf->title));
        }
        if (!empty($this->mpdf->subject)) {
            $this->writer->write('/Subject ' . $this->writer->utf16big_endian_text_string($this->mpdf->subject));
        }
        if (!empty($this->mpdf->author)) {
            $this->writer->write('/Author ' . $this->writer->utf16big_endian_text_string($this->mpdf->author));
        }
        if (!empty($this->mpdf->keywords)) {
            $this->writer->write('/Keywords ' . $this->writer->utf16big_endian_text_string($this->mpdf->keywords));
        }
        if (!empty($this->mpdf->creator)) {
            $this->writer->write('/Creator ' . $this->writer->utf16big_endian_text_string($this->mpdf->creator));
        }
        foreach ($this->mpdf->custom_properties as $key => $value) {
            // Sanitize key to valid PDF Name characters only (alphanumeric, underscore, hyphen, dot).
            // Keys containing whitespace, newlines, or PDF delimiters would corrupt the PDF structure
            // or allow injection of arbitrary PDF operators.
            $key = preg_replace('/[^0-9A-Za-z_.\-]/', '', (string) $key);
            if ($key === '') {
                continue;
            }
            $this->writer->write('/' . $key . ' ' . $this->writer->utf16big_endian_text_string($value));
        }
        $now = Pdf_Date::format(time());
        $this->writer->write('/CreationDate ' . $this->writer->string('D:' . $now));
        $this->writer->write('/ModDate ' . $this->writer->string('D:' . $now));
        if ($this->mpdf->PDFX) {
            $this->writer->write('/Trapped/False');
            $this->writer->write('/GTS_PDFXVersion(PDF/X-1a:2003)');
        }
    }
    public function write_output_intent()
    {
        $this->writer->object();
        $this->mpdf->output_intent_root = $this->mpdf->n;
        $this->writer->write('<</Type /OutputIntent');
        $icc_profile = str_replace('_', ' ', basename($this->mpdf->icc_profile, '.icc'));
        if ($this->mpdf->PDFA) {
            $this->writer->write('/S /GTS_PDFA1');
            if ($this->mpdf->icc_profile) {
                $this->writer->write('/Info (' . $icc_profile . ')');
                $this->writer->write('/OutputConditionIdentifier (Custom)');
                $this->writer->write('/OutputCondition ()');
            } else {
                $this->writer->write('/Info (sRGB IEC61966-2.1)');
                $this->writer->write('/OutputConditionIdentifier (sRGB IEC61966-2.1)');
                $this->writer->write('/OutputCondition ()');
            }
            $this->writer->write('/DestOutputProfile ' . ($this->mpdf->n + 1) . ' 0 R');
        } elseif ($this->mpdf->PDFX) {
            // always a CMYK profile
            $this->writer->write('/S /GTS_PDFX');
            if ($this->mpdf->icc_profile) {
                $this->writer->write('/Info (' . $icc_profile . ')');
                $this->writer->write('/OutputConditionIdentifier (Custom)');
                $this->writer->write('/OutputCondition ()');
                $this->writer->write('/DestOutputProfile ' . ($this->mpdf->n + 1) . ' 0 R');
            } else {
                $this->writer->write('/Info (CGATS TR 001)');
                $this->writer->write('/OutputConditionIdentifier (CGATS TR 001)');
                $this->writer->write('/OutputCondition (CGATS TR 001 (SWOP))');
                $this->writer->write('/RegistryName (http://www.color.org)');
            }
        }
        $this->writer->write('>>');
        $this->writer->write('endobj');
        if ($this->mpdf->PDFX && !$this->mpdf->icc_profile) {
            return;
        }
        $this->writer->object();
        if ($this->mpdf->icc_profile) {
            if (!file_exists($this->mpdf->icc_profile)) {
                throw new \Mpdf\Mpdf_Exception(sprintf('Unable to find ICC profile "%s"', $this->mpdf->icc_profile));
            }
            $s = file_get_contents($this->mpdf->icc_profile);
        } else {
            $s = file_get_contents(__DIR__ . '/../../data/iccprofiles/sRGB_IEC61966-2-1.icc');
        }
        if ($this->mpdf->compress) {
            $s = gzcompress($s);
        }
        $this->writer->write('<<');
        if ($this->mpdf->PDFX || $this->mpdf->PDFA && $this->mpdf->restrict_color_space === 3) {
            $this->writer->write('/N 4');
        } else {
            $this->writer->write('/N 3');
        }
        if ($this->mpdf->compress) {
            $this->writer->write('/Filter /FlateDecode ');
        }
        $this->writer->write('/Length ' . strlen($s) . '>>');
        $this->writer->stream($s);
        $this->writer->write('endobj');
    }
    public function write_associated_files()
    {
        if (!function_exists('gzcompress')) {
            throw new \Mpdf\Mpdf_Exception('ext-zlib is required for compression of associated files');
        }
        // for each file, we create the spec object + the stream object
        foreach ($this->mpdf->associated_files as $k => $file) {
            // spec
            $this->writer->object();
            $this->mpdf->associated_files[$k]['_root'] = $this->mpdf->n;
            // we store the root ref of object for future reference (e.g. /EmbeddedFiles catalog)
            $this->writer->write('<</F ' . $this->writer->string($file['name']));
            if ($file['description']) {
                $this->writer->write('/Desc ' . $this->writer->string($file['description']));
            }
            $this->writer->write('/Type /Filespec');
            $this->writer->write('/EF <<');
            $this->writer->write('/F ' . ($this->mpdf->n + 1) . ' 0 R');
            $this->writer->write('/UF ' . ($this->mpdf->n + 1) . ' 0 R');
            $this->writer->write('>>');
            if ($file['AFRelationship']) {
                $this->writer->write('/AFRelationship /' . $file['AFRelationship']);
            }
            $this->writer->write('/UF ' . $this->writer->string($file['name']));
            $this->writer->write('>>');
            $this->writer->write('endobj');
            $file_content = null;
            if (isset($file['path'])) {
                $file_content = @file_get_contents($file['path']);
            } elseif (isset($file['content'])) {
                $file_content = $file['content'];
            }
            if (!$file_content) {
                throw new \Mpdf\Mpdf_Exception(sprintf('Cannot access associated file - %s', $file['path']));
            }
            $filestream = gzcompress($file_content);
            $this->writer->object();
            $this->writer->write('<</Type /EmbeddedFile');
            if ($file['mime']) {
                $this->writer->write('/Subtype /' . $this->writer->escape_slashes($file['mime']));
            }
            $this->writer->write('/Length ' . strlen($filestream));
            $this->writer->write('/Filter /FlateDecode');
            if (isset($file['path'])) {
                $this->writer->write('/Params <</ModDate ' . $this->writer->string('D:' . Pdf_Date::format(filemtime($file['path']))) . ' >>');
            } else {
                $this->writer->write('/Params <</ModDate ' . $this->writer->string('D:' . Pdf_Date::format(time())) . ' >>');
            }
            $this->writer->write('>>');
            $this->writer->stream($filestream);
            $this->writer->write('endobj');
        }
        // AF array
        $this->writer->object();
        $refs = [];
        foreach ($this->mpdf->associated_files as $file) {
            $refs[] = '' . $file['_root'] . ' 0 R';
        }
        $this->writer->write('[' . implode(' ', $refs) . ']');
        $this->writer->write('endobj');
        $this->mpdf->associated_files_root = $this->mpdf->n;
    }
    public function write_catalog()
    {
        $this->writer->write('/Type /Catalog');
        $this->writer->write('/Pages 1 0 R');
        if (is_string($this->mpdf->current_lang)) {
            $this->writer->write(sprintf('/Lang (%s)', $this->mpdf->current_lang));
        } elseif (is_string($this->mpdf->default_lang)) {
            $this->writer->write(sprintf('/Lang (%s)', $this->mpdf->default_lang));
        }
        if ($this->mpdf->zoom_mode === 'fullpage') {
            $this->writer->write('/OpenAction [3 0 R /Fit]');
        } elseif ($this->mpdf->zoom_mode === 'fullwidth') {
            $this->writer->write('/OpenAction [3 0 R /FitH null]');
        } elseif ($this->mpdf->zoom_mode === 'real') {
            $this->writer->write('/OpenAction [3 0 R /XYZ null null 1]');
        } elseif (!is_string($this->mpdf->zoom_mode)) {
            $this->writer->write('/OpenAction [3 0 R /XYZ null null ' . $this->mpdf->zoom_mode / 100 . ']');
        } elseif ($this->mpdf->zoom_mode === 'none') {
            // do not write any zoom mode / OpenAction
        } else {
            $this->writer->write('/OpenAction [3 0 R /XYZ null null null]');
        }
        if ($this->mpdf->layout_mode === 'single') {
            $this->writer->write('/PageLayout /SinglePage');
        } elseif ($this->mpdf->layout_mode === 'continuous') {
            $this->writer->write('/PageLayout /OneColumn');
        } elseif ($this->mpdf->layout_mode === 'twoleft') {
            $this->writer->write('/PageLayout /TwoColumnLeft');
        } elseif ($this->mpdf->layout_mode === 'tworight') {
            $this->writer->write('/PageLayout /TwoColumnRight');
        } elseif ($this->mpdf->layout_mode === 'two') {
            if ($this->mpdf->mirror_margins) {
                $this->writer->write('/PageLayout /TwoColumnRight');
            } else {
                $this->writer->write('/PageLayout /TwoColumnLeft');
            }
        }
        // Bookmarks
        if (count($this->mpdf->b_moutlines) > 0) {
            $this->writer->write('/Outlines ' . $this->mpdf->outline_root . ' 0 R');
            $this->writer->write('/PageMode /UseOutlines');
        }
        // Fullscreen
        if (is_int(strpos($this->mpdf->display_preferences, 'FullScreen'))) {
            $this->writer->write('/PageMode /FullScreen');
        }
        // Metadata
        if ($this->mpdf->PDFA || $this->mpdf->PDFX) {
            $this->writer->write('/Metadata ' . $this->mpdf->metadata_root . ' 0 R');
        }
        // OutputIntents
        if ($this->mpdf->PDFA || $this->mpdf->PDFX || $this->mpdf->icc_profile) {
            $this->writer->write('/OutputIntents [' . $this->mpdf->output_intent_root . ' 0 R]');
        }
        // Associated files
        if ($this->mpdf->associated_files_root) {
            $this->writer->write('/AF ' . $this->mpdf->associated_files_root . ' 0 R');
            $names = [];
            foreach ($this->mpdf->associated_files as $file) {
                $names[] = $this->writer->string($file['name']) . ' ' . $file['_root'] . ' 0 R';
            }
            $this->writer->write('/Names << /EmbeddedFiles << /Names [' . implode(' ', $names) . '] >> >>');
        }
        // Forms
        if (count($this->form->forms) > 0) {
            $this->form->_put_forms_catalog();
        }
        if ($this->mpdf->js !== null) {
            $this->writer->write('/Names << /JavaScript ' . $this->mpdf->n_js . ' 0 R >> ');
        }
        if ($this->mpdf->display_preferences || $this->mpdf->directionality === 'rtl' || $this->mpdf->mirror_margins) {
            $this->writer->write('/ViewerPreferences<<');
            if (is_int(strpos($this->mpdf->display_preferences, 'HideMenubar'))) {
                $this->writer->write('/HideMenubar true');
            }
            if (is_int(strpos($this->mpdf->display_preferences, 'HideToolbar'))) {
                $this->writer->write('/HideToolbar true');
            }
            if (is_int(strpos($this->mpdf->display_preferences, 'HideWindowUI'))) {
                $this->writer->write('/HideWindowUI true');
            }
            if (is_int(strpos($this->mpdf->display_preferences, 'DisplayDocTitle'))) {
                $this->writer->write('/DisplayDocTitle true');
            }
            if (is_int(strpos($this->mpdf->display_preferences, 'CenterWindow'))) {
                $this->writer->write('/CenterWindow true');
            }
            if (is_int(strpos($this->mpdf->display_preferences, 'FitWindow'))) {
                $this->writer->write('/FitWindow true');
            }
            // PrintScaling is PDF 1.6 spec.
            if (!$this->mpdf->PDFA && !$this->mpdf->PDFX && is_int(strpos($this->mpdf->display_preferences, 'NoPrintScaling'))) {
                $this->writer->write('/PrintScaling /None');
            }
            if ($this->mpdf->directionality === 'rtl') {
                $this->writer->write('/Direction /R2L');
            }
            // Duplex is PDF 1.7 spec.
            if ($this->mpdf->mirror_margins && !$this->mpdf->PDFA && !$this->mpdf->PDFX) {
                // if ($this->mpdf->DefOrientation=='P') $this->writer->write('/Duplex /DuplexFlipShortEdge');
                $this->writer->write('/Duplex /DuplexFlipLongEdge');
                // PDF v1.7+
            }
            $this->writer->write('>>');
        }
        if ($this->mpdf->open_layer_pane && ($this->mpdf->has_oc || count($this->mpdf->layers))) {
            $this->writer->write('/PageMode /UseOC');
        }
        if ($this->mpdf->has_oc || count($this->mpdf->layers)) {
            $p = $v = $h = $l = $loff = $lall = $as = '';
            if ($this->mpdf->has_oc) {
                if (($this->mpdf->has_oc & 1) === 1) {
                    $p = $this->mpdf->n_ocg_print . ' 0 R';
                }
                if (($this->mpdf->has_oc & 2) === 2) {
                    $v = $this->mpdf->n_ocg_view . ' 0 R';
                }
                if (($this->mpdf->has_oc & 4) === 4) {
                    $h = $this->mpdf->n_ocg_hidden . ' 0 R';
                }
                $as = "<</Event /Print /OCGs [{$p} {$v} {$h}] /Category [/Print]>> <</Event /View /OCGs [{$p} {$v} {$h}] /Category [/View]>>";
            }
            if (count($this->mpdf->layers)) {
                foreach ($this->mpdf->layers as $k => $layer) {
                    if (isset($this->mpdf->layer_details[$k]) && strtolower($this->mpdf->layer_details[$k]['state']) === 'hidden') {
                        $loff .= $layer['n'] . ' 0 R ';
                    } else {
                        $l .= $layer['n'] . ' 0 R ';
                    }
                    $lall .= $layer['n'] . ' 0 R ';
                }
            }
            $this->writer->write("/OCProperties <</OCGs [{$p} {$v} {$h} {$lall}] /D <</ON [{$p} {$l}] /OFF [{$v} {$h} {$loff}] ");
            $this->writer->write("/Order [{$v} {$p} {$h} {$lall}] ");
            if ($as) {
                $this->writer->write("/AS [{$as}] ");
            }
            $this->writer->write('>>>>');
        }
    }
    /**
     * @since 5.7.2
     */
    public function write_annotations()
    {
        $nb = $this->mpdf->page;
        for ($n = 1; $n <= $nb; $n++) {
            if (isset($this->mpdf->page_links[$n]) || isset($this->mpdf->page_annots[$n]) || count($this->form->forms) > 0) {
                $w_pt = $this->mpdf->page_dim[$n]['w'] * Mpdf::SCALE;
                $h_pt = $this->mpdf->page_dim[$n]['h'] * Mpdf::SCALE;
                // Links
                if (isset($this->mpdf->page_links[$n])) {
                    foreach ($this->mpdf->page_links[$n] as $key => $pl) {
                        $this->writer->object();
                        $annot = '';
                        $rect = sprintf('%.3F %.3F %.3F %.3F', $pl[0], $pl[1], $pl[0] + $pl[2], $pl[1] - $pl[3]);
                        $annot .= '<</Type /Annot /Subtype /Link /Rect [' . $rect . ']';
                        // Removed as causing undesired effects in Chrome PDF viewer https://github.com/mpdf/mpdf/issues/283
                        // $annot .= ' /Contents ' . $this->writer->utf16BigEndianTextString($pl[4]);
                        $annot .= ' /NM ' . $this->writer->string(sprintf('%04u-%04u', $n, $key));
                        $annot .= ' /M ' . $this->writer->string('D:' . date('YmdHis'));
                        $annot .= ' /Border [0 0 0]';
                        // Use this (instead of /Border) to specify border around link
                        // $annot .= ' /BS <</W 1';	// Width on points; 0 = no line
                        // $annot .= ' /S /D';		// style - [S]olid, [D]ashed, [B]eveled, [I]nset, [U]nderline
                        // $annot .= ' /D [3 2]';		// Dash array - if dashed
                        // $annot .= ' >>';
                        // $annot .= ' /C [1 0 0]';	// Color RGB
                        if ($this->mpdf->PDFA || $this->mpdf->PDFX) {
                            $annot .= ' /F 28';
                        }
                        if (strpos($pl[4], '@') === 0) {
                            $p = substr($pl[4], 1);
                            // $h=isset($this->mpdf->OrientationChanges[$p]) ? $wPt : $hPt;
                            $htarg = $this->mpdf->page_dim[$p]['h'] * Mpdf::SCALE;
                            $annot .= sprintf(' /Dest [%d 0 R /XYZ 0 %.3F null]>>', 1 + 2 * $p, $htarg);
                        } elseif (is_string($pl[4])) {
                            $annot .= ' /A <</S /URI /URI ' . $this->writer->string($pl[4]) . '>> >>';
                        } else {
                            $l = $this->mpdf->links[$pl[4]];
                            // may not be set if #link points to non-existent target
                            if (isset($this->mpdf->page_dim[$l[0]]['h'])) {
                                $htarg = $this->mpdf->page_dim[$l[0]]['h'] * Mpdf::SCALE;
                            } else {
                                $htarg = $this->mpdf->h * Mpdf::SCALE;
                            }
                            // doesn't really matter
                            $annot .= sprintf(' /Dest [%d 0 R /XYZ 0 %.3F null]>>', 1 + 2 * $l[0], $htarg - $l[1] * Mpdf::SCALE);
                        }
                        $this->writer->write($annot);
                        $this->writer->write('endobj');
                    }
                }
                /* -- ANNOTATIONS -- */
                if (isset($this->mpdf->page_annots[$n])) {
                    foreach ($this->mpdf->page_annots[$n] as $key => $pl) {
                        $file_attachment = (bool) $pl['opt']['file'];
                        if ($file_attachment && !$this->mpdf->allow_annotation_files) {
                            $this->logger->warning('Embedded files for annotations have to be allowed explicitly with "allowAnnotationFiles" config key');
                            $file_attachment = false;
                        }
                        $this->writer->object();
                        $annot = '';
                        $pl['opt'] = array_change_key_case($pl['opt'], CASE_LOWER);
                        $x = $pl['x'];
                        if ($this->mpdf->annot_margin != 0 || $x == 0 || $x < 0) {
                            // Odd page, intentional non-strict comparison
                            $x = $w_pt / Mpdf::SCALE - $this->mpdf->annot_margin;
                        }
                        $w = $h = 0;
                        $a = $x * Mpdf::SCALE;
                        $b = $h_pt - $pl['y'] * Mpdf::SCALE;
                        $annot .= '<</Type /Annot ';
                        if ($file_attachment) {
                            $annot .= '/Subtype /FileAttachment ';
                            // Need to set a size for FileAttachment icons
                            if ($pl['opt']['icon'] === 'Paperclip') {
                                $w = 8.234999999999999;
                                $h = 20;
                            } elseif ($pl['opt']['icon'] === 'Tag') {
                                $w = 20;
                                $h = 16;
                            } elseif ($pl['opt']['icon'] === 'Graph') {
                                $w = 20;
                                $h = 20;
                            } else {
                                $w = 14;
                                $h = 20;
                            }
                            // PushPin
                            $f = $pl['opt']['file'];
                            $f = preg_replace('/^.*\//', '', $f);
                            $f = preg_replace('/[^a-zA-Z0-9._]/', '', $f);
                            $annot .= '/FS <</Type /Filespec /F (' . $f . ')';
                            $annot .= '/EF <</F ' . ($this->mpdf->n + 1) . ' 0 R>>';
                            $annot .= '>>';
                        } else {
                            $annot .= '/Subtype /Text';
                            $w = 20;
                            $h = 20;
                            // mPDF 6
                        }
                        $rect = sprintf('%.3F %.3F %.3F %.3F', $a, $b - $h, $a + $w, $b);
                        $annot .= ' /Rect [' . $rect . ']';
                        // contents = description of file in free text
                        $annot .= ' /Contents ' . $this->writer->utf16big_endian_text_string($pl['txt']);
                        $annot .= ' /NM ' . $this->writer->string(sprintf('%04u-%04u', $n, 2000 + $key));
                        $annot .= ' /M ' . $this->writer->string('D:' . date('YmdHis'));
                        $annot .= ' /CreationDate ' . $this->writer->string('D:' . date('YmdHis'));
                        $annot .= ' /Border [0 0 0]';
                        if ($this->mpdf->PDFA || $this->mpdf->PDFX) {
                            $annot .= ' /F 28';
                            $annot .= ' /CA 1';
                        } elseif ($pl['opt']['ca'] > 0) {
                            $annot .= ' /CA ' . $pl['opt']['ca'];
                        }
                        $annotcolor = ' /C [';
                        if (isset($pl['opt']['c']) && $pl['opt']['c']) {
                            $col = $pl['opt']['c'];
                            if ($col[0] == 3 || $col[0] == 5) {
                                $annotcolor .= sprintf('%.3F %.3F %.3F', ord($col[1]) / 255, ord($col[2]) / 255, ord($col[3]) / 255);
                            } elseif ($col[0] == 1) {
                                $annotcolor .= sprintf('%.3F', ord($col[1]) / 255);
                            } elseif ($col[0] == 4 || $col[0] == 6) {
                                $annotcolor .= sprintf('%.3F %.3F %.3F %.3F', ord($col[1]) / 100, ord($col[2]) / 100, ord($col[3]) / 100, ord($col[4]) / 100);
                            } else {
                                $annotcolor .= '1 1 0';
                            }
                        } else {
                            $annotcolor .= '1 1 0';
                        }
                        $annotcolor .= ']';
                        $annot .= $annotcolor;
                        // Usually Author
                        // Use as Title for fileattachment
                        if (isset($pl['opt']['t']) && is_string($pl['opt']['t'])) {
                            $annot .= ' /T ' . $this->writer->utf16big_endian_text_string($pl['opt']['t']);
                        }
                        if ($file_attachment) {
                            $iconsapp = ['Paperclip', 'Graph', 'PushPin', 'Tag'];
                        } else {
                            $iconsapp = ['Comment', 'Help', 'Insert', 'Key', 'NewParagraph', 'Note', 'Paragraph'];
                        }
                        if (isset($pl['opt']['icon']) && in_array($pl['opt']['icon'], $iconsapp)) {
                            $annot .= ' /Name /' . $pl['opt']['icon'];
                        } elseif ($file_attachment) {
                            $annot .= ' /Name /PushPin';
                        } else {
                            $annot .= ' /Name /Note';
                        }
                        if (!$file_attachment) {
                            // Subj is PDF 1.5 spec.
                            if (!$this->mpdf->PDFA && !$this->mpdf->PDFX && isset($pl['opt']['subj'])) {
                                $annot .= ' /Subj ' . $this->writer->utf16big_endian_text_string($pl['opt']['subj']);
                            }
                            if (!empty($pl['opt']['popup'])) {
                                $annot .= ' /Open true';
                                $annot .= ' /Popup ' . ($this->mpdf->n + 1) . ' 0 R';
                            } else {
                                $annot .= ' /Open false';
                            }
                        }
                        $annot .= ' /P ' . $pl['pageobj'] . ' 0 R';
                        $annot .= '>>';
                        $this->writer->write($annot);
                        $this->writer->write('endobj');
                        if ($file_attachment) {
                            $file = @file_get_contents($pl['opt']['file']);
                            if (!$file) {
                                throw new \Mpdf\Mpdf_Exception('mPDF Error: Cannot access file attachment - ' . $pl['opt']['file']);
                            }
                            $filestream = gzcompress($file);
                            $this->writer->object();
                            $this->writer->write('<</Type /EmbeddedFile');
                            $this->writer->write('/Length ' . strlen($filestream));
                            $this->writer->write('/Filter /FlateDecode');
                            $this->writer->write('>>');
                            $this->writer->stream($filestream);
                            $this->writer->write('endobj');
                        } elseif (!empty($pl['opt']['popup'])) {
                            $this->writer->object();
                            $annot = '';
                            if (is_array($pl['opt']['popup']) && isset($pl['opt']['popup'][0])) {
                                $x = $pl['opt']['popup'][0] * Mpdf::SCALE;
                            } else {
                                $x = $pl['x'] * Mpdf::SCALE;
                            }
                            if (is_array($pl['opt']['popup']) && isset($pl['opt']['popup'][1])) {
                                $y = $h_pt - $pl['opt']['popup'][1] * Mpdf::SCALE;
                            } else {
                                $y = $h_pt - $pl['y'] * Mpdf::SCALE;
                            }
                            if (is_array($pl['opt']['popup']) && isset($pl['opt']['popup'][2])) {
                                $w = $pl['opt']['popup'][2] * Mpdf::SCALE;
                            } else {
                                $w = 180;
                            }
                            if (is_array($pl['opt']['popup']) && isset($pl['opt']['popup'][3])) {
                                $h = $pl['opt']['popup'][3] * Mpdf::SCALE;
                            } else {
                                $h = 120;
                            }
                            $rect = sprintf('%.3F %.3F %.3F %.3F', $x, $y - $h, $x + $w, $y);
                            $annot .= '<</Type /Annot /Subtype /Popup /Rect [' . $rect . ']';
                            $annot .= ' /M ' . $this->writer->string('D:' . date('YmdHis'));
                            if ($this->mpdf->PDFA || $this->mpdf->PDFX) {
                                $annot .= ' /F 28';
                            }
                            $annot .= ' /Parent ' . ($this->mpdf->n - 1) . ' 0 R';
                            $annot .= '>>';
                            $this->writer->write($annot);
                            $this->writer->write('endobj');
                        }
                    }
                }
                // Active Forms
                if (count($this->form->forms) > 0) {
                    $this->form->_put_form_items($n, $h_pt);
                }
            }
        }
        // Active Forms - Radio Button Group entries
        // Output Radio Button Group form entries (radio_on_obj_id already determined)
        if (count($this->form->form_radio_groups)) {
            $this->form->_put_radio_items($n);
        }
    }
    public function write_encryption()
    {
        $this->writer->write('/Filter /Standard');
        if ($this->protection->get_use_rc128encryption()) {
            $this->writer->write('/V 2');
            $this->writer->write('/R 3');
            $this->writer->write('/Length 128');
        } else {
            $this->writer->write('/V 1');
            $this->writer->write('/R 2');
        }
        $this->writer->write('/O (' . $this->writer->escape($this->protection->get_o_value()) . ')');
        $this->writer->write('/U (' . $this->writer->escape($this->protection->get_u_value()) . ')');
        $this->writer->write('/P ' . $this->protection->get_p_value());
    }
    public function write_trailer()
    {
        $this->writer->write('/Size ' . ($this->mpdf->n + 1));
        $this->writer->write('/Root ' . $this->mpdf->n . ' 0 R');
        $this->writer->write('/Info ' . $this->mpdf->info_root . ' 0 R');
        if ($this->mpdf->encrypted) {
            $this->writer->write('/Encrypt ' . $this->mpdf->enc_obj_id . ' 0 R');
            $this->writer->write('/ID [<' . $this->protection->get_uniqid() . '> <' . $this->protection->get_uniqid() . '>]');
        } else {
            $uniqid = md5(time() . $this->mpdf->buffer->get_hash());
            $this->writer->write('/ID [<' . $uniqid . '> <' . $uniqid . '>]');
        }
    }
    private function get_version_string()
    {
        $return = Mpdf::VERSION;
        $head_file = __DIR__ . '/../../.git/HEAD';
        if (file_exists($head_file)) {
            $ref = file($head_file);
            $path = explode('/', $ref[0], 3);
            $branch = isset($path[2]) ? trim($path[2]) : '';
            $rev_file = __DIR__ . '/../../.git/refs/heads/' . $branch;
            if ($branch && file_exists($rev_file)) {
                $rev = file($rev_file);
                $rev = substr($rev[0], 0, 7);
                $return .= ' (' . $rev . ')';
            }
        }
        return $return;
    }
    private function get_producer_string()
    {
        return 'mPDF' . ($this->mpdf->expose_version ? ' ' . $this->get_version_string() : '');
    }
}