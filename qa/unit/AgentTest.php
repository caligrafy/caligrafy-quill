<?php

use Caligrafy\Agent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Agent::settings() resolves the provider from settings only: no request is sent
 */
final class AgentTest extends TestCase
{
    public function testGroqIsTheDefaultProviderWithItsCurrentModels(): void
    {
        $s = $this->settings([]);
        $this->assertSame('groq', $s['provider']);
        $this->assertSame('https://api.groq.com/openai/v1', $s['base']);
        $this->assertSame('openai/gpt-oss-120b', $s['model']);
        $this->assertSame('qwen/qwen3.8-27b', $s['vision']);
    }

    public function testGroqSettingsStillApplyAndAgentSettingsWin(): void
    {
        $s = $this->settings(['GROQ_API_KEY' => 'gsk_a', 'GROQ_MODEL' => 'm1', 'GROQ_API_BASE' => 'http://gateway/v1/']);
        $this->assertSame(['gsk_a', 'm1', 'http://gateway/v1', false], [$s['key'], $s['model'], $s['base'], $s['baseOverride']]);
        $s = $this->settings(['GROQ_API_KEY' => 'gsk_a', 'GROQ_MODEL' => 'm1', 'AGENT_MODEL' => 'm2', 'AGENT_API_BASE' => 'http://other/v1']);
        $this->assertSame(['m2', 'http://other/v1', true], [$s['model'], $s['base'], $s['baseOverride']]);
    }

    public function testProviderKeyThenAgentApiKey(): void
    {
        $this->assertSame('tg', $this->settings(['AGENT_PROVIDER' => 'together', 'TOGETHER_API_KEY' => 'tg', 'AGENT_API_KEY' => 'any'])['key']);
        $this->assertSame('any', $this->settings(['AGENT_PROVIDER' => 'together', 'AGENT_API_KEY' => 'any'])['key']);
        $this->assertSame('GROQ_API_KEY', $this->settings(['AGENT_PROVIDER' => 'groq', 'GROQ_MODEL' => 'x'])['keyName']);
        $this->assertSame('', $this->settings(['AGENT_PROVIDER' => 'openrouter', 'GROQ_API_KEY' => 'gsk_a'])['key'], 'another provider key is never used');
    }

    #[DataProvider('defaults')]
    public function testDefaultsPerProvider(string $provider, string $base, string $model, string $vision): void
    {
        $s = $this->settings(['AGENT_PROVIDER' => strtoupper($provider)]);
        $this->assertSame([$provider, $base, $model, $vision], [$s['provider'], $s['base'], $s['model'], $s['vision']]);
    }

    public static function defaults(): array
    {
        return [
            ['together', 'https://api.together.ai/v1', 'openai/gpt-oss-120b', 'Qwen/Qwen3.5-9B'],
            ['fireworks', 'https://api.fireworks.ai/inference/v1', 'accounts/fireworks/models/gpt-oss-120b', 'accounts/fireworks/models/glm-5p3-flash'],
            ['openrouter', 'https://openrouter.ai/api/v1', 'meta-llama/llama-3.3-70b-instruct', 'meta-llama/llama-4-maverick'],
            ['ollama', 'http://localhost:11434/v1', 'llama3.2', 'llama3.2-vision'],
            ['custom', '', '', ''],
        ];
    }

    public function testOllamaNeedsNoKey(): void
    {
        $this->assertSame('ollama', $this->settings(['AGENT_PROVIDER' => 'ollama'])['key']);
        $this->assertNull($this->settings(['AGENT_PROVIDER' => 'ollama'])['keyName']);
    }

    public function testUnknownProviderIsRejected(): void
    {
        $this->expectExceptionMessage("Unknown AGENT_PROVIDER 'meta'");
        $this->settings(['AGENT_PROVIDER' => 'meta']);
    }

    private function settings(array $env): array
    {
        return Agent::settings(function ($name) use ($env) { return $env[$name] ?? ''; });
    }
}
