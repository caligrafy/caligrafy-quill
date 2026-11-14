# Background jobs: capabilities run later

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

A **background job** runs one of your capabilities later, outside the request: the page answers at once, and the slow
part (an email, an AI call, a report, resizing images) happens a moment after, with retries when it fails. A job is a
capability's name and its input, pushed with `Caligrafy\Queue::push()` and run by a worker. Since 2.18.0.

## With Claude

```text
/caligrafy-quill:new-capability When a book is lent, notify the person who lent it of its due date, in the background
```

Claude writes two capabilities, the one the page calls (it checks who asks, saves, then pushes the job) and the one that
does the work, and adds the worker job on `queue_work` to `application/config/schedule.php`.

**Check**

+ The capability that pushes checks who may ask, and pushes ids, not data to trust later.
+ The work capability refuses callers other than the queue (it runs with full access).
+ `php caligrafer.php queue work --once` prints `done` for the job.

## By hand: a receipt for each loan

When the front desk of **Bookshelf** lends a book, the call answers at once, and a job tells the person who lent it who
has the book and until when. Start from the loans of the course
([lesson 13](https://github.com/caligrafy/caligrafy-quill-course/wiki/13-Capstone-Loans): the `loans` table, the `Loan`
model, `lend_book`) and from [Notifications](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications#example)
(`platform install`, `notify keys`). The commands run in the Docker server of the Quickstart; without Docker, drop
`docker compose exec dev-box`.

**1. Push the job from the capability that takes the request.** Replace
`application/services/Loans/LendBook.php` with (the course's, plus `use Caligrafy\Queue;` and the `Queue::push` line):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Queue;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// lend_book: records that someone borrowed a book, for a number of days
#[Capability(
    name: 'lend_book',
    description: 'Lends a book of the catalog to someone for a number of days (14 by default) and returns the loan. Refused when the book is already lent.',
    scope: 'loans:write'
)]
class LendBook extends Service
{
    public $rules = array('book_id' => 'required|integer', 'borrower' => 'required|max_len,100', 'days' => 'integer|min_numeric,1|max_numeric,60');
    public $filters = array('borrower' => 'trim');
    public $descriptions = array(
        'book_id' => 'The id of the book (list_books, search_books)',
        'borrower' => 'The name of the person who borrows it',
        'days' => 'For how many days: 1 to 60, 14 by default'
    );

    public function handle(array $input, Context $context)
    {
        if (!Book::find($input['book_id'])) {
            throw ServiceError::notFound("There is no book #{$input['book_id']}");
        }
        $open = Loan::where('book_id', $input['book_id'])->whereNull('returned_at')->first();
        if ($open) {
            throw ServiceError::conflict("This book is already lent to {$open->borrower}");
        }
        $days = (int) ($input['days'] ?? 14);
        $id = Loan::insert(array(
            'book_id' => $input['book_id'],
            'borrower' => $input['borrower'],
            'due_on' => date('Y-m-d', strtotime("+$days days"))
        ));
        if ($context->userId() !== null) {
            // later, outside the request: tell the person who lent it
            Queue::push('send_loan_receipt', array('loan_id' => (int) $id, 'user_id' => (int) $context->userId()));
        }
        return Loan::find($id);
    }
}
```

The job gets ids, not names or dates to trust later, and only when someone is behind the call.

**2. The capability that does the work.** Write `application/services/Loans/SendLoanReceipt.php`:

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Notify;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// send_loan_receipt: run by the queue after lend_book, tells the person who lent the book when it comes back
#[Capability(
    name: 'send_loan_receipt',
    description: 'Tells the person who lent a book who has it and when it is due. Run by the queue after lend_book.',
    mcp: false
)]
class SendLoanReceipt extends Service
{
    public $rules = array('loan_id' => 'required|integer', 'user_id' => 'required|integer');

    public function handle(array $input, Context $context)
    {
        if (!in_array($context->actorType(), array('queue', 'cli'), true)) {
            throw ServiceError::forbidden('The queue sends the loan receipts');
        }
        $loan = Loan::find($input['loan_id']);
        if (!$loan) {
            throw ServiceError::notFound("There is no loan #{$input['loan_id']}");
        }
        $book = Book::find($loan->book_id);
        $sent = Notify::user($input['user_id'], array(
            'title' => $book->title . ' is lent',
            'body' => $loan->borrower . ' has it until ' . $loan->due_on . '.',
            'url' => '/loans',
            'tag' => 'loan-' . $loan->id,
        ));
        return array('loan_id' => (int) $loan->id, 'push' => $sent['push'], 'email' => $sent['email']);
    }
}
```

The job runs with full access: the `actorType()` check keeps people from calling it through the REST API.

**3. The worker.** Add the queue to `application/config/schedule.php`:

```php
<?php
return array(
    array('name' => 'clear-returned-loans', 'capability' => 'clear_returned_loans', 'input' => array('days' => 365),
        'cron' => '0 3 * * *', 'timezone' => 'America/New_York'),
    array('name' => 'remind-loans', 'capability' => 'remind_loans', 'cron' => '0 8 * * *', 'timezone' => 'America/New_York'),
    array('name' => 'queue', 'capability' => 'queue_work', 'every' => '1m', 'timeout' => 55),
);
```

Every minute it runs the jobs that are due for up to 50 seconds: the `scheduler` container of the Docker server and
Caligrafy Cloud run it.

**4. Try it.** Lend a book as the front desk, with a token that acts for the administrator (user 1), through the REST
API (`Route::capabilities()` in `web.php`):

```bash
docker compose exec dev-box php caligrafer.php token create "Front desk" --scopes loans:write --user 1
curl -X POST -H "Authorization: Bearer cq_<id>_<secret>" -H "Content-Type: application/json" \
  -d '{"book_id": 1, "borrower": "Sam"}' http://localhost:8080/capabilities/lend_book
```

It answers at once: `201 {"data":{"id":1,"book_id":1,"borrower":"Sam","due_on":"<in 14 days>",...}}`. The same call again
answers `409 {"error":{"code":"conflict","message":"This book is already lent to Sam"}}`. The job waits:

```bash
docker compose exec dev-box php caligrafer.php queue list
```
```text
 #1      default  send_loan_receipt        pending  0/3  from 2026-11-07 23:40:15
```

Run it now (the scheduler's `queue` job does the same within a minute):

```bash
docker compose exec dev-box php caligrafer.php queue work --once
```
```text
 Working the queue default until it is empty
 #1 send_loan_receipt: done
 1 done, 0 to try again, 0 failed
```

The notification *A Wizard of Earthsea is lent* reaches the administrator's browsers if they turned alerts on.

**Check**

+ `queue status` reads `0 pending, 0 running, 1 done, 0 failed`.
+ `audit` has `queue send_loan_receipt #1 send_loan_receipt ok`.
+ The `lend_book` call answered before the job ran.

## Reference: `Queue::push`

```php
use Caligrafy\Queue;

Queue::push('send_loan_receipt', ['loan_id' => $id, 'user_id' => $userId]);
Queue::push('build_report', ['month' => '2026-11'], ['delay' => 600, 'attempts' => 5, 'timeout' => 120]);
```

| Option | Default | What it does |
|---|---|---|
| `delay` | `0` | seconds before the job may start |
| `attempts` | `3` | how many times it is tried (1 to 20) |
| `timeout` | `60` | seconds a run may take before it is stopped (900 at most) |
| `queue` | `default` | the queue's name (lowercase letters, digits, `-`, `_`), for a worker of its own |

+ It returns the job's id. The capability must exist when you push (`InvalidArgumentException` otherwise), and the
  input must encode to JSON.
+ Jobs are kept in `caligrafy_jobs` (made by `php caligrafer.php platform install` and the deploy step).
+ **Who it runs as.** A job runs with full access, like a scheduled job (`Context::system`: the audit log records
  `queue` and `<capability> #<id>`). Check who may ask for the work before you push it, and pass ids rather than data
  to trust later.

### Workers

| Way | When |
|---|---|
| a job on `queue_work` in `schedule.php` (`'every' => '1m', 'timeout' => 55`) | Caligrafy Cloud, and `server start` on your computer; a job starts within a minute and has what is left of it |
| `php caligrafer.php queue work` | your own server, longer jobs: a loop that runs jobs as they come (Ctrl+C stops it) |

```bash
php caligrafer.php queue work                 # runs jobs as they come
php caligrafer.php queue work --once          # runs what is due, then stops
php caligrafer.php queue work --for 300       # for 5 minutes
php caligrafer.php queue work --queue reports --sleep 3   # another queue; seconds between looks when empty
```

Each job runs in its own process, stopped at its timeout. Run one way or the other, not both. `queue_work` takes
`queue` and `seconds` (1 to 290, default 50) inputs, and refuses every caller but the scheduler and the command line.

### Failures and retries

+ A run that throws or times out is tried again after 30 seconds, then 1 minute, 2 minutes... until its attempts are
  used; then the job is `failed`.
+ A refusal of the capability (`invalid`, `not_found`, `forbidden`, `unauthenticated`) fails at once: it would fail
  again. `queue work` prints `#2 send_loan_receipt: failed (There is no loan #999)`.
+ A worker that stopped in the middle of a job (a restart) leaves it `running`; the next worker puts it back once its
  timeout and one more minute have passed.
+ Finished jobs are kept 7 days.

```bash
php caligrafer.php queue status              # pending, running, done, failed
php caligrafer.php queue list                # the latest jobs, with their last error (--status failed, --limit 50)
php caligrafer.php queue failed              # only the failed ones
php caligrafer.php queue retry 42            # or: queue retry all
```

### Testing

In a test, `$this->assertQueued('send_loan_receipt')` checks that a job was pushed and returns its input
([Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App)). Run the job itself with
`$this->call('send_loan_receipt', $input)`.

## Common mistakes

+ **Jobs stay `pending`.** No worker: add the `queue` job to `schedule.php`, or run `queue work`.
+ **Two workers for one queue.** A `queue_work` job and a `queue work` container side by side: keep one.
+ **A job reads the signed-in user.** Nobody is signed in when it runs: pass the ids it needs in its input.
+ **Anyone can trigger the work.** The work capability is also a REST capability: refuse callers whose
  `$context->actorType()` is not `queue` (or `cli`).
+ **A long job is stopped at 50 seconds** with the scheduler's worker: give it a worker of its own (`queue work`,
  timeouts up to 900 seconds).

## Related pages

+ [Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs)
+ [Notifications](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications)
+ [Snap Cards: a capability declared `async: true` runs in the queue as a Track card: Task](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant#tasks)
+ [Authentication: tokens](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#agents)
+ [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App)

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
