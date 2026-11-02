# Release notes: 2.13.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.13.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

![Caligrafy: Build Once, Experience Everywhere](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/build-once-banner.png)

Caligrafy Quill **2.13.0** lets you sell what you built: offer an app hosted on Caligrafy Cloud on the
[marketplace of caligrafy.com](https://caligrafy.com/marketplace), where Caligrafy sells a prompt that recreates it,
and earn 25% of every sale.

## The marketplace skill

```
/caligrafy-quill:marketplace myapp
```

After you offer the app on its page in the App console, this skill of the Claude Code plugin does the rest in the
app's folder:
- reads the whole code;
- runs the app on your computer as a separate Docker project (its own free ports and a throwaway database, so it never
  touches your other projects or your local data), creates an account and invented sample data through the app's own
  pages, and walks through it to screenshot its key features: 5 at most, all different, at 1280x800. A camera app sees
  a realistic picture or clip instead of a test pattern. Without a browser it can drive, it uses its own runner
  (Playwright's Chromium, installed once outside the project);
- writes the title, the description, the prompt that recreates the whole app with Caligrafy and an estimated price
  window from a short market study, in one private file: `.caligrafy/marketplace.json` (ignored by git);
- sends it and redeploys the app.

Caligrafy reviews the listing, sets the final price and publishes it, or turns it down with a reason. See
[Selling your app on the marketplace](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online#marketplace).

## `php caligrafer.php cloud submit <name>`

Sends `.caligrafy/marketplace.json` (or `--file <path>`) and the screenshots it names to Caligrafy Cloud with the
project's Cloud sign-in, then deploys the app (`--no-deploy` to skip, `--no-wait` not to follow the build). The same
screenshot sent twice is sent once.

## Updating a project

```bash
php caligrafer.php update
```

Or, in Claude Code, `/caligrafy-quill:update`. Nothing in your application changes; the plugin gets the new skill.
