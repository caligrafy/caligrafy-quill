#!/bin/sh
# Tooling tests: caligrafer, client-app scaffolds, the Vue SPA template and the Claude plugin.
# Called by qa/run.sh from the project root. Works in qa/tmp (removed by run.sh).

cd "$(dirname "$0")/../.."
ROOT=$(pwd)
TMP="$ROOT/qa/tmp"
rm -rf "$TMP" && mkdir -p "$TMP"
PASS=0; FAIL=0
ok()  { PASS=$((PASS + 1)); echo "  PASS $1"; }
ko()  { FAIL=$((FAIL + 1)); echo "  FAIL $1"; [ -n "${2:-}" ] && echo "$2" | sed 's/^/       /'; }

# --- PHP syntax of every framework/template PHP file -------------------------------------
BAD=$(find framework caligrafer.php index.php -name '*.php' -not -path '*/node_modules/*' -exec php -l {} \; 2>&1 | grep -v '^No syntax errors')
[ -z "$BAD" ] && ok "php -l on framework/, caligrafer.php and index.php" || ko "php -l" "$BAD"

# --- caligrafer initialize is idempotent for the keys ---------------------------------------
mkdir -p "$TMP/init" && cd "$TMP/init"
cp -r "$ROOT/framework" "$ROOT/caligrafer.php" . && ln -s "$ROOT/vendor" vendor && mkdir -p public/uploads
cp framework/settings/.env.example .env
php caligrafer.php initialize >/dev/null 2>&1
FIRST=$(grep -c '^APP_KEY=' .env); KEY1=$(grep '^APP_KEY=' .env | head -1)
php caligrafer.php initialize >/dev/null 2>&1
SECOND=$(grep -c '^APP_KEY=' .env); KEY2=$(grep '^APP_KEY=' .env | head -1)
if [ "$FIRST" = 1 ] && [ "$SECOND" = 1 ] && [ "$KEY1" = "$KEY2" ] && [ -d application ]; then
    ok "caligrafer initialize keeps existing APP_KEY/API_KEY when re-run"
else
    ko "caligrafer initialize re-run" "APP_KEY lines: $FIRST then $SECOND; key kept: $([ "$KEY1" = "$KEY2" ] && echo yes || echo no)"
fi
php caligrafer.php initialize off >/dev/null 2>&1
ROOTS=$(grep -c '^APP_ROOT=' .env); ROOTV=$(grep '^APP_ROOT=' .env)
if [ "$ROOTS" = 1 ] && [ "$ROOTV" = "APP_ROOT=" ]; then ok "caligrafer initialize updates APP_ROOT (root install argument)"
else ko "caligrafer initialize APP_ROOT" "$ROOTS line(s): $ROOTV"; fi
cd "$ROOT"

