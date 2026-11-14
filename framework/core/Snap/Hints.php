<?php
/**
 * Hints.php
 * The optional hints of Snap Cards (2.23.0): application/config/snap.php
 * @copyright 2026
 */

namespace Caligrafy\Snap;

/**
 * Class Hints
 * Snap Cards work without any hint: every card is built from what a capability declares (its rules, descriptions,
 * readOnly and destructive). Hints only make them better, from one file the app owns, application/config/snap.php:
 *
 *   return [
 *       'about' => 'a library app',                       // what the assistant is the assistant of (the planner)
 *       'cards' => [                                      // keyed by capability name
 *           'list_books' => ['title' => 'The catalog', 'columns' => ['title', 'author.name'], 'empty' => 'No book yet.'],
 *           'reading_report' => ['async' => true, 'working' => ['Counting the books…']],
 *       ],
 *   ];
 *
 * A file that returns the cards alone (no 'cards' key) works too. The keys of a card are in the wiki page
 * "Snap Cards and the Assistant": title, lead, submit, columns, labels, hide, values, choices, open, full, empty,
 * variant, noun, icon, done, undo, next, applies, empty_next, alert, insight, discover, working, async, consequence,
 * confirm. A capability is async when #[Capability(async: true)] says so, or its hint does.
 */
class Hints
{
    private static $file = null;

    /**
     * The hints of one capability (an empty array when it has none)
     */
    public static function for($name)
    {
        $cards = self::file()['cards'] ?? array();
        return (array) ($cards[$name] ?? array());
    }

    /**
     * What the app is about, for the assistant ("a library app"); empty when the file does not say
     */
    public static function about()
    {
        return (string) (self::file()['about'] ?? '');
    }

    /**
     * Forgets the file read (tests, and a file that changed)
     */
    public static function forget()
    {
        self::$file = null;
    }

    private static function file()
    {
        if (self::$file === null) {
            $path = (defined('CONFIG_PATH') ? CONFIG_PATH : (defined('APP_PATH') ? APP_PATH . 'config' . DIRECTORY_SEPARATOR : '')) . 'snap.php';
            $data = $path !== 'snap.php' && is_file($path) ? (array) require $path : array();
            // a file that returns the cards alone is the cards
            self::$file = isset($data['cards']) || isset($data['about']) ? $data : array('cards' => $data);
        }
        return self::$file;
    }
}
