<?php
/**
 * Http.php
 * Streamable HTTP transport of the MCP server (Route::mcp('/mcp'))
 * @copyright 2026
 *
*/

namespace Caligrafy\Mcp;

use Caligrafy\Context;
use Caligrafy\Tokens;

/**
 * Class Http
 * One JSON-RPC message per POST, answered with application/json (no event stream: every tool answers at once).
 * - Authentication: Authorization: Bearer <personal access token>. Without a valid token: 401 with WWW-Authenticate.
 * - Origin: a browser request from another site is refused (403), against DNS rebinding. Allowed: no Origin header
 *   (agents), the application's own host, and the origins listed in MCP_ALLOWED_ORIGINS (comma separated).
 * - Notifications are answered 202 with no body. GET and DELETE answer 405 (no server-initiated stream, no sessions).
 */
class Http
{
    public static function respond()
    {
        if (!self::originAllowed($_SERVER['HTTP_ORIGIN'] ?? null, $_SERVER['HTTP_HOST'] ?? '')) {
            self::send(403, Server::error(null, Server::INVALID_REQUEST, 'Origin not allowed'));
            return;
        }
        $request = request();
        $bearer = $request->bearer();
        $token = $bearer !== null && Tokens::looksLikeToken($bearer) ? self::verify($bearer) : null;
        if ($token === null) {
            if (!headers_sent()) {
                header('WWW-Authenticate: Bearer error="invalid_token", error_description="Send a personal access token: php caligrafer.php token create"');
            }
            self::send(401, Server::error(null, Server::INVALID_REQUEST, 'Unauthorized: send Authorization: Bearer <personal access token>'));
            return;
        }
        $server = new Server(Context::forToken($token, 'mcp'));
        $response = $server->handleJson(file_get_contents('php://input'));
        if ($response === null) {
            if (!headers_sent()) {
                http_response_code(202);
            }
            return;
        }
        self::send(isset($response['error']) && in_array($response['error']['code'], array(Server::PARSE_ERROR, Server::INVALID_REQUEST), true) ? 400 : 200, $response);
    }

    public static function methodNotAllowed()
    {
        if (!headers_sent()) {
            header('Allow: POST');
        }
        self::send(405, Server::error(null, Server::INVALID_REQUEST, 'This MCP endpoint only accepts POST'));
    }

    /**
     * Whether a browser Origin may call the endpoint
     */
    public static function originAllowed($origin, $host)
    {
        if ($origin === null || $origin === '') {
            return true;
        }
        $originHost = parse_url($origin, PHP_URL_HOST);
        $originPort = parse_url($origin, PHP_URL_PORT);
        $own = strtolower(explode(':', (string) $host)[0]);
        if ($originHost !== null && strtolower($originHost . ($originPort ? ":$originPort" : '')) === strtolower((string) $host)) {
            return true;
        }
        if ($originHost !== null && $originPort === null && strtolower($originHost) === $own) {
            return true;
        }
        $allowed = defined('MCP_ALLOWED_ORIGINS') ? MCP_ALLOWED_ORIGINS : getenv('MCP_ALLOWED_ORIGINS');
        $list = array_filter(array_map(function ($o) { return rtrim(strtolower(trim($o)), '/'); }, explode(',', (string) $allowed)), 'strlen');
        return in_array(rtrim(strtolower($origin), '/'), $list, true);
    }

    private static function verify($bearer)
    {
        try {
            return Tokens::verify($bearer);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function send($status, $body)
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json');
        }
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
