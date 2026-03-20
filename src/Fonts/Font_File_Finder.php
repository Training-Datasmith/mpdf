<?php

declare (strict_types=1);
namespace Mpdf\Fonts;

class Font_File_Finder
{
    private $directories;
    public function __construct($directories)
    {
        $this->set_directories($directories);
    }
    public function set_directories($directories)
    {
        if (!is_array($directories)) {
            $directories = [$directories];
        }
        $this->directories = $directories;
    }
    public function find_font_file(string $name)
    {
        foreach ($this->directories as $directory) {
            $filename = $directory . '/' . $name;
            if (file_exists($filename)) {
                return $filename;
            }
        }
        throw new \Mpdf\Mpdf_Exception(sprintf('Cannot find TTF TrueType font file "%s" in configured font directories.', $name));
    }
}