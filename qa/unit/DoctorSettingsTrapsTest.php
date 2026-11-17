<?php

use Caligrafy\Doctor;
use PHPUnit\Framework\TestCase;

/**
 * Doctor's settings that silently do nothing (2.26.2): a SESSION_NAME PHP refuses, and a Google tag that never prints
 */
final class DoctorSettingsTrapsTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['SESSION_NAME', 'GOOGLE_ANALYTICS_ID', 'APP_ENV'] as $name) putenv($name);
    }

    private function check(array $env): array
    {
        foreach ($env as $name => $value) putenv("$name=$value");
        $doctor = new Doctor(sys_get_temp_dir());
        (new ReflectionMethod(Doctor::class, 'settingsTraps'))->invoke($doctor);
        return array_map(fn ($r) => "{$r['status']} {$r['name']}: {$r['message']}", $doctor->results());
    }

    public function testNothingIsSaidWhenTheSettingsAreFine(): void
    {
        $this->assertSame([], $this->check(['SESSION_NAME' => '', 'GOOGLE_ANALYTICS_ID' => '', 'APP_ENV' => 'local']));
        $this->assertSame([], $this->check(['SESSION_NAME' => 'cloud_session', 'GOOGLE_ANALYTICS_ID' => 'G-ABC123', 'APP_ENV' => 'production']));
    }

    public function testASessionNamePhpRefusesIsWarned(): void
    {
        foreach (['12345', 'my-app', 'a b', 'app.session'] as $bad) {
            $this->assertStringStartsWith('WARN Session cookie: SESSION_NAME=' . $bad, $this->check(['SESSION_NAME' => $bad])[0] ?? '', $bad);
        }
    }

    public function testAGoogleTagThatNeverPrintsIsWarned(): void
    {
        $this->assertStringContainsString('Universal Analytics', $this->check(['GOOGLE_ANALYTICS_ID' => 'UA-12345-1', 'APP_ENV' => 'production'])[0]);
        $this->assertStringContainsString('only when APP_ENV=production (now local)', $this->check(['GOOGLE_ANALYTICS_ID' => 'G-ABC123', 'APP_ENV' => 'local'])[0]);
    }
}
