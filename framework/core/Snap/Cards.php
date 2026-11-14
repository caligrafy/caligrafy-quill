<?php
/**
 * Snap Cards (2.23.0): every card is built from what a capability already declares
 *
 * Families and types (marketing names the family with the type):
 *   Ask: Form, Choice, Confirm   Show: Answer, List   Go: Shortcut   Track: Task   Offer: Suggestion, Routine
 *
 * Phase 1 builds Ask: Form, Ask: Choice, Show: Answer and Show: List from:
 *   - the capability's rules and descriptions (fields, types, limits, choices, required)
 *   - the shape of its result (a page, a list, a record, a value)
 *   - optional hints in application/config/snap.php (Snap\Hints)
 * The model, later, only picks a card and fills it; it never invents a card's structure.
 */

namespace Caligrafy\Snap;

use Caligrafy\Capabilities;
use Caligrafy\Context;
use Caligrafy\Download;
use Caligrafy\ServiceError;

class Cards
{
    const VERSION = 1;
    const MAX_OPTIONS = 50;

    // icons (Lucide names): a card's from the thing it is about, a field's from its name; hints 'icon' / 'icons' win
    const THING_ICONS = array('book' => 'book-open', 'books' => 'library', 'author' => 'feather', 'authors' => 'feather', 'loan' => 'arrow-left-right',
        'loans' => 'arrow-left-right', 'user' => 'users', 'users' => 'users', 'report' => 'chart-column', 'reminders' => 'bell-ring', 'routine' => 'repeat');
    const VERB_ICONS = array('create' => 'book-plus', 'delete' => 'trash-2', 'update' => 'square-pen', 'lend' => 'hand-helping', 'return' => 'book-check',
        'search' => 'search', 'remind' => 'bell-ring', 'send' => 'send', 'register' => 'user-plus');
    const FIELD_ICONS = array('title' => 'book-open', 'published_year' => 'calendar', 'author_id' => 'feather', 'summary' => 'text', 'isbn' => 'barcode',
        'borrower' => 'user', 'days' => 'clock', 'due_on' => 'calendar-clock', 'book_id' => 'book-open', 'username' => 'mail', 'passcode' => 'lock',
        'permissions' => 'shield-check', 'query' => 'search', 'id' => 'hash', 'capability' => 'zap', 'weekday' => 'calendar-days', 'hour' => 'clock',
        'level' => 'sparkles', 'pause' => 'hourglass');      // a choice with more options than this asks for a search instead
    const MAX_COLUMNS = 5;
    const LONG_TEXT = 140;       // longer text stays out of a list's columns

    // capabilities that are plumbing, never offered as cards
    const HIDDEN = '/^(snap_|notify_|queue_work$)/';

