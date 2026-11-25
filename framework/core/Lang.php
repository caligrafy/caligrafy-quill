<?php

/**
 * Lang.php
 * The languages of an application (2.28.0): its words in one file per language, the visitor's language, right to left
 * @copyright 2026
 *
 * Files: application/lang/<code>.json, one per language of APP_LOCALES (en,fr,ar...), nested objects allowed:
 *   {"home": {"title": "Good food. Better hours."}, "deals_left": "{count} table left|{count} tables left"}
 * is read as the keys home.title and deals_left. In a view: t('home.title'), t('deals_left', ['count' => 3]),
 * html(lang=locale(), dir=lang_dir()), a link to another language: lang_url('fr'). In PHP: Lang::get(...).
 *
 * The visitor's language, in this order: ?lang=<code> in the address (remembered in the cookie "lang" for a year), the
 * cookie, the browser's Accept-Language, then APP_LOCALE (empty: the first of APP_LOCALES; neither: en). Only the codes
 * of APP_LOCALES are used. The command line and jobs use APP_LOCALE unless they call Lang::set().
 *
 * A missing word falls back to APP_LOCALE's file, then to the key itself, so a page never shows nothing.
 * {name} is replaced by the variable name; with a count, "one|other" or "zero|one|other" picks the form.
 * Arabic, Hebrew, Persian, Urdu and a few others are written right to left: lang_dir() gives rtl.
 *
 * Form messages: the capability validator speaks the visitor's language when GUMP has it (fr, es, de... not ar), and
 * the file's "validation" object replaces any of them ({"validation": {"required": "..."}}), "fields" names the fields
 * ({"fields": {"email": "Adresse e-mail"}}).
 */

namespace Caligrafy;

class Lang
{
    // the languages written right to left
    const RTL = array('ar', 'arc', 'ckb', 'dv', 'fa', 'he', 'ku', 'ps', 'sd', 'ug', 'ur', 'yi');
    const COOKIE = 'lang';

    /** @var string|null the language of this request, once known */
    private static $locale = null;
    /** @var array the words of each language read, flattened */
    private static $words = array();
    /** @var string|null where the files are; null: application/lang (tests point it elsewhere) */
    public static $path = null;
    /** @var callable|null test hook: fn($name, $value, $options) instead of setcookie */
    public static $cookie = null;

    /**
     * The languages of the app: APP_LOCALES, lowercased, in its order (en when empty)
     */
    public static function locales()
    {
        $list = array();
        foreach (explode(',', (string) self::setting('APP_LOCALES')) as $code) {
            $code = self::normalize($code);
            if ($code !== '' && !in_array($code, $list, true)) {
                $list[] = $code;
            }
        }
        return $list ?: array('en');
    }

    /**
     * The language used when none is asked for: APP_LOCALE when it is one of the app's, else the first of APP_LOCALES
     */
    public static function fallback()
    {
        $default = self::normalize((string) self::setting('APP_LOCALE'));
        $locales = self::locales();
        if (in_array($default, $locales, true)) {
            return $default;
        }
        $base = explode('-', $default)[0];          // en_US (the template's value before 2.28.1) is en
        return in_array($base, $locales, true) ? $base : $locales[0];
    }

    /**
     * The language of this request (found once: the address, the cookie, the browser, then the fallback)
     */
    public static function locale()
    {
        if (self::$locale === null) {
            self::$locale = self::detect();
        }
        return self::$locale;
    }

    /**
     * Uses a language for the rest of the request (a job writing to a person in theirs); false when it is not one of
     * the app's
     */
    public static function set($code)
    {
        $code = self::normalize((string) $code);
        if (!in_array($code, self::locales(), true)) {
            return false;
        }
        self::$locale = $code;
        return true;
    }

    /**
     * Uses a language and remembers it in the visitor's cookie for a year (a language menu)
     */
    public static function remember($code)
    {
        if (!self::set($code)) {
            return false;
        }
        $options = array('expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax', 'secure' => self::https(), 'httponly' => false);
        if (self::$cookie) {
            call_user_func(self::$cookie, self::COOKIE, self::$locale, $options);
        } elseif (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie(self::COOKIE, self::$locale, $options);
        }
        $_COOKIE[self::COOKIE] = self::$locale;
        return true;
    }

    /**
     * rtl or ltr: the direction a language is written in (this request's when none is given)
     */
    public static function dir($code = null)
    {
        $base = explode('-', self::normalize((string) ($code ?? self::locale())))[0];
        return in_array($base, self::RTL, true) ? 'rtl' : 'ltr';
    }

