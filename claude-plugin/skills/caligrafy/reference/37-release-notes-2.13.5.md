# Release notes: 2.13.5

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.13.5
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.13.5** is a fix release of [2.13.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.13.0).

## A new column ships with the code

Add a column to a table that is already online by adding a line to its `create_db.sql`:

```sql
ALTER TABLE books ADD summary TEXT NULL;
```

`php caligrafer.php deploy` runs the changed script when the app starts. Until 2.13.5 the line failed the next time the
script ran ("Duplicate column") and stopped every later deploy. Now a line that adds a column, an index or a foreign key
that is already there is skipped, so the line can stay.

## Also fixed

+ `redirect('https://...')` goes to that address; it used to put the site's own address in front.
+ `php caligrafer.php doctor` warns about two Pug mistakes that only show when the page renders: `$40` in a view shows
  nothing (write `&#36;40`), and code on the line of a one-line `- if (...)` or `- foreach (...)` does not compile.

## The Claude Code plugin (1.25.5)

It knows these three fixes, how to restyle the buttons of a colored section of your own page, and the pages of
caligrafy.com: **Listings**, **Earnings**, **Tasks** and **Library**, the three ways to get an app on Explore (its prompt,
its code, or the app managed for you) and **Submit your idea**.

## Updating a project

```bash
php caligrafer.php update
```

When the update ran in Docker, run `php caligrafer.php plugin` on your computer in the project's folder. Then restart
`claude` there.

See the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md) for every change.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
