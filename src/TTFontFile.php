<?php

namespace Mpdf;

use Mpdf\Fonts\Font_Cache;
use Mpdf\Fonts\Glyph_Operator;
// NOTE*** If you change the defined constants below, be sure to delete all temporary font data files in /ttfontdata/
// to force mPDF to regenerate cached font files.
if (!defined('_OTL_OLD_SPEC_COMPAT_2')) {
    define('_OTL_OLD_SPEC_COMPAT_2', true);
}
// Define the value used in the "head" table of a created TTF file
// 0x74727565 "true" for Mac
// 0x00010000 for Windows
// Either seems to work for a font embedded in a PDF file
// when read by Adobe Reader on a Windows PC(!)
if (!defined('_TTF_MAC_HEADER')) {
    define('_TTF_MAC_HEADER', false);
}
// Recalculate correct metadata/profiles when making subset fonts (not SIP/SMP)
// e.g. xMin, xMax, maxNContours
if (!defined('_RECALC_PROFILE')) {
    define('_RECALC_PROFILE', false);
}
// mPDF 5.7.1
if (!function_exists('\Mpdf\unicode_hex')) {
    function unicode_hex($unicode_dec)
    {
        return sprintf("%05s", strtoupper(dechex($unicode_dec)));
    }
}
/**
 * TTFontFile class
 *
 * This class is based on The ReportLab Open Source PDF library
 * written in Python - http://www.reportlab.com/software/opensource/
 * together with ideas from the OpenOffice source code and others.
 * This header must be retained in any redistribution or
 * modification of the file.
 *
 * @author Ian Back <ianb@bpm1.com>
 * @license LGPL
 */
