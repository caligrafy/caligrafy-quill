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
 * head, before any module script: != importmap() (2.33.0). A page has one import map: entries of its own (a bare name
 * such as 'three' for a library it bundles) go into the same one, importmap(['three' => '/public/js/vendor/three.js'])
 * (2.33.1); a second map is ignored by browsers that do not merge them.
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
     * @param array $own the page's own entries, specifier => address (a bare name such as 'three', or an address to
     *   send elsewhere): they join the map and win over a versioned address of the same file (2.33.1)
     * @return array
     */
    public static function map($public, $publicPath, array $own = array())
    {
        static $memo = array();
        $key = $public . '|' . $publicPath;
        if (isset($memo[$key])) {
            return $own ? array('imports' => array_merge($memo[$key]['imports'], $own)) : $memo[$key];
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
        $memo[$key] = array('imports' => $imports);
        return $own ? array('imports' => array_merge($imports, $own)) : $memo[$key];
    }

    /**
     * The import map as the head's script element, with the page's own entries ('' when there is nothing to map)
     */
    public static function tag($public, $publicPath, array $own = array())
    {
        $map = self::map($public, $publicPath, $own);
        if (!$map['imports']) {
            return '';
        }
        return '<script type="importmap">' . json_encode($map, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
}
