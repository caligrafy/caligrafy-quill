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

    public function testKeptPageIsNotReplacedAndStaysKept(): void
    {
        ob_start();
        (new Upgrader($this->root, true, null, ['application/old.php']))->run();
        ob_end_clean();
        $this->assertSame('old v1', file_get_contents("$this->root/application/old.php"));
        $this->assertSame('old v2', file_get_contents("$this->root/application/old.php" . Upgrader::NEW_SUFFIX));
        $this->assertSame('kept', (new Upgrader($this->root))->plan()['old.php']['status']);
        ob_start();
        (new Upgrader($this->root, true))->run();   // a later --apply without --keep
        ob_end_clean();
        $this->assertSame('old v1', file_get_contents("$this->root/application/old.php"));
    }

    public function testReportListsNewAndObsoleteSettings(): void
    {
        $this->write('framework/settings/.env.example', "APP_KEY=\nNEW_SETTING=\n");
        $this->write('.env', "APP_KEY=secret\nCRYPTO_PAY_KEY=old\n");
        $out = $this->dryRun();
        $this->assertStringContainsString('new        NEW_SETTING', $out);
        $this->assertStringContainsString('obsolete   CRYPTO_PAY_KEY', $out);
        $this->assertSame("APP_KEY=secret\nCRYPTO_PAY_KEY=old\n", file_get_contents("$this->root/.env"));
    }

    public function testReportListsBootstrap3Classes(): void
    {
        $this->write('application/views/page.pug', ".row\n    .col-xs-6.pull-right A panel of text\n");
        $this->write('application/views/fine.pug', ".row\n    .col-md-6 A panel of text\n");
        $out = $this->dryRun();
        $this->assertStringContainsString('application/views/page.pug: col-xs-6, pull-right', $out);
        $this->assertStringNotContainsString('fine.pug', $out);
    }

    private function userTemplates(): void
    {
        $this->write(Upgrader::TEMPLATE . '/services/Users/CaligrafyUsers.php', '<?php class CaligrafyUsers {}');
        $this->write(Upgrader::TEMPLATE . '/services/Users/CaligrafyListUsers.php', "<?php #[Capability(name: 'list_users')] class CaligrafyListUsers {}");
        $this->write(Upgrader::TEMPLATE . '/controllers/core/Auth/AdminController.php', 'admin v2');
        $this->write(Upgrader::TEMPLATE . '/controllers/core/Auth/AuthController.php', 'auth v2');
        $this->write('application/controllers/core/Auth/AuthController.php', 'auth v1');
        $history = ['controllers/core/Auth/AdminController.php' => [hash('sha256', 'admin v1')], 'controllers/core/Auth/AuthController.php' => [hash('sha256', 'auth v1')]];
        $this->write(Upgrader::HISTORY, json_encode($history));
    }

    public function testUserCapabilitiesAreAddedToAProjectThatManagesUsersTheFrameworkWay(): void
    {
        $this->userTemplates();
        $this->write('application/controllers/core/Auth/AdminController.php', 'admin v1');
        $plan = (new Upgrader($this->root))->plan();
        $this->assertSame('missing', $plan['services/Users/CaligrafyListUsers.php']['status']);
        $this->assertSame('outdated', $plan['controllers/core/Auth/AdminController.php']['status']);
    }

    public function testUserCapabilitiesAreHeldWhenTheAdminControllerIsCustomized(): void
    {
        $this->userTemplates();
        $this->write('application/controllers/core/Auth/AdminController.php', 'admin v1 with my own level');
        $plan = (new Upgrader($this->root))->plan();
        $this->assertSame('held', $plan['services/Users/CaligrafyListUsers.php']['status']);
        $this->assertStringContainsString('ADMIN_LEVEL', $plan['services/Users/CaligrafyListUsers.php']['reason']);
        $this->assertSame('held', $plan['controllers/core/Auth/AuthController.php']['status']);
        $out = $this->dryRun();
        $this->assertStringContainsString('held        application/services/Users/CaligrafyListUsers.php: not added', $out);
        ob_start();
        (new Upgrader($this->root, true))->run();
        ob_end_clean();
        $this->assertFileDoesNotExist("$this->root/application/services/Users/CaligrafyListUsers.php");
        $this->assertSame('auth v1', file_get_contents("$this->root/application/controllers/core/Auth/AuthController.php"));
    }

    public function testUserCapabilitiesAreHeldWhenTheProjectDeclaresTheSameCapability(): void
    {
        $this->userTemplates();
        $this->write('application/controllers/core/Auth/AdminController.php', 'admin v1');
        $this->write('application/services/Team/Members.php', "<?php #[Capability(name: 'list_users', scope: 'team:read')] class Members {}");
        $plan = (new Upgrader($this->root))->plan();
        $this->assertSame('held', $plan['services/Users/CaligrafyUsers.php']['status']);
        $this->assertStringContainsString('the capability list_users (services/Team/Members.php)', $plan['services/Users/CaligrafyUsers.php']['reason']);
        $this->assertSame('held', $plan['controllers/core/Auth/AdminController.php']['status'], 'the admin pages call the held capabilities');
    }

    private function dryRun(): string
    {
        ob_start();
        (new Upgrader($this->root))->run();
        return (string) ob_get_clean();
    }
}
