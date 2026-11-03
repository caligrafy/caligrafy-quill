# Release notes: 2.16.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.16.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

![Caligrafy: Describe it. Approve it. Ship it.](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/describe-approve-ship-banner.png)

Caligrafy Quill **2.16.0** answers unknown addresses with a real **404**.

## Unknown addresses: 404, not the home page

Until now an address no route declared ran the first route of `web.php`, usually the home page, with status 200: a
mistyped link looked like the home page, and search engines could count broken links as real pages. Now:

+ an address no route declares answers **404 Page Not Found** (a page, or `{"error": ...}` to an API call);
+ an address that exists, but not with that method (a `DELETE` on a page), answers **405** with an `Allow` header;
+ `/` and every declared route answer as before, and `HEAD` is answered like `GET`.

Nothing to change in your routes. If a link of yours relied on landing on the home page, give it its own route.

The Claude Code plugin (1.28.0) knows it (gotcha G4).

## Updating a project

```bash
php caligrafer.php update
```

When the update ran in Docker, run `php caligrafer.php plugin` on your computer in the project's folder, then restart
`claude` there.

See the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md) for every change.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
