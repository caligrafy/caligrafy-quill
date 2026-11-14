<?php
/**
 * The Snap shell's two spaces (2.23.0):
 *   Home       the starting workspace: the agent's message for the situation, prompt suggestions (and "What else can
 *              I do"), the input; then the cards: Needs you (alerts, tasks waiting on you, changes to confirm),
 *              For you (offers), In progress (tasks), Shortcuts. Only the sections that have something.
 *   Task space the engagement: the conversation minimized to the agent's latest answer (back and forth, history,
 *              rating, a different answer, the input on demand); only the cards of that answer.
 * Each turn is kept (snap_turns): what was asked, the caption, the cards, the suggestions, how it was answered.
 */

namespace Caligrafy\Snap;

use Caligrafy\Capabilities;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\ServiceError;

class Conversation
{
    const TABLE = 'caligrafy_snap_turns';
    const MORE = 'What else can I do?';
    const HOME = 'Back to Home';

    /**
     * Whether Snap's tables are there (php caligrafer.php platform install): checked once per request
     */
    public static function installed()
    {
        static $installed = null;
        if ($installed === null) {
            try {
                Database::table(self::TABLE)->limit(1)->get();
                $installed = true;
            } catch (\Throwable $e) {
                $installed = false;
            }
        }
        return $installed;
    }

    // ---- turns ----

    public static function save(Context $context, $conversation, $text, array $answer)
    {
        $id = Database::table(self::TABLE)->insert(array('user_id' => (int) $context->userId(), 'conversation' => $conversation, 'text' => mb_substr((string) $text, 0, 500),
            'caption' => (string) ($answer['caption'] ?? ''), 'cards' => json_encode($answer['cards'] ?? array(), JSON_UNESCAPED_UNICODE),
            'suggestions' => json_encode($answer['suggestions'] ?? array(), JSON_UNESCAPED_UNICODE), 'via' => (string) ($answer['via'] ?? 'fast')));
        return (int) $id;
    }

    public static function turns(Context $context, $conversation)
    {
        $rows = Database::table(self::TABLE)->where('user_id', (int) $context->userId())->where('conversation', $conversation)->orderBy('id')->get();
        if (!$rows) {
            throw ServiceError::notFound('No such conversation');
        }
        return array_map(function ($r) use ($context) { return self::fresh(self::turn((array) $r), $context); }, $rows);
    }

    // a turn read again: its records only offer what can be done to them now (a book lent since offers its return)
    private static function fresh(array $turn, Context $context)
    {
        foreach ($turn['cards'] as &$card) {
            if (!empty($card['actions']) && isset(((array) ($card['input'] ?? array()))['id'])) {
                $record = array('id' => ((array) $card['input'])['id']);
                $card['actions'] = array_values(array_filter($card['actions'], function ($a) use ($record, $context) { return Cards::applies($a['capability'], $record, $context); }));
            }
        }
        unset($card);
        return $turn;
    }

    public static function last(Context $context, $turnId)
    {
        $row = Database::table(self::TABLE)->where('id', (int) $turnId)->where('user_id', (int) $context->userId())->first();
        if (!$row) {
            throw ServiceError::notFound('No such turn');
        }
        return self::fresh(self::turn((array) $row), $context);
    }

    /**
     * This person's conversations, newest first: their first question is their title
     */
    public static function history(Context $context, $limit = 20)
    {
        $seen = array();
        $list = array();
        foreach (Database::table(self::TABLE)->where('user_id', (int) $context->userId())->orderBy('id', 'desc')->limit(400)->get() as $r) {
            $r = (array) $r;
            if (isset($seen[$r['conversation']])) {
                $seen[$r['conversation']]['turns']++;
                $seen[$r['conversation']]['title'] = $r['text'];   // older turns come later: the first question wins
                continue;
            }
            $seen[$r['conversation']] = array('conversation' => $r['conversation'], 'title' => $r['text'], 'turns' => 1, 'last_at' => $r['created_at']);
            $list[] = $r['conversation'];
        }
        return array_slice(array_map(function ($c) use (&$seen) { return $seen[$c]; }, $list), 0, $limit);
    }

    public static function rate(Context $context, $turnId, $rating)
    {
        $done = Database::table(self::TABLE)->where('id', (int) $turnId)->where('user_id', (int) $context->userId())->update(array('rating' => $rating));
        if (!$done) {
            throw ServiceError::notFound('No such turn');
        }
        return array('turn' => (int) $turnId, 'rating' => $rating);
    }

