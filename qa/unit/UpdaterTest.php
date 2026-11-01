<?php

use Caligrafy\Updater;
use PHPUnit\Framework\TestCase;

/**
 * php caligrafer.php update: a project of release 1.0.0 moved to 1.1.0, both releases built in a temporary folder
 */
final class UpdaterTest extends TestCase
{
    private string $dir;
    private array $commands = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/quill-update-' . bin2hex(random_bytes(4));
        $old = [
            'framework/core/Framework.php' => "<?php const VERSION = '1.0.0';",
            'framework/core/Gone.php' => '<?php // dropped in 1.1.0',
            'framework/settings/.env.example' => "APP_ENV=local\n",
            'claude-plugin/README.md' => 'plugin 1',
            'caligrafer.php' => 'cli 1',
            'public/js/services/app.js' => 'app 1',
            'public/css/styles.css' => 'styles 1',
            'README.md' => 'readme 1',
            'composer.json' => json_encode(['require' => ['php' => '>=8.3', 'a/lib' => '^1.0', 'b/lib' => '^2.0']]),
            '.gitignore' => "/vendor/\n",
        ];
        $new = array_merge($old, [
            'framework/core/Framework.php' => "<?php const VERSION = '1.1.0';",
            'framework/core/New.php' => '<?php // new in 1.1.0',
            'framework/settings/.env.example' => "APP_ENV=local\n# the new look\nDESIGN_STYLE=\n",
            'claude-plugin/README.md' => 'plugin 2',
            'caligrafer.php' => 'cli 2',
            'public/js/services/app.js' => 'app 2',
            'public/css/tokens.css' => 'tokens',
            'README.md' => 'readme 2',
            'composer.json' => json_encode(['require' => ['php' => '>=8.3', 'a/lib' => '^1.5', 'b/lib' => '^3.0', 'c/lib' => '^1.0']]),
            '.gitignore' => "/vendor/\n/.caligrafy/\n",
            'qa/run.sh' => 'tests',
            'LICENSE' => 'MIT',
        ]);
        unset($new['framework/core/Gone.php']);
        $this->write("$this->dir/old", $old);
        $this->write("$this->dir/new", $new);
        // the project: release 1.0.0 with its own changes
        $this->write("$this->dir/project", array_merge($old, [
            'framework/librairies/app/.env' => 'VITE_API_KEY=x',
            'public/js/services/app.js' => 'app 1 + mine',
            'public/css/styles.css' => 'styles 1 + mine',
            'README.md' => 'my app',
            'composer.json' => json_encode(['require' => ['php' => '>=8.3', 'a/lib' => '^1.0', 'b/lib' => '^2.1', 'mine/lib' => '^1']]),
            '.env' => "APP_ENV=local\nMY_SECRET=keep\n",
            'application/views/page.pug' => 'mine',
        ]));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    private function write(string $root, array $files): void
    {
        foreach ($files as $path => $content) {
            @mkdir(dirname("$root/$path"), 0755, true);
            file_put_contents("$root/$path", $content);
        }
    }

    private function update(bool $check = false, ?string $target = null): array
    {
        $fetch = fn ($v) => $v === 'latest' ? 'v1.1.0' : ($v === '1.0.0' ? "$this->dir/old" : ($v === '1.1.0' ? "$this->dir/new" : null));
        $run = function ($command) { $this->commands[] = $command; return 0; };
        ob_start();
        $code = (new Updater("$this->dir/project", $fetch, $run))->run($target, $check, true, true);
        return [$code, (string) ob_get_clean()];
    }

    private function read(string $path): ?string
    {
        return is_file("$this->dir/project/$path") ? file_get_contents("$this->dir/project/$path") : null;
    }

    public function testFrameworkAndPluginAreReplacedWholeKeepingWhatInitializeWrote(): void
    {
        [$code, $out] = $this->update();
        $this->assertSame(0, $code, $out);
        $this->assertSame("<?php const VERSION = '1.1.0';", $this->read('framework/core/Framework.php'));
        $this->assertSame('<?php // new in 1.1.0', $this->read('framework/core/New.php'));
        $this->assertNull($this->read('framework/core/Gone.php'));
        $this->assertSame('VITE_API_KEY=x', $this->read('framework/librairies/app/.env'));
        $this->assertSame('plugin 2', $this->read('claude-plugin/README.md'));
        $this->assertNotEmpty(glob("$this->dir/project/backups/caligrafy-update-1.0.0-to-1.1.0-*/framework/core/Gone.php"));
    }

