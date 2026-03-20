<?php

declare (strict_types=1);
namespace Mpdf;

use setasign\Fpdi\Pdf_Parser\Cross_Reference\Cross_Reference_Exception;
use setasign\Fpdi\Pdf_Parser\Filter\Ascii_Hex;
use setasign\Fpdi\Pdf_Parser\Type\Pdf_Array;
use setasign\Fpdi\Pdf_Parser\Type\Pdf_Dictionary;
use setasign\Fpdi\Pdf_Parser\Type\Pdf_Hex_String;
use setasign\Fpdi\Pdf_Parser\Type\Pdf_Indirect_Object;
use setasign\Fpdi\Pdf_Parser\Type\Pdf_Indirect_Object_Reference;
use setasign\Fpdi\Pdf_Parser\Type\Pdf_Name;
use setasign\Fpdi\Pdf_Parser\Type\Pdf_Null;
use setasign\Fpdi\Pdf_Parser\Type\Pdf_Numeric;
use setasign\Fpdi\Pdf_Parser\Type\Pdf_Stream;
use setasign\Fpdi\Pdf_Parser\Type\Pdf_String;
use setasign\Fpdi\Pdf_Parser\Type\Pdf_Type;
use setasign\Fpdi\Pdf_Parser\Type\Pdf_Type_Exception;
use setasign\Fpdi\Pdf_Reader\Data_Structure\Rectangle;
use setasign\Fpdi\Pdf_Reader\Page_Boundaries;
/**
 * @mixin Mpdf
 */
