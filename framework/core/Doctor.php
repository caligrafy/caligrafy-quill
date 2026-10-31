<?php
/**
 * Doctor.php
 * Read-only health check of a project and of the services it is configured for (php caligrafer.php doctor)
 * @copyright 2026
 *
*/

namespace Caligrafy;

require_once __DIR__ . '/Framework.php'; // Framework::VERSION on the first line of the report

/**
 * Class Doctor
 * Every check is read-only: it lists, reads or connects, it never creates, charges or sends anything
 * (the only exception is Watson when its assistants cannot be listed: a session is opened and closed right away).
 * Secret values are never printed: at most a known prefix and the last 4 characters.
 *
 * Base URLs can be overridden (tests, proxies, regional endpoints): OPENAI_API_BASE, GROQ_API_BASE, AGENT_API_BASE,
 * STRIPE_API_BASE, COINBASE_API_BASE. Watson always uses WATSON_URL.
 */
class Doctor
{
    const CHECKS = ['project', 'database', 'openai', 'groq', 'agent', 'stripe', 'coinbase', 'watson', 'mail'];
    const LABELS = ['project' => 'Project', 'database' => 'Database', 'openai' => 'OpenAI', 'groq' => 'Groq', 'agent' => 'Agent provider', 'stripe' => 'Stripe',
                    'coinbase' => 'Coinbase Business', 'watson' => 'Watson', 'mail' => 'Mail'];
    const TIMEOUT = 8;

    // values of framework/settings/.env.example that only mean "not configured yet"
    const PLACEHOLDERS = ['your-smtp-host', 'pk_test_1234', 'sk_test_1234', 'pk_live_1234', 'sk_live_1234'];

    // known key prefixes: a key that starts with the name but misses the separator is a copy/paste error
    const PREFIXES = ['sk-proj-', 'sk-svcacct-', 'sk-admin-', 'gsk_', 'sk_test_', 'sk_live_', 'rk_test_', 'rk_live_', 'pk_test_', 'pk_live_'];

    private $root;
    private $only;
    private $results = [];
    private $envFile;

    public function __construct($root = '.', $only = null, $envFile = '.env')
    {
        $this->root = rtrim($root, '/');
        $this->envFile = $envFile;
        $this->only = $only? array_map('trim', explode(',', strtolower($only))) : self::CHECKS;
    }

