<?php

use Caligrafy\Cache;
use PHPUnit\Framework\TestCase;

/**
 * Cache with the file store and off (the database store is checked by the QA's local run)
 */
final class CacheTest extends TestCase
{
    private $before;

    protected function setUp(): void
    {
        $this->before = getenv('CACHE_STORE');
        putenv('CACHE_STORE=file');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        $this->before === false ? putenv('CACHE_STORE') : putenv('CACHE_STORE=' . $this->before);
    }

    public function testTheDefaultStoreFollowsTheDatabase(): void
    {
        $db = getenv('DB_ACTIVATE');
        putenv('CACHE_STORE');
        putenv('DB_ACTIVATE=true');
        $this->assertSame('database', Cache::store());
        putenv('DB_ACTIVATE=false');
        $this->assertSame('file', Cache::store());
        $db === false ? putenv('DB_ACTIVATE') : putenv("DB_ACTIVATE=$db");
        putenv('CACHE_STORE=redis');
        try {
            Cache::store();
            $this->fail('an unknown store is refused');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('use file, database or off', $e->getMessage());
        } finally {
            putenv('CACHE_STORE=file');
        }
    }

    public function testValuesAreKeptAsJsonAcrossRequests(): void
    {
        Cache::put('a', array('x' => 1, 'y' => array(1.0, true, null)), 60);
        Cache::clearMemory();               // as another request would
        $this->assertSame(array('x' => 1, 'y' => array(1.0, true, null)), Cache::get('a'));
        $this->assertTrue(Cache::has('a'));
        Cache::forget('a');
        Cache::clearMemory();
        $this->assertSame('none', Cache::get('a', 'none'));
        $this->assertSame(array('k' => 'v'), Cache::remember('o', 60, fn () => (object) array('k' => 'v')), 'an object comes back as an array');
    }

    public function testRememberRunsOnceAndExpiredValuesAreGone(): void
    {
        $runs = 0;
        $make = function () use (&$runs) { $runs++; return 'v'; };
        Cache::remember('r', 60, $make);
        Cache::clearMemory();
        $this->assertSame('v', Cache::remember('r', 60, $make));
        $this->assertSame(1, $runs);
        Cache::put('gone', 'v', 0);
        $this->assertFalse(Cache::has('gone'));
        Cache::put('soon', 'v', 1);
        sleep(2);
        Cache::clearMemory();
        $this->assertFalse(Cache::has('soon'));
    }

    public function testOffKeepsNothing(): void
    {
        putenv('CACHE_STORE=off');
        $runs = 0;
        Cache::remember('x', 60, function () use (&$runs) { $runs++; return 1; });
        Cache::remember('x', 60, function () use (&$runs) { $runs++; return 1; });
        $this->assertSame(2, $runs);
        $this->assertFalse(Cache::has('x'));
    }
}
