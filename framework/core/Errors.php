<?php
/**
 * Errors.php
 * Class that handles all the error and exception messages in the system
 * @author Dory A.Azar
 * @copyright 2019
 * @version 1.0
 *
*/

/**
 * The Errors Class is the dictionary of all the error messages in the system
 * @version 1.0
*/

namespace Caligrafy;

class Errors extends \stdClass
{
    /**
     * @var array of error messages with codes
     * @property array of error messages with codes
    */
    private static $_errors = array(
        
        // 1xxx: Routing and Loading errors Errors
        1000 => '[ERROR 1000] Invalid Http Request Method ',
        1001 => '[ERROR 1001] Invalid Controller or Action ',
        1002 => '[ERROR 1002] Invalid library ',
        1003 => '[ERROR 1003] Property cannot be found ',
        1004 => '[ERROR 1004] Access Denied',
        
        //2xxx: Database Errors
        2000 => '[ERROR 2000] Database Connection Error ',
        2001 => '[ERROR 2001] Database Security Error - Not allowed to perform such an operation ',
        2002 => '[ERROR 2002] Database Query Error ',
        2003 => '[ERROR 2003] Database Query Error - Something went wrong in executing the query',
        
        
        //3xxx: Request Errors
        3000 => '[ERROR 3000] Security Error - Cross Site Forgery', 
        
        //4xxx: Mail Errors
        4000 => '[ERROR 4000] Mail Error - Mailer could not be initialized',
        4001 => '[ERROR 4001] Mail Error - Mail Content is not compliant',
        
        
        //5xxx: Caligrafer Errors
        5000 => '[ERROR 5000] Key Generator Error - Generator could not generate keys'
    );
    
    
    /**
     * @var array of HTTP status codes for the error codes. Codes that are not listed return 500
    */
    private static $_statuses = array(
        1000 => 405,
        1001 => 404,
        1004 => 401,
        3000 => 403
    );

    /**
     * @var string the body returned for unexpected errors when APP_DEBUG is not true
    */
    const GENERIC_MESSAGE = '[ERROR 500] Internal Server Error';

    /**
     * Get the HTTP status code of an error code
     * @param int $errorCode identifies the error code
     * @return int HTTP status code (500 when the error code is unknown)
    */
    public static function status($errorCode) {
        return self::$_statuses[(int)$errorCode] ?? 500;
    }

    /**
     * Creates the exception of a framework error and sets its HTTP status code right away, so that the status is
     * correct even when the exception is caught by an application that only echoes the message
     * @param int $errorCode identifies the error code
     * @return \Exception to be thrown
    */
    public static function raise($errorCode) {
        if (!headers_sent()) {
            http_response_code(self::status($errorCode));
        }
        return new \Exception(self::get($errorCode), (int)$errorCode);
    }

    /**
     * Sends an error response: HTTP status code and the error text (HTML requests) or
     * {"error": {"code": ..., "message": ...}} (requests that want JSON)
     * Framework errors keep their message. Other exceptions caught by the application keep theirs too
     * unless $hideUnknown is true (uncaught exceptions), in which case the message is only shown when APP_DEBUG is true
     * @param \Throwable $e the error
     * @param bool $hideUnknown hides the message of errors that are not framework errors unless APP_DEBUG is true
    */
    public static function handle(\Throwable $e, $hideUnknown = false) {
        $code = (int)$e->getCode();
        $known = isset(self::$_errors[$code]) && $e instanceof \Exception;
        $message = $e->getMessage();
        if (!$known) {
            $code = 500;
            if ($hideUnknown) {
                $message = self::debug()? '[ERROR 500] ' . $message : self::GENERIC_MESSAGE;
            }
        }
        self::respond(self::status($code), $code, $message);
    }

    /**
     * Shutdown function: turns PHP fatal errors into a 500 response (message only when APP_DEBUG is true)
    */
    public static function shutdown() {
        $error = error_get_last();
        if (!$error || !($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR))) {
            return;
        }
        // discard the partial page and PHP's own error text
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $message = self::debug()? '[ERROR 500] ' . strtok($error['message'], "\n") : self::GENERIC_MESSAGE;
        self::respond(500, 500, $message);
    }

    /**
     * Writes the error response
    */
    private static function respond($status, $code, $message) {
        $json = self::wantsJson();
        if (!headers_sent()) {
            http_response_code($status);
            if ($json) {
                header('Content-Type: application/json');
            }
        }
        if ($json) {
            echo json_encode(array('error' => array('code' => $code, 'message' => trim(preg_replace('/^\[ERROR \d+\]\s*/', '', $message)))));
        } else {
            echo $message;
        }
    }

    /**
     * Same rule as Request::wantsJson(): the client accepts application/json and not text/html
    */
    private static function wantsJson() {
        $accept = explode(',', strtolower(preg_replace('/\s+/', '', $_SERVER['HTTP_ACCEPT'] ?? '')));
        return !in_array('text/html', $accept) && in_array('application/json', $accept);
    }

    private static function debug() {
        return defined('APP_DEBUG') && filter_var(APP_DEBUG, FILTER_VALIDATE_BOOLEAN);
    }

    /** 
     * Get error message based on error code
     * @param string $errorCode identifies the error code
     * @return string identifying the error message
    */    
    public static function get($errorCode) {
        return self::$_errors[$errorCode];
    }
    
    public static function set($errorMessage, $errorCode) {
        self::$_errors[$errorCode] = !isset(self::$_errors[$errorCode])? $errorMessage : self::$_errors[$errorCode]." - ".$errorMessage;
        return self::$_errors[$errorCode];
    }
}
