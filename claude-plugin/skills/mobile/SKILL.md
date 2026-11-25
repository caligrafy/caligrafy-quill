---
name: mobile
description: Give a Caligrafy Quill project an iOS and Android app (2.27.0+) - php caligrafer.php mobile <name> creates public/<name> (Vue + Capacitor 8, the native ios/ and android/ projects), Route::mobile signs its user in through the site (PKCE, a personal access token, CORS) and the app calls the capabilities as that person, push notifications reach the phones through Notify (Apple APNs, Firebase), and the store builds go to TestFlight and Google Play with fastlane, on a Mac or on GitHub Actions. Also walks the owner through the accounts, tools and secrets the stores need.
when_to_use: The user wants a mobile, phone, tablet, iPhone, iPad, Android, iOS, App Store or Google Play app of their Caligrafy project, push notifications on phones, to run the app on a simulator or a phone, to publish it to TestFlight or Google Play, or to set up their Apple or Google developer accounts and signing for it.
argument-hint: "[<name>] [--id com.example.app] [publish]"
---

# A phone and tablet app of the project

Target: `$ARGUMENTS` (the app folder, `public/<name>`; `publish` jumps to step 5). Read first:
[gotchas.md](../caligrafy/reference/gotchas.md) G175-G182 and the API reference's Route::mobile and Notify sections.

The app is the project's capabilities on a phone: never a second backend. Every screen reads and writes through
`call('<capability>', input)` (`src/mobile/session.js`), as the signed-in person, under the same rules as the web pages,
REST and MCP. A feature the app needs is a capability first (`/caligrafy-quill:new-capability`). A `webOnly: true`
capability is not reachable from the app (it needs the session): give the app a capability of its own.

Never ask for, read, print or write an Apple or Google secret (the `.p8` keys, the keystore, its passwords, a service
account JSON). The owner downloads them and stores them as secrets themselves; say exactly which command or page.

## 1. Ready

```bash
php caligrafer.php version          # 2.27.0+ (else /caligrafy-quill:update)
node -v                             # v22+ on this computer (the Docker dev-box has no Node.js)
grep -n "Route::auth\|Route::capabilities\|Route::mobile" application/config/routing/web.php
```
The project needs `Route::capabilities('/capabilities')` (what the app calls). With `Auth::authentication(...)` and
`Route::auth()`, people sign in to the app with their site account; without accounts (2.27.2+), `Route::mobile` only
lets the apps call the public capabilities (CORS). When the site's pages already exist and are not a Vue app (a game),
the app can bundle them instead of the template's `src/` (G183).

## 2. Create it

```bash
php caligrafer.php mobile <name> --id <app id> --display "<Name>" --url https://<project>.caligrafy.com
```
+ **The app id** (`com.example.books`): the iOS bundle id, the Android package and the URL scheme the sign-in comes
  back through. Lowercase letters and digits in two or more parts separated by dots (no `-`, no `_`: G176). It cannot
  change once the app is in a store: ask the owner which domain or name to use (default `com.<project>.<name>`).
+ **--url**: the site the store builds call (`.env.production`). Online at Caligrafy Cloud it is
  `https://<app>.caligrafy.com` (`cloud apps`).
+ It runs `npm install`, `npx cap add ios`, `npx cap add android`, `npm run native` and `npm run icons`, and writes
  `.github/workflows/mobile-<name>.yml`. Without Node.js 22 it creates the folder and prints what to run.

