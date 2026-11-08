<?php
/**
 * TestCase.php
 * The base of an application's tests (2.18.0): php caligrafer.php test runs tests/*Test.php with it
 * @copyright 2026
 *
*/

namespace Caligrafy\Testing;

use Caligrafy\Cache;
use Caligrafy\Capabilities;
use Caligrafy\Container;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Query;
use Caligrafy\Queue;
use Caligrafy\ServiceError;
use Caligrafy\View;

/**
 * Test what the app does through its capabilities, as the pages, REST, the JS client and agents reach them:
 *
 *     final class BooksTest extends Caligrafy\Testing\TestCase
 *     {
 *         public function testAReaderAddsABook(): void
 *         {
 *             $book = $this->call('create_book', ['title' => 'Dune'], $this->user(1));
 *             $this->assertSame('Dune', $book['title']);
 *             $this->assertCapabilityFails('invalid', 'create_book', [], $this->user(1));
 *             $this->assertCapabilityFails('unauthenticated', 'create_book', ['title' => 'Dune'], 'guest');
 *             $this->assertStringContainsString('Dune', $this->render('Books/list', ['books' => [$book]]));
 *         }
 *     }
 *
 * Each test runs in a database transaction that is rolled back at its end (with DB_ACTIVATE=true), so tests leave the
 * database as they found it; a CREATE, ALTER or DROP ends the transaction (MySQL commits it). Use a database of its own
 * for tests: DB_TEST_DATABASE in .env, or the settings of .env.testing. Bindings of the container, the cache's copies
 * and the query shapes the N+1 warning counts (Query::forgetShapes) are forgotten between tests.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /** @var bool false runs the test without a transaction */
    protected $transactions = true;

    private $transaction = false;

    protected function setUp(): void
    {
        parent::setUp();
        Container::reset();
        Container::boot();             // the app's bindings first, so that a fake replaces them
        Cache::clearMemory();
        Query::forgetShapes();         // the N+1 warning counts the queries of this test only
        $this->transaction = false;
        if ($this->transactions && strtolower((string) getenv('DB_ACTIVATE')) === 'true') {
            $pdo = Database::connection();
            if ($pdo && !$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $this->transaction = true;
            }
        }
    }

    protected function tearDown(): void
    {
        if ($this->transaction) {
            $pdo = Database::connection();
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
        Container::reset();
        Cache::clearMemory();
        parent::tearDown();
    }

    /**
     * Calls a capability as a channel would, with its permission checks, validation and audit
     * @param Context|object|array|string|null $as null: the command line (full access); 'guest'; a user (see user());
     *                                              or a Context
     * @return mixed its answer
     */
    protected function call($capability, array $input = array(), $as = null)
    {
        return Capabilities::call($capability, $input, $this->context($as));
    }

    /**
     * Asserts that a capability refuses: invalid, unauthenticated, forbidden, not_found or conflict
     * @return ServiceError the error, to check its message or fields
     */
    protected function assertCapabilityFails($kind, $capability, array $input = array(), $as = null)
    {
        try {
            $this->call($capability, $input, $as);
        } catch (ServiceError $e) {
            $this->assertSame($kind, $e->kind, "$capability failed with '{$e->kind}' ({$e->getMessage()}), not '$kind'");
            return $e;
        }
        $this->fail("$capability did not fail (expected '$kind')");
    }

    /**
     * A signed-in user for call(): an id and a permission level (the users column Context::$levelAttribute), plus any
     * other attributes the capability reads
     */
    protected function user($id = 1, $level = 0, array $attributes = array())
    {
        return (object) array_merge(array('id' => $id, Context::$levelAttribute => $level), $attributes);
    }

    /**
     * The HTML of a Pug view of application/views, as a page would show it
     */
    protected function render($view, array $data = array())
    {
        ob_start();
        try {
            View::display(VIEW_PATH . $view . '.pug', $data);
        } finally {
            $html = ob_get_clean();
        }
        return $html;
    }

    /**
     * Replaces what the container gives for $id (a mailer, a payment gateway) with $object, for this test
     */
    protected function fake($id, $object)
    {
        Container::boot();
        return Container::instance($id, $object);
    }

    /**
     * Asserts that a job of this capability was pushed to the queue (in this test), and returns its input
     */
    protected function assertQueued($capability, ?callable $where = null)
    {
        $rows = Database::toArray(Database::execute('SELECT `input` FROM `' . Queue::TABLE . '` WHERE `capability` = ? AND `status` = ? ORDER BY `id`', array($capability, 'pending')));
        foreach ($rows as $row) {
            $input = json_decode((string) $row['input'], true) ?: array();
            if ($where === null || $where($input)) {
                $this->addToAssertionCount(1);
                return $input;
            }
        }
        $this->fail("No job of $capability is in the queue" . ($rows && $where ? ' with that input' : ''));
    }

    private function context($as)
    {
        if ($as instanceof Context) {
            return $as;
        }
        if ($as === null) {
            return Context::cli();
        }
        if ($as === 'guest') {
            return Context::guest('rest');
        }
        if (is_array($as)) {
            $as = (object) $as;
        }
        if (is_object($as)) {
            return new Context('rest', $as);
        }
        throw new \InvalidArgumentException("call(): as is null, 'guest', a user (\$this->user(1)) or a Context");
    }
}
