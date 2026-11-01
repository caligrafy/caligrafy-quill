<?php

use Caligrafy\Quickstart;
use PHPUnit\Framework\TestCase;

final class QuickstartTest extends TestCase
{
    private const WEB = "<?php\ntry {\n    Route::get('/', function () {});\n\n    // MUST NOT BE REMOVED - Routes need to be run after being defined\n    Route::run();\n}\ncatch (Exception \$e) {}\n";

    private const STACKS = [
        ['name' => 'MAMP', 'root' => '/nowhere/MAMP/htdocs', 'url' => 'http://localhost:8888', 'db' => ['host' => '127.0.0.1', 'port' => '8889', 'user' => 'root', 'password' => 'root']],
        ['name' => 'XAMPP', 'root' => 'C:/xampp/htdocs', 'url' => 'http://localhost', 'db' => ['host' => '127.0.0.1', 'port' => '3306', 'user' => 'root', 'password' => '']],
    ];

    public function testLocalSiteGivesTheAddressAndAppRootInsideAWebFolder(): void
    {
        $site = Quickstart::localSite('/nowhere/MAMP/htdocs/myapp', self::STACKS);
        $this->assertSame(['MAMP', 'http://localhost:8888/myapp', 'myapp', '8889'], [$site['name'], $site['url'], $site['appRoot'], $site['db']['port']]);
        $nested = Quickstart::localSite('/nowhere/MAMP/htdocs/clients/acme', self::STACKS);
        $this->assertSame(['http://localhost:8888/clients/acme', 'clients/acme'], [$nested['url'], $nested['appRoot']]);
        $this->assertSame(['http://localhost:8888', ''], array_values(array_intersect_key(Quickstart::localSite('/nowhere/MAMP/htdocs', self::STACKS), ['url' => 1, 'appRoot' => 1])));
    }

    public function testStacksReadTheRealPortsOfMamp(): void
    {
        $mamp = sys_get_temp_dir() . '/mamp-' . bin2hex(random_bytes(4));
        @mkdir("$mamp/conf/apache", 0777, true);
        @mkdir("$mamp/bin", 0777, true);
        @mkdir("$mamp/htdocs/myapp", 0777, true);
        file_put_contents("$mamp/conf/apache/httpd.conf", "# Listen 8888\nListen 80\n");
        file_put_contents("$mamp/bin/startMysql.sh", "mysqld_safe --port=3306 --socket=x &\n");
        $site = Quickstart::localSite(realpath("$mamp/htdocs/myapp"), Quickstart::stacks($mamp));
        $this->assertSame(['MAMP', 'http://localhost/myapp', '3306'], [$site['name'], $site['url'], $site['db']['port']]);
        file_put_contents("$mamp/conf/apache/httpd.conf", "Listen 8888\n");
        $this->assertSame('http://localhost:8888/myapp', Quickstart::localSite(realpath("$mamp/htdocs/myapp"), Quickstart::stacks($mamp))['url']);
        // a MAMP set to serve another folder (DocumentRoot)
        @mkdir("$mamp/sites/clients/acme", 0777, true);
        file_put_contents("$mamp/conf/apache/httpd.conf", "Listen 80\n# DocumentRoot \"/nope\"\nDocumentRoot \"" . realpath("$mamp/sites") . "\"\n");
        $site = Quickstart::localSite(realpath("$mamp/sites/clients/acme"), Quickstart::stacks($mamp));
        $this->assertSame(['http://localhost/clients/acme', 'clients/acme'], [$site['url'], $site['appRoot']]);
        $this->assertNull(Quickstart::localSite(realpath("$mamp/htdocs/myapp"), Quickstart::stacks($mamp)), 'htdocs is not served any more');
        exec('rm -rf ' . escapeshellarg($mamp));
    }

