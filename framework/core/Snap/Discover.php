<?php
/**
 * Discover (2.23.0): Home surfaces the things this application is about, to
 * invite browsing, the way a shop surfaces products. Out of the box, from one hint on a list capability ('discover'):
 *   - for you      more like what this person looked at (only when they turned noticing on: what they opened is in
 *                  snap_events), e.g. another book by the author of a book they opened
 *   - new          what was added last
 *   - the rule     a business rule of the app: only what is available now (not in another list, e.g. not lent)
 * Each pick is a Home card that opens the object in the task space; each says why it is there.
 *
 * The hint: 'discover' => array(
 *     'open' => 'get_book', 'key' => 'id',                     how an object opens (capability, the field it takes)
 *     'title' => 'title', 'meta' => array('author.name', ...),   what a pick shows
 *     'new' => 'created_at',                                   the field that says when it was added
 *     'related' => 'author.id', 'by' => 'author.name',          what "more like it" shares, and its name
 *     'opened' => array('get_book' => 'id', 'lend_book' => 'book_id'),   the requests that mean "looked at it"
 *     'unavailable' => array('capability' => 'list_loans', 'field' => 'book_id'),   the business rule
 *     'title_section' => 'On the shelf for you')
 */

namespace Caligrafy\Snap;

use Caligrafy\Capabilities;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\ServiceError;

class Discover
{
    const PICKS = 6;

    /**
     * @return array [title, cards, nudge] for each list capability with a 'discover' hint this person may read
     */
    public static function sections(Context $context)
    {
        $available = Capabilities::available($context);
        $sections = array();
        foreach ($available as $name => $entry) {
            $hint = Hints::for($name)['discover'] ?? null;
            if (!$hint || !$entry['capability']->readOnly || !isset($available[$hint['open']])) {
                continue;
            }
            $picks = self::picks($name, $hint, $context, $available);
            if ($picks) {
                $sections[] = array('title' => $hint['title_section'] ?? 'For you', 'cards' => $picks,
                    'more' => array('text' => 'See all', 'run' => array('capability' => $name, 'input' => (object) array())));
            }
        }
        return $sections;
    }

    private static function picks($name, array $hint, Context $context, array $available)
    {
        try {
            $rows = self::rows($name, $context);
        } catch (ServiceError $e) {
            return array();
        }
        $key = $hint['key'] ?? 'id';
        // the business rule: only what is available now (when this person may see what is not)
        $out = array();
        if (!empty($hint['unavailable']) && isset($available[$hint['unavailable']['capability']])) {
            try {
                foreach (self::rows($hint['unavailable']['capability'], $context) as $r) {
                    $out[(string) self::dig($r, $hint['unavailable']['field'])] = true;
                }
            } catch (ServiceError $e) {
            }
        }
        $byKey = array();
        foreach ($rows as $r) {
            $byKey[(string) self::dig($r, $key)] = $r;
        }
        $candidates = array_filter($byKey, function ($k) use ($out) { return !isset($out[$k]); }, ARRAY_FILTER_USE_KEY);

        $picks = array();
        // for you: more like what this person opened (noticing on only), newest first
        $opened = self::opened($context, (array) ($hint['opened'] ?? array($hint['open'] => $key)));
        if (!empty($hint['related'])) {
            foreach ($opened as $id) {
                if (!isset($byKey[$id])) continue;
                $like = self::dig($byKey[$id], $hint['related']);
                foreach ($candidates as $k => $r) {
                    $k = (string) $k;   // PHP makes numeric keys integers
                    if (count($picks) >= 3) break 2;
                    if ($k !== $id && !in_array($k, $opened, true) && !isset($picks[$k]) && self::dig($r, $hint['related']) === $like) {
                        $picks[$k] = array($r, 'Because you looked at ' . self::dig($byKey[$id], $hint['title']), 'sparkles');
                    }
                }
            }
        }
        // what they already looked at is not offered again (unless nothing else is left)
        $fresh = array_filter($candidates, function ($k) use ($opened) { return !in_array((string) $k, $opened, true); }, ARRAY_FILTER_USE_KEY);
        if ($fresh) {
            $candidates = $fresh;
        }
        // new: the latest additions (within 30 days)
        if (!empty($hint['new'])) {
            $recent = array_filter($candidates, function ($r) use ($hint) { return strtotime((string) self::dig($r, $hint['new'])) > time() - 30 * 86400; });
            uasort($recent, function ($a, $b) use ($hint, $key) {
                return array(self::dig($b, $hint['new']), (int) self::dig($b, $key)) <=> array(self::dig($a, $hint['new']), (int) self::dig($a, $key));
            });
            foreach (array_slice($recent, 0, 2, true) as $k => $r) {
                if (!isset($picks[$k])) $picks[$k] = array($r, 'New', 'badge-plus');
            }
        }
        // the rest, available now: a different few each day, so Home never stays the same
        $rest = array_diff_key($candidates, $picks);
        uksort($rest, function ($a, $b) { return crc32($a . date('Y-m-d')) <=> crc32($b . date('Y-m-d')); });
        foreach ($rest as $k => $r) {
            if (count($picks) >= self::PICKS) break;
            $picks[$k] = array($r, empty($hint['unavailable']) ? 'In the catalog' : ($hint['available_label'] ?? 'Available now'), 'circle-check');
        }

        $cards = array();
        foreach ($picks as $k => [$row, $reason, $badge]) {
            $k = (string) $k;
            $meta = array_values(array_filter(array_map(function ($f) use ($row) { return (string) self::dig($row, $f); }, (array) ($hint['meta'] ?? array())), 'strlen'));
            $cards[] = array('snap' => Cards::VERSION, 'family' => 'go', 'type' => 'object', 'capability' => $hint['open'],
                'icon' => Cards::icon($name), 'title' => (string) self::dig($row, $hint['title']), 'glance' => implode(' · ', $meta),
                'reason' => $reason, 'personal' => strpos($reason, 'Because') === 0,
                'target' => array('capability' => $hint['open'], 'input' => (object) array($key => $k)),
                'key' => 'object:' . $hint['open'] . ':' . $k, 'subject' => 'object:' . $hint['open'] . ':' . $k, 'url' => null, 'full' => null);
        }
        return $cards;
    }

    // the keys of the objects this person opened, most recent first: only what noticing kept (it is their choice)
    private static function opened(Context $context, array $from)
    {
        if (!$context->userId() || !Offers::noticing($context)) {
            return array();
        }
        $ids = array();
        foreach (Database::table('caligrafy_snap_events')->where('user_id', $context->userId())->orderBy('id', 'desc')->limit(200)->get() as $e) {
            $e = (array) $e;
            if (!isset($from[$e['capability']])) continue;
            $inputs = json_decode((string) $e['inputs'], true) ?: array();
            $id = (string) ($inputs[$from[$e['capability']]] ?? '');
            if ($id !== '' && !in_array($id, $ids, true)) $ids[] = $id;
        }
        return $ids;
    }

    private static function rows($name, Context $context)
    {
        $plain = Reads::plain($name, $context);
        if (is_array($plain) && isset($plain['data']) && is_array($plain['data'])) {
            $plain = $plain['data'];
        }
        return is_array($plain) ? array_values(array_filter($plain, 'is_array')) : array();
    }

    public static function dig($row, $path)
    {
        foreach (explode('.', (string) $path) as $p) {
            if (!is_array($row) || !array_key_exists($p, $row)) return null;
            $row = $row[$p];
        }
        return is_scalar($row) ? (string) $row : $row;
    }
}