    /**
     * The card that asks for a capability's inputs: Ask: Form, or Ask: Choice when one pick is all that is missing
     * @param array $values inputs already known (prefilled)
     * @param array $errors field => message, from a refused call
     */
    public static function ask($name, Context $context, array $values = array(), array $errors = array(), $message = null, $asForm = false)
    {
        $entry = self::entry($name, $context);
        $hints = Hints::for($name);
        // what the person gave (their prompt, a card they sent) is told apart from the record's current values
        $given = array_keys(array_filter($values, function ($v) { return $v !== null && $v !== ''; }));
        $values = self::current($name, $context, $values, $hints);
        $fields = self::fields($name, $context, $values, $hints);
        foreach ($fields as $i => $f) {
            $has = ($values[$f['name']] ?? '') !== '' && $values[$f['name']] !== null;
            $fields[$i]['state'] = isset($errors[$f['name']]) ? 'needed'
                : ($has ? (in_array($f['name'], $given, true) ? 'said' : 'current') : ($f['required'] ? 'needed' : 'optional'));
            if ($has) {
                $fields[$i]['shown'] = self::shownValue($fields[$i], $values[$f['name']]);
            }
        }
        $missing = array_values(array_filter($fields, function ($f) use ($values) {
            return $f['required'] && ($values[$f['name']] ?? '') === '';
        }));
        $card = self::base('ask', 'form', $name, $hints['title'] ?? self::title($name), $hints['lead'] ?? self::lead($entry['capability']->description), $values);
        // one required pick and nothing else to type: a Choice
        if (!$asForm && !$errors && count($missing) === 1 && $missing[0]['widget'] === 'choice' && self::onlyMissing($fields, $values, $missing[0]['name'])) {
            $card['type'] = 'choice';
            $card['field'] = $missing[0]['name'];
            $card['label'] = $missing[0]['label'];
            $card['options'] = $missing[0]['options'];
            $card['fixed'] = array_diff_key($values, array($missing[0]['name'] => true));
            // what the person already said stays in sight, read-only (Edit opens the whole form)
            $card['said'] = array_values(array_map(function ($f) { return array('name' => $f['name'], 'label' => $f['label'], 'shown' => $f['shown']); },
                array_filter($fields, function ($f) { return in_array($f['state'], array('said', 'current'), true); })));
            $card['glance'] = 'Which ' . strtolower($missing[0]['label']) . '?';
        } else {
            $card['fields'] = $fields;
            $card['needed'] = array_values(array_map(function ($f) { return $f['name']; }, array_filter($fields, function ($f) { return $f['state'] === 'needed'; })));
            $card['optional'] = array_values(array_map(function ($f) { return $f['name']; }, array_filter($fields, function ($f) { return $f['state'] === 'optional'; })));
            $card['submit'] = $hints['submit'] ?? self::verb($name, $entry);
            $card['errors'] = (object) $errors;
            $card['glance'] = $missing ? 'Needs ' . implode(', ', array_map(function ($f) { return strtolower($f['label']); }, $missing)) : 'Ready to ' . strtolower($card['submit']);
        }
        if ($message !== null) {
            $card['message'] = $message;
        }
        $card['risk'] = self::risk($entry['capability']);
        return $card;
    }

    /**
     * The card that shows a capability's result: Show: List for many rows, Show: Answer for one record or a value
     */
    public static function show($name, $result, array $input, Context $context)
    {
        $entry = self::entry($name, $context);
        $hints = Hints::for($name);
        $plain = Capabilities::plain($result);
        if (is_array($plain) && isset($plain['snap'], $plain['family'])) {
            return $plain;   // a capability that answers with a card already (snap_task)
        }
        $page = null;
        if (is_array($plain) && isset($plain['data'], $plain['total'], $plain['pages']) && is_array($plain['data'])) {
            $page = array('page' => (int) ($plain['page'] ?? 1), 'pages' => (int) $plain['pages'], 'total' => (int) $plain['total']);
            $plain = $plain['data'];
        }
        $title = $hints['title'] ?? self::title($name);
        if (is_array($plain) && array_is_list($plain)) {
            $rows = array_map(function ($row) { return is_array($row) ? $row : array('value' => $row); }, $plain);
            $card = self::base('show', 'list', $name, $title, $hints['lead'] ?? null, $input);
            $card['columns'] = self::columns($rows, $hints);
            $card['rows'] = array_map(function ($row) use ($card) { return self::cells($row, $card['columns']); }, $rows);
            $card['open'] = self::opener($name, $context, $hints);
            $from = $card['open']['from'] ?? 'id';   // the field of a row that the opening capability takes
            $card['keys'] = array_map(function ($row) use ($from) { return self::dig($row, $from); }, $rows);
            $card['page'] = $page;
            $card['variant'] = $hints['variant'] ?? 'rows';
            $count = $page ? $page['total'] : count($rows);
            $card['glance'] = $count . ' ' . (isset($hints['noun']) ? ($count === 1 ? self::singular($hints['noun']) : $hints['noun']) : self::noun($name, $count));
            $card['empty'] = $hints['empty'] ?? 'Nothing here yet.';
            return $card;
        }
        $card = self::base('show', 'answer', $name, $title, $hints['lead'] ?? null, $input);
        if (is_array($plain)) {
            $card['variant'] = 'record';
            $card['heading'] = self::name($plain);
            $card['fields'] = self::record($plain, $hints, $card['heading']);
            $card['glance'] = $card['heading'];
            $card['actions'] = self::actions($name, $plain, $context);
        } else {
            $card['variant'] = 'fact';
            $card['heading'] = is_bool($plain) ? ($plain ? 'Yes' : 'No') : (string) ($plain ?? 'Done');
            $card['fields'] = array();
            $card['glance'] = $card['heading'];
        }
        return $card;
    }

