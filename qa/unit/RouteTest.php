<?php

use Caligrafy\Route;
use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
    public function testGlobalClassWinsOverExampleNamespace(): void
    {
        $this->assertSame('QaUnitController', Route::resolveController('QaUnitController'));
    }

    public function testNamespacedControllerWithOrWithoutLeadingBackslash(): void
    {
        $this->assertSame('Shop\OrderController', Route::resolveController('Shop\OrderController'));
        $this->assertSame('Shop\OrderController', Route::resolveController('\Shop\OrderController'));
    }

    public function testShortNameFallsBackToExampleControllers(): void
    {
        $this->assertSame('Caligrafy\Controllers\QaFallbackController', Route::resolveController('QaFallbackController'));
    }

    public function testUnknownControllerIsNull(): void
    {
        $this->assertNull(Route::resolveController('NoSuchController'));
        $this->assertNull(Route::resolveController('Shop\NoSuchController'));
        $this->assertNull(Route::resolveController(''));
    }

    public function testRedirectRegistersAGetWithItsTarget(): void
    {
        Route::reset();
        try {
            Route::redirect('/', '/books');
            Route::redirect('/old/books/{id}', '/books/{id}', 301);
            $this->assertSame(array(array('method' => 'GET', 'route' => ''), array('method' => 'GET', 'route' => 'old/books/{id}')), Route::routes());
            $this->assertInstanceOf(Closure::class, Route::handler('GET', '/old/books/{id}'));
            $this->assertNull(Route::handler('POST', '/old/books/{id}'));
            $this->assertSame('/books/a%2Fb', Route::redirectTarget('/books/{id}', array('id' => 'a/b')), 'url-encoded');
            $this->assertSame('https://example.com/x', Route::redirectTarget('https://example.com/x'));
            foreach (array(array('/a', '/b', 200), array('/a', '/b', 404), array('/a', '', 302)) as $bad) {
                try {
                    Route::redirect(...$bad);
                    $this->fail('refused: ' . json_encode($bad));
                } catch (InvalidArgumentException $e) {
                    $this->assertStringContainsString('Route::redirect', $e->getMessage());
                }
            }
            $this->assertCount(2, Route::routes());
        } finally {
            Route::reset();
        }
    }
}
