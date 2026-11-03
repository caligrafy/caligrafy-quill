<?php

use Caligrafy\Context;
use Caligrafy\Scheduler;
use PHPUnit\Framework\TestCase;

/**
 * Scheduled jobs: the declaration of application/config/schedule.php, cron, the listing Caligrafy Cloud reads and the
 * context a job runs in
 */
final class SchedulerTest extends TestCase
{
    private function exists(): callable
    {
        return function ($name) { return in_array($name, ['check_prices', 'send_digest'], true); };
    }

    public function testTheSpecExampleIsValidAndCompleted(): void
    {
        $jobs = Scheduler::validate([
            ['name' => 'check-prices', 'capability' => 'check_prices', 'input' => ['due_only' => true], 'every' => '1m'],
            ['name' => 'daily-digest', 'capability' => 'send_digest', 'cron' => '0 8 * * *', 'timezone' => 'America/New_York', 'timeout' => 120],
        ], $this->exists());
        $this->assertSame('* * * * *', $jobs[0]['cron']);
        $this->assertSame('UTC', $jobs[0]['timezone']);
        $this->assertSame(55, $jobs[0]['timeout']);
        $this->assertTrue($jobs[0]['enabled']);
        $this->assertSame(['due_only' => true], $jobs[0]['input']);
        $this->assertSame('America/New_York', $jobs[1]['timezone']);
        $this->assertSame(120, $jobs[1]['timeout']);
    }

    public function testEveryProblemIsNamed(): void
    {
        try {
            Scheduler::validate([
                ['name' => 'Bad Name', 'capability' => 'check_prices', 'every' => '1m'],
                ['name' => 'twice', 'capability' => 'nope', 'every' => '2m'],
                ['name' => 'twice', 'capability' => 'send_digest', 'every' => '1h', 'cron' => '* * * * *'],
                ['name' => 'tz', 'capability' => 'send_digest', 'cron' => '0 8 * * *', 'timezone' => 'Mars/Base', 'timeout' => 900, 'enabled' => 'yes', 'when' => 'now'],
                ['name' => 'cron', 'capability' => 'send_digest', 'cron' => '61 * * * *'],
            ], $this->exists());
            $this->fail('expected the file to be invalid');
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
            foreach (["'name' must be a slug", "there is no capability 'nope'", "'every' must be one of", 'the name is used twice', "give 'every' or 'cron'",
                "'timezone' must be an IANA name", "'timeout' must be a number", "'enabled' must be true or false", "unknown field 'when'", 'out of range'] as $expected) {
                $this->assertStringContainsString($expected, $message);
            }
        }
    }

    public function testTheListingIsVersionedAndLeavesOutDisabledJobs(): void
    {
        $jobs = Scheduler::validate([
            ['name' => 'check-prices', 'capability' => 'check_prices', 'every' => '1m'],
            ['name' => 'off', 'capability' => 'send_digest', 'every' => '1d', 'enabled' => false],
        ]);
        $this->assertSame(['version' => 1, 'jobs' => [[
            'name' => 'check-prices', 'cron' => '* * * * *', 'timezone' => 'UTC', 'timeout' => 55,
            'command' => ['php', 'caligrafer.php', 'schedule', 'run', 'check-prices'],
        ]]], Scheduler::listing($jobs));
    }

    public function testEveryRhythmBecomesACron(): void
    {
        foreach (Scheduler::EVERY as $every => $cron) {
            Scheduler::parse($cron);
            $this->assertSame($cron, Scheduler::validate([['name' => 'j', 'capability' => 'x', 'every' => $every]])[0]['cron']);
        }
    }

    public function testCronFields(): void
    {
        $this->assertSame([0, 15, 30, 45], Scheduler::parse('*/15 * * * *')[0]);
        $this->assertSame([9, 10, 11, 12, 13, 14, 15, 16, 17], Scheduler::parse('0 9-17 * * *')[1]);
        $this->assertSame([1, 3, 5], Scheduler::parse('0 0 * * mon,wed,fri')[4]);
        $this->assertSame([0], Scheduler::parse('0 0 * * 7')[4]);
        $this->assertSame([1, 4, 7, 10], Scheduler::parse('0 0 1 jan-dec/3 *')[3]);
        foreach (['* * * *', '* * * * * *', '*/0 * * * *', '0 24 * * *', '0 0 32 * *', '0 0 * 13 *', '5-1 * * * *', 'a * * * *'] as $bad) {
            try {
                Scheduler::parse($bad);
                $this->fail("expected '$bad' to be refused");
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testDueReadsTheTimezoneAndTheDayRules(): void
    {
        $at = new DateTimeImmutable('2026-11-03 13:00:00', new DateTimeZone('UTC'));   // 08:00 in New York, a Tuesday
        $this->assertTrue(Scheduler::isDue('0 8 * * *', $at, 'America/New_York'));
        $this->assertFalse(Scheduler::isDue('0 8 * * *', $at, 'UTC'));
        $this->assertTrue(Scheduler::isDue('* * * * *', $at));
        $this->assertFalse(Scheduler::isDue('*/7 * * * *', $at->modify('+1 minute')));
        // day of the month or day of the week, when both are restricted
        $this->assertTrue(Scheduler::isDue('0 13 15 * 2', $at));
        $this->assertTrue(Scheduler::isDue('0 13 3 * 5', $at));
        $this->assertFalse(Scheduler::isDue('0 13 15 * 5', $at));
        // only the day of the week restricted
        $this->assertFalse(Scheduler::isDue('0 13 * * 1', $at));
    }

    public function testAJobRunsAsTheSchedulerWithFullAccess(): void
    {
        $context = Context::system('schedule', 'check-prices');
        $this->assertTrue($context->allows('anything:write', 9));
        $this->assertSame('cli', $context->channel);
        $this->assertSame('schedule', $context->actorType());
        $this->assertSame('check-prices', $context->actorId());
        $this->assertNull(Context::cli()->actorId());
    }

    public function testNoFileMeansNoJobs(): void
    {
        $this->assertSame([], (new Scheduler(sys_get_temp_dir() . '/quill-no-schedule-' . bin2hex(random_bytes(4))))->jobs(false));
    }
}