    /**
     * Ask: Confirm: what a change will do, before it runs (phase 2). The token is the person's consent once they click;
     * the card's address never carries it.
     * @param array $changes [label, before?, after] lines
     */
    public static function confirm($name, Context $context, array $inputs, $token, array $changes, $subject, $risk, $undoable, $message = null)
    {
        $hints = Hints::for($name);
        $entry = self::entry($name, $context);
        $card = self::base('ask', 'confirm', $name, $hints['title'] ?? self::title($name), null, $inputs);
        $verb = $hints['submit'] ?? self::verb($name, $entry);
        $card['risk'] = $risk;
        $card['subject'] = $subject;
        $card['changes'] = $changes;
        $card['inputs'] = (object) $inputs;
        $card['token'] = $token;
        $card['submit'] = $risk === 'destroy' ? $verb . ($subject ? ' "' . explode(' · ', $subject)[0] . '"' : '') : 'Yes, ' . lcfirst($verb);
        $card['consequence'] = $hints['consequence'] ?? ($risk === 'destroy' ? 'This cannot be undone.' : ($undoable ? 'You can undo it right after.' : 'This cannot be undone from here.'));
        $card['question'] = $hints['confirm'] ?? ($subject ? self::title($name) . ': ' . $subject . '?' : self::title($name) . '?');
        $card['glance'] = $card['question'];
        if ($message !== null) {
            $card['message'] = $message;
        }
        return $card;
    }

    // a value as people read it: a choice by its label, a password hidden, true/false as Yes/No
    private static function shownValue(array $field, $value)
    {
        foreach ((array) ($field['options'] ?? array()) as $option) {
            if ((string) $option['value'] === (string) $value) {
                return $option['label'] . (!empty($option['meta']) ? ' · ' . $option['meta'] : '');
            }
        }
        if ($field['widget'] === 'password') return '••••••••';
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        return (string) $value;
    }

    /**
     * Forgets the choices read in this request (after a change, so the next card shows what is true now)
     */
    public static function forget()
    {
        self::$optionRows = array();
        Reads::forget();   // what changed may be in a list read earlier in this request
    }

    /**
     * Whether an Ask card still needs the person: a required input is missing, or only the id of a thing to change
     * was given (an update_ starts as its form). A delete needs nothing more than its id.
     */
    public static function needsInput(array $ask, array $inputs)
    {
        if ($ask['type'] === 'choice') {
            return true;
        }
        foreach ((array) ($ask['fields'] ?? array()) as $field) {
            if ($field['required'] && ($inputs[$field['name']] ?? '') === '') {
                return true;
            }
        }
        $given = array_keys(array_filter($inputs, function ($v) { return $v !== null && $v !== ''; }));
        $others = array_filter((array) ($ask['fields'] ?? array()), function ($f) { return $f['name'] !== 'id'; });
        return $ask['risk'] !== 'destroy' && $given === array('id') && count($others) > 0;
    }

    /**
     * A message instead of a result: a refusal that is not about the inputs (not found, conflict, forbidden)
     */
    public static function notice($name, $message, $kind = 'info', array $inputs = array())
    {
        $card = self::base('show', 'answer', $name, self::title($name), null, $inputs);   // what was asked: to recover from it
        $card['variant'] = 'notice';
        $card['tone'] = $kind;
        $card['heading'] = $message;
        $card['fields'] = array();
        $card['glance'] = $message;
        return $card;
    }

