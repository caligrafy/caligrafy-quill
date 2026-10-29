#!/bin/sh
# 'php caligrafer.php doctor' inside the web container, against the OpenAI, Groq (and other Agent providers), Coinbase, Watson,
# SMTP mocks and stripe-mock.
# Called by qa/run.sh from the project root once the stack is up (the Watson mock already runs on 127.0.0.1:8769).

cd "$(dirname "$0")/../.."
COMPOSE="docker compose -f qa/docker-compose.yml"
TMP=qa/tmp/doctor
rm -rf "$TMP" && mkdir -p "$TMP"
PASS=0; FAIL=0
ok()  { PASS=$((PASS + 1)); echo "  PASS $1"; }
ko()  { FAIL=$((FAIL + 1)); echo "  FAIL $1"; [ -n "${2:-}" ] && echo "$2" | sed 's/^/       /'; }

$COMPOSE exec -d web php -S 127.0.0.1:8766 qa/fixtures/openai_mock.php
$COMPOSE exec -d web php -S 127.0.0.1:8767 qa/fixtures/coinbase_mock.php
$COMPOSE exec -d web php -S 127.0.0.1:8768 qa/fixtures/groq_mock.php
$COMPOSE exec -d web php qa/fixtures/smtp_mock.php 2525
sleep 1

OPENAI="sk-proj-QaDoctorSecret0penAiValue9EZ8A"
GROQ="gsk_QaDoctorSecretGroqValue7Kq2"
TOGETHER="QaDoctorSecretTogetherValue0c41d2"
OPENROUTER="sk-or-v1-QaDoctorSecretOpenRouter88f1"
STRIPE="sk_test_QaDoctorSecretStripeValue51x"
WATSON="QaDoctorSecretWatsonValue4471"
COINBASE=$($COMPOSE exec -T web php -r 'echo base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()));')
{
    cat .env
    echo "OPEN_AI_KEY=$OPENAI"
    echo "OPENAI_API_BASE=http://127.0.0.1:8766/v1"
    echo "GROQ_API_KEY=$GROQ"
    echo "GROQ_API_BASE=http://127.0.0.1:8768/openai/v1"
    echo "AGENT_PROVIDER=together"
    echo "TOGETHER_API_KEY=$TOGETHER"
    echo "AGENT_API_BASE=http://127.0.0.1:8768/together/v1"
    echo "PAY_PRIVATE_KEY_TEST=$STRIPE"
    echo "PAY_PUBLIC_KEY_TEST=pk_test_QaDoctorPublic"
    echo "STRIPE_API_BASE=http://stripe-mock:12111"
    echo "COINBASE_API_KEY_NAME=organizations/qa-org/apiKeys/qa-key"
    echo "COINBASE_API_KEY_SECRET=$COINBASE"
    echo "COINBASE_API_BASE=http://127.0.0.1:8767"
    echo "WATSON_API_KEY=$WATSON"
    echo "WATSON_ASSISTANT_ID=qa-assistant"
    echo "MAIL_HOST=127.0.0.1"
    echo "MAIL_PORT=2525"
} > "$TMP/all.env"
variant() { sed "$2" "$TMP/all.env" > "$TMP/$1.env"; }
variant wrongkey "s/^OPEN_AI_KEY=.*/OPEN_AI_KEY=sk-proj-invalidQaDoctorKey00/"
variant model "\$a\\
OPEN_AI_MODEL=gpt-9-restricted"
variant space "s/^OPEN_AI_KEY=.*/OPEN_AI_KEY=\" $OPENAI\"/"
variant dns "s#^OPENAI_API_BASE=.*#OPENAI_API_BASE=http://doctor-qa.invalid/v1#"
# agent <name> <settings...>: all.env with other Agent provider settings
agent() { name=$1; shift; grep -vE '^(AGENT_|TOGETHER_API_KEY)' "$TMP/all.env" > "$TMP/$name.env"; for line in "$@"; do echo "$line" >> "$TMP/$name.env"; done; }
agent ollama-docker AGENT_PROVIDER=ollama
agent ollama-pull AGENT_PROVIDER=ollama AGENT_API_BASE=http://127.0.0.1:8768/ollama/v1 AGENT_MODEL=llama3.3
agent ollama-empty AGENT_PROVIDER=ollama AGENT_API_BASE=http://127.0.0.1:8768/ollama-empty/v1
agent openrouter AGENT_PROVIDER=openrouter "OPENROUTER_API_KEY=$OPENROUTER" AGENT_API_BASE=http://127.0.0.1:8768/openrouter/api/v1
agent openrouter-bad AGENT_PROVIDER=openrouter OPENROUTER_API_KEY=sk-or-v1-invalidQaDoctorKey00 AGENT_API_BASE=http://127.0.0.1:8768/openrouter/api/v1
agent fireworks-nokey AGENT_PROVIDER=fireworks
agent unknown-provider AGENT_PROVIDER=llama
agent groq-llama AGENT_PROVIDER=groq AGENT_MODEL=llama-3.3-70b-versatile
doctor() { $COMPOSE exec -T web php caligrafer.php doctor --env "$TMP/$1.env" ${2:-} ${3:-} ${4:-} > "$TMP/$1.out" 2>&1; echo $?; }

