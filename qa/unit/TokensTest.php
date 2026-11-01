<?php

use Caligrafy\Tokens;
use PHPUnit\Framework\TestCase;

final class TokensTest extends TestCase
{
    public function testParseAcceptsOnlyTheTokenFormat(): void
    {
        $this->assertSame(12, Tokens::parse('cq_12_' . str_repeat('a1', 20)));
        $this->assertNull(Tokens::parse('cq_0_' . str_repeat('a1', 20)));
        $this->assertNull(Tokens::parse('cq_12_' . str_repeat('a1', 19)));
        $this->assertNull(Tokens::parse('cq_12_' . str_repeat('A1', 20)));
        $this->assertNull(Tokens::parse('xx_12_' . str_repeat('a1', 20)));
        $this->assertNull(Tokens::parse(null));
    }

    public function testScopesAreNormalized(): void
    {
        $this->assertSame(['books:read', 'books:write'], Tokens::normalizeScopes('books:write, books:read books:read'));
        $this->assertSame(['*'], Tokens::normalizeScopes(['*']));
        $this->assertSame(['authors:*'], Tokens::normalizeScopes('authors:* Books:read bad nope:'));
    }

    public function testAllows(): void
    {
        $this->assertTrue(Tokens::allows(['books:read'], 'books:read'));
        $this->assertFalse(Tokens::allows(['books:read'], 'books:write'));
        $this->assertTrue(Tokens::allows(['books:*'], 'books:write'));
        $this->assertFalse(Tokens::allows(['books:*'], 'bookshelf:write'));
        $this->assertTrue(Tokens::allows(['*'], 'anything:at_all'));
        $this->assertTrue(Tokens::allows([], ''));
        $this->assertFalse(Tokens::allows([], 'books:read'));
    }

    public function testExpiryIsReadOnTheServerClock(): void
    {
        $zone = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            $now = gmmktime(15, 0, 0, 6, 1, 2026);
            $this->assertFalse(Tokens::expired(null, $now));
            $this->assertFalse(Tokens::expired(date('Y-m-d H:i:s', $now + 60), $now));
            $this->assertTrue(Tokens::expired(date('Y-m-d H:i:s', $now - 60), $now));
            $this->assertTrue(Tokens::expired(date('Y-m-d H:i:s', $now), $now));
            $this->assertTrue(Tokens::expired('not a date', $now));
        } finally {
            date_default_timezone_set($zone);
        }
    }

    public function testAnExpiryInTheRepeatedHourIsStoredSoTheTokenNeverStopsEarly(): void
    {
        $zone = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            // 2026-11-01 05:21 UTC is 01:21 EDT; an hour later is 01:21 EST: the same local time
            $now = gmmktime(5, 21, 0, 11, 1, 2026);
            $this->assertSame('2026-11-01 01:21:00', date('Y-m-d H:i:s', $now + 3600));
            $later = Tokens::expiresAt($now + 3600);
            $this->assertSame('2026-11-01 02:00:00', $later);
            $this->assertFalse(Tokens::expired($later, $now));
            $this->assertFalse(Tokens::expired($later, $now + 3600));
            $this->assertTrue(Tokens::expired($later, gmmktime(7, 0, 0, 11, 1, 2026)));
            // the first occurrence, and expiries a minute ago, read back exactly
            $this->assertSame('2026-11-01 01:50:00', Tokens::expiresAt(gmmktime(5, 50, 0, 11, 1, 2026)));
            $this->assertTrue(Tokens::expired(Tokens::expiresAt($now - 60), $now));
            $this->assertFalse(Tokens::expired(Tokens::expiresAt($now + 60), $now));
            $this->assertSame('2026-11-01 02:00:00', Tokens::expiresAt(gmmktime(7, 0, 0, 11, 1, 2026)));
            // strings and null are kept as given
            $this->assertSame('2026-11-01 01:21:00', Tokens::expiresAt('2026-11-01 01:21:00'));
            $this->assertNull(Tokens::expiresAt(null));
        } finally {
            date_default_timezone_set($zone);
        }
    }
}
