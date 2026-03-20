<?php

namespace Mpdf;

class Tt_Font_File_Analysis extends Tt_Font_File
{
    // Used to get font information from files in directory
    function extract_core_info($file, $tt_cfont_id = 0)
    {
        $this->filename = $file;
        $this->fh = fopen($file, 'rb');
        if (!$this->fh) {
            throw new \Mpdf\Mpdf_Exception('ERROR - Can\'t open file ' . $file);
        }
        $this->_pos = 0;
        $this->char_widths = '';
        $this->glyph_pos = [];
        $this->char_to_glyph = [];
        $this->tables = [];
        $this->otables = [];
        $this->ascent = 0;
        $this->descent = 0;
        $this->num_ttc_fonts = 0;
        $this->ttc_fonts = [];
        $this->version = $version = $this->read_ulong();
        $this->panose = [];
        // mPDF 5.0
        if ($version == 0x4f54544f) {
            throw new \Mpdf\Exception\Font_Exception(sprintf('Fonts with postscript outlines are not supported (%s)', $file));
        }
        if ($version == 0x74746366) {
            if ($tt_cfont_id > 0) {
                $this->version = $version = $this->read_ulong();
                // TTC Header version now
                if (!in_array($version, [0x10000, 0x20000])) {
                    throw new \Mpdf\Mpdf_Exception("ERROR - NOT ADDED as Error parsing TrueType Collection: version=" . $version . " - " . $file);
                }
            } else {
                throw new \Mpdf\Mpdf_Exception("ERROR - Error parsing TrueType Collection - " . $file);
            }
            $this->num_ttc_fonts = $this->read_ulong();
            for ($i = 1; $i <= $this->num_ttc_fonts; $i++) {
                $this->ttc_fonts[$i]['offset'] = $this->read_ulong();
            }
            $this->seek($this->ttc_fonts[$tt_cfont_id]['offset']);
            $this->version = $version = $this->read_ulong();
            // TTFont version again now
            $this->read_table_directory(false);
        } else {
            if (!in_array($version, [0x10000, 0x74727565])) {
                throw new \Mpdf\Mpdf_Exception("ERROR - NOT ADDED as Not a TrueType font: version=" . $version . " - " . $file);
            }
            $this->read_table_directory(false);
        }
        /* Included for testing...
        		  $cmap_offset = $this->seek_table("cmap");
        		  $this->skip(2);
        		  $cmapTableCount = $this->read_ushort();
        		  $unicode_cmap_offset = 0;
        		  for ($i=0;$i<$cmapTableCount;$i++) {
        		  $x[$i]['platformId'] = $this->read_ushort();
        		  $x[$i]['encodingId'] = $this->read_ushort();
        		  $x[$i]['offset'] = $this->read_ulong();
        		  $save_pos = $this->_pos;
        		  $x[$i]['format'] = $this->get_ushort($cmap_offset + $x[$i]['offset'] );
        		  $this->seek($save_pos );
        		  }
        		  print_r($x); exit;
        		 */
        ///////////////////////////////////
        // name - Naming table
        ///////////////////////////////////
        /* Test purposes - displays table of names
        		  $name_offset = $this->seek_table("name");
        		  $format = $this->read_ushort();
        		  if ($format != 0 && $format != 1)	// mPDF 5.3.73
        		  die("Unknown name table format ".$format);
        		  $numRecords = $this->read_ushort();
        		  $string_data_offset = $name_offset + $this->read_ushort();
        		  for ($i=0;$i<$numRecords; $i++) {
        		  $x[$i]['platformId'] = $this->read_ushort();
        		  $x[$i]['encodingId'] = $this->read_ushort();
        		  $x[$i]['languageId'] = $this->read_ushort();
        		  $x[$i]['nameId'] = $this->read_ushort();
        		  $x[$i]['length'] = $this->read_ushort();
        		  $x[$i]['offset'] = $this->read_ushort();
        
        		  $N = '';
        		  if ($x[$i]['platformId'] == 1 && $x[$i]['encodingId'] == 0 && $x[$i]['languageId'] == 0) { // Roman
        		  $opos = $this->_pos;
        		  $N = $this->get_chunk($string_data_offset + $x[$i]['offset'] , $x[$i]['length'] );
        		  $this->_pos = $opos;
        		  $this->seek($opos);
        		  }
        		  else { 	// Unicode
        		  $opos = $this->_pos;
        		  $this->seek($string_data_offset + $x[$i]['offset'] );
        		  $length = $x[$i]['length'] ;
        		  if ($length % 2 != 0)
        		  $length -= 1;
        		  //		die("PostScript name is UTF-16BE string of odd length");
        		  $length /= 2;
        		  $N = '';
        		  while ($length > 0) {
        		  $char = $this->read_ushort();
        		  $N .= (chr($char));
        		  $length -= 1;
        		  }
        		  $this->_pos = $opos;
        		  $this->seek($opos);
        		  }
        		  $x[$i]['names'][$nameId] = $N;
        		  }
        		  print_r($x); exit;
        		 */
        $name_offset = $this->seek_table("name");
        $format = $this->read_ushort();
        if ($format != 0 && $format != 1) {
            // mPDF 5.3.73
            throw new \Mpdf\Mpdf_Exception("ERROR - NOT ADDED as Unknown name table format " . $format . " - " . $file);
        }
        $num_records = $this->read_ushort();
        $string_data_offset = $name_offset + $this->read_ushort();
        $names = [1 => '', 2 => '', 3 => '', 4 => '', 6 => ''];
        $K = array_keys($names);
        $name_count = count($names);
        for ($i = 0; $i < $num_records; $i++) {
            $platform_id = $this->read_ushort();
            $encoding_id = $this->read_ushort();
            $language_id = $this->read_ushort();
            $name_id = $this->read_ushort();
            $length = $this->read_ushort();
            $offset = $this->read_ushort();
            if (!in_array($name_id, $K)) {
                continue;
            }
            $N = '';
            if ($platform_id == 3 && $encoding_id == 1 && $language_id == 0x409) {
                // Microsoft, Unicode, US English, PS Name
                $opos = $this->_pos;
                $this->seek($string_data_offset + $offset);
                if ($length % 2 != 0) {
                    $length += 1;
                }
                $length /= 2;
                $N = '';
                while ($length > 0) {
                    $char = $this->read_ushort();
                    $N .= chr($char);
                    $length -= 1;
                }
                $this->_pos = $opos;
                $this->seek($opos);
            } elseif ($platform_id == 1 && $encoding_id == 0 && $language_id == 0) {
                // Macintosh, Roman, English, PS Name
                $opos = $this->_pos;
                $N = $this->get_chunk($string_data_offset + $offset, $length);
                $this->_pos = $opos;
                $this->seek($opos);
            }
            if ($N && $names[$name_id] == '') {
                $names[$name_id] = $N;
                $name_count -= 1;
                if ($name_count == 0) {
                    break;
                }
            }
        }
        if ($names[6]) {
            $ps_name = preg_replace('/ /', '-', $names[6]);
        } elseif ($names[4]) {
            $ps_name = preg_replace('/ /', '-', $names[4]);
        } elseif ($names[1]) {
            $ps_name = preg_replace('/ /', '-', $names[1]);
        } else {
            $ps_name = '';
        }
        if (!$names[1] && !$ps_name) {
            throw new \Mpdf\Mpdf_Exception("ERROR - NOT ADDED as Could not find valid font name - " . $file);
        }
        $this->name = $ps_name;
        if ($names[1]) {
            $this->family_name = $names[1];
        } else {
            $this->family_name = $ps_name;
        }
        if ($names[2]) {
            $this->style_name = $names[2];
        } else {
            $this->style_name = 'Regular';
        }
        ///////////////////////////////////
        // head - Font header table
        ///////////////////////////////////
        $this->seek_table("head");
        $ver_maj = $this->read_ushort();
        $ver_min = $this->read_ushort();
        if ($ver_maj != 1) {
            throw new \Mpdf\Mpdf_Exception('ERROR - NOT ADDED as Unknown head table version ' . $ver_maj . '.' . $ver_min . " - " . $file);
        }
        $this->font_revision = $this->read_ushort() . $this->read_ushort();
        $this->skip(4);
        $magic = $this->read_ulong();
        if ($magic != 0x5f0f3cf5) {
            throw new \Mpdf\Mpdf_Exception('ERROR - NOT ADDED as Invalid head table magic ' . $magic . " - " . $file);
        }
        $this->skip(2);
        $this->units_per_em = $units_per_em = $this->read_ushort();
        $scale = 1000 / $units_per_em;
        $this->skip(24);
        $mac_style = $this->read_short();
        $this->skip(4);
        $index_loc_format = $this->read_short();
        ///////////////////////////////////
        // OS/2 - OS/2 and Windows metrics table
        ///////////////////////////////////
        $s_family = '';
        $panose = '';
        $fs_selection = '';
        if (isset($this->tables["OS/2"])) {
            $this->seek_table("OS/2");
            $this->skip(30);
            $s_f = $this->read_short();
            $s_family = $s_f >> 8;
            $this->_pos += 10;
            //PANOSE = 10 byte length
            $panose = fread($this->fh, 10);
            $this->panose = [];
            for ($p = 0; $p < strlen($panose); $p++) {
                $this->panose[] = ord($panose[$p]);
            }
            $this->skip(20);
            $fs_selection = $this->read_short();
        }
        ///////////////////////////////////
        // post - PostScript table
        ///////////////////////////////////
        $this->seek_table("post");
        $this->skip(4);
        $this->italic_angle = $this->read_short() + $this->read_ushort() / 65536.0;
        $this->skip(4);
        $is_fixed_pitch = $this->read_ulong();
        ///////////////////////////////////
        // cmap - Character to glyph index mapping table
        ///////////////////////////////////
        $cmap_offset = $this->seek_table("cmap");
        $this->skip(2);
        $cmap_table_count = $this->read_ushort();
        $unicode_cmap_offset = 0;
        for ($i = 0; $i < $cmap_table_count; $i++) {
            $platform_id = $this->read_ushort();
            $encoding_id = $this->read_ushort();
            $offset = $this->read_ulong();
            $save_pos = $this->_pos;
            if ($platform_id == 3 && $encoding_id == 1 || $platform_id == 0) {
                // Microsoft, Unicode
                $format = $this->get_ushort($cmap_offset + $offset);
                if ($format == 4) {
                    if (!$unicode_cmap_offset) {
                        $unicode_cmap_offset = $cmap_offset + $offset;
                    }
                }
            } elseif ($platform_id == 3 && $encoding_id == 10 || $platform_id == 0) {
                // Microsoft, Unicode Format 12 table HKCS
                $format = $this->get_ushort($cmap_offset + $offset);
                if ($format == 12) {
                    $unicode_cmap_offset = $cmap_offset + $offset;
                    break;
                }
            }
            $this->seek($save_pos);
        }
        if (!$unicode_cmap_offset) {
            throw new \Mpdf\Mpdf_Exception('ERROR - Font (' . $this->filename . ') NOT ADDED as it is not Unicode encoded, and cannot be used by mPDF');
        }
        $rtl = false;
        $indic = false;
        $cjk = false;
        $sip = false;
        $smp = false;
        $pua = false;
        $puaag = false;
        $glyph_to_char = [];
        $un_a_glyphs = '';
        // Format 12 CMAP does characters above Unicode BMP i.e. some HKCS characters U+20000 and above
        if ($format == 12) {
            $this->seek($unicode_cmap_offset + 4);
            $length = $this->read_ulong();
            $limit = $unicode_cmap_offset + $length;
            $this->skip(4);
            $n_groups = $this->read_ulong();
            for ($i = 0; $i < $n_groups; $i++) {
                $start_char_code = $this->read_ulong();
                $end_char_code = $this->read_ulong();
                $start_glyph_code = $this->read_ulong();
                if ($end_char_code > 0x20000 && $end_char_code < 0x2a6df || $end_char_code > 0x2f800 && $end_char_code < 0x2fa1f) {
                    $sip = true;
                }
                if ($end_char_code > 0x10000 && $end_char_code < 0x1ffff) {
                    $smp = true;
                }
                if ($end_char_code > 0x590 && $end_char_code < 0x77f || $end_char_code > 0xfe70 && $end_char_code < 0xfeff || $end_char_code > 0xfb50 && $end_char_code < 0xfdff) {
                    $rtl = true;
                }
                if ($end_char_code > 0x900 && $end_char_code < 0xdff) {
                    $indic = true;
                }
                if ($end_char_code > 0xe000 && $end_char_code < 0xf8ff) {
                    $pua = true;
                    if ($end_char_code > 0xf500 && $end_char_code < 0xf7ff) {
                        $puaag = true;
                    }
                }
                if ($end_char_code > 0x2e80 && $end_char_code < 0x4dc0 || $end_char_code > 0x4e00 && $end_char_code < 0xa4cf || $end_char_code > 0xac00 && $end_char_code < 0xd7af || $end_char_code > 0xf900 && $end_char_code < 0xfaff || $end_char_code > 0xfe30 && $end_char_code < 0xfe4f) {
                    $cjk = true;
                }
                $offset = 0;
                // Get each glyphToChar - only point if going to analyse un-mapped Arabic Glyphs
                if (isset($this->tables['post'])) {
                    for ($unichar = $start_char_code; $unichar <= $end_char_code; $unichar++) {
                        $glyph = $start_glyph_code + $offset;
                        $offset++;
                        $glyph_to_char[$glyph][] = $unichar;
                    }
                }
            }
        } else {
            // Format 4 CMap
            $this->seek($unicode_cmap_offset + 2);
            $length = $this->read_ushort();
            $limit = $unicode_cmap_offset + $length;
            $this->skip(2);
            $seg_count = $this->read_ushort() / 2;
            $this->skip(6);
            $end_count = [];
            for ($i = 0; $i < $seg_count; $i++) {
                $end_count[] = $this->read_ushort();
            }
            $this->skip(2);
            $start_count = [];
            for ($i = 0; $i < $seg_count; $i++) {
                $start_count[] = $this->read_ushort();
            }
            $id_delta = [];
            for ($i = 0; $i < $seg_count; $i++) {
                $id_delta[] = $this->read_short();
            }
            $id_range_offset_start = $this->_pos;
            $id_range_offset = [];
            for ($i = 0; $i < $seg_count; $i++) {
                $id_range_offset[] = $this->read_ushort();
            }
            for ($n = 0; $n < $seg_count; $n++) {
                if ($end_count[$n] > 0x590 && $end_count[$n] < 0x77f || $end_count[$n] > 0xfe70 && $end_count[$n] < 0xfeff || $end_count[$n] > 0xfb50 && $end_count[$n] < 0xfdff) {
                    $rtl = true;
                }
                if ($end_count[$n] > 0x900 && $end_count[$n] < 0xdff) {
                    $indic = true;
                }
                if ($end_count[$n] > 0x2e80 && $end_count[$n] < 0x4dc0 || $end_count[$n] > 0x4e00 && $end_count[$n] < 0xa4cf || $end_count[$n] > 0xac00 && $end_count[$n] < 0xd7af || $end_count[$n] > 0xf900 && $end_count[$n] < 0xfaff || $end_count[$n] > 0xfe30 && $end_count[$n] < 0xfe4f) {
                    $cjk = true;
                }
                if ($end_count[$n] > 0xe000 && $end_count[$n] < 0xf8ff) {
                    $pua = true;
                    if ($end_count[$n] > 0xf500 && $end_count[$n] < 0xf7ff) {
                        $puaag = true;
                    }
                }
                // Get each glyphToChar - only point if going to analyse un-mapped Arabic Glyphs
                if (isset($this->tables['post'])) {
                    $endpoint = $end_count[$n] + 1;
                    for ($unichar = $start_count[$n]; $unichar < $endpoint; $unichar++) {
                        if ($id_range_offset[$n] == 0) {
                            $glyph = $unichar + $id_delta[$n] & 0xffff;
                        } else {
                            $offset = ($unichar - $start_count[$n]) * 2 + $id_range_offset[$n];
                            $offset = $id_range_offset_start + 2 * $n + $offset;
                            if ($offset >= $limit) {
                                $glyph = 0;
                            } else {
                                $glyph = $this->get_ushort($offset);
                                if ($glyph != 0) {
                                    $glyph = $glyph + $id_delta[$n] & 0xffff;
                                }
                            }
                        }
                        $glyph_to_char[$glyph][] = $unichar;
                    }
                }
            }
        }
        $bold = false;
        $italic = false;
        $ftype = '';
        if ($mac_style & 1 << 0) {
            $bold = true;
        } elseif ($fs_selection & 1 << 5) {
            $bold = true;
        }
        // 5 	BOLD 	Characters are emboldened
        if ($mac_style & 1 << 1) {
            $italic = true;
        } elseif ($fs_selection & 1 << 0) {
            $italic = true;
        } elseif ($this->italic_angle != 0) {
            $italic = true;
        }
        if ($is_fixed_pitch) {
            $ftype = 'mono';
        } elseif ($s_family > 0 && $s_family < 8) {
            $ftype = 'serif';
        } elseif ($s_family == 8) {
            $ftype = 'sans';
        } elseif ($s_family == 10) {
            $ftype = 'cursive';
        }
        // Use PANOSE
        if ($panose) {
            $b_family_type = ord($panose[0]);
            if ($b_family_type == 2) {
                $b_serif_style = ord($panose[1]);
                if (!$ftype) {
                    if ($b_serif_style > 1 && $b_serif_style < 11) {
                        $ftype = 'serif';
                    } elseif ($b_serif_style > 10) {
                        $ftype = 'sans';
                    }
                }
                $b_proportion = ord($panose[3]);
                if ($b_proportion == 9 || $b_proportion == 1) {
                    $ftype = 'mono';
                }
                // ==1 i.e. No Fit needed for OCR-a and -b
            } elseif ($b_family_type == 3) {
                $ftype = 'cursive';
            }
        }
        fclose($this->fh);
        return [$this->family_name, $bold, $italic, $ftype, $tt_cfont_id, $rtl, $indic, $cjk, $sip, $smp, $puaag, $pua, $un_a_glyphs];
    }
}