<?php

use Caligrafy\Capability;
use Caligrafy\HttpCache;
use PHPUnit\Framework\TestCase;

/**
 * Public answers (2.26.0): which capabilities may declare cache, and which requests may receive a shared answer.
 * The headers themselves (Cache-Control, the guard before they are sent) are checked over HTTP by qa/tests.
 */
final class HttpCacheTest extends TestCase
{
    private $server;
    private $cookie;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->cookie = $_COOKIE;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        $_COOKIE = array();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_COOKIE = $this->cookie;
    }

    public function testCacheNeedsAPublicReadOnlyCapability(): void
    {
        $this->assertSame(30, (new Capability(name: 'list_books', readOnly: true, public: true, cache: 30))->cache);
        $this->assertSame(0, (new Capability(name: 'list_books'))->cache);
        foreach (array(
            array('readOnly' => false, 'public' => true),
            array('readOnly' => true, 'public' => false),
            array('readOnly' => true, 'public' => true, 'webOnly' => true),
        ) as $options) {
            try {
                new Capability(...array_merge(array('name' => 'list_books', 'cache' => 30), $options));
                $this->fail('cache accepted with ' . json_encode($options));
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('cache needs readOnly: true and public: true', $e->getMessage());
            }
        }
    }

    public function testCacheIsBounded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Capability(name: 'list_books', readOnly: true, public: true, cache: HttpCache::MAX + 1);
    }

    public function testOnlyAnonymousReadsAreShareable(): void
    {
        $this->assertTrue(HttpCache::shareable(), 'a GET with no cookie and no token');
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $this->assertTrue(HttpCache::shareable(), 'a HEAD');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertFalse(HttpCache::shareable(), 'a POST');
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_COOKIE[session_name()] = 'abc';
        $this->assertFalse(HttpCache::shareable(), 'a request with a session cookie');
        $_COOKIE = array('analytics' => '1');
        $this->assertTrue(HttpCache::shareable(), 'another cookie is no session');
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer cq_1_x';
        $this->assertFalse(HttpCache::shareable(), 'a token');
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer cq_1_x';
        $this->assertFalse(HttpCache::shareable(), 'a token behind a rewrite');
    }
}
