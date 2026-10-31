<?php

use Caligrafy\Quickstart;
use PHPUnit\Framework\TestCase;

final class QuickstartTest extends TestCase
{
    private const WEB = "<?php\ntry {\n    Route::get('/', function () {});\n\n    // MUST NOT BE REMOVED - Routes need to be run after being defined\n    Route::run();\n}\ncatch (Exception \$e) {}\n";

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