    /**
     * Every capability this person may use, as the card it starts with (the gallery of phase 1)
     */
    public static function catalog(Context $context, $run = true)
    {
        $cards = array();
        foreach (Capabilities::available($context) as $name => $entry) {
            if (preg_match(self::HIDDEN, $name) || $entry['capability']->webOnly) {
                continue;
            }
            $ask = self::ask($name, $context);
            $needsInput = !empty($ask['fields']) && array_filter($ask['fields'], function ($f) { return $f['required']; });
            if ($run && $entry['capability']->readOnly && !$needsInput && $ask['type'] !== 'choice' && !Tasks::async($name)) {
                try {
                    $cards[] = self::show($name, Capabilities::call($name, array(), $context), array(), $context);
                    continue;
                } catch (ServiceError $e) {
                    // falls back to its Ask card
                }
            }
            $cards[] = $ask;
        }
        return $cards;
    }

    /**
     * The fields of an Ask card, from the rules: widget, limits, options, label, help, current value
     */
    public static function fields($name, Context $context, array $values = array(), ?array $hints = null)
    {
        $hints = $hints ?? Hints::for($name);   // the same labels, choices and hidden fields wherever fields are listed
        $schema = Capabilities::schema($name);
        $rules = get_class_vars(Capabilities::get($name)['class'])['rules'] ?? array();
        $required = (array) ($schema['required'] ?? array());
        $fields = array();
        foreach ((array) $schema['properties'] as $field => $property) {
            if (in_array($field, (array) ($hints['hide'] ?? array()), true) || $field === 'page') {
                continue;
            }
            $rule = (string) ($rules[$field] ?? '');
            $f = array(
                'name' => $field,
                'label' => $hints['labels'][$field] ?? (($field === 'id' && preg_match('/^(?:get|update|delete)_(\w+)$/', $name, $thing)) ? ucfirst(str_replace('_', ' ', $thing[1])) : self::label($field)),
                'help' => self::help($property['description'] ?? ''),
                'required' => in_array($field, $required, true),
                'value' => $values[$field] ?? null,
                'widget' => self::widget($property, $rule),
                'icon' => $hints['icons'][$field] ?? self::FIELD_ICONS[$field] ?? 'circle-dot',
            );
            foreach (array('minimum' => 'min', 'maximum' => 'max', 'minLength' => 'minlength', 'maxLength' => 'maxlength') as $from => $to) {
                if (isset($property[$from])) {
                    $f[$to] = $property[$from];
                }
            }
            if (!empty($hints['values'][$field])) {
                // a hint names the values: 1 is Monday, auto is "Do it and tell me"
                $f['widget'] = 'select';
                $f['options'] = array();
                foreach ($hints['values'][$field] as $value => $label) {
                    $f['options'][] = array('value' => $value, 'label' => $label);
                }
            } elseif (!empty($property['enum'])) {
                $f['widget'] = 'select';
                $f['options'] = array_map(function ($v) { return array('value' => $v, 'label' => ucfirst(str_replace('_', ' ', $v))); }, $property['enum']);
            } elseif ($source = self::choiceSource($name, $field, $rule, $context, $hints)) {
                $options = self::options($source, $context, $hints['choices'][$field] ?? null);
                if ($options !== null && count($options) <= self::MAX_OPTIONS) {
                    $f['widget'] = 'choice';
                    $f['options'] = $options;
                    if (preg_match('/^The id of/i', $f['help'])) {
                        $f['help'] = '';   // "The id of its author" is for agents: a person picks it by name
                    }
                    $f['source'] = is_array($source) ? $source['capability'] : $source;
                }
            }
            $fields[] = $f;
        }
        return $fields;
    }

    // ---- inside ----

