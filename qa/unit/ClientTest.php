<?php

use Caligrafy\Client;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testOneFunctionPerCapabilityWithItsInputs(): void
    {
        $code = Client::generate('/api/capabilities/');
        $this->assertStringContainsString("prefix: '/api/capabilities'", $code);
        $this->assertStringContainsString("export const qaEchoNote = (input = {}) => call('qa_echo_note', input)", $code);
        $this->assertStringContainsString('@param {{title: string, stars?: number, tag?: string, note?: string}} [input]', $code);
        $this->assertStringContainsString('Capability qa_purge_notes (destructive, notes:write)', $code);
        $this->assertStringContainsString('export class CapabilityError', $code);
        $this->assertStringNotContainsString('QaHelperService', $code);
    }

    public function testCamelCase(): void
    {
        $this->assertSame('createBook', Client::camel('create_book'));
        $this->assertSame('listBooks2', Client::camel('list_books2'));
    }
}
