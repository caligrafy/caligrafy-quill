<?php

use Caligrafy\PluginSource;
use PHPUnit\Framework\TestCase;

/**
 * Each project names its own Claude Code plugin source: a shared "caligrafy" gave every project the first one's plugin
 */
final class PluginSourceTest extends TestCase
{
    private string $dir;
    private string $home;
    private string|false $savedHome;

    protected function setUp(): void
    {
        $base = sys_get_temp_dir() . '/quill-plugin-' . bin2hex(random_bytes(4));
        $this->dir = "$base/myapp";
        $this->home = "$base/home";
        mkdir("$this->dir/.claude", 0755, true);
        mkdir("$this->dir/.claude-plugin", 0755, true);
        mkdir("$this->home/.claude/plugins", 0755, true);
        file_put_contents("$this->dir/.claude-plugin/marketplace.json", json_encode(['name' => 'caligrafy', 'plugins' => [['name' => 'caligrafy-quill', 'source' => './claude-plugin']]]));
        file_put_contents("$this->dir/.claude/settings.json", json_encode([
            'permissions' => ['allow' => ['Bash(php -l:*)']],
            'extraKnownMarketplaces' => ['caligrafy' => ['source' => ['source' => 'directory', 'path' => './']]],
            'enabledPlugins' => ['caligrafy-quill@caligrafy' => true],
        ]));
        $this->savedHome = getenv('HOME');
        putenv("HOME=$this->home");
    }

    protected function tearDown(): void
    {
        $this->savedHome === false ? putenv('HOME') : putenv("HOME=$this->savedHome");
        exec('rm -rf ' . escapeshellarg(dirname($this->dir)));
    }

    public function testASharedSourceGetsTheProjectsOwnNameInBothFiles(): void
    {
        $name = PluginSource::ensureOwn($this->dir);
        $this->assertMatchesRegularExpression('/^caligrafy-myapp-[0-9a-f]{4}$/', $name);
        $this->assertSame($name, PluginSource::name($this->dir));
        $settings = json_decode(file_get_contents("$this->dir/.claude/settings.json"), true);
        $this->assertSame(['source' => ['source' => 'directory', 'path' => './']], $settings['extraKnownMarketplaces'][$name]);
        $this->assertArrayNotHasKey('caligrafy', $settings['extraKnownMarketplaces']);
        $this->assertSame([ "caligrafy-quill@$name" => true], $settings['enabledPlugins']);
        $this->assertSame(['allow' => ['Bash(php -l:*)']], $settings['permissions']);      // the rest is kept
        $this->assertNull(PluginSource::ensureOwn($this->dir));                            // once only
        $this->assertSame($name, PluginSource::name($this->dir));
    }

    public function testConnectRegistersAndInstallsWithTheClaudeCommand(): void
    {
        $name = PluginSource::ensureOwn($this->dir);
        $commands = [];
        $run = function ($command) use (&$commands) { $commands[] = $command; return [0, '']; };
        $this->assertSame(0, PluginSource::connect($this->dir, function () {}, $run));
        $this->assertSame('command -v claude', $commands[0]);
        $this->assertStringContainsString('claude plugin marketplace add', $commands[1]);
        $this->assertStringContainsString("claude plugin install 'caligrafy-quill@$name' --scope project", end($commands));
    }

    public function testConnectReplacesASourceRegisteredFromAnotherFolder(): void
    {
        $name = PluginSource::ensureOwn($this->dir);
        file_put_contents("$this->home/.claude/plugins/known_marketplaces.json", json_encode([$name => ['installLocation' => '/gone/elsewhere']]));
        $this->assertSame('/gone/elsewhere', PluginSource::registeredPath($name));
        $commands = [];
        $run = function ($command) use (&$commands, $name) { $commands[] = $command; return [0, strpos($command, 'plugin list') ? "caligrafy-quill@$name" : '']; };
        PluginSource::connect($this->dir, function () {}, $run);
        $this->assertStringContainsString("claude plugin marketplace remove '$name'", $commands[1]);
        $this->assertStringContainsString('claude plugin marketplace add', $commands[2]);
        $this->assertStringNotContainsString('install', implode(' ', $commands));       // already installed
    }

