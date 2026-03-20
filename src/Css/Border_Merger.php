<?php

declare (strict_types=1);
namespace Mpdf\Css;

use Mpdf\Exception\InvalidArgumentException;
class Border_Merger
{
    /**
     * @var array<int> Border dominance levels for cell borders (top/right/bottom/left)
     */
    private $border_dominance = ['T' => 0, 'R' => 0, 'B' => 0, 'L' => 0];
    /**
     * Merge borders into CSS properties.
     *
     * @param array $newProperties properties to merge from
     * @param array $cssProperties current CSS properties (passed by reference)
     * @return void
     */
    public function merge_border_properties($new_properties, &$css_properties)
    {
        foreach (['TOP', 'RIGHT', 'BOTTOM', 'LEFT'] as $side) {
            $this->merge_side_border($side, $new_properties, $css_properties);
        }
    }
    /**
     * Merge border properties for a specific side.
     *
     * Helper method for mergeBorderProperties to handle merging of individual side properties
     * (style, width, color) into the shorthand border property.
     *
     * @param string $side Side to merge (TOP, RIGHT, BOTTOM, LEFT)
     * @param array $properties Source border properties
     * @param array $cssProperties Target CSS properties (passed by reference)
     * @return void
     */
    protected function merge_side_border($side, array $properties, array &$css_properties)
    {
        // Merges $a['BORDER-TOP-STYLE'] to $cssProperties['BORDER-TOP'] etc.
        $defaults = ['WIDTH' => '0px', 'STYLE' => 'none', 'COLOR' => '#000000'];
        $border_key = 'BORDER-' . $side;
        $current_border = isset($css_properties[$border_key]) ? trim($css_properties[$border_key]) : '';
        foreach (['STYLE', 'WIDTH', 'COLOR'] as $el) {
            $property_key = $border_key . '-' . $el;
            if (!isset($properties[$property_key])) {
                continue;
            }
            $value = trim($properties[$property_key]);
            if ($current_border) {
                // Update existing border value
                if ($el === 'STYLE') {
                    $css_properties[$border_key] = preg_replace('/(\S+)\s+(\S+)\s+(\S+)/', '\1 ' . $value . ' \3', $current_border);
                } elseif ($el === 'WIDTH') {
                    $css_properties[$border_key] = preg_replace('/(\S+)\s+(\S+)\s+(\S+)/', $value . ' \2 \3', $current_border);
                } else {
                    // COLOR
                    $css_properties[$border_key] = preg_replace('/(\S+)\s+(\S+)\s+(\S+)/', '\1 \2 ' . $value, $current_border);
                }
                $current_border = $css_properties[$border_key];
                // Update current border for next iteration
            } else {
                // Build new border from scratch with defaults
                if (!isset($border_parts)) {
                    $border_parts = $defaults;
                }
                $border_parts[$el] = $value;
                $css_properties[$border_key] = $border_parts['WIDTH'] . ' ' . $border_parts['STYLE'] . ' ' . $border_parts['COLOR'];
                $current_border = $css_properties[$border_key];
            }
        }
    }
    /**
     * Set border dominance level for table cells.
     *
     * Used in table rendering to determine which cell borders take
     * precedence when cells share borders.
     *
     * @param array $prop CSS properties containing border definitions
     * @param int $val Dominance level value
     * @return void
     */
    public function set_dominance_from_properties(array $prop, $val)
    {
        if (!empty($prop['BORDER-TOP'])) {
            $this->set_border_dominance('T', $val);
        }
        if (!empty($prop['BORDER-RIGHT'])) {
            $this->set_border_dominance('R', $val);
        }
        if (!empty($prop['BORDER-BOTTOM'])) {
            $this->set_border_dominance('B', $val);
        }
        if (!empty($prop['BORDER-LEFT'])) {
            $this->set_border_dominance('L', $val);
        }
    }
    /**
     * Set border dominance level for a specific side.
     *
     * @param string $side T|R|B|L
     * @param int $val Dominance value
     * @throws InvalidArgumentException
     */
    public function set_border_dominance($side, $val)
    {
        if (!isset($this->border_dominance[$side])) {
            throw new InvalidArgumentException('Invalid border dominance value:' . $side);
        }
        $this->border_dominance[$side] = (int) $val;
    }
    /**
     * Get border dominance level for a specific side.
     *
     * @param string $side T|R|B|L
     * @return int Dominance value
     */
    public function get_border_dominance($side)
    {
        return isset($this->border_dominance[$side]) ? $this->border_dominance[$side] : 0;
    }
}