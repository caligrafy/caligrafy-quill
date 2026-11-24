<?php

use Caligrafy\Doctor;
use PHPUnit\Framework\TestCase;

/**
 * Doctor's phone apps (2.27.0): an app of php caligrafer.php mobile without the Route::mobile that names it, without
 * Route::capabilities, or whose store builds call plain http
 */
final class DoctorMobileTest extends TestCase
{
    private $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/caligrafy-doctor-mobile-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/application/config/routing', 0755, true);
        mkdir($this->root . '/public/bingo', 0755, true);
        file_put_contents($this->root . '/public/bingo/capacitor.config.json', json_encode(['appId' => 'com.holiday.bingo', 'appName' => 'Bingo', 'webDir' => 'dist']));
        file_put_contents($this->root . '/public/bingo/.env.production', "VITE_API_ROUTE=https://bingo.caligrafy.com\n");
    }

    protected function tearDown(): void
    {
        foreach (['/public/bingo/capacitor.config.json', '/public/bingo/.env.production', '/application/config/routing/web.php'] as $file) @unlink($this->root . $file);
        foreach (['/public/bingo', '/public', '/application/config/routing', '/application/config', '/application', ''] as $folder) @rmdir($this->root . $folder);
    }

    private function check($web): array
    {
        file_put_contents($this->root . '/application/config/routing/web.php', "<?php\n$web\n");
        $doctor = new Doctor($this->root);
        (new ReflectionMethod(Doctor::class, 'mobileApps'))->invoke($doctor);
        return array_map(fn ($r) => "{$r['status']} {$r['name']}: {$r['message']}", $doctor->results());
    }

    public function testAnAppSignedInThroughRouteMobileIsOk(): void
    {
        $this->assertSame(['OK Phone app public/bingo: com.holiday.bingo signs in through Route::mobile'],
            $this->check("Route::capabilities('/capabilities');\nRoute::mobile(['scheme' => 'com.holiday.bingo']);"));
    }

    public function testWhatStopsTheAppIsWarned(): void
    {
        $this->assertStringContainsString('no Route::mobile', $this->check("Route::capabilities('/capabilities');\n// Route::mobile(['scheme' => 'com.holiday.bingo']);")[0]);
        $this->assertStringContainsString('does not name com.holiday.bingo', $this->check("Route::capabilities('/capabilities');\nRoute::mobile(['scheme' => 'com.other.app']);")[0]);
        $this->assertStringContainsString('no Route::capabilities', $this->check("Route::mobile(['scheme' => 'com.holiday.bingo']);")[0]);
        file_put_contents($this->root . '/public/bingo/.env.production', "VITE_API_ROUTE=http://bingo.example.com\n");
        $this->assertStringContainsString('refuse plain http', $this->check("Route::capabilities('/capabilities');\nRoute::mobile(['scheme' => 'com.holiday.bingo']);")[0]);
    }

    public function testAProjectWithoutAnAppHearsNothing(): void
    {
        unlink($this->root . '/public/bingo/capacitor.config.json');
        $this->assertSame([], $this->check("Route::capabilities('/capabilities');"));
    }
}
