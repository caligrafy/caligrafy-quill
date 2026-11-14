<?php
/**
 * The next card for a capability and its inputs, the same whoever asks (a card that is sent, the shell's fast path,
 * the model's choice): one place decides, so the planner can never take a shortcut around consent.
 *   read:        inputs missing -> its Ask card; complete -> runs -> Show card
 *   write/destroy: inputs missing -> its Ask card; refused by its rules -> Ask with errors; else -> Ask: Confirm
 * A change runs only in SnapRun, with the token of that Confirm.
 */

namespace Caligrafy\Snap;

use Caligrafy\Capabilities;
use Caligrafy\Context;
use Caligrafy\ServiceError;

class Flow
{
    /**
     * @return array [card, http status]
     */
    public static function next($name, array $inputs, Context $context)
    {
        [$card, $status] = self::decide($name, $inputs, $context);
        return array(Shortcuts::mark(Say::on($card), $context), $status);   // and whether it is a shortcut already   // the assistant's line about it, and whether to celebrate
    }

    private static function decide($name, array $inputs, Context $context)
    {
        $ask = Cards::ask($name, $context, $inputs);   // refuses a capability this person may not use
        $entry = Capabilities::available($context)[$name];
        if (Cards::needsInput($ask, $inputs) && ($ask['risk'] !== 'read' || self::requiredMissing($ask, $inputs))) {
            return array($ask, 200);
        }
        if ($ask['risk'] === 'read') {
            if (Tasks::async($name)) {
                Offers::record($context, $name, $inputs);
                return array(Tasks::start($name, $inputs, $context), 202);   // a slow read: a Track: Task at once
            }
            try {
                $card = Cards::show($name, Capabilities::call($name, $inputs, $context), $inputs, $context);
                Offers::record($context, $name, $inputs);   // only if this person turned noticing on
                return array($card, 200);
            } catch (ServiceError $e) {
                return self::refused($name, $context, $inputs, $e);
            }
        }
        try {
            (new $entry['class']())->validate($inputs);   // the capability's own rules, before anyone confirms
        } catch (ServiceError $e) {
            return self::refused($name, $context, $inputs, $e);
        }
        return array(Consent::ask($name, $context, $inputs), 200);
    }

    public static function refused($name, Context $context, array $inputs, ServiceError $e)
    {
        if ($e->kind === 'invalid') {
            return array(Cards::ask($name, $context, $inputs, $e->fields, $e->getMessage()), 422);
        }
        return array(Cards::notice($name, $e->getMessage(), $e->kind, $inputs), $e->kind === 'forbidden' ? 403 : ($e->kind === 'not_found' ? 404 : 409));
    }

    private static function requiredMissing(array $ask, array $inputs)
    {
        if ($ask['type'] === 'choice') {
            return true;
        }
        foreach ((array) ($ask['fields'] ?? array()) as $field) {
            if ($field['required'] && ($inputs[$field['name']] ?? '') === '') {
                return true;
            }
        }
        return false;
    }
}
