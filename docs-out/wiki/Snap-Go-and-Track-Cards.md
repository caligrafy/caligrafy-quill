Two families of [Snap](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant) cards belong
to a person rather than to a capability: **Go: Shortcut**, a card they saved (a capability with its inputs) run with
one click, and **Track: Task**, work that runs in the background for them and shows where it is. Since 2.23.0.

<br/>

## With Claude

```text
/caligrafy-quill:assistant
```

Claude marks the slow capabilities `async: true`, writes their `working` lines in `application/config/snap.php` and
adds the `queue_work` job to `application/config/schedule.php`. Shortcuts need nothing: people save their own.

**Check**

+ `php caligrafer.php snap` shows `[ok] Background tasks`.
+ On `/assistant`, *Save* on a card adds it under **Your shortcuts**; typing its title runs it.
+ An async card answers at once with a Task card that moves from *Waiting to start* to *Done*.

<br/>

## <a name="shortcut"></a>Go: Shortcut

A person saves a card from its *Save* control (the capability `snap_shortcut_save`: a `title` of up to 80 characters,
the `capability` and its `inputs`). Their shortcuts are on Home, under **Your shortcuts**, and typing a shortcut's title
in the assistant runs it at once, without a model.

+ **Running one goes through `Snap\Flow` like any card**: a read shows its result, a change asks for its Confirm. A
  shortcut never skips consent.
+ **Only to a capability the person may use**: saving one to another is refused.
+ **12 per person** (`Shortcuts::MAX`): a 13th answers *You have 12 shortcuts already: delete one first.*
+ A Show card says whether it is one of the person's shortcuts already, so its control reads *Save* or *Remove*.
+ The menu ("What can I do?") is a Go: Shortcut too, in its `menu` variant: the ready-made starts the app offers, not the
  ones a person saved.

They are kept in `caligrafy_snap_shortcuts` ([API: Snap\Shortcuts](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-shortcuts)).

<br/>

## <a name="task"></a>Track: Task

A capability declared `async: true` never runs in the request when a Snap card starts it:

```php
#[Capability(name: 'reading_report', description: '...', scope: 'books:read', readOnly: true, async: true)]
```

The hint `'async' => true` in `application/config/snap.php` does the same without the attribute. Then:

1. **A read** starts as a task at once; **a change** after its Ask: Confirm.
2. The task is a row of `caligrafy_snap_tasks` and a job of the [queue](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs)
   (`snap_task_run`).
3. The worker runs the capability **as the person who asked** (their user, the web channel: their permissions, not the
   queue's full access), keeps the card it ends with, and notifies them
   ([Notifications](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications)).

| Status | The card says | Then |
|---|---|---|
| `queued` | *Waiting to start* | |
| `running` | *Working on it* (and the hint `working` lines, in turn) | |
| `done` | *Done* | the Show card of the result |
| `needs_input` | *Needs your answer* | the Ask card of what the capability refused: the person answers it, a new task starts |
| `failed` | *Could not finish* | a notice, after the queue's retries |

Home shows tasks under **In progress**, then **Just done**; the ones that wait for an answer or failed are under
**Needs you**.

```php
'reading_report' => array('working' => array('Counting the books of each author…', 'Putting the report together…')),
```

**The queue must run.** Add the job to `application/config/schedule.php` (see
[Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs)), or run
`php caligrafer.php queue work`; without it, tasks stay *Waiting to start*.

<br/>

## Common mistakes

+ **`async: true` only changes Snap** (G155). REST, MCP, `Route::page`, `Route::form`, `$this->call()` and
  `php caligrafer.php call` still run the capability in the request.
+ **Tasks never start**: the queue does not run (G155).
+ **A shortcut to a change runs it at once**: it does not. It asks for its Confirm, every time.

<br/>

## Related pages

+ [Snap Cards and the assistant](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant#tasks)
+ [Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs)
+ [Snap: Offer cards](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Offer-Cards): routines run as tasks
+ [API: Snap\Tasks](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-tasks) · [Snap\Shortcuts](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-shortcuts)
