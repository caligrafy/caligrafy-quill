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
}
