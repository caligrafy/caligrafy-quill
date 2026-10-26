<?php

use Caligrafy\Doctor;
use PHPUnit\Framework\TestCase;

final class DoctorTest extends TestCase
{
    public function testMaskShowsPrefixAndLastFourCharactersOnly(): void
    {
        $key = 'sk-proj-' . str_repeat('a', 40) . 'WXYZ';
        $this->assertSame('sk-proj-…WXYZ (52 chars)', Doctor::mask($key));
        $this->assertSame('…7890 (20 chars)', Doctor::mask('abcdefghij1234567890'));
        $this->assertSame('(5 chars)', Doctor::mask('short'));
    }

    public function testWellFormedKeyIsOk(): void
    {
        [$result] = $this->secret('sk-proj-' . str_repeat('a', 40));
        $this->assertSame('OK', $result['status']);
        $this->assertStringNotContainsString(str_repeat('a', 8), $result['message']);
    }

    public function testLeadingSpaceIsWarned(): void
    {
        [$result] = $this->secret(' sk-proj-' . str_repeat('a', 40));
        $this->assertSame('WARN', $result['status']);
        $this->assertStringContainsString('leading or trailing spaces', $result['message']);
    }

    public function testMissingDashAfterPrefixIsWarned(): void
    {
        [$result] = $this->secret('sk-proj' . str_repeat('a', 40));
        $this->assertSame('WARN', $result['status']);
        $this->assertStringContainsString("missing '-'", $result['message']);
        $this->assertStringNotContainsString(str_repeat('a', 8), $result['message']);
    }

    /**
     * secret() is private: it records its result, read back with results()
     */
    private function secret(string $value): array
    {
        $doctor = new Doctor(sys_get_temp_dir());
        (new ReflectionMethod(Doctor::class, 'secret'))->invoke($doctor, 'openai', 'OPENAI_API_KEY', $value);
        return $doctor->results();
    }
}
