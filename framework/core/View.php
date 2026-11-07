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
        self::cache();
        Phug::displayFile($this->viewPath, $this->viewData);
    }

    /**
     * Compiled templates are kept between requests: a view is compiled to PHP once, then again only when it or a file
     * it includes or extends changes. On by default; VIEW_CACHE=false compiles every view on every request. A project
     * that sets Phug's cache_dir itself keeps its own. Returns the folder used, or null (no cache).
     */
    public static function cache()
    {
        static $folder = false;
        if ($folder !== false) return $folder;
        $folder = null;
        if (strtolower(trim((string) getenv('VIEW_CACHE'))) === 'false') return $folder;
        if (Phug::hasOption('cache_dir') && Phug::getOption('cache_dir')) return $folder = Phug::getOption('cache_dir');
        $folder = self::cacheFolder();
        if ($folder !== null) Phug::setOption('cache_dir', $folder);
        return $folder;
    }

    /**
     * A folder of the system's temporary directory for this project and this system user only (compiled templates are
     * PHP that runs): created private (0700), refused when someone else owns it or it cannot be written
     */
    public static function cacheFolder()
    {
        return ClassMap::tempFolder('views');
    }

}
