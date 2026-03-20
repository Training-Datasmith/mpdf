<?php

namespace Mpdf\Writer;

use Mpdf\Strict;
use Mpdf\Mpdf;
final class Optional_Content_Writer
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
    public function write_optional_content_groups()
    {
        if ($this->mpdf->has_oc) {
            $this->writer->object();
            $this->mpdf->n_ocg_print = $this->mpdf->n;
            $this->writer->write('<</Type /OCG /Name ' . $this->writer->string('Print only'));
            $this->writer->write('/Usage <</Print <</PrintState /ON>> /View <</ViewState /OFF>>>>>>');
            $this->writer->write('endobj');
            $this->writer->object();
            $this->mpdf->n_ocg_view = $this->mpdf->n;
            $this->writer->write('<</Type /OCG /Name ' . $this->writer->string('Screen only'));
            $this->writer->write('/Usage <</Print <</PrintState /OFF>> /View <</ViewState /ON>>>>>>');
            $this->writer->write('endobj');
            $this->writer->object();
            $this->mpdf->n_ocg_hidden = $this->mpdf->n;
            $this->writer->write('<</Type /OCG /Name ' . $this->writer->string('Hidden'));
            $this->writer->write('/Usage <</Print <</PrintState /OFF>> /View <</ViewState /OFF>>>>>>');
            $this->writer->write('endobj');
        }
        if (count($this->mpdf->layers)) {
            ksort($this->mpdf->layers);
            foreach ($this->mpdf->layers as $id => $layer) {
                $this->writer->object();
                $this->mpdf->layers[$id]['n'] = $this->mpdf->n;
                if (isset($this->mpdf->layer_details[$id]['name']) && $this->mpdf->layer_details[$id]['name']) {
                    $name = $this->mpdf->layer_details[$id]['name'];
                } else {
                    $name = $layer['name'];
                }
                $this->writer->write('<</Type /OCG /Name ' . $this->writer->utf16big_endian_text_string($name) . '>>');
                $this->writer->write('endobj');
            }
        }
    }
}