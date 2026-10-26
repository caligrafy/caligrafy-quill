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
}
