<?php

use Caligrafy\Capabilities;
use Caligrafy\ClassMap;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * The cache of the capability registry (2.26.1): a call by name reads it instead of finding and reflecting every service,
 * and it is out of date as soon as the class map is. Each test runs in its own process: it defines APP_PATH.
 */
final class CapabilityCacheTest extends TestCase
{
    private string $dir;
    private string $suffix;

    private function project(): void
    {
        $this->dir = sys_get_temp_dir() . '/caligrafy-capcache-' . getmypid() . '-' . mt_rand();
        $this->suffix = 'C' . mt_rand();
        mkdir("$this->dir/application/services/Books", 0777, true);
        define('APP_PATH', "$this->dir/application/");
        $this->service('ListBooks', 'cached_list_books', 'The books');
    }

    private function service(string $class, string $name, string $description): void
    {
        file_put_contents("$this->dir/application/services/Books/$class.php", "<?php\nuse Caligrafy\\Capability;\nuse Caligrafy\\Context;\nuse Caligrafy\\Service;\n\n"
            . "#[Capability(name: '$name', description: '$description', readOnly: true, public: true, cache: 30)]\n"
            . "class $class$this->suffix extends Service\n{\n    public function handle(array \$input, Context \$context) { return array('ok' => true); }\n}\n");
    }

    // a new request: the class map and the registry start again from their files
    private function request(): void
    {
        Capabilities::reset();
        ClassMap::register(array(APP_PATH));
    }

    private function registryBuilt(): bool
    {
        return (new ReflectionProperty(Capabilities::class, 'registry'))->getValue() !== null;
    }

    private function cacheFile(): ?string
    {
        return (new ReflectionMethod(Capabilities::class, 'cacheFile'))->invoke(null);
    }

    protected function tearDown(): void
    {
        if (isset($this->dir)) {
            $file = ClassMap::registered() ? $this->cacheFile() : null;
            if ($file !== null) {
                @unlink($file);
            }
            foreach (glob("$this->dir/application/services/Books/*.php") ?: array() as $f) unlink($f);
            @rmdir("$this->dir/application/services/Books"); @rmdir("$this->dir/application/services"); @rmdir("$this->dir/application"); @rmdir($this->dir);
        }
    }

    #[RunInSeparateProcess]
    public function testACallByNameReadsTheCacheOnceTheRegistryWasBuilt(): void
    {
        $this->project();
        $this->request();
        $first = Capabilities::get('cached_list_books');
        $this->assertSame("ListBooks$this->suffix", $first['class']);
        $this->assertTrue($this->registryBuilt(), 'no cache yet: the registry is built');
        $this->assertFileExists($this->cacheFile());
        $names = array_keys(Capabilities::all());

        $this->request();
        $entry = Capabilities::get('cached_list_books');
        $this->assertFalse($this->registryBuilt(), 'the cache answered: no service was reflected');
        $this->assertSame($first['class'], $entry['class']);
        $this->assertEquals($first['capability'], $entry['capability']);
        $this->assertSame(30, $entry['capability']->cache);
        // the framework's own capabilities are in the cache too
        $this->assertSame('CaligrafyNotifyStatus', Capabilities::get('notify_status')['class'] ?? null);
        $this->assertFalse($this->registryBuilt());

        // the whole registry is the same as before
        $this->request();
        $this->assertSame($names, array_keys(Capabilities::all()));
    }

    #[RunInSeparateProcess]
    public function testAFileAddedOrEditedMakesTheCacheOutOfDate(): void
    {
        $this->project();
        $this->request();
        Capabilities::get('cached_list_books');

        // a capability added: the class map changes, so the cache is not used, and the new one is found at once
        sleep(1);
        $this->service('AddBook', 'cached_add_book', 'Adds a book');
        $this->request();
        $this->assertSame("AddBook$this->suffix", Capabilities::get('cached_add_book')['class']);
        $this->assertTrue($this->registryBuilt());

        $this->request();
        Capabilities::get('cached_add_book');
        $this->assertFalse($this->registryBuilt(), 'the cache was written again');

        // a file edited
        touch(APP_PATH . 'services/Books/AddBook.php', time() + 5);
        $this->request();
        Capabilities::get('cached_list_books');
        $this->assertTrue($this->registryBuilt());
    }

    #[RunInSeparateProcess]
    public function testANameTheCacheDoesNotHaveBuildsTheRegistry(): void
    {
        $this->project();
        $this->request();
        Capabilities::get('cached_list_books');

        // a service declared outside the capability files (here in the test) is still found, as before
        $this->request();
        $this->assertSame('QaListNotes', Capabilities::get('qa_list_notes')['class']);
        $this->assertTrue($this->registryBuilt());
        $this->assertArrayNotHasKey('qa_list_notes', (new ReflectionMethod(Capabilities::class, 'cache'))->invoke(null)['entries'], 'only the files the registry loads are cached');

        // an unknown name too (404)
        $this->request();
        $this->assertNull(Capabilities::get('no_such_capability'));
        $this->assertTrue($this->registryBuilt());

        // a class registered by hand: the registry, as before
        $this->request();
        Capabilities::register('QaEchoNote');
        $this->assertSame("ListBooks$this->suffix", Capabilities::get('cached_list_books')['class']);
        $this->assertTrue($this->registryBuilt());
    }

    #[RunInSeparateProcess]
    public function testEachRequestInItsOwnProcessReadsTheCacheAndItsServiceCanExtendAFrameworkOne(): void
    {
        $this->project();
        file_put_contents(APP_PATH . "services/Books/MyNotes.php", "<?php\nuse Caligrafy\\Capability;\nuse Caligrafy\\Context;\n\n"
            . "#[Capability(name: 'cached_my_notes', description: 'Mine', readOnly: true)]\n"
            . "class MyNotes$this->suffix extends CaligrafyNotifyService\n{\n    public function handle(array \$input, Context \$context) { return 'mine'; }\n}\n");
        $script = "$this->dir/request.php";
        file_put_contents($script, "<?php\nrequire " . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ";\n"
            . "foreach (array('ClassMap', 'Capability', 'Service', 'ServiceError', 'Context', 'HttpCache', 'Capabilities') as \$c) require_once " . var_export(dirname(__DIR__, 2) . '/framework/core/', true) . " . \"\$c.php\";\n"
            . "define('APP_PATH', " . var_export(APP_PATH, true) . ");\n"
            . "Caligrafy\\ClassMap::register(array(APP_PATH));\n"
            . "\$entry = Caligrafy\\Capabilities::get('cached_my_notes');\n"
            . "\$built = (new ReflectionProperty(Caligrafy\\Capabilities::class, 'registry'))->getValue() !== null;\n"
            . "echo \$entry['class'], ' ', \$built ? 'built' : 'cached', ' ', (new \$entry['class']())->handle(array(), Caligrafy\\Context::cli());\n");
        try {
            $this->assertSame("MyNotes$this->suffix built mine", shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1'));
            $this->assertSame("MyNotes$this->suffix cached mine", shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1'));
        } finally {
            unlink($script);
            ClassMap::register(array(APP_PATH));   // the same cache file, for tearDown
        }
    }

    #[RunInSeparateProcess]
    public function testWithoutTheClassMapThereIsNoCache(): void
    {
        $this->project();
        $this->assertFalse(ClassMap::registered());
        Capabilities::reset();
        $this->assertSame("ListBooks$this->suffix", Capabilities::get('cached_list_books')['class']);
        $this->assertNull($this->cacheFile());
        Capabilities::reset();
        Capabilities::get('cached_list_books');
        $this->assertTrue($this->registryBuilt());
    }
}
