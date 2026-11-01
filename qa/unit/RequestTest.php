<?php

use Caligrafy\Request;
use PHPUnit\Framework\TestCase;

/**
 * The constructor of Request reads the live HTTP request: the tests build it without the constructor
 * and set the headers they need
 */
final class RequestTest extends TestCase
{
    private function request(array $properties): Request
    {
        $request = (new ReflectionClass(Request::class))->newInstanceWithoutConstructor();
        foreach ($properties as $name => $value) {
            $request->$name = $value;
        }
        return $request;
    }

    public function testWantsJson(): void
    {
        $this->assertTrue($this->request(['accept' => 'application/json'])->wantsJson());
        $this->assertTrue($this->request(['accept' => 'application/json, text/plain'])->wantsJson());
        $this->assertFalse($this->request(['accept' => 'text/html,application/json'])->wantsJson());
        $this->assertFalse($this->request(['accept' => '*/*'])->wantsJson());
        $this->assertFalse($this->request(['accept' => ''])->wantsJson());
    }

    public function testAuthorizeApiRequestWithValidKey(): void
    {
        $apiKey = encryptDecrypt('encrypt', APP_KEY);
        $this->assertTrue($this->request(['authorization' => "Bearer $apiKey"])->authorizeApiRequest());
    }

    public function testAuthorizeApiRequestRejectsInvalidOrMissingKeys(): void
    {
        $otherKey = encryptDecrypt('encrypt', 'another-key');
        $this->assertFalse($this->request(['authorization' => "Bearer $otherKey"])->authorizeApiRequest());
        $this->assertFalse($this->request(['authorization' => 'Bearer not-a-key'])->authorizeApiRequest());
        $this->assertFalse($this->request(['authorization' => encryptDecrypt('encrypt', APP_KEY)])->authorizeApiRequest());
        $this->assertFalse($this->request(['authorization' => ''])->authorizeApiRequest());
    }

    public function testLegacyKeyCanBeTurnedOff(): void
    {
        $apiKey = encryptDecrypt('encrypt', APP_KEY);
        putenv('LEGACY_API_KEY=false');
        try {
            $this->assertFalse($this->request(['authorization' => "Bearer $apiKey"])->authorizeApiRequest());
        } finally {
            putenv('LEGACY_API_KEY');
        }
        $this->assertTrue($this->request(['authorization' => "Bearer $apiKey"])->authorizeApiRequest());
    }

    public function testBearer(): void
    {
        $this->assertSame('abc', $this->request(['authorization' => 'Bearer abc'])->bearer());
        $this->assertSame('abc', $this->request(['authorization' => 'bearer   abc'])->bearer());
        $this->assertNull($this->request(['authorization' => 'Basic abc'])->bearer());
        $this->assertNull($this->request(['authorization' => ''])->bearer());
    }

    public function testMalformedTokenIsRefusedWithoutTheDatabase(): void
    {
        // cq_ prefix but not the token format: refused, and never sent to the legacy key check
        $this->assertFalse($this->request(['authorization' => 'Bearer cq_1_short'])->authorizeApiRequest());
    }

    private function withHeaders(array $headers): Request
    {
        $request = $this->request([]);
        $property = new ReflectionProperty(Request::class, '_request');
        $property->setAccessible(true);
        $property->setValue($request, $headers);
        return $request;
    }

    public function testSessionRequestNeedsTheCsrfTokenOfTheSession(): void
    {
        $saved = $_SESSION['token'] ?? null;
        $_SESSION['token'] = str_repeat('a', 64);
        try {
            $this->assertTrue($this->withHeaders(['X-CSRF-Token' => str_repeat('a', 64)])->authorizeSessionRequest());
            $this->assertTrue($this->withHeaders(['x-csrf-token' => str_repeat('a', 64)])->authorizeSessionRequest());
            $this->assertFalse($this->withHeaders(['X-CSRF-Token' => str_repeat('b', 64)])->authorizeSessionRequest());
            $this->assertFalse($this->withHeaders([])->authorizeSessionRequest());
            unset($_SESSION['token']);
            $this->assertFalse($this->withHeaders(['X-CSRF-Token' => ''])->authorizeSessionRequest());
        } finally {
            if ($saved === null) unset($_SESSION['token']); else $_SESSION['token'] = $saved;
        }
    }

    public function testApiRefusalSaysWhy(): void
    {
        $this->assertStringContainsString('X-CSRF-Token', $this->request(['authorization' => ''])->apiRefusal());
        $this->assertStringContainsString('personal access token is unknown', $this->request(['authorization' => 'Bearer cq_1_' . str_repeat('0', 40)])->apiRefusal());
        putenv('LEGACY_API_KEY=false');
        try {
            $this->assertStringContainsString('LEGACY_API_KEY=false', $this->request(['authorization' => 'Bearer something'])->apiRefusal());
        } finally {
            putenv('LEGACY_API_KEY');
        }
    }
}
