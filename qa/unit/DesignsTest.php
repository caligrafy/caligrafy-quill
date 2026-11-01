<?php

use Caligrafy\Designs;
use Caligrafy\Doctor;
use PHPUnit\Framework\TestCase;

final class DesignsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/quill-designs-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/framework/designs/calm', 0777, true);
        mkdir($this->root . '/framework/designs/prose.site', 0777, true);
        mkdir($this->root . '/application/designs/calm', 0777, true);
        mkdir($this->root . '/application/designs/own', 0777, true);
        mkdir($this->root . '/framework/designs-extra/extra', 0777, true);
        mkdir($this->root . '/framework/designs-extra/prose.site', 0777, true);
        mkdir($this->root . '/public/css/designs', 0777, true);
        file_put_contents($this->root . '/framework/designs/calm/DESIGN.md', "---\nversion: alpha\nname: Calm\ndescription: \"A calm framework style.\"\ncolors:\n  primary: \"#123456\"\n---\n\n## Overview\n");
        file_put_contents($this->root . '/framework/designs/prose.site/DESIGN.md', "# Design System Inspired by Prose\n\n## 1. Visual Theme\n\nA prose-only style with a **bold** first paragraph.\n\nSecond paragraph.\n");
        file_put_contents($this->root . '/application/designs/calm/DESIGN.md', "---\ndescription: The project's own calm.\n---\n");
        file_put_contents($this->root . '/application/designs/own/DESIGN.md', "Own style.\n");
        file_put_contents($this->root . '/framework/designs-extra/extra/DESIGN.md', "---\ndescription: An extra style.\n---\n");
        file_put_contents($this->root . '/framework/designs-extra/prose.site/DESIGN.md', "An extra prose.\n");
        file_put_contents($this->root . '/.env', "APP_ENV=local\nDESIGN_STYLE=\nAPP_DEBUG=true\n");
    }

    protected function tearDown(): void
    {
        putenv('DESIGN_STYLE');
        unset($_ENV['DESIGN_STYLE']);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testNamesAreLowercaseWordsWithoutPaths(): void
    {
        $this->assertSame('linear.app', Designs::name('Linear.app'));
        $this->assertSame('bmw-m', Designs::name(' "bmw-m" '));
        $this->assertSame('', Designs::name(''));
        $this->assertSame('', Designs::name('../x'));
        $this->assertSame('', Designs::name('a/b'));
        $this->assertSame('', Designs::name('a..b'));
        $this->assertSame('', Designs::name('.hidden'));
        $this->assertSame('', Designs::name('x y'));
    }

    public function testProjectStylesWinOverFrameworkStyles(): void
    {
        $styles = Designs::available($this->root);
        $this->assertSame(['calm', 'extra', 'own', 'prose.site'], array_keys($styles));
        $this->assertStringContainsString('/framework/designs-extra/extra/', $styles['extra']);
        $this->assertStringContainsString('/framework/designs-extra/prose.site/', $styles['prose.site']);   // extra wins over the collection
        $this->assertStringContainsString('/application/designs/calm/', $styles['calm']);
        $this->assertStringContainsString('/application/designs/calm/', Designs::find('calm', $this->root));
        $this->assertStringContainsString('/framework/designs-extra/prose.site/', Designs::find('prose.site', $this->root));
        $this->assertStringContainsString('/framework/designs-extra/extra/', Designs::find('extra', $this->root));
        $this->assertNull(Designs::find('nope', $this->root));
        $this->assertNull(Designs::find('../calm', $this->root));
    }

    public function testDescriptionComesFromTheFrontMatterOrTheFirstParagraph(): void
    {
        $this->assertSame('A calm framework style.', Designs::describe($this->root . '/framework/designs/calm/DESIGN.md'));
        $this->assertSame('A prose-only style with a **bold** first paragraph.', Designs::describe($this->root . '/framework/designs/prose.site/DESIGN.md'));
        $this->assertSame('An extra prose.', Designs::describe($this->root . '/framework/designs-extra/prose.site/DESIGN.md'));
        $long = $this->root . '/framework/designs/calm/LONG.md';
        file_put_contents($long, "---\ndescription: " . str_repeat('word ', 40) . "\n---\n");
        $this->assertSame(110, strlen(Designs::describe($long)));
        $this->assertSame(199, strlen(Designs::describe($long, 0)));
    }

    public function testEmptySettingKeepsTheCaligrafyLook(): void
    {
        putenv('DESIGN_STYLE=');
        $this->assertSame('OK', Designs::status($this->root)[0]);
        $this->assertStringContainsString("Caligrafy's own look", Designs::status($this->root)[1]);
        $this->assertSame('', Designs::stylesheetUrl('http://x/public/', $this->root));
    }

    public function testUnknownAndInvalidNamesAreWarnedAndChangeNothing(): void
    {
        putenv('DESIGN_STYLE=nope');
        [$status, $message, $fix] = Designs::status($this->root);
        $this->assertSame('WARN', $status);
        $this->assertStringContainsString("'nope' is unknown", $message);
        $this->assertStringContainsString('design list', $fix);
        $this->assertSame('', Designs::stylesheetUrl('http://x/public/', $this->root));

        putenv('DESIGN_STYLE=../../etc/passwd');
        $this->assertStringContainsString('is not a style name', Designs::status($this->root)[1]);
        $this->assertSame('', Designs::stylesheetUrl('http://x/public/', $this->root));
    }

    public function testStyleWithoutItsStylesheetKeepsTheCaligrafyLookUntilItIsWritten(): void
    {
        putenv('DESIGN_STYLE=calm');
        [$status, $message, $fix] = Designs::status($this->root);
        $this->assertSame('WARN', $status);
        $this->assertStringContainsString('public/css/designs/calm.css is not written yet', $message);
        $this->assertStringContainsString('/caligrafy-quill:design', $fix);
        $this->assertSame('', Designs::stylesheetUrl('http://x/public/', $this->root));

        file_put_contents($this->root . '/public/css/designs/calm.css', 'body { color: #123456; }');
        $this->assertSame('OK', Designs::status($this->root)[0]);
        $url = Designs::stylesheetUrl('http://x/public/', $this->root);
        $this->assertMatchesRegularExpression('#^http://x/public/css/designs/calm\.css\?v=\d+$#', $url);
    }

    public function testUseAndOffWriteDesignStyleInEnvOnly(): void
    {
        $env = $this->root . '/.env';
        $this->assertSame(0, $this->cli(['use', 'Prose.Site'], $output));
        $this->assertStringContainsString('not written yet', $output);
        $this->assertSame("APP_ENV=local\nDESIGN_STYLE=prose.site\nAPP_DEBUG=true\n", file_get_contents($env));

        $this->assertSame(1, $this->cli(['use', 'nope'], $output));
        $this->assertStringContainsString('DESIGN_STYLE=prose.site', file_get_contents($env));

        $this->assertSame(0, $this->cli(['off'], $output));
        $this->assertSame("APP_ENV=local\nDESIGN_STYLE=\nAPP_DEBUG=true\n", file_get_contents($env));

        file_put_contents($env, "APP_ENV=local\n");
        $this->assertSame(0, $this->cli(['use', 'own'], $output));
        $this->assertSame("APP_ENV=local\nDESIGN_STYLE=own\n", file_get_contents($env));
    }

    public function testListMarksTheSelectedStyleAndItsStylesheet(): void
    {
        putenv('DESIGN_STYLE=own');
        file_put_contents($this->root . '/public/css/designs/own.css', '');
        $this->assertSame(0, $this->cli(['list', '--json'], $output));
        $list = json_decode($output, true);
        $this->assertSame('own', $list['selected']);
        $own = array_values(array_filter($list['styles'], fn ($s) => $s['name'] === 'own'))[0];
        $this->assertTrue($own['selected']);
        $this->assertTrue($own['stylesheet']);
        $this->assertSame('application/designs/own/DESIGN.md', $own['design']);
        $this->assertSame(1, $this->cli(['bogus'], $output));
    }

    public function testDoctorReportsTheSelectedStyle(): void
    {
        putenv('DESIGN_STYLE=unknown-style');
        try {
            $doctor = new Doctor($this->root);
            (new ReflectionMethod(Doctor::class, 'project'))->invoke($doctor);
            $result = array_values(array_filter($doctor->results(), fn ($r) => $r['name'] === 'Design style'))[0] ?? null;
        } finally {
            putenv('DESIGN_STYLE');
        }
        $this->assertNotNull($result);
        $this->assertSame('WARN', $result['status']);
        $this->assertStringContainsString("'unknown-style' is unknown", $result['message']);
    }

    public function testTheFrameworkStylesKeepTheirLicenseAndAttribution(): void
    {
        $root = dirname(__DIR__, 2);
        $styles = Designs::available($root);
        $this->assertGreaterThanOrEqual(80, count(array_filter($styles, fn ($f) => strpos($f, '/framework/designs/') !== false)));
        $this->assertArrayHasKey('claude', $styles);
        $this->assertStringContainsString('Copyright (c) 2026 VoltAgent', file_get_contents($root . '/framework/designs/LICENSE'));
        $this->assertStringContainsString('Permission is hereby granted', file_get_contents($root . '/framework/designs/LICENSE'));
        $this->assertStringContainsString('VoltAgent/awesome-design-md', file_get_contents($root . '/framework/designs/README.md'));
        $this->assertMatchesRegularExpression('#^https://github\.com/VoltAgent/awesome-design-md [0-9a-f]{40} #', file_get_contents($root . '/framework/designs/SOURCE'));
        $this->assertStringContainsString('/framework/designs-extra/interstellar/DESIGN.md', $styles['interstellar'] ?? '');
        $this->assertStringStartsWith('A calm, dense laboratory operations system', Designs::describe($styles['interstellar']));
        $this->assertStringContainsString('`interstellar`', file_get_contents($root . '/framework/designs-extra/README.md'));
        $this->assertFileDoesNotExist($root . '/framework/designs/interstellar/DESIGN.md', 'interstellar is not part of the awesome-design-md copy');
        foreach ($styles as $name => $file) {
            $this->assertNotSame('', Designs::describe($file), "$name has no description");
        }
    }

    private function cli(array $args, ?string &$output): int
    {
        ob_start();
        $code = Designs::cli($args, $this->root, $this->root . '/.env');
        $output = ob_get_clean();
        return $code;
    }
}
