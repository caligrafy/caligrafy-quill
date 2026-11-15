The **Offer** cards of [Snap](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant) are the
assistant's own suggestions. **Offer: Suggestion** comes at the moment a person usually does something, or when rows need
them (an alert). **Offer: Routine** offers to do it for them on that
rhythm. Both come from **noticing**, which is off until each person turns it on. Since 2.23.0.

<br/>

## With Claude

```text
/caligrafy-quill:assistant
```

Claude adds the routines' job to `application/config/schedule.php` and writes the `alert` hints of the lists whose rows
carry a flag (an overdue loan).

**Check**

+ `php caligrafer.php snap` shows `[ok] Routines`.
+ On `/assistant/noticed`, turning noticing on and off works, and off forgets what was noticed.

<br/>

## By hand

1. **The routines' job**, in `application/config/schedule.php` (see
   [Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs)):
    ```php
    array('name' => 'snap-routines', 'capability' => 'snap_routines_due', 'every' => '1m', 'timeout' => 50)
    ```
   Routines at the *auto* level run as [tasks](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Go-and-Track-Cards#task),
   so the queue must run too.
2. **An alert**, for rows that need someone now, in `application/config/snap.php`:
    ```php
    'list_loans' => array('alert' => array('when' => 'overdue', 'message' => '{count} loan{s} overdue', 'label' => 'borrower')),
    ```
   When rows of `list_loans` (a read-only capability) have a true `overdue`, Home shows an Offer: Suggestion in its
   `alert` variant under **Needs you**; `'action' => '<capability>'` adds the button that takes care of it.
3. **People turn noticing on** at `/assistant/noticed`. Nothing is noticed before.

<br/>

## <a name="noticing"></a>Reference: noticing

`Caligrafy\Snap\Offers` ([API](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-offers)):

+ **Opt-in per person**, off by default. When on, the assistant keeps which card they used, with which inputs and when
  (a read that ran, a change they confirmed, a task they started); nothing else. `/assistant/noticed` shows every
  pattern, each deletable; turning noticing off deletes everything noticed.
+ **A pattern** is the same request (capability and inputs) in the last 42 days (`WINDOW_DAYS`):
  + **weekly**: on 3 days or more (`MIN_DAYS`) of the same weekday, around the same hour ("You did this 3 Mondays in a
    row, around 9 am.");
  + **daily**: on 4 different days or more, around the same hour ("You did this on 4 different days, around 9 am.").

  Anything weaker says nothing.
+ **At most 2 offers at a time** (`MAX_OFFERS`): routines that are due, then suggestions of this moment, then routines
  to propose; never one dismissed. Each says why, and has *Not now* (today) and *Don't suggest this again* (never).
+ What a person opened also picks what Home suggests to browse (the hint `discover`).

<br/>

## <a name="routine"></a>Reference: routines

Accepting an Offer: Routine is itself a change, with its Ask: Confirm (`snap_routine_save`), recorded in the audit log.
The person picks a level:

| Level | At the moment of the routine |
|---|---|
| `suggest` | an Offer: Suggestion |
| `confirm` | its Ask: Confirm, ready, under **Needs you** |
| `auto` | it runs as a task of its owner (the job `snap_routines_due`), and they are told |

A delete is never done on a rhythm: *A delete is never done on a rhythm.* A routine is stopped with
`snap_routine_delete`, from `/assistant/noticed`.

<br/>

## <a name="alert"></a>Reference: alerts

The hint `alert` on a read-only capability whose rows carry a flag:

| Key | What it does |
|---|---|
| `when` | the field of a row that makes it count (true, not empty) |
| `message` | the line; `{count}` is how many rows, `{s}` an `s` when more than one |
| `title`, `reason` | the card's title and why (`{count}` works there too) |
| `label` | the field each row is named by (`title` by default); 5 are listed |
| `action`, `yes` | the capability that takes care of it and its button (*Take care of it*); without it, or when the person may not call it, the alert opens the list (*See them*) |
| `tag`, `severity` | *Needs you* and `danger` by default |

<br/>

## Common mistakes

+ **No offer ever comes.** Noticing is off until the person turns it on; a pattern needs 3 weekdays or 4 days around one
  hour.
+ **Routines at the auto level never run.** The `snap-routines` job is missing from `schedule.php`, or the queue does
  not run.
+ **Hints on the capability.** `alert` is a key of `application/config/snap.php`, not of `#[Capability]` (G157).

<br/>

## Related pages

+ [Snap Cards and the assistant](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant#noticing)
+ [Snap: Go and Track cards](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Go-and-Track-Cards)
+ [Snap: hints](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Hints)
+ [Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs)
+ [API: Snap\Offers](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-offers)
