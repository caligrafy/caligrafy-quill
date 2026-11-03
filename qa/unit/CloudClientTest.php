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

    public function testALockedAppSaysSoWithoutItsPassword(): void
    {
        putenv('CALIGRAFY_CLOUD_TOKEN=cq_1_ff');
        $apps = json_encode(['data' => [
            ['name' => 'shelf', 'status' => 'running', 'url' => 'https://shelf.caligrafy.com', 'no_end' => true, 'password' => 'k7mq-3xtp-9fhr'],
            ['name' => 'open', 'status' => 'running', 'url' => 'https://open.caligrafy.com', 'no_end' => true, 'password' => null],
        ]]);
        [$code, $out] = $this->exec($this->client(['list_apps' => [200, $apps]]), ['apps']);
        [, $status] = $this->exec($this->client(['get_app' => [200, json_encode(['data' => ['name' => 'shelf', 'status' => 'running',
            'url' => 'https://shelf.caligrafy.com', 'no_end' => true, 'password' => 'k7mq-3xtp-9fhr']])]]), ['status', 'shelf']);
        putenv('CALIGRAFY_CLOUD_TOKEN');
        $this->assertSame(0, $code);
        $this->assertMatchesRegularExpression('/shelf .*\[password protected\]/', $out);
        $this->assertDoesNotMatchRegularExpression('/open .*password/', $out);
        $this->assertStringContainsString('[password protected]', $status);
        $this->assertStringNotContainsString('k7mq', $out . $status);
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

    public function testLoginWithoutATokenIsApprovedInTheBrowser(): void
    {
        putenv('CALIGRAFY_NO_BROWSER=1');
        $polls = 0;
        $client = new CloudClient($this->root, function ($method, $url, $headers, $body) use (&$polls) {
            $this->calls[] = [$url, json_decode($body, true), $headers];
            switch (basename($url)) {
                case 'start_cli_login':
                    return [200, '{"data":{"device_code":"' . str_repeat('d', 64) . '","user_code":"BCDF-GH23","verification_url":"https://cloud.test/cli?code=BCDF-GH23","expires_in":600,"interval":3}}'];
                case 'poll_cli_login':
                    return [200, ++$polls < 2 ? '{"data":{"status":"pending"}}' : '{"data":{"status":"approved","token":"cq_9_abc"}}'];
                case 'my_account':
                    return [200, '{"data":{"email":"me@example.com","credits":5}}'];
            }
            return [404, '{}'];
        }, function () {});
        try {
            [$code, $out] = $this->exec($client, ['login']);
        } finally {
            putenv('CALIGRAFY_NO_BROWSER');
        }
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('BCDF-GH23', $out);
        $this->assertStringContainsString('https://cloud.test/cli?code=BCDF-GH23', $out);
        $this->assertStringContainsString('Signed in as me@example.com', $out);
        $this->assertSame('cq_9_abc', json_decode(file_get_contents($this->root . '/.caligrafy/cloud.json'), true)['token']);
        // start and poll go without a token; my_account with the new one
        $this->assertNotContains('Authorization: Bearer cq_9_abc', $this->calls[0][2]);
        $this->assertContains('Authorization: Bearer cq_9_abc', end($this->calls)[2]);
        $this->assertSame(2, $polls);
    }

    public function testADeniedBrowserLoginFails(): void
    {
        putenv('CALIGRAFY_NO_BROWSER=1');
        $client = new CloudClient($this->root, function ($method, $url) {
            return basename($url) === 'start_cli_login'
                ? [200, '{"data":{"device_code":"x","user_code":"BCDF-GH23","verification_url":"https://cloud.test/cli?code=BCDF-GH23","expires_in":600,"interval":3}}']
                : [200, '{"data":{"status":"denied"}}'];
        }, function () {});
        try {
            [$code, $out] = $this->exec($client, ['login']);
        } finally {
            putenv('CALIGRAFY_NO_BROWSER');
        }
        $this->assertSame(1, $code);
        $this->assertStringContainsString('denied', $out);
        $this->assertFileDoesNotExist($this->root . '/.caligrafy/cloud.json');
    }

    public function testCreateKeepsTheDeployKeyForTheGithubCli(): void
    {
        @mkdir($this->root . '/.caligrafy');
        file_put_contents($this->root . '/.caligrafy/cloud.json', json_encode(['url' => 'https://cloud.test', 'token' => 'cq_7_abc']));
        [$code, $out] = $this->exec($this->client(['create_app' => [200, '{"data":{"url":"https://shop.caligrafy.com","repo_url":"https://github.com/me/shop","branch":"main","deploy_key":"ssh-ed25519 AAAA caligrafy-cloud-shop"}}']]),
            ['create', 'shop', '--repo', 'https://github.com/me/shop']);
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('gh repo deploy-key add .caligrafy/shop-deploy-key.pub', $out);
        $this->assertSame("ssh-ed25519 AAAA caligrafy-cloud-shop\n", file_get_contents($this->root . '/.caligrafy/shop-deploy-key.pub'));
    }

    public function testSubmitSendsTheMarketplaceFileThenDeploys(): void
    {
        putenv('CALIGRAFY_CLOUD_TOKEN=cq_7_abc123');
        mkdir("$this->root/.caligrafy");
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        file_put_contents("$this->root/.caligrafy/shot.png", $png);
        file_put_contents("$this->root/.caligrafy/same.png", $png);
        file_put_contents("$this->root/.caligrafy/marketplace.json", json_encode(['title' => 'Books', 'description' => 'A catalog.',
            'prompt' => 'Build a catalog...', 'pages' => [['path' => '/', 'caption' => 'Home']], 'images' => [['file' => 'shot.png', 'caption' => 'Signed in'], ['file' => 'same.png', 'caption' => 'Again']],
            'price' => ['low' => 19, 'high' => 39, 'rationale' => 'Like starter kits.']]));
        [$code, $out] = $this->exec($this->client([
            'submit_app_listing' => [200, '{"data":{"status":"processing"}}'],
            'deploy_app' => [200, '{"data":{"url":"https://books.caligrafy.com"}}'],
        ]), ['submit', 'books', '--no-wait']);
        putenv('CALIGRAFY_CLOUD_TOKEN');
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('Submitted the marketplace listing of books', $out);
        $sent = $this->calls[0][1];
        $this->assertSame('https://cloud.test/capabilities/submit_app_listing', $this->calls[0][0]);
        $this->assertSame(['books', 'Books', '19', '39'], [$sent['name'], $sent['title'], $sent['price_low'], $sent['price_high']]);
        $this->assertSame([['path' => '/', 'caption' => 'Home']], json_decode($sent['pages'], true));
        $this->assertStringStartsWith('data:image/png;base64,', json_decode($sent['images'], true)[0]['image']);
        $this->assertCount(1, json_decode($sent['images'], true), 'the same screen twice is sent once');
        $this->assertSame('https://cloud.test/capabilities/deploy_app', $this->calls[1][0]);
    }

    public function testSubmitNeedsTheMarketplaceFile(): void
    {
        putenv('CALIGRAFY_CLOUD_TOKEN=cq_7_abc123');
        [$code, $out] = $this->exec($this->client([]), ['submit', 'books']);
        putenv('CALIGRAFY_CLOUD_TOKEN');
        $this->assertSame(1, $code);
        $this->assertStringContainsString('/caligrafy-quill:marketplace', $out);
        $this->assertSame([], $this->calls);
    }

    public function testJobsListsEachJobWithItsLastAndNextRun(): void
    {
        putenv('CALIGRAFY_CLOUD_TOKEN=cq_7_abc123');
        $jobs = '{"data":{"status":"running","error":null,"note":null,"jobs":[{"name":"check-prices","cron":"* * * * *","timezone":"UTC","timeout":55,'
            . '"last_run":{"started_at":"2026-11-03T12:00:00+00:00","duration":1.2,"outcome":"ok","output":""},"next_run":"2026-11-03T12:01:00+00:00"}]}}';
        [$code, $out] = $this->exec($this->client(['app_jobs' => [200, $jobs]]), ['jobs', 'pricepilot']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('check-prices', $out);
        $this->assertStringContainsString(' ok', $out);
        $this->assertSame(['name' => 'pricepilot'], $this->calls[0][1]);
        putenv('CALIGRAFY_CLOUD_TOKEN');
    }

    public function testJobsRunAndHistoryCallTheirCapabilities(): void
    {
        putenv('CALIGRAFY_CLOUD_TOKEN=cq_7_abc123');
        [$code, $out] = $this->exec($this->client(['app_job_run' => [200, '{"data":{"job":"check-prices","starts_before":"2026-11-03T12:02:00+00:00"}}']]), ['jobs', 'pricepilot', 'run', 'check-prices']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('starts within a minute', $out);
        $this->assertSame(['name' => 'pricepilot', 'job' => 'check-prices'], $this->calls[0][1]);
        $history = '{"data":{"job":"check-prices","runs":[{"started_at":"2026-11-03T12:00:00+00:00","duration":1.2,"outcome":"error","output":"check-prices: error (no price)"}]}}';
        [$code, $out] = $this->exec($this->client(['app_job_history' => [200, $history]]), ['jobs', 'pricepilot', 'history', 'check-prices', '--limit', '5']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('check-prices: error (no price)', $out);
        $this->assertSame(['name' => 'pricepilot', 'job' => 'check-prices', 'limit' => 5], $this->calls[1][1]);
        putenv('CALIGRAFY_CLOUD_TOKEN');
    }
}
