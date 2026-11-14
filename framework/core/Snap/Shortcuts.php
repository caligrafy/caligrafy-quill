<?php
/**
 * Go: Shortcut cards (2.23.0): a request a person saved (a capability with its inputs), run with one click.
 * Running one goes through Snap\Flow like any card: a read shows its result, a change asks for its Confirm.
 */

namespace Caligrafy\Snap;

use Caligrafy\Capabilities;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\ServiceError;

class Shortcuts
{
    const TABLE = 'caligrafy_snap_shortcuts';
    const MAX = 12;

    public static function of(Context $context)
    {
        if (!$context->userId()) {
            return array();
        }
        $cards = array();
        foreach (Database::table(self::TABLE)->where('user_id', $context->userId())->orderBy('id')->get() as $row) {
            $row = (array) $row;
            $card = self::card($row, $context);
            if ($card) {
                $cards[] = $card;
            }
        }
        return $cards;
    }

    public static function save(Context $context, $title, $capability, array $inputs)
    {
        if (!$context->userId()) {
            throw ServiceError::unauthenticated();
        }
        Cards::ask($capability, $context);   // a shortcut only to a capability this person may use
        if (Database::table(self::TABLE)->where('user_id', $context->userId())->count() >= self::MAX) {
            throw ServiceError::conflict('You have ' . self::MAX . ' shortcuts already: delete one first.');
        }
        $id = Database::table(self::TABLE)->insert(array('user_id' => $context->userId(), 'title' => $title, 'capability' => $capability,
            'inputs' => json_encode($inputs, JSON_UNESCAPED_UNICODE)));
        return self::card((array) Database::table(self::TABLE)->where('id', $id)->first(), $context);
    }

    public static function delete(Context $context, $id)
    {
        $gone = Database::table(self::TABLE)->where('id', $id)->where('user_id', $context->userId())->delete();
        if (!$gone) {
            throw ServiceError::notFound('No such shortcut');
        }
        return array('deleted' => (int) $id);
    }

    /**
     * A Show card says whether it is one of this person's shortcuts already (its id), so its control shows the state:
     * saved (with Remove) or not (Save)
     */
    public static function mark(array $card, Context $context)
    {
        if (($card['family'] ?? '') !== 'show' || ($card['variant'] ?? '') === 'notice' || !$context->userId()) {
            return $card;
        }
        $inputs = json_encode((object) array_map('strval', (array) ($card['input'] ?? array())));
        $card['shortcut'] = null;
        foreach (Database::table(self::TABLE)->where('user_id', $context->userId())->where('capability', (string) $card['capability'])->get() as $row) {
            $row = (array) $row;
            if (json_encode((object) array_map('strval', json_decode((string) $row['inputs'], true) ?: array())) === $inputs) {
                $card['shortcut'] = (int) $row['id'];
            }
        }
        return $card;
    }

    /**
     * The shortcut whose title matches what was typed, or null
     */
    public static function find(Context $context, $text)
    {
        foreach (self::of($context) as $card) {
            if (Shell::normalize($card['title']) === Shell::normalize($text)) {
                return $card;
            }
        }
        return null;
    }

    private static function card(array $row, Context $context)
    {
        $available = Capabilities::available($context);
        if (!isset($available[$row['capability']])) {
            return null;   // a permission that went away: the shortcut hides
        }
        $inputs = json_decode((string) $row['inputs'], true) ?: array();
        $summary = array();
        foreach ($inputs as $key => $value) {
            $summary[] = str_replace('_', ' ', $key) . ' ' . $value;
        }
        return array('snap' => Cards::VERSION, 'family' => 'go', 'type' => 'shortcut', 'id' => (int) $row['id'], 'title' => $row['title'], 'icon' => Cards::icon($row['capability']),
            'lead' => null, 'target' => array('capability' => $row['capability'], 'input' => (object) $inputs),
            'risk' => Cards::risk($available[$row['capability']]['capability']),
            'glance' => Cards::title($row['capability']) . ($summary ? ' · ' . implode(', ', $summary) : ''),
            'url' => Cards::url($row['capability'], $inputs), 'full' => null);
    }
}
