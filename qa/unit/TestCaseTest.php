<?php

use Caligrafy\Query;

require_once dirname(__DIR__, 2) . '/framework/testing/TestCase.php';

/**
 * Caligrafy\Testing\TestCase (2.19.0): the N+1 warning counts the queries of one test only
 */
final class TestCaseTest extends Caligrafy\Testing\TestCase
{
    private $log = array();

    protected function tearDown(): void
    {
        Query::$logger = null;
        Query::watch(null);
        parent::tearDown();
    }

    public function testSetUpForgetsTheQueriesOfEarlierTests(): void
    {
        Query::watch(true);
        Query::$logger = function ($message) { $this->log[] = $message; };
        $sql = 'SELECT * FROM `authors` WHERE `id` = ?';
        for ($i = 1; $i < Query::$threshold; $i++) {
            Query::observe($sql);
        }
        $this->setUp();            // the next test
        Query::observe($sql);
        $this->assertSame(array(), $this->log, 'no Probable N+1 warning across tests');
        $this->assertTrue(Query::watching(), 'watch() is kept');
        for ($i = 1; $i < Query::$threshold; $i++) {
            Query::observe($sql);
        }
        $this->assertCount(1, $this->log, 'within one test it still warns');
    }
}