    private static function entry($name, Context $context)
    {
        $available = Capabilities::available($context);
        if (!isset($available[$name]) || $available[$name]['capability']->webOnly) {
            throw ServiceError::notFound("There is no card for $name");
        }
        return $available[$name];
    }

    private static function base($family, $type, $name, $title, $lead, array $input)
    {
        $plain = array_filter($input, function ($v) { return $v !== null && $v !== '' && !is_array($v); });
        return array('snap' => self::VERSION, 'family' => $family, 'type' => $type, 'capability' => $name, 'icon' => self::icon($name),
            'title' => $title, 'lead' => $lead, 'input' => (object) $plain, 'url' => self::url($name, $input), 'full' => self::full($name, $input));
    }

    /**
     * The address of a card: the same card, with the same inputs, for anyone allowed to see it
     */
    /** @var string where Route::snap() serves the cards ('/snap' by default) */
    public static $path = '/snap';

    public static function url($name, array $input = array())
    {
        $input = array_filter($input, function ($v) { return $v !== null && $v !== '' && !is_array($v); });
        return rtrim(self::$path, '/') . '/c/' . rawurlencode($name) . ($input ? '?in=' . rawurlencode(json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) : '');
    }

    // the page that shows the same thing in full (hint 'full': '/books/{id}'); null when there is none
    private static function full($name, array $input)
    {
        $full = Hints::for($name)['full'] ?? null;
        if (!$full) {
            return null;
        }
        $missing = false;
        $path = preg_replace_callback('/\{(\w+)\}/', function ($m) use ($input, &$missing) {
            if (!isset($input[$m[1]]) || $input[$m[1]] === '') { $missing = true; return ''; }
            return rawurlencode((string) $input[$m[1]]);
        }, $full);
        return $missing ? null : $path;
    }

    // an update_ or delete_ form starts from the current record (get_<thing> with the same id)
    private static function current($name, Context $context, array $values, array $hints)
    {
        if (!preg_match('/^update_(\w+)$/', $name, $m) || empty($values['id'])) {
            return $values;
        }
        $getter = $hints['current'] ?? 'get_' . $m[1];
        if (!isset(Capabilities::available($context)[$getter])) {
            return $values;
        }
        try {
            $record = Capabilities::plain(Capabilities::call($getter, array('id' => $values['id']), $context));
        } catch (ServiceError $e) {
            return $values;
        }
        foreach ((array) $record as $key => $value) {
            if (!array_key_exists($key, $values) && !is_array($value)) {
                $values[$key] = $value;
            }
        }
        return $values;
    }

    /**
     * Where a field's choices come from, in this order: a hint, an exists,<table>.<col> rule, <thing>_id, the id of
     * get_/update_/delete_<thing>. Each points to list_<things>, used only when this person may call it.
     */
    private static function choiceSource($name, $field, $rule, Context $context, array $hints)
    {
        $available = Capabilities::available($context);
        $candidates = array();
        if (isset($hints['choices'][$field])) {
            $candidates[] = is_array($hints['choices'][$field]) ? $hints['choices'][$field]['capability'] : $hints['choices'][$field];
        }
        if (preg_match('/(?:^|\|)exists,(\w+)\.\w+/', $rule, $m)) {
            $candidates[] = 'list_' . $m[1];
        }
        if (preg_match('/^(\w+)_id$/', $field, $m)) {
            $candidates[] = 'list_' . self::plural($m[1]);
        }
        if ($field === 'id' && preg_match('/^(?:get|update|delete)_(\w+)$/', $name, $m)) {
            $candidates[] = 'list_' . self::plural($m[1]);
        }
        foreach ($candidates as $candidate) {
            if (isset($available[$candidate]) && $available[$candidate]['capability']->readOnly) {
                return $candidate;
            }
        }
        return null;
    }

    // the options of a choice: value (id, or the hint's key) and label (title, name, username, or the hint's label)
    private static $optionRows = array();   // the rows of each choice source, once per request and person

