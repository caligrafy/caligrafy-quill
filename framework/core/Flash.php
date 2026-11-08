<?php
/**
 * Flash.php
 * Values kept in the session for the next request only: the errors and the old input of a form, messages
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Flash (2.19.0)
 * Route::form() puts the field errors, the old input and a message here before it redirects; the page of the next
 * request reads them with the view helpers errors($field), old($field) and flash($key), then they are gone.
 * Route::run() ages them when there are some: what the previous request put becomes this request's, and leaves the
 * session (without Route::run, the first read does).
 *
 * Kept in $_SESSION['__flash'] as ['errors' => [field => message], 'old' => [field => value], 'messages' => [key => text]].
 */
class Flash
{
    const KEY = '__flash';

    /** @var array|null what this request reads (null: not aged yet) */
    private static $current = null;

    /**
     * Moves what the previous request put into this request (Route::run calls it once)
     */
    public static function age()
    {
        self::$current = isset($_SESSION[self::KEY]) && is_array($_SESSION[self::KEY]) ? $_SESSION[self::KEY] : array();
        if (isset($_SESSION[self::KEY])) {
            unset($_SESSION[self::KEY]);
        }
    }

    /**
     * Keeps a value for the next request
     * @param string $key errors, old or messages
     */
    public static function put($key, $value)
    {
        if (function_exists('caligrafySessionStart')) {
            caligrafySessionStart();   // a value to keep (2.19.0)
        }
        if (self::$current === null && !isset($_SESSION[self::KEY])) {
            self::$current = array();  // nothing came from the previous request: what is put now is for the next one
        }
        $_SESSION[self::KEY][$key] = $value;
    }

    /**
     * Keeps a message for the next request: Flash::message('success', 'Book added')
     */
    public static function message($key, $text)
    {
        $messages = isset($_SESSION[self::KEY]['messages']) && is_array($_SESSION[self::KEY]['messages']) ? $_SESSION[self::KEY]['messages'] : array();
        $messages[$key] = $text;
        self::put('messages', $messages);
    }

    /**
     * What the previous request put (errors, old or messages)
     */
    public static function get($key, $default = null)
    {
        if (self::$current === null) {
            self::age();
        }
        return self::$current[$key] ?? $default;
    }

    /**
     * Forgets what this request read (tests)
     */
    public static function reset()
    {
        self::$current = null;
    }
}
