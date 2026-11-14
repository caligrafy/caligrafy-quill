<?php
/**
 * Offer: Suggestion and Offer: Routine (2.23.0): the assistant notices what a person does again and again,
 * only if they turned noticing on, and offers to help. Built to stay quiet rather than noisy:
 *   - opt-in per person (snap_settings.noticing), off by default; "What I've noticed" shows every pattern, each
 *     deletable, and turning noticing off deletes what was noticed
 *   - a pattern is the same request (capability + inputs) on 3 different days or more, on the same weekday (weekly)
 *     or around the same hour (daily); anything weaker says nothing
 *   - every offer says why ("you did this 4 Mondays in a row"), has Not now and Don't suggest this again, and at most
 *     MAX_OFFERS are shown at a time
 *   - Offer: Suggestion: it is the usual moment and it was not done yet today: do it now (through Snap\Flow, so a
 *     change still asks for its Confirm)
 *   - Offer: Routine: do it on that rhythm, at a level the person picks: suggest (a Suggestion at that moment),
 *     confirm (its Confirm ready at that moment), auto (it runs as a task and they are told). Accepting a routine is
 *     itself a change with its Ask: Confirm. A delete is never automatic.
 */

namespace Caligrafy\Snap;

use Caligrafy\Audit;
use Caligrafy\Capabilities;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\ServiceError;