    private static function options($source, Context $context, $hint = null)
    {
        $key = $source . '|' . $context->actorType() . ':' . $context->actorId();
        if (!array_key_exists($key, self::$optionRows)) {
            try {
                self::$optionRows[$key] = Download::rows(Capabilities::call($source, array(), $context));
            } catch (ServiceError $e) {
                self::$optionRows[$key] = null;
            }
        }
        $rows = self::$optionRows[$key];
        if ($rows === null) {
            return null;
        }
        $valueKey = is_array($hint) ? ($hint['value'] ?? 'id') : 'id';
        $labelKey = is_array($hint) ? ($hint['label'] ?? null) : null;
        $options = array();
        foreach ($rows as $row) {
            if (!array_key_exists($valueKey, $row)) {
                continue;
            }
            $label = $labelKey !== null ? self::dig($row, $labelKey) : ($row['title'] ?? $row['name'] ?? $row['username'] ?? ('#' . $row[$valueKey]));
            $meta = isset($row['author']['name']) ? $row['author']['name'] : ($row['borrower'] ?? null);
            $options[] = array('value' => $row[$valueKey], 'label' => (string) $label, 'meta' => $meta);
        }
        return $options;
    }

    private static function onlyMissing(array $fields, array $values, $pick)
    {
        foreach ($fields as $f) {
            if ($f['name'] !== $pick && $f['required'] && ($values[$f['name']] ?? '') === '') {
                return false;
            }
        }
        return true;
    }

    private static function widget(array $property, $rule)
    {
        if (preg_match('/(?:^|\|)(file|required_file)(?:\||$)/', $rule)) {
            return 'file';
        }
        switch ($property['type']) {
            case 'integer': case 'number': return 'number';
            case 'boolean': return 'checkbox';
        }
        switch ($property['format'] ?? '') {
            case 'email': return 'email';
            case 'uri': return 'url';
            case 'date': return 'date';
        }
        if (preg_match('/password/', $rule) || preg_match('/password|passcode/i', $property['description'] ?? '')) {
            return 'password';
        }
        return ($property['maxLength'] ?? 0) > 300 ? 'textarea' : 'text';
    }

    // the columns of a list: the hint, else the first row's short, plain fields (and author.name of a related row)
    private static function columns(array $rows, array $hints)
    {
        if (!empty($hints['columns'])) {
            // a related row's field is named after the relation: author.name is "Author", book.title is "Book"
            return array_map(function ($c) use ($hints) { return array('key' => $c, 'label' => $hints['labels'][$c] ?? self::label(explode('.', $c)[0])); }, $hints['columns']);
        }
        $first = $rows[0] ?? array();
        $columns = array();
        foreach ($first as $key => $value) {
            if (in_array($key, array('id', 'passcode', 'password', 'created_at', 'updated_at'), true) || preg_match('/_id$/', $key)) {
                continue;
            }
            if (is_array($value)) {
                foreach (array('name', 'title') as $inner) {
                    if (isset($value[$inner]) && !is_array($value[$inner])) {
                        $columns[] = array('key' => "$key.$inner", 'label' => self::label($key));
                        break;
                    }
                }
                continue;
            }
            $long = false;
            foreach ($rows as $row) {
                if (is_string($row[$key] ?? null) && mb_strlen($row[$key]) > self::LONG_TEXT) { $long = true; break; }
            }
            if (!$long) {
                $columns[] = array('key' => $key, 'label' => self::label($key));
            }
        }
        return array_slice($columns, 0, self::MAX_COLUMNS);
    }

    private static function cells(array $row, array $columns)
    {
        return array_map(function ($c) use ($row) { return self::display(self::dig($row, $c['key'])); }, $columns);
    }

