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
cd "$ROOT"

# --- scaffolded client apps ----------------------------------------------------------------
if node --check framework/plugins/facedetect/scripts/main.js 2>/dev/null && grep -q 'Vue.markRaw(new FaceDetector' framework/plugins/facedetect/scripts/main.js; then
    ok "facedetect scaffold wraps FaceDetector in Vue.markRaw and parses"
else
    ko "facedetect scaffold (markRaw / syntax)"
fi
grep -q 'vue@2.7.16/dist/vue' framework/plugins/bot/index.php && ok "bot scaffold pins Vue 2.7.16" || ko "bot scaffold Vue version is not pinned"
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