    // ---- memory: what the next ask is read with ----

    /**
     * The conversation's memory for the next ask: its earlier turns (what was asked, what was shown), what is on the
     * person's screen now, what they chose since (opened, picked, confirmed), and what it is about (the focus: "it").
     * What the page sends is checked and cut to size; it is the person's own context, and the planner is told it is
     * data from the app, never instructions.
     * @return array ['focus' => array|null, 'text' => string] (text empty: nothing to remember)
     */
    public static function memory(Context $context, $conversation, $sent)
    {
        $sent = is_string($sent) ? (json_decode($sent, true) ?: array()) : (array) $sent;
        $available = Capabilities::available($context);
        $card = function ($c) use ($available) {
            $c = (array) $c;
            $name = (string) ($c['capability'] ?? '');
            if (!preg_match('/^[a-z_]{2,64}$/', $name) || !isset($available[$name])) return null;
            $input = array();
            foreach (array_slice((array) ($c['input'] ?? array()), 0, 10, true) as $k => $v) {
                if (preg_match('/^[a-z_]{1,40}$/', (string) $k) && is_scalar($v)) $input[$k] = mb_substr((string) $v, 0, 200);
            }
            $title = mb_substr(trim((string) ($c['title'] ?? '')), 0, 120);
            $about = mb_substr(trim((string) ($c['about'] ?? '')), 0, 120);
            return array('capability' => $name, 'title' => $title, 'about' => $about, 'input' => $input);
        };
        $describe = function ($c) {
            $args = array();
            foreach ($c['input'] as $k => $v) $args[] = $k . '=' . $v;
            $what = trim(($c['about'] ?? '') !== '' ? $c['about'] : $c['title']);
            return ($what !== '' ? $what . ' ' : '') . '(' . $c['capability'] . ($args ? ' ' . implode(', ', $args) : '') . ')';
        };
        $lines = array();
        if ($conversation !== '' && $context->userId()) {
            $rows = Database::table(self::TABLE)->where('user_id', (int) $context->userId())->where('conversation', $conversation)->orderBy('id', 'desc')->limit(6)->get();
            foreach (array_reverse($rows) as $r) {
                $r = (array) $r;
                $shown = array();
                foreach (array_slice(json_decode((string) $r['cards'], true) ?: array(), 0, 3) as $c) {
                    $c = $card(array('capability' => $c['capability'] ?? '', 'title' => $c['title'] ?? '', 'about' => $c['heading'] ?? ($c['subject'] ?? ''), 'input' => $c['input'] ?? array()));
                    if ($c) $shown[] = $describe($c);
                }
                $lines[] = '- They asked "' . mb_substr((string) $r['text'], 0, 200) . '". You showed: ' . ($shown ? implode('; ', $shown) : 'nothing') . '.';
            }
        }
        $screen = array_values(array_filter(array_map($card, array_slice((array) ($sent['screen'] ?? array()), 0, 4))));
        $focus = !empty($sent['focus']) ? $card(array('capability' => $sent['focus']['capability'] ?? '', 'title' => '', 'about' => $sent['focus']['title'] ?? '', 'input' => $sent['focus']['input'] ?? array())) : null;
        $chosen = array_values(array_filter(array_map(function ($s) { return is_string($s) ? mb_substr(trim($s), 0, 120) : ''; }, array_slice((array) ($sent['selections'] ?? array()), -8)), 'strlen'));
        $text = '';
        if ($lines) $text .= "The conversation so far (oldest first):\n" . implode("\n", $lines) . "\n";
        if ($screen) $text .= 'On their screen now: ' . implode('; ', array_map($describe, $screen)) . ".\n";
        if ($chosen) $text .= 'What they did since: ' . implode('; ', $chosen) . ".\n";
        if ($focus) $text .= 'The conversation is about: ' . $describe($focus) . '. "It", "this", "that one" mean this unless they say otherwise.' . "\n";
        return array('focus' => $focus, 'text' => $text);
    }

    // ---- suggestions ----

