<?php

use Caligrafy\Doctor;
use PHPUnit\Framework\TestCase;

final class DoctorTest extends TestCase
{
    public function testMaskShowsPrefixAndLastFourCharactersOnly(): void
    {
        $key = 'sk-proj-' . str_repeat('a', 40) . 'WXYZ';
        $this->assertSame('sk-proj-…WXYZ (52 chars)', Doctor::mask($key));
        $this->assertSame('…7890 (20 chars)', Doctor::mask('abcdefghij1234567890'));
        $this->assertSame('(5 chars)', Doctor::mask('short'));
    }

    public function testWellFormedKeyIsOk(): void
    {
        [$result] = $this->secret('sk-proj-' . str_repeat('a', 40));
        $this->assertSame('OK', $result['status']);
        $this->assertStringNotContainsString(str_repeat('a', 8), $result['message']);
    }

    public function testLeadingSpaceIsWarned(): void
    {
        [$result] = $this->secret(' sk-proj-' . str_repeat('a', 40));
        $this->assertSame('WARN', $result['status']);
        $this->assertStringContainsString('leading or trailing spaces', $result['message']);
    }

    public function testMissingDashAfterPrefixIsWarned(): void
    {
        [$result] = $this->secret('sk-proj' . str_repeat('a', 40));
        $this->assertSame('WARN', $result['status']);
        $this->assertStringContainsString("missing '-'", $result['message']);
        $this->assertStringNotContainsString(str_repeat('a', 8), $result['message']);
    }

    public function testDebugOnAnHttpsSiteIsWarned(): void
    {
        $this->assertSame('WARN', $this->projectResult('APP_DEBUG', ['APP_DEBUG' => 'true', 'APP_PROTOCOL' => 'https'])['status'] ?? null);
        $this->assertNull($this->projectResult('APP_DEBUG', ['APP_DEBUG' => 'true', 'APP_PROTOCOL' => 'http']));
        $this->assertNull($this->projectResult('APP_DEBUG', ['APP_DEBUG' => 'false', 'APP_PROTOCOL' => 'https']));
    }

    public function testCoinbaseCommerceKeyIsWarned(): void
    {
        $results = $this->withEnv(['CRYPTO_PAY_KEY' => 'commerce-key', 'COINBASE_API_KEY_NAME' => '', 'COINBASE_API_KEY_SECRET' => ''], function () {
            $doctor = new Doctor(sys_get_temp_dir());
            (new ReflectionMethod(Doctor::class, 'coinbase'))->invoke($doctor);
            return $doctor->results();
        });
        $this->assertSame('WARN', $results[0]['status']);
        $this->assertSame('CRYPTO_PAY_KEY', $results[0]['name']);
        $this->assertStringContainsString('COINBASE_API_KEY_NAME', $results[0]['fix']);
    }

    /**
     * 2.26.0: the write capabilities without a scope (the test fixtures' qa_scope_write, qa_scope_admin_write) and the
     * scope a token now needs for each; the framework's own (notify_*, snap_*) are left out
     */
    public function testUnscopedWriteCapabilitiesAreListed(): void
    {
        $root = sys_get_temp_dir() . '/caligrafy-doctor-scopes-' . getmypid();
        @mkdir($root . '/application/services', 0777, true);
        $run = function () use ($root) {
            $doctor = new Doctor($root);
            (new ReflectionMethod(Doctor::class, 'tokenScopes'))->invoke($doctor);
            return $doctor->results();
        };
        try {
            [$result] = $this->withEnv(['TOKEN_DEFAULT_SCOPES' => ''], $run);
            $this->assertSame('Token scopes', $result['name']);
            $this->assertSame('WARN', $result['status']);
            $this->assertStringContainsString('a token now needs the scope named after each, or *: ', $result['message']);
            $this->assertStringContainsString('qa_scope_write (scope qa_scope_write)', $result['message']);
            $this->assertStringContainsString('qa_scope_admin_write (scope qa_scope_admin_write)', $result['message']);
            $this->assertStringNotContainsString('notify_', $result['message']);
            $this->assertStringNotContainsString('snap_', $result['message']);
            $this->assertStringNotContainsString('qa_echo_note', $result['message'], 'it declares notes:write');
            $this->assertStringContainsString("declare scope: '<resource>:write' on each", $result['fix']);

            [$old] = $this->withEnv(['TOKEN_DEFAULT_SCOPES' => 'false'], $run);
            $this->assertStringStartsWith('TOKEN_DEFAULT_SCOPES=false (deprecated): any token may call ', $old['message']);
        } finally {
            @rmdir($root . '/application/services');
            @rmdir($root . '/application');
            @rmdir($root);
        }
        $none = new Doctor(sys_get_temp_dir() . '/no-such-project');
        (new ReflectionMethod(Doctor::class, 'tokenScopes'))->invoke($none);
        $this->assertSame([], $none->results(), 'no application/services: nothing to say');
    }

    private function projectResult(string $name, array $env): ?array
    {
        $results = $this->withEnv($env, function () {
            $doctor = new Doctor(sys_get_temp_dir());
            (new ReflectionMethod(Doctor::class, 'project'))->invoke($doctor);
            return $doctor->results();
        });
        foreach ($results as $result) {
            if ($result['name'] === $name) return $result;
        }
        return null;
    }

    private function withEnv(array $env, callable $run)
    {
        foreach ($env as $key => $value) putenv("$key=$value");
        try {
            return $run();
        } finally {
            foreach ($env as $key => $value) putenv($key);
        }
    }

    /**
     * secret() is private: it records its result, read back with results()
     */
    private function secret(string $value): array
    {
        $doctor = new Doctor(sys_get_temp_dir());
        (new ReflectionMethod(Doctor::class, 'secret'))->invoke($doctor, 'openai', 'OPENAI_API_KEY', $value);
        return $doctor->results();
    }
}
