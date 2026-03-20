<?php

declare (strict_types=1);
namespace Mpdf\Css;

use Mpdf\Mpdf;
class Media_Query_Processor
{
    /**
     * @var \Mpdf\Mpdf
     */
    private $mpdf;
    public function __construct(Mpdf $mpdf)
    {
        $this->mpdf = $mpdf;
    }
    /**
     * Filter HTML elements by media query.
     *
     * Removes elements (style or link tags) that don't match the configured media type.
     *
     * @param string $html HTML content to filter
     * @param string $pattern Regex pattern to match elements
     * @return string Filtered HTML
     */
    public function filter_by_media_query($html, $pattern)
    {
        preg_match_all($pattern, $html, $m);
        foreach ($m[0] as $i => $url) {
            if (!$this->mpdf->cs_sselect_media || !preg_match('/(' . trim($this->mpdf->cs_sselect_media) . '|all)/i', $m[1][$i])) {
                $html = str_replace($m[0][$i], '', $html);
            }
        }
        return $html;
    }
    /**
     * Process @media queries in CSS.
     *
     * Filters or unwraps @media blocks based on configured media type.
     * If media doesn't match CSSselectMedia, the entire block is removed.
     * If it matches, the contents are unwrapped.
     *
     * @param string $cssStr CSS string potentially containing @media rules
     * @return string CSS string with media queries processed
     */
    public function process_media_queries($css_str)
    {
        if (!preg_match('/@media/', $css_str)) {
            return $css_str;
        }
        preg_match_all('/@media(.*?)\{(([^\{\}]*\{[^\{\}]*\})+)\s*\}/is', $css_str, $m);
        foreach ($m[0] as $i => $value) {
            if ($this->mpdf->cs_sselect_media && !preg_match('/(' . trim($this->mpdf->cs_sselect_media) . '|all)/i', $m[1][$i])) {
                $css_str = str_replace($m[0][$i], '', $css_str);
            } else {
                $css_str = str_replace($m[0][$i], ' ' . $m[2][$i] . ' ', $css_str);
            }
        }
        return $css_str;
    }
}