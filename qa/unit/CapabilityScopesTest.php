<?php

use Caligrafy\Capabilities;
use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;
use PHPUnit\Framework\TestCase;

/**
 * Token scope by default (2.26.0): a write capability declared without a scope needs, from a token, the scope named
 * after it (or *). Read-only and public ones, signed-in users and the CLI are unchanged. TOKEN_DEFAULT_SCOPES=false
 * (deprecated) restores the old behavior.
 */
final class CapabilityScopesTest extends TestCase
{
    protected function tearDown(): void
    {
        Capabilities::$defaultScopes = null;
    }

    private function token(array $scopes, $userId = null, $user = null): Context
    {
        return new Context('rest', $user, ['id' => 7, 'scopes' => $scopes, 'user_id' => $userId]);
    }

    private function refusal(callable $call): ServiceError
    {
        try {
            $call();
        } catch (ServiceError $e) {
            return $e;
        }
        $this->fail('a ServiceError was expected');
    }

    public function testTheScopeOfACapability(): void
    {
        $this->assertSame('qa_scope_write', Capabilities::scopeOf(Capabilities::get('qa_scope_write')['capability']), 'a write without a scope: its name');
        $this->assertSame('', Capabilities::scopeOf(Capabilities::get('qa_scope_read')['capability']), 'read-only: none');
        $this->assertSame('', Capabilities::scopeOf(Capabilities::get('qa_scope_public_write')['capability']), 'public: none');
        $this->assertSame('notes:write', Capabilities::scopeOf(Capabilities::get('qa_echo_note')['capability']), 'a declared scope stays');
        $this->assertSame('', Capabilities::get('qa_scope_write')['capability']->scope, 'the attribute itself is unchanged');
    }

    public function testATokenNeedsTheScopeNamedAfterAWriteCapability(): void
    {
        $forbidden = $this->refusal(fn () => Capabilities::call('qa_scope_write', ['text' => 'x'], $this->token(['notes:write'])));
        $this->assertSame('forbidden', $forbidden->kind);
        $this->assertSame(403, $forbidden->status());
        $this->assertSame('This needs the scope qa_scope_write (a write capability that declares no scope needs a token with the scope named after it, or *)', $forbidden->getMessage());
        $this->assertSame('forbidden', $this->refusal(fn () => Capabilities::call('qa_scope_write', ['text' => 'x'], $this->token([])))->kind);
        $this->assertSame('forbidden', $this->refusal(fn () => Capabilities::call('qa_scope_write', ['text' => 'x'], $this->token(['qa:*'])))->kind, 'a resource wildcard is not the name');

        $this->assertSame(['saved' => 'x'], Capabilities::call('qa_scope_write', ['text' => 'x'], $this->token(['qa_scope_write'])));
        $this->assertSame(['saved' => 'x'], Capabilities::call('qa_scope_write', ['text' => 'x'], $this->token(['*'])), '* grants everything');
    }

    public function testReadOnlyPublicUsersAndTheCliAreUnchanged(): void
    {
        $this->assertSame(['read' => true], Capabilities::call('qa_scope_read', [], $this->token([])), 'read-only without a scope: any token');
        $this->assertSame(['saved' => 'p'], Capabilities::call('qa_scope_public_write', ['text' => 'p'], $this->token([])), 'public');
        $this->assertSame(['saved' => 'p'], Capabilities::call('qa_scope_public_write', ['text' => 'p'], Context::guest()));
        $user = new Context('web', (object) ['id' => 3, 'permissions' => 0]);
        $this->assertSame(['saved' => 'u'], Capabilities::call('qa_scope_write', ['text' => 'u'], $user), 'a signed-in user needs no scope');
        $this->assertSame(['saved' => 'u'], Capabilities::call('qa_scope_write', ['text' => 'u'], new Context('rest', (object) ['id' => 3])), 'over REST too (the session)');
        $this->assertSame(['saved' => 'c'], Capabilities::call('qa_scope_write', ['text' => 'c'], Context::cli()));
        $this->assertSame(['saved' => 'c'], Capabilities::call('qa_scope_write', ['text' => 'c'], Context::local('mcp')), 'the local MCP server');
        $this->assertSame(['saved' => 's'], Capabilities::call('qa_scope_write', ['text' => 's'], Context::system('schedule', 'nightly')), 'scheduled jobs and the queue');
        $this->assertSame('unauthenticated', $this->refusal(fn () => Capabilities::call('qa_scope_write', ['text' => 'x'], Context::guest()))->kind);
    }

