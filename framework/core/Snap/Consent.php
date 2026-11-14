<?php
/**
 * Snap consent (2.23.0): nothing that changes data runs from a card until the person confirms it.
 *
 * - snap_run answers a change with an Ask: Confirm: what will change (before -> after), and a one-time token bound
 *   to this person, this capability and these exact inputs, for 10 minutes, kept in the session (never in the card's
 *   address). Only the person's click on that card sends it back; a model, a page or a link cannot make one.
 * - A confirmed change is recorded in Caligrafy's audit log (capability snap_confirm, detail: what was confirmed).
 * - What the change can be undone with is decided here, from what was true before (update_), what was created
 *   (create_ -> delete_), or a hint ('undo' in application/config/snap.php). Undoing is a change too: it gets its own Confirm.
 */

namespace Caligrafy\Snap;

use Caligrafy\Audit;
use Caligrafy\Capabilities;
use Caligrafy\Context;
use Caligrafy\ServiceError;

class Consent
{
    const TTL = 600;          // seconds a confirmation stays valid
    const KEEP = 20;          // pending confirmations kept per session
    const KEY = 'snap_consent';

    /**
     * The Ask: Confirm card of a change, with a fresh token
     */
    public static function ask($name, Context $context, array $inputs, $message = null)
    {
        $entry = Capabilities::available($context)[$name];
        $risk = Cards::risk($entry['capability']);
        $before = self::before($name, $context, $inputs);
        $token = bin2hex(random_bytes(16));
        $store = self::store();
        $store[$token] = array('capability' => $name, 'inputs' => self::fingerprint($inputs), 'user' => self::who($context),
            'expires' => time() + self::TTL, 'before' => $before);
        self::save(array_slice($store, -self::KEEP, null, true));
        return Cards::confirm($name, $context, $inputs, $token, self::preview($name, $context, $inputs, $before), self::subject($name, $before), $risk,
            self::undoable($name, $risk), $message);
    }

    /**
     * Whether $token confirms exactly this change by this person; a token works once
     * @return array|null what was kept with it (the values before the change)
     */
    public static function take($token, $name, Context $context, array $inputs)
    {
        $store = self::store();
        $kept = $store[$token] ?? null;
        unset($store[$token]);
        self::save($store);
        if (!$kept || $kept['expires'] < time() || $kept['capability'] !== $name || $kept['user'] !== self::who($context)
            || !hash_equals($kept['inputs'], self::fingerprint($inputs))) {
            return null;
        }
        Audit::record($context, 'snap_confirm', 'ok', "confirmed $name " . substr(json_encode(self::canonical($inputs), JSON_UNESCAPED_UNICODE), 0, 400));
        return $kept;
    }

    /**
     * The action that undoes a done change, or null when there is none
     */
    public static function undo($name, array $inputs, $result, array $kept, Context $context)
    {
        $available = Capabilities::available($context);
        $hint = Hints::for($name)['undo'] ?? null;
        $undo = null;
        if ($hint) {
            $values = array();
            foreach ((array) $hint['map'] as $to => $from) {
                $values[$to] = $inputs[$from] ?? (is_array($result) ? ($result[$from] ?? null) : null);
            }
            $undo = array('capability' => $hint['capability'], 'input' => $values, 'label' => $hint['label'] ?? 'Undo');
        } elseif (preg_match('/^update_(\w+)$/', $name) && !empty($kept['before'])) {
            $values = array('id' => $inputs['id']);
            foreach ($inputs as $key => $value) {
                if ($key !== 'id' && array_key_exists($key, $kept['before']) && (string) $kept['before'][$key] !== (string) $value) {
                    $values[$key] = $kept['before'][$key];
                }
            }
            $undo = count($values) > 1 ? array('capability' => $name, 'input' => $values, 'label' => 'Undo') : null;
        } elseif (preg_match('/^create_(\w+)$/', $name, $m) && is_array($result) && isset($result['id'])) {
            $undo = array('capability' => 'delete_' . $m[1], 'input' => array('id' => $result['id']), 'label' => 'Undo');
        }
        if ($undo && isset($available[$undo['capability']])) {
            $undo['risk'] = Cards::risk($available[$undo['capability']]['capability']);
            return $undo;
        }
        return null;
    }

