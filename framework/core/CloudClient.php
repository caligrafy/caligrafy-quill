<?php
/**
 * CloudClient.php
 * php caligrafer.php cloud: deploys a project to Caligrafy Cloud (https://deploy.caligrafy.com) from the command line
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class CloudClient
 * Calls the capabilities of Caligrafy Cloud (POST <url>/capabilities/<name>) with a personal access token of the
 * account. `cloud login` gets it by itself: it opens <url>/cli?code=..., the owner approves the code shown in the
 * terminal, and the command collects the token (start_cli_login, poll_cli_login). --token or --paste take one created
 * on the dashboard instead. The token is kept in .caligrafy/cloud.json of the project (git-ignored, never in the
 * image), or given with CALIGRAFY_CLOUD_TOKEN. CALIGRAFY_CLOUD_URL points to another service.
 */
class CloudClient
{
    const DEFAULT_URL = 'https://deploy.caligrafy.com';
    const PERIODS = array('week', 'month', 'year');

    private $root;
    private $http;
    private $sleep;

    /**
     * @param callable|null $http fn(string $method, string $url, array $headers, ?string $body): [int $status, string $body] (tests)
     * @param callable|null $sleep fn(int $seconds) between two checks of a sign-in or a deployment (tests)
     */
    public function __construct($root = '.', $http = null, $sleep = null)
    {
        $this->root = rtrim($root, '/');
        $this->http = $http ?: array($this, 'curl');
        $this->sleep = $sleep ?: 'sleep';
    }

