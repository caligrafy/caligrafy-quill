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
 * - 'filename': a name, or a callable($result, $input) returning one (default: the capability's name)
 * - refusals as Route::page: a visitor is sent to sign in when Auth is active, else 403 (error 1006); forbidden 403;
 *   anything else 404 (error 1005)
 */
class Download
{
    const FORMATS = array('csv', 'json');

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
            throw Errors::raise($e->kind === 'forbidden' || $e->kind === 'unauthenticated' ? 1006 : 1005);
        }
        $filename = self::filename($options['filename'] ?? null, $result, $input, $capability . '.' . $format);
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
        foreach (self::csvLines(self::rows($result), !empty($options['bom']), empty($options['formulas'])) as $line) {
            echo $line;
        }
        return null;
    }

    /**
     * csv or json (an unknown format is a mistake in web.php)
     */
    public static function format(array $options)
    {
        $format = strtolower((string) ($options['as'] ?? 'csv'));
        if (!in_array($format, self::FORMATS, true)) {
            throw new \InvalidArgumentException("Route::download: 'as' is csv or json, not '$format'");
        }
        return $format;
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
    public static function csvLines(array $rows, $bom = false, $escapeFormulas = true)
    {
        if ($bom) {
            yield "\xEF\xBB\xBF";
        }
        if (!$rows) {
            return;
        }
        $columns = array_keys(reset($rows));
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
    public static function csv(array $rows, $bom = false, $escapeFormulas = true)
    {
        return implode('', iterator_to_array(self::csvLines($rows, $bom, $escapeFormulas), false));
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
