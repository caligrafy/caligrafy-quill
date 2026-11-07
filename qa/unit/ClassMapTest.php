<?php

use Caligrafy\ClassMap;
use PHPUnit\Framework\TestCase;

/**
 * ClassMap: what a file declares, a class loaded only when used, files that must run at start, a new class found at once
 */
final class ClassMapTest extends TestCase
{
    public function testDeclarationsFindNamespacedClassesInterfacesTraitsAndEnums(): void
    {
        $info = ClassMap::declarations("<?php\nnamespace App\\Books;\nuse Caligrafy\\{Service, Context};\n#[Attr(['a' => 1])]\nfinal class Shelf extends Service implements A { public function f() { \$x = \"{\$y}\"; if (1) {} } }\n"
            . "interface Readable {}\ntrait Tidy {}\nenum Size: string { case S = 's'; }\nabstract class Base {}\n");
        $this->assertSame(array('App\\Books\\Shelf', 'App\\Books\\Readable', 'App\\Books\\Tidy', 'App\\Books\\Size', 'App\\Books\\Base'), $info['classes']);
        $this->assertFalse($info['eager']);
    }

    public function testAFileThatDoesMoreThanDeclareClassesRunsAtStart(): void
    {
        $this->assertTrue(ClassMap::declarations("<?php\nfunction helper() {}\n")['eager'], 'a function');
        $this->assertTrue(ClassMap::declarations("<?php\nclass A {}\nCapabilities::register(A::class);\n")['eager'], 'code');
        $this->assertTrue(ClassMap::declarations("<?php\nif (!class_exists('B')) { class B {} }\n")['eager'], 'a conditional class');
        $this->assertSame(array(), ClassMap::declarations("<?php\nif (!class_exists('B')) { class B {} }\n")['classes']);
        $this->assertTrue(ClassMap::declarations("<?php\ndeclare(strict_types=1);\n/** doc */\nclass C { const X = C::class; }\n\$o = null;")['eager'], 'a top-level statement');
        $this->assertFalse(ClassMap::declarations("<?php\ndeclare(strict_types=1);\nclass D { function f() { return new class {}; } }\n")['eager']);
    }

    public function testBracedNamespaces(): void
    {
        $info = ClassMap::declarations("<?php\nnamespace A { class One {} }\nnamespace B\\C { class Two {} }\n");
        $this->assertSame(array('A\\One', 'B\\C\\Two'), $info['classes']);
        $this->assertFalse($info['eager']);
    }

    public function testClassesLoadWhenUsedAndANewOneIsFoundAtOnce(): void
    {
        $dir = sys_get_temp_dir() . '/caligrafy-classmap-' . getmypid() . '-' . mt_rand();
        mkdir("$dir/app/models", 0777, true);
        mkdir("$dir/app/views", 0777, true);
        $suffix = 'T' . mt_rand();
        file_put_contents("$dir/app/models/Book.php", "<?php\nclass Book$suffix { const OK = 1; }\n");
        file_put_contents("$dir/app/models/helpers.php", "<?php\nfunction helper_$suffix() { return 2; }\n");
        file_put_contents("$dir/app/views/Skip.php", "<?php\nclass Skipped$suffix {}\n");
        file_put_contents("$dir/app/models/Svc.php", "<?php\n#[Caligrafy\\Capability('x')]\nclass Svc$suffix {}\n");

        ClassMap::register(array("$dir/app"), array("$dir/app/views"));
        $this->assertTrue(function_exists("helper_$suffix"), 'a file with functions is loaded at start');
        $this->assertFalse(class_exists("Book$suffix", false), 'not loaded before it is used');
        $this->assertSame(1, constant("Book$suffix::OK"));
        $this->assertFalse(class_exists("Skipped$suffix"), 'views are left out');
        $this->assertSame(array("$dir/app/models/Svc.php"), ClassMap::capabilityFiles());

        // a class added after the map was built
        sleep(1);
        file_put_contents("$dir/app/models/Author.php", "<?php\nnamespace Lib$suffix;\nclass Author {}\n");
        ClassMap::register(array("$dir/app"), array("$dir/app/views"));   // a new request: the saved map is read
        $this->assertTrue(class_exists("Lib$suffix\\Author"));

        foreach (array('models/Book.php', 'models/helpers.php', 'models/Svc.php', 'models/Author.php', 'views/Skip.php') as $f) unlink("$dir/app/$f");
        rmdir("$dir/app/models"); rmdir("$dir/app/views"); rmdir("$dir/app"); rmdir($dir);
    }

    public function testTheTemporaryFolderIsPrivate(): void
    {
        $folder = ClassMap::tempFolder('classes');
        $this->assertNotNull($folder);
        $this->assertSame(0700, fileperms($folder) & 0777);
        $this->assertStringContainsString('caligrafy-classes-', $folder);
    }
}
