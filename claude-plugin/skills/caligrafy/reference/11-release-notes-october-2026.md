# Release notes: October 2026

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-October-2026
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

This page lists every change made to Caligrafy Quill in October 2026, released as **v2.0.0**, the first tagged release, after the security release (`7dce1e4`, see [Security and Upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading)). For each change it explains what changed, why, what you need to do in a new and in an existing project, and what can break. The detailed documentation lives in the pages linked from each section.

> **New projects have nothing to do.** A project created with `composer create-project caligrafy/caligrafy-quill`, or cloned and initialized with `php caligrafer.php initialize`, gets every change below. The "Existing projects" steps only apply to projects initialized with an earlier version.

## Upgrade checklist for existing projects

Do these steps in order, on a branch, and commit after each one.

1. **Requirements.** PHP 8.3 or higher (the Docker image runs PHP 8.5), MySQL 8.4, and Node.js 20.19+ or 22.12+ if you build the Vue single page app.
2. **Update the framework**, then the PHP dependencies:
    ```bash
    git pull                # or download the new release
    composer update         # stable releases; adds lbuchs/webauthn (passkeys) and, for contributors, PHPUnit
    ```
3. **Update your `application` folder** with Caligrafer. The dry run changes nothing:
    ```bash
    php caligrafer.php upgrade            # dry run: missing, identical, outdated, customized files
    php caligrafer.php upgrade --apply    # backs up, then updates the files you never changed
    ```
    Merge every `<file>.caligrafy-new` into your customized file (or replace it with `php caligrafer.php upgrade --force <path>`), then commit `application/.caligrafy-manifest.json` and delete the `backups` folder (it is git-ignored, but `.htaccess` does not deny it). See [caligrafer upgrade](#upgrade).
4. **If you customized the root `.htaccess`**, copy the new `Service-Worker-Allowed` lines and the `AddType application/manifest+json .webmanifest` line from the new release. If you customized `public/css/theme.css`, copy everything from the "Bootstrap 5 compatibility layer" comment to the end of the file. See [Bootstrap 5](#bootstrap).
5. **Add the new `.env` settings** of the modules you use (all optional except `WATSON_URL` for Watson bots and the Coinbase keys for crypto payments). See [`.env` settings](#env).
6. **Update the code that calls the changed APIs**: Stripe Checkout redirects to `$result['url']`, crypto payments to `$transaction['url']`, OpenAI images come back as base64, `Assistant` runs on the Responses API, and errors now have real HTTP status codes. See the [compatibility summary](#compatibility).
7. **Update your client apps in `public/<app>`** (they are copies): `scripts('vue')` and `scripts('axios')` instead of your own CDN links, Bootstrap 5 classes, and compare the bot, FaceDetect and ML apps with `framework/plugins/<type>`. `php caligrafer.php upgrade` lists the files that differ.
8. **Check the project**: `php caligrafer.php doctor` (read-only). Every line that is not `OK` comes with a fix. See [caligrafer doctor](#doctor).
9. **Test in a browser** the pages that use Bootstrap columns, forms, payments, bots and FaceDetect.

If your project predates the security release, first follow the database steps of [Upgrading an existing project](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#upgrading) (`users.passcode` as `varchar(255)`, administrators with `permissions >= 1`).

## Changes at a glance

| Change | Who is affected | Action in an existing project |
| - | - | - |
| [Current libraries and services](#libraries) | Everyone | `composer update`, `caligrafer upgrade`, API changes below |
| [Bootstrap 5 and the Caligrafy look](#bootstrap) | Pug views, client apps | Bootstrap 5 classes in your own views |
| [Vue 3.5 and the Vite 8 SPA template](#vue) | Vue apps | Pinned `scripts('vue')`; Pinia instead of Vuex in SPAs |
| [OpenAI GPT-6 and gpt-image-2](#openai) | OpenAI | Read images from `b64_json` |
| [Assistant 2.0](#assistant) | Assistants | Same methods; re-create your assistants |
| [Stripe Payment Element and Checkout URL](#stripe) | Payments | Redirect to `$result['url']` |
| [Coinbase Business](#coinbase) | Crypto payments | New keys, redirect to `$transaction['url']` |
| [Watson on `WATSON_URL`](#watson) | Bots | `WATSON_URL` in `.env` |
| [ML Core 2.0 and face-api](#ml) | ML and FaceDetect apps | Re-create or compare the apps |
| [FaceDetector camera control and face prints](#camera) | FaceDetect apps | Optional |
| [Real HTTP status codes](#errors) | API clients, `web.php` | `Errors::handle($e)` in the routes `catch` |
| [`php caligrafer.php upgrade`](#upgrade) | Existing projects | Run it |
| [Namespaced controllers](#controllers) | Controllers | None (code that extends an example controller: use its full name) |
| [`php caligrafer.php doctor`](#doctor) | Everyone | Run it when something fails |
| [Versioned releases](#version) | Everyone | Optional: require `caligrafy/caligrafy-quill:^2.0` instead of `dev-master` |
| [Install to home screen](#install) | Pug layout | `'install' => true`, optional `PWA_SERVICE_WORKER` |
| [Face login security and passkeys](#facelogin) | Face login | Use `FaceLogin::verify()`; passkeys for sensitive accounts |
| [Agent providers](#agent) | Open model agents | Optional `AGENT_PROVIDER` |
| [Unit tests and the QA suite](#testing) | Contributors | None |
| [Claude Code plugin 1.2.0 to 1.9.1](#plugin) | Claude Code users | Updates itself |

## Current libraries and services

**What changed.** Every library and service moved to a current, supported version:

| Area | Before | Now |
| - | - | - |
| PHP | 7.2+ | 8.3+ (`composer.json` requires `^8.3`); PHP deprecation notices are never printed into pages |
| Composer packages | `dev-master` branches | Stable releases: stripe-php 22, Phug 2, Pug-php 3.6, PHPMailer 7, phpdotenv 5.8, GUMP 2.3, SimpleImage 4.4, groq-php 1.4, lbuchs/webauthn 2.2 |
| Docker | `php:8.2.2`, `mysql:latest`, `phpmyadmin:latest` | `php:8.5-apache`, `mysql:8.4`, `phpmyadmin:5.2.3` |
| Front-end libraries | Bootstrap 3.3.7, jQuery 1.12, unpinned Vue | Bootstrap 5.3.8, no jQuery (jQuery 4.0.0 only for the Summernote editor), Vue 3.5.44, axios 1.20.0, all pinned in `framework/settings/coresettings.php` |
| Removed | add-to-homescreen.js, Hammer.js gestures (`modules/gestures.pug`), the `lightbox` script key, Internet Explorer fallbacks, BotUI and Vue 2 | Replaced by the [install module](#install) and the [Vue 3 bot client](#watson) |

**Why.** Several services that Caligrafy used were shut down or retired (gpt-3.5-turbo and DALL.E, the OpenAI Assistants API on August 26, 2026, Coinbase Commerce on March 31, 2026, Stripe's `redirectToCheckout`, the Groq Llama 3 models, face-api.js 0.22), and the old libraries no longer received security fixes.

**What you need to do.**
+ **New projects:** nothing.
+ **Existing projects:** follow the [upgrade checklist](#checklist). The sections below give the details of each area.

**Compatibility.** Projects on PHP 8.2 or older must upgrade PHP first: `composer update` refuses to install on them.

Learn more: [Upgrading to the current libraries](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#libraries).

## Bootstrap 5 and the Caligrafy look

**What changed.**
+ The layout loads Bootstrap 5.3.8 and no jQuery. `public/js/script.js` and `public/js/picture_uploader.js` are plain JavaScript.
+ The end of `public/css/theme.css` is a **Bootstrap 5 compatibility layer**: it keeps the Caligrafy black and white look (14px text, black `btn-primary`, white `btn-secondary` with black text, black focus rings, readable `a.btn` links) and the Bootstrap 3 layout (full-width page container, header title centered, grids centered in their wrapper, Caligrafy card borders and colors, Bootstrap 3 text color and button metrics).
+ **Layout classes.** The Pug layout body is `body(id='gesturize' class='caligrafy-layout')`. The heading margins of the compatibility layer apply to `body.caligrafy-layout` and to `body#gesturize` (an alias, so older copies of `layout.pug` keep their look). Client apps that reuse the Caligrafy header outside the Pug layout add the class `caligrafy-header` to their `header`.
+ The signed-in navigation (`nav.horizontal.row.g-0`) keeps the Bootstrap 3 column padding (15px) and the title next to the menu icon on phones.
+ The framework views (home, signed-in navigation, users table, payment pages) use Bootstrap 5 classes: columns in a `.row`, one breakpoint up (`col-sm-6` became `col-md-6`, `col-md-4` became `col-lg-4`), `hidden-xs` became `d-none` plus a display class (for example `d-none d-lg-table-cell` in the users table).

**Why.** Bootstrap 3 and jQuery 1.12 are unmaintained. The compatibility layer lets existing pages keep their look without a redesign.

**What you need to do.**
+ **New projects:** nothing. In your own layouts, put `class='caligrafy-layout'` on the `body`.
+ **Existing projects:** `php caligrafer.php upgrade --apply` updates the views you never changed and deletes nothing: delete `application/views/default/modules/gestures.pug` yourself. If you customized `public/css/theme.css`, copy everything from the "Bootstrap 5 compatibility layer" comment to the end of the file. In your own views, wrap columns in a `.row` and move them one breakpoint up.
+ **To keep Bootstrap 3 for now**, add to `application/config/config.php`:
    ```php
    scripts('bootstrap_css', 'https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css');
    scripts('bootstrap_script', 'https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.min.js');
    scripts('bootstrap_jquery', 'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js');
    ```

**Compatibility.** Bootstrap 3 classes such as `col-xs-*`, `hidden-xs`, `panel` or `btn-default` have no effect in Bootstrap 5. Code that used `$` or `jQuery` in a page must load jQuery itself.

Learn more: [Views: default layout and Bootstrap 5](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#default-layout-and-bootstrap-5).

## Vue 3.5 and the Vite 8 SPA template

**What changed.**
+ Client apps created with `php caligrafer.php app <name>` load Vue 3.5.44 and axios 1.20.0 with `scripts('vue')` and `scripts('axios')` (pinned versions).
+ The single page app template (`php caligrafer.php create <name>`) uses Vite 8, Vue Router 5, **Pinia** (instead of Vuex), axios and an ESLint 10 flat config (`eslint.config.js`), and loads Bootstrap 5.3.8 instead of Bootstrap 3 and jQuery. It needs Node.js `^20.19.0 || >=22.12.0`.

**Why.** Vuex and Vue CLI are in maintenance mode, Vite 7 and ESLint 9's legacy configuration were superseded, and the template had known npm vulnerabilities (the QA suite now runs `npm audit` on it).

**What you need to do.**
+ **New projects:** nothing.
+ **Existing projects:** in apps created with `app`, replace your Vue and axios script tags with `scripts('vue')` and `scripts('axios')`. In single page apps created before October 2026, replace the Bootstrap 3 and jQuery tags of `index.html` with the Bootstrap 5.3.8 CSS and `bootstrap.bundle.min.js`, move the store to Pinia and update the columns of their components.

Learn more: [Setting up the Vue application](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.1---Setting-up-the-Vue-application), [State Management](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.4-State-Management).

## OpenAI: GPT-6 and gpt-image-2

**What changed.**
+ The default text model is `gpt-6-luna` (constant `GPT6_LUNA`); `GPT6_SOL` (`gpt-6.1-sol`) and `GPT6_ASTRA` (`gpt-6-astra`) are available. Set `OPEN_AI_MODEL` in `.env` to change the default without touching the code.
+ Only the model is sent by default: current reasoning models reject sampling parameters such as `temperature` or `max_tokens`. Pass them in `$parameters` when your model supports them (for example `array("max_completion_tokens" => 1000)`).
+ Images use `gpt-image-2` (`GPT_IMAGE`, or `OPEN_AI_IMAGE_MODEL` in `.env`) and come back as base64 in `b64_json`, not as URLs.
+ The old model constants (`GPT3`, `GPT4`, `GPT4O`, `GPT4OMINI`...) still exist for backward compatibility; OpenAI shut down most of them.

**What you need to do.**
+ **Existing projects:** read generated images from `b64_json` (for example `'data:image/png;base64,' . $image['b64_json']`) instead of `url`. Replace retired model ids in your code with the `GPT6_*` constants.

Learn more: [OpenAI: ChatGPT, GPTs, DALL.E](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.4-OpenAI:-ChatGPT,-GPTs,-DALL.E).

## Assistant 2.0 on Responses and Conversations

**What changed.** `Caligrafy\Assistant` now runs on the OpenAI Responses and Conversations APIs. The methods are the same and still chain (`start()`, `createMessage()`, `run()`, `retrieveResponse()`, `text()`...); files for `file_search` go to a vector store.

**Why.** OpenAI shut down the Assistants API on August 26, 2026.

**What you need to do.**
+ **Existing projects:** assistants, threads and files created with version 1 do not carry over. Re-create them with the new class and read [Migrating from version 1](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.5-Open-AI:-Assistants#migrating).

## Stripe: Payment Element and Checkout URL

**What changed.**
+ The card form uses the Stripe **Payment Element** (`stripe.confirmPayment`) instead of the Card Element (`public/js/services/stripe_client.js`, `views/default/pages/payment.pug`).
+ `Payment::createCheckout()` returns the hosted payment page in `$result['url']`: redirect the customer to it. Stripe.js removed `redirectToCheckout`, and `public/js/services/stripe_checkout.js` was removed.
+ `createCheckout()` no longer sends `payment_method_types` by default: the payment methods come from your Stripe dashboard (current API versions reject the parameter).

**What you need to do.**
+ **Existing projects:** replace a `sessionId` + `redirectToCheckout` flow with a redirect to `$result['url']` (for example `header('Location: ' . $result['url'], true, 303);` in the controller, or `window.location = url` in a client app; the `redirect()` helper only builds URLs of your own application), and update `payment.pug` and `checkout.pug` (`caligrafer upgrade` does it when you never changed them).

Learn more: [Stripe & Cryptocurrency Payment](https://github.com/caligrafy/caligrafy-quill/wiki/9.2-Stripe-&-Cryptocurrency-Payment).

## Coinbase Business for crypto payments

**What changed.** `Caligrafy\CryptoPayment` 2.0 uses the Coinbase Business Checkouts API with CDP API keys (signed JWTs, no SDK). `createTransaction()` returns the hosted checkout in `$transaction['url']`.

**Why.** Coinbase Commerce shut down on March 31, 2026.

**What you need to do.**
+ **Existing projects:** create a CDP API key in Coinbase Business, then replace `CRYPTO_PAY_KEY` in `.env` with `COINBASE_API_KEY_NAME` (`organizations/<org_id>/apiKeys/<key_id>`) and `COINBASE_API_KEY_SECRET`. Redirect buyers to `$transaction['url']` and update `views/default/modules/cryptopayment.pug`.

Learn more: [Cryptocurrency](https://github.com/caligrafy/caligrafy-quill/wiki/9.2-Stripe-&-Cryptocurrency-Payment#cryptocurrency).

## Watson bots

**What changed.**
+ `Caligrafy\Watson` reads the URL of your instance from `WATSON_URL` (required), an optional `WATSON_ENVIRONMENT_ID`, and uses API version `2024-08-25` (`WATSON_API_VERSION`).
+ The bot client (`php caligrafer.php bot <name>`) is a small Vue 3 chat (text, options, images) instead of BotUI and Vue 2. `pause` actions call `window.executeBotAction(context)`.

**What you need to do.**
+ **Existing projects:** add `WATSON_URL=https://api.<region>.assistant.watson.cloud.ibm.com/instances/<instance_id>` to `.env`, then re-create your bot apps or compare them with `framework/plugins/bot`.

Learn more: [Creating Bots with Watson](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.1-Creating-Bots-with-Watson).

## ML Core 2.0 and face-api

**What changed.**
+ ML Core (`public/js/services/mlcore.js`) runs on ml5 1.4 and p5 2.3: body poses use `bodyPose` and segmentation `bodySegmentation` (ml5 removed `poseNet` and `uNet`), callbacks receive `(results, error)`, and the previous pose shape is kept.
+ FaceDetect uses **@vladmandic/face-api 1.7.15** and its models in `public/resources/models` (face-api.js 0.22 is abandoned). The MTCNN detector was removed. `detector.ready` is a promise that resolves once the models are loaded.

**What you need to do.**
+ **Existing projects:** the shared services and models in `public/` are updated with the framework. Re-create your ML and FaceDetect apps, or compare them with `framework/plugins/ml` and `framework/plugins/facedetect`. Replace any use of MTCNN with `SsdMobilenetv1` or `TinyFaceDetector`.

Learn more: [Machine Learning](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.3-Machine-Learning), [Face Detection and Recognition](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition).

## FaceDetector camera control and face prints

**What changed.** `FaceDetector` (`public/js/services/detect.js`) gives you control of the camera:

| API | What it does |
| - | - |
| `new FaceDetector(id, { autoStart: false })` | Does not start the camera when the models are loaded (default `true`) |
| `startStream(constraints)` | Starts the camera; the promise rejects with the camera error (permission denied, no camera), also kept in `detector.streamError` |
| `stopStream()` | Stops the camera |
| `resolveMedia()` | Finds the video element that is on the page again (Vue replaces the elements after the detector is created) |
| `faceprint(media, minConfidence)` | Resolves with the 128-number descriptor of the only face in the media |

**Why.** A camera error used to be an unhandled promise rejection, so an app could not tell the user why nothing happened. Face prints are the building block of [face login](#facelogin). The scaffolds created the detector before Vue mounted, so the camera played in a detached copy of the video element.

**What you need to do.** Nothing: the default behavior is unchanged. Use `{ autoStart: false }` and `startStream().catch(...)` to show camera errors.

Learn more: [Controlling the camera](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition#controlling-the-camera), [Face prints](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition#face-prints).

## Real HTTP status codes

**What changed.** Framework errors are answered with their real HTTP status code instead of `200`:

| Error | Status |
| - | - |
| `1000` (invalid HTTP method; for your own `throw Errors::raise(1000)`, the router does not raise it) | `405` |
| `1001` (controller or action not found) | `404` |
| `1004` (missing or invalid API key) | `401` |
| `3000` (CSRF) | `403` |
| Other framework errors, PHP fatal errors, uncaught exceptions | `500` |

HTML requests keep the `[ERROR ...]` text. API calls (`Accept: application/json`) get `{"error": {"code": 1001, "message": "..."}}`. Exceptions caught by the `catch` of `web.php` keep their message; the message of a PHP fatal error or an uncaught exception is only shown when `APP_DEBUG=true`, otherwise the body is `[ERROR 500] Internal Server Error`. An unmatched URL still runs the first route: it is not a `404`.

`Caligrafy\Errors` has three new methods: `Errors::status($code)` (the HTTP status of an error code), `Errors::raise($code)` (an exception that also sets the status) and `Errors::handle($e)` (sends the error response).

**Why.** Every error used to be a `200`, so API clients, `fetch().ok` and monitoring tools treated errors as successes.

**What you need to do.**
+ **New projects:** nothing. The template routes end with:
    ```php
    catch (Exception $e) {
        Errors::handle($e);
    }
    ```
+ **Existing projects:** if your `application/config/routing/web.php` still ends with `echo $e->getMessage();`, `php caligrafer.php upgrade --apply` replaces it with `Errors::handle($e);` and adds `use Caligrafy\Errors;` (after a backup). Without this change the framework errors (401, 403, 404) already get their status code, but other exceptions (database errors, your own) still answer `200`, and every body stays plain text.

**Compatibility.** Clients that expected `200` on errors must check the status: axios rejects the promise and `fetch().ok` is `false`.

Learn more: [REST API](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API#restapi), [Behavior changes](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#changes).

## `php caligrafer.php upgrade`

**What changed.** A new Caligrafer command (`Caligrafy\Upgrader`) brings the `application` folder of an existing project up to date with the templates of the framework (`framework/settings/application`):

```bash
php caligrafer.php upgrade                                             # dry run: prints the plan, changes nothing
php caligrafer.php upgrade --apply                                     # applies it
php caligrafer.php upgrade --force application/views/Auth/login.pug   # replaces one customized file
```

Each template file is reported as `missing` (copied with `--apply`), `identical`, `outdated` (an older template you never changed: replaced), `customized` (kept; the new version is written next to it as `<file>.caligrafy-new`) or `routes` (`web.php` is never replaced). Replaced files are first backed up under `backups/caligrafy-upgrade-<timestamp>/`: delete that folder once you merged your changes, because `.htaccess` does not deny it. `.env`, `public/uploads`, `vendor` and the files you created are never touched. Client apps in `public/` created from `framework/plugins` are only reported.

`initialize` and `upgrade --apply` write `application/.caligrafy-manifest.json`, the hash of each template file as it was copied. Commit it: it is how the next upgrade tells untouched files from customized ones. Projects without a manifest are compared with every earlier version of the templates (`framework/settings/template-history.json`).

**Why.** Updating the framework never updated the copies in `application/` (authentication module, client app view, example controllers), so existing projects silently kept old, sometimes insecure, files.

Learn more: [Upgrading an existing project](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#upgrade-command).

## Namespaced controllers

**What changed.**
+ Routes accept fully qualified controller names, with or without a leading backslash: `Route::get('/orders/{id}', 'Shop\OrderController@show');`
+ The example controllers of `application/controllers/core/` (`AIController`, `ClientController`, `WatsonController`, `AuthController`, `AdminController`) are declared in the `Caligrafy\Controllers` namespace.
+ `Route::resolveController($name)` decides which class a route calls: a declared class with that name wins; a short name that is not declared falls back to `Caligrafy\Controllers`; otherwise the route answers `404` (error 1001).
+ The default foreign key of `hasOne`, `hasMany` and `belongsToMany` uses the short class name (`Shop\Order` gives `order_id`).

**Why.** A controller of yours named like one of the examples (`AIController`...) was a fatal `Cannot redeclare class` error on every page.

**What you need to do.**
+ **Existing projects:** nothing for your routes: `'ClientController'` keeps working. If your code references an example class directly (`new AuthController()`, `extends AIController`), use `Caligrafy\Controllers\AuthController`.

Learn more: [Namespaced controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers#namespacedcontrollers), [Controller routing](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#controllerroute).

## `php caligrafer.php doctor`

**What changed.** A new read-only health check (`Caligrafy\Doctor`) of the project and of the services configured in `.env`:

```bash
php caligrafer.php doctor                            # all checks
php caligrafer.php doctor --only openai,agent        # some checks
php caligrafer.php doctor --env .env.production      # another environment file
php caligrafer.php doctor --json                     # machine-readable output
```

The checks are `project` (PHP version and extensions, `vendor/`, `.env`, `APP_KEY` and `API_KEY`, `public/uploads`, the web app manifest, outdated templates), `database`, `openai`, `groq`, `agent`, `stripe` (test or live mode), `coinbase`, `watson` and `mail` (SMTP greeting only). Every line is `OK`, `WARN`, `FAIL` or `SKIP`, with a fix for each problem. Secrets are never printed (at most a known prefix and the last 4 characters). The command exits with `1` when a check fails. With Docker, run it in the container: `docker-compose exec dev-box php caligrafer.php doctor`.

**Why.** Most support questions were configuration problems (a key with a leading space, a model the key cannot use, a retired model, a closed SMTP port) that the code reported as `null` or a generic error.

Learn more: [Caligrafer doctor](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started#doctor).

## Versioned releases

**What changed.** Caligrafy Quill has releases: this one is **v2.0.0**, a git tag on [GitHub](https://github.com/caligrafy/caligrafy-quill/tags) and a version on [Packagist](https://packagist.org/packages/caligrafy/caligrafy-quill). The version follows [semantic versioning](https://semver.org) (a major version can break projects, a minor one adds features, a patch only fixes bugs) and every release is listed in [`CHANGELOG.md`](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

```bash
php caligrafer.php version     # Caligrafy Quill 2.0.0
```

In code the version is `Caligrafy\Framework::VERSION`, and `php caligrafer.php doctor` shows it on its first line (`"version"` in `--json`).

**Why.** Before, the only version was `dev-master`: a project could not say which framework it ran, and `composer create-project` needed `-s dev`.

**Existing projects.** Nothing to do. `composer create-project caligrafy/caligrafy-quill` now installs the latest release; `-s dev` still gets the unreleased `master`.

Learn more: [Version](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started#version).

## Install to home screen

**What changed.** add-to-homescreen.js was replaced with what browsers support natively:
+ `public/manifest.webmanifest` (name, black and white colors, 192 and 512 icons; `start_url` and `scope` are `../`, so they resolve to the application root whatever `APP_ROOT` is), linked from the layout with a `theme-color` meta.
+ `views/default/modules/install.pug`: pass `'install' => true` to a view to show an **Install** button (Chrome, Edge, Android, through `beforeinstallprompt`) or a "Share, then Add to Home Screen" hint on iOS and iPadOS Safari. A dismissal is remembered.
+ An optional offline page: `PWA_SERVICE_WORKER=true` in `.env` registers `public/sw.js`, which only shows `public/offline.html` when a page cannot load. It never caches pages, API routes or `POST` requests. Turning the setting off unregisters it.
+ The root `.htaccess` sends `Service-Worker-Allowed: /` for `public/sw.js` and serves `.webmanifest` files as `application/manifest+json`.

**What you need to do.**
+ **New projects:** `return view('default/index', ['install' => true]);` on the pages that should invite visitors to install the app.
+ **Existing projects:** `php caligrafer.php upgrade --apply` replaces `modules/install.pug` and adds `modules/serviceworker.pug`. If you customized `layoutblocks.pug`, add the `theme-color` meta, the manifest link and the two includes as shown in [Views](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#install). If you customized the root `.htaccess`, copy its service worker and manifest lines.

**Compatibility.** The `$install` view variable and the `add_to_home` script keys of add-to-homescreen.js no longer exist. Installation and the service worker need HTTPS (or `localhost`).

Learn more: [Mobile: install to home screen](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#install).

## Face login security and passkeys

**What changed.**
+ `FaceDetector.liveness({ challenges })` asks the person to turn left, turn right or smile, in an order chosen by the server, and captures face prints while they move. (A `blink` challenge was in the first version: it was removed because it did not detect blinks reliably on real cameras.)
+ `Caligrafy\FaceLogin` (server side): `FaceLogin::challenge($username)` issues a single-use nonce and a random order of challenges, kept in the session (2 of the 3 challenges, 60 seconds by default). `FaceLogin::verify($response, $username)` consumes the nonce (single use), even when the check fails, and checks the liveness result and the prints: 3 to 12 prints of one face that are not identical (a replayed static print). `FaceLogin::match($prints, $enrolled)` finds the matching account.
+ `Caligrafy\Passkey` (WebAuthn, on lbuchs/webauthn) and `public/js/services/passkey.js` (`PasskeyClient.create()`, `PasskeyClient.get()`): passkey registration and login with credentials in a `passkeys` table, single-use challenges, an exact origin check and user verification.

**Why.** Face prints are computed in the browser: logging in with a single print accepts a photo or a replayed request. Liveness and single-use nonces stop photos, naive replays and stolen requests; only a passkey resists an attacker who controls the browser.

**What you need to do.**
+ **Projects with face login:** never log users in with a single face print. Use `FaceLogin::challenge()` and `FaceLogin::verify()`, and require a passkey for sensitive accounts. Create the `passkeys` table and add the relying party to `.env` in production (they are not in `.env.example`):
    ```
    PASSKEY_RP_ID=example.com
    PASSKEY_ORIGIN=https://www.example.com
    ```
    Without them the request host is used, with `APP_PROTOCOL` as the scheme, which is fine in development. Passkeys need HTTPS, except on `localhost`.
+ **Note:** keep the liveness `timeout` within the `expires_in` of the challenge. `liveness()` passes only with at least 3 prints, the minimum of `FaceLogin::verify()`.
+ **Projects that used `blink`:** remove it from the challenges you pass to `liveness()` and from your prompts; `liveness()` refuses it as an unknown challenge, and `FaceLogin::challenge()` no longer issues it.

Learn more: [Face login](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition#face-login), [Passkeys](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition#passkeys).

## Agent providers

**What changed.** `Caligrafy\Agent` runs open models through any OpenAI-compatible provider. Choose it with `AGENT_PROVIDER` in `.env`:

| `AGENT_PROVIDER` | Key | Default model | Default vision model |
| - | - | - | - |
| `groq` (default) | `GROQ_API_KEY` | `openai/gpt-oss-120b` | `qwen/qwen3.8-27b` |
| `together` | `TOGETHER_API_KEY` | `openai/gpt-oss-120b` | `Qwen/Qwen3.5-9B` |
| `fireworks` | `FIREWORKS_API_KEY` | `accounts/fireworks/models/gpt-oss-120b` | `accounts/fireworks/models/glm-5p3-flash` |
| `openrouter` | `OPENROUTER_API_KEY` | `meta-llama/llama-3.3-70b-instruct` | `meta-llama/llama-4-maverick` |
| `ollama` | none | `llama3.2` | `llama3.2-vision` |
| `custom` | `AGENT_API_KEY` | `AGENT_MODEL` (required) | `AGENT_VISION_MODEL` |

`AGENT_API_KEY` is used when the provider key is empty, `AGENT_API_BASE` overrides the endpoint (required for `custom`; from Docker, Ollama is at `http://host.docker.internal:11434/v1`), and `AGENT_MODEL` and `AGENT_VISION_MODEL` override the defaults. New methods: `models()`, `provider()`, `model()`, `visionModel()` and the static `Agent::settings()`. Audio (`transcribe()`, `translate()`) stays Groq only. `configure()` parameters are now sent with every request (calling `configure()` used to be a fatal error).

**Why.** Groq retired its Llama chat models in August 2026; Llama now runs serverless on OpenRouter or locally with Ollama.

**What you need to do.**
+ **Existing projects on Groq:** nothing; the `GROQ_*` settings and constants keep working. The defaults moved from Llama 3.x to `openai/gpt-oss-120b`: if your code names a retired Llama model, see [Migrating from the Groq Llama models](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.6-Open-Source-Agents-(llama,-Mistral-etc.)#migrating).

Learn more: [Open Source Agents](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.6-Open-Source-Agents-(llama,-Mistral-etc.)).

## `.env` settings

All new settings are in `framework/settings/.env.example`. Add the ones of the modules you use:

| Setting | Module | Required |
| - | - | - |
| `COINBASE_API_KEY_NAME`, `COINBASE_API_KEY_SECRET` (replace `CRYPTO_PAY_KEY`) | Crypto payments | Yes, for crypto payments |
| `WATSON_URL`, `WATSON_ENVIRONMENT_ID`, `WATSON_API_VERSION` | Watson bots | `WATSON_URL` |
| `OPEN_AI_MODEL`, `OPEN_AI_IMAGE_MODEL` | OpenAI and Assistants | No |
| `GROQ_MODEL`, `GROQ_VISION_MODEL` | Groq agents | No |
| `AGENT_PROVIDER`, `TOGETHER_API_KEY`, `FIREWORKS_API_KEY`, `OPENROUTER_API_KEY`, `AGENT_API_KEY`, `AGENT_API_BASE`, `AGENT_MODEL`, `AGENT_VISION_MODEL` | Agent providers | No |
| `PWA_SERVICE_WORKER` (`false` by default) | Offline page | No |
| `PASSKEY_RP_ID`, `PASSKEY_ORIGIN` (not in the template) | Passkeys | In production |

Write `.env` comments on their own line (`# ...`). Some tools and the QA suite read `.env` with PHP's `parse_ini_file`, which fails on text after `true` or `false` (`FLAG=false # comment`) and on parentheses in unquoted values.

## Unit tests and the QA suite

**What changed.** The framework has PHPUnit unit tests of its core in `qa/unit` (routing, errors, requests, models, validation, upgrade, doctor, Agent settings, face login checks, passkeys), run by `vendor/bin/phpunit` and as a phase of the QA suite. The QA suite (`qa/run.sh`) gained doctor tests, `AllowOverride FileInfo` checks, front-end library checks, face login and passkey tests, `QA_WIKI` to check the plugin's wiki mirrors and `QA_NO_BUILD=1` to reuse an existing Docker image.

**What you need to do.** Nothing in your projects. Contributors: see [Testing Caligrafy](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy).

## Claude Code plugin 1.2.0 to 1.9.1

The `caligrafy-quill` plugin follows every change above:

| Version | Follows |
| - | - |
| 1.2.0 | The library upgrade (gotchas G40-G46, API reference for Assistant 2.0, GPT-6, CryptoPayment 2.0, Payment Element, Groq, watsonx) |
| 1.3.0 | FaceDetector camera control and face prints; Bootstrap 5 buttons and layout |
| 1.4.0 | Recipes: AI chat apps and face login; Docker troubleshooting |
| 1.4.1 | Real HTTP status codes |
| 1.5.0 | `php caligrafer.php upgrade` |
| 1.6.0 | Namespaced controllers |
| 1.7.0, 1.7.1 | Layout classes and the signed-in header |
| 1.8.0 | `php caligrafer.php doctor` |
| 1.8.1 | PHPUnit unit tests |
| 1.9.0 | Install to home screen, face login security and passkeys, Agent providers, `QA_NO_BUILD`, `.env` comments |
| 1.9.1 | These release notes, the Testing Caligrafy page and the documentation review |

Projects that enable the plugin through `.claude/settings.json` get the new version automatically. See [Claude Code Plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin).

## Compatibility summary

What can break in an existing project, and the fix:

| If your project... | It breaks because... | Fix |
| - | - | - |
| runs on PHP 8.2 or older | `composer.json` requires PHP `^8.3` | Upgrade PHP |
| uses Bootstrap 3 classes or jQuery in its views | The layout loads Bootstrap 5 and no jQuery | Bootstrap 5 classes, or keep Bootstrap 3 with `scripts()` ([details](#bootstrap)) |
| redirects to Stripe Checkout with `sessionId` | `redirectToCheckout` no longer exists | Redirect to `$result['url']` |
| uses Coinbase Commerce (`CRYPTO_PAY_KEY`) | Coinbase Commerce shut down | Coinbase Business keys, `$transaction['url']` |
| shows OpenAI image URLs | Images come back as base64 | Use `b64_json` |
| uses `Assistant` 1.x objects | The Assistants API shut down | Re-create assistants ([migrating](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.5-Open-AI:-Assistants#migrating)) |
| runs a Watson bot | `WATSON_URL` is required | Add it to `.env` |
| uses MTCNN, `poseNet` or `uNet` | They were removed | `SsdMobilenetv1`, `bodyPose`, `bodySegmentation` |
| has API clients that expect `200` on errors | Errors have real status codes | Check the status |
| extends or instantiates an example controller | They are in `Caligrafy\Controllers` | Use the full name |
| includes `modules/gestures.pug` or uses `$install` from add-to-homescreen.js | They were removed | Delete the include; use `'install' => true` |
| writes comments after `true`/`false` in `.env` | `parse_ini_file` fails | Comments on their own line |

>[Next Section: Security and Upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading)
