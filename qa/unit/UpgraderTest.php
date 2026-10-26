<?php

use Caligrafy\Upgrader;
use PHPUnit\Framework\TestCase;

/**
 * Classification of application/ files against a small fake template set, in a temporary project
 */
final class UpgraderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/quill-upgrader-' . bin2hex(random_bytes(4));
        $this->write(Upgrader::TEMPLATE . '/same.php', 'same v2');
        $this->write(Upgrader::TEMPLATE . '/old.php', 'old v2');
        $this->write(Upgrader::TEMPLATE . '/mine.php', 'mine v2');
        $this->write(Upgrader::TEMPLATE . '/new.php', 'new v2');
        $this->write('application/same.php', 'same v2');
        $this->write('application/old.php', 'old v1');
        $this->write('application/mine.php', 'mine v1 + my changes');
        $this->write('.env', "APP_KEY=secret\n");
        Upgrader::writeManifest($this->root, ['old.php' => hash('sha256', 'old v1'), 'mine.php' => hash('sha256', 'mine v1')]);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function write(string $path, string $content): void
    {
        @mkdir(dirname("$this->root/$path"), 0777, true);
        file_put_contents("$this->root/$path", $content);
    }

    public function testPlanClassifiesFiles(): void
    {
        $status = array_map(fn ($entry) => $entry['status'], (new Upgrader($this->root))->plan());
        $this->assertSame(['mine.php' => 'customized', 'new.php' => 'missing', 'old.php' => 'outdated', 'same.php' => 'identical'], $status);
    }

    public function testTemplateHistoryMarksUntouchedFilesAsOutdated(): void
    {
        unlink("$this->root/application/" . Upgrader::MANIFEST);
        $this->write(Upgrader::HISTORY, json_encode(['old.php' => [hash('sha256', 'old v1')]]));
        $plan = (new Upgrader($this->root))->plan();
        $this->assertSame('outdated', $plan['old.php']['status']);
        $this->assertSame('customized', $plan['mine.php']['status']);
    }

    public function testApplyUpdatesUntouchedFilesOnlyAndNeverTouchesEnv(): void
    {
        ob_start();
        (new Upgrader($this->root, true))->run();
        ob_end_clean();
        $this->assertSame('old v2', file_get_contents("$this->root/application/old.php"));
        $this->assertSame('new v2', file_get_contents("$this->root/application/new.php"));
        $this->assertSame('mine v1 + my changes', file_get_contents("$this->root/application/mine.php"));
        $this->assertSame("APP_KEY=secret\n", file_get_contents("$this->root/.env"));
    }
}
