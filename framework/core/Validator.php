<?php

/**
 * Validator.php
 * Class that validates inputs
 * @author Dory A.Azar
 * @copyright 2019
 * @version 1.0
 *
*/

/**
 * Class that validates inputs
 * @version 1.0
 *
 * Rules of the framework on top of GUMP's (2.19.0), usable in a capability's $rules:
 *   exists,<table>.<column>                                   the value is in that column ("There is no such author")
 *   unique,<table>.<column>[,<ignoreColumn>,<ignoreField>]    the value is not in that column yet ("This email is already
 *                                                             used"); the row whose <ignoreColumn> equals the input
 *                                                             <ignoreField> is ignored (an update: unique,users.email,id,id)
 *   min_numeric,current_year / max_numeric,current_year       the year of today, when the input is checked
 * exists and unique run a bound query through the query builder (Caligrafy\Query, the database of the app or
 * Query::useExecutor); the table and the column are checked as the builder checks identifiers. An empty optional
 * input is not checked, as with every GUMP rule.
 */

namespace Caligrafy;

class Validator extends \GUMP
{
    // the messages of the framework's rules: built from the table and the column unless the app sets its own
    const MESSAGES = array('exists' => 'There is no such {param}', 'unique' => 'This {param} is already used');

    private static $registered = false;

    public function __construct(string $lang = 'en')
    {
        parent::__construct($lang);
        self::registerRules();
    }

    /**
     * Registers the rules exists and unique with GUMP, once. A rule of the same name that the app added first is kept.
     */
    public static function registerRules()
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        $rules = array(
            'exists' => function ($field, array $input, array $params = array(), $value = null) {
                return self::exists($params, $value);
            },
            'unique' => function ($field, array $input, array $params = array(), $value = null) {
                return self::unique($params, $value, $input);
            },
        );
        foreach ($rules as $name => $callback) {
            try {
                \GUMP::add_validator($name, $callback, self::MESSAGES[$name]);
            } catch (\Exception $e) {
                // the app defined it already: its own rule stays
            }
        }
    }

    /**
     * Whether a value is in table.column (the rule exists)
     */
    public static function exists(array $params, $value)
    {
        list($table, $column) = self::target('exists', $params);
        return (new Query($table))->where($column, $value)->exists();
    }

    /**
     * Whether a value is not yet in table.column (the rule unique), ignoring the row whose column $params[1] equals
     * the input $params[2]
     */
    public static function unique(array $params, $value, array $input = array())
    {
        list($table, $column) = self::target('unique', $params);
        $query = (new Query($table))->where($column, $value);
        $ignoreColumn = isset($params[1]) ? trim((string) $params[1]) : '';
        if ($ignoreColumn !== '') {
            $ignoreField = isset($params[2]) && trim((string) $params[2]) !== '' ? trim((string) $params[2]) : $ignoreColumn;
            $ignored = $input[$ignoreField] ?? null;
            if ($ignored !== null && $ignored !== '' && is_scalar($ignored)) {
                $query->where($ignoreColumn, '!=', $ignored);
            }
        }
        return !$query->exists();
    }

    /**
     * The rules as GUMP reads them: unique's extra arguments joined with ; (GUMP keeps one , only) and current_year
     * replaced by the year of today
     * @param string|array $rules 'required|max_numeric,current_year' (or GUMP's array form, kept as it is)
     */
    public static function normalize($rules)
    {
        if (!is_string($rules) || (strpos($rules, 'current_year') === false && stripos($rules, 'unique,') === false && stripos($rules, 'exists,') === false)) {
            return $rules;
        }
        $parts = explode(self::$rules_delimiter, $rules);
        foreach ($parts as $i => $part) {
            $pieces = explode(self::$rules_parameters_delimiter, $part);
            $name = trim($pieces[0]);
            if (count($pieces) < 2) {
                continue;
            }
            if (($name === 'unique' || $name === 'exists') && count($pieces) > 2) {
                $parts[$i] = $name . self::$rules_parameters_delimiter . implode(self::$rules_parameters_arrays_delimiter, array_map('trim', array_slice($pieces, 1)));
            } elseif (($name === 'min_numeric' || $name === 'max_numeric') && trim($pieces[1]) === 'current_year') {
                $parts[$i] = $name . self::$rules_parameters_delimiter . date('Y');
            }
        }
        return implode(self::$rules_delimiter, $parts);
    }

    public function validate(array $input, array $ruleset)
    {
        return parent::validate($input, array_map(array(self::class, 'normalize'), $ruleset));
    }

    /**
     * The errors by field; exists and unique say what is missing or taken: "There is no such author",
     * "This email is already used" (GUMP::set_error_message() or the per-field messages replace them)
     */
    public function get_errors_array()
    {
        $result = parent::get_errors_array();
        $messages = $this->get_messages();
        foreach ($this->errors as $error) {
            $rule = $error['rule'];
            if (!isset(self::MESSAGES[$rule]) || ($messages[$rule] ?? null) !== self::MESSAGES[$rule]
                || isset($this->fields_error_messages[$error['field']][$rule])) {
                continue;
            }
            $target = (string) ($error['params'][0] ?? '');
            $dot = strrpos($target, '.');
            $table = $dot === false ? $target : substr($target, 0, $dot);
            $column = $dot === false ? $target : substr($target, $dot + 1);
            if (($pos = strrpos($table, '.')) !== false) {
                $table = substr($table, $pos + 1);      // db.table.column
            }
            $name = $rule === 'exists' ? Query::singular($table) : $column;
            $result[$error['field']] = strtr(self::MESSAGES[$rule], array('{param}' => str_replace(array('_', '-'), ' ', $name)));
        }
        return $result;
    }

    // table.column (or db.table.column) of exists and unique, checked as the query builder checks identifiers
    private static function target($rule, array $params)
    {
        $target = trim((string) ($params[0] ?? ''));
        $dot = strrpos($target, '.');
        if ($dot === false || $dot === 0 || $dot === strlen($target) - 1) {
            throw new \InvalidArgumentException("The rule $rule needs a table and a column: $rule,authors.id (not '$rule," . substr($target, 0, 60) . "')");
        }
        $table = substr($target, 0, $dot);
        $column = substr($target, $dot + 1);
        Query::quote($table);
        if (!preg_match(Query::NAME, $column)) {
            Query::quote($column);   // throws ERROR 2001
            throw new \InvalidArgumentException("The rule $rule: invalid column '$column'");
        }
        return array($table, $column);
    }

    public function check($data, $rules = array(), $filters = array())
    {
        $output = true;
        if (!empty($rules) && !empty($filters)) {
            $this->validation_rules($rules);
            $this->filter_rules($filters);
            $valid = $this->run($data);
            $output = $valid? $valid : $this->get_errors_array();
        } else if(!empty($rules)) {
            $valid = $this->validate($data, $rules);
            $output = $valid === true? $valid : $this->get_errors_array();
        } else if (!empty($filters)) {
            $valid = $this->filter($data, $filters);
            $output = $valid;
        }
        return $output;

    }

}
