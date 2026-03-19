<?php

declare(strict_types=1);

namespace Mpdf;

class HTMLParserMode
{
    /**
     * Parses a whole $html document
     */
    public const DEFAULT_MODE = 0;

    /**
     * Parses the $html as styles and stylesheets only
     */
    public const HEADER_CSS = 1;

    /**
     * Parses the $html as output elements only
     */
    public const HTML_BODY = 2;

    /**
     * (For internal use only - parses the $html code without writing to document)
     *
     * @internal
     */
    public const HTML_PARSE_NO_WRITE = 3;

    /**
     * (For internal use only - writes the $html code to a buffer)
     *
     * @internal
     */
    public const HTML_HEADER_BUFFER = 4;

    public static function getAllModes()
    {
        return [
            self::DEFAULT_MODE,
            self::HEADER_CSS,
            self::HTML_BODY,
            self::HTML_PARSE_NO_WRITE,
            self::HTML_HEADER_BUFFER,
        ];
    }
}
