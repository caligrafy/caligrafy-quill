<?php
/**
 * Server.php
 * Model Context Protocol server: AI agents use the capabilities as tools
 * @copyright 2026
 *
*/

namespace Caligrafy\Mcp;

use Caligrafy\Capabilities;
use Caligrafy\Context;
use Caligrafy\Framework;
use Caligrafy\ServiceError;

/**
 * Class Server
 * JSON-RPC 2.0 messages of MCP, independent of the transport (Http for Streamable HTTP, caligrafer mcp for stdio):
 * initialize, ping, tools/list, tools/call; notifications get no answer.
 *
 * The tools are the capabilities the context may call, with their input schema and the hints agents use to decide
 * when to ask the user first (readOnlyHint, destructiveHint). A tool call goes through Capabilities::call(): the same
 * permission checks, validation and audit as every other channel. A refused call (invalid input, forbidden...) is a
 * tool result with isError, so the agent can read the reason and correct itself; protocol errors are JSON-RPC errors.
 */
class Server
{
    const PROTOCOL_VERSIONS = array('2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05');

    const PARSE_ERROR = -32700;
    const INVALID_REQUEST = -32600;
    const METHOD_NOT_FOUND = -32601;
    const INVALID_PARAMS = -32602;
    const INTERNAL_ERROR = -32603;

    /** @var Context */
    protected $context;

    /** @var string */
    protected $name;

    public function __construct(Context $context, $name = null)
    {
        $this->context = $context;
        $this->name = $name ?? (defined('APP_NAME') && APP_NAME ? APP_NAME : 'caligrafy-app');
    }

    /**
     * Handles one message (a JSON string)
     * @return array|null the response, or null for a notification
     */
    public function handleJson($json)
    {
        $message = json_decode((string) $json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return self::error(null, self::PARSE_ERROR, 'Parse error: ' . json_last_error_msg());
        }
        return $this->handle($message);
    }

    /**
     * Handles one decoded message
     * @return array|null the response, or null for a notification
     */
    public function handle($message)
    {
        if (!is_array($message) || array_is_list($message) || ($message['jsonrpc'] ?? null) !== '2.0' || !is_string($message['method'] ?? null)) {
            return self::error(is_array($message) ? ($message['id'] ?? null) : null, self::INVALID_REQUEST, 'Invalid request: one JSON-RPC 2.0 message per request (batches are not supported)');
        }
        $isNotification = !array_key_exists('id', $message);
        $id = $message['id'] ?? null;
        $params = $message['params'] ?? array();
        if (!is_array($params)) {
            return $isNotification ? null : self::error($id, self::INVALID_PARAMS, 'params must be an object');
        }
        if ($isNotification) {
            return null;
        }
        try {
            switch ($message['method']) {
                case 'initialize':
                    return self::result($id, $this->initialize($params));
                case 'ping':
                    return self::result($id, new \stdClass());
                case 'tools/list':
                    return self::result($id, array('tools' => $this->tools()));
                case 'tools/call':
                    return $this->callTool($id, $params);
                default:
                    return self::error($id, self::METHOD_NOT_FOUND, "Method not found: {$message['method']}");
            }
        } catch (\Throwable $e) {
            $debug = defined('APP_DEBUG') && in_array(strtolower((string) APP_DEBUG), array('true', '1'), true);
            return self::error($id, self::INTERNAL_ERROR, $debug ? 'Internal error: ' . $e->getMessage() : 'Internal error');
        }
    }

    /**
     * The tools of this context
     */
    public function tools()
    {
        $tools = array();
        foreach (Capabilities::available($this->context) as $name => $entry) {
            $capability = $entry['capability'];
            $tools[] = array(
                'name' => $name,
                'title' => ucfirst(str_replace('_', ' ', $name)),
                'description' => $capability->description,
                'inputSchema' => Capabilities::schema($name),
                'annotations' => array(
                    'readOnlyHint' => $capability->readOnly,
                    'destructiveHint' => !$capability->readOnly && $capability->destructive,
                    'openWorldHint' => false,
                ),
            );
        }
        return $tools;
    }

    protected function initialize(array $params)
    {
        $requested = (string) ($params['protocolVersion'] ?? '');
        return array(
            'protocolVersion' => in_array($requested, self::PROTOCOL_VERSIONS, true) ? $requested : self::PROTOCOL_VERSIONS[0],
            'capabilities' => array('tools' => array('listChanged' => false)),
            'serverInfo' => array('name' => $this->name, 'title' => $this->name, 'version' => Framework::VERSION),
            'instructions' => 'Tools are the capabilities of this application that your token allows. Tools marked destructive delete or overwrite data: confirm with the user first.',
        );
    }

    protected function callTool($id, array $params)
    {
        $name = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? array();
        if (!is_string($name) || !is_array($arguments)) {
            return self::error($id, self::INVALID_PARAMS, 'tools/call needs a name and an arguments object');
        }
        $available = Capabilities::available($this->context);
        if (Capabilities::get($name) === null || (!isset($available[$name]) && $this->context->authenticated())) {
            // an unknown tool, or one this token may not see: the same answer, so tools/call reveals nothing more than tools/list
            return self::error($id, self::INVALID_PARAMS, "Unknown tool: $name");
        }
        try {
            $data = Capabilities::call($name, $arguments, $this->context);
            $structured = is_array($data) && !array_is_list($data) ? $data : array('result' => $data);
            return self::result($id, array(
                'content' => array(array('type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))),
                'structuredContent' => (object) $structured,
                'isError' => false,
            ));
        } catch (ServiceError $e) {
            return self::result($id, array(
                'content' => array(array('type' => 'text', 'text' => json_encode($e->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))),
                'isError' => true,
            ));
        }
    }

    public static function result($id, $result)
    {
        return array('jsonrpc' => '2.0', 'id' => $id, 'result' => $result);
    }

    public static function error($id, $code, $message)
    {
        return array('jsonrpc' => '2.0', 'id' => $id, 'error' => array('code' => $code, 'message' => $message));
    }
}
