---
name: new-app
description: Scaffold a Caligrafy Quill client app in public/<name> with caligrafer (app = Vue 3 as a library, create = Vite Vue SPA, bot = Watson bot, facedetect = FaceDetect on @vladmandic/face-api 1.7.15, ml = ml5/p5 ML Core) and wire its page route and data routes in web.php.
when_to_use: The user wants a new front-end/Vue app, SPA, chatbot, face detection or recognition app, or machine-learning app inside a Caligrafy Quill project, e.g. "/caligrafy-quill:new-app facedetect faceauth".
argument-hint: "<app|create|bot|facedetect|ml> <name>"
---

# Scaffold a Caligrafy client app

Arguments: `$ARGUMENTS` = `<type> <name>`. Types: `app` (Vue 3 as a library, default), `create` (Vite 8 SPA with Vue Router 5 + Pinia, needs Node 20.19+/22.12+ on the computer: the Docker `dev-box` has no Node, so `caligrafer create` run there says the packages are NOT installed (npm is not available); run `cd public/<name> && npm install` on the computer; if it reports 10 moderate vulnerabilities, see G66: never `npm audit fix --force`), `bot`, `facedetect`, `ml`.

Read first: [09.7.2-vue-routes.md](../caligrafy/reference/09.7.2-vue-routes.md), [gotchas.md](../caligrafy/reference/gotchas.md) G1, G15, G16, G22, G27, G35, G36, G40-G43, G50, G136, and the page for the type
(09.7.x for `app`, 09.8.x for `create`, 09.9.1 bot, 09.9.2 facedetect, 09.9.3 ml).

## Steps
1. **Check the name.** Lowercase, letters/digits/`-`/`_`. Not reserved: `app, bot, __bots__, css, facedetect, fonts, images, js, ml, resources, uploads`. `public/<name>` must not exist.
2. **Check the project.** `vendor/` and `application/` exist (else the project needs `composer install` + `php caligrafer.php initialize` - initialize deletes `.git`, ask first). `Route::capabilities('/capabilities');` is in web.php (quickstart adds it): apps call capabilities through the generated JS client. `.env` `API_KEY` only matters for the legacy axios + key pattern (G27).
3. **Scaffold** from the project root:
   ```bash
   php caligrafer.php <type> <name>        # or: .bin/caligrafer <type> <name>
   ```
   Without local PHP use `cp -r framework/plugins/<type> public/<name>` (`framework/librairies/app` for `create`, then `cd public/<name> && npm install`) - this is exactly what caligrafer does.
