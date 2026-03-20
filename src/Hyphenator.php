<?php

declare (strict_types=1);
namespace Mpdf;

class Hyphenator
{
    /**
     * @var \Mpdf\Mpdf
     */
    private $mpdf;
    private $patterns;
    private $dictionary;
    private $words;
    private $loaded_patterns;
    /**
     * @var bool
     */
    private $dictionary_loaded;
    public function __construct(Mpdf $mpdf)
    {
        $this->mpdf = $mpdf;
        $this->dictionary_loaded = false;
        $this->patterns = [];
        $this->dictionary = [];
        $this->words = [];
    }
    /**
     * @param string $word
     * @param int $currptr
     *
     * @return int
     */
    public function hyphenate_word($word, $currptr)
    {
        // Do everything inside this function in utf-8
        // Don't hyphenate web addresses
        if (preg_match('/^(http:|https:|www\.)/', $word)) {
            return -1;
        }
        // Don't hyphenate email addresses
        if (preg_match('/^[a-zA-Z0-9-_.+]+@[a-zA-Z0-9-_.]+/', $word)) {
            return -1;
        }
        $ptr = -1;
        if (!$this->dictionary_loaded) {
            $this->load_dictionary();
        }
        if (!in_array($this->mpdf->sh_ylang, $this->mpdf->sh_ylanguages)) {
            return -1;
        }
        // If no pattern loaded or not the best one
        if (!$this->patterns_loaded()) {
            $this->load_patterns();
        }
        if ($this->mpdf->using_core_font) {
            $word = mb_convert_encoding($word, 'UTF-8', $this->mpdf->mb_enc);
        }
        $prepre = '';
        $postpost = '';
        $startpunctuation = "«¿‘‛“‟";
        $endpunctuation = "„”‚’»";
        if (preg_match('/^(["\'' . $startpunctuation . '])+(.{' . $this->mpdf->sh_ycharmin . ',})$/u', $word, $m)) {
            $prepre = $m[1];
            $word = $m[2];
        }
        if (preg_match('/^(.{' . $this->mpdf->sh_ycharmin . ',})([\'\.,;:!?"' . $endpunctuation . ']+)$/u', $word, $m)) {
            $word = $m[1];
            $postpost = $m[2];
        }
        if (mb_strlen($word, 'UTF-8') < $this->mpdf->sh_ycharmin) {
            return -1;
        }
        $success = false;
        $preprelen = mb_strlen($prepre);
        if (isset($this->words[mb_strtolower($word)])) {
            foreach ($this->words[mb_strtolower($word)] as $i) {
                if ($i + $preprelen >= $currptr) {
                    break;
                }
                $ptr = $i + $preprelen;
                $success = true;
            }
        }
        if (!$success) {
            $text_word = '_' . $word . '_';
            $word_length = mb_strlen($text_word, 'UTF-8');
            $text_word = mb_strtolower($text_word, 'UTF-8');
            $hyphenated_word = [];
            $numbers = ['0' => true, '1' => true, '2' => true, '3' => true, '4' => true, '5' => true, '6' => true, '7' => true, '8' => true, '9' => true];
            for ($position = 0; $position <= $word_length - $this->mpdf->sh_ycharmin; $position++) {
                $maxwins = min($word_length - $position, $this->mpdf->sh_ycharmax);
                for ($win = $this->mpdf->sh_ycharmin; $win <= $maxwins; $win++) {
                    if (isset($this->patterns[mb_substr($text_word, $position, $win, 'UTF-8')])) {
                        $pattern = $this->patterns[mb_substr($text_word, $position, $win, 'UTF-8')];
                        $digits = 1;
                        $pattern_length = mb_strlen($pattern, 'UTF-8');
                        for ($i = 0; $i < $pattern_length; $i++) {
                            $char = $pattern[$i];
                            if (isset($numbers[$char])) {
                                $zero = $i === 0 ? $position - 1 : $position + $i - $digits;
                                if (!isset($hyphenated_word[$zero]) || $hyphenated_word[$zero] !== $char) {
                                    $hyphenated_word[$zero] = $char;
                                }
                                $digits++;
                            }
                        }
                    }
                }
            }
            for ($i = $this->mpdf->sh_yleftmin; $i <= mb_strlen($word, 'UTF-8') - $this->mpdf->sh_yrightmin; $i++) {
                if (isset($hyphenated_word[$i]) && $hyphenated_word[$i] % 2 !== 0) {
                    if ($i + $preprelen > $currptr) {
                        break;
                    }
                    $ptr = $i + $preprelen;
                }
            }
        }
        return $ptr;
    }
    private function patterns_loaded()
    {
        return !(count($this->patterns) < 1 || $this->loaded_patterns && $this->loaded_patterns !== $this->mpdf->sh_ylang);
    }
    private function load_patterns()
    {
        $patterns = require __DIR__ . '/../data/patterns/' . $this->mpdf->sh_ylang . '.php';
        $patterns = explode(' ', $patterns);
        $new_patterns = [];
        $pattern_count = count($patterns);
        for ($i = 0; $i < $pattern_count; $i++) {
            $value = $patterns[$i];
            $new_patterns[preg_replace('/[0-9]/', '', $value)] = $value;
        }
        $this->patterns = $new_patterns;
        $this->loaded_patterns = $this->mpdf->sh_ylang;
    }
    private function load_dictionary()
    {
        if (file_exists($this->mpdf->hyphenation_dictionary_file)) {
            $this->dictionary = file($this->mpdf->hyphenation_dictionary_file, FILE_SKIP_EMPTY_LINES);
            foreach ($this->dictionary as $entry) {
                $entry = trim($entry);
                $poss = [];
                $offset = 0;
                $p = true;
                $wl = mb_strlen($entry, 'UTF-8');
                while ($offset < $wl) {
                    $p = mb_strpos($entry, '/', $offset, 'UTF-8');
                    if ($p !== false) {
                        $poss[] = $p - count($poss);
                    } else {
                        break;
                    }
                    $offset = $p + 1;
                }
                if (count($poss)) {
                    $this->words[str_replace('/', '', mb_strtolower($entry))] = $poss;
                }
            }
        } elseif ($this->mpdf->debug) {
            throw new \Mpdf\Mpdf_Exception(sprintf('Unable to open hyphenation dictionary "%s"', $this->mpdf->hyphenation_dictionary_file));
        }
        $this->dictionary_loaded = true;
    }
}