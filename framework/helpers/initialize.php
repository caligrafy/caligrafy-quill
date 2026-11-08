<?php
/**
 * Initialize.php is the file that handles the calls to all bootstrap and external scripts
 * @copyright 2019
 * @author Dory A.Azar
 * @version 1.0
 */
/**
 * The Initialize module handles the calls to all bootstrap and external scripts
 * @author Dory A.Azar
 * @version 1.0
 */


date_default_timezone_set(getenv('SERVER_TIMEZONE') != null? getenv('SERVER_TIMEZONE'): 'America/New_York');
setlocale(LC_MONETARY, getenv('APP_LOCALE'));
// Sessions (2.19.0): a request that brings a session cookie starts its session, as always. A read (GET, HEAD) without one
// (an API client, an agent, a first visit) gets the same values in memory, and its session is started only when it needs
// one: when the CSRF token is read (session('token'): a form, a page of a Vue app) or, at the end of the request, when
// the request changed the session or its page carries the token (caligrafySessionShutdown() in corehelpers.php). This
// spares a session file per request, and the clean-up of all of them that PHP runs every 1,000 requests.
// SESSION_LAZY=false starts every session at once, as before 2.19.0.
// A request that changes something (POST, PUT, DELETE: sign-in, forms) always starts its session, so code that calls
// session_regenerate_id() or writes $_SESSION finds it active.
$sessionReadOnly = in_array(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), array('GET', 'HEAD', 'OPTIONS'), true);
if (PHP_SAPI === 'cli' || !$sessionReadOnly || strtolower(trim((string) getenv('SESSION_LAZY'))) === 'false' || isset($_COOKIE[session_name()])) {
    session_start();
} else {
    $_SESSION = array();
}
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}

// Define path constants

define("DS", DIRECTORY_SEPARATOR);
define("ROOT", getcwd() . DS);
define("APP_PATH", ROOT . 'application' . DS);
define("TEST_PATH", ROOT . 'tests' . DS);
define("FRAMEWORK_PATH", ROOT . "framework" . DS);
define("PUBLIC_PATH", ROOT . "public" . DS);
define("CONFIG_PATH", APP_PATH . "config" . DS);
define("ROUTING_PATH", CONFIG_PATH . "routing" . DS);
define("CONTROLLER_PATH", APP_PATH . "controllers" . DS);
define("MODEL_PATH", APP_PATH . "models" . DS);
define("VIEW_PATH", APP_PATH . "views" . DS);
define("CORE_PATH", FRAMEWORK_PATH . "core" . DS);
define('DB_PATH', FRAMEWORK_PATH . "database" . DS);
define("INTERFACES_PATH", FRAMEWORK_PATH . "interfaces" . DS);
// Files that moved to framework/interfaces in 2.1.2: a framework copied over an older one still has them, and loading
// them twice would declare their classes twice. The loaders skip them and doctor asks to delete them.
define("FRAMEWORK_MOVED_FILES", array('core/Rest.php', 'core/Client.php', 'core/Mcp/Server.php', 'core/Mcp/Http.php'));
define("LIB_PATH", FRAMEWORK_PATH . "librairies" . DS);
define("HELPER_PATH", FRAMEWORK_PATH . "helpers" . DS);
define("UPLOAD_PATH", PUBLIC_PATH . "uploads" . DS);
define("FONTS_PATH", PUBLIC_PATH . "fonts" . DS);


//URL of the application
$serverName = getenv('APP_ENV') == 'sandbox'? getenv('SANDBOX_SERVER') : (isset($_SERVER['SERVER_NAME'])? $_SERVER['SERVER_NAME'] : '');
$port =  getenv('APP_ENV') == 'sandbox'? getenv('SANDBOX_PORT') : (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != '80' ? ':'.$_SERVER['SERVER_PORT'] : '');
$_SESSION['base'] = getenv('APP_PROTOCOL')."://".$serverName.$port.DS;
$_SESSION['home'] = $_SESSION['base'].(getenv('APP_ROOT')? getenv('APP_ROOT').DS : '');
$_SESSION['public'] = $_SESSION['home'].'public'.DS;
$_SESSION['uploadsUrl'] = $_SESSION['home']."public/uploads".DS;
$_SESSION['jsUrl'] = $_SESSION['home']."public/js".DS;
$_SESSION['cssUrl'] = $_SESSION['home']."public/css".DS;
$_SESSION['imagesUrl'] = $_SESSION['home']."public/images".DS;
$_SESSION['appClient'] = $_SESSION['home'].'public/app'.DS;
$_SESSION['appService'] = $_SESSION['home'].'public/js/services'.DS;
$_SESSION['appResources'] = $_SESSION['home'].'public/resources'.DS;

define("APP_CLIENT", $_SESSION['appClient']);
define("APP_SERVICE_ROOT", $_SESSION['appService']);
define("APP_RESOURCES", $_SESSION['appResources']);