    /**
     * Runs the checks and prints the report
     * @return int exit code: 0 without FAIL, 1 otherwise
     */
    public function run($json = false)
    {
        $unknown = array_diff($this->only, self::CHECKS);
        if ($unknown) {
            fwrite(STDERR, "Unknown check(s): " . implode(', ', $unknown) . ". Available: " . implode(', ', self::CHECKS) . "\n");
            return 1;
        }
        foreach (self::CHECKS as $check) {
            if (!in_array($check, $this->only)) continue;
            try {
                $this->$check();
            } catch (\Throwable $e) {
                $this->add($check, ucfirst($check), 'FAIL', $this->scrub($e->getMessage()), 'unexpected error while checking');
            }
        }
        $counts = array_count_values(array_column($this->results, 'status')) + ['OK' => 0, 'WARN' => 0, 'FAIL' => 0, 'SKIP' => 0];
        if ($json) {
            print(json_encode(['version' => Framework::VERSION, 'results' => $this->results, 'summary' => $counts], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        } else {
            print("\nCaligrafy doctor (read-only): Caligrafy Quill " . Framework::VERSION . "\n");
            $section = null;
            foreach ($this->results as $r) {
                if ($r['check'] !== $section) {
                    $section = $r['check'];
                    print("\n " . self::LABELS[$section] . "\n");
                }
                printf("  %-4s  %s: %s\n", $r['status'], $r['name'], $r['message']);
                if ($r['fix']) print("        -> " . $r['fix'] . "\n");
            }
            printf("\n %d OK, %d WARN, %d FAIL, %d SKIP\n\n", $counts['OK'], $counts['WARN'], $counts['FAIL'], $counts['SKIP']);
        }
        return $counts['FAIL'] > 0? 1 : 0;
    }

    public function results()
    {
        return $this->results;
    }

    // --- checks ---------------------------------------------------------------------------------

    private function project()
    {
        $php = PHP_VERSION;
        version_compare($php, '8.3.0', '>=')? $this->add('project', 'PHP', 'OK', $php)
            : $this->add('project', 'PHP', 'FAIL', "$php is too old", 'Caligrafy Quill needs PHP 8.3 or later');

        $required = ['curl', 'json', 'mbstring', 'openssl', 'pdo'];
        $optional = ['gd' => 'image uploads and resizing', 'sodium' => 'Coinbase Ed25519 keys', 'intl' => 'localization', 'zip' => 'Composer'];
        $missing = array_filter($required, function ($e) { return !extension_loaded($e); });
        $missing? $this->add('project', 'Extensions', 'FAIL', 'missing ' . implode(', ', $missing), 'install the PHP extensions (the Docker image has them all)')
            : $this->add('project', 'Extensions', 'OK', implode(', ', $required));
        $missingOptional = array_filter(array_keys($optional), function ($e) { return !extension_loaded($e); });
        if ($missingOptional) {
            $this->add('project', 'Optional extensions', 'WARN', implode(', ', array_map(function ($e) use ($optional) { return "$e ({$optional[$e]})"; }, $missingOptional)) . ' not loaded',
                'install them if you use these features');
        }

        is_file($this->root . '/vendor/autoload.php')? $this->add('project', 'vendor/', 'OK', 'Composer dependencies installed')
            : $this->add('project', 'vendor/', 'FAIL', 'vendor/autoload.php not found', 'run composer install');
        is_file($this->envFile)? $this->add('project', $this->envFile, 'OK', 'present')
            : $this->add('project', $this->envFile, 'FAIL', 'missing', 'cp framework/settings/.env.example .env, then php caligrafer.php initialize');

        foreach (['APP_KEY', 'API_KEY'] as $key) {
            $value = $this->env($key);
            if ($value === '') $this->add('project', $key, 'FAIL', 'not set', 'php caligrafer.php generatekeys (or initialize) and put the keys in .env');
            else $this->secret('project', $key, $value);
        }

        $uploads = $this->root . '/public/uploads';
        if (!is_dir($uploads)) $this->add('project', 'public/uploads', 'WARN', 'missing', 'mkdir -p public/uploads && chmod -R 777 public/uploads');
        elseif (!is_writable($uploads)) $this->add('project', 'public/uploads', 'WARN', 'not writable by ' . get_current_user(), 'chmod -R 777 public/uploads');
        else $this->add('project', 'public/uploads', 'OK', 'writable');

        $this->webApp();

        if (!is_dir($this->root . '/application')) {
            $this->add('project', 'Templates', 'FAIL', 'application/ not found', 'php caligrafer.php initialize');
            return;
        }
        $plan = (new Upgrader($this->root))->plan();
        $statuses = array_count_values(array_column($plan, 'status'));
        $updatable = ($statuses['outdated'] ?? 0) + ($statuses['missing'] ?? 0);
        $manifest = is_file($this->root . '/application/' . Upgrader::MANIFEST);
        if ($updatable) {
            $this->add('project', 'Templates', 'WARN', "$updatable template file(s) older than this framework version" . ($manifest? '' : ' (no upgrade manifest: older project)'),
                'php caligrafer.php upgrade (dry run), then php caligrafer.php upgrade --apply');
        } else {
            $this->add('project', 'Templates', 'OK', 'application/ is up to date with the framework templates' . (isset($statuses['customized'])? " ({$statuses['customized']} customized)" : ''));
        }
    }

    /**
     * Install to home screen: public/manifest.webmanifest is valid and its icons exist (paths are relative to public/),
     * and the optional service worker (PWA_SERVICE_WORKER) has its files and HTTPS in production
     */
    private function webApp()
    {
        $public = $this->root . '/public/';
        $name = 'public/manifest.webmanifest';
        if (!is_file($public . 'manifest.webmanifest')) {
            $this->add('project', $name, 'WARN', 'missing: browsers will not offer to install the app', 'copy public/manifest.webmanifest from the framework');
        } else {
            $manifest = json_decode((string)file_get_contents($public . 'manifest.webmanifest'), true);
            if (!is_array($manifest)) {
                $this->add('project', $name, 'WARN', 'not valid JSON: ' . json_last_error_msg(), 'fix the JSON (compare with the framework copy)');
            } else {
                $missing = [];
                $sizes = [];
                foreach (is_array($manifest['icons'] ?? null)? $manifest['icons'] : [] as $icon) {
                    $src = (string)($icon['src'] ?? '');
                    if ($src === '' || preg_match('#^/|^\w+:#', $src)) continue; // absolute URLs are not checked
                    if (is_file($public . parse_url($src, PHP_URL_PATH))) $sizes = array_merge($sizes, explode(' ', (string)($icon['sizes'] ?? '')));
                    else $missing[] = $src;
                }
                $absent = array_diff(['name', 'start_url'], array_keys(array_filter($manifest)));
                $sizesMissing = array_diff(['192x192', '512x512'], $sizes);
                if ($missing) $this->add('project', $name, 'WARN', 'icon(s) not found: ' . implode(', ', array_unique($missing)), 'icon paths are relative to public/');
                elseif ($absent) $this->add('project', $name, 'WARN', 'no ' . implode(', ', $absent), 'browsers need a name and a start_url to install the app');
                elseif ($sizesMissing) $this->add('project', $name, 'WARN', 'no ' . implode(' or ', $sizesMissing) . ' icon', 'Chrome needs a 192x192 and a 512x512 icon to install the app');
                else $this->add('project', $name, 'OK', count($manifest['icons']) . ' icon(s) found');
            }
        }

        if (!filter_var($this->env('PWA_SERVICE_WORKER'), FILTER_VALIDATE_BOOLEAN)) return;
        $absentFiles = array_filter(['sw.js', 'offline.html'], function ($f) use ($public) { return !is_file($public . $f); });
        if ($absentFiles) {
            $this->add('project', 'Service worker', 'WARN', 'PWA_SERVICE_WORKER=true but public/' . implode(', public/', $absentFiles) . ' missing', 'copy them from the framework');
        } elseif (strtolower($this->env('APP_ENV')) === 'production' && strtolower($this->env('APP_PROTOCOL')) !== 'https') {
            $this->add('project', 'Service worker', 'WARN', 'browsers only register service workers over HTTPS (or on localhost)', 'APP_PROTOCOL=https in production');
        } else {
            $this->add('project', 'Service worker', 'OK', 'public/sw.js registered by the layout (offline page only)');
        }
    }

    private function database()
    {
        $active = strtolower($this->env('DB_ACTIVATE'));
        if (!in_array($active, ['true', '1', 'yes', 'on'])) {
            $this->add('database', 'Database', 'SKIP', 'DB_ACTIVATE is not true');
            return;
        }
        $driver = $this->env('DB_CONNECTION') ?: 'mysql';
        if (!extension_loaded("pdo_$driver")) {
            $this->add('database', 'Database', 'FAIL', "the pdo_$driver extension is not loaded", "install pdo_$driver or use the Docker server (php caligrafer.php server start)");
            return;
        }
        $host = $this->env('DB_HOST');
        $where = "$driver://" . $host . ($this->env('DB_PORT')? ':' . $this->env('DB_PORT') : '') . '/' . $this->env('DB_DATABASE');
        try {
            $pdo = new \PDO(\Caligrafy\Database::dsn($driver, $host, $this->env('DB_DATABASE'), $this->env('DB_PORT') ?: null),
                $this->env('DB_USERNAME'), $this->env('DB_PASSWORD'), [\PDO::ATTR_TIMEOUT => 5, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $this->add('database', 'Database', 'OK', "connected to $where (server " . $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION) . ')');
        } catch (\PDOException $e) {
            $message = $this->scrub($e->getMessage());
            $fix = 'check the DB_* values in .env';
            if (stripos($message, 'getaddrinfo') !== false || stripos($message, 'Name or service not known') !== false) {
                $fix = $host === 'mysql'? "DB_HOST=mysql only resolves inside Docker: run the command in the container (docker compose exec dev-box php caligrafer.php doctor) or use 127.0.0.1"
                    : 'DB_HOST cannot be resolved: use mysql inside Docker, localhost or 127.0.0.1 otherwise';
            } elseif (stripos($message, 'refused') !== false || stripos($message, 'No such file') !== false) {
                $fix = 'the database server is not running or not on this host/port (php caligrafer.php server start)';
            } elseif (stripos($message, 'Access denied') !== false) {
                $fix = 'wrong DB_USERNAME / DB_PASSWORD';
            } elseif (stripos($message, 'Unknown database') !== false) {
                $fix = 'create the database ' . $this->env('DB_DATABASE') . ' (phpMyAdmin on http://localhost:8077 with Docker)';
            }
            $this->add('database', 'Database', 'FAIL', "$where: $message", $fix);
        }
    }

    private function openai()
    {
        $key = $this->env('OPEN_AI_KEY', false);
        if (trim($key) === '') { $this->add('openai', 'OpenAI', 'SKIP', 'OPEN_AI_KEY is not set'); return; }
        $keyOk = $this->secret('openai', 'OPEN_AI_KEY', $key);
        $base = rtrim($this->env('OPENAI_API_BASE') ?: 'https://api.openai.com/v1', '/');
        $r = $this->http("$base/models", ['Authorization: Bearer ' . $key]);
        if (!$this->reachable('openai', 'OpenAI', $r)) return;
        if ($r['status'] === 401) {
            $this->add('openai', 'OpenAI', 'FAIL', 'the API key was rejected (401)' . ($keyOk? '' : ': see the key format warning above'),
                'create a key on https://platform.openai.com/api-keys and paste it as OPEN_AI_KEY=sk-proj-... without spaces');
            return;
        }
        if ($r['status'] !== 200 || !isset($r['json']['data'])) {
            $this->add('openai', 'OpenAI', 'FAIL', 'GET /models answered ' . $r['status'] . ': ' . $this->apiError($r), 'check the key permissions (Models: Read) on https://platform.openai.com/api-keys');
            return;
        }
        $models = array_column($r['json']['data'], 'id');
        $this->add('openai', 'OpenAI', 'OK', 'key accepted, ' . count($models) . ' models available. Billing and credits cannot be checked read-only',
            'a 429 "insufficient_quota" on the first request means no credits: https://platform.openai.com/settings/organization/billing');
        $chat = array_values(array_filter($models, function ($m) { return preg_match('/^(gpt-|o\d|chatgpt)/', $m) && !preg_match('/image|audio|realtime|transcribe|tts|embedding|search/', $m); }));
        $image = array_values(array_filter($models, function ($m) { return preg_match('/image|dall-e/', $m); }));
        $this->models('openai', 'OPEN_AI_MODEL', defined('OPENAI_DEFAULT_MODEL')? OPENAI_DEFAULT_MODEL : ($this->env('OPEN_AI_MODEL') ?: 'gpt-6-luna'), $models, $chat,
            'the project of this key does not allow it (Project settings > Limits > Model usage) or it was retired');
        $this->models('openai', 'OPEN_AI_IMAGE_MODEL', defined('OPENAI_DEFAULT_IMAGE_MODEL')? OPENAI_DEFAULT_IMAGE_MODEL : ($this->env('OPEN_AI_IMAGE_MODEL') ?: 'gpt-image-2'), $models, $image,
            'the project of this key does not allow it, or the organization is not verified for image models');
    }

    private function groq()
    {
        // AGENT_* settings apply to Groq when it is the Agent provider (AGENT_PROVIDER empty or groq)
        $agent = in_array(strtolower($this->env('AGENT_PROVIDER')), ['', 'groq'], true);
        $keyName = 'GROQ_API_KEY';
        $key = $this->env('GROQ_API_KEY', false);
        if (trim($key) === '' && $agent && $this->env('AGENT_API_KEY') !== '') { $keyName = 'AGENT_API_KEY'; $key = $this->env('AGENT_API_KEY', false); }
        if (trim($key) === '') { $this->add('groq', 'Groq', 'SKIP', 'GROQ_API_KEY is not set'); return; }
        $this->secret('groq', $keyName, $key);
        $base = rtrim(($agent? $this->env('AGENT_API_BASE') : '') ?: $this->env('GROQ_API_BASE') ?: 'https://api.groq.com/openai/v1', '/');
        $r = $this->http("$base/models", ['Authorization: Bearer ' . trim($key)]);
        if (!$this->reachable('groq', 'Groq', $r)) return;
        if ($r['status'] === 401) {
            $this->add('groq', 'Groq', 'FAIL', 'the API key was rejected (401)', "create a key on https://console.groq.com/keys and paste it as $keyName=gsk_... without spaces");
            return;
        }
        if ($r['status'] !== 200 || !isset($r['json']['data'])) {
            $this->add('groq', 'Groq', 'FAIL', 'GET /models answered ' . $r['status'] . ': ' . $this->apiError($r), 'check https://console.groq.com and GROQ_API_BASE');
            return;
        }
        $models = array_column(array_filter($r['json']['data'], function ($m) { return $m['active'] ?? true; }), 'id');
        $this->add('groq', 'Groq', 'OK', 'key accepted, ' . count($models) . ' models available');
        $chat = array_values(array_filter($models, function ($m) { return !preg_match('/whisper|guard|tts|orpheus|playai|distil/', $m); }));
        $retired = 'Groq retires models regularly (its Llama chat models are gone since August 2026): pick a current one on https://console.groq.com/docs/models';
        foreach (['AGENT_MODEL' => ['GROQ_MODEL', 'GROQ_DEFAULT_MODEL', 'openai/gpt-oss-120b'], 'AGENT_VISION_MODEL' => ['GROQ_VISION_MODEL', 'GROQ_DEFAULT_VISION_MODEL', 'qwen/qwen3.8-27b']] as $agentSetting => [$setting, $constant, $default]) {
            if ($agent && $this->env($agentSetting) !== '') { $this->models('groq', $agentSetting, $this->env($agentSetting), $models, $chat, $retired); continue; }
            $this->models('groq', $setting, defined($constant)? constant($constant) : ($this->env($setting) ?: $default), $models, $chat, $retired);
        }
    }

    /**
     * Provider of Caligrafy\Agent (AGENT_PROVIDER): Groq is checked by groq(), the others here (key, models, default models;
     * Ollama: server reachable and models pulled)
     */
    private function agent()
    {
        try {
            $s = Agent::settings(function ($name) { return $this->env($name); });
        } catch (\Exception $e) {
            $this->add('agent', 'AGENT_PROVIDER', 'FAIL', $e->getMessage(), 'fix AGENT_PROVIDER in .env (empty means groq)');
            return;
        }
        $provider = $s['provider'];
        $p = Agent::PROVIDERS[$provider];
        if ($provider === 'groq') {
            $this->add('agent', 'AGENT_PROVIDER', 'OK', ($this->env('AGENT_PROVIDER') === ''? 'not set: groq (default)' : 'groq') . ', see the Groq check');
            if (!in_array('groq', $this->only, true)) $this->groq();
            return;
        }
        if ($s['base'] === '') {
            $this->add('agent', 'AGENT_API_BASE', 'FAIL', 'AGENT_PROVIDER=custom needs AGENT_API_BASE', 'set AGENT_API_BASE=https://<your endpoint>/v1 (an OpenAI-compatible API)');
            return;
        }
        $this->add('agent', 'AGENT_PROVIDER', 'OK', "$provider ({$s['label']}), " . $s['base']);
        $label = $s['label'];
        $headers = ['Accept: application/json'];
        if ($provider !== 'ollama' && !($provider === 'custom' && $s['key'] === '')) { // custom endpoints may need no key
            $keyName = $this->env($s['keyName']) !== ''? $s['keyName'] : 'AGENT_API_KEY';
            if ($s['key'] === '') {
                $this->add('agent', $label, 'FAIL', "{$s['keyName']} is not set", "add {$s['keyName']}=<your key> (or AGENT_API_KEY) to .env" . ($p['keys']? ': create one on ' . $p['keys'] : ''));
                return;
            }
            $this->secret('agent', $keyName, $this->env($keyName, false));
            $headers[] = 'Authorization: Bearer ' . $s['key'];
        }
        $local = preg_match('#^https?://(localhost|127\.0\.0\.1|\[::1\])[:/]#i', $s['base'] . '/');
        $r = $this->http($s['base'] . '/models', $headers);
        if ($provider === 'ollama' && ($r['error'] !== null || $r['status'] === 0)) {
            $docker = is_file('/.dockerenv');
            $this->add('agent', $label, 'FAIL', 'the Ollama server does not answer on ' . $s['base'] . ': ' . $this->scrub($r['error'] ?: 'no answer'),
                ($docker && $local? 'inside Docker localhost is the container itself: set AGENT_API_BASE=http://host.docker.internal:11434/v1 (Linux: add extra_hosts "host.docker.internal:host-gateway" and start Ollama with OLLAMA_HOST=0.0.0.0)'
                    : 'start Ollama (ollama serve, or open the Ollama app) and check the port' . ($local? '' : '; from Docker use http://host.docker.internal:11434/v1')));
            return;
        }
        if (!$this->reachable('agent', $label, $r)) return;
        $fixKey = "create a key on {$p['keys']} and paste it as " . ($keyName ?? '') . '=<key> without spaces';
        if (in_array($r['status'], [401, 403], true)) {
            $this->add('agent', $label, 'FAIL', 'the API key was rejected (' . $r['status'] . ')', $provider === 'custom'? 'check AGENT_API_KEY' : $fixKey);
            return;
        }
        $models = $r['status'] === 200? Agent::modelIds($r['json']) : null;
        if ($models === null) {
            $this->add('agent', $label, 'FAIL', 'GET /models answered ' . $r['status'] . ': ' . $this->apiError($r), 'check AGENT_API_BASE' . ($p['docs']? ' and ' . $p['docs'] : ''));
            return;
        }
        if ($provider === 'openrouter') {
            // OpenRouter lists its models without a key: GET /key checks it (and shows the remaining credit)
            $k = $this->http($s['base'] . '/key', $headers);
            if (!$this->reachable('agent', $label, $k)) return;
            if ($k['status'] !== 200) {
                $this->add('agent', $label, 'FAIL', 'the API key was rejected (' . $k['status'] . '): ' . $this->apiError($k), $fixKey);
                return;
            }
            $d = $k['json']['data'] ?? [];
            $credit = !empty($d['is_free_tier'])? ', free tier (":free" models only, daily request limit)'
                : (isset($d['limit_remaining'])? ', ' . $d['limit_remaining'] . ' USD left on the key limit' : '');
            $this->add('agent', $label, 'OK', 'key accepted, ' . count($models) . ' models available' . $credit);
        } elseif ($provider === 'ollama') {
            if (!$models) {
                $this->add('agent', $label, 'WARN', 'the server answers but no model is pulled', 'ollama pull ' . ($s['model'] ?: 'llama3.2'));
                return;
            }
            $this->add('agent', $label, 'OK', 'server answers, ' . count($models) . ' model(s) pulled');
        } else {
            $this->add('agent', $label, 'OK', 'key accepted, ' . count($models) . ' models available');
        }
        foreach (['AGENT_MODEL' => $s['model'], 'AGENT_VISION_MODEL' => $s['vision']] as $setting => $model) {
            if ($model === '') {
                if ($setting === 'AGENT_MODEL') $this->add('agent', $setting, 'WARN', 'not set and ' . $label . ' has no default', 'set AGENT_MODEL=<a model of the list> in .env');
                continue;
            }
            if ($provider === 'ollama') {
                $tagged = strpos($model, ':') === false? "$model:latest" : $model;
                $model = in_array($tagged, $models, true)? $tagged : $model;
                $this->models('agent', $setting, $model, $models, $models, "pull it where Ollama runs: ollama pull " . preg_replace('/:latest$/', '', $model), 'on this Ollama server');
            } else {
                $this->models('agent', $setting, $model, $models, $models, 'pick a current model' . ($p['docs']? ' on ' . $p['docs'] : ''));
            }
        }
    }

    private function stripe()
    {
        $live = strtolower($this->env('APP_ENV')) === 'production';
        $suffix = $live? 'PRODUCTION' : 'TEST';
        $secret = $this->env("PAY_PRIVATE_KEY_$suffix", false);
        $public = $this->env("PAY_PUBLIC_KEY_$suffix", false);
        if (trim($secret) === '' || in_array(trim($secret), self::PLACEHOLDERS)) {
            $this->add('stripe', 'Stripe', 'SKIP', "PAY_PRIVATE_KEY_$suffix is not set (APP_ENV=" . ($this->env('APP_ENV') ?: 'local') . ')');
            return;
        }
        $this->secret('stripe', "PAY_PRIVATE_KEY_$suffix", $secret);
        if (trim($public) !== '' && !in_array(trim($public), self::PLACEHOLDERS)) $this->secret('stripe', "PAY_PUBLIC_KEY_$suffix", $public);
        $mode = preg_match('/^(sk|rk)_live_/', trim($secret))? 'live' : (preg_match('/^(sk|rk)_test_/', trim($secret))? 'test' : 'unknown');
        if ($live && $mode === 'test') $this->add('stripe', 'Mode', 'WARN', 'APP_ENV=production uses a test key: no real payment will be collected', 'put the sk_live_ key in PAY_PRIVATE_KEY_PRODUCTION');
        if (!$live && $mode === 'live') $this->add('stripe', 'Mode', 'WARN', 'a live key is used outside production: payments will be real', 'use a sk_test_ key in PAY_PRIVATE_KEY_TEST');
        if (trim($public) !== '' && !in_array(trim($public), self::PLACEHOLDERS) && strpos(trim($public), 'pk_' . $mode . '_') !== 0) {
            $this->add('stripe', 'Mode', 'WARN', "PAY_PUBLIC_KEY_$suffix is not a pk_{$mode}_ key: public and secret keys come from different modes", 'copy both keys from the same mode in the Stripe dashboard');
        }
        if (!class_exists('\Stripe\StripeClient')) { $this->add('stripe', 'Stripe', 'FAIL', 'stripe/stripe-php is not installed', 'composer install'); return; }
        $options = ['api_key' => trim($secret)];
        if ($this->env('STRIPE_API_BASE')) $options['api_base'] = rtrim($this->env('STRIPE_API_BASE'), '/');
        $client = new \Stripe\HttpClient\CurlClient();
        $client->setTimeout(self::TIMEOUT);
        $client->setConnectTimeout(5);
        \Stripe\ApiRequestor::setHttpClient($client);
        try {
            $balance = (new \Stripe\StripeClient($options))->balance->retrieve();
            $available = array_map(function ($b) { return number_format($b->amount / 100, 2) . ' ' . strtoupper($b->currency); }, $balance->available ?? []);
            $this->add('stripe', 'Stripe', 'OK', "key accepted ($mode mode" . ($balance->livemode? ', livemode' : '') . ')' . ($available? ', available balance ' . implode(', ', array_slice($available, 0, 3)) : ''));
        } catch (\Stripe\Exception\AuthenticationException $e) {
            $this->add('stripe', 'Stripe', 'FAIL', 'the secret key was rejected', 'copy the secret key from https://dashboard.stripe.com/apikeys into PAY_PRIVATE_KEY_' . $suffix);
        } catch (\Stripe\Exception\PermissionException $e) {
            $this->add('stripe', 'Stripe', 'WARN', 'the key is valid but cannot read the balance (restricted key)', 'give the restricted key Balance: Read, or ignore if Checkout works');
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            $this->network('stripe', 'Stripe', $e->getMessage());
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->add('stripe', 'Stripe', 'FAIL', $this->scrub($e->getMessage()), 'check the Stripe dashboard');
        }
    }

    private function coinbase()
    {
        $name = $this->env('COINBASE_API_KEY_NAME', false);
        $secret = $this->env('COINBASE_API_KEY_SECRET', false);
        if (trim($name) === '' && trim($secret) === '') { $this->add('coinbase', 'Coinbase', 'SKIP', 'COINBASE_API_KEY_NAME is not set'); return; }
        if (trim($name) === '' || trim($secret) === '') {
            $this->add('coinbase', 'Coinbase', 'FAIL', 'set both COINBASE_API_KEY_NAME and COINBASE_API_KEY_SECRET', 'create a Secret API key on https://portal.cdp.coinbase.com');
            return;
        }
        if (!preg_match('#^organizations/[^/]+/apiKeys/[^/]+$#', trim($name)) && !preg_match('/^[0-9a-f-]{36}$/i', trim($name))) {
            $this->add('coinbase', 'COINBASE_API_KEY_NAME', 'WARN', 'not in the organizations/<org_id>/apiKeys/<key_id> (or key id) form', 'copy the key name from the CDP portal');
        }
        if ($name !== trim($name) || $secret !== trim($secret)) {
            $this->add('coinbase', 'Key format', 'WARN', 'the key name or secret has leading/trailing spaces', 'remove the spaces in .env');
        }
        try {
            $jwt = (new CryptoPayment(trim($name), str_replace('\n', "\n", trim($secret))))->jwt('GET', '/api/v1/checkouts');
        } catch (\Throwable $e) {
            $this->add('coinbase', 'Coinbase', 'FAIL', 'the secret cannot sign a request: ' . $this->scrub($e->getMessage()), 'paste the whole secret (Ed25519 base64, or the EC PEM with \n) as COINBASE_API_KEY_SECRET');
            return;
        }
        $base = rtrim($this->env('COINBASE_API_BASE') ?: 'https://business.coinbase.com', '/');
        $r = $this->http("$base/api/v1/checkouts?pageSize=1", ['Authorization: Bearer ' . $jwt, 'Accept: application/json']);
        if (!$this->reachable('coinbase', 'Coinbase', $r)) return;
        if ($r['status'] === 401) {
            $this->add('coinbase', 'Coinbase', 'FAIL', 'the key was rejected (401)', 'use a CDP Secret API key of the organization that has Coinbase Business');
        } elseif ($r['status'] === 403) {
            $this->add('coinbase', 'Coinbase', 'FAIL', 'the key is not allowed to read checkouts (403)', 'Coinbase Business must be enabled for this organization (supported countries only)');
        } elseif ($r['status'] !== 200) {
            $this->add('coinbase', 'Coinbase', 'FAIL', 'GET /api/v1/checkouts answered ' . $r['status'] . ': ' . $this->apiError($r), 'check the key in the CDP portal');
        } else {
            $this->add('coinbase', 'Coinbase', 'OK', 'key accepted (checkouts list readable)');
        }
    }

    private function watson()
    {
        $key = $this->env('WATSON_API_KEY', false);
        if (trim($key) === '') { $this->add('watson', 'Watson', 'SKIP', 'WATSON_API_KEY is not set'); return; }
        $this->secret('watson', 'WATSON_API_KEY', $key);
        $url = rtrim($this->env('WATSON_URL'), '/');
        $local = preg_match('#^http://(127\.0\.0\.1|localhost)(:\d+)?/#', $url . '/');
        if (!preg_match('#^https://api\.[a-z0-9-]+\.assistant\.watson\.cloud\.ibm\.com/instances/[A-Za-z0-9-]+$#', $url) && !$local) {
            $this->add('watson', 'WATSON_URL', 'FAIL', $url === ''? 'not set' : "'$url' is not an instance URL",
                'copy the URL from IBM Cloud > your watsonx Assistant > Manage: https://api.<region>.assistant.watson.cloud.ibm.com/instances/<instance_id> (without /v2)');
            return;
        }
        $this->add('watson', 'WATSON_URL', 'OK', $url);
        $version = '?version=' . ($this->env('WATSON_API_VERSION') ?: '2024-08-25');
        $assistant = $this->env('WATSON_ASSISTANT_ID');
        $r = $this->http("$url/v2/assistants$version", [], 'GET', null, 'apikey:' . trim($key));
        if (!$this->reachable('watson', 'Watson', $r)) return;
        if ($r['status'] === 401) {
            $this->add('watson', 'Watson', 'FAIL', 'the API key was rejected (401)', 'copy the API key of this instance (IBM Cloud > Manage > Credentials) into WATSON_API_KEY');
            return;
        }
        if ($r['status'] === 200 && isset($r['json']['assistants'])) {
            $ids = array_column($r['json']['assistants'], 'assistant_id');
            if ($assistant !== '' && !in_array($assistant, $ids)) {
                $this->add('watson', 'Watson', 'WARN', "key accepted but WATSON_ASSISTANT_ID is not one of the " . count($ids) . " assistants of this instance", 'copy the id from Assistant settings > API details');
            } else {
                $this->add('watson', 'Watson', 'OK', 'key accepted, ' . count($ids) . ' assistant(s) on this instance');
            }
            return;
        }
        // listing assistants is not allowed on every plan: prove the credentials with a session that is closed right away
        if ($assistant === '') {
            $this->add('watson', 'Watson', 'WARN', 'the assistants cannot be listed with this plan (' . $r['status'] . ')', 'set WATSON_ASSISTANT_ID so that doctor can verify the key with a session');
            return;
        }
        $env = $this->env('WATSON_ENVIRONMENT_ID');
        $sessions = "$url/v2/assistants/" . rawurlencode($assistant) . ($env? '/environments/' . rawurlencode($env) : '') . '/sessions';
        $s = $this->http($sessions . $version, ['Content-Type: application/json'], 'POST', '{}', 'apikey:' . trim($key));
        if (isset($s['json']['session_id'])) {
            $this->http("$sessions/" . rawurlencode($s['json']['session_id']) . $version, [], 'DELETE', null, 'apikey:' . trim($key));
            $this->add('watson', 'Watson', 'OK', 'key and assistant accepted (a test session was opened and closed: assistants cannot be listed with this plan)');
        } else {
            $this->add('watson', 'Watson', 'FAIL', 'the session could not be opened (' . $s['status'] . '): ' . $this->apiError($s), 'check WATSON_ASSISTANT_ID and WATSON_ENVIRONMENT_ID (Environment settings > API details)');
        }
    }

    private function mail()
    {
        $host = trim($this->env('MAIL_HOST'));
        if ($host === '' || in_array($host, self::PLACEHOLDERS)) { $this->add('mail', 'Mail', 'SKIP', 'MAIL_HOST is not set'); return; }
        $port = (int)($this->env('MAIL_PORT') ?: 587);
        $context = stream_context_create(['ssl' => ['peer_name' => $host]]);
        $errno = 0; $error = '';
        $socket = @stream_socket_client(($port === 465? 'ssl' : 'tcp') . "://$host:$port", $errno, $error, 5, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            $this->network('mail', 'Mail', $error ?: (error_get_last()['message'] ?? 'connection failed'), "$host:$port");
            return;
        }
        stream_set_timeout($socket, 5);
        $banner = $this->smtp($socket);
        if (substr($banner, 0, 3) !== '220') {
            fclose($socket);
            $this->add('mail', 'Mail', 'FAIL', "$host:$port is not an SMTP server (" . trim(strtok($banner, "\n")) . ')', 'check MAIL_HOST and MAIL_PORT (587 STARTTLS or 465 SSL)');
            return;
        }
        $ehlo = $this->smtp($socket, 'EHLO caligrafy-doctor');
        $tls = $port === 465? 'SSL' : 'no TLS';
        if ($port !== 465 && stripos($ehlo, 'STARTTLS') !== false) {
            $this->smtp($socket, 'STARTTLS');
            set_error_handler(function ($n, $m) use (&$error) { $error = $m; return true; });
            $ok = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            restore_error_handler();
            if (!$ok) {
                fclose($socket);
                $this->network('mail', 'Mail', $error ?: 'TLS handshake failed', "$host:$port");
                return;
            }
            $tls = 'STARTTLS';
            $ehlo = $this->smtp($socket, 'EHLO caligrafy-doctor');
        }
        $this->smtp($socket, 'QUIT');
        fclose($socket);
        $auth = preg_match('/AUTH[ =]([^\r\n]+)/i', $ehlo, $m)? 'AUTH ' . trim($m[1]) : 'no AUTH advertised';
        $this->add('mail', 'Mail', 'OK', "$host:$port answers EHLO ($tls, $auth). No mail sent: credentials are only checked when sending");
    }

    // --- helpers ----------------------------------------------------------------------------------

    private function add($check, $name, $status, $message, $fix = '')
    {
        $this->results[] = ['check' => $check, 'name' => $name, 'status' => $status, 'message' => $message, 'fix' => $fix];
    }

    /**
     * Value of a setting as loaded from .env (constants first, as the framework reads them)
     */
    private function env($name, $trim = true)
    {
        $value = defined($name)? constant($name) : ($_ENV[$name] ?? getenv($name));
        $value = is_bool($value)? ($value? 'true' : '') : (string)$value;
        return $trim? trim($value) : $value;
    }

    /**
     * Masks a secret: known prefix + last 4 characters, never more than a quarter of the value
     */
    public static function mask($value)
    {
        $value = trim((string)$value);
        $length = strlen($value);
        if ($length < 16) return "($length chars)";
        $prefix = '';
        foreach (self::PREFIXES as $p) if (strpos($value, $p) === 0) { $prefix = $p; break; }
        return $prefix . '…' . substr($value, -4) . " ($length chars)";
    }

    /**
     * Reports a key as OK (masked) or WARN when it has spaces or a known prefix without its separator
     * @return bool true when the format looks right
     */
    private function secret($check, $name, $value)
    {
        $problems = [];
        if ($value !== trim($value)) $problems[] = 'it has leading or trailing spaces';
        $clean = trim($value);
        foreach (self::PREFIXES as $p) {
            $bare = substr($p, 0, -1);
            if (strpos($clean, $bare) === 0 && strpos($clean, $p) !== 0 && !$this->startsWithAny($clean)) {
                $problems[] = "it starts with '$bare' but not '$p' (missing '" . substr($p, -1) . "')";
                break;
            }
        }
        if (preg_match('/^(["\']).*\1$/', $clean)) $problems[] = 'it is wrapped in quotes twice';
        if ($problems) {
            $this->add($check, $name, 'WARN', self::mask($value) . ': ' . implode('; ', $problems), "fix the value in .env: $name=<the key exactly as copied, no spaces>");
            return false;
        }
        $this->add($check, $name, 'OK', 'set ' . self::mask($value));
        return true;
    }

    private function startsWithAny($value)
    {
        foreach (self::PREFIXES as $p) if (strpos($value, $p) === 0) return true;
        return false;
    }

    /**
     * Configured model present in the list of the key, otherwise WARN with a few alternatives
     */
    private function models($check, $setting, $model, $available, $suggestions, $reason, $where = 'for this key')
    {
        if (in_array($model, $available, true)) {
            $this->add($check, $setting, 'OK', "$model is available");
            return;
        }
        $some = array_slice($suggestions, 0, 6);
        $this->add($check, $setting, 'WARN', "$model is not available $where" . ($some? ' (available: ' . implode(', ', $some) . ')' : ''),
            "$reason; or set $setting=<an available model> in .env");
    }

    /**
     * GET (or other method) with short timeouts; returns status, decoded JSON and the cURL error if any
     */
    private function http($url, $headers = [], $method = 'GET', $body = null, $userpwd = null)
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => self::TIMEOUT,
        ]);
        if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        if ($userpwd) curl_setopt($curl, CURLOPT_USERPWD, $userpwd);
        $response = curl_exec($curl);
        $result = ['status' => (int)curl_getinfo($curl, CURLINFO_HTTP_CODE), 'error' => curl_errno($curl)? curl_error($curl) : null,
                   'json' => is_string($response)? json_decode($response, true) : null, 'body' => is_string($response)? substr($response, 0, 300) : ''];
        return $result;
    }

