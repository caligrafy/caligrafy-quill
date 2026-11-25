<?php

use Caligrafy\Doctor;
use PHPUnit\Framework\TestCase;

/**
 * Doctor's languages check (2.28.0): a file per language of APP_LOCALES, valid JSON, the default's words everywhere
 */
final class DoctorLanguagesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/quill-lang-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/application/lang', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
        putenv('APP_LOCALES');
        putenv('APP_LOCALE');
    }

    private function write(string $code, $words): void
    {
        file_put_contents("$this->root/application/lang/$code.json", is_string($words) ? $words : json_encode($words));
    }

    private function check(): array
    {
        $doctor = new Doctor($this->root);
        (new ReflectionMethod(Doctor::class, 'languages'))->invoke($doctor);
        return array_map(fn ($r) => "{$r['status']} {$r['name']}: {$r['message']}", $doctor->results());
    }

    public function testNothingWithoutLanguages(): void
    {
        $this->assertSame([], $this->check());
    }

    public function testEveryLanguageHasItsWords(): void
    {
        putenv('APP_LOCALES=en,ar');
        $this->write('en', ['home' => ['title' => 'Hi', 'cta' => 'Go']]);
        $this->write('ar', ['home' => ['title' => 'مرحبا', 'cta' => 'هيا']]);
        $this->assertSame(['OK Languages: en, ar: 2 words each, en by default'], $this->check());
    }

    public function testMissingFileBrokenJsonAndGapsAreWarned(): void
    {
        putenv('APP_LOCALES=en,fr,ar');
        $this->write('en', ['home' => ['title' => 'Hi', 'cta' => 'Go']]);
        $this->write('fr', '{"home": ');
        $this->assertStringContainsString('application/lang/fr.json is not valid JSON', $this->check()[0]);
        $this->assertStringContainsString('application/lang/ar.json is missing', $this->check()[0]);
        $this->write('fr', ['home' => ['title' => 'Salut']]);
        $this->write('ar', ['home' => ['title' => 'مرحبا', 'cta' => 'هيا']]);
        $this->assertSame(['WARN Languages: fr lacks 1 (home.cta)'], $this->check());
    }

    public function testTheDefaultMustBeOneOfThem(): void
    {
        putenv('APP_LOCALES=en,fr');
        putenv('APP_LOCALE=de');
        $this->write('en', ['a' => 'A']);
        $this->write('fr', ['a' => 'A']);
        $this->assertStringStartsWith('WARN Languages: APP_LOCALE=de is not in APP_LOCALES', $this->check()[0]);
    }
}
