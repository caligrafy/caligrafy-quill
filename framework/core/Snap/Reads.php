<?php
/**
 * The reads of one request, once (2.23.0): Home asks the same lists for its alerts, insights and picks; each runs
 * once per person per request. Cards::forget() (after a change) forgets them too.
 */

namespace Caligrafy\Snap;

use Caligrafy\Capabilities;
use Caligrafy\Context;

class Reads
{
    private static $cache = array();

    /**
     * A read-only capability's plain result, without inputs, once per request and person (its errors too)
     */
    public static function plain($name, Context $context)
    {
        $key = $name . '|' . $context->actorType() . '|' . $context->actorId();
        if (!array_key_exists($key, self::$cache)) {
            try {
                self::$cache[$key] = array(Capabilities::plain(Capabilities::call($name, array(), $context)), null);
            } catch (\Caligrafy\ServiceError $e) {
                self::$cache[$key] = array(null, $e);
            }
        }
        [$plain, $error] = self::$cache[$key];
        if ($error) {
            throw $error;
        }
        return $plain;
    }

    public static function forget()
    {
        self::$cache = array();
    }
}
