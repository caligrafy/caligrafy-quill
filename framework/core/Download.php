<?php
/**
 * Download.php
 * A capability's result as a file to download: Route::download()
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Download (2.19.0)
 * Route::download('/books.csv', 'list_books', ['as' => 'csv', 'filename' => 'books.csv']) answers a GET with the result
 * of a read-only capability as an attachment, without a controller:
 * - inputs: the query string and the route variables (which win), as the person who opens the link
 *   (Context::fromRequest(..., 'web')); the capability must be readOnly (a link must not change data)
 * - 'as' => 'csv' (default): RFC 4180 (CRLF, fields with a comma, a quote or a line break in double quotes, quotes
 *   doubled), UTF-8, the header row from the first row's keys; 'bom' => true starts the file with a UTF-8 BOM (Excel);
 *   text cells that start with = + - @ or a tab are prefixed with ' so that spreadsheets do not run them as formulas
 *   ('formulas' => true keeps them as they are); arrays in a cell are written as JSON, booleans as true / false.
 *   'as' => 'json': the result, pretty-printed
 * - 'as' => 'zip' (2.22.0): the files the capability lists, in one ZIP archive streamed from a temporary file. Each
 *   item is a path (the file keeps its name) or ['file' => path, 'name' => 'folder/name in the archive']; a page of
 *   paginate() gives its rows. Paths are relative to 'root', a folder of the project (default 'public/uploads'): a
 *   file outside it, or missing, is left out, so a link never reaches other files. Duplicate names get " (2)".
 *   Images, video, audio and archives are stored as they are, other files compressed. No file at all: 404
 *   (error 1005). Needs the PHP zip extension (in Caligrafy's Docker images and on Caligrafy Cloud)
 * - 'filename': a name, or a callable($result, $input) returning one (default: the capability's name)
 * - refusals as Route::page: a visitor is sent to sign in when Auth is active, else 403 (error 1006); forbidden 403;
 *   anything else 404 (error 1005)
 */
class Download
{
    const FORMATS = array('csv', 'json', 'zip');

