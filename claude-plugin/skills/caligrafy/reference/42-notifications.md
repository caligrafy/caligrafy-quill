# Notifications: web push and email

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Notifications
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

**Notifications** reach your users when none of your pages are open: a book due tomorrow, a reservation ready, an
order shipped. One call, `Caligrafy\Notify::user()`, sends the message on every channel the user turned on: **web push**
(a notification on their phone or computer, browser closed) and **email** (an address they confirmed). Since 2.15.0.

## With Claude

```text
/caligrafy-quill:new-capability Every morning at 8, tell the administrators how many books are due today or late, and add a "Turn on alerts" button to the loans page
```

Claude writes the capability that calls `Notify::user()`, the job in `application/config/schedule.php`, the button
with `public/js/notify.js`, and adds `Route::capabilities()` and `Route::notifications()` to `web.php`. It runs
`php caligrafer.php platform install` and `php caligrafer.php notify keys --subject mailto:<you>` on your computer
(Caligrafy Cloud makes its own keys).

**Check**

+ `php caligrafer.php notify status <your user id>` shows `push` `configured: true`.
+ After *Turn on alerts* in your browser, `php caligrafer.php notify test <your user id>` answers `"push": 1` and a
  notification appears.
+ `php caligrafer.php schedule run <job>` prints `ok`.

## By hand: tell the administrators what is late, every morning