    public function testReleaseFilesAreReplacedOnlyWhenUntouched(): void
    {
        [, $out] = $this->update();
        $this->assertSame('cli 2', $this->read('caligrafer.php'));                        // untouched: replaced
        $this->assertSame('tokens', $this->read('public/css/tokens.css'));               // missing: added
        $this->assertSame('app 1 + mine', $this->read('public/js/services/app.js'));     // changed by both: kept
        $this->assertSame('app 2', $this->read('public/js/services/app.js.caligrafy-new'));
        $this->assertSame('styles 1 + mine', $this->read('public/css/styles.css'));      // the release did not change it
        $this->assertNull($this->read('public/css/styles.css.caligrafy-new'));
        $this->assertSame('my app', $this->read('README.md'));                           // a document of the project
        $this->assertNull($this->read('README.md.caligrafy-new'));
        $this->assertNull($this->read('qa/run.sh'));
        $this->assertNull($this->read('LICENSE'));                                       // never added to a project
        $this->assertSame('mine', $this->read('application/views/page.pug'));
        $this->assertStringContainsString('customized  public/js/services/app.js', $out);
    }

    public function testComposerGitignoreAndEnvGetWhatTheReleaseAdded(): void
    {
        $this->update();
        $composer = json_decode($this->read('composer.json'), true)['require'];
        $this->assertSame('^1.5', $composer['a/lib']);      // untouched by the project: the release's version
        $this->assertSame('^2.1', $composer['b/lib']);      // changed by the project: kept
        $this->assertSame('^1.0', $composer['c/lib']);      // new
        $this->assertSame('^1', $composer['mine/lib']);
        $this->assertSame(["composer update --no-interaction 'a/lib' 'c/lib'"], array_slice($this->commands, 0, 1));
        $this->assertStringEndsWith('caligrafer.php upgrade --apply --local', end($this->commands));
        $this->assertStringContainsString('/.caligrafy/', $this->read('.gitignore'));
        $env = $this->read('.env');
        $this->assertStringContainsString("MY_SECRET=keep\n", $env);
        $this->assertStringContainsString("# the new look\nDESIGN_STYLE=\n", $env);
        $this->assertSame(1, substr_count($env, 'APP_ENV='));
    }

    public function testCheckChangesNothing(): void
    {
        [$code, $out] = $this->update(true);
        $this->assertSame(0, $code);
        $this->assertSame('cli 1', $this->read('caligrafer.php'));
        $this->assertNull($this->read('public/js/services/app.js.caligrafy-new'));
        $this->assertStringNotContainsString('DESIGN_STYLE', $this->read('.env'));
        $this->assertSame([], $this->commands);
        $this->assertStringContainsString('Nothing was changed', $out);
        $this->assertDirectoryDoesNotExist("$this->dir/project/backups");
    }

    public function testTheSameOrAnOlderReleaseChangesNothing(): void
    {
        [$code, $out] = $this->update(false, '1.0.0');
        $this->assertSame(0, $code);
        $this->assertStringContainsString('nothing to update', $out);
        [$code, $out] = $this->update(false, '0.9.0');
        $this->assertSame(1, $code);
        $this->assertStringContainsString('not supported', $out);
        [$code, $out] = $this->update(false, 'next');
        $this->assertSame(1, $code);
        $this->assertSame('cli 1', $this->read('caligrafer.php'));
    }

    public function testNewerReleaseTellsUpgradeWhetherToUpdate(): void
    {
        $updater = fn ($latest) => new Updater("$this->dir/project", fn ($v) => $v === 'latest' ? $latest : null);
        $this->assertSame(['1.1.0', null], $updater('v1.1.0')->newerRelease());
        $this->assertSame([null, null], $updater('v1.0.0')->newerRelease());
        $this->assertSame([null, null], $updater('0.9.0')->newerRelease());
        $offline = new Updater("$this->dir/project", function () { throw new RuntimeException('no network'); });
        $this->assertSame([null, 'no network'], $offline->newerRelease());
    }

    public function testTheUpdateRunsUpgradeOnThisReleaseOnly(): void
    {
        $this->update();
        $this->assertStringEndsWith('caligrafer.php upgrade --apply --local', end($this->commands));
    }
}