    public function testLocalSiteMatchesWindowsPathsWithoutCaseAndIgnoresOtherFolders(): void
    {
        $site = Quickstart::localSite('c:\\xampp\\htdocs\\MyApp', self::STACKS);
        $this->assertSame(['XAMPP', 'http://localhost/MyApp', 'MyApp'], [$site['name'], $site['url'], $site['appRoot']]);
        $this->assertNull(Quickstart::localSite('/nowhere/MAMP/htdocs-old/myapp', self::STACKS), 'a sibling folder is not inside the web folder');
        $this->assertNull(Quickstart::localSite('/Users/me/code/myapp', self::STACKS));
    }

    public function testWithoutATerminalTheDatabaseSettingsAreNeverWrittenUnlessAsked(): void
    {
        $dir = sys_get_temp_dir() . '/qs-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $lines = [];
        $quickstart = new class($dir, function ($line) use (&$lines) { $lines[] = $line; }) extends Quickstart {
            protected function interactive() { return false; }
        };
        $method = new ReflectionMethod(Quickstart::class, 'localServer');
        $env = "DB_ACTIVATE=false\nDB_HOST=localhost\nDB_DATABASE=databasename\n";
        [$after] = $method->invoke($quickstart, $env, []);
        $this->assertSame('false', Quickstart::envValue($after, 'DB_ACTIVATE'), 'no one confirmed: the database stays off');
        $this->assertStringContainsString('SKIP  Database: no terminal to confirm', implode("\n", $lines));
        [$after] = $method->invoke($quickstart, $env, ['dbDefaults' => true]);
        $this->assertSame(['true', '127.0.0.1', '3306'], [Quickstart::envValue($after, 'DB_ACTIVATE'), Quickstart::envValue($after, 'DB_HOST'), Quickstart::envValue($after, 'DB_PORT')]);
        rmdir($dir);
    }

    public function testAddRoutesGoesAboveRouteRunAndItsComment(): void
    {
        $out = Quickstart::addRoutes(self::WEB, ["Route::mcp('/mcp');   // agents"], 'BUILD ONCE');
        $this->assertStringContainsString("    // BUILD ONCE\n    Route::mcp('/mcp');   // agents\n\n    // MUST NOT BE REMOVED", $out);
        $this->assertLessThan(strpos($out, 'Route::run();'), strpos($out, "Route::mcp('/mcp');"));
    }

    public function testAddRoutesIsIdempotentAndAddsOnlyWhatIsMissing(): void
    {
        $once = Quickstart::addRoutes(self::WEB, ["Route::mcp('/mcp');", "Route::capabilities('/capabilities');"], 'A');
        $this->assertSame($once, Quickstart::addRoutes($once, ["Route::mcp('/mcp');", "Route::capabilities('/capabilities');"], 'A'));
        $mine = str_replace("Route::get('/'", "Route::mcp( '/mcp' );\n    Route::get('/'", self::WEB);
        $out = Quickstart::addRoutes($mine, ["Route::mcp('/mcp');", "Route::capabilities('/capabilities');"], 'B');
        $this->assertSame(1, substr_count($out, 'Route::mcp('));
        $this->assertStringContainsString("Route::capabilities('/capabilities');", $out);
    }

    public function testAddRoutesNeedsRouteRun(): void
    {
        $this->expectException(RuntimeException::class);
        Quickstart::addRoutes("<?php\n", ["Route::mcp('/mcp');"], 'X');
    }

    public function testEnvSetAndValue(): void
    {
        $env = "APP_KEY=abc\nDB_ACTIVATE=false\nDB_HOST=localhost  # use 'mysql' for Docker\n";
        $out = Quickstart::envSet($env, ['DB_ACTIVATE' => 'true', 'DB_HOST' => 'mysql', 'DB_DATABASE' => 'caligrafy']);
        $this->assertSame("APP_KEY=abc\nDB_ACTIVATE=true\nDB_HOST=mysql\nDB_DATABASE=caligrafy\n", $out);
        $this->assertSame('localhost', Quickstart::envValue($env, 'DB_HOST'));
        $this->assertSame('', Quickstart::envValue($env, 'NOPE'));
        $this->assertSame('a$b', Quickstart::envValue(Quickstart::envSet('X=1', ['X' => 'a$b']), 'X'));
    }
}
