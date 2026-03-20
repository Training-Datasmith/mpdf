<?php

declare (strict_types=1);
namespace Mpdf;

class Buffer
{
    /** @var array<int, string> */
    private $contents = [];
    /** @var int */
    private $length = 0;
    public function append($content, $new_line = false)
    {
        if ($content === null || $content === '') {
            return;
        }
        $content = (string) $content;
        $content_length = strlen($content);
        // Do not create an additional buffer entry if the content is relatively small.
        if ($new_line && $content_length < 1000000) {
            $content .= "\n";
            ++$content_length;
        }
        $this->contents[] = $content;
        $this->length += $content_length;
        if ($new_line && $content_length >= 1000000) {
            $this->contents[] = "\n";
            ++$this->length;
        }
    }
    public function get_length()
    {
        return $this->length;
    }
    public function write_to_file($handle)
    {
        foreach ($this->contents as $content) {
            fwrite($handle, $content);
        }
    }
    public function write_to_output()
    {
        foreach ($this->contents as $content) {
            echo $content;
        }
    }
    public function write_to_string()
    {
        return implode('', $this->contents);
    }
    public function get_hash()
    {
        $hash = '';
        foreach ($this->contents as $content) {
            $hash = md5($hash . $content);
        }
        return $hash;
    }
}