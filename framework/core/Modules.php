<?php
/**
 * Modules.php
 * The import map of public/js: every script module, versioned by its modification time
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Modules
 * A page loads its own script with ?v=<time> (the layouts do), but what that script imports ('./app.js',
 * '../capabilities.js') is fetched without one, and a browser may keep an old copy of it. After a release, an old
 * capabilities.js without a capability the new page script imports stops that script with "does not provide an export
 * named ..." (nothing on the page answers). The import map gives every .js file under public/js the same versioned
 * address wherever it is imported from, so a page always loads one release of its modules. The layouts print it in the
 * head, before any module script: != importmap() (2.33.0).
 */
class Modules
{
    const DIRECTORY = 'js';
    const SKIP = array('node_modules', 'dist', 'vendor');

    /**
     * The import map: {"imports": {"<public>js/app.js": "<public>js/app.js?v=<time>", ...}} for every .js under
     * public/js (node_modules, dist and vendor folders left out: they are built or versioned their own way)
     * @param string $public the public URL of the site (with its trailing slash), as session('public') gives it
     * @param string $publicPath the public folder on disk, with its trailing separator (PUBLIC_PATH)
     * @return array
     */
    public static function map($public, $publicPath)
    {
        static $memo = array();
        $key = $public . '|' . $publicPath;
        if (isset($memo[$key])) {
            return $memo[$key];
        }
        $imports = array();
        $root = rtrim($publicPath, '/\\') . DIRECTORY_SEPARATOR . self::DIRECTORY;
        if (is_dir($root)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                fn ($f) => !($f->isDir() && in_array($f->getFilename(), self::SKIP, true))));
            foreach ($files as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'js') {
                    $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
                    $url = $public . self::DIRECTORY . '/' . $relative;
                    $imports[$url] = $url . '?v=' . $file->getMTime();
                }
            }
        }
        ksort($imports);
        return $memo[$key] = array('imports' => $imports);
    }

    /**
     * The import map as the head's script element ('' when public/js has no script)
     */
    public static function tag($public, $publicPath)
    {
        $map = self::map($public, $publicPath);
        if (!$map['imports']) {
            return '';
        }
        return '<script type="importmap">' . json_encode($map, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
}
