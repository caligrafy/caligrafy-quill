# Background jobs: capabilities run later

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

A **background job** runs one of your capabilities later, outside the request: the page answers at once, and the slow
part (an email, an AI call, a report, resizing images) happens a moment after, with retries when it fails. Since
2.18.0.

## Pushing a job

From a capability, a controller or a scheduled job:

```php
use Caligrafy\Queue;

Queue::push('send_welcome_email', ['user_id' => $id]);
Queue::push('build_report', ['month' => '2026-11'], ['delay' => 600, 'attempts' => 5, 'timeout' => 120]);
```

| Option | Default | What it does |
|---|---|---|
| `delay` | `0` | seconds before the job may start |
| `attempts` | `3` | how many times it is tried (1 to 20) |
| `timeout` | `60` | seconds a run may take before it is stopped (900 at most) |
| `queue` | `default` | the queue's name, for a worker of its own (`queue work --queue <name>`; `queue_work` takes a `queue` input too) |

The job is the capability's name and its input, kept in the `caligrafy_jobs` table (the deploy step and
`php caligrafer.php platform install` create it). The capability must exist when you push.

**Who it runs as.** A job runs with full access, like a scheduled job (`Context::system`: the audit log records
`queue` and the job). Check who may ask for the work before you push it, and pass ids rather than trusting them later.

## Running the jobs

**With the scheduler** (Caligrafy Cloud, and `php caligrafer.php server start` on your computer): add one job to
`application/config/schedule.php`, on the framework's capability `queue_work`:

```php
array('name' => 'queue', 'capability' => 'queue_work', 'every' => '1m', 'timeout' => 55),
```

Every minute it runs the jobs that are due for up to 50 seconds, so a job starts within a minute, and has at most what
is left of that minute.

**With a worker of its own** (your own server, longer jobs):

```bash
php caligrafer.php queue work                 # runs jobs as they come (Ctrl+C stops it)
php caligrafer.php queue work --once          # runs what is due, then stops
php caligrafer.php queue work --for 300       # for 5 minutes
php caligrafer.php queue work --queue reports --sleep 3   # another queue; seconds between looks when empty
```

Each job runs in its own process, stopped at its timeout. Run one way or the other, not both.

## Failures and retries

+ A run that throws or times out is tried again after 30 seconds, then 1 minute, 2 minutes... until its attempts are
  used; then the job is `failed`.
+ A refusal of the capability (`invalid`, `not_found`, `forbidden`, `unauthenticated`) fails at once: it would fail again.
+ A worker that stopped in the middle of a job (a restart) leaves it `running`; the next worker puts it back once its
  timeout and one more minute have passed.

```bash
php caligrafer.php queue status              # pending, running, done, failed
php caligrafer.php queue list                # the latest jobs, with their last error
php caligrafer.php queue failed              # only the failed ones
php caligrafer.php queue retry 42            # or: queue retry all
```

Finished jobs are kept 7 days.

## Testing

In a test, `$this->assertQueued('send_welcome_email')` checks that a job was pushed, and returns its input
([Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App)). Run the job itself with
`$this->call('send_welcome_email', $input)`.
