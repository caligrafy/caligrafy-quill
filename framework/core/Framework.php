<?php 
/**
 * Framework.php
 * Framework loader
 * @author Dory A.Azar
 * @copyright 2019
 * @version 1.0
 *
*/

/**
 * Class Framework that loads the entire framework 
 * Framework loader
 * @author Dory A
*/

namespace Caligrafy;

class Framework extends \stdClass {

  /**
   * Version of Caligrafy Quill (semantic versioning: the git tag is v<VERSION>, the changes are in CHANGELOG.md)
   */
  const VERSION = '2.21.8';
    
  /**
   * Framework main 
   */
  public static function run() 
  {
       // Errors get real HTTP status codes: the page is buffered so that a PHP fatal error can still be turned into a
       // 500 response, and uncaught exceptions return 500 (message only when APP_DEBUG is true). No chunk size: one of
       // 1 MB (before 2.19.0) made PHP allocate 1 MB for the buffer of every request, even a page of 1 KB
       ob_start();
       require_once 'framework/core/Errors.php';
       register_shutdown_function(array('Caligrafy\\Errors', 'shutdown'));
       set_exception_handler(function ($e) { Errors::handle($e, true); });
       self::init();
       self::autoload();
       self::dispatch();
   }

    /**
     * Initializes all framework configurations
     */
   private static function init() 
   {
        // Initialize global constants
        require_once 'framework/helpers/initialize.php';

        // Initialize global helpers
        require_once 'framework/helpers/corehelpers.php';
       
        loadConstantFromArray($_ENV); // turn them into constants
       
        //Load framework core settings
        require_once 'framework/settings/coresettings.php';
       
   }

    /**
     * Loads all core framework classes 
     */
    private static function autoload()
    {
        // Load classes: each one when it is first used (ClassMap, 2.18.0), or every file (CLASS_MAP=false)
        require_once CORE_PATH . 'ClassMap.php';
        if (ClassMap::enabled()) {
            ClassMap::register(self::mappedFolders(), self::unmappedFolders());
            return;
        }
        spl_autoload_register(array(__CLASS__,'load'));
    }

    /**
     * The folders of the class map: the framework's classes and the application's
     */
    public static function mappedFolders()
    {
        return array(CORE_PATH, DB_PATH, INTERFACES_PATH, APP_PATH);
    }

    /**
     * Left out of the class map: settings and routes (loaded by dispatch), views (Pug)
     */
    public static function unmappedFolders()
    {
        return array(CONFIG_PATH, VIEW_PATH);
    }

    private static function load()
    {
        self::_require_all(CORE_PATH);
        self::_require_all(DB_PATH);
        self::_require_all(INTERFACES_PATH);
        self::_require_all(APP_PATH);
        // tests/ is no longer loaded (2.18.0): it holds the PHPUnit tests of php caligrafer.php test
    }

    /**
     * Dispatches the URL to the route
     */
    private static function dispatch() 
    {   
        // Load configuration files
        include CONFIG_PATH . "config.php";

        // what the session holds once the settings ran: a request without a session cookie gets one only if it needs it
        caligrafySessionSnapshot();

        // The application's own bindings (application/config/container.php, 2.18.0)
        Container::boot();

        // Load routes
        require_once CONFIG_PATH . "routing/web.php";
    }

    /**
     * Scan the api path, recursively including all PHP files
     *
     * @param string  $dir
     * @param int     $depth (optional)
     */
    /**
     * Whether a path is an old copy of a file that moved (FRAMEWORK_MOVED_FILES): it is never loaded
     */
    private static function movedFile($path)
    {
        foreach (defined('FRAMEWORK_MOVED_FILES') ? FRAMEWORK_MOVED_FILES : array() as $moved) {
            if (realpath($path) === realpath(FRAMEWORK_PATH . $moved)) {
                return true;
            }
        }
        return false;
    }

    private static function _require_all($dir, $depth=0) {
        if ($depth > 5) {
            return;
        }
        // require all php files
        $scan = glob("$dir" . DIRECTORY_SEPARATOR . "*");
        foreach ($scan as $path) {
            if (preg_match('/\.php$/', $path)) {
                if (self::movedFile($path)) {
                    continue;
                }
                require_once $path;
            }
            elseif (is_dir($path)) {
                self::_require_all($path, $depth+1);
            }
        }
    }
    
}