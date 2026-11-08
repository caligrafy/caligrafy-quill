# Scheduled jobs: capabilities on a timer

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

A **scheduled job** runs one of your capabilities on a timer, with no page open: send a reminder every morning, clean
old rows every night, check prices every minute. You declare the jobs in one file, `application/config/schedule.php`;
Caligrafy runs them on your computer while you develop and on Caligrafy Cloud in production. Since 2.14.0.

## With Claude

```text
/caligrafy-quill:new-capability Every night at 3:00 New York time, delete the loans returned more than a year ago
```

Claude writes the capability in `application/services/`, adds the job to `application/config/schedule.php`, then runs
`php caligrafer.php schedule list` and `php caligrafer.php schedule run <job>`.

**Check**

+ The capability finds its rows itself: a job has nobody signed in, so it never reads `$context->userId()`.
+ `php caligrafer.php schedule list` shows the job with the rhythm you asked for.
+ `php caligrafer.php schedule history <job>` shows a run with `ok`.

## By hand: clear the old loans every night

**Bookshelf** keeps every loan forever. This job deletes the loans returned more than a year ago, every night at 3:00
in New York. Start from the sign-in pages of [Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication)
(the `users` table and the Bookshelf catalog). The commands run in the Docker server of the Quickstart; without Docker,
drop `docker compose exec dev-box`.

**1. The loans table.** It is the one of the course's
[lesson 13](https://github.com/caligrafy/caligrafy-quill-course/wiki/13-Capstone-Loans). If you do not have it, write
`application/models/Loans/create_db.sql`:

```sql
CREATE TABLE IF NOT EXISTS loans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    book_id INT UNSIGNED NOT NULL,
    borrower VARCHAR(100) NOT NULL,
    due_on DATE NOT NULL,
    returned_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_loans_book_id (book_id),
    CONSTRAINT fk_loans_book_id FOREIGN KEY (book_id) REFERENCES books (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
```

and run `docker compose exec dev-box php caligrafer.php sql application/models/Loans/create_db.sql`.

**2. The capability.** Run `docker compose exec dev-box php caligrafer.php service Loans/ClearReturnedLoans --scope loans:write`, then replace
`application/services/Loans/ClearReturnedLoans.php` with:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;

#[Capability(
    name: 'clear_returned_loans',
    description: 'Deletes the loans returned more than a number of days ago (365 by default). Returns how many were deleted.',
    scope: 'loans:write',
    level: 1,
    destructive: true
)]
class ClearReturnedLoans extends Service
{
    public $rules = array('days' => 'integer|min_numeric,1|max_numeric,3650');
    public $descriptions = array('days' => 'Loans returned more than this many days ago are deleted (365 by default)');

    public function handle(array $input, Context $context)
    {
        $before = date('Y-m-d H:i:s', time() - (int) ($input['days'] ?? 365) * 86400);
        // delete() returns how many rows it deleted
        $deleted = Loan::whereNotNull('returned_at')->where('returned_at', '<', $before)->delete();
        return array('deleted' => $deleted);
    }
}
```

It is an ordinary capability: an administrator (`level: 1`) can also call it from a page, the REST API or an agent.

**3. The schedule.** Write `application/config/schedule.php`:

```php
<?php
return array(
    array('name' => 'clear-returned-loans', 'capability' => 'clear_returned_loans', 'input' => array('days' => 365),
        'cron' => '0 3 * * *', 'timezone' => 'America/New_York'),
);
```

**4. Check it and run it once.**

```bash
docker compose exec dev-box php caligrafer.php schedule list
```
```text
 clear-returned-loans     0 3 * * *      clear_returned_loans     America/New_York, timeout 55s
```
```bash
docker compose exec dev-box php caligrafer.php schedule run clear-returned-loans
```
```text
 clear-returned-loans: ok
```
```bash
docker compose exec dev-box php caligrafer.php schedule history
```
```text
 2026-11-07 23:19:29  clear-returned-loans     ok
