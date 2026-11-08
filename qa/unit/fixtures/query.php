<?php
/**
 * Fixtures of the query builder tests: an executor that records the queries and answers from in-memory tables,
 * and models with the three ways to name their table
 */

use Caligrafy\Model;

/**
 * Records every query; SELECTs are answered from $tables, filtered by the `col` IN (...) of the SQL when there is one
 * (the last bindings), or by a $responder
 */
final class QaFakeExecutor
{
    public $queries = array();
    public $insertId = 41;
    public $affected = 3;
    private $tables;
    private $responder;

    public function __construct(array $tables = array(), ?callable $responder = null)
    {
        $this->tables = $tables;
        $this->responder = $responder;
    }

    public function execute($sql, array $bindings)
    {
        $this->queries[] = array($sql, $bindings);
        if ($this->responder) {
            return ($this->responder)($sql, $bindings);
        }
        if (stripos($sql, 'SELECT') !== 0 || !preg_match('/ FROM `([a-z_]+)`/', $sql, $from)) {
            return array();
        }
        $rows = $this->tables[$from[1]] ?? array();
        // a pivot join: `tags`.*, `book_tag`.`book_id` AS `__pivot_key` ... INNER JOIN `book_tag` ON `book_tag`.`tag_id` = `tags`.`id`
        if (preg_match('/`([a-z_]+)`\.`([a-z_]+)` AS `__pivot_key` FROM `[a-z_]+` INNER JOIN `[a-z_]+` ON `[a-z_]+`\.`([a-z_]+)` = `[a-z_]+`\.`([a-z_]+)`/', $sql, $m)) {
            $joined = array();
            foreach ($this->tables[$m[1]] ?? array() as $link) {
                foreach ($rows as $row) {
                    if ((string) $row[$m[4]] === (string) $link[$m[3]]) {
                        $joined[] = $row + array('__pivot_key' => $link[$m[2]]);
                    }
                }
            }
            $rows = $joined;
            $filter = '__pivot_key';
        }
        // a WHERE of `col` = ? / `col` < ? joined by AND only (no OR, no group) filters the rows too
        if (preg_match('/ WHERE (`[a-z_]+` (=|<) \?(?: AND `[a-z_]+` (?:=|<) \?)*)(?: ORDER| LIMIT|$)/', $sql, $where)) {
            preg_match_all('/`([a-z_]+)` (=|<) \?/', $where[1], $conditions, PREG_SET_ORDER);
            foreach ($conditions as $i => $c) {
                $value = $bindings[$i];
                $rows = array_values(array_filter($rows, function ($row) use ($c, $value) {
                    return $c[2] === '=' ? (string) ($row[$c[1]] ?? '') === (string) $value : ($row[$c[1]] ?? 0) < $value;
                }));
            }
        }
        if (preg_match('/`([a-z_]+)` IN \(([?, ]+)\)/', $sql, $in)) {
            $filter = $filter ?? $in[1];
            $values = array_map('strval', array_slice($bindings, -substr_count($in[2], '?')));
            $rows = array_values(array_filter($rows, function ($row) use ($filter, $values) {
                return isset($row[$filter]) && in_array((string) $row[$filter], $values, true);
            }));
        }
        if (strpos($sql, 'COUNT(*) AS `aggregate`') !== false && strpos($sql, 'GROUP BY') !== false) {
            $counts = array();
            foreach ($rows as $row) {
                $key = (string) $row[$filter];
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
            $out = array();
            foreach ($counts as $key => $n) {
                $out[] = array('__key' => $key, 'aggregate' => $n);
            }
            return $out;
        }
        return $rows;
    }

    public function lastInsertId()
    {
        return $this->insertId;
    }

    public function rowCount()
    {
        return $this->affected;
    }

    public function sql()
    {
        return array_map(function ($q) { return $q[0]; }, $this->queries);
    }
}

class QaBook extends Model
{
    public $id;
    public $title;
    public $author_id;
}

class QaCategory extends Model
{
}

class QaPerson extends Model
{
    protected static $tableName = 'people';
}

class QaShelf extends Model
{
    public $table = 'book_shelves';
}

/**
 * A model that overrides find(): Book::find() is then the instance method, parent::find() the framework's
 */
class QaAudited extends Model
{
    public $found = array();

    public function find($id)
    {
        $this->found[] = $id;
        return parent::find($id);
    }
}
