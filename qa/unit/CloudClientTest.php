<?php

use Caligrafy\CloudClient;
use PHPUnit\Framework\TestCase;

/**
 * php caligrafer.php cloud: the token file, the calls to the capabilities and the messages, against a fake service
 */
final class CloudClientTest extends TestCase
{
    private string $root;
    private array $calls = [];

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/quill-cloud-' . bin2hex(random_bytes(4));
        mkdir($this->root);
        putenv('CALIGRAFY_CLOUD_TOKEN');
        putenv('CALIGRAFY_CLOUD_URL=https://cloud.test');
    }

    protected function tearDown(): void
    {
        putenv('CALIGRAFY_CLOUD_URL');
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function client(array $answers): CloudClient
    {
        return new CloudClient($this->root, function ($method, $url, $headers, $body) use ($answers) {
            $this->calls[] = [$url, json_decode($body, true), $headers];
            $capability = basename($url);
            return $answers[$capability] ?? [404, '{"error":{"code":"not_found","message":"There is no capability"}}'];
        });
    }

    private function exec(CloudClient $client, array $args): array
    {
        ob_start();
        $code = $client->run($args);
        return [$code, (string) ob_get_clean()];
    }

    public function testLoginKeepsTheTokenInTheProjectAndChecksIt(): void
    {
        [$code, $out] = $this->exec($this->client(['my_account' => [200, '{"data":{"email":"me@example.com","credits":5}}']]), ['login', '--token', 'cq_7_abc123']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Signed in as me@example.com (5 credits)', $out);
        $saved = json_decode(file_get_contents("$this->root/.caligrafy/cloud.json"), true);
        $this->assertSame(['url' => 'https://cloud.test', 'token' => 'cq_7_abc123'], $saved);
        $this->assertSame('https://cloud.test/capabilities/my_account', $this->calls[0][0]);
        $this->assertContains('Authorization: Bearer cq_7_abc123', $this->calls[0][2]);
    }

    public function testARejectedTokenIsNotKept(): void
    {
        [$code, $out] = $this->exec($this->client(['my_account' => [401, '{"error":{"code":"unauthenticated","message":"Sign in or send a personal access token"}}']]), ['login', '--token', 'cq_7_abc123']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Sign in or send a personal access token', $out);
        $this->assertFileDoesNotExist("$this->root/.caligrafy/cloud.json");
    }

    public function testCommandsNeedATokenFirst(): void
    {
        [$code, $out] = $this->exec($this->client([]), ['apps']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('Sign in first', $out);
    }

    public function testCreateSendsTheRepositoryAndShowsTheDeployKey(): void
    {
        putenv('CALIGRAFY_CLOUD_TOKEN=cq_1_ff');
        [$code, $out] = $this->exec($this->client(['create_app' => [201, json_encode(['data' => ['url' => 'https://shelf.caligrafy.com',
            'repo_url' => 'https://github.com/me/shelf', 'branch' => 'main', 'deploy_key' => 'ssh-ed25519 AAAA test']])]]),
            ['create', 'shelf', '--repo', 'https://github.com/me/shelf']);
        putenv('CALIGRAFY_CLOUD_TOKEN');
        $this->assertSame(0, $code);
        $this->assertSame(['name' => 'shelf', 'repo_url' => 'https://github.com/me/shelf'], $this->calls[0][1]);
        $this->assertStringContainsString('ssh-ed25519 AAAA test', $out);
    }

    public function testRefusalsShowTheMessageAndTheFields(): void
    {
        putenv('CALIGRAFY_CLOUD_TOKEN=cq_1_ff');
        [$code, $out] = $this->exec($this->client(['extend_app' => [403, '{"error":{"code":"forbidden","message":"A week costs 2 credits and you have 0"}}']]), ['extend', 'shelf', 'week']);
        putenv('CALIGRAFY_CLOUD_TOKEN');
        $this->assertSame(1, $code);
        $this->assertStringContainsString('A week costs 2 credits and you have 0', $out);
    }

    public function testDeleteNeedsYes(): void
    {
        putenv('CALIGRAFY_CLOUD_TOKEN=cq_1_ff');
        [$code, $out] = $this->exec($this->client([]), ['delete', 'shelf']);
        putenv('CALIGRAFY_CLOUD_TOKEN');
        $this->assertSame(1, $code);
        $this->assertSame([], $this->calls);
        $this->assertStringContainsString('--yes', $out);
    }
}