    private static function record(array $row, array $hints, $heading = null)
    {
        $fields = array();
        foreach ($row as $key => $value) {
            if (in_array($key, (array) ($hints['hide'] ?? array()), true) || in_array($key, array('passcode', 'password', 'id', 'created_at', 'modified_at', 'updated_at'), true)
                || preg_match('/_id$/', $key) || (!is_array($value) && (string) $value === (string) $heading)) {
                continue;   // the heading already says it; ids and timestamps are bookkeeping
            }
            if (is_array($value)) {
                $inner = $value['name'] ?? $value['title'] ?? null;
                if ($inner !== null) {
                    $fields[] = array('label' => self::label($key), 'value' => (string) $inner);
                }
                continue;
            }
            $fields[] = array('label' => self::label($key), 'value' => self::display($value));
        }
        return $fields;
    }

    // a List's rows open as Show: Answer through get_<thing> (by id) when this person may call it
    private static function opener($name, Context $context, array $hints)
    {
        if (isset($hints['open'])) {
            return isset(Capabilities::available($context)[$hints['open']['capability']]) ? $hints['open'] : null;
        }
        if (!preg_match('/^(?:list|search)_(\w+)$/', $name, $m)) {
            return null;
        }
        $getter = 'get_' . self::singular($m[1]);
        $available = Capabilities::available($context);
        return isset($available[$getter]) ? array('capability' => $getter, 'input' => 'id') : null;
    }

    // what an Answer can lead to: the capabilities of the same thing this person may call (update_, delete_), as Ask cards
    private static function actions($name, array $record, Context $context)
    {
        if (!preg_match('/^get_(\w+)$/', $name, $m) || !isset($record['id'])) {
            return array();
        }
        $actions = array();
        $available = Capabilities::available($context);
        foreach (array('update_', 'delete_') as $prefix) {
            if (isset($available[$prefix . $m[1]])) {
                $actions[] = array('capability' => $prefix . $m[1], 'label' => $prefix === 'update_' ? 'Change' : 'Delete', 'input' => array('id' => $record['id']),
                    'risk' => self::risk($available[$prefix . $m[1]]['capability']));
            }
        }
        foreach ($available as $other => $entry) {
            if (preg_match('/^\w+_' . preg_quote($m[1], '/') . '$/', $other) && !preg_match('/^(get|update|delete|list|search|create)_/', $other)
                && !$entry['capability']->webOnly && !preg_match(self::HIDDEN, $other)) {
                $field = $m[1] . '_id';
                $actions[] = array('capability' => $other, 'label' => self::title($other), 'input' => array($field => $record['id']), 'risk' => self::risk($entry['capability']));
            }
        }
        // only what can be done to this record now: an action whose hint 'applies' says when (lend a book that is not
        // out, record the return of one that is) is left out otherwise
        return array_values(array_filter($actions, function ($a) use ($record, $context) { return self::applies($a['capability'], $record, $context); }));
    }

    /**
     * Whether an action applies to a record now, from its hint 'applies':
     *   'in' => array('capability' => 'list_loans', 'field' => 'book_id')       the record is in that list
     *   'not_in' => array(...)                                                 the record is not in that list
     * The list is read as the person (once per request); one they may not read leaves the action out.
     */
    public static function applies($name, array $record, Context $context)
    {
        $rule = Hints::for($name)['applies'] ?? null;
        if (!$rule) {
            return true;
        }
        foreach (array('in' => true, 'not_in' => false) as $kind => $wanted) {
            if (empty($rule[$kind])) {
                continue;
            }
            try {
                $rows = \Caligrafy\Download::rows(Reads::plain($rule[$kind]['capability'], $context));
            } catch (\Caligrafy\ServiceError $e) {
                return false;
            }
            $keys = array_map(function ($r) use ($rule, $kind) { return (string) self::dig($r, $rule[$kind]['field']); }, $rows);
            if (in_array((string) ($record[$rule[$kind]['key'] ?? 'id'] ?? ''), $keys, true) !== $wanted) {
                return false;
            }
        }
        return true;
    }

