<?php
/**
 * Views.php
 * A capability shown through the page designed for it (2.24.0)
 * @copyright 2026
 */

namespace Caligrafy\Snap;

use Caligrafy\Route;

/**
 * Class Views
 * A Snap card of a capability that already has a page (Route::page with the capability as its data) shows that page:
 * the real view, with its own design, scripts and data, embedded in the card and sized to it. The page is rendered as the
 * person, in embed mode (?snap-embed): no top bar, no footer. A view marks the part that shows one capability with
 * data-snap="<capability>" (for example section(data-snap='get_book')): the card then shows only that part.
 * A page is about its first data capability: one it only also reads is not shown through it. Without a mark, the card of
 * a capability that only reads shows the page without its forms that change things (those are the card's actions).
 * A capability without a page gets a card designed from its data (Snap\Designer).
 * The hint 'view' => false keeps a capability out of it; 'view' => 'Bookshelf/book' picks one of its pages.
 */
class Views
{
    /**
     * The address of the page that shows a capability, with its inputs in place, in embed mode; null when there is none
     * (no page uses it as data, or one of the page's route variables is not among the inputs)
     */
    public static function embed($name, array $inputs)
    {
        $hint = Hints::for($name)['view'] ?? null;
        if ($hint === false) {
            return null;
        }
        $best = null;
        foreach (Route::pages() as $page) {
            if (is_string($hint) && $page['view'] !== $hint) {
                continue;
            }
            $sources = (array) ($page['options']['data'] ?? array());
            $position = null;
            $i = 0;
            foreach ($sources as $source) {
                if ((is_array($source) ? ($source[0] ?? null) : $source) === $name) {
                    $position = $i;
                    break;
                }
                $i++;
            }
            // a page is about its first data (the book of /books/{id}); a capability it only also reads (the authors of a
            // filter) is not shown through it, unless the hint 'view' names that page
            if ($position === null || ($position > 0 && !is_string($hint))) {
                continue;
            }
            // a page made to create or change something (/cases/new, /books/{id}/edit, /cases/bulk) is not where a card shows it
            if (!is_string($hint) && preg_match('#/(new|create|add|edit|bulk|import|upload|compose|setup)$#', $page['route'])) {
                continue;
            }
            $url = self::fill($page['route'], $inputs);
            if ($url === null) {
                continue;
            }
            // the inputs the page reads from its query string (a filter: ?author_id=3)
            $query = array();
            foreach ((array) ($page['options']['query'] ?? array()) as $key => $value) {
                $field = is_int($key) ? $value : $key;
                if (isset($inputs[$field]) && is_scalar($inputs[$field]) && (string) $inputs[$field] !== '') {
                    $query[$field] = (string) $inputs[$field];
                }
            }
            if ($query) {
                $url .= '?' . http_build_query($query);
            }
            // the page made for it first: the capability as its first data, then the fewest other data
            $score = array($position, count($sources));
            if ($best === null || $score < $best[0]) {
                $best = array($score, $url);
            }
        }
        if ($best === null) {
            return null;
        }
        $url = $best[1];
        return '/' . ltrim($url, '/') . (strpos($url, '?') === false ? '?' : '&') . 'snap-embed=' . rawurlencode($name);
    }

    /**
     * Renders a page in embed mode: its style sheet and script go in, so the page shows without the site's frame
     * and tells the card its height
     */
    public static function page($html)
    {
        $home = (string) ($_SESSION['home'] ?? '/');
        $version = function ($file) { return defined('PUBLIC_PATH') ? (string) @filemtime(PUBLIC_PATH . 'snap/' . $file) : ''; };
        $css = '<link rel="stylesheet" href="' . htmlspecialchars($home . 'public/snap/snap-embed.css?v=' . $version('snap-embed.css'), ENT_QUOTES) . '">';
        // a card that only reads shows what the page shows, not the forms that change things (they are the card's actions)
        $entry = \Caligrafy\Capabilities::get((string) ($_GET['snap-embed'] ?? ''));
        $reads = $entry && !empty($entry['capability']->readOnly) ? ' data-reads="1"' : '';
        $js = '<script src="' . htmlspecialchars($home . 'public/snap/snap-embed.js?v=' . $version('snap-embed.js'), ENT_QUOTES) . '"' . $reads . '></script>';
        $html = stripos($html, '</head>') !== false ? preg_replace_callback('#</head>#i', function () use ($css) { return $css . '</head>'; }, $html, 1) : $css . $html;
        return stripos($html, '</body>') !== false ? preg_replace_callback('#</body>(?!.*</body>)#is', function () use ($js) { return $js . '</body>'; }, $html, 1) : $html . $js;
    }

    // '/books/{id}' with array('id' => 7) -> 'books/7'; null when a variable is missing
    private static function fill($route, array $inputs)
    {
        $missing = false;
        $url = preg_replace_callback('#\{+([^}]*)\}+#', function ($m) use ($inputs, &$missing) {
            $key = ltrim($m[1], '_');
            if (!isset($inputs[$key]) || !is_scalar($inputs[$key]) || (string) $inputs[$key] === '') {
                $missing = true;
                return '';
            }
            return rawurlencode((string) $inputs[$key]);
        }, (string) $route);
        return $missing ? null : $url;
    }
}
