<?php

declare (strict_types=1);
namespace Mpdf;

use Mpdf\Color\Color_Converter;
use Mpdf\Writer\Base_Writer;
use Mpdf\Writer\Form_Writer;
class Form
{
    use Strict;
    // Input flags
    public const FLAG_READONLY = 1;
    public const FLAG_REQUIRED = 2;
    public const FLAG_NO_EXPORT = 3;
    public const FLAG_TEXTAREA = 13;
    public const FLAG_PASSWORD = 14;
    public const FLAG_RADIO = 15;
    public const FLAG_NOTOGGLEOFF = 16;
    public const FLAG_COMBOBOX = 18;
    public const FLAG_EDITABLE = 19;
    public const FLAG_MULTISELECT = 22;
    public const FLAG_NO_SPELLCHECK = 23;
    public const FLAG_NO_SCROLL = 24;
    /**
     * @var \Mpdf\Mpdf
     */
    private $mpdf;
    /**
     * @var \Mpdf\Otl
     */
    private $otl;
    /**
     * @var \Mpdf\Color\ColorConverter
     */
    private $color_converter;
    /**
     * @var \Mpdf\Writer\BaseWriter
     */
    private $writer;
    /**
     * @var \Mpdf\Writer\FormWriter
     */
    private $form_writer;
    /**
     * @var array
     */
    public $forms;
    /**
     * @var int
     */
    private $form_count;
    // Active Forms
    public $form_submit_no_value_fields;
    public $form_export_type;
    public $form_select_default_option;
    public $form_use_zap_d;
    // Form Styles
    public $form_border_color;
    public $form_background_color;
    public $form_border_width;
    public $form_border_style;
    public $form_button_border_color;
    public $form_button_background_color;
    public $form_button_border_width;
    public $form_button_border_style;
    public $form_radio_color;
    public $form_radio_background_color;
    public $form_element_spacing;
    // Active forms
    public $form_method;
    public $form_action;
    public $form_fonts;
    public $form_radio_groups;
    public $form_checkboxes;
    public $pdf_acro_array;
    public $pdf_array_co;
    public $array_form_button_js;
    public $array_form_choice_js;
    public $array_form_text_js;
    // Button Text
    public $form_button_text;
    public $form_button_text_over;
    public $form_button_text_click;
    public $form_button_icon;
    // FORMS
    public $textarea_lineheight;
    public function __construct(Mpdf $mpdf, Otl $otl, Color_Converter $color_converter, Base_Writer $writer, Form_Writer $form_writer)
    {
        $this->mpdf = $mpdf;
        $this->otl = $otl;
        $this->color_converter = $color_converter;
        $this->writer = $writer;
        $this->form_writer = $form_writer;
        // ACTIVE FORMS
        $this->form_export_type = 'xfdf';
        // 'xfdf' or 'html'
        $this->form_submit_no_value_fields = true;
        // Whether to include blank fields when submitting data
        $this->form_select_default_option = true;
        // for Select drop down box; if no option is explicitly maked as selected,
        // this determines whether to select 1st option (as per browser)
        // - affects whether "required" attribute is relevant
        $this->form_use_zap_d = true;
        // Determine whether to use ZapfDingbat icons for radio/checkboxes
        // FORM STYLES
        // These can alternatively use a 4 number string to represent CMYK colours
        $this->form_border_color = '0.6 0.6 0.72';
        // RGB
        $this->form_background_color = '0.975 0.975 0.975';
        // RGB
        $this->form_border_width = '1';
        // 0 doesn't seem to work as it should
        $this->form_border_style = 'S';
        // B - Bevelled; D - Double
        $this->form_button_border_color = '0.2 0.2 0.55';
        $this->form_button_background_color = '0.941 0.941 0.941';
        $this->form_button_border_width = '1';
        $this->form_button_border_style = 'S';
        $this->form_radio_color = '0.0 0.0 0.4';
        // radio and checkbox
        $this->form_radio_background_color = '0.9 0.9 0.9';
        // FORMS
        $this->textarea_lineheight = 1.25;
        // FORM ELEMENT SPACING
        $this->form_element_spacing['select']['outer']['h'] = 0.5;
        // Horizontal spacing around SELECT
        $this->form_element_spacing['select']['outer']['v'] = 0.5;
        // Vertical spacing around SELECT
        $this->form_element_spacing['select']['inner']['h'] = 0.7;
        // Horizontal padding around SELECT
        $this->form_element_spacing['select']['inner']['v'] = 0.7;
        // Vertical padding around SELECT
        $this->form_element_spacing['input']['outer']['h'] = 0.5;
        $this->form_element_spacing['input']['outer']['v'] = 0.5;
        $this->form_element_spacing['input']['inner']['h'] = 0.7;
        $this->form_element_spacing['input']['inner']['v'] = 0.7;
        $this->form_element_spacing['textarea']['outer']['h'] = 0.5;
        $this->form_element_spacing['textarea']['outer']['v'] = 0.5;
        $this->form_element_spacing['textarea']['inner']['h'] = 1;
        $this->form_element_spacing['textarea']['inner']['v'] = 0.5;
        $this->form_element_spacing['button']['outer']['h'] = 0.5;
        $this->form_element_spacing['button']['outer']['v'] = 0.5;
        $this->form_element_spacing['button']['inner']['h'] = 2;
        $this->form_element_spacing['button']['inner']['v'] = 1;
        // INITIALISE non-configurable
        $this->form_method = 'POST';
        $this->form_action = '';
        $this->form_fonts = [];
        $this->form_radio_groups = [];
        $this->form_checkboxes = false;
        $this->forms = [];
        $this->pdf_array_co = '';
    }
    public function print_ob_text(array $objattr, $w, $h, $texto, $rtlalign, $k, $blockdir)
    {
        // TEXT/PASSWORD INPUT
        if ($this->mpdf->use_active_forms) {
            $flags = [];
            if (!empty($objattr['disabled']) || !empty($objattr['readonly'])) {
                $flags[] = self::FLAG_READONLY;
            }
            if (!empty($objattr['disabled'])) {
                $flags[] = self::FLAG_NO_EXPORT;
                $objattr['color'] = [3, 128, 128, 128];
                // gray out disabled
            }
            if (!empty($objattr['required'])) {
                $flags[] = self::FLAG_REQUIRED;
            }
            if (!isset($objattr['spellcheck']) || !$objattr['spellcheck']) {
                $flags[] = self::FLAG_NO_SPELLCHECK;
            }
            if (isset($objattr['subtype']) && $objattr['subtype'] === 'PASSWORD') {
                $flags[] = self::FLAG_PASSWORD;
            }
            $this->mpdf->set_t_color(isset($objattr['color']) ? $objattr['color'] : $this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
            $fieldalign = $rtlalign;
            if (!empty($objattr['text_align'])) {
                $fieldalign = $objattr['text_align'];
                $val = $objattr['text'];
            } else {
                $val = $objattr['text'];
            }
            // mPDF 5.3.25
            $js = [];
            if (!empty($objattr['onCalculate'])) {
                $js[] = ['C', $objattr['onCalculate']];
            }
            if (!empty($objattr['onValidate'])) {
                $js[] = ['V', $objattr['onValidate']];
            }
            if (!empty($objattr['onFormat'])) {
                $js[] = ['F', $objattr['onFormat']];
            }
            if (!empty($objattr['onKeystroke'])) {
                $js[] = ['K', $objattr['onKeystroke']];
            }
            if (!empty($objattr['use_auto_fontsize']) && $objattr['use_auto_fontsize'] === true) {
                $this->mpdf->font_size_pt = 0.0;
            }
            $this->set_form_text($w, $h, $objattr['fieldname'], $val, $val, $objattr['title'], $flags, $fieldalign, false, isset($objattr['maxlength']) ? $objattr['maxlength'] : false, $js, isset($objattr['background-col']) ? $objattr['background-col'] : false, isset($objattr['border-col']) ? $objattr['border-col'] : false);
        } else {
            $w -= $this->form_element_spacing['input']['outer']['h'] * 2 / $k;
            $h -= $this->form_element_spacing['input']['outer']['v'] * 2 / $k;
            $this->mpdf->x += $this->form_element_spacing['input']['outer']['h'] / $k;
            $this->mpdf->y += $this->form_element_spacing['input']['outer']['v'] / $k;
            // Chop texto to max length $w-inner-padding
            while ($this->mpdf->get_string_width($texto) > $w - $this->form_element_spacing['input']['inner']['h'] * 2) {
                $texto = mb_substr($texto, 0, mb_strlen($texto, $this->mpdf->mb_enc) - 1, $this->mpdf->mb_enc);
            }
            // DIRECTIONALITY
            if (preg_match('/([' . $this->mpdf->preg_rt_lchars . '])/u', $texto)) {
                $this->mpdf->bi_directional = true;
            }
            // Use OTL OpenType Table Layout - GSUB & GPOS
            if (!empty($this->mpdf->current_font['useOTL'])) {
                $texto = $this->otl->apply_otl($texto, $this->mpdf->current_font['useOTL']);
                $ot_ldata = $this->otl->ot_ldata;
            }
            $this->mpdf->magic_reverse_dir($texto, $this->mpdf->directionality, $ot_ldata);
            $this->mpdf->set_line_width(0.2 / $k);
            if (!empty($objattr['disabled'])) {
                $this->mpdf->set_f_color($this->color_converter->convert(225, $this->mpdf->pdfa_xwarnings));
                $this->mpdf->set_t_color($this->color_converter->convert(127, $this->mpdf->pdfa_xwarnings));
            } elseif (!empty($objattr['readonly'])) {
                $this->mpdf->set_f_color($this->color_converter->convert(225, $this->mpdf->pdfa_xwarnings));
                $this->mpdf->set_t_color($this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
            } else {
                $this->mpdf->set_f_color($this->color_converter->convert(250, $this->mpdf->pdfa_xwarnings));
                $this->mpdf->set_t_color($this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
            }
            $this->mpdf->Cell($w, $h, $texto, 1, 0, $rtlalign, 1, '', 0, $this->form_element_spacing['input']['inner']['h'] / $k, $this->form_element_spacing['input']['inner']['h'] / $k, 'M', 0, false, $ot_ldata);
            $this->mpdf->set_f_color($this->color_converter->convert(255, $this->mpdf->pdfa_xwarnings));
            $this->mpdf->set_t_color($this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
        }
    }
    public function print_ob_textarea(array $objattr, $w, $h, $texto, $rtlalign, $k, $blockdir)
    {
        // TEXTAREA
        if ($this->mpdf->use_active_forms) {
            $flags = [self::FLAG_TEXTAREA];
            if (!empty($objattr['disabled']) || !empty($objattr['readonly'])) {
                $flags[] = self::FLAG_READONLY;
            }
            if (!empty($objattr['disabled'])) {
                $flags[] = self::FLAG_NO_EXPORT;
                $objattr['color'] = [3, 128, 128, 128];
                // gray out disabled
            }
            if (!empty($objattr['required'])) {
                $flags[] = self::FLAG_REQUIRED;
            }
            if (!isset($objattr['spellcheck']) || !$objattr['spellcheck']) {
                $flags[] = self::FLAG_NO_SPELLCHECK;
            }
            if (!empty($objattr['donotscroll'])) {
                $flags[] = self::FLAG_NO_SCROLL;
            }
            if (isset($objattr['color'])) {
                $this->mpdf->set_t_color($objattr['color']);
            } else {
                $this->mpdf->set_t_color($this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
            }
            $fieldalign = $rtlalign;
            if ($texto === ' ') {
                $texto = '';
            }
            // mPDF 5.3.24
            if (!empty($objattr['text_align'])) {
                $fieldalign = $objattr['text_align'];
            }
            // mPDF 5.3.25
            $js = [];
            if (!empty($objattr['onCalculate'])) {
                $js[] = ['C', $objattr['onCalculate']];
            }
            if (!empty($objattr['onValidate'])) {
                $js[] = ['V', $objattr['onValidate']];
            }
            if (!empty($objattr['onFormat'])) {
                $js[] = ['F', $objattr['onFormat']];
            }
            if (!empty($objattr['onKeystroke'])) {
                $js[] = ['K', $objattr['onKeystroke']];
            }
            if (!empty($objattr['use_auto_fontsize']) && $objattr['use_auto_fontsize'] === true) {
                $this->mpdf->font_size_pt = 0.0;
            }
            $this->set_form_text($w, $h, $objattr['fieldname'], $texto, $texto, isset($objattr['title']) ? $objattr['title'] : '', $flags, $fieldalign, false, -1, $js, isset($objattr['background-col']) ? $objattr['background-col'] : false, isset($objattr['border-col']) ? $objattr['border-col'] : false);
            $this->mpdf->set_t_color($this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
        } else {
            $w -= $this->form_element_spacing['textarea']['outer']['h'] * 2 / $k;
            $h -= $this->form_element_spacing['textarea']['outer']['v'] * 2 / $k;
            $this->mpdf->x += $this->form_element_spacing['textarea']['outer']['h'] / $k;
            $this->mpdf->y += $this->form_element_spacing['textarea']['outer']['v'] / $k;
            $this->mpdf->set_line_width(0.2 / $k);
            if (!empty($objattr['disabled'])) {
                $this->mpdf->set_f_color($this->color_converter->convert(225, $this->mpdf->pdfa_xwarnings));
                $this->mpdf->set_t_color($this->color_converter->convert(127, $this->mpdf->pdfa_xwarnings));
            } elseif (!empty($objattr['readonly'])) {
                $this->mpdf->set_f_color($this->color_converter->convert(225, $this->mpdf->pdfa_xwarnings));
                $this->mpdf->set_t_color($this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
            } else {
                $this->mpdf->set_f_color($this->color_converter->convert(250, $this->mpdf->pdfa_xwarnings));
                $this->mpdf->set_t_color(isset($objattr['color']) ? $objattr['color'] : $this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
            }
            $this->mpdf->Rect($this->mpdf->x, $this->mpdf->y, $w, $h, 'DF');
            $clip_path = sprintf('q %.3F %.3F %.3F %.3F re W n ', $this->mpdf->x * Mpdf::SCALE, ($this->mpdf->h - $this->mpdf->y) * Mpdf::SCALE, $w * Mpdf::SCALE, -$h * Mpdf::SCALE);
            $this->writer->write($clip_path);
            $w -= $this->form_element_spacing['textarea']['inner']['h'] * 2 / $k;
            $this->mpdf->x += $this->form_element_spacing['textarea']['inner']['h'] / $k;
            $this->mpdf->y += $this->form_element_spacing['textarea']['inner']['v'] / $k;
            if ($texto != '') {
                $this->mpdf->multi_cell($w, $this->mpdf->font_size * $this->textarea_lineheight, $texto, 0, '', 0, '', $blockdir, true, $objattr['OTLdata'], $objattr['rows']);
            }
            $this->writer->write('Q');
            $this->mpdf->set_f_color($this->color_converter->convert(255, $this->mpdf->pdfa_xwarnings));
            $this->mpdf->set_t_color($this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
        }
    }
    public function print_ob_select(array $objattr, $w, $h, $texto, $rtlalign, $k, $blockdir)
    {
        // SELECT
        if ($this->mpdf->use_active_forms) {
            $flags = [];
            if (!empty($objattr['disabled'])) {
                $flags[] = self::FLAG_READONLY;
                $flags[] = self::FLAG_NO_EXPORT;
                $objattr['color'] = [3, 128, 128, 128];
                // gray out disabled
            }
            if (!empty($objattr['required'])) {
                $flags[] = self::FLAG_REQUIRED;
            }
            if (!empty($objattr['multiple']) && isset($objattr['size']) && $objattr['size'] > 1) {
                $flags[] = self::FLAG_MULTISELECT;
            }
            if (isset($objattr['size']) && $objattr['size'] < 2) {
                $flags[] = self::FLAG_COMBOBOX;
                if (!empty($objattr['editable'])) {
                    $flags[] = self::FLAG_EDITABLE;
                }
            }
            // only allow spellcheck if combo and editable
            if (!isset($objattr['spellcheck']) || !$objattr['spellcheck'] || isset($objattr['size']) && $objattr['size'] > 1 || (!isset($objattr['editable']) || !$objattr['editable'])) {
                $flags[] = self::FLAG_NO_SPELLCHECK;
            }
            if (isset($objattr['subtype']) && $objattr['subtype'] === 'PASSWORD') {
                $flags[] = self::FLAG_PASSWORD;
            }
            if (!empty($objattr['onChange'])) {
                $js = $objattr['onChange'];
            } else {
                $js = '';
            }
            // mPDF 5.3.37
            $data = ['VAL' => [], 'OPT' => [], 'SEL' => []];
            if (isset($objattr['items'])) {
                for ($i = 0; $i < count($objattr['items']); $i++) {
                    $item = $objattr['items'][$i];
                    $data['VAL'][] = isset($item['exportValue']) ? $item['exportValue'] : '';
                    $data['OPT'][] = isset($item['content']) ? $item['content'] : '';
                    if (!empty($item['selected'])) {
                        $data['SEL'][] = $i;
                    }
                }
            }
            if (count($data['SEL']) === 0 && $this->form_select_default_option) {
                $data['SEL'][] = 0;
            }
            if (isset($objattr['color'])) {
                $this->mpdf->set_t_color($objattr['color']);
            } else {
                $this->mpdf->set_t_color($this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
            }
            $this->set_form_choice($w, $h, $objattr['fieldname'], $flags, $data, $rtlalign, $js);
            $this->mpdf->set_t_color($this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
        } else {
            $this->mpdf->set_line_width(0.2 / $k);
            if (!empty($objattr['disabled'])) {
                $this->mpdf->set_f_color($this->color_converter->convert(225, $this->mpdf->pdfa_xwarnings));
                $this->mpdf->set_t_color($this->color_converter->convert(127, $this->mpdf->pdfa_xwarnings));
            } else {
                $this->mpdf->set_f_color($this->color_converter->convert(250, $this->mpdf->pdfa_xwarnings));
                $this->mpdf->set_t_color($this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
            }
            $w -= $this->form_element_spacing['select']['outer']['h'] * 2 / $k;
            $h -= $this->form_element_spacing['select']['outer']['v'] * 2 / $k;
            $this->mpdf->x += $this->form_element_spacing['select']['outer']['h'] / $k;
            $this->mpdf->y += $this->form_element_spacing['select']['outer']['v'] / $k;
            // DIRECTIONALITY
            if (preg_match('/([' . $this->mpdf->preg_rt_lchars . '])/u', $texto)) {
                $this->mpdf->bi_directional = true;
            }
            // *RTL*
            $this->mpdf->magic_reverse_dir($texto, $this->mpdf->directionality, $objattr['OTLdata']);
            $this->mpdf->Cell($w - $this->mpdf->font_size * 1.4, $h, $texto, 1, 0, $rtlalign, 1, '', 0, $this->form_element_spacing['select']['inner']['h'] / $k, $this->form_element_spacing['select']['inner']['h'] / $k, 'M', 0, false, $objattr['OTLdata']);
            $this->mpdf->set_f_color($this->color_converter->convert(190, $this->mpdf->pdfa_xwarnings));
            $save_font = $this->mpdf->font_family;
            $save_currentfont = $this->mpdf->currentfontfamily;
            if ($this->mpdf->PDFA || $this->mpdf->PDFX) {
                if ($this->mpdf->PDFA && !$this->mpdf->pdf_aauto || $this->mpdf->PDFX && !$this->mpdf->pdf_xauto) {
                    $this->mpdf->pdfa_xwarnings[] = 'Core Adobe font Zapfdingbats cannot be embedded in mPDF - used in Form element: Select - which is required for PDFA1-b or PDFX/1-a. (Different character/font will be substituted.)';
                }
                $this->mpdf->set_font('sans');
                if ($this->mpdf->_char_defined($this->mpdf->current_font['cw'], 9660)) {
                    $down = "▼";
                } else {
                    $down = '=';
                }
                $this->mpdf->Cell($this->mpdf->font_size * 1.4, $h, $down, 1, 0, 'C', 1);
            } else {
                $this->mpdf->set_font('czapfdingbats');
                $this->mpdf->Cell($this->mpdf->font_size * 1.4, $h, chr(116), 1, 0, 'C', 1);
            }
            $this->mpdf->set_font($save_font);
            $this->mpdf->currentfontfamily = $save_currentfont;
            $this->mpdf->set_f_color($this->color_converter->convert(255, $this->mpdf->pdfa_xwarnings));
            $this->mpdf->set_t_color($this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
        }
    }
    public function print_ob_imageinput(array $objattr, $w, $h, $texto, $rtlalign, $k, $blockdir, $is_table)
    {
        // INPUT/BUTTON as IMAGE
        if ($this->mpdf->use_active_forms) {
            $flags = [];
            if (!empty($objattr['disabled'])) {
                $flags[] = self::FLAG_READONLY;
                $flags[] = self::FLAG_NO_EXPORT;
            }
            if (!empty($objattr['onClick'])) {
                $js = $objattr['onClick'];
            } else {
                $js = '';
            }
            $this->set_js_button($w, $h, $objattr['fieldname'], isset($objattr['value']) ? $objattr['value'] : '', $js, $objattr['ID'], $objattr['title'], $flags, isset($objattr['Indexed']) ? $objattr['Indexed'] : false);
        } else {
            $this->mpdf->y = $objattr['INNER-Y'];
            $this->writer->write(sprintf('q %.3F 0 0 %.3F %.3F %.3F cm /I%d Do Q', $objattr['INNER-WIDTH'] * Mpdf::SCALE, $objattr['INNER-HEIGHT'] * Mpdf::SCALE, $objattr['INNER-X'] * Mpdf::SCALE, ($this->mpdf->h - ($objattr['INNER-Y'] + $objattr['INNER-HEIGHT'])) * Mpdf::SCALE, $objattr['ID']));
            if (!empty($objattr['BORDER-WIDTH'])) {
                $this->mpdf->paint_img_border($objattr, $is_table);
            }
        }
    }
    public function print_ob_button(array $objattr, $w, $h, $texto, $rtlalign, $k, $blockdir)
    {
        // BUTTON
        if ($this->mpdf->use_active_forms) {
            $flags = [];
            if (!empty($objattr['disabled'])) {
                $flags[] = self::FLAG_READONLY;
                $flags[] = self::FLAG_NO_EXPORT;
                $objattr['color'] = [3, 128, 128, 128];
            }
            $this->mpdf->set_t_color(isset($objattr['color']) ? $objattr['color'] : $this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
            if (isset($objattr['subtype'])) {
                if ($objattr['subtype'] === 'RESET') {
                    $this->set_form_button_text($objattr['value']);
                    $this->set_form_reset($w, $h, $objattr['fieldname'], $objattr['value'], $objattr['title'], $flags, isset($objattr['background-col']) ? $objattr['background-col'] : false, isset($objattr['border-col']) ? $objattr['border-col'] : false, isset($objattr['noprint']) ? $objattr['noprint'] : false);
                } elseif ($objattr['subtype'] === 'SUBMIT') {
                    $url = $this->form_action;
                    $type = $this->form_export_type;
                    $method = $this->form_method;
                    $this->set_form_button_text($objattr['value']);
                    $this->set_form_submit($w, $h, $objattr['fieldname'], $objattr['value'], $url, $objattr['title'], $type, $method, $flags, isset($objattr['background-col']) ? $objattr['background-col'] : false, isset($objattr['border-col']) ? $objattr['border-col'] : false, isset($objattr['noprint']) ? $objattr['noprint'] : false);
                } elseif ($objattr['subtype'] === 'BUTTON') {
                    $this->set_form_button_text($objattr['value']);
                    if (isset($objattr['onClick']) && $objattr['onClick']) {
                        $js = $objattr['onClick'];
                    } else {
                        $js = '';
                    }
                    $this->set_js_button($w, $h, $objattr['fieldname'], $objattr['value'], $js, 0, $objattr['title'], $flags, false, isset($objattr['background-col']) ? $objattr['background-col'] : false, isset($objattr['border-col']) ? $objattr['border-col'] : false, isset($objattr['noprint']) ? $objattr['noprint'] : false);
                }
            }
            $this->mpdf->set_t_color($this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
        } else {
            $this->mpdf->set_line_width(0.2 / $k);
            $this->mpdf->set_f_color($this->color_converter->convert(190, $this->mpdf->pdfa_xwarnings));
            $w -= $this->form_element_spacing['button']['outer']['h'] * 2 / $k;
            $h -= $this->form_element_spacing['button']['outer']['v'] * 2 / $k;
            $this->mpdf->x += $this->form_element_spacing['button']['outer']['h'] / $k;
            $this->mpdf->y += $this->form_element_spacing['button']['outer']['v'] / $k;
            $this->mpdf->rounded_rect($this->mpdf->x, $this->mpdf->y, $w, $h, 0.5 / $k, 'DF');
            $w -= $this->form_element_spacing['button']['inner']['h'] * 2 / $k;
            $h -= $this->form_element_spacing['button']['inner']['v'] * 2 / $k;
            $this->mpdf->x += $this->form_element_spacing['button']['inner']['h'] / $k;
            $this->mpdf->y += $this->form_element_spacing['button']['inner']['v'] / $k;
            // DIRECTIONALITY
            if (preg_match('/([' . $this->mpdf->preg_rt_lchars . '])/u', $texto)) {
                $this->mpdf->bi_directional = true;
            }
            // Use OTL OpenType Table Layout - GSUB & GPOS
            if (!empty($this->mpdf->current_font['useOTL'])) {
                $texto = $this->otl->apply_otl($texto, $this->mpdf->current_font['useOTL']);
                $ot_ldata = $this->otl->ot_ldata;
            }
            $this->mpdf->magic_reverse_dir($texto, $this->mpdf->directionality, $ot_ldata);
            $this->mpdf->Cell($w, $h, $texto, '', 0, 'C', 0, '', 0, 0, 0, 'M', 0, false, $ot_ldata);
            $this->mpdf->set_f_color($this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
        }
    }
    public function print_ob_checkbox(array $objattr, $w, $h, $texto, $rtlalign, $k, $blockdir, $x, $y)
    {
        // CHECKBOX
        if ($this->mpdf->use_active_forms) {
            $flags = [];
            if (!empty($objattr['disabled'])) {
                $flags[] = self::FLAG_READONLY;
                $flags[] = self::FLAG_NO_EXPORT;
            }
            $checked = false;
            if (!empty($objattr['checked'])) {
                $checked = true;
            }
            if ($this->form_use_zap_d) {
                $save_font = $this->mpdf->font_family;
                $save_currentfont = $this->mpdf->currentfontfamily;
                $this->mpdf->set_font('czapfdingbats');
            }
            $this->set_check_box($w, $h, $objattr['fieldname'], $objattr['value'], $objattr['title'], $checked, $flags, isset($objattr['disabled']) ? $objattr['disabled'] : false);
            if ($this->form_use_zap_d) {
                $this->mpdf->set_font($save_font);
                $this->mpdf->currentfontfamily = $save_currentfont;
            }
        } else {
            $iw = $w * 0.7;
            $ih = $h * 0.7;
            $lx = $x + ($w - $iw) / 2;
            $ty = $y + ($h - $ih) / 2;
            $rx = $lx + $iw;
            $by = $ty + $ih;
            $this->mpdf->set_line_width(0.2 / $k);
            if (!empty($objattr['disabled'])) {
                $this->mpdf->set_f_color($this->color_converter->convert(225, $this->mpdf->pdfa_xwarnings));
                $this->mpdf->set_d_color($this->color_converter->convert(127, $this->mpdf->pdfa_xwarnings));
            } else {
                $this->mpdf->set_f_color($this->color_converter->convert(250, $this->mpdf->pdfa_xwarnings));
                $this->mpdf->set_d_color($this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
            }
            $this->mpdf->Rect($lx, $ty, $iw, $ih, 'DF');
            if (!empty($objattr['checked'])) {
                //Round join and cap
                $this->mpdf->set_line_cap(1);
                $this->mpdf->Line($lx, $ty, $rx, $by);
                $this->mpdf->Line($lx, $by, $rx, $ty);
                //Set line cap style back to square
                $this->mpdf->set_line_cap();
            }
            $this->mpdf->set_f_color($this->color_converter->convert(255, $this->mpdf->pdfa_xwarnings));
            $this->mpdf->set_d_color($this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
        }
    }
    public function print_ob_radio(array $objattr, $w, $h, $texto, $rtlalign, $k, $blockdir, $x, $y)
    {
        // RADIO
        if ($this->mpdf->use_active_forms) {
            $flags = [];
            if (!empty($objattr['disabled'])) {
                $flags[] = self::FLAG_READONLY;
                $flags[] = self::FLAG_NO_EXPORT;
            }
            $checked = false;
            if (!empty($objattr['checked'])) {
                $checked = true;
            }
            if ($this->form_use_zap_d) {
                $save_font = $this->mpdf->font_family;
                $save_currentfont = $this->mpdf->currentfontfamily;
                $this->mpdf->set_font('czapfdingbats');
            }
            $this->set_radio($w, $h, $objattr['fieldname'], $objattr['value'], isset($objattr['title']) ? $objattr['title'] : '', $checked, $flags, isset($objattr['disabled']) ? $objattr['disabled'] : false);
            if ($this->form_use_zap_d) {
                $this->mpdf->set_font($save_font);
                $this->mpdf->currentfontfamily = $save_currentfont;
            }
        } else {
            $this->mpdf->set_line_width(0.2 / $k);
            $radius = $this->mpdf->font_size * 0.35;
            $cx = $x + $w / 2;
            $cy = $y + $h / 2;
            $color = $this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings);
            if (isset($objattr['color']) && $objattr['color']) {
                $color = $objattr['color'];
            }
            if (!empty($objattr['disabled'])) {
                $this->mpdf->set_f_color($this->color_converter->convert(127, $this->mpdf->pdfa_xwarnings));
                $this->mpdf->set_d_color($this->color_converter->convert(127, $this->mpdf->pdfa_xwarnings));
            } else {
                $this->mpdf->set_f_color($color);
                $this->mpdf->set_d_color($color);
            }
            $this->mpdf->Circle($cx, $cy, $radius, 'D');
            if (!empty($objattr['checked'])) {
                $this->mpdf->Circle($cx, $cy, $radius * 0.4, 'DF');
            }
            $this->mpdf->set_f_color($this->color_converter->convert(255, $this->mpdf->pdfa_xwarnings));
            $this->mpdf->set_d_color($this->color_converter->convert(0, $this->mpdf->pdfa_xwarnings));
        }
    }
    private function get_count_items(array $form)
    {
        $total = 1;
        if ($form['typ'] === 'Tx') {
            if (isset($this->array_form_text_js[$form['T']])) {
                if (isset($this->array_form_text_js[$form['T']]['F'])) {
                    $total++;
                }
                if (isset($this->array_form_text_js[$form['T']]['K'])) {
                    $total++;
                }
                if (isset($this->array_form_text_js[$form['T']]['V'])) {
                    $total++;
                }
                if (isset($this->array_form_text_js[$form['T']]['C'])) {
                    $total++;
                }
            }
        }
        if ($form['typ'] === 'Bt') {
            if (isset($this->array_form_button_js[$form['T']])) {
                $total++;
            }
            if (isset($this->form_button_icon[$form['T']])) {
                $total++;
                if ($this->form_button_icon[$form['T']]['Indexed']) {
                    $total++;
                }
            }
            if ($form['subtype'] === 'radio') {
                $total += 2;
            } elseif ($form['subtype'] === 'checkbox') {
                $total++;
                if (!$this->form_use_zap_d) {
                    $total++;
                }
            }
        }
        if ($form['typ'] === 'Ch') {
            if (isset($this->array_form_choice_js[$form['T']])) {
                $total++;
            }
        }
        return $total;
    }
    // In _putpages
    public function count_page_forms($n, &$totaladdnum)
    {
        foreach ($this->forms as $form) {
            if ($form['page'] == $n) {
                $totaladdnum += $this->get_count_items($form);
            }
        }
    }
    // In _putpages
    public function add_form_ids($n, &$s, &$annotid)
    {
        foreach ($this->forms as $form) {
            if ($form['page'] == $n) {
                $s .= $annotid . ' 0 R ';
                $annotid += $this->get_count_items($form);
            }
        }
    }
    // In _putannots
    public function _put_form_items($n, $h_pt)
    {
        foreach ($this->forms as $val) {
            if ($val['page'] == $n) {
                if ($val['typ'] === 'Tx') {
                    $this->_putform_tx($val, $h_pt);
                }
                if ($val['typ'] === 'Ch') {
                    $this->_putform_ch($val, $h_pt);
                }
                if ($val['typ'] === 'Bt') {
                    $this->_putform_bt($val, $h_pt);
                }
            }
        }
    }
    // In _putannots
    public function _put_radio_items($n)
    {
        // Output Radio Groups
        $key = 1;
        foreach ($this->form_radio_groups as $name => $frg) {
            $this->writer->object();
            $this->pdf_acro_array .= $this->mpdf->n . ' 0 R ';
            $this->writer->write('<<');
            $this->writer->write('/Type /Annot ');
            $this->writer->write('/Subtype /Widget');
            $this->writer->write('/NM ' . $this->writer->string(sprintf('%04u-%04u', $n, 3000 + $key++)));
            $this->writer->write('/M ' . $this->writer->string('D:' . date('YmdHis')));
            $this->writer->write('/Rect [0 0 0 0] ');
            $this->writer->write('/FT /Btn ');
            if (!empty($frg['disabled'])) {
                $flags = [self::FLAG_READONLY, self::FLAG_NO_EXPORT, self::FLAG_RADIO, self::FLAG_NOTOGGLEOFF];
            } else {
                $flags = [self::FLAG_RADIO, self::FLAG_NOTOGGLEOFF];
            }
            $this->writer->write('/Ff ' . $this->_setflag($flags));
            $kstr = '';
            // $optstr = '';
            foreach ($frg['kids'] as $kid) {
                $kstr .= $this->forms[$kid['n']]['obj'] . ' 0 R ';
                //		$optstr .= ' '.$this->writer->string($kid['OPT']).' ';
            }
            $this->writer->write('/Kids [ ' . $kstr . ' ] ');
            // 11 0 R 12 0 R etc.
            //	$this->writer->write('/Opt [ '.$optstr.' ] ');
            //V entry holds index corresponding to the appearance state of
            //whichever child field is currently in the on state = or Off
            if (isset($frg['on'])) {
                $state = $frg['on'];
            } else {
                $state = 'Off';
            }
            $this->writer->write('/V /' . $state . ' ');
            $this->writer->write('/DV /' . $state . ' ');
            $this->writer->write('/T ' . $this->writer->string($name) . ' ');
            $this->writer->write('>>');
            $this->writer->write('endobj');
        }
    }
    public function _put_forms_catalog()
    {
        if (isset($this->pdf_acro_array)) {
            $this->writer->write('/AcroForm << /DA (/F1 0 Tf 0 g )');
            $this->writer->write('/Q 0');
            $this->writer->write('/Fields [' . $this->pdf_acro_array . ']');
            $f = '';
            foreach ($this->form_fonts as $fn) {
                if (is_array($this->mpdf->fonts[$fn]['n'])) {
                    throw new \Mpdf\Mpdf_Exception('Cannot use fonts with SMP or SIP characters for interactive Form elements');
                }
                $f .= '/F' . $this->mpdf->fonts[$fn]['i'] . ' ' . $this->mpdf->fonts[$fn]['n'] . ' 0 R ';
            }
            $this->writer->write('/DR << /Font << ' . $f . ' >> >>');
            // CO Calculation Order
            if ($this->pdf_array_co) {
                $this->writer->write('/CO [' . $this->pdf_array_co . ']');
            }
            $this->writer->write('/NeedAppearances true');
            $this->writer->write('>>');
        }
    }
    public function set_form_button_js($name, $js)
    {
        $js = str_replace("\t", ' ', trim($js));
        if (isset($name) && isset($js)) {
            $this->array_form_button_js[$this->writer->escape($name)] = ['js' => $js];
        }
    }
    public function set_form_choice_js($name, $js)
    {
        $js = str_replace("\t", ' ', trim($js));
        if (isset($name) && isset($js)) {
            $this->array_form_choice_js[$this->writer->escape($name)] = ['js' => $js];
        }
    }
    public function set_form_text_js($name, $js)
    {
        for ($i = 0; $i < count($js); $i++) {
            $j = str_replace("\t", ' ', trim($js[$i][1]));
            $format = $js[$i][0];
            if ($name) {
                $this->array_form_text_js[$this->writer->escape($name)][$format] = ['js' => $j];
            }
        }
    }
    public function win1252to_pdf_doc_encoding($txt)
    {
        $win1252to_pdf_doc_encoding = [chr(0200) => chr(0240), chr(0214) => chr(0226), chr(0212) => chr(0227), chr(0237) => chr(0230), chr(0225) => chr(0200), chr(0210) => chr(032), chr(0206) => chr(0201), chr(0207) => chr(0202), chr(0205) => chr(0203), chr(0227) => chr(0204), chr(0226) => chr(0205), chr(0203) => chr(0206), chr(0213) => chr(0210), chr(0233) => chr(0211), chr(0211) => chr(0213), chr(0204) => chr(0214), chr(0223) => chr(0215), chr(0224) => chr(0216), chr(0221) => chr(0217), chr(0222) => chr(0220), chr(0202) => chr(0221), chr(0232) => chr(0235), chr(0230) => chr(037), chr(0231) => chr(0222), chr(0216) => chr(0231), chr(0240) => chr(040)];
        // mPDF 5.3.46
        return strtr($txt, $win1252to_pdf_doc_encoding);
    }
    public function set_form_text($w, $h, $name, $value = '', $default = '', $title = '', $flags = [], $align = 'L', $hidden = false, $maxlen = -1, $js = '', $background_col = false, $border_col = false)
    {
        $this->form_count++;
        if ($align === 'C') {
            $align = '1';
        } elseif ($align === 'R') {
            $align = '2';
        } else {
            $align = '0';
        }
        if ($maxlen < 1) {
            $maxlen = false;
        }
        if (!preg_match('/^[a-zA-Z0-9_:\-]+$/', $name)) {
            throw new \Mpdf\Mpdf_Exception('Field [' . $name . '] must have a name attribute, which can only contain letters, numbers, colon(:), undersore(_) or hyphen(-)');
        }
        if ($this->mpdf->only_core_fonts) {
            $value = $this->win1252to_pdf_doc_encoding($value);
            $default = $this->win1252to_pdf_doc_encoding($default);
            $title = $this->win1252to_pdf_doc_encoding($title);
        } else {
            if (isset($this->mpdf->current_font['subset'])) {
                $this->mpdf->utf8string_to_array($value);
                // Add characters to font subset
                $this->mpdf->utf8string_to_array($default);
                // Add characters to font subset
                $this->mpdf->utf8string_to_array($title);
                // Add characters to font subset
            }
            if ($value) {
                $value = $this->writer->utf8to_utf16big_endian($value);
            }
            if ($default) {
                $default = $this->writer->utf8to_utf16big_endian($default);
            }
            $title = $this->writer->utf8to_utf16big_endian($title);
        }
        if ($background_col) {
            $bg_c = $this->mpdf->set_color($background_col, 'CodeOnly');
        } else {
            $bg_c = $this->form_background_color;
        }
        if ($border_col) {
            $bc_c = $this->mpdf->set_color($border_col, 'CodeOnly');
        } else {
            $bc_c = $this->form_border_color;
        }
        $f = ['n' => $this->form_count, 'typ' => 'Tx', 'page' => $this->mpdf->page, 'x' => $this->mpdf->x, 'y' => $this->mpdf->y, 'w' => $w, 'h' => $h, 'T' => $name, 'FF' => $flags, 'V' => $value, 'DV' => $default, 'TU' => $title, 'hidden' => $hidden, 'Q' => $align, 'maxlen' => $maxlen, 'BS_W' => $this->form_border_width, 'BS_S' => $this->form_border_style, 'BC_C' => $bc_c, 'BG_C' => $bg_c, 'style' => ['font' => $this->mpdf->font_family, 'fontsize' => $this->mpdf->font_size_pt, 'fontcolor' => $this->mpdf->text_color]];
        if (is_array($js) && count($js) > 0) {
            $this->set_form_text_js($name, $js);
        }
        // mPDF 5.3.25
        if ($this->mpdf->keep_block_together) {
            $this->mpdf->kt_forms[] = $f;
        } elseif ($this->mpdf->writing_htm_lheader || $this->mpdf->writing_htm_lfooter) {
            $this->mpdf->htm_lheader_page_forms[] = $f;
        } else {
            if ($this->mpdf->col_active) {
                $this->mpdf->columnbuffer[] = ['s' => 'ACROFORM', 'col' => $this->mpdf->curr_col, 'x' => $this->mpdf->x, 'y' => $this->mpdf->y, 'h' => $h];
                $this->mpdf->column_forms[$this->mpdf->curr_col][(int) $this->mpdf->x][(int) $this->mpdf->y] = $this->form_count;
            }
            $this->forms[$this->form_count] = $f;
        }
        if (!in_array($this->mpdf->font_family, $this->form_fonts)) {
            $this->form_fonts[] = $this->mpdf->font_family;
            $this->mpdf->fonts[$this->mpdf->font_family]['used'] = true;
        }
        if (!$hidden) {
            $this->mpdf->x += $w;
        }
    }
    public function set_form_choice($w, $h, $name, $flags, array $array, $align = 'L', $js = '')
    {
        $this->form_count++;
        if ($this->mpdf->blk[$this->mpdf->blklvl]['direction'] === 'rtl') {
            $align = '2';
        } else {
            $align = '0';
        }
        if (!preg_match('/^[a-zA-Z0-9_:\-]+$/', $name)) {
            throw new \Mpdf\Mpdf_Exception('Field [' . $name . '] must have a name attribute, which can only contain letters, numbers, colon(:), undersore(_) or hyphen(-)');
        }
        if ($this->mpdf->only_core_fonts) {
            for ($i = 0; $i < count($array['VAL']); $i++) {
                $array['VAL'][$i] = $this->win1252to_pdf_doc_encoding($array['VAL'][$i]);
                $array['OPT'][$i] = $this->win1252to_pdf_doc_encoding($array['OPT'][$i]);
            }
        } else {
            for ($i = 0; $i < count($array['VAL']); $i++) {
                if (isset($this->mpdf->current_font['subset'])) {
                    $this->mpdf->utf8string_to_array($array['VAL'][$i]);
                    // Add characters to font subset
                    $this->mpdf->utf8string_to_array($array['OPT'][$i]);
                    // Add characters to font subset
                }
                if ($array['VAL'][$i]) {
                    $array['VAL'][$i] = $this->writer->utf8to_utf16big_endian($array['VAL'][$i]);
                }
                if ($array['OPT'][$i]) {
                    $array['OPT'][$i] = $this->writer->utf8to_utf16big_endian($array['OPT'][$i]);
                }
            }
        }
        $f = ['n' => $this->form_count, 'typ' => 'Ch', 'page' => $this->mpdf->page, 'x' => $this->mpdf->x, 'y' => $this->mpdf->y, 'w' => $w, 'h' => $h, 'T' => $name, 'OPT' => $array, 'FF' => $flags, 'Q' => $align, 'BS_W' => $this->form_border_width, 'BS_S' => $this->form_border_style, 'BC_C' => $this->form_border_color, 'BG_C' => $this->form_background_color, 'style' => ['font' => $this->mpdf->font_family, 'fontsize' => $this->mpdf->font_size_pt, 'fontcolor' => $this->mpdf->text_color]];
        if ($js) {
            $this->set_form_choice_js($name, $js);
        }
        if ($this->mpdf->keep_block_together) {
            $this->mpdf->kt_forms[] = $f;
        } elseif ($this->mpdf->writing_htm_lheader || $this->mpdf->writing_htm_lfooter) {
            $this->mpdf->htm_lheader_page_forms[] = $f;
        } else {
            if ($this->mpdf->col_active) {
                $this->mpdf->columnbuffer[] = ['s' => 'ACROFORM', 'col' => $this->mpdf->curr_col, 'x' => $this->mpdf->x, 'y' => $this->mpdf->y, 'h' => $h];
                $this->mpdf->column_forms[$this->mpdf->curr_col][(int) $this->mpdf->x][(int) $this->mpdf->y] = $this->form_count;
            }
            $this->forms[$this->form_count] = $f;
        }
        if (!in_array($this->mpdf->font_family, $this->form_fonts)) {
            $this->form_fonts[] = $this->mpdf->font_family;
            $this->mpdf->fonts[$this->mpdf->font_family]['used'] = true;
        }
        $this->mpdf->x += $w;
    }
    // CHECKBOX
    public function set_check_box($w, $h, $name, $value, $title = '', $checked = false, $flags = [], $disabled = false)
    {
        $this->set_form_button($w, $h, $name, $value, 'checkbox', $title, $flags, $checked, $disabled);
        $this->mpdf->x += $w;
    }
    // RADIO
    public function set_radio($w, $h, $name, $value, $title = '', $checked = false, $flags = [], $disabled = false)
    {
        $this->set_form_button($w, $h, $name, $value, 'radio', $title, $flags, $checked, $disabled);
        $this->mpdf->x += $w;
    }
    public function set_form_reset($w, $h, $name, $value = 'Reset', $title = '', $flags = [], $background_col = false, $border_col = false, $noprint = false)
    {
        if (!$name) {
            $name = 'Reset';
        }
        $this->set_form_button($w, $h, $name, $value, 'reset', $title, $flags, false, false, $background_col, $border_col, $noprint);
        $this->mpdf->x += $w;
    }
    public function set_js_button($w, $h, $name, $value, $js, $image_id = 0, $title = '', $flags = [], $indexed = false, $background_col = false, $border_col = false, $noprint = false)
    {
        $this->set_form_button($w, $h, $name, $value, 'js_button', $title, $flags, false, false, $background_col, $border_col, $noprint);
        // pos => 1 = no caption, icon only; 0 = caption only
        if ($image_id) {
            $this->form_button_icon[$this->writer->escape($name)] = ['pos' => 1, 'image_id' => $image_id, 'Indexed' => $indexed];
        }
        if ($js) {
            $this->set_form_button_js($name, $js);
        }
        $this->mpdf->x += $w;
    }
    public function set_form_submit($w, $h, $name, $value = 'Submit', $url = '', $title = '', $typ = 'html', $method = 'POST', $flags = [], $background_col = false, $border_col = false, $noprint = false)
    {
        if (!$name) {
            $name = 'Submit';
        }
        $this->set_form_button($w, $h, $name, $value, 'submit', $title, $flags, false, false, $background_col, $border_col, $noprint);
        $this->forms[$this->form_count]['URL'] = $url;
        $this->forms[$this->form_count]['method'] = $method;
        $this->forms[$this->form_count]['exporttype'] = $typ;
        $this->mpdf->x += $w;
    }
    public function set_form_button_text($ca, $rc = '', $ac = '')
    {
        if ($this->mpdf->only_core_fonts) {
            $ca = $this->win1252to_pdf_doc_encoding($ca);
            if ($rc) {
                $rc = $this->win1252to_pdf_doc_encoding($rc);
            }
            if ($ac) {
                $ac = $this->win1252to_pdf_doc_encoding($ac);
            }
        } else {
            if (isset($this->mpdf->current_font['subset'])) {
                $this->mpdf->utf8string_to_array($ca);
                // Add characters to font subset
            }
            $ca = $this->writer->utf8to_utf16big_endian($ca);
            if ($rc) {
                if (isset($this->mpdf->current_font['subset'])) {
                    $this->mpdf->utf8string_to_array($rc);
                }
                $rc = $this->writer->utf8to_utf16big_endian($rc);
            }
            if ($ac) {
                if (isset($this->mpdf->current_font['subset'])) {
                    $this->mpdf->utf8string_to_array($ac);
                }
                $ac = $this->writer->utf8to_utf16big_endian($ac);
            }
        }
        $this->form_button_text = $ca;
        $this->form_button_text_over = $rc ?: $ca;
        $this->form_button_text_click = $ac ?: $ca;
    }
    public function set_form_button($bb, $hh, $name, $value, $type, $title = '', $flags = [], $checked = false, $disabled = false, $background_col = false, $border_col = false, $noprint = false)
    {
        $this->form_count++;
        if (!preg_match('/^[a-zA-Z0-9_:\-]+$/', $name)) {
            throw new \Mpdf\Mpdf_Exception('Field [' . $name . '] must have a name attribute, which can only contain letters, numbers, colon(:), undersore(_) or hyphen(-)');
        }
        if (!$this->mpdf->only_core_fonts) {
            if (isset($this->mpdf->current_font['subset'])) {
                $this->mpdf->utf8string_to_array($title);
                // Add characters to font subset
                $this->mpdf->utf8string_to_array($value);
                // Add characters to font subset
            }
            $title = $this->writer->utf8to_utf16big_endian($title);
            if ($type === 'checkbox') {
                $uvalue = $this->writer->utf8to_utf16big_endian($value);
            } elseif ($type === 'radio') {
                $uvalue = $this->writer->utf8to_utf16big_endian($value);
                $value = mb_convert_encoding($value, 'Windows-1252', 'UTF-8');
            } else {
                $value = $this->writer->utf8to_utf16big_endian($value);
                $uvalue = $value;
            }
        } else {
            $title = $this->win1252to_pdf_doc_encoding($title);
            $value = $this->win1252to_pdf_doc_encoding($value);
            //// ??? not needed
            $uvalue = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
            $uvalue = $this->writer->utf8to_utf16big_endian($uvalue);
        }
        if ($type === 'radio' || $type === 'checkbox') {
            if (!preg_match('/^[a-zA-Z0-9_:\-\.]+$/', $value)) {
                throw new \Mpdf\Mpdf_Exception("Field '" . $name . "' must have a value, which can only contain letters, numbers, colon(:), underscore(_), hyphen(-) or period(.)");
            }
        }
        if ($type === 'radio') {
            if (!isset($this->form_radio_groups[$name])) {
                $this->form_radio_groups[$name] = ['page' => $this->mpdf->page, 'kids' => []];
            }
            $this->form_radio_groups[$name]['kids'][] = ['n' => $this->form_count, 'V' => $value, 'OPT' => $uvalue, 'disabled' => $disabled];
            if ($checked) {
                $this->form_radio_groups[$name]['on'] = $value;
            }
            // Disable the whole radio group if one is disabled, because of inconsistency in PDF readers
            if ($disabled) {
                $this->form_radio_groups[$name]['disabled'] = true;
            }
        }
        if ($type === 'checkbox') {
            $this->form_checkboxes = true;
        }
        if ($checked) {
            $activ = 1;
        } else {
            $activ = 0;
        }
        if ($background_col) {
            $bg_c = $this->mpdf->set_color($background_col, 'CodeOnly');
        } else {
            $bg_c = $this->form_button_background_color;
        }
        if ($border_col) {
            $bc_c = $this->mpdf->set_color($border_col, 'CodeOnly');
        } else {
            $bc_c = $this->form_button_border_color;
        }
        $f = ['n' => $this->form_count, 'typ' => 'Bt', 'page' => $this->mpdf->page, 'subtype' => $type, 'x' => $this->mpdf->x, 'y' => $this->mpdf->y, 'w' => $bb, 'h' => $hh, 'T' => $name, 'V' => $value, 'OPT' => $uvalue, 'TU' => $title, 'FF' => $flags, 'CA' => $this->form_button_text, 'RC' => $this->form_button_text_over, 'AC' => $this->form_button_text_click, 'BS_W' => $this->form_button_border_width, 'BS_S' => $this->form_button_border_style, 'BC_C' => $bc_c, 'BG_C' => $bg_c, 'activ' => $activ, 'disabled' => $disabled, 'noprint' => $noprint, 'style' => ['font' => $this->mpdf->font_family, 'fontsize' => $this->mpdf->font_size_pt, 'fontcolor' => $this->mpdf->text_color]];
        if ($this->mpdf->keep_block_together) {
            $this->mpdf->kt_forms[] = $f;
        } elseif ($this->mpdf->writing_htm_lheader || $this->mpdf->writing_htm_lfooter) {
            $this->mpdf->htm_lheader_page_forms[] = $f;
        } else {
            if ($this->mpdf->col_active) {
                $this->mpdf->columnbuffer[] = ['s' => 'ACROFORM', 'col' => $this->mpdf->curr_col, 'x' => $this->mpdf->x, 'y' => $this->mpdf->y, 'h' => $hh];
                $this->mpdf->column_forms[$this->mpdf->curr_col][(int) $this->mpdf->x][(int) $this->mpdf->y] = $this->form_count;
            }
            $this->forms[$this->form_count] = $f;
        }
        if (!in_array($this->mpdf->font_family, $this->form_fonts)) {
            $this->form_fonts[] = $this->mpdf->font_family;
            $this->mpdf->fonts[$this->mpdf->font_family]['used'] = true;
        }
        $this->form_button_text = null;
        $this->form_button_text_over = null;
        $this->form_button_text_click = null;
    }
    public function set_form_border_width($string)
    {
        switch ($string) {
            case 'S':
                $this->form_border_width = '1';
                break;
            case 'M':
                $this->form_border_width = '2';
                break;
            case 'B':
                $this->form_border_width = '3';
                break;
            case '0':
            default:
                $this->form_border_width = '0';
                break;
        }
    }
    public function set_form_border_style($string)
    {
        switch ($string) {
            case 'S':
                $this->form_border_style = 'S';
                break;
            case 'D':
                $this->form_border_style = 'D /D [3]';
                break;
            case 'B':
            default:
                $this->form_border_style = 'B';
                break;
            case 'I':
                $this->form_border_style = 'I';
                break;
            case 'U':
                $this->form_border_style = 'U';
                break;
        }
    }
    public function set_form_border_color($r, $g = -1, $b = -1)
    {
        $this->form_border_color = $this->get_color($r, $g, $b);
    }
    public function set_form_background_color($r, $g = -1, $b = -1)
    {
        $this->form_background_color = $this->get_color($r, $g, $b);
    }
    private function get_color($r, $g = -1, $b = -1)
    {
        if ($r == 0 && $g == 0 && $b == 0 || $g == -1) {
            return sprintf('%.3F', $r / 255);
        }
        return sprintf('%.3F %.3F %.3F', $r / 255, $g / 255, $b / 255);
    }
    public function set_form_d($W, $S, $BC, $BG)
    {
        $this->set_form_border_width($W);
        $this->set_form_border_style($S);
        $this->set_form_border_color($BC);
        $this->set_form_background_color($BG);
    }
    public function _setflag($array)
    {
        $flag = 0;
        foreach ($array as $val) {
            $flag += 1 << $val - 1;
        }
        return $flag;
    }
    public function _form_rect($x, $y, $w, $h, $h_pt)
    {
        $x *= Mpdf::SCALE;
        $y = $h_pt - $y * Mpdf::SCALE;
        $x2 = $x + $w * Mpdf::SCALE;
        $y2 = $y - $h * Mpdf::SCALE;
        return sprintf('%.3F %.3F %.3F %.3F', $x, $y2, $x2, $y);
    }
    public function _put_button_icon(array $array, $w, $h)
    {
        $info = true;
        if (isset($array['image_id'])) {
            $info = false;
            foreach ($this->mpdf->images as $iid => $img) {
                if ($img['i'] == $array['image_id']) {
                    $info = $this->mpdf->images[$iid];
                    break;
                }
            }
        }
        if (!$info) {
            throw new \Mpdf\Mpdf_Exception('Cannot find Button image');
        }
        $this->writer->object();
        $this->writer->write('<<');
        $this->writer->write('/Type /XObject');
        $this->writer->write('/Subtype /Image');
        $this->writer->write('/BBox [0 0 1 1]');
        $this->writer->write('/Length ' . strlen($info['data']));
        $this->writer->write('/BitsPerComponent ' . $info['bpc']);
        if ($info['cs'] === 'Indexed') {
            $this->writer->write('/ColorSpace [/Indexed /DeviceRGB ' . (strlen($info['pal']) / 3 - 1) . ' ' . ($this->mpdf->n + 1) . ' 0 R]');
        } else {
            $this->writer->write('/ColorSpace /' . $info['cs']);
            if ($info['cs'] === 'DeviceCMYK') {
                if ($info['type'] === 'jpg') {
                    $this->writer->write('/Decode [1 0 1 0 1 0 1 0]');
                }
            }
        }
        if (isset($info['f'])) {
            $this->writer->write('/Filter /' . $info['f']);
        }
        if (isset($info['parms'])) {
            $this->writer->write($info['parms']);
        }
        $this->writer->write('/Width ' . $info['w']);
        $this->writer->write('/Height ' . $info['h']);
        $this->writer->write('>>');
        $this->writer->stream($info['data']);
        $this->writer->write('endobj');
        //Palette
        if ($info['cs'] === 'Indexed') {
            $filter = $this->mpdf->compress ? '/Filter /FlateDecode ' : '';
            $this->writer->object();
            $pal = $this->mpdf->compress ? gzcompress($info['pal']) : $info['pal'];
            $this->writer->write('<<' . $filter . '/Length ' . strlen($pal) . '>>');
            $this->writer->stream($pal);
            $this->writer->write('endobj');
        }
    }
    public function _putform_bt(array $form, $h_pt)
    {
        $cc = 0;
        $put_js = 0;
        $put_icon = 0;
        $this->writer->object();
        $n = $this->mpdf->n;
        if ($form['subtype'] !== 'radio') {
            $this->pdf_acro_array .= $n . ' 0 R ';
            // Add to /Field element
        }
        $this->forms[$form['n']]['obj'] = $n;
        $this->writer->write('<<');
        $this->writer->write('/Type /Annot ');
        $this->writer->write('/Subtype /Widget');
        $this->writer->write('/NM ' . $this->writer->string(sprintf('%04u-%04u', $n, 7000 + $form['n'])));
        $this->writer->write('/M ' . $this->writer->string('D:' . date('YmdHis')));
        $this->writer->write('/Rect [ ' . $this->_form_rect($form['x'], $form['y'], $form['w'], $form['h'], $h_pt) . ' ]');
        $form['noprint'] ? $this->writer->write('/F 0 ') : $this->writer->write('/F 4 ');
        $this->writer->write('/FT /Btn ');
        $this->writer->write('/H /P ');
        if ($form['subtype'] !== 'radio') {
            // mPDF 5.3.23
            $this->writer->write('/T ' . $this->writer->string($form['T']));
        }
        $this->writer->write('/TU ' . $this->writer->string($form['TU']));
        if (isset($this->form_button_icon[$form['T']])) {
            $form['BS_W'] = 0;
        }
        if ($form['BS_W'] == 0) {
            $form['BC_C'] = $form['BG_C'];
        }
        $bstemp = '';
        $bstemp .= '/W ' . $form['BS_W'] . ' ';
        $bstemp .= '/S /' . $form['BS_S'] . ' ';
        $temp = '';
        $temp .= '/BC [ ' . $form['BC_C'] . ' ] ';
        $temp .= '/BG [ ' . $form['BG_C'] . ' ] ';
        if ($form['subtype'] === 'checkbox') {
            if ($form['disabled']) {
                $radio_color = '0.5 0.5 0.5';
                $radio_background_color = '0.9 0.9 0.9';
            } else {
                $radio_color = $this->form_radio_color;
                $radio_background_color = $this->form_radio_background_color;
            }
            $temp = '';
            $temp .= '/BC [ ' . $radio_color . ' ] ';
            $temp .= '/BG [ ' . $radio_background_color . ' ] ';
            $this->writer->write('/BS << /W 1 /S /S >>');
            $this->writer->write("/MK << {$temp} >>");
            $this->writer->write('/Ff ' . $this->_setflag($form['FF']));
            if ($form['activ']) {
                $this->writer->write('/V /' . $this->writer->escape($form['V']) . ' ');
                $this->writer->write('/DV /' . $this->writer->escape($form['V']) . ' ');
                $this->writer->write('/AS /' . $this->writer->escape($form['V']) . ' ');
            } else {
                $this->writer->write('/AS /Off ');
            }
            if ($this->form_use_zap_d) {
                $this->writer->write('/DA (/F' . $this->mpdf->fonts['czapfdingbats']['i'] . ' 0 Tf ' . $radio_color . ' rg)');
                $this->writer->write('/AP << /N << /' . $this->writer->escape($form['V']) . ' ' . ($this->mpdf->n + 1) . ' 0 R /Off /Off >> >>');
            } else {
                $this->writer->write('/DA (/F' . $this->mpdf->fonts[$this->mpdf->current_font['fontkey']]['i'] . ' 0 Tf ' . $radio_color . ' rg)');
                $this->writer->write('/AP << /N << /' . $this->writer->escape($form['V']) . ' ' . ($this->mpdf->n + 1) . ' 0 R /Off ' . ($this->mpdf->n + 2) . ' 0 R >> >>');
            }
            $this->writer->write('/Opt [ ' . $this->writer->string($form['OPT']) . ' ' . $this->writer->string($form['OPT']) . ' ]');
        }
        if ($form['subtype'] === 'radio') {
            if (isset($form['disabled']) && $form['disabled'] || isset($this->form_radio_groups[$form['T']]['disabled']) && $this->form_radio_groups[$form['T']]['disabled']) {
                $radio_color = '0.5 0.5 0.5';
                $radio_background_color = '0.9 0.9 0.9';
            } else {
                $radio_color = $this->form_radio_color;
                $radio_background_color = $this->form_radio_background_color;
            }
            $this->writer->write('/Parent ' . $this->form_radio_groups[$form['T']]['obj_id'] . ' 0 R ');
            $temp = '';
            $temp .= '/BC [ ' . $radio_color . ' ] ';
            $temp .= '/BG [ ' . $radio_background_color . ' ] ';
            $this->writer->write('/BS << /W 1 /S /S >>');
            $this->writer->write('/MK << ' . $temp . ' >> ');
            $form['FF'][] = self::FLAG_NOTOGGLEOFF;
            $form['FF'][] = self::FLAG_RADIO;
            // must be same as radio button group setting?
            $this->writer->write('/Ff ' . $this->_setflag($form['FF']));
            if ($this->form_use_zap_d) {
                $this->writer->write('/DA (/F' . $this->mpdf->fonts['czapfdingbats']['i'] . ' 0 Tf ' . $radio_color . ' rg)');
            } else {
                $this->writer->write('/DA (/F' . $this->mpdf->fonts[$this->mpdf->current_font['fontkey']]['i'] . ' 0 Tf ' . $radio_color . ' rg)');
            }
            $this->writer->write('/AP << /N << /' . $this->writer->escape($form['V']) . ' ' . ($this->mpdf->n + 1) . ' 0 R /Off ' . ($this->mpdf->n + 2) . ' 0 R >> >>');
            if ($form['activ']) {
                $this->writer->write('/V /' . $this->writer->escape($form['V']) . ' ');
                $this->writer->write('/DV /' . $this->writer->escape($form['V']) . ' ');
                $this->writer->write('/AS /' . $this->writer->escape($form['V']) . ' ');
            } else {
                $this->writer->write('/AS /Off ');
            }
            $this->writer->write('/AP << /N << /' . $this->writer->escape($form['V']) . ' ' . ($this->mpdf->n + 1) . ' 0 R /Off ' . ($this->mpdf->n + 2) . ' 0 R >> >>');
            // $this->writer->write('/Opt [ '.$this->writer->string($form['OPT']).' '.$this->writer->string($form['OPT']).' ]');
        }
        if ($form['subtype'] === 'reset') {
            $temp .= $form['CA'] ? '/CA ' . $this->writer->string($form['CA']) . ' ' : '/CA ' . $this->writer->string($form['T']) . ' ';
            $temp .= $form['RC'] ? '/RC ' . $this->writer->string($form['RC']) . ' ' : '/RC ' . $this->writer->string($form['T']) . ' ';
            $temp .= $form['AC'] ? '/AC ' . $this->writer->string($form['AC']) . ' ' : '/AC ' . $this->writer->string($form['T']) . ' ';
            $this->writer->write("/BS << {$bstemp} >>");
            $this->writer->write('/MK << ' . $temp . ' >>');
            $this->writer->write('/DA (/F' . $this->mpdf->fonts[$form['style']['font']]['i'] . ' ' . $form['style']['fontsize'] . ' Tf ' . $form['style']['fontcolor'] . ')');
            $this->writer->write('/AA << /D << /S /ResetForm /Flags 1 >> >>');
            $form['FF'][] = 17;
            $this->writer->write('/Ff ' . $this->_setflag($form['FF']));
        }
        if ($form['subtype'] === 'submit') {
            $temp .= $form['CA'] ? '/CA ' . $this->writer->string($form['CA']) . ' ' : '/CA ' . $this->writer->string($form['T']) . ' ';
            $temp .= $form['RC'] ? '/RC ' . $this->writer->string($form['RC']) . ' ' : '/RC ' . $this->writer->string($form['T']) . ' ';
            $temp .= $form['AC'] ? '/AC ' . $this->writer->string($form['AC']) . ' ' : '/AC ' . $this->writer->string($form['T']) . ' ';
            $this->writer->write("/BS << {$bstemp} >>");
            $this->writer->write("/MK << {$temp} >>");
            $this->writer->write('/DA (/F' . $this->mpdf->fonts[$form['style']['font']]['i'] . ' ' . $form['style']['fontsize'] . ' Tf ' . $form['style']['fontcolor'] . ')');
            // Bit 4 (8) = useGETmethod else use POST
            // Bit 3 (4) = HTML export format (charset chosen by Adobe)--- OR ---
            // Bit 6 (32) = XFDF export format (form of XML in UTF-8)
            if ($form['exporttype'] === 'xfdf') {
                $flag = 32;
            } elseif ($form['method'] === 'GET') {
                // 'xfdf' or 'html'
                $flag = 12;
            } else {
                $flag = 4;
            }
            // Bit 2 (2) = IncludeNoValueFields
            if ($this->form_submit_no_value_fields) {
                $flag += 2;
            }
            // To submit a value, needs to be in /AP dictionary, AND this object must contain a /Fields entry
            // listing all fields to output
            $this->writer->write('/AA << /D << /S /SubmitForm /F (' . $form['URL'] . ') /Flags ' . $flag . ' >> >>');
            $form['FF'][] = 17;
            $this->writer->write('/Ff ' . $this->_setflag($form['FF']));
        }
        if ($form['subtype'] === 'js_button') {
            // Icon / image
            if (isset($this->form_button_icon[$form['T']])) {
                $cc++;
                $temp .= '/TP ' . $this->form_button_icon[$form['T']]['pos'] . ' ';
                $temp .= '/I ' . ($cc + $this->mpdf->n) . ' 0 R ';
                // Normal icon
                $temp .= '/RI ' . ($cc + $this->mpdf->n) . ' 0 R ';
                // onMouseOver
                $temp .= '/IX ' . ($cc + $this->mpdf->n) . ' 0 R ';
                // onClick / onMouseDown
                $temp .= '/IF << /SW /A /S /A /A [0.0 0.0] >> ';
                // Icon fit dictionary
                if ($this->form_button_icon[$form['T']]['Indexed']) {
                    $cc++;
                }
                $put_icon = 1;
            }
            $temp .= $form['CA'] ? '/CA ' . $this->writer->string($form['CA']) . ' ' : '/CA ' . $this->writer->string($form['T']) . ' ';
            $temp .= $form['RC'] ? '/RC ' . $this->writer->string($form['RC']) . ' ' : '/RC ' . $this->writer->string($form['T']) . ' ';
            $temp .= $form['AC'] ? '/AC ' . $this->writer->string($form['AC']) . ' ' : '/AC ' . $this->writer->string($form['T']) . ' ';
            $this->writer->write("/BS << {$bstemp} >>");
            $this->writer->write("/MK << {$temp} >>");
            $this->writer->write('/DA (/F' . $this->mpdf->fonts[$form['style']['font']]['i'] . ' ' . $form['style']['fontsize'] . ' Tf ' . $form['style']['fontcolor'] . ')');
            $form['FF'][] = 17;
            $this->writer->write('/Ff ' . $this->_setflag($form['FF']));
            // Javascript
            if (isset($this->array_form_button_js[$form['T']])) {
                $cc++;
                $this->writer->write('/AA << /D ' . ($cc + $this->mpdf->n) . ' 0 R >>');
                $put_js = 1;
            }
        }
        $this->writer->write('>>');
        $this->writer->write('endobj');
        // additional objects
        // obj icon
        if ($put_icon === 1) {
            $this->_put_button_icon($this->form_button_icon[$form['T']], $form['w'], $form['h']);
            $put_icon = null;
        }
        // obj + 1
        if ($put_js === 1) {
            $this->mpdf->_set_object_javascript($this->array_form_button_js[$form['T']]['js']);
            unset($this->array_form_button_js[$form['T']]);
            $put_js = null;
        }
        // RADIO and CHECK BOX appearance streams
        $filter = $this->mpdf->compress ? '/Filter /FlateDecode ' : '';
        if ($form['subtype'] === 'radio') {
            // output 2 appearance streams for radio buttons on/off
            if ($this->form_use_zap_d) {
                $fs = sprintf('%.3F', $form['style']['fontsize'] * 1.25);
                $fi = 'czapfdingbats';
                $r_on = 'q ' . $radio_color . ' rg BT /F' . $this->mpdf->fonts[$fi]['i'] . ' ' . $fs . ' Tf 0 0 Td (4) Tj ET Q';
                $r_off = 'q ' . $radio_color . ' rg BT /F' . $this->mpdf->fonts[$fi]['i'] . ' ' . $fs . ' Tf 0 0 Td (8) Tj ET Q';
            } else {
                $matrix = sprintf('%.3F 0 0 %.3F 0 %.3F', $form['style']['fontsize'] * 1.33 / 10, $form['style']['fontsize'] * 1.25 / 10, $form['style']['fontsize']);
                $fill = $radio_background_color . ' rg 3.778 -7.410 m 2.800 -7.410 1.947 -7.047 1.225 -6.322 c 0.500 -5.600 0.138 -4.747 0.138 -3.769 c 0.138 -2.788 0.500 -1.938 1.225 -1.213 c 1.947 -0.491 2.800 -0.128 3.778 -0.128 c 4.757 -0.128 5.610 -0.491 6.334 -1.213 c 7.056 -1.938 7.419 -2.788 7.419 -3.769 c 7.419 -4.747 7.056 -5.600 6.334 -6.322 c 5.610 -7.047 4.757 -7.410 3.778 -7.410 c h f ';
                $circle = '3.778 -6.963 m 4.631 -6.963 5.375 -6.641 6.013 -6.004 c 6.653 -5.366 6.972 -4.619 6.972 -3.769 c 6.972 -2.916 6.653 -2.172 6.013 -1.532 c 5.375 -0.894 4.631 -0.576 3.778 -0.576 c 2.928 -0.576 2.182 -0.894 1.544 -1.532 c 0.904 -2.172 0.585 -2.916 0.585 -3.769 c 0.585 -4.619 0.904 -5.366 1.544 -6.004 c 2.182 -6.641 2.928 -6.963 3.778 -6.963 c h 3.778 -7.410 m 2.800 -7.410 1.947 -7.047 1.225 -6.322 c 0.500 -5.600 0.138 -4.747 0.138 -3.769 c 0.138 -2.788 0.500 -1.938 1.225 -1.213 c 1.947 -0.491 2.800 -0.128 3.778 -0.128 c 4.757 -0.128 5.610 -0.491 6.334 -1.213 c 7.056 -1.938 7.419 -2.788 7.419 -3.769 c 7.419 -4.747 7.056 -5.600 6.334 -6.322 c 5.610 -7.047 4.757 -7.410 3.778 -7.410 c h f ';
                $r_on = 'q ' . $matrix . ' cm ' . $fill . $radio_color . ' rg ' . $circle . '  ' . $radio_color . ' rg
5.184 -5.110 m 4.800 -5.494 4.354 -5.685 3.841 -5.685 c 3.331 -5.685 2.885 -5.494 2.501 -5.110 c 2.119 -4.725 1.925 -4.279 1.925 -3.769 c 1.925 -3.257 2.119 -2.810 2.501 -2.429 c 2.885 -2.044 3.331 -1.853 3.841 -1.853 c 4.354 -1.853 4.800 -2.044 5.184 -2.429 c 5.566 -2.810 5.760 -3.257 5.760 -3.769 c 5.760 -4.279 5.566 -4.725 5.184 -5.110 c h
f Q ';
                $r_off = 'q ' . $matrix . ' cm ' . $fill . $radio_color . ' rg ' . $circle . '  Q ';
            }
            $this->writer->object();
            $p = $this->mpdf->compress ? gzcompress($r_on) : $r_on;
            $this->writer->write('<<' . $filter . '/Length ' . strlen($p) . ' /Resources 2 0 R>>');
            $this->writer->stream($p);
            $this->writer->write('endobj');
            $this->writer->object();
            $p = $this->mpdf->compress ? gzcompress($r_off) : $r_off;
            $this->writer->write('<<' . $filter . '/Length ' . strlen($p) . ' /Resources 2 0 R>>');
            $this->writer->stream($p);
            $this->writer->write('endobj');
        }
        if ($form['subtype'] === 'checkbox') {
            // First output appearance stream for check box on
            if ($this->form_use_zap_d) {
                $fs = sprintf('%.3F', $form['style']['fontsize'] * 1.25);
                $fi = 'czapfdingbats';
                $cb_on = 'q ' . $radio_color . ' rg BT /F' . $this->mpdf->fonts[$fi]['i'] . ' ' . $fs . ' Tf 0 0 Td (4) Tj ET Q';
                $cb_off = 'q ' . $radio_color . ' rg BT /F' . $this->mpdf->fonts[$fi]['i'] . ' ' . $fs . ' Tf 0 0 Td (8) Tj ET Q';
            } else {
                $matrix = sprintf('%.3F 0 0 %.3F 0 %.3F', $form['style']['fontsize'] * 1.33 / 10, $form['style']['fontsize'] * 1.25 / 10, $form['style']['fontsize']);
                $fill = $radio_background_color . ' rg 7.395 -0.070 m 7.395 -7.344 l 0.121 -7.344 l 0.121 -0.070 l 7.395 -0.070 l h  f ';
                $square = '0.508 -6.880 m 6.969 -6.880 l 6.969 -0.534 l 0.508 -0.534 l 0.508 -6.880 l h 7.395 -0.070 m 7.395 -7.344 l 0.121 -7.344 l 0.121 -0.070 l 7.395 -0.070 l h ';
                $cb_on = 'q ' . $matrix . ' cm ' . $fill . $radio_color . ' rg ' . $square . ' f ' . $radio_color . ' rg
6.321 -1.352 m 5.669 -2.075 5.070 -2.801 4.525 -3.532 c 3.979 -4.262 3.508 -4.967 3.112 -5.649 c 3.080 -5.706 3.039 -5.779 2.993 -5.868 c 2.858 -6.118 2.638 -6.243 2.334 -6.243 c 2.194 -6.243 2.100 -6.231 2.052 -6.205 c 2.003 -6.180 1.954 -6.118 1.904 -6.020 c 1.787 -5.788 1.688 -5.523 1.604 -5.226 c 1.521 -4.930 1.480 -4.721 1.480 -4.600 c 1.480 -4.535 1.491 -4.484 1.512 -4.447 c 1.535 -4.410 1.579 -4.367 1.647 -4.319 c 1.733 -4.259 1.828 -4.210 1.935 -4.172 c 2.040 -4.134 2.131 -4.115 2.205 -4.115 c 2.267 -4.115 2.341 -4.232 2.429 -4.469 c 2.437 -4.494 2.444 -4.511 2.448 -4.522 c 2.451 -4.531 2.456 -4.546 2.465 -4.568 c 2.546 -4.795 2.614 -4.910 2.668 -4.910 c 2.714 -4.910 2.898 -4.652 3.219 -4.136 c 3.539 -3.620 3.866 -3.136 4.197 -2.683 c 4.426 -2.367 4.633 -2.103 4.816 -1.889 c 4.998 -1.676 5.131 -1.544 5.211 -1.493 c 5.329 -1.426 5.483 -1.368 5.670 -1.319 c 5.856 -1.271 6.066 -1.238 6.296 -1.217 c 6.321 -1.352 l h  f  Q ';
                $cb_off = 'q ' . $matrix . ' cm ' . $fill . $radio_color . ' rg ' . $square . ' f Q ';
            }
            $this->writer->object();
            $p = $this->mpdf->compress ? gzcompress($cb_on) : $cb_on;
            $this->writer->write('<<' . $filter . '/Length ' . strlen($p) . ' /Resources 2 0 R>>');
            $this->writer->stream($p);
            $this->writer->write('endobj');
            // output appearance stream for check box off (only if not using ZapfDingbats)
            if (!$this->form_use_zap_d) {
                $this->writer->object();
                $p = $this->mpdf->compress ? gzcompress($cb_off) : $cb_off;
                $this->writer->write('<<' . $filter . '/Length ' . strlen($p) . ' /Resources 2 0 R>>');
                $this->writer->stream($p);
                $this->writer->write('endobj');
            }
        }
        return $n;
    }
    public function _putform_ch(array $form, $h_pt)
    {
        $put_js = 0;
        $this->writer->object();
        $n = $this->mpdf->n;
        $this->pdf_acro_array .= $n . ' 0 R ';
        $this->forms[$form['n']]['obj'] = $n;
        $this->writer->write('<<');
        $this->writer->write('/Type /Annot ');
        $this->writer->write('/Subtype /Widget');
        $this->writer->write('/Rect [ ' . $this->_form_rect($form['x'], $form['y'], $form['w'], $form['h'], $h_pt) . ' ]');
        $this->writer->write('/F 4');
        $this->writer->write('/FT /Ch');
        if ($form['Q']) {
            $this->writer->write('/Q ' . $form['Q'] . '');
        }
        $temp = '';
        $temp .= '/W ' . $form['BS_W'] . ' ';
        $temp .= '/S /' . $form['BS_S'] . ' ';
        $this->writer->write("/BS << {$temp} >>");
        $temp = '';
        $temp .= '/BC [ ' . $form['BC_C'] . ' ] ';
        $temp .= '/BG [ ' . $form['BG_C'] . ' ] ';
        $this->writer->write('/MK << ' . $temp . ' >>');
        $this->writer->write('/NM ' . $this->writer->string(sprintf('%04u-%04u', $n, 6000 + $form['n'])));
        $this->writer->write('/M ' . $this->writer->string('D:' . date('YmdHis')));
        $this->writer->write('/T ' . $this->writer->string($form['T']));
        $this->writer->write('/DA (/F' . $this->mpdf->fonts[$form['style']['font']]['i'] . ' ' . $form['style']['fontsize'] . ' Tf ' . $form['style']['fontcolor'] . ')');
        $opt = '';
        $count = count($form['OPT']['VAL']);
        for ($i = 0; $i < $count; $i++) {
            $opt .= '[ ' . $this->writer->string($form['OPT']['VAL'][$i]) . ' ' . $this->writer->string($form['OPT']['OPT'][$i]) . ' ] ';
        }
        $this->writer->write('/Opt [ ' . $opt . ']');
        // selected
        $select_item = false;
        $select_index = false;
        foreach ($form['OPT']['SEL'] as $select_val) {
            $select_name = $this->writer->string($form['OPT']['VAL'][$select_val]);
            $select_item .= ' ' . $select_name . ' ';
            $select_index .= ' ' . $select_val . ' ';
        }
        if ($select_item) {
            if (count($form['OPT']['SEL']) < 2) {
                $this->writer->write('/V ' . $select_item . ' ');
                $this->writer->write('/DV ' . $select_item . ' ');
            } else {
                $this->writer->write('/V [' . $select_item . '] ');
                $this->writer->write('/DV [' . $select_item . '] ');
            }
            $this->writer->write('/I [' . $select_index . '] ');
        }
        if (is_array($form['FF']) && count($form['FF']) > 0) {
            $this->writer->write('/Ff ' . $this->_setflag($form['FF']) . ' ');
        }
        // Javascript
        if (isset($this->array_form_choice_js[$form['T']])) {
            $this->writer->write('/AA << /V ' . ($this->mpdf->n + 1) . ' 0 R >>');
            $put_js = 1;
        }
        $this->writer->write('>>');
        $this->writer->write('endobj');
        // obj + 1
        if ($put_js === 1) {
            $this->mpdf->_set_object_javascript($this->array_form_choice_js[$form['T']]['js']);
            unset($this->array_form_choice_js[$form['T']]);
            $put_js = null;
        }
        return $n;
    }
    public function _putform_tx(array $form, $h_pt)
    {
        $put_js = 0;
        $this->writer->object();
        $n = $this->mpdf->n;
        $this->pdf_acro_array .= $n . ' 0 R ';
        $this->forms[$form['n']]['obj'] = $n;
        $this->writer->write('<<');
        $this->writer->write('/Type /Annot ');
        $this->writer->write('/Subtype /Widget ');
        $this->writer->write('/Rect [ ' . $this->_form_rect($form['x'], $form['y'], $form['w'], $form['h'], $h_pt) . ' ] ');
        $form['hidden'] ? $this->writer->write('/F 2 ') : $this->writer->write('/F 4 ');
        $this->writer->write('/FT /Tx ');
        $this->writer->write('/H /N ');
        $this->writer->write('/R 0 ');
        if (is_array($form['FF']) && count($form['FF']) > 0) {
            $this->writer->write('/Ff ' . $this->_setflag($form['FF']) . ' ');
        }
        if (isset($form['maxlen']) && $form['maxlen'] > 0) {
            $this->writer->write('/MaxLen ' . $form['maxlen']);
        }
        $temp = '';
        $temp .= '/W ' . $form['BS_W'] . ' ';
        $temp .= '/S /' . $form['BS_S'] . ' ';
        $this->writer->write("/BS << {$temp} >>");
        $temp = '';
        $temp .= '/BC [ ' . $form['BC_C'] . ' ] ';
        $temp .= '/BG [ ' . $form['BG_C'] . ' ] ';
        $this->writer->write('/MK <<' . $temp . ' >>');
        $this->writer->write('/T ' . $this->writer->string($form['T']));
        $this->writer->write('/TU ' . $this->writer->string($form['TU']));
        if ($form['V'] || $form['V'] === '0') {
            $this->writer->write('/V ' . $this->writer->string($form['V']));
        }
        $this->writer->write('/DV ' . $this->writer->string($form['DV']));
        $this->writer->write('/DA (/F' . $this->mpdf->fonts[$form['style']['font']]['i'] . ' ' . $form['style']['fontsize'] . ' Tf ' . $form['style']['fontcolor'] . ')');
        if ($form['Q']) {
            $this->writer->write('/Q ' . $form['Q'] . '');
        }
        $this->writer->write('/NM ' . $this->writer->string(sprintf('%04u-%04u', $n, 5000 + $form['n'])));
        $this->writer->write('/M ' . $this->writer->string('D:' . date('YmdHis')));
        if (isset($this->array_form_text_js[$form['T']])) {
            $put_js = 1;
            $cc = 0;
            $js_str = '';
            if (isset($this->array_form_text_js[$form['T']]['F'])) {
                $cc++;
                $js_str .= '/F ' . ($cc + $this->mpdf->n) . ' 0 R ';
            }
            if (isset($this->array_form_text_js[$form['T']]['K'])) {
                $cc++;
                $js_str .= '/K ' . ($cc + $this->mpdf->n) . ' 0 R ';
            }
            if (isset($this->array_form_text_js[$form['T']]['V'])) {
                $cc++;
                $js_str .= '/V ' . ($cc + $this->mpdf->n) . ' 0 R ';
            }
            if (isset($this->array_form_text_js[$form['T']]['C'])) {
                $cc++;
                $js_str .= '/C ' . ($cc + $this->mpdf->n) . ' 0 R ';
                $this->pdf_array_co .= $this->mpdf->n . ' 0 R ';
            }
            $this->writer->write('/AA << ' . $js_str . ' >>');
        }
        $this->writer->write('>>');
        $this->writer->write('endobj');
        if ($put_js == 1) {
            if (isset($this->array_form_text_js[$form['T']]['F'])) {
                $this->mpdf->_set_object_javascript($this->array_form_text_js[$form['T']]['F']['js']);
                unset($this->array_form_text_js[$form['T']]['F']);
            }
            if (isset($this->array_form_text_js[$form['T']]['K'])) {
                $this->mpdf->_set_object_javascript($this->array_form_text_js[$form['T']]['K']['js']);
                unset($this->array_form_text_js[$form['T']]['K']);
            }
            if (isset($this->array_form_text_js[$form['T']]['V'])) {
                $this->mpdf->_set_object_javascript($this->array_form_text_js[$form['T']]['V']['js']);
                unset($this->array_form_text_js[$form['T']]['V']);
            }
            if (isset($this->array_form_text_js[$form['T']]['C'])) {
                $this->mpdf->_set_object_javascript($this->array_form_text_js[$form['T']]['C']['js']);
                unset($this->array_form_text_js[$form['T']]['C']);
            }
        }
        return $n;
    }
}