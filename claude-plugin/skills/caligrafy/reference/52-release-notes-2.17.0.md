# Release notes: 2.17.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.17.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.17.0** serves pages faster: compiled views are kept between requests.

## Faster pages, nothing to change

Every `.pug` view used to be compiled to PHP on every request. Now it is compiled once, and again only when it, or a
file it includes or extends, is saved:

+ pages are the same, **3 to 8 times faster** (the benchmark's HTML page: about 124, now about 941 requests per second);
+ edit a view and reload, as before: the change shows at once;
+ the compiled views stay in a private folder of the temporary directory; a new container starts with an empty one.

`VIEW_CACHE=false` in `.env` turns it off. A project that already set Phug's `cache_dir` keeps its own folder.

On a server **without Docker**, after copying files that keep their old modification times (`rsync -a`, `cp -p`),
empty the folder: `rm -rf /tmp/caligrafy-views-*` ([Views](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#templates)).

The Claude Code plugin (1.29.0) knows it (gotcha G117).

## Updating a project

```bash
php caligrafer.php update
```

When the update ran in Docker, run `php caligrafer.php plugin` on your computer in the project's folder, then restart
`claude` there.
