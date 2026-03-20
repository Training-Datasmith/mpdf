<?php

declare (strict_types=1);
namespace Mpdf\Image;

use Mpdf\Asset_Fetcher;
use Mpdf\Cache;
use Mpdf\Color\Color_Converter;
use Mpdf\Color\Color_Mode_Converter;
use Mpdf\Css_Manager;
use Mpdf\Gif\Gif;
use Mpdf\Language\Language_To_Font_Interface;
use Mpdf\Language\Script_To_Language_Interface;
use Mpdf\Log\Context as LogContext;
use Mpdf\Mpdf;
use Mpdf\Otl;
use Mpdf\Psr_Log_Aware_Trait\Psr_Log_Aware_Trait;
use Mpdf\Size_Converter;
use Psr\Log\Logger_Interface;
class Image_Processor implements \Psr\Log\Logger_Aware_Interface
{
    use Psr_Log_Aware_Trait;
    /**
     * @var \Mpdf\Mpdf
     */
    private $mpdf;
    /**
     * @var \Mpdf\Otl
     */
    private $otl;
    /**
     * @var \Mpdf\CssManager
     */
    private $css_manager;
    /**
     * @var \Mpdf\SizeConverter
     */
    private $size_converter;
    /**
     * @var \Mpdf\Color\ColorConverter
     */
    private $color_converter;
    /**
     * @var \Mpdf\Color\ColorModeConverter
     */
    private $color_mode_converter;
    /**
     * @var \Mpdf\Cache
     */
    private $cache;
    /**
     * @var \Mpdf\Image\ImageTypeGuesser
     */
    private $guesser;
    /**
     * @var string[]
     */
    private $failed_images;
    /**
     * @var \Mpdf\Image\Bmp
     */
    private $bmp;
    /**
     * @var \Mpdf\Image\Wmf
     */
    private $wmf;
    /**
     * @var \Mpdf\Language\LanguageToFontInterface
     */
    private $language_to_font;
    /**
     * @var \Mpdf\Language\ScriptToLanguageInterface
     */
    public $script_to_language;
    /**
     * @var \Mpdf\AssetFetcher
     */
    private $asset_fetcher;
    public function __construct(Mpdf $mpdf, Otl $otl, Css_Manager $css_manager, Size_Converter $size_converter, Color_Converter $color_converter, Color_Mode_Converter $color_mode_converter, Cache $cache, Language_To_Font_Interface $language_to_font, Script_To_Language_Interface $script_to_language, Asset_Fetcher $asset_fetcher, Logger_Interface $logger)
    {
        $this->mpdf = $mpdf;
        $this->otl = $otl;
        $this->css_manager = $css_manager;
        $this->size_converter = $size_converter;
        $this->color_converter = $color_converter;
        $this->color_mode_converter = $color_mode_converter;
        $this->cache = $cache;
        $this->language_to_font = $language_to_font;
        $this->script_to_language = $script_to_language;
        $this->asset_fetcher = $asset_fetcher;
        $this->logger = $logger;
        $this->guesser = new Image_Type_Guesser();
        $this->failed_images = [];
    }
    public function get_image(&$file, $first_time = true, $allowvector = true, $orig_srcpath = false, $interpolation = false)
    {
        // mPDF 6
        // firsttime i.e. whether to add to this->images - use false when calling iteratively
        // Image Data passed directly as var:varname
        $type = null;
        $data = '';
        if (preg_match('/var:\s*(.*)/', $file, $v)) {
            if (!isset($this->mpdf->image_vars[$v[1]])) {
                return $this->image_error($file, $first_time, 'Unknown image variable');
            }
            $data = $this->mpdf->image_vars[$v[1]];
            $file = md5($data);
        }
        if (preg_match('/data:image\/(gif|jpe?g|png|webp|svg\+xml);base64,(.*)/', $file, $v)) {
            $type = $v[1];
            $data = base64_decode($v[2]);
            $file = md5($data);
        }
        // mPDF 5.7.4 URLs
        if ($first_time && $file && strpos($file, 'data:') !== 0) {
            $file = str_replace(' ', '%20', $file);
        }
        if ($first_time && $orig_srcpath) {
            // If orig_srcpath is a relative file path (and not a URL), then it needs to be URL decoded
            if (strpos($orig_srcpath, 'data:') !== 0) {
                $orig_srcpath = str_replace(' ', '%20', $orig_srcpath);
            }
            if (!preg_match('/^(http|ftp)/', $orig_srcpath)) {
                $orig_srcpath = $this->urldecode_parts($orig_srcpath);
            }
        }
        if ($orig_srcpath && isset($this->mpdf->images[$orig_srcpath])) {
            $file = $orig_srcpath;
            return $this->mpdf->images[$orig_srcpath];
        }
        if (isset($this->mpdf->images[$file])) {
            return $this->mpdf->images[$file];
        }
        if ($orig_srcpath && isset($this->mpdf->formobjects[$orig_srcpath])) {
            $file = $orig_srcpath;
            return $this->mpdf->formobjects[$file];
        }
        if (isset($this->mpdf->formobjects[$file])) {
            return $this->mpdf->formobjects[$file];
        }
        if ($first_time && isset($this->failed_images[$file])) {
            // Save re-trying image URL's which have already failed
            return $this->image_error($file, $first_time, '');
        }
        if (!$data) {
            try {
                $data = $this->asset_fetcher->fetch_data_from_path($file, $orig_srcpath);
            } catch (\Mpdf\Exception\Asset_Fetching_Exception $e) {
                return $this->image_error($orig_srcpath, $first_time, $e->get_message());
            }
        }
        if (!$data) {
            return $this->image_error($file, $first_time, 'Could not find image file');
        }
        if ($type === null) {
            $type = $this->guesser->guess($data);
        }
        if ($type === 'svg' || $type === 'svg+xml') {
            if (!$allowvector) {
                return $this->image_error($file, $first_time, 'SVG image file not supported in this context');
            }
            return $this->process_svg($data, $file, $first_time);
        }
        if ($type === 'wmf') {
            if (!$allowvector) {
                return $this->image_error($file, $first_time, 'WMF image file not supported in this context');
            }
            return $this->process_wmf($data, $file, $first_time);
        }
        if ($type === 'webp') {
            // Convert webp images to JPG and treat them as such
            $data = $this->process_webp($data, $file, $first_time);
            $type = 'jpeg';
        }
        if ($type === 'avif') {
            // Convert avif images to JPG and treat them as such
            $data = $this->process_avif($data, $file, $first_time);
            $type = 'jpeg';
        }
        // JPEG
        if ($type === 'jpeg' || $type === 'jpg') {
            return $this->process_jpg($data, $file, $first_time, $interpolation);
        }
        if ($type === 'png') {
            return $this->process_png($data, $file, $first_time, $interpolation);
        }
        if ($type === 'gif') {
            // GIF
            return $this->process_gif($data, $file, $first_time, $interpolation);
        }
        if ($type === 'bmp') {
            return $this->process_bmp($data, $file, $first_time, $interpolation);
        }
        return $this->process_unknown_type($data, $file, $first_time, $interpolation);
    }
    private function convert_image(&$data, $colspace, $targetcs, $w, $h, $dpi, $mask, $gamma_correction = false, $pngcolortype = false)
    {
        if (!function_exists('gd_info')) {
            return $this->image_error('', false, 'GD library needed to parse image files');
        }
        if ($this->mpdf->PDFA || $this->mpdf->PDFX) {
            $mask = false;
        }
        $im = @imagecreatefromstring($data);
        $info = [];
        $bpc = ord(substr($data, 24, 1));
        if ($im) {
            $imgdata = '';
            $mimgdata = '';
            $minfo = [];
            // mPDF 6 Gamma correction
            // Need to extract alpha channel info before imagegammacorrect (which loses the data)
            if ($mask) {
                // i.e. $pngalpha for PNG
                // mPDF 6
                if ($colspace === 'Indexed') {
                    // generate Alpha channel values from tRNS - only from PNG
                    //Read transparency info
                    $transparency = '';
                    $p = strpos($data, 'tRNS');
                    if ($p) {
                        $n = $this->four_bytes_to_int(substr($data, $p - 4, 4));
                        $transparency = substr($data, $p + 4, $n);
                        // ord($transparency[$index]) = the alpha value for that index
                        // generate alpha channel
                        for ($ypx = 0; $ypx < $h; ++$ypx) {
                            for ($xpx = 0; $xpx < $w; ++$xpx) {
                                $colorindex = imagecolorat($im, $xpx, $ypx);
                                if ($colorindex >= $n) {
                                    $alpha = 255;
                                } else {
                                    $alpha = ord($transparency[$colorindex]);
                                }
                                // 0-255
                                $mimgdata .= chr($alpha);
                            }
                        }
                    }
                } elseif ($pngcolortype === 0 || $pngcolortype === 2) {
                    // generate Alpha channel values from tRNS
                    // Get transparency as array of RGB
                    $p = strpos($data, 'tRNS');
                    if ($p) {
                        $trns = '';
                        $n = $this->four_bytes_to_int(substr($data, $p - 4, 4));
                        $t = substr($data, $p + 4, $n);
                        if ($colspace === 'DeviceGray') {
                            // ct===0
                            $trns = [$this->translate_value(substr($t, 0, 2), $bpc)];
                        } else {
                            /* $colspace=='DeviceRGB' */
                            // ct==2
                            $trns = [];
                            $trns[0] = $this->translate_value(substr($t, 0, 2), $bpc);
                            $trns[1] = $this->translate_value(substr($t, 2, 2), $bpc);
                            $trns[2] = $this->translate_value(substr($t, 4, 2), $bpc);
                        }
                        // generate alpha channel
                        for ($ypx = 0; $ypx < $h; ++$ypx) {
                            for ($xpx = 0; $xpx < $w; ++$xpx) {
                                $rgb = imagecolorat($im, $xpx, $ypx);
                                $r = $rgb >> 16 & 0xff;
                                $g = $rgb >> 8 & 0xff;
                                $b = $rgb & 0xff;
                                if ($colspace === 'DeviceGray' && $b == $trns[0]) {
                                    $alpha = 0;
                                } elseif ($r == $trns[0] && $g == $trns[1] && $b == $trns[2]) {
                                    $alpha = 0;
                                } else {
                                    $alpha = 255;
                                }
                                $mimgdata .= chr($alpha);
                            }
                        }
                    }
                } else {
                    for ($i = 0; $i < $h; $i++) {
                        for ($j = 0; $j < $w; $j++) {
                            $rgb = imagecolorat($im, $j, $i);
                            $alpha = ($rgb & 0x7f000000) >> 24;
                            if ($alpha < 127) {
                                $mimgdata .= chr(255 - $alpha * 2);
                            } else {
                                $mimgdata .= chr(0);
                            }
                        }
                    }
                }
            }
            // mPDF 6 Gamma correction
            if ($gamma_correction) {
                imagegammacorrect($im, $gamma_correction, 2.2);
            }
            // Read transparency info
            $trns = [];
            $trnsrgb = false;
            if (!$this->mpdf->PDFA && !$this->mpdf->PDFX && !$mask) {
                // mPDF 6 added NOT mask
                $p = strpos($data, 'tRNS');
                if ($p) {
                    $n = $this->four_bytes_to_int(substr($data, $p - 4, 4));
                    $t = substr($data, $p + 4, $n);
                    if ($colspace === 'DeviceGray') {
                        // ct===0
                        $trns = [$this->translate_value(substr($t, 0, 2), $bpc)];
                    } elseif ($colspace === 'DeviceRGB') {
                        // ct==2
                        $trns[0] = $this->translate_value(substr($t, 0, 2), $bpc);
                        $trns[1] = $this->translate_value(substr($t, 2, 2), $bpc);
                        $trns[2] = $this->translate_value(substr($t, 4, 2), $bpc);
                        $trnsrgb = $trns;
                        if ($targetcs === 'DeviceCMYK') {
                            $col = $this->color_mode_converter->rgb2cmyk([3, $trns[0], $trns[1], $trns[2]]);
                            $c1 = (int) ($col[1] * 2.55);
                            $c2 = (int) ($col[2] * 2.55);
                            $c3 = (int) ($col[3] * 2.55);
                            $c4 = (int) ($col[4] * 2.55);
                            $trns = [$c1, $c2, $c3, $c4];
                        } elseif ($targetcs === 'DeviceGray') {
                            $c = (int) ($trns[0] * 0.21 + $trns[1] * 0.71 + $trns[2] * 0.07000000000000001);
                            $trns = [$c];
                        }
                    } else {
                        // Indexed
                        $pos = strpos($t, chr(0));
                        if (is_int($pos)) {
                            $pal = imagecolorsforindex($im, $pos);
                            $r = $pal['red'];
                            $g = $pal['green'];
                            $b = $pal['blue'];
                            $trns = [$r, $g, $b];
                            // ****
                            $trnsrgb = $trns;
                            if ($targetcs === 'DeviceCMYK') {
                                $col = $this->color_mode_converter->rgb2cmyk([3, $r, $g, $b]);
                                $c1 = (int) ($col[1] * 2.55);
                                $c2 = (int) ($col[2] * 2.55);
                                $c3 = (int) ($col[3] * 2.55);
                                $c4 = (int) ($col[4] * 2.55);
                                $trns = [$c1, $c2, $c3, $c4];
                            } elseif ($targetcs === 'DeviceGray') {
                                $c = (int) ($r * 0.21 + $g * 0.71 + $b * 0.07000000000000001);
                                $trns = [$c];
                            }
                        }
                    }
                }
            }
            for ($i = 0; $i < $h; $i++) {
                for ($j = 0; $j < $w; $j++) {
                    $rgb = imagecolorat($im, $j, $i);
                    $r = $rgb >> 16 & 0xff;
                    $g = $rgb >> 8 & 0xff;
                    $b = $rgb & 0xff;
                    if ($colspace === 'Indexed') {
                        $pal = imagecolorsforindex($im, $rgb);
                        $r = $pal['red'];
                        $g = $pal['green'];
                        $b = $pal['blue'];
                    }
                    if ($targetcs === 'DeviceCMYK') {
                        $col = $this->color_mode_converter->rgb2cmyk([3, $r, $g, $b]);
                        $c1 = (int) ($col[1] * 2.55);
                        $c2 = (int) ($col[2] * 2.55);
                        $c3 = (int) ($col[3] * 2.55);
                        $c4 = (int) ($col[4] * 2.55);
                        if ($trnsrgb) {
                            // original pixel was not set as transparent but processed color does match
                            if ($trnsrgb !== [$r, $g, $b] && $trns === [$c1, $c2, $c3, $c4]) {
                                if ($c4 === 0) {
                                    $c4 = 1;
                                } else {
                                    $c4--;
                                }
                            }
                        }
                        $imgdata .= chr($c1) . chr($c2) . chr($c3) . chr($c4);
                    } elseif ($targetcs === 'DeviceGray') {
                        $c = (int) ($r * 0.21 + $g * 0.71 + $b * 0.07000000000000001);
                        if ($trnsrgb) {
                            // original pixel was not set as transparent but processed color does match
                            if ($trnsrgb !== [$r, $g, $b] && $trns === [$c]) {
                                if ($c === 0) {
                                    $c = 1;
                                } else {
                                    $c--;
                                }
                            }
                        }
                        $imgdata .= chr($c);
                    } elseif ($targetcs === 'DeviceRGB') {
                        $imgdata .= chr($r) . chr($g) . chr($b);
                    }
                }
            }
            if ($targetcs === 'DeviceGray') {
                $ncols = 1;
            } elseif ($targetcs === 'DeviceRGB') {
                $ncols = 3;
            } elseif ($targetcs === 'DeviceCMYK') {
                $ncols = 4;
            }
            $imgdata = $this->gz_compress($imgdata);
            $info = ['w' => $w, 'h' => $h, 'cs' => $targetcs, 'bpc' => 8, 'f' => 'FlateDecode', 'data' => $imgdata, 'type' => 'png', 'parms' => '/DecodeParms <</Colors ' . $ncols . ' /BitsPerComponent 8 /Columns ' . $w . '>>'];
            if ($dpi) {
                $info['set-dpi'] = $dpi;
            }
            if ($mask) {
                $mimgdata = $this->gz_compress($mimgdata);
                $minfo = ['w' => $w, 'h' => $h, 'cs' => 'DeviceGray', 'bpc' => 8, 'f' => 'FlateDecode', 'data' => $mimgdata, 'type' => 'png', 'parms' => '/DecodeParms <</Colors ' . $ncols . ' /BitsPerComponent 8 /Columns ' . $w . '>>'];
                if ($dpi) {
                    $minfo['set-dpi'] = $dpi;
                }
                $tempfile = '_tempImgPNG' . md5($data) . random_int(1, 10000) . '.png';
                $imgmask = count($this->mpdf->images) + 1;
                $minfo['i'] = $imgmask;
                $this->mpdf->images[$tempfile] = $minfo;
                $info['masked'] = $imgmask;
            } elseif ($trns) {
                $info['trns'] = $trns;
            }
            $this->destroy_image($im);
        }
        return $info;
    }
    private function jpg_header_from_string(&$data)
    {
        $p = 4;
        $p += $this->two_bytes_to_int(substr($data, $p, 2));
        // Length of initial marker block
        $marker = substr($data, $p, 2);
        while ($marker !== chr(255) . chr(192) && $marker !== chr(255) . chr(194) && $marker !== chr(255) . chr(193) && $p < strlen($data)) {
            // Start of frame marker (FFC0) (FFC1) or (FFC2)
            $p += $this->two_bytes_to_int(substr($data, $p + 2, 2)) + 2;
            // Length of marker block
            $marker = substr($data, $p, 2);
        }
        if ($marker !== chr(255) . chr(192) && $marker !== chr(255) . chr(194) && $marker !== chr(255) . chr(193)) {
            return false;
        }
        return substr($data, $p + 2, 10);
    }
    private function jpg_data_from_header($hdr)
    {
        $bpc = ord(substr($hdr, 2, 1));
        if (!$bpc) {
            $bpc = 8;
        }
        $h = $this->two_bytes_to_int(substr($hdr, 3, 2));
        $w = $this->two_bytes_to_int(substr($hdr, 5, 2));
        $channels = ord(substr($hdr, 7, 1));
        if ($channels === 3) {
            $colspace = 'DeviceRGB';
        } elseif ($channels === 4) {
            $colspace = 'DeviceCMYK';
        } else {
            $colspace = 'DeviceGray';
        }
        return [$w, $h, $colspace, $bpc, $channels];
    }
    /**
     * Corrects 2-byte integer to 8-bit depth value
     * If original image is bpc != 8, tRNS will be in this bpc
     * $im from imagecreatefromstring will always be in bpc=8
     * So why do we only need to correct 16-bit tRNS and NOT 2 or 4-bit???
     */
    private function translate_value($s, $bpc)
    {
        $n = $this->two_bytes_to_int($s);
        if ($bpc == 16) {
            return $n >> 8;
        }
        //elseif ($bpc==4) { $n = ($n << 2); }
        //elseif ($bpc==2) { $n = ($n << 4); }
        return $n;
    }
    /**
     * Read a 4-byte integer from string
     */
    private function four_bytes_to_int($s)
    {
        return (ord($s[0]) << 24) + (ord($s[1]) << 16) + (ord($s[2]) << 8) + ord($s[3]);
    }
    /**
     * Equivalent to _get_ushort
     * Read a 2-byte integer from string
     */
    private function two_bytes_to_int($s)
    {
        return (ord(substr($s, 0, 1)) << 8) + ord(substr($s, 1, 1));
    }
    private function gz_compress($data)
    {
        if (!function_exists('gzcompress')) {
            throw new \Mpdf\Mpdf_Exception('gzcompress is not available. install ext-zlib extension.');
        }
        return gzcompress($data);
    }
    /**
     * Throw an exception and save re-trying image URL's which have already failed
     */
    private function image_error($file, $first_time, $msg)
    {
        $this->failed_images[$file] = true;
        if ($first_time && ($this->mpdf->show_image_errors || $this->mpdf->debug)) {
            throw new \Mpdf\Mpdf_Image_Exception(sprintf('%s (%s)', $msg, substr($file, 0, 256)));
        }
        $this->logger->warning(sprintf('%s (%s)', $msg, $file), ['context' => Log_Context::IMAGES]);
    }
    /**
     * @since mPDF 5.7.4
     * @param string $url
     * @return string
     */
    private function urldecode_parts($url)
    {
        $file = $url;
        $query = '';
        if (preg_match('/[?]/', $url)) {
            $bits = preg_split('/[?]/', $url, 2);
            $file = $bits[0];
            $query = '?' . $bits[1];
        }
        $file = rawurldecode($file);
        $query = urldecode($query);
        return $file . $query;
    }
    public function process_jpg($data, $file, $first_time, $interpolation)
    {
        $pp_ux = 0;
        $hdr = $this->jpg_header_from_string($data);
        if (!$hdr) {
            return $this->image_error($file, $first_time, 'Error parsing JPG header');
        }
        $a = $this->jpg_data_from_header($hdr);
        $channels = (int) $a[4];
        $j = strpos($data, 'JFIF');
        if ($j) {
            // Read resolution
            $unit_sp = ord(substr($data, $j + 7, 1));
            if ($unit_sp > 0) {
                $pp_ux = $this->two_bytes_to_int(substr($data, $j + 8, 2));
                // horizontal pixels per meter, usually set to zero
                if ($unit_sp === 2) {
                    // = dots per cm (if == 1 set as dpi)
                    $pp_ux = round($pp_ux / 10 * 25.4);
                }
            }
        }
        if ($a[2] === 'DeviceCMYK' && ($this->mpdf->restrict_color_space === 2 || $this->mpdf->PDFA && $this->mpdf->restrict_color_space !== 3)) {
            // convert to RGB image
            if (!function_exists('gd_info')) {
                throw new \Mpdf\Mpdf_Exception(sprintf('JPG image may not use CMYK color space (%s).', $file));
            }
            if ($this->mpdf->PDFA && !$this->mpdf->pdf_aauto) {
                $this->mpdf->pdfa_xwarnings[] = sprintf('JPG image "%s" may not use CMYK color space. Image converted to RGB. The colour profile was altered', $file);
            }
            $im = @imagecreatefromstring($data);
            if ($im) {
                $tempfile = $this->cache->temp_filename('_tempImgPNG' . md5($file) . random_int(1, 10000) . '.png');
                imageinterlace($im, false);
                $check = @imagepng($im, $tempfile);
                if (!$check) {
                    return $this->image_error($file, $first_time, sprintf('Error creating temporary file "%s" when using GD library to parse JPG (CMYK) image', $tempfile));
                }
                // $info = $this->getImage($tempfile, false);
                $data = file_get_contents($tempfile);
                $info = $this->process_png($data, $tempfile, false, $interpolation);
                if (!$info) {
                    return $this->image_error($file, $first_time, sprintf('Error parsing temporary file "%s" created with GD library to parse JPG (CMYK) image', $tempfile));
                }
                $this->destroy_image($im);
                unlink($tempfile);
                $info['type'] = 'jpg';
                if ($first_time) {
                    $info['i'] = count($this->mpdf->images) + 1;
                    $info['interpolation'] = $interpolation;
                    // mPDF 6
                    $this->mpdf->images[$file] = $info;
                }
                return $info;
            }
            return $this->image_error($file, $first_time, 'Error creating GD image file from JPG(CMYK) image');
        }
        if ($a[2] === 'DeviceRGB' && ($this->mpdf->PDFX || $this->mpdf->restrict_color_space === 3)) {
            // Convert to CMYK image stream - nominally returned as type='png'
            $info = $this->convert_image($data, $a[2], 'DeviceCMYK', $a[0], $a[1], $pp_ux, false);
            if ($this->mpdf->PDFA && !$this->mpdf->pdf_aauto || $this->mpdf->PDFX && !$this->mpdf->pdf_xauto) {
                $this->mpdf->pdfa_xwarnings[] = sprintf('JPG image may not use RGB color space - %s - (Image converted to CMYK. NB This will alter the colour profile of the image.)', $file);
            }
        } elseif (($a[2] === 'DeviceRGB' || $a[2] === 'DeviceCMYK') && $this->mpdf->restrict_color_space === 1) {
            // Convert to Grayscale image stream - nominally returned as type='png'
            $info = $this->convert_image($data, $a[2], 'DeviceGray', $a[0], $a[1], $pp_ux, false);
        } else {
            // mPDF 6 Detect Adobe APP14 Tag
            //$pos = strpos($data, "\xFF\xEE\x00\x0EAdobe\0");
            //if ($pos !== false) {
            //}
            // mPDF 6 ICC profile
            $offset = 0;
            $icc = [];
            while (($pos = strpos($data, "ICC_PROFILE\x00", $offset)) !== false) {
                // get ICC sequence length
                $length = $this->two_bytes_to_int(substr($data, $pos - 2, 2)) - 16;
                $sn = max(1, ord($data[$pos + 12]));
                $nom = max(1, ord($data[$pos + 13]));
                $icc[$sn - 1] = substr($data, $pos + 14, $length);
                $offset = $pos + 14 + $length;
            }
            // order and compact ICC segments
            if (count($icc) > 0) {
                ksort($icc);
                $icc = implode('', $icc);
                if (substr($icc, 36, 4) !== 'acsp') {
                    // invalid ICC profile
                    $icc = false;
                }
                $input = substr($icc, 16, 4);
                $output = substr($icc, 20, 4);
                // Ignore Color profiles for conversion to other colorspaces e.g. CMYK/Lab
                if ($input !== 'RGB ' || $output !== 'XYZ ') {
                    $icc = false;
                }
            } else {
                $icc = false;
            }
            $info = ['w' => $a[0], 'h' => $a[1], 'cs' => $a[2], 'bpc' => $a[3], 'f' => 'DCTDecode', 'data' => $data, 'type' => 'jpg', 'ch' => $channels, 'icc' => $icc];
            if ($pp_ux) {
                $info['set-dpi'] = $pp_ux;
            }
        }
        if (!$info) {
            return $this->image_error($file, $first_time, 'Error parsing or converting JPG image');
        }
        if ($first_time) {
            $info['i'] = count($this->mpdf->images) + 1;
            $info['interpolation'] = $interpolation;
            // mPDF 6
            $this->mpdf->images[$file] = $info;
        }
        return $info;
    }
    public function process_png($data, $file, $first_time, $interpolation)
    {
        $pp_ux = 0;
        // Check signature
        if (strpos($data, chr(137) . 'PNG' . chr(13) . chr(10) . chr(26) . chr(10)) !== 0) {
            return $this->image_error($file, $first_time, 'Error parsing PNG identifier');
        }
        // Read header chunk
        if (substr($data, 12, 4) !== 'IHDR') {
            return $this->image_error($file, $first_time, 'Incorrect PNG file (no IHDR block found)');
        }
        $w = $this->four_bytes_to_int(substr($data, 16, 4));
        $h = $this->four_bytes_to_int(substr($data, 20, 4));
        $bpc = ord(substr($data, 24, 1));
        $errpng = false;
        $pngalpha = false;
        $channels = 0;
        //	if($bpc>8) { $errpng = 'not 8-bit depth'; }	// mPDF 6 Allow through to be handled as native PNG
        $ct = ord(substr($data, 25, 1));
        if ($ct === 0) {
            $colspace = 'DeviceGray';
            $channels = 1;
        } elseif ($ct === 2) {
            $colspace = 'DeviceRGB';
            $channels = 3;
        } elseif ($ct === 3) {
            $colspace = 'Indexed';
            $channels = 1;
        } elseif ($ct === 4) {
            $colspace = 'DeviceGray';
            $channels = 1;
            $errpng = 'alpha channel';
            $pngalpha = true;
        } else {
            $colspace = 'DeviceRGB';
            $channels = 3;
            $errpng = 'alpha channel';
            $pngalpha = true;
        }
        if ($ct < 4 && strpos($data, 'tRNS') !== false) {
            $errpng = 'transparency';
            $pngalpha = true;
        }
        // mPDF 6
        if ($ct === 3 && strpos($data, 'iCCP') !== false) {
            $errpng = 'indexed plus ICC';
        }
        // mPDF 6
        // $pngalpha is used as a FLAG of any kind of transparency which COULD be tranferred to an alpha channel
        // incl. single-color tarnsparency, depending which type of handling occurs later
        if (ord(substr($data, 26, 1)) !== 0) {
            $errpng = 'compression method';
        }
        // only 0 should be specified
        if (ord(substr($data, 27, 1)) !== 0) {
            $errpng = 'filter method';
        }
        // only 0 should be specified
        if (ord(substr($data, 28, 1)) !== 0) {
            $errpng = 'interlaced file';
        }
        $j = strpos($data, 'pHYs');
        if ($j) {
            //Read resolution
            $unit_sp = ord(substr($data, $j + 12, 1));
            if ($unit_sp === 1) {
                $pp_ux = $this->four_bytes_to_int(substr($data, $j + 4, 4));
                // horizontal pixels per meter, usually set to zero
                $pp_ux = round($pp_ux / 1000 * 25.4);
            }
        }
        // mPDF 6 Gamma correction
        $gamma = 0;
        $g_ama = 0;
        $j = strpos($data, 'gAMA');
        if ($j && strpos($data, 'sRGB') === false) {
            // sRGB colorspace - overrides gAMA
            $g_ama = $this->four_bytes_to_int(substr($data, $j + 4, 4));
            // Gamma value times 100000
            $g_ama /= 100000;
            // http://www.libpng.org/pub/png/spec/1.2/PNG-Encoders.html
            // "If the source file's gamma value is greater than 1.0, it is probably a display system exponent,..."
            // ("..and you should use its reciprocal for the PNG gamma.")
            //if ($gAMA > 1) { $gAMA = 1/$gAMA; }
            // (Some) Applications seem to ignore it... appearing how it was probably intended
            // Test Case - image(s) on http://www.w3.org/TR/CSS21/intro.html  - PNG has gAMA set as 1.45454
            // Probably unintentional as mentioned above and should be 0.45454 which is 1 / 2.2
            // Tested on Windows PC
            // Firefox and Opera display gray as 234 (correct, but looks wrong)
            // IE9 and Safari display gray as 193 (incorrect but looks right)
            // See test different gamma chunks at http://www.libpng.org/pub/png/pngsuite-all-good.html
        }
        if ($g_ama) {
            $gamma = 1 / $g_ama;
        }
        // Don't need to apply gamma correction if == default i.e. 2.2
        if ($gamma > 2.15 && $gamma < 2.25) {
            $gamma = 0;
        }
        // NOT supported at present
        //$j = strpos($data,'sRGB');	// sRGB colorspace - overrides gAMA
        //$j = strpos($data,'cHRM');	// Chromaticity and Whitepoint
        // $firstTime added mPDF 6 so when PNG Grayscale with alpha using resrtictcolorspace to CMYK
        // the alpha channel is sent through as secondtime as Indexed and should not be converted to CMYK
        if ($first_time && ($colspace === 'DeviceRGB' || $colspace === 'Indexed') && ($this->mpdf->PDFX || $this->mpdf->restrict_color_space === 3)) {
            // Convert to CMYK image stream - nominally returned as type='png'
            $info = $this->convert_image($data, $colspace, 'DeviceCMYK', $w, $h, $pp_ux, $pngalpha, $gamma, $ct);
            // mPDF 5.7.2 Gamma correction
            if ($this->mpdf->PDFA && !$this->mpdf->pdf_aauto || $this->mpdf->PDFX && !$this->mpdf->pdf_xauto) {
                $this->mpdf->pdfa_xwarnings[] = sprintf('PNG image may not use RGB color space - %s - (Image converted to CMYK. NB This will alter the colour profile of the image.)', $file);
            }
        } elseif ($first_time && ($colspace === 'DeviceRGB' || $colspace === 'Indexed') && $this->mpdf->restrict_color_space === 1) {
            // $firstTime added mPDF 6 so when PNG Grayscale with alpha using resrtictcolorspace to CMYK
            // the alpha channel is sent through as secondtime as Indexed and should not be converted to CMYK
            // Convert to Grayscale image stream - nominally returned as type='png'
            $info = $this->convert_image($data, $colspace, 'DeviceGray', $w, $h, $pp_ux, $pngalpha, $gamma, $ct);
            // mPDF 5.7.2 Gamma correction
        } elseif (($this->mpdf->PDFA || $this->mpdf->PDFX) && $pngalpha) {
            // Remove alpha channel
            if ($this->mpdf->restrict_color_space === 1) {
                // Grayscale
                $info = $this->convert_image($data, $colspace, 'DeviceGray', $w, $h, $pp_ux, $pngalpha, $gamma, $ct);
                // mPDF 5.7.2 Gamma correction
            } elseif ($this->mpdf->restrict_color_space === 3) {
                // CMYK
                $info = $this->convert_image($data, $colspace, 'DeviceCMYK', $w, $h, $pp_ux, $pngalpha, $gamma, $ct);
                // mPDF 5.7.2 Gamma correction
            } elseif ($this->mpdf->PDFA) {
                // RGB
                $info = $this->convert_image($data, $colspace, 'DeviceRGB', $w, $h, $pp_ux, $pngalpha, $gamma, $ct);
                // mPDF 5.7.2 Gamma correction
            }
            if ($this->mpdf->PDFA && !$this->mpdf->pdf_aauto || $this->mpdf->PDFX && !$this->mpdf->pdf_xauto) {
                $this->mpdf->pdfa_xwarnings[] = sprintf('Transparency (alpha channel) not permitted in PDFA or PDFX files - %s - (Image converted to one without transparency.)', $file);
            }
        } elseif ($first_time && ($errpng || $pngalpha || $gamma)) {
            // mPDF 5.7.2 Gamma correction
            $gd = function_exists('gd_info') ? gd_info() : [];
            if (!isset($gd['PNG Support'])) {
                return $this->image_error($file, $first_time, sprintf('GD library with PNG support required for image (%s)', $errpng));
            }
            $im = @imagecreatefromstring($data);
            if (!$im) {
                return $this->image_error($file, $first_time, sprintf('Error creating GD image from PNG file (%s)', $errpng));
            }
            $w = imagesx($im);
            $h = imagesy($im);
            $tempfile = $this->cache->temp_filename('_tempImgPNG' . md5($file) . bin2hex(random_bytes(6)) . '.png');
            // Alpha channel set (including using tRNS for Paletted images)
            if ($pngalpha) {
                if ($this->mpdf->PDFA) {
                    throw new \Mpdf\Mpdf_Exception(sprintf('PDFA1-b does not permit images with alpha channel transparency (%s).', $file));
                }
                $imgalpha = imagecreate($w, $h);
                // generate gray scale pallete
                for ($c = 0; $c < 256; ++$c) {
                    imagecolorallocate($imgalpha, $c, $c, $c);
                }
                // mPDF 6
                if ($colspace === 'Indexed') {
                    // generate Alpha channel values from tRNS
                    // Read transparency info
                    $p = strpos($data, 'tRNS');
                    if ($p) {
                        $n = $this->four_bytes_to_int(substr($data, $p - 4, 4));
                        $transparency = substr($data, $p + 4, $n);
                        // ord($transparency[$index]) = the alpha value for that index
                        // generate alpha channel
                        for ($ypx = 0; $ypx < $h; ++$ypx) {
                            for ($xpx = 0; $xpx < $w; ++$xpx) {
                                $colorindex = imagecolorat($im, $xpx, $ypx);
                                if ($colorindex >= $n) {
                                    $alpha = 255;
                                } else {
                                    $alpha = ord($transparency[$colorindex]);
                                }
                                // 0-255
                                if ($alpha > 0) {
                                    imagesetpixel($imgalpha, $xpx, $ypx, $alpha);
                                }
                            }
                        }
                    }
                } elseif ($ct === 0 || $ct === 2) {
                    // generate Alpha channel values from tRNS
                    // Get transparency as array of RGB
                    $p = strpos($data, 'tRNS');
                    if ($p) {
                        $trns = '';
                        $n = $this->four_bytes_to_int(substr($data, $p - 4, 4));
                        $t = substr($data, $p + 4, $n);
                        if ($colspace === 'DeviceGray') {
                            // ct===0
                            $trns = [$this->translate_value(substr($t, 0, 2), $bpc)];
                        } else {
                            /* $colspace=='DeviceRGB' */
                            // ct==2
                            $trns = [];
                            $trns[0] = $this->translate_value(substr($t, 0, 2), $bpc);
                            $trns[1] = $this->translate_value(substr($t, 2, 2), $bpc);
                            $trns[2] = $this->translate_value(substr($t, 4, 2), $bpc);
                        }
                        // generate alpha channel
                        for ($ypx = 0; $ypx < $h; ++$ypx) {
                            for ($xpx = 0; $xpx < $w; ++$xpx) {
                                $rgb = imagecolorat($im, $xpx, $ypx);
                                $r = $rgb >> 16 & 0xff;
                                $g = $rgb >> 8 & 0xff;
                                $b = $rgb & 0xff;
                                if ($colspace === 'DeviceGray' && $b == $trns[0]) {
                                    $alpha = 0;
                                } elseif ($r == $trns[0] && $g == $trns[1] && $b == $trns[2]) {
                                    $alpha = 0;
                                } else {
                                    // ct==2
                                    $alpha = 255;
                                }
                                if ($alpha > 0) {
                                    imagesetpixel($imgalpha, $xpx, $ypx, $alpha);
                                }
                            }
                        }
                    }
                } else {
                    // extract alpha channel
                    for ($ypx = 0; $ypx < $h; ++$ypx) {
                        for ($xpx = 0; $xpx < $w; ++$xpx) {
                            $alpha = (imagecolorat($im, $xpx, $ypx) & 0x7f000000) >> 24;
                            if ($alpha < 127) {
                                imagesetpixel($imgalpha, $xpx, $ypx, 255 - $alpha * 2);
                            }
                        }
                    }
                }
                // NB This must happen after the Alpha channel is extracted
                // imagegammacorrect() removes the alpha channel data in $im - (I think this is a bug in PHP)
                if ($gamma) {
                    imagegammacorrect($im, $gamma, 2.2);
                }
                $tempfile_alpha = $this->cache->temp_filename('_tempMskPNG' . md5($file) . random_int(1, 10000) . '.png');
                $check = @imagepng($imgalpha, $tempfile_alpha);
                if (!$check) {
                    return $this->image_error($file, $first_time, 'Failed to create temporary image file (' . $tempfile_alpha . ') parsing PNG image with alpha channel (' . $errpng . ')');
                }
                $this->destroy_image($imgalpha);
                // extract image without alpha channel
                $imgplain = imagecreatetruecolor($w, $h);
                imagealphablending($imgplain, false);
                // mPDF 5.7.2
                imagecopy($imgplain, $im, 0, 0, 0, 0, $w, $h);
                // create temp image file
                $check = @imagepng($imgplain, $tempfile);
                if (!$check) {
                    return $this->image_error($file, $first_time, 'Failed to create temporary image file (' . $tempfile . ') parsing PNG image with alpha channel (' . $errpng . ')');
                }
                $this->destroy_image($imgplain);
                // embed mask image
                //$minfo = $this->getImage($tempfile_alpha, false);
                $data = file_get_contents($tempfile_alpha);
                $minfo = $this->process_png($data, $tempfile_alpha, false, $interpolation);
                unlink($tempfile_alpha);
                if (!$minfo) {
                    return $this->image_error($file, $first_time, 'Error parsing temporary file (' . $tempfile_alpha . ') created with GD library to parse PNG image');
                }
                $imgmask = count($this->mpdf->images) + 1;
                $minfo['cs'] = 'DeviceGray';
                $minfo['i'] = $imgmask;
                $this->mpdf->images[$tempfile_alpha] = $minfo;
                // embed image, masked with previously embedded mask
                // $info = $this->getImage($tempfile, false);
                $data = file_get_contents($tempfile);
                $info = $this->process_png($data, $tempfile, false, $interpolation);
                unlink($tempfile);
                if (!$info) {
                    return $this->image_error($file, $first_time, 'Error parsing temporary file (' . $tempfile . ') created with GD library to parse PNG image');
                }
                $info['masked'] = $imgmask;
                if ($pp_ux) {
                    $info['set-dpi'] = $pp_ux;
                }
                $info['type'] = 'png';
                if ($first_time) {
                    $info['i'] = count($this->mpdf->images) + 1;
                    $info['interpolation'] = $interpolation;
                    // mPDF 6
                    $this->mpdf->images[$file] = $info;
                }
                return $info;
            }
            // No alpha/transparency set (but cannot read directly because e.g. bit-depth != 8, interlaced etc)
            // ICC profile
            $icc = false;
            $p = strpos($data, 'iCCP');
            if ($p && $colspace === 'Indexed') {
                // Cannot have ICC profile and Indexed together
                $p += 4;
                $n = $this->four_bytes_to_int(substr($data, $p - 8, 4));
                $nullsep = strpos(substr($data, $p, 80), chr(0));
                $icc = substr($data, $p + $nullsep + 2, $n - ($nullsep + 2));
                $icc = @gzuncompress($icc);
                // Ignored if fails
                if ($icc) {
                    if (substr($icc, 36, 4) !== 'acsp') {
                        $icc = false;
                    } else {
                        $input = substr($icc, 16, 4);
                        $output = substr($icc, 20, 4);
                        // Ignore Color profiles for conversion to other colorspaces e.g. CMYK/Lab
                        if ($input !== 'RGB ' || $output !== 'XYZ ') {
                            $icc = false;
                        }
                    }
                }
                // Convert to RGB colorspace so can use ICC Profile
                if ($icc) {
                    imagepalettetotruecolor($im);
                    $colspace = 'DeviceRGB';
                    $channels = 3;
                }
            }
            if ($gamma) {
                imagegammacorrect($im, $gamma, 2.2);
            }
            imagealphablending($im, false);
            imagesavealpha($im, false);
            imageinterlace($im, false);
            $check = @imagepng($im, $tempfile);
            if (!$check) {
                return $this->image_error($file, $first_time, 'Failed to create temporary image file (' . $tempfile . ') parsing PNG image (' . $errpng . ')');
            }
            $this->destroy_image($im);
            // $info = $this->getImage($tempfile, false);
            $data = file_get_contents($tempfile);
            $info = $this->process_png($data, $tempfile, false, $interpolation);
            unlink($tempfile);
            if (!$info) {
                return $this->image_error($file, $first_time, 'Error parsing temporary file (' . $tempfile . ') created with GD library to parse PNG image');
            }
            if ($pp_ux) {
                $info['set-dpi'] = $pp_ux;
            }
            $info['type'] = 'png';
            if ($first_time) {
                $info['i'] = count($this->mpdf->images) + 1;
                $info['interpolation'] = $interpolation;
                // mPDF 6
                if ($icc) {
                    $info['ch'] = $channels;
                    $info['icc'] = $icc;
                }
                $this->mpdf->images[$file] = $info;
            }
            return $info;
        } else {
            // PNG image with no need to convert alph channels, bpc <> 8 etc.
            $parms = '/DecodeParms <</Predictor 15 /Colors ' . $channels . ' /BitsPerComponent ' . $bpc . ' /Columns ' . $w . '>>';
            //Scan chunks looking for palette, transparency and image data
            $pal = '';
            $trns = '';
            $pngdata = '';
            $icc = false;
            $p = 33;
            do {
                $n = $this->four_bytes_to_int(substr($data, $p, 4));
                $p += 4;
                $type = substr($data, $p, 4);
                $p += 4;
                if ($type === 'PLTE') {
                    //Read palette
                    $pal = substr($data, $p, $n);
                    $p += $n;
                    $p += 4;
                } elseif ($type === 'tRNS') {
                    //Read transparency info
                    $t = substr($data, $p, $n);
                    $p += $n;
                    if ($ct === 0) {
                        $trns = [ord(substr($t, 1, 1))];
                    } elseif ($ct === 2) {
                        $trns = [ord(substr($t, 1, 1)), ord(substr($t, 3, 1)), ord(substr($t, 5, 1))];
                    } else {
                        $pos = strpos($t, chr(0));
                        if (is_int($pos)) {
                            $trns = [$pos];
                        }
                    }
                    $p += 4;
                } elseif ($type === 'IDAT') {
                    $pngdata .= substr($data, $p, $n);
                    $p += $n;
                    $p += 4;
                } elseif ($type === 'iCCP') {
                    $nullsep = strpos(substr($data, $p, 80), chr(0));
                    $icc = substr($data, $p + $nullsep + 2, $n - ($nullsep + 2));
                    $icc = @gzuncompress($icc);
                    // Ignored if fails
                    if ($icc) {
                        if (substr($icc, 36, 4) !== 'acsp') {
                            $icc = false;
                        } else {
                            $input = substr($icc, 16, 4);
                            $output = substr($icc, 20, 4);
                            // Ignore Color profiles for conversion to other colorspaces e.g. CMYK/Lab
                            if ($input !== 'RGB ' || $output !== 'XYZ ') {
                                $icc = false;
                            }
                        }
                    }
                    $p += $n;
                    $p += 4;
                } elseif ($type === 'IEND') {
                    break;
                } elseif (preg_match('/[a-zA-Z]{4}/', $type)) {
                    $p += $n + 4;
                } else {
                    return $this->image_error($file, $first_time, 'Error parsing PNG image data');
                }
            } while ($n);
            if (!$pngdata) {
                return $this->image_error($file, $first_time, 'Error parsing PNG image data - no IDAT data found');
            }
            if ($colspace === 'Indexed' && empty($pal)) {
                return $this->image_error($file, $first_time, 'Error parsing PNG image data - missing colour palette');
            }
            // mPDF 6 cannot have ICC profile and Indexed in a PDF document as both use the colorspace tag.
            if ($colspace === 'Indexed' && $icc) {
                $icc = false;
            }
            $info = ['w' => $w, 'h' => $h, 'cs' => $colspace, 'bpc' => $bpc, 'f' => 'FlateDecode', 'parms' => $parms, 'pal' => $pal, 'trns' => $trns, 'data' => $pngdata, 'ch' => $channels, 'icc' => $icc];
            $info['type'] = 'png';
            if ($pp_ux) {
                $info['set-dpi'] = $pp_ux;
            }
        }
        if (!$info) {
            return $this->image_error($file, $first_time, 'Error parsing or converting PNG image');
        }
        if ($first_time) {
            $info['i'] = count($this->mpdf->images) + 1;
            $info['interpolation'] = $interpolation;
            // mPDF 6
            $this->mpdf->images[$file] = $info;
        }
        return $info;
    }
    public function process_webp($data, $file, $first_time)
    {
        $im = @imagecreatefromstring($data);
        if (!function_exists('imagewebp') || false === $im) {
            return $this->image_error($file, $first_time, 'Missing GD support for WEBP images.');
        }
        $tempfile = $this->cache->temp_filename('_tempImgPNG' . md5($file) . random_int(1, 10000) . '.jpg');
        $checkfile = $this->cache->temp_filename('_tempImgPNG' . md5($file) . random_int(1, 10000) . '.jpg');
        $check = imagewebp($im, $checkfile);
        if (!$check) {
            return $this->image_error($file, $first_time, sprintf('Error creating temporary file "%s" when using GD library to parse WEBP image', $checkfile));
        }
        @imagejpeg($im, $tempfile);
        $data = file_get_contents($tempfile);
        $this->destroy_image($im);
        unlink($tempfile);
        unlink($checkfile);
        return $data;
    }
    public function process_avif($data, $file, $first_time)
    {
        $im = @imagecreatefromstring($data);
        if (!function_exists('imageavif') || false === $im) {
            return $this->image_error($file, $first_time, 'Missing GD support for AVIF images.');
        }
        $tempfile = $this->cache->temp_filename('_tempImgPNG' . md5($file) . random_int(1, 10000) . '.jpg');
        $checkfile = $this->cache->temp_filename('_tempImgPNG' . md5($file) . random_int(1, 10000) . '.jpg');
        $check = imageavif($im, $checkfile);
        if (!$check) {
            return $this->image_error($file, $first_time, sprintf('Error creating temporary file "%s" when using GD library to parse AVIF image', $checkfile));
        }
        @imagejpeg($im, $tempfile);
        $data = file_get_contents($tempfile);
        $this->destroy_image($im);
        unlink($tempfile);
        unlink($checkfile);
        return $data;
    }
    public function process_svg($data, $file, $first_time)
    {
        $svg = new Svg($this->mpdf, $this->otl, $this->css_manager, $this, $this->size_converter, $this->color_converter, $this->language_to_font, $this->script_to_language);
        $family = $this->mpdf->font_family;
        $style = $this->mpdf->font_style;
        $size = $this->mpdf->font_size_pt;
        $info = $svg->image_svg($data);
        // Restore font
        if ($family) {
            $this->mpdf->set_font($family, $style, $size, false);
        }
        if (!$info) {
            return $this->image_error($file, $first_time, 'Error parsing SVG file');
        }
        $info['type'] = 'svg';
        $info['i'] = count($this->mpdf->formobjects) + 1;
        $this->mpdf->formobjects[$file] = $info;
        return $info;
    }
    public function process_gif($data, $file, $first_time, $interpolation)
    {
        $gd = function_exists('gd_info') ? gd_info() : [];
        if (isset($gd['GIF Read Support']) && $gd['GIF Read Support']) {
            $im = @imagecreatefromstring($data);
            if ($im) {
                $tempfile = $this->cache->temp_filename('_tempImgPNG' . md5($file) . random_int(1, 10000) . '.png');
                imagealphablending($im, false);
                imagesavealpha($im, false);
                imageinterlace($im, false);
                $check = @imagepng($im, $tempfile);
                if (!$check) {
                    return $this->image_error($file, $first_time, 'Error creating temporary file (' . $tempfile . ') when using GD library to parse GIF image');
                }
                // $info = $this->getImage($tempfile, false);
                $data = file_get_contents($tempfile);
                $info = $this->process_png($data, $tempfile, false, $interpolation);
                if (!$info) {
                    return $this->image_error($file, $first_time, 'Error parsing temporary file (' . $tempfile . ') created with GD library to parse GIF image');
                }
                $this->destroy_image($im);
                unlink($tempfile);
                $info['type'] = 'gif';
                if ($first_time) {
                    $info['i'] = count($this->mpdf->images) + 1;
                    $info['interpolation'] = $interpolation;
                    // mPDF 6
                    $this->mpdf->images[$file] = $info;
                }
                return $info;
            }
            return $this->image_error($file, $first_time, 'Error creating GD image file from GIF image');
        }
        $gif = new Gif();
        $h = 0;
        $w = 0;
        $gif->load_file($data, 0);
        $n_colors = 0;
        $bg_color = -1;
        $colspace = 'DeviceGray';
        $pal = '';
        if (isset($gif->m_img->m_gih->m_b_local_clr) && $gif->m_img->m_gih->m_b_local_clr) {
            $n_colors = $gif->m_img->m_gih->m_n_table_size;
            $pal = $gif->m_img->m_gih->m_color_table->to_string();
            if ($bg_color !== -1) {
                // mPDF 5.7.3
                $bg_color = $gif->m_img->m_gih->m_color_table->color_index($bg_color);
            }
            $colspace = 'Indexed';
        } elseif (isset($gif->m_gfh->m_b_global_clr) && $gif->m_gfh->m_b_global_clr) {
            $n_colors = $gif->m_gfh->m_n_table_size;
            $pal = $gif->m_gfh->m_color_table->to_string();
            if ($bg_color != -1) {
                $bg_color = $gif->m_gfh->m_color_table->color_index($bg_color);
            }
            $colspace = 'Indexed';
        }
        $trns = '';
        if (isset($gif->m_img->m_b_trans) && $gif->m_img->m_b_trans && $n_colors > 0) {
            $trns = [$gif->m_img->m_n_trans];
        }
        $gifdata = $gif->m_img->m_data;
        $w = $gif->m_gfh->m_n_width;
        $h = $gif->m_gfh->m_n_height;
        $gif->clear_data();
        if ($colspace === 'Indexed' && empty($pal)) {
            return $this->image_error($file, $first_time, 'Error parsing GIF image - missing colour palette');
        }
        if ($this->mpdf->compress) {
            $gifdata = $this->gz_compress($gifdata);
            $info = ['w' => $w, 'h' => $h, 'cs' => $colspace, 'bpc' => 8, 'f' => 'FlateDecode', 'pal' => $pal, 'trns' => $trns, 'data' => $gifdata];
        } else {
            $info = ['w' => $w, 'h' => $h, 'cs' => $colspace, 'bpc' => 8, 'pal' => $pal, 'trns' => $trns, 'data' => $gifdata];
        }
        $info['type'] = 'gif';
        if ($first_time) {
            $info['i'] = count($this->mpdf->images) + 1;
            $info['interpolation'] = $interpolation;
            // mPDF 6
            $this->mpdf->images[$file] = $info;
        }
        return $info;
    }
    public function process_bmp($data, $file, $first_time, $interpolation)
    {
        if ($this->bmp === null) {
            $this->bmp = new Bmp($this->mpdf);
        }
        $info = $this->bmp->_get_bm_pimage($data, $file);
        if (isset($info['error'])) {
            return $this->image_error($file, $first_time, $info['error']);
        }
        if ($first_time) {
            $info['i'] = count($this->mpdf->images) + 1;
            $info['interpolation'] = $interpolation;
            // mPDF 6
            $this->mpdf->images[$file] = $info;
        }
        return $info;
    }
    public function process_wmf($data, $file, $first_time)
    {
        if ($this->wmf === null) {
            $this->wmf = new Wmf($this->mpdf, $this->color_converter);
        }
        $wmfres = $this->wmf->_get_wm_fimage($data);
        if ($wmfres[0] == 0) {
            if ($wmfres[1]) {
                return $this->image_error($file, $first_time, $wmfres[1]);
            }
            return $this->image_error($file, $first_time, 'Error parsing WMF image');
        }
        $info = ['x' => $wmfres[2][0], 'y' => $wmfres[2][1], 'w' => $wmfres[3][0], 'h' => $wmfres[3][1], 'data' => $wmfres[1]];
        $info['i'] = count($this->mpdf->formobjects) + 1;
        $info['type'] = 'wmf';
        $this->mpdf->formobjects[$file] = $info;
        return $info;
    }
    public function process_unknown_type($data, $file, $first_time, $interpolation)
    {
        $gd = function_exists('gd_info') ? gd_info() : [];
        if (isset($gd['PNG Support']) && $gd['PNG Support']) {
            $im = @imagecreatefromstring($data);
            if (!$im) {
                return $this->image_error($file, $first_time, 'Error parsing image file - image type not recognised and/or not supported by GD imagecreate');
            }
            $tempfile = $this->cache->temp_filename('_tempImgPNG' . md5($file) . random_int(1, 10000) . '.png');
            imagealphablending($im, false);
            imagesavealpha($im, false);
            imageinterlace($im, false);
            $check = @imagepng($im, $tempfile);
            if (!$check) {
                return $this->image_error($file, $first_time, sprintf('Error creating temporary file "%s" when using GD library to parse unknown image type', $tempfile));
            }
            //$info = $this->getImage($tempfile, false);
            $data = file_get_contents($tempfile);
            $info = $this->process_png($data, $tempfile, false, $interpolation);
            $this->destroy_image($im);
            unlink($tempfile);
            if (!$info) {
                return $this->image_error($file, $first_time, sprintf('Error parsing temporary file "%s" created with GD library to parse unknown image type', $tempfile));
            }
            $info['type'] = 'png';
            if ($first_time) {
                $info['i'] = count($this->mpdf->images) + 1;
                $info['interpolation'] = $interpolation;
                // mPDF 6
                $this->mpdf->images[$file] = $info;
            }
            return $info;
        }
    }
    private function destroy_image($im)
    {
        if (PHP_VERSION_ID < 80000) {
            imagedestroy($im);
        }
    }
}