class Tt_Font_File
{
    use Strict;
    private $font_cache;
    private $font_descriptor;
    var $gpos_features;
    var $gpos_lookups;
    var $gpos_script_lang;
    var $mark_attachment_type;
    var $mark_glyph_sets;
    var $glyph_class_marks;
    var $glyph_class_ligatures;
    var $glyph_class_bases;
    var $glyph_class_components;
    var $gsub_script_lang;
    var $rtl_pu_astr;
    var $fontkey;
    var $use_otl;
    var $max_uni;
    var $s_family_class;
    var $s_family_sub_class;
    var $sipset;
    var $smpset;
    var $_pos;
    var $num_tables;
    var $search_range;
    var $entry_selector;
    var $range_shift;
    var $tables;
    var $otables;
    var $filename;
    var $fh;
    var $glyph_pos;
    var $char_to_glyph;
    var $ascent;
    var $descent;
    var $line_gap;
    var $hheaascent;
    var $hheadescent;
    var $hhealine_gap;
    var $advance_width_max;
    var $typo_ascender;
    var $typo_descender;
    var $typo_line_gap;
    var $us_win_ascent;
    var $us_win_descent;
    var $strikeout_size;
    var $strikeout_position;
    var $name;
    var $family_name;
    var $style_name;
    var $full_name;
    var $unique_font_id;
    var $units_per_em;
    var $bbox;
    var $cap_height;
    var $x_height;
    var $stem_v;
    var $italic_angle;
    var $flags;
    var $underline_position;
    var $underline_thickness;
    var $char_widths;
    var $default_width;
    var $max_str_len_read;
    var $num_ttc_fonts;
    var $ttc_fonts;
    var $max_uni_char;
    var $kerninfo;
    var $haskern_gpos;
    var $hassmallcaps_gsub;
    var $code_to_glyph;
    var $glyphdata;
    var $lu_coverage;
    public $panose;
    public $version;
    public $font_revision;
    public $restricted_use;
    public $glyph_i_dto_uni;
    public $glyph_to_char;
    public $gsub_features;
    public $gsub_lookups;
    public $gs_lu_coverage;
    public function __construct(Font_Cache $font_cache, $font_descriptor)
    {
        $this->font_cache = $font_cache;
        $this->font_descriptor = $font_descriptor;
        // Maximum size of glyf table to read in as string (otherwise reads each glyph from file)
        $this->max_str_len_read = 200000;
    }
    public function get_metrics($file, $fontkey, $tt_cfont_id = 0, $debug = false, $bm_ponly = false, $use_otl = 0)
    {
        $this->use_otl = $use_otl;
        $this->fontkey = $fontkey;
        $this->filename = $file;
        $this->fh = fopen($file, 'rb');
        if (!$this->fh) {
            throw new \Mpdf\Exception\Font_Exception(sprintf('Unable to open font file "%s"', $file));
        }
        $this->_pos = 0;
        $this->char_widths = '';
        $this->glyph_pos = [];
        $this->char_to_glyph = [];
        $this->tables = [];
        $this->otables = [];
        $this->kerninfo = [];
        $this->haskern_gpos = [];
        $this->hassmallcaps_gsub = [];
        $this->ascent = 0;
        $this->descent = 0;
        $this->line_gap = 0;
        $this->hheaascent = 0;
        $this->hheadescent = 0;
        $this->hhealine_gap = 0;
        $this->x_height = 0;
        $this->cap_height = 0;
        $this->panose = [];
        $this->s_family_class = 0;
        $this->s_family_sub_class = 0;
        $this->typo_ascender = 0;
        $this->typo_descender = 0;
        $this->typo_line_gap = 0;
        $this->us_win_ascent = 0;
        $this->us_win_descent = 0;
        $this->advance_width_max = 0;
        $this->strikeout_size = 0;
        $this->strikeout_position = 0;
        $this->num_ttc_fonts = 0;
        $this->ttc_fonts = [];
        $this->version = $version = $this->read_ulong();
        $this->panose = [];
        if ($version === 0x4f54544f) {
            throw new \Mpdf\Exception\Font_Exception(sprintf('Fonts with postscript outlines are not supported (%s)', $file));
        }
        if ($version === 0x74746366 && !$tt_cfont_id) {
            throw new \Mpdf\Exception\Font_Exception(sprintf('TTCfontID for a TrueType Collection is not defined in mPDF "fontdata" configuration (%s)', $file));
        }
        if (!in_array($version, [0x10000, 0x74727565], true) && !$tt_cfont_id) {
            throw new \Mpdf\Exception\Font_Exception(sprintf('Not a TrueType font: version=%s)', $version));
        }
        if ($tt_cfont_id > 0) {
            $this->version = $version = $this->read_ulong();
            // TTC Header version now
            if (!in_array($version, [0x10000, 0x20000], true)) {
                throw new \Mpdf\Exception\Font_Exception(sprintf('Error parsing TrueType Collection: version=%s - (%s)', $version, $file));
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
        $this->extract_info($debug, $bm_ponly, $use_otl);
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
                if ($t['tag'] === 'head') {
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
            ++$yhi;
        }
        $reslo = $xlo - $ylo;
        if ($yhi > $xhi) {
            $xhi += 1 << 16;
        }
        $reshi = $xhi - $yhi;
        $reshi &= 0xffff;
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
            $lo &= 0xffff;
        }
        $hi &= 0xffff;
        return [$hi, $lo];
    }
    function get_table_pos($tag)
    {
        if (!isset($this->tables[$tag])) {
            return [0, 0];
        }
        $offset = $this->tables[$tag]['offset'];
        $length = $this->tables[$tag]['length'];
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
            $a = $a - (1 << 16);
        }
        return $a;
    }
    function unpack_short($s)
    {
        $a = (ord($s[0]) << 8) + ord($s[1]);
        if ($a & 1 << 15) {
            $a = $a - (1 << 16);
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
            ++$val;
        }
        return pack('n', $val);
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
        $data = fread($this->fh, $length);
        // fix for #1504
        // if fread is used to read from a compressed / buffered stream (e.g. phar://...)
        // the $length parameter will be ignored - fread is limited in size (usually 8192 bytes)
        // to fix this, the data length must be checked after reading. If the read was incomplete,
        // try to read the rest of the data
        $data_len = strlen($data);
        while ($data_len < $length && !feof($this->fh)) {
            $data .= fread($this->fh, $length - $data_len);
            $data_len = strlen($data);
        }
        return $data;
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
        if ($tag === 'head') {
            $data = $this->splice($data, 8, "\x00\x00\x00\x00");
        }
        $this->otables[$tag] = $data;
    }
    function get_ctg($file, $tt_cfont_id = 0, $debug = false, $use_otl = false)
    {
        // Only called if font is not to be used as embedded subset i.e. NOT called for SIP/SMP fonts
        $this->use_otl = $use_otl;
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
        $this->num_ttc_fonts = 0;
        $this->ttc_fonts = [];
        $this->skip(4);
        if ($tt_cfont_id > 0) {
            $this->version = $version = $this->read_ulong();
            // TTC Header version now
            if (!in_array($version, [0x10000, 0x20000], true)) {
                throw new \Mpdf\Exception\Font_Exception(sprintf("Error parsing TrueType Collection: version=%s (%s)", $version, $file));
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
        // cmap - Character to glyph index mapping table
        $cmap_offset = $this->seek_table('cmap');
        $this->skip(2);
        $cmap_table_count = $this->read_ushort();
        $unicode_cmap_offset = 0;
        for ($i = 0; $i < $cmap_table_count; $i++) {
            $platform_id = $this->read_ushort();
            $encoding_id = $this->read_ushort();
            $offset = $this->read_ulong();
            $save_pos = $this->_pos;
            if ($platform_id == 3 && $encoding_id == 1) {
                // Microsoft, Unicode
                $format = $this->get_ushort($cmap_offset + $offset);
                if ($format == 4) {
                    $unicode_cmap_offset = $cmap_offset + $offset;
                    break;
                }
            } elseif ($platform_id == 0) {
                // Unicode -- assume all encodings are compatible
                $format = $this->get_ushort($cmap_offset + $offset);
                if ($format == 4) {
                    $unicode_cmap_offset = $cmap_offset + $offset;
                    break;
                }
            }
            $this->seek($save_pos);
        }
        $glyph_to_char = [];
        $char_to_glyph = [];
        $this->get_cmap4($unicode_cmap_offset, $glyph_to_char, $char_to_glyph);
        // Map Unmapped glyphs - from $numGlyphs
        if ($use_otl) {
            $this->seek_table("maxp");
            $this->skip(4);
            $num_glyphs = $this->read_ushort();
            $bctr = 0xe000;
            for ($gid = 1; $gid < $num_glyphs; $gid++) {
                if (!isset($glyph_to_char[$gid])) {
                    while (isset($char_to_glyph[$bctr])) {
                        $bctr++;
                    }
                    // Avoid overwriting a glyph already mapped in PUA
                    if ($bctr > 0xf8ff) {
                        throw new \Mpdf\Exception\Font_Exception(sprintf('Font "%s" cannot map all included glyphs into Private Use Area U+E000-U+F8FF; cannot use useOTL on this font', $file));
                    }
                    $glyph_to_char[$gid][] = $bctr;
                    $char_to_glyph[$bctr] = $gid;
                    $bctr++;
                }
            }
        }
        fclose($this->fh);
        return $char_to_glyph;
    }
    function get_ttc_fonts($file)
    {
        $this->filename = $file;
        $this->fh = fopen($file, 'rb');
        if (!$this->fh) {
            throw new \Mpdf\Exception\Font_Exception(sprintf('Unable to open file "%s"', $file));
        }
        $this->num_ttc_fonts = 0;
        $this->ttc_fonts = [];
        $this->version = $version = $this->read_ulong();
        if ($version === 0x74746366) {
            $this->version = $version = $this->read_ulong();
            // TTC Header version now
            if (!in_array($version, [0x10000, 0x20000], true)) {
                throw new \Mpdf\Exception\Font_Exception(sprintf("Error parsing TrueType Collection: version=%s (%s)", $version, $file));
            }
        } else {
            throw new \Mpdf\Exception\Font_Exception(sprintf("Not a TrueType Collection: version=%s (%s)", $version, $file));
        }
        $this->num_ttc_fonts = $this->read_ulong();
        for ($i = 1; $i <= $this->num_ttc_fonts; $i++) {
            $this->ttc_fonts[$i]['offset'] = $this->read_ulong();
        }
    }
    function extract_info($debug = false, $bm_ponly = false, $use_otl = 0)
    {
        // Values are all set to 0 or blank at start of getMetrics
        // name - Naming table
        $name_offset = $this->seek_table("name");
        $format = $this->read_ushort();
        if ($format != 0 && $format != 1) {
            throw new \Mpdf\Exception\Font_Exception("Error loading font: Unknown name table format {$format} for font {$this->filename}");
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
                    throw new \Mpdf\Exception\Font_Exception("Error loading font: PostScript name is UTF-16BE string of odd length for font {$this->filename}");
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
            $ps_name = $names[6];
        } elseif ($names[4]) {
            $ps_name = preg_replace('/ /', '-', $names[4]);
        } elseif ($names[1]) {
            $ps_name = preg_replace('/ /', '-', $names[1]);
        } else {
            $ps_name = '';
        }
        if (!$ps_name) {
            throw new \Mpdf\Exception\Font_Exception("Error loading font: Could not find PostScript font name '{$this->filename}'");
        }
        // CHECK IF psName valid (PadaukBook contains illegal characters in Name ID 6 i.e. Postscript Name)
        $ps_name_invalid = false;
        $name_length = strlen($ps_name);
        for ($i = 0; $i < $name_length; $i++) {
            $c = $ps_name[$i];
            $oc = ord($c);
            if ($oc > 126 || strpos(' [](){}<>/%', $c) !== false) {
                //throw new \Mpdf\Exception\FontException("psName=".$psName." contains invalid character ".$c." ie U+".ord(c));
                $ps_name_invalid = true;
                break;
            }
        }
        if ($ps_name_invalid && $names[4]) {
            $ps_name = preg_replace('/ /', '-', $names[4]);
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
        if (!$ps_name_invalid && $names[6]) {
            $this->full_name = $names[6];
        }
        // head - Font header table
        $this->seek_table('head');
        if ($debug) {
            $ver_maj = $this->read_ushort();
            $ver_min = $this->read_ushort();
            if ($ver_maj != 1) {
                throw new \Mpdf\Exception\Font_Exception('Error loading font: Unknown head table version ' . $ver_maj . '.' . $ver_min);
            }
            $this->font_revision = $this->read_ushort() . $this->read_ushort();
            $this->skip(4);
            $magic = $this->read_ulong();
            if ($magic !== 0x5f0f3cf5) {
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
        $index_to_loc_format = $this->read_ushort();
        $glyph_data_format = $this->read_ushort();
        if ($glyph_data_format != 0) {
            throw new \Mpdf\Exception\Font_Exception(sprintf('Error loading font: Unknown glyph data format %s', $glyph_data_format));
        }
        // hhea metrics table
        if (isset($this->tables["hhea"])) {
            $this->seek_table("hhea");
            $this->skip(4);
            $hhea_ascender = $this->read_short();
            $hhea_descender = $this->read_short();
            $hhea_line_gap = $this->read_short();
            $hhea_advance_width_max = $this->read_ushort();
            $this->hheaascent = $hhea_ascender * $scale;
            $this->hheadescent = $hhea_descender * $scale;
            $this->hhealine_gap = $hhea_line_gap * $scale;
            $this->advance_width_max = $hhea_advance_width_max * $scale;
        }
        // OS/2 - OS/2 and Windows metrics table
        $use_typo_metrics = false;
        if (isset($this->tables["OS/2"])) {
            $this->seek_table("OS/2");
            $version = $this->read_ushort();
            $this->skip(2);
            $us_weight_class = $this->read_ushort();
            $this->skip(2);
            $fs_type = $this->read_ushort();
            if ($fs_type == 0x2 || ($fs_type & 0x300) != 0) {
                $this->restricted_use = true;
            }
            $this->skip(16);
            $y_strikeout_size = $this->read_short();
            $y_strikeout_position = $this->read_short();
            $this->strikeout_size = $y_strikeout_size * $scale;
            $this->strikeout_position = $y_strikeout_position * $scale;
            $s_f = $this->read_short();
            $this->s_family_class = $s_f >> 8;
            $this->s_family_sub_class = $s_f & 0xff;
            $this->_pos += 10;
            //PANOSE = 10 byte length
            $panose = fread($this->fh, 10);
            $this->panose = [];
            $panose_lenght = strlen($panose);
            for ($p = 0; $p < $panose_lenght; $p++) {
                $this->panose[] = ord($panose[$p]);
            }
            $this->skip(20);
            $fs_selection = $this->read_ushort();
            $use_typo_metrics = ($fs_selection & 0x80) === 0x80;
            // bit#7 = USE_TYPO_METRICS
            $this->skip(4);
            $s_typo_ascender = $this->read_short();
            $s_typo_descender = $this->read_short();
            $s_typo_line_gap = $this->read_short();
            if ($s_typo_ascender) {
                $this->typo_ascender = $s_typo_ascender * $scale;
            }
            if ($s_typo_descender) {
                $this->typo_descender = $s_typo_descender * $scale;
            }
            if ($s_typo_line_gap) {
                $this->typo_line_gap = $s_typo_line_gap * $scale;
            }
            $us_win_ascent = $this->read_ushort();
            $us_win_descent = $this->read_ushort();
            if ($us_win_ascent) {
                $this->us_win_ascent = $us_win_ascent * $scale;
            }
            if ($us_win_descent) {
                $this->us_win_descent = $us_win_descent * $scale;
            }
            if ($version > 1) {
                $this->skip(8);
                $sx_height = $this->read_short();
                $this->x_height = $sx_height * $scale;
                $s_cap_height = $this->read_short();
                $this->cap_height = $s_cap_height * $scale;
            }
        } else {
            $us_weight_class = 400;
        }
        $this->stem_v = 50 + (int) ($us_weight_class / 65.0) ** 2;
        // FONT DESCRIPTOR METRICS
        if ($this->font_descriptor === 'winTypo') {
            $this->ascent = $this->typo_ascender;
            $this->descent = $this->typo_descender;
            $this->line_gap = $this->typo_line_gap;
        } elseif ($this->font_descriptor === 'mac') {
            $this->ascent = $this->hheaascent;
            $this->descent = $this->hheadescent;
            $this->line_gap = $this->hhealine_gap;
        } else {
            // $this->fontDescriptor === 'win'
            $this->ascent = $this->us_win_ascent;
            $this->descent = -$this->us_win_descent;
            $this->line_gap = 0;
            // Special case - if either the winAscent or winDescent are greater than the
            // font bounding box yMin yMax, then reduce them accordingly.
            // This works with Myanmar Text (Windows 8 version) to give a
            // line-height normal that is equivalent to that produced in browsers.
            // Also Khmer OS = compatible with MSWord, Wordpad and browser.
            if ($this->ascent > $this->bbox[3]) {
                $this->ascent = $this->bbox[3];
            }
            if ($this->descent < $this->bbox[1]) {
                $this->descent = $this->bbox[1];
            }
            // Override case - if the USE_TYPO_METRICS bit is set on OS/2 fsSelection
            // this is telling the font to use the sTypo values and not the usWinAscent values.
            // This works as a fix with Cambria Math to give a normal line-height;
            // at present, this is the only font I have found with this bit set;
            // although note that MS WordPad and windows FF browser uses the big line-height from winAscent
            // but Word 2007 get it right
            if ($use_typo_metrics && $this->typo_ascender) {
                $this->ascent = $this->typo_ascender;
                $this->descent = $this->typo_descender;
                $this->line_gap = $this->typo_line_gap;
            }
        }
        // post - PostScript table
        $this->seek_table('post');
        if ($debug) {
            $ver_maj = $this->read_ushort();
            if ($ver_maj < 1 || $ver_maj > 4) {
                throw new \Mpdf\Exception\Font_Exception(sprintf('Error loading font: Unknown post table version %s', $ver_maj));
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
            $this->flags |= 64;
        }
        if ($us_weight_class >= 600) {
            $this->flags |= 262144;
        }
        if ($is_fixed_pitch) {
            $this->flags |= 1;
        }
        // hhea - Horizontal header table
        $this->seek_table('hhea');
        if ($debug) {
            $ver_maj = $this->read_ushort();
            if ($ver_maj != 1) {
                throw new \Mpdf\Exception\Font_Exception(sprintf('Error loading font: Unknown hhea table version %s', $ver_maj));
            }
            $this->skip(28);
        } else {
            $this->skip(32);
        }
        $metric_data_format = $this->read_ushort();
        if ($metric_data_format != 0) {
            throw new \Mpdf\Exception\Font_Exception(sprintf('Error loading font: Unknown horizontal metric data format "%s"', $metric_data_format));
        }
        $number_of_h_metrics = $this->read_ushort();
        if ($number_of_h_metrics == 0) {
            throw new \Mpdf\Exception\Font_Exception('Error loading font: Number of horizontal metrics is 0');
        }
        // maxp - Maximum profile table
        $this->seek_table('maxp');
        if ($debug) {
            $ver_maj = $this->read_ushort();
            if ($ver_maj != 1) {
                throw new \Mpdf\Exception\Font_Exception(sprintf('Error loading font: Unknown maxp table version %s', $ver_maj));
            }
        } else {
            $this->skip(4);
        }
        $num_glyphs = $this->read_ushort();
        // cmap - Character to glyph index mapping table
        $cmap_offset = $this->seek_table('cmap');
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
            } elseif (($platform_id == 3 && $encoding_id == 10 || $platform_id == 0) && !$bm_ponly) {
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
            throw new \Mpdf\Exception\Font_Exception(sprintf('Font "%s" does not have cmap for Unicode (platform 3, encoding 1, format 4, or platform 0, any encoding, format 4)', $this->filename));
        }
        $sipset = false;
        $smpset = false;
        $this->rtl_pu_astr = '';
        $this->gsub_script_lang = [];
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
                // ZZZ98
                if ($end_char_code > 0x20000 && $end_char_code < 0x2ffff) {
                    $sipset = true;
                } elseif ($end_char_code > 0x10000 && $end_char_code < 0x1ffff) {
                    $smpset = true;
                }
                $offset = 0;
                for ($unichar = $start_char_code; $unichar <= $end_char_code; $unichar++) {
                    $glyph = $start_glyph_code + $offset;
                    $offset++;
                    // ZZZ98
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
        // Map Unmapped glyphs (or glyphs mapped to upper PUA U+F00000 onwards i.e. > U+2FFFF) - from $numGlyphs
        if ($this->use_otl) {
            $bctr = 0xe000;
            for ($gid = 1; $gid < $num_glyphs; $gid++) {
                if (!isset($glyph_to_char[$gid])) {
                    while (isset($char_to_glyph[$bctr])) {
                        $bctr++;
                    }
                    // Avoid overwriting a glyph already mapped in PUA
                    // ZZZ98
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
                            throw new \Mpdf\Exception\Font_Exception(sprintf('The font "%s" does not have enough space to map all (unmapped) included glyphs into Private Use Area U+E000-U+F8FF', $names[1]));
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
        $this->gsub_script_lang = [];
        $this->rtl_pu_astr = '';
        if ($use_otl) {
            $this->_get_gde_ftables();
            list($this->gsub_script_lang, $this->gsub_features, $this->gsub_lookups, $this->rtl_pu_astr) = $this->_get_gsu_btables();
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
        // if xHeight and/or CapHeight are not available from OS/2 (e.g. eraly versions)
        // Calculate from yMax of 'x' or 'H' Glyphs...
        if ($this->x_height == 0) {
            if (isset($char_to_glyph[0x78])) {
                $gidx = $char_to_glyph[0x78];
                // U+0078 (LATIN SMALL LETTER X)
                $start = $this->seek_table('loca');
                if ($index_to_loc_format == 0) {
                    $this->skip($gidx * 2);
                    $locax = $this->read_ushort() * 2;
                } elseif ($index_to_loc_format == 1) {
                    $this->skip($gidx * 4);
                    $locax = $this->read_ulong();
                }
                $start = $this->seek_table('glyf');
                $this->skip($locax);
                $this->skip(8);
                $y_maxx = $this->read_short();
                $this->x_height = $y_maxx * $scale;
            }
        }
        if ($this->cap_height == 0) {
            if (isset($char_to_glyph[0x48])) {
                $gid_h = $char_to_glyph[0x48];
                // U+0048 (LATIN CAPITAL LETTER H)
                $start = $this->seek_table('loca');
                if ($index_to_loc_format == 0) {
                    $this->skip($gid_h * 2);
                    $loca_h = $this->read_ushort() * 2;
                } elseif ($index_to_loc_format == 1) {
                    $this->skip($gid_h * 4);
                    $loca_h = $this->read_ulong();
                }
                $start = $this->seek_table('glyf');
                $this->skip($loca_h);
                $this->skip(8);
                $y_max_h = $this->read_short();
                $this->cap_height = $y_max_h * $scale;
            } else {
                $this->cap_height = $this->ascent;
            }
            // final default is to set it = to Ascent
        }
        // hmtx - Horizontal metrics table
        $this->get_hmtx($number_of_h_metrics, $num_glyphs, $glyph_to_char, $scale);
        // kern - Kerning pair table
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
                if (isset($glyph_to_char[$left]) && count($glyph_to_char[$left]) == 1 && isset($glyph_to_char[$right]) && count($glyph_to_char[$right]) == 1) {
                    if ($left != 32 && $right != 32) {
                        $this->kerninfo[$glyph_to_char[$left][0]][$glyph_to_char[$right][0]] = intval($val * $scale);
                    }
                }
            }
        }
    }
    function _get_gde_ftables()
    {
        // http://www.microsoft.com/typography/otspec/gdef.htm
        if (isset($this->tables["GDEF"])) {
            $gdef_offset = $this->seek_table("GDEF");
            // ULONG Version of the GDEF table-currently 0x00010000
            $ver_maj = $this->read_ushort();
            $ver_min = $this->read_ushort();
            $glyph_class_def_offset = $this->read_ushort();
            $attach_list_offset = $this->read_ushort();
            $lig_caret_list_offset = $this->read_ushort();
            $mark_attach_class_def_offset = $this->read_ushort();
            // Version 0x00010002 of GDEF header contains additional Offset to a list defining mark glyph set definitions (MarkGlyphSetDef)
            if ($ver_min == 2) {
                $mark_glyph_sets_def_offset = $this->read_ushort();
            }
            // GlyphClassDef
            if ($glyph_class_def_offset) {
                $this->seek($gdef_offset + $glyph_class_def_offset);
                // 1 Base glyph (single character, spacing glyph)
                // 2 Ligature glyph (multiple character, spacing glyph)
                // 3 Mark glyph (non-spacing combining glyph)
                // 4 Component glyph (part of single character, spacing glyph)
                $glyph_by_class = $this->_get_class_definition_table();
            } else {
                $glyph_by_class = [];
            }
            if (isset($glyph_by_class[1]) && count($glyph_by_class[1]) > 0) {
                $this->glyph_class_bases = ' ' . implode('| ', $glyph_by_class[1]);
            } else {
                $this->glyph_class_bases = '';
            }
            if (isset($glyph_by_class[2]) && count($glyph_by_class[2]) > 0) {
                $this->glyph_class_ligatures = ' ' . implode('| ', $glyph_by_class[2]);
            } else {
                $this->glyph_class_ligatures = '';
            }
            if (isset($glyph_by_class[3]) && count($glyph_by_class[3]) > 0) {
                $this->glyph_class_marks = ' ' . implode('| ', $glyph_by_class[3]);
            } else {
                $this->glyph_class_marks = '';
            }
            if (isset($glyph_by_class[4]) && count($glyph_by_class[4]) > 0) {
                $this->glyph_class_components = ' ' . implode('| ', $glyph_by_class[4]);
            } else {
                $this->glyph_class_components = '';
            }
            if (isset($glyph_by_class[3]) && count($glyph_by_class[3]) > 0) {
                $Marks = $glyph_by_class[3];
            } else {
                // to use for MarkAttachmentType
                $Marks = [];
            }
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
                $this->seek($gdef_offset + $mark_attach_class_def_offset);
                $mark_attachment_types = $this->_get_class_definition_table();
                foreach ($mark_attachment_types as $class => $glyphs) {
                    if (is_array($Marks) && count($Marks)) {
                        $mat = array_diff($Marks, $mark_attachment_types[$class]);
                        sort($mat, SORT_STRING);
                    } else {
                        $mat = [];
                    }
                    $this->mark_attachment_type[$class] = ' ' . implode('| ', $mat);
                }
            } else {
                $this->mark_attachment_type = [];
            }
            // MarkGlyphSets only in Version 0x00010002 of GDEF
            if ($ver_min == 2 && $mark_glyph_sets_def_offset) {
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
                    $this->mark_glyph_sets[$i] = ' ' . implode('| ', $glyphs);
                }
            } else {
                $this->mark_glyph_sets = [];
            }
        } else {
            throw new \Mpdf\Exception\Font_Exception(sprintf('Unable to set font "%s" to use OTL as it does not include OTL tables (or at least not a GDEF table).', $this->filename));
        }
        $GSUB_offset = 0;
        $GPOS_offset = 0;
        $GSUB_length = 0;
        $s = '';
        if (isset($this->tables['GSUB'])) {
            $GSUB_offset = $this->seek_table('GSUB');
            $GSUB_length = $this->tables['GSUB']['length'];
            $s .= fread($this->fh, $this->tables['GSUB']['length']);
        }
        if (isset($this->tables['GPOS'])) {
            $GPOS_offset = $this->seek_table('GPOS');
            $s .= fread($this->fh, $this->tables['GPOS']['length']);
        }
        if ($s) {
            $this->font_cache->write($this->fontkey . '.GSUBGPOStables.dat', $s);
        }
        $font = ['GSUB_offset' => $GSUB_offset, 'GPOS_offset' => $GPOS_offset, 'GSUB_length' => $GSUB_length, 'GlyphClassBases' => $this->glyph_class_bases, 'GlyphClassMarks' => $this->glyph_class_marks, 'GlyphClassLigatures' => $this->glyph_class_ligatures, 'GlyphClassComponents' => $this->glyph_class_components, 'MarkGlyphSets' => $this->mark_glyph_sets, 'MarkAttachmentType' => $this->mark_attachment_type];
        $this->font_cache->json_write($this->fontkey . '.GDEFdata.json', $font);
    }
    function _get_class_definition_table()
    {
        // NB Any glyph not included in the range of covered GlyphIDs automatically belongs to Class 0. This is not returned by this function
        $class_format = $this->read_ushort();
        $glyph_by_class = [];
        if ($class_format == 1) {
            $start_glyph = $this->read_ushort();
            $glyph_count = $this->read_ushort();
            for ($i = 0; $i < $glyph_count; $i++) {
                $gid = $start_glyph + $i;
                $class = $this->read_ushort();
                // Several fonts  (mainly dejavu.../Freeserif etc) have a MarkAttachClassDef Format 1, where StartGlyph is 0 and GlyphCount is 1
                // This doesn't seem to do anything useful?
                // Freeserif does not have $this->glyphToChar[0] allocated and would throw an error, so check if isset:
                if (isset($this->glyph_to_char[$gid][0])) {
                    $glyph_by_class[$class][] = unicode_hex($this->glyph_to_char[$gid][0]);
                }
            }
        } elseif ($class_format == 2) {
            $table_count = $this->read_ushort();
            for ($i = 0; $i < $table_count; $i++) {
                $start_glyph_id = $this->read_ushort();
                $end_glyph_id = $this->read_ushort();
                $class = $this->read_ushort();
                for ($gid = $start_glyph_id; $gid <= $end_glyph_id; $gid++) {
                    if (isset($this->glyph_to_char[$gid][0])) {
                        $glyph_by_class[$class][] = unicode_hex($this->glyph_to_char[$gid][0]);
                    }
                }
            }
        }
        foreach ($glyph_by_class as $class => $glyphs) {
            sort($glyph_by_class[$class], SORT_STRING);
            // SORT makes it easier to read in development ? order not important ???
        }
        ksort($glyph_by_class);
        return $glyph_by_class;
    }
    /**
     * GSUB - Glyph Substitution
     */
    function _get_gsu_btables()
    {
        if (!isset($this->tables['GSUB'])) {
            return [[], [], [], ''];
        }
        $ffeats = [];
        $gsub_offset = $this->seek_table('GSUB');
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
        // Feauture List => LookupListIndex es
        $this->seek($feature_list_offset);
        $feature_count = $this->read_ushort();
        $Feature = [];
        for ($i = 0; $i < $feature_count; $i++) {
            $tag = $this->read_tag();
            if ($tag == 'smcp') {
                $this->hassmallcaps_gsub = true;
            }
            $Feature[$i] = ['tag' => $tag];
            $Feature[$i]['offset'] = $feature_list_offset + $this->read_ushort();
        }
        for ($i = 0; $i < $feature_count; $i++) {
            $this->seek($Feature[$i]['offset']);
            $this->read_ushort();
            // null [FeatureParams]
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
            } else {
                $gs_lookup[$i]['MarkFilteringSet'] = '';
            }
            // Lookup Type 7: Extension
            if ($gs_lookup[$i]['Type'] == 7) {
                // Overwrites new offset (32-bit) for each subtable, and a new lookup Type
                for ($c = 0; $c < $subtable_count[$i]; $c++) {
                    $this->seek($gs_lookup[$i]['Subtables'][$c]);
                    $extension_pos_format = $this->read_ushort();
                    $type = $this->read_ushort();
                    $ext_offset = $this->read_ulong();
                    $gs_lookup[$i]['Subtables'][$c] = $gs_lookup[$i]['Subtables'][$c] + $ext_offset;
                }
                $gs_lookup[$i]['Type'] = $type;
            }
        }
        // Process Whole LookupList - Get LuCoverage = Lookup coverage just for first glyph
        $this->gs_lu_coverage = [];
        for ($i = 0; $i < $lookup_count; $i++) {
            for ($c = 0; $c < $gs_lookup[$i]['SubtableCount']; $c++) {
                $this->seek($gs_lookup[$i]['Subtables'][$c]);
                $pos_format = $this->read_ushort();
                if ($gs_lookup[$i]['Type'] == 5 && $pos_format == 3) {
                    $this->skip(4);
                } elseif ($gs_lookup[$i]['Type'] == 6 && $pos_format == 3) {
                    $backtrack_glyph_count = $this->read_ushort();
                    $this->skip(2 * $backtrack_glyph_count + 2);
                }
                // NB Coverage only looks at glyphs for position 1 (i.e. 5.3 and 6.3)	// NEEDS TO READ ALL ********************
                $Coverage = $gs_lookup[$i]['Subtables'][$c] + $this->read_ushort();
                $this->seek($Coverage);
                $glyphs = $this->_get_coverage(false, 2);
                $this->gs_lu_coverage[$i][$c] = $glyphs;
            }
        }
        // $this->GSLuCoverage and $GSLookup
        $this->font_cache->json_write($this->fontkey . '.GSUBdata.json', $this->gs_lu_coverage);
        // Now repeats as original to get Substitution rules
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
                    } elseif ($subst_format == 2) {
                        // Specified output glyph indices
                        $glyph_count = $this->read_ushort();
                        for ($g = 0; $g < $glyph_count; $g++) {
                            $Lookup[$i]['Subtable'][$c]['Glyphs'][] = $this->read_ushort();
                        }
                    }
                } elseif ($Lookup[$i]['Type'] == 2) {
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
                } elseif ($Lookup[$i]['Type'] == 3) {
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
                } elseif ($Lookup[$i]['Type'] == 4) {
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
                } elseif ($Lookup[$i]['Type'] == 5) {
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
                    } elseif ($subst_format == 2) {
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
                } elseif ($Lookup[$i]['Type'] == 6) {
                    // Format 1: Simple Chaining Context Glyph Substitution  p255
                    if ($subst_format == 1) {
                        $Lookup[$i]['Subtable'][$c]['CoverageTableOffset'] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                        $Lookup[$i]['Subtable'][$c]['ChainSubRuleSetCount'] = $this->read_ushort();
                        for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['ChainSubRuleSetCount']; $b++) {
                            $Lookup[$i]['Subtable'][$c]['ChainSubRuleSetOffset'][] = $Lookup[$i]['Subtable'][$c]['Offset'] + $this->read_ushort();
                        }
                    } elseif ($subst_format == 2) {
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
                    } elseif ($subst_format == 3) {
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
                            // Substitution Lookup Record
                            // All contextual substitution subtables specify the substitution data in a Substitution Lookup Record
                            // (SubstLookupRecord). Each record contains a SequenceIndex, which indicates the position where the substitution
                            // will occur in the glyph sequence. In addition, a LookupListIndex identifies the lookup to be applied at the
                            // glyph position specified by the SequenceIndex.
                        }
                    }
                } else {
                    throw new \Mpdf\Exception\Font_Exception(sprintf('Lookup Type "%s" not supported.', $Lookup[$i]['Type']));
                }
            }
        }
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
                } elseif ($Lookup[$i]['Type'] == 2) {
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
                        if (!isset($Lookup[$i]['Subtable'][$c]['Sequences'][$g]['SubstituteGlyphID']) || count($Lookup[$i]['Subtable'][$c]['Sequences'][$g]['SubstituteGlyphID']) == 0) {
                            continue;
                        }
                        // Illegal for GlyphCount to be 0; either error in font, or something has gone wrong - lets carry on for now!
                        foreach ($Lookup[$i]['Subtable'][$c]['Sequences'][$g]['SubstituteGlyphID'] as $sub) {
                            $substitute[] = unicode_hex($this->glyph_to_char[$sub][0]);
                        }
                        $Lookup[$i]['Subtable'][$c]['subs'][] = ['Replace' => $replace, 'substitute' => $substitute];
                    }
                } elseif ($Lookup[$i]['Type'] == 3) {
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
                        $gid = $Lookup[$i]['Subtable'][$c]['AlternateSets'][$g]['SubstituteGlyphID'][0];
                        if (!isset($this->glyph_to_char[$gid][0])) {
                            continue;
                        }
                        $substitute[] = unicode_hex($this->glyph_to_char[$gid][0]);
                        $Lookup[$i]['Subtable'][$c]['subs'][] = ['Replace' => $replace, 'substitute' => $substitute];
                    }
                } elseif ($Lookup[$i]['Type'] == 4) {
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
                            if (!isset($this->glyph_to_char[$gid][0])) {
                                continue;
                            }
                            $substitute[] = unicode_hex($this->glyph_to_char[$gid][0]);
                            $Lookup[$i]['Subtable'][$c]['subs'][] = ['Replace' => $replace, 'substitute' => $substitute, 'CompCount' => $Lookup[$i]['Subtable'][$c]['LigSet'][$s]['Ligature'][$g]['CompCount']];
                        }
                    }
                } elseif ($Lookup[$i]['Type'] == 5) {
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
                    } elseif ($subst_format == 2) {
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
                            if ($Lookup[$i]['Subtable'][$c]['SubClassSetOffset'][$s] > 0) {
                                $sub_class_rule_cnt = $Lookup[$i]['Subtable'][$c]['SubClassSet'][$s]['SubClassRuleCnt'];
                                for ($b = 0; $b < $sub_class_rule_cnt; $b++) {
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
                    } elseif ($subst_format == 3) {
                        for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['InputGlyphCount']; $b++) {
                            $this->seek($Lookup[$i]['Subtable'][$c]['CoverageInput'][$b]);
                            $glyphs = $this->_get_coverage();
                            $Lookup[$i]['Subtable'][$c]['CoverageInputGlyphs'][] = implode("|", $glyphs);
                        }
                        throw new \Mpdf\Exception\Font_Exception("Lookup Type 5, SubstFormat 3 not tested. Please report this with the name of font used - " . $this->fontkey);
                    }
                } elseif ($Lookup[$i]['Type'] == 6) {
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
                    } elseif ($subst_format == 2) {
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
                            if (isset($Lookup[$i]['Subtable'][$c]['ChainSubClassSet'][$s]['ChainSubClassRuleCnt'])) {
                                $chain_sub_class_rule_cnt = $Lookup[$i]['Subtable'][$c]['ChainSubClassSet'][$s]['ChainSubClassRuleCnt'];
                            } else {
                                $chain_sub_class_rule_cnt = 0;
                            }
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
                    } elseif ($subst_format == 3) {
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
        $gsub_script_lang = [];
        $rtlpua = [];
        // All glyphs added to PUA [for magic_reverse]
        foreach ($gsub as $st => $scripts) {
            foreach ($scripts as $t => $langsys) {
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
                $volt = $this->_get_gsu_barray($Lookup, $lul, $st);
                // Interrogate $volt
                // isol, fin, medi, init(arab syrc) into $rtlSUB for use in ArabJoin
                // but also identify all RTL chars in PUA for magic_reverse (arab syrc hebr thaa nko  samr)
                // identify reph, matras, vatu, half forms etc for Indic for final re-ordering
                $rtl = [];
                $rtl_sub = [];
                $finals = '';
                if (strpos('arab syrc hebr thaa nko  samr', $st) !== false) {
                    // all RTL scripts [any/all languages] ? Mandaic
                    foreach ($volt as $v) {
                        // isol fina fin2 fin3 medi med2 for Syriac
                        // ISOLATED FORM :: FINAL :: INITIAL :: MEDIAL :: MED2 :: FIN2 :: FIN3
                        if (strpos('isol fina init medi fin2 fin3 med2', $v['tag']) !== false) {
                            $key = $v['match'];
                            $key = preg_replace('/[\(\)]*/', '', $key);
                            $sub = $v['replace'];
                            if ($v['tag'] === 'isol') {
                                $kk = 0;
                            } elseif ($v['tag'] === 'fina') {
                                $kk = 1;
                            } elseif ($v['tag'] === 'init') {
                                $kk = 2;
                            } elseif ($v['tag'] === 'medi') {
                                $kk = 3;
                            } elseif ($v['tag'] === 'med2') {
                                $kk = 4;
                            } elseif ($v['tag'] === 'fin2') {
                                $kk = 5;
                            } elseif ($v['tag'] === 'fin3') {
                                $kk = 6;
                            }
                            $rtl[$key][$kk] = $sub;
                            if (isset($v['prel']) && count($v['prel'])) {
                                $rtl[$key]['prel'][$kk] = $v['prel'];
                            }
                            if (isset($v['postl']) && count($v['postl'])) {
                                $rtl[$key]['postl'][$kk] = $v['postl'];
                            }
                            if (isset($v['ignore']) && $v['ignore']) {
                                $rtl[$key]['ignore'][$kk] = $v['ignore'];
                            }
                            $rtlpua[] = $sub;
                        } else if (isset($v['context']) && $v['context']) {
                            foreach ($v['rules'] as $vs) {
                                $match_count = count($vs['match']);
                                for ($i = 0; $i < $match_count; $i++) {
                                    if (isset($vs['replace'][$i]) && preg_match('/^0[A-F0-9]{4}$/', $vs['match'][$i])) {
                                        if (preg_match('/^0[EF][A-F0-9]{3}$/', $vs['replace'][$i])) {
                                            $rtlpua[] = $vs['replace'][$i];
                                        }
                                    }
                                }
                            }
                        } else {
                            preg_match_all('/\((0[A-F0-9]{4})\)/', $v['match'], $m);
                            $match_count = count($m[0]);
                            for ($i = 0; $i < $match_count; $i++) {
                                $sb = explode(' ', $v['replace']);
                                foreach ($sb as $sbg) {
                                    if (preg_match('/(0[EF][A-F0-9]{3})/', $sbg, $mr)) {
                                        $rtlpua[] = $mr[1];
                                    }
                                }
                            }
                        }
                    }
                    // For kashida, need to determine all final forms except ones already identified by kashida priority rules (see \Mpdf\Otl)
                    foreach ($rtl as $base => $variants) {
                        if (isset($variants[1])) {
                            // i.e. final form
                            if (strpos('0FE8E 0FE94 0FEA2 0FEAA 0FEAE 0FEC2 0FEDA 0FEDE 0FB93 0FECA 0FED2 0FED6 0FEEE 0FEF0 0FEF2', $variants[1]) === false) {
                                // not already included
                                // This version does not exclude RA (0631) FEAE; Ya (064A)  FEF2; Alef Maqsurah (0649) FEF0 which
                                // are selected in priority if connected to a medial Bah
                                //if (strpos('0FE8E 0FE94 0FEA2 0FEAA 0FEC2 0FEDA 0FEDE 0FB93 0FECA 0FED2 0FED6 0FEEE', $variants[1])===false) {	// not already included
                                $finals .= $variants[1] . ' ';
                            }
                        }
                    }
                    ksort($rtl);
                    $rtl_sub = $rtl;
                }
                // INDIC - Dynamic properties
                $rphf = [];
                $half = [];
                $pref = [];
                $blwf = [];
                $pstf = [];
                if (strpos('dev2 bng2 gur2 gjr2 ory2 tml2 tel2 knd2 mlm2 deva beng guru gujr orya taml telu knda mlym', $st) !== false) {
                    // all INDIC scripts [any/all languages]
                    if (strpos('deva beng guru gujr orya taml telu knda mlym', $st) !== false) {
                        $is_old_spec = true;
                    } else {
                        $is_old_spec = false;
                    }
                    // First get 'locl' substitutions (reversed!)
                    $loclsubs = [];
                    foreach ($volt as $v) {
                        if (strpos('locl', $v['tag']) !== false) {
                            $key = $v['match'];
                            $key = preg_replace('/[\(\)]*/', '', $key);
                            $sub = $v['replace'];
                            if ($key && strlen(trim($key)) == 5 && $sub) {
                                $loclsubs[$sub] = $key;
                            }
                        }
                    }
                    foreach ($volt as $v) {
                        // <rphf> <half> <pref> <blwf> <pstf>
                        // defines consonant types:
                        //     Reph <rphf>
                        //     Half forms <half>
                        //     Pre-base-reordering forms of Ra/Rra <pref>
                        //     Below-base forms <blwf>
                        //     Post-base forms <pstf>
                        // applied together with <locl> feature to input sequences consisting of two characters
                        // This is done for each consonant
                        // for <rphf> and <half>, features are applied to Consonant + Halant combinations
                        // for <pref>, <blwf> and <pstf>, features are applied to Halant + Consonant combinations
                        // Old version eg 'deva' <pref>, <blwf> and <pstf>, features are applied to Consonant + Halant
                        // Some malformed fonts still do Consonant + Halant for these - so match both??
                        // If these two glyphs form a ligature, with no additional glyphs in context
                        // this means the consonant has the corresponding form
                        // Currently set to cope with both
                        // See also classes/otl.php
                        if (strpos('rphf half pref blwf pstf', $v['tag']) !== false) {
                            if (isset($v['context']) && $v['context'] && $v['nBacktrack'] == 0 && $v['nLookahead'] == 0) {
                                foreach ($v['rules'] as $vs) {
                                    if (count($vs['match']) == 2 && count($vs['replace']) == 1) {
                                        $sub = $vs['replace'][0];
                                        // If Halant Cons   <pref>, <blwf> and <pstf> in New version only
                                        if (strpos('0094D 009CD 00A4D 00ACD 00B4D 00BCD 00C4D 00CCD 00D4D', $vs['match'][0]) !== false && strpos('pref blwf pstf', $v['tag']) !== false && !$is_old_spec) {
                                            $key = $vs['match'][1];
                                            $tag = $v['tag'];
                                            if (isset($loclsubs[$key])) {
                                                ${$tag}[$loclsubs[$key]] = $sub;
                                            }
                                            $tmp =& ${$tag};
                                            $tmp[hexdec($key)] = hexdec($sub);
                                        } elseif (strpos('0094D 009CD 00A4D 00ACD 00B4D 00BCD 00C4D 00CCD 00D4D', $vs['match'][1]) !== false && (strpos('rphf half', $v['tag']) !== false || strpos('pref blwf pstf', $v['tag']) !== false && ($is_old_spec || _OTL_OLD_SPEC_COMPAT_2))) {
                                            $key = $vs['match'][0];
                                            $tag = $v['tag'];
                                            if (isset($loclsubs[$key])) {
                                                ${$tag}[$loclsubs[$key]] = $sub;
                                            }
                                            $tmp =& ${$tag};
                                            $tmp[hexdec($key)] = hexdec($sub);
                                        }
                                    }
                                }
                            } elseif (!isset($v['context'])) {
                                $key = $v['match'];
                                $key = preg_replace('/[\(\)]*/', '', $key);
                                $sub = $v['replace'];
                                if ($key && strlen(trim($key)) == 11 && $sub) {
                                    // If Cons Halant    <rphf> and <half> always
                                    // and <pref>, <blwf> and <pstf> in Old version
                                    // If Halant Cons   <pref>, <blwf> and <pstf> in New version only
                                    if (strpos('0094D 009CD 00A4D 00ACD 00B4D 00BCD 00C4D 00CCD 00D4D', substr($key, 0, 5)) !== false && strpos('pref blwf pstf', $v['tag']) !== false && !$is_old_spec) {
                                        $key = substr($key, 6, 5);
                                        $tag = $v['tag'];
                                        if (isset($loclsubs[$key])) {
                                            ${$tag}[$loclsubs[$key]] = $sub;
                                        }
                                        $tmp =& ${$tag};
                                        $tmp[hexdec($key)] = hexdec($sub);
                                    } elseif (strpos('0094D 009CD 00A4D 00ACD 00B4D 00BCD 00C4D 00CCD 00D4D', substr($key, 6, 5)) !== false && (strpos('rphf half', $v['tag']) !== false || strpos('pref blwf pstf', $v['tag']) !== false && ($is_old_spec || _OTL_OLD_SPEC_COMPAT_2))) {
                                        $key = substr($key, 0, 5);
                                        $tag = $v['tag'];
                                        if (isset($loclsubs[$key])) {
                                            ${$tag}[$loclsubs[$key]] = $sub;
                                        }
                                        $tmp =& ${$tag};
                                        $tmp[hexdec($key)] = hexdec($sub);
                                    }
                                }
                            }
                        }
                    }
                }
                if (count($rtl) || count($rphf) || count($half) || count($pref) || count($blwf) || count($pstf) || $finals) {
                    $font = ['rtlSUB' => $rtl_sub, 'finals' => $finals, 'rphf' => $rphf, 'half' => $half, 'pref' => $pref, 'blwf' => $blwf, 'pstf' => $pstf];
                    $this->font_cache->json_write($this->fontkey . '.GSUB.' . $st . '.' . $t . '.json', $font);
                }
                if (!isset($gsub_script_lang[$st])) {
                    $gsub_script_lang[$st] = '';
                }
                $gsub_script_lang[$st] .= $t . ' ';
            }
        }
        // All RTL glyphs from font added to (or already in) PUA [reqd for magic_reverse]
        $rtl_pu_astr = '';
        if (count($rtlpua)) {
            $rtlpua = array_unique($rtlpua);
            sort($rtlpua);
            $n = count($rtlpua);
            for ($i = 0; $i < $n; $i++) {
                if (hexdec($rtlpua[$i]) < hexdec('E000') || hexdec($rtlpua[$i]) > hexdec('F8FF')) {
                    unset($rtlpua[$i]);
                }
            }
            sort($rtlpua, SORT_STRING);
            $rangeid = -1;
            $range = [];
            $prevgid = -2;
            // for each character
            foreach ($rtlpua as $gidhex) {
                $gid = hexdec($gidhex);
                if ($gid == $prevgid + 1) {
                    $range[$rangeid]['end'] = $gidhex;
                    $range[$rangeid]['count']++;
                } else {
                    // new range
                    $rangeid++;
                    $range[$rangeid] = [];
                    $range[$rangeid]['start'] = $gidhex;
                    $range[$rangeid]['end'] = $gidhex;
                    $range[$rangeid]['count'] = 1;
                }
                $prevgid = $gid;
            }
            foreach ($range as $rg) {
                if ($rg['count'] == 1) {
                    $rtl_pu_astr .= "\\x{" . $rg['start'] . "}";
                } elseif ($rg['count'] == 2) {
                    $rtl_pu_astr .= "\\x{" . $rg['start'] . "}\\x{" . $rg['end'] . "}";
                } else {
                    $rtl_pu_astr .= "\\x{" . $rg['start'] . "}-\\x{" . $rg['end'] . "}";
                }
            }
        }
        return [$gsub_script_lang, $gsub, $gs_lookup, $rtl_pu_astr];
    }
    // GSUB functions
    function _get_gsu_barray(&$Lookup, &$lul, $scripttag)
    {
        // Process (3) LookupList for specific Script-LangSys
        // Generate preg_replace
        $volt = [];
        $reph = '';
        $matra_e = '';
        $vatu = '';
        foreach ($lul as $i => $tag) {
            for ($c = 0; $c < $Lookup[$i]['SubtableCount']; $c++) {
                $subst_format = $Lookup[$i]['Subtable'][$c]['Format'];
                // LookupType 1: Single Substitution Subtable
                if ($Lookup[$i]['Type'] == 1) {
                    $sub_count = count($Lookup[$i]['Subtable'][$c]['subs']);
                    for ($s = 0; $s < $sub_count; $s++) {
                        $input_glyphs = $Lookup[$i]['Subtable'][$c]['subs'][$s]['Replace'];
                        $substitute = $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute'][0];
                        // Ignore has already been applied earlier on
                        $repl = $this->_make_gsu_binput_match($input_glyphs, "()");
                        $subs = $this->_make_gsu_binput_replacement(1, $substitute, "()", 0, 1, 0);
                        $volt[] = ['match' => $repl, 'replace' => $subs, 'tag' => $tag, 'key' => $input_glyphs[0], 'type' => 1];
                    }
                } elseif ($Lookup[$i]['Type'] == 2) {
                    for ($s = 0; $s < count($Lookup[$i]['Subtable'][$c]['subs']); $s++) {
                        $input_glyphs = $Lookup[$i]['Subtable'][$c]['subs'][$s]['Replace'];
                        $substitute = implode(" ", $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute']);
                        // Ignore has already been applied earlier on
                        $repl = $this->_make_gsu_binput_match($input_glyphs, "()");
                        $subs = $this->_make_gsu_binput_replacement(1, $substitute, "()", 0, 1, 0);
                        $volt[] = ['match' => $repl, 'replace' => $subs, 'tag' => $tag, 'key' => $input_glyphs[0], 'type' => 2];
                    }
                } elseif ($Lookup[$i]['Type'] == 3) {
                    for ($s = 0; $s < count($Lookup[$i]['Subtable'][$c]['subs']); $s++) {
                        $input_glyphs = $Lookup[$i]['Subtable'][$c]['subs'][$s]['Replace'];
                        $substitute = $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute'][0];
                        // Ignore has already been applied earlier on
                        $repl = $this->_make_gsu_binput_match($input_glyphs, "()");
                        $subs = $this->_make_gsu_binput_replacement(1, $substitute, "()", 0, 1, 0);
                        $volt[] = ['match' => $repl, 'replace' => $subs, 'tag' => $tag, 'key' => $input_glyphs[0], 'type' => 3];
                    }
                } elseif ($Lookup[$i]['Type'] == 4) {
                    for ($s = 0; $s < count($Lookup[$i]['Subtable'][$c]['subs']); $s++) {
                        $input_glyphs = $Lookup[$i]['Subtable'][$c]['subs'][$s]['Replace'];
                        $substitute = $Lookup[$i]['Subtable'][$c]['subs'][$s]['substitute'][0];
                        // Ignore has already been applied earlier on
                        $ignore = $this->_get_gsu_bignore_string($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
                        $repl = $this->_make_gsu_binput_match($input_glyphs, $ignore);
                        $subs = $this->_make_gsu_binput_replacement(count($input_glyphs), $substitute, $ignore, 0, count($input_glyphs), 0);
                        $volt[] = ['match' => $repl, 'replace' => $subs, 'tag' => $tag, 'key' => $input_glyphs[0], 'type' => 4, 'CompCount' => $Lookup[$i]['Subtable'][$c]['subs'][$s]['CompCount'], 'Lig' => $substitute];
                    }
                } elseif ($Lookup[$i]['Type'] == 5) {
                    // Format 1: Context Substitution
                    if ($subst_format == 1) {
                        $ignore = $this->_get_gsu_bignore_string($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
                        for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['SubRuleSetCount']; $s++) {
                            // SubRuleSet
                            $sub_rule = [];
                            foreach ($Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['SubRule'] as $rule) {
                                // SubRule
                                $input_glyphs = [];
                                if ($rule['GlyphCount'] > 1) {
                                    $input_glyphs = $rule['InputGlyphs'];
                                }
                                $input_glyphs[0] = $Lookup[$i]['Subtable'][$c]['SubRuleSet'][$s]['FirstGlyph'];
                                ksort($input_glyphs);
                                $n_input = count($input_glyphs);
                                $context_input_match = $this->_make_gsu_bcontext_input_match($input_glyphs, $ignore, [], 0);
                                $sub_rule = ['context' => 1, 'tag' => $tag, 'matchback' => '', 'match' => $context_input_match, 'nBacktrack' => 0, 'nInput' => $n_input, 'nLookahead' => 0, 'rules' => []];
                                for ($b = 0; $b < $rule['SubstCount']; $b++) {
                                    $lup = $rule['SubstLookupRecord'][$b]['LookupListIndex'];
                                    $seq_index = $rule['SubstLookupRecord'][$b]['SequenceIndex'];
                                    // $Lookup[$lup] = secondary Lookup
                                    for ($lus = 0; $lus < $Lookup[$lup]['SubtableCount']; $lus++) {
                                        if (count($Lookup[$lup]['Subtable'][$lus]['subs'])) {
                                            foreach ($Lookup[$lup]['Subtable'][$lus]['subs'] as $luss) {
                                                $lookup_glyphs = $luss['Replace'];
                                                $m_len = count($lookup_glyphs);
                                                // Only apply if the (first) 'Replace' glyph from the
                                                // Lookup list is in the [inputGlyphs] at ['SequenceIndex']
                                                // then apply the substitution
                                                if (strpos($input_glyphs[$seq_index], $lookup_glyphs[0]) === false) {
                                                    continue;
                                                }
                                                $REPL = implode(" ", $luss['substitute']);
                                                if (strpos("isol fina fin2 fin3 medi med2 init ", $tag) !== false && $scripttag == 'arab') {
                                                    $volt[] = ['match' => $lookup_glyphs[0], 'replace' => $REPL, 'tag' => $tag, 'prel' => $backtrack_glyphs, 'postl' => $lookahead_glyphs, 'ignore' => $ignore];
                                                } else {
                                                    $sub_rule['rules'][] = ['type' => $Lookup[$lup]['Type'], 'match' => $lookup_glyphs, 'replace' => $luss['substitute'], 'seqIndex' => $seq_index, 'key' => $lookup_glyphs[0]];
                                                }
                                            }
                                        }
                                    }
                                }
                                if (count($sub_rule['rules'])) {
                                    $volt[] = $sub_rule;
                                }
                            }
                        }
                    } elseif ($subst_format == 2) {
                        $ignore = $this->_get_gsu_bignore_string($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
                        foreach ($Lookup[$i]['Subtable'][$c]['SubClassSet'] as $input_class => $cscs) {
                            for ($cscrule = 0; $cscrule < $cscs['SubClassRuleCnt']; $cscrule++) {
                                $rule = $cscs['SubClassRule'][$cscrule];
                                $input_glyphs = [];
                                $input_glyphs[0] = $Lookup[$i]['Subtable'][$c]['InputClasses'][$input_class];
                                if ($rule['InputGlyphCount'] > 1) {
                                    //  NB starts at 1
                                    for ($gcl = 1; $gcl < $rule['InputGlyphCount']; $gcl++) {
                                        $classindex = $rule['Input'][$gcl];
                                        if (isset($Lookup[$i]['Subtable'][$c]['InputClasses'][$classindex])) {
                                            $input_glyphs[$gcl] = $Lookup[$i]['Subtable'][$c]['InputClasses'][$classindex];
                                        } else {
                                            $input_glyphs[$gcl] = '';
                                        }
                                    }
                                }
                                $n_input = $rule['InputGlyphCount'];
                                $n_isubs = 2 * $n_input - 1;
                                $context_input_match = $this->_make_gsu_bcontext_input_match($input_glyphs, $ignore, [], 0);
                                $sub_rule = ['context' => 1, 'tag' => $tag, 'matchback' => '', 'match' => $context_input_match, 'nBacktrack' => 0, 'nInput' => $n_input, 'nLookahead' => 0, 'rules' => []];
                                for ($b = 0; $b < $rule['SubstCount']; $b++) {
                                    $lup = $rule['LookupListIndex'][$b];
                                    $seq_index = $rule['SequenceIndex'][$b];
                                    // $Lookup[$lup] = secondary Lookup
                                    for ($lus = 0; $lus < $Lookup[$lup]['SubtableCount']; $lus++) {
                                        if (isset($Lookup[$lup]['Subtable'][$lus]['subs']) && count($Lookup[$lup]['Subtable'][$lus]['subs'])) {
                                            foreach ($Lookup[$lup]['Subtable'][$lus]['subs'] as $luss) {
                                                $lookup_glyphs = $luss['Replace'];
                                                $m_len = count($lookup_glyphs);
                                                // Only apply if the (first) 'Replace' glyph from the
                                                // Lookup list is in the [inputGlyphs] at ['SequenceIndex']
                                                // then apply the substitution
                                                if (strpos($input_glyphs[$seq_index], $lookup_glyphs[0]) === false) {
                                                    continue;
                                                }
                                                // Returns e.g. ¦(0612)¦(ignore) (0613)¦(ignore) (0614)¦
                                                $context_input_match = $this->_make_gsu_bcontext_input_match($input_glyphs, $ignore, $lookup_glyphs, $seq_index);
                                                $REPL = implode(" ", $luss['substitute']);
                                                // Returns e.g. "REPL\${6}\${8}" or "\${1}\${2} \${3} REPL\${4}\${6}\${8} \${9}"
                                                if (strpos("isol fina fin2 fin3 medi med2 init ", $tag) !== false && $scripttag == 'arab') {
                                                    $volt[] = ['match' => $lookup_glyphs[0], 'replace' => $REPL, 'tag' => $tag, 'prel' => $backtrack_glyphs, 'postl' => $lookahead_glyphs, 'ignore' => $ignore];
                                                } else {
                                                    $sub_rule['rules'][] = ['type' => $Lookup[$lup]['Type'], 'match' => $lookup_glyphs, 'replace' => $luss['substitute'], 'seqIndex' => $seq_index, 'key' => $lookup_glyphs[0]];
                                                }
                                            }
                                        }
                                    }
                                }
                                if (count($sub_rule['rules'])) {
                                    $volt[] = $sub_rule;
                                }
                            }
                        }
                    } elseif ($subst_format == 3) {
                        // IgnoreMarks flag set on main Lookup table
                        $ignore = $this->_get_gsu_bignore_string($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
                        $input_glyphs = $Lookup[$i]['Subtable'][$c]['CoverageInputGlyphs'];
                        $coverage_input_glyphs = implode('|', $input_glyphs);
                        $n_input = $Lookup[$i]['Subtable'][$c]['InputGlyphCount'];
                        if ($Lookup[$i]['Subtable'][$c]['BacktrackGlyphCount']) {
                            $backtrack_glyphs = $Lookup[$i]['Subtable'][$c]['CoverageBacktrackGlyphs'];
                        } else {
                            $backtrack_glyphs = [];
                        }
                        // Returns e.g. ¦(FEEB|FEEC)(ignore) ¦(FD12|FD13)(ignore) ¦
                        $backtrack_match = $this->_make_gsu_bbacktrack_match($backtrack_glyphs, $ignore);
                        if ($Lookup[$i]['Subtable'][$c]['LookaheadGlyphCount']) {
                            $lookahead_glyphs = $Lookup[$i]['Subtable'][$c]['CoverageLookaheadGlyphs'];
                        } else {
                            $lookahead_glyphs = [];
                        }
                        // Returns e.g. ¦(ignore) (FD12|FD13)¦(ignore) (FEEB|FEEC)¦
                        $lookahead_match = $this->_make_gsu_blookahead_match($lookahead_glyphs, $ignore);
                        $n_bsubs = 2 * count($backtrack_glyphs);
                        $n_isubs = 2 * $n_input - 1;
                        $context_input_match = $this->_make_gsu_bcontext_input_match($input_glyphs, $ignore, [], 0);
                        $sub_rule = ['context' => 1, 'tag' => $tag, 'matchback' => $backtrack_match, 'match' => $context_input_match . $lookahead_match, 'nBacktrack' => count($backtrack_glyphs), 'nInput' => $n_input, 'nLookahead' => count($lookahead_glyphs), 'rules' => []];
                        for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['SubstCount']; $b++) {
                            $lup = $Lookup[$i]['Subtable'][$c]['SubstLookupRecord'][$b]['LookupListIndex'];
                            $seq_index = $Lookup[$i]['Subtable'][$c]['SubstLookupRecord'][$b]['SequenceIndex'];
                            for ($lus = 0; $lus < $Lookup[$lup]['SubtableCount']; $lus++) {
                                if (count($Lookup[$lup]['Subtable'][$lus]['subs'])) {
                                    foreach ($Lookup[$lup]['Subtable'][$lus]['subs'] as $luss) {
                                        $lookup_glyphs = $luss['Replace'];
                                        $m_len = count($lookup_glyphs);
                                        // Only apply if the (first) 'Replace' glyph from the
                                        // Lookup list is in the [inputGlyphs] at ['SequenceIndex']
                                        // then apply the substitution
                                        if (strpos($input_glyphs[$seq_index], $lookup_glyphs[0]) === false) {
                                            continue;
                                        }
                                        // Returns e.g. ¦(0612)¦(ignore) (0613)¦(ignore) (0614)¦
                                        $context_input_match = $this->_make_gsu_bcontext_input_match($input_glyphs, $ignore, $lookup_glyphs, $seq_index);
                                        $REPL = implode(" ", $luss['substitute']);
                                        if (strpos("isol fina fin2 fin3 medi med2 init ", $tag) !== false && $scripttag == 'arab') {
                                            $volt[] = ['match' => $lookup_glyphs[0], 'replace' => $REPL, 'tag' => $tag, 'prel' => $backtrack_glyphs, 'postl' => $lookahead_glyphs, 'ignore' => $ignore];
                                        } else {
                                            $sub_rule['rules'][] = ['type' => $Lookup[$lup]['Type'], 'match' => $lookup_glyphs, 'replace' => $luss['substitute'], 'seqIndex' => $seq_index, 'key' => $lookup_glyphs[0]];
                                        }
                                    }
                                }
                            }
                        }
                        if (count($sub_rule['rules'])) {
                            $volt[] = $sub_rule;
                        }
                    }
                } elseif ($Lookup[$i]['Type'] == 6) {
                    // Format 1: Simple Chaining Context Glyph Substitution  p255
                    if ($subst_format == 1) {
                        $ignore = $this->_get_gsu_bignore_string($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
                        for ($s = 0; $s < $Lookup[$i]['Subtable'][$c]['ChainSubRuleSetCount']; $s++) {
                            // ChainSubRuleSet
                            $sub_rule = [];
                            $first_input_glyph = $Lookup[$i]['Subtable'][$c]['CoverageGlyphs'][$s];
                            // First input gyyph
                            foreach ($Lookup[$i]['Subtable'][$c]['ChainSubRuleSet'][$s]['ChainSubRule'] as $rule) {
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
                                $backtrack_match = $this->_make_gsu_bbacktrack_match($backtrack_glyphs, $ignore);
                                if ($rule['LookaheadGlyphCount']) {
                                    $lookahead_glyphs = $rule['LookaheadGlyphs'];
                                } else {
                                    $lookahead_glyphs = [];
                                }
                                $lookahead_match = $this->_make_gsu_blookahead_match($lookahead_glyphs, $ignore);
                                $n_bsubs = 2 * count($backtrack_glyphs);
                                $n_isubs = 2 * $n_input - 1;
                                $context_input_match = $this->_make_gsu_bcontext_input_match($input_glyphs, $ignore, [], 0);
                                $sub_rule = ['context' => 1, 'tag' => $tag, 'matchback' => $backtrack_match, 'match' => $context_input_match . $lookahead_match, 'nBacktrack' => count($backtrack_glyphs), 'nInput' => $n_input, 'nLookahead' => count($lookahead_glyphs), 'rules' => []];
                                for ($b = 0; $b < $rule['SubstCount']; $b++) {
                                    $lup = $rule['LookupListIndex'][$b];
                                    $seq_index = $rule['SequenceIndex'][$b];
                                    // $Lookup[$lup] = secondary Lookup
                                    for ($lus = 0; $lus < $Lookup[$lup]['SubtableCount']; $lus++) {
                                        if (count($Lookup[$lup]['Subtable'][$lus]['subs'])) {
                                            foreach ($Lookup[$lup]['Subtable'][$lus]['subs'] as $luss) {
                                                $lookup_glyphs = $luss['Replace'];
                                                $m_len = count($lookup_glyphs);
                                                // Only apply if the (first) 'Replace' glyph from the
                                                // Lookup list is in the [inputGlyphs] at ['SequenceIndex']
                                                // then apply the substitution
                                                if (strpos($input_glyphs[$seq_index], $lookup_glyphs[0]) === false) {
                                                    continue;
                                                }
                                                // Returns e.g. ¦(0612)¦(ignore) (0613)¦(ignore) (0614)¦
                                                $context_input_match = $this->_make_gsu_bcontext_input_match($input_glyphs, $ignore, $lookup_glyphs, $seq_index);
                                                $REPL = implode(" ", $luss['substitute']);
                                                if (strpos("isol fina fin2 fin3 medi med2 init ", $tag) !== false && $scripttag == 'arab') {
                                                    $volt[] = ['match' => $lookup_glyphs[0], 'replace' => $REPL, 'tag' => $tag, 'prel' => $backtrack_glyphs, 'postl' => $lookahead_glyphs, 'ignore' => $ignore];
                                                } else {
                                                    $sub_rule['rules'][] = ['type' => $Lookup[$lup]['Type'], 'match' => $lookup_glyphs, 'replace' => $luss['substitute'], 'seqIndex' => $seq_index, 'key' => $lookup_glyphs[0]];
                                                }
                                            }
                                        }
                                    }
                                }
                                if (count($sub_rule['rules'])) {
                                    $volt[] = $sub_rule;
                                }
                            }
                        }
                    } elseif ($subst_format == 2) {
                        $ignore = $this->_get_gsu_bignore_string($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
                        foreach ($Lookup[$i]['Subtable'][$c]['ChainSubClassSet'] as $input_class => $cscs) {
                            for ($cscrule = 0; $cscrule < $cscs['ChainSubClassRuleCnt']; $cscrule++) {
                                $rule = $cscs['ChainSubClassRule'][$cscrule];
                                // These contain classes of glyphs as strings
                                // $Lookup[$i]['Subtable'][$c]['InputClasses'][(class)] e.g. 02E6|02E7|02E8
                                // $Lookup[$i]['Subtable'][$c]['LookaheadClasses'][(class)]
                                // $Lookup[$i]['Subtable'][$c]['BacktrackClasses'][(class)]
                                // These contain arrays of classIndexes
                                // [Backtrack] [Lookahead] and [Input] (Input is from the second position only)
                                $input_glyphs = [];
                                if (isset($Lookup[$i]['Subtable'][$c]['InputClasses'][$input_class])) {
                                    $input_glyphs[0] = $Lookup[$i]['Subtable'][$c]['InputClasses'][$input_class];
                                } else {
                                    $input_glyphs[0] = '';
                                }
                                if ($rule['InputGlyphCount'] > 1) {
                                    //  NB starts at 1
                                    for ($gcl = 1; $gcl < $rule['InputGlyphCount']; $gcl++) {
                                        $classindex = $rule['Input'][$gcl];
                                        if (isset($Lookup[$i]['Subtable'][$c]['InputClasses'][$classindex])) {
                                            $input_glyphs[$gcl] = $Lookup[$i]['Subtable'][$c]['InputClasses'][$classindex];
                                        } else {
                                            $input_glyphs[$gcl] = '';
                                        }
                                    }
                                }
                                $n_input = $rule['InputGlyphCount'];
                                if ($rule['BacktrackGlyphCount']) {
                                    for ($gcl = 0; $gcl < $rule['BacktrackGlyphCount']; $gcl++) {
                                        $classindex = $rule['Backtrack'][$gcl];
                                        if (isset($Lookup[$i]['Subtable'][$c]['BacktrackClasses'][$classindex])) {
                                            $backtrack_glyphs[$gcl] = $Lookup[$i]['Subtable'][$c]['BacktrackClasses'][$classindex];
                                        } else {
                                            $backtrack_glyphs[$gcl] = '';
                                        }
                                    }
                                } else {
                                    $backtrack_glyphs = [];
                                }
                                // Returns e.g. ¦(FEEB|FEEC)(ignore) ¦(FD12|FD13)(ignore) ¦
                                $backtrack_match = $this->_make_gsu_bbacktrack_match($backtrack_glyphs, $ignore);
                                if ($rule['LookaheadGlyphCount']) {
                                    for ($gcl = 0; $gcl < $rule['LookaheadGlyphCount']; $gcl++) {
                                        $classindex = $rule['Lookahead'][$gcl];
                                        if (isset($Lookup[$i]['Subtable'][$c]['LookaheadClasses'][$classindex])) {
                                            $lookahead_glyphs[$gcl] = $Lookup[$i]['Subtable'][$c]['LookaheadClasses'][$classindex];
                                        } else {
                                            $lookahead_glyphs[$gcl] = '';
                                        }
                                    }
                                } else {
                                    $lookahead_glyphs = [];
                                }
                                // Returns e.g. ¦(ignore) (FD12|FD13)¦(ignore) (FEEB|FEEC)¦
                                $lookahead_match = $this->_make_gsu_blookahead_match($lookahead_glyphs, $ignore);
                                $n_bsubs = 2 * count($backtrack_glyphs);
                                $n_isubs = 2 * $n_input - 1;
                                $context_input_match = $this->_make_gsu_bcontext_input_match($input_glyphs, $ignore, [], 0);
                                $sub_rule = ['context' => 1, 'tag' => $tag, 'matchback' => $backtrack_match, 'match' => $context_input_match . $lookahead_match, 'nBacktrack' => count($backtrack_glyphs), 'nInput' => $n_input, 'nLookahead' => count($lookahead_glyphs), 'rules' => []];
                                for ($b = 0; $b < $rule['SubstCount']; $b++) {
                                    $lup = $rule['LookupListIndex'][$b];
                                    $seq_index = $rule['SequenceIndex'][$b];
                                    // $Lookup[$lup] = secondary Lookup
                                    for ($lus = 0; $lus < $Lookup[$lup]['SubtableCount']; $lus++) {
                                        if (count($Lookup[$lup]['Subtable'][$lus]['subs'])) {
                                            foreach ($Lookup[$lup]['Subtable'][$lus]['subs'] as $luss) {
                                                $lookup_glyphs = $luss['Replace'];
                                                $m_len = count($lookup_glyphs);
                                                // Only apply if the (first) 'Replace' glyph from the
                                                // Lookup list is in the [inputGlyphs] at ['SequenceIndex']
                                                // then apply the substitution
                                                if (strpos($input_glyphs[$seq_index], $lookup_glyphs[0]) === false) {
                                                    continue;
                                                }
                                                // Returns e.g. ¦(0612)¦(ignore) (0613)¦(ignore) (0614)¦
                                                $context_input_match = $this->_make_gsu_bcontext_input_match($input_glyphs, $ignore, $lookup_glyphs, $seq_index);
                                                $REPL = implode(" ", $luss['substitute']);
                                                // Returns e.g. "REPL\${6}\${8}" or "\${1}\${2} \${3} REPL\${4}\${6}\${8} \${9}"
                                                if (strpos("isol fina fin2 fin3 medi med2 init ", $tag) !== false && $scripttag == 'arab') {
                                                    $volt[] = ['match' => $lookup_glyphs[0], 'replace' => $REPL, 'tag' => $tag, 'prel' => $backtrack_glyphs, 'postl' => $lookahead_glyphs, 'ignore' => $ignore];
                                                } else {
                                                    $sub_rule['rules'][] = ['type' => $Lookup[$lup]['Type'], 'match' => $lookup_glyphs, 'replace' => $luss['substitute'], 'seqIndex' => $seq_index, 'key' => $lookup_glyphs[0]];
                                                }
                                            }
                                        }
                                    }
                                }
                                if (count($sub_rule['rules'])) {
                                    $volt[] = $sub_rule;
                                }
                            }
                        }
                    } elseif ($subst_format == 3) {
                        // IgnoreMarks flag set on main Lookup table
                        $ignore = $this->_get_gsu_bignore_string($Lookup[$i]['Flag'], $Lookup[$i]['MarkFilteringSet']);
                        $input_glyphs = $Lookup[$i]['Subtable'][$c]['CoverageInputGlyphs'];
                        $coverage_input_glyphs = implode('|', $input_glyphs);
                        $n_input = $Lookup[$i]['Subtable'][$c]['InputGlyphCount'];
                        if ($Lookup[$i]['Subtable'][$c]['BacktrackGlyphCount']) {
                            $backtrack_glyphs = $Lookup[$i]['Subtable'][$c]['CoverageBacktrackGlyphs'];
                        } else {
                            $backtrack_glyphs = [];
                        }
                        // Returns e.g. ¦(FEEB|FEEC)(ignore) ¦(FD12|FD13)(ignore) ¦
                        $backtrack_match = $this->_make_gsu_bbacktrack_match($backtrack_glyphs, $ignore);
                        if ($Lookup[$i]['Subtable'][$c]['LookaheadGlyphCount']) {
                            $lookahead_glyphs = $Lookup[$i]['Subtable'][$c]['CoverageLookaheadGlyphs'];
                        } else {
                            $lookahead_glyphs = [];
                        }
                        // Returns e.g. ¦(ignore) (FD12|FD13)¦(ignore) (FEEB|FEEC)¦
                        $lookahead_match = $this->_make_gsu_blookahead_match($lookahead_glyphs, $ignore);
                        $n_bsubs = 2 * count($backtrack_glyphs);
                        $n_isubs = 2 * $n_input - 1;
                        $context_input_match = $this->_make_gsu_bcontext_input_match($input_glyphs, $ignore, [], 0);
                        $sub_rule = ['context' => 1, 'tag' => $tag, 'matchback' => $backtrack_match, 'match' => $context_input_match . $lookahead_match, 'nBacktrack' => count($backtrack_glyphs), 'nInput' => $n_input, 'nLookahead' => count($lookahead_glyphs), 'rules' => []];
                        for ($b = 0; $b < $Lookup[$i]['Subtable'][$c]['SubstCount']; $b++) {
                            $lup = $Lookup[$i]['Subtable'][$c]['SubstLookupRecord'][$b]['LookupListIndex'];
                            $seq_index = $Lookup[$i]['Subtable'][$c]['SubstLookupRecord'][$b]['SequenceIndex'];
                            for ($lus = 0; $lus < $Lookup[$lup]['SubtableCount']; $lus++) {
                                if (empty($Lookup[$lup]['Subtable'][$lus]['subs']) || !is_array($Lookup[$lup]['Subtable'][$lus]['subs'])) {
                                    continue;
                                }
                                foreach ($Lookup[$lup]['Subtable'][$lus]['subs'] as $luss) {
                                    $lookup_glyphs = $luss['Replace'];
                                    // Only apply if the (first) 'Replace' glyph from the
                                    // Lookup list is in the [inputGlyphs] at ['SequenceIndex']
                                    // then apply the substitution
                                    if (strpos($input_glyphs[$seq_index], $lookup_glyphs[0]) === false) {
                                        continue;
                                    }
                                    // Returns e.g. ¦(0612)¦(ignore) (0613)¦(ignore) (0614)¦
                                    $context_input_match = $this->_make_gsu_bcontext_input_match($input_glyphs, $ignore, $lookup_glyphs, $seq_index);
                                    $REPL = implode(" ", $luss['substitute']);
                                    if (strpos("isol fina fin2 fin3 medi med2 init ", $tag) !== false && $scripttag == 'arab') {
                                        $volt[] = ['match' => $lookup_glyphs[0], 'replace' => $REPL, 'tag' => $tag, 'prel' => $backtrack_glyphs, 'postl' => $lookahead_glyphs, 'ignore' => $ignore];
                                    } else {
                                        $sub_rule['rules'][] = ['type' => $Lookup[$lup]['Type'], 'match' => $lookup_glyphs, 'replace' => $luss['substitute'], 'seqIndex' => $seq_index, 'key' => $lookup_glyphs[0]];
                                    }
                                }
                            }
                        }
                        if (count($sub_rule['rules'])) {
                            $volt[] = $sub_rule;
                        }
                    }
                }
            }
        }
        return $volt;
    }
    function _check_gsu_bignore($flag, $glyph, $mark_filtering_set)
    {
        $ignore = false;
        // Flag & 0x0008 = Ignore Marks - (unless already done with MarkAttachmentType)
        if (($flag & 0x8) == 0x8 && ($flag & 0xff00) == 0 && strpos($this->glyph_class_marks, $glyph)) {
            $ignore = true;
        }
        if (($flag & 0x4) == 0x4 && strpos($this->glyph_class_ligatures, $glyph)) {
            $ignore = true;
        }
        if (($flag & 0x2) == 0x2 && strpos($this->glyph_class_bases, $glyph)) {
            $ignore = true;
        }
        // Flag & 0xFF?? = MarkAttachmentType
        if ($flag & 0xff00) {
            // "a lookup must ignore any mark glyphs that are not in the specified mark attachment class"
            // $this->MarkAttachmentType is already adjusted for this i.e. contains all Marks except those in the MarkAttachmentClassDef table
            if (strpos($this->mark_attachment_type[$flag >> 8], $glyph)) {
                $ignore = true;
            }
        }
        // Flag & 0x0010 = UseMarkFilteringSet
        if ($flag & 0x10 && strpos($this->mark_glyph_sets[$mark_filtering_set], $glyph)) {
            $ignore = true;
        }
        return $ignore;
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
            // "a lookup must ignore any mark glyphs that are not in the specified mark attachment class"
            // $this->MarkAttachmentType is already adjusted for this i.e. contains all Marks except those in the MarkAttachmentClassDef table
            $mark_attachment_type = $flag >> 8;
            $ignoreflag = $flag;
            $str = $this->mark_attachment_type[$mark_attachment_type];
        }
        // Flag & 0x0010 = UseMarkFilteringSet
        if ($flag & 0x10) {
            throw new \Mpdf\Exception\Font_Exception("Font \"" . $this->fontkey . "\" contains MarkGlyphSets which is not supported");
            $str = $this->mark_glyph_sets[$mark_filtering_set];
        }
        // If Ignore Marks set, supercedes any above
        // Flag & 0x0008 = Ignore Marks - (unless already done with MarkAttachmentType)
        if (($flag & 0x8) == 0x8 && ($flag & 0xff00) == 0) {
            $ignoreflag = 8;
            $str = $this->glyph_class_marks;
        }
        // Flag & 0x0004 = Ignore Ligatures
        if (($flag & 0x4) == 0x4) {
            $ignoreflag += 4;
            if ($str) {
                $str .= "|";
            }
            $str .= $this->glyph_class_ligatures;
        }
        // Flag & 0x0002 = Ignore BaseGlyphs
        if (($flag & 0x2) == 0x2) {
            $ignoreflag += 2;
            if ($str) {
                $str .= "|";
            }
            $str .= $this->glyph_class_bases;
        }
        if ($str) {
            // This originally returned e.g. ((?:(?:[IGNORE8]))*) when NOT specific to a Lookup e.g. rtlSub in
            // arabictypesetting.GSUB.arab.DFLT.php
            // This would save repeatedly saving long text strings if used multiple times
            // When writing e.g. arabictypesetting.GSUB.arab.DFLT.php to file, included as $ignore[8]
            // Would need to also write the $ignore array to that file
            //		// If UseMarkFilteringSet (specific to the Lookup) return the string
            //		if (($flag & 0x0010) && ($flag & 0x0008) != 0x0008) {
            //			return "((?:(?:" . $str . "))*)";
            //		}
            //		else { return "((?:(?:" . "[IGNORE".$ignoreflag."]" . "))*)"; }
            //		// e.g. ((?:(?: 0031| 0032| 0033| 0034| 0045))*)
            // But never finished coding it to add the $ignore array to the file, and it doesn't seem to occur often enough to be worth
            // writing. So just output it as a string:
            return "((?:(?:" . $str . "))*)";
        } else {
            return "()";
        }
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
    function _make_gsu_bcontext_input_match($input_glyphs, $ignore, $lookup_glyphs, $seq_index)
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
                $str .= "(" . $lookup_glyphs[$i - $seq_index] . ")";
            } else {
                $str .= "(" . $input_glyphs[$i] . ")";
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
            $str .= "(" . $input_glyphs[$i - 1] . ")";
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
            $str .= "(" . $backtrack_glyphs[$i] . ")" . $ignore . " ";
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
            $str .= $ignore . " (" . $lookahead_glyphs[$i] . ")";
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
        } elseif ($n_input > 1) {
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
    function _get_coverage($convert2hex = true, $mode = 1)
    {
        $g = [];
        $ctr = 0;
        $coverage_format = $this->read_ushort();
        if ($coverage_format == 1) {
            $coverage_glyph_count = $this->read_ushort();
            for ($gid = 0; $gid < $coverage_glyph_count; $gid++) {
                $glyph_id = $this->read_ushort();
                $uni = $this->glyph_to_char[$glyph_id][0];
                if ($convert2hex) {
                    $g[] = unicode_hex($uni);
                } elseif ($mode == 2) {
                    $g[$uni] = $ctr;
                    $ctr++;
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
                for ($glyph_id = $start; $glyph_id <= $end; $glyph_id++) {
                    $uni = $this->glyph_to_char[$glyph_id][0];
                    if ($convert2hex) {
                        $g[] = unicode_hex($uni);
                    } elseif ($mode == 2) {
                        $uni = $g[$uni] = $ctr;
                        $ctr++;
                    } else {
                        $g[] = $glyph_id;
                    }
                }
            }
        }
        return $g;
    }
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
                    if (isset($this->glyph_to_char[$g][0])) {
                        $glyph_by_class[$class][] = unicode_hex($this->glyph_to_char[$g][0]);
                    }
                }
            }
        } elseif ($class_format == 2) {
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
    function _get_gpo_stables()
    {
        ///////////////////////////////////
        // GPOS - Glyph Positioning
        ///////////////////////////////////
        if (!isset($this->tables["GPOS"])) {
            return [[], [], []];
        }
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
        // Feauture List => LookupListIndex es
        $this->seek($feature_list_offset);
        $feature_count = $this->read_ushort();
        $Feature = [];
        for ($i = 0; $i < $feature_count; $i++) {
            $tag = $this->read_tag();
            if ($tag === 'kern') {
                $this->haskern_gpos = true;
            }
            $Feature[$i] = ['tag' => $tag];
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
            if (($flag & 0x10) === 0x10) {
                $Lookup[$i]['MarkFilteringSet'] = $this->read_ushort();
            } else {
                $Lookup[$i]['MarkFilteringSet'] = '';
            }
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
        // Process Whole LookupList - Get LuCoverage = Lookup coverage just for first glyph
        $this->lu_coverage = [];
        for ($i = 0; $i < $lookup_count; $i++) {
            for ($c = 0; $c < $Lookup[$i]['SubtableCount']; $c++) {
                $this->seek($Lookup[$i]['Subtables'][$c]);
                $pos_format = $this->read_ushort();
                if ($Lookup[$i]['Type'] == 7 && $pos_format == 3) {
                    $this->skip(4);
                } elseif ($Lookup[$i]['Type'] == 8 && $pos_format == 3) {
                    $backtrack_glyph_count = $this->read_ushort();
                    $this->skip(2 * $backtrack_glyph_count + 2);
                }
                // NB Coverage only looks at glyphs for position 1 (i.e. 7.3 and 8.3)	// NEEDS TO READ ALL ********************
                // NB For e.g. Type 4, this may be the Coverage for the Mark
                $Coverage = $Lookup[$i]['Subtables'][$c] + $this->read_ushort();
                $this->seek($Coverage);
                $glyphs = $this->_get_coverage(false, 2);
                $this->lu_coverage[$i][$c] = $glyphs;
            }
        }
        $this->font_cache->json_write($this->fontkey . '.GPOSdata.json', $this->lu_coverage);
        return [$gpos_script_lang, $gpos, $Lookup];
    }
    function make_subset($file, &$subset, $tt_cfont_id = 0, $debug = false, $use_otl = false)
    {
        $this->use_otl = $use_otl;
        $this->filename = $file;
        $this->fh = fopen($file, 'rb');
        if (!$this->fh) {
            throw new \Mpdf\Exception\Font_Exception(sprintf('Unable to open file %s', $file));
        }
        $this->_pos = 0;
        $this->char_widths = '';
        $this->glyph_pos = [];
        $this->char_to_glyph = [];
        $this->tables = [];
        $this->otables = [];
        $this->ascent = 0;
        $this->descent = 0;
        $this->strikeout_size = 0;
        $this->strikeout_position = 0;
        $this->num_ttc_fonts = 0;
        $this->ttc_fonts = [];
        $this->skip(4);
        $this->max_uni = 0;
        if ($tt_cfont_id > 0) {
            $this->version = $version = $this->read_ulong();
            // TTC Header version now
            if (!in_array($version, [0x10000, 0x20000], true)) {
                throw new \Mpdf\Exception\Font_Exception(sprintf('Error parsing TrueType Collection: version=%s - %s', $version, $file));
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
        // head - Font header table
        $this->seek_table('head');
        $this->skip(50);
        $index_to_loc_format = $this->read_ushort();
        $glyph_data_format = $this->read_ushort();
        // hhea - Horizontal header table
        $this->seek_table('hhea');
        $this->skip(32);
        $metric_data_format = $this->read_ushort();
        $orign_hmetrics = $number_of_h_metrics = $this->read_ushort();
        // maxp - Maximum profile table
        $this->seek_table('maxp');
        $this->skip(4);
        $num_glyphs = $this->read_ushort();
        // cmap - Character to glyph index mapping table
        $cmap_offset = $this->seek_table('cmap');
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
                    $unicode_cmap_offset = $cmap_offset + $offset;
                    break;
                }
            }
            $this->seek($save_pos);
        }
        if (!$unicode_cmap_offset) {
            throw new \Mpdf\Exception\Font_Exception(sprintf('Font "%s" does not have Unicode cmap (platform 3, encoding 1, format 4, or platform 0 [any encoding] format 4)', $this->filename));
        }
        $glyph_to_char = [];
        $char_to_glyph = [];
        $this->get_cmap4($unicode_cmap_offset, $glyph_to_char, $char_to_glyph);
        // Map Unmapped glyphs - from $numGlyphs
        if ($use_otl) {
            $bctr = 0xe000;
            for ($gid = 1; $gid < $num_glyphs; $gid++) {
                if (!isset($glyph_to_char[$gid])) {
                    while (isset($char_to_glyph[$bctr])) {
                        $bctr++;
                    }
                    // Avoid overwriting a glyph already mapped in PUA
                    if ($bctr > 0xf8ff) {
                        throw new \Mpdf\Exception\Font_Exception($file . " : WARNING - Font cannot map all included glyphs into Private Use Area U+E000 - U+F8FF; cannot use useOTL on this font");
                    }
                    $glyph_to_char[$gid][] = $bctr;
                    $char_to_glyph[$bctr] = $gid;
                    $bctr++;
                }
            }
        }
        $this->char_to_glyph = $char_to_glyph;
        $this->glyph_to_char = $glyph_to_char;
        // hmtx - Horizontal metrics table
        $scale = 1;
        // not used
        $this->get_hmtx($number_of_h_metrics, $num_glyphs, $glyph_to_char, $scale);
        // loca - Index to location
        $this->get_loca($index_to_loc_format, $num_glyphs);
        $subsetglyphs = [0 => 0, 1 => 1, 2 => 2];
        $subset_char_to_glyph = [];
        foreach ($subset as $code) {
            if (isset($this->char_to_glyph[$code])) {
                $subsetglyphs[$this->char_to_glyph[$code]] = $code;
                // Old Glyph ID => Unicode
                $subset_char_to_glyph[$code] = $this->char_to_glyph[$code];
                // Unicode to old GlyphID
            }
            $this->max_uni = max($this->max_uni, $code);
        }
        list($start, $dummy) = $this->get_table_pos('glyf');
        $glyph_set = [];
        ksort($subsetglyphs);
        $n = 0;
        $fs_last_char_index = 0;
        // maximum Unicode index (character code) in this font, according to the cmap subtable for platform ID 3 and platform- specific encoding ID 0 or 1.
        foreach ($subsetglyphs as $original_glyph_idx => $uni) {
            $fs_last_char_index = max($fs_last_char_index, $uni);
            $glyph_set[$original_glyph_idx] = $n;
            // old glyphID to new glyphID
            $n++;
        }
        $code_to_glyph = [];
        ksort($subset_char_to_glyph);
        foreach ($subset_char_to_glyph as $uni => $original_glyph_idx) {
            $code_to_glyph[$uni] = $glyph_set[$original_glyph_idx];
        }
        $this->code_to_glyph = $code_to_glyph;
        ksort($subsetglyphs);
        foreach ($subsetglyphs as $original_glyph_idx => $uni) {
            $this->get_glyphs($original_glyph_idx, $start, $glyph_set, $subsetglyphs);
        }
        $num_glyphs = $number_of_h_metrics = count($subsetglyphs);
        // name - table copied from the original
        // MS spec says that "Platform and encoding ID's in the name table should be consistent with those in the cmap table.
        // If they are not, the font will not load in Windows"
        // Doesn't seem to be a problem?
        $this->add('name', $this->get_table('name'));
        // tables copied from the original
        $tags = ['cvt ', 'fpgm', 'prep', 'gasp'];
        foreach ($tags as $tag) {
            if (isset($this->tables[$tag])) {
                $this->add($tag, $this->get_table($tag));
            }
        }
        // post - PostScript
        if (isset($this->tables['post'])) {
            $opost = $this->get_table('post');
            $post = "\x00\x03\x00\x00" . substr($opost, 4, 12) . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
            $this->add('post', $post);
        }
        // Sort CID2GID map into segments of contiguous codes
        ksort($code_to_glyph);
        unset($code_to_glyph[0]);
        $rangeid = 0;
        $range = [];
        $prevcid = -2;
        $prevglidx = -1;
        // for each character
        foreach ($code_to_glyph as $cid => $glidx) {
            if ($cid == $prevcid + 1 && $glidx == $prevglidx + 1) {
                $range[$rangeid][] = $glidx;
            } else {
                // new range
                $rangeid = $cid;
                $range[$rangeid] = [];
                $range[$rangeid][] = $glidx;
            }
            $prevcid = $cid;
            $prevglidx = $glidx;
        }
        // cmap - Character to glyph mapping
        $seg_count = count($range) + 1;
        // + 1 Last segment has missing character 0xFFFF
        $search_range = 1;
        $entry_selector = 0;
        while ($search_range * 2 <= $seg_count) {
            $search_range *= 2;
            ++$entry_selector;
        }
        $search_range *= 2;
        $range_shift = $seg_count * 2 - $search_range;
        $length = 16 + 8 * $seg_count + ($num_glyphs + 1);
        $cmap = [
            0,
            3,
            // Index : version, number of encoding subtables
            0,
            0,
            // Encoding Subtable : platform (UNI=0), encoding 0
            0,
            28,
            // Encoding Subtable : offset (hi,lo)
            0,
            3,
            // Encoding Subtable : platform (UNI=0), encoding 3
            0,
            28,
            // Encoding Subtable : offset (hi,lo)
            3,
            1,
            // Encoding Subtable : platform (MS=3), encoding 1
            0,
            28,
            // Encoding Subtable : offset (hi,lo)
            4,
            $length,
            0,
            // Format 4 Mapping subtable: format, length, language
            $seg_count * 2,
            $search_range,
            $entry_selector,
            $range_shift,
        ];
        // endCode(s)
        foreach ($range as $start => $subrange) {
            $end_code = $start + (count($subrange) - 1);
            $cmap[] = $end_code;
            // endCode(s)
        }
        $cmap[] = 0xffff;
        // endCode of last Segment
        $cmap[] = 0;
        // reservedPad
        // startCode(s)
        foreach ($range as $start => $subrange) {
            $cmap[] = $start;
            // startCode(s)
        }
        $cmap[] = 0xffff;
        // startCode of last Segment
        // idDelta(s)
        foreach ($range as $start => $subrange) {
            $id_delta = -($start - $subrange[0]);
            $n += count($subrange);
            $cmap[] = $id_delta;
            // idDelta(s)
        }
        $cmap[] = 1;
        // idDelta of last Segment
        // idRangeOffset(s)
        foreach ($range as $subrange) {
            $cmap[] = 0;
            // idRangeOffset[segCount]  	Offset in bytes to glyph indexArray, or 0
        }
        $cmap[] = 0;
        // idRangeOffset of last Segment
        foreach ($range as $subrange) {
            foreach ($subrange as $glidx) {
                $cmap[] = $glidx;
            }
        }
        $cmap[] = 0;
        // Mapping for last character
        $cmapstr = '';
        foreach ($cmap as $cm) {
            $cmapstr .= pack('n', $cm);
        }
        $this->add('cmap', $cmapstr);
        // glyf - Glyph data
        list($glyf_offset, $glyf_length) = $this->get_table_pos('glyf');
        if ($glyf_length < $this->max_str_len_read) {
            $glyph_data = $this->get_table('glyf');
        }
        $offsets = [];
        $glyf = '';
        $pos = 0;
        $hmtxstr = '';
        $x_min_t = 0;
        $y_min_t = 0;
        $x_max_t = 0;
        $y_max_t = 0;
        $advance_width_max = 0;
        $min_left_side_bearing = 0;
        $min_right_side_bearing = 0;
        $x_max_extent = 0;
        $max_points = 0;
        // points in non-compound glyph
        $max_contours = 0;
        // contours in non-compound glyph
        $max_component_points = 0;
        // points in compound glyph
        $max_component_contours = 0;
        // contours in compound glyph
        $max_component_elements = 0;
        // number of glyphs referenced at top level
        $max_component_depth = 0;
        // levels of recursion, set to 0 if font has only simple glyphs
        $this->glyphdata = [];
        foreach ($subsetglyphs as $original_glyph_idx => $uni) {
            // hmtx - Horizontal Metrics
            $hm = $this->get_h_metric($orign_hmetrics, $original_glyph_idx);
            $hmtxstr .= $hm;
            $offsets[] = $pos;
            $glyph_pos = $this->glyph_pos[$original_glyph_idx];
            $glyph_len = $this->glyph_pos[$original_glyph_idx + 1] - $glyph_pos;
            if ($glyf_length < $this->max_str_len_read) {
                $data = substr($glyph_data, $glyph_pos, $glyph_len);
            } else if ($glyph_len > 0) {
                $data = $this->get_chunk($glyf_offset + $glyph_pos, $glyph_len);
            } else {
                $data = '';
            }
            if ($glyph_len > 0) {
                if (_RECALC_PROFILE) {
                    $x_min = $this->unpack_short(substr($data, 2, 2));
                    $y_min = $this->unpack_short(substr($data, 4, 2));
                    $x_max = $this->unpack_short(substr($data, 6, 2));
                    $y_max = $this->unpack_short(substr($data, 8, 2));
                    $x_min_t = min($x_min_t, $x_min);
                    $y_min_t = min($y_min_t, $y_min);
                    $x_max_t = max($x_max_t, $x_max);
                    $y_max_t = max($y_max_t, $y_max);
                    $aw = $this->unpack_short(substr($hm, 0, 2));
                    $lsb = $this->unpack_short(substr($hm, 2, 2));
                    $advance_width_max = max($advance_width_max, $aw);
                    $min_left_side_bearing = min($min_left_side_bearing, $lsb);
                    $min_right_side_bearing = min($min_right_side_bearing, $aw - $lsb - ($x_max - $x_min));
                    $x_max_extent = max($x_max_extent, $lsb + ($x_max - $x_min));
                }
                $up = unpack("n", substr($data, 0, 2));
            }
            if ($glyph_len > 2 && $up[1] & 1 << 15) {
                // If number of contours <= -1 i.e. composiste glyph
                $pos_in_glyph = 10;
                $flags = Glyph_Operator::MORE;
                $n_component_elements = 0;
                while ($flags & Glyph_Operator::MORE) {
                    $n_component_elements += 1;
                    // number of glyphs referenced at top level
                    $up = unpack("n", substr($data, $pos_in_glyph, 2));
                    $flags = $up[1];
                    $up = unpack("n", substr($data, $pos_in_glyph + 2, 2));
                    $glyph_idx = $up[1];
                    $this->glyphdata[$original_glyph_idx]['compGlyphs'][] = $glyph_idx;
                    $data = $this->_set_ushort($data, $pos_in_glyph + 2, $glyph_set[$glyph_idx]);
                    $pos_in_glyph += 4;
                    if ($flags & Glyph_Operator::WORDS) {
                        $pos_in_glyph += 4;
                    } else {
                        $pos_in_glyph += 2;
                    }
                    if ($flags & Glyph_Operator::SCALE) {
                        $pos_in_glyph += 2;
                    } elseif ($flags & Glyph_Operator::XYSCALE) {
                        $pos_in_glyph += 4;
                    } elseif ($flags & Glyph_Operator::TWOBYTWO) {
                        $pos_in_glyph += 8;
                    }
                }
                $max_component_elements = max($max_component_elements, $n_component_elements);
            } elseif (_RECALC_PROFILE && $glyph_len > 2 && $up[1] < 1 << 15 && $up[1] > 0) {
                // Number of contours > 0 simple glyph
                $n_contours = $up[1];
                $this->glyphdata[$original_glyph_idx]['nContours'] = $n_contours;
                $max_contours = max($max_contours, $n_contours);
                // Count number of points in simple glyph
                $pos_in_glyph = 10 + $n_contours * 2 - 2;
                // Last endContourPoint
                $up = unpack("n", substr($data, $pos_in_glyph, 2));
                $points = $up[1] + 1;
                $this->glyphdata[$original_glyph_idx]['nPoints'] = $points;
                $max_points = max($max_points, $points);
            }
            $glyf .= $data;
            $pos += $glyph_len;
            if ($pos % 4 != 0) {
                $padding = 4 - $pos % 4;
                $glyf .= str_repeat("\x00", $padding);
                $pos += $padding;
            }
        }
        if (_RECALC_PROFILE) {
            foreach ($this->glyphdata as $original_glyph_idx => $val) {
                $maxdepth = $depth = -1;
                $points = 0;
                $contours = 0;
                $this->get_glyph_data($original_glyph_idx, $maxdepth, $depth, $points, $contours);
                $max_component_depth = max($max_component_depth, $maxdepth);
                $max_component_points = max($max_component_points, $points);
                $max_component_contours = max($max_component_contours, $contours);
            }
        }
        $offsets[] = $pos;
        $this->add('glyf', $glyf);
        // hmtx - Horizontal Metrics
        $this->add('hmtx', $hmtxstr);
        // loca - Index to location
        $locastr = '';
        if ($pos + 1 >> 1 > 0xffff) {
            $index_to_loc_format = 1;
            // long format
            foreach ($offsets as $offset) {
                $locastr .= pack("N", $offset);
            }
        } else {
            $index_to_loc_format = 0;
            // short format
            foreach ($offsets as $offset) {
                $locastr .= pack("n", $offset / 2);
            }
        }
        $this->add('loca', $locastr);
        // head - Font header
        $head = $this->get_table('head');
        $head = $this->_set_ushort($head, 50, $index_to_loc_format);
        if (_RECALC_PROFILE) {
            $head = $this->_set_short($head, 36, $x_min_t);
            // for all glyph bounding boxes
            $head = $this->_set_short($head, 38, $y_min_t);
            // for all glyph bounding boxes
            $head = $this->_set_short($head, 40, $x_max_t);
            // for all glyph bounding boxes
            $head = $this->_set_short($head, 42, $y_max_t);
            // for all glyph bounding boxes
            $head[17] = chr($head[17] & ~(1 << 4));
            // Unset Bit 4 (as hdmx/LTSH tables not included)
        }
        $this->add('head', $head);
        // hhea - Horizontal Header
        $hhea = $this->get_table('hhea');
        $hhea = $this->_set_ushort($hhea, 34, $number_of_h_metrics);
        if (_RECALC_PROFILE) {
            $hhea = $this->_set_ushort($hhea, 10, $advance_width_max);
            $hhea = $this->_set_short($hhea, 12, $min_left_side_bearing);
            $hhea = $this->_set_short($hhea, 14, $min_right_side_bearing);
            $hhea = $this->_set_short($hhea, 16, $x_max_extent);
        }
        $this->add('hhea', $hhea);
        // maxp - Maximum Profile
        $maxp = $this->get_table('maxp');
        $maxp = $this->_set_ushort($maxp, 4, $num_glyphs);
        if (_RECALC_PROFILE) {
            $maxp = $this->_set_ushort($maxp, 6, $max_points);
            // points in non-compound glyph
            $maxp = $this->_set_ushort($maxp, 8, $max_contours);
            // contours in non-compound glyph
            $maxp = $this->_set_ushort($maxp, 10, $max_component_points);
            // points in compound glyph
            $maxp = $this->_set_ushort($maxp, 12, $max_component_contours);
            // contours in compound glyph
            $maxp = $this->_set_ushort($maxp, 28, $max_component_elements);
            // number of glyphs referenced at top level
            $maxp = $this->_set_ushort($maxp, 30, $max_component_depth);
            // levels of recursion, set to 0 if font has only simple glyphs
        }
        $this->add('maxp', $maxp);
        // OS/2 - OS/2
        if (isset($this->tables['OS/2'])) {
            $os2_offset = $this->seek_table("OS/2");
            if (_RECALC_PROFILE) {
                $fs_selection = $this->get_ushort($os2_offset + 62);
                $fs_selection = $fs_selection & ~(1 << 6);
                // 2-byte bit field containing information concerning the nature of the font patterns
                // bit#0 = Italic; bit#5=Bold
                // Match name table's font subfamily string
                // Clear bit#6 used for 'Regular' and optional
            }
            // NB Currently this method never subsets characters above BMP
            // Could set nonBMP bit according to $this->maxUni
            $non_bmp = $this->get_ushort($os2_offset + 46);
            $non_bmp = $non_bmp & ~(1 << 9);
            // Unset Bit 57 (indicates non-BMP) - for interactive forms
            $os2 = $this->get_table('OS/2');
            if (_RECALC_PROFILE) {
                $os2 = $this->_set_ushort($os2, 62, $fs_selection);
                $os2 = $this->_set_ushort($os2, 66, $fs_last_char_index);
                $os2 = $this->_set_ushort($os2, 42, 0x0);
                // ulCharRange (ulUnicodeRange) bits 24-31 | 16-23
                $os2 = $this->_set_ushort($os2, 44, 0x0);
                // ulCharRange (Unicode ranges) bits  8-15 |  0-7
                $os2 = $this->_set_ushort($os2, 46, $non_bmp);
                // ulCharRange (Unicode ranges) bits 56-63 | 48-55
                $os2 = $this->_set_ushort($os2, 48, 0x0);
                // ulCharRange (Unicode ranges) bits 40-47 | 32-39
                $os2 = $this->_set_ushort($os2, 50, 0x0);
                // ulCharRange (Unicode ranges) bits  88-95 | 80-87
                $os2 = $this->_set_ushort($os2, 52, 0x0);
                // ulCharRange (Unicode ranges) bits  72-79 | 64-71
                $os2 = $this->_set_ushort($os2, 54, 0x0);
                // ulCharRange (Unicode ranges) bits  120-127 | 112-119
                $os2 = $this->_set_ushort($os2, 56, 0x0);
                // ulCharRange (Unicode ranges) bits  104-111 | 96-103
            }
            $os2 = $this->_set_ushort($os2, 46, $non_bmp);
            // Unset Bit 57 (indicates non-BMP) - for interactive forms
            $this->add('OS/2', $os2);
        }
        fclose($this->fh);
        // Put the TTF file together
        $stm = '';
        $this->end_tt_file($stm);
        return $stm;
    }
    function make_subset_sip($file, &$subset, $tt_cfont_id = 0, $debug = false, $use_otl = 0)
    {
        $this->fh = fopen($file, 'rb');
        if (!$this->fh) {
            throw new \Mpdf\Exception\Font_Exception(sprintf('Unable to open file "%s"', $file));
        }
        $this->filename = $file;
        $this->_pos = 0;
        $this->use_otl = $use_otl;
        // mPDF 5.7.1
        $this->char_widths = '';
        $this->glyph_pos = [];
        $this->char_to_glyph = [];
        $this->tables = [];
        $this->otables = [];
        $this->ascent = 0;
        $this->descent = 0;
        $this->strikeout_size = 0;
        $this->strikeout_position = 0;
        $this->num_ttc_fonts = 0;
        $this->ttc_fonts = [];
        $this->skip(4);
        if ($tt_cfont_id > 0) {
            $this->version = $version = $this->read_ulong();
            // TTC Header version now
            if (!in_array($version, [0x10000, 0x20000])) {
                throw new \Mpdf\Exception\Font_Exception("ERROR - Error parsing TrueType Collection: version=" . $version . " - " . $file);
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
        // head - Font header table
        $this->seek_table('head');
        $this->skip(50);
        $index_to_loc_format = $this->read_ushort();
        $glyph_data_format = $this->read_ushort();
        // hhea - Horizontal header table
        $this->seek_table('hhea');
        $this->skip(32);
        $metric_data_format = $this->read_ushort();
        $orign_hmetrics = $number_of_h_metrics = $this->read_ushort();
        // maxp - Maximum profile table
        $this->seek_table('maxp');
        $this->skip(4);
        $num_glyphs = $this->read_ushort();
        // cmap - Character to glyph index mapping table
        $cmap_offset = $this->seek_table('cmap');
        $this->skip(2);
        $cmap_table_count = $this->read_ushort();
        $unicode_cmap_offset = 0;
        for ($i = 0; $i < $cmap_table_count; $i++) {
            $platform_id = $this->read_ushort();
            $encoding_id = $this->read_ushort();
            $offset = $this->read_ulong();
            $save_pos = $this->_pos;
            if ($platform_id == 3 && $encoding_id == 10 || $platform_id == 0) {
                // Microsoft, Unicode Format 12 table HKCS
                $format = $this->get_ushort($cmap_offset + $offset);
                if ($format == 12) {
                    $unicode_cmap_offset = $cmap_offset + $offset;
                    break;
                }
            }
            if ($platform_id == 3 && $encoding_id == 1 || $platform_id == 0) {
                // Microsoft, Unicode
                $format = $this->get_ushort($cmap_offset + $offset);
                if ($format == 4) {
                    $unicode_cmap_offset = $cmap_offset + $offset;
                }
            }
            $this->seek($save_pos);
        }
        if (!$unicode_cmap_offset) {
            throw new \Mpdf\Exception\Font_Exception(sprintf('Font "%s" does not have cmap for Unicode (platform 3, encoding 1, format 4, or platform 0, any encoding, format 4)', $file));
        }
        // Format 12 CMAP does characters above Unicode BMP i.e. some HKCS characters U+20000 and above
        if ($format == 12) {
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
                $offset = 0;
                for ($unichar = $start_char_code; $unichar <= $end_char_code; $unichar++) {
                    $glyph = $start_glyph_code + $offset;
                    $offset++;
                    // ZZZ98
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
        // Map Unmapped glyphs - from $numGlyphs
        if ($use_otl) {
            $bctr = 0xe000;
            for ($gid = 1; $gid < $num_glyphs; $gid++) {
                if (!isset($glyph_to_char[$gid])) {
                    while (isset($char_to_glyph[$bctr])) {
                        $bctr++;
                    }
                    // Avoid overwriting a glyph already mapped in PUA
                    // ZZZ98
                    if ($bctr > 0xf8ff && $bctr < 0x2ceb0) {
                        $bctr = 0x2ceb0;
                        while (isset($char_to_glyph[$bctr])) {
                            $bctr++;
                        }
                    }
                    $glyph_to_char[$gid][] = $bctr;
                    $char_to_glyph[$bctr] = $gid;
                    $this->max_uni_char = max($bctr, $this->max_uni_char);
                    $bctr++;
                }
            }
        }
        // hmtx - Horizontal metrics table
        $scale = 1;
        // not used here
        $this->get_hmtx($number_of_h_metrics, $num_glyphs, $glyph_to_char, $scale);
        // loca - Index to location
        $this->get_loca($index_to_loc_format, $num_glyphs);
        $glyph_map = [0 => 0];
        $glyph_set = [0 => 0];
        $code_to_glyph = [];
        // Set a substitute if ASCII characters do not have glyphs
        if (isset($char_to_glyph[0x3f])) {
            $subs = $char_to_glyph[0x3f];
        } else {
            // Question mark
            $subs = $char_to_glyph[32];
        }
        foreach ($subset as $code) {
            if (isset($char_to_glyph[$code])) {
                $original_glyph_idx = $char_to_glyph[$code];
            } elseif ($code < 128) {
                $original_glyph_idx = $subs;
            } else {
                $original_glyph_idx = 0;
            }
            if (!isset($glyph_set[$original_glyph_idx])) {
                $glyph_set[$original_glyph_idx] = count($glyph_map);
                $glyph_map[] = $original_glyph_idx;
            }
            $code_to_glyph[$code] = $glyph_set[$original_glyph_idx];
        }
        list($start, $dummy) = $this->get_table_pos('glyf');
        $n = 0;
        while ($n < count($glyph_map)) {
            $original_glyph_idx = $glyph_map[$n];
            $glyph_pos = $this->glyph_pos[$original_glyph_idx];
            $glyph_len = $this->glyph_pos[$original_glyph_idx + 1] - $glyph_pos;
            ++$n;
            if (!$glyph_len) {
                continue;
            }
            $this->seek($start + $glyph_pos);
            $number_of_contours = $this->read_short();
            if ($number_of_contours < 0) {
                $this->skip(8);
                $flags = Glyph_Operator::MORE;
                while ($flags & Glyph_Operator::MORE) {
                    $flags = $this->read_ushort();
                    $glyph_idx = $this->read_ushort();
                    if (!isset($glyph_set[$glyph_idx])) {
                        $glyph_set[$glyph_idx] = count($glyph_map);
                        $glyph_map[] = $glyph_idx;
                    }
                    if ($flags & Glyph_Operator::WORDS) {
                        $this->skip(4);
                    } else {
                        $this->skip(2);
                    }
                    if ($flags & Glyph_Operator::SCALE) {
                        $this->skip(2);
                    } elseif ($flags & Glyph_Operator::XYSCALE) {
                        $this->skip(4);
                    } elseif ($flags & Glyph_Operator::TWOBYTWO) {
                        $this->skip(8);
                    }
                }
            }
        }
        $num_glyphs = $n = count($glyph_map);
        $number_of_h_metrics = $n;
        // MS spec says that "Platform and encoding ID's in the name table should be consistent with those in the cmap table.
        // If they are not, the font will not load in Windows"
        // Doesn't seem to be a problem?
        // Needs to have a name entry in 3,0 (e.g. symbol) - original font will be 3,1 (i.e. Unicode)
        $name = $this->get_table('name');
        $name_offset = $this->seek_table("name");
        $format = $this->read_ushort();
        $num_records = $this->read_ushort();
        $string_data_offset = $name_offset + $this->read_ushort();
        for ($i = 0; $i < $num_records; $i++) {
            $platform_id = $this->read_ushort();
            $encoding_id = $this->read_ushort();
            if ($platform_id == 3 && $encoding_id == 1) {
                $pos = 6 + $i * 12 + 2;
                $name = $this->_set_ushort($name, $pos, 0x0);
                // Change encoding to 3,0 rather than 3,1
            }
            $this->skip(8);
        }
        $this->add('name', $name);
        // OS/2
        if (isset($this->tables['OS/2'])) {
            $os2 = $this->get_table('OS/2');
            $os2 = $this->_set_ushort($os2, 42, 0x0);
            // ulCharRange (Unicode ranges)
            $os2 = $this->_set_ushort($os2, 44, 0x0);
            // ulCharRange (Unicode ranges)
            $os2 = $this->_set_ushort($os2, 46, 0x0);
            // ulCharRange (Unicode ranges)
            $os2 = $this->_set_ushort($os2, 48, 0x0);
            // ulCharRange (Unicode ranges)
            $os2 = $this->_set_ushort($os2, 50, 0x0);
            // ulCharRange (Unicode ranges)
            $os2 = $this->_set_ushort($os2, 52, 0x0);
            // ulCharRange (Unicode ranges)
            $os2 = $this->_set_ushort($os2, 54, 0x0);
            // ulCharRange (Unicode ranges)
            $os2 = $this->_set_ushort($os2, 56, 0x0);
            // ulCharRange (Unicode ranges)
            // Set Symbol character only in ulCodePageRange
            $os2 = $this->_set_ushort($os2, 78, 0x8000);
            // ulCodePageRange = Bit #31 Symbol ****  78 = Bit 16-31
            $os2 = $this->_set_ushort($os2, 80, 0x0);
            // ulCodePageRange = Bit #31 Symbol ****  80 = Bit 0-15
            $os2 = $this->_set_ushort($os2, 82, 0x0);
            // ulCodePageRange = Bit #32- Symbol **** 82 = Bits 48-63
            $os2 = $this->_set_ushort($os2, 84, 0x0);
            // ulCodePageRange = Bit #32- Symbol **** 84 = Bits 32-47
            $os2 = $this->_set_ushort($os2, 64, 0x1);
            // FirstCharIndex
            $os2 = $this->_set_ushort($os2, 66, count($subset));
            // LastCharIndex
            // Set PANOSE first bit to 5 for Symbol
            $os2 = $this->splice($os2, 32, chr(5) . chr(0) . chr(1) . chr(0) . chr(1) . chr(0) . chr(0) . chr(0) . chr(0) . chr(0));
            $this->add('OS/2', $os2);
        }
        //tables copied from the original
        $tags = ['cvt ', 'fpgm', 'prep', 'gasp'];
        foreach ($tags as $tag) {
            // 1.02
            if (isset($this->tables[$tag])) {
                $this->add($tag, $this->get_table($tag));
            }
        }
        // post - PostScript
        if (isset($this->tables['post'])) {
            $opost = $this->get_table('post');
            $post = "\x00\x03\x00\x00" . substr($opost, 4, 12) . "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        }
        $this->add('post', $post);
        // hhea - Horizontal Header
        $hhea = $this->get_table('hhea');
        $hhea = $this->_set_ushort($hhea, 34, $number_of_h_metrics);
        $this->add('hhea', $hhea);
        // maxp - Maximum Profile
        $maxp = $this->get_table('maxp');
        $maxp = $this->_set_ushort($maxp, 4, $num_glyphs);
        $this->add('maxp', $maxp);
        // CMap table Formats [1,0,]6 and [3,0,]4
        // Sort CID2GID map into segments of contiguous codes
        $rangeid = 0;
        $range = [];
        $prevcid = -2;
        $prevglidx = -1;
        // for each character
        foreach ($subset as $cid => $code) {
            $glidx = $code_to_glyph[$code];
            if ($cid == $prevcid + 1 && $glidx == $prevglidx + 1) {
                $range[$rangeid][] = $glidx;
            } else {
                // new range
                $rangeid = $cid;
                $range[$rangeid] = [];
                $range[$rangeid][] = $glidx;
            }
            $prevcid = $cid;
            $prevglidx = $glidx;
        }
        // cmap - Character to glyph mapping
        $seg_count = count($range) + 1;
        // + 1 Last segment has missing character 0xFFFF
        $search_range = 1;
        $entry_selector = 0;
        while ($search_range * 2 <= $seg_count) {
            $search_range = $search_range * 2;
            $entry_selector = $entry_selector + 1;
        }
        $search_range = $search_range * 2;
        $range_shift = $seg_count * 2 - $search_range;
        $length = 16 + 8 * $seg_count + ($num_glyphs + 1);
        $cmap = [
            4,
            $length,
            0,
            // Format 4 Mapping subtable: format, length, language
            $seg_count * 2,
            $search_range,
            $entry_selector,
            $range_shift,
        ];
        // endCode(s)
        foreach ($range as $start => $subrange) {
            $end_code = $start + (count($subrange) - 1);
            $cmap[] = $end_code;
            // endCode(s)
        }
        $cmap[] = 0xffff;
        // endCode of last Segment
        $cmap[] = 0;
        // reservedPad
        // startCode(s)
        foreach ($range as $start => $subrange) {
            $cmap[] = $start;
            // startCode(s)
        }
        $cmap[] = 0xffff;
        // startCode of last Segment
        // idDelta(s)
        foreach ($range as $start => $subrange) {
            $id_delta = -($start - $subrange[0]);
            $n += count($subrange);
            $cmap[] = $id_delta;
            // idDelta(s)
        }
        $cmap[] = 1;
        // idDelta of last Segment
        // idRangeOffset(s)
        foreach ($range as $subrange) {
            $cmap[] = 0;
            // idRangeOffset[segCount]  	Offset in bytes to glyph indexArray, or 0
        }
        $cmap[] = 0;
        // idRangeOffset of last Segment
        foreach ($range as $subrange) {
            foreach ($subrange as $glidx) {
                $cmap[] = $glidx;
            }
        }
        $cmap[] = 0;
        // Mapping for last character
        $cmapstr4 = '';
        foreach ($cmap as $cm) {
            $cmapstr4 .= pack("n", $cm);
        }
        // cmap - Character to glyph mapping
        $entry_count = count($subset);
        $length = 10 + $entry_count * 2;
        $off = 20 + $length;
        $hoff = $off >> 16;
        $loff = $off & 0xffff;
        $cmap = [
            0,
            2,
            // Index : version, number of subtables
            1,
            0,
            // Subtable : platform, encoding
            0,
            20,
            // offset (hi,lo)
            3,
            0,
            // Subtable : platform, encoding	// See note above for 'name'
            $hoff,
            $loff,
            // offset (hi,lo)
            6,
            $length,
            // Format 6 Mapping table: format, length
            0,
            1,
            // language, First char code
            $entry_count,
        ];
        $cmapstr = '';
        foreach ($subset as $code) {
            $cmap[] = $code_to_glyph[$code];
        }
        foreach ($cmap as $cm) {
            $cmapstr .= pack("n", $cm);
        }
        $cmapstr .= $cmapstr4;
        $this->add('cmap', $cmapstr);
        // hmtx - Horizontal Metrics
        $hmtxstr = '';
        for ($n = 0; $n < $num_glyphs; $n++) {
            $original_glyph_idx = $glyph_map[$n];
            $hm = $this->get_h_metric($orign_hmetrics, $original_glyph_idx);
            $hmtxstr .= $hm;
        }
        $this->add('hmtx', $hmtxstr);
        // glyf - Glyph data
        list($glyf_offset, $glyf_length) = $this->get_table_pos('glyf');
        if ($glyf_length < $this->max_str_len_read) {
            $glyph_data = $this->get_table('glyf');
        }
        $offsets = [];
        $glyf = '';
        $pos = 0;
        for ($n = 0; $n < $num_glyphs; $n++) {
            $offsets[] = $pos;
            $original_glyph_idx = $glyph_map[$n];
            $glyph_pos = $this->glyph_pos[$original_glyph_idx];
            $glyph_len = $this->glyph_pos[$original_glyph_idx + 1] - $glyph_pos;
            if ($glyf_length < $this->max_str_len_read) {
                $data = substr($glyph_data, $glyph_pos, $glyph_len);
            } else if ($glyph_len > 0) {
                $data = $this->get_chunk($glyf_offset + $glyph_pos, $glyph_len);
            } else {
                $data = '';
            }
            if ($glyph_len > 0) {
                $up = unpack('n', substr($data, 0, 2));
            }
            if ($glyph_len > 2 && $up[1] & 1 << 15) {
                $pos_in_glyph = 10;
                $flags = Glyph_Operator::MORE;
                while ($flags & Glyph_Operator::MORE) {
                    $up = unpack('n', substr($data, $pos_in_glyph, 2));
                    $flags = $up[1];
                    $up = unpack('n', substr($data, $pos_in_glyph + 2, 2));
                    $glyph_idx = $up[1];
                    $data = $this->_set_ushort($data, $pos_in_glyph + 2, $glyph_set[$glyph_idx]);
                    $pos_in_glyph += 4;
                    if ($flags & Glyph_Operator::WORDS) {
                        $pos_in_glyph += 4;
                    } else {
                        $pos_in_glyph += 2;
                    }
                    if ($flags & Glyph_Operator::SCALE) {
                        $pos_in_glyph += 2;
                    } elseif ($flags & Glyph_Operator::XYSCALE) {
                        $pos_in_glyph += 4;
                    } elseif ($flags & Glyph_Operator::TWOBYTWO) {
                        $pos_in_glyph += 8;
                    }
                }
            }
            $glyf .= $data;
            $pos += $glyph_len;
            if ($pos % 4 != 0) {
                $padding = 4 - $pos % 4;
                $glyf .= str_repeat("\x00", $padding);
                $pos += $padding;
            }
        }
        $offsets[] = $pos;
        $this->add('glyf', $glyf);
        // loca - Index to location
        $locastr = '';
        if ($pos + 1 >> 1 > 0xffff) {
            $index_to_loc_format = 1;
            // long format
            foreach ($offsets as $offset) {
                $locastr .= pack("N", $offset);
            }
        } else {
            $index_to_loc_format = 0;
            // short format
            foreach ($offsets as $offset) {
                $locastr .= pack("n", $offset / 2);
            }
        }
        $this->add('loca', $locastr);
        // head - Font header
        $head = $this->get_table('head');
        $head = $this->_set_ushort($head, 50, $index_to_loc_format);
        $this->add('head', $head);
        fclose($this->fh);
        $stm = '';
        $this->end_tt_file($stm);
        return $stm;
    }
    function get_glyph_data($original_glyph_idx, &$maxdepth, &$depth, &$points, &$contours)
    {
        $depth++;
        $maxdepth = max($maxdepth, $depth);
        if (count($this->glyphdata[$original_glyph_idx]['compGlyphs'])) {
            foreach ($this->glyphdata[$original_glyph_idx]['compGlyphs'] as $glyph_idx) {
                $this->get_glyph_data($glyph_idx, $maxdepth, $depth, $points, $contours);
            }
        } elseif ($this->glyphdata[$original_glyph_idx]['nContours'] > 0 && $depth > 0) {
            // simple
            $contours += $this->glyphdata[$original_glyph_idx]['nContours'];
            $points += $this->glyphdata[$original_glyph_idx]['nPoints'];
        }
        $depth--;
    }
    function get_glyphs($original_glyph_idx, &$start, &$glyph_set, &$subsetglyphs)
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
                } elseif ($flags & Glyph_Operator::XYSCALE) {
                    $this->skip(4);
                } elseif ($flags & Glyph_Operator::TWOBYTWO) {
                    $this->skip(8);
                }
            }
        }
    }
    function get_hmtx($number_of_h_metrics, $num_glyphs, &$glyph_to_char, $scale)
    {
        $start = $this->seek_table('hmtx');
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
            $arr = unpack('n*', $data);
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
                if ($glyph === 0) {
                    $this->default_width = $scale * $aw;
                    continue;
                }
                foreach ($glyph_to_char[$glyph] as $char) {
                    if ($char != 0 && $char != 65535) {
                        $w = (int) round($scale * $aw);
                        if ($w === 0) {
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
        $data = $this->get_chunk($start + $number_of_h_metrics * 4, $num_glyphs * 2);
        $arr = unpack("n*", $data);
        $diff = $num_glyphs - $number_of_h_metrics;
        $w = (int) round($scale * $aw);
        if ($w === 0) {
            $w = 65535;
        }
        for ($pos = 0; $pos < $diff; $pos++) {
            $glyph = $pos + $number_of_h_metrics;
            if (isset($glyph_to_char[$glyph])) {
                foreach ($glyph_to_char[$glyph] as $char) {
                    if ($char != 0 && $char != 65535) {
                        if ($char < 196608) {
                            $this->char_widths[$char * 2] = chr($w >> 8);
                            $this->char_widths[$char * 2 + 1] = chr($w & 0xff);
                            $n_char_widths++;
                        }
                    }
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
        } elseif ($index_to_loc_format == 1) {
            $data = $this->get_chunk($start, $num_glyphs * 4 + 4);
            $arr = unpack("N*", $data);
            for ($n = 0; $n <= $num_glyphs; $n++) {
                $this->glyph_pos[] = $arr[$n + 1];
            }
        } else {
            throw new \Mpdf\Exception\Font_Exception('Unknown location table format ' . $index_to_loc_format);
        }
    }
    /**
     * CMAP Format 4
     */
    function get_cmap4($unicode_cmap_offset, &$glyph_to_char, &$char_to_glyph)
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
    function end_tt_file(&$stm)
    {
        $stm = '';
        $num_tables = count($this->otables);
        $search_range = 1;
        $entry_selector = 0;
        while ($search_range * 2 <= $num_tables) {
            $search_range *= 2;
            $entry_selector += 1;
        }
        $search_range *= 16;
        $range_shift = $num_tables * 16 - $search_range;
        // Header
        if (_TTF_MAC_HEADER) {
            $stm .= pack('Nnnnn', 0x74727565, $num_tables, $search_range, $entry_selector, $range_shift);
            // Mac
        } else {
            $stm .= pack('Nnnnn', 0x10000, $num_tables, $search_range, $entry_selector, $range_shift);
            // Windows
        }
        // Table directory
        $tables = $this->otables;
        ksort($tables);
        $offset = 12 + $num_tables * 16;
        foreach ($tables as $tag => $data) {
            if ($tag === 'head') {
                $head_start = $offset;
            }
            $stm .= $tag;
            $checksum = $this->calc_checksum($data);
            $stm .= pack('nn', $checksum[0], $checksum[1]);
            $stm .= pack('NN', $offset, strlen($data));
            $padded_length = strlen($data) + 3 & ~3;
            $offset += $padded_length;
        }
        // Table data
        foreach ($tables as $tag => $data) {
            $data .= "\x00\x00\x00";
            $stm .= substr($data, 0, strlen($data) & ~3);
        }
        $checksum = $this->calc_checksum($stm);
        $checksum = $this->sub32([0xb1b0, 0xafba], $checksum);
        $chk = pack("nn", $checksum[0], $checksum[1]);
        $stm = $this->splice($stm, $head_start + 8, $chk);
        return $stm;
    }
    function repackage_ttf($file, $tt_cfont_id = 0, $debug = false, $use_otl = false)
    {
        $this->use_otl = $use_otl;
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
        $this->ascent = 0;
        $this->descent = 0;
        $this->strikeout_size = 0;
        $this->strikeout_position = 0;
        $this->num_ttc_fonts = 0;
        $this->ttc_fonts = [];
        $this->skip(4);
        $this->max_uni = 0;
        if ($tt_cfont_id > 0) {
            $this->version = $version = $this->read_ulong();
            // TTC Header version now
            if (!in_array($version, [0x10000, 0x20000], true)) {
                throw new \Mpdf\Exception\Font_Exception(sprintf('Error parsing TrueType Collection: version=%s - %s', $version, $file));
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
        $tags = ['OS/2', 'glyf', 'head', 'hhea', 'hmtx', 'loca', 'maxp', 'name', 'post', 'cvt ', 'fpgm', 'gasp', 'prep'];
        foreach ($tags as $tag) {
            if (isset($this->tables[$tag])) {
                $this->add($tag, $this->get_table($tag));
            }
        }
        if ($use_otl) {
            // maxp - Maximum profile table
            $this->seek_table('maxp');
            $this->skip(4);
            $num_glyphs = $this->read_ushort();
            // cmap - Character to glyph index mapping table
            $cmap_offset = $this->seek_table('cmap');
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
                        $unicode_cmap_offset = $cmap_offset + $offset;
                        break;
                    }
                }
                $this->seek($save_pos);
            }
            if (!$unicode_cmap_offset) {
                throw new \Mpdf\Exception\Font_Exception(sprintf('Font "%s" does not have cmap for Unicode (platform 3, encoding 1, format 4, or platform 0, any encoding, format 4)', $this->filename));
            }
            $glyph_to_char = [];
            $char_to_glyph = [];
            $this->get_cmap4($unicode_cmap_offset, $glyph_to_char, $char_to_glyph);
            // Map Unmapped glyphs - from $numGlyphs
            $bctr = 0xe000;
            for ($gid = 1; $gid < $num_glyphs; $gid++) {
                if (!isset($glyph_to_char[$gid])) {
                    while (isset($char_to_glyph[$bctr])) {
                        $bctr++;
                    }
                    // Avoid overwriting a glyph already mapped in PUA (6,400)
                    if ($bctr > 0xf8ff) {
                        throw new \Mpdf\Exception\Font_Exception("Problem. Trying to repackage TF file; not enough space for unmapped glyphs");
                    }
                    $glyph_to_char[$gid][] = $bctr;
                    $char_to_glyph[$bctr] = $gid;
                    $bctr++;
                }
            }
            // Sort CID2GID map into segments of contiguous codes
            unset($char_to_glyph[65535]);
            unset($char_to_glyph[0]);
            ksort($char_to_glyph);
            $rangeid = 0;
            $range = [];
            $prevcid = -2;
            $prevglidx = -1;
            // for each character
            foreach ($char_to_glyph as $cid => $glidx) {
                if ($cid == $prevcid + 1 && $glidx == $prevglidx + 1) {
                    $range[$rangeid][] = $glidx;
                } else {
                    // new range
                    $rangeid = $cid;
                    $range[$rangeid] = [];
                    $range[$rangeid][] = $glidx;
                }
                $prevcid = $cid;
                $prevglidx = $glidx;
            }
            // CMap table
            // cmap - Character to glyph mapping
            $seg_count = count($range) + 1;
            // + 1 Last segment has missing character 0xFFFF
            $search_range = 1;
            $entry_selector = 0;
            while ($search_range * 2 <= $seg_count) {
                $search_range *= 2;
                ++$entry_selector;
            }
            $search_range *= 2;
            $range_shift = $seg_count * 2 - $search_range;
            $length = 16 + 8 * $seg_count + ($num_glyphs + 1);
            $cmap = [
                0,
                3,
                // Index : version, number of encoding subtables
                0,
                0,
                // Encoding Subtable : platform (UNI=0), encoding 0
                0,
                28,
                // Encoding Subtable : offset (hi,lo)
                0,
                3,
                // Encoding Subtable : platform (UNI=0), encoding 3
                0,
                28,
                // Encoding Subtable : offset (hi,lo)
                3,
                1,
                // Encoding Subtable : platform (MS=3), encoding 1
                0,
                28,
                // Encoding Subtable : offset (hi,lo)
                4,
                $length,
                0,
                // Format 4 Mapping subtable: format, length, language
                $seg_count * 2,
                $search_range,
                $entry_selector,
                $range_shift,
            ];
            // endCode(s)
            foreach ($range as $start => $subrange) {
                $end_code = $start + (count($subrange) - 1);
                $cmap[] = $end_code;
                // endCode(s)
            }
            $cmap[] = 0xffff;
            // endCode of last Segment
            $cmap[] = 0;
            // reservedPad
            // startCode(s)
            foreach ($range as $start => $subrange) {
                $cmap[] = $start;
                // startCode(s)
            }
            $cmap[] = 0xffff;
            // startCode of last Segment
            // idDelta(s)
            foreach ($range as $start => $subrange) {
                $id_delta = -($start - $subrange[0]);
                $cmap[] = $id_delta;
                // idDelta(s)
            }
            $cmap[] = 1;
            // idDelta of last Segment
            // idRangeOffset(s)
            foreach ($range as $subrange) {
                $cmap[] = 0;
                // idRangeOffset[segCount] Offset in bytes to glyph indexArray, or 0
            }
            $cmap[] = 0;
            // idRangeOffset of last Segment
            foreach ($range as $subrange) {
                foreach ($subrange as $glidx) {
                    $cmap[] = $glidx;
                }
            }
            $cmap[] = 0;
            // Mapping for last character
            $cmapstr = '';
            foreach ($cmap as $cm) {
                $cmapstr .= pack('n', $cm);
            }
            $this->add('cmap', $cmapstr);
        } else {
            $this->add('cmap', $this->get_table('cmap'));
        }
        fclose($this->fh);
        $stm = '';
        $this->end_tt_file($stm);
        return $stm;
    }
}