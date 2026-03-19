<?php

declare(strict_types=1);

namespace Mpdf\Fonts;

class GlyphOperator
{
    public const WORDS = 1 << 0;

    public const SCALE = 1 << 3;

    public const MORE = 1 << 5;

    public const XYSCALE = 1 << 6;

    public const TWOBYTWO = 1 << 7;
}
