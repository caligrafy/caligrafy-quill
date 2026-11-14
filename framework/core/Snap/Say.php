<?php
/**
 * The assistant's line after every action (2.23.0): whatever the person did (sent a
 * form, picked a choice, confirmed, opened a row, undid, saved a shortcut), the answer carries what the assistant says
 * about it, shown in the conversation section. Written here, at once and for free, from what the card is; the model
 * writes only the captions of free questions. Also: which answers deserve a celebration.
 */

namespace Caligrafy\Snap;

class Say
{
    /**
     * The line for a card the person just reached, and whether it is a moment to celebrate
     * @return array [say, celebrate]
     */
    public static function for(array $card, $after = null)
    {
        $type = $card['type'] ?? '';
        $title = $card['title'] ?? '';
        $heading = $card['heading'] ?? '';
        switch ($type) {
            case 'form':
                if (!empty((array) ($card['errors'] ?? array()))) {
                    $labels = self::labels($card, array_keys((array) $card['errors']));
                    return array('Almost there: ' . $labels . ' ' . (count((array) $card['errors']) > 1 ? 'need' : 'needs') . ' a fix.', false);
                }
                $needed = self::labels($card, (array) ($card['needed'] ?? array()));
                $said = count(array_filter((array) ($card['fields'] ?? array()), function ($f) { return ($f['state'] ?? '') === 'said'; }));
                $current = array_filter((array) ($card['fields'] ?? array()), function ($f) { return ($f['state'] ?? '') === 'current'; });
                if ($needed === '' && $current) {
                    // a change starts from the record as it is: what to change is the question
                    $what = '';
                    foreach ((array) $card['fields'] as $f) {
                        if (($f['state'] ?? '') === 'said' && !empty($f['shown'])) { $what = explode(' · ', $f['shown'])[0]; break; }
                    }
                    return array('What would you like to change' . ($what !== '' ? ' about ' . $what : '') . '?', false);
                }
                $to = 'To ' . self::lower($title);
                if ($needed === '') {
                    return array('Everything is ready to ' . self::lower($title) . '. Have a look, then ' . strtolower($card['submit'] ?? 'go') . '.', false);
                }
                return array(($said ? 'Got it. ' : '') . $to . ', I just need ' . $needed . '.', false);
            case 'choice':
                return array((!empty($card['said']) ? 'Got it. ' : '') . 'To ' . self::lower($title) . ', which ' . strtolower($card['label'] ?? 'one') . '?', false);
            case 'confirm':
                if (!empty($card['message'])) {
                    return array($card['message'], false);
                }
                $subject = !empty($card['subject']) ? explode(' · ', $card['subject'])[0] : '';
                if (($card['risk'] ?? '') === 'destroy') {
                    return array($title . ($subject !== '' ? ': "' . $subject . '"' : '') . '. This one cannot be undone. Are you sure?', false);
                }
                return array('Ready to ' . self::lower($title) . ($subject !== '' ? ' (' . $subject . ')' : '') . '? Here is what will change.', false);
            case 'answer':
                if (($card['variant'] ?? '') === 'notice' && ($card['tone'] ?? '') === 'not_found') {
                    return array('Hmm, ' . lcfirst(rtrim($heading, '.')) . '. Here is what you can do instead.', false);
                }
                if (($card['variant'] ?? '') === 'notice') {
                    return array(($card['tone'] ?? '') === 'info' ? $heading : 'Hmm, ' . lcfirst($heading), ($card['tone'] ?? '') === 'info' && strpos($heading, 'Deleted') === 0);
                }
                if (!empty($card['done'])) {
                    return array(self::done($card), true);
                }
                return array('Here is ' . ($heading !== '' ? $heading : strtolower($title)) . '.', false);
            case 'list':
                $glance = $card['glance'] ?? '';
                if (empty($card['rows'])) {
                    // nothing came back: say so plainly, and that the ways out are right there
                    $query = '';
                    foreach ((array) ($card['input'] ?? array()) as $k => $v) {
                        if ($k !== 'page' && is_string($v) && !is_numeric($v)) { $query = $v; break; }
                    }
                    return array(($query !== '' ? 'I found nothing for "' . $query . '".' : ($card['empty'] ?? 'Nothing here yet.')) . ' Here is what you can do instead.', false);
                }
                return array('Here you go: ' . self::lower($title) . ', ' . $glance . '.', false);
            case 'task':
                $status = $card['status'] ?? '';
                if ($status === 'done') return array('Done! ' . $title . ' is ready.', true);
                if ($status === 'needs_input') return array($title . ' needs one answer from you.', false);
                if ($status === 'failed') return array($title . ' could not finish.', false);
                return array('On it. ' . $title . ' is running; I will tell you when it is done.', false);
            case 'shortcut':
                return array(($card['variant'] ?? '') === 'menu' ? 'Here is everything you can do. Pick one.' : $title . ': ' . ($card['glance'] ?? '') . '.', false);
            case 'suggestion':
            case 'routine':
                return array($card['question'] ?? '', false);
        }
        return array('', false);
    }

    /**
     * Adds the line (and the celebration) to a card
     */
    public static function on(array $card)
    {
        [$say, $celebrate] = self::for($card);
        $card['say'] = $say;
        $card['celebrate'] = $celebrate;
        return $card;
    }

    private static function done(array $card)
    {
        $capability = (string) ($card['capability'] ?? '');
        $heading = $card['heading'] ?? '';
        $own = Hints::for($capability)['done'] ?? null;   // the app's own words: 'done' => 'Done! {heading} is lent.'
        if ($own) return strtr($own, array('{heading}' => $heading));
        if (preg_match('/^create_/', $capability)) return 'Done! ' . $heading . ' is added.';
        if (preg_match('/^update_/', $capability)) return 'Saved! ' . $heading . ' is up to date.';
        if ($capability === 'snap_routine_save') return 'Your routine is set. One less thing to remember.';
        return 'Done! ' . ($heading !== '' && $heading !== 'Done.' ? $heading : 'All set') . '.';
    }

    // "Add a book" -> "add a book"; "ISBN lookup" stays
    private static function lower($text)
    {
        return preg_match('/^[A-Z][a-z]/', (string) $text) ? lcfirst($text) : (string) $text;
    }

    private static function labels(array $card, array $names)
    {
        $labels = array();
        foreach ((array) ($card['fields'] ?? array()) as $f) {
            if (in_array($f['name'], $names, true)) {
                $labels[] = $f['label'] === strtoupper($f['label']) ? $f['label'] : strtolower($f['label']);
            }
        }
        if (count($labels) > 1) {
            $last = array_pop($labels);
            return implode(', ', $labels) . ' and ' . $last;
        }
        return $labels[0] ?? '';
    }
}
