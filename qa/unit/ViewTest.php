<?php

use Caligrafy\View;
use PHPUnit\Framework\TestCase;

/**
 * View::cache() keeps compiled Pug templates between requests, in a private folder of this project and system user
 */
final class ViewTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('ROOT')) define('ROOT', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
    }

    public function testTheCacheFolderIsPrivateToThisProjectAndUser(): void
    {
        $folder = View::cacheFolder();
        $this->assertNotNull($folder);
        $this->assertStringStartsWith(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'caligrafy-views-', $folder);
        $this->assertDirectoryIsWritable($folder);
        $this->assertSame(0700, fileperms($folder) & 0777);
        if (function_exists('posix_geteuid')) $this->assertSame(posix_geteuid(), fileowner($folder));
        $this->assertSame($folder, View::cacheFolder(), 'the same folder on every request');
    }

    public function testRenderingUsesTheCacheFolderAndRefreshesAnEditedView(): void
    {
        $folder = View::cache();
        $this->assertSame(View::cacheFolder(), $folder);
        $this->assertSame($folder, \Phug::getOption('cache_dir'));

        $view = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'caligrafy-view-test-' . getmypid() . '.pug';
        file_put_contents($view, "p first\n");
        $this->assertSame('<p>first</p>', \Phug::renderFile($view));
        touch($view, time() + 2);                       // an edit after the cached copy was written
        file_put_contents($view, "p second\n");
        touch($view, time() + 2);
        $this->assertSame('<p>second</p>', \Phug::renderFile($view));
        unlink($view);
    }
}
