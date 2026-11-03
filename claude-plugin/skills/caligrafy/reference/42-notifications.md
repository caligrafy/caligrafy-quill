# Notifications: web push and email

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Notifications
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

![Caligrafy: Describe it. Approve it. Ship it.](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/describe-approve-ship-banner.png)

**Notifications** reach your users when none of your pages are open: a price that hit its target, a booking
tomorrow, an order shipped. One call sends the message on every channel the user turned on: **web push** (a
notification on their phone or computer, browser closed) and **email** (a confirmed address). Since 2.15.0.

## Sending

From any capability, a [scheduled job](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs) included:

```php
$result = Caligrafy\Notify::user($userId, array(
    'title'   => 'Your price hit: $42',        // required, at most 80 characters
    'body'    => 'Trail shoes at Shop A.',     // required, at most 300 characters
    'url'     => '/watches/17',                // opened on tap: a path of the app, or an https:// address
    'tag'     => 'watch-17',                   // optional: a new message with the same tag replaces the old one
    'urgency' => 'high',                       // optional: low, normal (default) or high
));
// array('push' => 2, 'email' => true, 'skipped' => null)
```

+ `$userId` is the id `$context->userId()` gives (in a job, the id of each user the job works for).
+ A delivery failure never throws: the result says what went out. A bad message throws `ServiceError::invalid`.
+ At most `NOTIFY_RATE_PER_HOUR` messages per user and hour (30): above it nothing is sent (`'skipped' => 'rate'`).
+ Every call is in the table `caligrafy_notifications` (who, the title, the tag, what each channel did; never the
  body), kept 30 days.
+ When you delete a user, call `Caligrafy\Notify::forget($userId)`: their browsers, address, choices and log go.

## Setting it up

```bash
php caligrafer.php notify keys --subject mailto:you@example.com   # the web push keys, once
php caligrafer.php platform install                               # the notification tables
```

`notify keys` writes `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` and `VAPID_SUBJECT` to `.env`, and never replaces
existing keys: new keys cut off every browser that turned push on. On **Caligrafy Cloud** you do nothing: each app gets
its keys at its first deploy, kept across deploys.

In `application/config/routing/web.php` (new projects have both from `quickstart`):

```php
Route::capabilities('/capabilities');   // what notify.js calls
Route::notifications();                 // the pages the emails link to: confirm, unsubscribe
```

| `.env` | Default | |
|---|---|---|
| `NOTIFY_CHANNELS` | `push,email` | The channels the app offers |
| `NOTIFY_RATE_PER_HOUR` | `30` | Messages per user and hour |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` | empty | Written by `notify keys` |
| `APP_URL` | empty | The app's address, for the links of emails sent by jobs (Caligrafy Cloud sets it) |
| `MAIL_HOST`... `MAIL_FROM` | | The emails' server and sender; without them email reports `not-configured` and push still works |

## In a page: "Turn on alerts"

```pug
script(src=$home . 'public/js/notify.js')
button.btn.btn-primary#alerts(type='button') Turn on alerts
script.
  document.getElementById('alerts').addEventListener('click', async () => {
    const status = await Notify.enable()   // asks permission: call it from a click
    if (status === 'install-needed') alert('On iPhone and iPad, add the app to your Home Screen first')
  })
```

| `notify.js` | |
|---|---|
| `Notify.status()` | `unsupported`, `install-needed` (iPhone and iPad outside the Home Screen app), `denied`, `off` or `on` |
| `Notify.enable()` | Asks permission, subscribes this browser, saves it; returns the new status |
| `Notify.disable()` | Unsubscribes this browser |
| `Notify.test()` | Sends a test message to the signed-in user |
| `Notify.email(address)` | Sets the address and emails a link to confirm it (24 hours) |
| `Notify.channel('email', false)` | Turns a channel off (or on) |

It registers `public/sw.js`, which shows the message with the browser closed and, on a tap, focuses a tab of its
address or opens one. Keep your in-page banner for when a page is open: notifications are for when it is not.

## Email

+ **Only to a confirmed address.** `Notify.email()` (or the `notify_email_set` capability) sends a link valid 24
  hours, used once. Nothing else is sent there before.
+ **Unsubscribe in one click:** every email has a signed link and the `List-Unsubscribe` and `List-Unsubscribe-Post`
  headers that mail apps show as a button.
+ **In your app's look:** the emails take the colors, font and corners of your
  [design style](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles) (`DESIGN_STYLE`), else Caligrafy's.

## Built-in capabilities

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

## Where push works

Chrome, Edge and Firefox on computers and Android; Safari on Mac; Safari on iPhone and iPad once the app is added to
the Home Screen ([install to home screen](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#install)). A
device that is off gets the message when it comes back, within 24 hours. Push needs HTTPS (or `localhost`).

Test it: `php caligrafer.php notify test <user id>` sends a test message, `notify status <user id>` shows the user's
channels.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
