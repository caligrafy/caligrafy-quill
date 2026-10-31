<?php

use Caligrafy\Framework;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    public function testVersionIsSemantic(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/', Framework::VERSION);
    }

    public function testChangelogStartsWithThisVersion(): void
    {
        $changelog = file_get_contents(dirname(__DIR__, 2) . '/CHANGELOG.md');
        $this->assertMatchesRegularExpression('/^## \[([^\]]+)\]/m', $changelog);
        preg_match('/^## \[([^\]]+)\]/m', $changelog, $m);
        $this->assertSame(Framework::VERSION, $m[1], 'bump Framework::VERSION and CHANGELOG.md together');
    }
}
