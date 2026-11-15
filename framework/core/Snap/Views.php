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
 * How a Show card looks, best first (2.25.0):
 *  1. its card view: application/views/snap/<capability>.pug, a Pug view made for the card, in the app's own markup and
 *     classes, rendered on the server into the card (the hint 'card' => 'Folder/view' names another, false none). It gets
 *     $result (what the capability returned), $rows and $page for a list, $record for one record, $input, $card,
 *     $home, $authorized and $user. A link with data-snap-open="<capability>" and data-snap-input='{"id":7}' opens that
 *     card in the conversation. Written by Claude from the app's views (the assistant skill); its first line names the
 *     page it was made from and that page's fingerprint, so `php caligrafer.php snap views` says when it is stale.
 *  2. the part of its page marked data-snap="<capability>" (a Route::page whose first data it is), embedded in the card
 *     (?snap-embed: no frame, sized to it); a whole page only when the hint 'view' names it
 *  3. a card designed for its data in the app's design system (Snap\Designer)
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
            // only the part made for it: a page that marks it (data-snap), or the page the hint names
            if (!is_string($hint) && !self::marks($page['view'], $name)) {
                continue;
            }
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

    /**
     * The HTML of a capability's card view, or null when it has none (or it failed: the card is then designed)
     * @param mixed $result what the capability returned, as plain arrays
     */
    public static function card($name, $result, array $input, array $card = array())
    {
        $file = self::cardFile($name);
        if ($file === null) {
            return null;
        }
        $data = array('result' => $result, 'input' => $input, 'card' => $card, 'home' => (string) ($_SESSION['home'] ?? '/'),
            'rows' => null, 'page' => null, 'record' => null);
        $list = $result;
        if (is_array($result) && isset($result['data'], $result['total'], $result['pages']) && is_array($result['data'])) {
            $data['page'] = array('page' => (int) ($result['page'] ?? 1), 'pages' => (int) $result['pages'], 'total' => (int) $result['total']);
            $list = $result['data'];
        }
        if (is_array($list) && array_is_list($list)) {
            $data['rows'] = $list;
        } elseif (is_array($result)) {
            $data['record'] = $result;
        }
        if (class_exists('Caligrafy\\PageController')) {
            $data += \Caligrafy\PageController::identity();
        }
        $level = ob_get_level();
        ob_start();
        try {
            \Caligrafy\View::display($file, $data);
            return trim((string) ob_get_clean());
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            error_log("Snap card view of $name: " . $e->getMessage());
            return null;
        }
    }

    /**
     * The card view file of a capability, or null: the hint 'card' (a view name, or false), else views/snap/<name>.pug
     */
    public static function cardFile($name)
    {
        $hint = Hints::for($name)['card'] ?? null;
        if ($hint === false || !defined('VIEW_PATH')) {
            return null;
        }
        $file = VIEW_PATH . (is_string($hint) && $hint !== '' ? $hint : 'snap/' . $name) . '.pug';
        return is_file($file) ? $file : null;
    }

    /**
     * What the first line of a card view says it was made from: array('view' => 'Bookshelf/books', 'print' => '1a2b3c4d')
     * (//- snap card of list_books, from Bookshelf/books.pug 1a2b3c4d), or null
     */
    public static function source($file)
    {
        $line = (string) strtok((string) @file_get_contents($file), "\n");
        return preg_match('#from\s+([\w./-]+?)(?:\.pug)?\s+([0-9a-f]{8})\b#', $line, $m) ? array('view' => $m[1], 'print' => $m[2]) : null;
    }

    /**
     * A view's fingerprint, written in the card views made from it: when the view changes, they are stale
     */
    public static function fingerprint($view)
    {
        $file = (defined('VIEW_PATH') ? VIEW_PATH : 'application/views/') . $view . '.pug';
        return is_file($file) ? substr(sha1_file($file), 0, 8) : null;
    }

    /**
     * Every card view of the app, with what it was made from and whether it is stale:
     * [name => ['file', 'from', 'stale' (true, false, or null when its first line names no view)]]
     */
    public static function cards()
    {
        $out = array();
        $folder = (defined('VIEW_PATH') ? VIEW_PATH : 'application/views/') . 'snap/';
        foreach (glob($folder . '*.pug') ?: array() as $file) {
            $source = self::source($file);
            $now = $source ? self::fingerprint($source['view']) : null;
            $out[basename($file, '.pug')] = array('file' => $file, 'from' => $source['view'] ?? null,
                'stale' => $source ? $now !== $source['print'] : null);
        }
        ksort($out);
        return $out;
    }

    // a view that marks the part showing a capability: data-snap='list_books' (several names: data-snap='a b')
    private static function marks($view, $name)
    {
        $file = (defined('VIEW_PATH') ? VIEW_PATH : 'application/views/') . $view . '.pug';
        $source = is_file($file) ? (string) file_get_contents($file) : '';
        return (bool) preg_match('#data-snap\s*=\s*["\'][^"\']*\b' . preg_quote($name, '#') . '\b#', $source);
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
