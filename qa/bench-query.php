<?php
/**
 * Micro-benchmark of the query builder (not part of the QA run): the time to build 10,000 queries, to SQL and
 * bindings, without a database. php qa/bench-query.php [iterations]
 */

$root = dirname(__DIR__);
require $root . '/framework/core/Query.php';

use Caligrafy\Query;

$iterations = max(1, (int) ($argv[1] ?? 10000));

$cases = array(
    'simple: where + orderBy + limit' => function ($i) {
        $q = (new Query('books'))->where('author_id', $i)->orderBy('title')->limit(20);
        return array($q->toSql(), $q->getBindings());
    },
    'complex: select, 6 conditions, IN of 10, search, 2 orders, page' => function ($i) {
        $q = (new Query('books'))
            ->select('id', 'title', 'books.price', 'author_id')
            ->where('price', '<', 20)
            ->where(array('status' => 'published', 'featured' => true))
            ->orWhere(function (Query $g) use ($i) { $g->where('author_id', $i)->whereNotNull('isbn'); })
            ->whereIn('category_id', array(1, 2, 3, 4, 5, 6, 7, 8, 9, $i))
            ->whereBetween('year', array(1990, 2026))
            ->search(array('title', 'summary'), 'php mvc')
            ->latest()
            ->orderBy('title')
            ->limit(20)->offset(40);
        return array($q->toSql(), $q->getBindings());
    },
    'eager settings only: with + withCount (no query run)' => function ($i) {
        $q = (new Query('books'))->with('author.country', 'tags')->withCount('reviews')->where('id', $i);
        return array($q->toSql(), $q->getBindings());
    },
);

printf("PHP %s, %d iterations each\n", PHP_VERSION, $iterations);
foreach ($cases as $name => $case) {
    $case(0);                                   // warm the identifier cache once
    $start = hrtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $case($i);
    }
    $ms = (hrtime(true) - $start) / 1e6;
    printf("%-66s %8.2f ms total  %6.2f us per query\n", $name, $ms, $ms * 1000 / $iterations);
}