    // already compressed: stored as they are in a ZIP (deflating them again only costs time)
    const STORED = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'heic', 'mp3', 'm4a', 'aac', 'ogg', 'mp4', 'mov', 'webm', 'zip', 'gz', 'tgz', '7z', 'rar', 'docx', 'xlsx', 'pptx', 'woff', 'woff2');

    /**
     * Answers the current request (the route's closure)
     */
    public static function handle($capability, array $options = array(), $request = null)
    {
        $request = $request ?? request();
        $format = self::format($options);
        $entry = Capabilities::get($capability);
        if ($entry === null) {
            throw new \LogicException("Route::download: no capability named '$capability'");
        }
        if (!$entry['capability']->readOnly) {
            throw new \LogicException("Route::download: '$capability' changes data, and opening a link must not (only readOnly capabilities)");
        }
        $input = array_merge((array) ($request->parameters ?? array()), PageController::routeVariables());
        unset($input['uri']);
        try {
            $result = Capabilities::call($capability, $input, Context::fromRequest($request, 'web'));
        } catch (ServiceError $e) {
            if ($e->kind === 'unauthenticated' && Auth::active()) {
                redirect((string) ($options['signIn'] ?? '/login'));
                return null;
            }
            if ($e->kind === 'invalid') {
                // inputs to fix (a date, a filter): 422 with what is wrong, as REST answers it (2.19.5)
                if (!headers_sent()) {
                    http_response_code(422);
                    header('Content-Type: application/json; charset=utf-8');
                }
                echo json_encode($e->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                return null;
            }
            throw Errors::raise($e->kind === 'forbidden' || $e->kind === 'unauthenticated' ? 1006 : 1005);
        }
        $filename = self::filename($options['filename'] ?? null, $result, $input, $capability . '.' . $format);
        if ($format === 'zip') {
            return self::sendArchive(self::archive($result, $options), $filename);
        }
        if (!headers_sent()) {
            header('Content-Type: ' . ($format === 'csv' ? 'text/csv; charset=utf-8' : 'application/json; charset=utf-8'));
            header('Content-Disposition: ' . self::disposition($filename));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store');
        }
        if ($format === 'json') {
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return null;
        }
        foreach (self::csvLines(self::rows($result), !empty($options['bom']), empty($options['formulas']), self::columns($options)) as $line) {
            echo $line;
        }
        return null;
    }

    /**
     * csv or json (an unknown format is a mistake in web.php)
     */
    /**
     * The 'columns' option (2.19.5): a list of column names, or null for the keys of the first row
     */
    public static function columns(array $options)
    {
        if (!isset($options['columns'])) {
            return null;
        }
        $columns = array_values(array_map('strval', (array) $options['columns']));
        if (!$columns) {
            throw new \InvalidArgumentException("Route::download: 'columns' is a list of column names");
        }
        return $columns;
    }

    public static function format(array $options)
    {
        $format = strtolower((string) ($options['as'] ?? 'csv'));
        if (!in_array($format, self::FORMATS, true)) {
            throw new \InvalidArgumentException("Route::download: 'as' is csv, json or zip, not '$format'");
        }
        if ($format === 'zip') {
            self::root($options);   // a 'root' that leaves the project is a mistake in web.php
        }
        return $format;
    }

    /**
     * The 'root' option of a ZIP (2.22.0): a folder of the project, 'public/uploads' by default, as an absolute path
     * (null when it does not exist)
     */
    public static function root(array $options)
    {
        $root = trim(str_replace('\\', '/', (string) ($options['root'] ?? 'public/uploads')), '/');
        if ($root === '' || in_array('..', explode('/', $root), true) || preg_match('#^[A-Za-z]:#', $root)) {
            throw new \InvalidArgumentException("Route::download: 'root' is a folder of the project, such as public/uploads, not '" . ($options['root'] ?? '') . "'");
        }
        $base = defined('ROOT') ? ROOT : getcwd() . DIRECTORY_SEPARATOR;
        $real = realpath($base . $root);
        return $real !== false && is_dir($real) ? $real : null;
    }

    /**
     * The files of a result for a ZIP: [absolute path => name in the archive], in order. Paths outside the root or
     * that are not files are left out; names are cleaned (no .., no leading /) and made unique
     */
    public static function files($result, $root)
    {
        $files = array();
        $taken = array();
        if ($root === null) {
            return $files;
        }
        foreach (self::rows($result) as $row) {
            $path = $row['file'] ?? $row['path'] ?? $row['value'] ?? null;
            if (!is_string($path) || $path === '' || strpos($path, "\0") !== false) {
                continue;
            }
            $real = realpath($root . DIRECTORY_SEPARATOR . ltrim(str_replace('\\', '/', $path), '/'));
            if ($real === false || !is_file($real) || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0) {
                continue;
            }
            $name = self::entryName(isset($row['name']) && is_string($row['name']) ? $row['name'] : '') ?: basename($real);
            $unique = $name;
            for ($n = 2; isset($taken[strtolower($unique)]); $n++) {
                $dot = strrpos(basename($name), '.');
                $unique = $dot > 0 ? substr($name, 0, strlen($name) - strlen(basename($name)) + $dot) . " ($n)" . substr(basename($name), $dot) : "$name ($n)";
            }
            $taken[strtolower($unique)] = true;
            $files[$real] = $unique;
        }
        return $files;
    }

    /**
     * A name inside a ZIP: folders kept, without empty parts, . or .., control characters or a leading /
     */
    public static function entryName($name)
    {
        $parts = array();
        foreach (explode('/', str_replace('\\', '/', (string) $name)) as $part) {
            $part = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $part));
            if ($part !== '' && $part !== '.' && $part !== '..') {
                $parts[] = $part;
            }
        }
        return implode('/', $parts);
    }

    /**
     * The ZIP of a result (2.22.0): a temporary file the caller removes, or null when no file can go in it.
     * Usable on its own, in a test: ['path' => ..., 'count' => ..., 'names' => [...]]
     */
    public static function archive($result, array $options = array())
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException("Route::download: 'as' => 'zip' needs the PHP zip extension");
        }
        $files = self::files($result, self::root($options));
        if (!$files) {
            return null;
        }
        @set_time_limit(0);
        $path = tempnam(sys_get_temp_dir(), 'caligrafy-zip-');
        $zip = new \ZipArchive();
        if ($path === false || $zip->open($path, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Route::download: the ZIP file could not be created');
        }
        foreach ($files as $file => $name) {
            $zip->addFile($file, $name);
            if (in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::STORED, true)) {
                $zip->setCompressionName($name, \ZipArchive::CM_STORE);
            }
        }
        if (!$zip->close()) {
            @unlink($path);
            throw new \RuntimeException('Route::download: the ZIP file could not be written');
        }
        return array('path' => $path, 'count' => count($files), 'names' => array_values($files));
    }

    /**
     * Sends a ZIP made by archive() and removes it; no archive: the missing page (error 1005)
     */
    private static function sendArchive($archive, $filename)
    {
        if ($archive === null) {
            throw Errors::raise(1005);
        }
        try {
            if (!headers_sent()) {
                header('Content-Type: application/zip');
                header('Content-Disposition: ' . self::disposition($filename));
                header('Content-Length: ' . filesize($archive['path']));
                header('X-Content-Type-Options: nosniff');
                header('Cache-Control: private, no-store');
            }
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            readfile($archive['path']);
        } finally {
            @unlink($archive['path']);
        }
        return null;
    }

    /**
     * The rows of a result: a list of rows as it is, one row (an associative array) as a list of one, a list of
     * values as rows of one 'value' column
     */
    public static function rows($result)
    {
        $result = Capabilities::plain($result);
        if ($result === null || $result === array()) {
            return array();
        }
        if (!is_array($result)) {
            return array(array('value' => $result));
        }
        // a page of the query builder (paginate(): data, total, page, per_page, pages): its rows (2.19.0)
        if (isset($result['data'], $result['total'], $result['pages']) && is_array($result['data']) && array_is_list($result['data'])) {
            $result = $result['data'];
        }
        if (!array_is_list($result)) {
            return array($result);
        }
        return array_map(function ($row) { return is_array($row) ? $row : array('value' => $row); }, $result);
    }

    /**
     * The lines of a CSV file (each ends with CRLF): the header row from the first row's keys, then one line per row
     * in that order (a missing column is empty, an extra one left out)
     */
    public static function csvLines(array $rows, $bom = false, $escapeFormulas = true, ?array $columns = null)
    {
        if ($bom) {
            yield "\xEF\xBB\xBF";
        }
        // the 'columns' option (2.19.5): the header row and its order, written even when there is no row
        if (!$rows && !$columns) {
            return;
        }
        $columns = $columns ?: array_keys(reset($rows));
        yield self::line($columns, false);
        foreach ($rows as $row) {
            $cells = array();
            foreach ($columns as $column) {
                $cells[] = $row[$column] ?? null;
            }
            yield self::line($cells, $escapeFormulas);
        }
    }

    /**
     * A whole CSV file
     */
    public static function csv(array $rows, $bom = false, $escapeFormulas = true, ?array $columns = null)
    {
        return implode('', iterator_to_array(self::csvLines($rows, $bom, $escapeFormulas, $columns), false));
    }

    /**
     * One field of a CSV line, quoted when it must be (RFC 4180)
     */
    public static function cell($value, $escapeFormulas = true)
    {
        if ($value === null) {
            $value = '';
        } elseif (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } elseif (is_string($value) && $escapeFormulas && $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false && !is_numeric($value)) {
            $value = "'" . $value;
        }
        $value = (string) $value;
        return strpbrk($value, ",\"\r\n") !== false ? '"' . str_replace('"', '""', $value) . '"' : $value;
    }

    /**
     * The file name: the option, or what its callable returns, without a path, quotes or control characters
     */
    public static function filename($option, $result, array $input, $default)
    {
        $name = is_callable($option) && !is_string($option) ? call_user_func($option, $result, $input) : $option;
        $name = basename(str_replace('\\', '/', (string) ($name ?? '')));
        $name = trim(preg_replace('/[\x00-\x1F\x7F"]+/', '', $name));
        return $name === '' || $name === '.' || $name === '..' ? $default : $name;
    }

    /**
     * Content-Disposition: attachment, with an ASCII name and the UTF-8 one (RFC 6266)
     */
    public static function disposition($filename)
    {
        $ascii = preg_replace('/[^A-Za-z0-9._ -]/u', '_', $filename) ?? preg_replace('/[^A-Za-z0-9._ -]/', '_', $filename);
        return 'attachment; filename="' . $ascii . '"' . ($ascii !== $filename ? "; filename*=UTF-8''" . rawurlencode($filename) : '');
    }

    private static function line(array $cells, $escapeFormulas)
    {
        return implode(',', array_map(function ($cell) use ($escapeFormulas) { return self::cell($cell, $escapeFormulas); }, $cells)) . "\r\n";
    }
}
