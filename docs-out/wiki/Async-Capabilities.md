A capability declared `async: true` is **slow**: a report, a long count, an AI call. In
[Snap](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant) it never runs in the request:
the person gets a **Track card: Task** at once, a job of the [queue](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs)
runs it as that person, and they are notified when it ends. Every other channel (pages, REST, MCP, the command line)
still calls it directly, as before. Since 2.23.0.

<br/>

## With Claude

```text
/caligrafy-quill:new-capability reading_report books:read read-only async: the books read this year by every reader, by month
```

Claude writes the capability with `async: true` and makes sure a worker runs the queue (a job on `queue_work` in
`application/config/schedule.php`).

**Check** before you say OK:
+ in Snap, asking for it shows a Task card that goes from *Waiting to start* to *Working on it*, then its result;
+ `php caligrafer.php queue work --once` runs the waiting `snap_task_run` job.

<br/>

## By hand

1. **The tables.** `docker compose exec dev-box php caligrafer.php platform install` creates the queue's `caligrafy_jobs`
   and Snap's tables (the deploy step does too).
2. **Declare it.**
    ```php
    #[Capability(name: 'reading_report', description: '...', scope: 'books:read', readOnly: true, async: true)]
    ```
    Or leave the attribute as it is and make it async for Snap only, with the hint `async` in `application/config/snap.php`;
    `working` gives the lines the card shows, in turn, while it runs:
    ```php
    return [
        'cards' => [
            'reading_report' => ['async' => true, 'working' => ['Counting the books…']],
        ],
    ];
    ```
3. **A worker.** Something must run the queue: a job on `queue_work` in `application/config/schedule.php`
   (`array('name' => 'queue', 'capability' => 'queue_work', 'every' => '1m', 'timeout' => 55)`, which Caligrafy Cloud and
   `server start` run), or `php caligrafer.php queue work` on your own server. See
   [Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs).

<br/>

## <a name="run"></a>How a task runs

1. A read starts as a task at once; a change starts after the person confirms its Ask: Confirm card. Only a signed-in
   person can start one (a visitor gets `unauthenticated`).
2. `Snap\Tasks::start()` writes a row of `caligrafy_snap_tasks` (the person, the capability, the inputs, `queued`) and
   pushes the job `snap_task_run` to the queue, with 3 attempts and a 120 second timeout.
3. The worker runs the capability with `Capabilities::call()` **as the person who asked**: their user on the `web`
   channel, so their permissions, not the queue's full access. Its checks, validation and audit are those of any call.
4. It ends in one of three states, keeps the card it ended with, and notifies the person on every channel they turned on
   ([Notifications](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications)):

| Status | Label | When | The card it ends with |
| --- | --- | --- | --- |
| `queued` | Waiting to start | pushed, or put back for the queue's next attempt after an error | |
| `running` | Working on it | the worker started it | |
| `done` | Done | it answered | the Show card of the result |
| `needs_input` | Needs your answer | it refused an input (`invalid`) | the Ask card of what it refused; answering it starts a new task |
| `failed` | Could not finish | it refused (`forbidden`, `unauthenticated`, `not_found`, `conflict`), the person no longer exists, or the queue's retries ran out | a notice |

The Track card shows where it is, live, with its steps (Asked, Started, then how it ended); Home shows it in progress,
then just done. A person sees only their own tasks.

Reference: [`Snap\Tasks`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-tasks),
[`Snap\Tasks::async()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-tasks-async),
[`Queue::push()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Jobs#queue-push).

<br/>

## <a name="channels"></a>On the other channels

`async` changes nothing outside Snap: REST, MCP, the JS client, `Route::page`, `Route::form` and
`php caligrafer.php call` run it in the request, as any capability. To run any capability later from your own code,
push it to the queue yourself: `Queue::push('build_report', ['month' => '2026-11'])`
([Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs)).

<br/>

## Related pages

+ [Snap Cards and the assistant: work in the background](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant#tasks)
+ [Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs) · [Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs)
+ [The capability attribute](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Attribute)
