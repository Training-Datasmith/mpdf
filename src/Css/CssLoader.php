<?php

declare (strict_types=1);
namespace Mpdf\Css;

use Mpdf\Asset_Fetcher;
use Mpdf\Cache;
use Mpdf\Exception\Asset_Fetching_Exception;
use Mpdf\Mpdf;
use Mpdf\Mpdf_Exception;
use Mpdf\Utils\Path;
class Css_Loader
{
    /**
     * @var Mpdf
     */
    private $mpdf;
    /**
     * @var AssetFetcher
     */
    private $asset_fetcher;
    /**
     * @var Cache
     */
    private $cache;
    public function __construct(Mpdf $mpdf, Asset_Fetcher $asset_fetcher, Cache $cache)
    {
        $this->mpdf = $mpdf;
        $this->asset_fetcher = $asset_fetcher;
        $this->cache = $cache;
    }
    /**
     * Fetch and return the CSS from $path
     *
     * @param string $path
     * @return string
     * @throws MpdfException If asset fetching issue, is through when $mpdf->debug = true
     */
    public function load_stylesheet($path)
    {
        $path = preg_replace('/\.css\?.*$/', '.css', $path);
        try {
            $data = $this->asset_fetcher->fetch_data_from_path($path);
            if (!$data) {
                $path = !$this->mpdf->basepath_is_local ? Path::normalize_local_file_path($path) : $path;
                $data = $this->asset_fetcher->fetch_data_from_path($path);
            }
        } catch (Asset_Fetching_Exception $e) {
            $data = '';
            // do nothing
            if ($this->mpdf->debug) {
                throw new Mpdf_Exception($e->get_message(), 0, E_ERROR, null, null, $e);
            }
        }
        return $data;
    }
    /**
     * Extract external stylesheet URLs from HTML.
     *
     * Finds all external CSS file references including:
     * - <link rel="stylesheet" href="...">
     * - <link href="..." rel="stylesheet">
     * - @import url(...)
     * - @import "..."
     *
     * @param string $html HTML content to scan
     * @return array Array of CSS file URLs
     */
    public function extract_external_stylesheet_urls($html)
    {
        $css_urls = [];
        // <link rel="stylesheet" href="...">
        if (preg_match_all('/<link[^>]*rel=["\']stylesheet["\'][^>]*href=["\']([^>"\']*)["\'].*?>/si', $html, $cxt)) {
            $css_urls = $cxt[1];
        }
        // <link href="..." rel="stylesheet">
        if (preg_match_all('/<link[^>]*href=["\']([^>"\']*)["\'][^>]*?rel=["\']stylesheet["\'].*?>/si', $html, $cxt)) {
            $css_urls = array_merge($css_urls, $cxt[1]);
        }
        // @import url(...)
        if (preg_match_all('/@import url\([\'\"]{0,1}(\S*?\.css(\?[^\s\'\"]+)?)[\'\"]{0,1}\)\;?/si', $html, $cxt)) {
            $css_urls = array_merge($css_urls, $cxt[1]);
        }
        // @import "..."
        if (preg_match_all('/@import (?!url)[\'\"]{0,1}(\S*?\.css(\?[^\s\'\"]+)?)[\'\"]{0,1}\;?/si', $html, $cxt)) {
            return array_merge($css_urls, $cxt[1]);
        }
        return $css_urls;
    }
    /**
     * Locate embedded @import stylesheets in other stylesheets and fix url paths
     * (including background-images) relative to stylesheet
     *
     * @param string $stylesheetCss
     * @param string $path
     * @param array $externalCss
     * @param int $externalCssCount
     * @return string
     */
    public function process_external_css_imports($stylesheet_css, $path, &$external_css, &$external_css_count)
    {
        $css = '';
        $css_base_path = preg_replace('/\/[^\/]*$/', '', $path) . '/';
        if (preg_match_all('/@import url\([\'\"]{0,1}(.*?\.css(\?\S+)?)[\'\"]{0,1}\)/si', $stylesheet_css, $cxtem)) {
            foreach ($cxtem[1] as $cxtembedded) {
                // path is relative to original stylesheet!!
                $external_css[] = Path::relative_to_absolute_path($cxtembedded, $css_base_path);
                $external_css_count++;
            }
        }
        return $css . (' ' . $this->resolve_background_urls($stylesheet_css, $css_base_path));
    }
    /**
     * Resolve background image URLs in CSS.
     *
     * Converts relative URLs to absolute paths using Path::relativeToAbsolute.
     * Skips data URIs which are already absolute.
     *
     * @param string $cssStr CSS string potentially containing background URLs
     * @param string|null $basePath Optional base path for resolving relative URLs
     * @return string CSS string with resolved URLs
     */
    public function resolve_background_urls($css_str, $base_path = null)
    {
        if (!preg_match_all('/(background[^;]*url\s*\(\s*[\'"]{0,1})([^)\'"]*)([\'"]{0,1}\s*\))/si', $css_str, $cxtem)) {
            return $css_str;
        }
        $base_path = $base_path ?: $this->mpdf->basepath;
        foreach ($cxtem[0] as $i => $value) {
            $embedded = $cxtem[2][$i];
            if (!preg_match('/^data:image/i', $embedded)) {
                $new_path = Path::relative_to_absolute_path($embedded, $base_path);
                $css_str = str_replace($cxtem[0][$i], $cxtem[1][$i] . $new_path . $cxtem[3][$i], $css_str);
            }
        }
        return $css_str;
    }
    /**
     * Process data URI images in CSS.
     *
     * Converts data URI images to temporary files for processing.
     * Example: url(data:image/png;base64,...) becomes url("tempfile.png")
     *
     * @param string $cssStr CSS string potentially containing data URIs
     * @return string CSS string with data URIs replaced by temp file references
     * @throws \Random\RandomException
     */
    public function process_data_uri_images($css_str)
    {
        preg_match_all("/(url\\(data:image\\/(jpeg|gif|png);base64,(.*?)\\))/si", $css_str, $idata);
        if (count($idata[0]) === 0) {
            return $css_str;
        }
        foreach ($idata[0] as $i => $value) {
            $file = $this->cache->write('_tempCSSidata' . random_int(1, 10000) . '_' . $i . '.' . $idata[2][$i], base64_decode($idata[3][$i]));
            $css_str = str_replace($idata[0][$i], 'url("' . $file . '")', $css_str);
        }
        return $css_str;
    }
}