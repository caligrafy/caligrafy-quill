<?php
/**
 * Notify.php
 * Notifications: one server-side call reaches a user on every channel they turned on (web push, confirmed email)
 * @copyright 2026
 *
*/

namespace Caligrafy;

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

/**
 * Class Notify
 * Notify::user($userId, ['title' => ..., 'body' => ..., 'url' => ..., 'tag' => ..., 'urgency' => ...]) sends to every
 * channel the user turned on and logs the result (caligrafy_notifications: never the body; kept 30 days):
 * - push: each browser the user subscribed (caligrafy_push_subscriptions), with the VAPID keys of .env (php caligrafer.php
 *   notify keys), 5 seconds per push service, the message kept 24 hours for a device that is off; a 404 or 410 deletes
 *   the subscription, so do 5 failures in a row
 * - push to the phone apps (2.27.0, php caligrafer.php mobile): each iPhone or iPad the user turned it on in, through
 *   Apple (APNS_KEY, APNS_KEY_ID, APNS_TEAM_ID, APNS_TOPIC), and each Android device through Firebase
 *   (FCM_SERVICE_ACCOUNT), registered in caligrafy_push_devices (notify_register_device); a device Apple or Firebase
 *   says is gone is deleted, so is one after 5 failures in a row. Signing out of the app (/mobile/signout) forgets it
 * - email: the user's confirmed address only (caligrafy_notify_emails), through MAIL_*, with a one-click unsubscribe link
 *   (a signed URL) and the List-Unsubscribe headers
 * A delivery failure never throws; a bad message throws ServiceError::invalid. At most NOTIFY_RATE_PER_HOUR messages per
 * user and hour (30): above it nothing is sent ('skipped' => 'rate'). In-page alerts stay the app's job.
 * Without the tables (php caligrafer.php platform install) nothing is sent and nothing fails.
 */
class Notify
{
    const CHANNELS = array('push', 'email');
    const SUBSCRIPTIONS = 'caligrafy_push_subscriptions';
    const DEVICES = 'caligrafy_push_devices';
    const PROVIDERS = array('apns', 'fcm');
    const EMAILS = 'caligrafy_notify_emails';
    const PREFS = 'caligrafy_notify_prefs';
    const LOG = 'caligrafy_notifications';
    const TTL = 86400;
    const PUSH_TIMEOUT = 5;
    const MAX_FAILURES = 5;
    const PAYLOAD_BYTES = 3072;
    const TOKEN_HOURS = 24;
    const KEEP_DAYS = 30;

    /** @var callable|null replaces the push sender in tests: fn(array $subscriptions, string $payload, array $options) => [endpoint => status] */
    public static $pushSender = null;

    /** @var callable|null replaces the mail sender in tests: fn($to, $subject, $html, array $headers) => bool */
    public static $mailSender = null;

    /** @var callable|null replaces the phone sender in tests: fn(array $device, array $message) => [int status, string reason] */
    public static $deviceSender = null;

    // ---------------------------------------------------------------------------------------------------------------
    // Sending

    /**
     * Sends a message to a user on every channel they turned on
     * @param int|string $userId the id Context::userId() returns
     * @param array $message title (80), body (300), url (https or a path of the app), tag, urgency (low, normal, high)
     * @return array push (how many browsers got it), email (whether it was sent), skipped (null, or 'rate')
     * @throws ServiceError invalid, on a bad message
     */
    public static function user($userId, array $message)
    {
        $message = self::message($message);
        $result = array('push' => 0, 'email' => false, 'skipped' => null);
        try {
            $recent = Database::toArray(Database::execute('SELECT COUNT(*) AS n FROM ' . self::LOG . ' WHERE user_id = ? AND created_at > ? AND skipped IS NULL',
                array((string) $userId, date('Y-m-d H:i:s', time() - 3600))));
        } catch (\Throwable $e) {
            return $result + array('reason' => 'not installed: php caligrafer.php platform install');
        }
        if ((int) ($recent[0]['n'] ?? 0) >= self::rate()) {
            $result['skipped'] = 'rate';
            self::log($userId, $message, 0, 'off', 'rate');
            return $result;
        }
        $offered = self::offered();
        $email = 'off';
        if (in_array('push', $offered, true) && self::enabled($userId, 'push')) {
            $result['push'] = self::push($userId, $message) + self::pushDevices($userId, $message);
        }
        if (in_array('email', $offered, true) && self::enabled($userId, 'email')) {
            $email = self::email($userId, $message);
            $result['email'] = $email === 'sent';
        }
        self::log($userId, $message, $result['push'], $email, null);
        return $result;
    }

    /**
     * Deletes everything notifications keep about a user (their subscriptions, address, choices and log): call it when
     * you delete the user
     */
    public static function forget($userId)
    {
        foreach (array(self::SUBSCRIPTIONS, self::DEVICES, self::EMAILS, self::PREFS, self::LOG) as $table) {
            try { Database::execute("DELETE FROM $table WHERE user_id = ?", array((string) $userId)); } catch (\Throwable $e) {}
        }
    }

