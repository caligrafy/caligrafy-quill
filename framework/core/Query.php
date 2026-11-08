<?php
/**
 * Query.php
 * The query builder of Caligrafy: Database::table('books') and Book::query() return one
 * @copyright 2026
 *
*/

namespace Caligrafy;

use InvalidArgumentException;
use LogicException;

/**
 * Class Query
 * Builds one SQL statement with bound values only. Table and column names are validated against IDENTIFIER and
 * quoted with backticks, operators come from an allow-list, values are never written into the SQL.
 *
 *   Database::table('books')->where('price', '<', 20)->orderBy('title')->limit(10)->get();   // arrays
 *   Book::where('author_id', 3)->with('author', 'tags')->paginate(20);                       // Book models
 *
 * Eager loading (with(), withCount()) runs one more query per relation (WHERE key IN (...), in chunks of 1000 keys)
 * and attaches its rows under the relation's name; it never joins in a way that repeats the main rows.
 * In development (APP_ENV other than production) the same SELECT shape run 10 times in one request is logged once
 * as a probable N+1 query (error_log), with the file and line that ran it.
 */
class Query
{
    const IDENTIFIER = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/';
    const NAME = '/^[A-Za-z_][A-Za-z0-9_]*$/';
    const IN_CHUNK = 1000;
    const NO_LIMIT = '9223372036854775807';   // OFFSET without a LIMIT (MySQL needs one; SQLite takes signed 64 bits)

    /** @var int identical SELECT shapes in a request before the N+1 warning */
    public static $threshold = 10;

    /** @var callable|null receives the N+1 warning instead of error_log() */
    public static $logger = null;

    /** @var bool|null null until the first query decides it from APP_ENV (see watching()) */
    public static $watching = null;

    private static $operators = array(
        '=' => '=', '!=' => '!=', '<>' => '<>', '<' => '<', '>' => '>', '<=' => '<=', '>=' => '>=',
        'like' => 'LIKE', 'not like' => 'NOT LIKE'
    );
    private static $relationKeys = array(
        'type' => 1, 'table' => 1, 'foreign_key' => 1, 'local_key' => 1, 'owner_key' => 1, 'pivot' => 1,
        'related_key' => 1, 'query' => 1
    );
    private static $types = array('belongsTo' => 1, 'hasOne' => 1, 'hasMany' => 1, 'belongsToMany' => 1);
    private static $quoted = array();
    private static $executor = null;
    private static $shapes = array();
    private static $warned = array();
    private static $pivots = array();     // conventional pivot name => the reversed name that exists

    private $name;              // the table as given
    private $table;             // the table quoted
    private $columns = '*';
    private $wheres = '';       // compiled conditions, without WHERE
    private $bindings = array();
    private $orders = array();
    private $limit = null;
    private $offset = null;
    private $joins = '';        // internal (belongsToMany)
    private $groups = '';       // internal (withCount)
    private $eager = array();   // name => ['config' => ?array, 'children' => [...]]
    private $counts = array();  // name => ?array
    private $model = null;      // class hydrated by get()
    private $input = null;

    /**
     * @param string $table a table name (letters, digits, _; one optional schema prefix: db.table)
     */
    public function __construct($table)
    {
        $this->table = self::quote($table);
        $this->name = $table;
    }

    /*
     * Settings shared by every query
     */

    /**
     * Sends the queries of the builder to $executor instead of Database (tests, another connection)
     * @param object|null $executor execute(string $sql, array $bindings): array of rows; optional lastInsertId() and
     *                              rowCount(); null goes back to Database
     */
    public static function useExecutor($executor = null)
    {
        if ($executor !== null && !method_exists($executor, 'execute')) {
            throw new InvalidArgumentException('Query::useExecutor() needs an object with execute($sql, $bindings)');
        }
        self::$executor = $executor;
    }

    /**
     * Whether the N+1 detection is on: decided once, from APP_ENV (off with production); watch() forces it
     */
    public static function watching()
    {
        if (self::$watching === null) {
            $env = getenv('APP_ENV');
            if ($env === false && defined('APP_ENV')) {
                $env = APP_ENV;
            }
            self::$watching = strtolower(trim((string) $env)) !== 'production';
        }
        return self::$watching;
    }

    /**
     * Turns the N+1 detection on or off (null: from APP_ENV again) and forgets the shapes counted so far
     */
    public static function watch($on = null)
    {
        self::$watching = $on === null ? null : (bool) $on;
        self::$shapes = array();
        self::$warned = array();
    }