    /**
     * A word of the app in this request's language (or $code's): its file, then the fallback's, then the key itself.
     * $vars replace {name}; a count picks a form of "one|other" or "zero|one|other"
     */
    public static function get($key, array $vars = array(), $code = null)
    {
        $key = (string) $key;
        $code = $code === null ? self::locale() : self::normalize((string) $code);
        $words = self::words($code);
        if (!array_key_exists($key, $words)) {
            $words = self::words(self::fallback());
        }
        $text = array_key_exists($key, $words) && is_string($words[$key]) ? $words[$key] : $key;
        if (array_key_exists('count', $vars) && strpos($text, '|') !== false) {
            $forms = explode('|', $text);
            $count = (float) $vars['count'];
            if (count($forms) >= 3) {
                $text = $count == 0 ? $forms[0] : ($count == 1 ? $forms[1] : $forms[2]);
            } else {
                $text = $count == 1 ? $forms[0] : $forms[1];
            }
        }
        if ($vars) {
            $replace = array();
            foreach ($vars as $name => $value) {
                if (is_scalar($value) || $value === null) {
                    $replace['{' . $name . '}'] = (string) $value;
                }
            }
            $text = strtr($text, $replace);
        }
        return $text;
    }

    /**
     * Whether the language (this request's when none is given) has that word in its own file
     */
    public static function has($key, $code = null)
    {
        return array_key_exists((string) $key, self::words($code === null ? self::locale() : self::normalize((string) $code)));
    }

    /**
     * The words under a key, without it: group('validation') gives ['required' => ...]. The fallback's fill the gaps
     */
    public static function group($prefix, $code = null)
    {
        $code = $code === null ? self::locale() : self::normalize((string) $code);
        $prefix = rtrim((string) $prefix, '.') . '.';
        $out = array();
        foreach (array(self::fallback(), $code) as $from) {
            foreach (self::words($from) as $key => $text) {
                if (strpos($key, $prefix) === 0) {
                    $out[substr($key, strlen($prefix))] = $text;
                }
            }
        }
        return $out;
    }

    /**
     * Every word of a language (the fallback's under it), flattened: for a page's script (lang_json())
     */
    public static function all($code = null)
    {
        $code = $code === null ? self::locale() : self::normalize((string) $code);
        return array_merge(self::words(self::fallback()), self::words($code));
    }

    /**
     * The address of this page in another language (?lang=<code>, the rest of the query kept)
     */
    public static function url($code, $uri = null)
    {
        $uri = (string) ($uri ?? ($_SERVER['REQUEST_URI'] ?? '/'));
        $parts = parse_url($uri);
        $query = array();
        parse_str($parts['query'] ?? '', $query);
        $query['lang'] = self::normalize((string) $code);
        return ($parts['path'] ?? '/') . '?' . http_build_query($query);
    }

    /**
     * An amount of money in a language's way: "$1,234.50", "1 234,50 €", "١٬٢٣٤٫٥٠ US$" (intl), else "1,234.50 USD"
     */
    public static function money($amount, $currency, $code = null)
    {
        $currency = strtoupper((string) $currency);
        $code = $code === null ? self::locale() : self::normalize((string) $code);
        if (class_exists('NumberFormatter')) {
            $formatter = new \NumberFormatter($code, \NumberFormatter::CURRENCY);
            $text = $formatter->formatCurrency((float) $amount, $currency);
            if ($text !== false) {
                return $text;
            }
        }
        $decimals = fmod((float) $amount, 1) == 0 ? 0 : 2;
        return number_format((float) $amount, $decimals) . ' ' . $currency;
    }

    /**
     * A number in a language's way ("1,234.5", "1 234,5")
     */
    public static function number($value, $decimals = 0, $code = null)
    {
        $code = $code === null ? self::locale() : self::normalize((string) $code);
        if (class_exists('NumberFormatter')) {
            $formatter = new \NumberFormatter($code, \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, (int) $decimals);
            $text = $formatter->format((float) $value);
            if ($text !== false) {
                return $text;
            }
        }
        return number_format((float) $value, (int) $decimals);
    }

    /**
     * A date (a timestamp or a date string) in a language's way: short, medium, long or full (intl), else "25 Nov 2026"
     */
    public static function date($value, $style = 'medium', $code = null, $timezone = null)
    {
        $time = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if ($time === false) {
            return '';
        }
        $code = $code === null ? self::locale() : self::normalize((string) $code);
        $styles = array('short' => 3, 'medium' => 2, 'long' => 1, 'full' => 0);
        if (class_exists('IntlDateFormatter')) {
            $formatter = new \IntlDateFormatter($code, $styles[$style] ?? 2, \IntlDateFormatter::NONE, $timezone ?: date_default_timezone_get());
            $text = $formatter->format($time);
            if ($text !== false) {
                return $text;
            }
        }
        return date('j M Y', $time);
    }