```

`schedule run` prints the outcome, not the result: `docker compose exec dev-box php caligrafer.php call clear_returned_loans days=365` prints
`{"data": {"deleted": 0}}`. `docker compose exec dev-box php caligrafer.php audit` shows the run as `cli schedule clear-returned-loans
clear_returned_loans ok`.

**5. Let it run.** On your computer, the Docker server (`.bin/caligrafer server start`) has a `scheduler` container: the job runs at 3:00 every night while it is up. Without Docker, keep `php caligrafer.php schedule work` open in
a terminal. Online on Caligrafy Cloud, deploy, then check:

```bash
docker compose exec dev-box php caligrafer.php cloud deploy bookshelf
docker compose exec dev-box php caligrafer.php cloud jobs bookshelf          # clear-returned-loans, its last run and its next one
```

**Check**

+ `schedule list` prints the job (an invalid file prints every problem and exits with `2`).
+ `schedule run clear-returned-loans` prints `ok`.
+ `schedule history` has the run.

To tell people something from a job, call [`Caligrafy\Notify::user()`](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications#example)
from its capability.

## Reference: the schedule file

`application/config/schedule.php` returns a list of jobs. No file means no jobs.

| Field | Required | Rule |
|---|---|---|
| `name` | yes | A slug (`a-z`, `0-9`, `-`), at most 40 characters, unique in the file |
| `capability` | yes | A capability of the app (`php caligrafer.php capabilities`) |
| `input` | no | Its inputs: an array that encodes to JSON, checked by the capability's rules when it runs |
| `every` | `every` or `cron` | `1m`, `5m`, `10m`, `15m`, `30m`, `1h`, `6h`, `12h` or `1d` (in UTC; `1d` is midnight UTC) |
| `cron` | `every` or `cron` | Five fields: minute, hour, day of the month, month, day of the week (`*/15 * * * *`, `0 9-17 * * mon-fri`) |
| `timezone` | no | An IANA name such as `Europe/Paris`, for `cron` only; `UTC` by default |
| `timeout` | no | Seconds, 55 by default, from 1 to 300 |
| `enabled` | no | `true` by default; `false` keeps the job declared but never run |

Any other field is an error. Background jobs ([Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs))
run through one more job of this file, on the framework's capability `queue_work`:
`array('name' => 'queue', 'capability' => 'queue_work', 'every' => '1m', 'timeout' => 55)`.

### How a job runs

+ **With full access**, like the command line. The audit log records it with the actor type `schedule` and the job's
  name. Nobody is signed in: `$context->userId()` is `null`, so a job that works for each user finds them itself.
+ **In its own process, one run at a time.** A run that starts while the previous one is still going is skipped and
  recorded as `skipped`.
+ **Stopped at its timeout**, and recorded as `timeout`.
+ **Recorded** in `caligrafy_job_runs` (made by `php caligrafer.php platform install` and the deploy step): the job, its
  start and end, the outcome (`ok`, `error`, `timeout`, `skipped`) and a short detail. Runs older than 30 days are
  deleted.
+ **Needs the database** (`DB_ACTIVATE=true`).

### Commands

| Command | What it does |
|---|---|
| `php caligrafer.php schedule list` | Checks the file and lists the jobs; `--json` prints what Caligrafy Cloud reads |
| `php caligrafer.php schedule run <job>` | Runs one job now; with `--if-due`, only when it is due this minute in its timezone |
| `php caligrafer.php schedule due` | Runs every enabled job due this minute, side by side, then stops |
| `php caligrafer.php schedule work` | Runs `schedule due` at the start of every minute until you stop it |
| `php caligrafer.php schedule history [<job>] [--limit 50]` | The latest runs, newest first |

Exit codes: `0` ok, `1` the capability failed or timed out, `2` the file is invalid, `3` skipped. `php caligrafer.php
doctor` reports an invalid file too.

### While you develop

`.bin/caligrafer server start` starts the **scheduler** container: it runs `schedule work`, so your jobs run as they
will online. Without Docker, run `php caligrafer.php schedule work` in a terminal. Projects made before 2.14.0 get the
`scheduler` service in `docker-compose.yaml` with `php caligrafer.php update` (or `docker-compose.yaml.caligrafy-new`
when you changed the file: copy its `scheduler` service into yours).

### Online

**Caligrafy Cloud** runs the jobs while the app is online. At each start the deploy step prints the jobs on one line of
the log (`caligrafy-schedule {...}`, the listing of `schedule list --json`), and Cloud runs each one inside the app's
container on its rhythm, with the app's code, settings and database.

| | |
|---|---|
| **See them** | `php caligrafer.php cloud jobs <app>` (each job, its last run and its next one), or **Jobs** on the app's page |
| **Run one now** | `php caligrafer.php cloud jobs <app> run <job>` (within the minute), or **Run now** |
| **Their runs** | `php caligrafer.php cloud jobs <app> history <job>` (7 days, with their output), `cloud logs <app> --jobs`. A run that has not ended shows `running`, with no duration |
| **Limits** | 20 jobs per app, once a minute at most, 300 seconds per run; included in the app's time, no extra credits |
| **Paused** | A paused app runs no jobs, and missed runs are not caught up |
| **Failing** | After 10 failed runs in a row of one job, Cloud emails you once; it never turns a job off |
| **Invalid file** | The app stays online but its jobs do not run: the app's page and an email say why. Fix the file and deploy again |

**Your own server** (Coolify, Docker): run a second container from the same image and `.env` with
`php caligrafer.php schedule work`, or `php caligrafer.php schedule due` from the system's cron every minute. One of
the two, never both.

## Common mistakes

+ **The job does nothing for "the current user".** A job has nobody signed in: find the users or rows in the capability.
+ **`'timezone' is for 'cron' only`.** `every` is always in UTC: use `cron` with a `timezone` for a time of day.
+ **A job stopped at 55 seconds.** Raise its `timeout` (300 at most), or move the work to a
  [background job](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs) (900 seconds).
+ **Jobs run twice online.** Your own server runs both `schedule work` and a cron line: keep one.
+ **Nothing runs on your computer.** The `scheduler` container is not up (`server start`), or `schedule work` is not open.

## Related pages

+ [Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs)
+ [Notifications](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications)
+ [Build once: services, capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP)
+ [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online)

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
