<?php

use Caligrafy\Deployer;
use PHPUnit\Framework\TestCase;

/**
 * What `php caligrafer.php deploy` runs in production: which scripts, and which ones are never run twice
 */
final class DeployerTest extends TestCase
{
    public function testOnlyCreateDbScriptsOfModelFoldersInOrder(): void
    {
        $root = sys_get_temp_dir() . '/quill-deployer-' . bin2hex(random_bytes(4));
        foreach (['Loans/create_db.sql', 'Auth/create_db.sql', 'Bookshelf/create_db.sql', 'Bookshelf/seed.sql', 'Bookshelf/Book.php'] as $file) {
            @mkdir(dirname("$root/application/models/$file"), 0777, true);
            file_put_contents("$root/application/models/$file", '-- x');
        }
        $this->assertSame(['application/models/Auth/create_db.sql', 'application/models/Bookshelf/create_db.sql', 'application/models/Loans/create_db.sql'],
            (new Deployer($root))->scripts());
        exec('rm -rf ' . escapeshellarg($root));
    }

    public function testDestructiveScriptsAreRecognized(): void
    {
        $this->assertTrue(Deployer::destructive("DROP TABLE IF EXISTS users;\nCREATE TABLE users (id INT);"));
        $this->assertTrue(Deployer::destructive('delete from books where id > 3'));
        $this->assertTrue(Deployer::destructive('TRUNCATE loans'));
        $this->assertTrue(Deployer::destructive('ALTER TABLE books DROP COLUMN summary'));
        $this->assertFalse(Deployer::destructive("CREATE TABLE IF NOT EXISTS books (id INT, dropped_at DATETIME, deleted TINYINT);"));
        $this->assertFalse(Deployer::destructive("/* DROP TABLE books; was the old way */\n-- DELETE FROM books\nCREATE TABLE IF NOT EXISTS books (id INT);"));
    }
}
