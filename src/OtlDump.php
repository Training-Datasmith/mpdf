<?php

namespace Mpdf;

// Define the value used in the "head" table of a created TTF file
// 0x74727565 "true" for Mac
// 0x00010000 for Windows
// Either seems to work for a font embedded in a PDF file
// when read by Adobe Reader on a Windows PC(!)
use Mpdf\Fonts\Glyph_Operator;
if (!defined('_TTF_MAC_HEADER')) {
    define("_TTF_MAC_HEADER", false);
}
// Recalculate correct metadata/profiles when making subset fonts (not SIP/SMP)
// e.g. xMin, xMax, maxNContours
if (!defined('_RECALC_PROFILE')) {
    define("_RECALC_PROFILE", false);
}
// mPDF 5.7.1
if (!function_exists('Mpdf\unicode_hex')) {
    function unicode_hex($unicode_dec)
    {
        return sprintf("%05s", strtoupper(dechex($unicode_dec)));
    }
}
class Otl_Dump
{
    public $gpos_features;
    // mPDF 5.7.1
    public $gpos_lookups;
    // mPDF 5.7.1
    public $gpos_script_lang;
    // mPDF 5.7.1
    public $ignore_strings;
    // mPDF 5.7.1
    public $mark_attachment_type;
    // mPDF 5.7.1
    public $mark_glyph_sets;
    // mPDF 7.5.1
    public $glyph_class_marks;
    // mPDF 5.7.1
    public $glyph_class_ligatures;
    // mPDF 5.7.1
    public $glyph_class_bases;
    // mPDF 5.7.1
    public $glyph_class_components;
    // mPDF 5.7.1
    public $gsub_script_lang;
    // mPDF 5.7.1
    public $rtl_pu_astr;
    // mPDF 5.7.1
    public $rtl_pu_aarr;
    // mPDF 5.7.1
    public $fontkey;
    // mPDF 5.7.1
    public $use_otl;
    // mPDF 5.7.1
    public $panose;
    public $max_uni;
    public $s_family_class;
    public $s_family_sub_class;
    public $sipset;
    public $smpset;
    public $_pos;
    public $num_tables;
    public $search_range;
    public $entry_selector;
    public $range_shift;
    public $tables;
    public $otables;
    public $filename;
    public $fh;
    public $glyph_pos;
    public $char_to_glyph;
    public $ascent;
    public $descent;
    public $name;
    public $family_name;
    public $style_name;
    public $full_name;
    public $unique_font_id;
    public $units_per_em;
    public $bbox;
    public $cap_height;
    public $stem_v;
    public $italic_angle;
    public $flags;
    public $underline_position;
    public $underline_thickness;
    public $char_widths;
    public $default_width;
    public $max_str_len_read;
    public $num_ttc_fonts;
    public $ttc_fonts;
    public $max_uni_char;
    public $kerninfo;
    public $mode;
    public $glyph_to_char;
    public $font_revision;
    public $glyphdata;
    public $glyph_i_dto_un;
    public $restricted_use;
    public $gsub_features;
    public $gsub_lookups;
    public $glyph_i_dto_uni;
    public $gs_lu_coverage;
    public $version;
    private $mpdf;
    public function __construct(Mpdf $mpdf)
    {
        $this->mpdf = $mpdf;
        $this->max_str_len_read = 200000;
        // Maximum size of glyf table to read in as string (otherwise reads each glyph from file)
    }
    function get_metrics($file, $fontkey, $tt_cfont_id = 0, $debug = false, $bm_ponly = false, $kerninfo = false, $use_otl = 0, $mode = null)
    {
        // mPDF 5.7.1
        $this->mode = $mode;
        $this->use_otl = $use_otl;
        // mPDF 5.7.1
        $this->fontkey = $fontkey;
        // mPDF 5.7.1
        $this->filename = $file;
        $this->fh = fopen($file, 'rb');
        if (!$this->fh) {
            throw new \Mpdf\Exception\Font_Exception(sprintf('Unable to open file "%s"', $file));
        }
        $this->_pos = 0;
        $this->char_widths = '';
        $this->glyph_pos = [];
        $this->char_to_glyph = [];
        $this->tables = [];
        $this->otables = [];
        $this->kerninfo = [];
        $this->ascent = 0;
        $this->descent = 0;
        $this->num_ttc_fonts = 0;
        $this->ttc_fonts = [];
        $this->version = $version = $this->read_ulong();
        $this->panose = [];
        if ($version == 0x4f54544f) {
            throw new \Mpdf\Exception\Font_Exception(sprintf('Fonts with postscript outlines are not supported (%s)', $file));
        }
        if ($version == 0x74746366 && !$tt_cfont_id) {
            throw new \Mpdf\Exception\Font_Exception("TTCfontID for a TrueType Collection has to be defined in ttfontdata configuration key (" . $file . ")");
        }
        if (!in_array($version, [0x10000, 0x74727565]) && !$tt_cfont_id) {
            throw new \Mpdf\Exception\Font_Exception("Not a TrueType font: version=" . $version);
        }
        if ($tt_cfont_id > 0) {
            $this->version = $version = $this->read_ulong();
            // TTC Header version now
            if (!in_array($version, [0x10000, 0x20000])) {
                throw new \Mpdf\Exception\Font_Exception("Error parsing TrueType Collection: version=" . $version . " - " . $file);
            }
            $this->num_ttc_fonts = $this->read_ulong();
            for ($i = 1; $i <= $this->num_ttc_fonts; $i++) {
                $this->ttc_fonts[$i]['offset'] = $this->read_ulong();
            }
            $this->seek($this->ttc_fonts[$tt_cfont_id]['offset']);
            $this->version = $version = $this->read_ulong();
            // TTFont version again now
        }
        $this->read_table_directory($debug);
        $this->extract_info($debug, $bm_ponly, $kerninfo, $use_otl);
        fclose($this->fh);
    }
    function read_table_directory($debug = false)
    {
        $this->num_tables = $this->read_ushort();
        $this->search_range = $this->read_ushort();
        $this->entry_selector = $this->read_ushort();
        $this->range_shift = $this->read_ushort();
        $this->tables = [];
        for ($i = 0; $i < $this->num_tables; $i++) {
            $record = [];
            $record['tag'] = $this->read_tag();
            $record['checksum'] = [$this->read_ushort(), $this->read_ushort()];
            $record['offset'] = $this->read_ulong();
            $record['length'] = $this->read_ulong();
            $this->tables[$record['tag']] = $record;
        }
        if ($debug) {
            $this->checksum_tables();
        }
    }
    function checksum_tables()
    {
        // Check the checksums for all tables
        foreach ($this->tables as $t) {
            if ($t['length'] > 0 && $t['length'] < $this->max_str_len_read) {
                // 1.02
                $table = $this->get_chunk($t['offset'], $t['length']);
                $checksum = $this->calc_checksum($table);
                if ($t['tag'] == 'head') {
                    $up = unpack('n*', substr($table, 8, 4));
                    $adjustment[0] = $up[1];
                    $adjustment[1] = $up[2];
                    $checksum = $this->sub32($checksum, $adjustment);
                }
                $xchecksum = $t['checksum'];
                if ($xchecksum != $checksum) {
                    throw new \Mpdf\Exception\Font_Exception(sprintf('TTF file "%s": invalid checksum %s table: %s (expected %s)', $this->filename, dechex($checksum[0]) . dechex($checksum[1]), $t['tag'], dechex($xchecksum[0]) . dechex($xchecksum[1])));
                }
            }
        }
    }
    function sub32($x, $y)
    {
        $xlo = $x[1];
        $xhi = $x[0];
        $ylo = $y[1];
        $yhi = $y[0];
        if ($ylo > $xlo) {
            $xlo += 1 << 16;
            $yhi += 1;
        }
        $reslo = $xlo - $ylo;
        if ($yhi > $xhi) {
            $xhi += 1 << 16;
        }
        $reshi = $xhi - $yhi;
        $reshi = $reshi & 0xffff;
        return [$reshi, $reslo];
    }
    function calc_checksum($data)
    {
        if (strlen($data) % 4) {
            $data .= str_repeat("\x00", 4 - strlen($data) % 4);
        }
        $len = strlen($data);
        $hi = 0x0;
        $lo = 0x0;
        for ($i = 0; $i < $len; $i += 4) {
            $hi += (ord($data[$i]) << 8) + ord($data[$i + 1]);
            $lo += (ord($data[$i + 2]) << 8) + ord($data[$i + 3]);
            $hi += $lo >> 16 & 0xffff;
            $lo = $lo & 0xffff;
        }
        return [$hi, $lo];
    }
    function get_table_pos($tag)
    {
        $offset = isset($this->tables[$tag]['offset']) ? $this->tables[$tag]['offset'] : null;
        $length = isset($this->tables[$tag]['length']) ? $this->tables[$tag]['length'] : null;
        return [$offset, $length];
    }
    function seek($pos)
    {
        $this->_pos = $pos;
        fseek($this->fh, $this->_pos);
    }
    function skip($delta)
    {
        $this->_pos = $this->_pos + $delta;
        fseek($this->fh, $delta, SEEK_CUR);
    }
    function seek_table($tag, $offset_in_table = 0)
    {
        $tpos = $this->get_table_pos($tag);
        $this->_pos = $tpos[0] + $offset_in_table;
        fseek($this->fh, $this->_pos);
        return $this->_pos;
    }
    function read_tag()
    {
        $this->_pos += 4;
        return fread($this->fh, 4);
    }
    function read_short()
    {
        $this->_pos += 2;
        $s = fread($this->fh, 2);
        $a = (ord($s[0]) << 8) + ord($s[1]);
        if ($a & 1 << 15) {
            return $a - (1 << 16);
        }
        return $a;
    }
    function unpack_short($s)
    {
        $a = (ord($s[0]) << 8) + ord($s[1]);
        if ($a & 1 << 15) {
            return $a - (1 << 16);
        }
        return $a;
    }
    function read_ushort()
    {
        $this->_pos += 2;
        $s = fread($this->fh, 2);
        return (ord($s[0]) << 8) + ord($s[1]);
    }
    function read_ulong()
    {
        $this->_pos += 4;
        $s = fread($this->fh, 4);
        // if large uInt32 as an integer, PHP converts it to -ve
        return ord($s[0]) * 16777216 + (ord($s[1]) << 16) + (ord($s[2]) << 8) + ord($s[3]);
        // 	16777216  = 1<<24
    }
    function get_ushort($pos)
    {
        fseek($this->fh, $pos);
        $s = fread($this->fh, 2);
        return (ord($s[0]) << 8) + ord($s[1]);
    }
    function get_ulong($pos)
    {
        fseek($this->fh, $pos);
        $s = fread($this->fh, 4);
        // iF large uInt32 as an integer, PHP converts it to -ve
        return ord($s[0]) * 16777216 + (ord($s[1]) << 16) + (ord($s[2]) << 8) + ord($s[3]);
        // 	16777216  = 1<<24
    }
    function pack_short($val)
    {
        if ($val < 0) {
            $val = abs($val);
            $val = ~$val;
            $val += 1;
        }
        return pack("n", $val);
    }
    function splice($stream, $offset, $value)
    {
        return substr($stream, 0, $offset) . $value . substr($stream, $offset + strlen($value));
    }
    function _set_ushort($stream, $offset, $value)
    {
        $up = pack("n", $value);
        return $this->splice($stream, $offset, $up);
    }
    function _set_short($stream, $offset, $val)
    {
        if ($val < 0) {
            $val = abs($val);
            $val = ~$val;
            $val += 1;
        }
        $up = pack("n", $val);
        return $this->splice($stream, $offset, $up);
    }
    function get_chunk($pos, $length)
    {
        fseek($this->fh, $pos);
        if ($length < 1) {
            return '';
        }
        return fread($this->fh, $length);
    }
    function get_table($tag)
    {
        list($pos, $length) = $this->get_table_pos($tag);
        if ($length == 0) {
            return '';
        }
        fseek($this->fh, $pos);
        return fread($this->fh, $length);
    }
    function add($tag, $data)
    {
        if ($tag == 'head') {
            $data = $this->splice($data, 8, "\x00\x00\x00\x00");
        }
        $this->otables[$tag] = $data;
    }
    /////////////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////////////////////////////////////////////////////////////
    function extract_info($debug = false, $bm_ponly = false, $kerninfo = false, $use_otl = 0)
    {
        $this->panose = [];
        $this->s_family_class = 0;
        $this->s_family_sub_class = 0;
        ///////////////////////////////////
        // name - Naming table
        ///////////////////////////////////
        $name_offset = $this->seek_table("name");
        $format = $this->read_ushort();
        if ($format != 0 && $format != 1) {
            throw new \Mpdf\Exception\Font_Exception("Error loading font: Unknown name table format " . $format);
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
                    throw new \Mpdf\Exception\Font_Exception("Error loading font: PostScript name is UTF-16BE string of odd length");
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
            } else if ($platform_id == 1 && $encoding_id == 0 && $language_id == 0) {
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
            $ps_name = $names[6];
        } else if ($names[4]) {
            $ps_name = preg_replace('/ /', '-', $names[4]);
        } else if ($names[1]) {
            $ps_name = preg_replace('/ /', '-', $names[1]);
        } else {
            $ps_name = '';
        }
        if (!$ps_name) {
            throw new \Mpdf\Exception\Font_Exception("Error loading font: Could not find PostScript font name: " . $this->filename);
        }
        if ($debug) {
            for ($i = 0; $i < count($ps_name); $i++) {
                $c = $ps_name[$i];
                $oc = ord($c);
                if ($oc > 126 || strpos(' [](){}<>/%', $c) !== false) {
                    throw new \Mpdf\Exception\Font_Exception("psName=" . $ps_name . " contains invalid character " . $c . " ie U+" . ord($c));
                }
            }
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
        if ($names[4]) {
            $this->full_name = $names[4];
        } else {
            $this->full_name = $ps_name;
        }
        if ($names[3]) {
            $this->unique_font_id = $names[3];
        } else {
            $this->unique_font_id = $ps_name;
        }
        if ($names[6]) {
            $this->full_name = $names[6];
        }
        ///////////////////////////////////
        // head - Font header table
        ///////////////////////////////////
        $this->seek_table("head");
        if ($debug) {
            $ver_maj = $this->read_ushort();
            $ver_min = $this->read_ushort();
            if ($ver_maj != 1) {
                throw new \Mpdf\Exception\Font_Exception('Error loading font: Unknown head table version ' . $ver_maj . '.' . $ver_min);
            }
            $this->font_revision = $this->read_ushort() . $this->read_ushort();
            $this->skip(4);
            $magic = $this->read_ulong();
            if ($magic != 0x5f0f3cf5) {
                throw new \Mpdf\Exception\Font_Exception('Error loading font: Invalid head table magic ' . $magic);
            }
            $this->skip(2);
        } else {
            $this->skip(18);
        }
        $this->units_per_em = $units_per_em = $this->read_ushort();
        $scale = 1000 / $units_per_em;
        $this->skip(16);
        $x_min = $this->read_short();
        $y_min = $this->read_short();
        $x_max = $this->read_short();
        $y_max = $this->read_short();
        $this->bbox = [$x_min * $scale, $y_min * $scale, $x_max * $scale, $y_max * $scale];
        $this->skip(3 * 2);
        $this->read_ushort();
        $glyph_data_format = $this->read_ushort();
        if ($glyph_data_format != 0) {
            throw new \Mpdf\Exception\Font_Exception('Error loading font: Unknown glyph data format ' . $glyph_data_format);
        }
        ///////////////////////////////////
        // hhea metrics table
        ///////////////////////////////////
        // ttf2t1 seems to use this value rather than the one in OS/2 - so put in for compatibility
        if (isset($this->tables["hhea"])) {
            $this->seek_table("hhea");
            $this->skip(4);
            $hhea_ascender = $this->read_short();
            $hhea_descender = $this->read_short();
            $this->ascent = $hhea_ascender * $scale;
            $this->descent = $hhea_descender * $scale;
        }
        ///////////////////////////////////
        // OS/2 - OS/2 and Windows metrics table
        ///////////////////////////////////
        if (isset($this->tables["OS/2"])) {
            $this->seek_table("OS/2");
            $version = $this->read_ushort();
            $this->skip(2);
            $us_weight_class = $this->read_ushort();
            $this->skip(2);
            $fs_type = $this->read_ushort();
            if ($fs_type == 0x2 || ($fs_type & 0x300) != 0) {
                global $override_ttf_font_restriction;
                if (!$override_ttf_font_restriction) {
                    throw new \Mpdf\Exception\Font_Exception('Font file ' . $this->filename . ' cannot be embedded due to copyright restrictions.');
                }
                $this->restricted_use = true;
            }
            $this->skip(20);
            $s_f = $this->read_short();
            $this->s_family_class = $s_f >> 8;
            $this->s_family_sub_class = $s_f & 0xff;
            $this->_pos += 10;
            //PANOSE = 10 byte length
            $panose = fread($this->fh, 10);
            $this->panose = [];
            for ($p = 0; $p < strlen($panose); $p++) {
                $this->panose[] = ord($panose[$p]);
            }
            $this->skip(26);
            $s_typo_ascender = $this->read_short();
            $s_typo_descender = $this->read_short();
            if (!$this->ascent) {
                $this->ascent = $s_typo_ascender * $scale;
            }
            if (!$this->descent) {
                $this->descent = $s_typo_descender * $scale;
            }
            if ($version > 1) {
                $this->skip(16);
                $s_cap_height = $this->read_short();
                $this->cap_height = $s_cap_height * $scale;
            } else {
                $this->cap_height = $this->ascent;
            }
        } else {
            $us_weight_class = 500;
            if (!$this->ascent) {
                $this->ascent = $y_max * $scale;
            }
            if (!$this->descent) {
                $this->descent = $y_min * $scale;
            }
            $this->cap_height = $this->ascent;
        }
        $this->stem_v = 50 + intval(($us_weight_class / 65.0) ** 2);
        ///////////////////////////////////
        // post - PostScript table
        ///////////////////////////////////
        $this->seek_table("post");
        if ($debug) {
            $ver_maj = $this->read_ushort();
            $ver_min = $this->read_ushort();
            if ($ver_maj < 1 || $ver_maj > 4) {
                throw new \Mpdf\Exception\Font_Exception('Error loading font: Unknown post table version ' . $ver_maj);
            }
        } else {
            $this->skip(4);
        }
        $this->italic_angle = $this->read_short() + $this->read_ushort() / 65536.0;
        $this->underline_position = $this->read_short() * $scale;
        $this->underline_thickness = $this->read_short() * $scale;
        $is_fixed_pitch = $this->read_ulong();
        $this->flags = 4;
        if ($this->italic_angle != 0) {
            $this->flags = $this->flags | 64;
        }
        if ($us_weight_class >= 600) {
            $this->flags = $this->flags | 262144;
        }
        if ($is_fixed_pitch) {
            $this->flags = $this->flags | 1;
        }
        ///////////////////////////////////
        // hhea - Horizontal header table
        ///////////////////////////////////
        $this->seek_table("hhea");
        if ($debug) {
            $ver_maj = $this->read_ushort();
            $ver_min = $this->read_ushort();
            if ($ver_maj != 1) {
                throw new \Mpdf\Exception\Font_Exception(sprintf('Error loading font: Unknown hhea table version %s', $ver_maj));
            }
            $this->skip(28);
        } else {
            $this->skip(32);
        }
        $metric_data_format = $this->read_ushort();
        if ($metric_data_format != 0) {
            throw new \Mpdf\Exception\Font_Exception('Error loading font: Unknown horizontal metric data format ' . $metric_data_format);
        }
        $number_of_h_metrics = $this->read_ushort();
        if ($number_of_h_metrics == 0) {
            throw new \Mpdf\Exception\Font_Exception('Error loading font: Number of horizontal metrics is 0');
        }
        ///////////////////////////////////
        // maxp - Maximum profile table
        ///////////////////////////////////
        $this->seek_table("maxp");
        if ($debug) {
            $ver_maj = $this->read_ushort();
            $ver_min = $this->read_ushort();
            if ($ver_maj != 1) {
                throw new \Mpdf\Exception\Font_Exception('Error loading font: Unknown maxp table version ' . $ver_maj);
            }
        } else {
            $this->skip(4);
        }
        $num_glyphs = $this->read_ushort();
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
                    if ($bm_ponly) {
                        break;
                    }
                }
            } else if (($platform_id == 3 && $encoding_id == 10 || $platform_id == 0) && !$bm_ponly) {
                $format = $this->get_ushort($cmap_offset + $offset);
                if ($format == 12) {
                    $unicode_cmap_offset = $cmap_offset + $offset;
                    break;
                }
            }
            $this->seek($save_pos);
        }
        if (!$unicode_cmap_offset) {
            throw new \Mpdf\Exception\Font_Exception('Font (' . $this->filename . ') does not have cmap for Unicode (platform 3, encoding 1, format 4, or platform 0, any encoding, format 4)');
        }
        $sipset = false;
        $smpset = false;
        // mPDF 5.7.1
        $this->gsub_script_lang = [];
        $this->rtl_pu_astr = '';
        $this->rtl_pu_aarr = [];
        $this->gsub_features = [];
        $this->gsub_lookups = [];
        $this->gpos_script_lang = [];
        $this->gpos_features = [];
        $this->gpos_lookups = [];
        $this->glyph_i_dto_uni = '';
        // Format 12 CMAP does characters above Unicode BMP i.e. some HKCS characters U+20000 and above
        if ($format == 12 && !$bm_ponly) {
            $this->max_uni_char = 0;
            $this->seek($unicode_cmap_offset + 4);
            $length = $this->read_ulong();
            $limit = $unicode_cmap_offset + $length;
            $this->skip(4);
            $n_groups = $this->read_ulong();
            $glyph_to_char = [];
            $char_to_glyph = [];
            for ($i = 0; $i < $n_groups; $i++) {
                $start_char_code = $this->read_ulong();
                $end_char_code = $this->read_ulong();
                $start_glyph_code = $this->read_ulong();
                if ($end_char_code > 0x20000 && $end_char_code < 0x2ffff) {
                    $sipset = true;
                } else if ($end_char_code > 0x10000 && $end_char_code < 0x1ffff) {
                    $smpset = true;
                }
                $offset = 0;
                for ($unichar = $start_char_code; $unichar <= $end_char_code; $unichar++) {
                    $glyph = $start_glyph_code + $offset;
                    $offset++;
                    if ($unichar < 0x30000) {
                        $char_to_glyph[$unichar] = $glyph;
                        $this->max_uni_char = max($unichar, $this->max_uni_char);
                        $glyph_to_char[$glyph][] = $unichar;
                    }
                }
            }
        } else {
            $glyph_to_char = [];
            $char_to_glyph = [];
            $this->get_cmap4($unicode_cmap_offset, $glyph_to_char, $char_to_glyph);
        }
        $this->sipset = $sipset;
        $this->smpset = $smpset;
        ///////////////////////////////////
        // mPDF 5.7.1
        // Map Unmapped glyphs - from $numGlyphs
        if ($this->use_otl) {
            $bctr = 0xe000;
            for ($gid = 1; $gid < $num_glyphs; $gid++) {
                if (!isset($glyph_to_char[$gid])) {
                    while (isset($char_to_glyph[$bctr])) {
                        $bctr++;
                    }
                    // Avoid overwriting a glyph already mapped in PUA
                    if ($bctr > 0xf8ff && $bctr < 0x2ceb0) {
                        if (!$bm_ponly) {
                            $bctr = 0x2ceb0;
                            // Use unassigned area 0x2CEB0 to 0x2F7FF (space for 10,000 characters)
                            $this->sipset = $sipset = true;
                            // forces subsetting; also ensure charwidths are saved
                            while (isset($char_to_glyph[$bctr])) {
                                $bctr++;
                            }
                        } else {
                            throw new \Mpdf\Exception\Font_Exception(sprintf('Font "%s" does not have cmap for Unicode (platform 3, encoding 1, format 4, or platform 0, any encoding, format 4)', $this->filename));
                        }
                    }
                    $glyph_to_char[$gid][] = $bctr;
                    $char_to_glyph[$bctr] = $gid;
                    $this->max_uni_char = max($bctr, $this->max_uni_char);
                    $bctr++;
                }
            }
        }
        $this->glyph_to_char = $glyph_to_char;
        $this->char_to_glyph = $char_to_glyph;
        ///////////////////////////////////
        // mPDF 5.7.1	OpenType Layout tables
        $this->gsub_script_lang = [];
        $this->rtl_pu_astr = '';
        $this->rtl_pu_aarr = [];
        if ($use_otl) {
            $this->_get_gde_ftables();
            list($this->gsub_script_lang, $this->gsub_features, $this->gsub_lookups, $this->rtl_pu_astr, $this->rtl_pu_aarr) = $this->_get_gsu_btables();
            list($this->gpos_script_lang, $this->gpos_features, $this->gpos_lookups) = $this->_get_gpo_stables();
            $this->glyph_i_dto_uni = str_pad('', 256 * 256 * 3, "\x00");
            foreach ($glyph_to_char as $gid => $arr) {
                if (isset($glyph_to_char[$gid][0])) {
                    $char = $glyph_to_char[$gid][0];
                    if ($char != 0 && $char != 65535) {
                        $this->glyph_i_dto_uni[$gid * 3] = chr($char >> 16);
                        $this->glyph_i_dto_uni[$gid * 3 + 1] = chr($char >> 8 & 0xff);
                        $this->glyph_i_dto_uni[$gid * 3 + 2] = chr($char & 0xff);
                    }
                }
            }
        }
        ///////////////////////////////////
        ///////////////////////////////////
        // hmtx - Horizontal metrics table
        ///////////////////////////////////
        $this->get_hmtx($number_of_h_metrics, $num_glyphs, $glyph_to_char, $scale);
        ///////////////////////////////////
        // kern - Kerning pair table
        ///////////////////////////////////
        if ($kerninfo) {
            // Recognises old form of Kerning table - as required by Windows - Format 0 only
            $kern_offset = $this->seek_table("kern");
            $version = $this->read_ushort();
            $n_tables = $this->read_ushort();
            // subtable header
            $sversion = $this->read_ushort();
            $slength = $this->read_ushort();
            $scoverage = $this->read_ushort();
            $format = $scoverage >> 8;
            if ($kern_offset && $version == 0 && $format == 0) {
                // Format 0
                $n_pairs = $this->read_ushort();
                $this->skip(6);
                for ($i = 0; $i < $n_pairs; $i++) {
                    $left = $this->read_ushort();
                    $right = $this->read_ushort();
                    $val = $this->read_short();
                    if (count($glyph_to_char[$left]) == 1 && count($glyph_to_char[$right]) == 1) {
                        if ($left != 32 && $right != 32) {
                            $this->kerninfo[$glyph_to_char[$left][0]][$glyph_to_char[$right][0]] = intval($val * $scale);
                        }
                    }
                }
            }
        }
    }
    /////////////////////////////////////////////////////////////////////////////////////////
    function _get_gde_ftables()
    {
        ///////////////////////////////////
        // GDEF - Glyph Definition
        ///////////////////////////////////
        // http://www.microsoft.com/typography/otspec/gdef.htm
        if (isset($this->tables["GDEF"])) {
            if ($this->mode == 'summary') {
                $this->mpdf->write_html('<h1>GDEF table</h1>');
            }
            $gdef_offset = $this->seek_table("GDEF");
            // ULONG Version of the GDEF table-currently 0x00010000
            $ver_maj = $this->read_ushort();
            $ver_min = $this->read_ushort();
            // Version 0x00010002 of GDEF header contains additional Offset to a list defining mark glyph set definitions (MarkGlyphSetDef)
            $glyph_class_def_offset = $this->read_ushort();
            $attach_list_offset = $this->read_ushort();
            $lig_caret_list_offset = $this->read_ushort();
            $mark_attach_class_def_offset = $this->read_ushort();
            if ($ver_min == 2) {
                $mark_glyph_sets_def_offset = $this->read_ushort();
            }
            // GlyphClassDef
            $this->seek($gdef_offset + $glyph_class_def_offset);
            /*
             1	Base glyph (single character, spacing glyph)
             2	Ligature glyph (multiple character, spacing glyph)
             3	Mark glyph (non-spacing combining glyph)
             4	Component glyph (part of single character, spacing glyph)
            */
            $glyph_by_class = $this->_get_class_definition_table();
            if ($this->mode == 'summary') {
                $this->mpdf->write_html('<h2>Glyph classes</h2>');
            }
            if (isset($glyph_by_class[1]) && count($glyph_by_class[1]) > 0) {
                $this->glyph_class_bases = $this->format_class_arr($glyph_by_class[1]);
                if ($this->mode == 'summary') {
                    $this->mpdf->write_html('<h3>Glyph class 1</h3>');
                    $this->mpdf->write_html('<h5>Base glyph (single character, spacing glyph)</h5>');
                    $html = '';
                    $html .= '<div class="glyphs">';
                    foreach ($glyph_by_class[1] as $g) {
                        $html .= '&#x' . $g . '; ';
                    }
                    $html .= '</div>';
                    $this->mpdf->write_html($html);
                }
            } else {
                $this->glyph_class_bases = '';
            }
            if (isset($glyph_by_class[2]) && count($glyph_by_class[2]) > 0) {
                $this->glyph_class_ligatures = $this->format_class_arr($glyph_by_class[2]);
                if ($this->mode == 'summary') {
                    $this->mpdf->write_html('<h3>Glyph class 2</h3>');
                    $this->mpdf->write_html('<h5>Ligature glyph (multiple character, spacing glyph)</h5>');
                    $html = '';
                    $html .= '<div class="glyphs">';
                    foreach ($glyph_by_class[2] as $g) {
                        $html .= '&#x' . $g . '; ';
                    }
                    $html .= '</div>';
                    $this->mpdf->write_html($html);
                }
            } else {
                $this->glyph_class_ligatures = '';
            }
            if (isset($glyph_by_class[3]) && count($glyph_by_class[3]) > 0) {
                $this->glyph_class_marks = $this->format_class_arr($glyph_by_class[3]);
                if ($this->mode == 'summary') {
                    $this->mpdf->write_html('<h3>Glyph class 3</h3>');
                    $this->mpdf->write_html('<h5>Mark glyph (non-spacing combining glyph)</h5>');
                    $html = '';
                    $html .= '<div class="glyphs">';
                    foreach ($glyph_by_class[3] as $g) {
                        $html .= '&#x25cc;&#x' . $g . '; ';
                    }
                    $html .= '</div>';
                    $this->mpdf->write_html($html);
                }
            } else {
                $this->glyph_class_marks = '';
            }
            if (isset($glyph_by_class[4]) && count($glyph_by_class[4]) > 0) {
                $this->glyph_class_components = $this->format_class_arr($glyph_by_class[4]);
                if ($this->mode == 'summary') {
                    $this->mpdf->write_html('<h3>Glyph class 4</h3>');
                    $this->mpdf->write_html('<h5>Component glyph (part of single character, spacing glyph)</h5>');
                    $html = '';
                    $html .= '<div class="glyphs">';
                    foreach ($glyph_by_class[4] as $g) {
                        $html .= '&#x' . $g . '; ';
                    }
                    $html .= '</div>';
                    $this->mpdf->write_html($html);
                }
            } else {
                $this->glyph_class_components = '';
            }
            $Marks = $glyph_by_class[3];
            // to use for MarkAttachmentType
            /* Required for GPOS
            			  // Attachment List
            			  if ($AttachList_offset) {
            			  $this->seek($gdef_offset+$AttachList_offset );
            			  }
            			  The Attachment Point List table (AttachmentList) identifies all the attachment points defined in the GPOS table and their associated glyphs so a client can quickly access coordinates for each glyph's attachment points. As a result, the client can cache coordinates for attachment points along with glyph bitmaps and avoid recalculating the attachment points each time it displays a glyph. Without this table, processing speed would be slower because the client would have to decode the GPOS lookups that define attachment points and compile the points in a list.
            
            			  The Attachment List table (AttachList) may be used to cache attachment point coordinates along with glyph bitmaps.
            
            			  The table consists of an offset to a Coverage table (Coverage) listing all glyphs that define attachment points in the GPOS table, a count of the glyphs with attachment points (GlyphCount), and an array of offsets to AttachPoint tables (AttachPoint). The array lists the AttachPoint tables, one for each glyph in the Coverage table, in the same order as the Coverage Index.
            			  AttachList table
            			  Type 	Name 	Description
            			  Offset 	Coverage 	Offset to Coverage table - from beginning of AttachList table
            			  uint16 	GlyphCount 	Number of glyphs with attachment points
            			  Offset 	AttachPoint[GlyphCount] 	Array of offsets to AttachPoint tables-from beginning of AttachList table-in Coverage Index order
            
            			  An AttachPoint table consists of a count of the attachment points on a single glyph (PointCount) and an array of contour indices of those points (PointIndex), listed in increasing numerical order.
            
            			  AttachPoint table
            			  Type 	Name 	Description
            			  uint16 	PointCount 	Number of attachment points on this glyph
            			  uint16 	PointIndex[PointCount] 	Array of contour point indices -in increasing numerical order
            
            			  See Example 3 - http://www.microsoft.com/typography/otspec/gdef.htm
            			 */
            // Ligature Caret List
            // The Ligature Caret List table (LigCaretList) defines caret positions for all the ligatures in a font.
            // Not required for mDPF
            // MarkAttachmentType
            if ($mark_attach_class_def_offset) {
                if ($this->mode == 'summary') {
                    $this->mpdf->write_html('<h1>Mark Attachment Types</h1>');
                }
                $this->seek($gdef_offset + $mark_attach_class_def_offset);
                $mark_attachment_types = $this->_get_class_definition_table();
                foreach ($mark_attachment_types as $class => $glyphs) {
                    if (is_array($Marks) && count($Marks)) {
                        $mat = array_diff($Marks, $mark_attachment_types[$class]);
                        sort($mat, SORT_STRING);
                    } else {
                        $mat = [];
                    }
                    $this->mark_attachment_type[$class] = $this->format_class_arr($mat);
                    if ($this->mode == 'summary') {
                        $this->mpdf->write_html('<h3>Mark Attachment Type: ' . $class . '</h3>');
                        $html = '';
                        $html .= '<div class="glyphs">';
                        foreach ($glyphs as $g) {
                            $html .= '&#x25cc;&#x' . $g . '; ';
                        }
                        $html .= '</div>';
                        $this->mpdf->write_html($html);
                    }
                }
            } else {
                $this->mark_attachment_type = [];
            }
            // MarkGlyphSets only in Version 0x00010002 of GDEF
            if ($ver_min == 2 && $mark_glyph_sets_def_offset) {
                if ($this->mode == 'summary') {
                    $this->mpdf->write_html('<h1>Mark Glyph Sets</h1>');
                }
                $this->seek($gdef_offset + $mark_glyph_sets_def_offset);
                $mark_set_table_format = $this->read_ushort();
                $mark_set_count = $this->read_ushort();
                $mark_set_offset = [];
                for ($i = 0; $i < $mark_set_count; $i++) {
                    $mark_set_offset[] = $this->read_ulong();
                }
                for ($i = 0; $i < $mark_set_count; $i++) {
                    $this->seek($mark_set_offset[$i]);
                    $glyphs = $this->_get_coverage();
                    $this->mark_glyph_sets[$i] = $this->format_class_arr($glyphs);
                    if ($this->mode == 'summary') {
                        $this->mpdf->write_html('<h3>Mark Glyph Set class: ' . $i . '</h3>');
                        $html = '';
                        $html .= '<div class="glyphs">';
                        foreach ($glyphs as $g) {
                            $html .= '&#x25cc;&#x' . $g . '; ';
                        }
                        $html .= '</div>';
                        $this->mpdf->write_html($html);
                    }
                }
            } else {
                $this->mark_glyph_sets = [];
            }
        } else {
            $this->mpdf->write_html('<div>GDEF table not defined</div>');
        }
        //echo $this->GlyphClassMarks ; exit;
        //print_r($GlyphClass); exit;
        //print_r($GlyphByClass); exit;
    }
    function _get_class_definition_table($offset = 0)
    {
        if ($offset > 0) {
            $this->seek($offset);
        }
        // NB Any glyph not included in the range of covered GlyphIDs automatically belongs to Class 0. This is not returned by this function
        $class_format = $this->read_ushort();
        $glyph_by_class = [];
        if ($class_format == 1) {
            $start_glyph = $this->read_ushort();
            $glyph_count = $this->read_ushort();
            for ($i = 0; $i < $glyph_count; $i++) {
                $gid = $start_glyph + $i;
                $class = $this->read_ushort();
                $glyph_by_class[$class][] = unicode_hex($this->glyph_to_char[$gid][0]);
            }
        } else if ($class_format == 2) {
            $table_count = $this->read_ushort();
            for ($i = 0; $i < $table_count; $i++) {
                $start_glyph_id = $this->read_ushort();
                $end_glyph_id = $this->read_ushort();
                $class = $this->read_ushort();
                for ($gid = $start_glyph_id; $gid <= $end_glyph_id; $gid++) {
                    $glyph_by_class[$class][] = unicode_hex($this->glyph_to_char[$gid][0]);
                }
            }
        }
        ksort($glyph_by_class);
        return $glyph_by_class;
    }
    function _get_gsu_btables()
    {
        ///////////////////////////////////
        // GSUB - Glyph Substitution
        ///////////////////////////////////
        if (isset($this->tables["GSUB"])) {
            $this->mpdf->write_html('<h1>GSUB Tables</h1>');
            $ffeats = [];
            $gsub_offset = $this->seek_table("GSUB");
            $this->skip(4);
            $script_list_offset = $gsub_offset + $this->read_ushort();
            $feature_list_offset = $gsub_offset + $this->read_ushort();
            $lookup_list_offset = $gsub_offset + $this->read_ushort();
            // ScriptList
            $this->seek($script_list_offset);
            $script_count = $this->read_ushort();
            for ($i = 0; $i < $script_count; $i++) {
                $script_tag = $this->read_tag();
                // = "beng", "deva" etc.
                $script_table_offset = $this->read_ushort();
                $ffeats[$script_tag] = $script_list_offset + $script_table_offset;
            }
            // Script Table
            foreach ($ffeats as $t => $o) {
                $ls = [];
                $this->seek($o);
                $def_lang_sys_offset = $this->read_ushort();
                if ($def_lang_sys_offset > 0) {
                    $ls['DFLT'] = $def_lang_sys_offset + $o;
                }
                $lang_sys_count = $this->read_ushort();
                for ($i = 0; $i < $lang_sys_count; $i++) {
                    $lang_tag = $this->read_tag();
                    // =
                    $lang_table_offset = $this->read_ushort();
                    $ls[$lang_tag] = $o + $lang_table_offset;
                }
                $ffeats[$t] = $ls;
            }
            //print_r($ffeats); exit;
            // Get FeatureIndexList
            // LangSys Table - from first listed langsys
            foreach ($ffeats as $st => $scripts) {
                foreach ($scripts as $t => $o) {
                    $feature_index = [];
                    $langsystable_offset = $o;
                    $this->seek($langsystable_offset);
                    $look_up_order = $this->read_ushort();
                    //==NULL
                    $req_feature_index = $this->read_ushort();
                    if ($req_feature_index != 0xffff) {
                        $feature_index[] = $req_feature_index;
                    }
                    $feature_count = $this->read_ushort();
                    for ($i = 0; $i < $feature_count; $i++) {
                        $feature_index[] = $this->read_ushort();
                        // = index of feature
                    }
                    $ffeats[$st][$t] = $feature_index;
                }
            }
            //print_r($ffeats); exit;
            // Feauture List => LookupListIndex es
            $this->seek($feature_list_offset);
            $feature_count = $this->read_ushort();
            $Feature = [];
            for ($i = 0; $i < $feature_count; $i++) {
                $Feature[$i] = ['tag' => $this->read_tag()];
                $Feature[$i]['offset'] = $feature_list_offset + $this->read_ushort();
            }
            for ($i = 0; $i < $feature_count; $i++) {
                $this->seek($Feature[$i]['offset']);
                $this->read_ushort();
                // null
                $Feature[$i]['LookupCount'] = $Lookupcount = $this->read_ushort();
                $Feature[$i]['LookupListIndex'] = [];
                for ($c = 0; $c < $Lookupcount; $c++) {
                    $Feature[$i]['LookupListIndex'][] = $this->read_ushort();
                }
            }
            foreach ($ffeats as $st => $scripts) {
                foreach ($scripts as $t => $o) {
                    $feature_index = $ffeats[$st][$t];
                    foreach ($feature_index as $k => $fi) {
                        $ffeats[$st][$t][$k] = $Feature[$fi];
                    }
                }
            }
            //=====================================================================================
            $gsub = [];
            $gsub_script_lang = [];
            foreach ($ffeats as $st => $scripts) {
                foreach ($scripts as $t => $langsys) {
                    $lg = [];
                    foreach ($langsys as $ft) {
                        $lg[$ft['LookupListIndex'][0]] = $ft;
                    }
                    // list of Lookups in order they need to be run i.e. order listed in Lookup table
                    ksort($lg);
                    foreach ($lg as $ft) {
                        $gsub[$st][$t][$ft['tag']] = $ft['LookupListIndex'];
                    }
                    if (!isset($gsub_script_lang[$st])) {
                        $gsub_script_lang[$st] = '';
                    }
                    $gsub_script_lang[$st] .= $t . ' ';
                }
            }
            //print_r($gsub); exit;
            if ($this->mode == 'summary') {
                $this->mpdf->write_html('<h3>GSUB Scripts &amp; Languages</h3>');
                $this->mpdf->write_html('<div class="glyphs">');
                $html = '';
                if (count($gsub)) {
                    foreach ($gsub as $st => $g) {
                        $html .= '<h5>' . $st . '</h5>';
                        foreach ($g as $l => $t) {
                            $html .= '<div><a href="font_dump_OTL.php?script=' . $st . '&lang=' . $l . '">' . $l . '</a></b>: ';
                            foreach ($t as $tag => $o) {
                                $html .= $tag . ' ';
                            }
                            $html .= '</div>';
                        }
                    }
                } else {
                    $html .= '<div>No entries in GSUB table.</div>';
                }
                $this->mpdf->write_html($html);
                $this->mpdf->write_html('</div>');
                return 0;
            }
            //=====================================================================================
            // Get metadata and offsets for whole Lookup List table
            $this->seek($lookup_list_offset);
            $lookup_count = $this->read_ushort();
            $gs_lookup = [];
            $Offsets = [];
            $subtable_count = [];
            for ($i = 0; $i < $lookup_count; $i++) {
                $Offsets[$i] = $lookup_list_offset + $this->read_ushort();
            }
            for ($i = 0; $i < $lookup_count; $i++) {
                $this->seek($Offsets[$i]);
                $gs_lookup[$i]['Type'] = $this->read_ushort();
                $gs_lookup[$i]['Flag'] = $flag = $this->read_ushort();
                $gs_lookup[$i]['SubtableCount'] = $subtable_count[$i] = $this->read_ushort();
                for ($c = 0; $c < $subtable_count[$i]; $c++) {
                    $gs_lookup[$i]['Subtables'][$c] = $Offsets[$i] + $this->read_ushort();
                }
                // MarkFilteringSet = Index (base 0) into GDEF mark glyph sets structure
                if (($flag & 0x10) == 0x10) {
                    $gs_lookup[$i]['MarkFilteringSet'] = $this->read_ushort();
                }
                // else { $GSLookup[$i]['MarkFilteringSet'] = ''; }
                // Lookup Type 7: Extension
                if ($gs_lookup[$i]['Type'] == 7) {
                    // Overwrites new offset (32-bit) for each subtable, and a new lookup Type
                    for ($c = 0; $c < $subtable_count[$i]; $c++) {
                        $this->seek($gs_lookup[$i]['Subtables'][$c]);
                        $extension_pos_format = $this->read_ushort();
                        $type = $this->read_ushort();
                        $gs_lookup[$i]['Subtables'][$c] = $gs_lookup[$i]['Subtables'][$c] + $this->read_ulong();
                    }
                    $gs_lookup[$i]['Type'] = $type;
                }
            }
            //print_r($GSLookup); exit;
            //=====================================================================================
            // Process Whole LookupList - Get LuCoverage = Lookup coverage just for first glyph
            $this->gs_lu_coverage = [];
            for ($i = 0; $i < $lookup_count; $i++) {
                for ($c = 0; $c < $gs_lookup[$i]['SubtableCount']; $c++) {
                    $this->seek($gs_lookup[$i]['Subtables'][$c]);
                    $pos_format = $this->read_ushort();
                    if ($gs_lookup[$i]['Type'] == 5 && $pos_format == 3) {
                        $this->skip(4);
                    } else if ($gs_lookup[$i]['Type'] == 6 && $pos_format == 3) {
                        $backtrack_glyph_count = $this->read_ushort();
                        $this->skip(2 * $backtrack_glyph_count + 2);
                    }
                    // NB Coverage only looks at glyphs for position 1 (i.e. 5.3 and 6.3)	// NEEDS TO READ ALL ********************
                    $Coverage = $gs_lookup[$i]['Subtables'][$c] + $this->read_ushort();
                    $this->seek($Coverage);
                    $glyphs = $this->_get_coverage();
                    $this->gs_lu_coverage[$i][$c] = implode('|', $glyphs);
                }
            }
            //=====================================================================================
            $s = '<?php
$GlyphClassBases = \'' . $this->glyph_class_bases . '\';
$GlyphClassMarks = \'' . $this->glyph_class_marks . '\';
$GlyphClassLigatures = \'' . $this->glyph_class_ligatures . '\';
$GlyphClassComponents = \'' . $this->glyph_class_components . '\';
$MarkGlyphSets = ' . var_export($this->mark_glyph_sets, true) . ';
$MarkAttachmentType = ' . var_export($this->mark_attachment_type, true) . ';
?>';
            //=====================================================================================
            //=====================================================================================
            //=====================================================================================
            // Now repeats as original to get Substitution rules
            //=====================================================================================
            //=====================================================================================
            //=====================================================================================
            // Get metadata and offsets for whole Lookup List table
            $this->seek($lookup_list_offset);
            $lookup_count = $this->read_ushort();
            $Lookup = [];
            for ($i = 0; $i < $lookup_count; $i++) {
                $Lookup[$i]['offset'] = $lookup_list_offset + $this->read_ushort();
            }
            for ($i = 0; $i < $lookup_count; $i++) {
                $this->seek($Lookup[$i]['offset']);
                $Lookup[$i]['Type'] = $this->read_ushort();
                $Lookup[$i]['Flag'] = $flag = $this->read_ushort();
                $Lookup[$i]['SubtableCount'] = $this->read_ushort();
                for ($c = 0; $c < $Lookup[$i]['SubtableCount']; $c++) {
                    $Lookup[$i]['Subtable'][$c]['Offset'] = $Lookup[$i]['offset'] + $this->read_ushort();
                }
                // MarkFilteringSet = Index (base 0) into GDEF mark glyph sets structure
                if (($flag & 0x10) == 0x10) {
                    $Lookup[$i]['MarkFilteringSet'] = $this->read_ushort();
                } else {
                    $Lookup[$i]['MarkFilteringSet'] = '';
                }
                // Lookup Type 7: Extension
                if ($Lookup[$i]['Type'] == 7) {
                    // Overwrites new offset (32-bit) for each subtable, and a new lookup Type
                    for ($c = 0; $c < $Lookup[$i]['SubtableCount']; $c++) {
                        $this->seek($Lookup[$i]['Subtable'][$c]['Offset']);
                        $extension_pos_format = $this->read_ushort();
                        $type = $this->read_ushort();
                        $Lookup[$i]['Subtable'][$c]['Offset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ulong();
                    }
                    $Lookup[$i]['Type'] = $type;
                }
            }
            //print_r($Lookup); exit;
            //=====================================================================================
            // Process (1) Whole LookupList
            for ($i = 0; $i < $lookup_count; $i++) {
                for ($c = 0; $c < $Lookup[$i]['SubtableCount']; $c++) {
                    $this->seek($Lookup[$i]['Subtable'][$c]['Offset']);
                    $subst_format = $this->read_ushort();
                    $Lookup[$i]['Subtable'][$c]['Format'] = $subst_format;
                    /*
                     Lookup['Type'] Enumeration table for glyph substitution
                     Value	Type	Description
                     1	Single	Replace one glyph with one glyph
                     2	Multiple	Replace one glyph with more than one glyph
                     3	Alternate	Replace one glyph with one of many glyphs
                     4	Ligature	Replace multiple glyphs with one glyph
                     5	Context	Replace one or more glyphs in context
                     6	Chaining Context	Replace one or more glyphs in chained context
                     7	Extension Substitution	Extension mechanism for other substitutions (i.e. this excludes the Extension type substitution itself)
                     8	Reverse chaining context single 	Applied in reverse order, replace single glyph in chaining context
                    */
                    // LookupType 1: Single Substitution Subtable
                    if ($Lookup[$i]['Type'] == 1) {
                        $Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                        if ($subst_format == 1) {
                            // Calculated output glyph indices
                            $Lookup[$i]['Subtable'][$c]['DeltaGlyphID'] = $this->read_short();
                        } else if ($subst_format == 2) {
                            // Specified output glyph indices
                            $glyph_count = $this->read_ushort();
                            for ($g = 0; $g < $glyph_count; $g++) {
                                $Lookup[$i]['Subtable'][$c]['Glyphs'][] = $this->read_ushort();
                            }
                        }
                    } else if ($Lookup[$i]['Type'] == 2) {
                        $Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                        $Lookup[$i]['Subtable'][$c]['SequenceCount'] = $sequence_count = $this->read_short();
                        for ($s = 0; $s < $sequence_count; $s++) {
                            $Lookup[$i]['Subtable'][$c]['Sequences'][$s]['Offset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_short();
                        }
                        for ($s = 0; $s < $sequence_count; $s++) {
                            // Sequence Tables
                            $this->seek($Lookup[$i]['Subtable'][$c]['Sequences'][$s]['Offset']);
                            $Lookup[$i]['Subtable'][$c]['Sequences'][$s]['GlyphCount'] = $this->read_short();
                            for ($g = 0; $g < $Lookup[$i]['Subtable'][$c]['Sequences'][$s]['GlyphCount']; $g++) {
                                $Lookup[$i]['Subtable'][$c]['Sequences'][$s]['SubstituteGlyphID'][] = $this->read_ushort();
                            }
                        }
                    } else if ($Lookup[$i]['Type'] == 3) {
                        $Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                        $Lookup[$i]['Subtable'][$c]['AlternateSetCount'] = $alternate_set_count = $this->read_short();
                        for ($s = 0; $s < $alternate_set_count; $s++) {
                            $Lookup[$i]['Subtable'][$c]['AlternateSets'][$s]['Offset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_short();
                        }
                        for ($s = 0; $s < $alternate_set_count; $s++) {
                            // AlternateSet Tables
                            $this->seek($Lookup[$i]['Subtable'][$c]['AlternateSets'][$s]['Offset']);
                            $Lookup[$i]['Subtable'][$c]['AlternateSets'][$s]['GlyphCount'] = $this->read_short();
                            for ($g = 0; $g < $Lookup[$i]['Subtable'][$c]['AlternateSets'][$s]['GlyphCount']; $g++) {
                                $Lookup[$i]['Subtable'][$c]['AlternateSets'][$s]['SubstituteGlyphID'][] = $this->read_ushort();
                            }
                        }
                    } else if ($Lookup[$i]['Type'] == 4) {
                        $Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                        $Lookup[$i]['Subtable'][$c]['LigSetCount'] = $lig_set_count = $this->read_short();
                        for ($s = 0; $s < $lig_set_count; $s++) {
                            $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Offset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_short();
                        }
                        for ($s = 0; $s < $lig_set_count; $s++) {
                            // LigatureSet Tables
                            $this->seek($Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Offset']);
                            $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['LigCount'] = $this->read_short();
                            for ($g = 0; $g < $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['LigCount']; $g++) {
                                $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['LigatureOffset'][$g] = $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Offset'] + $this->read_ushort();
                            }
                        }
                        for ($s = 0; $s < $lig_set_count; $s++) {
                            for ($g = 0; $g < $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['LigCount']; $g++) {
                                // Ligature tables
                                $this->seek($Lookup[$i]['Subtable'][$c]['LigSet'][$s]['LigatureOffset'][$g]);
                                $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['LigGlyph'] = $this->read_ushort();
                                $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['CompCount'] = $this->read_ushort();
                                for ($l = 1; $l < $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['CompCount']; $l++) {
                                    $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['GlyphID'][$l] = $this->read_ushort();
                                }
                            }
                        }
                    } else if ($Lookup[$i]['Type'] == 5) {
                        // Format 1: Context Substitution
                        if ($subst_format == 1) {
                            $Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                            $Lookup[$i]['Subtable'][$c]['SubRuleSetCount'] = $sub_rule_set_count = $this->read_short();
                            for ($s = 0; $s < $sub_rule_set_count; $s++) {
                                $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['Offset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_short();
                            }
                            for ($s = 0; $s < $sub_rule_set_count; $s++) {
                                // SubRuleSet Tables
                                $this->seek($Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['Offset']);
                                $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRuleCount'] = $this->read_short();
                                for ($g = 0; $g < $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRuleCount']; $g++) {
                                    $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRuleOffset'][$g] = $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['Offset'] + $this->read_ushort();
                                }
                            }
                            for ($s = 0; $s < $sub_rule_set_count; $s++) {
                                // SubRule Tables
                                for ($g = 0; $g < $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRuleCount']; $g++) {
                                    // Ligature tables
                                    $this->seek($Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRuleOffset'][$g]);
                                    $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'][$g]['GlyphCount'] = $this->read_ushort();
                                    $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'][$g]['SubstCount'] = $this->read_ushort();
                                    // "Input"::[GlyphCount - 1]::Array of input GlyphIDs-start with second glyph
                                    for ($l = 1; $l < $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'][$g]['GlyphCount']; $l++) {
                                        $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'][$g]['Input'][$l] = $this->read_ushort();
                                    }
                                    // "SubstLookupRecord"::[SubstCount]::Array of SubstLookupRecords-in design order
                                    for ($l = 0; $l < $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'][$g]['SubstCount']; $l++) {
                                        $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'][$g]['SubstLookupRecord'][$l]['SequenceIndex'] = $this->read_ushort();
                                        $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'][$g]['SubstLookupRecord'][$l]['LookupListIndex'] = $this->read_ushort();
                                    }
                                }
                            }
                        } else if ($subst_format == 2) {
                            $Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                            $Lookup[$i]['Subtable'][$c]['ClassDefOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                            $Lookup[$i]['Subtable'][$c]['SubClassSetCnt'] = $this->read_ushort();
                            for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['SubClassSetCnt']; $b++) {
                                $offset = $this->read_ushort();
                                if ($offset == 0x0) {
                                    $Lookup[$i]['Subtable'][$c]['SubClassSetOffset'][] = 0;
                                } else {
                                    $Lookup[$i]['Subtable'][$c]['SubClassSetOffset'][] = $Lookup[$i]['Subtable'][$c]['Offset'] + $offset;
                                }
                            }
                        } else {
                            throw new \Mpdf\Exception\Font_Exception("GPOS Lookup Type " . $Lookup[$i]['Type'] . ", Format " . $subst_format . " not supported (ttfontsuni.php).");
                        }
                    } else if ($Lookup[$i]['Type'] == 6) {
                        // Format 1: Simple Chaining Context Glyph Substitution  p255
                        if ($subst_format == 1) {
                            $Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                            $Lookup[$i]['Subtable'][$c]['ChainSubRuleSetCount'] = $this->read_ushort();
                            for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['ChainSubRuleSetCount']; $b++) {
                                $Lookup[$i]['Subtable'][$c]['ChainSubRuleSetOffset'][] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                            }
                        } else if ($subst_format == 2) {
                            $Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                            $Lookup[$i]['Subtable'][$c]['BacktrackClassDefOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                            $Lookup[$i]['Subtable'][$c]['InputClassDefOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                            $Lookup[$i]['Subtable'][$c]['LookaheadClassDefOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                            $Lookup[$i]['Subtable'][$c]['ChainSubClassSetCnt'] = $this->read_ushort();
                            for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['ChainSubClassSetCnt']; $b++) {
                                $offset = $this->read_ushort();
                                if ($offset == 0x0) {
                                    $Lookup[$i]['Subtable'][$c]['ChainSubClassSetOffset'][] = $offset;
                                } else {
                                    $Lookup[$i]['Subtable'][$c]['ChainSubClassSetOffset'][] = $Lookup[$i]['Subtable'][$c]['Offset'] + $offset;
                                }
                            }
                        } else if ($subst_format == 3) {
                            $Lookup[$i]['Subtable'][$c]['BacktrackGlyphCount'] = $this->read_ushort();
                            for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['BacktrackGlyphCount']; $b++) {
                                $Lookup[$i]['Subtable'][$c]['CoverageBacktrack'][] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                            }
                            $Lookup[$i]['Subtable'][$c]['InputGlyphCount'] = $this->read_ushort();
                            for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['InputGlyphCount']; $b++) {
                                $Lookup[$i]['Subtable'][$c]['CoverageInput'][] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                            }
                            $Lookup[$i]['Subtable'][$c]['LookaheadGlyphCount'] = $this->read_ushort();
                            for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['LookaheadGlyphCount']; $b++) {
                                $Lookup[$i]['Subtable'][$c]['CoverageLookahead'][] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                            }
                            $Lookup[$i]['Subtable'][$c]['SubstCount'] = $this->read_ushort();
                            for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['SubstCount']; $b++) {
                                $Lookup[$i]['Subtable'][$c]['SubstLookupRecord'][$b]['SequenceIndex'] = $this->read_ushort();
                                $Lookup[$i]['Subtable'][$c]['SubstLookupRecord'][$b]['LookupListIndex'] = $this->read_ushort();
                                /*
                                 Substitution Lookup Record
                                 All contextual substitution subtables specify the substitution data in a Substitution Lookup Record (SubstLookupRecord). Each record contains a SequenceIndex, which indicates the position where the substitution will occur in the glyph sequence. In addition, a LookupListIndex identifies the lookup to be applied at the glyph position specified by the SequenceIndex.
                                */
                            }
                        }
                    } else {
                        throw new \Mpdf\Exception\Font_Exception("Lookup Type " . $Lookup[$i]['Type'] . " not supported.");
                    }
                }
            }
            //print_r($Lookup); exit;
            //=====================================================================================
            // Process (2) Whole LookupList
            // Get Coverage tables and prepare preg_replace
            for ($i = 0; $i < $lookup_count; $i++) {
                for ($c = 0; $c < $Lookup[$i]['SubtableCount']; $c++) {
                    $subst_format = $Lookup[$i]['Subtable'][$c]['Format'];
                    // LookupType 1: Single Substitution Subtable 1 => 1
                    if ($Lookup[$i]['Type'] == 1) {
                        $this->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
                        $glyphs = $this->_get_coverage(false);
                        for ($g = 0; $g < count($glyphs); $g++) {
                            $replace = [];
                            $substitute = [];
                            $replace[] = unicode_hex($this->glyph_to_char[$glyphs[$g]][0]);
                            // Flag = Ignore
                            if ($this->_check_gsu_bignore($Lookup[$i]['Flag'], $replace[0], $Lookup[$i]['MarkFilteringSet'])) {
                                continue;
                            }
                            if (isset($Lookup[$i]['Subtable'][$c]['DeltaGlyphID'])) {
                                // Format 1
                                $substitute[] = unicode_hex($this->glyph_to_char[$glyphs[$g] + $Lookup[$i]['Subtable'][$c]['DeltaGlyphID']][0]);
                            } else {
                                // Format 2
                                $substitute[] = unicode_hex($this->glyph_to_char[$Lookup[$i]['Subtable'][$c]['Glyphs'][$g]][0]);
                            }
                            $Lookup[$i]['Subtable'][$c]['subs'][] = ['Replace' => $replace, 'substitute' => $substitute];
                        }
                    } else if ($Lookup[$i]['Type'] == 2) {
                        $this->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
                        $glyphs = $this->_get_coverage();
                        for ($g = 0; $g < count($glyphs); $g++) {
                            $replace = [];
                            $substitute = [];
                            $replace[] = $glyphs[$g];
                            // Flag = Ignore
                            if ($this->_check_gsu_bignore($Lookup[$i]['Flag'], $replace[0], $Lookup[$i]['MarkFilteringSet'])) {
                                continue;
                            }
                            if (!isset($Lookup[$i]['Subtable'][$c]['Sequences'][$g]['SubstituteGlyphID'])) {
                                continue;
                            }
                            if (count($Lookup[$i]['Subtable'][$c]['Sequences'][$g]['SubstituteGlyphID']) == 0) {
                                continue;
                            }
                            // Illegal for GlyphCount to be 0; either error in font, or something has gone wrong - lets carry on for now!
                            foreach ($Lookup[$i]['Subtable'][$c]['Sequences'][$g]['SubstituteGlyphID'] as $sub) {
                                $substitute[] = unicode_hex($this->glyph_to_char[$sub][0]);
                            }
                            $Lookup[$i]['Subtable'][$c]['subs'][] = ['Replace' => $replace, 'substitute' => $substitute];
                        }
                    } else if ($Lookup[$i]['Type'] == 3) {
                        $this->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
                        $glyphs = $this->_get_coverage();
                        for ($g = 0; $g < count($glyphs); $g++) {
                            $replace = [];
                            $substitute = [];
                            $replace[] = $glyphs[$g];
                            // Flag = Ignore
                            if ($this->_check_gsu_bignore($Lookup[$i]['Flag'], $replace[0], $Lookup[$i]['MarkFilteringSet'])) {
                                continue;
                            }
                            for ($gl = 0; $gl < $Lookup[$i]['Subtable'][$c]['AlternateSets'][$g]['GlyphCount']; $gl++) {
                                $gid = $Lookup[$i]['Subtable'][$c]['AlternateSets'][$g]['SubstituteGlyphID'][$gl];
                                $substitute[] = unicode_hex($this->glyph_to_char[$gid][0]);
                            }
                            //$gid = $Lookup[$i]['Subtable'][$c]['AlternateSets'][$g]['SubstituteGlyphID'][0];
                            //$substitute[] = unicode_hex($this->glyphToChar[$gid][0]);
                            $Lookup[$i]['Subtable'][$c]['subs'][] = ['Replace' => $replace, 'substitute' => $substitute];
                        }
                        if ($i == 166) {
                            print_r($Lookup[$i]['Subtable']);
                            exit;
                        }
                    } else if ($Lookup[$i]['Type'] == 4) {
                        $this->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
                        $glyphs = $this->_get_coverage();
                        $lig_set_count = $Lookup[$i]['Subtable'][$c]['LigSetCount'];
                        for ($s = 0; $s < $lig_set_count; $s++) {
                            for ($g = 0; $g < $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['LigCount']; $g++) {
                                $replace = [];
                                $substitute = [];
                                $replace[] = $glyphs[$s];
                                // Flag = Ignore
                                if ($this->_check_gsu_bignore($Lookup[$i]['Flag'], $replace[0], $Lookup[$i]['MarkFilteringSet'])) {
                                    continue;
                                }
                                for ($l = 1; $l < $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['CompCount']; $l++) {
                                    $gid = $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['GlyphID'][$l];
                                    $rpl = unicode_hex($this->glyph_to_char[$gid][0]);
                                    // Flag = Ignore
                                    if ($this->_check_gsu_bignore($Lookup[$i]['Flag'], $rpl, $Lookup[$i]['MarkFilteringSet'])) {
                                        continue 2;
                                    }
                                    $replace[] = $rpl;
                                }
                                $gid = $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['LigGlyph'];
                                $substitute[] = unicode_hex($this->glyph_to_char[$gid][0]);
                                $Lookup[$i]['Subtable'][$c]['subs'][] = ['Replace' => $replace, 'substitute' => $substitute, 'CompCount' => $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['CompCount']];
                            }
                        }
                    } else if ($Lookup[$i]['Type'] == 5) {
                        // Format 1: Context Substitution
                        if ($subst_format == 1) {
                            $this->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
                            $Lookup[$i]['Subtable'][$c]['CoverageGlyphs'] = $coverage_glyphs = $this->_get_coverage();
                            for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['SubRuleSetCount']; $s++) {
                                $sub_rule_set = $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s];
                                $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['FirstGlyph'] = $coverage_glyphs[$s];
                                for ($r = 0; $r < $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRuleCount']; $r++) {
                                    $glyph_count = $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'][$r]['GlyphCount'];
                                    for ($g = 1; $g < $glyph_count; $g++) {
                                        $glyph_id = $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'][$r]['Input'][$g];
                                        $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'][$r]['InputGlyphs'][$g] = unicode_hex($this->glyph_to_char[$glyph_id][0]);
                                    }
                                }
                            }
                        } else if ($subst_format == 2) {
                            $this->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
                            $Lookup[$i]['Subtable'][$c]['CoverageGlyphs'] = $coverage_glyphs = $this->_get_coverage();
                            $input_classes = $this->_get_classes($Lookup[$i]['Subtable'][$c]['ClassDefOffset']);
                            $Lookup[$i]['Subtable'][$c]['InputClasses'] = $input_classes;
                            for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['SubClassSetCnt']; $s++) {
                                if ($Lookup[$i]['Subtable'][$c]['SubClassSetOffset'][$s] > 0) {
                                    $this->seek($Lookup[$i]['Subtable'][$c]['SubClassSetOffset'][$s]);
                                    $Lookup[$i]['Subtable'][$c]['SubClassSet'][$s]['SubClassRuleCnt'] = $sub_class_rule_cnt = $this->read_ushort();
                                    $sub_class_rule = [];
                                    for ($b = 0; $b < $sub_class_rule_cnt; $b++) {
                                        $sub_class_rule[$b] = $Lookup[$i]['Subtable'][$c]['SubClassSetOffset'][$s] + $this->read_ushort();
                                        $Lookup[$i]['Subtable'][$c]['SubClassSet'][$s]['SubClassRule'][$b] = $sub_class_rule[$b];
                                    }
                                }
                            }
                            for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['SubClassSetCnt']; $s++) {
                                $sub_class_rule_cnt = $Lookup[$i]['Subtable'][$c]['SubClassSet'][$s]['SubClassRuleCnt'];
                                for ($b = 0; $b < $sub_class_rule_cnt; $b++) {
                                    if ($Lookup[$i]['Subtable'][$c]['SubClassSetOffset'][$s] > 0) {
                                        $this->seek($Lookup[$i]['Subtable'][$c]['SubClassSet'][$s]['SubClassRule'][$b]);
                                        $Rule = [];
                                        $Rule['InputGlyphCount'] = $this->read_ushort();
                                        $Rule['SubstCount'] = $this->read_ushort();
                                        for ($r = 1; $r < $Rule['InputGlyphCount']; $r++) {
                                            $Rule['Input'][$r] = $this->read_ushort();
                                        }
                                        for ($r = 0; $r < $Rule['SubstCount']; $r++) {
                                            $Rule['SequenceIndex'][$r] = $this->read_ushort();
                                            $Rule['LookupListIndex'][$r] = $this->read_ushort();
                                        }
                                        $Lookup[$i]['Subtable'][$c]['SubClassSet'][$s]['SubClassRule'][$b] = $Rule;
                                    }
                                }
                            }
                        } else if ($subst_format == 3) {
                            for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['InputGlyphCount']; $b++) {
                                $this->seek($Lookup[$i]['Subtable'][$c]['CoverageInput'][$b]);
                                $glyphs = $this->_get_coverage();
                                $Lookup[$i]['Subtable'][$c]['CoverageInputGlyphs'][] = implode("|", $glyphs);
                            }
                            throw new \Mpdf\Exception\Font_Exception("Lookup Type 5, SubstFormat 3 not tested. Please report this with the name of font used - " . $this->fontkey);
                        }
                    } else if ($Lookup[$i]['Type'] == 6) {
                        // Format 1: Simple Chaining Context Glyph Substitution  p255
                        if ($subst_format == 1) {
                            $this->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
                            $Lookup[$i]['Subtable'][$c]['CoverageGlyphs'] = $coverage_glyphs = $this->_get_coverage();
                            $chain_sub_rule_set_cnt = $Lookup[$i]['Subtable'][$c]['ChainSubRuleSetCount'];
                            for ($s = 0; $s < $chain_sub_rule_set_cnt; $s++) {
                                $this->seek($Lookup[$i]['Subtable'][$c]['ChainSubRuleSetOffset'][$s]);
                                $chain_sub_rule_cnt = $Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRuleCount'] = $this->read_ushort();
                                for ($r = 0; $r < $chain_sub_rule_cnt; $r++) {
                                    $Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRuleOffset'][$r] = $Lookup[$i]['Subtable'][$c]['ChainSubRuleSetOffset'][$s] + $this->read_ushort();
                                }
                            }
                            for ($s = 0; $s < $chain_sub_rule_set_cnt; $s++) {
                                $chain_sub_rule_cnt = $Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRuleCount'];
                                for ($r = 0; $r < $chain_sub_rule_cnt; $r++) {
                                    // ChainSubRule
                                    $this->seek($Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRuleOffset'][$r]);
                                    $backtrack_glyph_count = $Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRule'][$r]['BacktrackGlyphCount'] = $this->read_ushort();
                                    for ($g = 0; $g < $backtrack_glyph_count; $g++) {
                                        $glyph_id = $this->read_ushort();
                                        $Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRule'][$r]['BacktrackGlyphs'][$g] = unicode_hex($this->glyph_to_char[$glyph_id][0]);
                                    }
                                    $input_glyph_count = $Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRule'][$r]['InputGlyphCount'] = $this->read_ushort();
                                    for ($g = 1; $g < $input_glyph_count; $g++) {
                                        $glyph_id = $this->read_ushort();
                                        $Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRule'][$r]['InputGlyphs'][$g] = unicode_hex($this->glyph_to_char[$glyph_id][0]);
                                    }
                                    $lookahead_glyph_count = $Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRule'][$r]['LookaheadGlyphCount'] = $this->read_ushort();
                                    for ($g = 0; $g < $lookahead_glyph_count; $g++) {
                                        $glyph_id = $this->read_ushort();
                                        $Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRule'][$r]['LookaheadGlyphs'][$g] = unicode_hex($this->glyph_to_char[$glyph_id][0]);
                                    }
                                    $subst_count = $Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRule'][$r]['SubstCount'] = $this->read_ushort();
                                    for ($lu = 0; $lu < $subst_count; $lu++) {
                                        $Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRule'][$r]['SequenceIndex'][$lu] = $this->read_ushort();
                                        $Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRule'][$r]['LookupListIndex'][$lu] = $this->read_ushort();
                                    }
                                }
                            }
                        } else if ($subst_format == 2) {
                            $this->seek($Lookup[$i]['Subtable'][$c]['CoverageTableOffset']);
                            $Lookup[$i]['Subtable'][$c]['CoverageGlyphs'] = $coverage_glyphs = $this->_get_coverage();
                            $backtrack_classes = $this->_get_classes($Lookup[$i]['Subtable'][$c]['BacktrackClassDefOffset']);
                            $Lookup[$i]['Subtable'][$c]['BacktrackClasses'] = $backtrack_classes;
                            $input_classes = $this->_get_classes($Lookup[$i]['Subtable'][$c]['InputClassDefOffset']);
                            $Lookup[$i]['Subtable'][$c]['InputClasses'] = $input_classes;
                            $lookahead_classes = $this->_get_classes($Lookup[$i]['Subtable'][$c]['LookaheadClassDefOffset']);
                            $Lookup[$i]['Subtable'][$c]['LookaheadClasses'] = $lookahead_classes;
                            for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['ChainSubClassSetCnt']; $s++) {
                                if ($Lookup[$i]['Subtable'][$c]['ChainSubClassSetOffset'][$s] > 0) {
                                    $this->seek($Lookup[$i]['Subtable'][$c]['ChainSubClassSetOffset'][$s]);
                                    $Lookup[$i]['Subtable'][$c]['ChainSubClassSet'][$s]['ChainSubClassRuleCnt'] = $chain_sub_class_rule_cnt = $this->read_ushort();
                                    $chain_sub_class_rule = [];
                                    for ($b = 0; $b < $chain_sub_class_rule_cnt; $b++) {
                                        $chain_sub_class_rule[$b] = $Lookup[$i]['Subtable'][$c]['ChainSubClassSetOffset'][$s] + $this->read_ushort();
                                        $Lookup[$i]['Subtable'][$c]['ChainSubClassSet'][$s]['ChainSubClassRule'][$b] = $chain_sub_class_rule[$b];
                                    }
                                }
                            }
                            for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['ChainSubClassSetCnt']; $s++) {
                                $chain_sub_class_rule_cnt = $Lookup[$i]['Subtable'][$c]['ChainSubClassSet'][$s]['ChainSubClassRuleCnt'];
                                for ($b = 0; $b < $chain_sub_class_rule_cnt; $b++) {
                                    if ($Lookup[$i]['Subtable'][$c]['ChainSubClassSetOffset'][$s] > 0) {
                                        $this->seek($Lookup[$i]['Subtable'][$c]['ChainSubClassSet'][$s]['ChainSubClassRule'][$b]);
                                        $Rule = [];
                                        $Rule['BacktrackGlyphCount'] = $this->read_ushort();
                                        for ($r = 0; $r < $Rule['BacktrackGlyphCount']; $r++) {
                                            $Rule['Backtrack'][$r] = $this->read_ushort();
                                        }
                                        $Rule['InputGlyphCount'] = $this->read_ushort();
                                        for ($r = 1; $r < $Rule['InputGlyphCount']; $r++) {
                                            $Rule['Input'][$r] = $this->read_ushort();
                                        }
                                        $Rule['LookaheadGlyphCount'] = $this->read_ushort();
                                        for ($r = 0; $r < $Rule['LookaheadGlyphCount']; $r++) {
                                            $Rule['Lookahead'][$r] = $this->read_ushort();
                                        }
                                        $Rule['SubstCount'] = $this->read_ushort();
                                        for ($r = 0; $r < $Rule['SubstCount']; $r++) {
                                            $Rule['SequenceIndex'][$r] = $this->read_ushort();
                                            $Rule['LookupListIndex'][$r] = $this->read_ushort();
                                        }
                                        $Lookup[$i]['Subtable'][$c]['ChainSubClassSet'][$s]['ChainSubClassRule'][$b] = $Rule;
                                    }
                                }
                            }
                        } else if ($subst_format == 3) {
                            for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['BacktrackGlyphCount']; $b++) {
                                $this->seek($Lookup[$i]['Subtable'][$c]['CoverageBacktrack'][$b]);
                                $glyphs = $this->_get_coverage();
                                $Lookup[$i]['Subtable'][$c]['CoverageBacktrackGlyphs'][] = implode("|", $glyphs);
                            }
                            for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['InputGlyphCount']; $b++) {
                                $this->seek($Lookup[$i]['Subtable'][$c]['CoverageInput'][$b]);
                                $glyphs = $this->_get_coverage();
                                $Lookup[$i]['Subtable'][$c]['CoverageInputGlyphs'][] = implode("|", $glyphs);
                                // Don't use above value as these are ordered numerically not as need to process
                            }
                            for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['LookaheadGlyphCount']; $b++) {
                                $this->seek($Lookup[$i]['Subtable'][$c]['CoverageLookahead'][$b]);
                                $glyphs = $this->_get_coverage();
                                $Lookup[$i]['Subtable'][$c]['CoverageLookaheadGlyphs'][] = implode("|", $glyphs);
                            }
                        }
                    }
                }
            }
            //=====================================================================================
            //=====================================================================================
            //=====================================================================================
            $st = $this->mpdf->ot_lscript;
            $t = $this->mpdf->ot_llang;
            $langsys = $gsub[$st][$t];
            $lul = [];
            // array of LookupListIndexes
            $tags = [];
            // corresponding array of feature tags e.g. 'ccmp'
            foreach ($langsys as $tag => $ft) {
                foreach ($ft as $ll) {
                    $lul[$ll] = $tag;
                }
            }
            ksort($lul);
            // Order the Lookups in the order they are in the GUSB table, regardless of Feature order
            $this->_get_gsu_barray($Lookup, $lul, $st);
            //print_r($lul); exit;
        }
        //print_r($Lookup); exit;
        return [$gsub_script_lang, $gsub, $gs_lookup, $rtl_pu_astr, $rtl_pu_aarr];
    }
    /////////////////////////////////////////////////////////////////////////////////////////
    // GSUB functions
    function _get_gsu_barray(array &$Lookup, &$lul, $scripttag, $level = 1, $coverage = '', $ex_b = '', $ex_l = '')
    {
        // Process (3) LookupList for specific Script-LangSys
        // Generate preg_replace
        $html = '';
        if ($level == 1) {
            $html .= '<bookmark level="0" content="GSUB features">';
        }
        foreach ($lul as $i => $tag) {
            $html .= '<div class="level' . $level . '">';
            $html .= '<h5 class="level' . $level . '">';
            if ($level == 1) {
                $html .= '<bookmark level="1" content="' . $tag . ' [#' . $i . ']">';
            }
            $html .= 'Lookup #' . $i . ' [tag: <span style="color:#000066;">' . $tag . '</span>]</h5>';
            $ignore = $this->_get_gsu_bignore_string($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
            if ($ignore) {
                $html .= '<div class="ignore">Ignoring: ' . $ignore . '</div> ';
            }
            $Type = $Lookup[$i]['Type'];
            $Flag = $Lookup[$i]['Flag'];
            if (($Flag & 0x1) == 1) {
                $dir = 'RTL';
            } else {
                $dir = 'LTR';
            }
            for ($c = 0; $c < $Lookup[$i]['SubtableCount']; $c++) {
                $html .= '<div class="subtable">Subtable #' . $c;
                if ($level == 1) {
                    $html .= '<bookmark level="2" content="Subtable #' . $c . '">';
                }
                $html .= '</div>';
                $subst_format = $Lookup[$i]['Subtable'][$c]['Format'];
                // LookupType 1: Single Substitution Subtable
                if ($Lookup[$i]['Type'] == 1) {
                    $html .= '<div class="lookuptype">LookupType 1: Single Substitution Subtable</div>';
                    for ($s = 0; $s < count($Lookup[$i]['Subtable'][$c]['subs']); $s++) {
                        $input_glyphs = $Lookup[$i]['Subtable'][$c]['subs'][$s]['Replace'];
                        $substitute = $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute'][0];
                        if ($level == 2 && strpos($coverage, $input_glyphs[0]) === false) {
                            continue;
                        }
                        $html .= '<div class="substitution">';
                        $html .= '<span class="unicode">' . $this->format_uni($input_glyphs[0]) . '&nbsp;</span> ';
                        if ($level == 2 && $ex_b) {
                            $html .= $ex_b;
                        }
                        $html .= '<span class="unchanged">&nbsp;' . $this->format_entity($input_glyphs[0]) . '</span>';
                        if ($level == 2 && $ex_l) {
                            $html .= $ex_l;
                        }
                        $html .= '&nbsp; &raquo; &raquo; &nbsp;';
                        if ($level == 2 && $ex_b) {
                            $html .= $ex_b;
                        }
                        $html .= '<span class="changed">&nbsp;' . $this->format_entity($substitute) . '</span>';
                        if ($level == 2 && $ex_l) {
                            $html .= $ex_l;
                        }
                        $html .= '&nbsp; <span class="unicode">' . $this->format_uni($substitute) . '</span> ';
                        $html .= '</div>';
                    }
                } else if ($Lookup[$i]['Type'] == 2) {
                    $html .= '<div class="lookuptype">LookupType 2: Multiple Substitution Subtable</div>';
                    for ($s = 0; $s < count($Lookup[$i]['Subtable'][$c]['subs']); $s++) {
                        $input_glyphs = $Lookup[$i]['Subtable'][$c]['subs'][$s]['Replace'];
                        $substitute = $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute'];
                        if ($level == 2 && strpos($coverage, $input_glyphs[0]) === false) {
                            continue;
                        }
                        $html .= '<div class="substitution">';
                        $html .= '<span class="unicode">' . $this->format_uni($input_glyphs[0]) . '&nbsp;</span> ';
                        if ($level == 2 && $ex_b) {
                            $html .= $ex_b;
                        }
                        $html .= '<span class="unchanged">&nbsp;' . $this->format_entity($input_glyphs[0]) . '</span>';
                        if ($level == 2 && $ex_l) {
                            $html .= $ex_l;
                        }
                        $html .= '&nbsp; &raquo; &raquo; &nbsp;';
                        if ($level == 2 && $ex_b) {
                            $html .= $ex_b;
                        }
                        $html .= '<span class="changed">&nbsp;' . $this->format_entity_arr($substitute) . '</span>';
                        if ($level == 2 && $ex_l) {
                            $html .= $ex_l;
                        }
                        $html .= '&nbsp; <span class="unicode">' . $this->format_uni_arr($substitute) . '</span> ';
                        $html .= '</div>';
                    }
                } else if ($Lookup[$i]['Type'] == 3) {
                    $html .= '<div class="lookuptype">LookupType 3: Alternate Forms</div>';
                    for ($s = 0; $s < count($Lookup[$i]['Subtable'][$c]['subs']); $s++) {
                        $input_glyphs = $Lookup[$i]['Subtable'][$c]['subs'][$s]['Replace'];
                        $substitute = $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute'][0];
                        if ($level == 2 && strpos($coverage, $input_glyphs[0]) === false) {
                            continue;
                        }
                        $html .= '<div class="substitution">';
                        $html .= '<span class="unicode">' . $this->format_uni($input_glyphs[0]) . '&nbsp;</span> ';
                        if ($level == 2 && $ex_b) {
                            $html .= $ex_b;
                        }
                        $html .= '<span class="unchanged">&nbsp;' . $this->format_entity($input_glyphs[0]) . '</span>';
                        if ($level == 2 && $ex_l) {
                            $html .= $ex_l;
                        }
                        $html .= '&nbsp; &raquo; &raquo; &nbsp;';
                        if ($level == 2 && $ex_b) {
                            $html .= $ex_b;
                        }
                        $html .= '<span class="changed">&nbsp;' . $this->format_entity($substitute) . '</span>';
                        if ($level == 2 && $ex_l) {
                            $html .= $ex_l;
                        }
                        $html .= '&nbsp; <span class="unicode">' . $this->format_uni($substitute) . '</span> ';
                        if (count($Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute']) > 1) {
                            for ($alt = 1; $alt < count($Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute']); $alt++) {
                                $substitute = $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute'][$alt];
                                $html .= '&nbsp; | &nbsp; ALT #' . $alt . ' &nbsp; ';
                                $html .= '<span class="changed">&nbsp;' . $this->format_entity($substitute) . '</span>';
                                $html .= '&nbsp; <span class="unicode">' . $this->format_uni($substitute) . '</span> ';
                            }
                        }
                        $html .= '</div>';
                    }
                } else if ($Lookup[$i]['Type'] == 4) {
                    $html .= '<div class="lookuptype">LookupType 4: Ligature Substitution Subtable</div>';
                    for ($s = 0; $s < count($Lookup[$i]['Subtable'][$c]['subs']); $s++) {
                        $input_glyphs = $Lookup[$i]['Subtable'][$c]['subs'][$s]['Replace'];
                        $substitute = $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute'][0];
                        if ($level == 2 && strpos($coverage, $input_glyphs[0]) === false) {
                            continue;
                        }
                        $html .= '<div class="substitution">';
                        $html .= '<span class="unicode">' . $this->format_uni_arr($input_glyphs) . '&nbsp;</span> ';
                        if ($level == 2 && $ex_b) {
                            $html .= $ex_b;
                        }
                        $html .= '<span class="unchanged">&nbsp;' . $this->format_entity_arr($input_glyphs) . '</span>';
                        if ($level == 2 && $ex_l) {
                            $html .= $ex_l;
                        }
                        $html .= '&nbsp; &raquo; &raquo; &nbsp;';
                        if ($level == 2 && $ex_b) {
                            $html .= $ex_b;
                        }
                        $html .= '<span class="changed">&nbsp;' . $this->format_entity($substitute) . '</span>';
                        if ($level == 2 && $ex_l) {
                            $html .= $ex_l;
                        }
                        $html .= '&nbsp; <span class="unicode">' . $this->format_uni($substitute) . '</span> ';
                        $html .= '</div>';
                    }
                } else if ($Lookup[$i]['Type'] == 5) {
                    $html .= '<div class="lookuptype">LookupType 5: Contextual Substitution Subtable</div>';
                    // Format 1: Context Substitution
                    if ($subst_format == 1) {
                        $html .= '<div class="lookuptypesub">Format 1: Context Substitution</div>';
                        for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['SubRuleSetCount']; $s++) {
                            // SubRuleSet
                            $sub_rule = [];
                            $html .= '<div class="rule">Subrule Set: ' . $s . '</div>';
                            foreach ($Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'] as $rctr => $rule) {
                                // SubRule
                                $html .= '<div class="rule">SubRule: ' . $rctr . '</div>';
                                $input_glyphs = [];
                                if ($rule['GlyphCount'] > 1) {
                                    $input_glyphs = $rule['InputGlyphs'];
                                }
                                $input_glyphs[0] = $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['FirstGlyph'];
                                ksort($input_glyphs);
                                $n_input = count($input_glyphs);
                                $example_i = [];
                                $html .= '<div class="context">CONTEXT: ';
                                for ($ff = 0; $ff < count($input_glyphs); $ff++) {
                                    $html .= '<div>Input #' . $ff . ': <span class="unchanged">&nbsp;' . $this->format_entity_str($input_glyphs[$ff]) . '&nbsp;</span></div>';
                                    $example_i[] = $this->format_entity_first($input_glyphs[$ff]);
                                }
                                $html .= '</div>';
                                for ($b = 0; $b < $rule['SubstCount']; $b++) {
                                    $lup = $rule['SubstLookupRecord'][$b]['LookupListIndex'];
                                    $seq_index = $rule['SubstLookupRecord'][$b]['SequenceIndex'];
                                    // GENERATE exampleI[<seqIndex] .... exampleI[>seqIndex]
                                    $ex_b = '';
                                    $ex_l = '';
                                    if ($seq_index > 0) {
                                        $ex_b .= '<span class="inputother">';
                                        for ($ip = 0; $ip < $seq_index; $ip++) {
                                            $ex_b .= $this->format_entity($input_glyphs[$ip]) . '&#x200d;';
                                        }
                                        $ex_b .= '</span>';
                                    }
                                    if (count($input_glyphs) > $seq_index + 1) {
                                        $ex_l .= '<span class="inputother">';
                                        for ($ip = $seq_index + 1; $ip < count($input_glyphs); $ip++) {
                                            $ex_l .= $this->format_entity($input_glyphs[$ip]) . '&#x200d;';
                                        }
                                        $ex_l .= '</span>';
                                    }
                                    $html .= '<div class="sequenceIndex">Substitution Position: ' . $seq_index . '</div>';
                                    $lul2 = [$lup => $tag];
                                    // Only apply if the (first) 'Replace' glyph from the
                                    // Lookup list is in the [inputGlyphs] at ['SequenceIndex']
                                    // Pass $inputGlyphs[$seqIndex] e.g. 00636|00645|00656
                                    // to level 2 and only apply if first Replace glyph is in this list
                                    $html .= $this->_get_gsu_barray($Lookup, $lul2, $scripttag, 2, $input_glyphs[$seq_index], $ex_b, $ex_l);
                                }
                                if (count($sub_rule['rules'])) {
                                    $volt[] = $sub_rule;
                                }
                            }
                        }
                    } else if ($subst_format == 2) {
                        $html .= '<div class="lookuptypesub">Format 2: Class-based Context Glyph Substitution</div>';
                        foreach ($Lookup[$i]['Subtable'][$c]['SubClassSet'] as $input_class => $cscs) {
                            $html .= '<div class="rule">Input Class: ' . $input_class . '</div>';
                            for ($cscrule = 0; $cscrule < $cscs['SubClassRuleCnt']; $cscrule++) {
                                $html .= '<div class="rule">Rule: ' . $cscrule . '</div>';
                                $rule = $cscs['SubClassRule'][$cscrule];
                                $input_glyphs = [];
                                $input_glyphs[0] = $Lookup[$i]['Subtable'][$c]['InputClasses'][$input_class];
                                if ($rule['InputGlyphCount'] > 1) {
                                    //  NB starts at 1
                                    for ($gcl = 1; $gcl < $rule['InputGlyphCount']; $gcl++) {
                                        $classindex = $rule['Input'][$gcl];
                                        $input_glyphs[$gcl] = $Lookup[$i]['Subtable'][$c]['InputClasses'][$classindex];
                                    }
                                }
                                // Class 0 contains all the glyphs NOT in the other classes
                                $class0excl = implode('|', $Lookup[$i]['Subtable'][$c]['InputClasses']);
                                $example_i = [];
                                $html .= '<div class="context">CONTEXT: ';
                                for ($ff = 0; $ff < count($input_glyphs); $ff++) {
                                    if (!$input_glyphs[$ff]) {
                                        $html .= '<div>Input #' . $ff . ': <span class="unchanged">&nbsp;[NOT ' . $this->format_entity_str($class0excl) . ']&nbsp;</span></div>';
                                        $example_i[] = '[NOT ' . $this->format_entity_first($class0excl) . ']';
                                    } else {
                                        $html .= '<div>Input #' . $ff . ': <span class="unchanged">&nbsp;' . $this->format_entity_str($input_glyphs[$ff]) . '&nbsp;</span></div>';
                                        $example_i[] = $this->format_entity_first($input_glyphs[$ff]);
                                    }
                                }
                                $html .= '</div>';
                                for ($b = 0; $b < $rule['SubstCount']; $b++) {
                                    $lup = $rule['LookupListIndex'][$b];
                                    $seq_index = $rule['SequenceIndex'][$b];
                                    // GENERATE exampleI[<seqIndex] .... exampleI[>seqIndex]
                                    $ex_b = '';
                                    $ex_l = '';
                                    if ($seq_index > 0) {
                                        $ex_b .= '<span class="inputother">';
                                        for ($ip = 0; $ip < $seq_index; $ip++) {
                                            if (!$input_glyphs[$ip]) {
                                                $ex_b .= '[*]';
                                            } else {
                                                $ex_b .= $this->format_entity_first($input_glyphs[$ip]) . '&#x200d;';
                                            }
                                        }
                                        $ex_b .= '</span>';
                                    }
                                    if (count($input_glyphs) > $seq_index + 1) {
                                        $ex_l .= '<span class="inputother">';
                                        for ($ip = $seq_index + 1; $ip < count($input_glyphs); $ip++) {
                                            if (!$input_glyphs[$ip]) {
                                                $ex_l .= '[*]';
                                            } else {
                                                $ex_l .= $this->format_entity_first($input_glyphs[$ip]) . '&#x200d;';
                                            }
                                        }
                                        $ex_l .= '</span>';
                                    }
                                    $html .= '<div class="sequenceIndex">Substitution Position: ' . $seq_index . '</div>';
                                    $lul2 = [$lup => $tag];
                                    // Only apply if the (first) 'Replace' glyph from the
                                    // Lookup list is in the [inputGlyphs] at ['SequenceIndex']
                                    // Pass $inputGlyphs[$seqIndex] e.g. 00636|00645|00656
                                    // to level 2 and only apply if first Replace glyph is in this list
                                    $html .= $this->_get_gsu_barray($Lookup, $lul2, $scripttag, 2, $input_glyphs[$seq_index], $ex_b, $ex_l);
                                }
                                if (count($sub_rule['rules'])) {
                                    $volt[] = $sub_rule;
                                }
                            }
                        }
                    } else if ($subst_format == 3) {
                        $html .= '<div class="lookuptypesub">Format 3: Coverage-based Context Glyph Substitution  </div>';
                        // IgnoreMarks flag set on main Lookup table
                        $input_glyphs = $Lookup[$i]['Subtable'][$c]['CoverageInputGlyphs'];
                        $coverage_input_glyphs = implode('|', $input_glyphs);
                        $n_input = $Lookup[$i]['Subtable'][$c]['InputGlyphCount'];
                        $example_i = [];
                        $html .= '<div class="context">CONTEXT: ';
                        for ($ff = 0; $ff < count($input_glyphs); $ff++) {
                            $html .= '<div>Input #' . $ff . ': <span class="unchanged">&nbsp;' . $this->format_entity_str($input_glyphs[$ff]) . '&nbsp;</span></div>';
                            $example_i[] = $this->format_entity_first($input_glyphs[$ff]);
                        }
                        $html .= '</div>';
                        for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['SubstCount']; $b++) {
                            $lup = $Lookup[$i]['Subtable'][$c]['SubstLookupRecord'][$b]['LookupListIndex'];
                            $seq_index = $Lookup[$i]['Subtable'][$c]['SubstLookupRecord'][$b]['SequenceIndex'];
                            // GENERATE exampleI[<seqIndex] .... exampleI[>seqIndex]
                            $ex_b = '';
                            $ex_l = '';
                            if ($seq_index > 0) {
                                $ex_b .= '<span class="inputother">';
                                for ($ip = 0; $ip < $seq_index; $ip++) {
                                    $ex_b .= $example_i[$ip] . '&#x200d;';
                                }
                                $ex_b .= '</span>';
                            }
                            if (count($input_glyphs) > $seq_index + 1) {
                                $ex_l .= '<span class="inputother">';
                                for ($ip = $seq_index + 1; $ip < count($input_glyphs); $ip++) {
                                    $ex_l .= $example_i[$ip] . '&#x200d;';
                                }
                                $ex_l .= '</span>';
                            }
                            $html .= '<div class="sequenceIndex">Substitution Position: ' . $seq_index . '</div>';
                            $lul2 = [$lup => $tag];
                            // Only apply if the (first) 'Replace' glyph from the
                            // Lookup list is in the [inputGlyphs] at ['SequenceIndex']
                            // Pass $inputGlyphs[$seqIndex] e.g. 00636|00645|00656
                            // to level 2 and only apply if first Replace glyph is in this list
                            $html .= $this->_get_gsu_barray($Lookup, $lul2, $scripttag, 2, $input_glyphs[$seq_index], $ex_b, $ex_l);
                        }
                        if (count($sub_rule['rules'])) {
                            $volt[] = $sub_rule;
                        }
                    }
                    //print_r($Lookup[$i]);
                    //print_r($volt[(count($volt)-1)]); exit;
                } else if ($Lookup[$i]['Type'] == 6) {
                    $html .= '<div class="lookuptype">LookupType 6: Chaining Contextual Substitution Subtable</div>';
                    // Format 1: Simple Chaining Context Glyph Substitution  p255
                    if ($subst_format == 1) {
                        $html .= '<div class="lookuptypesub">Format 1: Simple Chaining Context Glyph Substitution  </div>';
                        for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['ChainSubRuleSetCount']; $s++) {
                            // ChainSubRuleSet
                            $sub_rule = [];
                            $html .= '<div class="rule">Subrule Set: ' . $s . '</div>';
                            $first_input_glyph = $Lookup[$i]['Subtable'][$c]['CoverageGlyphs'][$s];
                            // First input gyyph
                            foreach ($Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRule'] as $rctr => $rule) {
                                $html .= '<div class="rule">SubRule: ' . $rctr . '</div>';
                                // ChainSubRule
                                $input_glyphs = [];
                                if ($rule['InputGlyphCount'] > 1) {
                                    $input_glyphs = $rule['InputGlyphs'];
                                }
                                $input_glyphs[0] = $first_input_glyph;
                                ksort($input_glyphs);
                                $n_input = count($input_glyphs);
                                if ($rule['BacktrackGlyphCount']) {
                                    $backtrack_glyphs = $rule['BacktrackGlyphs'];
                                } else {
                                    $backtrack_glyphs = [];
                                }
                                if ($rule['LookaheadGlyphCount']) {
                                    $lookahead_glyphs = $rule['LookaheadGlyphs'];
                                } else {
                                    $lookahead_glyphs = [];
                                }
                                $example_b = [];
                                $example_i = [];
                                $example_l = [];
                                $html .= '<div class="context">CONTEXT: ';
                                for ($ff = count($backtrack_glyphs) - 1; $ff >= 0; $ff--) {
                                    $html .= '<div>Backtrack #' . $ff . ': <span class="unicode">' . $this->format_uni_str($backtrack_glyphs[$ff]) . '</span></div>';
                                    $example_b[] = $this->format_entity_first($backtrack_glyphs[$ff]);
                                }
                                for ($ff = 0; $ff < count($input_glyphs); $ff++) {
                                    $html .= '<div>Input #' . $ff . ': <span class="unchanged">&nbsp;' . $this->format_entity_str($input_glyphs[$ff]) . '&nbsp;</span></div>';
                                    $example_i[] = $this->format_entity_first($input_glyphs[$ff]);
                                }
                                for ($ff = 0; $ff < count($lookahead_glyphs); $ff++) {
                                    $html .= '<div>Lookahead #' . $ff . ': <span class="unicode">' . $this->format_uni_str($lookahead_glyphs[$ff]) . '</span></div>';
                                    $example_l[] = $this->format_entity_first($lookahead_glyphs[$ff]);
                                }
                                $html .= '</div>';
                                for ($b = 0; $b < $rule['SubstCount']; $b++) {
                                    $lup = $rule['LookupListIndex'][$b];
                                    $seq_index = $rule['SequenceIndex'][$b];
                                    // GENERATE exampleB[n] exampleI[<seqIndex] .... exampleI[>seqIndex] exampleL[n]
                                    $ex_b = '';
                                    $ex_l = '';
                                    if (count($example_b)) {
                                        $ex_b .= '<span class="backtrack">' . implode('&#x200d;', $example_b) . '</span>';
                                    }
                                    if ($seq_index > 0) {
                                        $ex_b .= '<span class="inputother">';
                                        for ($ip = 0; $ip < $seq_index; $ip++) {
                                            $ex_b .= $this->format_entity($input_glyphs[$ip]) . '&#x200d;';
                                        }
                                        $ex_b .= '</span>';
                                    }
                                    if (count($input_glyphs) > $seq_index + 1) {
                                        $ex_l .= '<span class="inputother">';
                                        for ($ip = $seq_index + 1; $ip < count($input_glyphs); $ip++) {
                                            $ex_l .= $this->format_entity($input_glyphs[$ip]) . '&#x200d;';
                                        }
                                        $ex_l .= '</span>';
                                    }
                                    if (count($example_l)) {
                                        $ex_l .= '<span class="lookahead">' . implode('&#x200d;', $example_l) . '</span>';
                                    }
                                    $html .= '<div class="sequenceIndex">Substitution Position: ' . $seq_index . '</div>';
                                    $lul2 = [$lup => $tag];
                                    // Only apply if the (first) 'Replace' glyph from the
                                    // Lookup list is in the [inputGlyphs] at ['SequenceIndex']
                                    // Pass $inputGlyphs[$seqIndex] e.g. 00636|00645|00656
                                    // to level 2 and only apply if first Replace glyph is in this list
                                    $html .= $this->_get_gsu_barray($Lookup, $lul2, $scripttag, 2, $input_glyphs[$seq_index], $ex_b, $ex_l);
                                }
                                if (count($sub_rule['rules'])) {
                                    $volt[] = $sub_rule;
                                }
                            }
                        }
                    } else if ($subst_format == 2) {
                        $html .= '<div class="lookuptypesub">Format 2: Class-based Chaining Context Glyph Substitution  </div>';
                        foreach ($Lookup[$i]['Subtable'][$c]['ChainSubClassSet'] as $input_class => $cscs) {
                            $html .= '<div class="rule">Input Class: ' . $input_class . '</div>';
                            for ($cscrule = 0; $cscrule < $cscs['ChainSubClassRuleCnt']; $cscrule++) {
                                $html .= '<div class="rule">Rule: ' . $cscrule . '</div>';
                                $rule = $cscs['ChainSubClassRule'][$cscrule];
                                // These contain classes of glyphs as strings
                                // $Lookup[$i]['Subtable'][$c]['InputClasses'][(class)] e.g. 02E6|02E7|02E8
                                // $Lookup[$i]['Subtable'][$c]['LookaheadClasses'][(class)]
                                // $Lookup[$i]['Subtable'][$c]['BacktrackClasses'][(class)]
                                // These contain arrays of classIndexes
                                // [Backtrack] [Lookahead] and [Input] (Input is from the second position only)
                                $input_glyphs = [];
                                $input_glyphs[0] = $Lookup[$i]['Subtable'][$c]['InputClasses'][$input_class];
                                if ($rule['InputGlyphCount'] > 1) {
                                    //  NB starts at 1
                                    for ($gcl = 1; $gcl < $rule['InputGlyphCount']; $gcl++) {
                                        $classindex = $rule['Input'][$gcl];
                                        $input_glyphs[$gcl] = $Lookup[$i]['Subtable'][$c]['InputClasses'][$classindex];
                                    }
                                }
                                // Class 0 contains all the glyphs NOT in the other classes
                                $class0excl = implode('|', $Lookup[$i]['Subtable'][$c]['InputClasses']);
                                $n_input = $rule['InputGlyphCount'];
                                if ($rule['BacktrackGlyphCount']) {
                                    for ($gcl = 0; $gcl < $rule['BacktrackGlyphCount']; $gcl++) {
                                        $classindex = $rule['Backtrack'][$gcl];
                                        $backtrack_glyphs[$gcl] = $Lookup[$i]['Subtable'][$c]['BacktrackClasses'][$classindex];
                                    }
                                } else {
                                    $backtrack_glyphs = [];
                                }
                                if ($rule['LookaheadGlyphCount']) {
                                    for ($gcl = 0; $gcl < $rule['LookaheadGlyphCount']; $gcl++) {
                                        $classindex = $rule['Lookahead'][$gcl];
                                        $lookahead_glyphs[$gcl] = $Lookup[$i]['Subtable'][$c]['LookaheadClasses'][$classindex];
                                    }
                                } else {
                                    $lookahead_glyphs = [];
                                }
                                $example_b = [];
                                $example_i = [];
                                $example_l = [];
                                $html .= '<div class="context">CONTEXT: ';
                                for ($ff = count($backtrack_glyphs) - 1; $ff >= 0; $ff--) {
                                    if (!$backtrack_glyphs[$ff]) {
                                        $html .= '<div>Backtrack #' . $ff . ': <span class="unchanged">&nbsp;[NOT ' . $this->format_entity_str($class0excl) . ']&nbsp;</span></div>';
                                        $example_b[] = '[NOT ' . $this->format_entity_first($class0excl) . ']';
                                    } else {
                                        $html .= '<div>Backtrack #' . $ff . ': <span class="unicode">' . $this->format_uni_str($backtrack_glyphs[$ff]) . '</span></div>';
                                        $example_b[] = $this->format_entity_first($backtrack_glyphs[$ff]);
                                    }
                                }
                                for ($ff = 0; $ff < count($input_glyphs); $ff++) {
                                    if (!$input_glyphs[$ff]) {
                                        $html .= '<div>Input #' . $ff . ': <span class="unchanged">&nbsp;[NOT ' . $this->format_entity_str($class0excl) . ']&nbsp;</span></div>';
                                        $example_i[] = '[NOT ' . $this->format_entity_first($class0excl) . ']';
                                    } else {
                                        $html .= '<div>Input #' . $ff . ': <span class="unchanged">&nbsp;' . $this->format_entity_str($input_glyphs[$ff]) . '&nbsp;</span></div>';
                                        $example_i[] = $this->format_entity_first($input_glyphs[$ff]);
                                    }
                                }
                                for ($ff = 0; $ff < count($lookahead_glyphs); $ff++) {
                                    if (!$lookahead_glyphs[$ff]) {
                                        $html .= '<div>Lookahead #' . $ff . ': <span class="unchanged">&nbsp;[NOT ' . $this->format_entity_str($class0excl) . ']&nbsp;</span></div>';
                                        $example_l[] = '[NOT ' . $this->format_entity_first($class0excl) . ']';
                                    } else {
                                        $html .= '<div>Lookahead #' . $ff . ': <span class="unicode">' . $this->format_uni_str($lookahead_glyphs[$ff]) . '</span></div>';
                                        $example_l[] = $this->format_entity_first($lookahead_glyphs[$ff]);
                                    }
                                }
                                $html .= '</div>';
                                for ($b = 0; $b < $rule['SubstCount']; $b++) {
                                    $lup = $rule['LookupListIndex'][$b];
                                    $seq_index = $rule['SequenceIndex'][$b];
                                    // GENERATE exampleB[n] exampleI[<seqIndex] .... exampleI[>seqIndex] exampleL[n]
                                    $ex_b = '';
                                    $ex_l = '';
                                    if (count($example_b)) {
                                        $ex_b .= '<span class="backtrack">' . implode('&#x200d;', $example_b) . '</span>';
                                    }
                                    if ($seq_index > 0) {
                                        $ex_b .= '<span class="inputother">';
                                        for ($ip = 0; $ip < $seq_index; $ip++) {
                                            if (!$input_glyphs[$ip]) {
                                                $ex_b .= '[*]';
                                            } else {
                                                $ex_b .= $this->format_entity_first($input_glyphs[$ip]) . '&#x200d;';
                                            }
                                        }
                                        $ex_b .= '</span>';
                                    }
                                    if (count($input_glyphs) > $seq_index + 1) {
                                        $ex_l .= '<span class="inputother">';
                                        for ($ip = $seq_index + 1; $ip < count($input_glyphs); $ip++) {
                                            if (!$input_glyphs[$ip]) {
                                                $ex_l .= '[*]';
                                            } else {
                                                $ex_l .= $this->format_entity_first($input_glyphs[$ip]) . '&#x200d;';
                                            }
                                        }
                                        $ex_l .= '</span>';
                                    }
                                    if (count($example_l)) {
                                        $ex_l .= '<span class="lookahead">' . implode('&#x200d;', $example_l) . '</span>';
                                    }
                                    $html .= '<div class="sequenceIndex">Substitution Position: ' . $seq_index . '</div>';
                                    $lul2 = [$lup => $tag];
                                    // Only apply if the (first) 'Replace' glyph from the
                                    // Lookup list is in the [inputGlyphs] at ['SequenceIndex']
                                    // Pass $inputGlyphs[$seqIndex] e.g. 00636|00645|00656
                                    // to level 2 and only apply if first Replace glyph is in this list
                                    $html .= $this->_get_gsu_barray($Lookup, $lul2, $scripttag, 2, $input_glyphs[$seq_index], $ex_b, $ex_l);
                                }
                            }
                        }
                        //print_r($Lookup[$i]['Subtable'][$c]); exit;
                    } else if ($subst_format == 3) {
                        $html .= '<div class="lookuptypesub">Format 3: Coverage-based Chaining Context Glyph Substitution  </div>';
                        // IgnoreMarks flag set on main Lookup table
                        $input_glyphs = $Lookup[$i]['Subtable'][$c]['CoverageInputGlyphs'];
                        $coverage_input_glyphs = implode('|', $input_glyphs);
                        $n_input = $Lookup[$i]['Subtable'][$c]['InputGlyphCount'];
                        if ($Lookup[$i]['Subtable'][$c]['BacktrackGlyphCount']) {
                            $backtrack_glyphs = $Lookup[$i]['Subtable'][$c]['CoverageBacktrackGlyphs'];
                        } else {
                            $backtrack_glyphs = [];
                        }
                        if ($Lookup[$i]['Subtable'][$c]['LookaheadGlyphCount']) {
                            $lookahead_glyphs = $Lookup[$i]['Subtable'][$c]['CoverageLookaheadGlyphs'];
                        } else {
                            $lookahead_glyphs = [];
                        }
                        $example_b = [];
                        $example_i = [];
                        $example_l = [];
                        $html .= '<div class="context">CONTEXT: ';
                        for ($ff = count($backtrack_glyphs) - 1; $ff >= 0; $ff--) {
                            $html .= '<div>Backtrack #' . $ff . ': <span class="unicode">' . $this->format_uni_str($backtrack_glyphs[$ff]) . '</span></div>';
                            $example_b[] = $this->format_entity_first($backtrack_glyphs[$ff]);
                        }
                        for ($ff = 0; $ff < count($input_glyphs); $ff++) {
                            $html .= '<div>Input #' . $ff . ': <span class="unchanged">&nbsp;' . $this->format_entity_str($input_glyphs[$ff]) . '&nbsp;</span></div>';
                            $example_i[] = $this->format_entity_first($input_glyphs[$ff]);
                        }
                        for ($ff = 0; $ff < count($lookahead_glyphs); $ff++) {
                            $html .= '<div>Lookahead #' . $ff . ': <span class="unicode">' . $this->format_uni_str($lookahead_glyphs[$ff]) . '</span></div>';
                            $example_l[] = $this->format_entity_first($lookahead_glyphs[$ff]);
                        }
                        $html .= '</div>';
                        for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['SubstCount']; $b++) {
                            $lup = $Lookup[$i]['Subtable'][$c]['SubstLookupRecord'][$b]['LookupListIndex'];
                            $seq_index = $Lookup[$i]['Subtable'][$c]['SubstLookupRecord'][$b]['SequenceIndex'];
                            // GENERATE exampleB[n] exampleI[<seqIndex] .... exampleI[>seqIndex] exampleL[n]
                            $ex_b = '';
                            $ex_l = '';
                            if (count($example_b)) {
                                $ex_b .= '<span class="backtrack">' . implode('&#x200d;', $example_b) . '</span>';
                            }
                            if ($seq_index > 0) {
                                $ex_b .= '<span class="inputother">';
                                for ($ip = 0; $ip < $seq_index; $ip++) {
                                    $ex_b .= $example_i[$ip] . '&#x200d;';
                                }
                                $ex_b .= '</span>';
                            }
                            if (count($input_glyphs) > $seq_index + 1) {
                                $ex_l .= '<span class="inputother">';
                                for ($ip = $seq_index + 1; $ip < count($input_glyphs); $ip++) {
                                    $ex_l .= $example_i[$ip] . '&#x200d;';
                                }
                                $ex_l .= '</span>';
                            }
                            if (count($example_l)) {
                                $ex_l .= '<span class="lookahead">' . implode('&#x200d;', $example_l) . '</span>';
                            }
                            $html .= '<div class="sequenceIndex">Substitution Position: ' . $seq_index . '</div>';
                            $lul2 = [$lup => $tag];
                            // Only apply if the (first) 'Replace' glyph from the
                            // Lookup list is in the [inputGlyphs] at ['SequenceIndex']
                            // Pass $inputGlyphs[$seqIndex] e.g. 00636|00645|00656
                            // to level 2 and only apply if first Replace glyph is in this list
                            $html .= $this->_get_gsu_barray($Lookup, $lul2, $scripttag, 2, $input_glyphs[$seq_index], $ex_b, $ex_l);
                        }
                    }
                }
            }
            $html .= '</div>';
        }
        if ($level == 1) {
            $this->mpdf->write_html($html);
        } else {
            return $html;
        }
        //print_r($Lookup); exit;
    }
    //=====================================================================================
    //=====================================================================================
    // mPDF 5.7.1
    function _check_gsu_bignore($flag, $glyph, $mark_filtering_set)
    {
        // Flag & 0x0008 = Ignore Marks
        if (($flag & 0x8) == 0x8 && strpos($this->glyph_class_marks, $glyph)) {
            return true;
        }
        if (($flag & 0x4) == 0x4 && strpos($this->glyph_class_ligatures, $glyph)) {
            return true;
        }
        if (($flag & 0x2) == 0x2 && strpos($this->glyph_class_bases, $glyph)) {
            return true;
        }
        // Flag & 0xFF?? = MarkAttachmentType
        if ($flag & 0xff00 && strpos($this->mark_attachment_type[$flag >> 8], $glyph)) {
            return true;
        }
        // Flag & 0x0010 = UseMarkFilteringSet
        if ($flag & 0x10 && strpos($this->mark_glyph_sets[$mark_filtering_set], $glyph)) {
            return true;
        }
        return false;
    }
    function _get_gsu_bignore_string($flag, $mark_filtering_set)
    {
        // If ignoreFlag set, combine all ignore glyphs into -> "((?:(?: FBA1| FBA2| FBA3))*)"
        // else "()"
        // for Input - set on secondary Lookup table if in Context, and set Backtrack and Lookahead on Context Lookup
        $str = "";
        $ignoreflag = 0;
        // Flag & 0xFF?? = MarkAttachmentType
        if ($flag & 0xff00) {
            $mark_attachment_type = $flag >> 8;
            $ignoreflag = $flag;
            //$str = $this->MarkAttachmentType[$MarkAttachmentType];
            $str = "MarkAttachmentType[" . $mark_attachment_type . "] ";
        }
        // Flag & 0x0010 = UseMarkFilteringSet
        if ($flag & 0x10) {
            throw new \Mpdf\Exception\Font_Exception("This font " . $this->fontkey . " contains MarkGlyphSets");
        }
        // If Ignore Marks set, supercedes any above
        // Flag & 0x0008 = Ignore Marks
        if (($flag & 0x8) == 0x8) {
            $ignoreflag = 8;
            //$str = $this->GlyphClassMarks;
            $str = "Mark Glyphs ";
        }
        // Flag & 0x0004 = Ignore Ligatures
        if (($flag & 0x4) == 0x4) {
            $ignoreflag += 4;
            if ($str) {
                $str .= "|";
            }
            //$str .= $this->GlyphClassLigatures;
            $str .= "Ligature Glyphs ";
        }
        // Flag & 0x0002 = Ignore BaseGlyphs
        if (($flag & 0x2) == 0x2) {
            $ignoreflag += 2;
            if ($str) {
                $str .= "|";
            }
            //$str .= $this->GlyphClassBases;
            $str .= "Base Glyphs ";
        }
        if ($str) {
            return $str;
        }
        return "";
    }
    // GSUB Patterns
    /*
     BACKTRACK                        INPUT                   LOOKAHEAD
     ==================================  ==================  ==================================
     (FEEB|FEEC)(ign) ¦(FD12|FD13)(ign) ¦(0612)¦(ign) (0613)¦(ign) (FD12|FD13)¦(ign) (FEEB|FEEC)
     ----------------  ----------------  -----  ------------  ---------------   ---------------
     Backtrack 1       Backtrack 2     Input 1   Input 2       Lookahead 1      Lookahead 2
     --------   ---    ---------  ---    ----   ---   ----   ---   ---------   ---    -------
     \${1}  \${2}     \${3}   \${4}                      \${5+}  \${6+}    \${7+}  \${8+}
    
     nBacktrack = 2               nInput = 2                 nLookahead = 2
    
     nBsubs = 2xnBack          nIsubs = (nBsubs+)    nLsubs = (nBsubs+nIsubs+) 2xnLookahead
     "\${1}\${2} "                 (nInput*2)-1               "\${5+} \${6+}"
     "REPL"
    
     ¦\${1}\${2} ¦\${3}\${4} ¦REPL¦\${5+} \${6+}¦\${7+} \${8+}¦
    
    
     INPUT nInput = 5
     ============================================================
     ¦(0612)¦(ign) (0613)¦(ign) (0614)¦(ign) (0615)¦(ign) (0615)¦
     \${1}  \${2}  \${3}  \${4} \${5} \${6}  \${7} \${8}  \${9} (All backreference numbers are + nBsubs)
     -----  ------------ ------------ ------------ ------------
     Input 1   Input 2      Input 3      Input 4      Input 5
    
     A======  SequenceIndex=1 ; Lookup match nGlyphs=1
     B===================  SequenceIndex=1 ; Lookup match nGlyphs=2
     C===============================  SequenceIndex=1 ; Lookup match nGlyphs=3
     D=======================  SequenceIndex=2 ; Lookup match nGlyphs=2
     E=====================================  SequenceIndex=2 ; Lookup match nGlyphs=3
     F======================  SequenceIndex=4 ; Lookup match nGlyphs=2
    
     All backreference numbers are + nBsubs
     A - "REPL\${2} \${3}\${4} \${5}\${6} \${7}\${8} \${9}"
     B - "REPL\${2}\${4} \${5}\${6} \${7}\${8} \${9}"
     C - "REPL\${2}\${4}\${6} \${7}\${8} \${9}"
     D - "\${1} REPL\${2}\${4}\${6} \${7}\${8} \${9}"
     E - "\${1} REPL\${2}\${4}\${6}\${8} \${9}"
     F - "\${1}\${2} \${3}\${4} \${5} REPL\${6}\${8}"
    */
    function _make_gsu_bcontext_input_match($input_glyphs, $ignore, array $lookup_glyphs, $seq_index)
    {
        // $ignore = "((?:(?: FBA1| FBA2| FBA3))*)" or "()"
        // Returns e.g. ¦(0612)¦(ignore) (0613)¦(ignore) (0614)¦
        // $inputGlyphs = array of glyphs(glyphstrings) making up Input sequence in Context
        // $lookupGlyphs = array of glyphs (single Glyphs) making up Lookup Input sequence
        $m_len = count($lookup_glyphs);
        // nGlyphs in the secondary Lookup match
        $n_input = count($input_glyphs);
        // nGlyphs in the Primary Input sequence
        $str = "";
        for ($i = 0; $i < $n_input; $i++) {
            if ($i > 0) {
                $str .= $ignore . " ";
            }
            if ($i >= $seq_index && $i < $seq_index + $m_len) {
                $str .= "" . $lookup_glyphs[$i - $seq_index] . "";
            } else {
                $str .= "" . $input_glyphs[$i] . "";
            }
        }
        return $str;
    }
    function _make_gsu_binput_match($input_glyphs, $ignore)
    {
        // $ignore = "((?:(?: FBA1| FBA2| FBA3))*)" or "()"
        // Returns e.g. ¦(0612)¦(ignore) (0613)¦(ignore) (0614)¦
        // $inputGlyphs = array of glyphs(glyphstrings) making up Input sequence in Context
        // $lookupGlyphs = array of glyphs making up Lookup Input sequence - if applicable
        $str = "";
        for ($i = 1; $i <= count($input_glyphs); $i++) {
            if ($i > 1) {
                $str .= $ignore . " ";
            }
            $str .= "" . $input_glyphs[$i - 1] . "";
        }
        return $str;
    }
    function _make_gsu_bbacktrack_match($backtrack_glyphs, $ignore)
    {
        // $ignore = "((?:(?: FBA1| FBA2| FBA3))*)" or "()"
        // Returns e.g. ¦(FEEB|FEEC)(ignore) ¦(FD12|FD13)(ignore) ¦
        // $backtrackGlyphs = array of glyphstrings making up Backtrack sequence
        // 3  2  1  0
        // each item being e.g. E0AD|E0AF|F1FD
        $str = "";
        for ($i = count($backtrack_glyphs) - 1; $i >= 0; $i--) {
            $str .= "" . $backtrack_glyphs[$i] . " " . $ignore . " ";
        }
        return $str;
    }
    function _make_gsu_blookahead_match($lookahead_glyphs, $ignore)
    {
        // $ignore = "((?:(?: FBA1| FBA2| FBA3))*)" or "()"
        // Returns e.g. ¦(ignore) (FD12|FD13)¦(ignore) (FEEB|FEEC)¦
        // $lookaheadGlyphs = array of glyphstrings making up Lookahead sequence
        // 0  1  2  3
        // each item being e.g. E0AD|E0AF|F1FD
        $str = "";
        for ($i = 0; $i < count($lookahead_glyphs); $i++) {
            $str .= $ignore . " " . $lookahead_glyphs[$i] . "";
        }
        return $str;
    }
    function _make_gsu_binput_replacement($n_input, $REPL, $ignore, $n_bsubs, $m_len, $seq_index)
    {
        // Returns e.g. "REPL\${6}\${8}" or "\${1}\${2} \${3} REPL\${4}\${6}\${8} \${9}"
        // $nInput	nGlyphs in the Primary Input sequence
        // $REPL 	replacement glyphs from secondary lookup
        // $ignore = "((?:(?: FBA1| FBA2| FBA3))*)" or "()"
        // $nBsubs	Number of Backtrack substitutions (= 2x Number of Backtrack glyphs)
        // $mLen 	nGlyphs in the secondary Lookup match - if no secondary lookup, should=$nInput
        // $seqIndex	Sequence Index to apply the secondary match
        if ($ignore == "()") {
            $ign = false;
        } else {
            $ign = true;
        }
        $str = "";
        if ($n_input == 1) {
            $str = $REPL;
        } else if ($n_input > 1) {
            if ($m_len == $n_input) {
                // whole string replaced
                $str = $REPL;
                if ($ign) {
                    // for every nInput over 1, add another replacement backreference, to move IGNORES after replacement
                    for ($x = 2; $x <= $n_input; $x++) {
                        $str .= '\\' . ($n_bsubs + 2 * ($x - 1));
                    }
                }
            } else {
                // if only part of string replaced:
                for ($x = 1; $x < $seq_index + 1; $x++) {
                    if ($x == 1) {
                        $str .= '\\' . ($n_bsubs + 1);
                    } else {
                        if ($ign) {
                            $str .= '\\' . ($n_bsubs + 2 * ($x - 1));
                        }
                        $str .= ' \\' . ($n_bsubs + 1 + 2 * ($x - 1));
                    }
                }
                if ($seq_index > 0) {
                    $str .= " ";
                }
                $str .= $REPL;
                if ($ign) {
                    for ($x = max($seq_index + 1, 2); $x < $seq_index + 1 + $m_len; $x++) {
                        //  move IGNORES after replacement
                        $str .= '\\' . ($n_bsubs + 2 * ($x - 1));
                    }
                }
                for ($x = $seq_index + 1 + $m_len; $x <= $n_input; $x++) {
                    if ($ign) {
                        $str .= '\\' . ($n_bsubs + 2 * ($x - 1));
                    }
                    $str .= ' \\' . ($n_bsubs + 1 + 2 * ($x - 1));
                }
            }
        }
        return $str;
    }
    //////////////////////////////////////////////////////////////////////////////////
    function _get_coverage($convert2hex = true)
    {
        $g = [];
        $coverage_format = $this->read_ushort();
        if ($coverage_format == 1) {
            $coverage_glyph_count = $this->read_ushort();
            for ($gid = 0; $gid < $coverage_glyph_count; $gid++) {
                $glyph_id = $this->read_ushort();
                if ($convert2hex) {
                    $g[] = unicode_hex($this->glyph_to_char[$glyph_id][0]);
                } else {
                    $g[] = $glyph_id;
                }
            }
        }
        if ($coverage_format == 2) {
            $range_count = $this->read_ushort();
            for ($r = 0; $r < $range_count; $r++) {
                $start = $this->read_ushort();
                $end = $this->read_ushort();
                $start_coverage_index = $this->read_ushort();
                // n/a
                for ($gid = $start; $gid <= $end; $gid++) {
                    $glyph_id = $gid;
                    if ($convert2hex) {
                        $g[] = unicode_hex($this->glyph_to_char[$glyph_id][0]);
                    } else {
                        $g[] = $glyph_id;
                    }
                }
            }
        }
        return $g;
    }
    //////////////////////////////////////////////////////////////////////////////////
    function _get_classes($offset)
    {
        $this->seek($offset);
        $class_format = $this->read_ushort();
        $glyph_by_class = [];
        if ($class_format == 1) {
            $start_glyph = $this->read_ushort();
            $glyph_count = $this->read_ushort();
            for ($i = 0; $i < $glyph_count; $i++) {
                $start_glyph_id = $start_glyph + $i;
                $end_glyph_id = $start_glyph + $i;
                $class = $this->read_ushort();
                for ($g = $start_glyph_id; $g <= $end_glyph_id; $g++) {
                    if ($this->glyph_to_char[$g][0]) {
                        $glyph_by_class[$class][] = unicode_hex($this->glyph_to_char[$g][0]);
                    }
                }
            }
        } else if ($class_format == 2) {
            $table_count = $this->read_ushort();
            for ($i = 0; $i < $table_count; $i++) {
                $start_glyph_id = $this->read_ushort();
                $end_glyph_id = $this->read_ushort();
                $class = $this->read_ushort();
                for ($g = $start_glyph_id; $g <= $end_glyph_id; $g++) {
                    if ($this->glyph_to_char[$g][0]) {
                        $glyph_by_class[$class][] = unicode_hex($this->glyph_to_char[$g][0]);
                    }
                }
            }
        }
        $gbc = [];
        foreach ($glyph_by_class as $class => $garr) {
            $gbc[$class] = implode('|', $garr);
        }
        return $gbc;
    }
    //////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////////////
    function _get_gpo_stables()
    {
        ///////////////////////////////////
        // GPOS - Glyph Positioning
        ///////////////////////////////////
        if (isset($this->tables["GPOS"])) {
            $this->mpdf->write_html('<h1>GPOS Tables</h1>');
            $ffeats = [];
            $gpos_offset = $this->seek_table("GPOS");
            $this->skip(4);
            $script_list_offset = $gpos_offset + $this->read_ushort();
            $feature_list_offset = $gpos_offset + $this->read_ushort();
            $lookup_list_offset = $gpos_offset + $this->read_ushort();
            // ScriptList
            $this->seek($script_list_offset);
            $script_count = $this->read_ushort();
            for ($i = 0; $i < $script_count; $i++) {
                $script_tag = $this->read_tag();
                // = "beng", "deva" etc.
                $script_table_offset = $this->read_ushort();
                $ffeats[$script_tag] = $script_list_offset + $script_table_offset;
            }
            // Script Table
            foreach ($ffeats as $t => $o) {
                $ls = [];
                $this->seek($o);
                $def_lang_sys_offset = $this->read_ushort();
                if ($def_lang_sys_offset > 0) {
                    $ls['DFLT'] = $def_lang_sys_offset + $o;
                }
                $lang_sys_count = $this->read_ushort();
                for ($i = 0; $i < $lang_sys_count; $i++) {
                    $lang_tag = $this->read_tag();
                    // =
                    $lang_table_offset = $this->read_ushort();
                    $ls[$lang_tag] = $o + $lang_table_offset;
                }
                $ffeats[$t] = $ls;
            }
            // Get FeatureIndexList
            // LangSys Table - from first listed langsys
            foreach ($ffeats as $st => $scripts) {
                foreach ($scripts as $t => $o) {
                    $feature_index = [];
                    $langsystable_offset = $o;
                    $this->seek($langsystable_offset);
                    $look_up_order = $this->read_ushort();
                    //==NULL
                    $req_feature_index = $this->read_ushort();
                    if ($req_feature_index != 0xffff) {
                        $feature_index[] = $req_feature_index;
                    }
                    $feature_count = $this->read_ushort();
                    for ($i = 0; $i < $feature_count; $i++) {
                        $feature_index[] = $this->read_ushort();
                        // = index of feature
                    }
                    $ffeats[$st][$t] = $feature_index;
                }
            }
            //print_r($ffeats); exit;
            // Feauture List => LookupListIndex es
            $this->seek($feature_list_offset);
            $feature_count = $this->read_ushort();
            $Feature = [];
            for ($i = 0; $i < $feature_count; $i++) {
                $Feature[$i] = ['tag' => $this->read_tag()];
                $Feature[$i]['offset'] = $feature_list_offset + $this->read_ushort();
            }
            for ($i = 0; $i < $feature_count; $i++) {
                $this->seek($Feature[$i]['offset']);
                $this->read_ushort();
                // null
                $Feature[$i]['LookupCount'] = $Lookupcount = $this->read_ushort();
                $Feature[$i]['LookupListIndex'] = [];
                for ($c = 0; $c < $Lookupcount; $c++) {
                    $Feature[$i]['LookupListIndex'][] = $this->read_ushort();
                }
            }
            foreach ($ffeats as $st => $scripts) {
                foreach ($scripts as $t => $o) {
                    $feature_index = $ffeats[$st][$t];
                    foreach ($feature_index as $k => $fi) {
                        $ffeats[$st][$t][$k] = $Feature[$fi];
                    }
                }
            }
            //print_r($ffeats); exit;
            //=====================================================================================
            $gpos = [];
            $gpos_script_lang = [];
            foreach ($ffeats as $st => $scripts) {
                foreach ($scripts as $t => $langsys) {
                    $lg = [];
                    foreach ($langsys as $ft) {
                        $lg[$ft['LookupListIndex'][0]] = $ft;
                    }
                    // list of Lookups in order they need to be run i.e. order listed in Lookup table
                    ksort($lg);
                    foreach ($lg as $ft) {
                        $gpos[$st][$t][$ft['tag']] = $ft['LookupListIndex'];
                    }
                    if (!isset($gpos_script_lang[$st])) {
                        $gpos_script_lang[$st] = '';
                    }
                    $gpos_script_lang[$st] .= $t . ' ';
                }
            }
            if ($this->mode == 'summary') {
                $this->mpdf->write_html('<h3>GPOS Scripts &amp; Languages</h3>');
                $html = '';
                if (count($gpos)) {
                    foreach ($gpos as $st => $g) {
                        $html .= '<h5>' . $st . '</h5>';
                        foreach ($g as $l => $t) {
                            $html .= '<div><a href="font_dump_OTL.php?script=' . $st . '&lang=' . $l . '">' . $l . '</a></b>: ';
                            foreach ($t as $tag => $o) {
                                $html .= $tag . ' ';
                            }
                            $html .= '</div>';
                        }
                    }
                } else {
                    $html .= '<div>No entries in GPOS table.</div>';
                }
                $this->mpdf->write_html($html);
                $this->mpdf->write_html('</div>');
                return 0;
            }
            //=====================================================================================
            // Get metadata and offsets for whole Lookup List table
            $this->seek($lookup_list_offset);
            $lookup_count = $this->read_ushort();
            $Lookup = [];
            $Offsets = [];
            $subtable_count = [];
            for ($i = 0; $i < $lookup_count; $i++) {
                $Offsets[$i] = $lookup_list_offset + $this->read_ushort();
            }
            for ($i = 0; $i < $lookup_count; $i++) {
                $this->seek($Offsets[$i]);
                $Lookup[$i]['Type'] = $this->read_ushort();
                $Lookup[$i]['Flag'] = $flag = $this->read_ushort();
                $Lookup[$i]['SubtableCount'] = $subtable_count[$i] = $this->read_ushort();
                for ($c = 0; $c < $subtable_count[$i]; $c++) {
                    $Lookup[$i]['Subtables'][$c] = $Offsets[$i] + $this->read_ushort();
                }
                // MarkFilteringSet = Index (base 0) into GDEF mark glyph sets structure
                if (($flag & 0x10) == 0x10) {
                    $Lookup[$i]['MarkFilteringSet'] = $this->read_ushort();
                }
                // else { $Lookup[$i]['MarkFilteringSet'] = ''; }
                // Lookup Type 9: Extension
                if ($Lookup[$i]['Type'] == 9) {
                    // Overwrites new offset (32-bit) for each subtable, and a new lookup Type
                    for ($c = 0; $c < $subtable_count[$i]; $c++) {
                        $this->seek($Lookup[$i]['Subtables'][$c]);
                        $extension_pos_format = $this->read_ushort();
                        $type = $this->read_ushort();
                        $Lookup[$i]['Subtables'][$c] = $Lookup[$i]['Subtables'][$c] + $this->read_ulong();
                    }
                    $Lookup[$i]['Type'] = $type;
                }
            }
            //=====================================================================================
            $st = $this->mpdf->ot_lscript;
            $t = $this->mpdf->ot_llang;
            $langsys = $gpos[$st][$t];
            $lul = [];
            // array of LookupListIndexes
            $tags = [];
            // corresponding array of feature tags e.g. 'ccmp'
            if (count($langsys)) {
                foreach ($langsys as $tag => $ft) {
                    foreach ($ft as $ll) {
                        $lul[$ll] = $tag;
                    }
                }
            }
            ksort($lul);
            // Order the Lookups in the order they are in the GUSB table, regardless of Feature order
            $this->_get_gpo_sarray($Lookup, $lul, $st);
            //print_r($lul); exit;
            return [$gpos_script_lang, $gpos, $Lookup];
        }
        // end if GPOS
    }
    //////////////////////////////////////////////////////////////////////////////////
    //=====================================================================================
    //=====================================================================================
    //=====================================================================================
    /////////////////////////////////////////////////////////////////////////////////////////
    // GPOS functions
    function _get_gpo_sarray(array &$Lookup, $lul, $scripttag, $level = 1, $lcoverage = '', $ex_b = '', $ex_l = '')
    {
        // Process (3) LookupList for specific Script-LangSys
        $html = '';
        if ($level == 1) {
            $html .= '<bookmark level="0" content="GPOS features">';
        }
        foreach ($lul as $luli => $tag) {
            $html .= '<div class="level' . $level . '">';
            $html .= '<h5 class="level' . $level . '">';
            if ($level == 1) {
                $html .= '<bookmark level="1" content="' . $tag . ' [#' . $luli . ']">';
            }
            $html .= 'Lookup #' . $luli . ' [tag: <span style="color:#000066;">' . $tag . '</span>]</h5>';
            $ignore = $this->_get_gsu_bignore_string($Lookup[$luli]['Flag'], $Lookup[$luli]['MarkFilteringSet']);
            if ($ignore) {
                $html .= '<div class="ignore">Ignoring: ' . $ignore . '</div> ';
            }
            $Type = $Lookup[$luli]['Type'];
            $Flag = $Lookup[$luli]['Flag'];
            if (($Flag & 0x1) == 1) {
                $dir = 'RTL';
            } else {
                $dir = 'LTR';
            }
            for ($c = 0; $c < $Lookup[$luli]['SubtableCount']; $c++) {
                $html .= '<div class="subtable">Subtable #' . $c;
                if ($level == 1) {
                    $html .= '<bookmark level="2" content="Subtable #' . $c . '">';
                }
                $html .= '</div>';
                // Lets start
                $subtable_offset = $Lookup[$luli]['Subtables'][$c];
                $this->seek($subtable_offset);
                $pos_format = $this->read_ushort();
                ////////////////////////////////////////////////////////////////////////////////
                // LookupType 1: Single adjustment 	Adjust position of a single glyph (e.g. SmallCaps/Sups/Subs)
                ////////////////////////////////////////////////////////////////////////////////
                if ($Lookup[$luli]['Type'] == 1) {
                    $html .= '<div class="lookuptype">LookupType 1: Single adjustment [Format ' . $pos_format . ']</div>';
                    //===========
                    // Format 1:
                    //===========
                    if ($pos_format == 1) {
                        $Coverage = $subtable_offset + $this->read_ushort();
                        $value_format = $this->read_ushort();
                        $Value = $this->_get_value_record($value_format);
                        $this->seek($Coverage);
                        $glyphs = $this->_get_coverage();
                        // Array of Hex Glyphs
                        for ($g = 0; $g < count($glyphs); $g++) {
                            if ($level == 2 && strpos($lcoverage, $glyphs[$g]) === false) {
                                continue;
                            }
                            $html .= '<div class="substitution">';
                            $html .= '<span class="unicode">' . $this->format_uni($glyphs[$g]) . '&nbsp;</span> ';
                            if ($level == 2 && $ex_b) {
                                $html .= $ex_b;
                            }
                            $html .= '<span class="unchanged">&nbsp;' . $this->format_entity($glyphs[$g]) . '</span>';
                            if ($level == 2 && $ex_l) {
                                $html .= $ex_l;
                            }
                            $html .= '&nbsp; &raquo; &raquo; &nbsp;';
                            if ($level == 2 && $ex_b) {
                                $html .= $ex_b;
                            }
                            $html .= '<span class="changed" style="font-feature-settings:\'' . $tag . '\' 1;">&nbsp;' . $this->format_entity($glyphs[$g]) . '</span>';
                            if ($level == 2 && $ex_l) {
                                $html .= $ex_l;
                            }
                            $html .= ' <span class="unicode">';
                            if ($Value['XPlacement']) {
                                $html .= ' Xpl: ' . $Value['XPlacement'] . ';';
                            }
                            if ($Value['YPlacement']) {
                                $html .= ' YPl: ' . $Value['YPlacement'] . ';';
                            }
                            if ($Value['XAdvance']) {
                                $html .= ' Xadv: ' . $Value['XAdvance'];
                            }
                            $html .= '</span>';
                            $html .= '</div>';
                        }
                    } else if ($pos_format == 2) {
                        $Coverage = $subtable_offset + $this->read_ushort();
                        $value_format = $this->read_ushort();
                        $value_count = $this->read_ushort();
                        $Values = [];
                        for ($v = 0; $v < $value_count; $v++) {
                            $Values[] = $this->_get_value_record($value_format);
                        }
                        $this->seek($Coverage);
                        $glyphs = $this->_get_coverage();
                        // Array of Hex Glyphs
                        for ($g = 0; $g < count($glyphs); $g++) {
                            if ($level == 2 && strpos($lcoverage, $glyphs[$g]) === false) {
                                continue;
                            }
                            $Value = $Values[$g];
                            $html .= '<div class="substitution">';
                            $html .= '<span class="unicode">' . $this->format_uni($glyphs[$g]) . '&nbsp;</span> ';
                            if ($level == 2 && $ex_b) {
                                $html .= $ex_b;
                            }
                            $html .= '<span class="unchanged">&nbsp;' . $this->format_entity($glyphs[$g]) . '</span>';
                            if ($level == 2 && $ex_l) {
                                $html .= $ex_l;
                            }
                            $html .= '&nbsp; &raquo; &raquo; &nbsp;';
                            if ($level == 2 && $ex_b) {
                                $html .= $ex_b;
                            }
                            $html .= '<span class="changed" style="font-feature-settings:\'' . $tag . '\' 1;">&nbsp;' . $this->format_entity($glyphs[$g]) . '</span>';
                            if ($level == 2 && $ex_l) {
                                $html .= $ex_l;
                            }
                            $html .= ' <span class="unicode">';
                            if ($Value['XPlacement']) {
                                $html .= ' Xpl: ' . $Value['XPlacement'] . ';';
                            }
                            if ($Value['YPlacement']) {
                                $html .= ' YPl: ' . $Value['YPlacement'] . ';';
                            }
                            if ($Value['XAdvance']) {
                                $html .= ' Xadv: ' . $Value['XAdvance'];
                            }
                            $html .= '</span>';
                            $html .= '</div>';
                        }
                    }
                } else if ($Lookup[$luli]['Type'] == 2) {
                    $html .= '<div class="lookuptype">LookupType 2: Pair adjustment e.g. Kerning [Format ' . $pos_format . ']</div>';
                    $Coverage = $subtable_offset + $this->read_ushort();
                    $value_format1 = $this->read_ushort();
                    $value_format2 = $this->read_ushort();
                    //===========
                    // Format 1:
                    //===========
                    if ($pos_format == 1) {
                        $pair_set_count = $this->read_ushort();
                        $pair_set_offset = [];
                        for ($p = 0; $p < $pair_set_count; $p++) {
                            $pair_set_offset[] = $subtable_offset + $this->read_ushort();
                        }
                        $this->seek($Coverage);
                        $glyphs = $this->_get_coverage();
                        // Array of Hex Glyphs
                        for ($p = 0; $p < $pair_set_count; $p++) {
                            if ($level == 2 && strpos($lcoverage, $glyphs[$p]) === false) {
                                continue;
                            }
                            $this->seek($pair_set_offset[$p]);
                            // First Glyph = $glyphs[$p]
                            // Takes too long e.g. Calibri font - just list kerning pairs with this:
                            $html .= '<div class="glyphs">';
                            $html .= '<span class="unchanged">&nbsp;' . $this->format_entity($glyphs[$p]) . ' </span>';
                            //PairSet table
                            $pair_value_count = $this->read_ushort();
                            for ($pv = 0; $pv < $pair_value_count; $pv++) {
                                //PairValueRecord
                                $gid = $this->read_ushort();
                                $second_glyph = unicode_hex($this->glyph_to_char[$gid][0]);
                                $Value1 = $this->_get_value_record($value_format1);
                                $Value2 = $this->_get_value_record($value_format2);
                                // If RTL pairs, GPOS declares a XPlacement e.g. -180 for an XAdvance of -180 to take
                                // account of direction. mPDF does not need the XPlacement adjustment
                                if ($dir == 'RTL' && $Value1['XPlacement']) {
                                    $Value1['XPlacement'] -= $Value1['XAdvance'];
                                }
                                if ($value_format2) {
                                    // If RTL pairs, GPOS declares a XPlacement e.g. -180 for an XAdvance of -180 to take
                                    // account of direction. mPDF does not need the XPlacement adjustment
                                    if ($dir == 'RTL' && $Value2['XPlacement'] && $Value2['XAdvance']) {
                                        $Value2['XPlacement'] -= $Value2['XAdvance'];
                                    }
                                }
                                $html .= ' ' . $this->format_entity($second_glyph) . ' ';
                                /*
                                 $html .= '<div class="substitution">';
                                 $html .= '<span class="unicode">'.$this->formatUni($glyphs[$p]).'&nbsp;</span> ';
                                 if ($level==2 && $exB) { $html .= $exB; }
                                 $html .= '<span class="unchanged">&nbsp;'.$this->formatEntity($glyphs[$p]).$this->formatEntity($SecondGlyph).'</span>';
                                 if ($level==2 && $exL) { $html .= $exL; }
                                 $html .= '&nbsp; &raquo; &raquo; &nbsp;';
                                 if ($level==2 && $exB) { $html .= $exB; }
                                 $html .= '<span class="changed" style="font-feature-settings:\''.$tag.'\' 1;">&nbsp;'.$this->formatEntity($glyphs[$p]).$this->formatEntity($SecondGlyph).'</span>';
                                 if ($level==2 && $exL) { $html .= $exL; }
                                 $html .= ' <span class="unicode">';
                                 if ($Value1['XPlacement']) { $html .= ' Xpl[1]: '.$Value1['XPlacement'].';'; }
                                 if ($Value1['YPlacement']) { $html .= ' YPl[1]: '.$Value1['YPlacement'].';'; }
                                 if ($Value1['XAdvance']) { $html .= ' Xadv[1]: '.$Value1['XAdvance']; }
                                 if ($Value2['XPlacement']) { $html .= ' Xpl[2]: '.$Value2['XPlacement'].';'; }
                                 if ($Value2['YPlacement']) { $html .= ' YPl[2]: '.$Value2['YPlacement'].';'; }
                                 if ($Value2['XAdvance']) { $html .= ' Xadv[2]: '.$Value2['XAdvance']; }
                                 $html .= '</span>';
                                 $html .= '</div>';
                                */
                            }
                            $html .= '</div>';
                        }
                    } else if ($pos_format == 2) {
                        $class_def1 = $subtable_offset + $this->read_ushort();
                        $class_def2 = $subtable_offset + $this->read_ushort();
                        $Class1Count = $this->read_ushort();
                        $Class2Count = $this->read_ushort();
                        $size_of_pair = 2 * $this->count_bits($value_format1) + 2 * $this->count_bits($value_format2);
                        $size_of_value_records = $Class1Count * $Class2Count * $size_of_pair;
                        // NB Class1Count includes Class 0 even though it is not defined by $ClassDef1
                        // i.e. Class1Count = 5; Class1 will contain array(indices 1-4);
                        $Class1 = $this->_get_class_definition_table($class_def1);
                        $Class2 = $this->_get_class_definition_table($class_def2);
                        $this->seek($subtable_offset + 16);
                        for ($i = 0; $i < $Class1Count; $i++) {
                            for ($j = 0; $j < $Class2Count; $j++) {
                                $Value1 = $this->_get_value_record($value_format1);
                                $Value2 = $this->_get_value_record($value_format2);
                                // If RTL pairs, GPOS declares a XPlacement e.g. -180 for an XAdvance of -180
                                // of direction. mPDF does not need the XPlacement adjustment
                                if ($dir == 'RTL' && $Value1['XPlacement'] && $Value1['XAdvance']) {
                                    $Value1['XPlacement'] -= $Value1['XAdvance'];
                                }
                                if ($value_format2) {
                                    if ($dir == 'RTL' && $Value2['XPlacement'] && $Value2['XAdvance']) {
                                        $Value2['XPlacement'] -= $Value2['XAdvance'];
                                    }
                                }
                                for ($c1 = 0; $c1 < count($Class1[$i]); $c1++) {
                                    $first_glyph = $Class1[$i][$c1];
                                    if ($level == 2 && strpos($lcoverage, $first_glyph) === false) {
                                        continue;
                                    }
                                    for ($c2 = 0; $c2 < count($Class2[$j]); $c2++) {
                                        $second_glyph = $Class2[$j][$c2];
                                        if (!$Value1['XPlacement'] && !$Value1['YPlacement'] && !$Value1['XAdvance'] && !$Value2['XPlacement'] && !$Value2['YPlacement'] && !$Value2['XAdvance']) {
                                            continue;
                                        }
                                        $html .= '<div class="substitution">';
                                        $html .= '<span class="unicode">' . $this->format_uni($first_glyph) . '&nbsp;</span> ';
                                        if ($level == 2 && $ex_b) {
                                            $html .= $ex_b;
                                        }
                                        $html .= '<span class="unchanged">&nbsp;' . $this->format_entity($first_glyph) . $this->format_entity($second_glyph) . '</span>';
                                        if ($level == 2 && $ex_l) {
                                            $html .= $ex_l;
                                        }
                                        $html .= '&nbsp; &raquo; &raquo; &nbsp;';
                                        if ($level == 2 && $ex_b) {
                                            $html .= $ex_b;
                                        }
                                        $html .= '<span class="changed" style="font-feature-settings:\'' . $tag . '\' 1;">&nbsp;' . $this->format_entity($first_glyph) . $this->format_entity($second_glyph) . '</span>';
                                        if ($level == 2 && $ex_l) {
                                            $html .= $ex_l;
                                        }
                                        $html .= ' <span class="unicode">';
                                        if ($Value1['XPlacement']) {
                                            $html .= ' Xpl[1]: ' . $Value1['XPlacement'] . ';';
                                        }
                                        if ($Value1['YPlacement']) {
                                            $html .= ' YPl[1]: ' . $Value1['YPlacement'] . ';';
                                        }
                                        if ($Value1['XAdvance']) {
                                            $html .= ' Xadv[1]: ' . $Value1['XAdvance'];
                                        }
                                        if ($Value2['XPlacement']) {
                                            $html .= ' Xpl[2]: ' . $Value2['XPlacement'] . ';';
                                        }
                                        if ($Value2['YPlacement']) {
                                            $html .= ' YPl[2]: ' . $Value2['YPlacement'] . ';';
                                        }
                                        if ($Value2['XAdvance']) {
                                            $html .= ' Xadv[2]: ' . $Value2['XAdvance'];
                                        }
                                        $html .= '</span>';
                                        $html .= '</div>';
                                    }
                                }
                            }
                        }
                    }
                } else if ($Lookup[$luli]['Type'] == 3) {
                    $html .= '<div class="lookuptype">LookupType 3: Cursive attachment </div>';
                    $Coverage = $subtable_offset + $this->read_ushort();
                    $entry_exit_count = $this->read_ushort();
                    $entry_anchors = [];
                    $exit_anchors = [];
                    for ($i = 0; $i < $entry_exit_count; $i++) {
                        $entry_anchors[$i] = $this->read_ushort();
                        $exit_anchors[$i] = $this->read_ushort();
                    }
                    $this->seek($Coverage);
                    $Glyphs = $this->_get_coverage();
                    for ($i = 0; $i < $entry_exit_count; $i++) {
                        // Need default XAdvance for glyph
                        $pdf_width = $this->mpdf->_get_char_width($this->mpdf->fonts[$this->fontkey]['cw'], hexdec($Glyphs[$i]));
                        $entry_anchor = $entry_anchors[$i];
                        $exit_anchor = $exit_anchors[$i];
                        $html .= '<div class="glyphs">';
                        $html .= '<span class="unchanged">' . $this->format_entity($Glyphs[$i]) . ' </span> ';
                        $html .= '<span class="unicode"> ' . $this->format_uni($Glyphs[$i]) . ' => ';
                        if ($entry_anchor != 0) {
                            $entry_anchor += $subtable_offset;
                            list($x, $y) = $this->_get_anchor_table($entry_anchor);
                            if ($dir == 'RTL') {
                                if (round($pdf_width) == round($x * 1000 / $this->mpdf->fonts[$this->fontkey]['desc']['unitsPerEm'])) {
                                    $x = 0;
                                } else {
                                    $x = $x - $pdf_width * $this->mpdf->fonts[$this->fontkey]['desc']['unitsPerEm'] / 1000;
                                }
                            }
                            $html .= " Entry X: " . $x . " Y: " . $y . "; ";
                        }
                        if ($exit_anchor != 0) {
                            $exit_anchor += $subtable_offset;
                            list($x, $y) = $this->_get_anchor_table($exit_anchor);
                            if ($dir == 'LTR') {
                                if (round($pdf_width) == round($x * 1000 / $this->mpdf->fonts[$this->fontkey]['desc']['unitsPerEm'])) {
                                    $x = 0;
                                } else {
                                    $x = $x - $pdf_width * $this->mpdf->fonts[$this->fontkey]['desc']['unitsPerEm'] / 1000;
                                }
                            }
                            $html .= " Exit X: " . $x . " Y: " . $y . "; ";
                        }
                        $html .= '</span></div>';
                    }
                } else if ($Lookup[$luli]['Type'] == 4) {
                    $html .= '<div class="lookuptype">LookupType 4: MarkToBase attachment </div>';
                    $mark_coverage = $subtable_offset + $this->read_ushort();
                    $base_coverage = $subtable_offset + $this->read_ushort();
                    $this->seek($mark_coverage);
                    $mark_glyphs = $this->_get_coverage();
                    $this->seek($base_coverage);
                    $base_glyphs = $this->_get_coverage();
                    $first_mark = '';
                    $html .= '<div class="glyphs">Marks: ';
                    for ($i = 0; $i < count($mark_glyphs); $i++) {
                        if ($level == 2 && strpos($lcoverage, $mark_glyphs[$i]) === false) {
                            continue;
                        }
                        if (!$first_mark) {
                            $first_mark = $mark_glyphs[$i];
                        }
                        $html .= ' ' . $this->format_entity($mark_glyphs[$i]) . ' ';
                    }
                    $html .= '</div>';
                    if (!$first_mark) {
                        return;
                    }
                    $html .= '<div class="glyphs">Bases: ';
                    for ($j = 0; $j < count($base_glyphs); $j++) {
                        $html .= ' ' . $this->format_entity($base_glyphs[$j]) . ' ';
                    }
                    $html .= '</div>';
                    // Example
                    $html .= '<div class="glyphs" style="font-feature-settings:\'' . $tag . '\' 1;">Example(s): ';
                    for ($j = 0; $j < min(count($base_glyphs), 20); $j++) {
                        $html .= ' ' . $this->format_entity($base_glyphs[$j]) . $this->format_entity($first_mark, true) . ' &nbsp; ';
                    }
                    $html .= '</div>';
                } else if ($Lookup[$luli]['Type'] == 5) {
                    $html .= '<div class="lookuptype">LookupType 5: MarkToLigature attachment </div>';
                    $mark_coverage = $subtable_offset + $this->read_ushort();
                    //$MarkCoverage is already set in $lcoverage 00065|00073 etc
                    $ligature_coverage = $subtable_offset + $this->read_ushort();
                    $class_count = $this->read_ushort();
                    // Number of classes defined for marks = Number of mark glyphs in the MarkCoverage table
                    $mark_array = $subtable_offset + $this->read_ushort();
                    // Offset to MarkArray table
                    $ligature_array = $subtable_offset + $this->read_ushort();
                    // Offset to LigatureArray table
                    $this->seek($mark_coverage);
                    $mark_glyphs = $this->_get_coverage();
                    $this->seek($ligature_coverage);
                    $ligature_glyphs = $this->_get_coverage();
                    $first_mark = '';
                    $html .= '<div class="glyphs">Marks: <span class="unchanged">';
                    $mark_record = [];
                    for ($i = 0; $i < count($mark_glyphs); $i++) {
                        if ($level == 2 && strpos($lcoverage, $mark_glyphs[$i]) === false) {
                            continue;
                        }
                        if (!$first_mark) {
                            $first_mark = $mark_glyphs[$i];
                        }
                        // Get the relevant MarkRecord
                        $mark_record[$i] = $this->_get_mark_record($mark_array, $i);
                        //Mark Class is = $MarkRecord[$i]['Class']
                        $html .= ' ' . $this->format_entity($mark_glyphs[$i]) . ' ';
                    }
                    $html .= '</span></div>';
                    if (!$first_mark) {
                        return;
                    }
                    $this->seek($ligature_array);
                    $ligature_count = $this->read_ushort();
                    $ligature_attach = [];
                    $html .= '<div class="glyphs">Ligatures: <span class="unchanged">';
                    for ($j = 0; $j < count($ligature_glyphs); $j++) {
                        // Get the relevant LigatureRecord
                        $ligature_attach[$j] = $ligature_array + $this->read_ushort();
                        $html .= ' ' . $this->format_entity($ligature_glyphs[$j]) . ' ';
                    }
                    $html .= '</span></div>';
                    /*
                     for ($i=0;$i<count($MarkGlyphs);$i++) {
                     $html .= '<div class="glyphs">';
                     $html .= '<span class="unchanged">'.$this->formatEntity($MarkGlyphs[$i]).'</span>';
                    
                     for ($j=0;$j<count($LigatureGlyphs);$j++) {
                     $this->seek($LigatureAttach[$j]);
                     $ComponentCount = $this->read_ushort();
                     $html .= '<span class="unchanged">'.$this->formatEntity($LigatureGlyphs[$j]).'</span>';
                     $offsets = array();
                     for ($comp=0;$comp<$ComponentCount;$comp++) {
                     // ComponentRecords
                     for ($class=0;$class<$ClassCount;$class++) {
                     $offset = $this->read_ushort();
                     if ($offset!= 0 && $class == $MarkRecord[$i]['Class']) {
                    
                     $html .= ' ['.$comp.'] ';
                    
                     }
                     }
                     }
                     }
                     $html .= '</span></div>';
                     }
                    */
                } else if ($Lookup[$luli]['Type'] == 6) {
                    $html .= '<div class="lookuptype">LookupType 6: MarkToMark attachment </div>';
                    $Mark1Coverage = $subtable_offset + $this->read_ushort();
                    // Combining Mark
                    //$Mark1Coverage is already set in $LuCoverage 0065|0073 etc
                    $Mark2Coverage = $subtable_offset + $this->read_ushort();
                    // Base Mark
                    $class_count = $this->read_ushort();
                    // Number of classes defined for marks = No. of Combining mark1 glyphs in the MarkCoverage table
                    $this->seek($Mark1Coverage);
                    $Mark1Glyphs = $this->_get_coverage();
                    $this->seek($Mark2Coverage);
                    $Mark2Glyphs = $this->_get_coverage();
                    $first_mark = '';
                    $html .= '<div class="glyphs">Marks: <span class="unchanged">';
                    for ($i = 0; $i < count($Mark1Glyphs); $i++) {
                        if ($level == 2 && strpos($lcoverage, $Mark1Glyphs[$i]) === false) {
                            continue;
                        }
                        if (!$first_mark) {
                            $first_mark = $Mark1Glyphs[$i];
                        }
                        $html .= ' ' . $this->format_entity($Mark1Glyphs[$i]) . ' ';
                    }
                    $html .= '</span></div>';
                    if ($first_mark) {
                        $html .= '<div class="glyphs">Bases: <span class="unchanged">';
                        for ($j = 0; $j < count($Mark2Glyphs); $j++) {
                            $html .= ' ' . $this->format_entity($Mark2Glyphs[$j]) . ' ';
                        }
                        $html .= '</span></div>';
                        // Example
                        $html .= '<div class="glyphs" style="font-feature-settings:\'' . $tag . '\' 1;">Example(s): <span class="changed">';
                        for ($j = 0; $j < min(count($Mark2Glyphs), 20); $j++) {
                            $html .= ' ' . $this->format_entity($Mark2Glyphs[$j]) . $this->format_entity($first_mark, true) . ' &nbsp; ';
                        }
                        $html .= '</span></div>';
                    }
                } else {
                    if ($Lookup[$luli]['Type'] == 7) {
                        $html .= '<div class="lookuptype">LookupType 7: Context positioning [Format ' . $pos_format . ']</div>';
                        //===========
                        // Format 1:
                        //===========
                        if ($pos_format == 1) {
                            throw new \Mpdf\Exception\Font_Exception("GPOS Lookup Type " . $Type . " Format " . $pos_format . " not YET TESTED.");
                        }
                        if ($pos_format == 2) {
                            throw new \Mpdf\Exception\Font_Exception("GPOS Lookup Type " . $Type . " Format " . $pos_format . " not YET TESTED.");
                        }
                        if ($pos_format == 3) {
                            throw new \Mpdf\Exception\Font_Exception("GPOS Lookup Type " . $Type . " Format " . $pos_format . " not YET TESTED.");
                        }
                        throw new \Mpdf\Exception\Font_Exception("GPOS Lookup Type " . $Type . ", Format " . $pos_format . " not supported.");
                    }
                    if ($Lookup[$luli]['Type'] == 8) {
                        $html .= '<div class="lookuptype">LookupType 8: Chained Context positioning [Format ' . $pos_format . ']</div>';
                        //===========
                        // Format 1:
                        //===========
                        if ($pos_format == 1) {
                            throw new \Mpdf\Exception\Font_Exception("GPOS Lookup Type " . $Type . " Format " . $pos_format . " not TESTED YET.");
                        }
                        if ($pos_format == 2) {
                            $html .= '<div>GPOS Lookup Type 8: Format 2 not yet supported in OTL dump</div>';
                            continue;
                        }
                        if ($pos_format == 3) {
                            $backtrack_glyph_count = $this->read_ushort();
                            $coverage_backtrack_offset = [];
                            for ($b = 0; $b < $backtrack_glyph_count; $b++) {
                                $coverage_backtrack_offset[] = $subtable_offset + $this->read_ushort();
                                // in glyph sequence order
                            }
                            $input_glyph_count = $this->read_ushort();
                            $coverage_input_offset = [];
                            for ($b = 0; $b < $input_glyph_count; $b++) {
                                $coverage_input_offset[] = $subtable_offset + $this->read_ushort();
                                // in glyph sequence order
                            }
                            $lookahead_glyph_count = $this->read_ushort();
                            $coverage_lookahead_offset = [];
                            for ($b = 0; $b < $lookahead_glyph_count; $b++) {
                                $coverage_lookahead_offset[] = $subtable_offset + $this->read_ushort();
                                // in glyph sequence order
                            }
                            $pos_count = $this->read_ushort();
                            $pos_lookup_record = [];
                            for ($p = 0; $p < $pos_count; $p++) {
                                // PosLookupRecord
                                $pos_lookup_record[$p]['SequenceIndex'] = $this->read_ushort();
                                $pos_lookup_record[$p]['LookupListIndex'] = $this->read_ushort();
                            }
                            $backtrack_glyphs = [];
                            for ($b = 0; $b < $backtrack_glyph_count; $b++) {
                                $this->seek($coverage_backtrack_offset[$b]);
                                $backtrack_glyphs[$b] = implode('|', $this->_get_coverage());
                            }
                            $input_glyphs = [];
                            for ($b = 0; $b < $input_glyph_count; $b++) {
                                $this->seek($coverage_input_offset[$b]);
                                $input_glyphs[$b] = implode('|', $this->_get_coverage());
                            }
                            $lookahead_glyphs = [];
                            for ($b = 0; $b < $lookahead_glyph_count; $b++) {
                                $this->seek($coverage_lookahead_offset[$b]);
                                $lookahead_glyphs[$b] = implode('|', $this->_get_coverage());
                            }
                            $example_b = [];
                            $example_i = [];
                            $example_l = [];
                            $html .= '<div class="context">CONTEXT: ';
                            for ($ff = count($backtrack_glyphs) - 1; $ff >= 0; $ff--) {
                                $html .= '<div>Backtrack #' . $ff . ': <span class="unicode">' . $this->format_uni_str($backtrack_glyphs[$ff]) . '</span></div>';
                                $example_b[] = $this->format_entity_first($backtrack_glyphs[$ff]);
                            }
                            for ($ff = 0; $ff < count($input_glyphs); $ff++) {
                                $html .= '<div>Input #' . $ff . ': <span class="unchanged">&nbsp;' . $this->format_entity_str($input_glyphs[$ff]) . '&nbsp;</span></div>';
                                $example_i[] = $this->format_entity_first($input_glyphs[$ff]);
                            }
                            for ($ff = 0; $ff < count($lookahead_glyphs); $ff++) {
                                $html .= '<div>Lookahead #' . $ff . ': <span class="unicode">' . $this->format_uni_str($lookahead_glyphs[$ff]) . '</span></div>';
                                $example_l[] = $this->format_entity_first($lookahead_glyphs[$ff]);
                            }
                            $html .= '</div>';
                            for ($p = 0; $p < $pos_count; $p++) {
                                $lup = $pos_lookup_record[$p]['LookupListIndex'];
                                $seq_index = $pos_lookup_record[$p]['SequenceIndex'];
                                // GENERATE exampleB[n] exampleI[<seqIndex] .... exampleI[>seqIndex] exampleL[n]
                                $ex_b = '';
                                $ex_l = '';
                                if (count($example_b)) {
                                    $ex_b .= '<span class="backtrack">' . implode('&#x200d;', $example_b) . '</span>';
                                }
                                if ($seq_index > 0) {
                                    $ex_b .= '<span class="inputother">';
                                    for ($ip = 0; $ip < $seq_index; $ip++) {
                                        $ex_b .= $example_i[$ip] . '&#x200d;';
                                    }
                                    $ex_b .= '</span>';
                                }
                                if (count($input_glyphs) > $seq_index + 1) {
                                    $ex_l .= '<span class="inputother">';
                                    for ($ip = $seq_index + 1; $ip < count($input_glyphs); $ip++) {
                                        $ex_l .= '&#x200d;' . $example_i[$ip];
                                    }
                                    $ex_l .= '</span>';
                                }
                                if (count($example_l)) {
                                    $ex_l .= '<span class="lookahead">' . implode('&#x200d;', $example_l) . '</span>';
                                }
                                $html .= '<div class="sequenceIndex">Substitution Position: ' . $seq_index . '</div>';
                                $lul2 = [$lup => $tag];
                                // Only apply if the (first) 'Replace' glyph from the
                                // Lookup list is in the [inputGlyphs] at ['SequenceIndex']
                                // Pass $inputGlyphs[$seqIndex] e.g. 00636|00645|00656
                                // to level 2 and only apply if first Replace glyph is in this list
                                $html .= $this->_get_gpo_sarray($Lookup, $lul2, $scripttag, 2, $input_glyphs[$seq_index], $ex_b, $ex_l);
                            }
                        }
                    }
                }
            }
            $html .= '</div>';
        }
        if ($level == 1) {
            $this->mpdf->write_html($html);
        } else {
            return $html;
        }
        //print_r($Lookup); exit;
    }
    //=====================================================================================
    //=====================================================================================
    // GPOS FUNCTIONS
    //=====================================================================================
    function count_bits($n)
    {
        for ($c = 0; $n; $c++) {
            $n &= $n - 1;
            // clear the least significant bit set
        }
        return $c;
    }
    function _get_value_record($value_format)
    {
        // Common ValueRecord for GPOS
        // Only returns 3 possible: $vra['XPlacement'] $vra['YPlacement'] $vra['XAdvance']
        $vra = [];
        // Horizontal adjustment for placement-in design units
        if (($value_format & 0x1) == 0x1) {
            $vra['XPlacement'] = $this->read_short();
        }
        // Vertical adjustment for placement-in design units
        if (($value_format & 0x2) == 0x2) {
            $vra['YPlacement'] = $this->read_short();
        }
        // Horizontal adjustment for advance-in design units (only used for horizontal writing)
        if (($value_format & 0x4) == 0x4) {
            $vra['XAdvance'] = $this->read_short();
        }
        // Vertical adjustment for advance-in design units (only used for vertical writing)
        if (($value_format & 0x8) == 0x8) {
            $this->read_short();
        }
        // Offset to Device table for horizontal placement-measured from beginning of PosTable (may be NULL)
        if (($value_format & 0x10) == 0x10) {
            $this->read_ushort();
        }
        // Offset to Device table for vertical placement-measured from beginning of PosTable (may be NULL)
        if (($value_format & 0x20) == 0x20) {
            $this->read_ushort();
        }
        // Offset to Device table for horizontal advance-measured from beginning of PosTable (may be NULL)
        if (($value_format & 0x40) == 0x40) {
            $this->read_ushort();
        }
        // Offset to Device table for vertical advance-measured from beginning of PosTable (may be NULL)
        if (($value_format & 0x80) == 0x80) {
            $this->read_ushort();
        }
        return $vra;
    }
    function _get_anchor_table($offset = 0)
    {
        if ($offset) {
            $this->seek($offset);
        }
        $this->read_ushort();
        $x_coordinate = $this->read_short();
        $y_coordinate = $this->read_short();
        // Format 2 specifies additional link to contour point; Format 3 additional Device table
        return [$x_coordinate, $y_coordinate];
    }
    function _get_mark_record($offset, $mark_pos)
    {
        $this->seek($offset);
        $this->read_ushort();
        $this->skip($mark_pos * 4);
        $Class = $this->read_ushort();
        $mark_anchor = $offset + $this->read_ushort();
        // = Offset to anchor table
        list($x, $y) = $this->_get_anchor_table($mark_anchor);
        return ['Class' => $Class, 'AnchorX' => $x, 'AnchorY' => $y];
    }
    //////////////////////////////////////////////////////////////////////////////////
    // Recursively get composite glyph data
    function get_glyph_data($original_glyph_idx, &$maxdepth, &$depth, &$points, &$contours)
    {
        $depth++;
        $maxdepth = max($maxdepth, $depth);
        if (count($this->glyphdata[$original_glyph_idx]['compGlyphs'])) {
            foreach ($this->glyphdata[$original_glyph_idx]['compGlyphs'] as $glyph_idx) {
                $this->get_glyph_data($glyph_idx, $maxdepth, $depth, $points, $contours);
            }
        } else if ($this->glyphdata[$original_glyph_idx]['nContours'] > 0 && $depth > 0) {
            // simple
            $contours += $this->glyphdata[$original_glyph_idx]['nContours'];
            $points += $this->glyphdata[$original_glyph_idx]['nPoints'];
        }
        $depth--;
    }
    //////////////////////////////////////////////////////////////////////////////////
    // Recursively get composite glyphs
    function get_glyphs($original_glyph_idx, &$start, array &$glyph_set, array &$subsetglyphs)
    {
        $glyph_pos = $this->glyph_pos[$original_glyph_idx];
        $glyph_len = $this->glyph_pos[$original_glyph_idx + 1] - $glyph_pos;
        if (!$glyph_len) {
            return;
        }
        $this->seek($start + $glyph_pos);
        $number_of_contours = $this->read_short();
        if ($number_of_contours < 0) {
            $this->skip(8);
            $flags = Glyph_Operator::MORE;
            while ($flags & Glyph_Operator::MORE) {
                $flags = $this->read_ushort();
            }
            $glyph_idx = $this->read_ushort();
            if (!isset($glyph_set[$glyph_idx])) {
                $glyph_set[$glyph_idx] = count($subsetglyphs);
                // old glyphID to new glyphID
                $subsetglyphs[$glyph_idx] = true;
            }
            $savepos = ftell($this->fh);
            $this->get_glyphs($glyph_idx, $start, $glyph_set, $subsetglyphs);
            $this->seek($savepos);
            if ($flags & Glyph_Operator::WORDS) {
                $this->skip(4);
            } else {
                $this->skip(2);
            }
            if ($flags & Glyph_Operator::SCALE) {
                $this->skip(2);
            } else if ($flags & Glyph_Operator::XYSCALE) {
                $this->skip(4);
            } else if ($flags & Glyph_Operator::TWOBYTWO) {
                $this->skip(8);
            }
        }
    }
    //////////////////////////////////////////////////////////////////////////////////
    function get_hmtx($number_of_h_metrics, $num_glyphs, array &$glyph_to_char, $scale)
    {
        $start = $this->seek_table("hmtx");
        $aw = 0;
        $this->char_widths = str_pad('', 256 * 256 * 2, "\x00");
        if ($this->max_uni_char > 65536) {
            $this->char_widths .= str_pad('', 256 * 256 * 2, "\x00");
        }
        // Plane 1 SMP
        if ($this->max_uni_char > 131072) {
            $this->char_widths .= str_pad('', 256 * 256 * 2, "\x00");
        }
        // Plane 2 SMP
        $n_char_widths = 0;
        if ($number_of_h_metrics * 4 < $this->max_str_len_read) {
            $data = $this->get_chunk($start, $number_of_h_metrics * 4);
            $arr = unpack("n*", $data);
        } else {
            $this->seek($start);
        }
        for ($glyph = 0; $glyph < $number_of_h_metrics; $glyph++) {
            if ($number_of_h_metrics * 4 < $this->max_str_len_read) {
                $aw = $arr[$glyph * 2 + 1];
            } else {
                $aw = $this->read_ushort();
                $lsb = $this->read_ushort();
            }
            if (isset($glyph_to_char[$glyph]) || $glyph == 0) {
                if ($aw >= 1 << 15) {
                    $aw = 0;
                }
                // 1.03 Some (arabic) fonts have -ve values for width
                // although should be unsigned value - comes out as e.g. 65108 (intended -50)
                if ($glyph == 0) {
                    $this->default_width = $scale * $aw;
                    continue;
                }
                foreach ($glyph_to_char[$glyph] as $char) {
                    //$this->charWidths[$char] = intval(round($scale*$aw));
                    if ($char != 0 && $char != 65535) {
                        $w = intval(round($scale * $aw));
                        if ($w == 0) {
                            $w = 65535;
                        }
                        if ($char < 196608) {
                            $this->char_widths[$char * 2] = chr($w >> 8);
                            $this->char_widths[$char * 2 + 1] = chr($w & 0xff);
                            $n_char_widths++;
                        }
                    }
                }
            }
        }
        $this->get_chunk($start + $number_of_h_metrics * 4, $num_glyphs * 2);
        $diff = $num_glyphs - $number_of_h_metrics;
        $w = intval(round($scale * $aw));
        if ($w == 0) {
            $w = 65535;
        }
        for ($pos = 0; $pos < $diff; $pos++) {
            $glyph = $pos + $number_of_h_metrics;
            if (isset($glyph_to_char[$glyph])) {
                foreach ($glyph_to_char[$glyph] as $char) {
                    if (!($char != 0)) {
                        continue;
                    }
                    if (!($char != 65535)) {
                        continue;
                    }
                    if ($char >= 196608) {
                        continue;
                    }
                    $this->char_widths[$char * 2] = chr($w >> 8);
                    $this->char_widths[$char * 2 + 1] = chr($w & 0xff);
                    $n_char_widths++;
                }
            }
        }
        // NB 65535 is a set width of 0
        // First bytes define number of chars in font
        $this->char_widths[0] = chr($n_char_widths >> 8);
        $this->char_widths[1] = chr($n_char_widths & 0xff);
    }
    function get_h_metric($number_of_h_metrics, $gid)
    {
        $start = $this->seek_table("hmtx");
        if ($gid < $number_of_h_metrics) {
            $this->seek($start + $gid * 4);
            $hm = fread($this->fh, 4);
        } else {
            $this->seek($start + ($number_of_h_metrics - 1) * 4);
            $hm = fread($this->fh, 2);
            $this->seek($start + $number_of_h_metrics * 2 + $gid * 2);
            $hm .= fread($this->fh, 2);
        }
        return $hm;
    }
    function get_loca($index_to_loc_format, $num_glyphs)
    {
        $start = $this->seek_table('loca');
        $this->glyph_pos = [];
        if ($index_to_loc_format == 0) {
            $data = $this->get_chunk($start, $num_glyphs * 2 + 2);
            $arr = unpack("n*", $data);
            for ($n = 0; $n <= $num_glyphs; $n++) {
                $this->glyph_pos[] = $arr[$n + 1] * 2;
            }
        } else if ($index_to_loc_format == 1) {
            $data = $this->get_chunk($start, $num_glyphs * 4 + 4);
            $arr = unpack("N*", $data);
            for ($n = 0; $n <= $num_glyphs; $n++) {
                $this->glyph_pos[] = $arr[$n + 1];
            }
        } else {
            throw new \Mpdf\Exception\Font_Exception('Unknown location table format ' . $index_to_loc_format);
        }
    }
    // CMAP Format 4
    function get_cmap4($unicode_cmap_offset, array &$glyph_to_char, array &$char_to_glyph)
    {
        $this->max_uni_char = 0;
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
        // ???? was unsigned short
        $id_range_offset_start = $this->_pos;
        $id_range_offset = [];
        for ($i = 0; $i < $seg_count; $i++) {
            $id_range_offset[] = $this->read_ushort();
        }
        for ($n = 0; $n < $seg_count; $n++) {
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
                $char_to_glyph[$unichar] = $glyph;
                if ($unichar < 196608) {
                    $this->max_uni_char = max($unichar, $this->max_uni_char);
                }
                $glyph_to_char[$glyph][] = $unichar;
            }
        }
    }
    function format_uni($char)
    {
        $x = preg_replace('/^[0]*/', '', $char);
        $x = str_pad($x, 4, '0', STR_PAD_LEFT);
        $d = hexdec($x);
        if ($d > 57343 && $d < 63744 || $d > 122879 && $d < 126977) {
            $id = 'M';
        } else {
            $id = 'U';
        }
        return $id . '+' . $x;
    }
    function format_entity($char, $allowjoining = false)
    {
        $char = preg_replace('/^[0]/', '', $char);
        $x = '&#x' . $char . ';';
        if (strpos($this->glyph_class_marks, $char) !== false) {
            if (!$allowjoining) {
                $x = '&#x25cc;' . $x;
            }
        }
        return $x;
    }
    function format_uni_arr($arr)
    {
        $s = [];
        foreach ($arr as $c) {
            $x = preg_replace('/^[0]*/', '', $c);
            $d = hexdec($x);
            if ($d > 57343 && $d < 63744 || $d > 122879 && $d < 126977) {
                $id = 'M';
            } else {
                $id = 'U';
            }
            $s[] = $id . '+' . str_pad($x, 4, '0', STR_PAD_LEFT);
        }
        return implode(', ', $s);
    }
    function format_entity_arr($arr)
    {
        $s = [];
        foreach ($arr as $c) {
            $c = preg_replace('/^[0]/', '', $c);
            $x = '&#x' . $c . ';';
            if (strpos($this->glyph_class_marks, $c) !== false) {
                $x = '&#x25cc;' . $x;
            }
            $s[] = $x;
        }
        return implode(' ', $s);
        // ZWNJ? &#x200d;
    }
    function format_class_arr($arr)
    {
        $s = [];
        foreach ($arr as $c) {
            $x = preg_replace('/^[0]*/', '', $c);
            $d = hexdec($x);
            if ($d > 57343 && $d < 63744 || $d > 122879 && $d < 126977) {
                $id = 'M';
            } else {
                $id = 'U';
            }
            $s[] = $id . '+' . str_pad($x, 4, '0', STR_PAD_LEFT);
        }
        return implode(', ', $s);
    }
    function format_uni_str($str)
    {
        $s = [];
        $arr = explode('|', $str);
        foreach ($arr as $c) {
            $x = preg_replace('/^[0]*/', '', $c);
            $d = hexdec($x);
            if ($d > 57343 && $d < 63744 || $d > 122879 && $d < 126977) {
                $id = 'M';
            } else {
                $id = 'U';
            }
            $s[] = $id . '+' . str_pad($x, 4, '0', STR_PAD_LEFT);
        }
        return implode(', ', $s);
    }
    function format_entity_str($str)
    {
        $s = [];
        $arr = explode('|', $str);
        foreach ($arr as $c) {
            $c = preg_replace('/^[0]/', '', $c);
            $x = '&#x' . $c . ';';
            if (strpos($this->glyph_class_marks, $c) !== false) {
                $x = '&#x25cc;' . $x;
            }
            $s[] = $x;
        }
        return implode(' ', $s);
        // ZWNJ? &#x200d;
    }
    function format_entity_first($str)
    {
        $arr = explode('|', $str);
        $char = preg_replace('/^[0]/', '', $arr[0]);
        $x = '&#x' . $char . ';';
        if (strpos($this->glyph_class_marks, $char) !== false) {
            return '&#x25cc;' . $x;
        }
        return $x;
    }
}