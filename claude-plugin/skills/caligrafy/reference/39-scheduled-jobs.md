# Scheduled jobs: capabilities on a timer

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

A **scheduled job** runs one of your capabilities on a timer, with no page open: check prices every minute, send a
digest every morning, clean old rows every night. You declare the jobs in one file; Caligrafy runs them on your
computer while you develop, and on Caligrafy Cloud in production. Since 2.14.0.

## Declaring the jobs

**`application/config/schedule.php`** returns a list of jobs. No file means no jobs.

```php
<?php
return array(
    array('name' => 'check-prices', 'capability' => 'check_prices', 'input' => array('due_only' => true), 'every' => '1m'),
    array('name' => 'daily-digest', 'capability' => 'send_digest', 'cron' => '0 8 * * *', 'timezone' => 'America/New_York', 'timeout' => 120),
);
```

| Field | Required | Rule |
|---|---|---|
| `name` | yes | A slug (`a-z`, `0-9`, `-`), at most 40 characters, unique in the file |
| `capability` | yes | A capability of `application/services/` |
| `input` | no | Its inputs: an array that encodes to JSON, checked by the capability's own rules when it runs |
| `every` | `every` or `cron` | `1m`, `5m`, `10m`, `15m`, `30m`, `1h`, `6h`, `12h` or `1d` (in UTC) |
| `cron` | `every` or `cron` | Five fields: minute, hour, day of the month, month, day of the week (`*/15 * * * *`, `0 9-17 * * mon-fri`) |
| `timezone` | no | An IANA name such as `Europe/Paris`, for `cron` only; `UTC` by default |
| `timeout` | no | Seconds, 55 by default, at most 300 |
| `enabled` | no | `true` by default; `false` keeps the job declared but never run |

A job is just a capability: write it as any other ([Build once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP)),
then name it in the file. Its inputs are validated, and the audit log records each call.

## Example: clear the finished tasks every night

The [Tasks example](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart) (`php caligrafer.php quickstart
--example`) keeps every task forever. This job deletes the finished ones that nobody touched for a week, every night at
3:00 in New York.

**1. The capability.** Create it:

```bash
php caligrafer.php service Tasks/ClearDoneTasks --scope tasks:write
```

Then write it in **`application/services/Tasks/ClearDoneTasks.php`**:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Service;

#[Capability(
    name: 'clear_done_tasks',
    description: 'Deletes the finished tasks not changed for a number of days (7 by default). Returns how many were deleted.',
    scope: 'tasks:write',
    destructive: true
)]
class ClearDoneTasks extends Service
{
    public $rules = array('days' => 'integer|min_numeric,1|max_numeric,365');
    public $descriptions = array('days' => 'Finished tasks older than this many days are deleted (7 by default)');

