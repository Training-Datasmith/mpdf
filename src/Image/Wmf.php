<?php

declare (strict_types=1);
namespace Mpdf\Image;

use Mpdf\Color\Color_Converter;
use Mpdf\Mpdf;
class Wmf
{
    /**
     * @var \Mpdf\Mpdf
     */
    private $mpdf;
    /**
     * @var \Mpdf\Color\ColorConverter
     */
    private $color_converter;
    /**
     * @var array
     */
    private $gdi_object_array;
    public function __construct(Mpdf $mpdf, Color_Converter $color_converter)
    {
        $this->mpdf = $mpdf;
        $this->color_converter = $color_converter;
    }
    public function _get_wm_fimage($data)
    {
        $k = Mpdf::SCALE;
        $this->gdi_object_array = [];
        $a = unpack('stest', "\x01\x00");
        if ($a['test'] != 1) {
            return [0, 'Error parsing WMF image - Big-endian architecture not supported'];
        }
        // check for Aldus placeable metafile header
        $key = unpack('Lmagic', substr($data, 0, 4));
        $p = 18;
        // WMF header
        if ($key['magic'] == 0x9ac6cdd7) {
            $p += 22;
        }
        // Aldus header
        // define some state variables
        $wo = null;
        // window origin
        $we = null;
        // window extent
        $poly_fill_mode = 0;
        $null_pen = false;
        $null_brush = false;
        $end_record = false;
        $wmfdata = '';
        while ($p < strlen($data) && !$end_record) {
            $record_info = unpack('Lsize/Sfunc', substr($data, $p, 6));
            $p += 6;
            // size of record given in WORDs (= 2 bytes)
            $size = $record_info['size'];
            // func is number of GDI function
            $func = $record_info['func'];
            if ($size > 3) {
                $parms = substr($data, $p, 2 * ($size - 3));
                $p += 2 * ($size - 3);
            }
            switch ($func) {
                case 0x20b:
                    // SetWindowOrg
                    // do not allow window origin to be changed
                    // after drawing has begun
                    if (!$wmfdata) {
                        $wo = array_reverse(unpack('s2', $parms));
                    }
                    break;
                case 0x20c:
                    // SetWindowExt
                    // do not allow window extent to be changed
                    // after drawing has begun
                    if (!$wmfdata) {
                        $we = array_reverse(unpack('s2', $parms));
                    }
                    break;
                case 0x2fc:
                    // CreateBrushIndirect
                    $brush = unpack('sstyle/Cr/Cg/Cb/Ca/Shatch', $parms);
                    $brush['type'] = 'B';
                    $this->_add_gdi_object($brush);
                    break;
                case 0x2fa:
                    // CreatePenIndirect
                    $pen = unpack('Sstyle/swidth/sdummy/Cr/Cg/Cb/Ca', $parms);
                    // convert width from twips to user unit
                    $pen['width'] /= 20 * $k;
                    $pen['type'] = 'P';
                    $this->_add_gdi_object($pen);
                    break;
                // MUST create other GDI objects even if we don't handle them
                case 0x6fe:
                // CreateBitmap
                case 0x2fd:
                // CreateBitmapIndirect
                case 0xf8:
                // CreateBrush
                case 0x2fb:
                // CreateFontIndirect
                case 0xf7:
                // CreatePalette
                case 0x1f9:
                // CreatePatternBrush
                case 0x6ff:
                // CreateRegion
                case 0x142:
                    // DibCreatePatternBrush
                    $dummy_object = ['type' => 'D'];
                    $this->_add_gdi_object($dummy_object);
                    break;
                case 0x106:
                    // SetPolyFillMode
                    $poly_fill_mode = unpack('smode', $parms);
                    $poly_fill_mode = $poly_fill_mode['mode'];
                    break;
                case 0x1f0:
                    // DeleteObject
                    $idx = unpack('Sidx', $parms);
                    $idx = $idx['idx'];
                    $this->_delete_gdi_object($idx);
                    break;
                case 0x12d:
                    // SelectObject
                    $idx = unpack('Sidx', $parms);
                    $idx = $idx['idx'];
                    $obj = $this->_get_gdi_object($idx);
                    switch ($obj['type']) {
                        case 'B':
                            $null_brush = false;
                            if ($obj['style'] == 1) {
                                $null_brush = true;
                            } else {
                                $wmfdata .= $this->mpdf->set_f_color($this->color_converter->convert('rgb(' . $obj['r'] . ',' . $obj['g'] . ',' . $obj['b'] . ')', $this->mpdf->pdfa_xwarnings), true) . "\n";
                            }
                            break;
                        case 'P':
                            $null_pen = false;
                            $dash_array = [];
                            // dash parameters are custom
                            switch ($obj['style']) {
                                case 0:
                                    // PS_SOLID
                                    break;
                                case 1:
                                    // PS_DASH
                                    $dash_array = [3, 1];
                                    break;
                                case 2:
                                    // PS_DOT
                                    $dash_array = [0.5, 0.5];
                                    break;
                                case 3:
                                    // PS_DASHDOT
                                    $dash_array = [2, 1, 0.5, 1];
                                    break;
                                case 4:
                                    // PS_DASHDOTDOT
                                    $dash_array = [2, 1, 0.5, 1, 0.5, 1];
                                    break;
                                case 5:
                                    // PS_NULL
                                    $null_pen = true;
                                    break;
                            }
                            if (!$null_pen) {
                                $wmfdata .= $this->mpdf->set_d_color($this->color_converter->convert('rgb(' . $obj['r'] . ',' . $obj['g'] . ',' . $obj['b'] . ')', $this->mpdf->pdfa_xwarnings), true) . "\n";
                                $wmfdata .= sprintf("%.3F w\n", $obj['width'] * $k);
                            }
                            if (!empty($dash_array)) {
                                $s = '[';
                                for ($i = 0; $i < count($dash_array); $i++) {
                                    $s .= $dash_array[$i] * $k;
                                    if ($i != count($dash_array) - 1) {
                                        $s .= ' ';
                                    }
                                }
                                $s .= '] 0 d';
                                $wmfdata .= $s . "\n";
                            }
                            break;
                    }
                    break;
                case 0x325:
                // Polyline
                case 0x324:
                    // Polygon
                    $coords = unpack('s' . ($size - 3), $parms);
                    $numpoints = $coords[1];
                    for ($i = $numpoints; $i > 0; $i--) {
                        $px = $coords[2 * $i];
                        $py = $coords[2 * $i + 1];
                        if ($i < $numpoints) {
                            $wmfdata .= $this->_line_to($px, $py);
                        } else {
                            $wmfdata .= $this->_move_to($px, $py);
                        }
                    }
                    if ($func == 0x325) {
                        $op = 's';
                    } elseif ($func == 0x324) {
                        if ($null_pen) {
                            if ($null_brush) {
                                $op = 'n';
                            } else {
                                $op = 'f';
                            }
                            // fill
                        } else {
                            if ($null_brush) {
                                $op = 's';
                            } else {
                                $op = 'b';
                            }
                            // stroke and fill
                        }
                        if ($poly_fill_mode == 1 && ($op == 'b' || $op == 'f')) {
                            $op .= '*';
                        }
                        // use even-odd fill rule
                    }
                    $wmfdata .= $op . "\n";
                    break;
                case 0x538:
                    // PolyPolygon
                    $coords = unpack('s' . ($size - 3), $parms);
                    $numpolygons = $coords[1];
                    $adjustment = $numpolygons;
                    for ($j = 1; $j <= $numpolygons; $j++) {
                        $numpoints = $coords[$j + 1];
                        for ($i = $numpoints; $i > 0; $i--) {
                            $px = $coords[2 * $i + $adjustment];
                            $py = $coords[2 * $i + 1 + $adjustment];
                            if ($i == $numpoints) {
                                $wmfdata .= $this->_move_to($px, $py);
                            } else {
                                $wmfdata .= $this->_line_to($px, $py);
                            }
                        }
                        $adjustment += $numpoints * 2;
                    }
                    if ($null_pen) {
                        if ($null_brush) {
                            $op = 'n';
                        } else {
                            $op = 'f';
                        }
                        // fill
                    } else {
                        if ($null_brush) {
                            $op = 's';
                        } else {
                            $op = 'b';
                        }
                        // stroke and fill
                    }
                    if ($poly_fill_mode == 1 && ($op == 'b' || $op == 'f')) {
                        $op .= '*';
                    }
                    // use even-odd fill rule
                    $wmfdata .= $op . "\n";
                    break;
                case 0x0:
                    $end_record = true;
                    break;
            }
        }
        return [1, $wmfdata, $wo, $we];
    }
    public function _move_to($x, $y)
    {
        return "{$x} {$y} m\n";
    }
    // a line must have been started using _MoveTo() first
    public function _line_to($x, $y)
    {
        return "{$x} {$y} l\n";
    }
    public function _add_gdi_object($obj)
    {
        // find next available slot
        $idx = 0;
        if (!empty($this->gdi_object_array)) {
            $empty = false;
            $i = 0;
            while (!$empty) {
                $empty = !isset($this->gdi_object_array[$i]);
                $i++;
            }
            $idx = $i - 1;
        }
        $this->gdi_object_array[$idx] = $obj;
    }
    public function _get_gdi_object($idx)
    {
        return $this->gdi_object_array[$idx];
    }
    public function _delete_gdi_object($idx)
    {
        unset($this->gdi_object_array[$idx]);
    }
}