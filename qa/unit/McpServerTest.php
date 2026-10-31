<?php

use Caligrafy\Context;
use Caligrafy\Mcp\Http;
use Caligrafy\Mcp\Server;
use PHPUnit\Framework\TestCase;

final class McpServerTest extends TestCase
{
    private function server(array $scopes = ['notes:write']): Server
    {
        return new Server(new Context('mcp', null, ['id' => 1, 'scopes' => $scopes, 'user_id' => null]), 'qa');
    }

    private function call(Server $server, string $method, array $params = [], $id = 1): ?array
    {
        return $server->handle(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]);
    }

    public function testInitializeNegotiatesTheProtocolVersion(): void
    {
        $result = $this->call($this->server(), 'initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => [], 'clientInfo' => ['name' => 't', 'version' => '1']])['result'];
        $this->assertSame('2025-06-18', $result['protocolVersion']);
        $this->assertSame(['tools' => ['listChanged' => false]], $result['capabilities']);
        $this->assertSame('qa', $result['serverInfo']['name']);
        $this->assertSame(Server::PROTOCOL_VERSIONS[0], $this->call($this->server(), 'initialize', ['protocolVersion' => '1999-01-01'])['result']['protocolVersion']);
    }

    public function testToolsListShowsOnlyWhatTheTokenMayCall(): void
    {
        $tools = array_column($this->call($this->server(['notes:read']), 'tools/list')['result']['tools'], null, 'name');
        $this->assertArrayHasKey('qa_list_notes', $tools);
        $this->assertArrayNotHasKey('qa_echo_note', $tools);
        $all = array_column($this->call($this->server(), 'tools/list')['result']['tools'], null, 'name');
        $this->assertSame(['title'], $all['qa_echo_note']['inputSchema']['required']);
        $this->assertTrue($all['qa_list_notes']['annotations']['readOnlyHint']);
        $this->assertTrue($all['qa_purge_notes']['annotations']['destructiveHint']);
        $this->assertFalse($all['qa_echo_note']['annotations']['destructiveHint']);
    }

    public function testToolsCall(): void
    {
        $ok = $this->call($this->server(), 'tools/call', ['name' => 'qa_echo_note', 'arguments' => ['title' => 'Hi']])['result'];
        $this->assertFalse($ok['isError']);
        $this->assertSame('Hi', $ok['structuredContent']->title);
        $this->assertSame('mcp', json_decode($ok['content'][0]['text'], true)['channel']);

        $list = $this->call($this->server(), 'tools/call', ['name' => 'qa_list_notes', 'arguments' => []])['result'];
        $this->assertSame([['title' => 'one'], ['title' => 'two']], $list['structuredContent']->result);

        $invalid = $this->call($this->server(), 'tools/call', ['name' => 'qa_echo_note', 'arguments' => []])['result'];
        $this->assertTrue($invalid['isError']);
        $this->assertSame('invalid', json_decode($invalid['content'][0]['text'], true)['error']['code']);
    }

    public function testHiddenAndUnknownToolsGetTheSameError(): void
    {
        $hidden = $this->call($this->server(['notes:read']), 'tools/call', ['name' => 'qa_echo_note', 'arguments' => ['title' => 'x']]);
        $unknown = $this->call($this->server(['notes:read']), 'tools/call', ['name' => 'nope', 'arguments' => []]);
        $this->assertSame(Server::INVALID_PARAMS, $hidden['error']['code']);
        $this->assertSame(Server::INVALID_PARAMS, $unknown['error']['code']);
    }

    public function testProtocolErrors(): void
    {
        $server = $this->server();
        $this->assertSame(Server::PARSE_ERROR, $server->handleJson('{nope')['error']['code']);
        $this->assertSame(Server::INVALID_REQUEST, $server->handle([['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']])['error']['code']);
        $this->assertSame(Server::METHOD_NOT_FOUND, $this->call($server, 'resources/list')['error']['code']);
        $this->assertNull($server->handle(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));
        $this->assertEquals(new stdClass(), $this->call($server, 'ping')['result']);
        $this->assertSame(7, $this->call($server, 'ping', [], 7)['id']);
    }

    public function testOriginCheck(): void
    {
        $this->assertTrue(Http::originAllowed(null, 'localhost:8080'));
        $this->assertTrue(Http::originAllowed('http://localhost:8080', 'localhost:8080'));
        $this->assertTrue(Http::originAllowed('https://app.example.com', 'app.example.com'));
        $this->assertFalse(Http::originAllowed('https://evil.example', 'localhost:8080'));
        putenv('MCP_ALLOWED_ORIGINS=https://inspector.example, https://other.example/');
        try {
            $this->assertTrue(Http::originAllowed('https://other.example', 'localhost:8080'));
        } finally {
            putenv('MCP_ALLOWED_ORIGINS');
        }
    }
}
