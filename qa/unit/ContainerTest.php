<?php

use Caligrafy\Container;
use PHPUnit\Framework\TestCase;

interface CtClock { public function now(): string; }
final class CtFixedClock implements CtClock { public function now(): string { return 'noon'; } }
final class CtMailer { public $sent = array(); }
final class CtGreeter
{
    public function __construct(public CtMailer $mailer, public CtClock $clock, public string $greeting = 'Hello') {}
}
final class CtNeedsInterface { public function __construct(public CtClock $clock) {} }
final class CtLoopA { public function __construct(public CtLoopB $b) {} }
final class CtLoopB { public function __construct(public CtLoopA $a) {} }
final class CtNeedsScalar { public function __construct(public string $name) {} }

/**
 * Container: constructor injection, bindings, singletons, instances (test fakes), and clear errors
 */
final class ContainerTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::reset();
    }

    public function testAClassIsBuiltWithItsDependencies(): void
    {
        Container::bind(CtClock::class, CtFixedClock::class);
        $g = Container::make(CtGreeter::class);
        $this->assertInstanceOf(CtMailer::class, $g->mailer);
        $this->assertSame('noon', $g->clock->now());
        $this->assertSame('Hello', $g->greeting);
        $this->assertSame('Hi', Container::make(CtGreeter::class, array('greeting' => 'Hi'))->greeting);
    }

    public function testSingletonsAndInstances(): void
    {
        Container::singleton(CtMailer::class);
        Container::bind(CtClock::class, fn () => new CtFixedClock());
        $this->assertSame(Container::get(CtMailer::class), Container::get(CtMailer::class));
        $this->assertNotSame(Container::get(CtClock::class), Container::get(CtClock::class));
        $fake = new CtMailer();
        Container::instance(CtMailer::class, $fake);
        $this->assertSame($fake, Container::make(CtGreeter::class)->mailer, 'a test swaps a dependency');
        $this->assertTrue(Container::has(CtMailer::class));
        Container::reset();
        $this->assertFalse(Container::has(CtMailer::class));
    }

    public function testAnUnboundInterfaceSaysToBindIt(): void
    {
        $this->expectExceptionMessage('Bind it to a class in application/config/container.php');
        Container::make(CtNeedsInterface::class);
    }

    public function testACycleAndAMissingScalarAreExplained(): void
    {
        try {
            Container::make(CtLoopA::class);
            $this->fail('a cycle is refused');
        } catch (LogicException $e) {
            $this->assertStringContainsString('CtLoopA -> CtLoopB -> CtLoopA', $e->getMessage());
        }
        $this->expectExceptionMessage('nothing gives its parameter $name');
        Container::make(CtNeedsScalar::class);
    }

    public function testCapabilitiesAreMadeByTheContainer(): void
    {
        $this->assertStringContainsString('Container::make($entry[\'class\'])', file_get_contents(dirname(__DIR__, 2) . '/framework/core/Capabilities.php'));
    }
}
