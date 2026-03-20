<?php

declare (strict_types=1);
namespace Mpdf\Color;

use Mpdf\Mpdf;
class Color_Space_Restrictor
{
    public const RESTRICT_TO_GRAYSCALE = 1;
    public const RESTRICT_TO_RGB_SPOT_GRAYSCALE = 2;
    public const RESTRICT_TO_CMYK_SPOT_GRAYSCALE = 3;
    /**
     * @var \Mpdf\Mpdf
     */
    private $mpdf;
    /**
     * @var \Mpdf\Color\ColorModeConverter
     */
    private $color_mode_converter;
    /**
     * Process $mode settings
     *     1 - allow GRAYSCALE only [convert CMYK/RGB->gray]
     *     2 - allow RGB / SPOT COLOR / Grayscale [convert CMYK->RGB]
     *     3 - allow CMYK / SPOT COLOR / Grayscale [convert RGB->CMYK]
     *
     * @param \Mpdf\Mpdf $mpdf
     * @param \Mpdf\Color\ColorModeConverter $colorModeConverter
     * @param int $mode
     */
    public function __construct(Mpdf $mpdf, Color_Mode_Converter $color_mode_converter)
    {
        $this->mpdf = $mpdf;
        $this->color_mode_converter = $color_mode_converter;
    }
    /**
     * @param mixed $c
     * @param string $color
     * @param string[] $PDFAXwarnings
     *
     * @return float[]|mixed
     */
    public function restrict_color_space($c, $color, &$pdfa_xwarnings = [])
    {
        if (!is_array($c)) {
            return $c;
        }
        $mode = (int) $c[0];
        switch ($mode) {
            case 1:
                return $c;
            case 2:
                return $this->restrict_spot_color_space($c, $pdfa_xwarnings);
            case 3:
                return $this->restrict_rgb_color_space($c, $color, $pdfa_xwarnings);
            case 4:
                return $this->restrict_cmyk_color_space($c, $color, $pdfa_xwarnings);
            case 5:
                return $this->restrict_rgba_color_space($c, $color, $pdfa_xwarnings);
            case 6:
                return $this->restrict_cmyka_color_space($c, $color, $pdfa_xwarnings);
        }
        return $c;
    }
    /**
     * @param string $c
     * @param string[] $PDFAXwarnings
     *
     * @return float[]
     */
    private function restrict_spot_color_space(array $c, &$pdfa_xwarnings = [])
    {
        if (!isset($this->mpdf->spot_color_i_ds[$c[1]])) {
            throw new \Mpdf\Mpdf_Exception('Error: Spot colour has not been defined - ' . $this->mpdf->spot_color_i_ds[$c[1]]);
        }
        if ($this->mpdf->PDFA) {
            if ($this->mpdf->PDFA && !$this->mpdf->pdf_aauto) {
                $pdfa_xwarnings[] = "Spot color specified '" . $this->mpdf->spot_color_i_ds[$c[1]] . "' (converted to process color)";
            }
            if ($this->mpdf->restrict_color_space != 3) {
                $sp = $this->mpdf->spot_colors[$this->mpdf->spot_color_i_ds[$c[1]]];
                $c = $this->color_mode_converter->cmyk2rgb([4, $sp['c'], $sp['m'], $sp['y'], $sp['k']]);
            }
        } elseif ($this->mpdf->restrict_color_space == 1) {
            $sp = $this->mpdf->spot_colors[$this->mpdf->spot_color_i_ds[$c[1]]];
            $c = $this->color_mode_converter->cmyk2gray([4, $sp['c'], $sp['m'], $sp['y'], $sp['k']]);
        }
        return $c;
    }
    /**
     * @param mixed $c
     * @param string $color
     * @param string[] $PDFAXwarnings
     *
     * @return float[]
     */
    private function restrict_rgb_color_space(array $c, $color, &$pdfa_xwarnings = [])
    {
        if ($this->mpdf->PDFX || $this->mpdf->PDFA && $this->mpdf->restrict_color_space == 3) {
            if ($this->mpdf->PDFA && !$this->mpdf->pdf_aauto || $this->mpdf->PDFX && !$this->mpdf->pdf_xauto) {
                $pdfa_xwarnings[] = "RGB color specified '" . $color . "' (converted to CMYK)";
            }
            $c = $this->color_mode_converter->rgb2cmyk($c);
        } elseif ($this->mpdf->restrict_color_space == 1) {
            $c = $this->color_mode_converter->rgb2gray($c);
        } elseif ($this->mpdf->restrict_color_space == 3) {
            $c = $this->color_mode_converter->rgb2cmyk($c);
        }
        return $c;
    }
    /**
     * @param mixed $c
     * @param string $color
     * @param string[] $PDFAXwarnings
     *
     * @return float[]
     */
    private function restrict_cmyk_color_space(array $c, $color, &$pdfa_xwarnings = [])
    {
        if ($this->mpdf->PDFA && $this->mpdf->restrict_color_space != 3) {
            if ($this->mpdf->PDFA && !$this->mpdf->pdf_aauto) {
                $pdfa_xwarnings[] = "CMYK color specified '" . $color . "' (converted to RGB)";
            }
            $c = $this->color_mode_converter->cmyk2rgb($c);
        } elseif ($this->mpdf->restrict_color_space == 1) {
            $c = $this->color_mode_converter->cmyk2gray($c);
        } elseif ($this->mpdf->restrict_color_space == 2) {
            $c = $this->color_mode_converter->cmyk2rgb($c);
        }
        return $c;
    }
    /**
     * @param mixed $c
     * @param string $color
     * @param string[] $PDFAXwarnings
     *
     * @return float[]
     */
    private function restrict_rgba_color_space(array $c, $color, &$pdfa_xwarnings = [])
    {
        if ($this->mpdf->PDFX || $this->mpdf->PDFA && $this->mpdf->restrict_color_space == 3) {
            if ($this->mpdf->PDFA && !$this->mpdf->pdf_aauto || $this->mpdf->PDFX && !$this->mpdf->pdf_xauto) {
                $pdfa_xwarnings[] = "RGB color with transparency specified '" . $color . "' (converted to CMYK without transparency)";
            }
            $c = $this->color_mode_converter->rgb2cmyk($c);
            $c = [4, $c[1], $c[2], $c[3], $c[4]];
        } elseif ($this->mpdf->PDFA && $this->mpdf->restrict_color_space != 3) {
            if (!$this->mpdf->pdf_aauto) {
                $pdfa_xwarnings[] = "RGB color with transparency specified '" . $color . "' (converted to RGB without transparency)";
            }
            $c = $this->color_mode_converter->rgb2cmyk($c);
            $c = [4, $c[1], $c[2], $c[3], $c[4]];
        } elseif ($this->mpdf->restrict_color_space == 1) {
            $c = $this->color_mode_converter->rgb2gray($c);
        } elseif ($this->mpdf->restrict_color_space == 3) {
            $c = $this->color_mode_converter->rgb2cmyk($c);
        }
        return $c;
    }
    /**
     * @param mixed $c
     * @param string $color
     * @param string[] $PDFAXwarnings
     *
     * @return float[]
     */
    private function restrict_cmyka_color_space(array $c, $color, &$pdfa_xwarnings = [])
    {
        if ($this->mpdf->PDFA && $this->mpdf->restrict_color_space != 3) {
            if ($this->mpdf->PDFA && !$this->mpdf->pdf_aauto || $this->mpdf->PDFX && !$this->mpdf->pdf_xauto) {
                $pdfa_xwarnings[] = "CMYK color with transparency specified '" . $color . "' (converted to RGB without transparency)";
            }
            $c = $this->color_mode_converter->cmyk2rgb($c);
            $c = [3, $c[1], $c[2], $c[3]];
        } elseif ($this->mpdf->PDFX || $this->mpdf->PDFA && $this->mpdf->restrict_color_space == 3) {
            if ($this->mpdf->PDFA && !$this->mpdf->pdf_aauto || $this->mpdf->PDFX && !$this->mpdf->pdf_xauto) {
                $pdfa_xwarnings[] = "CMYK color with transparency specified '" . $color . "' (converted to CMYK without transparency)";
            }
            $c = $this->color_mode_converter->cmyk2rgb($c);
            $c = [3, $c[1], $c[2], $c[3]];
        } elseif ($this->mpdf->restrict_color_space == 1) {
            $c = $this->color_mode_converter->cmyk2gray($c);
        } elseif ($this->mpdf->restrict_color_space == 2) {
            $c = $this->color_mode_converter->cmyk2rgb($c);
        }
        return $c;
    }
}