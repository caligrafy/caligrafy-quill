The **job commands** of [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer) run work outside
a page: `schedule` runs capabilities on a timer, `queue` runs the background jobs your capabilities pushed, `notify`
sets up web push and sends a test, and `test` runs the app's tests.

<br/>

## With Claude

```text
/caligrafy-quill:new-capability check_prices prices:write: run it every hour
/caligrafy-quill:new-capability send_welcome_email users:write: run it in the background after sign-up
```

The `new-capability` skill writes the job in `application/config/schedule.php` (or the `Queue::push(...)` call), runs
`schedule list` or `queue work --once` to check it, and writes its test.

**Check** before you say OK: `php caligrafer.php schedule list` lists the job with the rhythm you asked for, and
`php caligrafer.php test` ends with `OK (...)`.

<br/>

## By hand

```bash
docker compose exec dev-box php caligrafer.php schedule list
docker compose exec dev-box php caligrafer.php schedule run check-prices
docker compose exec dev-box php caligrafer.php queue work --once
docker compose exec dev-box php caligrafer.php test
```

(without Docker, drop `docker compose exec dev-box`). The Docker server's `scheduler` container already runs
`schedule work` while it is up.

<br/>

## <a name="schedule"></a>`schedule`

```text
 Usage: php caligrafer.php schedule <list [--json] | run <job> | due | work | history [<job>] [--limit 50]>
 Jobs are declared in application/config/schedule.php
```

| Command | What it does |
| --- | --- |
| `schedule list` | checks `schedule.php` (every job, its capability, its rhythm) and lists the jobs |
| `schedule list --json` | the same as one line of JSON (version 1): what Caligrafy Cloud reads at each deploy |
| `schedule run <job>` | runs one job now; `--if-due`: only when it is due this minute |
| `schedule due` | runs the jobs due this minute (for a cron line every minute) |
| `schedule work` | runs `due` every minute until stopped: ` Running the scheduled jobs every minute (Ctrl-C to stop)` |
| `schedule history [<job>] [--limit 50]` | the latest runs: when, the job, the outcome and its detail |

```text
 check-prices             every 1h       check_prices             UTC, timeout 55s
 nightly-report           0 2 * * *      build_report             Europe/Paris, timeout 120s (disabled)
```

+ A job calls one capability with fixed inputs, `every` one of `1m`, `5m`, `10m`, `15m`, `30m`, `1h`, `6h`, `12h`,
  `1d` (in UTC) or a five-field `cron` in its `timezone`, with a `timeout` (55 seconds by default, 300 at most).
+ One run at a time per job: a run that starts while the last one is still going is ` <job>: skipped, the previous run is still running`.
+ Each run is a child process stopped at its timeout, with full access like the command line, recorded in
  `caligrafy_job_runs` (kept 30 days).
+ Exit codes: `0` ok, `1` a job failed or timed out, `2` the file is invalid (every problem is printed, one per line),
  `3` skipped.
+ With no `schedule.php`: ` No scheduled jobs (application/config/schedule.php declares them)`.

Learn more in [Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs) and
[`Caligrafy\Scheduler`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Jobs#scheduler).

<br/>

## <a name="queue"></a>`queue`

```text
 Usage: php caligrafer.php queue <work [--queue default] [--for <seconds>] [--once] [--sleep 3] | status | list [--status failed] [--limit 50] | failed | retry <id | all>>
 Background jobs pushed with Caligrafy\Queue::push('<capability>', [...])
```

```bash
php caligrafer.php queue work --once      # what is due, then stops
php caligrafer.php queue work --for 300   # for 5 minutes
php caligrafer.php queue work             # until Ctrl+C
php caligrafer.php queue status
php caligrafer.php queue list --status failed
php caligrafer.php queue retry 42         # retry all: every failed job
```

```text
 Working the queue default until it is empty
 #12 send_welcome_email: done
 1 done, 0 to try again, 0 failed
```

| Command | What it does |
| --- | --- |
| `queue work` | runs the jobs of a queue (`--queue`, `default` by default): `--once` until it is empty, `--for <seconds>` for a while, else until stopped; `--sleep` is the pause when there is nothing to do (3 seconds) |
| `queue status` | ` 0 pending, 0 running, 0 done, 0 failed` |
| `queue list [--status <s>] [--limit 50]` | one line per job: its id, queue, capability, status, attempts, and when it may run or when it finished, with its last error |
| `queue failed` | `queue list --status failed` |
| `queue retry <id \| all>` | puts a failed job, or all of them, back in the queue: ` 1 failed job(s) back in the queue` |

+ Each job runs in its own process (`queue exec <id>`, internal), stopped at its timeout, with full access (G121): the
  capability that pushes the work checks who may ask for it.
+ A failed run is tried again after 30 s, 1 min, 2 min... until its attempts are used (3 by default); an input the
  capability refuses (invalid, not found) fails at once. Jobs are kept 7 days after they finish.
+ Online, a scheduled job on the framework's `queue_work` capability runs them every minute (Caligrafy Cloud runs it
  too), or a container of its own runs `queue work` for longer jobs.

