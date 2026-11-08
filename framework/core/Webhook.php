<?php
/**
 * Webhook.php
 * Events another service posts to the application: Route::webhook()
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Webhook (2.19.0)
 * Route::webhook('/stripe/events', 'record_payment', ['verify' => 'stripe']) answers the POSTs of another service with
 * a capability, without a controller:
 * - no CSRF token (the sender has no session): the signature of the body proves who sent it. 'verify' is required:
 *   'stripe' checks the Stripe-Signature header with \Stripe\Webhook::constructEvent and the secret in the variable
 *   'secret_env' names (default STRIPE_WEBHOOK_SECRET), with 'tolerance' seconds (default 300); a callable
 *   function (string $rawBody, array $headers): bool checks anything else (headers with lower-case names)
 * - the raw body (php://input) must be JSON; the capability gets ['event' => the decoded body], as the system
 *   (Context::system('webhook', $path)): declare public $rules = ['event' => 'required']
 * - answers: 200 {"received": true}; 400 for a bad signature or a body that is not JSON; the status of a ServiceError;
 *   500 for anything else (written to the PHP error log)
 */
class Webhook
{
    /** @var callable|null replaces error_log() (tests) */
    public static $logger = null;

    /**
     * Answers the current request (the route's closure)
     */
    public static function handle($path, $capability, array $options)
    {
        $headers = function_exists('getallheaders') ? (array) getallheaders() : array();
        list($status, $body) = self::process($path, $capability, $options, (string) file_get_contents('php://input'), $headers);
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json');
        }
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * What a webhook post leads to, without answering it (testable without a server)
     * @return array [status, body]
     */
    public static function process($path, $capability, array $options, $raw, array $headers)
    {
        $headers = array_change_key_case($headers, CASE_LOWER);
        try {
            $verified = self::verify($raw, $headers, $options);
        } catch (\Throwable $e) {
            self::log("Caligrafy webhook $path: " . $e->getMessage());
            return array(500, self::error('error', 'The webhook is not set up'));
        }
        if ($verified !== true) {
            return array(400, self::error('invalid_signature', is_string($verified) ? $verified : 'The signature is not valid'));
        }
        $event = json_decode($raw, true);
        if (!is_array($event)) {
            return array(400, self::error('invalid', 'The body is not JSON'));
        }
        try {
            Capabilities::call($capability, array('event' => $event), Context::system('webhook', $path));
        } catch (ServiceError $e) {
            if ($e->kind === 'not_found' && Capabilities::get($capability) === null) {
                self::log("Caligrafy webhook $path: no capability named '$capability'");
                return array(500, self::error('error', 'The webhook could not be processed'));
            }
            return array($e->status(), $e->toArray());
        } catch (\Throwable $e) {
            self::log("Caligrafy webhook $path: " . get_class($e) . ': ' . $e->getMessage());
            return array(500, self::error('error', 'The webhook could not be processed'));
        }
        return array(200, array('received' => true));
    }

    /**
     * Whether the body comes from the sender
     * @param array $headers lower-case names
     * @return true|string true, or why not
     * @throws \LogicException when the webhook is not set up (no secret, unknown 'verify')
     */
    public static function verify($raw, array $headers, array $options)
    {
        $verify = $options['verify'] ?? null;
        if ($verify === 'stripe') {
            $variable = (string) ($options['secret_env'] ?? 'STRIPE_WEBHOOK_SECRET');
            $secret = (string) ($options['secret'] ?? getenv($variable));
            if ($secret === '') {
                throw new \LogicException("$variable is not set in .env");
            }
            $signature = $headers['stripe-signature'] ?? '';
            if (is_array($signature)) {
                $signature = (string) reset($signature);
            }
            try {
                \Stripe\Webhook::constructEvent((string) $raw, (string) $signature, $secret, (int) ($options['tolerance'] ?? \Stripe\Webhook::DEFAULT_TOLERANCE));
            } catch (\Stripe\Exception\SignatureVerificationException $e) {
                return 'The Stripe signature is not valid';
            } catch (\UnexpectedValueException $e) {
                return 'The body is not a Stripe event';
            }
            return true;
        }
        if (is_callable($verify) && !is_string($verify)) {
            return call_user_func($verify, (string) $raw, $headers) === true ? true : 'The signature is not valid';
        }
        throw new \LogicException("Route::webhook: 'verify' must be 'stripe' or a function (\$rawBody, \$headers): bool");
    }

    private static function log($message)
    {
        call_user_func(self::$logger ?? 'error_log', $message);
    }

    private static function error($code, $message)
    {
        return array('error' => array('code' => $code, 'message' => $message));
    }
}
