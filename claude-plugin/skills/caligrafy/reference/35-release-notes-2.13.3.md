# Release notes: 2.13.3

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.13.3
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.13.3** is a fix release of [2.13.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.13.0).

## A form with no method leaks its fields

A form without `method` is a GET form. When a script sends it (the JS client), it works while the script runs; when the
script is not loaded, fails or is still loading, the browser sends the form itself, as a GET: every field, a password
included, goes into the address, the browser history and the server's logs. It happened on Caligrafy Cloud's sign-up
page, whose view did not load its script.

+ Every form has `method='POST'`, also when a script sends it: the Tasks example and the template payment page do now.
  Without its script, such a form is refused by the CSRF check instead of showing its fields in an address.
+ `php caligrafer.php doctor` warns about a Pug form of `application/views` with an email or a password field and no
  method, with the file and the line.
+ The Claude Code plugin knows it (gotcha G104): the pages it writes have a method on every form, load their script,
  show each field's error under its field, and are checked by sending each form once (the address must not get a `?`).

## Updating a project

```bash
php caligrafer.php update
php caligrafer.php doctor
```

If `doctor` names a form, add `method='POST'` to it. When the update ran in Docker, run `php caligrafer.php plugin`
on your computer in the project's folder, and restart `claude` there.

See the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md) for every change.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