    /**
     * false (and a FAIL with a network hint) when the request did not get an HTTP answer
     */
    private function reachable($check, $name, $r)
    {
        if ($r['error'] === null && $r['status'] > 0) return true;
        $this->network($check, $name, $r['error'] ?: 'no answer');
        return false;
    }

    private function network($check, $name, $error, $where = '')
    {
        $error = $this->scrub($error);
        $message = ($where? "$where: " : '') . $error;
        if (preg_match('/self[- ]signed certificate|unable to get local issuer|certificate verify failed|SSL certificate problem/i', $error)) {
            $fix = 'a TLS inspection proxy (corporate network: Zscaler, Netskope...) re-signs HTTPS. Give PHP the corporate root CA '
                . '(Claude plugin, server skill: "self-signed certificate in certificate chain" from PHP in Docker) or use another network';
        } elseif (preg_match('/resolve host|getaddrinfo|Name or service not known|nodename nor servname|php_network_getaddresses/i', $error)) {
            $fix = 'DNS: the host cannot be resolved. Check the URL, your network or VPN, and Docker DNS (docker compose exec dev-box getent hosts <host>)';
        } elseif (preg_match('/timed out|timeout/i', $error)) {
            $fix = 'timeout: a firewall or proxy blocks the port, or the service is down. Try curl from the same machine/container';
        } elseif (preg_match('/refused/i', $error)) {
            $fix = 'connection refused: wrong port or the service is not running';
        } elseif (preg_match('/SSL|TLS/i', $error)) {
            $fix = 'TLS handshake failed: check the port (SMTP: 587 STARTTLS, 465 SSL) or a proxy that intercepts HTTPS';
        } else {
            $fix = 'network error: check the URL and connectivity from where the app runs (inside Docker if you use it)';
        }
        $this->add($check, $name, 'FAIL', $message, $fix);
    }