    public function handle(array $input, Context $context)
    {
        $before = date('Y-m-d H:i:s', time() - (int) ($input['days'] ?? 7) * 86400);
        $count = Database::toArray(Database::execute('SELECT COUNT(*) AS n FROM tasks WHERE done = 1 AND modified_at < ?', array($before)));
        Database::execute('DELETE FROM tasks WHERE done = 1 AND modified_at < ?', array($before));
        return array('deleted' => (int) $count[0]['n']);
    }
}
```

It works for everyone's tasks, not for a signed-in user: a job has nobody signed in. And it is still an ordinary
capability: a page, the REST API or an AI agent can call it too, with the scope `tasks:write`.

**2. The schedule.** Create **`application/config/schedule.php`**:

```php
<?php
return array(
    array('name' => 'clear-done-tasks', 'capability' => 'clear_done_tasks', 'input' => array('days' => 7),
        'cron' => '0 3 * * *', 'timezone' => 'America/New_York'),
);
```

Every 15 minutes instead: `'every' => '15m'` in place of `cron` and `timezone`.

**3. Check it and run it once.**

```bash
php caligrafer.php schedule list                    # checks the file: clear-done-tasks  0 3 * * *  clear_done_tasks  America/New_York
php caligrafer.php schedule run clear-done-tasks    # runs it now: clear-done-tasks: ok
php caligrafer.php schedule history                 # the run: when, ok, and the error when it fails
```

With Docker, put `docker compose exec dev-box` before each command.

**4. Let it run.** On your computer, `php caligrafer.php server start` starts the `scheduler` container: the job runs at
3:00 every night while it is up. Online on Caligrafy Cloud, deploy, then check:

```bash
php caligrafer.php cloud deploy myapp
php caligrafer.php cloud jobs myapp                 # clear-done-tasks, its last run and its next one
```

To tell users something from a job, call [`Caligrafy\Notify::user()`](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications)
from its capability.

## How a job runs

+ **With full access**, like the command line. The audit log records it with the actor type `schedule` and the job's
  name. Nobody is signed in: `$context->userId()` is `null`, so a job that works for each user finds them itself.
+ **One run at a time.** A run that starts while the previous one is still going is skipped and recorded as `skipped`.
+ **Stopped at its timeout**, and recorded as `timeout`.
+ **Recorded** in the table `caligrafy_job_runs` (created by `php caligrafer.php platform install` and by the deploy
  step): the job, its start and end, the outcome (`ok`, `error`, `timeout`, `skipped`) and a short detail. Runs older
  than 30 days are deleted.

## Commands

| Command | What it does |
|---|---|
| `php caligrafer.php schedule list` | Checks the file and lists the jobs; `--json` prints what Caligrafy Cloud reads |
| `php caligrafer.php schedule run <job>` | Runs one job now; with `--if-due`, only when it is due this minute in its timezone |
| `php caligrafer.php schedule due` | Runs every job due this minute, then stops |
| `php caligrafer.php schedule work` | Runs `schedule due` every minute until you stop it |
| `php caligrafer.php schedule history [<job>] [--limit 50]` | The latest runs |

Exit codes: `0` ok, `1` the capability failed or timed out, `2` the file is invalid, `3` skipped. `php caligrafer.php
doctor` reports an invalid file too.

## While you develop

`php caligrafer.php server start` starts a **scheduler** container next to your server: it runs `schedule work`, so
your jobs run every minute as they will online. Without Docker, run `php caligrafer.php schedule work` in a terminal.

Projects made before 2.14.0 get the `scheduler` service in `docker-compose.yaml` with `php caligrafer.php update`
(or `docker-compose.yaml.caligrafy-new` when you changed the file: copy its `scheduler` service into yours).

## Online

+ **Caligrafy Cloud** runs the jobs while the app is online: deploy, and they start. At each start the deploy step
  prints the jobs on one line of the log (`caligrafy-schedule {...}`, the listing of `schedule list --json`), and Cloud
  runs each one inside the app's container on its rhythm, with the app's code, settings and database.

  | | |
  |---|---|
  | **See them** | `php caligrafer.php cloud jobs myapp` (each job, its last run and its next one), or **Jobs** on the app's page |
  | **Run one now** | `php caligrafer.php cloud jobs myapp run check-prices` (within the minute), or **Run now** |
  | **Their runs** | `php caligrafer.php cloud jobs myapp history check-prices` (7 days, with their output), `cloud logs myapp --jobs` |
  | **Limits** | 20 jobs per app, once a minute at most, 300 seconds per run; included in the app's time, no extra credits |
  | **Paused** | A paused app runs no jobs, and missed runs are not caught up |
  | **Failing** | After 10 failed runs in a row of one job, Cloud emails you once; it never turns a job off |
  | **Invalid file** | The app stays online but its jobs do not run: the app's page and an email say why. Fix the file and deploy again |

  Apps made before 2.14.0 have no jobs until they are updated (`php caligrafer.php update`) and deployed again.
+ **Your own server** (Coolify, Docker): run a second container from the same image with
  `php caligrafer.php schedule work`, or `php caligrafer.php schedule due` from the system's cron every minute.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
