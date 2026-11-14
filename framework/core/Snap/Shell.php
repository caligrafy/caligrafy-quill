<?php
/**
 * The Snap shell's fast path (2.23.0): what it can answer without a model, at once and for free:
 *   - "What can I do?" (help, menu, ?): the cards this person may use, by family
 *   - the title of one of their shortcuts: that Go: Shortcut, run
 *   - the title or the name of a card ("the catalog", "lend a book", "list_loans"): that card
 * Anything else goes to the planner (Claude) when it is on.
 */

namespace Caligrafy\Snap;

use Caligrafy\Capabilities;
use Caligrafy\Context;

class Shell
{
    const MENU = array('what can i do', 'what else can i do', 'what can you do', 'help', 'menu', '?', 'options');

    /**
     * @return array|null ['caption', 'cards', 'menu'?, 'via' => 'fast'] or null when only the planner can answer
     */
    // the verbs people use on the record in focus, and the capability verb each one means
    const ON_IT = array('delete' => 'delete', 'remove' => 'delete', 'change' => 'update', 'edit' => 'update', 'update' => 'update', 'fix' => 'update',
        'lend' => 'lend', 'loan' => 'lend', 'return' => 'return', 'open' => 'get', 'show' => 'get', 'see' => 'get');

    /**
     * A follow-up on the record the conversation is about, at once: "delete it", "change this", "lend that one".
     * Anything longer ("lend it to Sam") goes to the planner, with the conversation's memory.
     * @param array|null $focus ['capability' => 'get_book', 'input' => ['id' => 7]]
     */
    public static function onFocus($text, Context $context, $focus)
    {
        if (!$focus || !preg_match('/^(\w+)\s+(it|this|that|this one|that one|this \w+|that \w+)$/', self::normalize($text), $m) || !isset(self::ON_IT[$m[1]])) {
            return null;
        }
        if (!preg_match('/^[a-z]+_(\w+)$/', $focus['capability'], $t)) {
            return null;
        }
        $thing = in_array(explode('_', $focus['capability'])[0], array('list', 'search'), true) ? Cards::singular($t[1]) : $t[1];
        $name = self::ON_IT[$m[1]] . '_' . $thing;
        $id = $focus['input']['id'] ?? ($focus['input'][$thing . '_id'] ?? null);
        if (!isset(Capabilities::available($context)[$name]) || $id === null) {
            return null;
        }
        $schema = Capabilities::schema($name);
        $field = $schema && array_key_exists('id', (array) $schema['properties']) ? 'id' : $thing . '_id';
        [$card] = Flow::next($name, array($field => $id), $context);
        return array('caption' => '', 'cards' => array($card), 'via' => 'fast');
    }

    public static function fast($text, Context $context)
    {
        $said = self::normalize($text);
        if (in_array($said, self::MENU, true) || $said === '') {
            return array('caption' => 'Here is everything you can do. Pick one, or say it in your own words.', 'cards' => array(self::menuCard($context)), 'via' => 'fast');
        }
        if ($shortcut = Shortcuts::find($context, $text)) {
            [$card] = Flow::next($shortcut['target']['capability'], (array) $shortcut['target']['input'], $context);
            return array('caption' => '', 'cards' => array($card), 'via' => 'fast');   // the card's own line says it
        }
        foreach (self::names($context) as $name => $titles) {
            if (in_array($said, $titles, true)) {
                [$card] = Flow::next($name, array(), $context);
                return array('caption' => '', 'cards' => array($card), 'via' => 'fast');
            }
        }
        return null;
    }

    /**
     * The menu as a card: Go: Shortcut in its menu variant (ready-made starts the system offers, not ones you saved)
     */
    public static function menuCard(Context $context)
    {
        $items = self::menu($context);
        return array('snap' => Cards::VERSION, 'family' => 'go', 'type' => 'shortcut', 'variant' => 'menu', 'capability' => 'snap_menu',
            'title' => 'Everything you can do', 'lead' => null, 'items' => $items, 'risk' => 'read',
            'glance' => count($items) . ' things you can do', 'url' => null, 'full' => null);
    }

    /**
     * The menu: every card this person may start, with its family and type
     */
    public static function menu(Context $context)
    {
        $menu = array();
        foreach (Capabilities::available($context) as $name => $entry) {
            if (preg_match(Cards::HIDDEN, $name) || $entry['capability']->webOnly) {
                continue;
            }
            $ask = Cards::ask($name, $context);
            $family = $ask['risk'] === 'read' && !Cards::needsInput($ask, array()) ? 'show' : 'ask';
            $menu[] = array('capability' => $name, 'title' => Cards::title($name), 'family' => $family, 'risk' => $ask['risk'], 'icon' => Cards::icon($name));
        }
        usort($menu, function ($a, $b) { return array($a['family'] === 'ask', $a['title']) <=> array($b['family'] === 'ask', $b['title']); });
        return $menu;
    }

    public static function normalize($text)
    {
        $text = strtolower(trim((string) $text));
        if ($text === '?') {
            return '?';
        }
        $text = preg_replace('/[^a-z0-9_ ]+/', ' ', $text);   // punctuation never changes what was asked ("What can I do?")
        return trim(preg_replace('/\s+/', ' ', preg_replace('/^(the|show me|show|open|please)\s+/', '', $text)));
    }

    // each card's names a person may type: its title, its capability name, both without "the"
    private static function names(Context $context)
    {
        $names = array();
        foreach (Capabilities::available($context) as $name => $entry) {
            if (preg_match(Cards::HIDDEN, $name) || $entry['capability']->webOnly) {
                continue;
            }
            $names[$name] = array_unique(array(self::normalize(Cards::title($name)), self::normalize($name), self::normalize(str_replace('_', ' ', $name))));
        }
        return $names;
    }
}