class Offers
{
    const MIN_DAYS = 3;
    const MAX_OFFERS = 2;
    const WINDOW_DAYS = 42;
    const LEVELS = array('suggest', 'confirm', 'auto');
    const DAYS = array(1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday');

    /** @var int|null tests: the time "now" is */
    public static $now = null;

    // ---- noticing ----

    public static function noticing(Context $context)
    {
        return $context->userId() && (bool) Database::table('caligrafy_snap_settings')->where('user_id', $context->userId())->value('noticing');
    }

    public static function setNoticing(Context $context, $on)
    {
        $id = $context->userId();
        if (!$id) {
            throw ServiceError::unauthenticated();
        }
        if (Database::table('caligrafy_snap_settings')->where('user_id', $id)->exists()) {
            Database::table('caligrafy_snap_settings')->where('user_id', $id)->update(array('noticing' => $on ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s')));
        } else {
            Database::table('caligrafy_snap_settings')->insert(array('user_id' => $id, 'noticing' => $on ? 1 : 0));
        }
        if (!$on) {
            Database::table('caligrafy_snap_events')->where('user_id', $id)->delete();   // off means forgotten, not just paused
        }
        return array('noticing' => (bool) $on);
    }

    /**
     * Something this person did through a card (a read that ran, a change they confirmed, a task they started)
     */
    public static function record(Context $context, $name, array $inputs)
    {
        if (!self::noticing($context) || preg_match(Cards::HIDDEN, $name)) {
            return;
        }
        Database::table('caligrafy_snap_events')->insert(array('user_id' => $context->userId(), 'capability' => $name,
            'inputs' => json_encode(self::canonical($inputs), JSON_UNESCAPED_UNICODE), 'fingerprint' => self::fingerprint($name, $inputs),
            'at' => date('Y-m-d H:i:s', self::now())));
    }

    public static function forget(Context $context, $fingerprint = null)
    {
        $q = Database::table('caligrafy_snap_events')->where('user_id', $context->userId());
        if ($fingerprint !== null) {
            $q->where('fingerprint', $fingerprint);
        }
        return array('forgotten' => (int) $q->delete());
    }

    // ---- patterns ----

    /**
     * What was noticed: each request done on MIN_DAYS different days or more, with its rhythm and why
     */
    public static function patterns(Context $context)
    {
        if (!$context->userId() || !self::noticing($context)) {
            return array();   // off: nothing is noticed, whatever the table holds
        }
        $since = date('Y-m-d H:i:s', self::now() - self::WINDOW_DAYS * 86400);
        $groups = array();
        foreach (Database::table('caligrafy_snap_events')->where('user_id', $context->userId())->where('at', '>=', $since)->orderBy('at')->get() as $event) {
            $event = (array) $event;
            $groups[$event['fingerprint']][] = $event;
        }
        $available = Capabilities::available($context);
        $patterns = array();
        foreach ($groups as $fingerprint => $events) {
            $first = $events[0];
            if (!isset($available[$first['capability']])) {
                continue;
            }
            $pattern = self::rhythm($events);
            if ($pattern === null) {
                continue;
            }
            $patterns[] = $pattern + array('fingerprint' => $fingerprint, 'capability' => $first['capability'],
                'inputs' => json_decode((string) $first['inputs'], true) ?: array(), 'title' => Cards::title($first['capability']),
                'risk' => Cards::risk($available[$first['capability']]['capability']));
        }
        return $patterns;
    }

    /**
     * The offers to show now: routines that are due, then suggestions of this moment, then routines to propose;
     * never more than MAX_OFFERS, never one dismissed
     */
    public static function offers(Context $context)
    {
        if (!$context->userId()) {
            return array();
        }
        $offers = array();
        $routines = self::routines($context);
        $routineFingerprints = array_column($routines, 'fingerprint');
        foreach ($routines as $routine) {
            if ($routine['level'] !== 'auto' && strtotime($routine['next_at']) <= self::now() && !self::dismissed($context, $routine['fingerprint'])) {
                $offers[] = self::suggestion($routine, $context, 'Your routine: ' . self::rhythmText($routine) . '.', $routine['level']);
            }
        }
        foreach (self::patterns($context) as $pattern) {
            if (in_array($pattern['fingerprint'], $routineFingerprints, true) || self::dismissed($context, $pattern['fingerprint'])) {
                continue;
            }
            if (self::isMoment($pattern) && !self::doneToday($context, $pattern['fingerprint'])) {
                $offers[] = self::suggestion($pattern, $context, $pattern['reason'], 'suggest');
            } elseif ($pattern['risk'] !== 'destroy') {
                $offers[] = self::routineOffer($pattern);
            }
        }
        return array_slice($offers, 0, self::MAX_OFFERS);
    }

    public static function dismiss(Context $context, $fingerprint, $kind)
    {
        Database::table('caligrafy_snap_dismissed')->where('user_id', $context->userId())->where('fingerprint', $fingerprint)->delete();
        Database::table('caligrafy_snap_dismissed')->insert(array('user_id' => $context->userId(), 'fingerprint' => $fingerprint, 'kind' => $kind,
            'until_at' => $kind === 'today' ? date('Y-m-d 23:59:59', self::now()) : null));
        foreach (Database::table('caligrafy_snap_routines')->where('user_id', $context->userId())->where('fingerprint', $fingerprint)->get() as $r) {
            $r = (array) $r;
            Database::table('caligrafy_snap_routines')->where('id', $r['id'])->update(array('last_at' => date('Y-m-d H:i:s', self::now()),
                'next_at' => self::nextAt($r['weekday'] === null ? null : (int) $r['weekday'], (int) $r['hour'], self::now() + 3600)));
        }
        return array('dismissed' => $fingerprint, 'kind' => $kind);
    }

    // ---- routines ----

    public static function routines(Context $context)
    {
        if (!$context->userId()) {
            return array();
        }
        return array_map(function ($r) { $r = (array) $r; $r['inputs'] = json_decode((string) $r['inputs'], true) ?: array(); $r['title'] = Cards::title($r['capability']); return $r; },
            Database::table('caligrafy_snap_routines')->where('user_id', $context->userId())->orderBy('id')->get());
    }

    /**
     * Keeps a routine (snap_routine_save, after its Ask: Confirm): the consent to do it on a rhythm, recorded in the audit log
     */
    public static function saveRoutine(Context $context, $capability, array $inputs, $weekday, $hour, $level)
    {
        $available = Capabilities::available($context);
        if (!isset($available[$capability])) {
            throw ServiceError::notFound('There is no card for ' . $capability);
        }
        if (Cards::risk($available[$capability]['capability']) === 'destroy') {
            throw ServiceError::invalid(array('capability' => 'A delete is never done on a rhythm.'));
        }
        $fingerprint = self::fingerprint($capability, $inputs);
        Database::table('caligrafy_snap_routines')->where('user_id', $context->userId())->where('fingerprint', $fingerprint)->delete();
        $weekday = $weekday === null || $weekday === '' ? null : (int) $weekday;
        $id = Database::table('caligrafy_snap_routines')->insert(array('user_id' => $context->userId(), 'capability' => $capability,
            'inputs' => json_encode(self::canonical($inputs), JSON_UNESCAPED_UNICODE), 'fingerprint' => $fingerprint, 'weekday' => $weekday,
            'hour' => (int) $hour, 'level' => $level, 'next_at' => self::nextAt($weekday, (int) $hour, self::now())));
        Audit::record($context, 'snap_routine', 'ok', "routine $capability " . ($weekday ? self::DAYS[$weekday] : 'daily') . " at $hour:00, $level");
        $routine = (array) Database::table('caligrafy_snap_routines')->where('id', $id)->first();
        return array('routine' => (int) $id, 'rhythm' => self::rhythmText($routine), 'level' => $level, 'title' => Cards::title($capability));
    }

    public static function deleteRoutine(Context $context, $id)
    {
        if (!Database::table('caligrafy_snap_routines')->where('id', $id)->where('user_id', $context->userId())->delete()) {
            throw ServiceError::notFound('No such routine');
        }
        return array('deleted' => (int) $id);
    }

    /**
     * The scheduler's side (snap_routines_due, every minute): routines at the auto level that are due run as a task,
     * as their owner, and they are told (by the task). Others wait for the person, as offers.
     */
    public static function runDue()
    {
        $ran = array();
        foreach (Database::table('caligrafy_snap_routines')->where('level', 'auto')->where('next_at', '<=', date('Y-m-d H:i:s', self::now()))->get() as $routine) {
            $routine = (array) $routine;
            $user = Database::table('users')->where('id', $routine['user_id'])->first();
            Database::table('caligrafy_snap_routines')->where('id', $routine['id'])->update(array('last_at' => date('Y-m-d H:i:s', self::now()),
                'next_at' => self::nextAt($routine['weekday'] === null ? null : (int) $routine['weekday'], (int) $routine['hour'], self::now() + 3600)));
            if (!$user) {
                continue;
            }
            $owner = new Context('web', (object) (array) $user);
            try {
                $task = Tasks::start($routine['capability'], json_decode((string) $routine['inputs'], true) ?: array(), $owner);
                $ran[] = array('routine' => (int) $routine['id'], 'task' => $task['id']);
            } catch (ServiceError $e) {
                $ran[] = array('routine' => (int) $routine['id'], 'refused' => $e->getMessage());
            }
        }
        return $ran;
    }

    // ---- inside ----

    // weekly: MIN_DAYS different days on one weekday, around one hour; daily: MIN_DAYS different days around one hour
    private static function rhythm(array $events)
    {
        $days = array();
        foreach ($events as $e) {
            $t = strtotime($e['at']);
            $days[date('Y-m-d', $t)] = array('weekday' => (int) date('N', $t), 'hour' => (int) date('G', $t));
        }
        if (count($days) < self::MIN_DAYS) {
            return null;
        }
        $byWeekday = array_count_values(array_column($days, 'weekday'));
        arsort($byWeekday);
        $hours = array_column($days, 'hour');
        sort($hours);
        $hour = $hours[intdiv(count($hours), 2)];   // the middle one
        $nearHour = count(array_filter($hours, function ($h) use ($hour) { return abs($h - $hour) <= 1; }));
        $weekday = (int) array_key_first($byWeekday);
        if ($byWeekday[$weekday] >= self::MIN_DAYS && $nearHour >= self::MIN_DAYS) {
            return array('weekday' => $weekday, 'hour' => $hour, 'days' => $byWeekday[$weekday],
                'reason' => 'You did this ' . $byWeekday[$weekday] . ' ' . self::DAYS[$weekday] . 's in a row, around ' . self::clock($hour) . '.');
        }
        if ($nearHour >= self::MIN_DAYS && count($days) >= self::MIN_DAYS + 1) {
            return array('weekday' => null, 'hour' => $hour, 'days' => count($days),
                'reason' => 'You did this on ' . count($days) . ' different days, around ' . self::clock($hour) . '.');
        }
        return null;
    }

    private static function isMoment(array $pattern)
    {
        $now = self::now();
        $sameDay = $pattern['weekday'] === null || (int) date('N', $now) === (int) $pattern['weekday'];
        return $sameDay && abs((int) date('G', $now) - (int) $pattern['hour']) <= 1;
    }

    private static function doneToday(Context $context, $fingerprint)
    {
        return Database::table('caligrafy_snap_events')->where('user_id', $context->userId())->where('fingerprint', $fingerprint)
            ->where('at', '>=', date('Y-m-d 00:00:00', self::now()))->exists();
    }

    private static function dismissed(Context $context, $fingerprint)
    {
        $row = Database::table('caligrafy_snap_dismissed')->where('user_id', $context->userId())->where('fingerprint', $fingerprint)->first();
        if (!$row) {
            return false;
        }
        $row = (array) $row;
        return $row['kind'] === 'never' || ($row['until_at'] !== null && strtotime($row['until_at']) >= self::now());
    }

    // Offer: Suggestion: do it now; Yes goes through Snap\Flow (a change still asks for its Confirm)
    private static function suggestion(array $p, Context $context, $reason, $level)
    {
        $when = $p['weekday'] !== null ? 'It is ' . self::DAYS[(int) $p['weekday']] . ' ' . self::partOfDay((int) $p['hour']) : 'It is ' . self::partOfDay((int) $p['hour']);
        return array('snap' => Cards::VERSION, 'family' => 'offer', 'type' => 'suggestion', 'capability' => $p['capability'], 'title' => $p['title'],
            'question' => $when . ': ' . lcfirst(self::verbPhrase($p['capability'])) . '?', 'reason' => $reason, 'fingerprint' => $p['fingerprint'],
            'target' => array('capability' => $p['capability'], 'input' => (object) $p['inputs']), 'level' => $level,
            'risk' => Cards::risk(Capabilities::get($p['capability'])['capability']),
            'glance' => $p['title'] . ' · ' . $when, 'url' => Cards::url($p['capability'], $p['inputs']), 'full' => null, 'lead' => null);
    }

    // Offer: Routine: do it on its rhythm, at a level the person picks (accepting goes through its own Ask: Confirm)
    private static function routineOffer(array $p)
    {
        $levels = array('suggest' => 'Remind me', 'confirm' => 'Prepare it for me to confirm');
        if ($p['risk'] === 'read' || $p['risk'] === 'write') {
            $levels['auto'] = 'Do it and tell me';   // the same words as its Confirm
        }
        return array('snap' => Cards::VERSION, 'family' => 'offer', 'type' => 'routine', 'capability' => $p['capability'], 'title' => $p['title'],
            'question' => 'Do this for you every ' . ($p['weekday'] !== null ? self::DAYS[(int) $p['weekday']] : 'day') . ' around ' . self::clock((int) $p['hour']) . '?',
            'reason' => $p['reason'], 'fingerprint' => $p['fingerprint'], 'weekday' => $p['weekday'], 'hour' => (int) $p['hour'],
            'target' => array('capability' => $p['capability'], 'input' => (object) $p['inputs']), 'levels' => $levels, 'risk' => $p['risk'],
            'glance' => 'Routine? ' . $p['title'], 'url' => Cards::url($p['capability'], $p['inputs']), 'full' => null, 'lead' => null);
    }

    private static function verbPhrase($capability)
    {
        $title = Cards::title($capability);
        return preg_match('/^(add|change|delete|lend|record|send|remind|find|open|register)\b/i', $title) ? $title : 'Open ' . lcfirst($title);
    }

    public static function rhythmText(array $r)
    {
        return ($r['weekday'] !== null && $r['weekday'] !== '' ? 'every ' . self::DAYS[(int) $r['weekday']] : 'every day') . ' around ' . self::clock((int) $r['hour']);
    }

    private static function nextAt($weekday, $hour, $from)
    {
        $t = strtotime(date('Y-m-d', $from) . sprintf(' %02d:00:00', $hour));
        while ($t < $from || ($weekday !== null && (int) date('N', $t) !== (int) $weekday)) {
            $t = strtotime('+1 day', $t);
        }
        return date('Y-m-d H:i:s', $t);
    }

    private static function clock($hour) { return $hour === 0 ? 'midnight' : ($hour < 12 ? "$hour am" : ($hour === 12 ? 'noon' : ($hour - 12) . ' pm')); }

    private static function partOfDay($hour) { return $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening'); }

    private static function now() { return self::$now ?? time(); }

    public static function fingerprint($name, array $inputs)
    {
        return sha1($name . '|' . json_encode(self::canonical($inputs)));
    }

    private static function canonical(array $inputs)
    {
        $inputs = array_filter($inputs, function ($v) { return $v !== null && $v !== '' && !is_array($v) && !is_object($v); });
        unset($inputs['page']);
        ksort($inputs);
        return array_map('strval', $inputs);
    }
}