Learn more in [Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs) and
[`Caligrafy\Queue`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Jobs#queue).

<br/>

## <a name="notify"></a>`notify`

```text
 Usage: php caligrafer.php notify <keys [--subject mailto:you@example.com] | test <user id> | status <user id>>
```

```bash
php caligrafer.php notify keys --subject mailto:you@example.com   # the web push keys, once
php caligrafer.php notify test 7                                  # a test notification to user 7
php caligrafer.php notify status 7                                # user 7's channels
```

+ **`keys`** writes `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` and `VAPID_SUBJECT` to `.env`:
  ` Web push keys written to .env (VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT=mailto:you@example.com). Keep them: new keys cut off every subscribed browser`.
  It refuses to replace keys that are there (exit `1`). The subject is a `mailto:` address or an `https://` address;
  without `--subject` it uses `MAIL_FROM` or `MAIL_USERNAME` when it is an email address, else `APP_URL`.
+ **`test <user id>`** sends ` Notifications work` (` A test from php caligrafer.php notify test.`) to the user's
  channels, and prints the result as JSON.
+ **`status <user id>`** prints, as JSON, the channels offered, then `push` (`configured`, `public_key`, the number of
  `browsers`, `enabled`) and `email` (`configured`, `address`, `confirmed`, `enabled`) for that user.

Learn more in [Notifications](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications) and
[`Caligrafy\Notify`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Notify).

<br/>

## <a name="test"></a>`test`

```text
 Usage: php caligrafer.php test make <Name>   (writes tests/<Name>Test.php)
```

```bash
php caligrafer.php test make Books   # writes tests/BooksTest.php
php caligrafer.php test              # every tests/*Test.php and tests/*/*Test.php
php caligrafer.php test Books        # only the tests whose class or method name matches
```

```text
 tests/BooksTest.php written: php caligrafer.php test Books
```

+ **`make <Name>`** writes a class that extends `Caligrafy\Testing\TestCase` with one test and commented examples of
  `$this->call(...)` and `$this->assertCapabilityFails(...)`. A trailing `Test` in the name is dropped; an existing file
  is refused. It also writes `tests/.gitignore` when the folder had none, or one that ignored every file (projects made
  before 2.18.0).
+ **`test [<filter>]`** runs PHPUnit with `framework/testing/bootstrap.php`, no configuration file,
  `--display-warnings` and `--fail-on-warning`: a warning fails the run. Its exit code is PHPUnit's.
+ With no tests: ` No tests yet: php caligrafer.php test make <Name> writes tests/<Name>Test.php` (exit `0`). Without
  PHPUnit (`composer install --no-dev`): ` PHPUnit is not installed: composer install (with the development packages), then php caligrafer.php test`
  (exit `1`).

How to write the tests: [Testing capabilities](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Capabilities),
[Testing pages, forms and actions](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Pages-Forms-and-Actions)
and [The test database](https://github.com/caligrafy/caligrafy-quill/wiki/The-Test-Database).

<br/>

## Common mistakes

+ **A scheduled job expects someone signed in** (G108): a job runs with nobody signed in, as the system. Give it the
  ids it works on in its inputs.
+ **A background job trusts its input** (G121): it runs with full access. Check who may ask in the capability that
  pushes it.
+ **`skipped` read as a failure** (G150): it is a run that started while the same job was still going.
+ **New web push keys**: every browser that turned push on stops receiving. `notify keys` refuses to replace them;
  keep them across deploys.
+ **A test that creates a table** (G121): MySQL commits the `CREATE` and ends the test's transaction, so its rows stay.

<br/>

## Related pages

+ [Caligrafer: every command](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer)
+ [Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs) · [Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs) · [Notifications](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications)
+ [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App)
+ [API reference: jobs, notifications and mail](https://github.com/caligrafy/caligrafy-quill/wiki/API-Jobs)

<br/><br/>
>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
