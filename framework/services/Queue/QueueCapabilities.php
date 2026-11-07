<?php
/**
 * The queue's worker as a capability (2.18.0), for the scheduler: a job of application/config/schedule.php runs the
 * queue every minute, where no worker process of its own can run (Caligrafy Cloud runs scheduled jobs):
 *
 *     ['name' => 'queue', 'capability' => 'queue_work', 'every' => '1m', 'timeout' => 55],
 *
 * Only the scheduler and the CLI may call it, never a page, REST or an agent.
 */

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Queue;
use Caligrafy\Service;
use Caligrafy\ServiceError;

#[Capability(name: 'queue_work', description: 'Runs the background jobs that are due (Caligrafy\\Queue) for up to 50 seconds. For the scheduler (application/config/schedule.php).', scope: 'caligrafy:queue', mcp: false)]
class CaligrafyQueueWork extends Service
{
    public $rules = array('queue' => 'alpha_dash|max_len,40', 'seconds' => 'integer|min_numeric,1|max_numeric,290');
    public $descriptions = array('queue' => 'The queue to run (default)', 'seconds' => 'How long to start jobs for (50 by default)');

    public function handle(array $input, Context $context)
    {
        if (!in_array($context->actorType(), array('schedule', 'cli'), true)) {
            throw ServiceError::forbidden('Only the scheduler and the command line run the queue');
        }
        return Queue::work(array('queue' => $input['queue'] ?? 'default', 'seconds' => (int) ($input['seconds'] ?? 50), 'once' => false, 'sleep' => 2));
    }
}