CODE=$(doctor all)
BAD=$(grep -E '^  (WARN|FAIL)' "$TMP/all.out")
OKS=$(grep -c '^  OK ' "$TMP/all.out")
if [ "$CODE" = 0 ] && [ -z "$BAD" ] && grep -q ', 0 WARN, 0 FAIL, 0 SKIP' "$TMP/all.out"; then ok "doctor: every check OK against the mocks ($OKS OK, exit 0)"
else ko "doctor against the mocks (exit $CODE)" "$(cat "$TMP/all.out")"; fi
for svc in "Database: connected" "OpenAI: key accepted" "Groq: key accepted" "Together AI: key accepted, 3 models available" "AGENT_MODEL: openai/gpt-oss-120b is available" "AGENT_VISION_MODEL: Qwen/Qwen3.5-9B is available" "Stripe: key accepted (test mode" "Coinbase: key accepted" "Watson: key accepted" "Mail: 127.0.0.1:2525 answers EHLO"; do
    grep -qF "$svc" "$TMP/all.out" || ko "doctor: '$svc' missing" "$(cat "$TMP/all.out")"
done

CODE=$(doctor wrongkey)
grep -q '^  FAIL  OpenAI: the API key was rejected (401)' "$TMP/wrongkey.out" && [ "$CODE" = 1 ] && ok "doctor: a rejected OpenAI key is a FAIL and exits 1" \
    || ko "doctor wrong key (exit $CODE)" "$(cat "$TMP/wrongkey.out")"

CODE=$(doctor model)
grep -q '^  WARN  OPEN_AI_MODEL: gpt-9-restricted is not available for this key (available: gpt-6-luna' "$TMP/model.out" && [ "$CODE" = 0 ] \
    && ok "doctor: a model missing from the key's list is a WARN with the available models" || ko "doctor model WARN (exit $CODE)" "$(cat "$TMP/model.out")"

doctor space >/dev/null
grep -q "^  WARN  OPEN_AI_KEY: sk-proj-…EZ8A ([0-9]* chars): it has leading or trailing spaces" "$TMP/space.out" \
    && ok "doctor: a key with a leading space is a WARN (masked)" || ko "doctor leading space" "$(cat "$TMP/space.out")"

CODE=$(doctor dns --only openai)
grep -q 'FAIL  OpenAI: .*resolve' "$TMP/dns.out" && grep -q -- '-> DNS:' "$TMP/dns.out" && [ "$CODE" = 1 ] \
    && ok "doctor: an unresolvable host gets the DNS hint" || ko "doctor DNS hint (exit $CODE)" "$(cat "$TMP/dns.out")"

CODE=$(doctor ollama-docker --only agent)
grep -q '^  FAIL  Ollama: the Ollama server does not answer on http://localhost:11434/v1' "$TMP/ollama-docker.out" && grep -q -- '-> inside Docker localhost is the container itself: set AGENT_API_BASE=http://host.docker.internal:11434/v1' "$TMP/ollama-docker.out" && [ "$CODE" = 1 ] \
    && ok "doctor: Ollama unreachable from Docker is a FAIL with the host.docker.internal hint" || ko "doctor Ollama in Docker (exit $CODE)" "$(cat "$TMP/ollama-docker.out")"

CODE=$(doctor ollama-pull --only agent)
grep -q '^  OK    Ollama: server answers, 2 model(s) pulled' "$TMP/ollama-pull.out" && grep -q '^  WARN  AGENT_MODEL: llama3.3 is not available' "$TMP/ollama-pull.out" \
    && grep -q 'ollama pull llama3.3' "$TMP/ollama-pull.out" && grep -q '^  OK    AGENT_VISION_MODEL: llama3.2-vision:latest is available' "$TMP/ollama-pull.out" && [ "$CODE" = 0 ] \
    && ok "doctor: an Ollama model that is not pulled is a WARN with 'ollama pull'" || ko "doctor Ollama models (exit $CODE)" "$(cat "$TMP/ollama-pull.out")"