Then in `web.php`, after `Route::auth(...)` and `Route::capabilities(...)`:
```php
Route::mobile(['scheme' => '<app id>']);
```
Options: `scopes` (`['*']`: narrow it, `['books:read', 'books:write']`, when the app needs less than the person may do),
`days` (90), `origins` (another origin that calls with a token), `name` (on the consent page), `view` (2.31.0+: the
consent page in the site's own look, a copy of `framework/views/mobile/authorize.pug` in `application/views/Auth/mobile.pug`
that extends the site's layout and keeps its form: G188).

## 3. Build its screens

`src/components/pages/*.vue` and `src/router/index.js` (hash history, `meta: { signedIn: true }` for the pages behind the
sign-in). Data: `await call('list_books', { page: 1 })`; errors carry `status`, `code` and `fields` (show `fields[name]`
under its field). The look is Caligrafy's (`layout.css`, `look.css` and the design style, bundled into the app): the
UI guidelines apply; keep touch targets 44 px high and text 16 px or more on phones.

Check it in a browser first: `php caligrafer.php server start`, then `cd public/<name> && npm run dev` and sign in (the
site sends the code back to the browser only while it runs on this computer, G178). Drive it with a browser tool at a
phone size (390x844), sign in, use every screen.

On a simulator (development builds call the local site over http, G181): `npm run ios` opens Xcode (Run),
`npm run android` Android Studio. Without the IDEs:
+ iOS (Xcode, its license accepted, the iOS platform downloaded: G182): `npm run sync`, then `xcodebuild -project
  ios/App/App.xcodeproj -scheme App -sdk iphonesimulator -configuration Debug -derivedDataPath build/ios-sim
  -destination 'generic/platform=iOS Simulator' build`, and launch
  `build/ios-sim/Build/Products/Debug-iphonesimulator/App.app` with this session's iOS simulator tool.
+ Android (an SDK with an emulator, `JAVA_HOME` on Java 21): `npm run sync`, `cd android && ./gradlew assembleDebug`,
  start the emulator, `adb install -r app/build/outputs/apk/debug/app-debug.apk`, then drive it with `adb shell input`
  and read it with `adb exec-out screencap -p` and `uiautomator dump`. A fresh emulator's Chrome shows a first-run
  screen: start Chrome with `--disable-fre --no-first-run` (`/data/local/tmp/chrome-command-line` and
  `am set-debug-app --persistent com.android.chrome`) rather than accepting its terms.
Walk the whole sign-in on each: Sign in, the site's page, Allow, back in the app, a restart (still signed in), Sign out
(the token revoked: `/mobile/session` answers 401).

## 4. Notifications (when the app sends any)

Server side, one call: `Notify::user($userId, ['title' => ..., 'body' => ..., 'url' => ...])` (browsers, email,
phones, and the in-app inbox since 2.32.0: a bell in the app reads it with `call('notify_inbox')` and marks it with
`call('notify_read', { ids })`). The phones need the site's settings (the owner sets them: `.env` locally, `php caligrafer.php cloud secret set
<app> NAME=value` online) and the app's set-up; the README of `public/<name>`, Notifications, lists each one and where
it comes from (Apple: `APNS_KEY`, `APNS_KEY_ID`, `APNS_TEAM_ID`, `APNS_TOPIC`; Firebase: `FCM_SERVICE_ACCOUNT` and
`android/app/google-services.json`). In Xcode, once: Signing & Capabilities, + Capability, Push Notifications.
Test: `php caligrafer.php notify status <user id>` (`devices`, `apns`, `fcm`), then `notify test <user id>`.
Push from Xcode needs `APNS_ENVIRONMENT=sandbox` (G179).

## 5. Publish: the accounts and environments, with the owner

Go through it with the owner one step at a time, checking each before the next (what is there already: `gh auth
status`, `xcodebuild -version`, `gh secret list`). What only they can do (enrol, pay, accept agreements, download a
key, type a password) they do in their browser; open the page for them and say in one line what it asks.

1. **The accounts**: Apple Developer Program (99 USD a year, https://developer.apple.com/programs/enroll/, enrolment
   can take a day or two for an organization: it needs a D-U-N-S number) and Google Play Console (25 USD once,
   https://play.google.com/console/signup; a new personal account must run a closed test with 12 testers for 14 days
   before it can publish to production, G180).
2. **The apps in the stores**: App Store Connect, the bundle id registered (Identifiers, with Push Notifications) and a
   new app; Play Console, Create app.
3. **The keys** (the README, Publishing, has each click): the App Store Connect API key (Admin), the Android upload
   keystore (`keytool`), the Google Play service account (Release permissions), Firebase for Android push.
4. **The secrets of the repository**, typed or piped by the owner, never through this conversation:
   `gh secret set ASC_KEY_P8 < <(base64 -i AuthKey_XXXX.p8)`, `gh secret set APPLE_TEAM_ID`, ... (`gh secret set NAME`
   asks for the value without echoing it). `gh secret list` then shows the names only.
5. **The first Android build is uploaded by hand** (Google needs one before its API takes any): build it once on the
   Mac (`bundle exec fastlane android beta` with the variables exported in the owner's terminal, or Android Studio,
   Generate Signed App Bundle) and upload it in Play Console, Internal testing.
6. **Each release** (the workflow builds with `npm run release:ios` / `release:android`): bump `version` in
   `public/<name>/package.json`, commit, push, then
   `gh workflow run mobile-<name>.yml -f platform=both` and follow it (`gh run watch`). iOS lands in TestFlight (a few
   minutes of processing), Android on the internal track. Sending a build to review (App Store, Production) is the
   owner's click in App Store Connect and Play Console: Apple needs screenshots, a privacy policy URL, the privacy
   labels and, for an app with accounts, a way to delete the account inside the app (G177).

A Mac with Xcode builds iOS locally too (`bundle exec fastlane ios beta`), with the same variables; GitHub's Mac
runners need nothing on the owner's computer (macOS minutes count 10x on private repositories).

## 6. Report

The app's folder and id, what runs (browser, simulator, phone), the `Route::mobile` line, the push set-up, and for
publishing: which accounts and secrets exist, the workflow run's link, the TestFlight and Play Console pages. What is
left for the owner, as a short list.
