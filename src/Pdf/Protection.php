<?php

namespace Mpdf\Pdf;

use Mpdf\Pdf\Protection\Uniqid_Generator;
class Protection
{
    /**
     * @var string
     */
    private $last_rc4key;
    /**
     * @var string
     */
    private $last_rc4key_c;
    /**
     * @var bool
     */
    private $use_rc128encryption;
    /**
     * @var string
     */
    private $encryption_key;
    /**
     * @var string
     */
    private $padding;
    /**
     * @var string
     */
    private $uniqid;
    /**
     * @var string
     */
    private $o_value;
    /**
     * @var string
     */
    private $u_value;
    /**
     * @var string
     */
    private $p_value;
    /**
     * @var int[] Array of permission => byte representation
     */
    private $options;
    /**
     * @var \Mpdf\Pdf\Protection\UniqidGenerator
     */
    private $uniqid_generator;
    public function __construct(Uniqid_Generator $uniqid_generator)
    {
        if (!function_exists('random_int') || !function_exists('random_bytes')) {
            throw new \Mpdf\Mpdf_Exception('Unable to set PDF file protection, CSPRNG Functions are not available. ' . 'Use paragonie/random_compat polyfill or upgrade to PHP 7.');
        }
        $this->uniqid_generator = $uniqid_generator;
        $this->last_rc4key = '';
        $this->padding = "(\xbfN^Nu\x8aAd\x00NV\xff\xfa\x01\x08" . "..\x00\xb6\xd0h>\x80/\f\xa9\xfedSiz";
        $this->use_rc128encryption = false;
        $this->options = [
            'print' => 4,
            // bit 3
            'modify' => 8,
            // bit 4
            'copy' => 16,
            // bit 5
            'annot-forms' => 32,
            // bit 6
            'fill-forms' => 256,
            // bit 9
            'extract' => 512,
            // bit 10
            'assemble' => 1024,
            // bit 11
            'print-highres' => 2048,
        ];
    }
    /**
     * @param array $permissions
     * @param string $user_pass
     * @param string $owner_pass
     * @param int $length
     *
     * @return bool
     */
    public function set_protection($permissions = [], $user_pass = '', $owner_pass = null, $length = 40)
    {
        if (is_string($permissions) && strlen($permissions) > 0) {
            $permissions = [$permissions];
        } elseif (!is_array($permissions)) {
            return false;
        }
        $protection = $this->get_protection_bits_from_options($permissions);
        if ($length === 128) {
            $this->use_rc128encryption = true;
        } elseif ($length !== 40) {
            throw new \Mpdf\Mpdf_Exception('PDF protection only allows lenghts of 40 or 128');
        }
        if ($owner_pass === null) {
            $owner_pass = bin2hex(random_bytes(23));
        }
        $this->generate_encryption_key($user_pass, $owner_pass, $protection);
        return true;
    }
    /**
     * Compute key depending on object number where the encrypted data is stored
     *
     * @param int $n
     *
     * @return string
     */
    public function object_key($n)
    {
        if ($this->use_rc128encryption) {
            $len = 16;
        } else {
            $len = 10;
        }
        return substr($this->md5to_binary($this->encryption_key . pack('VXxx', $n)), 0, $len);
    }
    /**
     * RC4 is the standard encryption algorithm used in PDF format
     *
     * @param string $key
     * @param string $text
     *
     * @return string
     */
    public function rc4($key, $text)
    {
        if ($this->last_rc4key != $key) {
            $k = str_repeat($key, round(256 / strlen($key)) + 1);
            $rc4 = range(0, 255);
            $j = 0;
            for ($i = 0; $i < 256; $i++) {
                $t = $rc4[$i];
                $j = ($j + $t + ord($k[$i])) % 256;
                $rc4[$i] = $rc4[$j];
                $rc4[$j] = $t;
            }
            $this->last_rc4key = $key;
            $this->last_rc4key_c = $rc4;
        } else {
            $rc4 = $this->last_rc4key_c;
        }
        $len = strlen($text);
        $a = 0;
        $b = 0;
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $a = ($a + 1) % 256;
            $t = $rc4[$a];
            $b = ($b + $t) % 256;
            $rc4[$a] = $rc4[$b];
            $rc4[$b] = $t;
            $k = $rc4[($rc4[$a] + $rc4[$b]) % 256];
            $out .= chr(ord($text[$i]) ^ $k);
        }
        return $out;
    }
    /**
     * @return mixed
     */
    public function get_use_rc128encryption()
    {
        return $this->use_rc128encryption;
    }
    /**
     * @return mixed
     */
    public function get_uniqid()
    {
        return $this->uniqid;
    }
    /**
     * @return mixed
     */
    public function get_o_value()
    {
        return $this->o_value;
    }
    /**
     * @return mixed
     */
    public function get_u_value()
    {
        return $this->u_value;
    }
    /**
     * @return mixed
     */
    public function get_p_value()
    {
        return $this->p_value;
    }
    private function get_protection_bits_from_options($permissions)
    {
        // bit 31 = 1073741824
        // bit 32 = 2147483648
        // bits 13-31 = 2147479552
        // bits 13-32 = 4294963200 + 192 = 4294963392
        $protection = 4294963392;
        // bits 7, 8, 13-32
        foreach ($permissions as $permission) {
            if (!isset($this->options[$permission])) {
                throw new \Mpdf\Mpdf_Exception(sprintf('Invalid permission type "%s"', $permission));
            }
            if ($this->options[$permission] > 32) {
                $this->use_rc128encryption = true;
            }
            if (isset($this->options[$permission])) {
                $protection += $this->options[$permission];
            }
        }
        return $protection;
    }
    private function o_value($user_pass, $owner_pass)
    {
        $tmp = $this->md5to_binary($owner_pass);
        if ($this->use_rc128encryption) {
            for ($i = 0; $i < 50; ++$i) {
                $tmp = $this->md5to_binary($tmp);
            }
        }
        if ($this->use_rc128encryption) {
            $keybytelen = 128 / 8;
        } else {
            $keybytelen = 40 / 8;
        }
        $owner_rc4_key = substr($tmp, 0, $keybytelen);
        $enc = $this->rc4($owner_rc4_key, $user_pass);
        if ($this->use_rc128encryption) {
            $len = strlen($owner_rc4_key);
            for ($i = 1; $i <= 19; ++$i) {
                $key = '';
                for ($j = 0; $j < $len; ++$j) {
                    $key .= chr(ord($owner_rc4_key[$j]) ^ $i);
                }
                $enc = $this->rc4($key, $enc);
            }
        }
        return $enc;
    }
    private function u_value()
    {
        if ($this->use_rc128encryption) {
            $tmp = $this->md5to_binary($this->padding . $this->hex_to_string($this->uniqid));
            $enc = $this->rc4($this->encryption_key, $tmp);
            $len = strlen($tmp);
            for ($i = 1; $i <= 19; ++$i) {
                $key = '';
                for ($j = 0; $j < $len; ++$j) {
                    $key .= chr(ord($this->encryption_key[$j]) ^ $i);
                }
                $enc = $this->rc4($key, $enc);
            }
            $enc .= str_repeat("\x00", 16);
            return substr($enc, 0, 32);
        } else {
            return $this->rc4($this->encryption_key, $this->padding);
        }
    }
    private function generate_encryption_key($user_pass, $owner_pass, $protection)
    {
        // Pad passwords
        $user_pass = substr($user_pass . $this->padding, 0, 32);
        $owner_pass = substr($owner_pass . $this->padding, 0, 32);
        $this->o_value = $this->o_value($user_pass, $owner_pass);
        $this->uniqid = $this->uniqid_generator->generate();
        // Compute encyption key
        if ($this->use_rc128encryption) {
            $keybytelen = 128 / 8;
        } else {
            $keybytelen = 40 / 8;
        }
        $prot = sprintf('%032b', $protection);
        $perms = chr(bindec(substr($prot, 24, 8)));
        $perms .= chr(bindec(substr($prot, 16, 8)));
        $perms .= chr(bindec(substr($prot, 8, 8)));
        $perms .= chr(bindec(substr($prot, 0, 8)));
        $tmp = $this->md5to_binary($user_pass . $this->o_value . $perms . $this->hex_to_string($this->uniqid));
        if ($this->use_rc128encryption) {
            for ($i = 0; $i < 50; ++$i) {
                $tmp = $this->md5to_binary(substr($tmp, 0, $keybytelen));
            }
        }
        $this->encryption_key = substr($tmp, 0, $keybytelen);
        $this->u_value = $this->u_value();
        $this->p_value = $protection;
    }
    private function md5to_binary($string)
    {
        return pack('H*', md5($string));
    }
    private function hex_to_string($hs)
    {
        $s = '';
        $len = strlen($hs);
        if ($len % 2 != 0) {
            $hs .= '0';
            ++$len;
        }
        for ($i = 0; $i < $len; $i += 2) {
            $s .= chr(hexdec($hs[$i] . $hs[$i + 1]));
        }
        return $s;
    }
}