    /**
     * @param array $args the words after "cloud"
     * @return int exit code
     */
    public function run(array $args)
    {
        $command = strtolower($args[0] ?? 'help');
        $name = $args[1] ?? null;
        try {
            switch ($command) {
                case 'login':
                    return $this->login($this->option($args, '--token'), $args);
                case 'logout':
                    @unlink($this->file());
                    $this->out(" Signed out: .caligrafy/cloud.json removed.");
                    return 0;
                case 'account':
                    $a = $this->call('my_account');
                    $this->out(" {$a['email']}: " . (!empty($a['unlimited']) ? 'unlimited (no credits needed, your apps stay online with no end)'
                        : "{$a['credits']} credits, every new app is free for its first " . ($a['free_minutes_per_app'] ?? 30) . ' minutes')
                        . ($a['verified'] ? '' : ' (confirm your email to deploy)'));
                    return 0;
                case 'apps':
                    $apps = $this->call('list_apps');
                    if (!$apps) $this->out(" No app yet: php caligrafer.php cloud create <name>");
                    foreach ($apps as $app) $this->out(sprintf(" %-24s %-10s %s", $app['name'], $app['status'], $app['url'] . $this->left($app)));
                    return 0;
                case 'create':
                    $repo = $this->option($args, '--repo') ?: $this->originRepo();
                    if (!$name || !$repo) return $this->usage('create <name> [--repo https://github.com/<owner>/<repo>] [--branch <branch>]');
                    $input = array('name' => $name, 'repo_url' => $repo);
                    if ($branch = $this->option($args, '--branch')) $input['branch'] = $branch;
                    $app = $this->call('create_app', $input);
                    $this->out(" Created {$app['url']} from {$app['repo_url']} ({$app['branch']}).");
                    if (!empty($app['deploy_key'])) {
                        $file = $this->saveKey($name, $app['deploy_key']);
                        $this->out("\n The repository is private: add this read-only deploy key on GitHub (Settings, Deploy keys, Add deploy key),"
                            . "\n or with the GitHub CLI: gh repo deploy-key add $file --title \"Caligrafy Cloud\"\n\n {$app['deploy_key']}\n");
                    }
                    $this->out(" Next: php caligrafer.php cloud deploy $name");
                    return 0;
                case 'key':
                    // the deploy key alone, for scripts: php caligrafer.php cloud key <name> > key.pub
                    if (!$name) return $this->usage('key <name>');
                    $app = $this->call('get_app', array('name' => $name));
                    if (empty($app['deploy_key'])) {
                        $this->out(" $name is built from a public repository: it needs no deploy key.");
                        return 1;
                    }
                    $this->saveKey($name, $app['deploy_key']);
                    $this->out($app['deploy_key']);
                    return 0;
                case 'deploy':
                    if (!$name) return $this->usage('deploy <name>');
                    $app = $this->call('deploy_app', array('name' => $name));
                    $this->out(" Deploying {$app['url']} (a few minutes)...");
                    return in_array('--no-wait', $args, true) ? 0 : $this->follow($name);
                case 'status':
                    if (!$name) return $this->usage('status <name>');
                    $app = $this->call('get_app', array('name' => $name));
                    $this->out(" {$app['url']}: {$app['status']}{$this->left($app)}");
                    return 0;
                case 'logs':
                    if (!$name) return $this->usage('logs <name>');
                    $logs = $this->call('app_logs', array('name' => $name));
                    $this->out($logs['logs'] !== '' ? $logs['logs'] : " No logs ({$logs['status']}).");
                    return 0;
                case 'extend':
                    $period = strtolower($args[2] ?? '');
                    if (!$name || !in_array($period, self::PERIODS, true)) return $this->usage('extend <name> week|month|year   (2, 5 or 50 credits)');
                    $app = $this->call('extend_app', array('name' => $name, 'period' => $period));
                    $this->out(" {$app['url']} is online until " . date('Y-m-d H:i T', strtotime($app['online_until'])) . ". Credits left: {$app['credits_left']}." . ($app['restarted'] ? ' It is starting again.' : ''));
                    return 0;
                case 'credits':
                    if (!in_array((int) $name, array(5, 10, 25, 50), true)) return $this->usage('credits 5|10|25|50   (1 credit = 1 USD)');
                    $checkout = $this->call('buy_credits', array('pack' => (int) $name));
                    $this->out(" Pay on Stripe, then the credits appear in a few seconds:\n {$checkout['url']}");
                    return 0;
                case 'submit':
                    // the listing the marketplace skill wrote (.caligrafy/marketplace.json), sent privately, then a deploy
                    if (!$name) return $this->usage('submit <name> [--file .caligrafy/marketplace.json] [--no-deploy]');
                    $profile = $this->call('submit_app_listing', $this->listing($name, $this->option($args, '--file') ?: '.caligrafy/marketplace.json'));
                    $this->out(" Submitted the marketplace listing of $name: Caligrafy reviews it, sets the final price and publishes it ({$profile['status']}).");
                    if (in_array('--no-deploy', $args, true)) return 0;
                    // a new deployment: the key pages are screenshot once it is online
                    $app = $this->call('deploy_app', array('name' => $name));
                    $this->out(" Deploying {$app['url']} again, for the screenshots of its key pages...");
                    return in_array('--no-wait', $args, true) ? 0 : $this->follow($name);
                case 'secret':
                    return $this->secret(strtolower($args[1] ?? ''), $args[2] ?? null, $args[3] ?? null);
                case 'delete':
                    if (!$name) return $this->usage('delete <name> --yes');
                    if (!in_array('--yes', $args, true)) {
                        $this->out(" This deletes $name, its database and its files for good. Add --yes to confirm.");
                        return 1;
                    }
                    $this->call('delete_app', array('name' => $name));
                    $this->out(" Deleted $name.");
                    return 0;
                default:
                    return $this->help();
            }
        } catch (\RuntimeException $e) {
            $this->out(' ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Calls a capability: returns its data, or throws a RuntimeException with its message (and field messages)
     */
    public function call($capability, array $input = array(), $signedIn = true)
    {
        $headers = array('Accept: application/json', 'Content-Type: application/json');
        if ($signedIn) {
            $settings = $this->settings();
            if (empty($settings['token'])) {
                throw new \RuntimeException("Sign in first: php caligrafer.php cloud login (it opens " . $this->url() . " to approve)");
            }
            $headers[] = 'Authorization: Bearer ' . $settings['token'];
        }
        list($status, $body) = call_user_func($this->http, 'POST', $this->url() . '/capabilities/' . rawurlencode($capability),
            $headers, json_encode((object) $input));
        $json = json_decode((string) $body, true);
        if ($status >= 200 && $status < 300 && is_array($json) && array_key_exists('data', $json)) {
            return $json['data'];
        }
        $error = $json['error'] ?? null;
        if (is_array($error)) {
            $fields = !empty($error['fields']) ? ': ' . implode(' ', (array) $error['fields']) : '';
            throw new \RuntimeException(($error['message'] ?? 'Refused') . $fields);
        }
        throw new \RuntimeException($status === 0 ? 'Caligrafy Cloud could not be reached (' . $this->url() . ')' : "Caligrafy Cloud answered $status");
    }

    private function login($token, array $args = array())
    {
        if (!$token && !in_array('--paste', $args, true)) {
            return $this->browserLogin(!in_array('--no-browser', $args, true));
        }
        if (!$token) {
            $this->out(" Paste the token created in your dashboard (" . $this->url() . "/dashboard), then press Enter:");
            @system('stty -echo 2>/dev/null');
            $token = trim((string) fgets(STDIN));
            @system('stty echo 2>/dev/null');
        }
        if (!preg_match('/^cq_\d+_[0-9a-f]+$/', (string) $token)) {
            $this->out(" This is not a Caligrafy token (cq_...).");
            return 1;
        }
        $this->saveToken($token);
        try {
            $account = $this->call('my_account');
        } catch (\RuntimeException $e) {
            @unlink($this->file());
            $this->out(' ' . $e->getMessage());
            return 1;
        }
        $this->out(" Signed in as {$account['email']} ({$account['credits']} credits). The token is in .caligrafy/cloud.json (git-ignored).");
        return 0;
    }

    private function secret($action, $name, $pair)
    {
        if ($action === 'list' && $name) {
            $keys = $this->call('list_secrets', array('name' => $name));
            $this->out($keys ? ' ' . implode("\n ", $keys) : " No settings for $name.");
            return 0;
        }
        if ($action === 'set' && $name && $pair && strpos($pair, '=') !== false) {
            list($key, $value) = explode('=', $pair, 2);
            $this->call('set_secret', array('name' => $name, 'key' => $key, 'value' => $value));
            $this->out(" $key saved for $name: php caligrafer.php cloud deploy $name applies it.");
            return 0;
        }
        if ($action === 'delete' && $name && $pair) {
            $this->call('delete_secret', array('name' => $name, 'key' => $pair));
            $this->out(" $pair removed from $name: php caligrafer.php cloud deploy $name applies it.");
            return 0;
        }
        return $this->usage('secret set <name> KEY=value | secret list <name> | secret delete <name> KEY');
    }

    // cloud login without a token: the owner approves the code in the browser, the command collects the token
    private function browserLogin($openBrowser)
    {
        $device = 'caligrafer on ' . (gethostname() ?: 'this computer') . ' (' . basename((string) (realpath($this->root) ?: $this->root)) . ')';
        $start = $this->call('start_cli_login', array('device_name' => substr($device, 0, 100)), false);
        $this->out(" To sign in, approve this code in your browser: {$start['user_code']}\n {$start['verification_url']}\n"
            . " (sign up or sign in there first if needed). Waiting for the approval...");
        if ($openBrowser) {
            $this->openBrowser($start['verification_url']);
        }
        $until = time() + (int) $start['expires_in'];
        do {
            call_user_func($this->sleep, max(1, (int) $start['interval']));
            $poll = $this->call('poll_cli_login', array('device_code' => $start['device_code']), false);
            if ($poll['status'] === 'approved' && !empty($poll['token'])) {
                $this->saveToken($poll['token']);
                $account = $this->call('my_account');
                $this->out(" Signed in as {$account['email']} ({$account['credits']} credits). The token is in .caligrafy/cloud.json (git-ignored).");
                return 0;
            }
            if ($poll['status'] === 'denied' || $poll['status'] === 'expired') {
                $this->out(" The sign-in was {$poll['status']}: php caligrafer.php cloud login starts another one.");
                return 1;
            }
        } while (time() < $until);
        $this->out(" The code expired before it was approved: php caligrafer.php cloud login starts another one.");
        return 1;
    }

    // opens a page in the browser of this computer (nothing inside a container: the URL is printed for the host)
    private function openBrowser($url)
    {
        if (is_file('/.dockerenv') || getenv('CALIGRAFY_NO_BROWSER')) {
            return;
        }
        $command = PHP_OS_FAMILY === 'Darwin' ? 'open' : (PHP_OS_FAMILY === 'Windows' ? 'start ""' : 'xdg-open');
        @exec($command . ' ' . escapeshellarg($url) . ' > /dev/null 2>&1 &');
    }

    private function saveToken($token)
    {
        @mkdir($this->root . '/.caligrafy', 0700, true);
        file_put_contents($this->file(), json_encode(array('url' => $this->url(), 'token' => $token), JSON_PRETTY_PRINT) . "\n");
        @chmod($this->file(), 0600);
    }

    // the deploy key of an app in .caligrafy/<name>-deploy-key.pub (public: safe to keep), for gh repo deploy-key add
    private function saveKey($name, $key)
    {
        @mkdir($this->root . '/.caligrafy', 0700, true);
        $file = '.caligrafy/' . preg_replace('/[^a-z0-9-]/', '', strtolower($name)) . '-deploy-key.pub';
        file_put_contents($this->root . '/' . $file, trim($key) . "\n");
        return $file;
    }

    /**
     * The input of submit_app_listing from the marketplace file: {"title", "description", "prompt", "images": [{"file",
     * "caption"}] (1 to 5 screenshots next to the file, all different), "pages": [{"path", "caption"}] (optional),
     * "price": {"low", "high", "rationale"}}
     */
    private function listing($name, $path)
    {
        $file = strpos($path, '/') === 0 ? $path : $this->root . '/' . $path;
        if (!is_file($file)) throw new \RuntimeException("No $path: run the marketplace skill of the Claude Code plugin first (/caligrafy-quill:marketplace)");
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) throw new \RuntimeException("$path is not valid JSON");
        foreach (array('title', 'description', 'prompt') as $key) {
            if (!is_string($data[$key] ?? null) || trim($data[$key]) === '') throw new \RuntimeException("$path has no $key");
        }
        if (!isset($data['price']['low'], $data['price']['high'])) throw new \RuntimeException("$path has no estimated price (price.low, price.high)");
        $images = array();
        $seen = array();
        foreach ((array) ($data['images'] ?? array()) as $image) {
            $source = dirname($file) . '/' . ltrim((string) ($image['file'] ?? ''), '/');
            $type = is_file($source) ? (array('png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp')[strtolower(pathinfo($source, PATHINFO_EXTENSION))] ?? null) : null;
            if (!$type || filesize($source) > 1000000) throw new \RuntimeException("Image {$image['file']}: a PNG, JPEG or WebP file of 1 MB at most, next to $path");
            // the same screen twice is sent once
            if (isset($seen[sha1_file($source)])) continue;
            $seen[sha1_file($source)] = true;
            $images[] = array('image' => "data:$type;base64," . base64_encode(file_get_contents($source)), 'caption' => (string) ($image['caption'] ?? ''));
        }
        if (!$images && empty($data['pages'])) throw new \RuntimeException("$path has no screenshots (images): the skill takes them along the experience");
        if (count($images) > 5) throw new \RuntimeException("$path has more than 5 screenshots");
        return array('name' => $name, 'title' => $data['title'], 'description' => $data['description'], 'prompt' => $data['prompt'],
            'pages' => json_encode(array_values((array) ($data['pages'] ?? array()))), 'images' => json_encode($images),
            'price_low' => (string) $data['price']['low'], 'price_high' => (string) $data['price']['high'],
            'price_rationale' => (string) ($data['price']['rationale'] ?? ''));
    }

    // waits for the deployment: running (exit 0) or failed (the end of the build log, exit 1)
    private function follow($name)
    {
        $until = time() + 900;
        do {
            call_user_func($this->sleep, 5);
            $app = $this->call('get_app', array('name' => $name));
            if ($app['status'] === 'running') {
                $this->out(" Online: {$app['url']}" . $this->left($app));
                return 0;
            }
            if ($app['status'] === 'failed') {
                $logs = $this->call('app_logs', array('name' => $name));
                $this->out(" The deployment failed. The end of the build log:\n" . implode("\n", array_slice(explode("\n", $logs['logs']), -30)));
                return 1;
            }
        } while (time() < $until);
        $this->out(" Still deploying after 15 minutes: php caligrafer.php cloud status $name");
        return 1;
    }

    private function left(array $app)
    {
        // an unlimited account's apps have no end (Caligrafy Cloud reports no_end)
        if (!empty($app['no_end'])) return $app['status'] === 'paused' ? ' (paused: php caligrafer.php cloud extend ' . $app['name'] . ' week restarts it, at no cost)' : ' (no end)';
        $m = (int) ($app['time_left_minutes'] ?? 0);
        if ($m <= 0) return $app['status'] === 'paused' ? ' (paused: php caligrafer.php cloud extend ' . $app['name'] . ' week)' : '';
        return ' (' . ($m >= 2880 ? round($m / 1440) . ' days' : ($m >= 120 ? round($m / 60) . ' hours' : "$m minutes")) . ' left' . (!empty($app['on_trial']) ? ', free trial' : '') . ')';
    }

    // https://github.com/<owner>/<repo> of the git remote "origin", if it is on GitHub
    private function originRepo()
    {
        $remote = trim((string) @shell_exec('git -C ' . escapeshellarg($this->root) . ' remote get-url origin 2>/dev/null'));
        if (preg_match('#github\.com[:/]([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(\.git)?$#', $remote, $m)) {
            return "https://github.com/$m[1]/$m[2]";
        }
        return null;
    }

    private function option(array $args, $name)
    {
        $at = array_search($name, $args, true);
        return $at !== false ? ($args[$at + 1] ?? null) : null;
    }

    private function settings()
    {
        $saved = is_file($this->file()) ? (json_decode((string) file_get_contents($this->file()), true) ?: array()) : array();
        if (getenv('CALIGRAFY_CLOUD_TOKEN')) $saved['token'] = getenv('CALIGRAFY_CLOUD_TOKEN');
        return $saved;
    }

    private function url()
    {
        return rtrim(getenv('CALIGRAFY_CLOUD_URL') ?: ($this->savedUrl() ?: self::DEFAULT_URL), '/');
    }

    private function savedUrl()
    {
        return is_file($this->file()) ? (json_decode((string) file_get_contents($this->file()), true)['url'] ?? null) : null;
    }

    private function file()
    {
        return $this->root . '/.caligrafy/cloud.json';
    }

    private function curl($method, $url, array $headers, $body)
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, array(CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 120));
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return array($response === false ? 0 : $status, (string) $response);
    }

