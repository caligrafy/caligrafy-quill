# Release notes: 2.14.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.14.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.14.0** runs your capabilities on a timer, with no page open: **scheduled jobs**.

## Scheduled jobs

Declare them in **`application/config/schedule.php`**: each job calls one capability, with fixed inputs, on a rhythm.

```php
<?php
return array(
    array('name' => 'check-prices', 'capability' => 'check_prices', 'input' => array('due_only' => true), 'every' => '1m'),
    array('name' => 'daily-digest', 'capability' => 'send_digest', 'cron' => '0 8 * * *', 'timezone' => 'America/New_York'),
);
```

+ **Every minute to every day** (`every`: `1m` ... `1d`), or any **cron** in a timezone.
+ **Safe to leave alone:** one run at a time per job, stopped at its timeout, every run recorded (`caligrafy_job_runs`).
+ **Audited:** each call is in the audit log as `schedule` and the job's name.
+ **While you develop:** `php caligrafer.php server start` now also starts a `scheduler` container that runs them every
  minute. Or `php caligrafer.php schedule work` in a terminal.
+ **Checked:** `php caligrafer.php schedule list` and `doctor` name every mistake in the file.

The whole page: [Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs).

Caligrafy Cloud runs them in production from its next release. On your own server, run `php caligrafer.php schedule
work` in a second container.

The Claude Code plugin (1.26.0) knows it: ask Claude for "a job that runs every morning" and it writes the capability
and the schedule.

## Updating a project

```bash
php caligrafer.php update
php caligrafer.php platform install
```

`platform install` adds the `caligrafy_job_runs` table (the deploy step does it online). When you changed
`docker-compose.yaml`, copy the `scheduler` service of `docker-compose.yaml.caligrafy-new` into it. When the update ran
in Docker, run `php caligrafer.php plugin` on your computer in the project's folder, then restart `claude` there.

See the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md) for every change.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
