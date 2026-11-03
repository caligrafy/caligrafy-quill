# Release notes: 2.15.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.15.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

![Caligrafy: Describe it. Approve it. Ship it.](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/describe-approve-ship-banner.png)

Caligrafy Quill **2.15.0** reaches your users when none of your pages are open: **notifications**.

## Notifications

```php
Caligrafy\Notify::user($userId, array('title' => 'Your price hit: $42', 'body' => 'Trail shoes at Shop A.', 'url' => '/watches/17'));
```

One call, every channel the user turned on:

+ **Web push**: a notification on their phone or computer, browser closed (Chrome, Edge, Firefox, Safari; iPhone and
  iPad from the Home Screen app). A **Turn on alerts** button calls `Notify.enable()` of `public/js/notify.js`.
+ **Email**: only to a confirmed address, unsubscribed in one click, in the colors and font of your design style.

Seven built-in capabilities (`notify_status`, `notify_subscribe`...) let pages, REST and the JS client manage it with no
code of yours; a rate limit, a log without the message bodies, and `Notify::forget()` for deleted users.

With a [scheduled job](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs), an app now watches and alerts
on its own. The whole page: [Notifications](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications).

On Caligrafy Cloud each app gets its web push keys at its first deploy, and `APP_URL`. The Claude Code plugin (1.27.0)
knows it: ask Claude for "alert me when..." and it writes the job, the notification and the button.

## Updating a project

```bash
php caligrafer.php update
php caligrafer.php platform install
php caligrafer.php notify keys --subject mailto:you@example.com   # not on Caligrafy Cloud: it makes them
```

Add `Route::capabilities('/capabilities');` (if it is not there) and `Route::notifications();` to
`application/config/routing/web.php`. When the update ran in Docker, run `php caligrafer.php plugin` on your computer in
the project's folder, then restart `claude` there.

See the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md) for every change.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
