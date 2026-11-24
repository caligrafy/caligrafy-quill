# __APP_NAME__: the iOS and Android app

The phone and tablet app of this Caligrafy project (`php caligrafer.php mobile`, Caligrafy Quill 2.27.0+): a Vue app
that Capacitor turns into an iOS and an Android app. It signs its user in through the site and calls the project's
capabilities as that person, so the rules of the web pages, REST and the AI agents are the rules of the app too.

| | |
| --- | --- |
| App id | `__APP_ID__` (the iOS bundle id, the Android package and the sign-in's URL scheme) |
| The site it calls | `.env.development` while it is built, `.env.production` (__API_URL__) in the store builds |
| Its pages | `src/components/pages/`, `src/router/index.js` |
| Signing in, calls | `src/mobile/session.js`: `signIn()`, `signOut()`, `currentUser()`, `call('list_books', { page: 1 })` |
| Notifications | `src/mobile/push.js`: `enablePush()`, `onNotificationOpened()` |
| Native projects | `ios/` (Xcode) and `android/` (Android Studio), made by `npx cap add` and set up by `npm run native` |
| Store builds | `fastlane/Fastfile` (a Mac or GitHub Actions: `.github/workflows/mobile-__NAME__.yml`) |

## The site

In `application/config/routing/web.php`, after `Route::auth()` and `Route::capabilities()`:

```php
Route::mobile(['scheme' => '__APP_ID__']);
```

The app opens the site's own sign-in page in the system browser, the person signs in (or creates an account) and
allows this device, and the site sends them back to the app with a one-time code that the app trades for a personal
access token of that person (PKCE). The app never sees a password. Options: `scopes` (`['*']`: what the app may call),
`days` (90: how long it stays signed in), `origins`, `name`. Signing out in the app revokes its token.

## Building it

Node.js 22+. In this folder:

```bash
npm install
npm run dev            # the app in a browser, against the Docker server of the project (http://localhost:8080)
npm run ios            # a development build, copied into ios/, then Xcode: Run on a simulator
npm run android        # the same for Android Studio: an emulator
npm run release:ios    # the build the stores get: it calls the site online (.env.production); fastlane uses it
npm run release:android
```

The development builds (`ios`, `android`, `sync`) call the local site over plain http: the iOS simulator at
`localhost`, the Android emulator at `10.0.2.2` (the app swaps it by itself), and `scripts/native.mjs --dev` lets the Android
web view make those calls. Plain http is allowed to this computer's addresses only. A real phone cannot reach this
computer that way: try a phone with the release build against the site online.

`npm run dev` signs in through the local site too: the sign-in page sends the code back to the browser, only while the
site runs on this computer. Every change to the web code: `npm run sync` (or `npm run ios` / `android`) copies it into
the native projects. `npm run icons` makes every icon and splash size from `assets/` (1024 px `icon-only.png`, 2732 px
`splash.png`).

## Notifications

One `Notify::user($id, ['title' => ..., 'body' => ..., 'url' => ...])` on the server reaches the person's browsers,
email and phones. The phones need, on the site (`php caligrafer.php cloud secret set <app> NAME=value` online, `.env`
locally):

| Setting | Where it comes from |
| --- | --- |
| `APNS_KEY` | Apple Developer, Certificates, Identifiers & Profiles, Keys: a key with Apple Push Notifications service. The `.p8` file's text, or that text in base64 |
| `APNS_KEY_ID`, `APNS_TEAM_ID` | The key's id, and the team id (Membership) |
| `APNS_TOPIC` | `__APP_ID__` |
| `APNS_ENVIRONMENT` | `sandbox` while the app runs from Xcode; leave it out for TestFlight and the App Store |
| `FCM_SERVICE_ACCOUNT` | Firebase console, Project settings, Service accounts, Generate new private key: the JSON, or that JSON in base64 |

and, in the app: the **Push Notifications** capability in Xcode (Signing & Capabilities, once), and
`android/app/google-services.json` (Firebase console, Project settings, Your apps, Android app `__APP_ID__`). Without
that file push stays off on Android (registering would stop the app).

## Publishing

What only the owner can do, once: the accounts, and the app's entry in each store. Then every release is
`/caligrafy-quill:ship` (or `gh workflow run mobile-__NAME__.yml`): iOS goes to TestFlight, Android to the internal
testing track of Google Play; from there, the owner sends it to review in App Store Connect and Play Console.

### Apple (App Store, TestFlight)

1. An Apple Developer Program membership (99 USD a year): https://developer.apple.com/programs/enroll/
2. App Store Connect, Apps, +: a new app with the bundle id `__APP_ID__` (register it first in Certificates,
   Identifiers & Profiles, Identifiers, with Push Notifications on).
3. App Store Connect, Users and Access, Integrations, App Store Connect API: a team key with the **Admin** role (Xcode
   signs the builds with it: no certificate or profile to keep). Download its `.p8` once.
4. The repository's secrets (GitHub, Settings, Secrets and variables, Actions):
   `APPLE_TEAM_ID`, `ASC_KEY_ID`, `ASC_ISSUER_ID`, `ASC_KEY_P8` (`base64 -i AuthKey_XXXX.p8`).

### Google (Google Play)

1. A Google Play developer account (25 USD once): https://play.google.com/console/signup
2. Play Console, Create app. The first build is uploaded by hand (Test and release, Internal testing): build it once
   with the lane below on a computer, or from Android Studio (Build, Generate Signed App Bundle).
3. The upload key, made once and kept safe (losing it means asking Google to reset it):
   `keytool -genkeypair -v -keystore upload.jks -alias upload -keyalg RSA -keysize 2048 -validity 10000`
4. A service account that may release (Google Cloud console, IAM, Service accounts, a JSON key; then Play Console,
   Users and permissions, invite its email with Release permissions).
5. The repository's secrets: `ANDROID_KEYSTORE` (`base64 -i upload.jks`), `ANDROID_KEYSTORE_PASSWORD`,
   `ANDROID_KEY_ALIAS` (`upload`), `ANDROID_KEY_PASSWORD`, `PLAY_SERVICE_ACCOUNT` (the JSON), and for push
   `GOOGLE_SERVICES_JSON` (the file's text). Once the app is published: the variable `PLAY_RELEASE_STATUS=completed`
   (until then Google only takes drafts).

### On a Mac instead of GitHub

Xcode (App Store), Android Studio, Ruby 3.3 (`brew install ruby`), then in this folder, with the same names in the
environment: `bundle install`, `npm run release:ios` then `bundle exec fastlane ios beta`, or `npm run release:android`
then `bundle exec fastlane android beta`.
Never put those values in a file of the repository.
