<?php

declare (strict_types=1);
namespace Mpdf;

use Mpdf\Color\Color_Converter;
use Mpdf\Css\Text_Vars;
class Direct_Write
{
    /**
     * @var \Mpdf\Mpdf
     */
    private $mpdf;
    /**
     * @var \Mpdf\Otl
     */
    private $otl;
    /**
     * @var \Mpdf\SizeConverter
     */
    private $size_converter;
    /**
     * @var \Mpdf\Color\ColorConverter
     */
    private $color_converter;
    public function __construct(Mpdf $mpdf, Otl $otl, Size_Converter $size_converter, Color_Converter $color_converter)
    {
        $this->mpdf = $mpdf;
        $this->otl = $otl;
        $this->size_converter = $size_converter;
        $this->color_converter = $color_converter;
    }
    public function Write($h, $txt, $currentx = 0, $link = '', $directionality = 'ltr', $align = '', $fill = 0)
    {
        if (!$align) {
            if ($directionality === 'rtl') {
                $align = 'R';
            } else {
                $align = 'L';
            }
        }
        if ($h == 0) {
            $this->mpdf->set_line_height();
            $h = $this->mpdf->lineheight;
        }
        //Output text in flowing mode
        $w = $this->mpdf->w - $this->mpdf->r_margin - $this->mpdf->x;
        $wmax = $w - ($this->mpdf->c_margin_l + $this->mpdf->c_margin_r);
        $s = str_replace("\r", '', $txt);
        if ($this->mpdf->using_core_font) {
            $nb = strlen($s);
        } else {
            $nb = mb_strlen($s, $this->mpdf->mb_enc);
            // handle single space character
            if ($nb === 1 && $s === ' ') {
                $this->mpdf->x += $this->mpdf->get_string_width($s);
                return;
            }
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        if (!$this->mpdf->using_core_font) {
            if (preg_match('/([' . $this->mpdf->preg_rt_lchars . '])/u', $txt)) {
                $this->mpdf->bi_directional = true;
            }
            // *RTL*
            while ($i < $nb) {
                //Get next character
                $c = mb_substr($s, $i, 1, $this->mpdf->mb_enc);
                if ($c === "\n") {
                    // WORD SPACING
                    $this->mpdf->reset_spacing();
                    //Explicit line break
                    $tmp = rtrim(mb_substr($s, $j, $i - $j, $this->mpdf->mb_enc));
                    $this->mpdf->Cell($w, $h, $tmp, 0, 2, $align, $fill, $link);
                    $i++;
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    if ($nl === 1) {
                        if ($currentx != 0) {
                            $this->mpdf->x = $currentx;
                        } else {
                            $this->mpdf->x = $this->mpdf->l_margin;
                        }
                        $w = $this->mpdf->w - $this->mpdf->r_margin - $this->mpdf->x;
                        $wmax = $w - ($this->mpdf->c_margin_l + $this->mpdf->c_margin_r);
                    }
                    $nl++;
                    continue;
                }
                if ($c === ' ') {
                    $sep = $i;
                }
                $l += $this->mpdf->get_char_width_non_core($c);
                // mPDF 5.3.04
                if ($l > $wmax) {
                    //Automatic line break (word wrapping)
                    if ($sep == -1) {
                        // WORD SPACING
                        $this->mpdf->reset_spacing();
                        if ($this->mpdf->x > $this->mpdf->l_margin) {
                            //Move to next line
                            if ($currentx != 0) {
                                $this->mpdf->x = $currentx;
                            } else {
                                $this->mpdf->x = $this->mpdf->l_margin;
                            }
                            $this->mpdf->y += $h;
                            $w = $this->mpdf->w - $this->mpdf->r_margin - $this->mpdf->x;
                            $wmax = $w - ($this->mpdf->c_margin_l + $this->mpdf->c_margin_r);
                            $i++;
                            $nl++;
                            continue;
                        }
                        if ($i == $j) {
                            $i++;
                        }
                        $tmp = rtrim(mb_substr($s, $j, $i - $j, $this->mpdf->mb_enc));
                        $this->mpdf->Cell($w, $h, $tmp, 0, 2, $align, $fill, $link);
                    } else {
                        $tmp = rtrim(mb_substr($s, $j, $sep - $j, $this->mpdf->mb_enc));
                        if ($align === 'J') {
                            //////////////////////////////////////////
                            // JUSTIFY J using Unicode fonts (Word spacing doesn't work)
                            // WORD SPACING
                            // Change NON_BREAKING SPACE to spaces so they are 'spaced' properly
                            $tmp = str_replace(chr(194) . chr(160), chr(32), $tmp);
                            $len_ligne = $this->mpdf->get_string_width($tmp);
                            $nb_carac = mb_strlen($tmp, $this->mpdf->mb_enc);
                            $nb_spaces = mb_substr_count($tmp, ' ', $this->mpdf->mb_enc);
                            $incl_cursive = false;
                            if (!empty($this->mpdf->current_font['useOTL']) && preg_match('/([' . $this->mpdf->preg_cur_schars . '])/u', $tmp)) {
                                $incl_cursive = true;
                            }
                            list($charspacing, $ws) = $this->mpdf->get_jspacing($nb_carac, $nb_spaces, ($w - 2 - $len_ligne) * Mpdf::SCALE, $incl_cursive);
                            $this->mpdf->set_spacing($charspacing, $ws);
                            //////////////////////////////////////////
                        }
                        $this->mpdf->Cell($w, $h, $tmp, 0, 2, $align, $fill, $link);
                        $i = $sep + 1;
                    }
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    if ($nl === 1) {
                        if ($currentx != 0) {
                            $this->mpdf->x = $currentx;
                        } else {
                            $this->mpdf->x = $this->mpdf->l_margin;
                        }
                        $w = $this->mpdf->w - $this->mpdf->r_margin - $this->mpdf->x;
                        $wmax = $w - ($this->mpdf->c_margin_l + $this->mpdf->c_margin_r);
                    }
                    $nl++;
                } else {
                    $i++;
                }
            }
            //Last chunk
            // WORD SPACING
            $this->mpdf->reset_spacing();
        } else {
            while ($i < $nb) {
                //Get next character
                $c = $s[$i];
                if ($c === "\n") {
                    //Explicit line break
                    // WORD SPACING
                    $this->mpdf->reset_spacing();
                    $this->mpdf->Cell($w, $h, substr($s, $j, $i - $j), 0, 2, $align, $fill, $link);
                    $i++;
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    if ($nl === 1) {
                        if ($currentx != 0) {
                            $this->mpdf->x = $currentx;
                        } else {
                            $this->mpdf->x = $this->mpdf->l_margin;
                        }
                        $w = $this->mpdf->w - $this->mpdf->r_margin - $this->mpdf->x;
                        $wmax = $w - ($this->mpdf->c_margin_l + $this->mpdf->c_margin_r);
                    }
                    $nl++;
                    continue;
                }
                if ($c === ' ') {
                    $sep = $i;
                }
                $l += $this->mpdf->get_char_width_core($c);
                // mPDF 5.3.04
                if ($l > $wmax) {
                    //Automatic line break (word wrapping)
                    if ($sep == -1) {
                        // WORD SPACING
                        $this->mpdf->reset_spacing();
                        if ($this->mpdf->x > $this->mpdf->l_margin) {
                            //Move to next line
                            if ($currentx != 0) {
                                $this->mpdf->x = $currentx;
                            } else {
                                $this->mpdf->x = $this->mpdf->l_margin;
                            }
                            $this->mpdf->y += $h;
                            $w = $this->mpdf->w - $this->mpdf->r_margin - $this->mpdf->x;
                            $wmax = $w - ($this->mpdf->c_margin_l + $this->mpdf->c_margin_r);
                            $i++;
                            $nl++;
                            continue;
                        }
                        if ($i == $j) {
                            $i++;
                        }
                        $this->mpdf->Cell($w, $h, substr($s, $j, $i - $j), 0, 2, $align, $fill, $link);
                    } else {
                        $tmp = substr($s, $j, $sep - $j);
                        if ($align === 'J') {
                            //////////////////////////////////////////
                            // JUSTIFY J using Unicode fonts
                            // WORD SPACING is not fully supported for complex scripts
                            // Change NON_BREAKING SPACE to spaces so they are 'spaced' properly
                            $tmp = str_replace(chr(160), chr(32), $tmp);
                            $len_ligne = $this->mpdf->get_string_width($tmp);
                            $nb_carac = strlen($tmp);
                            $nb_spaces = substr_count($tmp, ' ');
                            list($charspacing, $ws) = $this->mpdf->get_jspacing($nb_carac, $nb_spaces, ($w - 2 - $len_ligne) * Mpdf::SCALE, $false);
                            $this->mpdf->set_spacing($charspacing, $ws);
                            //////////////////////////////////////////
                        }
                        $this->mpdf->Cell($w, $h, $tmp, 0, 2, $align, $fill, $link);
                        $i = $sep + 1;
                    }
                    $sep = -1;
                    $j = $i;
                    $l = 0;
                    if ($nl === 1) {
                        if ($currentx != 0) {
                            $this->mpdf->x = $currentx;
                        } else {
                            $this->mpdf->x = $this->mpdf->l_margin;
                        }
                        $w = $this->mpdf->w - $this->mpdf->r_margin - $this->mpdf->x;
                        $wmax = $w - ($this->mpdf->c_margin_l + $this->mpdf->c_margin_r);
                    }
                    $nl++;
                } else {
                    $i++;
                }
            }
            // WORD SPACING
            $this->mpdf->reset_spacing();
        }
        //Last chunk
        if ($i != $j) {
            if ($currentx != 0) {
                $this->mpdf->x = $currentx;
            } else {
                $this->mpdf->x = $this->mpdf->l_margin;
            }
            if ($this->mpdf->using_core_font) {
                $tmp = substr($s, $j, $i - $j);
            } else {
                $tmp = mb_substr($s, $j, $i - $j, $this->mpdf->mb_enc);
            }
            $this->mpdf->Cell($w, $h, $tmp, 0, 0, $align, $fill, $link);
        }
    }
    public function circular_text($x, $y, $r, $text, $align = 'top', $fontfamily = '', $fontsize_pt = 0, $fontstyle = '', $kerning = 120, $fontwidth = 100, $divider = '')
    {
        if ($fontfamily || $fontstyle || $fontsize_pt) {
            $this->mpdf->set_font($fontfamily, $fontstyle, $fontsize_pt);
        }
        $kerning /= 100;
        $fontwidth /= 100;
        if ($kerning == 0) {
            throw new \Mpdf\Mpdf_Exception('Please use values unequal to zero for kerning (CircularText)');
        }
        if ($fontwidth == 0) {
            throw new \Mpdf\Mpdf_Exception('Please use values unequal to zero for font width (CircularText)');
        }
        $text = str_replace("\r", '', $text);
        // circumference
        $u = $r * 2 * M_PI;
        $checking = true;
        $autoset = false;
        while ($checking) {
            $t = 0;
            $w = [];
            if ($this->mpdf->using_core_font) {
                $nb = strlen($text);
                for ($i = 0; $i < $nb; $i++) {
                    $w[$i] = $this->mpdf->get_string_width($text[$i]);
                    $w[$i] *= $kerning * $fontwidth;
                    $t += $w[$i];
                }
            } else {
                $nb = mb_strlen($text, $this->mpdf->mb_enc);
                $lastchar = '';
                $unicode = $this->mpdf->utf8string_to_array($text);
                for ($i = 0; $i < $nb; $i++) {
                    $c = mb_substr($text, $i, 1, $this->mpdf->mb_enc);
                    $w[$i] = $this->mpdf->get_string_width($c);
                    $w[$i] *= $kerning * $fontwidth;
                    $char = $unicode[$i];
                    if ($this->mpdf->use_kerning && $lastchar && isset($this->mpdf->current_font['kerninfo'][$lastchar][$char])) {
                        $tk = $this->mpdf->current_font['kerninfo'][$lastchar][$char] * ($this->mpdf->font_size / 1000) * $kerning * $fontwidth;
                        $w[$i] += $tk / 2;
                        $w[$i - 1] += $tk / 2;
                        $t += $tk;
                    }
                    $lastchar = $char;
                    $t += $w[$i];
                }
            }
            if ($fontsize_pt >= 0 || $autoset) {
                $checking = false;
            } else {
                $t += $this->mpdf->get_string_width('  ');
                if ($divider) {
                    $t += $this->mpdf->get_string_width('  ');
                }
                if ($fontsize_pt == -2) {
                    $fontsize_pt = $this->mpdf->font_size_pt * 0.5 * $u / $t;
                } else {
                    $fontsize_pt = $this->mpdf->font_size_pt * $u / $t;
                }
                $this->mpdf->set_font_size($fontsize_pt);
                $autoset = true;
            }
        }
        // total width of string in degrees
        $d = $t / $u * 360;
        $this->mpdf->start_transform();
        // rotate matrix for the first letter to center the text
        // (half of total degrees)
        if ($align === 'top') {
            $this->mpdf->transform_rotate(-$d / 2, $x, $y);
        } else {
            $this->mpdf->transform_rotate($d / 2, $x, $y);
        }
        // run through the string
        for ($i = 0; $i < $nb; $i++) {
            if ($align === 'top') {
                // rotate matrix half of the width of current letter + half of the width of preceding letter
                if ($i === 0) {
                    $this->mpdf->transform_rotate($w[$i] / 2 / $u * 360, $x, $y);
                } else {
                    $this->mpdf->transform_rotate(($w[$i] / 2 + $w[$i - 1] / 2) / $u * 360, $x, $y);
                }
                if ($fontwidth !== 1) {
                    $this->mpdf->start_transform();
                    $this->mpdf->transform_scale($fontwidth * 100, 100, $x, $y);
                }
                $this->mpdf->set_xy($x - $w[$i] / 2, $y - $r);
            } else {
                // rotate matrix half of the width of current letter + half of the width of preceding letter
                if ($i === 0) {
                    $this->mpdf->transform_rotate(-($w[$i] / 2 / $u) * 360, $x, $y);
                } else {
                    $this->mpdf->transform_rotate(-(($w[$i] / 2 + $w[$i - 1] / 2) / $u) * 360, $x, $y);
                }
                if ($fontwidth !== 1) {
                    $this->mpdf->start_transform();
                    $this->mpdf->transform_scale($fontwidth * 100, 100, $x, $y);
                }
                $this->mpdf->set_xy($x - $w[$i] / 2, $y + $r - $this->mpdf->font_size);
            }
            if ($this->mpdf->using_core_font) {
                $c = $text[$i];
            } else {
                $c = mb_substr($text, $i, 1, $this->mpdf->mb_enc);
            }
            $this->mpdf->Cell($w[$i], $this->mpdf->font_size, $c, 0, 0, 'C');
            // mPDF 5.3.53
            if ($fontwidth !== 1) {
                $this->mpdf->stop_transform();
            }
        }
        $this->mpdf->stop_transform();
        // mPDF 5.5.23
        if ($align === 'top' && $divider != '') {
            $wc = $this->mpdf->get_string_width($divider);
            $wc *= $kerning * $fontwidth;
            $this->mpdf->start_transform();
            $this->mpdf->transform_rotate(90, $x, $y);
            $this->mpdf->set_xy($x - $wc / 2, $y - $r);
            $this->mpdf->Cell($wc, $this->mpdf->font_size, $divider, 0, 0, 'C');
            $this->mpdf->stop_transform();
            $this->mpdf->start_transform();
            $this->mpdf->transform_rotate(-90, $x, $y);
            $this->mpdf->set_xy($x - $wc / 2, $y - $r);
            $this->mpdf->Cell($wc, $this->mpdf->font_size, $divider, 0, 0, 'C');
            $this->mpdf->stop_transform();
        }
    }
    public function Shaded_box($text, $font = '', $fontstyle = 'B', $szfont = '', $width = '70%', $style = 'DF', $radius = 2.5, $fill = '#FFFFFF', $color = '#000000', $pad = 2)
    {
        // F (shading - no line),S (line, no shading),DF (both)
        if (!$font) {
            $font = $this->mpdf->default_font;
        }
        if (!$szfont) {
            $szfont = $this->mpdf->default_font_size * 1.8;
        }
        $text = ' ' . $text . ' ';
        $this->mpdf->set_font($font, $fontstyle, $szfont, false);
        $text = $this->mpdf->purify_utf8_text($text);
        if ($this->mpdf->text_input_as_HTML) {
            $text = $this->mpdf->all_entities_to_utf8($text);
        }
        if ($this->mpdf->using_core_font) {
            $text = mb_convert_encoding($text, $this->mpdf->mb_enc, 'UTF-8');
        }
        // DIRECTIONALITY
        if (preg_match('/([' . $this->mpdf->preg_rt_lchars . '])/u', $text)) {
            $this->mpdf->bi_directional = true;
        }
        // *RTL*
        $textvar = 0;
        $save_ot_ltags = $this->mpdf->ot_ltags;
        $this->mpdf->ot_ltags = [];
        if ($this->mpdf->use_kerning) {
            if ($this->mpdf->current_font['haskernGPOS']) {
                $this->mpdf->ot_ltags['Plus'] .= ' kern';
            } else {
                $textvar |= Text_Vars::FC_KERNING;
            }
        }
        // Use OTL OpenType Table Layout - GSUB & GPOS
        if (!empty($this->mpdf->current_font['useOTL'])) {
            $text = $this->otl->apply_otl($text, $this->mpdf->current_font['useOTL']);
            $ot_ldata = $this->otl->ot_ldata;
        }
        $this->mpdf->ot_ltags = $save_ot_ltags;
        $this->mpdf->magic_reverse_dir($text, $this->mpdf->directionality, $ot_ldata);
        if (!$width) {
            $width = $this->mpdf->pgwidth;
        } else {
            $width = $this->size_converter->convert($width, $this->mpdf->pgwidth);
        }
        $midpt = $this->mpdf->l_margin + $this->mpdf->pgwidth / 2;
        $r1 = $midpt - $width / 2;
        //($this->mpdf->w / 2) - 40;
        $r2 = $r1 + $width;
        //$r1 + 80;
        $y1 = $this->mpdf->y;
        $loop = 0;
        while ($loop === 0) {
            $this->mpdf->set_font($font, $fontstyle, $szfont, false);
            $sz = $this->mpdf->get_string_width($text, true, $ot_ldata, $textvar);
            if ($r1 + $sz > $r2) {
                $szfont--;
            } else {
                $loop++;
            }
        }
        $this->mpdf->set_font($font, $fontstyle, $szfont, true, true);
        $y2 = $this->mpdf->font_size + $pad * 2;
        $this->mpdf->set_line_width(0.1);
        $fc = $this->color_converter->convert($fill, $this->mpdf->pdfa_xwarnings);
        $tc = $this->color_converter->convert($color, $this->mpdf->pdfa_xwarnings);
        $this->mpdf->set_f_color($fc);
        $this->mpdf->set_t_color($tc);
        $this->mpdf->rounded_rect($r1, $y1, $r2 - $r1, $y2, $radius, $style);
        $this->mpdf->set_x($r1);
        $this->mpdf->Cell($r2 - $r1, $y2, $text, 0, 1, 'C', 0, '', 0, 0, 0, 'M', 0, false, $ot_ldata, $textvar);
        $this->mpdf->set_y($y1 + $y2 + 2);
        // +2 = mm margin below shaded box
        $this->mpdf->Reset();
    }
}