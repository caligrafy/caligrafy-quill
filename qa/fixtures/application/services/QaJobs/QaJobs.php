<?php
/**
 * Capabilities the scheduled-jobs tests run (qa/tests/cli_tests.php): one that counts its runs, one that takes its
 * time, one that fails
 */

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Service;
use Caligrafy\ServiceError;

#[Capability(name: 'qa_job_tick', description: 'Counts one run of a scheduled job', scope: 'jobs:write')]
class QaJobTick extends Service
{
    public $rules = array('label' => 'max_len,20');

    public function handle(array $input, Context $context)
    {
        Database::execute('CREATE TABLE IF NOT EXISTS qa_job_ticks (id INT UNSIGNED NOT NULL AUTO_INCREMENT, label VARCHAR(20) NULL, actor VARCHAR(20) NOT NULL, actor_id VARCHAR(64) NULL, PRIMARY KEY (id)) ENGINE = InnoDB');
        Database::execute('INSERT INTO qa_job_ticks (label, actor, actor_id) VALUES (?, ?, ?)', array($input['label'] ?? null, $context->actorType(), $context->actorId()));
        return array('ticked' => true);
    }
}

#[Capability(name: 'qa_job_slow', description: 'Takes its time', scope: 'jobs:write')]
class QaJobSlow extends Service
{
    public $rules = array('seconds' => 'integer');

    public function handle(array $input, Context $context)
    {
        sleep((int) ($input['seconds'] ?? 5));
        return array('slept' => true);
    }
}

#[Capability(name: 'qa_job_fail', description: 'Fails on purpose', scope: 'jobs:write')]
class QaJobFail extends Service
{
    public function handle(array $input, Context $context)
    {
        throw ServiceError::conflict('broken on purpose');
    }
}