trait Fpdi_Trait
{
    use \setasign\Fpdi\Fpdi_Trait {
        writePdfType as fpdiWritePdfType;
        useImportedPage as fpdiUseImportedPage;
        importPage as fpdiImportPage;
    }
    protected $k = Mpdf::SCALE;
    /**
     * The currently used object number.
     *
     * @var int
     */
    public $current_object_number;
    /**
     * A counter for template ids.
     *
     * @var int
     */
    protected $template_id = 0;
    protected function set_page_format(array $format, $orientation)
    {
        // in mPDF this needs to be "P" (why ever)
        $orientation = 'P';
        $this->_set_page_size([$format['width'], $format['height']], $orientation);
        if ($orientation != $this->def_orientation) {
            $this->orientation_changes[$this->page] = true;
        }
        $this->w_pt = $this->fw_pt;
        $this->h_pt = $this->fh_pt;
        $this->w = $this->fw;
        $this->h = $this->fh;
        $this->cur_orientation = $orientation;
        $this->reset_margins();
        $this->pgwidth = $this->w - $this->l_margin - $this->r_margin;
        $this->page_break_trigger = $this->h - $this->b_margin;
        $this->page_dim[$this->page]['w'] = $this->w;
        $this->page_dim[$this->page]['h'] = $this->h;
    }
    /**
     * Set the minimal PDF version.
     *
     * @param string $pdfVersion
     */
    protected function set_min_pdf_version($pdf_version)
    {
        if (\version_compare($pdf_version, $this->pdf_version, '>')) {
            $this->pdf_version = $pdf_version;
        }
    }
    /**
     * Get the next template id.
     *
     * @return int
     */
    protected function get_next_template_id()
    {
        return $this->template_id++;
    }
    /**
     * Draws an imported page or a template onto the page or another template.
     *
     * Omit one of the size parameters (width, height) to calculate the other one automatically in view to the aspect
     * ratio.
     *
     * @param mixed $tpl The template id
     * @param float|int|array $x The abscissa of upper-left corner. Alternatively you could use an assoc array
     *                           with the keys "x", "y", "width", "height", "adjustPageSize".
     * @param float|int $y The ordinate of upper-left corner.
     * @param float|int|null $width The width.
     * @param float|int|null $height The height.
     * @param bool $adjustPageSize
     * @return array The size
     * @see Fpdi::getTemplateSize()
     */
    public function use_template($tpl, $x = 0, $y = 0, $width = null, $height = null, $adjust_page_size = false)
    {
        return $this->use_imported_page($tpl, $x, $y, $width, $height, $adjust_page_size);
    }
    /**
     * Draws an imported page onto the page.
     *
     * Omit one of the size parameters (width, height) to calculate the other one automatically in view to the aspect
     * ratio.
     *
     * @param mixed $pageId The page id
     * @param float|int|array $x The abscissa of upper-left corner. Alternatively you could use an assoc array
     *                           with the keys "x", "y", "width", "height", "adjustPageSize".
     * @param float|int $y The ordinate of upper-left corner.
     * @param float|int|null $width The width.
     * @param float|int|null $height The height.
     * @param bool $adjustPageSize
     * @return array The size.
     * @see Fpdi::getTemplateSize()
     */
    public function use_imported_page($page_id, $x = 0, $y = 0, $width = null, $height = null, $adjust_page_size = false)
    {
        if ($this->state == 0) {
            $this->add_page();
        }
        /* Extract $x if an array */
        if (is_array($x)) {
            unset($x['pageId']);
            extract($x, EXTR_IF_EXISTS);
            if (is_array($x)) {
                $x = 0;
            }
        }
        $new_size = $this->fpdi_use_imported_page($page_id, $x, $y, $width, $height, $adjust_page_size);
        $this->set_imported_page_links($page_id, $x, $y, $new_size);
        return $new_size;
    }
    /**
     * Imports a page.
     *
     * @param int $pageNumber The page number.
     * @param string $box The page boundary to import. Default set to PageBoundaries::CROP_BOX.
     * @param bool $groupXObject Define the form XObject as a group XObject to support transparency (if used).
     * @return string A unique string identifying the imported page.
     * @throws CrossReferenceException
     * @throws FilterException
     * @throws PdfParserException
     * @throws PdfTypeException
     * @throws PdfReaderException
     * @see PageBoundaries
     */
    public function import_page($page_number, $box = Page_Boundaries::CROP_BOX, $group_x_object = true)
    {
        $page_id = $this->fpdi_import_page($page_number, $box, $group_x_object);
        $this->imported_pages[$page_id]['externalLinks'] = $this->get_imported_external_page_links($page_number);
        return $page_id;
    }
    /**
     * Imports the external page links
     *
     * @param int $pageNumber The page number.
     * @return array
     * @throws CrossReferenceException
     * @throws PdfTypeException
     * @throws \setasign\Fpdi\PdfParser\PdfParserException
     */
    public function get_imported_external_page_links($page_number)
    {
        $links = [];
        $reader = $this->get_pdf_reader($this->current_reader_id);
        $parser = $reader->get_parser();
        $page = $reader->get_page($page_number);
        $page->get_page_dictionary();
        $annotations = $page->get_attribute('Annots');
        if ($annotations instanceof Pdf_Indirect_Object_Reference) {
            $annotations = Pdf_Type::resolve($parser->get_indirect_object($annotations->value), $parser);
        }
        if ($annotations instanceof Pdf_Array) {
            $annotations = Pdf_Type::resolve($annotations, $parser);
            foreach ($annotations->value as $annotation) {
                try {
                    $annotation = Pdf_Type::resolve($annotation, $parser);
                    $type = Pdf_Name::ensure(Pdf_Type::resolve(Pdf_Dictionary::get($annotation, 'Type'), $parser));
                    $subtype = Pdf_Name::ensure(Pdf_Type::resolve(Pdf_Dictionary::get($annotation, 'Subtype'), $parser));
                    $link = Pdf_Dictionary::ensure(Pdf_Type::resolve(Pdf_Dictionary::get($annotation, 'A'), $parser));
                    /* Skip over annotations that aren't links */
                    if ($type->value !== 'Annot') {
                        continue;
                    }
                    if ($subtype->value !== 'Link') {
                        continue;
                    }
                    /* Calculate the link positioning */
                    $position = Pdf_Array::ensure(Pdf_Type::resolve(Pdf_Dictionary::get($annotation, 'Rect'), $parser), 4);
                    $rect = Rectangle::by_pdf_array($position, $parser);
                    $uri = Pdf_String::ensure(Pdf_Type::resolve(Pdf_Dictionary::get($link, 'URI'), $parser));
                    $links[] = ['x' => $rect->get_llx() / Mpdf::SCALE, 'y' => $rect->get_lly() / Mpdf::SCALE, 'width' => $rect->get_width() / Mpdf::SCALE, 'height' => $rect->get_height() / Mpdf::SCALE, 'url' => $uri->value];
                } catch (Pdf_Type_Exception $e) {
                    continue;
                }
            }
        }
        return $links;
    }
    /**
     * @param mixed $pageId The page id
     * @param int|float $x The abscissa of upper-left corner.
     * @param int|float $y The ordinate of upper-right corner.
     * @param array $newSize The size.
     */
    public function set_imported_page_links($page_id, $x, $y, array $new_size)
    {
        $original_size = $this->get_template_size($page_id);
        $page_height_difference = $this->h - $new_size['height'];
        /* Handle different aspect ratio */
        $width_ratio = $new_size['width'] / $original_size['width'];
        $height_ratio = $new_size['height'] / $original_size['height'];
        foreach ($this->imported_pages[$page_id]['externalLinks'] as $item) {
            $item['x'] *= $width_ratio;
            $item['width'] *= $width_ratio;
            $item['y'] *= $height_ratio;
            $item['height'] *= $height_ratio;
            $this->Link(
                $item['x'] + $x,
                /* convert Y to be measured from the top of the page */
                $this->h - $item['y'] - $item['height'] - $page_height_difference + $y,
                $item['width'],
                $item['height'],
                $item['url']
            );
        }
    }
    /**
     * Get the size of an imported page or template.
     *
     * Omit one of the size parameters (width, height) to calculate the other one automatically in view to the aspect
     * ratio.
     *
     * @param mixed $tpl The template id
     * @param float|int|null $width The width.
     * @param float|int|null $height The height.
     * @return array|bool An array with following keys: width, height, 0 (=width), 1 (=height), orientation (L or P)
     */
    public function get_template_size($tpl, $width = null, $height = null)
    {
        return $this->get_imported_page_size($tpl, $width, $height);
    }
    /**
     * @throws CrossReferenceException
     * @throws PdfTypeException
     * @throws \setasign\Fpdi\PdfParser\PdfParserException
     */
    public function write_imported_pages_and_resolved_objects()
    {
        $this->current_reader_id = null;
        foreach ($this->imported_pages as $key => $page_data) {
            $this->writer->object();
            $this->imported_pages[$key]['objectNumber'] = $this->n;
            $this->current_reader_id = $page_data['readerId'];
            $this->write_pdf_type($page_data['stream']);
            $this->_put('endobj');
        }
        foreach (\array_keys($this->readers) as $reader_id) {
            $parser = $this->get_pdf_reader($reader_id)->get_parser();
            $this->current_reader_id = $reader_id;
            while (($object_number = \array_pop($this->objects_to_copy[$reader_id])) !== null) {
                try {
                    $object = $parser->get_indirect_object($object_number);
                } catch (Cross_Reference_Exception $e) {
                    if ($e->get_code() === Cross_Reference_Exception::OBJECT_NOT_FOUND) {
                        $object = Pdf_Indirect_Object::create($object_number, 0, new Pdf_Null());
                    } else {
                        throw $e;
                    }
                }
                $this->write_pdf_type($object);
            }
        }
        $this->current_reader_id = null;
    }
    public function get_imported_pages()
    {
        return $this->imported_pages;
    }
    protected function _put($s, $new_line = true)
    {
        $this->buffer->append($s, $new_line);
    }
    /**
     * Writes a PdfType object to the resulting buffer.
     *
     * @throws PdfTypeException
     */
    public function write_pdf_type(Pdf_Type $value)
    {
        if (!$this->encrypted) {
            if ($value instanceof Pdf_Indirect_Object) {
                /**
                 * @var $value PdfIndirectObject
                 */
                $n = $this->object_map[$this->current_reader_id][$value->object_number];
                $this->writer->object($n);
                $this->write_pdf_type($value->value);
                $this->_put('endobj');
                return;
            }
            $this->fpdi_write_pdf_type($value);
            return;
        }
        if ($value instanceof Pdf_String) {
            $string = Pdf_String::unescape($value->value);
            $string = $this->protection->rc4($this->protection->object_key($this->current_object_number), $string);
            $value->value = $this->writer->escape($string);
        } elseif ($value instanceof Pdf_Hex_String) {
            $filter = new Ascii_Hex();
            $string = $filter->decode($value->value);
            $string = $this->protection->rc4($this->protection->object_key($this->current_object_number), $string);
            $value->value = $filter->encode($string, true);
        } elseif ($value instanceof Pdf_Stream) {
            $stream = $value->get_stream();
            $stream = $this->protection->rc4($this->protection->object_key($this->current_object_number), $stream);
            $dictionary = $value->value;
            $dictionary->value['Length'] = Pdf_Numeric::create(\strlen($stream));
            $value = Pdf_Stream::create($dictionary, $stream);
        } elseif ($value instanceof Pdf_Indirect_Object) {
            /**
             * @var $value PdfIndirectObject
             */
            $this->current_object_number = $this->object_map[$this->current_reader_id][$value->object_number];
            /**
             * @var $value PdfIndirectObject
             */
            $n = $this->object_map[$this->current_reader_id][$value->object_number];
            $this->writer->object($n);
            $this->write_pdf_type($value->value);
            $this->_put('endobj');
            return;
        }
        $this->fpdi_write_pdf_type($value);
    }
}