    /**
     * Counts a SELECT by its shape (values, numbers and IN lists folded) and logs one warning when the same shape
     * reaches $threshold in the request. Called by Database::execute() and by the builder's own executor.
     */
    public static function observe($sql)
    {
        if (strncasecmp(ltrim($sql), 'select', 6) !== 0) {
            return;
        }
        $shape = self::shape($sql);
        if (!isset(self::$shapes[$shape])) {
            if (count(self::$shapes) >= 2000) {
                self::$shapes = array();      // a long-running worker: start counting again
            }
            self::$shapes[$shape] = 0;
        }
        if (++self::$shapes[$shape] === self::$threshold && !isset(self::$warned[$shape])) {
            self::$warned[$shape] = true;
            $message = sprintf(
                "[Caligrafy] Probable N+1 query: the same SELECT ran %d times in this request (%s), from %s. "
                . "Load the rows once with with(): Database::table('books')->with('author')->get() or Book::with('author')->get().",
                self::$threshold, strlen($shape) > 200 ? substr($shape, 0, 200) . '...' : $shape, self::caller()
            );
            self::$logger ? (self::$logger)($message) : error_log($message);
        }
    }

    /**
     * The shape of a query: string and number literals become ?, IN lists one ?, spaces one space, lower case
     */
    public static function shape($sql)
    {
        return strtolower(trim(preg_replace(
            array("/'(?:[^'\\\\]|\\\\.)*'/s", '/"(?:[^"\\\\]|\\\\.)*"/s', '/(?<![A-Za-z0-9_`])-?\d+(?:\.\d+)?/', '/\bin\s*\(\s*\?(?:\s*,\s*\?)*\s*\)/i', '/\s+/'),
            array('?', '?', '?', 'in (?)', ' '),
            $sql
        )));
    }

