<?php

use Caligrafy\Modules;
use PHPUnit\Framework\TestCase;

final class ModulesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/quill-modules-' . bin2hex(random_bytes(4)) . '/public/';
        foreach (array('js/yum', 'js/node_modules/pkg', 'js/vendor', 'js/app/dist', 'css') as $dir) {
            mkdir($this->root . $dir, 0777, true);
        }
        foreach (array('js/capabilities.js', 'js/yum/app.js', 'js/node_modules/pkg/index.js', 'js/vendor/qrcode.js', 'js/app/dist/main.js', 'js/notes.txt', 'css/site.css') as $file) {
            file_put_contents($this->root . $file, '// ' . $file);
        }
        touch($this->root . 'js/capabilities.js', 1700000000);
        touch($this->root . 'js/yum/app.js', 1700000123);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg(dirname($this->root)));
    }

    public function testEveryScriptOfPublicJsGetsAVersionedAddress(): void
    {
        $map = Modules::map('/site/public/', $this->root);
        $this->assertSame(array(
            '/site/public/js/capabilities.js' => '/site/public/js/capabilities.js?v=1700000000',
            '/site/public/js/yum/app.js' => '/site/public/js/yum/app.js?v=1700000123',
        ), $map['imports']);
    }

    public function testBuiltAndThirdPartyFoldersAndOtherFilesAreLeftOut(): void
    {
        $imports = Modules::map('https://example.test/public/', $this->root)['imports'];
        foreach (array_keys($imports) as $url) {
            $this->assertDoesNotMatchRegularExpression('~/(node_modules|vendor|dist)/|\.txt$|\.css$~', $url);
        }
        $this->assertCount(2, $imports);
    }

    public function testThePageOwnEntriesJoinTheSameMap(): void
    {
        $map = Modules::map('/p/', $this->root, array('three' => '/p/js/vendor/three.module.js', '/p/js/capabilities.js' => '/elsewhere/capabilities.js'));
        $this->assertSame('/p/js/vendor/three.module.js', $map['imports']['three']);
        $this->assertSame('/elsewhere/capabilities.js', $map['imports']['/p/js/capabilities.js']);   // the page's own entry wins
        $this->assertSame('/p/js/yum/app.js?v=1700000123', $map['imports']['/p/js/yum/app.js']);
        // only on that page: the next map has the versioned address again
        $this->assertSame('/p/js/capabilities.js?v=1700000000', Modules::map('/p/', $this->root)['imports']['/p/js/capabilities.js']);
        $this->assertSame(1, substr_count(Modules::tag('/p/', $this->root, array('three' => '/t.js')), '<script type="importmap">'));
    }

    public function testTheTagIsAnImportMapAndNothingWithoutScripts(): void
    {
        $tag = Modules::tag('/p/', $this->root);
        $this->assertStringStartsWith('<script type="importmap">{"imports":{"/p/js/capabilities.js":"/p/js/capabilities.js?v=1700000000"', $tag);
        $this->assertStringEndsWith('</script>', $tag);
        $empty = sys_get_temp_dir() . '/quill-modules-empty-' . bin2hex(random_bytes(4)) . '/';
        mkdir($empty);
        $this->assertSame('', Modules::tag('/p/', $empty));
        rmdir($empty);
    }
}
