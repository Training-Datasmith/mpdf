<?php

namespace Mpdf\Writer;

use Mpdf\Strict;
use Mpdf\Mpdf;
use Mpdf\Psr_Log_Aware_Trait\Psr_Log_Aware_Trait;
use Psr\Log\Logger_Interface;
final class Resource_Writer implements \Psr\Log\Logger_Aware_Interface
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
     * @var \Mpdf\Writer\ColorWriter
     */
    private $color_writer;
    /**
     * @var \Mpdf\Writer\FontWriter
     */
    private $font_writer;
    /**
     * @var \Mpdf\Writer\ImageWriter
     */
    private $image_writer;
    /**
     * @var \Mpdf\Writer\FormWriter
     */
    private $form_writer;
    /**
     * @var \Mpdf\Writer\OptionalContentWriter
     */
    private $optional_content_writer;
    /**
     * @var \Mpdf\Writer\BackgroundWriter
     */
    private $background_writer;
    /**
     * @var \Mpdf\Writer\BookmarkWriter
     */
    private $bookmark_writer;
    /**
     * @var \Mpdf\Writer\MetadataWriter
     */
    private $metadata_writer;
    /**
     * @var \Mpdf\Writer\JavaScriptWriter
     */
    private $java_script_writer;
    public function __construct(Mpdf $mpdf, Base_Writer $writer, Color_Writer $color_writer, Font_Writer $font_writer, Image_Writer $image_writer, Form_Writer $form_writer, Optional_Content_Writer $optional_content_writer, Background_Writer $background_writer, Bookmark_Writer $bookmark_writer, Metadata_Writer $metadata_writer, Java_Script_Writer $java_script_writer, Logger_Interface $logger)
    {
        $this->mpdf = $mpdf;
        $this->writer = $writer;
        $this->color_writer = $color_writer;
        $this->font_writer = $font_writer;
        $this->image_writer = $image_writer;
        $this->form_writer = $form_writer;
        $this->optional_content_writer = $optional_content_writer;
        $this->background_writer = $background_writer;
        $this->bookmark_writer = $bookmark_writer;
        $this->metadata_writer = $metadata_writer;
        $this->java_script_writer = $java_script_writer;
        $this->logger = $logger;
    }
    public function write_resources()
    {
        if ($this->mpdf->has_oc || count($this->mpdf->layers)) {
            $this->optional_content_writer->write_optional_content_groups();
        }
        $this->mpdf->_putextgstates();
        $this->color_writer->write_spot_colors();
        // @log Compiling Fonts
        $this->font_writer->write_fonts();
        // @log Compiling Images
        $this->image_writer->write_images();
        $this->form_writer->write_form_objects();
        $this->mpdf->write_imported_pages_and_resolved_objects();
        $this->background_writer->write_shaders();
        $this->background_writer->write_patterns();
        // Resource dictionary
        $this->mpdf->offsets[2] = $this->mpdf->buffer->get_length();
        $this->writer->write('2 0 obj');
        $this->writer->write('<</ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $this->writer->write('/Font <<');
        foreach ($this->mpdf->fonts as $font) {
            if (isset($font['type']) && $font['type'] === 'TTF' && !$font['used']) {
                continue;
            }
            if (isset($font['type']) && $font['type'] === 'TTF' && ($font['sip'] || $font['smp'])) {
                foreach ($font['n'] as $k => $fid) {
                    $this->writer->write('/F' . $font['subsetfontids'][$k] . ' ' . $font['n'][$k] . ' 0 R');
                }
            } else {
                $this->writer->write('/F' . $font['i'] . ' ' . $font['n'] . ' 0 R');
            }
        }
        $this->writer->write('>>');
        if (count($this->mpdf->spot_colors)) {
            $this->writer->write('/ColorSpace <<');
            foreach ($this->mpdf->spot_colors as $color) {
                $this->writer->write('/CS' . $color['i'] . ' ' . $color['n'] . ' 0 R');
            }
            $this->writer->write('>>');
        }
        if (count($this->mpdf->extgstates)) {
            $this->writer->write('/ExtGState <<');
            foreach ($this->mpdf->extgstates as $k => $extgstate) {
                if (isset($extgstate['trans'])) {
                    $this->writer->write('/' . $extgstate['trans'] . ' ' . $extgstate['n'] . ' 0 R');
                } else {
                    $this->writer->write('/GS' . $k . ' ' . $extgstate['n'] . ' 0 R');
                }
            }
            $this->writer->write('>>');
        }
        /* -- BACKGROUNDS -- */
        if ($this->mpdf->gradients !== null && count($this->mpdf->gradients) > 0) {
            // mPDF 5.7.3
            $this->writer->write('/Shading <<');
            foreach ($this->mpdf->gradients as $id => $grad) {
                $this->writer->write('/Sh' . $id . ' ' . $grad['id'] . ' 0 R');
            }
            $this->writer->write('>>');
            /*
             // ??? Not needed !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
             $this->writer->write('/Pattern <<');
             foreach ($this->mpdf->gradients as $id => $grad) {
             $this->writer->write('/P'.$id.' '.$grad['pattern'].' 0 R');
             }
             $this->writer->write('>>');
            */
        }
        /* -- END BACKGROUNDS -- */
        if (count($this->mpdf->images) || count($this->mpdf->formobjects) || count($this->mpdf->get_imported_pages())) {
            $this->writer->write('/XObject <<');
            foreach ($this->mpdf->images as $image) {
                $this->writer->write('/I' . $image['i'] . ' ' . $image['n'] . ' 0 R');
            }
            foreach ($this->mpdf->formobjects as $formobject) {
                $this->writer->write('/FO' . $formobject['i'] . ' ' . $formobject['n'] . ' 0 R');
            }
            /* -- IMPORTS -- */
            foreach ($this->mpdf->get_imported_pages() as $page_data) {
                $this->writer->write('/' . $page_data['id'] . ' ' . $page_data['objectNumber'] . ' 0 R');
            }
            /* -- END IMPORTS -- */
            $this->writer->write('>>');
        }
        /* -- BACKGROUNDS -- */
        if (count($this->mpdf->patterns)) {
            $this->writer->write('/Pattern <<');
            foreach ($this->mpdf->patterns as $k => $patterns) {
                $this->writer->write('/P' . $k . ' ' . $patterns['n'] . ' 0 R');
            }
            $this->writer->write('>>');
        }
        /* -- END BACKGROUNDS -- */
        if ($this->mpdf->has_oc || count($this->mpdf->layers)) {
            $this->writer->write('/Properties <<');
            if ($this->mpdf->has_oc) {
                $this->writer->write('/OC1 ' . $this->mpdf->n_ocg_print . ' 0 R /OC2 ' . $this->mpdf->n_ocg_view . ' 0 R /OC3 ' . $this->mpdf->n_ocg_hidden . ' 0 R ');
            }
            if (count($this->mpdf->layers)) {
                foreach ($this->mpdf->layers as $id => $layer) {
                    $this->writer->write('/ZI' . $id . ' ' . $layer['n'] . ' 0 R');
                }
            }
            $this->writer->write('>>');
        }
        $this->writer->write('>>');
        $this->writer->write('endobj');
        // end resource dictionary
        $this->bookmark_writer->write_bookmarks();
        if (!empty($this->mpdf->js)) {
            $this->java_script_writer->write_javascript();
        }
        if ($this->mpdf->encrypted) {
            $this->writer->object();
            $this->mpdf->enc_obj_id = $this->mpdf->n;
            $this->writer->write('<<');
            $this->metadata_writer->write_encryption();
            $this->writer->write('>>');
            $this->writer->write('endobj');
        }
    }
}