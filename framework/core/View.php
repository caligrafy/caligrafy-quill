<?php

/**
 * View.php
 * View is the view renderer of the application
 * @author Dory A.Azar
 * @copyright 2019
 * @version 1.0
 *
*/

/**
 * Class View is the view renderer of the application
 * @author Dory Azar
*/

namespace Caligrafy;

use \Phug;

class View extends \stdClass {

    public $viewName;
    public $viewData;
    protected $viewPath;
    protected $request;

    public function __construct($viewName, $viewData = array())
    {
        $this->request = request();
        $this->viewName = $viewName;
        $this->viewData = $viewData;
        $this->viewPath = VIEW_PATH.$viewName.'.pug';

        // If it's an api call then don't render the view
        if ($this->request->wantsJson() || $viewName == null) {
            echo json_encode($viewData, JSON_UNESCAPED_SLASHES);
            exit;
        }
        // Otherwise render view
        self::display($this->viewPath, $this->viewData);
    }

    /**
     * Prints a Pug file (2.19.0). A view already compiled and up to date runs through Phug's Optimizer, which includes
     * the compiled PHP without building Phug's compiler (3 classes instead of about 100); a view to compile, or a
     * project that customized Phug at run time (its renderer already built), goes through Phug's renderer as before.
     */
    public static function display($path, array $data = array())
    {
        $folder = self::cache();
        if ($folder !== null && !Phug::isRendererInitialized() && strtolower(trim((string) getenv('VIEW_OPTIMIZER'))) !== 'false') {
            $options = array('cache_dir' => $folder, 'debug' => self::debug(), 'up_to_date_check' => true);
            foreach (array('shared_variables', 'globals') as $name) {
                if (Phug::hasOption($name) && Phug::getOption($name)) {
                    $options[$name] = Phug::getOption($name);
                }
            }
            $optimizer = new Phug\Optimizer($options);
            // a view to compile goes to the renderer below: the Optimizer would hand its own options to Phug again,
            // merged with the ones already set (an error)
            if (!$optimizer->isExpired($path, $compiled)) {
                $optimizer->displayFile($path, $data);
                return;
            }
            Phug::displayFile($path, $data);
            // the compiled file was just rewritten: OPcache must not serve its old copy to the next request
            if (function_exists('opcache_invalidate') && is_string($compiled) && $compiled !== '') {
                @opcache_invalidate($compiled, true);
            }
            return;
        }
        Phug::displayFile($path, $data);
    }

    /**
     * Phug's debug mode (line numbers of the Pug file in errors) only with APP_DEBUG=true: it makes every page about
     * three times slower (2.19.0)
     */
    public static function debug()
    {
        return filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Compiled templates are kept between requests: a view is compiled to PHP once, then again only when it or a file
     * it includes or extends changes. On by default; VIEW_CACHE=false compiles every view on every request. A project
     * that sets Phug's cache_dir itself keeps its own. Returns the folder used, or null (no cache). Also sets Phug's
     * debug mode from APP_DEBUG.
     */
    public static function cache()
    {
        static $folder = false;
        if ($folder !== false) return $folder;
        Phug::setOption('debug', self::debug());
        $folder = null;
        if (strtolower(trim((string) getenv('VIEW_CACHE'))) === 'false') return $folder;
        if (Phug::hasOption('cache_dir') && Phug::getOption('cache_dir')) return $folder = Phug::getOption('cache_dir');
        $folder = self::cacheFolder();
        if ($folder !== null) Phug::setOption('cache_dir', $folder);
        return $folder;
    }

    /**
     * A folder of the system's temporary directory for this project and this system user only (compiled templates are
     * PHP that runs): created private (0700), refused when someone else owns it or it cannot be written. Views compiled
     * in debug mode are kept apart (the cache does not know the options a view was compiled with).
     */
    public static function cacheFolder()
    {
        return ClassMap::tempFolder(self::debug() ? 'views-debug' : 'views');
    }

}