doctor ollama-empty --only agent >/dev/null
grep -q '^  WARN  Ollama: the server answers but no model is pulled' "$TMP/ollama-empty.out" && grep -q -- '-> ollama pull llama3.2' "$TMP/ollama-empty.out" \
    && ok "doctor: an Ollama server without models is a WARN" || ko "doctor Ollama empty" "$(cat "$TMP/ollama-empty.out")"

CODE=$(doctor openrouter --only agent)
grep -q '^  OK    OpenRouter: key accepted, 3 models available, 7.5 USD left' "$TMP/openrouter.out" && grep -q '^  OK    AGENT_MODEL: meta-llama/llama-3.3-70b-instruct is available' "$TMP/openrouter.out" \
    && grep -q '^  OK    OPENROUTER_API_KEY: set ' "$TMP/openrouter.out" && [ "$CODE" = 0 ] \
    && ok "doctor: OpenRouter key checked with GET /key, Llama defaults available" || ko "doctor OpenRouter (exit $CODE)" "$(cat "$TMP/openrouter.out")"

CODE=$(doctor openrouter-bad --only agent)
grep -q '^  FAIL  OpenRouter: the API key was rejected (401)' "$TMP/openrouter-bad.out" && [ "$CODE" = 1 ] \
    && ok "doctor: a rejected OpenRouter key is a FAIL" || ko "doctor OpenRouter bad key (exit $CODE)" "$(cat "$TMP/openrouter-bad.out")"

CODE=$(doctor fireworks-nokey --only agent)
grep -q '^  FAIL  Fireworks AI: FIREWORKS_API_KEY is not set' "$TMP/fireworks-nokey.out" && [ "$CODE" = 1 ] \
    && ok "doctor: a provider without its key is a FAIL" || ko "doctor Fireworks without key (exit $CODE)" "$(cat "$TMP/fireworks-nokey.out")"

CODE=$(doctor unknown-provider --only agent)
grep -q "^  FAIL  AGENT_PROVIDER: Unknown AGENT_PROVIDER 'llama'" "$TMP/unknown-provider.out" && [ "$CODE" = 1 ] \
    && ok "doctor: an unknown AGENT_PROVIDER is a FAIL" || ko "doctor unknown provider (exit $CODE)" "$(cat "$TMP/unknown-provider.out")"

CODE=$(doctor groq-llama --only agent)
grep -q '^  WARN  AGENT_MODEL: llama-3.3-70b-versatile is not available for this key' "$TMP/groq-llama.out" && grep -q 'Llama chat models are gone' "$TMP/groq-llama.out" && [ "$CODE" = 0 ] \
    && ok "doctor: AGENT_PROVIDER=groq checks AGENT_MODEL, a retired Groq Llama id is a WARN" || ko "doctor Groq Llama (exit $CODE)" "$(cat "$TMP/groq-llama.out")"

LEAK=""
for secret in "$OPENAI" "$GROQ" "$TOGETHER" "$OPENROUTER" "$STRIPE" "$WATSON" "$COINBASE" "QaDoctorSecret" "$(sed -n 's/^APP_KEY=//p' .env)" "$(sed -n 's/^API_KEY=//p' .env)"; do
    grep -lF -- "$secret" "$TMP"/*.out >/dev/null 2>&1 && LEAK="$LEAK ${secret%"${secret#????????}"}..."
done
[ -z "$LEAK" ] && ok "doctor: no secret value appears in any output" || ko "doctor prints secrets" "$LEAK"

cp "$TMP/all.env" "$TMP/json.env"; CODE=$(doctor json --json)
$COMPOSE exec -T web php -r '$j = json_decode(file_get_contents($argv[1]), true); exit(is_array($j) && $j["summary"]["FAIL"] === 0 && count($j["results"]) > 10 ? 0 : 1);' "$TMP/json.out" \
    && [ "$CODE" = 0 ] && ok "doctor --json prints valid JSON with a summary" || ko "doctor --json" "$(head -20 "$TMP/json.out")"

cp "$TMP/all.env" "$TMP/only.env"; doctor only --only openai,groq --json >/dev/null
CHECKS=$($COMPOSE exec -T web php -r 'echo implode(",", array_unique(array_column(json_decode(file_get_contents($argv[1]), true)["results"], "check")));' "$TMP/only.out")
[ "$CHECKS" = "openai,groq" ] && ok "doctor --only openai,groq runs only those checks" || ko "doctor --only" "checks: $CHECKS"

cp "$TMP/all.env" "$TMP/unknown.env"; CODE=$(doctor unknown --only openia)
[ "$CODE" = 1 ] && grep -q 'Unknown check(s): openia' "$TMP/unknown.out" && ok "doctor --only rejects an unknown check" || ko "doctor --only unknown (exit $CODE)" "$(cat "$TMP/unknown.out")"

echo
echo "$PASS passed, $FAIL failed"
[ "$FAIL" = 0 ]
