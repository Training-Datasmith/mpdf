<?php

declare (strict_types=1);
namespace Mpdf\Css;

class Text_Vars
{
    // font-decoration
    public const FD_UNDERLINE = 1;
    public const FD_LINETHROUGH = 2;
    public const FD_OVERLINE = 4;
    // font-(vertical)-align
    public const FA_SUPERSCRIPT = 8;
    public const FA_SUBSCRIPT = 16;
    // font-transform
    public const FT_UPPERCASE = 32;
    public const FT_LOWERCASE = 64;
    public const FT_CAPITALIZE = 128;
    // font-(other)-controls
    public const FC_KERNING = 256;
    public const FC_SMALLCAPS = 512;
}