    /**
     * What the person may want to do next, beyond the buttons of the cards on screen: the optional details a form
     * still takes, what follows a change (another one, the list it belongs to), what a list leads to, then
     * "What else can I do?". Each suggestion carries what it runs, so a click is at once a card, without a model.
     */
    public static function suggestions(array $cards, Context $context)
    {
        $available = Capabilities::available($context);
        $out = array();
        $run = function ($text, $capability, array $input = array()) use (&$out, $available) {
            if (!isset($available[$capability]) || isset($out[$text]) || count($out) >= 4) return;
            $out[$text] = array('text' => $text, 'icon' => Cards::icon($capability), 'run' => array('capability' => $capability, 'input' => (object) $input));
        };
        foreach ($cards as $card) {
            if (($card['type'] ?? '') === 'task' && !empty($card['result'])) {
                $card = $card['result'];   // a finished task leads where its result leads
            }
            $type = $card['type'] ?? '';
            $name = (string) ($card['capability'] ?? '');
            if ($type === 'form') {
                foreach ((array) ($card['optional'] ?? array()) as $field) {
                    foreach ($card['fields'] as $f) {
                        if ($f['name'] === $field && count($out) < 2) {
                            $label = $f['label'] === strtoupper($f['label']) ? $f['label'] : strtolower($f['label']);   // ISBN stays ISBN
                            $out['Add ' . $label] = array('text' => 'Add ' . $label, 'icon' => 'plus', 'reveal' => $f['name']);
                        }
                    }
                }
                continue;
            }
            // nothing came back (an empty list, a search without results, a record that is gone): ways out first
            if (self::empty($card)) {
                foreach (self::recover($card, $available) as [$text, $capability, $input]) {
                    $run($text, $capability, $input);
                }
            }
            if (($card['variant'] ?? '') === 'notice' && ($card['tone'] ?? '') === 'info') {
                $card['done'] = true;   // a change that went through (a delete): it leads where any change does
            }
            if (in_array($type, array('confirm', 'choice'), true) || (($card['variant'] ?? '') === 'notice' && empty($card['done'])) || ($card['variant'] ?? '') === 'menu') {
                continue;   // in the middle of something, or nothing to lead to
            }
            if (in_array($type, array('suggestion', 'routine', 'shortcut'), true)) {
                continue;   // an offer's next steps are its own buttons: never said twice
            }
            // a record's actions (Change, Delete, Lend) are buttons on its card: the suggestions go beyond them
            foreach (self::related($name, $card, $available) as [$text, $capability]) {
                $run($text, $capability);
            }
        }
        // never a dead end: when the cards lead nowhere new, a few starting points (not what is on screen), then Home
        $onScreen = array_map(function ($c) { return $c['capability'] ?? ''; }, $cards);
        foreach (Shell::menu($context) as $item) {
            if (count($out) >= 2) break;
            if ($item['family'] === 'show' && !in_array($item['capability'], $onScreen, true)) $run($item['title'], $item['capability']);
        }
        $list = array_values($out);
        $list[] = array('text' => self::HOME, 'icon' => 'house', 'home' => true);
        $list[] = array('text' => self::MORE, 'icon' => 'sparkles');
        return $list;
    }

    // an answer with nothing in it: a list without rows, or a record that could not be found
    public static function empty(array $card)
    {
        return (($card['type'] ?? '') === 'list' && empty($card['rows'])) || (($card['variant'] ?? '') === 'notice' && ($card['tone'] ?? '') === 'not_found');
    }

