---
name: new-app
description: Scaffold a Caligrafy Quill client app in public/<name> with caligrafer (app = Vue 3 as a library, create = Vite Vue SPA, bot = Watson bot, facedetect = face-api.js FaceDetect, ml = ml5/p5 ML Core) and wire its page route and data routes in web.php.
when_to_use: The user wants a new front-end/Vue app, SPA, chatbot, face detection or recognition app, or machine-learning app inside a Caligrafy Quill project, e.g. "/caligrafy-quill:new-app facedetect faceauth".
argument-hint: "<app|create|bot|facedetect|ml> <name>"
---

# Scaffold a Caligrafy client app

Arguments: `$ARGUMENTS` = `<type> <name>`. Types: `app` (Vue 3 as a library, default), `create` (Vite SPA with vue-router + vuex, needs Node >= 16 and runs `npm install`), `bot`, `facedetect`, `ml`.

Read first: [09.7.2-vue-routes.md](../caligrafy/reference/09.7.2-vue-routes.md), [gotchas.md](../caligrafy/reference/gotchas.md) G1, G15, G16, G22, G27, G35, G36, and the page for the type
(09.7.x for `app`, 09.8.x for `create`, 09.9.1 bot, 09.9.2 facedetect, 09.9.3 ml).

## Steps
1. **Check the name.** Lowercase, letters/digits/`-`/`_`. Not reserved: `app, bot, __bots__, css, facedetect, fonts, images, js, ml, resources, uploads`. `public/<name>` must not exist.
2. **Check the project.** `vendor/` and `application/` exist (else the project needs `composer install` + `php caligrafer.php initialize` - initialize deletes `.git`, ask first). `.env` contains `API_KEY` (client pages read it, G27).
3. **Scaffold** from the project root:
   ```bash
   php caligrafer.php <type> <name>        # or: .bin/caligrafer <type> <name>
   ```
   Without local PHP use `cp -r framework/plugins/<type> public/<name>` (`framework/librairies/app` for `create`, then `cd public/<name> && npm install`) - this is exactly what caligrafer does.
4. **Page route** in `application/config/routing/web.php` (before `Route::run();`). The path must end with the app folder name:
   - generic routes already exist: `/apps/{appName}` (app, facedetect), `/bot/{appName}` (bot, needs `?botSkillId=`), `/ml/{appName}` (ml, `?app=featureextractor|posedetector|neuralnetwork`). The app is reachable at `<base>/apps/<name>` with no route change.
   - for a dedicated URL add either
     ```php
     Route::get('/<name>', function() { return view('Client/app', array('appName' => '<name>', 'app' => 'index')); });
     ```
     or a controller action returning `view('Client/app', ['appName' => '<name>', 'app' => 'index', ...data for env])` when the page needs server data or `authorized()` checks.
   - `create` (Vite SPA) is served by its own dev server (`npm run dev`) and built to `public/<name>/dist`; it talks to Caligrafy only through data routes.
5. **Data routes** for anything the app reads/writes: `'Controller@action'` routes returning `api(...)` (scaffold with `/caligrafy-quill:new-resource`). Keep `Auth::activateAPI();` in web.php.
6. **Client code** in `public/<name>/scripts/main.js`:
   ```js
   const app = Vue.createApp({
     data() {
       return {
         env: env,
         config: { headers: { 'Authorization': 'Bearer ' + apiKey, 'Accept': 'application/json', 'Content-Type': 'application/json' } }
       };
     },
     methods: {
       load() { return axios.get(this.env.home + '<name>/items', this.config); }
     }
   });
   app.mount('#app');
   ```
   - routes: build URLs from `env.home` (includes `APP_ROOT`).
   - form posts to non-API routes: `formData.append('_token', this.env.token)`, `formData.append('_method', 'PUT')` for PUT/DELETE.
   - **facedetect**: `const detector = Vue.markRaw(new FaceDetector('detection'));` (G16) and keep the `#detector` section, media element `id="detection"`, `.controls #apps` and `#infobar` placeholders; recognition samples go in `public/<name>/recognition/<Label>/1.png..N.png` (same count per label).
   - **ml**: load p5 and ml5 before `mlcore.js` exactly as the scaffolded `index.php` does.
   - **create** (Vite): fix `src/common/app.js` to `import axios from 'axios'` and `import.meta.env.VITE_API_KEY` (G22).
7. **Verify**: `php -l` any PHP you touched, open `<base>/<route>` (Docker: `http://localhost:8080/...`), check the browser console, and call one data route with curl + JSON headers.

Report the files created, the URL to open, and any `.env` keys the type needs (`WATSON_API_KEY` for bot, none for facedetect/ml).
