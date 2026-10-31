<?php

use Caligrafy\Capabilities;
use Caligrafy\Context;
use Caligrafy\ServiceError;
use PHPUnit\Framework\TestCase;

final class CapabilitiesTest extends TestCase
{
    private function writer(array $scopes = ['notes:write']): Context
    {
        return new Context('rest', null, ['id' => 1, 'scopes' => $scopes, 'user_id' => null]);
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

    public function testRegistryFindsOnlyServicesWithTheAttribute(): void
    {
        $names = array_keys(Capabilities::all());
        $this->assertContains('qa_echo_note', $names);
        $this->assertContains('qa_list_notes', $names);
        $this->assertSame('QaEchoNote', Capabilities::get('qa_echo_note')['class']);
        foreach (Capabilities::all() as $entry) {
            $this->assertNotSame('QaHelperService', $entry['class']);
        }
        $this->assertNull(Capabilities::get('nope'));
    }

    public function testSchemaFromRules(): void
    {
        $schema = Capabilities::schema('qa_echo_note');
        $this->assertSame(['title'], $schema['required']);
        $this->assertSame(['type' => 'string', 'maxLength' => 20, 'description' => 'The title'], $schema['properties']['title']);
        $this->assertSame(['type' => 'integer', 'minimum' => 1, 'maximum' => 5], $schema['properties']['stars']);
        $this->assertSame(['type' => 'string', 'enum' => ['red', 'blue']], $schema['properties']['tag']);
        $this->assertSame(['type' => 'string'], $schema['properties']['note']);
        $this->assertFalse($schema['additionalProperties']);
        $this->assertEquals(new stdClass(), Capabilities::schema('qa_list_notes')['properties']);
        $this->assertSame(['type' => 'string', 'format' => 'email'], Capabilities::schemaFromRules(['mail' => 'valid_email'])['properties']['mail']);
        $this->assertSame(['type' => 'boolean'], Capabilities::schemaFromRules(['done' => 'boolean'])['properties']['done']);
    }

    public function testCallValidatesFiltersAndKeepsOnlyDeclaredInputs(): void
    {
        $result = Capabilities::call('qa_echo_note', ['title' => '  Hi  ', 'stars' => '4', 'admin' => true], $this->writer());
        $this->assertSame('Hi', $result['title']);
        $this->assertSame(['token', 'rest'], [$result['actor'], $result['channel']]);

        $invalid = $this->refusal(fn () => Capabilities::call('qa_echo_note', ['stars' => 9], $this->writer()));
        $this->assertSame('invalid', $invalid->kind);
        $this->assertSame(422, $invalid->status());
        $this->assertArrayHasKey('title', $invalid->fields);
        $this->assertArrayHasKey('stars', $invalid->fields);
    }

    public function testCallChecksTheContextBeforeAnythingElse(): void
    {
        $this->assertSame('unauthenticated', $this->refusal(fn () => Capabilities::call('qa_echo_note', ['title' => 'x'], Context::guest()))->kind);
        $forbidden = $this->refusal(fn () => Capabilities::call('qa_echo_note', ['title' => 'x'], $this->writer(['notes:read'])));
        $this->assertSame('forbidden', $forbidden->kind);
        $this->assertStringContainsString('notes:write', $forbidden->getMessage());
        // invalid input is reported only to someone allowed to call
        $this->assertSame('forbidden', $this->refusal(fn () => Capabilities::call('qa_echo_note', [], $this->writer(['notes:read'])))->kind);
        $user = new Context('web', (object) ['id' => 9, 'permissions' => 1]);
        $this->assertSame('forbidden', $this->refusal(fn () => Capabilities::call('qa_purge_notes', [], $user))->kind);
        $admin = new Context('web', (object) ['id' => 9, 'permissions' => 2]);
        $this->assertSame(['deleted' => 3], Capabilities::call('qa_purge_notes', [], $admin));
        $this->assertSame('not_found', $this->refusal(fn () => Capabilities::call('nope', [], Context::cli()))->kind);
    }

    public function testPublicCapabilitiesNeedNoIdentityAndResultsArePlain(): void
    {
        $this->assertSame([['title' => 'one'], ['title' => 'two']], Capabilities::call('qa_list_notes', [], Context::guest()));
    }

    public function testServiceErrorsFromHandle(): void
    {
        $conflict = $this->refusal(fn () => Capabilities::call('qa_echo_note', ['title' => 'taken'], Context::cli()));
        $this->assertSame(['error' => ['code' => 'conflict', 'message' => 'That title is taken']], $conflict->toArray());
        $this->assertSame(409, $conflict->status());
    }

    public function testAvailableAndDescribeFollowTheContext(): void
    {
        $names = array_keys(Capabilities::available($this->writer(['notes:read'])));
        $this->assertContains('qa_list_notes', $names);
        $this->assertNotContains('qa_echo_note', $names);
        $described = array_column(Capabilities::describe($this->writer()), null, 'name');
        $this->assertTrue($described['qa_list_notes']['readOnly']);
        $this->assertSame('notes:write', $described['qa_echo_note']['scope']);
        // an application token (no owner) is limited by its scopes only: levels apply to users
        $this->assertArrayHasKey('qa_purge_notes', $described);
        $userToken = new Context('rest', (object) ['id' => 9, 'permissions' => 0], ['id' => 2, 'scopes' => ['notes:write'], 'user_id' => 9]);
        $this->assertArrayNotHasKey('qa_purge_notes', Capabilities::available($userToken));
    }
}
