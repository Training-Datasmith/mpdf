<?php

declare (strict_types=1);
namespace Mpdf\Fonts;

use Mpdf\Tt_Font_File;
class Metrics_Generator
{
    private $font_cache;
    private $font_descriptor;
    public function __construct(Font_Cache $font_cache, $font_descriptor)
    {
        $this->font_cache = $font_cache;
        $this->font_descriptor = $font_descriptor;
    }
    public function generate_metrics($ttffile, array $ttfstat, $fontkey, $tt_cfont_id, $debugfonts, $bm_ponly, $use_otl, $font_use_otl)
    {
        $ttf = new Tt_Font_File($this->font_cache, $this->font_descriptor);
        $ttf->get_metrics($ttffile, $fontkey, $tt_cfont_id, $debugfonts, $bm_ponly, $use_otl);
        // mPDF 5.7.1
        $font = [
            'name' => $this->get_font_name($ttf->full_name),
            'type' => 'TTF',
            'desc' => [
                'CapHeight' => round($ttf->cap_height),
                'XHeight' => round($ttf->x_height),
                'FontBBox' => '[' . round($ttf->bbox[0]) . ' ' . round($ttf->bbox[1]) . ' ' . round($ttf->bbox[2]) . ' ' . round($ttf->bbox[3]) . ']',
                /* FontBBox from head table */
                /* 		'MaxWidth' => round($ttf->advanceWidthMax),	// AdvanceWidthMax from hhea table	NB ArialUnicode MS = 31990 ! */
                'Flags' => $ttf->flags,
                'Ascent' => round($ttf->ascent),
                'Descent' => round($ttf->descent),
                'Leading' => round($ttf->line_gap),
                'ItalicAngle' => $ttf->italic_angle,
                'StemV' => round($ttf->stem_v),
                'MissingWidth' => round($ttf->default_width),
            ],
            'unitsPerEm' => round($ttf->units_per_em),
            'up' => round($ttf->underline_position),
            'ut' => round($ttf->underline_thickness),
            'strp' => round($ttf->strikeout_position),
            'strs' => round($ttf->strikeout_size),
            'ttffile' => $ttffile,
            'TTCfontID' => $tt_cfont_id,
            'originalsize' => $ttfstat['size'] + 0,
            /* cast ? */
            'sip' => $ttf->sipset ? true : false,
            'smp' => $ttf->smpset ? true : false,
            'BMPselected' => $bm_ponly ? true : false,
            'fontkey' => $fontkey,
            'panose' => $this->get_panose($ttf),
            'haskerninfo' => $ttf->kerninfo ? true : false,
            'haskernGPOS' => $ttf->haskern_gpos ? true : false,
            'hassmallcapsGSUB' => $ttf->hassmallcaps_gsub ? true : false,
            'fontmetrics' => $this->font_descriptor,
            'useOTL' => $font_use_otl ?: 0,
            'rtlPUAstr' => $ttf->rtl_pu_astr,
            'GSUBScriptLang' => $ttf->gsub_script_lang,
            'GSUBFeatures' => $ttf->gsub_features,
            'GSUBLookups' => $ttf->gsub_lookups,
            'GPOSScriptLang' => $ttf->gpos_script_lang,
            'GPOSFeatures' => $ttf->gpos_features,
            'GPOSLookups' => $ttf->gpos_lookups,
            'kerninfo' => $ttf->kerninfo,
        ];
        $this->font_cache->json_write($fontkey . '.mtx.json', $font);
        $this->font_cache->binary_write($fontkey . '.cw.dat', $ttf->char_widths);
        $this->font_cache->binary_write($fontkey . '.gid.dat', $ttf->glyph_i_dto_uni);
        if ($this->font_cache->has($fontkey . '.cgm')) {
            $this->font_cache->remove($fontkey . '.cgm');
        }
        if ($this->font_cache->has($fontkey . '.z')) {
            $this->font_cache->remove($fontkey . '.z');
        }
        if ($this->font_cache->json_has($fontkey . '.cw127.json')) {
            $this->font_cache->json_remove($fontkey . '.cw127.json');
        }
        if ($this->font_cache->has($fontkey . '.cw')) {
            $this->font_cache->remove($fontkey . '.cw');
        }
        unset($ttf);
    }
    protected function get_font_name($full_name)
    {
        return preg_replace('/[ ()]/', '', $full_name);
    }
    protected function get_panose($ttf)
    {
        $panose = '';
        if (count($ttf->panose)) {
            $panose_array = array_merge([$ttf->s_family_class, $ttf->s_family_sub_class], $ttf->panose);
            foreach ($panose_array as $value) {
                $panose .= ' ' . dechex($value);
            }
        }
        return $panose;
    }
}