    private function apiError($r)
    {
        $j = $r['json'];
        $message = is_array($j)? ($j['error']['message'] ?? $j['error'] ?? $j['errorMessage'] ?? $j['message'] ?? null) : null;
        return $this->scrub(is_string($message)? $message : trim(strip_tags($r['body'])));
    }

    /**
     * Removes anything that looks like a key from messages that come back from services
     */
    private function scrub($text)
    {
        $text = (string)$text;
        foreach (['OPEN_AI_KEY', 'GROQ_API_KEY', 'AGENT_API_KEY', 'TOGETHER_API_KEY', 'FIREWORKS_API_KEY', 'OPENROUTER_API_KEY', 'PAY_PRIVATE_KEY_TEST', 'PAY_PRIVATE_KEY_PRODUCTION', 'COINBASE_API_KEY_SECRET', 'WATSON_API_KEY', 'DB_PASSWORD', 'MAIL_PASSWORD', 'APP_KEY', 'API_KEY'] as $name) {
            $value = $this->env($name);
            if (strlen($value) >= 6) $text = str_replace($value, self::mask($value), $text);
        }
        return preg_replace('/\b(sk-[A-Za-z0-9_-]{8,}|gsk_[A-Za-z0-9]{8,}|fw_[A-Za-z0-9]{8,}|[sr]k_(test|live)_[A-Za-z0-9]{8,})/', '[key]', $text);
    }

    private function smtp($socket, $command = null)
    {
        if ($command !== null) fwrite($socket, "$command\r\n");
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $response;
    }
}