    // ---- inside ----

    // the record a change starts from (get_<thing> with its id), to show before -> after and to undo
    private static function before($name, Context $context, array $inputs)
    {
        $id = $inputs['id'] ?? null;
        if (!preg_match('/^(?:update|delete)_(\w+)$/', $name, $m) || $id === null) {
            return array();
        }
        $getter = 'get_' . $m[1];
        if (!isset(Capabilities::available($context)[$getter])) {
            return array();
        }
        try {
            $record = Capabilities::plain(Capabilities::call($getter, array('id' => $id), $context));
        } catch (ServiceError $e) {
            return array();
        }
        return is_array($record) ? $record : array();
    }

    // what the Confirm card says will change: label, before (when known), after; choices by their label
    private static function preview($name, Context $context, array $inputs, array $before)
    {
        $lines = array();
        foreach (Cards::fields($name, $context, $inputs) as $field) {
            $key = $field['name'];
            if (!array_key_exists($key, $inputs) || ($key === 'id' && $before)) {
                continue;
            }
            $after = self::shown($field, $inputs[$key]);
            if ($before) {
                if (!array_key_exists($key, $before) || (string) $before[$key] === (string) $inputs[$key]) {
                    continue;   // unchanged: not part of what this does
                }
                $lines[] = array('label' => $field['label'], 'icon' => $field['icon'], 'before' => self::shown($field, $before[$key]), 'after' => $after);
            } else {
                $lines[] = array('label' => $field['label'], 'icon' => $field['icon'], 'after' => $after);
            }
        }
        return $lines;
    }

    private static function subject($name, array $before)
    {
        if (!$before) {
            return null;
        }
        $title = $before['title'] ?? $before['name'] ?? $before['username'] ?? ('#' . ($before['id'] ?? ''));
        $by = null;   // the name of a related record ("Kindred · Octavia E. Butler")
        foreach ($before as $value) {
            if (is_array($value) && isset($value['name']) && is_scalar($value['name'])) { $by = $value['name']; break; }
        }
        return $by ? "$title · $by" : (string) $title;
    }

    private static function undoable($name, $risk)
    {
        if (isset(Hints::for($name)['undo'])) return true;
        if ($risk === 'destroy') return false;
        return (bool) preg_match('/^(update|create)_/', $name);
    }

    private static function shown(array $field, $value)
    {
        foreach ((array) ($field['options'] ?? array()) as $option) {
            if ((string) $option['value'] === (string) $value) {
                return $option['label'];
            }
        }
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        if ($field['name'] === 'capability' && is_string($value) && Capabilities::get($value)) return Cards::title($value);   // a routine names what it does
        if ($field['widget'] === 'password') return '••••••••';
        return $value === null || $value === '' ? '(empty)' : (string) $value;
    }

    private static function fingerprint(array $inputs)
    {
        return hash('sha256', json_encode(self::canonical($inputs)));
    }

    // the same inputs whatever their order or type ("1979" and 1979)
    private static function canonical(array $inputs)
    {
        $inputs = array_filter($inputs, function ($v) { return $v !== null && $v !== ''; });
        ksort($inputs);
        return array_map(function ($v) { return is_bool($v) ? ($v ? '1' : '0') : (string) $v; }, $inputs);
    }

    private static function who(Context $context)
    {
        $actor = $context->actorType() . ':' . $context->actorId();
        return $context->authenticated() ? $actor : 'guest:' . session_id();   // a visitor is told apart by the session
    }

    private static function store()
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = array();
        }
        return (array) ($_SESSION[self::KEY] ?? array());
    }

    private static function save(array $store)
    {
        $_SESSION[self::KEY] = $store;
    }
}
