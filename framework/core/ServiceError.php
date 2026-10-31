<?php
/**
 * ServiceError.php
 * The errors a service returns to every channel
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class ServiceError
 * A service throws it to refuse a call. Each channel turns it into its own form: an HTTP status and a JSON error for
 * REST, a tool error for MCP, the errors of a form for web pages.
 */
class ServiceError extends \Exception
{
    const STATUSES = array('invalid' => 422, 'unauthenticated' => 401, 'forbidden' => 403, 'not_found' => 404, 'conflict' => 409);

    /** @var string invalid, unauthenticated, forbidden, not_found or conflict */
    public $kind;

    /** @var array field => message, for invalid input */
    public $fields;

    public function __construct($kind, $message = '', array $fields = array())
    {
        if (!isset(self::STATUSES[$kind])) {
            throw new \InvalidArgumentException("Unknown service error '$kind'");
        }
        $this->kind = $kind;
        $this->fields = $fields;
        parent::__construct($message !== '' ? $message : self::defaultMessage($kind), self::STATUSES[$kind]);
    }

    public static function invalid(array $fields, $message = 'Some of the inputs are invalid')
    {
        return new self('invalid', $message, $fields);
    }

    public static function unauthenticated($message = 'Sign in or send a personal access token')
    {
        return new self('unauthenticated', $message);
    }

    public static function forbidden($message = 'You are not allowed to do this')
    {
        return new self('forbidden', $message);
    }

    public static function notFound($message = 'Not found')
    {
        return new self('not_found', $message);
    }

    public static function conflict($message)
    {
        return new self('conflict', $message);
    }

    /**
     * The HTTP status of this error
     */
    public function status()
    {
        return self::STATUSES[$this->kind];
    }

    /**
     * {"error": {"code": "invalid", "message": "...", "fields": {...}}}
     */
    public function toArray()
    {
        $error = array('code' => $this->kind, 'message' => $this->getMessage());
        if ($this->fields) {
            $error['fields'] = $this->fields;
        }
        return array('error' => $error);
    }

    private static function defaultMessage($kind)
    {
        return array('invalid' => 'Some of the inputs are invalid', 'unauthenticated' => 'Sign in or send a personal access token',
            'forbidden' => 'You are not allowed to do this', 'not_found' => 'Not found', 'conflict' => 'Conflict')[$kind];
    }
}
