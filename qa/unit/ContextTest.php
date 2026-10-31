<?php

use Caligrafy\Context;
use PHPUnit\Framework\TestCase;

final class ContextTest extends TestCase
{
    private function user(int $level): object
    {
        return (object) ['id' => 7, 'permissions' => $level];
    }

    public function testCliAndLegacyKeyHaveFullAccess(): void
    {
        $this->assertTrue(Context::cli()->allows('books:write', 9));
        $this->assertSame('cli', Context::cli()->actorType());
        $key = new Context('rest', null, null, true);
        $this->assertTrue($key->allows('books:write', 9));
        $this->assertSame('key', $key->actorType());
    }

    public function testGuestHasNoAccess(): void
    {
        $guest = Context::guest();
        $this->assertFalse($guest->authenticated());
        $this->assertFalse($guest->allows('', 0));
        $this->assertSame('guest', $guest->actorType());
    }

    public function testSignedInUserNeedsTheLevel(): void
    {
        $reader = new Context('web', $this->user(0));
        $this->assertTrue($reader->allows('books:write', 0));
        $this->assertFalse($reader->allows('books:delete', 1));
        $admin = new Context('web', $this->user(2));
        $this->assertTrue($admin->allows('books:delete', 1));
        $this->assertSame(['user', '7'], [$admin->actorType(), $admin->actorId()]);
    }

    public function testTokenNeedsTheScopeAndItsOwnerTheLevel(): void
    {
        $appToken = new Context('mcp', null, ['id' => 3, 'scopes' => ['books:read'], 'user_id' => null]);
        $this->assertTrue($appToken->allows('books:read', 1));
        $this->assertFalse($appToken->allows('books:write', 0));
        $this->assertSame(['token', '3'], [$appToken->actorType(), $appToken->actorId()]);

        $userToken = new Context('mcp', $this->user(0), ['id' => 4, 'scopes' => ['books:*'], 'user_id' => 7]);
        $this->assertTrue($userToken->allows('books:write', 0));
        $this->assertFalse($userToken->allows('books:delete', 1));

        // owner unknown (authentication off): only capabilities that need no level
        $orphan = new Context('mcp', null, ['id' => 5, 'scopes' => ['*'], 'user_id' => 7]);
        $this->assertTrue($orphan->allows('books:write', 0));
        $this->assertFalse($orphan->allows('books:delete', 1));
    }

    public function testUnknownChannelIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Context('fax');
    }
}
