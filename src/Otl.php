<?php

namespace Mpdf;

use Mpdf\Strict;
use Mpdf\Css\Text_Vars;
use Mpdf\Fonts\Font_Cache;
use Mpdf\Shaper\Indic;
use Mpdf\Shaper\Myanmar;
use Mpdf\Shaper\Sea;
use Mpdf\Utils\Utf_String;
class Otl
{
    use Strict;
    const _OTL_OLD_SPEC_COMPAT_1 = true;
    const _DICT_NODE_TYPE_SPLIT = 0x1;
    const _DICT_NODE_TYPE_LINEAR = 0x2;
    const _DICT_INTERMEDIATE_MATCH = 0x3;
    const _DICT_FINAL_MATCH = 0x4;
    private $mpdf;
    private $font_cache;
    public $arab_left_joining;
    public $arab_right_joining;
    public $arab_transparent_join;
    public $arab_transparent;
    public $gsu_bdata;
    public $gpo_sdata;
    public $gsu_bfont;
    public $fontkey;
    public $ttf_ot_ldata;
    public $glyph_i_dto_uni;
    public $_pos;
    public $GSUB_offset;
    public $GPOS_offset;
    public $mark_attachment_type;
    public $mark_glyph_sets;
    public $glyph_class_marks;
    public $glyph_class_ligatures;
    public $glyph_class_bases;
    public $glyph_class_components;
    public $Ignores;
    public $lu_coverage;
    public $ot_ldata;
    public $assoc_ligs;
    public $assoc_marks;
    public $shaper;
    public $restrict_to_syllable;
    public $lbdicts;
    // Line-breaking dictionaries
    public $lu_data_cache;
    public $arab_glyphs;
    public $current_fh;
    public $Entry;
    public $Exit;
    public $gde_fdata;
    public $gpos_lookups;
    public $gs_lu_coverage;
    public $GSUB_length;
    public $gsub_lookups;
    public $sch_ot_ldata;
    public $last_bidi_strong_type;
    public $debug_otl = false;
    public function __construct(Mpdf $mpdf, Font_Cache $font_cache)
    {
        $this->mpdf = $mpdf;
        $this->font_cache = $font_cache;
        $this->current_fh = '';
        $this->lbdicts = [];
        $this->lu_data_cache = [];
    }
    function apply_otl($str, $use_otl)
    {
        if (!$this->arab_left_joining) {
            $this->arabic_initialise();
        }
        $this->ot_ldata = [];
        if (trim($str) == '') {
            return $str;
        }
        if (!$use_otl) {
            return $str;
        }
        // 1. Load GDEF data
        //==============================
        $this->fontkey = $this->mpdf->current_font['fontkey'];
        $this->glyph_i_dto_uni = $this->mpdf->current_font['glyphIDtoUni'];
        $font_cache_filename = $this->fontkey . '.GDEFdata.json';
        if (!isset($this->gde_fdata[$this->fontkey]) && $this->font_cache->json_has($font_cache_filename)) {
            $font = $this->font_cache->json_load($font_cache_filename);
            $this->GSUB_offset = $this->gde_fdata[$this->fontkey]['GSUB_offset'] = $font['GSUB_offset'];
            $this->GPOS_offset = $this->gde_fdata[$this->fontkey]['GPOS_offset'] = $font['GPOS_offset'];
            $this->GSUB_length = $this->gde_fdata[$this->fontkey]['GSUB_length'] = $font['GSUB_length'];
            $this->mark_attachment_type = $this->gde_fdata[$this->fontkey]['MarkAttachmentType'] = $font['MarkAttachmentType'];
            $this->mark_glyph_sets = $this->gde_fdata[$this->fontkey]['MarkGlyphSets'] = $font['MarkGlyphSets'];
            $this->glyph_class_marks = $this->gde_fdata[$this->fontkey]['GlyphClassMarks'] = $font['GlyphClassMarks'];
            $this->glyph_class_ligatures = $this->gde_fdata[$this->fontkey]['GlyphClassLigatures'] = $font['GlyphClassLigatures'];
            $this->glyph_class_components = $this->gde_fdata[$this->fontkey]['GlyphClassComponents'] = $font['GlyphClassComponents'];
            $this->glyph_class_bases = $this->gde_fdata[$this->fontkey]['GlyphClassBases'] = $font['GlyphClassBases'];
        } else {
            $this->GSUB_offset = $this->gde_fdata[$this->fontkey]['GSUB_offset'];
            $this->GPOS_offset = $this->gde_fdata[$this->fontkey]['GPOS_offset'];
            $this->GSUB_length = $this->gde_fdata[$this->fontkey]['GSUB_length'];
            $this->mark_attachment_type = $this->gde_fdata[$this->fontkey]['MarkAttachmentType'];
            $this->mark_glyph_sets = $this->gde_fdata[$this->fontkey]['MarkGlyphSets'];
            $this->glyph_class_marks = $this->gde_fdata[$this->fontkey]['GlyphClassMarks'];
            $this->glyph_class_ligatures = $this->gde_fdata[$this->fontkey]['GlyphClassLigatures'];
            $this->glyph_class_components = $this->gde_fdata[$this->fontkey]['GlyphClassComponents'];
            $this->glyph_class_bases = $this->gde_fdata[$this->fontkey]['GlyphClassBases'];
        }
        // 2. Prepare string as HEX string and Analyse character properties
        //=================================================================
        $earr = $this->mpdf->utf8string_to_array($str, false);
        $scriptblock = 0;
        $scriptblocks = [];
        $scriptblocks[0] = 0;
        $ot_ldata = [];
        $subchunk = 0;
        $charctr = 0;
        foreach ($earr as $char) {
            $ucd_record = Ucdn::get_ucd_record($char);
            $sbl = $ucd_record[6];
            // Special case - Arabic End of Ayah
            if ($char == 1757) {
                $sbl = Ucdn::SCRIPT_ARABIC;
            }
            if ($sbl && $sbl != 40 && $sbl != 102) {
                if ($scriptblock == 0) {
                    $scriptblock = $sbl;
                    $scriptblocks[$subchunk] = $scriptblock;
                } elseif ($scriptblock > 0 && $scriptblock != $sbl) {
                    // *************************************************
                    // NEW (non-common) Script encountered in this chunk. Start a new subchunk
                    $subchunk++;
                    $scriptblock = $sbl;
                    $charctr = 0;
                    $scriptblocks[$subchunk] = $scriptblock;
                }
            }
            $ot_ldata[$subchunk][$charctr]['general_category'] = $ucd_record[0];
            $ot_ldata[$subchunk][$charctr]['bidi_type'] = $ucd_record[2];
            //$OTLdata[$subchunk][$charctr]['combining_class'] = $ucd_record[1];
            //$OTLdata[$subchunk][$charctr]['bidi_type'] = $ucd_record[2];
            //$OTLdata[$subchunk][$charctr]['mirrored'] = $ucd_record[3];
            //$OTLdata[$subchunk][$charctr]['east_asian_width'] = $ucd_record[4];
            //$OTLdata[$subchunk][$charctr]['normalization_check'] = $ucd_record[5];
            //$OTLdata[$subchunk][$charctr]['script'] = $ucd_record[6];
            $charasstr = $this->unicode_hex($char);
            if (strpos($this->glyph_class_marks, $charasstr) !== false) {
                $ot_ldata[$subchunk][$charctr]['group'] = 'M';
            } elseif ($char == 32 || $char == 12288) {
                $ot_ldata[$subchunk][$charctr]['group'] = 'S';
            } else {
                $ot_ldata[$subchunk][$charctr]['group'] = 'C';
            }
            $ot_ldata[$subchunk][$charctr]['uni'] = $char;
            $ot_ldata[$subchunk][$charctr]['hex'] = $charasstr;
            $charctr++;
        }
        /* PROCESS EACH SUBCHUNK WITH DIFFERENT SCRIPTS */
        for ($sch = 0; $sch <= $subchunk; $sch++) {
            $this->ot_ldata = $ot_ldata[$sch];
            $scriptblock = $scriptblocks[$sch];
            // 3. Get Appropriate Scripts, and Shaper engine from analysing text and list of available scripts/langsys in font
            //==============================
            // Based on actual script block of text, select shaper (and line-breaking dictionaries)
            if (Ucdn::SCRIPT_DEVANAGARI <= $scriptblock && $scriptblock <= Ucdn::SCRIPT_MALAYALAM) {
                $this->shaper = "I";
            } elseif ($scriptblock == Ucdn::SCRIPT_ARABIC || $scriptblock == Ucdn::SCRIPT_SYRIAC) {
                $this->shaper = "A";
            } elseif ($scriptblock == Ucdn::SCRIPT_NKO || $scriptblock == Ucdn::SCRIPT_MANDAIC) {
                $this->shaper = "A";
            } elseif ($scriptblock == Ucdn::SCRIPT_KHMER) {
                $this->shaper = "K";
            } elseif ($scriptblock == Ucdn::SCRIPT_THAI) {
                $this->shaper = "T";
            } elseif ($scriptblock == Ucdn::SCRIPT_LAO) {
                $this->shaper = "L";
            } elseif ($scriptblock == Ucdn::SCRIPT_SINHALA) {
                $this->shaper = "S";
            } elseif ($scriptblock == Ucdn::SCRIPT_MYANMAR) {
                $this->shaper = "M";
            } elseif ($scriptblock == Ucdn::SCRIPT_NEW_TAI_LUE) {
                $this->shaper = "E";
            } elseif ($scriptblock == Ucdn::SCRIPT_CHAM) {
                $this->shaper = "E";
            } elseif ($scriptblock == Ucdn::SCRIPT_TAI_THAM) {
                $this->shaper = "E";
            } else {
                $this->shaper = "";
            }
            // Get scripttag based on actual text script
            $scripttag = Ucdn::$uni_scriptblock[$scriptblock];
            $gsu_bscript_tag = '';
            $gsu_blangsys = '';
            $gpo_sscript_tag = '';
            $gpo_slangsys = '';
            $is_old_spec = false;
            $script_lang = $this->mpdf->current_font['GSUBScriptLang'];
            if (count($script_lang)) {
                list($gsu_bscript_tag, $is_old_spec) = $this->_get_ot_lscript_tag($script_lang, $scripttag, $scriptblock, $this->shaper, $use_otl);
                if ($this->mpdf->font_language_override && strpos($script_lang[$gsu_bscript_tag], $this->mpdf->font_language_override) !== false) {
                    $gsu_blangsys = str_pad($this->mpdf->font_language_override, 4);
                } elseif ($gsu_bscript_tag && isset($script_lang[$gsu_bscript_tag]) && $script_lang[$gsu_bscript_tag] != '') {
                    $gsu_blangsys = $this->_get_otl_lang_tag($this->mpdf->current_lang, $script_lang[$gsu_bscript_tag]);
                }
            }
            $script_lang = $this->mpdf->current_font['GPOSScriptLang'];
            // NB If after GSUB, the same script/lang exist for GPOS, just use these...
            if ($gsu_bscript_tag && $gsu_blangsys && isset($script_lang[$gsu_bscript_tag]) && strpos($script_lang[$gsu_bscript_tag], $gsu_blangsys) !== false) {
                $gpo_slangsys = $gsu_blangsys;
                $gpo_sscript_tag = $gsu_bscript_tag;
            } elseif (count($script_lang)) {
                list($gpo_sscript_tag, $dummy) = $this->_get_ot_lscript_tag($script_lang, $scripttag, $scriptblock, $this->shaper, $use_otl);
                if ($gpo_sscript_tag && $this->mpdf->font_language_override && strpos($script_lang[$gpo_sscript_tag], $this->mpdf->font_language_override) !== false) {
                    $gpo_slangsys = str_pad($this->mpdf->font_language_override, 4);
                } elseif ($gpo_sscript_tag && isset($script_lang[$gpo_sscript_tag]) && $script_lang[$gpo_sscript_tag] != '') {
                    $gpo_slangsys = $this->_get_otl_lang_tag($this->mpdf->current_lang, $script_lang[$gpo_sscript_tag]);
                }
            }
            // This is just for the font_dump_OTL utility to set script and langsys override
            // $mpdf->overrideOTLsettings does not exist, this is never called
            /*if (isset($this->mpdf->overrideOTLsettings) && isset($this->mpdf->overrideOTLsettings[$this->fontkey])) {
            			$GSUBscriptTag = $GPOSscriptTag = $this->mpdf->overrideOTLsettings[$this->fontkey]['script'];
            			$GSUBlangsys = $GPOSlangsys = $this->mpdf->overrideOTLsettings[$this->fontkey]['lang'];
            		}*/
            if (!$gsu_bscript_tag && !$gsu_blangsys && !$gpo_sscript_tag && !$gpo_slangsys) {
                // Remove ZWJ and ZWNJ
                for ($i = 0; $i < count($this->ot_ldata); $i++) {
                    if ($this->ot_ldata[$i]['uni'] == 8204 || $this->ot_ldata[$i]['uni'] == 8205) {
                        array_splice($this->ot_ldata, $i, 1);
                    }
                }
                $this->sch_ot_ldata[$sch] = $this->ot_ldata;
                $this->ot_ldata = [];
                continue;
            }
            // Don't use MYANMAR shaper unless using v2 scripttag
            if ($this->shaper == 'M' && $gsu_bscript_tag != 'mym2') {
                $this->shaper = '';
            }
            $gsub_features = isset($this->mpdf->current_font['GSUBFeatures'][$gsu_bscript_tag][$gsu_blangsys]) ? $this->mpdf->current_font['GSUBFeatures'][$gsu_bscript_tag][$gsu_blangsys] : false;
            $gpos_features = isset($this->mpdf->current_font['GPOSFeatures'][$gpo_sscript_tag][$gpo_slangsys]) ? $this->mpdf->current_font['GPOSFeatures'][$gpo_sscript_tag][$gpo_slangsys] : false;
            $this->assoc_ligs = [];
            // Ligatures[$posarr lpos] => nc
            $this->assoc_marks = [];
            // assocMarks[$posarr mpos] => array(compID, ligPos)
            if (!isset($this->gde_fdata[$this->fontkey]['GSUBGPOStables'])) {
                $this->ttf_ot_ldata = $this->gde_fdata[$this->fontkey]['GSUBGPOStables'] = $this->font_cache->load($this->fontkey . '.GSUBGPOStables.dat', 'rb');
                if (!$this->ttf_ot_ldata) {
                    throw new \Mpdf\Mpdf_Exception('Can\'t open file ' . $this->font_cache->temp_filename($this->fontkey . '.GSUBGPOStables.dat'));
                }
            } else {
                $this->ttf_ot_ldata = $this->gde_fdata[$this->fontkey]['GSUBGPOStables'];
            }
            if ($this->debug_otl) {
                $this->_dumpproc('BEGIN', '-', '-', '-', '-', -1, '-', 0);
            }
            ////////////////////////////////////////////////////////////////
            /////////  LINE BREAKING FOR KHMER, THAI + LAO /////////////////
            ////////////////////////////////////////////////////////////////
            // Insert U+200B at word boundaries using dictionaries
            if ($this->mpdf->use_dictionary_lbr && ($this->shaper == "K" || $this->shaper == "T" || $this->shaper == "L")) {
                // Sets $this->OTLdata[$i]['wordend']=true at possible end of word boundaries
                $this->sea_line_breaking();
            } elseif ($this->mpdf->use_tibetan_lbr && $scriptblock == Ucdn::SCRIPT_TIBETAN) {
                // Sets $this->OTLdata[$i]['wordend']=true at possible end of word boundaries
                $this->tibetan_line_breaking();
            }
            ////////////////////////////////////////////////////////////////
            //////////       GSUB          /////////////////////////////////
            ////////////////////////////////////////////////////////////////
            if ($use_otl & 0xff && $gsu_bscript_tag && $gsu_blangsys && $gsub_features) {
                // 4. Load GSUB data, Coverage & Lookups
                //=================================================================
                $this->gsu_bfont = $this->fontkey . '.GSUB.' . $gsu_bscript_tag . '.' . $gsu_blangsys;
                if (!isset($this->gsu_bdata[$this->gsu_bfont])) {
                    $font_cache_filename = $this->gsu_bfont . '.json';
                    if ($this->font_cache->json_has($font_cache_filename)) {
                        $font = $this->font_cache->json_load($font_cache_filename);
                        $this->gsu_bdata[$this->gsu_bfont]['rtlSUB'] = $font['rtlSUB'];
                        $this->gsu_bdata[$this->gsu_bfont]['finals'] = $font['finals'];
                        if ($this->shaper == 'I') {
                            $this->gsu_bdata[$this->gsu_bfont]['rphf'] = $font['rphf'];
                            $this->gsu_bdata[$this->gsu_bfont]['half'] = $font['half'];
                            $this->gsu_bdata[$this->gsu_bfont]['pref'] = $font['pref'];
                            $this->gsu_bdata[$this->gsu_bfont]['blwf'] = $font['blwf'];
                            $this->gsu_bdata[$this->gsu_bfont]['pstf'] = $font['pstf'];
                        }
                    } else {
                        $this->gsu_bdata[$this->gsu_bfont] = ['rtlSUB' => [], 'rphf' => [], 'pref' => [], 'blwf' => [], 'pstf' => [], 'finals' => ''];
                    }
                }
                $font_cache_filename = $this->fontkey . '.GSUBdata.json';
                if (!isset($this->gsu_bdata[$this->fontkey]) && $this->font_cache->json_has($font_cache_filename)) {
                    $this->gs_lu_coverage = $this->gsu_bdata[$this->fontkey]['GSLuCoverage'] = $this->font_cache->json_load($font_cache_filename);
                } else {
                    $this->gs_lu_coverage = $this->gsu_bdata[$this->fontkey]['GSLuCoverage'];
                }
                $this->gsub_lookups = $this->mpdf->current_font['GSUBLookups'];
                // 5(A). GSUB - Shaper - ARABIC
                //==============================
                if ($this->shaper == 'A') {
                    //-----------------------------------------------------------------------------------
                    // a. Apply initial GSUB Lookups (in order specified in lookup list but only selecting from certain tags)
                    //-----------------------------------------------------------------------------------
                    $tags = 'locl ccmp';
                    $omittags = '';
                    $usetags = $tags;
                    if (!empty($this->mpdf->ot_ltags)) {
                        $usetags = $this->_apply_tag_settings($tags, $gsub_features, $omittags, true);
                    }
                    $this->_apply_gsu_brules($usetags, $gsu_bscript_tag, $gsu_blangsys);
                    //-----------------------------------------------------------------------------------
                    // b. Apply context-specific forms GSUB Lookups (initial, isolated, medial, final)
                    //-----------------------------------------------------------------------------------
                    // Arab and Syriac are the only scripts requiring the special joining - which takes the place of
                    // isol fina medi init rules in GSUB (+ fin2 fin3 med2 in Syriac syrc)
                    $tags = 'isol fina fin2 fin3 medi med2 init';
                    $omittags = '';
                    $usetags = $tags;
                    if (!empty($this->mpdf->ot_ltags)) {
                        $usetags = $this->_apply_tag_settings($tags, $gsub_features, $omittags, true);
                    }
                    $this->arab_glyphs = $this->gsu_bdata[$this->gsu_bfont]['rtlSUB'];
                    $gcms = explode("| ", $this->glyph_class_marks);
                    $gcm = [];
                    foreach ($gcms as $g) {
                        $gcm[hexdec($g)] = 1;
                    }
                    $this->arab_transparent_join = $this->arab_transparent + $gcm;
                    $this->arabic_shaper($usetags, $gsu_bscript_tag);
                    //-----------------------------------------------------------------------------------
                    // c. Set Kashida points (after joining occurred - medi, fina, init) but before other substitutions
                    //-----------------------------------------------------------------------------------
                    //if ($scriptblock == Ucdn::SCRIPT_ARABIC ) {
                    for ($i = 0; $i < count($this->ot_ldata); $i++) {
                        // Put the kashida marker on the character BEFORE which is inserted the kashida
                        // Kashida marker is inverse of priority i.e. Priority 1 => 7, Priority 7 => 1.
                        // Priority 1   User-inserted Kashida 0640 = Tatweel
                        // The user entered a Kashida in a position
                        // Position: Before the user-inserted kashida
                        if ($this->ot_ldata[$i]['uni'] == 0x640) {
                            $this->ot_ldata[$i]['GPOSinfo']['kashida'] = 8;
                            // Put before the next character
                        } elseif ($this->ot_ldata[$i]['uni'] == 0xfeb3 || $this->ot_ldata[$i]['uni'] == 0xfeb4 || $this->ot_ldata[$i]['uni'] == 0xfebb || $this->ot_ldata[$i]['uni'] == 0xfebc) {
                            $checkpos = $i + 1;
                            while (isset($this->ot_ldata[$checkpos]) && strpos($this->glyph_class_marks, $this->ot_ldata[$checkpos]['hex']) !== false) {
                                $checkpos++;
                            }
                            if (isset($this->ot_ldata[$checkpos])) {
                                $this->ot_ldata[$checkpos]['GPOSinfo']['kashida'] = 7;
                                // Put after marks on next character
                            }
                        } elseif ($this->ot_ldata[$i]['uni'] == 0xfe94 || $this->ot_ldata[$i]['uni'] == 0xfea2 || $this->ot_ldata[$i]['uni'] == 0xfeaa) {
                            $this->ot_ldata[$i]['GPOSinfo']['kashida'] = 6;
                        } elseif ($this->ot_ldata[$i]['uni'] == 0xfe8e || $this->ot_ldata[$i]['uni'] == 0xfec2 || $this->ot_ldata[$i]['uni'] == 0xfede || $this->ot_ldata[$i]['uni'] == 0xfeda || $this->ot_ldata[$i]['uni'] == 0xfb93) {
                            $this->ot_ldata[$i]['GPOSinfo']['kashida'] = 5;
                        } elseif ($this->ot_ldata[$i]['uni'] == 0xfeae || $this->ot_ldata[$i]['uni'] == 0xfef2 || $this->ot_ldata[$i]['uni'] == 0xfef0 || $this->ot_ldata[$i]['uni'] == 0xfef4 || $this->ot_ldata[$i]['uni'] == 0xfbe9 || $this->ot_ldata[$i]['uni'] == 0xfbfd || $this->ot_ldata[$i]['uni'] == 0xfbff) {
                            $checkpos = $i - 1;
                            while (isset($this->ot_ldata[$checkpos]) && strpos($this->glyph_class_marks, $this->ot_ldata[$checkpos]['hex']) !== false) {
                                $checkpos--;
                            }
                            if (isset($this->ot_ldata[$checkpos]) && $this->ot_ldata[$checkpos]['uni'] == 0xfe92) {
                                $this->ot_ldata[$checkpos]['GPOSinfo']['kashida'] = 4;
                                // ******* Before preceding BAA
                            }
                        } elseif ($this->ot_ldata[$i]['uni'] == 0xfeee || $this->ot_ldata[$i]['uni'] == 0xfeca || $this->ot_ldata[$i]['uni'] == 0xfed6 || $this->ot_ldata[$i]['uni'] == 0xfed2) {
                            $this->ot_ldata[$i]['GPOSinfo']['kashida'] = 3;
                        }
                        // Priority 7   Other connecting characters
                        // Final form
                        // Connecting to previous character
                        // Position: Before the character
                        /* This isn't in the spec, but using MS WORD as a basis, give a lower priority to the 3 characters already checked
                        		  in (5) above. Test case:
                        		  &#x62e;&#x652;&#x631;&#x64e;&#x649;&#x670;
                        		  &#x641;&#x64e;&#x62a;&#x64f;&#x630;&#x64e;&#x643;&#x651;&#x650;&#x631;
                        		 */
                        if (!isset($this->ot_ldata[$i]['GPOSinfo']['kashida'])) {
                            if (strpos($this->gsu_bdata[$this->gsu_bfont]['finals'], $this->ot_ldata[$i]['hex']) !== false) {
                                // ANY OTHER FINAL FORM
                                $this->ot_ldata[$i]['GPOSinfo']['kashida'] = 2;
                            } elseif (strpos('0FEAE 0FEF0 0FEF2', $this->ot_ldata[$i]['hex']) !== false) {
                                // not already included in 5 above
                                $this->ot_ldata[$i]['GPOSinfo']['kashida'] = 1;
                            }
                        }
                    }
                    //-----------------------------------------------------------------------------------
                    // d. Apply Presentation Forms GSUB Lookups (+ any discretionary) - Apply one at a time in Feature order
                    //-----------------------------------------------------------------------------------
                    $tags = 'rlig calt liga clig mset';
                    $omittags = 'locl ccmp nukt akhn rphf rkrf pref blwf abvf half pstf cfar vatu cjct init medi fina isol med2 fin2 fin3 ljmo vjmo tjmo';
                    $usetags = $tags;
                    if (!empty($this->mpdf->ot_ltags)) {
                        $usetags = $this->_apply_tag_settings($tags, $gsub_features, $omittags, false);
                    }
                    $ts = explode(' ', $usetags);
                    foreach ($ts as $ut) {
                        //  - Apply one at a time in Feature order
                        $this->_apply_gsu_brules($ut, $gsu_bscript_tag, $gsu_blangsys);
                    }
                    //-----------------------------------------------------------------------------------
                    // e. NOT IN SPEC
                    // If space precedes a mark -> substitute a &nbsp; before the Mark, to prevent line breaking Test:
                    //-----------------------------------------------------------------------------------
                    for ($ptr = 1; $ptr < count($this->ot_ldata); $ptr++) {
                        if ($this->ot_ldata[$ptr]['general_category'] == Ucdn::UNICODE_GENERAL_CATEGORY_NON_SPACING_MARK && $this->ot_ldata[$ptr - 1]['uni'] == 32) {
                            $this->ot_ldata[$ptr - 1]['uni'] = 0xa0;
                            $this->ot_ldata[$ptr - 1]['hex'] = '000A0';
                        }
                    }
                } elseif ($this->shaper == 'I' || $this->shaper == 'K' || $this->shaper == 'S') {
                    $this->restrict_to_syllable = true;
                    //-----------------------------------------------------------------------------------
                    // a. First decompose/compose split mattras
                    // (normalize) ??????? Nukta/Halant order etc ??????????????????????????????????????????????????????????????????????????
                    //-----------------------------------------------------------------------------------
                    for ($ptr = 0; $ptr < count($this->ot_ldata); $ptr++) {
                        $char = $this->ot_ldata[$ptr]['uni'];
                        $sub = Indic::decompose_indic($char);
                        if ($sub) {
                            $newinfo = [];
                            for ($i = 0; $i < count($sub); $i++) {
                                $newinfo[$i] = [];
                                $ucd_record = Ucdn::get_ucd_record($sub[$i]);
                                $newinfo[$i]['general_category'] = $ucd_record[0];
                                $newinfo[$i]['bidi_type'] = $ucd_record[2];
                                $charasstr = $this->unicode_hex($sub[$i]);
                                if (strpos($this->glyph_class_marks, $charasstr) !== false) {
                                    $newinfo[$i]['group'] = 'M';
                                } else {
                                    $newinfo[$i]['group'] = 'C';
                                }
                                $newinfo[$i]['uni'] = $sub[$i];
                                $newinfo[$i]['hex'] = $charasstr;
                            }
                            array_splice($this->ot_ldata, $ptr, 1, $newinfo);
                            $ptr += count($sub) - 1;
                        }
                        /* Only Composition-exclusion exceptions that we want to recompose. */
                        if ($this->shaper == 'I') {
                            if ($char == 0x9af && isset($this->ot_ldata[$ptr + 1]) && $this->ot_ldata[$ptr + 1]['uni'] == 0x9bc) {
                                $sub = 0x9df;
                                $newinfo = [];
                                $newinfo[0] = [];
                                $ucd_record = Ucdn::get_ucd_record($sub);
                                $newinfo[0]['general_category'] = $ucd_record[0];
                                $newinfo[0]['bidi_type'] = $ucd_record[2];
                                $newinfo[0]['group'] = 'C';
                                $newinfo[0]['uni'] = $sub;
                                $newinfo[0]['hex'] = $this->unicode_hex($sub);
                                array_splice($this->ot_ldata, $ptr, 2, $newinfo);
                            }
                        }
                    }
                    //-----------------------------------------------------------------------------------
                    // b. Analyse characters - group as syllables/clusters (Indic); invalid diacritics; add dotted circle
                    //-----------------------------------------------------------------------------------
                    $indic_category_string = '';
                    foreach ($this->ot_ldata as $eid => $c) {
                        Indic::set_indic_properties($this->ot_ldata[$eid], $scriptblock);
                        // sets ['indic_category'] and ['indic_position']
                        //$c['general_category']
                        //$c['combining_class']
                        //$c['uni'] =  $char;
                        $indic_category_string .= Indic::$indic_category_char[$this->ot_ldata[$eid]['indic_category']];
                    }
                    $broken_syllables = false;
                    if ($this->shaper == 'I') {
                        Indic::set_syllables($this->ot_ldata, $indic_category_string, $broken_syllables);
                    } elseif ($this->shaper == 'S') {
                        Indic::set_syllables_sinhala($this->ot_ldata, $indic_category_string, $broken_syllables);
                    } elseif ($this->shaper == 'K') {
                        Indic::set_syllables_khmer($this->ot_ldata, $indic_category_string, $broken_syllables);
                    }
                    $indic_category_string = '';
                    //-----------------------------------------------------------------------------------
                    // c. Initial Re-ordering (Indic / Khmer / Sinhala)
                    //-----------------------------------------------------------------------------------
                    // Find base consonant
                    // Decompose/compose and reorder Matras
                    // Reorder marks to canonical order
                    $indic_config = Indic::$indic_configs[$scriptblock];
                    $dottedcircle = false;
                    if ($broken_syllables) {
                        if ($this->mpdf->_char_defined($this->mpdf->fonts[$this->fontkey]['cw'], 0x25cc)) {
                            $dottedcircle = [];
                            $ucd_record = Ucdn::get_ucd_record(0x25cc);
                            $dottedcircle[0]['general_category'] = $ucd_record[0];
                            $dottedcircle[0]['bidi_type'] = $ucd_record[2];
                            $dottedcircle[0]['group'] = 'C';
                            $dottedcircle[0]['uni'] = 0x25cc;
                            $dottedcircle[0]['indic_category'] = Indic::OT_DOTTEDCIRCLE;
                            $dottedcircle[0]['indic_position'] = Indic::POS_BASE_C;
                            $dottedcircle[0]['hex'] = '025CC';
                            // TEMPORARY *****
                        }
                    }
                    Indic::initial_reordering($this->ot_ldata, $this->gsu_bdata[$this->gsu_bfont], $broken_syllables, $indic_config, $scriptblock, $is_old_spec, $dottedcircle);
                    //-----------------------------------------------------------------------------------
                    // d. Apply initial and basic shaping forms GSUB Lookups (one at a time)
                    //-----------------------------------------------------------------------------------
                    if ($this->shaper == 'I' || $this->shaper == 'S') {
                        $tags = 'locl ccmp nukt akhn rphf rkrf pref blwf half pstf vatu cjct';
                    } elseif ($this->shaper == 'K') {
                        $tags = 'locl ccmp pref blwf abvf pstf cfar';
                    }
                    $this->_apply_gsu_brules_indic($tags, $gsu_bscript_tag, $gsu_blangsys, $is_old_spec);
                    //-----------------------------------------------------------------------------------
                    // e. Final Re-ordering (Indic / Khmer / Sinhala)
                    //-----------------------------------------------------------------------------------
                    // Reorder matras
                    // Reorder reph
                    // Reorder pre-base reordering consonants:
                    Indic::final_reordering($this->ot_ldata, $this->gsu_bdata[$this->gsu_bfont], $indic_config, $scriptblock, $is_old_spec);
                    //-----------------------------------------------------------------------------------
                    // f. Apply 'init' feature to first syllable in word (indicated by ['mask']) Indic::FLAG(Indic::INIT);
                    //-----------------------------------------------------------------------------------
                    if ($this->shaper == 'I' || $this->shaper == 'S') {
                        $tags = 'init';
                        $this->_apply_gsu_brules_indic($tags, $gsu_bscript_tag, $gsu_blangsys, $is_old_spec);
                    }
                    //-----------------------------------------------------------------------------------
                    // g. Apply Presentation Forms GSUB Lookups (+ any discretionary)
                    //-----------------------------------------------------------------------------------
                    $tags = 'pres abvs blws psts haln rlig calt liga clig mset';
                    $omittags = 'locl ccmp nukt akhn rphf rkrf pref blwf abvf half pstf cfar vatu cjct init medi fina isol med2 fin2 fin3 ljmo vjmo tjmo';
                    $usetags = $tags;
                    if (!empty($this->mpdf->ot_ltags)) {
                        $usetags = $this->_apply_tag_settings($tags, $gsub_features, $omittags, false);
                    }
                    if ($this->shaper == 'K') {
                        // Features are applied one at a time, working through each codepoint
                        $this->_apply_gsu_brules_singly($usetags, $gsu_bscript_tag, $gsu_blangsys);
                    } else {
                        $this->_apply_gsu_brules($usetags, $gsu_bscript_tag, $gsu_blangsys);
                    }
                    $this->restrict_to_syllable = false;
                } elseif ($this->shaper == 'M') {
                    $this->restrict_to_syllable = true;
                    //-----------------------------------------------------------------------------------
                    // a. Analyse characters - group as syllables/clusters (Myanmar); invalid diacritics; add dotted circle
                    //-----------------------------------------------------------------------------------
                    $myanmar_category_string = '';
                    foreach ($this->ot_ldata as $eid => $c) {
                        Myanmar::set_myanmar_properties($this->ot_ldata[$eid]);
                        // sets ['myanmar_category'] and ['myanmar_position']
                        $myanmar_category_string .= Myanmar::$myanmar_category_char[$this->ot_ldata[$eid]['myanmar_category']];
                    }
                    $broken_syllables = false;
                    Myanmar::set_syllables($this->ot_ldata, $myanmar_category_string, $broken_syllables);
                    $myanmar_category_string = '';
                    //-----------------------------------------------------------------------------------
                    // b. Re-ordering (Myanmar mym2)
                    //-----------------------------------------------------------------------------------
                    $dottedcircle = false;
                    if ($broken_syllables) {
                        if ($this->mpdf->_char_defined($this->mpdf->fonts[$this->fontkey]['cw'], 0x25cc)) {
                            $dottedcircle = [];
                            $ucd_record = Ucdn::get_ucd_record(0x25cc);
                            $dottedcircle[0]['general_category'] = $ucd_record[0];
                            $dottedcircle[0]['bidi_type'] = $ucd_record[2];
                            $dottedcircle[0]['group'] = 'C';
                            $dottedcircle[0]['uni'] = 0x25cc;
                            $dottedcircle[0]['myanmar_category'] = Myanmar::OT_DOTTEDCIRCLE;
                            $dottedcircle[0]['myanmar_position'] = Myanmar::POS_BASE_C;
                            $dottedcircle[0]['hex'] = '025CC';
                        }
                    }
                    Myanmar::reordering($this->ot_ldata, $this->gsu_bdata[$this->gsu_bfont], $broken_syllables, $dottedcircle);
                    //-----------------------------------------------------------------------------------
                    // c. Apply initial and basic shaping forms GSUB Lookups (one at a time)
                    //-----------------------------------------------------------------------------------
                    $tags = 'locl ccmp rphf pref blwf pstf';
                    $this->_apply_gsu_brules_myanmar($tags, $gsu_bscript_tag, $gsu_blangsys);
                    //-----------------------------------------------------------------------------------
                    // d. Apply Presentation Forms GSUB Lookups (+ any discretionary)
                    //-----------------------------------------------------------------------------------
                    $tags = 'pres abvs blws psts haln rlig calt liga clig mset';
                    $omittags = 'locl ccmp nukt akhn rphf rkrf pref blwf abvf half pstf cfar vatu cjct init medi fina isol med2 fin2 fin3 ljmo vjmo tjmo';
                    $usetags = $tags;
                    if (!empty($this->mpdf->ot_ltags)) {
                        $usetags = $this->_apply_tag_settings($tags, $gsub_features, $omittags, false);
                    }
                    $this->_apply_gsu_brules($usetags, $gsu_bscript_tag, $gsu_blangsys);
                    $this->restrict_to_syllable = false;
                } elseif ($this->shaper == 'E') {
                    /* HarfBuzz says: If the designer designed the font for the 'DFLT' script,
                     * use the default shaper.  Otherwise, use the SEA shaper.
                     * Note that for some simple scripts, there may not be *any*
                     * GSUB/GPOS needed, so there may be no scripts found! */
                    $this->restrict_to_syllable = true;
                    //-----------------------------------------------------------------------------------
                    // a. Analyse characters - group as syllables/clusters (Indic); invalid diacritics; add dotted circle
                    //-----------------------------------------------------------------------------------
                    $sea_category_string = '';
                    foreach ($this->ot_ldata as $eid => $c) {
                        Sea::set_sea_properties($this->ot_ldata[$eid], $scriptblock);
                        // sets ['sea_category'] and ['sea_position']
                        //$c['general_category']
                        //$c['combining_class']
                        //$c['uni'] =  $char;
                        $sea_category_string .= Sea::$sea_category_char[$this->ot_ldata[$eid]['sea_category']];
                    }
                    $broken_syllables = false;
                    Sea::set_syllables($this->ot_ldata, $sea_category_string, $broken_syllables);
                    $sea_category_string = '';
                    //-----------------------------------------------------------------------------------
                    // b. Apply locl and ccmp shaping forms - before initial re-ordering; GSUB Lookups (one at a time)
                    //-----------------------------------------------------------------------------------
                    $tags = 'locl ccmp';
                    $this->_apply_gsu_brules_singly($tags, $gsu_bscript_tag, $gsu_blangsys);
                    //-----------------------------------------------------------------------------------
                    // c. Initial Re-ordering
                    //-----------------------------------------------------------------------------------
                    // Find base consonant
                    // Decompose/compose and reorder Matras
                    // Reorder marks to canonical order
                    $dottedcircle = false;
                    if ($broken_syllables) {
                        if ($this->mpdf->_char_defined($this->mpdf->fonts[$this->fontkey]['cw'], 0x25cc)) {
                            $dottedcircle = [];
                            $ucd_record = Ucdn::get_ucd_record(0x25cc);
                            $dottedcircle[0]['general_category'] = $ucd_record[0];
                            $dottedcircle[0]['bidi_type'] = $ucd_record[2];
                            $dottedcircle[0]['group'] = 'C';
                            $dottedcircle[0]['uni'] = 0x25cc;
                            $dottedcircle[0]['sea_category'] = Sea::OT_GB;
                            $dottedcircle[0]['sea_position'] = Sea::POS_BASE_C;
                            $dottedcircle[0]['hex'] = '025CC';
                            // TEMPORARY *****
                        }
                    }
                    Sea::initial_reordering($this->ot_ldata, $this->gsu_bdata[$this->gsu_bfont], $broken_syllables, $scriptblock, $dottedcircle);
                    //-----------------------------------------------------------------------------------
                    // d. Apply basic shaping forms GSUB Lookups (one at a time)
                    //-----------------------------------------------------------------------------------
                    $tags = 'pref abvf blwf pstf';
                    $this->_apply_gsu_brules_singly($tags, $gsu_bscript_tag, $gsu_blangsys);
                    //-----------------------------------------------------------------------------------
                    // e. Final Re-ordering
                    //-----------------------------------------------------------------------------------
                    Sea::final_reordering($this->ot_ldata, $this->gsu_bdata[$this->gsu_bfont], $scriptblock);
                    //-----------------------------------------------------------------------------------
                    // f. Apply Presentation Forms GSUB Lookups (+ any discretionary)
                    //-----------------------------------------------------------------------------------
                    $tags = 'pres abvs blws psts';
                    $omittags = 'locl ccmp nukt akhn rphf rkrf pref blwf abvf half pstf cfar vatu cjct init medi fina isol med2 fin2 fin3 ljmo vjmo tjmo';
                    $usetags = $tags;
                    if (!empty($this->mpdf->ot_ltags)) {
                        $usetags = $this->_apply_tag_settings($tags, $gsub_features, $omittags, false);
                    }
                    $this->_apply_gsu_brules($usetags, $gsu_bscript_tag, $gsu_blangsys);
                    $this->restrict_to_syllable = false;
                } else {
                    // DEFAULT
                    //-----------------------------------------------------------------------------------
                    // a. First decompose/compose in Thai / Lao - Tibetan
                    //-----------------------------------------------------------------------------------
                    // Decomposition for THAI or LAO
                    /* This function implements the shaping logic documented here:
                     *
                     *   http://linux.thai.net/~thep/th-otf/shaping.html
                     *
                     * The first shaping rule listed there is needed even if the font has Thai
                     * OpenType tables.
                     *
                     *
                     * The following is NOT specified in the MS OT Thai spec, however, it seems
                     * to be what Uniscribe and other engines implement.  According to Eric Muller:
                     *
                     * When you have a SARA AM, decompose it in NIKHAHIT + SARA AA, *and* move the
                     * NIKHAHIT backwards over any tone mark (0E48-0E4B).
                     *
                     * <0E14, 0E4B, 0E33> -> <0E14, 0E4D, 0E4B, 0E32>
                     *
                     * This reordering is legit only when the NIKHAHIT comes from a SARA AM, not
                     * when it's there to start with. The string <0E14, 0E4B, 0E4D> is probably
                     * not what a user wanted, but the rendering is nevertheless nikhahit above
                     * chattawa.
                     *
                     * Same for Lao.
                     *
                     *          Thai        Lao
                     * SARA AM:     U+0E33  U+0EB3
                     * SARA AA:     U+0E32  U+0EB2
                     * Nikhahit:    U+0E4D  U+0ECD
                     *
                     * Testing shows that Uniscribe reorder the following marks:
                     * Thai:    <0E31,0E34..0E37,0E47..0E4E>
                     * Lao: <0EB1,0EB4..0EB7,0EC7..0ECE>
                     *
                     * Lao versions are the same as Thai + 0x80.
                     */
                    if ($this->shaper == 'T' || $this->shaper == 'L') {
                        for ($ptr = 0; $ptr < count($this->ot_ldata); $ptr++) {
                            $char = $this->ot_ldata[$ptr]['uni'];
                            if (($char & ~0x80) == 0xe33) {
                                // if SARA_AM (U+0E33 or U+0EB3)
                                $NIKHAHIT = $char + 0x1a;
                                $SARA_AA = $char - 1;
                                $sub = [$SARA_AA, $NIKHAHIT];
                                $newinfo = [];
                                $ucd_record = Ucdn::get_ucd_record($sub[0]);
                                $newinfo[0]['general_category'] = $ucd_record[0];
                                $newinfo[0]['bidi_type'] = $ucd_record[2];
                                $charasstr = $this->unicode_hex($sub[0]);
                                if (strpos($this->glyph_class_marks, $charasstr) !== false) {
                                    $newinfo[0]['group'] = 'M';
                                } else {
                                    $newinfo[0]['group'] = 'C';
                                }
                                $newinfo[0]['uni'] = $sub[0];
                                $newinfo[0]['hex'] = $charasstr;
                                $this->ot_ldata[$ptr] = $newinfo[0];
                                // Substitute SARA_AM => SARA_AA
                                $ntones = 0;
                                // number of (preceding) tone marks
                                // IS_TONE_MARK ((x) & ~0x0080, 0x0E34 - 0x0E37, 0x0E47 - 0x0E4E, 0x0E31)
                                while (isset($this->ot_ldata[$ptr - 1 - $ntones]) && (($this->ot_ldata[$ptr - 1 - $ntones]['uni'] & ~0x80) == 0xe31 || ($this->ot_ldata[$ptr - 1 - $ntones]['uni'] & ~0x80) >= 0xe34 && ($this->ot_ldata[$ptr - 1 - $ntones]['uni'] & ~0x80) <= 0xe37 || ($this->ot_ldata[$ptr - 1 - $ntones]['uni'] & ~0x80) >= 0xe47 && ($this->ot_ldata[$ptr - 1 - $ntones]['uni'] & ~0x80) <= 0xe4e)) {
                                    $ntones++;
                                }
                                $newinfo = [];
                                $ucd_record = Ucdn::get_ucd_record($sub[1]);
                                $newinfo[0]['general_category'] = $ucd_record[0];
                                $newinfo[0]['bidi_type'] = $ucd_record[2];
                                $charasstr = $this->unicode_hex($sub[1]);
                                if (strpos($this->glyph_class_marks, $charasstr) !== false) {
                                    $newinfo[0]['group'] = 'M';
                                } else {
                                    $newinfo[0]['group'] = 'C';
                                }
                                $newinfo[0]['uni'] = $sub[1];
                                $newinfo[0]['hex'] = $charasstr;
                                // Insert NIKAHIT
                                array_splice($this->ot_ldata, $ptr - $ntones, 0, $newinfo);
                                $ptr++;
                            }
                        }
                    }
                    if ($scriptblock == Ucdn::SCRIPT_TIBETAN) {
                        // =========================
                        // Reordering TIBETAN
                        // =========================
                        // Tibetan does not need to need a shaper generally, as long as characters are presented in the correct order
                        // so we will do one minor change here:
                        // From ICU: If the present character is a number, and the next character is a pre-number combining mark
                        // then the two characters are reordered
                        // From MS OTL spec the following are Digit modifiers (Md): 0F18–0F19, 0F3E–0F3F
                        // Digits: 0F20–0F33
                        // On testing only 0x0F3F (pre-based mark) seems to need re-ordering
                        for ($ptr = 0; $ptr < count($this->ot_ldata) - 1; $ptr++) {
                            if (Indic::in_range($this->ot_ldata[$ptr]['uni'], 0xf20, 0xf33) && $this->ot_ldata[$ptr + 1]['uni'] == 0xf3f) {
                                $tmp = $this->ot_ldata[$ptr + 1];
                                $this->ot_ldata[$ptr + 1] = $this->ot_ldata[$ptr];
                                $this->ot_ldata[$ptr] = $tmp;
                            }
                        }
                        // =========================
                        // Decomposition for TIBETAN
                        // =========================
                        /* Recommended, but does not seem to change anything...
                        		  for($ptr=0; $ptr<count($this->OTLdata); $ptr++) {
                        		  $char = $this->OTLdata[$ptr]['uni'];
                        		  $sub = Indic::decompose_indic($char);
                        		  if ($sub) {
                        		  $newinfo = array();
                        		  for($i=0;$i<count($sub);$i++) {
                        		  $newinfo[$i] = array();
                        		  $ucd_record = Ucdn::get_ucd_record($sub[$i]);
                        		  $newinfo[$i]['general_category'] = $ucd_record[0];
                        		  $newinfo[$i]['bidi_type'] = $ucd_record[2];
                        		  $charasstr = $this->unicode_hex($sub[$i]);
                        		  if (strpos($this->GlyphClassMarks, $charasstr)!==false) { $newinfo[$i]['group'] =  'M'; }
                        		  else { $newinfo[$i]['group'] =  'C'; }
                        		  $newinfo[$i]['uni'] =  $sub[$i];
                        		  $newinfo[$i]['hex'] =  $charasstr;
                        		  }
                        		  array_splice($this->OTLdata, $ptr, 1, $newinfo);
                        		  $ptr += count($sub)-1;
                        		  }
                        		  }
                        		 */
                    }
                    //-----------------------------------------------------------------------------------
                    // b. Apply all GSUB Lookups (in order specified in lookup list)
                    //-----------------------------------------------------------------------------------
                    $tags = 'locl ccmp pref blwf abvf pstf pres abvs blws psts haln rlig calt liga clig mset  RQD';
                    // pref blwf abvf pstf required for Tibetan
                    // " RQD" is a non-standard tag in Garuda font - presumably intended to be used by default ? "ReQuireD"
                    // Being a 3 letter tag is non-standard, and does not allow it to be set by font-feature-settings
                    /* ?Add these until shapers witten?
                    		  Hangul:   ljmo vjmo tjmo
                    		 */
                    $omittags = '';
                    $use_gsu_btags = $tags;
                    if (!empty($this->mpdf->ot_ltags)) {
                        $use_gsu_btags = $this->_apply_tag_settings($tags, $gsub_features, $omittags, false);
                    }
                    // APPLY GSUB rules (as long as not Latin + SmallCaps - but not OTL smcp)
                    if (!($this->mpdf->textvar & Text_Vars::FC_SMALLCAPS && $scriptblock == Ucdn::SCRIPT_LATIN && strpos($use_gsu_btags, 'smcp') === false)) {
                        $this->_apply_gsu_brules($use_gsu_btags, $gsu_bscript_tag, $gsu_blangsys);
                    }
                }
            }
            // Shapers - KHMER & THAI & LAO - Replace Word boundary marker with U+200B
            // Also TIBETAN (no shaper)
            //=======================================================
            if ($this->shaper == "K" || $this->shaper == "T" || $this->shaper == "L" || $scriptblock == Ucdn::SCRIPT_TIBETAN) {
                // Set up properties to insert a U+200B character
                $newinfo = [];
                //$newinfo[0] = array('general_category' => 1, 'bidi_type' => 14, 'group' => 'S', 'uni' => 0x200B, 'hex' => '0200B');
                $newinfo[0] = ['general_category' => Ucdn::UNICODE_GENERAL_CATEGORY_FORMAT, 'bidi_type' => Ucdn::BIDI_CLASS_BN, 'group' => 'S', 'uni' => 0x200b, 'hex' => '0200B'];
                // Then insert U+200B at (after) all word end boundaries
                for ($i = count($this->ot_ldata) - 1; $i > 0; $i--) {
                    // Make sure after GSUB that wordend has not been moved - check next char is not in the same syllable
                    if (isset($this->ot_ldata[$i]['wordend']) && $this->ot_ldata[$i]['wordend'] && isset($this->ot_ldata[$i + 1]['uni']) && (!isset($this->ot_ldata[$i + 1]['syllable']) || !isset($this->ot_ldata[$i + 1]['syllable']) || $this->ot_ldata[$i + 1]['syllable'] != $this->ot_ldata[$i]['syllable'])) {
                        array_splice($this->ot_ldata, $i + 1, 0, $newinfo);
                        $this->_update_ligature_marks($i, 1);
                    } elseif ($this->ot_ldata[$i]['uni'] == 0x2e) {
                        // Word end if Full-stop.
                        array_splice($this->ot_ldata, $i + 1, 0, $newinfo);
                        $this->_update_ligature_marks($i, 1);
                    }
                }
            }
            // Shapers - INDIC & ARABIC & KHMER & SINHALA  & MYANMAR - Remove ZWJ and ZWNJ
            //=======================================================
            if ($this->shaper == 'I' || $this->shaper == 'S' || $this->shaper == 'A' || $this->shaper == 'K' || $this->shaper == 'M') {
                // Remove ZWJ and ZWNJ
                for ($i = 0; $i < count($this->ot_ldata); $i++) {
                    if ($this->ot_ldata[$i]['uni'] == 8204 || $this->ot_ldata[$i]['uni'] == 8205) {
                        array_splice($this->ot_ldata, $i, 1);
                        $this->_update_ligature_marks($i, -1);
                    }
                }
            }
            ////////////////////////////////////////////////////////////////
            ////////////////////////////////////////////////////////////////
            //////////       GPOS          /////////////////////////////////
            ////////////////////////////////////////////////////////////////
            ////////////////////////////////////////////////////////////////
            if ($use_otl & 0xff && $gpo_sscript_tag && $gpo_slangsys && $gpos_features) {
                $this->Entry = [];
                $this->Exit = [];
                // 6. Load GPOS data, Coverage & Lookups
                //=================================================================
                $font_cache_filename = $this->mpdf->current_font['fontkey'] . '.GPOSdata.json';
                if (!isset($this->gpo_sdata[$this->fontkey]) && $this->font_cache->json_has($font_cache_filename)) {
                    $this->lu_coverage = $this->gpo_sdata[$this->fontkey]['LuCoverage'] = $this->font_cache->json_load($font_cache_filename);
                } else {
                    $this->lu_coverage = $this->gpo_sdata[$this->fontkey]['LuCoverage'];
                }
                $this->gpos_lookups = $this->mpdf->current_font['GPOSLookups'];
                // 7. Select Feature tags to use (incl optional)
                //==============================
                $tags = 'abvm blwm mark mkmk curs cpsp dist requ';
                // Default set
                // 'requ' is not listed in the Microsoft registry of Feature tags
                // Found in Arial Unicode MS, it repositions the baseline for punctuation in Kannada script
                // ZZZ96
                // Set kern to be included by default in non-Latin script (? just when shapers used)
                // Kern is used in some fonts to reposition marks etc. and is essential for correct display
                //if ($this->shaper) {$tags .= ' kern'; }
                if ($scriptblock != Ucdn::SCRIPT_LATIN) {
                    $tags .= ' kern';
                }
                $omittags = '';
                $usetags = $tags;
                if (!empty($this->mpdf->ot_ltags)) {
                    $usetags = $this->_apply_tag_settings($tags, $gpos_features, $omittags, false);
                }
                // 8. Get GPOS LookupList from Feature tags
                //==============================
                $lookup_list = [];
                foreach ($gpos_features as $tag => $arr) {
                    if (strpos($usetags, $tag) !== false) {
                        foreach ($arr as $lu) {
                            $lookup_list[$lu] = $tag;
                        }
                    }
                }
                ksort($lookup_list);
                // 9. Apply GPOS Lookups (in order specified in lookup list but selecting from specified tags)
                //==============================
                // APPLY THE GPOS RULES (as long as not Latin + SmallCaps - but not OTL smcp)
                if (!($this->mpdf->textvar & Text_Vars::FC_SMALLCAPS && $scriptblock == Ucdn::SCRIPT_LATIN && strpos($use_gsu_btags, 'smcp') === false)) {
                    $this->_apply_gpo_srules($lookup_list, $is_old_spec);
                    // (sets: $this->OTLdata[n]['GPOSinfo'] XPlacement YPlacement XAdvance Entry Exit )
                }
                // 10. Process cursive text
                //==============================
                if (count($this->Entry) || count($this->Exit)) {
                    // RTL
                    $incurs = false;
                    for ($i = count($this->ot_ldata) - 1; $i >= 0; $i--) {
                        if (isset($this->Entry[$i]) && isset($this->Entry[$i]['Y']) && $this->Entry[$i]['dir'] == 'RTL') {
                            $nextbase = $i - 1;
                            // Set as next base ignoring marks (next base reading RTL in logical oder
                            while (isset($this->ot_ldata[$nextbase]['hex']) && strpos($this->glyph_class_marks, $this->ot_ldata[$nextbase]['hex']) !== false) {
                                $nextbase--;
                            }
                            if (isset($this->Exit[$nextbase]) && isset($this->Exit[$nextbase]['Y'])) {
                                $diff = $this->Entry[$i]['Y'] - $this->Exit[$nextbase]['Y'];
                                if ($incurs === false) {
                                    $incurs = $diff;
                                } else {
                                    $incurs += $diff;
                                }
                                for ($j = $i - 1; $j >= $nextbase; $j--) {
                                    if (isset($this->ot_ldata[$j]['GPOSinfo']['YPlacement'])) {
                                        $this->ot_ldata[$j]['GPOSinfo']['YPlacement'] += $incurs;
                                    } else {
                                        $this->ot_ldata[$j]['GPOSinfo']['YPlacement'] = $incurs;
                                    }
                                }
                                if (isset($this->Exit[$i]['X']) && isset($this->Entry[$nextbase]['X'])) {
                                    $adj = -($this->Entry[$i]['X'] - $this->Exit[$nextbase]['X']);
                                    // If XAdvance is aplied - in order for PDF to position the Advance correctly need to place it on:
                                    // in RTL - the current glyph or the last of any associated marks
                                    if (isset($this->ot_ldata[$nextbase + 1]['GPOSinfo']['XAdvance'])) {
                                        $this->ot_ldata[$nextbase + 1]['GPOSinfo']['XAdvance'] += $adj;
                                    } else {
                                        $this->ot_ldata[$nextbase + 1]['GPOSinfo']['XAdvance'] = $adj;
                                    }
                                }
                            } else {
                                $incurs = false;
                            }
                        } elseif (strpos($this->glyph_class_marks, $this->ot_ldata[$i]['hex']) !== false) {
                            continue;
                        } else {
                            $incurs = false;
                        }
                    }
                    // LTR
                    $incurs = false;
                    for ($i = 0; $i < count($this->ot_ldata); $i++) {
                        if (isset($this->Exit[$i]) && isset($this->Exit[$i]['Y']) && $this->Exit[$i]['dir'] == 'LTR') {
                            $nextbase = $i + 1;
                            // Set as next base ignoring marks
                            while (strpos($this->glyph_class_marks, $this->ot_ldata[$nextbase]['hex']) !== false) {
                                $nextbase++;
                            }
                            if (isset($this->Entry[$nextbase]) && isset($this->Entry[$nextbase]['Y'])) {
                                $diff = $this->Exit[$i]['Y'] - $this->Entry[$nextbase]['Y'];
                                if ($incurs === false) {
                                    $incurs = $diff;
                                } else {
                                    $incurs += $diff;
                                }
                                for ($j = $i + 1; $j <= $nextbase; $j++) {
                                    if (isset($this->ot_ldata[$j]['GPOSinfo']['YPlacement'])) {
                                        $this->ot_ldata[$j]['GPOSinfo']['YPlacement'] += $incurs;
                                    } else {
                                        $this->ot_ldata[$j]['GPOSinfo']['YPlacement'] = $incurs;
                                    }
                                }
                                if (isset($this->Exit[$i]['X']) && isset($this->Entry[$nextbase]['X'])) {
                                    $adj = -($this->Exit[$i]['X'] - $this->Entry[$nextbase]['X']);
                                    // If XAdvance is aplied - in order for PDF to position the Advance correctly need to place it on:
                                    // in LTR - the next glyph, ignoring marks
                                    if (isset($this->ot_ldata[$nextbase]['GPOSinfo']['XAdvance'])) {
                                        $this->ot_ldata[$nextbase]['GPOSinfo']['XAdvance'] += $adj;
                                    } else {
                                        $this->ot_ldata[$nextbase]['GPOSinfo']['XAdvance'] = $adj;
                                    }
                                }
                            } else {
                                $incurs = false;
                            }
                        } elseif (strpos($this->glyph_class_marks, $this->ot_ldata[$i]['hex']) !== false) {
                            continue;
                        } else {
                            $incurs = false;
                        }
                    }
                }
            }
            // end GPOS
            if ($this->debug_otl) {
                $this->_dumpproc('END', '-', '-', '-', '-', 0, '-', 0);
                exit;
            }
            $this->sch_ot_ldata[$sch] = $this->ot_ldata;
            $this->ot_ldata = [];
        }
        // END foreach subchunk
        // 11. Re-assemble and return text string
        //==============================
        $new_gpo_sinfo = [];
        $newchar_data = [];
        $newgroup = '';
        $e = '';
        $ectr = 0;
        for ($sch = 0; $sch <= $subchunk; $sch++) {
            for ($i = 0; $i < count($this->sch_ot_ldata[$sch]); $i++) {
                if (isset($this->sch_ot_ldata[$sch][$i]['GPOSinfo'])) {
                    $new_gpo_sinfo[$ectr] = $this->sch_ot_ldata[$sch][$i]['GPOSinfo'];
                }
                $newchar_data[$ectr] = ['bidi_class' => $this->sch_ot_ldata[$sch][$i]['bidi_type'], 'uni' => $this->sch_ot_ldata[$sch][$i]['uni']];
                $newgroup .= $this->sch_ot_ldata[$sch][$i]['group'];
                $e .= Utf_String::code2utf($this->sch_ot_ldata[$sch][$i]['uni']);
                if (isset($this->mpdf->current_font['subset'])) {
                    $this->mpdf->current_font['subset'][$this->sch_ot_ldata[$sch][$i]['uni']] = $this->sch_ot_ldata[$sch][$i]['uni'];
                }
                $ectr++;
            }
        }
        $this->ot_ldata['GPOSinfo'] = $new_gpo_sinfo;
        $this->ot_ldata['char_data'] = $newchar_data;
        $this->ot_ldata['group'] = $newgroup;
        // This leaves OTLdata::GPOSinfo, ::bidi_type, & ::group
        return $e;
    }
    function _apply_tag_settings($tags, array $Features, $omittags = '', $onlytags = false)
    {
        if (empty($this->mpdf->ot_ltags['Plus']) && empty($this->mpdf->ot_ltags['Minus']) && empty($this->mpdf->ot_ltags['FFPlus']) && empty($this->mpdf->ot_ltags['FFMinus'])) {
            return $tags;
        }
        // Use $tags as starting point
        $usetags = $tags;
        // Only set / unset tags which are in the font
        // Ignore tags which are in $omittags
        // If $onlytags, then just unset tags which are already in the Tag list
        $fp = $fm = $ffp = $ffm = '';
        // Font features to enable - set by font-variant-xx
        if (isset($this->mpdf->ot_ltags['Plus'])) {
            $fp = $this->mpdf->ot_ltags['Plus'];
        }
        preg_match_all('/([a-zA-Z0-9]{4})/', $fp, $m);
        for ($i = 0; $i < count($m[0]); $i++) {
            $t = $m[1][$i];
            // Is it a valid tag?
            if (isset($Features[$t]) && strpos($omittags, $t) === false && (!$onlytags || strpos($tags, $t) !== false)) {
                $usetags .= ' ' . $t;
            }
        }
        // Font features to disable - set by font-variant-xx
        if (isset($this->mpdf->ot_ltags['Minus'])) {
            $fm = $this->mpdf->ot_ltags['Minus'];
        }
        preg_match_all('/([a-zA-Z0-9]{4})/', $fm, $m);
        for ($i = 0; $i < count($m[0]); $i++) {
            $t = $m[1][$i];
            // Is it a valid tag?
            if (isset($Features[$t]) && strpos($omittags, $t) === false && (!$onlytags || strpos($tags, $t) !== false)) {
                $usetags = str_replace($t, '', $usetags);
            }
        }
        // Font features to enable - set by font-feature-settings
        if (isset($this->mpdf->ot_ltags['FFPlus'])) {
            $ffp = $this->mpdf->ot_ltags['FFPlus'];
            // Font Features - may include integer: salt4
        }
        preg_match_all('/([a-zA-Z0-9]{4})([\d+]*)/', $ffp, $m);
        for ($i = 0; $i < count($m[0]); $i++) {
            $t = $m[1][$i];
            // Is it a valid tag?
            if (isset($Features[$t]) && strpos($omittags, $t) === false && (!$onlytags || strpos($tags, $t) !== false)) {
                $usetags .= ' ' . $m[0][$i];
                //  - may include integer: salt4
            }
        }
        // Font features to disable - set by font-feature-settings
        if (isset($this->mpdf->ot_ltags['FFMinus'])) {
            $ffm = $this->mpdf->ot_ltags['FFMinus'];
        }
        preg_match_all('/([a-zA-Z0-9]{4})/', $ffm, $m);
        for ($i = 0; $i < count($m[0]); $i++) {
            $t = $m[1][$i];
            // Is it a valid tag?
            if (isset($Features[$t]) && strpos($omittags, $t) === false && (!$onlytags || strpos($tags, $t) !== false)) {
                $usetags = str_replace($t, '', $usetags);
            }
        }
        return $usetags;
    }
    function _apply_gsu_brules($usetags, $script_tag, $langsys)
    {
        // Features from all Tags are applied together, in Lookup List order.
        // For Indic - should be applied one syllable at a time
        // - Implemented in functions checkContextMatch and checkContextMatchMultiple by failing to match if outside scope of current 'syllable'
        // if $this->restrictToSyllable is true
        $gsub_features = $this->mpdf->current_font['GSUBFeatures'][$script_tag][$langsys];
        $lookup_list = [];
        foreach ($gsub_features as $tag => $arr) {
            if (strpos($usetags, $tag) !== false) {
                foreach ($arr as $lu) {
                    $lookup_list[$lu] = $tag;
                }
            }
        }
        ksort($lookup_list);
        foreach ($lookup_list as $lu => $tag) {
            $Type = $this->gsub_lookups[$lu]['Type'];
            $Flag = $this->gsub_lookups[$lu]['Flag'];
            $mark_filtering_set = $this->gsub_lookups[$lu]['MarkFilteringSet'];
            $tag_int = 1;
            if (preg_match('/' . $tag . '([0-9]{1,2})/', $usetags, $m)) {
                $tag_int = $m[1];
            }
            $ptr = 0;
            // Test each glyph sequentially
            while ($ptr < count($this->ot_ldata)) {
                // whilst there is another glyph ..0064
                $curr_glyph = $this->ot_ldata[$ptr]['hex'];
                $curr_gid = $this->ot_ldata[$ptr]['uni'];
                $shift = 1;
                foreach ($this->gsub_lookups[$lu]['Subtables'] as $c => $subtable_offset) {
                    // NB Coverage only looks at glyphs for position 1 (esp. 7.3 and 8.3)
                    if (isset($this->gs_lu_coverage[$lu][$c][$curr_gid])) {
                        // Get rules from font GSUB subtable
                        $shift = $this->_apply_gsu_bsubtable($lu, $c, $ptr, $curr_glyph, $curr_gid, $subtable_offset - $this->GSUB_offset, $Type, $Flag, $mark_filtering_set, $this->gs_lu_coverage[$lu][$c], 0, $tag, 0, $tag_int);
                        if ($shift) {
                            break;
                        }
                    }
                }
                if ($shift == 0) {
                    $shift = 1;
                }
                $ptr += $shift;
            }
        }
    }
    function _apply_gsu_brules_singly($usetags, $script_tag, $langsys)
    {
        // Features are applied one at a time, working through each codepoint
        $gsub_features = $this->mpdf->current_font['GSUBFeatures'][$script_tag][$langsys];
        $tags = explode(' ', $usetags);
        foreach ($tags as $usetag) {
            $lookup_list = [];
            foreach ($gsub_features as $tag => $arr) {
                if (strpos($usetags, $tag) !== false) {
                    foreach ($arr as $lu) {
                        $lookup_list[$lu] = $tag;
                    }
                }
            }
            ksort($lookup_list);
            $ptr = 0;
            // Test each glyph sequentially
            while ($ptr < count($this->ot_ldata)) {
                // whilst there is another glyph ..0064
                $curr_glyph = $this->ot_ldata[$ptr]['hex'];
                $curr_gid = $this->ot_ldata[$ptr]['uni'];
                $shift = 1;
                foreach ($lookup_list as $lu => $tag) {
                    $Type = $this->gsub_lookups[$lu]['Type'];
                    $Flag = $this->gsub_lookups[$lu]['Flag'];
                    $mark_filtering_set = $this->gsub_lookups[$lu]['MarkFilteringSet'];
                    $tag_int = 1;
                    if (preg_match('/' . $tag . '([0-9]{1,2})/', $usetags, $m)) {
                        $tag_int = $m[1];
                    }
                    foreach ($this->gsub_lookups[$lu]['Subtables'] as $c => $subtable_offset) {
                        // NB Coverage only looks at glyphs for position 1 (esp. 7.3 and 8.3)
                        if (isset($this->gs_lu_coverage[$lu][$c][$curr_gid])) {
                            // Get rules from font GSUB subtable
                            $shift = $this->_apply_gsu_bsubtable($lu, $c, $ptr, $curr_glyph, $curr_gid, $subtable_offset - $this->GSUB_offset, $Type, $Flag, $mark_filtering_set, $this->gs_lu_coverage[$lu][$c], 0, $tag, 0, $tag_int);
                            if ($shift) {
                                break 2;
                            }
                        }
                    }
                }
                if ($shift == 0) {
                    $shift = 1;
                }
                $ptr += $shift;
            }
        }
    }
    function _apply_gsu_brules_myanmar($usetags, $script_tag, $langsys)
    {
        // $usetags = locl ccmp rphf pref blwf pstf';
        // applied to all characters
        $gsub_features = $this->mpdf->current_font['GSUBFeatures'][$script_tag][$langsys];
        // ALL should be applied one syllable at a time
        // Implemented in functions checkContextMatch and checkContextMatchMultiple by failing to match if outside scope of current 'syllable'
        $tags = explode(' ', $usetags);
        foreach ($tags as $usetag) {
            $lookup_list = [];
            foreach ($gsub_features as $tag => $arr) {
                if ($tag == $usetag) {
                    foreach ($arr as $lu) {
                        $lookup_list[$lu] = $tag;
                    }
                }
            }
            ksort($lookup_list);
            foreach ($lookup_list as $lu => $tag) {
                $Type = $this->gsub_lookups[$lu]['Type'];
                $Flag = $this->gsub_lookups[$lu]['Flag'];
                $mark_filtering_set = $this->gsub_lookups[$lu]['MarkFilteringSet'];
                $tag_int = 1;
                if (preg_match('/' . $tag . '([0-9]{1,2})/', $usetags, $m)) {
                    $tag_int = $m[1];
                }
                $ptr = 0;
                // Test each glyph sequentially
                while ($ptr < count($this->ot_ldata)) {
                    // whilst there is another glyph ..0064
                    $curr_glyph = $this->ot_ldata[$ptr]['hex'];
                    $curr_gid = $this->ot_ldata[$ptr]['uni'];
                    $shift = 1;
                    foreach ($this->gsub_lookups[$lu]['Subtables'] as $c => $subtable_offset) {
                        // NB Coverage only looks at glyphs for position 1 (esp. 7.3 and 8.3)
                        if (isset($this->gs_lu_coverage[$lu][$c][$curr_gid])) {
                            // Get rules from font GSUB subtable
                            $shift = $this->_apply_gsu_bsubtable($lu, $c, $ptr, $curr_glyph, $curr_gid, $subtable_offset - $this->GSUB_offset, $Type, $Flag, $mark_filtering_set, $this->gs_lu_coverage[$lu][$c], 0, $usetag, 0, $tag_int);
                            if ($shift) {
                                break;
                            }
                        }
                    }
                    if ($shift == 0) {
                        $shift = 1;
                    }
                    $ptr += $shift;
                }
            }
        }
    }
    function _apply_gsu_brules_indic($usetags, $script_tag, $langsys, $is_old_spec)
    {
        // $usetags = 'locl ccmp nukt akhn rphf rkrf pref blwf half pstf vatu cjct'; then later - init
        // rphf, pref, blwf, half, abvf, pstf, and init are only applied where ['mask'] indicates:  Indic::FLAG(Indic::RPHF);
        // The rest are applied to all characters
        $gsub_features = $this->mpdf->current_font['GSUBFeatures'][$script_tag][$langsys];
        // ALL should be applied one syllable at a time
        // Implemented in functions checkContextMatch and checkContextMatchMultiple by failing to match if outside scope of current 'syllable'
        $tags = explode(' ', $usetags);
        foreach ($tags as $usetag) {
            $lookup_list = [];
            foreach ($gsub_features as $tag => $arr) {
                if ($tag == $usetag) {
                    foreach ($arr as $lu) {
                        $lookup_list[$lu] = $tag;
                    }
                }
            }
            ksort($lookup_list);
            foreach ($lookup_list as $lu => $tag) {
                $Type = $this->gsub_lookups[$lu]['Type'];
                $Flag = $this->gsub_lookups[$lu]['Flag'];
                $mark_filtering_set = $this->gsub_lookups[$lu]['MarkFilteringSet'];
                $tag_int = 1;
                if (preg_match('/' . $tag . '([0-9]{1,2})/', $usetags, $m)) {
                    $tag_int = $m[1];
                }
                $ptr = 0;
                // Test each glyph sequentially
                while ($ptr < count($this->ot_ldata)) {
                    // whilst there is another glyph ..0064
                    $curr_glyph = $this->ot_ldata[$ptr]['hex'];
                    $curr_gid = $this->ot_ldata[$ptr]['uni'];
                    $shift = 1;
                    foreach ($this->gsub_lookups[$lu]['Subtables'] as $c => $subtable_offset) {
                        // NB Coverage only looks at glyphs for position 1 (esp. 7.3 and 8.3)
                        if (isset($this->gs_lu_coverage[$lu][$c][$curr_gid])) {
                            if (strpos('rphf pref blwf half pstf cfar init', $usetag) !== false) {
                                // only apply when mask indicates
                                $mask = 0;
                                switch ($usetag) {
                                    case 'rphf':
                                        $mask = 1 << Indic::RPHF;
                                        break;
                                    case 'pref':
                                        $mask = 1 << Indic::PREF;
                                        break;
                                    case 'blwf':
                                        $mask = 1 << Indic::BLWF;
                                        break;
                                    case 'half':
                                        $mask = 1 << Indic::HALF;
                                        break;
                                    case 'pstf':
                                        $mask = 1 << Indic::PSTF;
                                        break;
                                    case 'cfar':
                                        $mask = 1 << Indic::CFAR;
                                        break;
                                    case 'init':
                                        $mask = 1 << Indic::INIT;
                                        break;
                                }
                                if (!($this->ot_ldata[$ptr]['mask'] & $mask)) {
                                    continue;
                                }
                            }
                            // Get rules from font GSUB subtable
                            $shift = $this->_apply_gsu_bsubtable($lu, $c, $ptr, $curr_glyph, $curr_gid, $subtable_offset - $this->GSUB_offset, $Type, $Flag, $mark_filtering_set, $this->gs_lu_coverage[$lu][$c], 0, $usetag, $is_old_spec, $tag_int);
                            if ($shift) {
                                break;
                            }
                        } elseif (static::_OTL_OLD_SPEC_COMPAT_1 && $Type == 4 && !$is_old_spec && strpos('0094D 009CD 00A4D 00ACD 00B4D 00BCD 00C4D 00CCD 00D4D', $curr_glyph) !== false) {
                            // only apply when 'pref blwf pstf' tags, and when mask indicates
                            if (strpos('pref blwf pstf', $usetag) !== false) {
                                $mask = 0;
                                switch ($usetag) {
                                    case 'pref':
                                        $mask = 1 << Indic::PREF;
                                        break;
                                    case 'blwf':
                                        $mask = 1 << Indic::BLWF;
                                        break;
                                    case 'pstf':
                                        $mask = 1 << Indic::PSTF;
                                        break;
                                }
                                if (!($this->ot_ldata[$ptr]['mask'] & $mask)) {
                                    continue;
                                }
                                if (!isset($this->ot_ldata[$ptr + 1])) {
                                    continue;
                                }
                                $next_glyph = $this->ot_ldata[$ptr + 1]['hex'];
                                $next_gid = $this->ot_ldata[$ptr + 1]['uni'];
                                if (isset($this->gs_lu_coverage[$lu][$c][$next_gid])) {
                                    // Get rules from font GSUB subtable
                                    $shift = $this->_apply_gsu_bsubtable_special($lu, $c, $ptr, $curr_glyph, $curr_gid, $next_glyph, $next_gid, $subtable_offset - $this->GSUB_offset, $Type, $this->gs_lu_coverage[$lu][$c]);
                                    if ($shift) {
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    if ($shift == 0) {
                        $shift = 1;
                    }
                    $ptr += $shift;
                }
            }
        }
    }
    function _apply_gsu_bsubtable_special($lookup_id, $subtable, $ptr, $curr_glyph, $curr_gid, $next_glyph, $next_gid, $subtable_offset, $Type, array $lu_coverage)
    {
        // Special case for Indic
        // Check to substitute Halant-Consonant in PREF, BLWF or PSTF
        // i.e. new spec but GSUB tables have Consonant-Halant in Lookups e.g. FreeSerif, which
        // incorrectly just moved old spec tables to new spec. Uniscribe seems to cope with this
        // See also ttffontsuni.php
        $this->seek($subtable_offset);
        $this->read_ushort();
        // Subtable contains Consonant - Halant
        // Text string contains Halant ($CurrGlyph) - Consonant ($nextGlyph)
        // Halant has already been matched, and already checked that $nextGID is in Coverage table
        ////////////////////////////////////////////////////////////////////////////////
        // Only does: LookupType 4: Ligature Substitution Subtable : n to 1
        ////////////////////////////////////////////////////////////////////////////////
        $this->read_ushort();
        $next_glyph_pos = $lu_coverage[$next_gid];
        $this->read_short();
        $this->skip($next_glyph_pos * 2);
        $lig_set = $subtable_offset + $this->read_short();
        $this->seek($lig_set);
        $lig_count = $this->read_short();
        // LigatureSet i.e. all starting with the same Glyph $nextGlyph [Consonant]
        $ligature_offset = [];
        for ($g = 0; $g < $lig_count; $g++) {
            $ligature_offset[$g] = $lig_set + $this->read_ushort();
        }
        for ($g = 0; $g < $lig_count; $g++) {
            // Ligature tables
            $this->seek($ligature_offset[$g]);
            $lig_glyph = $this->read_ushort();
            $substitute = $this->glyph_to_char($lig_glyph);
            $comp_count = $this->read_ushort();
            if ($comp_count != 2) {
                return 0;
            }
            // Only expecting to work with 2:1 (and no ignore characters in between)
            $gid = $this->read_ushort();
            $check_glyph = $this->glyph_to_char($gid);
            // Other component/input Glyphs starting at position 2 (arrayindex 1)
            if ($curr_gid == $check_glyph) {
                $match = true;
            } else {
                $match = false;
                break;
            }
            $glyph_pos = [];
            $glyph_pos[] = $ptr;
            $glyph_pos[] = $ptr + 1;
            $shift = $this->gsu_bsubstitute($ptr, $substitute, 4, $glyph_pos);
            // GlyphPos contains positions to set null
            if ($shift) {
                return 1;
            }
        }
        return 0;
    }
    function _apply_gsu_bsubtable($lookup_id, $subtable, $ptr, $curr_glyph, $curr_gid, $subtable_offset, $Type, $Flag, $mark_filtering_set, array $lu_coverage, $level, $current_tag, $is_old_spec, $tag_int)
    {
        $ignore = $this->_get_gco_mignore_string($Flag);
        // Lets start
        $this->seek($subtable_offset);
        $subst_format = $this->read_ushort();
        ////////////////////////////////////////////////////////////////////////////////
        // LookupType 1: Single Substitution Subtable : 1 to 1
        ////////////////////////////////////////////////////////////////////////////////
        if ($Type == 1) {
            // Flag = Ignore
            if ($this->_check_gco_mignore($Flag, $curr_glyph, $mark_filtering_set)) {
                return 0;
            }
            $coverage_offset = $subtable_offset + $this->read_ushort();
            $glyph_pos = $lu_coverage[$curr_gid];
            //===========
            // Format 1:
            //===========
            if ($subst_format == 1) {
                // Calculated output glyph indices
                $delta_glyph_id = $this->read_short();
                $this->seek($coverage_offset);
                $glyphs = $this->_get_coverage_gid();
                $glyph_id = $glyphs[$glyph_pos] + $delta_glyph_id;
            } elseif ($subst_format == 2) {
                // Specified output glyph indices
                $glyph_count = $this->read_ushort();
                $this->skip($glyph_pos * 2);
                $glyph_id = $this->read_ushort();
            }
            $substitute = $this->glyph_to_char($glyph_id);
            $shift = $this->gsu_bsubstitute($ptr, $substitute, $Type);
            if ($this->debug_otl && $shift) {
                $this->_dumpproc('GSUB', $lookup_id, $subtable, $Type, $subst_format, $ptr, $curr_glyph, $level);
            }
            if ($shift) {
                return 1;
            }
            return 0;
        }
        ////////////////////////////////////////////////////////////////////////////////
        // LookupType 2: Multiple Substitution Subtable : 1 to n
        ////////////////////////////////////////////////////////////////////////////////
        if ($Type == 2) {
            // Flag = Ignore
            if ($this->_check_gco_mignore($Flag, $curr_glyph, $mark_filtering_set)) {
                return 0;
            }
            $Coverage = $subtable_offset + $this->read_ushort();
            $glyph_pos = $lu_coverage[$curr_gid];
            $this->skip(2);
            $this->skip($glyph_pos * 2);
            $Sequences = $subtable_offset + $this->read_short();
            $this->seek($Sequences);
            $glyph_count = $this->read_short();
            $substitute_glyphs = [];
            for ($g = 0; $g < $glyph_count; $g++) {
                $sgid = $this->read_ushort();
                $substitute_glyphs[] = $this->glyph_to_char($sgid);
            }
            $shift = $this->gsu_bsubstitute($ptr, $substitute_glyphs, $Type);
            if ($this->debug_otl && $shift) {
                $this->_dumpproc('GSUB', $lookup_id, $subtable, $Type, $subst_format, $ptr, $curr_glyph, $level);
            }
            if ($shift) {
                return $shift;
            }
            return 0;
        }
        ////////////////////////////////////////////////////////////////////////////////
        // LookupType 3: Alternate Forms : 1 to 1(n)
        ////////////////////////////////////////////////////////////////////////////////
        if ($Type == 3) {
            // Flag = Ignore
            if ($this->_check_gco_mignore($Flag, $curr_glyph, $mark_filtering_set)) {
                return 0;
            }
            $Coverage = $subtable_offset + $this->read_ushort();
            $alternate_set_count = $this->read_short();
            ///////////////////////////////////////////////////////////////////////////////!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            // Need to set alternate IF set by CSS3 font-feature for a tag
            // i.e. if this is 'salt' alternate may be set to 2
            // default value will be $alt=1 ( === index of 0 in list of alternates)
            $alt = 1;
            // $alt=1 points to Alternative[0]
            if ($tag_int > 1) {
                $alt = $tag_int;
            }
            ///////////////////////////////////////////////////////////////////////////////!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            if ($alt == 0) {
                return 0;
            }
            // If specified alternate not present, cancel [ or could default $alt = 1 ?]
            $glyph_pos = $lu_coverage[$curr_gid];
            $this->skip($glyph_pos * 2);
            $alternate_sets = $subtable_offset + $this->read_short();
            $this->seek($alternate_sets);
            $alternate_glyph_count = $this->read_short();
            if ($alt > $alternate_glyph_count) {
                return 0;
            }
            // If specified alternate not present, cancel [ or could default $alt = 1 ?]
            $this->skip(($alt - 1) * 2);
            $glyph_id = $this->read_ushort();
            $substitute = $this->glyph_to_char($glyph_id);
            $shift = $this->gsu_bsubstitute($ptr, $substitute, $Type);
            if ($this->debug_otl && $shift) {
                $this->_dumpproc('GSUB', $lookup_id, $subtable, $Type, $subst_format, $ptr, $curr_glyph, $level);
            }
            if ($shift) {
                return 1;
            }
            return 0;
        }
        ////////////////////////////////////////////////////////////////////////////////
        // LookupType 4: Ligature Substitution Subtable : n to 1
        ////////////////////////////////////////////////////////////////////////////////
        if ($Type == 4) {
            // Flag = Ignore
            if ($this->_check_gco_mignore($Flag, $curr_glyph, $mark_filtering_set)) {
                return 0;
            }
            $Coverage = $subtable_offset + $this->read_ushort();
            $first_glyph_pos = $lu_coverage[$curr_gid];
            $lig_set_count = $this->read_short();
            $this->skip($first_glyph_pos * 2);
            $lig_set = $subtable_offset + $this->read_short();
            $this->seek($lig_set);
            $lig_count = $this->read_short();
            // LigatureSet i.e. all starting with the same first Glyph $currGlyph
            $ligature_offset = [];
            for ($g = 0; $g < $lig_count; $g++) {
                $ligature_offset[$g] = $lig_set + $this->read_ushort();
            }
            for ($g = 0; $g < $lig_count; $g++) {
                // Ligature tables
                $this->seek($ligature_offset[$g]);
                $lig_glyph = $this->read_ushort();
                // Output Ligature GlyphID
                $substitute = $this->glyph_to_char($lig_glyph);
                $comp_count = $this->read_ushort();
                $spos = $ptr;
                $match = true;
                $glyph_pos = [];
                $glyph_pos[] = $spos;
                for ($l = 1; $l < $comp_count; $l++) {
                    $gid = $this->read_ushort();
                    $check_glyph = $this->glyph_to_char($gid);
                    // Other component/input Glyphs starting at position 2 (arrayindex 1)
                    $spos++;
                    //while $this->OTLdata[$spos]['uni'] is an "ignore" =>  spos++
                    while (isset($this->ot_ldata[$spos]) && strpos($ignore, $this->ot_ldata[$spos]['hex']) !== false) {
                        $spos++;
                    }
                    if (isset($this->ot_ldata[$spos]) && $this->ot_ldata[$spos]['uni'] == $check_glyph) {
                        $glyph_pos[] = $spos;
                    } else {
                        $match = false;
                        break;
                    }
                }
                if ($match) {
                    $shift = $this->gsu_bsubstitute($ptr, $substitute, $Type, $glyph_pos);
                    // GlyphPos contains positions to set null
                    if ($this->debug_otl && $shift) {
                        $this->_dumpproc('GSUB', $lookup_id, $subtable, $Type, $subst_format, $ptr, $curr_glyph, $level);
                    }
                    if ($shift) {
                        return $spos - $ptr + 1 - ($comp_count - 1);
                    }
                }
            }
            return 0;
        }
        ////////////////////////////////////////////////////////////////////////////////
        // LookupType 5: Contextual Substitution Subtable
        ////////////////////////////////////////////////////////////////////////////////
        if ($Type == 5) {
            //===========
            // Format 1: Simple Context Glyph Substitution
            //===========
            if ($subst_format == 1) {
                $coverage_table_offset = $subtable_offset + $this->read_ushort();
                $sub_rule_set_count = $this->read_ushort();
                $sub_rule_set_offset = [];
                for ($b = 0; $b < $sub_rule_set_count; $b++) {
                    $offset = $this->read_ushort();
                    if ($offset == 0x0) {
                        $sub_rule_set_offset[] = $offset;
                    } else {
                        $sub_rule_set_offset[] = $subtable_offset + $offset;
                    }
                }
                // SubRuleSet tables: All contexts beginning with the same glyph
                // Select the SubRuleSet required using the position of the glyph in the coverage table
                $glyph_pos = $lu_coverage[$curr_gid];
                if ($sub_rule_set_offset[$glyph_pos] > 0) {
                    $this->seek($sub_rule_set_offset[$glyph_pos]);
                    $sub_rule_cnt = $this->read_ushort();
                    $sub_rule = [];
                    for ($b = 0; $b < $sub_rule_cnt; $b++) {
                        $sub_rule[$b] = $sub_rule_set_offset[$glyph_pos] + $this->read_ushort();
                    }
                    for ($b = 0; $b < $sub_rule_cnt; $b++) {
                        // EACH RULE
                        $this->seek($sub_rule[$b]);
                        $input_glyph_count = $this->read_ushort();
                        $subst_count = $this->read_ushort();
                        $Backtrack = [];
                        $Lookahead = [];
                        $Input = [];
                        $Input[0] = $this->ot_ldata[$ptr]['uni'];
                        for ($r = 1; $r < $input_glyph_count; $r++) {
                            $gid = $this->read_ushort();
                            $Input[$r] = $this->glyph_to_char($gid);
                        }
                        $matched = $this->check_context_match($Input, $Backtrack, $Lookahead, $ignore, $ptr);
                        if ($matched) {
                            if ($this->debug_otl) {
                                $this->_dumpproc('GSUB', $lookup_id, $subtable, $Type, $subst_format, $ptr, $curr_glyph, $level);
                            }
                            for ($p = 0; $p < $subst_count; $p++) {
                                // EACH LOOKUP
                                $sequence_index[$p] = $this->read_ushort();
                                $lookup_list_index[$p] = $this->read_ushort();
                            }
                            for ($p = 0; $p < $subst_count; $p++) {
                                // Apply  $LookupListIndex  at   $SequenceIndex
                                if ($sequence_index[$p] >= $input_glyph_count) {
                                    continue;
                                }
                                $lu = $lookup_list_index[$p];
                                $lu_type = $this->gsub_lookups[$lu]['Type'];
                                $lu_flag = $this->gsub_lookups[$lu]['Flag'];
                                $lu_mark_filtering_set = $this->gsub_lookups[$lu]['MarkFilteringSet'];
                                $luptr = $matched[$sequence_index[$p]];
                                $lucurr_glyph = $this->ot_ldata[$luptr]['hex'];
                                $lucurr_gid = $this->ot_ldata[$luptr]['uni'];
                                foreach ($this->gsub_lookups[$lu]['Subtables'] as $luc => $lusubtable_offset) {
                                    $shift = $this->_apply_gsu_bsubtable($lu, $luc, $luptr, $lucurr_glyph, $lucurr_gid, $lusubtable_offset - $this->GSUB_offset, $lu_type, $lu_flag, $lu_mark_filtering_set, $this->gs_lu_coverage[$lu][$luc], 1, $current_tag, $is_old_spec, $tag_int);
                                    if ($shift) {
                                        break;
                                    }
                                }
                            }
                            if (!defined("OMIT_OTL_FIX_3") || OMIT_OTL_FIX_3 != 1) {
                                return $shift;
                            }
                            return $input_glyph_count;
                            // should be + matched ignores in Input Sequence
                        }
                    }
                }
                return 0;
            }
            //===========
            // Format 2:
            //===========
            // Format 2: Class-based Context Glyph Substitution
            if ($subst_format == 2) {
                $coverage_table_offset = $subtable_offset + $this->read_ushort();
                $input_class_def_offset = $subtable_offset + $this->read_ushort();
                $sub_class_set_cnt = $this->read_ushort();
                $sub_class_set_offset = [];
                for ($b = 0; $b < $sub_class_set_cnt; $b++) {
                    $offset = $this->read_ushort();
                    if ($offset == 0x0) {
                        $sub_class_set_offset[] = $offset;
                    } else {
                        $sub_class_set_offset[] = $subtable_offset + $offset;
                    }
                }
                $input_classes = $this->_get_classes($input_class_def_offset);
                for ($s = 0; $s < $sub_class_set_cnt; $s++) {
                    // $SubClassSet is ordered by input class-may be NULL
                    // Select $SubClassSet if currGlyph is in First Input Class
                    if ($sub_class_set_offset[$s] > 0 && isset($input_classes[$s][$curr_gid])) {
                        $this->seek($sub_class_set_offset[$s]);
                        $sub_class_rule_cnt = $this->read_ushort();
                        $sub_class_rule = [];
                        for ($b = 0; $b < $sub_class_rule_cnt; $b++) {
                            $sub_class_rule[$b] = $sub_class_set_offset[$s] + $this->read_ushort();
                        }
                        for ($b = 0; $b < $sub_class_rule_cnt; $b++) {
                            // EACH RULE
                            $this->seek($sub_class_rule[$b]);
                            $input_glyph_count = $this->read_ushort();
                            $subst_count = $this->read_ushort();
                            $Input = [];
                            for ($r = 1; $r < $input_glyph_count; $r++) {
                                $Input[$r] = $this->read_ushort();
                            }
                            $input_class = $s;
                            $input_glyphs = [];
                            $input_glyphs[0] = $input_classes[$input_class];
                            if ($input_glyph_count > 1) {
                                //  NB starts at 1
                                for ($gcl = 1; $gcl < $input_glyph_count; $gcl++) {
                                    $classindex = $Input[$gcl];
                                    if (isset($input_classes[$classindex])) {
                                        $input_glyphs[$gcl] = $input_classes[$classindex];
                                    } else {
                                        $input_glyphs[$gcl] = '';
                                    }
                                }
                            }
                            // Class 0 contains all the glyphs NOT in the other classes
                            $class0excl = [];
                            for ($gc = 1; $gc <= count($input_classes); $gc++) {
                                if (is_array($input_classes[$gc])) {
                                    $class0excl = $class0excl + $input_classes[$gc];
                                }
                            }
                            $backtrack_glyphs = [];
                            $lookahead_glyphs = [];
                            $matched = $this->check_context_match_multiple_uni($input_glyphs, $backtrack_glyphs, $lookahead_glyphs, $ignore, $ptr, $class0excl);
                            if ($matched) {
                                if ($this->debug_otl) {
                                    $this->_dumpproc('GSUB', $lookup_id, $subtable, $Type, $subst_format, $ptr, $curr_glyph, $level);
                                }
                                for ($p = 0; $p < $subst_count; $p++) {
                                    // EACH LOOKUP
                                    $sequence_index[$p] = $this->read_ushort();
                                    $lookup_list_index[$p] = $this->read_ushort();
                                }
                                for ($p = 0; $p < $subst_count; $p++) {
                                    // Apply  $LookupListIndex  at   $SequenceIndex
                                    if ($sequence_index[$p] >= $input_glyph_count) {
                                        continue;
                                    }
                                    $lu = $lookup_list_index[$p];
                                    $lu_type = $this->gsub_lookups[$lu]['Type'];
                                    $lu_flag = $this->gsub_lookups[$lu]['Flag'];
                                    $lu_mark_filtering_set = $this->gsub_lookups[$lu]['MarkFilteringSet'];
                                    $luptr = $matched[$sequence_index[$p]];
                                    $lucurr_glyph = $this->ot_ldata[$luptr]['hex'];
                                    $lucurr_gid = $this->ot_ldata[$luptr]['uni'];
                                    foreach ($this->gsub_lookups[$lu]['Subtables'] as $luc => $lusubtable_offset) {
                                        $shift = $this->_apply_gsu_bsubtable($lu, $luc, $luptr, $lucurr_glyph, $lucurr_gid, $lusubtable_offset - $this->GSUB_offset, $lu_type, $lu_flag, $lu_mark_filtering_set, $this->gs_lu_coverage[$lu][$luc], 1, $current_tag, $is_old_spec, $tag_int);
                                        if ($shift) {
                                            break;
                                        }
                                    }
                                }
                                if (!defined("OMIT_OTL_FIX_3") || OMIT_OTL_FIX_3 != 1) {
                                    return $shift;
                                }
                                return $input_glyph_count;
                                // should be + matched ignores in Input Sequence
                            }
                        }
                    }
                }
                return 0;
            }
            //===========
            // Format 3:
            //===========
            // Format 3: Coverage-based Context Glyph Substitution
            if ($subst_format == 3) {
                throw new \Mpdf\Mpdf_Exception("GSUB Lookup Type " . $Type . " Format " . $subst_format . " not TESTED YET.");
            }
        } elseif ($Type == 6) {
            //===========
            // Format 1:
            //===========
            // Format 1: Simple Chaining Context Glyph Substitution
            if ($subst_format == 1) {
                $Coverage = $subtable_offset + $this->read_ushort();
                $glyph_pos = $lu_coverage[$curr_gid];
                $chain_sub_rule_set_count = $this->read_ushort();
                // All of the ChainSubRule tables defining contexts that begin with the same first glyph are grouped together and defined in a ChainSubRuleSet table
                $this->skip($glyph_pos * 2);
                $chain_sub_rule_set = $subtable_offset + $this->read_ushort();
                $this->seek($chain_sub_rule_set);
                $chain_sub_rule_count = $this->read_ushort();
                for ($s = 0; $s < $chain_sub_rule_count; $s++) {
                    $chain_sub_rule[$s] = $chain_sub_rule_set + $this->read_ushort();
                }
                for ($s = 0; $s < $chain_sub_rule_count; $s++) {
                    $this->seek($chain_sub_rule[$s]);
                    $backtrack_glyph_count = $this->read_ushort();
                    $Backtrack = [];
                    for ($b = 0; $b < $backtrack_glyph_count; $b++) {
                        $gid = $this->read_ushort();
                        $Backtrack[] = $this->glyph_to_char($gid);
                    }
                    $Input = [];
                    $Input[0] = $this->ot_ldata[$ptr]['uni'];
                    $input_glyph_count = $this->read_ushort();
                    for ($b = 1; $b < $input_glyph_count; $b++) {
                        $gid = $this->read_ushort();
                        $Input[$b] = $this->glyph_to_char($gid);
                    }
                    $lookahead_glyph_count = $this->read_ushort();
                    $Lookahead = [];
                    for ($b = 0; $b < $lookahead_glyph_count; $b++) {
                        $gid = $this->read_ushort();
                        $Lookahead[] = $this->glyph_to_char($gid);
                    }
                    $matched = $this->check_context_match($Input, $Backtrack, $Lookahead, $ignore, $ptr);
                    if ($matched) {
                        if ($this->debug_otl) {
                            $this->_dumpproc('GSUB', $lookup_id, $subtable, $Type, $subst_format, $ptr, $curr_glyph, $level);
                        }
                        $subst_count = $this->read_ushort();
                        for ($p = 0; $p < $subst_count; $p++) {
                            // SubstLookupRecord
                            $subst_lookup_record[$p]['SequenceIndex'] = $this->read_ushort();
                            $subst_lookup_record[$p]['LookupListIndex'] = $this->read_ushort();
                        }
                        for ($p = 0; $p < $subst_count; $p++) {
                            // Apply  $SubstLookupRecord[$p]['LookupListIndex']  at   $SubstLookupRecord[$p]['SequenceIndex']
                            if ($subst_lookup_record[$p]['SequenceIndex'] >= $input_glyph_count) {
                                continue;
                            }
                            $lu = $subst_lookup_record[$p]['LookupListIndex'];
                            $lu_type = $this->gsub_lookups[$lu]['Type'];
                            $lu_flag = $this->gsub_lookups[$lu]['Flag'];
                            $lu_mark_filtering_set = $this->gsub_lookups[$lu]['MarkFilteringSet'];
                            $luptr = $matched[$subst_lookup_record[$p]['SequenceIndex']];
                            $lucurr_glyph = $this->ot_ldata[$luptr]['hex'];
                            $lucurr_gid = $this->ot_ldata[$luptr]['uni'];
                            foreach ($this->gsub_lookups[$lu]['Subtables'] as $luc => $lusubtable_offset) {
                                $shift = $this->_apply_gsu_bsubtable($lu, $luc, $luptr, $lucurr_glyph, $lucurr_gid, $lusubtable_offset - $this->GSUB_offset, $lu_type, $lu_flag, $lu_mark_filtering_set, $this->gs_lu_coverage[$lu][$luc], 1, $current_tag, $is_old_spec, $tag_int);
                                if ($shift) {
                                    break;
                                }
                            }
                        }
                        if (!defined("OMIT_OTL_FIX_3") || OMIT_OTL_FIX_3 != 1) {
                            return $shift;
                        }
                        return $input_glyph_count;
                        // should be + matched ignores in Input Sequence
                    }
                }
                return 0;
            }
            //===========
            // Format 2:
            //===========
            // Format 2: Class-based Chaining Context Glyph Substitution  p257
            if ($subst_format == 2) {
                // NB Format 2 specifies fixed class assignments (identical for each position in the backtrack, input, or lookahead sequence) and exclusive classes (a glyph cannot be in more than one class at a time)
                $coverage_table_offset = $subtable_offset + $this->read_ushort();
                $backtrack_class_def_offset = $subtable_offset + $this->read_ushort();
                $input_class_def_offset = $subtable_offset + $this->read_ushort();
                $lookahead_class_def_offset = $subtable_offset + $this->read_ushort();
                $chain_sub_class_set_cnt = $this->read_ushort();
                $chain_sub_class_set_offset = [];
                for ($b = 0; $b < $chain_sub_class_set_cnt; $b++) {
                    $offset = $this->read_ushort();
                    if ($offset == 0x0) {
                        $chain_sub_class_set_offset[] = $offset;
                    } else {
                        $chain_sub_class_set_offset[] = $subtable_offset + $offset;
                    }
                }
                $backtrack_classes = $this->_get_classes($backtrack_class_def_offset);
                $input_classes = $this->_get_classes($input_class_def_offset);
                $lookahead_classes = $this->_get_classes($lookahead_class_def_offset);
                for ($s = 0; $s < $chain_sub_class_set_cnt; $s++) {
                    // $ChainSubClassSet is ordered by input class-may be NULL
                    // Select $ChainSubClassSet if currGlyph is in First Input Class
                    if ($chain_sub_class_set_offset[$s] > 0 && isset($input_classes[$s][$curr_gid])) {
                        $this->seek($chain_sub_class_set_offset[$s]);
                        $chain_sub_class_rule_cnt = $this->read_ushort();
                        $chain_sub_class_rule = [];
                        for ($b = 0; $b < $chain_sub_class_rule_cnt; $b++) {
                            $chain_sub_class_rule[$b] = $chain_sub_class_set_offset[$s] + $this->read_ushort();
                        }
                        for ($b = 0; $b < $chain_sub_class_rule_cnt; $b++) {
                            // EACH RULE
                            $this->seek($chain_sub_class_rule[$b]);
                            $backtrack_glyph_count = $this->read_ushort();
                            for ($r = 0; $r < $backtrack_glyph_count; $r++) {
                                $Backtrack[$r] = $this->read_ushort();
                            }
                            $input_glyph_count = $this->read_ushort();
                            for ($r = 1; $r < $input_glyph_count; $r++) {
                                $Input[$r] = $this->read_ushort();
                            }
                            $lookahead_glyph_count = $this->read_ushort();
                            for ($r = 0; $r < $lookahead_glyph_count; $r++) {
                                $Lookahead[$r] = $this->read_ushort();
                            }
                            // These contain classes of glyphs as arrays
                            // $InputClasses[(class)] e.g. 0x02E6,0x02E7,0x02E8
                            // $LookaheadClasses[(class)]
                            // $BacktrackClasses[(class)]
                            // These contain arrays of classIndexes
                            // [Backtrack] [Lookahead] and [Input] (Input is from the second position only)
                            $input_class = $s;
                            //???
                            $input_glyphs = [];
                            $input_glyphs[0] = $input_classes[$input_class];
                            if ($input_glyph_count > 1) {
                                //  NB starts at 1
                                for ($gcl = 1; $gcl < $input_glyph_count; $gcl++) {
                                    $classindex = $Input[$gcl];
                                    if (isset($input_classes[$classindex])) {
                                        $input_glyphs[$gcl] = $input_classes[$classindex];
                                    } else {
                                        $input_glyphs[$gcl] = '';
                                    }
                                }
                            }
                            // Class 0 contains all the glyphs NOT in the other classes
                            $class0excl = [];
                            for ($gc = 1; $gc <= count($input_classes); $gc++) {
                                if (isset($input_classes[$gc])) {
                                    $class0excl = $class0excl + $input_classes[$gc];
                                }
                            }
                            if ($backtrack_glyph_count) {
                                for ($gcl = 0; $gcl < $backtrack_glyph_count; $gcl++) {
                                    $classindex = $Backtrack[$gcl];
                                    if (isset($backtrack_classes[$classindex])) {
                                        $backtrack_glyphs[$gcl] = $backtrack_classes[$classindex];
                                    } else {
                                        $backtrack_glyphs[$gcl] = '';
                                    }
                                }
                            } else {
                                $backtrack_glyphs = [];
                            }
                            // Class 0 contains all the glyphs NOT in the other classes
                            $bclass0excl = [];
                            for ($gc = 1; $gc <= count($backtrack_classes); $gc++) {
                                if (isset($backtrack_classes[$gc])) {
                                    $bclass0excl = $bclass0excl + $backtrack_classes[$gc];
                                }
                            }
                            if ($lookahead_glyph_count) {
                                for ($gcl = 0; $gcl < $lookahead_glyph_count; $gcl++) {
                                    $classindex = $Lookahead[$gcl];
                                    if (isset($lookahead_classes[$classindex])) {
                                        $lookahead_glyphs[$gcl] = $lookahead_classes[$classindex];
                                    } else {
                                        $lookahead_glyphs[$gcl] = '';
                                    }
                                }
                            } else {
                                $lookahead_glyphs = [];
                            }
                            // Class 0 contains all the glyphs NOT in the other classes
                            $lclass0excl = [];
                            for ($gc = 1; $gc <= count($lookahead_classes); $gc++) {
                                if (isset($lookahead_classes[$gc])) {
                                    $lclass0excl = $lclass0excl + $lookahead_classes[$gc];
                                }
                            }
                            $matched = $this->check_context_match_multiple_uni($input_glyphs, $backtrack_glyphs, $lookahead_glyphs, $ignore, $ptr, $class0excl, $bclass0excl, $lclass0excl);
                            if ($matched) {
                                if ($this->debug_otl) {
                                    $this->_dumpproc('GSUB', $lookup_id, $subtable, $Type, $subst_format, $ptr, $curr_glyph, $level);
                                }
                                $subst_count = $this->read_ushort();
                                for ($p = 0; $p < $subst_count; $p++) {
                                    // EACH LOOKUP
                                    $sequence_index[$p] = $this->read_ushort();
                                    $lookup_list_index[$p] = $this->read_ushort();
                                }
                                for ($p = 0; $p < $subst_count; $p++) {
                                    // Apply  $LookupListIndex  at   $SequenceIndex
                                    if ($sequence_index[$p] >= $input_glyph_count) {
                                        continue;
                                    }
                                    $lu = $lookup_list_index[$p];
                                    $lu_type = $this->gsub_lookups[$lu]['Type'];
                                    $lu_flag = $this->gsub_lookups[$lu]['Flag'];
                                    $lu_mark_filtering_set = $this->gsub_lookups[$lu]['MarkFilteringSet'];
                                    $luptr = $matched[$sequence_index[$p]];
                                    $lucurr_glyph = $this->ot_ldata[$luptr]['hex'];
                                    $lucurr_gid = $this->ot_ldata[$luptr]['uni'];
                                    foreach ($this->gsub_lookups[$lu]['Subtables'] as $luc => $lusubtable_offset) {
                                        $shift = $this->_apply_gsu_bsubtable($lu, $luc, $luptr, $lucurr_glyph, $lucurr_gid, $lusubtable_offset - $this->GSUB_offset, $lu_type, $lu_flag, $lu_mark_filtering_set, $this->gs_lu_coverage[$lu][$luc], 1, $current_tag, $is_old_spec, $tag_int);
                                        if ($shift) {
                                            break;
                                        }
                                    }
                                }
                                if (!defined("OMIT_OTL_FIX_3") || OMIT_OTL_FIX_3 != 1) {
                                    return $shift;
                                }
                                return $input_glyph_count;
                                // should be + matched ignores in Input Sequence
                            }
                        }
                    }
                }
                return 0;
            }
            //===========
            // Format 3:
            //===========
            // Format 3: Coverage-based Chaining Context Glyph Substitution  p259
            if ($subst_format == 3) {
                $backtrack_glyph_count = $this->read_ushort();
                for ($b = 0; $b < $backtrack_glyph_count; $b++) {
                    $coverage_backtrack_offset[] = $subtable_offset + $this->read_ushort();
                    // in glyph sequence order
                }
                $input_glyph_count = $this->read_ushort();
                for ($b = 0; $b < $input_glyph_count; $b++) {
                    $coverage_input_offset[] = $subtable_offset + $this->read_ushort();
                    // in glyph sequence order
                }
                $lookahead_glyph_count = $this->read_ushort();
                for ($b = 0; $b < $lookahead_glyph_count; $b++) {
                    $coverage_lookahead_offset[] = $subtable_offset + $this->read_ushort();
                    // in glyph sequence order
                }
                $subst_count = $this->read_ushort();
                $save_pos = $this->_pos;
                // Save the point just after PosCount
                $coverage_backtrack_glyphs = [];
                for ($b = 0; $b < $backtrack_glyph_count; $b++) {
                    $this->seek($coverage_backtrack_offset[$b]);
                    $glyphs = $this->_get_coverage();
                    $coverage_backtrack_glyphs[$b] = implode("|", $glyphs);
                }
                $coverage_input_glyphs = [];
                for ($b = 0; $b < $input_glyph_count; $b++) {
                    $this->seek($coverage_input_offset[$b]);
                    $glyphs = $this->_get_coverage();
                    $coverage_input_glyphs[$b] = implode("|", $glyphs);
                }
                $coverage_lookahead_glyphs = [];
                for ($b = 0; $b < $lookahead_glyph_count; $b++) {
                    $this->seek($coverage_lookahead_offset[$b]);
                    $glyphs = $this->_get_coverage();
                    $coverage_lookahead_glyphs[$b] = implode("|", $glyphs);
                }
                $matched = $this->check_context_match_multiple($coverage_input_glyphs, $coverage_backtrack_glyphs, $coverage_lookahead_glyphs, $ignore, $ptr);
                if ($matched) {
                    if ($this->debug_otl) {
                        $this->_dumpproc('GSUB', $lookup_id, $subtable, $Type, $subst_format, $ptr, $curr_glyph, $level);
                    }
                    $this->seek($save_pos);
                    // Return to just after PosCount
                    for ($p = 0; $p < $subst_count; $p++) {
                        // SubstLookupRecord
                        $subst_lookup_record[$p]['SequenceIndex'] = $this->read_ushort();
                        $subst_lookup_record[$p]['LookupListIndex'] = $this->read_ushort();
                    }
                    for ($p = 0; $p < $subst_count; $p++) {
                        // Apply  $SubstLookupRecord[$p]['LookupListIndex']  at   $SubstLookupRecord[$p]['SequenceIndex']
                        if ($subst_lookup_record[$p]['SequenceIndex'] >= $input_glyph_count) {
                            continue;
                        }
                        $lu = $subst_lookup_record[$p]['LookupListIndex'];
                        $lu_type = $this->gsub_lookups[$lu]['Type'];
                        $lu_flag = $this->gsub_lookups[$lu]['Flag'];
                        $lu_mark_filtering_set = $this->gsub_lookups[$lu]['MarkFilteringSet'];
                        $luptr = $matched[$subst_lookup_record[$p]['SequenceIndex']];
                        $lucurr_glyph = $this->ot_ldata[$luptr]['hex'];
                        $lucurr_gid = $this->ot_ldata[$luptr]['uni'];
                        foreach ($this->gsub_lookups[$lu]['Subtables'] as $luc => $lusubtable_offset) {
                            $shift = $this->_apply_gsu_bsubtable($lu, $luc, $luptr, $lucurr_glyph, $lucurr_gid, $lusubtable_offset - $this->GSUB_offset, $lu_type, $lu_flag, $lu_mark_filtering_set, $this->gs_lu_coverage[$lu][$luc], 1, $current_tag, $is_old_spec, $tag_int);
                            if ($shift) {
                                break;
                            }
                        }
                    }
                    if (!defined("OMIT_OTL_FIX_3") || OMIT_OTL_FIX_3 != 1) {
                        return isset($shift) ? $shift : 0;
                    }
                    return $input_glyph_count;
                    // should be + matched ignores in Input Sequence
                }
                return 0;
            }
        } else {
            throw new \Mpdf\Mpdf_Exception("GSUB Lookup Type " . $Type . " not supported.");
        }
    }
    function _update_ligature_marks($pos, $n)
    {
        if ($n > 0) {
            // Update position of Ligatures and associated Marks
            // Foreach lig/assocMarks
            // Any position lpos or mpos > $pos + count($substitute)
            //  $this->assocMarks = array();    // assocMarks[$pos mpos] => array(compID, ligPos)
            //  $this->assocLigs = array(); // Ligatures[$pos lpos] => nc
            for ($p = count($this->ot_ldata) - 1; $p >= $pos + $n; $p--) {
                if (isset($this->assoc_ligs[$p])) {
                    $tmp = $this->assoc_ligs[$p];
                    unset($this->assoc_ligs[$p]);
                    $this->assoc_ligs[$p + $n] = $tmp;
                }
            }
            for ($p = count($this->ot_ldata) - 1; $p >= 0; $p--) {
                if (isset($this->assoc_marks[$p])) {
                    if ($this->assoc_marks[$p]['ligPos'] >= $pos + $n) {
                        $this->assoc_marks[$p]['ligPos'] += $n;
                    }
                    if ($p >= $pos + $n) {
                        $tmp = $this->assoc_marks[$p];
                        unset($this->assoc_marks[$p]);
                        $this->assoc_marks[$p + $n] = $tmp;
                    }
                }
            }
        } elseif ($n < 1) {
            // glyphs removed
            $nrem = -$n;
            // Update position of pre-existing Ligatures and associated Marks
            for ($p = $pos + 1; $p < count($this->ot_ldata); $p++) {
                if (isset($this->assoc_ligs[$p])) {
                    $tmp = $this->assoc_ligs[$p];
                    unset($this->assoc_ligs[$p]);
                    $this->assoc_ligs[$p - $nrem] = $tmp;
                }
            }
            for ($p = 0; $p < count($this->ot_ldata); $p++) {
                if (isset($this->assoc_marks[$p])) {
                    if ($this->assoc_marks[$p]['ligPos'] >= $pos) {
                        $this->assoc_marks[$p]['ligPos'] -= $nrem;
                    }
                    if ($p > $pos) {
                        $tmp = $this->assoc_marks[$p];
                        unset($this->assoc_marks[$p]);
                        $this->assoc_marks[$p - $nrem] = $tmp;
                    }
                }
            }
        }
    }
    function gsu_bsubstitute($pos, $substitute, $Type, $glyph_pos = null)
    {
        // LookupType 1: Simple Substitution Subtable : 1 to 1
        // LookupType 3: Alternate Forms : 1 to 1(n)
        if ($Type == 1 || $Type == 3) {
            $this->ot_ldata[$pos]['uni'] = $substitute;
            $this->ot_ldata[$pos]['hex'] = $this->unicode_hex($substitute);
            return 1;
        }
        // LookupType 2: Multiple Substitution Subtable : 1 to n
        if ($Type == 2) {
            for ($i = 0; $i < count($substitute); $i++) {
                $uni = $substitute[$i];
                $new_ot_ldata[$i] = [];
                $new_ot_ldata[$i]['uni'] = $uni;
                $new_ot_ldata[$i]['hex'] = $this->unicode_hex($uni);
                // Get types of new inserted chars - or replicate type of char being replaced
                //  $bt = Ucdn::get_bidi_class($uni);
                //  if (!$bt) {
                $bt = $this->ot_ldata[$pos]['bidi_type'];
                //  }
                if (strpos($this->glyph_class_marks, $new_ot_ldata[$i]['hex']) !== false) {
                    $gp = 'M';
                } elseif ($uni == 32) {
                    $gp = 'S';
                } else {
                    $gp = 'C';
                }
                // Need to update matra_type ??? of new glyphs inserted ???????????????????????????????????????
                $new_ot_ldata[$i]['bidi_type'] = $bt;
                $new_ot_ldata[$i]['group'] = $gp;
                // Need to update details of new glyphs inserted
                $new_ot_ldata[$i]['general_category'] = $this->ot_ldata[$pos]['general_category'];
                if ($this->shaper == 'I' || $this->shaper == 'K' || $this->shaper == 'S') {
                    $new_ot_ldata[$i]['indic_category'] = $this->ot_ldata[$pos]['indic_category'];
                    $new_ot_ldata[$i]['indic_position'] = $this->ot_ldata[$pos]['indic_position'];
                } elseif ($this->shaper == 'M') {
                    $new_ot_ldata[$i]['myanmar_category'] = $this->ot_ldata[$pos]['myanmar_category'];
                    $new_ot_ldata[$i]['myanmar_position'] = $this->ot_ldata[$pos]['myanmar_position'];
                }
                if (isset($this->ot_ldata[$pos]['mask'])) {
                    $new_ot_ldata[$i]['mask'] = $this->ot_ldata[$pos]['mask'];
                }
                if (isset($this->ot_ldata[$pos]['syllable'])) {
                    $new_ot_ldata[$i]['syllable'] = $this->ot_ldata[$pos]['syllable'];
                }
            }
            if ($this->shaper == 'K' || $this->shaper == 'T' || $this->shaper == 'L') {
                if ($this->ot_ldata[$pos]['wordend']) {
                    $new_ot_ldata[count($substitute) - 1]['wordend'] = true;
                }
            }
            array_splice($this->ot_ldata, $pos, 1, $new_ot_ldata);
            // Replace 1 with n
            // Update position of Ligatures and associated Marks
            // count($substitute)-1  is the number of glyphs added
            $nadd = count($substitute) - 1;
            $this->_update_ligature_marks($pos, $nadd);
            return count($substitute);
        }
        // LookupType 4: Ligature Substitution Subtable : n to 1
        if ($Type == 4) {
            // Create Ligatures and associated Marks
            $first_glyph = $this->ot_ldata[$pos]['hex'];
            // If all components of the ligature are marks (and in the same syllable), we call this a mark ligature.
            $contains_marks = false;
            $contains_nonmarks = false;
            if (isset($this->ot_ldata[$pos]['syllable'])) {
                $current_syllable = $this->ot_ldata[$pos]['syllable'];
            } else {
                $current_syllable = 0;
            }
            for ($i = 0; $i < count($glyph_pos); $i++) {
                // If subsequent components are not Marks as well - don't ligate
                $unistr = $this->ot_ldata[$glyph_pos[$i]]['hex'];
                if ($this->restrict_to_syllable && isset($this->ot_ldata[$glyph_pos[$i]]['syllable']) && $this->ot_ldata[$glyph_pos[$i]]['syllable'] != $current_syllable) {
                    return 0;
                }
                if (strpos($this->glyph_class_marks, $unistr) !== false) {
                    $contains_marks = true;
                } else {
                    $contains_nonmarks = true;
                }
            }
            if ($contains_marks && !$contains_nonmarks) {
                // Mark Ligature (all components are Marks)
                $first_mark_assoc = '';
                if (isset($this->assoc_marks[$pos])) {
                    $first_mark_assoc = $this->assoc_marks[$pos];
                }
                // If all components of the ligature are marks, we call this a mark ligature.
                for ($i = 1; $i < count($glyph_pos); $i++) {
                    // If subsequent components are not Marks as well - don't ligate
                    //      $unistr = $this->OTLdata[$GlyphPos[$i]]['hex'];
                    //      if (strpos($this->GlyphClassMarks, $unistr )===false) { return; }
                    $next_mark_assoc = '';
                    if (isset($this->assoc_marks[$glyph_pos[$i]])) {
                        $next_mark_assoc = $this->assoc_marks[$glyph_pos[$i]];
                    }
                    // If first component was attached to a previous ligature component,
                    // all subsequent components should be attached to the same ligature
                    // component, otherwise we shouldn't ligate them.
                    // If first component was NOT attached to a previous ligature component,
                    // all subsequent components should also NOT be attached to any ligature component,
                    if ($first_mark_assoc != $next_mark_assoc) {
                        // unless they are attached to the first component itself!
                        //          if (!is_array($nextMarkAssoc) || $nextMarkAssoc['ligPos']!= $pos) { return; }
                        // Update/Edit - In test with myanmartext font
                        // &#x1004;&#x103a;&#x1039;&#x1000;&#x1039;&#x1000;&#x103b;&#x103c;&#x103d;&#x1031;&#x102d;
                        // => Lookup 17  E003 E066B E05A 102D
                        // E003 and 102D should form a mark ligature, but 102D is already associated with (non-mark) ligature E05A
                        // So instead of disallowing the mark ligature to form, just dissociate...
                        if (!is_array($next_mark_assoc) || $next_mark_assoc['ligPos'] != $pos) {
                            unset($this->assoc_marks[$glyph_pos[$i]]);
                        }
                    }
                }
                /*
                 * - If it *is* a mark ligature, we don't allocate a new ligature id, and leave
                 *   the ligature to keep its old ligature id.  This will allow it to attach to
                 *   a base ligature in GPOS.  Eg. if the sequence is: LAM,LAM,SHADDA,FATHA,HEH,
                 *   and LAM,LAM,HEH form a ligature, they will leave SHADDA and FATHA wit a
                 *   ligature id and component value of 2.  Then if SHADDA,FATHA form a ligature
                 *   later, we don't want them to lose their ligature id/component, otherwise
                 *   GPOS will fail to correctly position the mark ligature on top of the
                 *   LAM,LAM,HEH ligature.
                 */
                // So if is_array($firstMarkAssoc) - the new (Mark) ligature should keep this association
                $last_pos = $glyph_pos[count($glyph_pos) - 1];
            } else {
                /*
                 * - Ligatures cannot be formed across glyphs attached to different components
                 *   of previous ligatures.  Eg. the sequence is LAM,SHADDA,LAM,FATHA,HEH, and
                 *   LAM,LAM,HEH form a ligature, leaving SHADDA,FATHA next to eachother.
                 *   However, it would be wrong to ligate that SHADDA,FATHA sequence.
                 *   There is an exception to this: If a ligature tries ligating with marks that
                 *   belong to it itself, go ahead, assuming that the font designer knows what
                 *   they are doing (otherwise it can break Indic stuff when a matra wants to
                 *   ligate with a conjunct...)
                 */
                /*
                 * - If a ligature is formed of components that some of which are also ligatures
                 *   themselves, and those ligature components had marks attached to *their*
                 *   components, we have to attach the marks to the new ligature component
                 *   positions!  Now *that*'s tricky!  And these marks may be following the
                 *   last component of the whole sequence, so we should loop forward looking
                 *   for them and update them.
                 *
                 *   Eg. the sequence is LAM,LAM,SHADDA,FATHA,HEH, and the font first forms a
                 *   'calt' ligature of LAM,HEH, leaving the SHADDA and FATHA with a ligature
                 *   id and component == 1.  Now, during 'liga', the LAM and the LAM-HEH ligature
                 *   form a LAM-LAM-HEH ligature.  We need to reassign the SHADDA and FATHA to
                 *   the new ligature with a component value of 2.
                 *
                 *   This in fact happened to a font...  See:
                 *   https://bugzilla.gnome.org/show_bug.cgi?id=437633
                 */
                $curr_comp = 0;
                for ($i = 0; $i < count($glyph_pos); $i++) {
                    if ($i > 0 && isset($this->assoc_ligs[$glyph_pos[$i]])) {
                        // One of the other components is already a ligature
                        $nc = $this->assoc_ligs[$glyph_pos[$i]];
                    } else {
                        $nc = 1;
                    }
                    // While next char to right is a mark (but not the next matched glyph)
                    // ?? + also include a Mark Ligature here
                    $ic = 1;
                    while (($i == count($glyph_pos) - 1 || isset($glyph_pos[$i + 1]) && $glyph_pos[$i] + $ic < $glyph_pos[$i + 1]) && isset($this->ot_ldata[$glyph_pos[$i] + $ic]) && strpos($this->glyph_class_marks, $this->ot_ldata[$glyph_pos[$i] + $ic]['hex']) !== false) {
                        $new_comp = $curr_comp;
                        if (isset($this->assoc_marks[$glyph_pos[$i] + $ic])) {
                            // One of the inbetween Marks is already associated with a Lig
                            // OK as long as it is associated with the current Lig
                            //      if ($this->assocMarks[($GlyphPos[$i]+$ic)]['ligPos'] != ($GlyphPos[$i]+$ic)) { die("Problem #1"); }
                            $new_comp += $this->assoc_marks[$glyph_pos[$i] + $ic]['compID'];
                        }
                        $this->assoc_marks[$glyph_pos[$i] + $ic] = ['compID' => $new_comp, 'ligPos' => $pos];
                        $ic++;
                    }
                    $curr_comp += $nc;
                }
                $last_pos = $glyph_pos[count($glyph_pos) - 1] + $ic - 1;
                $this->assoc_ligs[$pos] = $curr_comp;
                // Number of components in new Ligature
            }
            // Now remove the unwanted glyphs and associated metadata
            $new_ot_ldata[0] = [];
            // Get types of new inserted chars - or replicate type of char being replaced
            //  $bt = Ucdn::get_bidi_class($substitute);
            //  if (!$bt) {
            $bt = $this->ot_ldata[$pos]['bidi_type'];
            //  }
            if (strpos($this->glyph_class_marks, $this->unicode_hex($substitute)) !== false) {
                $gp = 'M';
            } elseif ($substitute == 32) {
                $gp = 'S';
            } else {
                $gp = 'C';
            }
            // Need to update details of new glyphs inserted
            $new_ot_ldata[0]['general_category'] = $this->ot_ldata[$pos]['general_category'];
            $new_ot_ldata[0]['bidi_type'] = $bt;
            $new_ot_ldata[0]['group'] = $gp;
            // KASHIDA: If forming a ligature when the last component was identified as a kashida point (final form)
            // If previous/first component of ligature is a medial form, then keep this as a kashida point
            // TEST (Arabic Typesetting) &#x64a;&#x64e;&#x646;&#x62a;&#x64f;&#x645;
            $ka = 0;
            if (isset($this->ot_ldata[$glyph_pos[count($glyph_pos) - 1]]['GPOSinfo']['kashida'])) {
                $ka = $this->ot_ldata[$glyph_pos[count($glyph_pos) - 1]]['GPOSinfo']['kashida'];
            }
            if ($ka == 1 && isset($this->ot_ldata[$pos]['form']) && $this->ot_ldata[$pos]['form'] == 3) {
                $new_ot_ldata[0]['GPOSinfo']['kashida'] = $ka;
            }
            $new_ot_ldata[0]['uni'] = $substitute;
            $new_ot_ldata[0]['hex'] = $this->unicode_hex($substitute);
            if ($this->shaper == 'I' || $this->shaper == 'K' || $this->shaper == 'S') {
                $new_ot_ldata[0]['indic_category'] = $this->ot_ldata[$pos]['indic_category'];
                $new_ot_ldata[0]['indic_position'] = $this->ot_ldata[$pos]['indic_position'];
            } elseif ($this->shaper == 'M') {
                $new_ot_ldata[0]['myanmar_category'] = $this->ot_ldata[$pos]['myanmar_category'];
                $new_ot_ldata[0]['myanmar_position'] = $this->ot_ldata[$pos]['myanmar_position'];
            }
            if (isset($this->ot_ldata[$pos]['mask'])) {
                $new_ot_ldata[0]['mask'] = $this->ot_ldata[$pos]['mask'];
            }
            if (isset($this->ot_ldata[$pos]['syllable'])) {
                $new_ot_ldata[0]['syllable'] = $this->ot_ldata[$pos]['syllable'];
            }
            $new_ot_ldata[0]['is_ligature'] = true;
            array_splice($this->ot_ldata, $pos, 1, $new_ot_ldata);
            // GlyphPos contains array of arr_pos to set null - not necessarily contiguous
            // +- Remove any assocMarks or assocLigs from the main components (the ones that are deleted)
            for ($i = count($glyph_pos) - 1; $i > 0; $i--) {
                $gpos = $glyph_pos[$i];
                array_splice($this->ot_ldata, $gpos, 1);
                unset($this->assoc_ligs[$gpos]);
                unset($this->assoc_marks[$gpos]);
            }
            //  $this->assocLigs = array(); // Ligatures[$posarr lpos] => nc
            //  $this->assocMarks = array();    // assocMarks[$posarr mpos] => array(compID, ligPos)
            // Update position of pre-existing Ligatures and associated Marks
            // Start after first GlyphPos
            // count($GlyphPos)-1  is the number of glyphs removed from string
            for ($p = $glyph_pos[0] + 1; $p < count($this->ot_ldata) + count($glyph_pos) - 1; $p++) {
                $nrem = 0;
                // Number of Glyphs removed at this point in the string
                for ($i = 0; $i < count($glyph_pos); $i++) {
                    if ($i > 0 && $p > $glyph_pos[$i]) {
                        $nrem++;
                    }
                }
                if (isset($this->assoc_ligs[$p])) {
                    $tmp = $this->assoc_ligs[$p];
                    unset($this->assoc_ligs[$p]);
                    $this->assoc_ligs[$p - $nrem] = $tmp;
                }
                if (isset($this->assoc_marks[$p])) {
                    $tmp = $this->assoc_marks[$p];
                    unset($this->assoc_marks[$p]);
                    if ($tmp['ligPos'] > $glyph_pos[0]) {
                        $tmp['ligPos'] -= $nrem;
                    }
                    $this->assoc_marks[$p - $nrem] = $tmp;
                }
            }
            return 1;
        }
        return 0;
    }
    ////////////////////////////////////////////////////////////////
    //////////       ARABIC        /////////////////////////////////
    ////////////////////////////////////////////////////////////////
    private function arabic_initialise()
    {
        // cf. http://unicode.org/Public/UNIDATA/ArabicShaping.txt
        // http://unicode.org/Public/UNIDATA/extracted/DerivedJoiningType.txt
        // JOIN TO FOLLOWING LETTER IN LOGICAL ORDER (i.e. AS INITIAL/MEDIAL FORM) = Unicode Left-Joining (+ Dual-Joining + Join_Causing 00640)
        $this->arab_left_joining = [
            0x620 => 1,
            0x626 => 1,
            0x628 => 1,
            0x62a => 1,
            0x62b => 1,
            0x62c => 1,
            0x62d => 1,
            0x62e => 1,
            0x633 => 1,
            0x634 => 1,
            0x635 => 1,
            0x636 => 1,
            0x637 => 1,
            0x638 => 1,
            0x639 => 1,
            0x63a => 1,
            0x63b => 1,
            0x63c => 1,
            0x63d => 1,
            0x63e => 1,
            0x63f => 1,
            0x640 => 1,
            0x641 => 1,
            0x642 => 1,
            0x643 => 1,
            0x644 => 1,
            0x645 => 1,
            0x646 => 1,
            0x647 => 1,
            0x649 => 1,
            0x64a => 1,
            0x66e => 1,
            0x66f => 1,
            0x678 => 1,
            0x679 => 1,
            0x67a => 1,
            0x67b => 1,
            0x67c => 1,
            0x67d => 1,
            0x67e => 1,
            0x67f => 1,
            0x680 => 1,
            0x681 => 1,
            0x682 => 1,
            0x683 => 1,
            0x684 => 1,
            0x685 => 1,
            0x686 => 1,
            0x687 => 1,
            0x69a => 1,
            0x69b => 1,
            0x69c => 1,
            0x69d => 1,
            0x69e => 1,
            0x69f => 1,
            0x6a0 => 1,
            0x6a1 => 1,
            0x6a2 => 1,
            0x6a3 => 1,
            0x6a4 => 1,
            0x6a5 => 1,
            0x6a6 => 1,
            0x6a7 => 1,
            0x6a8 => 1,
            0x6a9 => 1,
            0x6aa => 1,
            0x6ab => 1,
            0x6ac => 1,
            0x6ad => 1,
            0x6ae => 1,
            0x6af => 1,
            0x6b0 => 1,
            0x6b1 => 1,
            0x6b2 => 1,
            0x6b3 => 1,
            0x6b4 => 1,
            0x6b5 => 1,
            0x6b6 => 1,
            0x6b7 => 1,
            0x6b8 => 1,
            0x6b9 => 1,
            0x6ba => 1,
            0x6bb => 1,
            0x6bc => 1,
            0x6bd => 1,
            0x6be => 1,
            0x6bf => 1,
            0x6c1 => 1,
            0x6c2 => 1,
            0x6cc => 1,
            0x6ce => 1,
            0x6d0 => 1,
            0x6d1 => 1,
            0x6fa => 1,
            0x6fb => 1,
            0x6fc => 1,
            0x6ff => 1,
            /* Arabic Supplement */
            0x750 => 1,
            0x751 => 1,
            0x752 => 1,
            0x753 => 1,
            0x754 => 1,
            0x755 => 1,
            0x756 => 1,
            0x757 => 1,
            0x758 => 1,
            0x75c => 1,
            0x75d => 1,
            0x75e => 1,
            0x75f => 1,
            0x760 => 1,
            0x761 => 1,
            0x762 => 1,
            0x763 => 1,
            0x764 => 1,
            0x765 => 1,
            0x766 => 1,
            0x767 => 1,
            0x768 => 1,
            0x769 => 1,
            0x76a => 1,
            0x76d => 1,
            0x76e => 1,
            0x76f => 1,
            0x770 => 1,
            0x772 => 1,
            0x775 => 1,
            0x776 => 1,
            0x777 => 1,
            0x77a => 1,
            0x77b => 1,
            0x77c => 1,
            0x77d => 1,
            0x77e => 1,
            0x77f => 1,
            /* Extended Arabic */
            0x8a0 => 1,
            0x8a2 => 1,
            0x8a3 => 1,
            0x8a4 => 1,
            0x8a5 => 1,
            0x8a6 => 1,
            0x8a7 => 1,
            0x8a8 => 1,
            0x8a9 => 1,
            /* 'syrc' Syriac */
            0x712 => 1,
            0x713 => 1,
            0x714 => 1,
            0x71a => 1,
            0x71b => 1,
            0x71c => 1,
            0x71d => 1,
            0x71f => 1,
            0x720 => 1,
            0x721 => 1,
            0x722 => 1,
            0x723 => 1,
            0x724 => 1,
            0x725 => 1,
            0x726 => 1,
            0x727 => 1,
            0x729 => 1,
            0x72b => 1,
            0x72d => 1,
            0x72e => 1,
            0x74e => 1,
            0x74f => 1,
            /* N'Ko */
            0x7ca => 1,
            0x7cb => 1,
            0x7cc => 1,
            0x7cd => 1,
            0x7ce => 1,
            0x7cf => 1,
            0x7d0 => 1,
            0x7d1 => 1,
            0x7d2 => 1,
            0x7d3 => 1,
            0x7d4 => 1,
            0x7d5 => 1,
            0x7d6 => 1,
            0x7d7 => 1,
            0x7d8 => 1,
            0x7d9 => 1,
            0x7da => 1,
            0x7db => 1,
            0x7dc => 1,
            0x7dd => 1,
            0x7de => 1,
            0x7df => 1,
            0x7e0 => 1,
            0x7e1 => 1,
            0x7e2 => 1,
            0x7e3 => 1,
            0x7e4 => 1,
            0x7e5 => 1,
            0x7e6 => 1,
            0x7e7 => 1,
            0x7e8 => 1,
            0x7e9 => 1,
            0x7ea => 1,
            0x7fa => 1,
            /* Mandaic */
            0x841 => 1,
            0x842 => 1,
            0x843 => 1,
            0x844 => 1,
            0x845 => 1,
            0x847 => 1,
            0x848 => 1,
            0x84a => 1,
            0x84b => 1,
            0x84c => 1,
            0x84d => 1,
            0x84e => 1,
            0x850 => 1,
            0x851 => 1,
            0x852 => 1,
            0x853 => 1,
            0x855 => 1,
            /* ZWJ U+200D */
            0x200d => 1,
        ];
        /* JOIN TO PREVIOUS LETTER IN LOGICAL ORDER (i.e. AS FINAL/MEDIAL FORM) = Unicode Right-Joining (+ Dual-Joining + Join_Causing) */
        $this->arab_right_joining = [
            0x620 => 1,
            0x622 => 1,
            0x623 => 1,
            0x624 => 1,
            0x625 => 1,
            0x626 => 1,
            0x627 => 1,
            0x628 => 1,
            0x629 => 1,
            0x62a => 1,
            0x62b => 1,
            0x62c => 1,
            0x62d => 1,
            0x62e => 1,
            0x62f => 1,
            0x630 => 1,
            0x631 => 1,
            0x632 => 1,
            0x633 => 1,
            0x634 => 1,
            0x635 => 1,
            0x636 => 1,
            0x637 => 1,
            0x638 => 1,
            0x639 => 1,
            0x63a => 1,
            0x63b => 1,
            0x63c => 1,
            0x63d => 1,
            0x63e => 1,
            0x63f => 1,
            0x640 => 1,
            0x641 => 1,
            0x642 => 1,
            0x643 => 1,
            0x644 => 1,
            0x645 => 1,
            0x646 => 1,
            0x647 => 1,
            0x648 => 1,
            0x649 => 1,
            0x64a => 1,
            0x66e => 1,
            0x66f => 1,
            0x671 => 1,
            0x672 => 1,
            0x673 => 1,
            0x675 => 1,
            0x676 => 1,
            0x677 => 1,
            0x678 => 1,
            0x679 => 1,
            0x67a => 1,
            0x67b => 1,
            0x67c => 1,
            0x67d => 1,
            0x67e => 1,
            0x67f => 1,
            0x680 => 1,
            0x681 => 1,
            0x682 => 1,
            0x683 => 1,
            0x684 => 1,
            0x685 => 1,
            0x686 => 1,
            0x687 => 1,
            0x688 => 1,
            0x689 => 1,
            0x68a => 1,
            0x68b => 1,
            0x68c => 1,
            0x68d => 1,
            0x68e => 1,
            0x68f => 1,
            0x690 => 1,
            0x691 => 1,
            0x692 => 1,
            0x693 => 1,
            0x694 => 1,
            0x695 => 1,
            0x696 => 1,
            0x697 => 1,
            0x698 => 1,
            0x699 => 1,
            0x69a => 1,
            0x69b => 1,
            0x69c => 1,
            0x69d => 1,
            0x69e => 1,
            0x69f => 1,
            0x6a0 => 1,
            0x6a1 => 1,
            0x6a2 => 1,
            0x6a3 => 1,
            0x6a4 => 1,
            0x6a5 => 1,
            0x6a6 => 1,
            0x6a7 => 1,
            0x6a8 => 1,
            0x6a9 => 1,
            0x6aa => 1,
            0x6ab => 1,
            0x6ac => 1,
            0x6ad => 1,
            0x6ae => 1,
            0x6af => 1,
            0x6b0 => 1,
            0x6b1 => 1,
            0x6b2 => 1,
            0x6b3 => 1,
            0x6b4 => 1,
            0x6b5 => 1,
            0x6b6 => 1,
            0x6b7 => 1,
            0x6b8 => 1,
            0x6b9 => 1,
            0x6ba => 1,
            0x6bb => 1,
            0x6bc => 1,
            0x6bd => 1,
            0x6be => 1,
            0x6bf => 1,
            0x6c0 => 1,
            0x6c1 => 1,
            0x6c2 => 1,
            0x6c3 => 1,
            0x6c4 => 1,
            0x6c5 => 1,
            0x6c6 => 1,
            0x6c7 => 1,
            0x6c8 => 1,
            0x6c9 => 1,
            0x6ca => 1,
            0x6cb => 1,
            0x6cc => 1,
            0x6cd => 1,
            0x6ce => 1,
            0x6cf => 1,
            0x6d0 => 1,
            0x6d1 => 1,
            0x6d2 => 1,
            0x6d3 => 1,
            0x6d5 => 1,
            0x6ee => 1,
            0x6ef => 1,
            0x6fa => 1,
            0x6fb => 1,
            0x6fc => 1,
            0x6ff => 1,
            /* Arabic Supplement */
            0x750 => 1,
            0x751 => 1,
            0x752 => 1,
            0x753 => 1,
            0x754 => 1,
            0x755 => 1,
            0x756 => 1,
            0x757 => 1,
            0x758 => 1,
            0x759 => 1,
            0x75a => 1,
            0x75b => 1,
            0x75c => 1,
            0x75d => 1,
            0x75e => 1,
            0x75f => 1,
            0x760 => 1,
            0x761 => 1,
            0x762 => 1,
            0x763 => 1,
            0x764 => 1,
            0x765 => 1,
            0x766 => 1,
            0x767 => 1,
            0x768 => 1,
            0x769 => 1,
            0x76a => 1,
            0x76b => 1,
            0x76c => 1,
            0x76d => 1,
            0x76e => 1,
            0x76f => 1,
            0x770 => 1,
            0x771 => 1,
            0x772 => 1,
            0x773 => 1,
            0x774 => 1,
            0x775 => 1,
            0x776 => 1,
            0x777 => 1,
            0x778 => 1,
            0x779 => 1,
            0x77a => 1,
            0x77b => 1,
            0x77c => 1,
            0x77d => 1,
            0x77e => 1,
            0x77f => 1,
            /* Extended Arabic */
            0x8a0 => 1,
            0x8a2 => 1,
            0x8a3 => 1,
            0x8a4 => 1,
            0x8a5 => 1,
            0x8a6 => 1,
            0x8a7 => 1,
            0x8a8 => 1,
            0x8a9 => 1,
            0x8aa => 1,
            0x8ab => 1,
            0x8ac => 1,
            /* 'syrc' Syriac */
            0x710 => 1,
            0x712 => 1,
            0x713 => 1,
            0x714 => 1,
            0x715 => 1,
            0x716 => 1,
            0x717 => 1,
            0x718 => 1,
            0x719 => 1,
            0x71a => 1,
            0x71b => 1,
            0x71c => 1,
            0x71d => 1,
            0x71e => 1,
            0x71f => 1,
            0x720 => 1,
            0x721 => 1,
            0x722 => 1,
            0x723 => 1,
            0x724 => 1,
            0x725 => 1,
            0x726 => 1,
            0x727 => 1,
            0x728 => 1,
            0x729 => 1,
            0x72a => 1,
            0x72b => 1,
            0x72c => 1,
            0x72d => 1,
            0x72e => 1,
            0x72f => 1,
            0x74d => 1,
            0x74e => 1,
            0x74f,
            /* N'Ko */
            0x7ca => 1,
            0x7cb => 1,
            0x7cc => 1,
            0x7cd => 1,
            0x7ce => 1,
            0x7cf => 1,
            0x7d0 => 1,
            0x7d1 => 1,
            0x7d2 => 1,
            0x7d3 => 1,
            0x7d4 => 1,
            0x7d5 => 1,
            0x7d6 => 1,
            0x7d7 => 1,
            0x7d8 => 1,
            0x7d9 => 1,
            0x7da => 1,
            0x7db => 1,
            0x7dc => 1,
            0x7dd => 1,
            0x7de => 1,
            0x7df => 1,
            0x7e0 => 1,
            0x7e1 => 1,
            0x7e2 => 1,
            0x7e3 => 1,
            0x7e4 => 1,
            0x7e5 => 1,
            0x7e6 => 1,
            0x7e7 => 1,
            0x7e8 => 1,
            0x7e9 => 1,
            0x7ea => 1,
            0x7fa => 1,
            /* Mandaic */
            0x841 => 1,
            0x842 => 1,
            0x843 => 1,
            0x844 => 1,
            0x845 => 1,
            0x847 => 1,
            0x848 => 1,
            0x84a => 1,
            0x84b => 1,
            0x84c => 1,
            0x84d => 1,
            0x84e => 1,
            0x850 => 1,
            0x851 => 1,
            0x852 => 1,
            0x853 => 1,
            0x855 => 1,
            0x840 => 1,
            0x846 => 1,
            0x849 => 1,
            0x84f => 1,
            0x854 => 1,
            /* Right joining */
            /* ZWJ U+200D */
            0x200d => 1,
        ];
        /* VOWELS = TRANSPARENT-JOINING = Unicode Transparent-Joining type (not just vowels) */
        $this->arab_transparent = [
            0x610 => 1,
            0x611 => 1,
            0x612 => 1,
            0x613 => 1,
            0x614 => 1,
            0x615 => 1,
            0x616 => 1,
            0x617 => 1,
            0x618 => 1,
            0x619 => 1,
            0x61a => 1,
            0x64b => 1,
            0x64c => 1,
            0x64d => 1,
            0x64e => 1,
            0x64f => 1,
            0x650 => 1,
            0x651 => 1,
            0x652 => 1,
            0x653 => 1,
            0x654 => 1,
            0x655 => 1,
            0x656 => 1,
            0x657 => 1,
            0x658 => 1,
            0x659 => 1,
            0x65a => 1,
            0x65b => 1,
            0x65c => 1,
            0x65d => 1,
            0x65e => 1,
            0x65f => 1,
            0x670 => 1,
            0x6d6 => 1,
            0x6d7 => 1,
            0x6d8 => 1,
            0x6d9 => 1,
            0x6da => 1,
            0x6db => 1,
            0x6dc => 1,
            0x6df => 1,
            0x6e0 => 1,
            0x6e1 => 1,
            0x6e2 => 1,
            0x6e3 => 1,
            0x6e4 => 1,
            0x6e7 => 1,
            0x6e8 => 1,
            0x6ea => 1,
            0x6eb => 1,
            0x6ec => 1,
            0x6ed => 1,
            /* Extended Arabic */
            0x8e4 => 1,
            0x8e5 => 1,
            0x8e6 => 1,
            0x8e7 => 1,
            0x8e8 => 1,
            0x8e9 => 1,
            0x8ea => 1,
            0x8eb => 1,
            0x8ec => 1,
            0x8ed => 1,
            0x8ee => 1,
            0x8ef => 1,
            0x8f0 => 1,
            0x8f1 => 1,
            0x8f2 => 1,
            0x8f3 => 1,
            0x8f4 => 1,
            0x8f5 => 1,
            0x8f6 => 1,
            0x8f7 => 1,
            0x8f8 => 1,
            0x8f9 => 1,
            0x8fa => 1,
            0x8fb => 1,
            0x8fc => 1,
            0x8fd => 1,
            0x8fe => 1,
            /* Arabic ligatures in presentation form (converted in 'ccmp' in e.g. Arial and Times ? need to add others in this range) */
            0xfc5e => 1,
            0xfc5f => 1,
            0xfc60 => 1,
            0xfc61 => 1,
            0xfc62 => 1,
            /*  'syrc' Syriac */
            0x70f => 1,
            0x711 => 1,
            0x730 => 1,
            0x731 => 1,
            0x732 => 1,
            0x733 => 1,
            0x734 => 1,
            0x735 => 1,
            0x736 => 1,
            0x737 => 1,
            0x738 => 1,
            0x739 => 1,
            0x73a => 1,
            0x73b => 1,
            0x73c => 1,
            0x73d => 1,
            0x73e => 1,
            0x73f => 1,
            0x740 => 1,
            0x741 => 1,
            0x742 => 1,
            0x743 => 1,
            0x744 => 1,
            0x745 => 1,
            0x746 => 1,
            0x747 => 1,
            0x748 => 1,
            0x749 => 1,
            0x74a => 1,
            /* N'Ko */
            0x7eb => 1,
            0x7ec => 1,
            0x7ed => 1,
            0x7ee => 1,
            0x7ef => 1,
            0x7f0 => 1,
            0x7f1 => 1,
            0x7f2 => 1,
            0x7f3 => 1,
            /* Mandaic */
            0x859 => 1,
            0x85a => 1,
            0x85b => 1,
        ];
    }
    private function arabic_shaper($usetags, $script_tag)
    {
        $chars = [];
        for ($i = 0; $i < count($this->ot_ldata); $i++) {
            $chars[] = $this->ot_ldata[$i]['hex'];
        }
        $crnt_char = null;
        $prev_char = null;
        $next_char = null;
        $output = [];
        $max = count($chars);
        for ($i = $max - 1; $i >= 0; $i--) {
            $crnt_char = $chars[$i];
            if ($i > 0) {
                $prev_char = hexdec($chars[$i - 1]);
            } else {
                $prev_char = null;
            }
            if ($prev_char && isset($this->arab_transparent_join[$prev_char]) && isset($chars[$i - 2])) {
                $prev_char = hexdec($chars[$i - 2]);
                if ($prev_char && isset($this->arab_transparent_join[$prev_char]) && isset($chars[$i - 3])) {
                    $prev_char = hexdec($chars[$i - 3]);
                    if ($prev_char && isset($this->arab_transparent_join[$prev_char]) && isset($chars[$i - 4])) {
                        $prev_char = hexdec($chars[$i - 4]);
                    }
                }
            }
            if ($crnt_char && isset($this->arab_transparent_join[hexdec($crnt_char)])) {
                // If next_char = RightJoining && prev_char = LeftJoining:
                if (isset($chars[$i + 1]) && $chars[$i + 1] && isset($this->arab_right_joining[hexdec($chars[$i + 1])]) && $prev_char && isset($this->arab_left_joining[$prev_char])) {
                    $output[] = $this->get_arab_glyphs($crnt_char, 1, $chars, $i, $script_tag, $usetags);
                    // <final> form
                } else {
                    $output[] = $this->get_arab_glyphs($crnt_char, 0, $chars, $i, $script_tag, $usetags);
                    // <isolated> form
                }
                continue;
            }
            if (hexdec($crnt_char) < 128) {
                $output[] = [$crnt_char, 0];
                $next_char = $crnt_char;
                continue;
            }
            // 0=ISOLATED FORM :: 1=FINAL :: 2=INITIAL :: 3=MEDIAL
            $form = 0;
            if ($prev_char && isset($this->arab_left_joining[$prev_char])) {
                $form++;
            }
            if ($next_char && isset($this->arab_right_joining[hexdec($next_char)])) {
                $form += 2;
            }
            $output[] = $this->get_arab_glyphs($crnt_char, $form, $chars, $i, $script_tag, $usetags);
            $next_char = $crnt_char;
        }
        $ra = array_reverse($output);
        for ($i = 0; $i < count($this->ot_ldata); $i++) {
            $this->ot_ldata[$i]['uni'] = hexdec($ra[$i][0]);
            $this->ot_ldata[$i]['hex'] = $ra[$i][0];
            $this->ot_ldata[$i]['form'] = $ra[$i][1];
            // Actaul form substituted 0=ISOLATED FORM :: 1=FINAL :: 2=INITIAL :: 3=MEDIAL
        }
    }
    private function get_arab_glyphs($char, $type, array &$chars, $i, $script_tag, $usetags)
    {
        // Optional Feature settings    // doesn't control Syriac at present
        if ($type === 0 && strpos($usetags, 'isol') === false || $type === 1 && strpos($usetags, 'fina') === false || $type === 2 && strpos($usetags, 'init') === false || $type === 3 && strpos($usetags, 'medi') === false) {
            return [$char, 0];
        }
        // 0=ISOLATED FORM :: 1=FINAL :: 2=INITIAL :: 3=MEDIAL (:: 4=MED2 :: 5=FIN2 :: 6=FIN3)
        $retk = -1;
        // Alaph 00710 in Syriac
        if ($script_tag == 'syrc' && $char == '00710') {
            // if there is a preceding (base?) character *** should search back to previous base - ignoring vowels and change $n
            // set $n as the position of the last base; for now we'll just do this:
            $n = $i - 1;
            // if the preceding (base) character cannot be joined to
            // not in $this->arabLeftJoining i.e. not a char which can join to the next one
            if (isset($chars[$n]) && isset($this->arab_left_joining[hexdec($chars[$n])])) {
                // if in the middle of Syriac words
                if (isset($chars[$i + 1]) && preg_match('/[\x{0700}-\x{0745}]/u', Utf_String::code2utf(hexdec($chars[$n]))) && preg_match('/[\x{0700}-\x{0745}]/u', Utf_String::code2utf(hexdec($chars[$i + 1]))) && isset($this->arab_glyphs[$char][4])) {
                    $retk = 4;
                } elseif (!isset($chars[$i + 1]) || !preg_match('/[\x{0700}-\x{0745}]/u', Utf_String::code2utf(hexdec($chars[$i + 1])))) {
                    // if preceding base character IS (00715|00716|0072A)
                    if (strpos('0715|0716|072A', $chars[$n]) !== false && isset($this->arab_glyphs[$char][6])) {
                        $retk = 6;
                    } elseif (isset($this->arab_glyphs[$char][5])) {
                        $retk = 5;
                    }
                }
            }
            if ($retk != -1) {
                return [$this->arab_glyphs[$char][$retk], $retk];
            }
            return [$char, 0];
        }
        if (($type > 0 || $type === 0) && isset($this->arab_glyphs[$char][$type])) {
            $retk = $type;
        } elseif ($type == 3 && isset($this->arab_glyphs[$char][1])) {
            // if <medial> not defined, but <final>, return <final>
            $retk = 1;
        } elseif ($type == 2 && isset($this->arab_glyphs[$char][0])) {
            // if <initial> not defined, but <isolated>, return <isolated>
            $retk = 0;
        }
        if ($retk != -1) {
            $match = true;
            // If GSUB includes a Backtrack or Lookahead condition (e.g. font ArabicTypesetting)
            if (isset($this->arab_glyphs[$char]['prel'][$retk]) && $this->arab_glyphs[$char]['prel'][$retk]) {
                $ig = 1;
                foreach ($this->arab_glyphs[$char]['prel'][$retk] as $k => $v) {
                    // $k starts 0, 1...
                    if (!isset($chars[$i - $ig - $k])) {
                        $match = false;
                    } elseif (strpos($v, $chars[$i - $ig - $k]) === false) {
                        while (strpos($this->arab_glyphs[$char]['ignore'][$retk], $chars[$i - $ig - $k]) !== false) {
                            // ignore
                            $ig++;
                        }
                        if (!isset($chars[$i - $ig - $k])) {
                            $match = false;
                        } elseif (strpos($v, $chars[$i - $ig - $k]) === false) {
                            $match = false;
                        }
                    }
                }
            }
            if (isset($this->arab_glyphs[$char]['postl'][$retk]) && $this->arab_glyphs[$char]['postl'][$retk]) {
                $ig = 1;
                foreach ($this->arab_glyphs[$char]['postl'][$retk] as $k => $v) {
                    // $k starts 0, 1...
                    if (!isset($chars[$i + $ig + $k])) {
                        $match = false;
                    } elseif (strpos($v, $chars[$i + $ig + $k]) === false) {
                        while (strpos($this->arab_glyphs[$char]['ignore'][$retk], $chars[$i + $ig + $k]) !== false) {
                            // ignore
                            $ig++;
                        }
                        if (!isset($chars[$i + $ig + $k])) {
                            $match = false;
                        } elseif (strpos($v, $chars[$i + $ig + $k]) === false) {
                            $match = false;
                        }
                    }
                }
            }
            if ($match) {
                return [$this->arab_glyphs[$char][$retk], $retk];
            }
            return [$char, 0];
        }
        return [$char, 0];
    }
    ////////////////////////////////////////////////////////////////
    /////////////////       LINE BREAKING    ///////////////////////
    ////////////////////////////////////////////////////////////////
    /////////////       TIBETAN LINE BREAKING    ///////////////////
    ////////////////////////////////////////////////////////////////
    // Sets $this->OTLdata[$i]['wordend']=true at possible end of word boundaries
    private function tibetan_line_breaking()
    {
        for ($ptr = 0; $ptr < count($this->ot_ldata); $ptr++) {
            // Break opportunities at U+0F0B Tsheg or U=0F0D
            if (isset($this->ot_ldata[$ptr]['uni']) && ($this->ot_ldata[$ptr]['uni'] == 0xf0b || $this->ot_ldata[$ptr]['uni'] == 0xf0d)) {
                if (isset($this->ot_ldata[$ptr + 1]['uni']) && ($this->ot_ldata[$ptr + 1]['uni'] == 0xf0d || $this->ot_ldata[$ptr + 1]['uni'] == 0xf0e)) {
                    continue;
                }
                // Set end of word marker in OTLdata at matchpos
                $this->ot_ldata[$ptr]['wordend'] = true;
            }
        }
    }
    /**
     * South East Asian Linebreaking (Thai, Khmer and Lao) using dictionary of words
     *
     * Sets $this->OTLdata[$i]['wordend']=true at possible end of word boundaries
     */
    private function sea_line_breaking()
    {
        // Load Line-breaking dictionary
        if (!isset($this->lbdicts[$this->shaper]) && file_exists(__DIR__ . '/../data/linebrdict' . $this->shaper . '.dat')) {
            $this->lbdicts[$this->shaper] = file_get_contents(__DIR__ . '/../data/linebrdict' . $this->shaper . '.dat');
        }
        $dict =& $this->lbdicts[$this->shaper];
        // Find all word boundaries and mark end of word $this->OTLdata[$i]['wordend']=true on last character
        // If Thai, allow for possible suffixes (not in Lao or Khmer)
        // repeater/ellision characters
        // (0x0E2F);        // Ellision character THAI_PAIYANNOI 0x0E2F  UTF-8 0xE0 0xB8 0xAF
        // (0x0E46);        // Repeat character THAI_MAIYAMOK 0x0E46   UTF-8 0xE0 0xB9 0x86
        // (0x0EC6);        // Repeat character LAO   UTF-8 0xE0 0xBB 0x86
        $rollover = [];
        $ptr = 0;
        while ($ptr < count($this->ot_ldata) - 3) {
            if (count($rollover)) {
                $matches = $rollover;
                $rollover = [];
            } else {
                $matches = $this->checkwordmatch($dict, $ptr);
            }
            if (count($matches) == 1) {
                $matchpos = $matches[0];
                // Check for repeaters - if so $matchpos++
                if (isset($this->ot_ldata[$matchpos + 1]['uni']) && ($this->ot_ldata[$matchpos + 1]['uni'] == 0xe2f || $this->ot_ldata[$matchpos + 1]['uni'] == 0xe46 || $this->ot_ldata[$matchpos + 1]['uni'] == 0xec6)) {
                    $matchpos++;
                }
                // Set end of word marker in OTLdata at matchpos
                $this->ot_ldata[$matchpos]['wordend'] = true;
                $ptr = $matchpos + 1;
            } elseif (empty($matches)) {
                $ptr++;
                // Move past any ASCII characters
                while (isset($this->ot_ldata[$ptr]['uni']) && $this->ot_ldata[$ptr]['uni'] >> 8 == 0) {
                    $ptr++;
                }
            } else {
                // Multiple matches
                $secondmatch = false;
                for ($m = count($matches) - 1; $m >= 0; $m--) {
                    //for ($m=0;$m<count($matches);$m++) {
                    $firstmatch = $matches[$m];
                    $matches2 = $this->checkwordmatch($dict, $firstmatch + 1);
                    if (count($matches2)) {
                        // Set end of word marker in OTLdata at matchpos
                        $this->ot_ldata[$firstmatch]['wordend'] = true;
                        $ptr = $firstmatch + 1;
                        $rollover = $matches2;
                        $secondmatch = true;
                        break;
                    }
                }
                if (!$secondmatch) {
                    // Set end of word marker in OTLdata at end of longest first match
                    $this->ot_ldata[$matches[count($matches) - 1]]['wordend'] = true;
                    $ptr = $matches[count($matches) - 1] + 1;
                    // Move past any ASCII characters
                    while (isset($this->ot_ldata[$ptr]['uni']) && $this->ot_ldata[$ptr]['uni'] >> 8 == 0) {
                        $ptr++;
                    }
                }
            }
        }
    }
    private function checkwordmatch(&$dict, $ptr)
    {
        /*
         Node type: Split.
         Divide at < 98 >= 98
         Offset for >= 98 == 79    (long 4-byte unsigned)
        
         Node type: Linear match.
         Char = 97
        
         Intermediate match
        
         Final match
        */
        $dictptr = 0;
        $ok = true;
        $matches = [];
        while ($ok) {
            $x = ord($dict[$dictptr]);
            $c = $this->ot_ldata[$ptr]['uni'] & 0xff;
            if ($x == static::_DICT_INTERMEDIATE_MATCH) {
                //echo "DICT_INTERMEDIATE_MATCH: ".dechex($c).'<br />';
                // Do not match if next character in text is a Mark
                if (isset($this->ot_ldata[$ptr]['uni']) && strpos($this->glyph_class_marks, $this->ot_ldata[$ptr]['hex']) === false) {
                    $matches[] = $ptr - 1;
                }
                $dictptr++;
            } elseif ($x == static::_DICT_FINAL_MATCH) {
                //echo "DICT_FINAL_MATCH: ".dechex($c).'<br />';
                // Do not match if next character in text is a Mark
                if (isset($this->ot_ldata[$ptr]['uni']) && strpos($this->glyph_class_marks, $this->ot_ldata[$ptr]['hex']) === false) {
                    $matches[] = $ptr - 1;
                }
                return $matches;
            } elseif ($x == static::_DICT_NODE_TYPE_LINEAR) {
                //echo "DICT_NODE_TYPE_LINEAR: ".dechex($c).'<br />';
                $dictptr++;
                $m = ord($dict[$dictptr]);
                if ($c == $m) {
                    $ptr++;
                    if ($ptr > count($this->ot_ldata) - 1) {
                        $next = ord($dict[$dictptr + 1]);
                        if ($next == static::_DICT_INTERMEDIATE_MATCH || $next == static::_DICT_FINAL_MATCH) {
                            // Do not match if next character in text is a Mark
                            if (isset($this->ot_ldata[$ptr]['uni']) && strpos($this->glyph_class_marks, $this->ot_ldata[$ptr]['hex']) === false) {
                                $matches[] = $ptr - 1;
                            }
                        }
                        return $matches;
                    }
                    $dictptr++;
                    continue;
                }
                //echo "DICT_NODE_TYPE_LINEAR NOT: ".dechex($c).'<br />';
                return $matches;
            } elseif ($x == static::_DICT_NODE_TYPE_SPLIT) {
                //echo "DICT_NODE_TYPE_SPLIT ON ".dechex($d).": ".dechex($c).'<br />';
                $dictptr++;
                $d = ord($dict[$dictptr]);
                if ($c < $d) {
                    $dictptr += 5;
                } else {
                    $dictptr++;
                    // Unsigned long 32-bit offset
                    $offset = ord($dict[$dictptr]) * 16777216 + (ord($dict[$dictptr + 1]) << 16) + (ord($dict[$dictptr + 2]) << 8) + ord($dict[$dictptr + 3]);
                    $dictptr = $offset;
                }
            } else {
                //echo "PROBLEM: ".($x).'<br />';
                $ok = false;
                // Something has gone wrong
            }
        }
        return $matches;
    }
    ////////////////////////////////////////////////////////////////
    //////////       GPOS    ///////////////////////////////////////
    ////////////////////////////////////////////////////////////////
    private function _apply_gpo_srules(array $lookup_list, $is_old_spec = false)
    {
        foreach ($lookup_list as $lu => $tag) {
            $Type = $this->gpos_lookups[$lu]['Type'];
            $Flag = $this->gpos_lookups[$lu]['Flag'];
            $mark_filtering_set = '';
            if (isset($this->gpos_lookups[$lu]['MarkFilteringSet'])) {
                $mark_filtering_set = $this->gpos_lookups[$lu]['MarkFilteringSet'];
            }
            $ptr = 0;
            // Test each glyph sequentially
            while ($ptr < count($this->ot_ldata)) {
                // whilst there is another glyph ..0064
                $curr_glyph = $this->ot_ldata[$ptr]['hex'];
                $curr_gid = $this->ot_ldata[$ptr]['uni'];
                $shift = 1;
                foreach ($this->gpos_lookups[$lu]['Subtables'] as $c => $subtable_offset) {
                    // NB Coverage only looks at glyphs for position 1 (esp. 7.3 and 8.3)
                    if (!isset($this->lu_coverage[$lu][$c][$curr_gid])) {
                        continue;
                    }
                    // Get rules from font GPOS subtable
                    if (!isset($this->ot_ldata[$ptr]['bidi_type'])) {
                        continue;
                    }
                    // No need to check bidi_type - just a check that it exists
                    $shift = $this->_apply_gpo_ssubtable($lu, $c, $ptr, $curr_glyph, $curr_gid, $subtable_offset - $this->GPOS_offset + $this->GSUB_length, $Type, $Flag, $mark_filtering_set, $this->lu_coverage[$lu][$c], $tag, 0, $is_old_spec);
                    if ($shift) {
                        break;
                    }
                }
                if ($shift == 0) {
                    $shift = 1;
                }
                $ptr += $shift;
            }
        }
    }
    //////////////////////////////////////////////////////////////////////////////////
    // GPOS Types
    // Lookup Type 1: Single Adjustment Positioning Subtable        Adjust position of a single glyph
    // Lookup Type 2: Pair Adjustment Positioning Subtable      Adjust position of a pair of glyphs
    // Lookup Type 3: Cursive Attachment Positioning Subtable       Attach cursive glyphs
    // Lookup Type 4: MarkToBase Attachment Positioning Subtable    Attach a combining mark to a base glyph
    // Lookup Type 5: MarkToLigature Attachment Positioning Subtable    Attach a combining mark to a ligature
    // Lookup Type 6: MarkToMark Attachment Positioning Subtable    Attach a combining mark to another mark
    // Lookup Type 7: Contextual Positioning Subtables          Position one or more glyphs in context
    // Lookup Type 8: Chaining Contextual Positioning Subtable      Position one or more glyphs in chained context
    // Lookup Type 9: Extension positioning
    //////////////////////////////////////////////////////////////////////////////////
    private function _apply_gpo_svaluerecord($basepos, array $Value)
    {
        // If current glyph is a mark with a defined width, any XAdvance is considered to REPLACE the character Advance Width
        // Test case <div style="font-family:myanmartext">&#x1004;&#x103a;&#x1039;&#x1000;&#x1039;&#x1000;&#x103b;&#x103c;&#x103d;&#x1031;&#x102d;</div>
        if (strpos($this->glyph_class_marks, $this->ot_ldata[$basepos]['hex']) !== false) {
            $cw = round($this->mpdf->_get_char_width($this->mpdf->current_font['cw'], $this->ot_ldata[$basepos]['uni']) * $this->mpdf->current_font['unitsPerEm'] / 1000);
            // convert back to font design units
        } else {
            $cw = 0;
        }
        $apos = $this->_get_x_advance_pos($basepos);
        if (isset($Value['XAdvance']) && $Value['XAdvance'] - $cw != 0) {
            // However DON'T REPLACE the character Advance Width if Advance Width is negative
            // Test case <div style="font-family: dejavusansmono">&#x440;&#x443;&#x301;&#x441;&#x441;&#x43a;&#x438;&#x439;</div>
            if ($Value['XAdvance'] < 0) {
                $cw = 0;
            }
            // For LTR apply XAdvanceL to the last mark following the base = at $apos
            // For RTL apply XAdvanceR to base = at $basepos
            if (isset($this->ot_ldata[$apos]['GPOSinfo']['XAdvanceL'])) {
                $this->ot_ldata[$apos]['GPOSinfo']['XAdvanceL'] += $Value['XAdvance'] - $cw;
            } else {
                $this->ot_ldata[$apos]['GPOSinfo']['XAdvanceL'] = $Value['XAdvance'] - $cw;
            }
            if (isset($this->ot_ldata[$basepos]['GPOSinfo']['XAdvanceR'])) {
                $this->ot_ldata[$basepos]['GPOSinfo']['XAdvanceR'] += $Value['XAdvance'] - $cw;
            } else {
                $this->ot_ldata[$basepos]['GPOSinfo']['XAdvanceR'] = $Value['XAdvance'] - $cw;
            }
        }
        // Any XPlacement (? and Y Placement) apply to base and marks (from basepos to apos)
        for ($a = $basepos; $a <= $apos; $a++) {
            if (isset($Value['XPlacement'])) {
                if (isset($this->ot_ldata[$a]['GPOSinfo']['XPlacement'])) {
                    $this->ot_ldata[$a]['GPOSinfo']['XPlacement'] += $Value['XPlacement'];
                } else {
                    $this->ot_ldata[$a]['GPOSinfo']['XPlacement'] = $Value['XPlacement'];
                }
            }
            if (isset($Value['YPlacement'])) {
                if (isset($this->ot_ldata[$a]['GPOSinfo']['YPlacement'])) {
                    $this->ot_ldata[$a]['GPOSinfo']['YPlacement'] += $Value['YPlacement'];
                } else {
                    $this->ot_ldata[$a]['GPOSinfo']['YPlacement'] = $Value['YPlacement'];
                }
            }
        }
    }
    // If XAdvance is aplied to $ptr - in order for PDF to position the Advance correctly need to place it on
    // the last of any Marks which immediately follow the current glyph
    private function _get_x_advance_pos($pos)
    {
        // NB Not all fonts have all marks specified in GlyphClassMarks
        // If the current glyph is not a base (but a mark) then ignore this, and apply to the current position
        if (strpos($this->glyph_class_marks, $this->ot_ldata[$pos]['hex']) !== false) {
            return $pos;
        }
        while (isset($this->ot_ldata[$pos + 1]['hex']) && strpos($this->glyph_class_marks, $this->ot_ldata[$pos + 1]['hex']) !== false) {
            $pos++;
        }
        return $pos;
    }
    private function _apply_gpo_ssubtable($lookup_id, $subtable, $ptr, $curr_glyph, $curr_gid, $subtable_offset, $Type, $Flag, $mark_filtering_set, array $lu_coverage, $tag, $level, $is_old_spec)
    {
        if (($Flag & 0x1) == 1) {
            $dir = 'RTL';
        } else {
            // only used for Type 3
            $dir = 'LTR';
        }
        $ignore = $this->_get_gco_mignore_string($Flag);
        // Lets start
        $this->seek($subtable_offset);
        $pos_format = $this->read_ushort();
        ////////////////////////////////////////////////////////////////////////////////
        // LookupType 1: Single adjustment  Adjust position of a single glyph (e.g. SmallCaps/Sups/Subs)
        ////////////////////////////////////////////////////////////////////////////////
        if ($Type == 1) {
            //===========
            // Format 1:
            //===========
            if ($pos_format == 1) {
                $Coverage = $subtable_offset + $this->read_ushort();
                $value_format = $this->read_ushort();
                $Value = $this->_get_value_record($value_format);
            } elseif ($pos_format == 2) {
                $Coverage = $subtable_offset + $this->read_ushort();
                $value_format = $this->read_ushort();
                $value_count = $this->read_ushort();
                $glyph_pos = $lu_coverage[$curr_gid];
                $this->skip($glyph_pos * 2 * $this->count_bits($value_format));
                $Value = $this->_get_value_record($value_format);
            }
            $this->_apply_gpo_svaluerecord($ptr, $Value);
            if ($this->debug_otl) {
                $this->_dumpproc('GPOS', $lookup_id, $subtable, $Type, $pos_format, $ptr, $curr_glyph, $level);
            }
            return 1;
        }
        ////////////////////////////////////////////////////////////////////////////////
        // LookupType 2: Pair adjustment    Adjust position of a pair of glyphs (Kerning)
        ////////////////////////////////////////////////////////////////////////////////
        if ($Type == 2) {
            $Coverage = $subtable_offset + $this->read_ushort();
            $value_format1 = $this->read_ushort();
            $value_format2 = $this->read_ushort();
            $size_of_pair = 2 * $this->count_bits($value_format1) + 2 * $this->count_bits($value_format2);
            //===========
            // Format 1:
            //===========
            if ($pos_format == 1) {
                $pair_set_count = $this->read_ushort();
                $pair_set_offset = [];
                for ($p = 0; $p < $pair_set_count; $p++) {
                    $pair_set_offset[] = $subtable_offset + $this->read_ushort();
                }
                for ($p = 0; $p < $pair_set_count; $p++) {
                    if (isset($lu_coverage[$curr_gid]) && $lu_coverage[$curr_gid] == $p) {
                        $this->seek($pair_set_offset[$p]);
                        //PairSet table
                        $pair_value_count = $this->read_ushort();
                        for ($pv = 0; $pv < $pair_value_count; $pv++) {
                            //PairValueRecord
                            $gid = $this->read_ushort();
                            $second_glyph = $this->glyph_to_char($gid);
                            $first_glyph = $this->ot_ldata[$ptr]['uni'];
                            $checkpos = $ptr;
                            $checkpos++;
                            while (isset($this->ot_ldata[$checkpos]) && strpos($ignore, $this->ot_ldata[$checkpos]['hex']) !== false) {
                                $checkpos++;
                            }
                            if (isset($this->ot_ldata[$checkpos]) && $this->ot_ldata[$checkpos]['uni'] == $second_glyph) {
                                $matchedpos = $checkpos;
                            } else {
                                $matchedpos = false;
                            }
                            if ($matchedpos !== false) {
                                $Value1 = $this->_get_value_record($value_format1);
                                $Value2 = $this->_get_value_record($value_format2);
                                if ($value_format1) {
                                    $this->_apply_gpo_svaluerecord($ptr, $Value1);
                                }
                                if ($value_format2) {
                                    $this->_apply_gpo_svaluerecord($matchedpos, $Value2);
                                    if ($this->debug_otl) {
                                        $this->_dumpproc('GPOS', $lookup_id, $subtable, $Type, $pos_format, $ptr, $curr_glyph, $level);
                                    }
                                    return $matchedpos - $ptr + 1;
                                }
                                if ($this->debug_otl) {
                                    $this->_dumpproc('GPOS', $lookup_id, $subtable, $Type, $pos_format, $ptr, $curr_glyph, $level);
                                }
                                return $matchedpos - $ptr;
                            }
                            $this->skip($size_of_pair);
                        }
                    }
                }
                return 0;
            }
            //===========
            // Format 2:
            //===========
            if ($pos_format == 2) {
                $class_def1 = $subtable_offset + $this->read_ushort();
                $class_def2 = $subtable_offset + $this->read_ushort();
                $Class1Count = $this->read_ushort();
                $Class2Count = $this->read_ushort();
                $size_of_value_records = $Class1Count * $Class2Count * $size_of_pair;
                //$this->skip($sizeOfValueRecords );  ???? NOT NEEDED
                // NB Class1Count includes Class 0 even though it is not defined by $ClassDef1
                // i.e. Class1Count = 5; Class1 will contain array(indices 1-4);
                $Class1 = $this->_get_class_definition_table($class_def1);
                $Class2 = $this->_get_class_definition_table($class_def2);
                $first_glyph = $this->ot_ldata[$ptr]['uni'];
                $checkpos = $ptr;
                $checkpos++;
                while (isset($this->ot_ldata[$checkpos]) && strpos($ignore, $this->ot_ldata[$checkpos]['hex']) !== false) {
                    $checkpos++;
                }
                if (isset($this->ot_ldata[$checkpos])) {
                    $matchedpos = $checkpos;
                } else {
                    return 0;
                }
                $second_glyph = $this->ot_ldata[$matchedpos]['uni'];
                for ($i = 0; $i < $Class1Count; $i++) {
                    if (isset($Class1[$i]) && count($Class1[$i])) {
                        $first_class_pos = array_search($first_glyph, $Class1[$i]);
                        if ($first_class_pos === false) {
                            continue;
                        }
                        for ($j = 0; $j < $Class2Count; $j++) {
                            if (isset($Class2[$j]) && count($Class2[$j])) {
                                $second_class_pos = array_search($second_glyph, $Class2[$j]);
                                if ($second_class_pos === false) {
                                    continue;
                                }
                                // Get ValueRecord[$i][$j]
                                $offs = $i * $Class2Count * $size_of_pair + $j * $size_of_pair;
                                $this->seek($subtable_offset + 16 + $offs);
                                $Value1 = $this->_get_value_record($value_format1);
                                $Value2 = $this->_get_value_record($value_format2);
                                if ($value_format1) {
                                    $this->_apply_gpo_svaluerecord($ptr, $Value1);
                                }
                                if ($value_format2) {
                                    $this->_apply_gpo_svaluerecord($matchedpos, $Value2);
                                    if ($this->debug_otl) {
                                        $this->_dumpproc('GPOS', $lookup_id, $subtable, $Type, $pos_format, $ptr, $curr_glyph, $level);
                                    }
                                    return $matchedpos - $ptr + 1;
                                }
                                if ($this->debug_otl) {
                                    $this->_dumpproc('GPOS', $lookup_id, $subtable, $Type, $pos_format, $ptr, $curr_glyph, $level);
                                }
                                return $matchedpos - $ptr;
                            }
                        }
                    }
                }
                return 0;
            }
        } elseif ($Type == 8) {
            //===========
            // Format 1:
            //===========
            if ($pos_format == 1) {
                throw new \Mpdf\Mpdf_Exception("GPOS Lookup Type " . $Type . " Format " . $pos_format . " not TESTED YET.");
            }
            //===========
            // Format 2:
            //===========
            if ($pos_format == 2) {
                $coverage_table_offset = $subtable_offset + $this->read_ushort();
                $backtrack_class_def_offset = $subtable_offset + $this->read_ushort();
                $input_class_def_offset = $subtable_offset + $this->read_ushort();
                $lookahead_class_def_offset = $subtable_offset + $this->read_ushort();
                $chain_pos_class_set_cnt = $this->read_ushort();
                $chain_pos_class_set_offset = [];
                for ($b = 0; $b < $chain_pos_class_set_cnt; $b++) {
                    $offset = $this->read_ushort();
                    if ($offset == 0x0) {
                        $chain_pos_class_set_offset[] = $offset;
                    } else {
                        $chain_pos_class_set_offset[] = $subtable_offset + $offset;
                    }
                }
                $backtrack_classes = $this->_get_classes($backtrack_class_def_offset);
                $input_classes = $this->_get_classes($input_class_def_offset);
                $lookahead_classes = $this->_get_classes($lookahead_class_def_offset);
                for ($s = 0; $s < $chain_pos_class_set_cnt; $s++) {
                    // $ChainPosClassSet is ordered by input class-may be NULL
                    // Select $ChainPosClassSet if currGlyph is in First Input Class
                    if ($chain_pos_class_set_offset[$s] > 0 && isset($input_classes[$s][$curr_gid])) {
                        $this->seek($chain_pos_class_set_offset[$s]);
                        $chain_pos_class_rule_cnt = $this->read_ushort();
                        $chain_pos_class_rule = [];
                        for ($b = 0; $b < $chain_pos_class_rule_cnt; $b++) {
                            $chain_pos_class_rule[$b] = $chain_pos_class_set_offset[$s] + $this->read_ushort();
                        }
                        for ($b = 0; $b < $chain_pos_class_rule_cnt; $b++) {
                            // EACH RULE
                            $this->seek($chain_pos_class_rule[$b]);
                            $backtrack_glyph_count = $this->read_ushort();
                            $Backtrack = [];
                            for ($r = 0; $r < $backtrack_glyph_count; $r++) {
                                $Backtrack[$r] = $this->read_ushort();
                            }
                            $input_glyph_count = $this->read_ushort();
                            $Input = [];
                            for ($r = 1; $r < $input_glyph_count; $r++) {
                                $Input[$r] = $this->read_ushort();
                            }
                            $lookahead_glyph_count = $this->read_ushort();
                            $Lookahead = [];
                            for ($r = 0; $r < $lookahead_glyph_count; $r++) {
                                $Lookahead[$r] = $this->read_ushort();
                            }
                            $input_class = $s;
                            //???
                            $input_glyphs = [];
                            $input_glyphs[0] = $input_classes[$input_class];
                            if ($input_glyph_count > 1) {
                                //  NB starts at 1
                                for ($gcl = 1; $gcl < $input_glyph_count; $gcl++) {
                                    $classindex = $Input[$gcl];
                                    if (isset($input_classes[$classindex])) {
                                        $input_glyphs[$gcl] = $input_classes[$classindex];
                                    } else {
                                        $input_glyphs[$gcl] = '';
                                    }
                                }
                            }
                            // Class 0 contains all the glyphs NOT in the other classes
                            $class0excl = [];
                            for ($gc = 1; $gc <= count($input_classes); $gc++) {
                                if (isset($input_classes[$gc]) && is_array($input_classes[$gc])) {
                                    $class0excl = $class0excl + $input_classes[$gc];
                                }
                            }
                            if ($backtrack_glyph_count) {
                                $backtrack_glyphs = [];
                                for ($gcl = 0; $gcl < $backtrack_glyph_count; $gcl++) {
                                    $classindex = $Backtrack[$gcl];
                                    if (isset($backtrack_classes[$classindex])) {
                                        $backtrack_glyphs[$gcl] = $backtrack_classes[$classindex];
                                    } else {
                                        $backtrack_glyphs[$gcl] = '';
                                    }
                                }
                            } else {
                                $backtrack_glyphs = [];
                            }
                            // Class 0 contains all the glyphs NOT in the other classes
                            $bclass0excl = [];
                            for ($gc = 1; $gc <= count($backtrack_classes); $gc++) {
                                if (isset($backtrack_classes[$gc]) && is_array($backtrack_classes[$gc])) {
                                    $bclass0excl = $bclass0excl + $backtrack_classes[$gc];
                                }
                            }
                            if ($lookahead_glyph_count) {
                                $lookahead_glyphs = [];
                                for ($gcl = 0; $gcl < $lookahead_glyph_count; $gcl++) {
                                    $classindex = $Lookahead[$gcl];
                                    if (isset($lookahead_classes[$classindex])) {
                                        $lookahead_glyphs[$gcl] = $lookahead_classes[$classindex];
                                    } else {
                                        $lookahead_glyphs[$gcl] = '';
                                    }
                                }
                            } else {
                                $lookahead_glyphs = [];
                            }
                            // Class 0 contains all the glyphs NOT in the other classes
                            $lclass0excl = [];
                            for ($gc = 1; $gc <= count($lookahead_classes); $gc++) {
                                if (isset($lookahead_classes[$gc]) && is_array($lookahead_classes[$gc])) {
                                    $lclass0excl = $lclass0excl + $lookahead_classes[$gc];
                                }
                            }
                            $matched = $this->check_context_match_multiple_uni($input_glyphs, $backtrack_glyphs, $lookahead_glyphs, $ignore, $ptr, $class0excl, $bclass0excl, $lclass0excl);
                            if ($matched) {
                                $pos_count = $this->read_ushort();
                                $sequence_index = [];
                                $lookup_list_index = [];
                                for ($p = 0; $p < $pos_count; $p++) {
                                    // EACH LOOKUP
                                    $sequence_index[$p] = $this->read_ushort();
                                    $lookup_list_index[$p] = $this->read_ushort();
                                }
                                for ($p = 0; $p < $pos_count; $p++) {
                                    // Apply  $LookupListIndex  at   $SequenceIndex
                                    if ($sequence_index[$p] >= $input_glyph_count) {
                                        continue;
                                    }
                                    $lu = $lookup_list_index[$p];
                                    $lu_type = $this->gpos_lookups[$lu]['Type'];
                                    $lu_flag = $this->gpos_lookups[$lu]['Flag'];
                                    $lu_mark_filtering_set = $this->gpos_lookups[$lu]['MarkFilteringSet'];
                                    $luptr = $matched[$sequence_index[$p]];
                                    $lucurr_glyph = $this->ot_ldata[$luptr]['hex'];
                                    $lucurr_gid = $this->ot_ldata[$luptr]['uni'];
                                    foreach ($this->gpos_lookups[$lu]['Subtables'] as $luc => $lusubtable_offset) {
                                        $shift = $this->_apply_gpo_ssubtable($lu, $luc, $luptr, $lucurr_glyph, $lucurr_gid, $lusubtable_offset - $this->GPOS_offset + $this->GSUB_length, $lu_type, $lu_flag, $lu_mark_filtering_set, $this->lu_coverage[$lu][$luc], $tag, 1, $is_old_spec);
                                        if ($this->debug_otl && $shift) {
                                            $this->_dumpproc('GPOS', $lookup_id, $subtable, $Type, $pos_format, $ptr, $curr_glyph, $level);
                                        }
                                        if ($shift) {
                                            break;
                                        }
                                    }
                                }
                                if (!defined("OMIT_OTL_FIX_3") || OMIT_OTL_FIX_3 != 1) {
                                    return $shift;
                                }
                                return $input_glyph_count;
                                // should be + matched ignores in Input Sequence
                            }
                        }
                    }
                }
                return 0;
            }
            //===========
            // Format 3:
            //===========
            if ($pos_format == 3) {
                $backtrack_glyph_count = $this->read_ushort();
                for ($b = 0; $b < $backtrack_glyph_count; $b++) {
                    $coverage_backtrack_offset[] = $subtable_offset + $this->read_ushort();
                    // in glyph sequence order
                }
                $input_glyph_count = $this->read_ushort();
                for ($b = 0; $b < $input_glyph_count; $b++) {
                    $coverage_input_offset[] = $subtable_offset + $this->read_ushort();
                    // in glyph sequence order
                }
                $lookahead_glyph_count = $this->read_ushort();
                for ($b = 0; $b < $lookahead_glyph_count; $b++) {
                    $coverage_lookahead_offset[] = $subtable_offset + $this->read_ushort();
                    // in glyph sequence order
                }
                $pos_count = $this->read_ushort();
                $save_pos = $this->_pos;
                // Save the point just after PosCount
                $coverage_backtrack_glyphs = [];
                for ($b = 0; $b < $backtrack_glyph_count; $b++) {
                    $this->seek($coverage_backtrack_offset[$b]);
                    $glyphs = $this->_get_coverage();
                    $coverage_backtrack_glyphs[$b] = implode("|", $glyphs);
                }
                $coverage_input_glyphs = [];
                for ($b = 0; $b < $input_glyph_count; $b++) {
                    $this->seek($coverage_input_offset[$b]);
                    $glyphs = $this->_get_coverage();
                    $coverage_input_glyphs[$b] = implode("|", $glyphs);
                }
                $coverage_lookahead_glyphs = [];
                for ($b = 0; $b < $lookahead_glyph_count; $b++) {
                    $this->seek($coverage_lookahead_offset[$b]);
                    $glyphs = $this->_get_coverage();
                    $coverage_lookahead_glyphs[$b] = implode("|", $glyphs);
                }
                $matched = $this->check_context_match_multiple($coverage_input_glyphs, $coverage_backtrack_glyphs, $coverage_lookahead_glyphs, $ignore, $ptr);
                if ($matched) {
                    $this->seek($save_pos);
                    // Return to just after PosCount
                    for ($p = 0; $p < $pos_count; $p++) {
                        // PosLookupRecord
                        $pos_lookup_record[$p]['SequenceIndex'] = $this->read_ushort();
                        $pos_lookup_record[$p]['LookupListIndex'] = $this->read_ushort();
                    }
                    for ($p = 0; $p < $pos_count; $p++) {
                        // Apply  $PosLookupRecord[$p]['LookupListIndex']  at   $PosLookupRecord[$p]['SequenceIndex']
                        if ($pos_lookup_record[$p]['SequenceIndex'] >= $input_glyph_count) {
                            continue;
                        }
                        $lu = $pos_lookup_record[$p]['LookupListIndex'];
                        $lu_type = $this->gpos_lookups[$lu]['Type'];
                        $lu_flag = $this->gpos_lookups[$lu]['Flag'];
                        if (isset($this->gpos_lookups[$lu]['MarkFilteringSet'])) {
                            $lu_mark_filtering_set = $this->gpos_lookups[$lu]['MarkFilteringSet'];
                        } else {
                            $lu_mark_filtering_set = '';
                        }
                        $luptr = $matched[$pos_lookup_record[$p]['SequenceIndex']];
                        $lucurr_glyph = $this->ot_ldata[$luptr]['hex'];
                        $lucurr_gid = $this->ot_ldata[$luptr]['uni'];
                        foreach ($this->gpos_lookups[$lu]['Subtables'] as $luc => $lusubtable_offset) {
                            $shift = $this->_apply_gpo_ssubtable($lu, $luc, $luptr, $lucurr_glyph, $lucurr_gid, $lusubtable_offset - $this->GPOS_offset + $this->GSUB_length, $lu_type, $lu_flag, $lu_mark_filtering_set, $this->lu_coverage[$lu][$luc], $tag, 1, $is_old_spec);
                            if ($this->debug_otl && $shift) {
                                $this->_dumpproc('GPOS', $lookup_id, $subtable, $Type, $pos_format, $ptr, $curr_glyph, $level);
                            }
                            if ($shift) {
                                break;
                            }
                        }
                    }
                }
            } else {
                throw new \Mpdf\Mpdf_Exception("GPOS Lookup Type " . $Type . ", Format " . $pos_format . " not supported.");
            }
        } elseif ($Type == 3) {
            $this->skip(4);
            // Need default XAdvance for glyph
            $pdf_width = $this->mpdf->_get_char_width($this->mpdf->current_font['cw'], hexdec($curr_glyph));
            // DON'T convert back to design units
            $c_pos = $lu_coverage[$curr_gid];
            $this->skip($c_pos * 4);
            $entry_anchor = $this->read_ushort();
            $exit_anchor = $this->read_ushort();
            if ($entry_anchor != 0) {
                $entry_anchor += $subtable_offset;
                list($x, $y) = $this->_get_anchor_table($entry_anchor);
                if ($dir == 'RTL') {
                    if (round($pdf_width) == round($x * 1000 / $this->mpdf->current_font['unitsPerEm'])) {
                        $x = 0;
                    } else {
                        $x = $x - $pdf_width * $this->mpdf->current_font['unitsPerEm'] / 1000;
                    }
                }
                $this->Entry[$ptr] = ['X' => $x, 'Y' => $y, 'dir' => $dir];
            }
            if ($exit_anchor != 0) {
                $exit_anchor += $subtable_offset;
                list($x, $y) = $this->_get_anchor_table($exit_anchor);
                if ($dir == 'LTR') {
                    if (round($pdf_width) == round($x * 1000 / $this->mpdf->current_font['unitsPerEm'])) {
                        $x = 0;
                    } else {
                        $x = $x - $pdf_width * $this->mpdf->current_font['unitsPerEm'] / 1000;
                    }
                }
                $this->Exit[$ptr] = ['X' => $x, 'Y' => $y, 'dir' => $dir];
            }
            if ($this->debug_otl) {
                $this->_dumpproc('GPOS', $lookup_id, $subtable, $Type, $pos_format, $ptr, $curr_glyph, $level);
            }
            return 1;
        } elseif ($Type == 4) {
            $mark_coverage = $subtable_offset + $this->read_ushort();
            //$MarkCoverage is already set in $LuCoverage 00065|00073 etc
            $base_coverage = $subtable_offset + $this->read_ushort();
            $class_count = $this->read_ushort();
            // Number of classes defined for marks = Number of mark glyphs in the MarkCoverage table
            $mark_array = $subtable_offset + $this->read_ushort();
            // Offset to MarkArray table
            $base_array = $subtable_offset + $this->read_ushort();
            // Offset to BaseArray table
            $this->seek($base_coverage);
            $base_glyphs = implode('|', $this->_get_coverage());
            $checkpos = $ptr;
            $checkpos--;
            // ZZZ93
            // In Lohit-Kannada font (old-spec), rules specify a Type 4 GPOS to attach below-forms to base glyph
            // the repositioning does not happen in MS Word, and shouldn't happen comparing with other fonts
            // ?Why not
            // This Fix blocks the GPOS rule if the "mark" is not actually classified as a mark in the GlyphClasses of GDEF
            // but only in Indic old-spec.
            // Test cases: &#xca8;&#xccd;&#xca8;&#xcc1; and &#xc95;&#xccd;&#xcb0;&#xccc;
            if ($this->shaper == 'I' && $is_old_spec && strpos($this->glyph_class_marks, $this->ot_ldata[$ptr]['hex']) === false) {
                return;
            }
            // "To identify the base glyph that combines with a mark, the text-processing client must look backward in the glyph string from the mark to the preceding base glyph."
            while (isset($this->ot_ldata[$checkpos]) && strpos($this->glyph_class_marks, $this->ot_ldata[$checkpos]['hex']) !== false) {
                $checkpos--;
            }
            if (isset($this->ot_ldata[$checkpos]) && strpos($base_glyphs, $this->ot_ldata[$checkpos]['hex']) !== false) {
                $matchedpos = $checkpos;
            } else {
                $matchedpos = false;
            }
            if ($matchedpos !== false) {
                // Get the relevant MarkRecord
                $mark_pos = $lu_coverage[$curr_gid];
                $mark_record = $this->_get_mark_record($mark_array, $mark_pos);
                // e.g. Array ( [Class] => 0 [AnchorX] => -549 [AnchorY] => 1548 )
                //Mark Class is = $MarkRecord['Class']
                // Get the relevant BaseRecord
                $this->seek($base_array);
                $base_count = $this->read_ushort();
                $base_pos = strpos($base_glyphs, $this->ot_ldata[$matchedpos]['hex']) / 6;
                // Move to the BaseRecord we want
                $n_skip = 2 * $base_pos * $class_count;
                $this->skip($n_skip);
                // Read BaseRecord we want for appropriate Class
                $n_skip = 2 * $mark_record['Class'];
                $this->skip($n_skip);
                $base_record_offset = $base_array + $this->read_ushort();
                list($x, $y) = $this->_get_anchor_table($base_record_offset);
                $base_record = ['AnchorX' => $x, 'AnchorY' => $y];
                // e.g. Array ( [AnchorX] => 660 [AnchorY] => 1556 )
                // Need default XAdvance for Base glyph
                $base_width = $this->mpdf->_get_char_width($this->mpdf->current_font['cw'], $this->ot_ldata[$matchedpos]['uni']) * $this->mpdf->current_font['unitsPerEm'] / 1000;
                // convert back to font design units
                $this->ot_ldata[$ptr]['GPOSinfo']['BaseWidth'] = $base_width;
                // And any intervening (ignored) characters
                if ($ptr - $matchedpos > 1) {
                    for ($i = $matchedpos + 1; $i < $ptr; $i++) {
                        $base_width_extra = $this->mpdf->_get_char_width($this->mpdf->current_font['cw'], $this->ot_ldata[$i]['uni']) * $this->mpdf->current_font['unitsPerEm'] / 1000;
                        // convert back to font design units
                        $this->ot_ldata[$ptr]['GPOSinfo']['BaseWidth'] += $base_width_extra;
                    }
                }
                // Align to previous Glyph by attachment - so need to add to previous placement values
                $prev_x_placement = isset($this->ot_ldata[$matchedpos]['GPOSinfo']['XPlacement']) ? $this->ot_ldata[$matchedpos]['GPOSinfo']['XPlacement'] : 0;
                $prev_y_placement = isset($this->ot_ldata[$matchedpos]['GPOSinfo']['YPlacement']) ? $this->ot_ldata[$matchedpos]['GPOSinfo']['YPlacement'] : 0;
                $this->ot_ldata[$ptr]['GPOSinfo']['XPlacement'] = $prev_x_placement + $base_record['AnchorX'] - $mark_record['AnchorX'];
                $this->ot_ldata[$ptr]['GPOSinfo']['YPlacement'] = $prev_y_placement + $base_record['AnchorY'] - $mark_record['AnchorY'];
                if ($this->debug_otl) {
                    $this->_dumpproc('GPOS', $lookup_id, $subtable, $Type, $pos_format, $ptr, $curr_glyph, $level);
                }
                return 1;
            }
            return 0;
        } elseif ($Type == 5) {
            $mark_coverage = $subtable_offset + $this->read_ushort();
            //$MarkCoverage is already set in $LuCoverage 00065|00073 etc
            $ligature_coverage = $subtable_offset + $this->read_ushort();
            $class_count = $this->read_ushort();
            // Number of classes defined for marks = Number of mark glyphs in the MarkCoverage table
            $mark_array = $subtable_offset + $this->read_ushort();
            // Offset to MarkArray table
            $ligature_array = $subtable_offset + $this->read_ushort();
            // Offset to LigatureArray table
            $this->seek($ligature_coverage);
            $ligature_glyphs = implode('|', $this->_get_coverage());
            $checkpos = $ptr;
            $checkpos--;
            // "To position a combining mark using a MarkToLigature attachment subtable, the text-processing client must work backward from the mark to the preceding ligature glyph."
            while (isset($this->ot_ldata[$checkpos]) && strpos($this->glyph_class_marks, $this->ot_ldata[$checkpos]['hex']) !== false) {
                $checkpos--;
            }
            if (isset($this->ot_ldata[$checkpos]) && strpos($ligature_glyphs, $this->ot_ldata[$checkpos]['hex']) !== false) {
                $matchedpos = $checkpos;
            } else {
                $matchedpos = false;
            }
            if ($matchedpos !== false) {
                // Get the relevant MarkRecord
                $mark_pos = $lu_coverage[$curr_gid];
                $mark_record = $this->_get_mark_record($mark_array, $mark_pos);
                // e.g. Array ( [Class] => 0 [AnchorX] => -549 [AnchorY] => 1548 )
                //Mark Class is = $MarkRecord['Class']
                // Get the relevant LigatureRecord
                $this->seek($ligature_array);
                $ligature_count = $this->read_ushort();
                $ligature_pos = strpos($ligature_glyphs, $this->ot_ldata[$matchedpos]['hex']) / 6;
                // Move to the LigatureAttach table Record we want
                $n_skip = 2 * $ligature_pos;
                $this->skip($n_skip);
                $ligature_attach_offset = $ligature_array + $this->read_ushort();
                $this->seek($ligature_attach_offset);
                $component_count = $this->read_ushort();
                $offsets = [];
                for ($comp = 0; $comp < $component_count; $comp++) {
                    // ComponentRecords
                    for ($class = 0; $class < $class_count; $class++) {
                        $offsets[$comp][$class] = $this->read_ushort();
                    }
                }
                // Get the specific component for this mark attachment
                if (isset($this->assoc_ligs[$matchedpos]) && isset($this->assoc_marks[$ptr]['ligPos']) && $this->assoc_marks[$ptr]['ligPos'] == $matchedpos) {
                    $component = $this->assoc_marks[$ptr]['compID'];
                } else {
                    $component = $component_count - 1;
                }
                $offset = $offsets[$component][$mark_record['Class']];
                if ($offset != 0) {
                    $ligature_record_offset = $offset + $ligature_attach_offset;
                    list($x, $y) = $this->_get_anchor_table($ligature_record_offset);
                    $ligature_record = ['AnchorX' => $x, 'AnchorY' => $y];
                    // Need default XAdvance for Ligature glyph
                    $ligature_width = $this->mpdf->_get_char_width($this->mpdf->current_font['cw'], $this->ot_ldata[$matchedpos]['uni']) * $this->mpdf->current_font['unitsPerEm'] / 1000;
                    // convert back to font design units
                    $this->ot_ldata[$ptr]['GPOSinfo']['BaseWidth'] = $ligature_width;
                    // And any intervening (ignored)characters
                    if ($ptr - $matchedpos > 1) {
                        for ($i = $matchedpos + 1; $i < $ptr; $i++) {
                            $ligature_width_extra = $this->mpdf->_get_char_width($this->mpdf->current_font['cw'], $this->ot_ldata[$i]['uni']) * $this->mpdf->current_font['unitsPerEm'] / 1000;
                            // convert back to font design units
                            $this->ot_ldata[$ptr]['GPOSinfo']['BaseWidth'] += $ligature_width_extra;
                        }
                    }
                    // Align to previous Ligature by attachment - so need to add to previous placement values
                    if (isset($this->ot_ldata[$matchedpos]['GPOSinfo']['XPlacement'])) {
                        $prev_x_placement = $this->ot_ldata[$matchedpos]['GPOSinfo']['XPlacement'];
                    } else {
                        $prev_x_placement = 0;
                    }
                    if (isset($this->ot_ldata[$matchedpos]['GPOSinfo']['YPlacement'])) {
                        $prev_y_placement = $this->ot_ldata[$matchedpos]['GPOSinfo']['YPlacement'];
                    } else {
                        $prev_y_placement = 0;
                    }
                    $this->ot_ldata[$ptr]['GPOSinfo']['XPlacement'] = $prev_x_placement + $ligature_record['AnchorX'] - $mark_record['AnchorX'];
                    $this->ot_ldata[$ptr]['GPOSinfo']['YPlacement'] = $prev_y_placement + $ligature_record['AnchorY'] - $mark_record['AnchorY'];
                    if ($this->debug_otl) {
                        $this->_dumpproc('GPOS', $lookup_id, $subtable, $Type, $pos_format, $ptr, $curr_glyph, $level);
                    }
                    return 1;
                }
            }
            return 0;
        } elseif ($Type == 6) {
            $Mark1Coverage = $subtable_offset + $this->read_ushort();
            // Combining Mark
            //$Mark1Coverage is already set in $LuCoverage 0065|0073 etc
            $Mark2Coverage = $subtable_offset + $this->read_ushort();
            // Base Mark
            $class_count = $this->read_ushort();
            // Number of classes defined for marks = No. of Combining mark1 glyphs in the MarkCoverage table
            $Mark1Array = $subtable_offset + $this->read_ushort();
            // Offset to MarkArray table
            $Mark2Array = $subtable_offset + $this->read_ushort();
            // Offset to Mark2Array table
            $this->seek($Mark2Coverage);
            $Mark2Glyphs = implode('|', $this->_get_coverage());
            $checkpos = $ptr;
            $checkpos--;
            while (isset($this->ot_ldata[$checkpos]) && strpos($ignore, $this->ot_ldata[$checkpos]['hex']) !== false) {
                $checkpos--;
            }
            if (isset($this->ot_ldata[$checkpos]) && strpos($Mark2Glyphs, $this->ot_ldata[$checkpos]['hex']) !== false) {
                $matchedpos = $checkpos;
            } else {
                $matchedpos = false;
            }
            if ($matchedpos !== false) {
                // Get the relevant MarkRecord
                $Mark1Pos = $lu_coverage[$curr_gid];
                $Mark1Record = $this->_get_mark_record($Mark1Array, $Mark1Pos);
                // e.g. Array ( [Class] => 0 [AnchorX] => -549 [AnchorY] => 1548 )
                //Mark Class is = $Mark1Record['Class']
                // Get the relevant Mark2Record
                $this->seek($Mark2Array);
                $Mark2Count = $this->read_ushort();
                $Mark2Pos = strpos($Mark2Glyphs, $this->ot_ldata[$matchedpos]['hex']) / 6;
                // Move to the Mark2Record we want
                $n_skip = 2 * $Mark2Pos * $class_count;
                $this->skip($n_skip);
                // Read Mark2Record we want for appropriate Class
                $n_skip = 2 * $Mark1Record['Class'];
                $this->skip($n_skip);
                $mark2record_offset = $Mark2Array + $this->read_ushort();
                list($x, $y) = $this->_get_anchor_table($mark2record_offset);
                $Mark2Record = ['AnchorX' => $x, 'AnchorY' => $y];
                // e.g. Array ( [AnchorX] => 660 [AnchorY] => 1556 )
                // Need default XAdvance for Mark2 glyph
                $Mark2Width = $this->mpdf->_get_char_width($this->mpdf->current_font['cw'], $this->ot_ldata[$matchedpos]['uni']) * $this->mpdf->current_font['unitsPerEm'] / 1000;
                // convert back to font design units
                // IF combining marks are set on different components of a ligature glyph, do not apply this rule
                // Test: arabictypesetting: &#x625;&#x650;&#x644;&#x64e;&#x649;&#x670;&#x653;
                // Test: arabictypesetting: &#x628;&#x651;&#x64e;&#x64a;&#x652;&#x646;&#x64e;&#x643;&#x64f;&#x645;&#x652;
                $prev_lig = -1;
                $this_lig = -1;
                $prev_comp = -1;
                $this_comp = -1;
                if (isset($this->assoc_marks[$matchedpos])) {
                    $prev_lig = $this->assoc_marks[$matchedpos]['ligPos'];
                    $prev_comp = $this->assoc_marks[$matchedpos]['compID'];
                }
                if (isset($this->assoc_marks[$ptr])) {
                    $this_lig = $this->assoc_marks[$ptr]['ligPos'];
                    $this_comp = $this->assoc_marks[$ptr]['compID'];
                }
                // However IF Mark2 (first in logical order, i.e. being attached to) is not associated with a base, carry on
                // This happens in Indic when the Mark being attached to e.g. [Halant Ma lig] -> MatraU,  [U+0B4D + U+B2E as E0F5]-> U+0B41 become E135
                if (!defined("OMIT_OTL_FIX_1") || OMIT_OTL_FIX_1 != 1) {
                    /* OTL_FIX_1 */
                    if (isset($this->assoc_marks[$matchedpos]) && ($prev_lig != $this_lig || $prev_comp != $this_comp)) {
                        return 0;
                    }
                } else if ($prev_lig != $this_lig || $prev_comp != $this_comp) {
                    return 0;
                }
                if (!defined("OMIT_OTL_FIX_2") || OMIT_OTL_FIX_2 != 1) {
                    /* OTL_FIX_2 */
                    if (!isset($this->ot_ldata[$matchedpos]['GPOSinfo']['BaseWidth']) || !$this->ot_ldata[$matchedpos]['GPOSinfo']['BaseWidth']) {
                        $this->ot_ldata[$ptr]['GPOSinfo']['BaseWidth'] = $Mark2Width;
                    }
                }
                // ZZZ99Q - Test Case font-family: garuda &#xe19;&#xe49;&#xe33;
                if (isset($this->ot_ldata[$matchedpos]['GPOSinfo']['BaseWidth']) && $this->ot_ldata[$matchedpos]['GPOSinfo']['BaseWidth']) {
                    $this->ot_ldata[$ptr]['GPOSinfo']['BaseWidth'] = $this->ot_ldata[$matchedpos]['GPOSinfo']['BaseWidth'];
                }
                // Align to previous Mark by attachment - so need to add the previous placement values
                $prev_x_placement = isset($this->ot_ldata[$matchedpos]['GPOSinfo']['XPlacement']) ? $this->ot_ldata[$matchedpos]['GPOSinfo']['XPlacement'] : 0;
                $prev_y_placement = isset($this->ot_ldata[$matchedpos]['GPOSinfo']['YPlacement']) ? $this->ot_ldata[$matchedpos]['GPOSinfo']['YPlacement'] : 0;
                $this->ot_ldata[$ptr]['GPOSinfo']['XPlacement'] = $prev_x_placement + $Mark2Record['AnchorX'] - $Mark1Record['AnchorX'];
                $this->ot_ldata[$ptr]['GPOSinfo']['YPlacement'] = $prev_y_placement + $Mark2Record['AnchorY'] - $Mark1Record['AnchorY'];
                if ($this->debug_otl) {
                    $this->_dumpproc('GPOS', $lookup_id, $subtable, $Type, $pos_format, $ptr, $curr_glyph, $level);
                }
                return 1;
            }
            return 0;
        } elseif ($Type == 7) {
            //===========
            // Format 1:
            //===========
            if ($pos_format == 1) {
                throw new \Mpdf\Mpdf_Exception("GPOS Lookup Type " . $Type . " Format " . $pos_format . " not TESTED YET.");
            }
            //===========
            // Format 2:
            //===========
            if ($pos_format == 2) {
                $coverage_table_offset = $subtable_offset + $this->read_ushort();
                $input_class_def_offset = $subtable_offset + $this->read_ushort();
                $pos_class_set_cnt = $this->read_ushort();
                $pos_class_set_offset = [];
                for ($b = 0; $b < $pos_class_set_cnt; $b++) {
                    $offset = $this->read_ushort();
                    if ($offset == 0x0) {
                        $pos_class_set_offset[] = $offset;
                    } else {
                        $pos_class_set_offset[] = $subtable_offset + $offset;
                    }
                }
                $input_classes = $this->_get_classes($input_class_def_offset);
                for ($s = 0; $s < $pos_class_set_cnt; $s++) {
                    // $ChainPosClassSet is ordered by input class-may be NULL
                    // Select $PosClassSet if currGlyph is in First Input Class
                    if ($pos_class_set_offset[$s] > 0 && isset($input_classes[$s][$curr_gid])) {
                        $this->seek($pos_class_set_offset[$s]);
                        $pos_class_rule_cnt = $this->read_ushort();
                        $pos_class_rule = [];
                        for ($b = 0; $b < $pos_class_rule_cnt; $b++) {
                            $pos_class_rule[$b] = $pos_class_set_offset[$s] + $this->read_ushort();
                        }
                        for ($b = 0; $b < $pos_class_rule_cnt; $b++) {
                            // EACH RULE
                            $this->seek($pos_class_rule[$b]);
                            $input_glyph_count = $this->read_ushort();
                            $pos_count = $this->read_ushort();
                            $Input = [];
                            for ($r = 1; $r < $input_glyph_count; $r++) {
                                $Input[$r] = $this->read_ushort();
                            }
                            $input_class = $s;
                            $input_glyphs = [];
                            $input_glyphs[0] = $input_classes[$input_class];
                            if ($input_glyph_count > 1) {
                                //  NB starts at 1
                                for ($gcl = 1; $gcl < $input_glyph_count; $gcl++) {
                                    $classindex = $Input[$gcl];
                                    if (isset($input_classes[$classindex])) {
                                        $input_glyphs[$gcl] = $input_classes[$classindex];
                                    } else {
                                        $input_glyphs[$gcl] = '';
                                    }
                                }
                            }
                            // Class 0 contains all the glyphs NOT in the other classes
                            $class0excl = [];
                            for ($gc = 1; $gc <= count($input_classes); $gc++) {
                                if (is_array($input_classes[$gc])) {
                                    $class0excl = $class0excl + $input_classes[$gc];
                                }
                            }
                            $backtrack_glyphs = [];
                            $lookahead_glyphs = [];
                            $matched = $this->check_context_match_multiple_uni($input_glyphs, $backtrack_glyphs, $lookahead_glyphs, $ignore, $ptr, $class0excl);
                            if ($matched) {
                                for ($p = 0; $p < $pos_count; $p++) {
                                    // EACH LOOKUP
                                    $sequence_index[$p] = $this->read_ushort();
                                    $lookup_list_index[$p] = $this->read_ushort();
                                }
                                for ($p = 0; $p < $pos_count; $p++) {
                                    // Apply  $LookupListIndex  at   $SequenceIndex
                                    if ($sequence_index[$p] >= $input_glyph_count) {
                                        continue;
                                    }
                                    $lu = $lookup_list_index[$p];
                                    $lu_type = $this->gpos_lookups[$lu]['Type'];
                                    $lu_flag = $this->gpos_lookups[$lu]['Flag'];
                                    $lu_mark_filtering_set = $this->gpos_lookups[$lu]['MarkFilteringSet'];
                                    $luptr = $matched[$sequence_index[$p]];
                                    $lucurr_glyph = $this->ot_ldata[$luptr]['hex'];
                                    $lucurr_gid = $this->ot_ldata[$luptr]['uni'];
                                    foreach ($this->gpos_lookups[$lu]['Subtables'] as $luc => $lusubtable_offset) {
                                        $shift = $this->_apply_gpo_ssubtable($lu, $luc, $luptr, $lucurr_glyph, $lucurr_gid, $lusubtable_offset - $this->GPOS_offset + $this->GSUB_length, $lu_type, $lu_flag, $lu_mark_filtering_set, $this->lu_coverage[$lu][$luc], $tag, 1, $is_old_spec);
                                        if ($this->debug_otl && $shift) {
                                            $this->_dumpproc('GPOS', $lookup_id, $subtable, $Type, $pos_format, $ptr, $curr_glyph, $level);
                                        }
                                        if ($shift) {
                                            break;
                                        }
                                    }
                                }
                                if (!defined("OMIT_OTL_FIX_3") || OMIT_OTL_FIX_3 != 1) {
                                    return $shift;
                                }
                                return $input_glyph_count;
                                // should be + matched ignores in Input Sequence
                            }
                        }
                    }
                }
                return 0;
            }
            //===========
            // Format 3:
            //===========
            if ($pos_format == 3) {
                throw new \Mpdf\Mpdf_Exception("GPOS Lookup Type " . $Type . " Format " . $pos_format . " not TESTED YET.");
            }
            throw new \Mpdf\Mpdf_Exception("GPOS Lookup Type " . $Type . ", Format " . $pos_format . " not supported.");
        } else {
            throw new \Mpdf\Mpdf_Exception("GPOS Lookup Type " . $Type . " not supported.");
        }
    }
    //////////////////////////////////////////////////////////////////////////////////
    // GPOS / GSUB / GCOM (common) functions
    //////////////////////////////////////////////////////////////////////////////////
    private function check_context_match(array $Input, array $Backtrack, array $Lookahead, $ignore, $ptr)
    {
        // Input etc are single numbers - GSUB Format 6.1
        // Input starts with (1=>xxx)
        // return false if no match, else an array of ptr for matches (0=>0, 1=>3,...)
        $current_syllable = isset($this->ot_ldata[$ptr]['syllable']) ? $this->ot_ldata[$ptr]['syllable'] : 0;
        // BACKTRACK
        $checkpos = $ptr;
        for ($i = 0; $i < count($Backtrack); $i++) {
            $checkpos--;
            while (isset($this->ot_ldata[$checkpos]) && strpos($ignore, $this->ot_ldata[$checkpos]['hex']) !== false) {
                $checkpos--;
            }
            // If outside scope of current syllable - return no match
            if ($this->restrict_to_syllable && isset($this->ot_ldata[$checkpos]['syllable']) && $this->ot_ldata[$checkpos]['syllable'] != $current_syllable) {
                return false;
            }
            // If outside scope of current syllable - return no match
            if (!isset($this->ot_ldata[$checkpos]) || $this->ot_ldata[$checkpos]['uni'] != $Backtrack[$i]) {
                return false;
            }
        }
        // INPUT
        $matched = [0 => $ptr];
        $checkpos = $ptr;
        for ($i = 1; $i < count($Input); $i++) {
            $checkpos++;
            while (isset($this->ot_ldata[$checkpos]) && strpos($ignore, $this->ot_ldata[$checkpos]['hex']) !== false) {
                $checkpos++;
            }
            // If outside scope of current syllable - return no match
            if ($this->restrict_to_syllable && isset($this->ot_ldata[$checkpos]['syllable']) && $this->ot_ldata[$checkpos]['syllable'] != $current_syllable) {
                return false;
            }
            // If outside scope of current syllable - return no match
            if (isset($this->ot_ldata[$checkpos]) && $this->ot_ldata[$checkpos]['uni'] == $Input[$i]) {
                $matched[] = $checkpos;
            } else {
                return false;
            }
        }
        // LOOKAHEAD
        for ($i = 0; $i < count($Lookahead); $i++) {
            $checkpos++;
            while (isset($this->ot_ldata[$checkpos]) && strpos($ignore, $this->ot_ldata[$checkpos]['hex']) !== false) {
                $checkpos++;
            }
            // If outside scope of current syllable - return no match
            if ($this->restrict_to_syllable && isset($this->ot_ldata[$checkpos]['syllable']) && $this->ot_ldata[$checkpos]['syllable'] != $current_syllable) {
                return false;
            }
            // If outside scope of current syllable - return no match
            if (!isset($this->ot_ldata[$checkpos]) || $this->ot_ldata[$checkpos]['uni'] != $Lookahead[$i]) {
                return false;
            }
        }
        return $matched;
    }
    private function check_context_match_multiple(array $Input, array $Backtrack, array $Lookahead, $ignore, $ptr, $class0excl = '', $bclass0excl = '', $lclass0excl = '')
    {
        // Input etc are string/array of glyph strings  - GSUB Format 5.2, 5.3, 6.2, 6.3, GPOS Format 7.2, 7.3, 8.2, 8.3
        // Input starts with (1=>xxx)
        // return false if no match, else an array of ptr for matches (0=>0, 1=>3,...)
        // $class0excl is the string of glyphs in all classes except Class 0 (GSUB 5.2, 6.2, GPOS 7.2, 8.2)
        // $bclass0excl & $lclass0excl are the same for lookahead and backtrack (GSUB 6.2, GPOS 8.2)
        $current_syllable = isset($this->ot_ldata[$ptr]['syllable']) ? $this->ot_ldata[$ptr]['syllable'] : 0;
        // BACKTRACK
        $checkpos = $ptr;
        for ($i = 0; $i < count($Backtrack); $i++) {
            $checkpos--;
            while (isset($this->ot_ldata[$checkpos]) && strpos($ignore, $this->ot_ldata[$checkpos]['hex']) !== false) {
                $checkpos--;
            }
            // If outside scope of current syllable - return no match
            if ($this->restrict_to_syllable && isset($this->ot_ldata[$checkpos]['syllable']) && $this->ot_ldata[$checkpos]['syllable'] != $current_syllable) {
                return false;
            }
            // If Class 0 specified, matches anything NOT in $bclass0excl
            if (!$Backtrack[$i] && isset($this->ot_ldata[$checkpos]) && strpos($bclass0excl, $this->ot_ldata[$checkpos]['hex']) !== false) {
                return false;
            }
            // If outside scope of current syllable - return no match
            if (!isset($this->ot_ldata[$checkpos]) || strpos($Backtrack[$i], $this->ot_ldata[$checkpos]['hex']) === false) {
                return false;
            }
        }
        // INPUT
        $matched = [0 => $ptr];
        $checkpos = $ptr;
        for ($i = 1; $i < count($Input); $i++) {
            // Start at 1 - already matched the first InputGlyph
            $checkpos++;
            while (isset($this->ot_ldata[$checkpos]) && strpos($ignore, $this->ot_ldata[$checkpos]['hex']) !== false) {
                $checkpos++;
            }
            // If outside scope of current syllable - return no match
            if ($this->restrict_to_syllable && isset($this->ot_ldata[$checkpos]['syllable']) && $this->ot_ldata[$checkpos]['syllable'] != $current_syllable) {
                return false;
            }
            // If outside scope of current syllable - return no match
            if (!$Input[$i] && isset($this->ot_ldata[$checkpos]) && strpos($class0excl, $this->ot_ldata[$checkpos]['hex']) === false) {
                $matched[] = $checkpos;
            } elseif (isset($this->ot_ldata[$checkpos]) && strpos($Input[$i], $this->ot_ldata[$checkpos]['hex']) !== false) {
                $matched[] = $checkpos;
            } else {
                return false;
            }
        }
        // LOOKAHEAD
        for ($i = 0; $i < count($Lookahead); $i++) {
            $checkpos++;
            while (isset($this->ot_ldata[$checkpos]) && strpos($ignore, $this->ot_ldata[$checkpos]['hex']) !== false) {
                $checkpos++;
            }
            // If outside scope of current syllable - return no match
            if ($this->restrict_to_syllable && isset($this->ot_ldata[$checkpos]['syllable']) && $this->ot_ldata[$checkpos]['syllable'] != $current_syllable) {
                return false;
            }
            // If Class 0 specified, matches anything NOT in $lclass0excl
            if (!$Lookahead[$i] && isset($this->ot_ldata[$checkpos]) && strpos($lclass0excl, $this->ot_ldata[$checkpos]['hex']) !== false) {
                return false;
            }
            // If outside scope of current syllable - return no match
            if (!isset($this->ot_ldata[$checkpos]) || strpos($Lookahead[$i], $this->ot_ldata[$checkpos]['hex']) === false) {
                return false;
            }
        }
        return $matched;
    }
    private function check_context_match_multiple_uni(array $Input, array $Backtrack, array $Lookahead, $ignore, $ptr, array $class0excl = [], array $bclass0excl = [], array $lclass0excl = [])
    {
        // Input etc are array of glyphs - GSUB Format 5.2, 5.3, 6.2, 6.3, GPOS Format 7.2, 7.3, 8.2, 8.3
        // Input starts with (1=>xxx)
        // return false if no match, else an array of ptr for matches (0=>0, 1=>3,...)
        // $class0excl is array of glyphs in all classes except Class 0 (GSUB 5.2, 6.2, GPOS 7.2, 8.2)
        // $bclass0excl & $lclass0excl are the same for lookahead and backtrack (GSUB 6.2, GPOS 8.2)
        $current_syllable = isset($this->ot_ldata[$ptr]['syllable']) ? $this->ot_ldata[$ptr]['syllable'] : 0;
        // BACKTRACK
        $checkpos = $ptr;
        for ($i = 0; $i < count($Backtrack); $i++) {
            $checkpos--;
            while (isset($this->ot_ldata[$checkpos]) && strpos($ignore, $this->ot_ldata[$checkpos]['hex']) !== false) {
                $checkpos--;
            }
            // If outside scope of current syllable - return no match
            if ($this->restrict_to_syllable && isset($this->ot_ldata[$checkpos]['syllable']) && $this->ot_ldata[$checkpos]['syllable'] != $current_syllable) {
                return false;
            }
            // If Class 0 specified, matches anything NOT in $bclass0excl
            if (!$Backtrack[$i] && isset($this->ot_ldata[$checkpos]) && isset($bclass0excl[$this->ot_ldata[$checkpos]['uni']])) {
                return false;
            }
            // If outside scope of current syllable - return no match
            if (!isset($this->ot_ldata[$checkpos]) || !isset($Backtrack[$i][$this->ot_ldata[$checkpos]['uni']])) {
                return false;
            }
        }
        // INPUT
        $matched = [0 => $ptr];
        $checkpos = $ptr;
        for ($i = 1; $i < count($Input); $i++) {
            // Start at 1 - already matched the first InputGlyph
            $checkpos++;
            while (isset($this->ot_ldata[$checkpos]) && strpos($ignore, $this->ot_ldata[$checkpos]['hex']) !== false) {
                $checkpos++;
            }
            // If outside scope of current syllable - return no match
            if ($this->restrict_to_syllable && isset($this->ot_ldata[$checkpos]['syllable']) && $this->ot_ldata[$checkpos]['syllable'] != $current_syllable) {
                return false;
            }
            // If outside scope of current syllable - return no match
            if (!$Input[$i] && isset($this->ot_ldata[$checkpos]) && !isset($class0excl[$this->ot_ldata[$checkpos]['uni']])) {
                $matched[] = $checkpos;
            } elseif (isset($this->ot_ldata[$checkpos]) && isset($Input[$i][$this->ot_ldata[$checkpos]['uni']])) {
                $matched[] = $checkpos;
            } else {
                return false;
            }
        }
        // LOOKAHEAD
        for ($i = 0; $i < count($Lookahead); $i++) {
            $checkpos++;
            while (isset($this->ot_ldata[$checkpos]) && strpos($ignore, $this->ot_ldata[$checkpos]['hex']) !== false) {
                $checkpos++;
            }
            // If outside scope of current syllable - return no match
            if ($this->restrict_to_syllable && isset($this->ot_ldata[$checkpos]['syllable']) && $this->ot_ldata[$checkpos]['syllable'] != $current_syllable) {
                return false;
            }
            // If Class 0 specified, matches anything NOT in $lclass0excl
            if (!$Lookahead[$i] && isset($this->ot_ldata[$checkpos]) && isset($lclass0excl[$this->ot_ldata[$checkpos]['uni']])) {
                return false;
            }
            // If outside scope of current syllable - return no match
            if (!isset($this->ot_ldata[$checkpos]) || !isset($Lookahead[$i][$this->ot_ldata[$checkpos]['uni']])) {
                return false;
            }
        }
        return $matched;
    }
    private function _get_class_definition_table($offset)
    {
        if (isset($this->lu_data_cache[$this->fontkey][$offset])) {
            $glyph_by_class = $this->lu_data_cache[$this->fontkey][$offset];
        } else {
            $this->seek($offset);
            $class_format = $this->read_ushort();
            $glyph_class = [];
            $glyph_by_class = [];
            if ($class_format == 1) {
                $start_glyph = $this->read_ushort();
                $glyph_count = $this->read_ushort();
                for ($i = 0; $i < $glyph_count; $i++) {
                    $glyph_class[$i]['startGlyphID'] = $start_glyph + $i;
                    $glyph_class[$i]['endGlyphID'] = $start_glyph + $i;
                    $glyph_class[$i]['class'] = $this->read_ushort();
                    for ($g = $glyph_class[$i]['startGlyphID']; $g <= $glyph_class[$i]['endGlyphID']; $g++) {
                        $glyph_by_class[$glyph_class[$i]['class']][] = $this->glyph_to_char($g);
                    }
                }
            } elseif ($class_format == 2) {
                $table_count = $this->read_ushort();
                for ($i = 0; $i < $table_count; $i++) {
                    $glyph_class[$i]['startGlyphID'] = $this->read_ushort();
                    $glyph_class[$i]['endGlyphID'] = $this->read_ushort();
                    $glyph_class[$i]['class'] = $this->read_ushort();
                    for ($g = $glyph_class[$i]['startGlyphID']; $g <= $glyph_class[$i]['endGlyphID']; $g++) {
                        $glyph_by_class[$glyph_class[$i]['class']][] = $this->glyph_to_char($g);
                    }
                }
            }
            ksort($glyph_by_class);
            $this->lu_data_cache[$this->fontkey][$offset] = $glyph_by_class;
        }
        return $glyph_by_class;
    }
    private function count_bits($n)
    {
        for ($c = 0; $n; $c++) {
            $n &= $n - 1;
            // clear the least significant bit set
        }
        return $c;
    }
    private function _get_value_record($value_format)
    {
        // Common ValueRecord for GPOS
        // Only returns 3 possible: $vra['XPlacement'] $vra['YPlacement'] $vra['XAdvance']
        $vra = [];
        // Horizontal adjustment for placement - in design units
        if (($value_format & 0x1) == 0x1) {
            $vra['XPlacement'] = $this->read_short();
        }
        // Vertical adjustment for placement - in design units
        if (($value_format & 0x2) == 0x2) {
            $vra['YPlacement'] = $this->read_short();
        }
        // Horizontal adjustment for advance - in design units (only used for horizontal writing)
        if (($value_format & 0x4) == 0x4) {
            $vra['XAdvance'] = $this->read_short();
        }
        // Vertical adjustment for advance - in design units (only used for vertical writing)
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
    private function _get_anchor_table($offset = 0)
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
    private function _get_mark_record($offset, $mark_pos)
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
    private function _get_gco_mignore_string($flag)
    {
        // If ignoreFlag set, combine all ignore glyphs into -> "(?:( 0FBA1| 0FBA2| 0FBA3)*)"
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
            throw new \Mpdf\Mpdf_Exception("This font [" . $this->fontkey . "] contains MarkGlyphSets - Not tested yet");
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
            return "((?:(?:" . $str . "))*)";
        }
        return "()";
    }
    private function _check_gco_mignore($flag, $glyph, $mark_filtering_set)
    {
        $ignore = false;
        // Flag & 0x0008 = Ignore Marks - (unless already done with MarkAttachmentType)
        if ($flag & 0x8 && ($flag & 0xff00) == 0 && strpos($this->glyph_class_marks, $glyph)) {
            $ignore = true;
        }
        if ($flag & 0x4 && strpos($this->glyph_class_ligatures, $glyph)) {
            $ignore = true;
        }
        if ($flag & 0x2 && strpos($this->glyph_class_bases, $glyph)) {
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
            return true;
        }
        return $ignore;
    }
    /**
     * Bidi algorithm
     *
     * These functions are called from mpdf after GSUB/GPOS has taken place
     * At this stage the bidi-type is in string form
     *
     * Bidirectional Character Types
     * =============================
     * Type  Description     General Scope
     * Strong
     * L     Left-to-Right       LRM, most alphabetic, syllabic, Han ideographs, non-European or non-Arabic digits, ...
     * LRE   Left-to-Right Embedding LRE
     * LRO   Left-to-Right Override  LRO
     * R     Right-to-Left       RLM, Hebrew alphabet, and related punctuation
     * AL    Right-to-Left Arabic    Arabic, Thaana, and Syriac alphabets, most punctuation specific to those scripts, ...
     * RLE   Right-to-Left Embedding RLE
     * RLO   Right-to-Left Override  RLO
     * Weak
     * PDF   Pop Directional Format      PDF
     * EN    European Number             European digits, Eastern Arabic-Indic digits, ...
     * ES    European Number Separator   Plus sign, minus sign
     * ET    European Number Terminator  Degree sign, currency symbols, ...
     * AN    Arabic Number           Arabic-Indic digits, Arabic decimal and thousands separators, ...
     * CS    Common Number Separator     Colon, comma, full stop (period), No-break space, ...
     * NSM   Nonspacing Mark             Characters marked Mn (Nonspacing_Mark) and Me (Enclosing_Mark) in the Unicode Character Database
     * BN    Boundary Neutral            Default ignorables, non-characters, and control characters, other than those explicitly given other types.
     * Neutral
     * B     Paragraph Separator     Paragraph separator, appropriate Newline Functions, higher-level protocol paragraph determination
     * S     Segment Separator   Tab
     * WS    Whitespace          Space, figure space, line separator, form feed, General Punctuation spaces, ...
     * ON    Other Neutrals      All other characters, including OBJECT REPLACEMENT CHARACTER
     */
    public function bidi_sort($ta, $str, $dir, array &$chunk_ot_ldata, $use_gpos)
    {
        $pel = 0;
        // paragraph embedding level
        $maxlevel = 0;
        $numchars = count($chunk_ot_ldata['char_data']);
        // Set the initial paragraph embedding level
        if ($dir == 'rtl') {
            $pel = 1;
        } else {
            $pel = 0;
        }
        // X1. Begin by setting the current embedding level to the paragraph embedding level. Set the directional override status to neutral.
        // Current Embedding Level
        $cel = $pel;
        // directional override status (-1 is Neutral)
        $dos = -1;
        $remember = [];
        // Array of characters data
        $chardata = [];
        // Process each character iteratively, applying rules X2 through X9. Only embedding levels from 0 to 61 are valid in this phase.
        // In the resolution of levels in rules I1 and I2, the maximum embedding level of 62 can be reached.
        for ($i = 0; $i < $numchars; ++$i) {
            if ($chunk_ot_ldata['char_data'][$i]['uni'] == 8235) {
                // RLE
                // X2. With each RLE, compute the least greater odd embedding level.
                //  a. If this new level would be valid, then this embedding code is valid. Remember (push) the current embedding level and override status. Reset the current level to this new level, and reset the override status to neutral.
                //  b. If the new level would not be valid, then this code is invalid. Do not change the current level or override status.
                $next_level = $cel + $cel % 2 + 1;
                if ($next_level < 62) {
                    $remember[] = ['num' => 8235, 'cel' => $cel, 'dos' => $dos];
                    $cel = $next_level;
                    $dos = -1;
                }
            } elseif ($chunk_ot_ldata['char_data'][$i]['uni'] == 8234) {
                // LRE
                // X3. With each LRE, compute the least greater even embedding level.
                //  a. If this new level would be valid, then this embedding code is valid. Remember (push) the current embedding level and override status. Reset the current level to this new level, and reset the override status to neutral.
                //  b. If the new level would not be valid, then this code is invalid. Do not change the current level or override status.
                $next_level = $cel + 2 - $cel % 2;
                if ($next_level < 62) {
                    $remember[] = ['num' => 8234, 'cel' => $cel, 'dos' => $dos];
                    $cel = $next_level;
                    $dos = -1;
                }
            } elseif ($chunk_ot_ldata['char_data'][$i]['uni'] == 8238) {
                // RLO
                // X4. With each RLO, compute the least greater odd embedding level.
                //  a. If this new level would be valid, then this embedding code is valid. Remember (push) the current embedding level and override status. Reset the current level to this new level, and reset the override status to right-to-left.
                //  b. If the new level would not be valid, then this code is invalid. Do not change the current level or override status.
                $next_level = $cel + $cel % 2 + 1;
                if ($next_level < 62) {
                    $remember[] = ['num' => 8238, 'cel' => $cel, 'dos' => $dos];
                    $cel = $next_level;
                    $dos = Ucdn::BIDI_CLASS_R;
                }
            } elseif ($chunk_ot_ldata['char_data'][$i]['uni'] == 8237) {
                // LRO
                // X5. With each LRO, compute the least greater even embedding level.
                //  a. If this new level would be valid, then this embedding code is valid. Remember (push) the current embedding level and override status. Reset the current level to this new level, and reset the override status to left-to-right.
                //  b. If the new level would not be valid, then this code is invalid. Do not change the current level or override status.
                $next_level = $cel + 2 - $cel % 2;
                if ($next_level < 62) {
                    $remember[] = ['num' => 8237, 'cel' => $cel, 'dos' => $dos];
                    $cel = $next_level;
                    $dos = Ucdn::BIDI_CLASS_L;
                }
            } elseif ($chunk_ot_ldata['char_data'][$i]['uni'] == 8236) {
                // PDF
                // X7. With each PDF, determine the matching embedding or override code. If there was a valid matching code, restore (pop) the last remembered (pushed) embedding level and directional override.
                if (count($remember)) {
                    $last = count($remember) - 1;
                    if ($remember[$last]['num'] == 8235 || $remember[$last]['num'] == 8234 || $remember[$last]['num'] == 8238 || $remember[$last]['num'] == 8237) {
                        $match = array_pop($remember);
                        $cel = $match['cel'];
                        $dos = $match['dos'];
                    }
                }
            } elseif ($chunk_ot_ldata['char_data'][$i]['uni'] == 10) {
                // NEW LINE
                // Reset to start values
                $cel = $pel;
                $dos = -1;
                $remember = [];
            } else {
                // X6. For all types besides RLE, LRE, RLO, LRO, and PDF:
                //  a. Set the level of the current character to the current embedding level.
                //  b. When the directional override status is not neutral, reset the current character type to directional override status.
                if ($dos != -1) {
                    $chardir = $dos;
                } else {
                    $chardir = $chunk_ot_ldata['char_data'][$i]['bidi_class'];
                }
                // stores string characters and other information
                if (isset($chunk_ot_ldata['GPOSinfo'][$i])) {
                    $gpos = $chunk_ot_ldata['GPOSinfo'][$i];
                } else {
                    $gpos = '';
                }
                $chardata[] = ['char' => $chunk_ot_ldata['char_data'][$i]['uni'], 'level' => $cel, 'type' => $chardir, 'group' => $chunk_ot_ldata['group'][$i], 'GPOSinfo' => $gpos];
            }
        }
        $numchars = count($chardata);
        // X8. All explicit directional embeddings and overrides are completely terminated at the end of each paragraph.
        // Paragraph separators are not included in the embedding.
        // X9. Remove all RLE, LRE, RLO, LRO, and PDF codes.
        // This is effectively done by only saving other codes to chardata
        // X10. Determine the start-of-sequence (sor) and end-of-sequence (eor) types, either L or R, for each isolating run sequence. These depend on the higher of the two levels on either side of the sequence boundary:
        // For sor, compare the level of the first character in the sequence with the level of the character preceding it in the paragraph or if there is none, with the paragraph embedding level.
        // For eor, compare the level of the last character in the sequence with the level of the character following it in the paragraph or if there is none, with the paragraph embedding level.
        // If the higher level is odd, the sor or eor is R; otherwise, it is L.
        $prelevel = $pel;
        $postlevel = $pel;
        // current embedding level
        for ($i = 0; $i < $numchars; ++$i) {
            $level = $chardata[$i]['level'];
            if ($i == 0) {
                $left = $prelevel;
            } else {
                $left = $chardata[$i - 1]['level'];
            }
            if ($i == $numchars - 1) {
                $right = $postlevel;
            } else {
                $right = $chardata[$i + 1]['level'];
            }
            $chardata[$i]['sor'] = max($left, $level) % 2 ? Ucdn::BIDI_CLASS_R : Ucdn::BIDI_CLASS_L;
            $chardata[$i]['eor'] = max($right, $level) % 2 ? Ucdn::BIDI_CLASS_R : Ucdn::BIDI_CLASS_L;
        }
        // 3.3.3 Resolving Weak Types
        // Weak types are now resolved one level run at a time. At level run boundaries where the type of the character on the other side of the boundary is required, the type assigned to sor or eor is used.
        // Nonspacing marks are now resolved based on the previous characters.
        // W1. Examine each nonspacing mark (NSM) in the level run, and change the type of the NSM to the type of the previous character. If the NSM is at the start of the level run, it will get the type of sor.
        for ($i = 0; $i < $numchars; ++$i) {
            if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_NSM) {
                if ($i == 0 || $chardata[$i]['level'] != $chardata[$i - 1]['level']) {
                    $chardata[$i]['type'] = $chardata[$i]['sor'];
                } else {
                    $chardata[$i]['type'] = $chardata[$i - 1]['type'];
                }
            }
        }
        // W2. Search backward from each instance of a European number until the first strong type (R, L, AL, or sor) is found. If an AL is found, change the type of the European number to Arabic number.
        $prevlevel = -1;
        $levcount = 0;
        for ($i = 0; $i < $numchars; ++$i) {
            if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_EN) {
                $found = false;
                for ($j = $levcount; $j >= 0; $j--) {
                    if ($chardata[$j]['type'] == Ucdn::BIDI_CLASS_AL) {
                        $chardata[$i]['type'] = Ucdn::BIDI_CLASS_AN;
                        $found = true;
                        break;
                    } elseif ($chardata[$j]['type'] == Ucdn::BIDI_CLASS_L || $chardata[$j]['type'] == Ucdn::BIDI_CLASS_R) {
                        $found = true;
                        break;
                    }
                }
            }
            if ($chardata[$i]['level'] != $prevlevel) {
                $levcount = 0;
            } else {
                ++$levcount;
            }
            $prevlevel = $chardata[$i]['level'];
        }
        // W3. Change all ALs to R.
        for ($i = 0; $i < $numchars; ++$i) {
            if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_AL) {
                $chardata[$i]['type'] = Ucdn::BIDI_CLASS_R;
            }
        }
        // W4. A single European separator between two European numbers changes to a European number. A single common separator between two numbers of the same type changes to that type.
        for ($i = 1; $i < $numchars; ++$i) {
            if ($i + 1 < $numchars && $chardata[$i]['level'] == $chardata[$i + 1]['level'] && $chardata[$i]['level'] == $chardata[$i - 1]['level']) {
                if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_ES && $chardata[$i - 1]['type'] == Ucdn::BIDI_CLASS_EN && $chardata[$i + 1]['type'] == Ucdn::BIDI_CLASS_EN) {
                    $chardata[$i]['type'] = Ucdn::BIDI_CLASS_EN;
                } elseif ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_CS && $chardata[$i - 1]['type'] == Ucdn::BIDI_CLASS_EN && $chardata[$i + 1]['type'] == Ucdn::BIDI_CLASS_EN) {
                    $chardata[$i]['type'] = Ucdn::BIDI_CLASS_EN;
                } elseif ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_CS && $chardata[$i - 1]['type'] == Ucdn::BIDI_CLASS_AN && $chardata[$i + 1]['type'] == Ucdn::BIDI_CLASS_AN) {
                    $chardata[$i]['type'] = Ucdn::BIDI_CLASS_AN;
                }
            }
        }
        // W5. A sequence of European terminators adjacent to European numbers changes to all European numbers.
        for ($i = 0; $i < $numchars; ++$i) {
            if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_ET) {
                if ($i > 0 && $chardata[$i - 1]['type'] == Ucdn::BIDI_CLASS_EN && $chardata[$i]['level'] == $chardata[$i - 1]['level']) {
                    $chardata[$i]['type'] = Ucdn::BIDI_CLASS_EN;
                } else {
                    $j = $i + 1;
                    while ($j < $numchars && $chardata[$j]['level'] == $chardata[$i]['level']) {
                        if ($chardata[$j]['type'] == Ucdn::BIDI_CLASS_EN) {
                            $chardata[$i]['type'] = Ucdn::BIDI_CLASS_EN;
                            break;
                        } elseif ($chardata[$j]['type'] != Ucdn::BIDI_CLASS_ET) {
                            break;
                        }
                        ++$j;
                    }
                }
            }
        }
        // W6. Otherwise, separators and terminators change to Other Neutral.
        for ($i = 0; $i < $numchars; ++$i) {
            if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_ET || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_ES || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_CS) {
                $chardata[$i]['type'] = Ucdn::BIDI_CLASS_ON;
            }
        }
        //W7. Search backward from each instance of a European number until the first strong type (R, L, or sor) is found. If an L is found, then change the type of the European number to L.
        for ($i = 0; $i < $numchars; ++$i) {
            if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_EN) {
                if ($i == 0) {
                    // Start of Level run
                    if ($chardata[$i]['sor'] == Ucdn::BIDI_CLASS_L) {
                        $chardata[$i]['type'] = $chardata[$i]['sor'];
                    }
                } else {
                    for ($j = $i - 1; $j >= 0; $j--) {
                        if ($chardata[$j]['level'] != $chardata[$i]['level']) {
                            // Level run boundary
                            if ($chardata[$j + 1]['sor'] == Ucdn::BIDI_CLASS_L) {
                                $chardata[$i]['type'] = $chardata[$j + 1]['sor'];
                            }
                            break;
                        } elseif ($chardata[$j]['type'] == Ucdn::BIDI_CLASS_L) {
                            $chardata[$i]['type'] = Ucdn::BIDI_CLASS_L;
                            break;
                        } elseif ($chardata[$j]['type'] == Ucdn::BIDI_CLASS_R) {
                            break;
                        }
                    }
                }
            }
        }
        // N1. A sequence of neutrals takes the direction of the surrounding strong text if the text on both sides has the same direction. European and Arabic numbers act as if they were R in terms of their influence on neutrals. Start-of-level-run (sor) and end-of-level-run (eor) are used at level run boundaries.
        for ($i = 0; $i < $numchars; ++$i) {
            if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_ON || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_WS) {
                $left = -1;
                // LEFT
                if ($i == 0) {
                    // first char
                    $left = $chardata[$i]['sor'];
                } elseif ($chardata[$i - 1]['level'] != $chardata[$i]['level']) {
                    // run boundary
                    $left = $chardata[$i]['sor'];
                } elseif ($chardata[$i - 1]['type'] == Ucdn::BIDI_CLASS_L) {
                    $left = Ucdn::BIDI_CLASS_L;
                } elseif ($chardata[$i - 1]['type'] == Ucdn::BIDI_CLASS_R || $chardata[$i - 1]['type'] == Ucdn::BIDI_CLASS_EN || $chardata[$i - 1]['type'] == Ucdn::BIDI_CLASS_AN) {
                    $left = Ucdn::BIDI_CLASS_R;
                }
                // RIGHT
                $right = -1;
                $j = $i;
                // move to the right of any following neutrals OR hit a run boundary
                while (($chardata[$j]['type'] == Ucdn::BIDI_CLASS_ON || $chardata[$j]['type'] == Ucdn::BIDI_CLASS_WS) && $j <= $numchars - 1) {
                    if ($j == $numchars - 1) {
                        // last char
                        $right = $chardata[$j]['eor'];
                        break;
                    } elseif ($chardata[$j + 1]['level'] != $chardata[$j]['level']) {
                        // run boundary
                        $right = $chardata[$j]['eor'];
                        break;
                    } elseif ($chardata[$j + 1]['type'] == Ucdn::BIDI_CLASS_L) {
                        $right = Ucdn::BIDI_CLASS_L;
                        break;
                    } elseif ($chardata[$j + 1]['type'] == Ucdn::BIDI_CLASS_R || $chardata[$j + 1]['type'] == Ucdn::BIDI_CLASS_EN || $chardata[$j + 1]['type'] == Ucdn::BIDI_CLASS_AN) {
                        $right = Ucdn::BIDI_CLASS_R;
                        break;
                    }
                    $j++;
                }
                if ($left > -1 && $left == $right) {
                    $chardata[$i]['orig_type'] = $chardata[$i]['type'];
                    // Need to store the original 'WS' for reference in L1 below
                    $chardata[$i]['type'] = $left;
                }
            }
        }
        // N2. Any remaining neutrals take the embedding direction
        for ($i = 0; $i < $numchars; ++$i) {
            if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_ON || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_WS) {
                $chardata[$i]['type'] = $chardata[$i]['level'] % 2 ? Ucdn::BIDI_CLASS_R : Ucdn::BIDI_CLASS_L;
                $chardata[$i]['orig_type'] = $chardata[$i]['type'];
                // Need to store the original 'WS' for reference in L1 below
            }
        }
        // I1. For all characters with an even (left-to-right) embedding direction, those of type R go up one level and those of type AN or EN go up two levels.
        // I2. For all characters with an odd (right-to-left) embedding direction, those of type L, EN or AN go up one level.
        for ($i = 0; $i < $numchars; ++$i) {
            $odd = $chardata[$i]['level'] % 2;
            if ($odd) {
                if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_L || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_AN || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_EN) {
                    $chardata[$i]['level'] += 1;
                }
            } else if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_R) {
                $chardata[$i]['level'] += 1;
            } elseif ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_AN || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_EN) {
                $chardata[$i]['level'] += 2;
            }
            $maxlevel = max($chardata[$i]['level'], $maxlevel);
        }
        // NB
        //  Separate into lines at this point************
        //
        // L1. On each line, reset the embedding level of the following characters to the paragraph embedding level:
        //  1. Segment separators (Tab) 'S',
        //  2. Paragraph separators 'B',
        //  3. Any sequence of whitespace characters 'WS' preceding a segment separator or paragraph separator, and
        //  4. Any sequence of whitespace characters 'WS' at the end of the line.
        //  The types of characters used here are the original types, not those modified by the previous phase cf N1 and N2*******
        //  Because a Paragraph Separator breaks lines, there will be at most one per line, at the end of that line.
        for ($i = $numchars - 1; $i > 0; $i--) {
            if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_WS || isset($chardata[$i]['orig_type']) && $chardata[$i]['orig_type'] == Ucdn::BIDI_CLASS_WS) {
                $chardata[$i]['level'] = $pel;
            } else {
                break;
            }
        }
        // L2. From the highest level found in the text to the lowest odd level on each line, including intermediate levels not actually present in the text, reverse any contiguous sequence of characters that are at that level or higher.
        for ($j = $maxlevel; $j > 0; $j--) {
            $ordarray = [];
            $revarr = [];
            $onlevel = false;
            for ($i = 0; $i < $numchars; ++$i) {
                if ($chardata[$i]['level'] >= $j) {
                    $onlevel = true;
                    // L4. A character is depicted by a mirrored glyph if and only if (a) the resolved directionality of that character is R, and (b) the Bidi_Mirrored property value of that character is true.
                    if (isset(Ucdn::$mirror_pairs[$chardata[$i]['char']]) && $chardata[$i]['type'] == Ucdn::BIDI_CLASS_R) {
                        $chardata[$i]['char'] = Ucdn::$mirror_pairs[$chardata[$i]['char']];
                    }
                    $revarr[] = $chardata[$i];
                } else {
                    if ($onlevel) {
                        $revarr = array_reverse($revarr);
                        $ordarray = array_merge($ordarray, $revarr);
                        $revarr = [];
                        $onlevel = false;
                    }
                    $ordarray[] = $chardata[$i];
                }
            }
            if ($onlevel) {
                $revarr = array_reverse($revarr);
                $ordarray = array_merge($ordarray, $revarr);
            }
            $chardata = $ordarray;
        }
        $group = '';
        $e = '';
        $GPOS = [];
        $cctr = 0;
        $rtl_content = 0x0;
        foreach ($chardata as $cd) {
            $e .= Utf_String::code2utf($cd['char']);
            $group .= $cd['group'];
            if ($use_gpos && is_array($cd['GPOSinfo'])) {
                $GPOS[$cctr] = $cd['GPOSinfo'];
                $GPOS[$cctr]['wDir'] = $cd['level'] % 2 ? 'RTL' : 'LTR';
            }
            if ($cd['type'] == Ucdn::BIDI_CLASS_L) {
                $rtl_content |= 1;
            } elseif ($cd['type'] == Ucdn::BIDI_CLASS_R) {
                $rtl_content |= 2;
            }
            $cctr++;
        }
        $chunk_ot_ldata['group'] = $group;
        if ($use_gpos) {
            $chunk_ot_ldata['GPOSinfo'] = $GPOS;
        }
        return [$e, $rtl_content];
    }
    /**
     * The following versions for BidiSort work on amalgamated chunks to process the whole paragraph
     *
     * Firstly set the level in the OTLdata - called from fn printbuffer() [_bidiPrepare]
     * Secondly re-order - called from fn writeFlowingBlock and FinishFlowingBlock, when already divided into lines. [_bidiReorder]
     */
    public function bidi_prepare(&$para, $dir)
    {
        // Set the initial paragraph embedding level
        $pel = 0;
        // paragraph embedding level
        if ($dir == 'rtl') {
            $pel = 1;
        }
        // X1. Begin by setting the current embedding level to the paragraph embedding level. Set the directional override status to neutral.
        // Current Embedding Level
        $cel = $pel;
        // directional override status (-1 is Neutral)
        $dos = -1;
        $remember = [];
        $controlchars = false;
        $strongrtl = false;
        $diid = 0;
        // direction isolate ID
        $dictr = 0;
        // direction isolate counter
        // Process each character iteratively, applying rules X2 through X9. Only embedding levels from 0 to 61 are valid in this phase.
        // In the resolution of levels in rules I1 and I2, the maximum embedding level of 62 can be reached.
        $numchunks = count($para);
        for ($nc = 0; $nc < $numchunks; $nc++) {
            $chunk_ot_ldata =& $para[$nc][18];
            $numchars = count($chunk_ot_ldata['char_data']);
            for ($i = 0; $i < $numchars; ++$i) {
                if ($chunk_ot_ldata['char_data'][$i]['uni'] == 8235) {
                    // RLE
                    // X2. With each RLE, compute the least greater odd embedding level.
                    //  a. If this new level would be valid, then this embedding code is valid. Remember (push) the current embedding level and override status. Reset the current level to this new level, and reset the override status to neutral.
                    //  b. If the new level would not be valid, then this code is invalid. Do not change the current level or override status.
                    $next_level = $cel + $cel % 2 + 1;
                    if ($next_level < 62) {
                        $remember[] = ['num' => 8235, 'cel' => $cel, 'dos' => $dos];
                        $cel = $next_level;
                        $dos = -1;
                        $controlchars = true;
                    }
                } elseif ($chunk_ot_ldata['char_data'][$i]['uni'] == 8234) {
                    // LRE
                    // X3. With each LRE, compute the least greater even embedding level.
                    //  a. If this new level would be valid, then this embedding code is valid. Remember (push) the current embedding level and override status. Reset the current level to this new level, and reset the override status to neutral.
                    //  b. If the new level would not be valid, then this code is invalid. Do not change the current level or override status.
                    $next_level = $cel + 2 - $cel % 2;
                    if ($next_level < 62) {
                        $remember[] = ['num' => 8234, 'cel' => $cel, 'dos' => $dos];
                        $cel = $next_level;
                        $dos = -1;
                        $controlchars = true;
                    }
                } elseif ($chunk_ot_ldata['char_data'][$i]['uni'] == 8238) {
                    // RLO
                    // X4. With each RLO, compute the least greater odd embedding level.
                    //  a. If this new level would be valid, then this embedding code is valid. Remember (push) the current embedding level and override status. Reset the current level to this new level, and reset the override status to right-to-left.
                    //  b. If the new level would not be valid, then this code is invalid. Do not change the current level or override status.
                    $next_level = $cel + $cel % 2 + 1;
                    if ($next_level < 62) {
                        $remember[] = ['num' => 8238, 'cel' => $cel, 'dos' => $dos];
                        $cel = $next_level;
                        $dos = Ucdn::BIDI_CLASS_R;
                        $controlchars = true;
                    }
                } elseif ($chunk_ot_ldata['char_data'][$i]['uni'] == 8237) {
                    // LRO
                    // X5. With each LRO, compute the least greater even embedding level.
                    //  a. If this new level would be valid, then this embedding code is valid. Remember (push) the current embedding level and override status. Reset the current level to this new level, and reset the override status to left-to-right.
                    //  b. If the new level would not be valid, then this code is invalid. Do not change the current level or override status.
                    $next_level = $cel + 2 - $cel % 2;
                    if ($next_level < 62) {
                        $remember[] = ['num' => 8237, 'cel' => $cel, 'dos' => $dos];
                        $cel = $next_level;
                        $dos = Ucdn::BIDI_CLASS_L;
                        $controlchars = true;
                    }
                } elseif ($chunk_ot_ldata['char_data'][$i]['uni'] == 8236) {
                    // PDF
                    // X7. With each PDF, determine the matching embedding or override code. If there was a valid matching code, restore (pop) the last remembered (pushed) embedding level and directional override.
                    if (count($remember)) {
                        $last = count($remember) - 1;
                        if ($remember[$last]['num'] == 8235 || $remember[$last]['num'] == 8234 || $remember[$last]['num'] == 8238 || $remember[$last]['num'] == 8237) {
                            $match = array_pop($remember);
                            $cel = $match['cel'];
                            $dos = $match['dos'];
                        }
                    }
                } elseif ($chunk_ot_ldata['char_data'][$i]['uni'] == 8294 || $chunk_ot_ldata['char_data'][$i]['uni'] == 8295 || $chunk_ot_ldata['char_data'][$i]['uni'] == 8296) {
                    // LRI // RLI // FSI
                    // X5a. With each RLI:
                    // X5b. With each LRI:
                    // X5c. With each FSI, apply rules P2 and P3 for First Strong character
                    //  Set the RLI/LRI/FSI embedding level to the embedding level of the last entry on the directional status stack.
                    if ($dos != -1) {
                        $chardir = $dos;
                    } else {
                        $chardir = $chunk_ot_ldata['char_data'][$i]['bidi_class'];
                    }
                    $chunk_ot_ldata['char_data'][$i]['level'] = $cel;
                    $chunk_ot_ldata['char_data'][$i]['type'] = $chardir;
                    $chunk_ot_ldata['char_data'][$i]['diid'] = $diid;
                    $fsi = '';
                    // X5c. With each FSI, apply rules P2 and P3 within the isolate run for First Strong character
                    if ($chunk_ot_ldata['char_data'][$i]['uni'] == 8296) {
                        // FSI
                        $lvl = 0;
                        $nc2 = $nc;
                        $i2 = $i;
                        while (!($nc2 == $numchunks - 1 && $i2 == count($para[$nc2][18]['char_data']) - 1)) {
                            // while not at end of last chunk
                            $i2++;
                            if ($i2 >= count($para[$nc2][18]['char_data'])) {
                                $nc2++;
                                $i2 = 0;
                            }
                            if ($lvl > 0) {
                                continue;
                            }
                            if ($para[$nc2][18]['char_data'][$i2]['uni'] == 8294 || $para[$nc2][18]['char_data'][$i2]['uni'] == 8295 || $para[$nc2][18]['char_data'][$i2]['uni'] == 8296) {
                                $lvl++;
                                continue;
                            }
                            if ($para[$nc2][18]['char_data'][$i2]['uni'] == 8297) {
                                $lvl--;
                                break;
                            }
                            if ($para[$nc2][18]['char_data'][$i2]['bidi_class'] === Ucdn::BIDI_CLASS_L || $para[$nc2][18]['char_data'][$i2]['bidi_class'] == Ucdn::BIDI_CLASS_AL || $para[$nc2][18]['char_data'][$i2]['bidi_class'] === Ucdn::BIDI_CLASS_R) {
                                $fsi = $para[$nc2][18]['char_data'][$i2]['bidi_class'];
                                break;
                            }
                        }
                        // if fsi not found, fsi is same as paragraph embedding level
                        if (!$fsi && $fsi !== 0) {
                            if ($pel == 1) {
                                $fsi = Ucdn::BIDI_CLASS_R;
                            } else {
                                $fsi = Ucdn::BIDI_CLASS_L;
                            }
                        }
                    }
                    if ($chunk_ot_ldata['char_data'][$i]['uni'] == 8294 || $fsi === Ucdn::BIDI_CLASS_L) {
                        // LRI or FSI-L
                        //  Compute the least even embedding level greater than the embedding level of the last entry on the directional status stack.
                        $next_level = $cel + 2 - $cel % 2;
                    } elseif ($chunk_ot_ldata['char_data'][$i]['uni'] == 8295 || $fsi == Ucdn::BIDI_CLASS_R || $fsi == Ucdn::BIDI_CLASS_AL) {
                        // RLI or FSI-R
                        //  Compute the least odd embedding level greater than the embedding level of the last entry on the directional status stack.
                        $next_level = $cel + $cel % 2 + 1;
                    }
                    //  Increment the isolate count by one, and push an entry consisting of the new embedding level,
                    //  neutral directional override status, and true directional isolate status onto the directional status stack.
                    $remember[] = ['num' => $chunk_ot_ldata['char_data'][$i]['uni'], 'cel' => $cel, 'dos' => $dos, 'diid' => $diid];
                    $cel = $next_level;
                    $dos = -1;
                    $diid = ++$dictr;
                    // Set new direction isolate ID after incrementing direction isolate counter
                    $controlchars = true;
                } elseif ($chunk_ot_ldata['char_data'][$i]['uni'] == 8297) {
                    // PDI
                    // X6a. With each PDI, perform the following steps:
                    //  Pop the last entry from the directional status stack and decrement the isolate count by one.
                    while (count($remember)) {
                        $last = count($remember) - 1;
                        if ($remember[$last]['num'] == 8294 || $remember[$last]['num'] == 8295 || $remember[$last]['num'] == 8296) {
                            $match = array_pop($remember);
                            $cel = $match['cel'];
                            $dos = $match['dos'];
                            $diid = $match['diid'];
                            break;
                        } elseif ($remember[$last]['num'] == 8235 || $remember[$last]['num'] == 8234 || $remember[$last]['num'] == 8238 || $remember[$last]['num'] == 8237) {
                            $match = array_pop($remember);
                        }
                    }
                    //  In all cases, set the PDI’s level to the embedding level of the last entry on the directional status stack left after the steps above.
                    //  NB The level assigned to an isolate initiator is always the same as that assigned to the matching PDI.
                    if ($dos != -1) {
                        $chardir = $dos;
                    } else {
                        $chardir = $chunk_ot_ldata['char_data'][$i]['bidi_class'];
                    }
                    $chunk_ot_ldata['char_data'][$i]['level'] = $cel;
                    $chunk_ot_ldata['char_data'][$i]['type'] = $chardir;
                    $chunk_ot_ldata['char_data'][$i]['diid'] = $diid;
                    $controlchars = true;
                } elseif ($chunk_ot_ldata['char_data'][$i]['uni'] == 10) {
                    // NEW LINE
                    // Reset to start values
                    $cel = $pel;
                    $dos = -1;
                    $remember = [];
                } else {
                    // X6. For all types besides RLE, LRE, RLO, LRO, and PDF:
                    //  a. Set the level of the current character to the current embedding level.
                    //  b. When the directional override status is not neutral, reset the current character type to directional override status.
                    if ($dos != -1) {
                        $chardir = $dos;
                    } else {
                        $chardir = $chunk_ot_ldata['char_data'][$i]['bidi_class'];
                        if ($chardir == Ucdn::BIDI_CLASS_R || $chardir == Ucdn::BIDI_CLASS_AL) {
                            $strongrtl = true;
                        }
                    }
                    $chunk_ot_ldata['char_data'][$i]['level'] = $cel;
                    $chunk_ot_ldata['char_data'][$i]['type'] = $chardir;
                    $chunk_ot_ldata['char_data'][$i]['diid'] = $diid;
                }
            }
            // X8. All explicit directional embeddings and overrides are completely terminated at the end of each paragraph.
            // Paragraph separators are not included in the embedding.
            // X9. Remove all RLE, LRE, RLO, LRO, and PDF codes.
            if ($controlchars) {
                $this->remove_char($para[$nc][0], $para[$nc][18], "‪");
                $this->remove_char($para[$nc][0], $para[$nc][18], "‫");
                $this->remove_char($para[$nc][0], $para[$nc][18], "‬");
                $this->remove_char($para[$nc][0], $para[$nc][18], "‭");
                $this->remove_char($para[$nc][0], $para[$nc][18], "‮");
                preg_replace("/\\x{202a}-\\x{202e}/u", '', $para[$nc][0]);
            }
        }
        // Remove any blank chunks made by removing directional codes
        $numchunks = count($para);
        for ($nc = $numchunks - 1; $nc >= 0; $nc--) {
            if (count($para[$nc][18]['char_data']) == 0) {
                array_splice($para, $nc, 1);
            }
        }
        if ($dir != 'rtl' && !$strongrtl && !$controlchars) {
            return;
        }
        $numchunks = count($para);
        // X10. Determine the start-of-sequence (sor) and end-of-sequence (eor) types, either L or R, for each isolating run sequence. These depend on the higher of the two levels on either side of the sequence boundary:
        // For sor, compare the level of the first character in the sequence with the level of the character preceding it in the paragraph or if there is none, with the paragraph embedding level.
        // For eor, compare the level of the last character in the sequence with the level of the character following it in the paragraph or if there is none, with the paragraph embedding level.
        // If the higher level is odd, the sor or eor is R; otherwise, it is L.
        for ($ir = 0; $ir <= $dictr; $ir++) {
            $prelevel = $pel;
            $postlevel = $pel;
            $firstchar = true;
            for ($nc = 0; $nc < $numchunks; $nc++) {
                $chardata =& $para[$nc][18]['char_data'];
                $numchars = count($chardata);
                for ($i = 0; $i < $numchars; ++$i) {
                    if (!isset($chardata[$i]['diid'])) {
                        continue;
                    }
                    if ($chardata[$i]['diid'] != $ir) {
                        continue;
                    }
                    // Ignore characters in a different isolate run
                    $right = $postlevel;
                    $nc2 = $nc;
                    $i2 = $i;
                    while (!($nc2 == $numchunks - 1 && $i2 == count($para[$nc2][18]['char_data']) - 1)) {
                        // while not at end of last chunk
                        $i2++;
                        if ($i2 >= count($para[$nc2][18]['char_data'])) {
                            $nc2++;
                            $i2 = 0;
                        }
                        if (isset($para[$nc2][18]['char_data'][$i2]['diid']) && $para[$nc2][18]['char_data'][$i2]['diid'] == $ir) {
                            $right = $para[$nc2][18]['char_data'][$i2]['level'];
                            break;
                        }
                    }
                    $level = $chardata[$i]['level'];
                    if ($firstchar || $level != $prelevel) {
                        $chardata[$i]['sor'] = max($prelevel, $level) % 2 ? Ucdn::BIDI_CLASS_R : Ucdn::BIDI_CLASS_L;
                    }
                    if ($nc == $numchunks - 1 && $i == $numchars - 1 || $level != $right) {
                        $chardata[$i]['eor'] = max($right, $level) % 2 ? Ucdn::BIDI_CLASS_R : Ucdn::BIDI_CLASS_L;
                    }
                    $prelevel = $level;
                    $firstchar = false;
                }
            }
        }
        // 3.3.3 Resolving Weak Types
        // Weak types are now resolved one level run at a time. At level run boundaries where the type of the character on the other side of the boundary is required, the type assigned to sor or eor is used.
        // Nonspacing marks are now resolved based on the previous characters.
        // W1. Examine each nonspacing mark (NSM) in the level run, and change the type of the NSM to the type of the previous character. If the NSM is at the start of the level run, it will get the type of sor.
        for ($ir = 0; $ir <= $dictr; $ir++) {
            $prevtype = 0;
            for ($nc = 0; $nc < $numchunks; $nc++) {
                $chardata =& $para[$nc][18]['char_data'];
                $numchars = count($chardata);
                for ($i = 0; $i < $numchars; ++$i) {
                    if (!isset($chardata[$i]['diid'])) {
                        continue;
                    }
                    if ($chardata[$i]['diid'] != $ir) {
                        continue;
                    }
                    // Ignore characters in a different isolate run
                    if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_NSM) {
                        if (isset($chardata[$i]['sor'])) {
                            $chardata[$i]['type'] = $chardata[$i]['sor'];
                        } else {
                            $chardata[$i]['type'] = $prevtype;
                        }
                    }
                    $prevtype = $chardata[$i]['type'];
                }
            }
        }
        // W2. Search backward from each instance of a European number until the first strong type (R, L, AL or sor) is found. If an AL is found, change the type of the European number to Arabic number.
        for ($ir = 0; $ir <= $dictr; $ir++) {
            $laststrongtype = -1;
            for ($nc = 0; $nc < $numchunks; $nc++) {
                $chardata =& $para[$nc][18]['char_data'];
                $numchars = count($chardata);
                for ($i = 0; $i < $numchars; ++$i) {
                    if (!isset($chardata[$i]['diid'])) {
                        continue;
                    }
                    if ($chardata[$i]['diid'] != $ir) {
                        continue;
                    }
                    // Ignore characters in a different isolate run
                    if (isset($chardata[$i]['sor'])) {
                        $laststrongtype = $chardata[$i]['sor'];
                    }
                    if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_EN && $laststrongtype == Ucdn::BIDI_CLASS_AL) {
                        $chardata[$i]['type'] = Ucdn::BIDI_CLASS_AN;
                    }
                    if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_L || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_R || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_AL) {
                        $laststrongtype = $chardata[$i]['type'];
                    }
                }
            }
        }
        // W3. Change all ALs to R.
        for ($nc = 0; $nc < $numchunks; $nc++) {
            $chardata =& $para[$nc][18]['char_data'];
            $numchars = count($chardata);
            for ($i = 0; $i < $numchars; ++$i) {
                if (isset($chardata[$i]['type']) && $chardata[$i]['type'] == Ucdn::BIDI_CLASS_AL) {
                    $chardata[$i]['type'] = Ucdn::BIDI_CLASS_R;
                }
            }
        }
        // W4. A single European separator between two European numbers changes to a European number. A single common separator between two numbers of the same type changes to that type.
        for ($ir = 0; $ir <= $dictr; $ir++) {
            $prevtype = -1;
            $nexttype = -1;
            for ($nc = 0; $nc < $numchunks; $nc++) {
                $chardata =& $para[$nc][18]['char_data'];
                $numchars = count($chardata);
                for ($i = 0; $i < $numchars; ++$i) {
                    if (!isset($chardata[$i]['diid'])) {
                        continue;
                    }
                    if ($chardata[$i]['diid'] != $ir) {
                        continue;
                    }
                    // Ignore characters in a different isolate run
                    // Get next type
                    $nexttype = -1;
                    $nc2 = $nc;
                    $i2 = $i;
                    while (!($nc2 == $numchunks - 1 && $i2 == count($para[$nc2][18]['char_data']) - 1)) {
                        // while not at end of last chunk
                        $i2++;
                        if ($i2 >= count($para[$nc2][18]['char_data'])) {
                            $nc2++;
                            $i2 = 0;
                        }
                        if (isset($para[$nc2][18]['char_data'][$i2]['diid']) && $para[$nc2][18]['char_data'][$i2]['diid'] == $ir) {
                            $nexttype = $para[$nc2][18]['char_data'][$i2]['type'];
                            break;
                        }
                    }
                    if (!isset($chardata[$i]['sor']) && !isset($chardata[$i]['eor'])) {
                        if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_ES && $prevtype == Ucdn::BIDI_CLASS_EN && $nexttype == Ucdn::BIDI_CLASS_EN) {
                            $chardata[$i]['type'] = Ucdn::BIDI_CLASS_EN;
                        } elseif ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_CS && $prevtype == Ucdn::BIDI_CLASS_EN && $nexttype == Ucdn::BIDI_CLASS_EN) {
                            $chardata[$i]['type'] = Ucdn::BIDI_CLASS_EN;
                        } elseif ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_CS && $prevtype == Ucdn::BIDI_CLASS_AN && $nexttype == Ucdn::BIDI_CLASS_AN) {
                            $chardata[$i]['type'] = Ucdn::BIDI_CLASS_AN;
                        }
                    }
                    $prevtype = $chardata[$i]['type'];
                }
            }
        }
        // W5. A sequence of European terminators adjacent to European numbers changes to all European numbers.
        for ($ir = 0; $ir <= $dictr; $ir++) {
            $prevtype = -1;
            $nexttype = -1;
            for ($nc = 0; $nc < $numchunks; $nc++) {
                $chardata =& $para[$nc][18]['char_data'];
                $numchars = count($chardata);
                for ($i = 0; $i < $numchars; ++$i) {
                    if (!isset($chardata[$i]['diid'])) {
                        continue;
                    }
                    if ($chardata[$i]['diid'] != $ir) {
                        continue;
                    }
                    // Ignore characters in a different isolate run
                    if (isset($chardata[$i]['sor'])) {
                        $prevtype = $chardata[$i]['sor'];
                    }
                    if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_ET) {
                        if ($prevtype == Ucdn::BIDI_CLASS_EN) {
                            $chardata[$i]['type'] = Ucdn::BIDI_CLASS_EN;
                        } elseif (!isset($chardata[$i]['eor'])) {
                            $nexttype = -1;
                            $nc2 = $nc;
                            $i2 = $i;
                            while (!($nc2 == $numchunks - 1 && $i2 == count($para[$nc2][18]['char_data']) - 1)) {
                                // while not at end of last chunk
                                $i2++;
                                if ($i2 >= count($para[$nc2][18]['char_data'])) {
                                    $nc2++;
                                    $i2 = 0;
                                }
                                if (!isset($para[$nc2][18]['char_data'][$i2]['diid'])) {
                                    continue;
                                }
                                if ($para[$nc2][18]['char_data'][$i2]['diid'] != $ir) {
                                    continue;
                                }
                                $nexttype = $para[$nc2][18]['char_data'][$i2]['type'];
                                if (isset($para[$nc2][18]['char_data'][$i2]['sor'])) {
                                    break;
                                }
                                if ($nexttype == Ucdn::BIDI_CLASS_EN) {
                                    $chardata[$i]['type'] = Ucdn::BIDI_CLASS_EN;
                                    break;
                                } elseif ($nexttype != Ucdn::BIDI_CLASS_ET) {
                                    break;
                                }
                            }
                        }
                    }
                    $prevtype = $chardata[$i]['type'];
                }
            }
        }
        // W6. Otherwise, separators and terminators change to Other Neutral.
        for ($nc = 0; $nc < $numchunks; $nc++) {
            $chardata =& $para[$nc][18]['char_data'];
            $numchars = count($chardata);
            for ($i = 0; $i < $numchars; ++$i) {
                if (isset($chardata[$i]['type']) && ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_ET || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_ES || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_CS)) {
                    $chardata[$i]['type'] = Ucdn::BIDI_CLASS_ON;
                }
            }
        }
        //W7. Search backward from each instance of a European number until the first strong type (R, L, or sor) is found. If an L is found, then change the type of the European number to L.
        for ($ir = 0; $ir <= $dictr; $ir++) {
            $laststrongtype = -1;
            for ($nc = 0; $nc < $numchunks; $nc++) {
                $chardata =& $para[$nc][18]['char_data'];
                $numchars = count($chardata);
                for ($i = 0; $i < $numchars; ++$i) {
                    if (!isset($chardata[$i]['diid'])) {
                        continue;
                    }
                    if ($chardata[$i]['diid'] != $ir) {
                        continue;
                    }
                    // Ignore characters in a different isolate run
                    if (isset($chardata[$i]['sor'])) {
                        $laststrongtype = $chardata[$i]['sor'];
                    }
                    if (isset($chardata[$i]['type']) && $chardata[$i]['type'] == Ucdn::BIDI_CLASS_EN && $laststrongtype == Ucdn::BIDI_CLASS_L) {
                        $chardata[$i]['type'] = Ucdn::BIDI_CLASS_L;
                    }
                    if (isset($chardata[$i]['type']) && ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_L || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_R || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_AL)) {
                        $laststrongtype = $chardata[$i]['type'];
                    }
                }
            }
        }
        // N1. A sequence of neutrals takes the direction of the surrounding strong text if the text on both sides has the same direction. European and Arabic numbers act as if they were R in terms of their influence on neutrals. Start-of-level-run (sor) and end-of-level-run (eor) are used at level run boundaries.
        for ($ir = 0; $ir <= $dictr; $ir++) {
            $laststrongtype = -1;
            for ($nc = 0; $nc < $numchunks; $nc++) {
                $chardata =& $para[$nc][18]['char_data'];
                $numchars = count($chardata);
                for ($i = 0; $i < $numchars; ++$i) {
                    if (!isset($chardata[$i]['diid'])) {
                        continue;
                    }
                    if ($chardata[$i]['diid'] != $ir) {
                        continue;
                    }
                    // Ignore characters in a different isolate run
                    if (isset($chardata[$i]['sor'])) {
                        $laststrongtype = $chardata[$i]['sor'];
                    }
                    if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_ON || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_WS) {
                        $left = -1;
                        // LEFT
                        if ($laststrongtype == Ucdn::BIDI_CLASS_R || $laststrongtype == Ucdn::BIDI_CLASS_EN || $laststrongtype == Ucdn::BIDI_CLASS_AN) {
                            $left = Ucdn::BIDI_CLASS_R;
                        } elseif ($laststrongtype == Ucdn::BIDI_CLASS_L) {
                            $left = Ucdn::BIDI_CLASS_L;
                        }
                        // RIGHT
                        $right = -1;
                        // move to the right of any following neutrals OR hit a run boundary
                        if (isset($chardata[$i]['eor'])) {
                            $right = $chardata[$i]['eor'];
                        } else {
                            $nexttype = -1;
                            $nc2 = $nc;
                            $i2 = $i;
                            while (!($nc2 == $numchunks - 1 && $i2 == count($para[$nc2][18]['char_data']) - 1)) {
                                // while not at end of last chunk
                                $i2++;
                                if ($i2 >= count($para[$nc2][18]['char_data'])) {
                                    $nc2++;
                                    $i2 = 0;
                                }
                                if (!isset($para[$nc2][18]['char_data'][$i2]['diid'])) {
                                    continue;
                                }
                                if ($para[$nc2][18]['char_data'][$i2]['diid'] != $ir) {
                                    continue;
                                }
                                $nexttype = $para[$nc2][18]['char_data'][$i2]['type'];
                                if ($nexttype == Ucdn::BIDI_CLASS_R || $nexttype == Ucdn::BIDI_CLASS_EN || $nexttype == Ucdn::BIDI_CLASS_AN) {
                                    $right = Ucdn::BIDI_CLASS_R;
                                    break;
                                } elseif ($nexttype == Ucdn::BIDI_CLASS_L) {
                                    $right = Ucdn::BIDI_CLASS_L;
                                    break;
                                } elseif (isset($para[$nc2][18]['char_data'][$i2]['eor'])) {
                                    $right = $para[$nc2][18]['char_data'][$i2]['eor'];
                                    break;
                                }
                            }
                        }
                        if ($left > -1 && $left == $right) {
                            $chardata[$i]['orig_type'] = $chardata[$i]['type'];
                            // Need to store the original 'WS' for reference in L1 below
                            $chardata[$i]['type'] = $left;
                        }
                    } elseif ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_L || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_R || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_EN || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_AN) {
                        $laststrongtype = $chardata[$i]['type'];
                    }
                }
            }
        }
        // N2. Any remaining neutrals take the embedding direction
        for ($nc = 0; $nc < $numchunks; $nc++) {
            $chardata =& $para[$nc][18]['char_data'];
            $numchars = count($chardata);
            for ($i = 0; $i < $numchars; ++$i) {
                if (isset($chardata[$i]['type']) && ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_ON || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_WS)) {
                    $chardata[$i]['orig_type'] = $chardata[$i]['type'];
                    // Need to store the original 'WS' for reference in L1 below
                    $chardata[$i]['type'] = $chardata[$i]['level'] % 2 ? Ucdn::BIDI_CLASS_R : Ucdn::BIDI_CLASS_L;
                }
            }
        }
        // I1. For all characters with an even (left-to-right) embedding direction, those of type R go up one level and those of type AN or EN go up two levels.
        // I2. For all characters with an odd (right-to-left) embedding direction, those of type L, EN or AN go up one level.
        for ($nc = 0; $nc < $numchunks; $nc++) {
            $chardata =& $para[$nc][18]['char_data'];
            $numchars = count($chardata);
            for ($i = 0; $i < $numchars; ++$i) {
                if (isset($chardata[$i]['level'])) {
                    $odd = $chardata[$i]['level'] % 2;
                    if ($odd) {
                        if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_L || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_AN || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_EN) {
                            $chardata[$i]['level'] += 1;
                        }
                    } else if ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_R) {
                        $chardata[$i]['level'] += 1;
                    } elseif ($chardata[$i]['type'] == Ucdn::BIDI_CLASS_AN || $chardata[$i]['type'] == Ucdn::BIDI_CLASS_EN) {
                        $chardata[$i]['level'] += 2;
                    }
                }
            }
        }
        // Remove Isolate formatters
        $numchunks = count($para);
        if ($controlchars) {
            for ($nc = 0; $nc < $numchunks; $nc++) {
                $this->remove_char($para[$nc][0], $para[$nc][18], "⁦");
                $this->remove_char($para[$nc][0], $para[$nc][18], "⁧");
                $this->remove_char($para[$nc][0], $para[$nc][18], "⁨");
                $this->remove_char($para[$nc][0], $para[$nc][18], "⁩");
                preg_replace("/\\x{2066}-\\x{2069}/u", '', $para[$nc][0]);
            }
            // Remove any blank chunks made by removing directional codes
            for ($nc = $numchunks - 1; $nc >= 0; $nc--) {
                if (count($para[$nc][18]['char_data']) == 0) {
                    array_splice($para, $nc, 1);
                }
            }
        }
    }
    /**
     * Reorder, once divided into lines
     */
    public function bidi_reorder(array &$chunkorder, array &$content, array &$c_ot_ldata, $blockdir)
    {
        $bidi_data = [];
        // First combine into one array (and get the highest level in use)
        $numchunks = count($content);
        $maxlevel = 0;
        for ($nc = 0; $nc < $numchunks; $nc++) {
            $numchars = isset($c_ot_ldata[$nc]['char_data']) ? count($c_ot_ldata[$nc]['char_data']) : 0;
            for ($i = 0; $i < $numchars; ++$i) {
                $carac = ['level' => 0];
                if (isset($c_ot_ldata[$nc]['GPOSinfo'][$i])) {
                    $carac['GPOSinfo'] = $c_ot_ldata[$nc]['GPOSinfo'][$i];
                }
                $carac['uni'] = $c_ot_ldata[$nc]['char_data'][$i]['uni'];
                if (isset($c_ot_ldata[$nc]['char_data'][$i]['type'])) {
                    $carac['type'] = $c_ot_ldata[$nc]['char_data'][$i]['type'];
                }
                if (isset($c_ot_ldata[$nc]['char_data'][$i]['level'])) {
                    $carac['level'] = $c_ot_ldata[$nc]['char_data'][$i]['level'];
                }
                if (isset($c_ot_ldata[$nc]['char_data'][$i]['orig_type'])) {
                    $carac['orig_type'] = $c_ot_ldata[$nc]['char_data'][$i]['orig_type'];
                }
                $carac['group'] = $c_ot_ldata[$nc]['group'][$i];
                $carac['chunkid'] = $chunkorder[$nc];
                // gives font id and/or object ID
                $maxlevel = max(isset($carac['level']) ? $carac['level'] : 0, $maxlevel);
                $bidi_data[] = $carac;
            }
        }
        if ($maxlevel === 0) {
            return;
        }
        $numchars = count($bidi_data);
        // L1. On each line, reset the embedding level of the following characters to the paragraph embedding level:
        //  1. Segment separators (Tab) 'S',
        //  2. Paragraph separators 'B',
        //  3. Any sequence of whitespace characters 'WS' preceding a segment separator or paragraph separator, and
        //  4. Any sequence of whitespace characters 'WS' at the end of the line.
        //  The types of characters used here are the original types, not those modified by the previous phase cf N1 and N2*******
        //  Because a Paragraph Separator breaks lines, there will be at most one per line, at the end of that line.
        // Set the initial paragraph embedding level
        if ($blockdir === 'rtl') {
            $pel = 1;
        } else {
            $pel = 0;
        }
        for ($i = $numchars - 1; $i > 0; $i--) {
            if ($bidi_data[$i]['type'] == Ucdn::BIDI_CLASS_WS || isset($bidi_data[$i]['orig_type']) && $bidi_data[$i]['orig_type'] == Ucdn::BIDI_CLASS_WS) {
                $bidi_data[$i]['level'] = $pel;
            } else {
                break;
            }
        }
        // L2. From the highest level found in the text to the lowest odd level on each line, including intermediate levels not actually present in the text, reverse any contiguous sequence of characters that are at that level or higher.
        for ($j = $maxlevel; $j > 0; $j--) {
            $ordarray = [];
            $revarr = [];
            $onlevel = false;
            for ($i = 0; $i < $numchars; ++$i) {
                if ($bidi_data[$i]['level'] >= $j) {
                    $onlevel = true;
                    // L4. A character is depicted by a mirrored glyph if and only if (a) the resolved directionality of that character is R, and (b) the Bidi_Mirrored property value of that character is true.
                    if (isset(Ucdn::$mirror_pairs[$bidi_data[$i]['uni']]) && $bidi_data[$i]['type'] == Ucdn::BIDI_CLASS_R) {
                        $bidi_data[$i]['uni'] = Ucdn::$mirror_pairs[$bidi_data[$i]['uni']];
                    }
                    $revarr[] = $bidi_data[$i];
                } else {
                    if ($onlevel) {
                        $revarr = array_reverse($revarr);
                        $ordarray = array_merge($ordarray, $revarr);
                        $revarr = [];
                        $onlevel = false;
                    }
                    $ordarray[] = $bidi_data[$i];
                }
            }
            if ($onlevel) {
                $revarr = array_reverse($revarr);
                $ordarray = array_merge($ordarray, $revarr);
            }
            $bidi_data = $ordarray;
        }
        $content = [];
        $c_ot_ldata = [];
        $chunkorder = [];
        $nc = -1;
        // New chunk order ID
        $chunkid = -1;
        foreach ($bidi_data as $carac) {
            if ($carac['chunkid'] != $chunkid) {
                $nc++;
                $chunkorder[$nc] = $carac['chunkid'];
                $cctr = 0;
                $content[$nc] = '';
                $c_ot_ldata[$nc]['group'] = '';
            }
            if ($carac['uni'] != 0xfffc) {
                // Object replacement character (65532)
                $content[$nc] .= Utf_String::code2utf($carac['uni']);
                $c_ot_ldata[$nc]['group'] .= $carac['group'];
                if (!empty($carac['GPOSinfo'])) {
                    if (isset($carac['GPOSinfo'])) {
                        $c_ot_ldata[$nc]['GPOSinfo'][$cctr] = $carac['GPOSinfo'];
                    }
                    $c_ot_ldata[$nc]['GPOSinfo'][$cctr]['wDir'] = $carac['level'] % 2 ? 'RTL' : 'LTR';
                }
            }
            $chunkid = $carac['chunkid'];
            $cctr++;
        }
    }
    public function split_ot_ldata(array &$c_ot_ldata, $ot_lcutoffpos, $ot_lrestartpos = '')
    {
        if (!$ot_lrestartpos) {
            $ot_lrestartpos = $ot_lcutoffpos;
        }
        $new_ot_ldata = ['GPOSinfo' => [], 'char_data' => []];
        $new_ot_ldata['group'] = substr($c_ot_ldata['group'], $ot_lrestartpos);
        $c_ot_ldata['group'] = substr($c_ot_ldata['group'], 0, $ot_lcutoffpos);
        if (isset($c_ot_ldata['GPOSinfo']) && $c_ot_ldata['GPOSinfo']) {
            foreach ($c_ot_ldata['GPOSinfo'] as $k => $val) {
                if ($k >= $ot_lrestartpos) {
                    $new_ot_ldata['GPOSinfo'][$k - $ot_lrestartpos] = $val;
                }
                if ($k >= $ot_lcutoffpos) {
                    unset($c_ot_ldata['GPOSinfo'][$k]);
                    //$cOTLdata['GPOSinfo'][$k] = array();
                }
            }
        }
        if (isset($c_ot_ldata['char_data'])) {
            $new_ot_ldata['char_data'] = array_slice($c_ot_ldata['char_data'], $ot_lrestartpos);
            array_splice($c_ot_ldata['char_data'], $ot_lcutoffpos);
        }
        // Not necessary - easier to debug
        if (isset($c_ot_ldata['GPOSinfo'])) {
            ksort($c_ot_ldata['GPOSinfo']);
        }
        if (isset($new_ot_ldata['GPOSinfo'])) {
            ksort($new_ot_ldata['GPOSinfo']);
        }
        return $new_ot_ldata;
    }
    public function slice_ot_ldata(array $ot_ldata, $pos, $len)
    {
        $new_ot_ldata = ['GPOSinfo' => [], 'char_data' => []];
        $new_ot_ldata['group'] = substr($ot_ldata['group'], $pos, $len);
        if ($ot_ldata['GPOSinfo']) {
            foreach ($ot_ldata['GPOSinfo'] as $k => $val) {
                if ($k >= $pos && $k < $pos + $len) {
                    $new_ot_ldata['GPOSinfo'][$k - $pos] = $val;
                }
            }
        }
        if (isset($ot_ldata['char_data'])) {
            $new_ot_ldata['char_data'] = array_slice($ot_ldata['char_data'], $pos, $len);
        }
        // Not necessary - easier to debug
        if ($new_ot_ldata['GPOSinfo']) {
            ksort($new_ot_ldata['GPOSinfo']);
        }
        return $new_ot_ldata;
    }
    /**
     * Remove one or more occurrences of $char (single character) from $txt and adjust OTLdata
     */
    public function remove_char(&$txt, array &$c_ot_ldata, $char)
    {
        while (mb_strpos($txt, $char, 0, $this->mpdf->mb_enc) !== false) {
            $pos = mb_strpos($txt, $char, 0, $this->mpdf->mb_enc);
            $new_gpo_sinfo = [];
            $c_ot_ldata['group'] = substr_replace($c_ot_ldata['group'], '', $pos, 1);
            if ($c_ot_ldata['GPOSinfo']) {
                foreach ($c_ot_ldata['GPOSinfo'] as $k => $val) {
                    if ($k > $pos) {
                        $new_gpo_sinfo[$k - 1] = $val;
                    } elseif ($k != $pos) {
                        $new_gpo_sinfo[$k] = $val;
                    }
                }
                $c_ot_ldata['GPOSinfo'] = $new_gpo_sinfo;
            }
            if (isset($c_ot_ldata['char_data'])) {
                array_splice($c_ot_ldata['char_data'], $pos, 1);
            }
            $txt = preg_replace("/" . $char . "/", '', $txt, 1);
        }
    }
    /**
     * Remove one or more occurrences of $char (single character) from $txt and adjust OTLdata
     */
    public function replace_space(&$txt, array &$c_ot_ldata)
    {
        $char = chr(194) . chr(160);
        // NBSP
        while (mb_strpos($txt, $char, 0, $this->mpdf->mb_enc) !== false) {
            $pos = mb_strpos($txt, $char, 0, $this->mpdf->mb_enc);
            if ($c_ot_ldata['char_data'][$pos]['uni'] == 160) {
                $c_ot_ldata['char_data'][$pos]['uni'] = 32;
            }
            $txt = preg_replace("/" . $char . "/", ' ', $txt, 1);
        }
    }
    public function trim_ot_ldata(&$c_ot_ldata, $Left = true, $Right = true)
    {
        $len = !is_array($c_ot_ldata) || $c_ot_ldata['char_data'] === null ? 0 : count($c_ot_ldata['char_data']);
        $n_left = 0;
        $n_right = 0;
        for ($i = 0; $i < $len; $i++) {
            if ($c_ot_ldata['char_data'][$i]['uni'] == 32 || $c_ot_ldata['char_data'][$i]['uni'] == 12288) {
                $n_left++;
            } else {
                break;
            }
        }
        for ($i = $len - 1; $i >= 0; $i--) {
            if ($c_ot_ldata['char_data'][$i]['uni'] == 32 || $c_ot_ldata['char_data'][$i]['uni'] == 12288) {
                $n_right++;
            } else {
                break;
            }
        }
        // Trim Right
        if ($Right && $n_right) {
            $c_ot_ldata['group'] = substr($c_ot_ldata['group'], 0, strlen($c_ot_ldata['group']) - $n_right);
            if ($c_ot_ldata['GPOSinfo']) {
                foreach ($c_ot_ldata['GPOSinfo'] as $k => $val) {
                    if ($k >= $len - $n_right) {
                        unset($c_ot_ldata['GPOSinfo'][$k]);
                    }
                }
            }
            if (isset($c_ot_ldata['char_data'])) {
                for ($i = 0; $i < $n_right; $i++) {
                    array_pop($c_ot_ldata['char_data']);
                }
            }
        }
        // Trim Left
        if ($Left && $n_left) {
            $c_ot_ldata['group'] = substr($c_ot_ldata['group'], $n_left);
            if ($c_ot_ldata['GPOSinfo']) {
                $new_po_sinfo = [];
                foreach ($c_ot_ldata['GPOSinfo'] as $k => $val) {
                    if ($k >= $n_left) {
                        $new_po_sinfo[$k - $n_left] = $c_ot_ldata['GPOSinfo'][$k];
                    }
                }
                $c_ot_ldata['GPOSinfo'] = $new_po_sinfo;
            }
            if (isset($c_ot_ldata['char_data'])) {
                for ($i = 0; $i < $n_left; $i++) {
                    array_shift($c_ot_ldata['char_data']);
                }
            }
        }
    }
    ////////////////////////////////////////////////////////////////
    //////////         GENERAL OTL FUNCTIONS       /////////////////
    ////////////////////////////////////////////////////////////////
    private function glyph_to_char($gid)
    {
        return (ord($this->glyph_i_dto_uni[$gid * 3]) << 16) + (ord($this->glyph_i_dto_uni[$gid * 3 + 1]) << 8) + ord($this->glyph_i_dto_uni[$gid * 3 + 2]);
    }
    private function unicode_hex($unicode_dec)
    {
        return str_pad(strtoupper(dechex($unicode_dec)), 5, '0', STR_PAD_LEFT);
    }
    private function seek($pos)
    {
        $this->_pos = $pos;
    }
    private function skip($delta)
    {
        $this->_pos += $delta;
    }
    private function read_short()
    {
        $a = (ord($this->ttf_ot_ldata[$this->_pos]) << 8) + ord($this->ttf_ot_ldata[$this->_pos + 1]);
        if ($a & 1 << 15) {
            $a = $a - (1 << 16);
        }
        $this->_pos += 2;
        return $a;
    }
    private function read_ushort()
    {
        $a = (ord($this->ttf_ot_ldata[$this->_pos]) << 8) + ord($this->ttf_ot_ldata[$this->_pos + 1]);
        $this->_pos += 2;
        return $a;
    }
    private function _get_coverage_gid()
    {
        // Called from Lookup Type 1, Format 1 - returns glyphIDs rather than hexstrings
        // Need to do this separately to cache separately
        // Otherwise the same as fn below _getCoverage
        $offset = $this->_pos;
        if (isset($this->lu_data_cache[$this->fontkey]['GID'][$offset])) {
            $g = $this->lu_data_cache[$this->fontkey]['GID'][$offset];
        } else {
            $g = [];
            $coverage_format = $this->read_ushort();
            if ($coverage_format == 1) {
                $coverage_glyph_count = $this->read_ushort();
                for ($gid = 0; $gid < $coverage_glyph_count; $gid++) {
                    $glyph_id = $this->read_ushort();
                    $g[] = $glyph_id;
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
                        $g[] = $glyph_id;
                    }
                }
            }
            $this->lu_data_cache[$this->fontkey]['GID'][$offset] = $g;
        }
        return $g;
    }
    private function _get_coverage()
    {
        $offset = $this->_pos;
        if (isset($this->lu_data_cache[$this->fontkey][$offset])) {
            $g = $this->lu_data_cache[$this->fontkey][$offset];
        } else {
            $g = [];
            $coverage_format = $this->read_ushort();
            if ($coverage_format == 1) {
                $coverage_glyph_count = $this->read_ushort();
                for ($gid = 0; $gid < $coverage_glyph_count; $gid++) {
                    $glyph_id = $this->read_ushort();
                    $g[] = $this->unicode_hex($this->glyph_to_char($glyph_id));
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
                        $g[] = $this->unicode_hex($this->glyph_to_char($glyph_id));
                    }
                }
            }
            $this->lu_data_cache[$this->fontkey][$offset] = $g;
        }
        return $g;
    }
    private function _get_classes($offset)
    {
        if (isset($this->lu_data_cache[$this->fontkey][$offset])) {
            $glyph_by_class = $this->lu_data_cache[$this->fontkey][$offset];
        } else {
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
                    // Note: Font FreeSerif , tag "blws"
                    // $BacktrackClasses[0] is defined ? a mistake in the font ???
                    // Let's ignore for now
                    if ($class > 0) {
                        for ($g = $start_glyph_id; $g <= $end_glyph_id; $g++) {
                            if ($this->glyph_to_char($g)) {
                                $glyph_by_class[$class][$this->glyph_to_char($g)] = 1;
                            }
                        }
                    }
                }
            } elseif ($class_format == 2) {
                $table_count = $this->read_ushort();
                for ($i = 0; $i < $table_count; $i++) {
                    $start_glyph_id = $this->read_ushort();
                    $end_glyph_id = $this->read_ushort();
                    $class = $this->read_ushort();
                    // Note: Font FreeSerif , tag "blws"
                    // $BacktrackClasses[0] is defined ? a mistake in the font ???
                    // Let's ignore for now
                    if ($class > 0) {
                        for ($g = $start_glyph_id; $g <= $end_glyph_id; $g++) {
                            if ($this->glyph_to_char($g)) {
                                $glyph_by_class[$class][$this->glyph_to_char($g)] = 1;
                            }
                        }
                    }
                }
            }
            $this->lu_data_cache[$this->fontkey][$offset] = $glyph_by_class;
        }
        return $glyph_by_class;
    }
    private function _get_ot_lscript_tag(array $script_lang, $scripttag, $scriptblock, $shaper, $use_otl)
    {
        // ScriptLang is the array of available script/lang tags supported by the font
        // $scriptblock is the (number/code) for the script of the actual text string based on Unicode properties (Ucdn::$uni_scriptblock)
        // $scripttag is the default tag derived from $scriptblock
        /*
        		  http://www.microsoft.com/typography/otspec/ttoreg.htm
        		  http://www.microsoft.com/typography/otspec/scripttags.htm
         Values for useOTL
         Bit   dn  hn  Value
        		  1 1   0x0001  GSUB/GPOS - Latin scripts
        		  2 2   0x0002  GSUB/GPOS - Cyrillic scripts
        		  3 4   0x0004  GSUB/GPOS - Greek scripts
        		  4 8   0x0008  GSUB/GPOS - CJK scripts (excluding Hangul-Jamo)
        		  5 16  0x0010  (Reserved)
        		  6 32  0x0020  (Reserved)
        		  7 64  0x0040  (Reserved)
        		  8 128 0x0080  GSUB/GPOS - All other scripts (including all RTL scripts, complex scripts with shapers etc)
         NB If change for RTL - cf. function magic_reverse_dir in mpdf.php to update
        */
        if ($scriptblock == Ucdn::SCRIPT_LATIN) {
            if (!($use_otl & 0x1)) {
                return ['', false];
            }
        } elseif ($scriptblock == Ucdn::SCRIPT_CYRILLIC) {
            if (!($use_otl & 0x2)) {
                return ['', false];
            }
        } elseif ($scriptblock == Ucdn::SCRIPT_GREEK) {
            if (!($use_otl & 0x4)) {
                return ['', false];
            }
        } elseif ($scriptblock >= Ucdn::SCRIPT_HIRAGANA && $scriptblock <= Ucdn::SCRIPT_YI) {
            if (!($use_otl & 0x8)) {
                return ['', false];
            }
        } else if (!($use_otl & 0x80)) {
            return ['', false];
        }
        //  If availabletags includes scripttag - choose
        if (isset($script_lang[$scripttag])) {
            return [$scripttag, false];
        }
        //  If INDIC (or Myanmar) and available tag not includes new version, check if includes old version & choose old version
        if ($shaper) {
            switch ($scripttag) {
                case 'bng2':
                    if (isset($script_lang['beng'])) {
                        return ['beng', true];
                    }
                // fallthrough
                case 'dev2':
                    if (isset($script_lang['deva'])) {
                        return ['deva', true];
                    }
                // fallthrough
                case 'gjr2':
                    if (isset($script_lang['gujr'])) {
                        return ['gujr', true];
                    }
                // fallthrough
                case 'gur2':
                    if (isset($script_lang['guru'])) {
                        return ['guru', true];
                    }
                // fallthrough
                case 'knd2':
                    if (isset($script_lang['knda'])) {
                        return ['knda', true];
                    }
                // fallthrough
                case 'mlm2':
                    if (isset($script_lang['mlym'])) {
                        return ['mlym', true];
                    }
                // fallthrough
                case 'ory2':
                    if (isset($script_lang['orya'])) {
                        return ['orya', true];
                    }
                // fallthrough
                case 'tml2':
                    if (isset($script_lang['taml'])) {
                        return ['taml', true];
                    }
                // fallthrough
                case 'tel2':
                    if (isset($script_lang['telu'])) {
                        return ['telu', true];
                    }
                // fallthrough
                case 'mym2':
                    if (isset($script_lang['mymr'])) {
                        return ['mymr', true];
                    }
            }
        }
        //  choose DFLT if present
        if (isset($script_lang['DFLT'])) {
            return ['DFLT', false];
        }
        //  else choose dflt if present
        if (isset($script_lang['dflt'])) {
            return ['dflt', false];
        }
        //  else return no scriptTag
        if (isset($script_lang['latn'])) {
            return ['latn', false];
        }
        //  else return no scriptTag
        return ['', false];
    }
    // LangSys tags
    private function _get_otl_lang_tag($ietf, $available)
    {
        // http://en.wikipedia.org/wiki/List_of_ISO_639-1_codes
        // http://www.microsoft.com/typography/otspec/languagetags.htm
        // IETF tag = e.g. en-US, und-Arab, sr-Cyrl cf. class LangToFont
        if ($available == '') {
            return '';
        }
        $tags = $ietf ? preg_split('/-/', $ietf) : [];
        $lang = '';
        $country = '';
        $lang = isset($tags[0]) ? strtolower($tags[0]) : '';
        if (isset($tags[1]) && $tags[1]) {
            if (strlen($tags[1]) == 2) {
                $country = strtolower($tags[1]);
            }
        }
        if (isset($tags[2]) && $tags[2]) {
            $country = strtolower($tags[2]);
        }
        if ($lang != '' && isset(Ucdn::$ot_languages[$lang])) {
            $langsys = Ucdn::$ot_languages[$lang];
        } elseif ($lang != '' && $country != '' && isset(Ucdn::$ot_languages[$lang . '' . $country])) {
            $langsys = Ucdn::$ot_languages[$lang . '' . $country];
        } else {
            $langsys = "DFLT";
        }
        if (strpos($available, $langsys) === false) {
            if (strpos($available, "DFLT") !== false) {
                return "DFLT";
            }
            return '';
        }
        return $langsys;
    }
    private function _dumpproc($GPOSSUB, $lookup_id, $subtable, $Type, $Format, $ptr, $curr_glyph, $level)
    {
        echo '<div style="padding-left: ' . $level * 2 . 'em;">';
        echo $GPOSSUB . ' LookupID #' . $lookup_id . ' Subtable#' . $subtable . ' Type: ' . $Type . ' Format: ' . $Format . '<br />';
        echo '<div style="font-family:monospace">';
        echo 'Glyph position: ' . $ptr . ' Current Glyph: ' . $curr_glyph . '<br />';
        for ($i = 0; $i < count($this->ot_ldata); $i++) {
            if ($i == $ptr) {
                echo '<b>';
            }
            echo $this->ot_ldata[$i]['hex'] . ' ';
            if ($i == $ptr) {
                echo '</b>';
            }
        }
        echo '<br />';
        for ($i = 0; $i < count($this->ot_ldata); $i++) {
            if ($i == $ptr) {
                echo '<b>';
            }
            echo str_pad($this->ot_ldata[$i]['uni'], 5) . ' ';
            if ($i == $ptr) {
                echo '</b>';
            }
        }
        echo '<br />';
        if ($GPOSSUB == 'GPOS') {
            for ($i = 0; $i < count($this->ot_ldata); $i++) {
                if (!empty($this->ot_ldata[$i]['GPOSinfo'])) {
                    echo $this->ot_ldata[$i]['hex'] . ' &#x' . $this->ot_ldata[$i]['hex'] . '; ';
                    print_r($this->ot_ldata[$i]['GPOSinfo']);
                    echo ' ';
                }
            }
        }
        echo '</div>';
        echo '</div>';
    }
}