    private static function caller()
    {
        $framework = dirname(__DIR__) . DIRECTORY_SEPARATOR;
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40) as $frame) {
            if (isset($frame['file']) && strpos($frame['file'], $framework) !== 0) {
                return $frame['file'] . ':' . ($frame['line'] ?? 0);
            }
        }
        return 'the framework';
    }

    /**
     * Validates an identifier (column, table, or table.column) and returns it quoted: `books`.`title`
     */
    public static function quote($name)
    {
        if (is_string($name) && isset(self::$quoted[$name])) {
            return self::$quoted[$name];
        }
        if (!is_string($name) || !preg_match(self::IDENTIFIER, $name)) {
            throw new InvalidArgumentException(
                "[ERROR 2001] Invalid table or column name '" . (is_scalar($name) ? substr((string) $name, 0, 60) : gettype($name))
                . "': letters, digits and _ only, with one optional prefix (table.column)", 2001
            );
        }
        if (count(self::$quoted) > 2000) {
            self::$quoted = array();
        }
        return self::$quoted[$name] = '`' . str_replace('.', '`.`', $name) . '`';
    }

    /**
     * The simple plural of a name: author -> authors, category -> categories, box/class/church/wish -> ...es
     */
    public static function plural($name)
    {
        $lower = strtolower($name);
        if (preg_match('/[^aeiou]y$/', $lower)) {
            return substr($name, 0, -1) . 'ies';
        }
        if (preg_match('/(s|x|z|ch|sh)$/', $lower)) {
            return $name . 'es';
        }
        return $name . 's';
    }

    /**
     * The singular that plural() inverts: authors -> author, categories -> category, boxes -> box
     */
    public static function singular($name)
    {
        $lower = strtolower($name);
        if (preg_match('/[^aeiou]ies$/', $lower)) {
            return substr($name, 0, -3) . 'y';
        }
        if (preg_match('/(ss|x|z|ch|sh)es$/', $lower)) {
            return substr($name, 0, -2);
        }
        if (substr($lower, -1) === 's' && substr($lower, -2) !== 'ss') {
            return substr($name, 0, -1);
        }
        return $name;
    }

    /*
     * Building
     */

    /**
     * The columns to read: select('id', 'title'), select(['id', 'title']), 'books.*', 'title as name'
     */
    public function select(...$columns)
    {
        if (count($columns) === 1 && is_array($columns[0])) {
            $columns = $columns[0];
        }
        $sql = array();
        foreach ($columns as $column) {
            $sql[] = self::column($column);
        }
        $this->columns = $sql ? implode(', ', $sql) : '*';
        return $this;
    }

    private static function column($column)
    {
        if ($column === '*') {
            return '*';
        }
        if (is_string($column)) {
            if (substr($column, -2) === '.*' && preg_match(self::NAME, substr($column, 0, -2))) {
                return self::quote(substr($column, 0, -2)) . '.*';
            }
            if (stripos($column, ' as ') !== false && preg_match('/^\s*(\S+)\s+as\s+(\S+)\s*$/i', $column, $m)) {
                if (!preg_match(self::NAME, $m[2])) {
                    throw new InvalidArgumentException("[ERROR 2001] Invalid alias '" . substr($m[2], 0, 60) . "'", 2001);
                }
                return self::quote($m[1]) . ' AS `' . $m[2] . '`';
            }
        }
        return self::quote($column);
    }

    /**
     * where('price', '<', 20), where('author_id', 3) (=), where(['status' => 'new', 'author_id' => 3]),
     * where(function ($q) { $q->where(...)->orWhere(...); }) for a group in parentheses.
     * A null value with = / != becomes IS NULL / IS NOT NULL.
     */
    public function where($column, $operator = null, $value = null)
    {
        if (is_string($column)) {      // the common case, kept short: where('col', v) / where('col', op, v)
            $sql = func_num_args() === 2 ? $this->comparison($column, '=', $operator) : $this->comparison($column, $operator, $value);
            $this->wheres .= $this->wheres === '' ? $sql : ' AND ' . $sql;
            return $this;
        }
        return $this->addWhere('AND', func_num_args(), $column, $operator, $value);
    }

    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->addWhere('OR', func_num_args(), $column, $operator, $value);
    }

    private function addWhere($boolean, $argc, $column, $operator, $value)
    {
        if ($column instanceof \Closure) {
            $group = new static($this->name);
            $column($group);
            return $group->wheres === '' ? $this : $this->push($boolean, '(' . $group->wheres . ')', $group->bindings);
        }
        if (is_array($column)) {
            $parts = array();
            foreach ($column as $name => $item) {
                $parts[] = $this->comparison($name, '=', $item);      // binds in order: nothing else is added meanwhile
            }
            if (!$parts) {
                return $this;
            }
            return $this->push($boolean, count($parts) > 1 ? '(' . implode(' AND ', $parts) . ')' : $parts[0], array());
        }
        if ($argc === 2) {
            $value = $operator;
            $operator = '=';
        } elseif ($argc < 2) {
            throw new InvalidArgumentException('where() needs a column and a value');
        }
        return $this->push($boolean, $this->comparison($column, $operator, $value), array());
    }

    /**
     * `column` op ? with the value bound (IS NULL / IS NOT NULL for null with = / !=)
     */
    private function comparison($column, $operator, $value)
    {
        $quoted = is_string($column) && isset(self::$quoted[$column]) ? self::$quoted[$column] : self::quote($column);
        $op = is_string($operator) ? (self::$operators[$operator] ?? self::$operators[strtolower(trim($operator))] ?? null) : null;
        if ($op === null) {
            throw new InvalidArgumentException(
                "[ERROR 2001] Operator not allowed: use =, !=, <>, <, >, <=, >=, like or not like", 2001
            );
        }
        if ($value === null) {
            if ($op === '=') {
                return $quoted . ' IS NULL';
            }
            if ($op === '!=' || $op === '<>') {
                return $quoted . ' IS NOT NULL';
            }
            throw new InvalidArgumentException("A null value can only be compared with = or != ($column)");
        }
        $this->bindings[] = is_int($value) || is_string($value) ? $value : self::scalar($value);
        return $quoted . ' ' . $op . ' ?';
    }

    private static function scalar($value)
    {
        if (is_bool($value)) {
            return (int) $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_array($value) || is_object($value)) {
            throw new InvalidArgumentException('A condition compares one value: use whereIn() for a list');
        }
        return $value;
    }

    private function push($boolean, $sql, array $bindings)
    {
        $this->wheres .= $this->wheres === '' ? $sql : ' ' . $boolean . ' ' . $sql;
        if ($bindings) {
            array_push($this->bindings, ...$bindings);
        }
        return $this;
    }

    /**
     * whereIn('id', [1, 2, 3]); an empty list matches no row (0 = 1)
     */
    public function whereIn($column, array $values)
    {
        $quoted = self::quote($column);
        if (!$values) {
            return $this->push('AND', '0 = 1', array());
        }
        $bindings = array();
        foreach ($values as $value) {
            $bindings[] = self::scalar($value);
        }
        return $this->push('AND', $quoted . ' IN (' . str_repeat('?, ', count($bindings) - 1) . '?)', $bindings);
    }

    /**
     * whereNotIn('id', [1, 2]); an empty list adds no condition
     */
    public function whereNotIn($column, array $values)
    {
        $quoted = self::quote($column);
        if (!$values) {
            return $this;
        }
        $bindings = array();
        foreach ($values as $value) {
            $bindings[] = self::scalar($value);
        }
        return $this->push('AND', $quoted . ' NOT IN (' . str_repeat('?, ', count($bindings) - 1) . '?)', $bindings);
    }

    public function whereNull($column)
    {
        return $this->push('AND', self::quote($column) . ' IS NULL', array());
    }

    public function whereNotNull($column)
    {
        return $this->push('AND', self::quote($column) . ' IS NOT NULL', array());
    }

    /**
     * whereBetween('price', [10, 20]): both ends included
     */
    public function whereBetween($column, array $range)
    {
        if (count($range) !== 2) {
            throw new InvalidArgumentException('whereBetween() needs two values: [from, to]');
        }
        $range = array_values($range);
        return $this->push('AND', self::quote($column) . ' BETWEEN ? AND ?', array(self::scalar($range[0]), self::scalar($range[1])));
    }

    /**
     * search(['title', 'summary'], 'php mvc'): (title LIKE ? OR summary LIKE ?) with %php mvc% bound, % and _ of
     * the keywords escaped so they match themselves. Empty keywords add no condition.
     */
    public function search(array $columns, $keywords)
    {
        if (!$columns) {
            throw new InvalidArgumentException('search() needs at least one column');
        }
        $keywords = trim((string) $keywords);
        if ($keywords === '') {
            return $this;
        }
        $pattern = '%' . addcslashes($keywords, '\\%_') . '%';
        $parts = array();
        $bindings = array();
        foreach ($columns as $column) {
            $parts[] = self::quote($column) . ' LIKE ?';
            $bindings[] = $pattern;
        }
        return $this->push('AND', '(' . implode(' OR ', $parts) . ')', $bindings);
    }

    /**
     * orderBy('title'), orderBy('created_at', 'desc')
     */
    public function orderBy($column, $direction = 'asc')
    {
        if ($direction !== 'asc' && $direction !== 'desc') {
            $direction = is_string($direction) ? strtolower(trim($direction)) : '';
        }
        if ($direction !== 'asc' && $direction !== 'desc') {
            throw new InvalidArgumentException("[ERROR 2001] orderBy() direction must be 'asc' or 'desc'", 2001);
        }
        $this->orders[] = self::quote($column) . ($direction === 'asc' ? ' ASC' : ' DESC');
        return $this;
    }

    public function latest($column = 'created_at')
    {
        return $this->orderBy($column, 'desc');
    }

    public function oldest($column = 'created_at')
    {
        return $this->orderBy($column, 'asc');
    }

    public function limit($count)
    {
        $this->limit = $count === null ? null : self::whole($count, 'limit');
        return $this;
    }

    public function offset($count)
    {
        $this->offset = $count === null ? null : self::whole($count, 'offset');
        return $this;
    }

    private static function whole($value, $what)
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }
        throw new InvalidArgumentException("$what() needs a whole number >= 0");
    }

    /**
     * The input paginate() reads the page from (instead of $_GET): ->input($request->parameters)
     */
    public function input(array $input)
    {
        $this->input = $input;
        return $this;
    }

    /**
     * Rows become instances of $class (a Model) through its arrayToModel()
     */
    public function models($class = Model::class)
    {
        if (!is_string($class) || !is_a($class, Model::class, true)) {
            throw new InvalidArgumentException('models() needs a class that extends Caligrafy\Model');
        }
        $this->model = $class;
        return $this;
    }

    /**
     * Rows stay associative arrays (the default of Database::table(), and undoes models())
     */
    public function arrays()
    {
        $this->model = null;
        return $this;
    }

    /**
     * with('author', 'tags', 'author.country'), with(['author' => ['table' => 'people', 'foreign_key' => 'writer_id']]),
     * with(['tags' => ['pivot' => 'tag_links']]), with(['books' => function (Query $q) { $q->where('published', 1); }])
     */
    public function with(...$relations)
    {
        foreach ($relations as $relation) {
            if (is_string($relation)) {
                $this->addEager($relation, null);
            } elseif (is_array($relation)) {
                foreach ($relation as $name => $config) {
                    is_int($name) ? $this->addEager($config, null) : $this->addEager($name, $config);
                }
            } else {
                throw new InvalidArgumentException('with() takes relation names or [name => settings]');
            }
        }
        return $this;
    }

    /**
     * withCount('books'): adds books_count to each row (hasMany or belongsToMany), one GROUP BY query
     */
    public function withCount(...$relations)
    {
        foreach ($relations as $relation) {
            $items = is_array($relation) ? $relation : array($relation);
            foreach ($items as $name => $config) {
                if (is_int($name)) {
                    $name = $config;
                    $config = null;
                }
                if (!is_string($name) || !preg_match(self::NAME, $name)) {
                    throw new InvalidArgumentException("[ERROR 2001] Invalid relation name for withCount()", 2001);
                }
                $this->counts[$name] = self::settings($config);
            }
        }
        return $this;
    }

    private function addEager($path, $config)
    {
        if (!is_string($path)) {
            throw new InvalidArgumentException('A relation name must be a string');
        }
        $config = self::settings($config);
        $node = &$this->eager;
        $segments = explode('.', $path);
        $last = count($segments) - 1;
        foreach ($segments as $i => $segment) {
            if (!preg_match(self::NAME, $segment)) {
                throw new InvalidArgumentException("[ERROR 2001] Invalid relation name '" . substr($path, 0, 60) . "'", 2001);
            }
            if (!isset($node[$segment])) {
                $node[$segment] = array('config' => null, 'children' => array());
            }
            if ($i === $last && $config !== null) {
                $node[$segment]['config'] = $config;
            }
            $node = &$node[$segment]['children'];
        }
        unset($node);
    }

    private static function settings($config)
    {
        if ($config === null) {
            return null;
        }
        if ($config instanceof \Closure) {
            return array('query' => $config);
        }
        if (!is_array($config)) {
            throw new InvalidArgumentException('Relation settings are an array (table, foreign_key, local_key, owner_key, pivot, related_key, type, query) or a function');
        }
        foreach ($config as $key => $value) {
            if (!isset(self::$relationKeys[$key])) {
                throw new InvalidArgumentException("Unknown relation setting '$key': use table, foreign_key, local_key, owner_key, pivot, related_key, type or query");
            }
        }
        if (isset($config['type']) && !isset(self::$types[$config['type']])) {
            throw new InvalidArgumentException("Unknown relation type: use belongsTo, hasOne, hasMany or belongsToMany");
        }
        foreach (array('table', 'pivot') as $key) {
            if (isset($config[$key]) && $config[$key] !== true) {
                self::quote($config[$key]);
            }
        }
        foreach (array('foreign_key', 'local_key', 'owner_key', 'related_key') as $key) {
            if (isset($config[$key]) && (!is_string($config[$key]) || !preg_match(self::NAME, $config[$key]))) {
                throw new InvalidArgumentException("[ERROR 2001] Invalid $key '" . (is_string($config[$key]) ? substr($config[$key], 0, 60) : gettype($config[$key])) . "' for a relation", 2001);
            }
        }
        if (isset($config['query']) && !is_callable($config['query'])) {
            throw new InvalidArgumentException("The 'query' setting of a relation is a function (Query \$q)");
        }
        return $config;
    }

    /**
     * How a relation of $parent is read, by convention unless $config says otherwise:
     * - singular name (author) on books: belongsTo, books.author_id -> authors.id
     * - plural name (books) on authors: hasMany, books.author_id = authors.id
     * - ['pivot' => true|'name'] or type belongsToMany (tags on books): the pivot Model::belongsToMany() creates,
     *   books_tags (<this table>_<related table>), or tags_books when only that one exists (tried when books_tags
     *   fails, then remembered); books_tags.book_id = books.id, books_tags.tag_id = tags.id
     * @return array type, table, foreign_key, local_key, owner_key, pivot, related_key, query
     */
    public static function relation($parent, $name, $config = null)
    {
        $config = $config ?? array();
        $parentName = strpos($parent, '.') !== false ? substr($parent, strrpos($parent, '.') + 1) : $parent;
        $singular = self::singular($name);
        $type = $config['type'] ?? (isset($config['pivot']) ? 'belongsToMany' : ($singular === $name ? 'belongsTo' : 'hasMany'));
        $owner = self::singular($parentName);
        $r = array('type' => $type, 'query' => $config['query'] ?? null, 'pivot' => null, 'related_key' => null, 'pivot_reverse' => null);
        if ($type === 'belongsTo') {
            $r['table'] = $config['table'] ?? self::plural($name);
            $r['foreign_key'] = $config['foreign_key'] ?? $name . '_id';
            $r['owner_key'] = $config['owner_key'] ?? 'id';
            $r['local_key'] = null;
        } else {
            $r['table'] = $config['table'] ?? ($type === 'hasOne' ? self::plural($name) : $name);
            $r['foreign_key'] = $config['foreign_key'] ?? $owner . '_id';
            $r['local_key'] = $config['local_key'] ?? 'id';
            $r['owner_key'] = $config['owner_key'] ?? 'id';
            if ($type === 'belongsToMany') {
                $pivot = $config['pivot'] ?? true;
                if ($pivot === true) {
                    // the name Model::belongsToMany() gives the pivot it creates: <table1>_<table2>, or
                    // <table2>_<table1> when that one exists
                    $pivot = $parent . '_' . $r['table'];
                    $reverse = $r['table'] . '_' . $parent;
                    if (isset(self::$pivots[$pivot])) {
                        $pivot = self::$pivots[$pivot];
                    } elseif ($reverse !== $pivot) {
                        $r['pivot_reverse'] = $reverse;
                    }
                }
                $r['pivot'] = $pivot;
                $r['related_key'] = $config['related_key'] ?? self::singular($name) . '_id';
            }
        }
        foreach (array('table', 'pivot', 'pivot_reverse') as $key) {
            if ($r[$key] !== null) {
                self::quote($r[$key]);
            }
        }
        foreach (array('foreign_key', 'local_key', 'owner_key', 'related_key') as $key) {
            if ($r[$key] !== null && (!is_string($r[$key]) || !preg_match(self::NAME, $r[$key]))) {
                throw new InvalidArgumentException("[ERROR 2001] Invalid $key for the relation '$name'", 2001);
            }
        }
        return $r;
    }

    /*
     * SQL
     */

    /**
     * The SELECT this query runs (placeholders for the values: see getBindings())
     */
    public function toSql()
    {
        $sql = 'SELECT ' . $this->columns . ' FROM ' . $this->table . $this->joins;
        if ($this->wheres !== '') {
            $sql .= ' WHERE ' . $this->wheres;
        }
        $sql .= $this->groups;
        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ($this->limit === null ? ' LIMIT ' . self::NO_LIMIT : '') . ' OFFSET ' . $this->offset;
        }
        return $sql;
    }

    public function getBindings()
    {
        return $this->bindings;
    }

    private function run($sql, array $bindings)
    {
        if (self::$executor !== null) {
            if (self::$watching ?? self::watching()) {
                self::observe($sql);
            }
            return self::$executor->execute($sql, $bindings);
        }
        return Database::toArray(Database::execute($sql, $bindings));
    }

    /*
     * Reading
     */

    /**
     * The rows: arrays, or models after models() (Book::query() hydrates Book by default)
     */
    public function get()
    {
        $rows = $this->run($this->toSql(), $this->bindings);
        if ($rows && $this->eager) {
            $rows = $this->loadRelations($rows, $this->name, $this->eager);
        }
        if ($rows && $this->counts) {
            $rows = $this->loadCounts($rows, $this->name, $this->counts);
        }
        if ($rows && $this->model !== null) {
            $class = $this->model;
            $prototype = new $class($this->name);
            foreach ($rows as $i => $row) {
                $rows[$i] = $prototype->arrayToModel($row);
            }
        }
        return $rows;
    }

    public function first()
    {
        $query = clone $this;
        $query->limit = 1;
        $rows = $query->get();
        return $rows ? $rows[0] : null;
    }

    public function find($id, $column = 'id')
    {
        return (clone $this)->where($column, $id)->first();
    }

    /**
     * The value of one column of the first row, or null
     */
    public function value($column)
    {
        $query = $this->bare();
        $query->columns = self::column($column);
        $query->limit = 1;
        $rows = $query->get();
        return $rows ? reset($rows[0]) : null;
    }

    /**
     * pluck('title') -> ['A', 'B']; pluck('title', 'id') -> [1 => 'A', 2 => 'B']
     */
    public function pluck($column, $key = null)
    {
        $query = $this->bare();
        $query->columns = self::column($column) . ($key !== null ? ', ' . self::column($key) : '');
        $rows = $query->get();
        return array_column($rows, self::key($column), $key !== null ? self::key($key) : null);
    }

    private static function key($column)
    {
        if (stripos($column, ' as ') !== false && preg_match('/\s+as\s+(\S+)\s*$/i', $column, $m)) {
            return $m[1];
        }
        $dot = strrpos($column, '.');
        return $dot === false ? $column : substr($column, $dot + 1);
    }

    public function count()
    {
        $query = $this->bare();
        $query->columns = 'COUNT(*) AS `aggregate`';
        $query->orders = array();
        $query->limit = null;
        $query->offset = null;
        $rows = $query->get();
        return $rows ? (int) $rows[0]['aggregate'] : 0;
    }

    public function exists()
    {
        $query = $this->bare();
        $query->columns = '1 AS `found`';
        $query->orders = array();
        $query->limit = 1;
        $query->offset = null;
        return (bool) $query->get();
    }

    /**
     * paginate(20): ['data' => rows, 'total' => n, 'page' => p, 'per_page' => 20, 'pages' => m]
     * $page null reads 'page' from input() or $_GET; anything but a whole number >= 1 is page 1.
     * A page past the last one returns no rows (and runs no second query).
     */
    public function paginate($perPage = 20, $page = null)
    {
        $perPage = filter_var($perPage, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
        if ($perPage === false) {
            throw new InvalidArgumentException('paginate() needs a number of rows per page >= 1');
        }
        if ($page === null) {
            $source = $this->input ?? $_GET;
            $page = $source['page'] ?? 1;
        }
        $page = is_scalar($page) ? filter_var($page, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1))) : false;
        $page = $page === false ? 1 : $page;
        $total = $this->count();
        $offset = ($page - 1) * $perPage;
        $data = array();
        if ($offset < $total) {
            $query = clone $this;
            $query->limit = $perPage;
            $query->offset = $offset;
            $data = $query->get();
        }
        return array(
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
        );
    }

    /**
     * Runs $callback($rows, $chunkNumber) on chunks of $size rows (ordered by id unless the query has an order);
     * a callback that returns false stops. Returns false when stopped, true otherwise.
     */
    public function chunk($size, callable $callback)
    {
        $size = self::whole($size, 'chunk');
        if ($size < 1) {
            throw new InvalidArgumentException('chunk() needs a size >= 1');
        }
        $base = clone $this;
        if (!$base->orders) {
            $base->orderBy('id');
        }
        for ($chunk = 0; ; $chunk++) {
            $query = clone $base;
            $query->limit = $size;
            $query->offset = $chunk * $size;
            $rows = $query->get();
            if (!$rows) {
                return true;
            }
            if ($callback($rows, $chunk + 1) === false) {
                return false;
            }
            if (count($rows) < $size) {
                return true;
            }
        }
    }

    /**
     * A copy without eager loading or hydration (count, exists, value, pluck)
     */
    private function bare()
    {
        $query = clone $this;
        $query->eager = array();
        $query->counts = array();
        $query->model = null;
        return $query;
    }

    /*
     * Writing
     */

    /**
     * insert(['title' => 'A', 'price' => 10]) or insert([[...], [...]]) (one INSERT for every row, same columns).
     * Arrays and objects are saved as JSON, booleans as 0/1. Returns the last insert id.
     */
    public function insert(array $values)
    {
        $rows = $values && array_is_list($values) && is_array($values[0]) ? $values : array($values);
        $columns = array_keys($rows[0]);
        $quoted = array();
        foreach ($columns as $column) {
            $quoted[] = self::quote($column);
        }
        $bindings = array();
        $groups = array();
        $group = '(' . ($columns ? str_repeat('?, ', count($columns) - 1) . '?' : '') . ')';
        foreach ($rows as $row) {
            if (!is_array($row) || count($row) !== count($columns)) {
                throw new InvalidArgumentException('insert(): every row needs the same columns');
            }
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new InvalidArgumentException("insert(): a row has no '$column'");
                }
                $bindings[] = self::writable($row[$column]);
            }
            $groups[] = $group;
        }
        $this->run('INSERT INTO ' . $this->table . ' (' . implode(', ', $quoted) . ') VALUES ' . implode(', ', $groups), $bindings);
        return $this->lastInsertId();
    }

    /**
     * update(['price' => 12]): needs a where() (LogicException otherwise). Returns the number of rows changed.
     */
    public function update(array $values)
    {
        $this->needsWhere('update');
        if (!$values) {
            throw new InvalidArgumentException('update() needs at least one column');
        }
        $set = array();
        $bindings = array();
        foreach ($values as $column => $value) {
            $set[] = self::quote($column) . ' = ?';
            $bindings[] = self::writable($value);
        }
        return $this->write('UPDATE ' . $this->table . ' SET ' . implode(', ', $set), $bindings);
    }

    /**
     * delete(): needs a where() (LogicException otherwise). Returns the number of rows deleted.
     */
    public function delete()
    {
        $this->needsWhere('delete');
        return $this->write('DELETE FROM ' . $this->table, array());
    }

    /**
     * increment('views'), increment('stock', 5, ['restocked_at' => date('Y-m-d H:i:s')]): needs a where()
     */
    public function increment($column, $amount = 1, array $extra = array())
    {
        return $this->step($column, $amount, $extra, '+', 'increment');
    }

    public function decrement($column, $amount = 1, array $extra = array())
    {
        return $this->step($column, $amount, $extra, '-', 'decrement');
    }

    private function step($column, $amount, array $extra, $sign, $what)
    {
        $this->needsWhere($what);
        if (!is_int($amount) && !is_float($amount) && !(is_string($amount) && is_numeric($amount))) {
            throw new InvalidArgumentException("$what() needs a number");
        }
        $quoted = self::quote($column);
        $set = array($quoted . ' = ' . $quoted . ' ' . $sign . ' ?');
        $bindings = array($amount);
        foreach ($extra as $name => $value) {
            $set[] = self::quote($name) . ' = ?';
            $bindings[] = self::writable($value);
        }
        return $this->write('UPDATE ' . $this->table . ' SET ' . implode(', ', $set), $bindings);
    }

    private function write($sql, array $bindings)
    {
        $sql .= ' WHERE ' . $this->wheres;
        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($bindings) {
            array_push($bindings, ...$this->bindings);
        } else {
            $bindings = $this->bindings;
        }
        $this->run($sql, $bindings);
        return $this->rowCount();
    }

    private function needsWhere($what)
    {
        if ($this->wheres === '') {
            throw new LogicException("$what() needs a where(): ->where('id', \$id)->$what(...). For every row of the table, write the SQL with Database::execute()");
        }
    }

    private static function writable($value)
    {
        if (is_bool($value)) {
            return (int) $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        return $value;
    }

    private function lastInsertId()
    {
        if (self::$executor !== null) {
            return method_exists(self::$executor, 'lastInsertId') ? self::$executor->lastInsertId() : null;
        }
        return Database::lastInsertId();
    }

    private function rowCount()
    {
        if (self::$executor !== null) {
            return method_exists(self::$executor, 'rowCount') ? (int) self::$executor->rowCount() : 0;
        }
        return Database::rowCount();
    }

    /*
     * Eager loading
     */

    private function loadRelations(array $rows, $table, array $tree)
    {
        foreach ($tree as $name => $node) {
            $rows = $this->loadRelation($rows, $name, self::relation($table, $name, $node['config']), $node['children']);
        }
        return $rows;
    }

    private function loadRelation(array $rows, $name, array $r, array $children)
    {
        if ($r['type'] === 'belongsTo') {
            $keys = self::keys($rows, $r['foreign_key']);
            $related = $keys ? $this->fetchIn($r['table'], $r['owner_key'], $keys, $r['query']) : array();
            if ($children && $related) {
                $related = $this->loadRelations($related, $r['table'], $children);
            }
            $map = array();
            foreach ($related as $row) {
                $map[(string) $row[$r['owner_key']]] = $row;
            }
            foreach ($rows as $i => $row) {
                $key = $row[$r['foreign_key']] ?? null;
                $rows[$i][$name] = $key !== null ? ($map[(string) $key] ?? null) : null;
            }
            return $rows;
        }

        $keys = self::keys($rows, $r['local_key']);
        if ($r['type'] === 'belongsToMany') {
            $related = $keys ? $this->throughPivot($r, function (array $r) use ($keys) {
                $pivot = self::quote($r['pivot']);
                $table = self::quote($r['table']);
                $prepare = function (Query $q) use ($r, $pivot, $table) {
                    $q->columns = $table . '.*, ' . $pivot . '.`' . $r['foreign_key'] . '` AS `__pivot_key`';
                    $q->joins = ' INNER JOIN ' . $pivot . ' ON ' . $pivot . '.`' . $r['related_key'] . '` = ' . $table . '.`' . $r['owner_key'] . '`';
                };
                return $this->fetchIn($r['table'], $r['pivot'] . '.' . $r['foreign_key'], $keys, $r['query'], $prepare);
            }) : array();
            $groupKey = '__pivot_key';
        } else {
            $related = $keys ? $this->fetchIn($r['table'], $r['foreign_key'], $keys, $r['query']) : array();
            $groupKey = $r['foreign_key'];
        }
        if ($children && $related) {
            $related = $this->loadRelations($related, $r['table'], $children);
        }
        $groups = array();
        foreach ($related as $row) {
            $key = (string) $row[$groupKey];
            if ($groupKey === '__pivot_key') {
                unset($row['__pivot_key']);
            }
            $groups[$key][] = $row;
        }
        $one = $r['type'] === 'hasOne';
        foreach ($rows as $i => $row) {
            $key = $row[$r['local_key']] ?? null;
            $found = $key !== null ? ($groups[(string) $key] ?? array()) : array();
            $rows[$i][$name] = $one ? ($found[0] ?? null) : $found;
        }
        return $rows;
    }

    private function loadCounts(array $rows, $table, array $counts)
    {
        foreach ($counts as $name => $config) {
            $r = self::relation($table, $name, $config);
            if ($r['type'] === 'belongsTo' || $r['type'] === 'hasOne') {
                throw new InvalidArgumentException("withCount('$name') counts a plural relation (hasMany or belongsToMany)");
            }
            $keys = self::keys($rows, $r['local_key']);
            $map = array();
            if ($keys) {
                $run = function (array $r) use ($keys) {
                    if ($r['type'] === 'belongsToMany') {
                        $from = $r['pivot'];
                        $column = $r['pivot'] . '.' . $r['foreign_key'];
                        $prepare = $r['query'] ? function (Query $q) use ($r) {
                            $q->joins = ' INNER JOIN ' . self::quote($r['table']) . ' ON ' . self::quote($r['pivot']) . '.`' . $r['related_key'] . '` = ' . self::quote($r['table']) . '.`' . $r['owner_key'] . '`';
                        } : null;
                    } else {
                        $from = $r['table'];
                        $column = $r['foreign_key'];
                        $prepare = null;
                    }
                    $quoted = self::quote($column);
                    $count = function (Query $q) use ($quoted, $prepare) {
                        if ($prepare) {
                            $prepare($q);
                        }
                        $q->columns = $quoted . ' AS `__key`, COUNT(*) AS `aggregate`';
                        $q->groups = ' GROUP BY ' . $quoted;
                    };
                    return $this->fetchIn($from, $column, $keys, $r['query'], $count);
                };
                foreach ($r['type'] === 'belongsToMany' ? $this->throughPivot($r, $run) : $run($r) as $row) {
                    $map[(string) $row['__key']] = (int) $row['aggregate'];
                }
            }
            foreach ($rows as $i => $row) {
                $key = $row[$r['local_key']] ?? null;
                $rows[$i][$name . '_count'] = $key !== null ? ($map[(string) $key] ?? 0) : 0;
            }
        }
        return $rows;
    }

    /**
     * Runs $run($r) on the conventional pivot (<table>_<related>); when that query fails and the reversed name
     * (<related>_<table>) was not ruled out, runs it on the reversed one and remembers it for the process, as
     * Model::belongsToMany() uses the reversed pivot when it exists. The first error is thrown when both fail.
     */
    private function throughPivot(array $r, callable $run)
    {
        if ($r['pivot_reverse'] === null) {
            return $run($r);
        }
        try {
            return $run($r);
        } catch (\Throwable $first) {
            $conventional = $r['pivot'];
            $r['pivot'] = $r['pivot_reverse'];
            try {
                $rows = $run($r);
            } catch (\Throwable $second) {
                throw $first;
            }
            self::$pivots[$conventional] = $r['pivot'];
            return $rows;
        }
    }

    /**
     * SELECT ... FROM $table WHERE ($constraint) AND $column IN (...): one query per 1000 keys
     */
    private function fetchIn($table, $column, array $keys, $constraint = null, $prepare = null)
    {
        $out = array();
        foreach (array_chunk($keys, self::IN_CHUNK) as $chunk) {
            $query = new static($table);
            if ($constraint) {
                $constraint($query);
                if ($query->wheres !== '') {
                    $query->wheres = '(' . $query->wheres . ')';
                }
            }
            if ($prepare) {
                $prepare($query);
            }
            $query->model = null;
            $query->whereIn($column, $chunk);
            foreach ($query->get() as $row) {
                $out[] = $row;
            }
        }
        return $out;
    }

    private static function keys(array $rows, $column)
    {
        $keys = array();
        foreach ($rows as $row) {
            $value = $row[$column] ?? null;
            if ($value !== null && $value !== '') {
                $keys[(string) $value] = $value;
            }
        }
        return array_values($keys);
    }
}
