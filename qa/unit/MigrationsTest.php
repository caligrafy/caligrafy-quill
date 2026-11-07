<?php

use Caligrafy\Migrations;
use PHPUnit\Framework\TestCase;

/**
 * Migrations without a database: how a script is split, which files are migrations and in what order, make()
 */
final class MigrationsTest extends TestCase
{
    private $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/caligrafy-migrations-' . getmypid() . '-' . mt_rand();
        mkdir($this->root . '/' . Migrations::FOLDER, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/' . Migrations::FOLDER . '/*') as $f) unlink($f);
        rmdir($this->root . '/' . Migrations::FOLDER);
        rmdir($this->root . '/application');
        rmdir($this->root);
    }

    public function testStatementsSplitOutsideQuotesAndComments(): void
    {
        $sql = "-- a comment; with a semicolon\nCREATE TABLE a (id INT); # another; comment\n"
            . "INSERT INTO a VALUES ('x;y', \"it''s; \\\"z\\\"\", `c;d`);\n/* block; comment */ UPDATE a SET id = 2;;\n  \n"
            . "INSERT INTO a VALUES ('it''s')";
        $this->assertSame(array(
            'CREATE TABLE a (id INT)',
            "INSERT INTO a VALUES ('x;y', \"it''s; \\\"z\\\"\", `c;d`)",
            'UPDATE a SET id = 2',
            "INSERT INTO a VALUES ('it''s')",
        ), Migrations::statements($sql));
    }

    public function testADoubleDashWithoutASpaceIsNotAComment(): void
    {
        $this->assertSame(array('SELECT 1--1'), Migrations::statements('SELECT 1--1;'));
    }

    public function testFilesAreInNumberOrder(): void
    {
        foreach (array('20261107120000_b.sql', '2_a.sql', '10_c.sql') as $f) touch($this->root . '/' . Migrations::FOLDER . "/$f");
        $this->assertSame(array('2_a.sql', '10_c.sql', '20261107120000_b.sql'), array_keys((new Migrations($this->root))->files()));
        $this->assertSame(Migrations::FOLDER . '/2_a.sql', (new Migrations($this->root))->files()['2_a.sql']);
    }

    public function testABadNameOrTheSameNumberTwiceIsRefused(): void
    {
        touch($this->root . '/' . Migrations::FOLDER . '/add books.sql');
        try {
            (new Migrations($this->root))->files();
            $this->fail('a bad name is refused');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('<number>_<name>.sql', $e->getMessage());
        }
        unlink($this->root . '/' . Migrations::FOLDER . '/add books.sql');
        touch($this->root . '/' . Migrations::FOLDER . '/5_a.sql');
        touch($this->root . '/' . Migrations::FOLDER . '/005_b.sql');
        $this->expectExceptionMessage('the same number');
        (new Migrations($this->root))->files();
    }

    public function testMakeWritesATimestampedFile(): void
    {
        $path = (new Migrations($this->root))->make('Add books ISBN!', mktime(9, 8, 7, 11, 7, 2026));
        $this->assertSame(Migrations::FOLDER . '/20261107090807_add_books_isbn.sql', $path);
        $this->assertFileExists($this->root . '/' . $path);
        $this->assertSame(array('20261107090807_add_books_isbn.sql'), array_keys((new Migrations($this->root))->files()));
        $this->expectException(InvalidArgumentException::class);
        (new Migrations($this->root))->make('  !! ');
    }
}