    public function testASignedInUserWithoutTheLevelIsNotToldAboutScopes(): void
    {
        $user = new Context('web', (object) ['id' => 3, 'permissions' => 0]);
        $this->assertSame('You are not allowed to do this', $this->refusal(fn () => Capabilities::call('qa_scope_admin_write', [], $user))->getMessage());
        $token = $this->token(['notes:write'], 3, (object) ['id' => 3, 'permissions' => 5]);
        $this->assertSame('This needs the scope qa_scope_admin_write and permission level 5 (a write capability that declares no scope needs a token with the scope named after it, or *)',
            $this->refusal(fn () => Capabilities::call('qa_scope_admin_write', [], $token))->getMessage());
    }

    public function testAvailableAndDescribeShowTheScope(): void
    {
        $names = array_keys(Capabilities::available($this->token(['notes:write'])));
        $this->assertContains('qa_echo_note', $names);
        $this->assertContains('qa_scope_read', $names);
        $this->assertNotContains('qa_scope_write', $names, 'MCP lists only what the token may call');
        $this->assertContains('qa_scope_write', array_keys(Capabilities::available($this->token(['qa_scope_write']))));
        $described = array_column(Capabilities::describe(), null, 'name');
        $this->assertSame('qa_scope_write', $described['qa_scope_write']['scope']);
        $this->assertSame('', $described['qa_scope_read']['scope']);
    }

    public function testUnscopedWritesForTheDoctor(): void
    {
        $unscoped = Capabilities::unscopedWrites();
        $this->assertSame('qa_scope_write', $unscoped['qa_scope_write']);
        $this->assertSame('qa_scope_admin_write', $unscoped['qa_scope_admin_write']);
        foreach (array('qa_scope_read', 'qa_scope_public_write', 'qa_echo_note', 'qa_web_action') as $name) {
            $this->assertArrayNotHasKey($name, $unscoped);
        }
    }

    public function testTokenDefaultScopesFalseRestoresTheOldBehavior(): void
    {
        $setting = getenv('TOKEN_DEFAULT_SCOPES');
        try {
            putenv('TOKEN_DEFAULT_SCOPES=false');
            Capabilities::$defaultScopes = null;
            $this->assertSame('', Capabilities::scopeOf(Capabilities::get('qa_scope_write')['capability']));
            $this->assertSame(['saved' => 'x'], Capabilities::call('qa_scope_write', ['text' => 'x'], $this->token(['notes:write'])));
            putenv('TOKEN_DEFAULT_SCOPES=');
            Capabilities::$defaultScopes = null;
            $this->assertSame('qa_scope_write', Capabilities::scopeOf(Capabilities::get('qa_scope_write')['capability']), 'empty is the default: on');
        } finally {
            $setting === false ? putenv('TOKEN_DEFAULT_SCOPES') : putenv('TOKEN_DEFAULT_SCOPES=' . $setting);
        }
    }

    public function testTheFrameworksOwnCapabilitiesKeepServingSignedInPages(): void
    {
        $user = new Context('web', (object) ['id' => 3, 'permissions' => 0]);
        $available = Capabilities::available($user);
        foreach (array('notify_subscribe', 'notify_unsubscribe', 'notify_email_set', 'notify_preferences', 'notify_test', 'notify_status',
                       'snap_rate', 'snap_shortcut_save', 'snap_shortcut_delete', 'snap_noticing_set', 'snap_forget', 'snap_dismiss',
                       'snap_routine_save', 'snap_routine_delete', 'snap_home', 'snap_card') as $name) {
            $this->assertArrayHasKey($name, $available, "$name for a signed-in page");
        }
        // the queue's own (declared caligrafy:queue) run as the system context; the public Snap ones need no token
        $this->assertSame('caligrafy:queue', Capabilities::scopeOf(Capabilities::get('queue_work')['capability']));
        $this->assertArrayHasKey('snap_task_run', Capabilities::available(Context::system('queue', 'snap_task_run #1')));
        $this->assertArrayHasKey('snap_card', Capabilities::available($this->token([])));
    }
}

#[Capability(name: 'qa_scope_write', description: 'Saves a text (a write capability without a scope)')]
class QaScopeWrite extends Service
{
    public $rules = array('text' => 'required|max_len,20');

    public function handle(array $input, Context $context)
    {
        return array('saved' => $input['text']);
    }
}

#[Capability(name: 'qa_scope_admin_write', description: 'An admin write without a scope', level: 5)]
class QaScopeAdminWrite extends Service
{
    public function handle(array $input, Context $context)
    {
        return array('done' => true);
    }
}

#[Capability(name: 'qa_scope_read', description: 'Reads (read-only without a scope)', readOnly: true)]
class QaScopeRead extends Service
{
    public function handle(array $input, Context $context)
    {
        return array('read' => true);
    }
}

#[Capability(name: 'qa_scope_public_write', description: 'A public write without a scope', public: true)]
class QaScopePublicWrite extends Service
{
    public $rules = array('text' => 'required|max_len,20');

    public function handle(array $input, Context $context)
    {
        return array('saved' => $input['text']);
    }
}
