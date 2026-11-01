# Release notes: 2.7.0

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.7.0
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

Caligrafy Quill **2.7.0** goes from an idea to an application online without handing the work to anyone. Pages work online exactly as they do on your computer, Caligrafy Cloud signs you in with an approval in your browser, and Claude ships a project by itself. Nothing breaks in existing projects. The full list is in the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

## Pages call their routes with the session

A page used to call its own JSON routes with the project's shared API key. That key is public, since every visitor's browser receives it, and Caligrafy Cloud turns it off (`LEGACY_API_KEY=false`). A page that worked locally was then refused online with `[ERROR 1004] Access Denied`.

Now a page calls its controller routes the way it calls capabilities: with the session and its CSRF token.

```js
axios.post(env.home + 'facelogin/start', body, { headers: apiHeaders() })
```

+ `apiHeaders()` (in `public/js/services/app.js`, loaded by every client app) sends `Accept` and `X-CSRF-Token`; axios sets `Content-Type` itself, for JSON and for file uploads. The controller sees the signed-in person, or a visitor, exactly as for a form post.
+ `env.apiKey` is `null` once the legacy key is off.
+ `[ERROR 1004] Access Denied` now says why: no CSRF token, a token that is unknown or does not grant enough, the legacy key turned off, the API not activated, or a key from another application.
+ `doctor` warns about client app scripts that still send the key.
+ The face-login recipe, the bot template and the documentation examples now use `apiHeaders()`.

Programs and AI agents keep using [personal access tokens](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP).

## Caligrafy Cloud: sign in with an approval

```bash
php caligrafer.php cloud login
```

You no longer create a token and paste it. The command shows a code and opens `https://deploy.caligrafy.com/cli?code=...`. You sign in there (or sign up first), check that the page shows the same code, and approve. The command then has its token.

+ From the Docker server, which cannot open your browser, it prints the address for you to open.
+ `--token` and `--paste` still work.
+ The dashboard lists your tokens and revokes them.

A private repository's read-only deploy key is saved in `.caligrafy/<name>-deploy-key.pub` when the app is created, and `cloud key <name>` prints it again. Add it with `gh repo deploy-key add`.

**Every new app is free for its first 30 minutes online**, from its first deploy. The free minutes used to be once per account.

## Claude ships it: `/caligrafy-quill:ship`

The Claude Code plugin 1.19.0 adds `/caligrafy-quill:ship <name>`. It puts the project online and does every step itself:

1. It commits the work.
2. It creates the GitHub repository and pushes it. It uses your SSH key when GitHub knows it, otherwise HTTPS with the GitHub CLI. Otherwise it signs the GitHub CLI in with a code you approve on the GitHub page it opens.
3. It signs in to Caligrafy Cloud with the approval above, creates the app and adds its deploy key.
4. It deploys the app and checks that it answers.

You only approve in your browser, and Claude asks before spending credits. [`/caligrafy-quill:idea`](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude) now asks you once, at the plan, then builds and ships the application by itself.

## Upgrading from 2.6.0

1. Update the framework: `git pull`, or copy `framework/`, `caligrafer.php`, `.bin/`, `claude-plugin/` and `public/js/services/app.js` from the release.
2. Run `php caligrafer.php upgrade`, then `php caligrafer.php upgrade --apply`. This replaces `views/Client/app.pug` if you never changed it.
3. In your client apps, replace `'Authorization': 'Bearer ' + apiKey` with `apiHeaders()`. `php caligrafer.php doctor` lists the scripts that still send the key.

>[Back to the documentation](https://github.com/caligrafy/caligrafy-quill/wiki)
