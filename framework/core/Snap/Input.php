<?php
/**
 * Input.php
 * The inputs of a Snap Card, as an array (2.23.0)
 * @copyright 2026
 */

namespace Caligrafy\Snap;

/**
 * Class Input
 * The inputs of a card arrive as an object, or its JSON (agents and addresses send JSON text): this gives the plain
 * values only (a nested object or list is left out: a card's inputs are scalars).
 */
class Input
{
    public static function decode($value)
    {
        if (is_string($value)) {
            $value = trim($value) === '' ? array() : json_decode($value, true);
        }
        if (is_object($value)) {
            $value = (array) $value;
        }
        if (!is_array($value)) {
            return array();
        }
        return array_filter($value, function ($v) { return !is_array($v) && !is_object($v); });
    }
}