    /**
     * Ways out of an empty answer, out of the box, from the names of the capabilities and what was asked:
     *   a search without results   each of its words alone (broader), add what was looked for (prefilled)
     *   a filtered list            the list without its filter, add one that fits the filter (prefilled)
     *   an empty list              what fills it (hint 'empty_next': nothing lent -> Lend a book), add one
     *   a record that is gone      find it, or the list
     * @return array [[text, capability, input]]
     */
    private static function recover(array $card, array $available)
    {
        $name = (string) ($card['capability'] ?? '');
        $input = (array) ($card['input'] ?? array());
        if (!preg_match('/^([a-z]+)_(\w+)$/', $name, $m)) {
            return array();
        }
        $plural = in_array($m[1], array('list', 'search'), true) ? $m[2] : Cards::plural($m[2]);
        $one = Cards::singular($plural);
        $list = 'list_' . $plural;
        $search = 'search_' . $plural;
        $create = 'create_' . $one;
        $props = function ($capability) { $schema = Capabilities::schema($capability); return $schema ? (array) $schema['properties'] : array(); };
        $out = array();
        // what was looked for: the search's words
        $query = '';
        if ($m[1] === 'search') {
            foreach ($props($name) as $field => $p) {
                if (($p['type'] ?? '') === 'string' && is_string($input[$field] ?? null)) { $query = trim($input[$field]); $key = $field; break; }
            }
        }
        if ($query !== '') {
            $words = array_values(array_filter(preg_split('/\s+/', $query), function ($w) { return mb_strlen($w) >= 3; }));
            if (count($words) > 1) {
                foreach (array_slice($words, 0, 2) as $w) {
                    $out[] = array('Search for "' . $w . '"', $name, array($key => $w));
                }
            }
            if (isset($available[$create])) {
                $named = array_key_exists('title', $props($create)) ? 'title' : (array_key_exists('name', $props($create)) ? 'name' : null);
                if ($named) $out[] = array('Add "' . $query . '"', $create, array($named => $query));
            }
        }
        // a filtered list: add one that fits the filter, or see them all
        $filters = array_diff_key($input, array('page' => true));
        if ($m[1] === 'list' && $filters && isset($available[$create])) {
            $fits = array_intersect_key($filters, $props($create));
            if ($fits) $out[] = array(Cards::title($create), $create, $fits);
        }
        foreach ((array) (Hints::for($name)['empty_next'] ?? array()) as $next) {
            if (isset($available[$next])) $out[] = array(Cards::title($next), $next, array());
        }
        if (($filters || $m[1] !== 'list') && isset($available[$list])) {
            $out[] = array('See ' . self::lower(Cards::title($list)), $list, array());
        }
        if ($m[1] === 'get' && isset($available[$search])) {
            $out[] = array(Cards::title($search), $search, array());
        }
        if ($m[1] === 'list' && !$filters && isset($available[$create])) {
            $out[] = array(Cards::title($create), $create, array());
        }
        return $out;
    }

    /**
     * What a card naturally leads to, from the names of the capabilities (create_book, list_books, search_books) and
     * the hint 'next': another one after adding, the list after a change, adding or finding from a list
     * @return array [[text, capability]]
     */
    private static function related($name, array $card, array $available)
    {
        $out = array();
        $see = function ($capability) use ($available) { return 'See ' . self::lower(Cards::title($capability)); };
        foreach ((array) (Hints::for($name)['next'] ?? array()) as $next) {
            if (isset($available[$next])) $out[] = array($see($next), $next);
        }
        if (preg_match('/^(create|update|delete|get|lend|return)_(\w+)$/', $name, $m)) {
            $list = 'list_' . Cards::plural($m[2]);
            if ($m[1] === 'create' && !empty($card['done'])) $out[] = array('Add another ' . str_replace('_', ' ', $m[2]), $name);
            if (isset($available[$list]) && (!empty($card['done']) || $m[1] === 'get')) $out[] = array($see($list), $list);
        } elseif (preg_match('/^(list|search)_(\w+)$/', $name, $m)) {
            $one = Cards::singular($m[2]);
            if (isset($available['create_' . $one])) $out[] = array(Cards::title('create_' . $one), 'create_' . $one);
            if ($m[1] === 'list' && isset($available['search_' . $m[2]])) $out[] = array(Cards::title('search_' . $m[2]), 'search_' . $m[2]);
        }
        return $out;
    }

    // "The catalog" -> "the catalog"; "ISBN lookup" stays
    public static function lower($text)
    {
        return preg_match('/^[A-Z][a-z]/', (string) $text) ? lcfirst($text) : (string) $text;
    }

    // ---- home ----