    public function testWithoutClaudeItOnlySaysWhatToDo(): void
    {
        $said = [];
        $code = PluginSource::connect($this->dir, function ($line) use (&$said) { $said[] = $line; }, fn ($c) => [1, '']);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('php caligrafer.php plugin', $said[0]);
    }

    public function testConnectRemovesTheSharedSourceThisFolderRegisteredBeforeItsRename(): void
    {
        $name = PluginSource::ensureOwn($this->dir);
        file_put_contents("$this->home/.claude/plugins/known_marketplaces.json", json_encode([
            'caligrafy' => ['installLocation' => realpath($this->dir)], 'other' => ['installLocation' => '/x']]));
        $commands = [];
        $run = function ($command) use (&$commands) { $commands[] = $command; return [0, '']; };
        PluginSource::connect($this->dir, function () {}, $run);
        $this->assertStringContainsString("claude plugin marketplace remove 'caligrafy'", $commands[1]);
        $this->assertStringStartsWith('cd ', $commands[1]);
        // a shared source registered from another folder is that folder's business
        file_put_contents("$this->home/.claude/plugins/known_marketplaces.json", json_encode(['caligrafy' => ['installLocation' => '/elsewhere']]));
        $commands = [];
        PluginSource::connect($this->dir, function () {}, $run);
        $this->assertStringNotContainsString("remove 'caligrafy'", implode(' ', $commands));
    }

    // Claude Code runs a copy of the plugin made at install time: a newer claude-plugin/ is refreshed into it
    private function installed(string $name, string $version): void
    {
        mkdir("$this->dir/claude-plugin/.claude-plugin", 0755, true);
        file_put_contents("$this->dir/claude-plugin/.claude-plugin/plugin.json", json_encode(['name' => 'caligrafy-quill', 'version' => '1.25.1']));
        file_put_contents("$this->home/.claude/plugins/known_marketplaces.json", json_encode([$name => ['installLocation' => realpath($this->dir)]]));
        file_put_contents("$this->home/.claude/plugins/installed_plugins.json", json_encode(['version' => 2, 'plugins' => [
            "caligrafy-quill@$name" => [['scope' => 'project', 'projectPath' => realpath($this->dir), 'version' => $version],
                ['scope' => 'project', 'projectPath' => '/another/project', 'version' => '0.9.0']]]]));
    }

    public function testAnOlderCopyOfThePluginIsRefreshed(): void
    {
        $name = PluginSource::ensureOwn($this->dir);
        $this->installed($name, '1.24.4');
        $this->assertSame('1.24.4', PluginSource::installedVersion($name, $this->dir));
        $this->assertSame('1.25.1', PluginSource::projectVersion($this->dir));
        $commands = []; $said = [];
        $run = function ($command) use (&$commands, $name) { $commands[] = $command; return [0, strpos($command, 'plugin list') ? "caligrafy-quill@$name" : '']; };
        $this->assertSame(0, PluginSource::connect($this->dir, function ($line) use (&$said) { $said[] = $line; }, $run));
        $this->assertStringContainsString("claude plugin marketplace update '$name'", $commands[2]);
        $this->assertStringContainsString("claude plugin update 'caligrafy-quill@$name' --scope project", $commands[3]);
        $this->assertStringContainsString('1.24.4, now 1.25.1', $said[0]);
    }

    public function testACurrentCopyIsLeftAlone(): void
    {
        $name = PluginSource::ensureOwn($this->dir);
        $this->installed($name, '1.25.1');
        $commands = [];
        $run = function ($command) use (&$commands, $name) { $commands[] = $command; return [0, strpos($command, 'plugin list') ? "caligrafy-quill@$name" : '']; };
        PluginSource::connect($this->dir, function () {}, $run);
        $this->assertStringNotContainsString('update', implode(' ', $commands));
        $this->assertNull(PluginSource::installedVersion('caligrafy-other-1234', $this->dir));
    }
}
