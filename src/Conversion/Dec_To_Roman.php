<?php

declare (strict_types=1);
namespace Mpdf\Conversion;

/**
 * @link https://github.com/JeroenDeDauw/RomanNumbers
 * @license GNU GPL v2+
 */
class Dec_To_Roman
{
    private $symbol_map;
    public function __construct(array $symbol_map = [])
    {
        if ($symbol_map !== []) {
            $this->symbol_map = $symbol_map;
        } else {
            $this->symbol_map = [['I', 'V'], ['X', 'L'], ['C', 'D'], ['M']];
        }
    }
    public function convert($number, $to_upper = true)
    {
        $this->ensure_number_is_an_integer($number);
        $this->ensure_number_is_within_bounds($number);
        return $this->construct_roman_string($number, $to_upper);
    }
    private function ensure_number_is_an_integer($number)
    {
        if (!is_int($number)) {
            throw new \InvalidArgumentException('Can only translate integers to roman');
        }
    }
    private function ensure_number_is_within_bounds($number)
    {
        if ($number < 1) {
            throw new \OutOfRangeException('Numbers under one cannot be translated to roman');
        }
        if ($number > $this->get_upper_bound()) {
            throw new \OutOfBoundsException('The provided number is to big to be fully translated to roman');
        }
    }
    public function get_upper_bound()
    {
        $symbol_group_count = count($this->symbol_map);
        $value_of_one = 10 ** ($symbol_group_count - 1);
        $has_five_symbol = array_key_exists(1, $this->symbol_map[$symbol_group_count - 1]);
        return $value_of_one * ($has_five_symbol ? 9 : 4) - 1;
    }
    private function construct_roman_string($number, $to_upper)
    {
        $roman_number = '';
        $symbol_map_count = count($this->symbol_map);
        for ($i = 0; $i < $symbol_map_count; $i++) {
            $divisor = 10 ** ($i + 1);
            $remainder = $number % $divisor;
            $digit = $remainder / 10 ** $i;
            $number -= $remainder;
            $roman_number = $this->format_digit($digit, $i) . $roman_number;
            if ($number === 0) {
                break;
            }
        }
        if (!$to_upper) {
            return strtolower($roman_number);
        }
        return $roman_number;
    }
    private function format_digit($digit, $order_of_magnitude)
    {
        if ($digit === 0) {
            return '';
        }
        if ($digit === 4 || $digit === 9) {
            return $this->format_four_or_nine($digit, $order_of_magnitude);
        }
        $roman_number = '';
        if ($digit >= 5) {
            $digit -= 5;
            $roman_number .= $this->get_five_symbol($order_of_magnitude);
        }
        return $roman_number . $this->format_one_to_three($order_of_magnitude, $digit);
    }
    private function format_four_or_nine($digit, $order_of_magnitude)
    {
        $first_symbol = $this->get_one_symbol($order_of_magnitude);
        $second_symbol = $digit === 4 ? $this->get_five_symbol($order_of_magnitude) : $this->get_ten_symbol($order_of_magnitude);
        return $first_symbol . $second_symbol;
    }
    private function format_one_to_three($order_of_magnitude, $digit)
    {
        return str_repeat($this->get_one_symbol($order_of_magnitude), $digit);
    }
    private function get_one_symbol($order_of_magnitude)
    {
        return $this->symbol_map[$order_of_magnitude][0];
    }
    private function get_five_symbol($order_of_magnitude)
    {
        return $this->symbol_map[$order_of_magnitude][1];
    }
    private function get_ten_symbol($order_of_magnitude)
    {
        return $this->symbol_map[$order_of_magnitude + 1][0];
    }
}