<?php

use Caligrafy\Cache;
use Caligrafy\Mail;
use PHPUnit\Framework\TestCase;

/**
 * MAIL_RATE_PER_HOUR (2.30.0): the most emails an app sends in an hour, counted in the cache. Over it, sendMail()
 * sends nothing and returns false, and Mail::limited() says why. Nothing leaves the computer: the emails of these tests
 * are held back before any connection.
 */
final class MailRateTest extends TestCase
{
    private $store, $rate;

    protected function setUp(): void
    {
        $this->store = getenv('CACHE_STORE');
        $this->rate = getenv('MAIL_RATE_PER_HOUR');
        putenv('CACHE_STORE=file');
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        $this->store === false ? putenv('CACHE_STORE') : putenv('CACHE_STORE=' . $this->store);
        $this->rate === false ? putenv('MAIL_RATE_PER_HOUR') : putenv('MAIL_RATE_PER_HOUR=' . $this->rate);
    }

    private function allow($count = 1)
    {
        $m = new ReflectionMethod(Mail::class, 'allow');
        $m->setAccessible(true);
        return $m->invoke(null, $count);
    }

    public function testNoSettingMeansNoLimit(): void
    {
        foreach (array('', '0', 'abc') as $value) {
            putenv('MAIL_RATE_PER_HOUR=' . $value);
            $this->assertNull(Mail::rate());
            for ($i = 0; $i < 50; $i++) $this->assertTrue($this->allow());
            $this->assertSame(0, Mail::sentThisHour(), 'nothing is counted without a limit');
        }
    }

    public function testTheHourCountsUpToTheLimitThenHoldsBack(): void
    {
        putenv('MAIL_RATE_PER_HOUR=3');
        $this->assertSame(3, Mail::rate());
        $this->assertTrue($this->allow());
        $this->assertTrue($this->allow());
        $this->assertFalse($this->allow(2), 'two more would go over: none counted');
        $this->assertSame(2, Mail::sentThisHour());
        $this->assertTrue($this->allow());
        $this->assertFalse($this->allow());
        $this->assertSame(3, Mail::sentThisHour());
    }

    public function testSendMailOverTheLimitSendsNothingAndSaysWhy(): void
    {
        putenv('MAIL_RATE_PER_HOUR=1');
        $this->assertTrue($this->allow());
        $sent = (new Mail())->sendMail(array('email' => 'someone@example.test'), array('subject' => 'Hello', 'body' => '<p>Hi</p>'), array('email' => 'app@example.test', 'name' => 'App'));
        $this->assertFalse($sent);
        $this->assertTrue(Mail::limited());
        $this->assertSame(1, Mail::sentThisHour(), 'the email held back is not counted');
    }

    public function testWithTheCacheOffNothingIsHeldBack(): void
    {
        putenv('MAIL_RATE_PER_HOUR=1');
        putenv('CACHE_STORE=off');
        $this->assertTrue($this->allow());
        $this->assertTrue($this->allow(), 'nowhere to count: nothing is held back');
    }
}