Every morning at 8 in New York, each administrator of **Bookshelf** hears how many loans are due today or late. Start
from [Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs#example) (the `loans` table) and
an administrator (`permissions` 1, [Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication)).
The commands run in the Docker server of the Quickstart; without Docker, drop `docker compose exec dev-box`.
The whole lesson, with a page and Caligrafy Cloud, is
[course lesson 15](https://github.com/caligrafy/caligrafy-quill-course/wiki/15-Reminders).

**1. Turn notifications on, once.**

```bash
docker compose exec dev-box php caligrafer.php platform install
docker compose exec dev-box php caligrafer.php notify keys --subject mailto:you@example.com
```

`notify keys` prints ` Web push keys written to .env (VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT=mailto:you@example.com).
Keep them: new keys cut off every subscribed browser`. Run again, it keeps them.

**2. The routes.** In `application/config/routing/web.php`, below `Route::auth(...)`:

```php
    Route::capabilities();      // what notify.js calls (/capabilities)
    Route::notifications();     // the pages the emails link to: /notify/confirm/{token}, /notify/unsubscribe
```

**3. The capability.** Write `application/services/Loans/RemindLoans.php` (the course's, lesson 15):

```php
<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Database;
use Caligrafy\Notify;
use Caligrafy\Service;

// remind_loans: tells the administrators which books are due today or late; a scheduled job runs it every morning
#[Capability(
    name: 'remind_loans',
    description: 'Notifies every administrator of the loans due today or late, by push and email. Returns how many loans are due today, how many are late, and how many administrators got the message.',
    scope: 'loans:write',
    level: 1
)]
class RemindLoans extends Service
{
    public function handle(array $input, Context $context)
    {
        $today = date('Y-m-d');
        $due = Loan::whereNull('returned_at')->where('due_on', $today)->count();         // SELECT COUNT(*)
        $late = Loan::whereNull('returned_at')->where('due_on', '<', $today)->count();
        if ($due + $late === 0) {
            return array('due' => 0, 'late' => 0, 'notified' => 0);
        }
        $books = $due + $late;
        $message = array(
            'title' => $books === 1 ? '1 book to bring back' : "$books books to bring back",
            'body' => "$due due today, $late late. Open the loans to see who has them.",
            'url' => '/loans',
            'tag' => 'loans-due'
        );
        $notified = 0;
        foreach (Database::table('users')->where('permissions', '>=', 1)->pluck('id') as $admin) {
            $sent = Notify::user($admin, $message);
            if ($sent['push'] > 0 || $sent['email']) {
                $notified++;
            }
        }
        return array('due' => $due, 'late' => $late, 'notified' => $notified);
    }
}
```

A job has nobody signed in, so the capability finds the people it notifies itself.

**4. The schedule.** Add the job to `application/config/schedule.php`:

```php
<?php
return array(
    array('name' => 'clear-returned-loans', 'capability' => 'clear_returned_loans', 'input' => array('days' => 365),
        'cron' => '0 3 * * *', 'timezone' => 'America/New_York'),
    array('name' => 'remind-loans', 'capability' => 'remind_loans', 'cron' => '0 8 * * *', 'timezone' => 'America/New_York'),
);
```

**5. The button.** Push needs the user's permission, asked from a click. Add to the loans page of
the course, `application/views/Loans/index.pug`, at the end of `block content`:

```pug
    button.btn.btn-primary#alerts(type='button') Turn on alerts
    script(src=$home . 'public/js/notify.js')
    script.
      document.getElementById('alerts').addEventListener('click', async () => {
        const status = await Notify.enable()
        if (status === 'install-needed') alert('On iPhone and iPad, add the app to your Home Screen first')
      })
```

**6. Try it.** Sign in as the administrator, open `http://localhost:8080/loans`, press *Turn on alerts* and allow notifications. Then:

```bash
docker compose exec dev-box php caligrafer.php notify test 1          # 1: the administrator's user id
```
```json
{
    "push": 1,
    "email": false,
    "skipped": null
}
```

A *Notifications work* message appears. With a loan due today or late:

```bash
docker compose exec dev-box php caligrafer.php schedule run remind-loans     # remind-loans: ok
docker compose exec dev-box php caligrafer.php call remind_loans             # {"data": {"due": 0, "late": 1, "notified": 1}}
```

Before any browser turned push on, the same calls answer `"push": 0` and `"notified": 0`: nothing is sent, nothing
fails.

**Check**

+ `docker compose exec dev-box php caligrafer.php notify status 1` lists `"browsers": 1` under `push`.
+ The *1 book to bring back* notification opens `/loans` on a tap.
+ The table `caligrafy_notifications` has one line per message (never its body).

## Reference: `Notify::user`

```php
$result = Caligrafy\Notify::user($userId, array(
    'title'   => 'Kindred is due tomorrow', // required, at most 80 characters
    'body'    => 'Bring it back by Friday.', // required, at most 300 characters
    'url'     => '/books/3',               // opened on a tap: a path of the app, or an https:// address
    'tag'     => 'loan-17',                // optional: a new message with the same tag replaces the old one
    'urgency' => 'high',                   // optional: low, normal (default) or high
));
// array('push' => 1, 'email' => true, 'skipped' => null)
```

+ `$userId` is the id `$context->userId()` gives (in a job, the id of each user it works for).
+ `push` is how many browsers got it; `email` whether an email was sent.
+ A delivery failure never throws. A bad message throws `ServiceError::invalid` with the fields.
+ At most `NOTIFY_RATE_PER_HOUR` messages per user and hour (30): above it nothing is sent (`'skipped' => 'rate'`).
+ Every call is logged in `caligrafy_notifications` (who, the title, the tag, what each channel did; never the body),
  kept 30 days.
+ Without the tables (`platform install`) nothing is sent and nothing fails.
+ When you delete a user, call `Caligrafy\Notify::forget($userId)`: their browsers, address, choices and log go.

### Settings

| `.env` | Default | |
|---|---|---|
| `NOTIFY_CHANNELS` | `push,email` | The channels the app offers |
| `NOTIFY_RATE_PER_HOUR` | `30` | Messages per user and hour |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` | empty | Written by `notify keys`; never replaced (new keys cut off every subscribed browser) |
| `APP_URL` | empty | The app's address, for the links of emails sent by jobs and the command line (Caligrafy Cloud sets it) |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM` | | The emails' server and sender; without them email reports `not-configured` and push still works |

On **Caligrafy Cloud** each app gets its web push keys at its first deploy, kept across deploys.

| Command | |
|---|---|
| `php caligrafer.php notify keys [--subject mailto:you@example.com]` | writes the web push keys to `.env`, once |
| `php caligrafer.php notify test <user id>` | sends that user a test message on every channel they turned on |
| `php caligrafer.php notify status <user id>` | the channels, the browsers, the email address and whether it is confirmed |

### `notify.js`

| | |
|---|---|
| `Notify.status()` | `unsupported`, `install-needed` (iPhone and iPad outside the Home Screen app), `denied`, `off` or `on` |
| `Notify.enable()` | asks permission, subscribes this browser, saves it; returns the new status. Call it from a click |
| `Notify.disable()` | unsubscribes this browser |
| `Notify.test()` | sends a test message to the signed-in user |
| `Notify.email(address)` | sets the address and emails a link to confirm it (24 hours) |
| `Notify.channel('email', false)` | turns a channel off (or on) |
| `Notify.info()` | what `notify_status` answers |

It registers `public/sw.js`, which shows the message with the browser closed and, on a tap, focuses a tab of its
address or opens one. Keep an in-page banner for when a page is open: notifications are for when it is not.

### Email

+ **Only to a confirmed address.** `Notify.email()` (the `notify_email_set` capability) sends a link valid 24 hours,
  used once. Nothing else is sent there before.
+ **Unsubscribe in one click:** every email has a signed link and the `List-Unsubscribe` and `List-Unsubscribe-Post`
  headers that mail apps show as a button (`Route::notifications()` answers them).
+ **In your app's look:** the emails take the colors, font and corners of your
  [design style](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles) (`DESIGN_STYLE`), else Caligrafy's.

### Built-in capabilities

They ship with the framework, so pages, REST and the JS client use them with no code of yours. Each acts only on the
signed-in user's own data; none is an MCP tool (only a browser can subscribe or open a link).

| Capability | Input |
|---|---|
| `notify_status` | none: the channels and their state |
| `notify_subscribe` | the browser's `PushSubscription` JSON |
| `notify_unsubscribe` | `endpoint` |
| `notify_email_set` | `email` (sends the confirmation) |
| `notify_email_confirm` | `token` (public: the email's link) |
| `notify_preferences` | `channel`, `enabled` |
| `notify_test` | none |

### Where push works

Chrome, Edge and Firefox on computers and Android; Safari on Mac; Safari on iPhone and iPad once the app is added to
the Home Screen ([install to home screen](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#install)). A
device that is off gets the message when it comes back, within 24 hours. Push needs HTTPS (or `localhost`).

## Common mistakes

+ **`Notify.enable()` does nothing.** It was called on page load: browsers ask permission only from a click.
+ **`"push": 0` for everyone.** No browser turned push on yet, or the VAPID keys changed (`notify keys` was run on a new
  `.env`): every browser must turn it on again.
+ **Emails from a job have no links.** `APP_URL` is empty: set it to the app's address.
+ **`"email": false`.** The user has no confirmed address, or `MAIL_*` is not set.
+ **Nothing on iPhone.** The app must be added to the Home Screen, and served over HTTPS.

## Related pages

+ [Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs)
+ [Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs)
+ [Views: install to home screen](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#install)

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