    /**
     * Forgets the request's language and the words read (tests, a long-running worker between requests)
     */
    public static function reset()
    {
        self::$locale = null;
        self::$words = array();
    }

    /**
     * The words of a language, flattened (home.title), read once from application/lang/<code>.json. A file that is not
     * valid JSON gives none (doctor says why)
     */
    public static function words($code)
    {
        $code = self::normalize((string) $code);
        if (!isset(self::$words[$code])) {
            self::$words[$code] = array();
            $file = self::file($code);
            if ($file !== null && is_file($file)) {
                $data = json_decode((string) file_get_contents($file), true);
                if (is_array($data)) {
                    self::$words[$code] = self::flatten($data);
                } else {
                    error_log('Caligrafy Lang: ' . $file . ' is not valid JSON: ' . json_last_error_msg());
                }
            }
        }
        return self::$words[$code];
    }

    /**
     * The file of a language: application/lang/<code>.json (or $path)
     */
    public static function file($code)
    {
        $dir = self::$path ?? (defined('APP_PATH') ? APP_PATH . 'lang' : null);
        if ($dir === null || !preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $code)) {
            return null;
        }
        return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $code . '.json';
    }

    /**
     * The language GUMP speaks for the validator: this request's when GUMP has it (vendor/wixel/gump/lang), else en
     */
    public static function validatorLanguage()
    {
        $code = self::locale();
        foreach (array($code, explode('-', $code)[0]) as $try) {
            $gump = dirname((new \ReflectionClass('GUMP'))->getFileName()) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . $try . '.php';
            if (is_file($gump)) {
                return $try;
            }
        }
        return 'en';
    }

    // ---------------------------------------------------------------------------------------------------------------

    private static function detect()
    {
        $locales = self::locales();
        if (PHP_SAPI !== 'cli' || self::$cookie) {
            $asked = isset($_GET['lang']) && is_string($_GET['lang']) ? self::normalize($_GET['lang']) : '';
            if ($asked !== '' && in_array($asked, $locales, true)) {
                self::remember($asked);
                return $asked;
            }
            $kept = isset($_COOKIE[self::COOKIE]) && is_string($_COOKIE[self::COOKIE]) ? self::normalize($_COOKIE[self::COOKIE]) : '';
            if ($kept !== '' && in_array($kept, $locales, true)) {
                return $kept;
            }
            $browser = self::fromAcceptLanguage((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), $locales);
            if ($browser !== null) {
                return $browser;
            }
        }
        return self::fallback();
    }

    /**
     * The app's language the browser prefers (fr-CA;q=0.9 matches fr), or null
     */
    public static function fromAcceptLanguage($header, array $locales)
    {
        $wanted = array();
        foreach (explode(',', $header) as $i => $part) {
            $bits = explode(';', trim($part));
            $tag = self::normalize($bits[0]);
            if ($tag === '' || $tag === '*') {
                continue;
            }
            $q = 1.0;
            foreach (array_slice($bits, 1) as $param) {
                if (preg_match('/^\s*q=([0-9.]+)\s*$/', $param, $m)) {
                    $q = (float) $m[1];
                }
            }
            $wanted[] = array($tag, $q, $i);
        }
        usort($wanted, function ($a, $b) { return $b[1] <=> $a[1] ?: $a[2] <=> $b[2]; });
        foreach ($wanted as $w) {
            if ($w[1] <= 0) {
                continue;
            }
            if (in_array($w[0], $locales, true)) {
                return $w[0];
            }
            $base = explode('-', $w[0])[0];
            if (in_array($base, $locales, true)) {
                return $base;
            }
        }
        return null;
    }

    private static function flatten(array $data, $prefix = '')
    {
        $out = array();
        foreach ($data as $key => $value) {
            $name = $prefix . $key;
            if (is_array($value) && $value && array_keys($value) !== range(0, count($value) - 1)) {
                $out += self::flatten($value, $name . '.');
            } elseif (is_scalar($value)) {
                $out[$name] = (string) $value;
            }
        }
        return $out;
    }

    private static function normalize($code)
    {
        return strtolower(str_replace('_', '-', trim((string) $code)));
    }

    private static function setting($name)
    {
        if (defined($name)) {
            return constant($name);
        }
        $value = getenv($name);
        return $value === false ? ($_ENV[$name] ?? '') : $value;
    }

    private static function https()
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
