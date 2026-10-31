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
 * Calls the capabilities of Caligrafy Cloud (POST <url>/capabilities/<name>) with a personal access token created in
 * its dashboard. The token is kept in .caligrafy/cloud.json of the project (git-ignored, never in the image), or given
 * with CALIGRAFY_CLOUD_TOKEN. CALIGRAFY_CLOUD_URL points to another service.
 */
class CloudClient
{
    const DEFAULT_URL = 'https://deploy.caligrafy.com';
    const PERIODS = array('week', 'month', 'year');

    private $root;
    private $http;

    /**
     * @param callable|null $http fn(string $method, string $url, array $headers, ?string $body): [int $status, string $body] (tests)
     */
    public function __construct($root = '.', $http = null)
    {
        $this->root = rtrim($root, '/');
        $this->http = $http ?: array($this, 'curl');
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
                    return $this->login($this->option($args, '--token'));
                case 'logout':
                    @unlink($this->file());
                    $this->out(" Signed out: .caligrafy/cloud.json removed.");
                    return 0;
                case 'account':
                    $a = $this->call('my_account');
                    $this->out(" {$a['email']}: {$a['credits']} credits" . ($a['verified'] ? '' : ' (confirm your email to deploy)') . ($a['free_trial_used'] ? '' : ', your first 30 minutes are free'));
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
                        $this->out("\n The repository is private: add this read-only deploy key on GitHub (Settings, Deploy keys, Add deploy key):\n\n {$app['deploy_key']}\n");
                    }
                    $this->out(" Next: php caligrafer.php cloud deploy $name");
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
                    $this->out(" {$app['url']} is online until {$app['online_until']} (server time). Credits left: {$app['credits_left']}." . ($app['restarted'] ? ' It is starting again.' : ''));
                    return 0;
                case 'credits':
                    if (!in_array((int) $name, array(5, 10, 25, 50), true)) return $this->usage('credits 5|10|25|50   (1 credit = 1 USD)');
                    $checkout = $this->call('buy_credits', array('pack' => (int) $name));
                    $this->out(" Pay on Stripe, then the credits appear in a few seconds:\n {$checkout['url']}");
                    return 0;
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
    public function call($capability, array $input = array())
    {
        $settings = $this->settings();
        if (empty($settings['token'])) {
            throw new \RuntimeException("Sign in first: php caligrafer.php cloud login (with a token from the dashboard of " . $this->url() . ")");
        }
        list($status, $body) = call_user_func($this->http, 'POST', $this->url() . '/capabilities/' . rawurlencode($capability),
            array('Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . $settings['token']), json_encode((object) $input));
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

    private function login($token)
    {
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
        @mkdir($this->root . '/.caligrafy', 0700, true);
        file_put_contents($this->file(), json_encode(array('url' => $this->url(), 'token' => $token), JSON_PRETTY_PRINT) . "\n");
        @chmod($this->file(), 0600);
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

    // waits for the deployment: running (exit 0) or failed (the end of the build log, exit 1)
    private function follow($name)
    {
        $until = time() + 900;
        do {
            sleep(5);
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
            . "  cloud login [--token cq_...]          sign in with a token from your dashboard\n"
            . "  cloud account                         your email and credits\n"
            . "  cloud create <name> [--repo <url>] [--branch <b>]   from this project's GitHub repository\n"
            . "  cloud deploy <name> [--no-wait]       build and start it (the first 30 minutes are free)\n"
            . "  cloud apps | status <name> | logs <name>\n"
            . "  cloud extend <name> week|month|year   2, 5 or 50 credits\n"
            . "  cloud credits 5|10|25|50              buy credits (1 credit = 1 USD) on Stripe\n"
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