    /**
     * The message, checked and completed (urgency normal)
     * @throws ServiceError invalid
     */
    public static function message(array $message)
    {
        $fields = array();
        $title = trim((string) ($message['title'] ?? ''));
        $body = trim((string) ($message['body'] ?? ''));
        $url = trim((string) ($message['url'] ?? ''));
        $tag = (string) ($message['tag'] ?? '');
        $urgency = (string) ($message['urgency'] ?? 'normal');
        if ($title === '' || mb_strlen($title) > 80) $fields['title'] = 'A title of 1 to 80 characters';
        if ($body === '' || mb_strlen($body) > 300) $fields['body'] = 'A body of 1 to 300 characters';
        if ($url !== '' && !preg_match('#^(https://[^\s]+|/[^\s]*)$#', $url)) $fields['url'] = 'An https:// address or a path of the app starting with /';
        if ($tag !== '' && !preg_match('/^[A-Za-z0-9_.:-]{1,64}$/', $tag)) $fields['tag'] = 'At most 64 letters, digits, _ . : -';
        if (!in_array($urgency, array('low', 'normal', 'high'), true)) $fields['urgency'] = 'low, normal or high';
        if ($fields) throw ServiceError::invalid($fields);
        return array('title' => $title, 'body' => $body, 'url' => $url, 'tag' => $tag, 'urgency' => $urgency);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Web push

    /**
     * Whether the VAPID keys are set (php caligrafer.php notify keys)
     */
    public static function pushConfigured()
    {
        return trim((string) getenv('VAPID_PUBLIC_KEY')) !== '' && trim((string) getenv('VAPID_PRIVATE_KEY')) !== '' && trim((string) getenv('VAPID_SUBJECT')) !== '';
    }

    /**
     * A new pair of VAPID keys (base64url): public and private
     */
    public static function keys()
    {
        Features::need('push', 'Web push (Caligrafy\\Notify)');   // minishlink/web-push is optional since 2.26.0
        $keys = VAPID::createVapidKeys();
        return array('public' => $keys['publicKey'], 'private' => $keys['privateKey']);
    }

    /**
     * Saves a browser's push subscription (the JSON of PushSubscription) for a user; an endpoint belongs to one user
     */
    public static function subscribe($userId, array $subscription, $userAgent = '')
    {
        $endpoint = (string) ($subscription['endpoint'] ?? '');
        $p256dh = (string) ($subscription['keys']['p256dh'] ?? '');
        $auth = (string) ($subscription['keys']['auth'] ?? '');
        $fields = array();
        // push services are https; http only on this computer (a local push service in tests)
        if (!preg_match('#^(https://[^\s]{10,2000}|http://(localhost|127\.0\.0\.1)(:\d+)?/[^\s]*)$#', $endpoint)) $fields['endpoint'] = 'An https:// push endpoint';
        if (!preg_match('/^[A-Za-z0-9_-]{40,200}$/', $p256dh)) $fields['keys.p256dh'] = 'The p256dh key of the subscription';
        if (!preg_match('/^[A-Za-z0-9_-]{10,100}$/', $auth)) $fields['keys.auth'] = 'The auth secret of the subscription';
        if ($fields) throw ServiceError::invalid($fields);
        $hash = hash('sha256', $endpoint);
        Database::execute('INSERT INTO ' . self::SUBSCRIPTIONS . ' (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent, created_at, failures) VALUES (?, ?, ?, ?, ?, ?, ?, 0) '
            . 'ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = VALUES(user_agent), failures = 0',
            array((string) $userId, $endpoint, $hash, $p256dh, $auth, mb_substr((string) $userAgent, 0, 255), date('Y-m-d H:i:s')));
        return true;
    }

    /**
     * Deletes one of the user's subscriptions (only theirs): whether one was deleted
     */
    public static function unsubscribe($userId, $endpoint)
    {
        $before = self::count(self::SUBSCRIPTIONS, $userId);
        Database::execute('DELETE FROM ' . self::SUBSCRIPTIONS . ' WHERE user_id = ? AND endpoint_hash = ?', array((string) $userId, hash('sha256', (string) $endpoint)));
        return self::count(self::SUBSCRIPTIONS, $userId) < $before;
    }

    /**
     * Sends the message to each of the user's browsers: how many accepted it
     */
    private static function push($userId, array $message)
    {
        if (!self::pushConfigured()) return 0;
        $rows = Database::toArray(Database::execute('SELECT * FROM ' . self::SUBSCRIPTIONS . ' WHERE user_id = ?', array((string) $userId))) ?: array();
        if (!$rows) return 0;
        // minishlink/web-push is optional since 2.26.0: without it push is skipped (the email channel still works), the
        // subscriptions are kept, and the log says how to add it
        if (!self::$pushSender && !Features::installed('push')) {
            error_log('Caligrafy\\Notify: ' . Features::message('push', 'Web push (Caligrafy\\Notify)'));
            return 0;
        }
        $payload = json_encode(array('title' => $message['title'], 'body' => $message['body'], 'url' => $message['url'] !== '' ? self::absolute($message['url']) : self::base(),
            'tag' => $message['tag'] !== '' ? $message['tag'] : null, 'icon' => self::base() . 'public/images/resources/caligrafy-mark.svg'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($payload) > self::PAYLOAD_BYTES) $payload = json_encode(array('title' => $message['title'], 'body' => mb_substr($message['body'], 0, 120), 'url' => self::base()), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $options = array('TTL' => self::TTL, 'urgency' => $message['urgency']);
        if ($message['tag'] !== '') $options['topic'] = substr(preg_replace('/[^A-Za-z0-9_-]/', '-', $message['tag']), 0, 32);
        $statuses = self::$pushSender ? call_user_func(self::$pushSender, $rows, $payload, $options) : self::sendPush($rows, $payload, $options);
        $sent = 0;
        foreach ($rows as $row) {
            $status = (int) ($statuses[$row['endpoint']] ?? 0);
            if ($status >= 200 && $status < 300) {
                $sent++;
                Database::execute('UPDATE ' . self::SUBSCRIPTIONS . ' SET last_success_at = ?, failures = 0 WHERE id = ?', array(date('Y-m-d H:i:s'), $row['id']));
            } elseif ($status === 404 || $status === 410 || (int) $row['failures'] + 1 >= self::MAX_FAILURES) {
                Database::execute('DELETE FROM ' . self::SUBSCRIPTIONS . ' WHERE id = ?', array($row['id']));
            } else {
                Database::execute('UPDATE ' . self::SUBSCRIPTIONS . ' SET failures = failures + 1 WHERE id = ?', array($row['id']));
            }
        }
        return $sent;
    }

    private static function sendPush(array $rows, $payload, array $options)
    {
        $webPush = new WebPush(array('VAPID' => array('subject' => getenv('VAPID_SUBJECT'), 'publicKey' => getenv('VAPID_PUBLIC_KEY'), 'privateKey' => getenv('VAPID_PRIVATE_KEY'))),
            $options, new \GuzzleHttp\Client(array('timeout' => self::PUSH_TIMEOUT, 'connect_timeout' => self::PUSH_TIMEOUT)));
        foreach ($rows as $row) {
            $webPush->queueNotification(Subscription::create(array('endpoint' => $row['endpoint'], 'publicKey' => $row['p256dh'], 'authToken' => $row['auth'], 'contentEncoding' => 'aes128gcm')), $payload);
        }
        $statuses = array();
        foreach ($webPush->flush() as $report) {
            $response = $report->getResponse();
            $statuses[$report->getEndpoint()] = $response ? $response->getStatusCode() : 0;
        }
        return $statuses;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Push to the phone apps (2.27.0)

    /**
     * Whether a phone provider is set up: apns (APNS_KEY, APNS_KEY_ID, APNS_TEAM_ID, APNS_TOPIC) or fcm (FCM_SERVICE_ACCOUNT)
     */
    public static function deviceConfigured($provider)
    {
        if ($provider === 'apns') {
            foreach (array('APNS_KEY', 'APNS_KEY_ID', 'APNS_TEAM_ID', 'APNS_TOPIC') as $name) {
                if (trim((string) getenv($name)) === '') return false;
            }
            return true;
        }
        return $provider === 'fcm' && self::serviceAccount() !== null;
    }

    /**
     * Saves a phone's push token for a user (one token belongs to one user): apns (an iPhone, iPad) or fcm (Android)
     * @param int|null $appTokenId the app token it signed in with (Context::$token['id']): signing out forgets the device
     */
    public static function registerDevice($userId, $provider, $deviceToken, $appTokenId = null, $name = '')
    {
        $provider = strtolower(trim((string) $provider));
        $deviceToken = trim((string) $deviceToken);
        $fields = array();
        if (!in_array($provider, self::PROVIDERS, true)) $fields['provider'] = 'apns (iOS) or fcm (Android)';
        elseif ($provider === 'apns' && !preg_match('/^[0-9a-fA-F]{64,200}$/', $deviceToken)) $fields['token'] = 'The device token Apple gave the app (hexadecimal)';
        elseif ($provider === 'fcm' && !preg_match('/^[A-Za-z0-9_:\-.]{20,4096}$/', $deviceToken)) $fields['token'] = 'The registration token Firebase gave the app';
        if (strlen($deviceToken) > 512) $fields['token'] = 'At most 512 characters';
        if ($fields) throw ServiceError::invalid($fields);
        if ($provider === 'apns') $deviceToken = strtolower($deviceToken);
        Database::execute('INSERT INTO ' . self::DEVICES . ' (user_id, provider, device_token, token_hash, app_token_id, name, created_at, failures) VALUES (?, ?, ?, ?, ?, ?, ?, 0) '
            . 'ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), provider = VALUES(provider), app_token_id = VALUES(app_token_id), name = VALUES(name), failures = 0',
            array((string) $userId, $provider, $deviceToken, hash('sha256', $deviceToken), $appTokenId !== null ? (int) $appTokenId : null,
                mb_substr(trim((string) $name), 0, 100) ?: null, date('Y-m-d H:i:s')));
        return true;
    }

    /**
     * Deletes one of the user's phones (only theirs): whether one was deleted
     */
    public static function forgetDevice($userId, $deviceToken)
    {
        $token = trim((string) $deviceToken);
        Database::execute('DELETE FROM ' . self::DEVICES . ' WHERE user_id = ? AND token_hash IN (?, ?)', array((string) $userId, hash('sha256', $token), hash('sha256', strtolower($token))));
        return Database::rowCount() > 0;
    }

    /**
     * Forgets the phones that signed in with an app token (its sign-out)
     */
    public static function forgetDevicesOfToken($appTokenId)
    {
        try { Database::execute('DELETE FROM ' . self::DEVICES . ' WHERE app_token_id = ?', array((int) $appTokenId)); } catch (\Throwable $e) {}
    }

    /**
     * Sends the message to each of the user's phones: how many accepted it
     */
    private static function pushDevices($userId, array $message)
    {
        try {
            $rows = Database::toArray(Database::execute('SELECT * FROM ' . self::DEVICES . ' WHERE user_id = ?', array((string) $userId))) ?: array();
        } catch (\Throwable $e) {
            return 0;     // a project created before 2.27.0 without the table: php caligrafer.php platform install
        }
        $sent = 0;
        foreach ($rows as $row) {
            if (!self::$deviceSender && !self::deviceConfigured($row['provider'])) continue;
            list($status, $reason) = self::$deviceSender ? call_user_func(self::$deviceSender, $row, $message) : self::sendDevice($row, $message);
            if ($status >= 200 && $status < 300) {
                $sent++;
                Database::execute('UPDATE ' . self::DEVICES . ' SET last_success_at = ?, failures = 0 WHERE id = ?', array(date('Y-m-d H:i:s'), $row['id']));
            } elseif (self::deviceGone($row['provider'], (int) $status, (string) $reason) || (int) $row['failures'] + 1 >= self::MAX_FAILURES) {
                Database::execute('DELETE FROM ' . self::DEVICES . ' WHERE id = ?', array($row['id']));
            } else {
                Database::execute('UPDATE ' . self::DEVICES . ' SET failures = failures + 1 WHERE id = ?', array($row['id']));
                error_log("Caligrafy\\Notify: {$row['provider']} answered $status $reason");
            }
        }
        return $sent;
    }

    /**
     * Whether Apple or Firebase said the device no longer takes notifications (the app was removed, the token changed)
     */
    public static function deviceGone($provider, $status, $reason)
    {
        if ($provider === 'apns') {
            return $status === 410 || ($status === 400 && in_array($reason, array('BadDeviceToken', 'DeviceTokenNotForTopic'), true));
        }
        return $status === 404 || in_array($reason, array('UNREGISTERED', 'NOT_FOUND'), true)
            || ($status === 400 && strpos($reason, 'registration token') !== false);
    }

    /**
     * What Apple or Firebase receives for a message (their JSON), for a device
     */
    public static function devicePayload($provider, array $message)
    {
        $url = $message['url'] !== '' ? self::absolute($message['url']) : (string) self::base();
        if ($provider === 'apns') {
            $aps = array('alert' => array('title' => $message['title'], 'body' => $message['body']), 'sound' => 'default');
            if ($message['tag'] !== '') $aps['thread-id'] = $message['tag'];
            return array('aps' => $aps, 'url' => $url, 'tag' => $message['tag']);
        }
        $android = array('priority' => $message['urgency'] === 'high' ? 'HIGH' : 'NORMAL', 'ttl' => self::TTL . 's');
        if ($message['tag'] !== '') $android['collapse_key'] = substr($message['tag'], 0, 64);
        return array('notification' => array('title' => $message['title'], 'body' => $message['body']),
            'data' => array('url' => $url, 'tag' => $message['tag']), 'android' => $android);
    }

    private static function sendDevice(array $row, array $message)
    {
        try {
            return $row['provider'] === 'apns' ? self::sendApns($row, $message) : self::sendFcm($row, $message);
        } catch (\Throwable $e) {
            error_log('Caligrafy\\Notify: ' . $e->getMessage());
            return array(0, 'error');
        }
    }

    private static function sendApns(array $row, array $message)
    {
        // APNS_ENVIRONMENT=sandbox: the builds Xcode runs on a phone (development); TestFlight and the App Store are production.
        // APNS_URL replaces the address (a proxy, the tests)
        $base = rtrim(trim((string) getenv('APNS_URL')), '/')
            ?: (strtolower((string) getenv('APNS_ENVIRONMENT')) === 'sandbox' ? 'https://api.sandbox.push.apple.com' : 'https://api.push.apple.com');
        $headers = array('authorization: bearer ' . self::apnsJwt(), 'apns-topic: ' . trim((string) getenv('APNS_TOPIC')), 'apns-push-type: alert',
            'apns-priority: ' . ($message['urgency'] === 'low' ? '5' : '10'), 'apns-expiration: ' . (time() + self::TTL), 'content-type: application/json');
        if ($message['tag'] !== '') $headers[] = 'apns-collapse-id: ' . substr($message['tag'], 0, 64);
        list($status, $body) = self::post("$base/3/device/" . rawurlencode($row['device_token']), $headers,
            json_encode(self::devicePayload('apns', $message), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), true);
        $reason = (string) (json_decode($body, true)['reason'] ?? '');
        if ($status === 403 && in_array($reason, array('ExpiredProviderToken', 'InvalidProviderToken'), true)) {
            Cache::forget('caligrafy.notify.apns-jwt');
        }
        return array($status, $reason);
    }

    private static function sendFcm(array $row, array $message)
    {
        $account = self::serviceAccount();
        $body = json_encode(array('message' => array('token' => $row['device_token']) + self::devicePayload('fcm', $message)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $base = rtrim(trim((string) getenv('FCM_URL')), '/') ?: 'https://fcm.googleapis.com';    // FCM_URL: a proxy, the tests
        list($status, $answer) = self::post("$base/v1/projects/" . rawurlencode($account['project_id']) . '/messages:send',
            array('authorization: Bearer ' . self::fcmAccessToken($account), 'content-type: application/json'), $body, false);
        $error = json_decode($answer, true)['error'] ?? array();
        $reason = '';
        foreach ((array) ($error['details'] ?? array()) as $detail) {
            if (!empty($detail['errorCode'])) $reason = (string) $detail['errorCode'];
        }
        if ($status === 401) Cache::forget('caligrafy.notify.fcm-token');
        return array($status, $reason !== '' ? $reason : (string) ($error['message'] ?? ''));
    }

    /**
     * The Firebase service account of FCM_SERVICE_ACCOUNT (its JSON, or that JSON in base64): project_id, client_email,
     * private_key, or null
     */
    public static function serviceAccount()
    {
        $raw = trim((string) getenv('FCM_SERVICE_ACCOUNT'));
        if ($raw === '') return null;
        $json = json_decode($raw[0] === '{' ? $raw : (string) base64_decode($raw, true), true);
        return is_array($json) && !empty($json['project_id']) && !empty($json['client_email']) && !empty($json['private_key']) ? $json : null;
    }

    /**
     * The token APNs takes from this app (ES256, APNS_KEY: the .p8 file's text or that text in base64), kept 50
     * minutes: Apple refuses one older than an hour and one renewed more than every 20 minutes
     */
    public static function apnsJwt()
    {
        return Cache::remember('caligrafy.notify.apns-jwt', 3000, function () {
            $key = self::pem((string) getenv('APNS_KEY'));
            $header = self::base64url(json_encode(array('alg' => 'ES256', 'kid' => trim((string) getenv('APNS_KEY_ID')))));
            $claims = self::base64url(json_encode(array('iss' => trim((string) getenv('APNS_TEAM_ID')), 'iat' => time())));
            $private = openssl_pkey_get_private($key);
            if ($private === false || !openssl_sign("$header.$claims", $der, $private, OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('APNS_KEY is not the .p8 key of Apple Developer (Keys, Apple Push Notifications service)');
            }
            return "$header.$claims." . self::base64url(self::derToJose($der, 32));
        });
    }

    /**
     * The OAuth access token of the service account for Firebase Cloud Messaging (RS256), kept 50 minutes
     */
    private static function fcmAccessToken(array $account)
    {
        return Cache::remember('caligrafy.notify.fcm-token', 3000, function () use ($account) {
            $now = time();
            $header = self::base64url(json_encode(array('alg' => 'RS256', 'typ' => 'JWT')));
            $claims = self::base64url(json_encode(array('iss' => $account['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $account['token_uri'] ?? 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600)));
            $private = openssl_pkey_get_private((string) $account['private_key']);
            if ($private === false || !openssl_sign("$header.$claims", $signature, $private, OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('FCM_SERVICE_ACCOUNT has no valid private_key: download the service account key of the Firebase project again');
            }
            list($status, $body) = self::post($account['token_uri'] ?? 'https://oauth2.googleapis.com/token', array('content-type: application/x-www-form-urlencoded'),
                http_build_query(array('grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => "$header.$claims." . self::base64url($signature))), false);
            $token = json_decode($body, true)['access_token'] ?? null;
            if ($status !== 200 || !is_string($token)) {
                throw new \RuntimeException("Google refused the Firebase service account ($status)");
            }
            return $token;
        });
    }

    /**
     * An ECDSA signature in DER (openssl_sign) as the R || S of JWS (ES256: 32 bytes each)
     */
    public static function derToJose($der, $size)
    {
        $offset = 2 + (ord($der[1]) & 0x80 ? ord($der[1]) & 0x7f : 0);
        $parts = array();
        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $parts[] = str_pad(ltrim(substr($der, $offset + 2, $length), "\x00"), $size, "\x00", STR_PAD_LEFT);
            $offset += 2 + $length;
        }
        return $parts[0] . $parts[1];
    }

    private static function pem($key)
    {
        $key = trim($key);
        if ($key !== '' && strpos($key, '-----BEGIN') === false) {
            $decoded = base64_decode($key, true);
            $key = $decoded !== false && strpos($decoded, '-----BEGIN') !== false ? $decoded
                : "-----BEGIN PRIVATE KEY-----\n" . chunk_split($key, 64, "\n") . "-----END PRIVATE KEY-----\n";
        }
        return str_replace('\n', "\n", $key);    // a key pasted in one line of .env
    }

    private static function base64url($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * A POST with curl (HTTP/2 for Apple, which accepts nothing else): [status, body]
     */
    private static function post($url, array $headers, $body, $http2)
    {
        if (!function_exists('curl_init')) throw new \RuntimeException('Push to phones needs the curl extension of PHP');
        $curl = curl_init($url);
        $options = array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::PUSH_TIMEOUT, CURLOPT_CONNECTTIMEOUT => self::PUSH_TIMEOUT);
        if ($http2) {
            if (!defined('CURL_HTTP_VERSION_2_0')) throw new \RuntimeException('Apple push needs HTTP/2: the curl of this PHP has none');
            $options[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_2_0;
        }
        curl_setopt_array($curl, $options);
        $answer = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($answer === false) {
            $error = curl_error($curl);
            throw new \RuntimeException("push: $error");
        }
        return array($status, (string) $answer);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Email

    /**
     * Whether email can be sent: MAIL_HOST set and an address for the app's links (APP_URL, or a web request)
     */
    public static function emailConfigured()
    {
        $host = trim((string) getenv('MAIL_HOST'));
        return $host !== '' && $host !== 'your-smtp-host' && self::base() !== null;
    }

    /**
     * Sets the user's address (one per user) and emails the confirmation link: valid 24 hours, used once
     * @return array the address, and whether the link was sent
     */
    public static function setEmail($userId, $email)
    {
        $email = trim((string) $email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) throw ServiceError::invalid(array('email' => 'An email address'));
        $token = bin2hex(random_bytes(24));
        Database::execute('INSERT INTO ' . self::EMAILS . ' (user_id, email, confirmed_at, token_hash, token_expires_at) VALUES (?, ?, NULL, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE email = VALUES(email), confirmed_at = NULL, token_hash = VALUES(token_hash), token_expires_at = VALUES(token_expires_at)',
            array((string) $userId, $email, hash('sha256', $token), date('Y-m-d H:i:s', time() + self::TOKEN_HOURS * 3600)));
        if (!self::emailConfigured()) return array('email' => $email, 'sent' => false, 'reason' => 'not-configured');
        $link = self::base() . 'notify/confirm/' . $token;
        $sent = self::mail($email, 'Confirm your email for ' . self::appName(), self::html('Confirm your email',
            array('Confirm this address to get the notifications of ' . htmlspecialchars(self::appName()) . ' by email.'), array('Confirm my email', $link),
            'The link works once, for ' . self::TOKEN_HOURS . ' hours. Did not ask for it? Ignore this email.'), array());
        return array('email' => $email, 'sent' => $sent);
    }

    /**
     * Confirms an address from its link: the user id, or null when the link is unknown, used or expired
     */
    public static function confirm($token)
    {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{48}$/', $token)) return null;
        $rows = Database::toArray(Database::execute('SELECT user_id FROM ' . self::EMAILS . ' WHERE token_hash = ? AND token_expires_at > ?',
            array(hash('sha256', $token), date('Y-m-d H:i:s'))));
        if (!$rows) return null;
        Database::execute('UPDATE ' . self::EMAILS . ' SET confirmed_at = ?, token_hash = NULL, token_expires_at = NULL WHERE user_id = ?', array(date('Y-m-d H:i:s'), $rows[0]['user_id']));
        self::prefer($rows[0]['user_id'], 'email', true);
        return $rows[0]['user_id'];
    }

    /**
     * The one-click unsubscribe link of a user's emails (signed with APP_KEY)
     */
    public static function unsubscribeUrl($userId)
    {
        return self::base() . 'notify/unsubscribe?u=' . rawurlencode((string) $userId) . '&s=' . self::signature($userId);
    }

    /**
     * Turns the user's emails off from a signed link: whether the signature was right
     */
    public static function unsubscribeEmail($userId, $signature)
    {
        if (!is_string($signature) || !hash_equals(self::signature($userId), $signature)) return false;
        self::prefer($userId, 'email', false);
        return true;
    }

    private static function signature($userId)
    {
        return substr(hash_hmac('sha256', 'notify-unsubscribe|' . $userId, (string) getenv('APP_KEY')), 0, 32);
    }

    /**
     * Sends the message to the user's confirmed address: sent, failed, none (no confirmed address), not-configured or
     * rate-limited (MAIL_RATE_PER_HOUR reached, 2.30.0)
     */
    private static function email($userId, array $message)
    {
        $rows = Database::toArray(Database::execute('SELECT email FROM ' . self::EMAILS . ' WHERE user_id = ? AND confirmed_at IS NOT NULL', array((string) $userId)));
        if (!$rows) return 'none';
        if (!self::emailConfigured()) return 'not-configured';
        $unsubscribe = self::unsubscribeUrl($userId);
        $html = self::html($message['title'], array(nl2br(htmlspecialchars($message['body']))),
            $message['url'] !== '' ? array('Open', self::absolute($message['url'])) : null,
            'You get this because you turned on email notifications of ' . htmlspecialchars(self::appName()) . '. <a href="' . htmlspecialchars($unsubscribe) . '">Unsubscribe</a>.');
        return self::mail($rows[0]['email'], $message['title'], $html,
            array('List-Unsubscribe' => '<' . $unsubscribe . '>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click')) ? 'sent' : (Mail::limited() ? 'rate-limited' : 'failed');
    }

    private static function mail($to, $subject, $html, array $headers)
    {
        if (self::$mailSender) return (bool) call_user_func(self::$mailSender, $to, $subject, $html, $headers);
        try {
            $from = trim((string) getenv('MAIL_FROM')) ?: (filter_var(getenv('MAIL_USERNAME'), FILTER_VALIDATE_EMAIL) ? getenv('MAIL_USERNAME') : 'no-reply@' . (parse_url((string) self::base(), PHP_URL_HOST) ?: 'localhost'));
            return (new Mail())->sendMail(array('email' => $to), array('subject' => $subject, 'body' => $html), array('email' => $from, 'name' => self::appName()), $headers);
        } catch (\Throwable $e) {
            error_log('notify email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * An email in the app's look: the colors, font and corners of its design style (DESIGN_STYLE), else Caligrafy's,
     * written inline since email clients ignore stylesheets. $code: a code to type (shown large), $footer: the last line
     */
    public static function html($title, array $paragraphs, $button, $footer, $code = null)
    {
        // tables and inline styles: what every mail app (Gmail, Outlook, Apple Mail) renders the same
        $t = self::theme();
        $e = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES); };
        $font = $e($t['font']);
        $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e($title) . '</title></head>'
            . '<body style="margin:0;padding:0;background:' . $e($t['page']) . ';">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $e($t['page']) . ';"><tr><td align="center" style="padding:32px 16px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:' . $e($t['canvas']) . ';border:1px solid ' . $e($t['border']) . ';border-radius:' . $e($t['card']) . ';">'
            . '<tr><td style="padding:32px 32px 0 32px;font-family:' . $font . ';font-size:15px;font-weight:600;color:' . $e($t['ink']) . ';">' . $e(self::appName()) . '</td></tr>'
            . '<tr><td style="padding:24px 32px 0 32px;font-family:' . $font . ';">'
            . '<h1 style="margin:0 0 16px 0;font-size:22px;line-height:1.25;font-weight:600;color:' . $e($t['ink']) . ';">' . $e($title) . '</h1>';
        foreach ($paragraphs as $p) $html .= '<p style="margin:0 0 16px 0;font-size:15px;line-height:1.55;color:' . $e($t['text']) . ';">' . $p . '</p>';
        if ($code !== null) {
            $html .= '<p style="margin:8px 0 24px 0;padding:16px;background:' . $e($t['page']) . ';border-radius:' . $e($t['card']) . ';text-align:center;'
                . 'font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:32px;letter-spacing:8px;font-weight:600;color:' . $e($t['ink']) . ';">' . $e($code) . '</p>';
        }
        if ($button) {
            $html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 24px 0;"><tr><td style="background:' . $e($t['primary']) . ';border-radius:' . $e($t['radius']) . ';">'
                . '<a href="' . $e($button[1]) . '" style="display:inline-block;padding:13px 22px;font-family:' . $font . ';font-size:15px;font-weight:600;color:' . $e($t['on_primary']) . ';text-decoration:none;">' . $e($button[0]) . '</a></td></tr></table>'
                . '<p style="margin:0 0 16px 0;font-size:13px;line-height:1.5;color:' . $e($t['muted']) . ';">Or open this address: '
                . '<a href="' . $e($button[1]) . '" style="color:' . $e($t['ink']) . ';word-break:break-all;">' . $e($button[1]) . '</a></p>';
        }
        return $html . '</td></tr><tr><td style="padding:16px 32px 32px 32px;font-family:' . $font . ';font-size:12px;line-height:1.5;color:' . $e($t['muted']) . ';border-top:1px solid ' . $e($t['border']) . ';">'
            . preg_replace('/<a /', '<a style="color:' . $e($t['muted']) . ';" ', $footer) . '</td></tr></table></td></tr></table></body></html>';
    }

    /**
     * The look of the app's emails: Caligrafy's (public/css/look.css), with the design style of DESIGN_STYLE on top
     * (public/css/designs/<name>.css): page, canvas, ink, text, muted, border, primary, on_primary, font, radius, card
     */
    public static function theme($public = null)
    {
        $public = $public ?? (defined('PUBLIC_PATH') ? PUBLIC_PATH : getcwd() . '/public/');
        $vars = array();
        $files = array($public . 'css/tokens.css', $public . 'css/look.css');
        $style = preg_replace('/[^a-z0-9._-]/i', '', (string) getenv('DESIGN_STYLE'));
        if ($style !== '') $files[] = $public . 'css/designs/' . $style . '.css';
        foreach ($files as $file) {
            if (!is_file($file)) continue;
            // the custom properties of the file's first rule (:root), later files winning
            $css = preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($file));
            $root = preg_match('/^\s*(?:html\s*,\s*)?:root[^{]*\{([^}]*)\}/m', $css, $m) ? $m[1] : $css;
            preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;]+);/i', $root, $all, PREG_SET_ORDER);
            foreach ($all as $d) $vars[$d[1]] = trim($d[2]);
        }
        $get = function ($names, $default) use ($vars) {
            foreach ((array) $names as $name) {
                $value = $vars[$name] ?? null;
                for ($i = 0; $value !== null && $i < 5 && preg_match('/^var\((--[a-z0-9-]+)(?:\s*,\s*([^)]+))?\)$/i', $value, $m); $i++) {
                    $value = $vars[$m[1]] ?? (isset($m[2]) ? trim($m[2]) : null);
                }
                if ($value !== null && $value !== '' && strpos($value, 'var(') === false) return $value;
            }
            return $default;
        };
        return array(
            'page' => $get(array('--cq-color-paper', '--design-surface'), '#f4f4f4'),
            'canvas' => $get(array('--cq-color-page', '--design-canvas'), '#ffffff'),
            'ink' => $get(array('--cq-color-ink', '--design-ink'), '#171a20'),
            'text' => $get(array('--cq-color-text', '--design-body'), '#393c41'),
            'muted' => $get(array('--cq-color-text-muted', '--design-muted'), '#5c5e62'),
            'border' => $get(array('--cq-color-border', '--design-hairline'), '#e2e3e3'),
            'primary' => $get(array('--cq-color-primary', '--design-primary'), '#000000'),
            'on_primary' => $get(array('--design-on-primary'), '#ffffff'),
            'font' => $get(array('--design-font-body', '--design-font', '--cq-font'), '-apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif'),
            'radius' => $get(array('--cq-radius-control', '--design-radius-control'), '4px'),
            'card' => $get(array('--design-radius-card'), '12px'),
        );
    }

    /**
     * A short page for the links of the emails (Route::notifications)
     */
    public static function page($title, $text, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        $home = self::base() ?? '/';
        print('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($title) . '</title></head>'
            . '<body style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;max-width:520px;margin:15vh auto;padding:0 24px;color:#111">'
            . '<h1 style="font-size:24px">' . htmlspecialchars($title) . '</h1><p style="font-size:16px;line-height:1.5">' . htmlspecialchars($text) . '</p>'
            . '<p><a href="' . htmlspecialchars($home) . '" style="color:#111">' . htmlspecialchars(self::appName()) . '</a></p></body></html>');
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Choices and status

    /**
     * The channels the app offers (NOTIFY_CHANNELS, push,email by default)
     */
    public static function offered()
    {
        $setting = getenv('NOTIFY_CHANNELS');
        $list = $setting === false || trim($setting) === '' ? self::CHANNELS : array_map('trim', explode(',', strtolower($setting)));
        return array_values(array_intersect(self::CHANNELS, $list));
    }

    /**
     * Turns a channel on or off for a user (a channel without a choice is on)
     */
    public static function prefer($userId, $channel, $enabled)
    {
        if (!in_array($channel, self::CHANNELS, true)) throw ServiceError::invalid(array('channel' => 'push or email'));
        Database::execute('INSERT INTO ' . self::PREFS . ' (user_id, channel, enabled) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)',
            array((string) $userId, $channel, $enabled ? 1 : 0));
    }

    public static function enabled($userId, $channel)
    {
        $rows = Database::toArray(Database::execute('SELECT enabled FROM ' . self::PREFS . ' WHERE user_id = ? AND channel = ?', array((string) $userId, $channel)));
        return !$rows || (int) $rows[0]['enabled'] === 1;
    }

    /**
     * What a user sees about their notifications: the channels, their state and the push public key
     */
    public static function status($userId)
    {
        $email = Database::toArray(Database::execute('SELECT email, confirmed_at FROM ' . self::EMAILS . ' WHERE user_id = ?', array((string) $userId)));
        return array(
            'channels' => self::offered(),
            'push' => array('configured' => self::pushConfigured(), 'public_key' => self::pushConfigured() ? getenv('VAPID_PUBLIC_KEY') : null,
                'browsers' => self::count(self::SUBSCRIPTIONS, $userId), 'enabled' => self::enabled($userId, 'push'),
                // the phone apps (2.27.0): Apple and Firebase set up, and the user's phones
                'devices' => self::countDevices($userId), 'apns' => self::deviceConfigured('apns'), 'fcm' => self::deviceConfigured('fcm')),
            'email' => array('configured' => self::emailConfigured(), 'address' => $email[0]['email'] ?? null,
                'confirmed' => !empty($email[0]['confirmed_at']), 'enabled' => self::enabled($userId, 'email')),
        );
    }

    // ---------------------------------------------------------------------------------------------------------------

    private static function rate()
    {
        $rate = getenv('NOTIFY_RATE_PER_HOUR');
        return $rate === false || trim($rate) === '' ? 30 : max(1, (int) $rate);
    }

    private static function log($userId, array $message, $push, $email, $skipped)
    {
        try {
            Database::execute('INSERT INTO ' . self::LOG . ' (user_id, title, tag, push, email, skipped, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                array((string) $userId, $message['title'], $message['tag'] !== '' ? $message['tag'] : null, (int) $push, $email, $skipped, date('Y-m-d H:i:s')));
            if (random_int(1, 50) === 1) Database::execute('DELETE FROM ' . self::LOG . ' WHERE created_at < ?', array(date('Y-m-d H:i:s', time() - self::KEEP_DAYS * 86400)));
        } catch (\Throwable $e) {
        }
    }

    private static function countDevices($userId)
    {
        try { return self::count(self::DEVICES, $userId); } catch (\Throwable $e) { return 0; }
    }

    private static function count($table, $userId)
    {
        return (int) (Database::toArray(Database::execute("SELECT COUNT(*) AS n FROM $table WHERE user_id = ?", array((string) $userId)))[0]['n'] ?? 0);
    }

    /**
     * The app's address, ending with /: APP_URL, else the address of the current web request (null on the command line
     * without APP_URL)
     */
    public static function base()
    {
        $url = trim((string) getenv('APP_URL'));
        if ($url !== '') return rtrim($url, '/') . '/';
        return !empty($_SERVER['HTTP_HOST']) && isset($_SESSION['home']) ? $_SESSION['home'] : null;
    }

    private static function absolute($url)
    {
        return $url[0] === '/' ? rtrim((string) self::base(), '/') . $url : $url;
    }

    private static function appName()
    {
        return defined('APP_NAME') && APP_NAME ? APP_NAME : (getenv('APP_NAME') ?: (parse_url((string) self::base(), PHP_URL_HOST) ?: 'the app'));
    }
}