4. **Page route** in `application/config/routing/web.php` (before `Route::run();`). The path must end with the app folder name:
   - generic routes already exist in the template: `/apps/{appName}` (app, facedetect), `/bot/{appName}` (bot, needs `?botSkillId=`), `/ml/{appName}` (ml, `?app=featureextractor|posedetector|neuralnetwork`). The app is reachable at `<base>/apps/<name>` with no route change. They name example controllers; the same lines declared (2.21.0+) are `Route::page('/apps/{appName}', 'Client/app', ['query' => ['app' => 'index']]);`, `Route::page('/bot/{appName}', 'Client/app', ['query' => ['app' => 'index', 'botSkillId']]);`, `Route::page('/ml/{appName}', 'Client/app', ['query' => ['app' => 'index']]);` and `Route::bots();` for the bot's data routes.
   - for a dedicated URL add
     ```php
     Route::page('/<name>', 'Client/app', ['with' => ['appName' => '<name>', 'app' => 'index']]);
     ```
     with its options when the page needs them (`'data' => [...]` for server data in `env`, `'signedIn' => true, 'comeBack' => true`, `'level' => 1`, `'query' => ['q']`). `env` gets `authorized` and `user`. A page that first decides what to show: `Route::action` and `Web::view('Client/app', [...])` in a `webOnly: true` capability (G143).
   - `create` (Vite SPA) is served by its own dev server (`npm run dev`, http://localhost:5173) and built to `public/<name>/dist` (served at `<base>/public/<name>/dist/index.html`). It talks to Caligrafy through the JS client generated into `src/capabilities.js`. Since 2.19.1 the template already has `base: './'` in `vite.config.js` and `createWebHashHistory()` in `src/router/index.js` (works in dev and in `dist/` from any folder; check both in an app created earlier, G22), and `dist/` is committed (not in its `.gitignore`: the production image runs no npm, so run `npm run build` and commit `dist/` before shipping). Add `server.proxy: { '/capabilities': 'http://localhost:8080' }` to `vite.config.js` (the session cookie of localhost is shared across ports, so a user signed in on :8080 is signed in on :5173). In `App.vue` use `<router-view :key="$route.fullPath">` (otherwise `#/books/1` -> `#/books/3` reuses the page and keeps showing book 1). The template's `HomePage.vue` can be deleted once the router no longer imports it.
5. **Data** for anything the app reads/writes: prefer **capabilities** (`/caligrafy-quill:new-capability`), then generate the client: `php caligrafer.php client public/js/capabilities.js` (Vue as a library, shared with Pug pages) or `php caligrafer.php client public/<name>/src/capabilities.js` (Vite SPA); run it again after every capability change. The client uses the session and the CSRF token: no key in the page. Existing `'Controller@action'` routes returning `api(...)` (`/caligrafy-quill:new-resource`, or a `Route::action` of a recipe): since 2.7.0 a page calls them with `{ headers: apiHeaders() }` (the session and its `X-CSRF-Token`, G87); programs need `Auth::activateAPI();` and a token with the scope `*`. Never `'Bearer ' + apiKey`: online the key is off.
6. **Client code** in `public/<name>/scripts/main.js`. Since 2.3.0 the `app` template already loads the JS client and lists the visitor's capabilities; in older projects it ships an axios + `apiKey` example: replace it, because the shared key is refused once `LEGACY_API_KEY=false` and must never be in a page. Load the generated client with a dynamic import (the template loads classic scripts):
   ```js
   const app = Vue.createApp({
     data () {
       return { items: [], error: '' }
     },
     async mounted () {
       this.api = await import(env.home + 'public/js/capabilities.js')   // one function per capability
       this.items = await this.api.listBooks()
     },
     methods: {
       async add (input) {
         this.error = ''
         try {
           await this.api.createBook(input)
           this.items = await this.api.listBooks()
         } catch (e) {
           this.error = e.name === 'CapabilityError' ? Object.values(e.fields).join(' ') || e.message : 'Something went wrong.'
         }
       }
     }
   })
   app.mount('#app')
   ```
   In the template's `index.php`, drop `axios` if unused and keep `app.js` + `loadEnvironment()` (they define `env.home`). To show only what the visitor may do, ask `await this.api.capabilities()` (the capabilities this caller may call) and hide the rest. In a Vite SPA: `import { listBooks, CapabilityError } from '../../capabilities.js'`.
   - routes: build URLs from `env.home` (includes `APP_ROOT`).
   - form posts to non-API routes: `formData.append('_token', this.env.token)`, `formData.append('_method', 'PUT')` for PUT/DELETE.
   - **facedetect**: `const detector = Vue.markRaw(new FaceDetector('detection'));` (G16) and keep the `#detector` section, media element `id="detection"`, `.controls #apps` and `#infobar` placeholders; recognition samples go in `public/<name>/recognition/<Label>/1.png..N.png` (same count per label). To tell the user that the camera is blocked or missing, create the detector with `{ autoStart: false }` and `await detector.startStream()` in a try/catch (`NotAllowedError`, `NotFoundError`; also `detector.streamError`). `await detector.faceprint(media)` gives the 128-number descriptor of the only face. Face login is never a single print: use the server challenge + `await detector.liveness({ challenges })` + `FaceLogin::verify()` (nonce, liveness, prints that differ) and offer a passkey (`Caligrafy\Passkey`, `passkey.js`) as the strong factor; tell the user browser liveness is not proof (G42). Liveness challenges are `turn-left`, `turn-right`, `smile` (the server picks 2 in a random order; there is no `blink`). Full face login (tables, password sign-in or sign-up on the page, enrolment with liveness, anti-replay, lockout, passkey step-up, and the client UI in the Caligrafy look: oval face guide with placement hints, prompts on the video, Sign in / Enrol tabs): [recipes-face-login.md](../caligrafy/reference/recipes-face-login.md) - copy its `index.php`, `css/styles.css` and `scripts/main.js` rather than restyling the facedetect scaffold.
   - **look**: build the app's screens with Caligrafy's look of [ui-guidelines.md](../caligrafy/reference/ui-guidelines.md) and follow its checklist: the app template ships it (`body.cq-site > #app > nav.cq-nav` with the Caligrafy mark, a title band `section.cq-hero.cq-hero--page`, `section.cq-band > .cq-inner > ...`; `layout.css` and `look.css` are linked by the app template and imported by the SPA's `src/main.js`). Lists are `ul.cq-rows > li.cq-row` with `.cq-row-actions`, forms `.cq-form-grid > .cq-field` (label + `.form-control`), links to other screens `.cq-tiles > a.cq-tile`, errors `.cq-alert.cq-alert--error` or `p.cq-field-error` next to the action. `btn btn-primary` (black) / `btn btn-secondary` (ash), never Bootstrap colors, no cards, no custom CSS. A camera app (face detection, machine learning) fills the screen with `camera.css` (`.cq-camera`, see the Camera pages section).
   - **install to home screen** (only if asked): the scaffolds do not link the manifest. In `index.php` add `<link rel="manifest" href="<?php echo session('public').'manifest.webmanifest'; ?>">` + `<meta name="theme-color" content="#ffffff">` to install the whole application, or copy `public/manifest.webmanifest` to `public/<name>/manifest.webmanifest` with `start_url`/`scope`/`id` `"./"`, its own `name` and icons `../images/resources/icon-192x192.png` / `icon-512x512.png`, and link that copy to install the app on its own. Vite SPA: the copy and its icons go in the app's `public/` folder, linked from `index.html`. Never write a service worker for the app: the framework's `public/sw.js` (`PWA_SERVICE_WORKER=true`) already covers every page under the application root and never caches API or POST requests (wiki 7 #install).
   - **AI chat** (OpenAI, Groq, OpenRouter, Ollama in an `app` or a Pug page): follow [recipes-ai-apps.md](../caligrafy/reference/recipes-ai-apps.md) - every AI call is a capability in `application/services/` (`/caligrafy-quill:new-capability`, scope such as `ai:use`), served at once to a page (`Route::page` + `Route::form` for a one-shot answer), REST, the JS client (the chat page calls it with the generated client) and MCP; no controller. The capability keeps the history it is sent to user/assistant turns (capped) and adds its own system prompt, checks OpenAI's `response['error']` and `api_success`, validates model ids, lists models live, and turns provider errors into `ServiceError::conflict`; images need a long client timeout. The Watson bot (`bot`) keeps its `WatsonController` routes: its conversation lives in the PHP session (wiki 9.9.1); a one-question Watson capability serves the other channels.
   - **ml**: load p5 and ml5 before `mlcore.js` exactly as the scaffolded `index.php` does.
   - **create** (Vite): call capabilities with the generated client (`src/capabilities.js`). For other JSON routes of the application use what `src/common/app.js` exports (2.19.1): `apiConfig` for reads (`withCredentials: true`) and `{ headers: await apiHeaders(), withCredentials: true }` for writes (the session cookie + `X-CSRF-Token` read from `GET /capabilities`, so web.php needs `Route::capabilities('/capabilities')`); `config.apiRoute` = `VITE_API_ROUTE` (empty for the same origin). Never `VITE_API_KEY` or an `Authorization: Bearer` header in a bundle: anything built in is public (G22, G50). An app created before 2.19.1 still sends `'Bearer ' + VITE_API_KEY` from `main.js` and `src/common/app.js`: replace both with the 2.19.1 versions of `framework/librairies/app/src/`.
7. **Verify**: `php -l` any PHP you touched, open `<base>/<route>` (Docker: `http://localhost:8080/...`), check the browser console, and call one capability with curl (`curl -X POST -H "Accept: application/json" -H "Content-Type: application/json" -d '{}' <base>/capabilities/<name>`; with a token for non-public ones).

Report the files created, the URL to open, and any `.env` keys the type needs (`WATSON_API_KEY` for bot, none for facedetect/ml). For apps that call OpenAI, Groq, Watson, Stripe or Coinbase, have the user run `php caligrafer.php doctor` after entering the keys: it catches malformed keys and models the key cannot use before the app is tested.