# --- caligrafer upgrade on an older project ------------------------------------------------
[ -f "$TMP/init/application/.caligrafy-manifest.json" ] && ok "caligrafer initialize writes application/.caligrafy-manifest.json" || ko "initialize does not write the upgrade manifest"
MISSINGHIST=$(php -r '$h = json_decode(file_get_contents("framework/settings/template-history.json"), true); require "framework/core/Upgrader.php";
    foreach (Caligrafy\Upgrader::hashes("framework/settings/application") as $p => $x) if (!in_array($x, $h[$p] ?? [])) echo "$p\n";')
[ -z "$MISSINGHIST" ] && ok "template-history.json knows every current template (php qa/build-template-history.php)" || ko "template-history.json is stale: run php qa/build-template-history.php" "$MISSINGHIST"
mkdir -p "$TMP/upgrade" && cd "$TMP/upgrade"
cp -r "$ROOT/framework" "$ROOT/caligrafer.php" . && ln -s "$ROOT/vendor" vendor && mkdir -p public/uploads
cp framework/settings/.env.example .env
php caligrafer.php initialize >/dev/null 2>&1
P=framework/settings/application
# an older project: no manifest, an untouched old view, a customized view, a missing file, the old routes catch block
rm application/.caligrafy-manifest.json application/models/Auth/create_db.sql
git -C "$ROOT" show edc90af:$P/views/Auth/login.pug > application/views/Auth/login.pug
echo "// my change" >> application/views/default/index.pug
git -C "$ROOT" show b16b3ca^:$P/config/routing/web.php > application/config/routing/web.php
echo "mine" > application/views/default/qa-mine.pug
echo "QA_SECRET=keep" >> .env; ENVSUM=$(cksum < .env)
DRY=$(php caligrafer.php upgrade 2>&1)
if echo "$DRY" | grep -q '^  missing     application/models/Auth/create_db.sql' && echo "$DRY" | grep -q '^  outdated    application/views/Auth/login.pug' \
    && echo "$DRY" | grep -q '^  customized  application/views/default/index.pug' && echo "$DRY" | grep -q 'echo \$e->getMessage();' \
    && [ ! -f application/models/Auth/create_db.sql ] && [ ! -d backups ] && [ ! -f application/.caligrafy-manifest.json ]; then
    ok "caligrafer upgrade dry run lists missing, outdated (untouched), customized files and the routes patch without changing anything"
else ko "caligrafer upgrade dry run" "$DRY"; fi
php caligrafer.php upgrade --apply >"$TMP/upgrade.log" 2>&1
B=$(ls -d backups/caligrafy-upgrade-* 2>/dev/null | head -1)
cmp -s application/models/Auth/create_db.sql $P/models/Auth/create_db.sql && cmp -s application/views/Auth/login.pug $P/views/Auth/login.pug \
    && [ -n "$B" ] && [ -f "$B/application/views/Auth/login.pug" ] && ! cmp -s "$B/application/views/Auth/login.pug" $P/views/Auth/login.pug \
    && ok "caligrafer upgrade --apply adds missing files and replaces untouched ones after a backup" || ko "upgrade --apply (missing/outdated)" "$(cat "$TMP/upgrade.log")"
grep -q '// my change' application/views/default/index.pug && cmp -s application/views/default/index.pug.caligrafy-new $P/views/default/index.pug \
    && ok "caligrafer upgrade --apply keeps customized files and writes <file>.caligrafy-new" || ko "upgrade --apply (customized)"
grep -q 'Errors::handle(\$e);' application/config/routing/web.php && grep -q '^use Caligrafy\\Errors;' application/config/routing/web.php \
    && ! grep -q 'echo \$e->getMessage' application/config/routing/web.php && php -l application/config/routing/web.php >/dev/null 2>&1 \
    && [ -f "$B/application/config/routing/web.php" ] && grep -q 'echo \$e->getMessage' "$B/application/config/routing/web.php" \
    && ok "caligrafer upgrade --apply patches the routes catch block to Errors::handle(\$e) after a backup" || ko "upgrade --apply (routes patch)"
SUMS1=$(find application -type f -not -name .caligrafy-manifest.json -exec cksum {} \; | sort)
AGAIN=$(php caligrafer.php upgrade --apply 2>&1)
SUMS2=$(find application -type f -not -name .caligrafy-manifest.json -exec cksum {} \; | sort)
[ "$SUMS1" = "$SUMS2" ] && [ "$(ls -d backups/caligrafy-upgrade-* | wc -l | tr -d ' ')" = 1 ] && ! echo "$AGAIN" | grep -qE '^  (missing|outdated) ' \
    && [ "$(cat application/views/default/qa-mine.pug)" = mine ] && [ "$(cksum < .env)" = "$ENVSUM" ] && [ -f application/.caligrafy-manifest.json ] \
    && ok "caligrafer upgrade --apply is idempotent and leaves .env and user files alone" || ko "upgrade --apply second run" "$AGAIN"
sleep 1; php caligrafer.php upgrade --force application/views/default/index.pug >/dev/null 2>&1
cmp -s application/views/default/index.pug $P/views/default/index.pug && [ ! -f application/views/default/index.pug.caligrafy-new ] \
    && [ "$(ls -d backups/caligrafy-upgrade-* | wc -l | tr -d ' ')" = 2 ] \
    && ok "caligrafer upgrade --force replaces one customized file after a backup" || ko "upgrade --force"
cd "$ROOT"

# --- scaffolded client apps ----------------------------------------------------------------
if node --check framework/plugins/facedetect/scripts/main.js 2>/dev/null && grep -q 'Vue.markRaw(new FaceDetector' framework/plugins/facedetect/scripts/main.js; then
    ok "facedetect scaffold wraps FaceDetector in Vue.markRaw and parses"
else
    ko "facedetect scaffold (markRaw / syntax)"
fi
if grep -q "scripts('vue')" framework/plugins/bot/index.php && ! grep -rqi 'botui\|vue@2' framework/plugins/bot public/js/services 2>/dev/null; then ok "bot scaffold uses Vue 3 without BotUI"; else ko "bot scaffold still references Vue 2 or BotUI"; fi

# --- front-end libraries --------------------------------------------------------------------
BADJQ=$(grep -n '\$(\|jQuery(\|jQuery\.' public/js/script.js public/js/picture_uploader.js framework/settings/application/views/default/master/*.pug 2>/dev/null)
[ -z "$BADJQ" ] && ok "layout scripts do not need jQuery" || ko "jQuery is still used" "$BADJQ"
grep -q 'vladmandic/face-api' public/js/services/face-api.min.js && ! ls public/resources/models | grep -q mtcnn && ! grep -q 'nets.mtcnn' public/js/services/detect.js \
    && ok "face-api is @vladmandic/face-api with its own models (no MTCNN)" || ko "face-api library or models are outdated"
node --check public/js/services/detect.js 2>/dev/null && grep -q 'async startStream' public/js/services/detect.js && grep -q 'this.streamError = error' public/js/services/detect.js \
    && grep -q 'async faceprint' public/js/services/detect.js && grep -q 'isConnected' public/js/services/detect.js && grep -q 'autoStart' public/js/services/detect.js && ! grep -q 'return Promise.reject(new Error(error))' public/js/services/detect.js \
    && ok "FaceDetector reports camera errors (startStream/streamError) and computes face prints" || ko "FaceDetector camera control or faceprint is missing"
grep -q -- '--bs-btn-color: #000' public/css/theme.css && grep -q -- '--bs-btn-disabled-bg: #000' public/css/theme.css && grep -q 'a.btn-primary:link' public/css/theme.css \
    && ok "theme.css keeps Bootstrap 5 buttons black and white (readable btn-secondary)" || ko "theme.css lets Bootstrap 5 recolor the buttons (white on white btn-secondary)"
if grep -q 'ml5.bodyPose' public/js/services/mlcore.js && ! grep -q 'ml5.poseNet\|ml5.uNet' public/js/services/mlcore.js && ! grep -rq 'p5.dom\|p5.js/0\.' framework/plugins/ml; then
    ok "ML scaffold uses ml5 1.x APIs and p5 2.x"
else ko "ML scaffold still uses ml5 0.x or p5 0.x"; fi
UNPINNED=$(grep -rhoE "https://(unpkg\.com|cdn\.jsdelivr\.net/npm|cdnjs\.cloudflare\.com/ajax/libs)/[^\"' )]+" framework public/js 2>/dev/null | grep -v node_modules | grep -vE '@[0-9]+\.[0-9]+\.[0-9]+|/[0-9]+\.[0-9]+\.[0-9]+/' | sort -u)
[ -z "$UNPINNED" ] && ok "every CDN library has an exact version" || ko "unpinned CDN libraries" "$UNPINNED"
DEAD=""
for url in $(grep -rhoE "https://(unpkg\.com|cdn\.jsdelivr\.net|cdnjs\.cloudflare\.com)/[^\"' )]+" framework/settings/coresettings.php framework/plugins framework/librairies/app/index.html 2>/dev/null | sort -u); do
    code=$(curl -s -o /dev/null -L -w '%{http_code}' --max-time 20 "$url"); [ "$code" = 200 ] || DEAD="$DEAD $url=$code"
done
[ -z "$DEAD" ] && ok "every CDN library URL answers 200" || ko "CDN URLs not reachable" "$DEAD"
OLDLIBS=$(grep -rnE "maxcdn\.bootstrapcdn|bootstrap(@|/)3\.|ajax\.googleapis\.com/ajax/libs/jquery|jquery/1\.|col-xs-|hidden-xs|visible-xs" framework/librairies/app/index.html framework/librairies/app/src framework/settings/application/views framework/plugins 2>/dev/null | grep -v node_modules)
[ -z "$OLDLIBS" ] && ok "no Bootstrap 3 / jQuery 1 links or Bootstrap 3 grid classes (views, plugins, SPA template)" || ko "Bootstrap 3 or jQuery 1 is still referenced" "$OLDLIBS"
grep -q 'body .container' public/css/theme.css && grep -q 'max-width: none' public/css/theme.css && grep -q ':where(#gesturize) h1' public/css/theme.css \
    && grep -q -- '--bs-gutter-x: 30px' public/css/theme.css && grep -q -- '--bs-card-border-color: #d9d9d9' public/css/theme.css \
    && ! grep -q 'content-section.row' framework/settings/application/views/default/index.pug \
    && ok "theme.css keeps the Bootstrap 3 layout on Bootstrap 5 (full-width container, header title, centered grid, card borders)" \
    || ko "theme.css or the home view lost the Bootstrap 3 layout compatibility"
BADJS=""
for f in framework/plugins/*/scripts/*.js public/js/services/app.js; do node --check "$f" 2>/dev/null || BADJS="$BADJS $f"; done
[ -z "$BADJS" ] && ok "client scaffold scripts parse (node --check)" || ko "node --check" "$BADJS"

# --- Vue SPA template builds, including common/app.js ---------------------------------------
if [ "${QA_SKIP_SPA:-0}" = 1 ]; then
    echo "  SKIP Vue SPA build (QA_SKIP_SPA=1)"
else
    rm -rf public/qaspa && cp -r framework/librairies/app public/qaspa && cd public/qaspa
    printf 'VITE_APP_KEY=qa\nVITE_API_KEY=qa-api-key\n' > .env
    printf "import { config as qaConfig, apiConfig as qaApiConfig, callApi } from './common/app.js'\nconsole.log('QA-CONFIG', qaConfig.apiKey, qaApiConfig.headers.Authorization, typeof callApi)\n" >> src/main.js
    if npm install --no-audit --no-fund --loglevel=error >"$TMP/npm.log" 2>&1 && npx vite build >"$TMP/build.log" 2>&1; then
        # common/app.js must not keep Vue CLI code (require(), process.env.VUE_APP_*) that breaks at runtime in Vite
        if grep -rqE "VUE_APP_API_KEY|require\(['\"]axios['\"]\)" dist/assets/*.js; then
            ko "Vue SPA bundle still contains Vue CLI code from common/app.js (require/process.env)"
        elif grep -rq 'qa-api-key' dist/assets/*.js; then ok "Vue SPA template builds with Vite and common/app.js reads VITE_API_KEY"
            OUT=$(npx eslint . 2>&1) && ok "Vue SPA template passes ESLint (flat config)" || ko "Vue SPA ESLint" "$OUT"
            OUT=$(npm audit --omit=dev 2>&1) && ok "Vue SPA template has no known vulnerabilities" || ko "npm audit" "$(echo "$OUT" | tail -5)"
        else ko "Vue SPA build does not inline VITE_API_KEY"; fi
    else
        ko "Vue SPA build failed" "$(tail -20 "$TMP/build.log" 2>/dev/null; tail -5 "$TMP/npm.log")"
    fi
    cd "$ROOT" && rm -rf public/qaspa
fi

# --- Claude plugin -------------------------------------------------------------------------
if command -v claude >/dev/null 2>&1; then
    OUT=$(claude plugin validate ./claude-plugin --strict 2>&1) && ok "claude plugin validate --strict" || ko "claude plugin validate" "$OUT"
    OUT=$(claude plugin validate . --strict 2>&1) && ok "claude plugin validate (marketplace)" || ko "marketplace validate" "$OUT"
else
    echo "  SKIP claude plugin validate (claude CLI not installed)"
fi
if [ -n "${QA_WIKI:-}" ]; then
    OUT=$(python3 claude-plugin/scripts/sync-wiki.py "$QA_WIKI" --check 2>&1) && ok "plugin wiki mirrors are up to date with $QA_WIKI" || ko "plugin wiki mirrors are stale" "$OUT"
else
    echo "  SKIP plugin wiki mirror check (set QA_WIKI=<wiki clone>)"
fi
OUT=$(sh -n claude-plugin/scripts/php-lint.sh 2>&1) && ok "php-lint hook script syntax" || ko "php-lint hook" "$OUT"
echo '{"tool_input":{"file_path":"'"$ROOT"'/qa/fixtures/outside/marker.php"}}' | sh claude-plugin/scripts/php-lint.sh >/dev/null 2>&1 && ok "php-lint hook passes a valid file" || ko "php-lint hook rejects a valid file"
printf '<?php echo ;\n' > "$TMP/broken.php"
echo '{"tool_input":{"file_path":"'"$TMP"'/broken.php"}}' | sh claude-plugin/scripts/php-lint.sh >/dev/null 2>&1
[ $? = 2 ] && ok "php-lint hook blocks a broken file (exit 2)" || ko "php-lint hook does not block a broken file"

echo
echo "$PASS passed, $FAIL failed"
[ "$FAIL" = 0 ]
