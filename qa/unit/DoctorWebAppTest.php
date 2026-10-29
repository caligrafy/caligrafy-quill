<?php

use Caligrafy\Doctor;
use PHPUnit\Framework\TestCase;

/**
 * Doctor's install to home screen check (web app manifest, optional service worker) in a temporary project
 */
final class DoctorWebAppTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/quill-webapp-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/public/images/resources', 0777, true);
        foreach (['icon-192x192.png', 'icon-512x512.png'] as $icon) touch("$this->root/public/images/resources/$icon");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
        putenv('PWA_SERVICE_WORKER');
        putenv('APP_ENV');
        putenv('APP_PROTOCOL');
    }

    private function manifest(array $icons, array $extra = []): void
    {
        $icons = array_map(fn ($size) => ['src' => "images/resources/icon-$size.png", 'sizes' => $size, 'type' => 'image/png'], $icons);
        file_put_contents("$this->root/public/manifest.webmanifest", json_encode(['name' => 'Test', 'start_url' => '../', 'icons' => $icons] + $extra));
    }

    /**
     * webApp() is private: it records its results, read back with results()
     */
    private function check(?string $root = null): array
    {
        $doctor = new Doctor($root ?? $this->root);
        (new ReflectionMethod(Doctor::class, 'webApp'))->invoke($doctor);
        return array_map(fn ($r) => "{$r['status']} {$r['name']}: {$r['message']}", $doctor->results());
    }

    public function testFrameworkManifestIsValidAndItsIconsExist(): void
    {
        $this->assertSame(['OK public/manifest.webmanifest: 4 icon(s) found'], $this->check(dirname(__DIR__, 2)));
    }

    public function testMissingManifestIsWarned(): void
    {
        $this->assertStringStartsWith('WARN public/manifest.webmanifest: missing', $this->check()[0]);
    }

    public function testInvalidJsonIsWarned(): void
    {
        file_put_contents("$this->root/public/manifest.webmanifest", '{"name": "Test",}');
        $this->assertStringStartsWith('WARN public/manifest.webmanifest: not valid JSON', $this->check()[0]);
    }

    public function testMissingIconIsWarned(): void
    {
        $this->manifest(['192x192', '512x512', '1024x1024']);
        $this->assertSame(['WARN public/manifest.webmanifest: icon(s) not found: images/resources/icon-1024x1024.png'], $this->check());
    }

    public function testChromeSizesAreRequired(): void
    {
        $this->manifest(['192x192']);
        $this->assertSame(['WARN public/manifest.webmanifest: no 512x512 icon'], $this->check());
    }

    public function testNameAndStartUrlAreRequired(): void
    {
        file_put_contents("$this->root/public/manifest.webmanifest", json_encode(['icons' => []]));
        $this->assertSame(['WARN public/manifest.webmanifest: no name, start_url'], $this->check());
    }

    public function testServiceWorkerIsOnlyCheckedWhenEnabled(): void
    {
        $this->manifest(['192x192', '512x512']);
        $this->assertCount(1, $this->check());

        putenv('PWA_SERVICE_WORKER=true');
        $this->assertStringStartsWith('WARN Service worker: PWA_SERVICE_WORKER=true but public/sw.js, public/offline.html missing', $this->check()[1]);

        touch("$this->root/public/sw.js");
        touch("$this->root/public/offline.html");
        $this->assertStringStartsWith('OK Service worker', $this->check()[1]);

        putenv('APP_ENV=production');
        putenv('APP_PROTOCOL=http');
        $this->assertStringStartsWith('WARN Service worker: browsers only register service workers over HTTPS', $this->check()[1]);
    }
}