    /**
     * Home, for this person, now: an orchestrator of what to concentrate on, in this order:
     *   Needs you      alerts, tasks waiting on the person, changes to confirm (the focus)
     *   insights       numbers, each with the action it calls for (none without one)
     *   For you        offers (what they usually do now, routines)
     *   In progress    running tasks
     *   discover       the things this app is about, to browse: more like what they looked at, new, available
     *   Just done, Your shortcuts
     * The message says the focus and nudges the conversation on; the suggestions start with the focus's action and a
     * pick to look at. One card per capability (the latest task of a capability is its only card) and per object.
     * Each card carries its key: opening it (snap_ask open) shows it in the task space.
     */
    public static function home(Context $context)
    {
        $tasks = array();
        foreach (Tasks::of($context, 30) as $task) {
            $tasks[$task['of']] = $tasks[$task['of']] ?? $task;   // newest first: the latest of each capability
        }
        $tasks = array_values($tasks);
        $byStatus = function (array $statuses) use ($tasks) { return array_values(array_filter($tasks, function ($t) use ($statuses) { return in_array($t['status'], $statuses, true); })); };
        $done = array_values(array_filter($byStatus(array('done')), function ($t) { return strtotime((string) ($t['steps'][2]['at'] ?? '')) > time() - 86400; }));
        $sections = array(
            // each section has one card template, chosen by what the person does with it (the card grammar):
            //   banner (act now, full width) · offer (decide, halves) · progress (watch, full-width rows)
            //   object (browse, thirds) · update (revisit, full-width rows) · shortcut (go, quarters)
            array('key' => 'needs', 'title' => 'Needs you', 'template' => 'banner', 'cards' => self::needs($context, $byStatus(array('needs_input', 'failed')))),
            array('key' => 'for-you', 'title' => 'For you', 'template' => 'offer', 'cards' => Offers::offers($context)),
            array('key' => 'progress', 'title' => 'In progress', 'template' => 'progress', 'cards' => $byStatus(array('queued', 'running'))),
        );
        foreach (Discover::sections($context) as $i => $d) {
            $sections[] = array('key' => 'discover' . ($i ? '-' . $i : ''), 'title' => $d['title'], 'template' => 'object', 'cards' => $d['cards'], 'more' => $d['more']);
        }
        $sections[] = array('key' => 'done', 'title' => 'Just done', 'template' => 'update', 'cards' => $done);
        $sections[] = array('key' => 'shortcuts', 'title' => 'Your shortcuts', 'template' => 'shortcut', 'cards' => Shortcuts::of($context),
            'more' => array('text' => 'Everything you can do', 'ask' => self::MORE));
        $seen = array();
        foreach ($sections as &$section) {
            $section['cards'] = array_values(array_filter(array_map(array(self::class, 'keyed'), $section['cards']), function ($c) use (&$seen) {
                if (isset($seen[$c['subject']]) || isset($seen['key:' . $c['key']])) return false;
                $seen[$c['subject']] = $seen['key:' . $c['key']] = true;
                return true;
            }));
        }
        unset($section);
        $by = array_column($sections, null, 'key');
        $name = $context->user ? explode('@', (string) ($context->user->username ?? ''))[0] : '';
        $shortcuts = $by['shortcuts']['cards'];
        $lines = self::message($by);
        $hour = (int) date('G');
        $hello = $hour < 5 ? 'Hello' : ($hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening'));
        return array('greeting' => $hello . ($name !== '' ? ', ' . ucfirst($name) : ''), 'lines' => $lines, 'message' => implode(' ', $lines),
            'insights' => self::insights($context), 'progress' => self::progress($context),
            'suggestions' => self::homeSuggestions($context, $shortcuts, $by),
            'sections' => array_values(array_filter($sections, function ($s) { return count($s['cards']) > 0; })));
    }

    /**
     * What Home says, as lines (two at most, one idea each): what needs the person now, on a line of its own, then
     * one nudge to keep going (something to look at). Nothing needs them: the first line says so only when there is
     * no nudge to give.
     * @return array the lines
     */
    private static function message(array $by)
    {
        $needs = $by['needs']['cards'];
        $offers = $by['for-you']['cards'];
        $running = $by['progress']['cards'];
        $first = $needs[0] ?? null;
        $what = $first ? rtrim((string) ($first['question'] ?? $first['glance'] ?? $first['title']), '.?') : '';
        if (count($needs) === 1) {
            $focus = 'One thing needs you: ' . self::lower($what) . '.';
        } elseif ($needs) {
            $focus = count($needs) . ' things need you, starting with ' . self::lower($what) . '.';
        } elseif ($offers) {
            $focus = (string) ($offers[0]['question'] ?? 'I have a suggestion for you.');
        } elseif ($running) {
            $focus = 'Your ' . self::lower($running[0]['title']) . ' is on its way; I will tell you when it is ready.';
        } else {
            $focus = 'Nothing needs you right now.';
        }
        $pick = null;
        foreach ($by as $key => $section) {
            if (strpos($key, 'discover') === 0 && !empty($section['cards'])) {
                $pick = $section['cards'][0];
                break;
            }
        }
        if ($pick && !empty($pick['personal'])) {
            $nudge = 'Since ' . lcfirst(substr($pick['reason'], strlen('Because '))) . ', you might like ' . $pick['title'] . '.';
        } elseif ($pick && $pick['reason'] === 'New') {
            $nudge = 'New: ' . $pick['title'] . '.';
        } else {
            $nudge = $needs ? '' : 'What do you want to do?';
        }
        if (!$needs && !$offers && !$running && $nudge !== 'What do you want to do?') {
            return array($nudge);   // nothing to concentrate on: the nudge alone
        }
        return array_values(array_filter(array($focus, $nudge), 'strlen'));
    }

    /**
     * A Home card opened in the task space, by its key: a shortcut runs what it saved; anything else is shown in full,
     * with the assistant's line
     * @return array the answer: caption, cards, via, text
     */
    public static function open(Context $context, $key)
    {
        foreach (self::home($context)['sections'] as $section) {
            foreach ($section['cards'] as $card) {
                if ($card['key'] !== $key) {
                    continue;
                }
                if ($card['type'] === 'shortcut' || $card['type'] === 'object') {
                    [$next] = Flow::next($card['target']['capability'], (array) $card['target']['input'], $context);
                    return array('caption' => '', 'cards' => array($next), 'via' => 'open', 'text' => $card['title']);
                }
                $card = Say::on($card);
                $card['celebrate'] = false;   // opened again: it was celebrated when it happened
                return array('caption' => '', 'cards' => array($card), 'via' => 'open', 'text' => $card['title']);
            }
        }
        throw ServiceError::notFound('That card is no longer on your Home.');
    }

    // a Home card with its key (to open it) and its subject (one card per capability)
    private static function keyed(array $card)
    {
        switch ($card['type']) {
            case 'task':
                $card['key'] = 'task:' . $card['id'];
                $card['subject'] = 'cap:' . $card['of'];
                break;
            case 'object':
                break;   // Snap\Discover keyed it
            case 'shortcut':
                $card['key'] = 'shortcut:' . $card['id'];
                $card['subject'] = 'cap:' . $card['target']['capability'] . ':' . json_encode($card['target']['input']);
                break;
            default:
                $alert = ($card['variant'] ?? '') === 'alert';
                $card['key'] = $alert ? 'alert:' . $card['capability'] : 'offer:' . ($card['fingerprint'] ?? $card['capability']);
                $card['subject'] = 'cap:' . ($alert ? $card['capability'] : ($card['target']['capability'] ?? $card['capability']));
        }
        return $card;
    }

    /**
     * Needs you: alerts (a hint 'alert' on a read capability whose rows carry a flag), tasks that wait on the person
     * (needs your answer, could not finish), routines waiting for their confirm. Each one carries its next step.
     */
    public static function needs(Context $context, ?array $tasks = null)
    {
        $cards = array();
        foreach (Capabilities::available($context) as $name => $entry) {
            $alert = Hints::for($name)['alert'] ?? null;
            if (!$alert || !$entry['capability']->readOnly) {
                continue;
            }
            try {
                $rows = \Caligrafy\Download::rows(Reads::plain($name, $context));
            } catch (ServiceError $e) {
                continue;
            }
            $hits = array_values(array_filter($rows, function ($r) use ($alert) { return !empty($r[$alert['when']]); }));
            if (!$hits) {
                continue;
            }
            $count = count($hits);
            $action = $alert['action'] ?? null;
            if ($action !== null && !isset(Capabilities::available($context)[$action])) {
                $action = null;   // this person may not take the action: the alert leads to the list itself
            }
            $cards[] = array('snap' => Cards::VERSION, 'family' => 'offer', 'type' => 'suggestion', 'variant' => 'alert', 'capability' => $name,
                'title' => strtr($alert['title'] ?? Cards::title($name), array('{count}' => $count)),
                'tag' => $alert['tag'] ?? 'Needs you', 'severity' => $alert['severity'] ?? 'danger',
                'question' => strtr($alert['message'], array('{count}' => $count, '{s}' => $count === 1 ? '' : 's')),
                'reason' => strtr($alert['reason'] ?? '', array('{count}' => $count)),
                'items' => array_slice(array_map(function ($r) use ($alert) { return (string) self::dig($r, $alert['label'] ?? 'title'); }, $hits), 0, 5),
                'target' => array('capability' => $action ?? $name, 'input' => (object) array()),
                'yes' => $action ? ($alert['yes'] ?? 'Take care of it') : 'See them', 'open' => $action ? array('capability' => $name, 'input' => (object) array()) : null,
                'risk' => $action ? Cards::risk(Capabilities::get($action)['capability']) : 'read', 'fingerprint' => null,
                'glance' => strtr($alert['message'], array('{count}' => $count, '{s}' => $count === 1 ? '' : 's')),
                'url' => Cards::url($name), 'full' => null, 'lead' => null);
        }
        foreach ($tasks ?? Tasks::of($context, 10) as $task) {
            if (in_array($task['status'], array('needs_input', 'failed'), true)) {
                $cards[] = $task;
            }
        }
        foreach (Offers::offers($context) as $offer) {
            if (($offer['level'] ?? null) === 'confirm') {
                $cards[] = $offer;   // a routine that prepared a change: it waits for its confirm
            }
        }
        return $cards;
    }

    /**
     * Insights: numbers that call for something. One per read capability with an 'insight' hint, shown only when there
     * is something to act on (a count above zero) and this person may take its action; each carries that action.
     *   'insight' => array('label', 'icon', 'action' => array('capability', 'text'),
     *                      'since' => array('field', 'days')?   count only what was added lately
     *                      'ring' => array('of', 'label', 'invert')?)   and a share of another count
     */
    public static function insights(Context $context)
    {
        $available = Capabilities::available($context);
        $rows = array();
        $read = function ($name) use ($context, $available, &$rows) {
            if (!array_key_exists($name, $rows)) {
                $rows[$name] = null;
                if (isset($available[$name]) && $available[$name]['capability']->readOnly) {
                    try {
                        $plain = Reads::plain($name, $context);
                        $rows[$name] = array('total' => is_array($plain) && isset($plain['total']) ? (int) $plain['total'] : (is_array($plain) ? count($plain) : 0),
                            'rows' => is_array($plain) && isset($plain['data']) ? (array) $plain['data'] : (is_array($plain) ? $plain : array()));
                    } catch (ServiceError $e) {
                    }
                }
            }
            return $rows[$name];
        };
        $tiles = array();
        foreach ($available as $name => $entry) {
            $hint = Hints::for($name)['insight'] ?? null;
            if (!$hint || !isset($available[$hint['action']['capability'] ?? '']) || ($data = $read($name)) === null) {
                continue;
            }
            $n = $data['total'];
            if (!empty($hint['since'])) {
                $since = time() - $hint['since']['days'] * 86400;
                $n = count(array_filter($data['rows'], function ($r) use ($hint, $since) { return strtotime((string) Discover::dig($r, $hint['since']['field'])) > $since; }));
            }
            if ($n < 1) {
                continue;   // nothing to act on: no number for its own sake
            }
            $tile = array('capability' => $name, 'label' => $hint['label'], 'icon' => $hint['icon'] ?? Cards::icon($name), 'value' => $n,
                'action' => array('text' => $hint['action']['text'], 'run' => array('capability' => $hint['action']['capability'], 'input' => (object) array())));
            if (!empty($hint['ring']) && ($of = $read($hint['ring']['of'])) && $of['total'] > 0) {
                $share = $n / $of['total'];
                $tile['ring'] = array('label' => $hint['ring']['label'], 'percent' => (int) round(100 * (!empty($hint['ring']['invert']) ? 1 - $share : $share)));
            }
            $tiles[] = $tile;
        }
        return $tiles;
    }

    /**
     * The person's progress: what they got done today through the app, and how many days in a row they did something
     * (from Caligrafy's audit log: their successful changes; the assistant's own bookkeeping is left out)
     */
    public static function progress(Context $context)
    {
        if (!$context->userId()) {
            return array('today' => 0, 'streak' => 0);
        }
        $days = array();
        try {
            $rows = Database::table('caligrafy_audit')->where('actor_type', $context->actorType())->where('actor_id', (string) $context->actorId())
                ->where('outcome', 'ok')->where('occurred_at', '>=', date('Y-m-d 00:00:00', strtotime('-30 days')))->select('capability', 'occurred_at')->get();
        } catch (\Throwable $e) {
            return array('today' => 0, 'streak' => 0);
        }
        $today = 0;
        foreach ($rows as $r) {
            $r = (array) $r;
            if (preg_match(Cards::HIDDEN, $r['capability']) || preg_match('/^snap_/', $r['capability'])) {
                continue;
            }
            $day = substr($r['occurred_at'], 0, 10);
            $days[$day] = true;
            if ($day === date('Y-m-d')) {
                $today++;
            }
        }
        $streak = 0;
        for ($d = strtotime(date('Y-m-d')); isset($days[date('Y-m-d', $d)]); $d = strtotime('-1 day', $d)) {
            $streak++;
        }
        return array('today' => $today, 'streak' => $streak);
    }

    /**
     * Home's suggestions, general: the person's shortcuts, then what they do often (a capability they used three times
     * or more, by its title, never the exact words of one request), then a few starting points
     */
    private static function homeSuggestions(Context $context, array $shortcuts, array $by = array())
    {
        $available = Capabilities::available($context);
        $out = array();
        $add = function ($text, $capability, $input) use (&$out, $available) {
            $same = $capability . json_encode((object) $input);   // one suggestion per card, whatever its name
            if (!isset($available[$capability]) || isset($out[Shell::normalize($text)]) || isset($out[$same])) return;
            $out[$same] = true;
            $out[Shell::normalize($text)] = array('text' => $text, 'icon' => Cards::icon($capability), 'run' => array('capability' => $capability, 'input' => (object) $input));
        };
        // the focus first: what needs the person, by its action
        foreach (array_slice($by['needs']['cards'] ?? array(), 0, 1) as $need) {
            if (($need['variant'] ?? '') === 'alert' && !empty($need['target'])) {
                $add($need['yes'] ?? 'Take care of it', $need['target']['capability'], (array) $need['target']['input']);
            } elseif (!empty($need['key'])) {
                $out['open:' . $need['key']] = array('text' => $need['title'], 'icon' => $need['icon'] ?? 'bell-ring', 'open' => $need['key']);
            }
        }
        // something to look at: the first pick of what this app is about
        foreach ($by as $key => $section) {
            if (strpos($key, 'discover') === 0 && !empty($section['cards'])) {
                $pick = $section['cards'][0];
                $add('Look at ' . $pick['title'], $pick['target']['capability'], (array) $pick['target']['input']);
                break;
            }
        }
        foreach (array_slice($shortcuts, 0, 2) as $s) {
            $add($s['title'], $s['target']['capability'], (array) $s['target']['input']);
        }
        $counts = array();
        foreach (Database::table(self::TABLE)->where('user_id', (int) $context->userId())->orderBy('id', 'desc')->limit(60)->pluck('cards') as $cards) {
            $first = (json_decode((string) $cards, true) ?: array())[0] ?? array();
            $name = ($first['type'] ?? '') === 'task' ? ($first['of'] ?? '') : (string) ($first['capability'] ?? '');
            if ($name !== '' && !preg_match(Cards::HIDDEN, $name)) {
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
        }
        arsort($counts);
        foreach ($counts as $name => $n) {
            if ($n >= 3) $add(Cards::title($name), $name, array());
        }
        foreach (Shell::menu($context) as $item) {
            $add($item['title'], $item['capability'], array());
        }
        $list = array_slice(array_values(array_filter($out, 'is_array')), 0, 3);
        $list[] = array('text' => self::MORE, 'icon' => 'sparkles');
        return $list;
    }

    private static function turn(array $r)
    {
        return array('id' => (int) $r['id'], 'conversation' => $r['conversation'], 'text' => $r['text'], 'caption' => (string) $r['caption'],
            'cards' => json_decode((string) $r['cards'], true) ?: array(), 'suggestions' => json_decode((string) $r['suggestions'], true) ?: array(),
            'via' => $r['via'], 'rating' => $r['rating'] === null ? null : (int) $r['rating'], 'at' => $r['created_at']);
    }

    private static function dig($row, $path)
    {
        foreach (explode('.', $path) as $p) {
            if (!is_array($row) || !array_key_exists($p, $row)) return '';
            $row = $row[$p];
        }
        return $row;
    }
}