    private function usage($text)
    {
        $this->out(" Usage: php caligrafer.php cloud $text");
        return 1;
    }

    private function help()
    {
        $this->out(" Caligrafy Cloud: put this project online at https://<name>.caligrafy.com (" . $this->url() . ")\n"
            . "  cloud login [--no-browser]            sign in: approve the code in your browser (--token cq_... or --paste: a dashboard token)\n"
            . "  cloud account                         your email and credits\n"
            . "  cloud create <name> [--repo <url>] [--branch <b>]   from this project's GitHub repository\n"
            . "  cloud key <name>                      the read-only deploy key of a private repository (gh repo deploy-key add)\n"
            . "  cloud deploy <name> [--no-wait]       build and start it (every new app: 30 free minutes from its first deploy)\n"
            . "  cloud apps | status <name> | logs <name>\n"
            . "  cloud extend <name> week|month|year   2, 5 or 50 credits\n"
            . "  cloud credits 5|10|25|50              buy credits (1 credit = 1 USD) on Stripe\n"
            . "  cloud submit <name> [--no-deploy]     send the marketplace listing (.caligrafy/marketplace.json), then deploy\n"
            . "  cloud secret set <name> KEY=value | secret list <name> | secret delete <name> KEY\n"
            . "  cloud delete <name> --yes             the app, its database and files, for good\n"
            . "  cloud logout");
        return 0;
    }

    private function out($text)
    {
        print($text . "\n");
    }
}
