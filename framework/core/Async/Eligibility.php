<?php
/**
 * Eligibility.php
 * Which capabilities the async server may run (2.26.0, experimental)
 * @copyright 2026
 *
*/

namespace Caligrafy\Async;

use Caligrafy\Capabilities;

/**
 * Class Eligibility
 * The async server keeps the application in memory and answers many requests from one process, so a capability it runs
 * must not keep anything from one caller to the next, nor talk to the web server's request directly. A capability is
 * eligible when its class's own source (read as PHP tokens: comments and strings do not count) uses none of:
 * - the request and the session: $_SESSION, $_COOKIE, $_GET, $_POST, $_REQUEST, $_FILES, $_SERVER, session(), request(),
 *   Auth::, redirect(), view() (the async channel knows its caller only from a token, or as a visitor);
 * - output and the end of the process: echo, print, printf, var_dump, header(), setcookie(), http_response_code(),
 *   exit, die, ob_*() (it returns data; the server sends it);
 * - state kept between calls: global, static variables, assignments to static properties;
 * and it is not webOnly and takes no file input (the `file` rule).
 * Anything else (classes it calls) is not read: the isolation test (php caligrafer.php async check) runs each eligible
 * capability for two callers in one process and compares. A capability that is not eligible is answered by the web
 * server as before: being unsafe makes it slower, never shared.
 */
class Eligibility
{
    const SUPERGLOBALS = array('$_SESSION', '$_COOKIE', '$_GET', '$_POST', '$_REQUEST', '$_FILES', '$_SERVER', '$GLOBALS');
    const FUNCTIONS = array('session', 'request', 'redirect', 'view', 'header', 'setcookie', 'setrawcookie', 'http_response_code',
        'printf', 'vprintf', 'var_dump', 'print_r', 'var_export', 'ob_start', 'ob_get_clean', 'ob_end_flush', 'flush', 'session_start',
        'session_regenerate_id', 'session_destroy', 'header_remove', 'headers_sent', 'set_time_limit', 'ignore_user_abort');

    /**
     * Why a capability cannot run on the async server, or an empty list when it can
     * @return string[] the reasons
     */
    public static function reasons($name)
    {
        $entry = Capabilities::get($name);
        if ($entry === null) {
            return array("no capability named '$name'");
        }
        $reasons = array();
        if ($entry['capability']->webOnly) {
            $reasons[] = 'webOnly: it reads the session or signs people in';
        }
        $class = new \ReflectionClass($entry['class']);
        $rules = $class->getDefaultProperties()['rules'] ?? array();
        foreach ((array) $rules as $input => $rule) {
            if (preg_match('/(^|\|)file(,|\||$)/', (string) $rule)) {
                $reasons[] = "the input '$input' is a file";
            }
        }
        $file = $class->getFileName();
        if (!is_string($file) || !is_file($file)) {
            return array_merge($reasons, array('its source cannot be read'));
        }
        // the class's own lines only: other classes of the same file are not its code
        $lines = array_slice(file($file), $class->getStartLine() - 1, $class->getEndLine() - $class->getStartLine() + 1);
        return array_merge($reasons, self::sourceReasons("<?php\n" . implode('', $lines)));
    }

    /**
     * Whether a capability can run on the async server
     */
    public static function eligible($name)
    {
        return !self::reasons($name);
    }

    /**
     * What a PHP source uses that the async server cannot allow
     * @return string[] the reasons, at most one per kind
     */
    public static function sourceReasons($source)
    {
        $tokens = array_values(array_filter(token_get_all((string) $source), function ($t) {
            return !(is_array($t) && in_array($t[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true));
        }));
        $found = array();
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];
            $id = is_array($t) ? $t[0] : $t;
            $text = is_array($t) ? $t[1] : $t;
            $prev = $i > 0 ? (is_array($tokens[$i - 1]) ? $tokens[$i - 1][0] : $tokens[$i - 1]) : null;
            $next = $i + 1 < $count ? (is_array($tokens[$i + 1]) ? $tokens[$i + 1][0] : $tokens[$i + 1]) : null;
            // a keyword after const, ::, -> or function is a name (const ECHO = ..., self::PRINT, $x->exit())
            if (in_array($prev, array(T_CONST, T_DOUBLE_COLON, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_FUNCTION), true) && $id !== T_VARIABLE) {
                continue;
            }
            if ($id === T_VARIABLE && in_array($text, self::SUPERGLOBALS, true)) {
                $found[$text] = "uses $text";
            } elseif ($id === T_ECHO || $id === T_PRINT || ($id === T_INLINE_HTML && trim($text) !== '')) {
                $found['output'] = 'prints output (echo, print): return the data instead';
            } elseif ($id === T_EXIT) {
                $found['exit'] = 'calls exit or die: throw a ServiceError instead';
            } elseif ($id === T_GLOBAL) {
                $found['global'] = 'uses global variables';
            } elseif ($id === T_STATIC && $next === T_VARIABLE && $prev !== T_DOUBLE_COLON
                && !in_array($prev, array(T_PUBLIC, T_PROTECTED, T_PRIVATE, T_READONLY, T_VAR), true)) {
                // static $x inside a function (a static property declaration is "private static $x")
                $found['static'] = 'keeps a static variable between calls';
            } elseif ($id === T_STRING && $next === '(' && !in_array($prev, array(T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW), true)
                && in_array(strtolower($text), self::FUNCTIONS, true)) {
                $found['fn:' . strtolower($text)] = 'calls ' . strtolower($text) . '()';
            } elseif (($id === T_STRING || $id === T_NAME_FULLY_QUALIFIED || $id === T_NAME_QUALIFIED) && $next === T_DOUBLE_COLON
                && in_array(strtolower(ltrim($text, '\\')), array('auth', 'caligrafy\\auth'), true)) {
                $found['auth'] = 'uses Auth (the session): the caller is $context';
            } elseif ($id === T_VARIABLE && $prev === T_DOUBLE_COLON && self::assignedAt($tokens, $i)) {
                $found['static-property'] = 'writes a static property: state kept between calls';
            }
        }
        return array_values($found);
    }

    // whether the variable at $i is assigned to (=, .=, +=, ++, [] =...)
    private static function assignedAt(array $tokens, $i)
    {
        $assign = array('=', T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL, T_CONCAT_EQUAL, T_MOD_EQUAL, T_AND_EQUAL,
            T_OR_EQUAL, T_XOR_EQUAL, T_SL_EQUAL, T_SR_EQUAL, T_POW_EQUAL, T_COALESCE_EQUAL, T_INC, T_DEC);
        $depth = 0;
        for ($j = $i + 1, $n = count($tokens); $j < $n; $j++) {
            $t = $tokens[$j];
            $id = is_array($t) ? $t[0] : $t;
            if ($id === '[') { $depth++; continue; }
            if ($id === ']') { $depth--; continue; }
            if ($depth > 0) continue;
            return in_array($id, $assign, true);
        }
        return false;
    }
}
