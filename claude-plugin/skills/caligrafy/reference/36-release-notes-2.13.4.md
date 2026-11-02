# Release notes: 2.13.4

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.13.4
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

![Caligrafy: Describe it. Approve it. Ship it.](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/describe-approve-ship-banner.png)

Caligrafy Quill **2.13.4** is a fix release of [2.13.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.13.0).

## Caligrafy accounts

Your Caligrafy account is the same on caligrafy.com and Caligrafy Cloud:

+ **Your email is your username.** *Create account* asks for it and for your password twice.
+ **Confirm your email to sign in.** Open the link of the email from Caligrafy; until then the sign-in page says
  *Confirm your email first* and can send the link again.
+ **Forgot your password?** on the sign-in page emails you a 6-digit code (10 minutes). Enter it with your new password.

The Claude Code plugin knows it: `/caligrafy-quill:ship` tells you what to do at each step, and never asks you for your
password or a code. `php caligrafer.php cloud account` asks to open the confirmation link when it is not done yet.

## Updating a project

```bash
php caligrafer.php update
```

When the update ran in Docker, run `php caligrafer.php plugin` on your computer in the project's folder. Then restart
`claude` there.

See the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md) for every change.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
