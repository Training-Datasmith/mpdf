<?php

declare (strict_types=1);
namespace Mpdf\Gif;

/**
 * GIF Util - (C) 2003 Yamasoft (S/C)
 *
 * All Rights Reserved
 *
 * This file can be freely copied, distributed, modified, updated by anyone under the only
 * condition to leave the original address (Yamasoft, http://www.yamasoft.com) and this header.
 *
 * @link http://www.yamasoft.com
 */
class Lzw
{
    public $MAX_LZW_BITS;
    public $Fresh;
    public $code_size;
    public $set_code_size;
    public $max_code;
    public $max_code_size;
    public $first_code;
    public $old_code;
    public $clear_code;
    public $end_code;
    public $Next;
    public $Vals;
    public $Stack;
    public $sp;
    public $Buf;
    public $cur_bit;
    public $last_bit;
    public $Done;
    public $last_byte;
    public function __construct()
    {
        $this->MAX_LZW_BITS = 12;
        unset($this->Next);
        unset($this->Vals);
        unset($this->Stack);
        unset($this->Buf);
        $this->Next = range(0, (1 << $this->MAX_LZW_BITS) - 1);
        $this->Vals = range(0, (1 << $this->MAX_LZW_BITS) - 1);
        $this->Stack = range(0, (1 << $this->MAX_LZW_BITS + 1) - 1);
        $this->Buf = range(0, 279);
    }
    public function de_compress($data, &$dat_len)
    {
        $dat_len = 0;
        $ret = '';
        $dp = 0;
        // data pointer
        // INITIALIZATION
        $this->lzw_command_init($data, $dp);
        while (($i_index = $this->lzw_command($data, $dp)) >= 0) {
            $ret .= chr($i_index);
        }
        $dat_len = $dp;
        if ($i_index != -2) {
            return false;
        }
        return $ret;
    }
    public function lzw_command_init(&$data, &$dp)
    {
        $this->set_code_size = ord($data[0]);
        $dp += 1;
        $this->code_size = $this->set_code_size + 1;
        $this->clear_code = 1 << $this->set_code_size;
        $this->end_code = $this->clear_code + 1;
        $this->max_code = $this->clear_code + 2;
        $this->max_code_size = $this->clear_code << 1;
        $this->get_code_init($data, $dp);
        $this->Fresh = 1;
        for ($i = 0; $i < $this->clear_code; $i++) {
            $this->Next[$i] = 0;
            $this->Vals[$i] = $i;
        }
        for (; $i < 1 << $this->MAX_LZW_BITS; $i++) {
            $this->Next[$i] = 0;
            $this->Vals[$i] = 0;
        }
        $this->sp = 0;
        return 1;
    }
    public function lzw_command(&$data, &$dp)
    {
        if ($this->Fresh) {
            $this->Fresh = 0;
            do {
                $this->first_code = $this->get_code($data, $dp);
                $this->old_code = $this->first_code;
            } while ($this->first_code == $this->clear_code);
            return $this->first_code;
        }
        if ($this->sp > 0) {
            $this->sp--;
            return $this->Stack[$this->sp];
        }
        while (($Code = $this->get_code($data, $dp)) >= 0) {
            if ($Code == $this->clear_code) {
                for ($i = 0; $i < $this->clear_code; $i++) {
                    $this->Next[$i] = 0;
                    $this->Vals[$i] = $i;
                }
                for (; $i < 1 << $this->MAX_LZW_BITS; $i++) {
                    $this->Next[$i] = 0;
                    $this->Vals[$i] = 0;
                }
                $this->code_size = $this->set_code_size + 1;
                $this->max_code_size = $this->clear_code << 1;
                $this->max_code = $this->clear_code + 2;
                $this->sp = 0;
                $this->first_code = $this->get_code($data, $dp);
                $this->old_code = $this->first_code;
                return $this->first_code;
            }
            if ($Code == $this->end_code) {
                return -2;
            }
            $in_code = $Code;
            if ($Code >= $this->max_code) {
                $this->Stack[$this->sp++] = $this->first_code;
                $Code = $this->old_code;
            }
            while ($Code >= $this->clear_code) {
                $this->Stack[$this->sp++] = $this->Vals[$Code];
                if ($Code == $this->Next[$Code]) {
                    // Circular table entry, big GIF Error!
                    return -1;
                }
                $Code = $this->Next[$Code];
            }
            $this->first_code = $this->Vals[$Code];
            $this->Stack[$this->sp++] = $this->first_code;
            if (($Code = $this->max_code) < 1 << $this->MAX_LZW_BITS) {
                $this->Next[$Code] = $this->old_code;
                $this->Vals[$Code] = $this->first_code;
                $this->max_code++;
                if ($this->max_code >= $this->max_code_size && $this->max_code_size < 1 << $this->MAX_LZW_BITS) {
                    $this->max_code_size *= 2;
                    $this->code_size++;
                }
            }
            $this->old_code = $in_code;
            if ($this->sp > 0) {
                $this->sp--;
                return $this->Stack[$this->sp];
            }
        }
        return $Code;
    }
    public function get_code_init(&$data, &$dp)
    {
        $this->cur_bit = 0;
        $this->last_bit = 0;
        $this->Done = 0;
        $this->last_byte = 2;
        return 1;
    }
    public function get_code(array &$data, &$dp)
    {
        if ($this->cur_bit + $this->code_size >= $this->last_bit) {
            if ($this->Done) {
                if ($this->cur_bit >= $this->last_bit) {
                    // Ran off the end of my bits
                    return 0;
                }
                return -1;
            }
            $this->Buf[0] = $this->Buf[$this->last_byte - 2];
            $this->Buf[1] = $this->Buf[$this->last_byte - 1];
            $Count = ord($data[$dp]);
            $dp += 1;
            if ($Count) {
                for ($i = 0; $i < $Count; $i++) {
                    $this->Buf[2 + $i] = ord($data[$dp + $i]);
                }
                $dp += $Count;
            } else {
                $this->Done = 1;
            }
            $this->last_byte = 2 + $Count;
            $this->cur_bit = $this->cur_bit - $this->last_bit + 16;
            $this->last_bit = 2 + $Count << 3;
        }
        $i_ret = 0;
        for ($i = $this->cur_bit, $j = 0; $j < $this->code_size; $i++, $j++) {
            $i_ret |= (($this->Buf[intval($i / 8)] & 1 << $i % 8) != 0) << $j;
        }
        $this->cur_bit += $this->code_size;
        return $i_ret;
    }
}