    // what doing it costs: read (runs), write (asks for a Confirm from phase 2), destroy (a Confirm that cannot be missed)
    /**
     * What a record is called: its own title or name, else the title or name of the record it is about (a loan is
     * about its book), else its username, else #id
     */
    public static function name(array $record)
    {
        foreach (array('title', 'name') as $key) {
            if (isset($record[$key]) && is_scalar($record[$key]) && $record[$key] !== '') return (string) $record[$key];
        }
        foreach ($record as $value) {
            if (is_array($value) && !array_is_list($value)) {
                foreach (array('title', 'name') as $key) {
                    if (isset($value[$key]) && is_scalar($value[$key]) && $value[$key] !== '') return (string) $value[$key];
                }
            }
        }
        return (string) ($record['username'] ?? ('#' . ($record['id'] ?? '')));
    }

        public static function risk($capability)
    {
        return $capability->readOnly ? 'read' : ($capability->destructive ? 'destroy' : 'write');
    }

    private static function verb($name, $entry)
    {
        $verbs = array('create' => 'Add', 'update' => 'Save', 'delete' => 'Delete', 'list' => 'Show', 'get' => 'Show', 'search' => 'Search',
            'lend' => 'Lend', 'return' => 'Record the return', 'register' => 'Create the account', 'send' => 'Send', 'remind' => 'Remind', 'renew' => 'Renew');
        $first = explode('_', $name)[0];
        return $verbs[$first] ?? ($entry['capability']->readOnly ? 'Show' : 'Go');
    }

    public static function icon($name)
    {
        $hinted = Hints::for($name)['icon'] ?? null;
        if ($hinted) {
            return $hinted;
        }
        $parts = explode('_', $name);
        if (isset(self::VERB_ICONS[$parts[0]])) {
            return self::VERB_ICONS[$parts[0]];
        }
        return self::THING_ICONS[end($parts)] ?? 'sparkles';
    }

    public static function title($name)
    {
        $titles = Hints::for($name)['title'] ?? null;
        return $titles ?? ucfirst(str_replace('_', ' ', $name));
    }

    private static function label($field)
    {
        $field = preg_replace('/_id$/', '', $field);
        $label = ucfirst(str_replace('_', ' ', $field));
        // acronyms stay in capitals: ISBN, URL, ID, API, PIN
        return preg_replace_callback('/\b(isbn|url|id|api|pin|sku|vat)\b/i', function ($m) { return strtoupper($m[1]); }, $label);
    }

    // a capability's description for people: the sentences that address agents (they name other capabilities:
    // "use list_loans to see what is out") are left out
    private static function lead($description)
    {
        $sentences = preg_split('/(?<=\.)\s+/', trim((string) $description));
        $kept = array_filter($sentences, function ($s) { return !preg_match('/\b[a-z]+_[a-z_]+\b/', $s); });
        return trim(implode(' ', $kept)) ?: null;
    }

    // the description without the hints meant for agents ("(list_books, search_books)", "Optional: ")
    private static function help($description)
    {
        $description = preg_replace('/\s*\((?:[a-z_]+(?:, )?)+\)/', '', (string) $description);
        return trim(preg_replace('/^Optional:\s*/i', '', $description));
    }

    private static function noun($name, $count)
    {
        $noun = str_replace('_', ' ', preg_replace('/^(list|search)_/', '', $name));
        return $count === 1 ? self::singular($noun) : $noun;
    }

    private static function display($value)
    {
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        if ($value === null) return '';
        if (is_array($value)) return (string) ($value['name'] ?? $value['title'] ?? json_encode($value));
        return (string) $value;
    }

    private static function dig(array $row, $path)
    {
        foreach (explode('.', $path) as $part) {
            if (!is_array($row) || !array_key_exists($part, $row)) return null;
            $row = $row[$part];
        }
        return $row;
    }

    public static function plural($word)
    {
        return preg_match('/y$/', $word) ? substr($word, 0, -1) . 'ies' : $word . 's';
    }

    public static function singular($word)
    {
        return preg_match('/ies$/', $word) ? substr($word, 0, -3) . 'y' : preg_replace('/s$/', '', $word);
    }
}
