<?php

namespace Mpdf\Writer;

use Mpdf\Strict;
use Mpdf\Mpdf;
use pdf_parser;
final class Object_Writer
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
    public function write_imported_objects()
    {
        if (is_array($this->mpdf->parsers) && count($this->mpdf->parsers) > 0) {
            foreach ($this->mpdf->parsers as $filename => $p) {
                $this->mpdf->current_parser = $this->mpdf->parsers[$filename];
                if (is_array($this->mpdf->_obj_stack[$filename])) {
                    while ($n = key($this->mpdf->_obj_stack[$filename])) {
                        $n_obj = $this->mpdf->current_parser->resolve_object($this->mpdf->_obj_stack[$filename][$n][1]);
                        $this->writer->object($this->mpdf->_obj_stack[$filename][$n][0]);
                        if ($n_obj[0] == pdf_parser::TYPE_STREAM) {
                            $this->mpdf->pdf_write_value($n_obj);
                        } else {
                            $this->mpdf->pdf_write_value($n_obj[1]);
                        }
                        $this->writer->write('endobj');
                        $this->mpdf->_obj_stack[$filename][$n] = null;
                        // free memory
                        unset($this->mpdf->_obj_stack[$filename][$n]);
                        reset($this->mpdf->_obj_stack[$filename]);
                    }
                }
            }
        }
    }
}