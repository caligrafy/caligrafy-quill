<?php

use Caligrafy\Doctor;
use PHPUnit\Framework\TestCase;

/**
 * Two Pug mistakes that only show when the page renders: a dollar sign before digits vanishes, and code on the line of a
 * one-line - if / - foreach does not compile; doctor names them
 */
final class DoctorPugTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/quill-pug-' . bin2hex(random_bytes(4));
        mkdir("$this->dir/application/views/Shop", 0755, true);
    }

    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->dir);
    }

    public function testADollarBeforeDigitsAndAOneLineIfAreNamed(): void
    {
        file_put_contents("$this->dir/application/views/Shop/price.pug",
            "block content\n  p.price From \$40 a month\n  - if (\$sold) echo 'Sold'\n  - foreach (\$rows as \$r) { echo \$r; }\n");
        $this->assertSame(['application/views/Shop/price.pug:2 $digits', 'application/views/Shop/price.pug:3 one-line - if', 'application/views/Shop/price.pug:4 one-line - foreach'],
            Doctor::pugPitfalls($this->dir));
    }

    public function testTheRightWaysAreFine(): void
    {
        file_put_contents("$this->dir/application/views/Shop/price.pug",
            "block content\n  //- \$40 in a comment is fine\n  p.price From &#36;40 a month\n  p= \$price\n  - if (count(\$rows) > 0)\n    p Some\n  if \$sold\n    p Sold\n  - foreach (\$rows as \$r) {\n");
        $this->assertSame([], Doctor::pugPitfalls($this->dir));
    }
}
