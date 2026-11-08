<?php
/**
 * Uploads.php
 * File inputs of capabilities: the `file` rule
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Uploads (2.19.0)
 * A capability declares a file input with the rule `file`, an optional allow-list and an optional maximum size:
 *
 *   public $rules = ['cover' => 'required|file|extension,png;jpg|max_size,2mb'];
 *
 * - web pages (Route::form) and REST (Route::capability, Route::capabilities) take the field from $_FILES once the
 *   caller is allowed to call the capability, check it (one file, the allow-list - never an executable type, see
 *   isAllowedUpload() - and the size), save it in public/uploads under a new random name with uploadFile() (the name
 *   the client sent is never used) and give handle() the saved file's public URL, a string. A value sent in that field
 *   without a file is dropped. The saved file is deleted when the call fails afterwards.
 * - MCP and the command line cannot send files: the field is a URL or a path string; its extension is checked against
 *   the allow-list.
 * The file rules (file, extension, max_size, required_file) are never given to GUMP: Service::validate() strips them.
 * Without `extension` the allow-list is DEFAULT_UPLOAD_EXTENSIONS.
 */
class Uploads
{
    const RULES = array('file', 'extension', 'max_size', 'required_file');

    /** @var array the $_FILES of the request */
    private $files;

    /** @var callable function (array $file, array $allowed): string the public URL, '' when refused */
    private $store;

    /** @var callable function (string $url): bool */
    private $delete;

    /** @var array the URLs saved by apply() */
    private $saved = array();

    public function __construct(?array $files = null, ?callable $store = null, ?callable $delete = null)
    {
        $this->files = $files ?? ($_FILES ?? array());
        $this->store = $store ?? function ($file, $allowed) { return uploadFile($file, $allowed); };
        $this->delete = $delete ?? function ($url) { return deleteFile($url); };
    }

    /**
     * The file inputs of a set of rules
     * @return array field => ['extensions' => [..] or null, 'max' => bytes or null]
     */
    public static function fields(array $rules)
    {
        $fields = array();
        foreach ($rules as $field => $rule) {
            $spec = self::parse($rule);
            if ($spec !== null) {
                $fields[$field] = $spec;
            }
        }
        return $fields;
    }

    /**
     * The file part of one rule: null when the rule has no `file`
     */
    public static function parse($rule)
    {
        if (!is_string($rule)) {
            return null;
        }
        $file = false;
        $spec = array('extensions' => null, 'max' => null);
        foreach (explode('|', $rule) as $part) {
            $pieces = explode(',', $part, 2);
            $name = strtolower(trim($pieces[0]));
            $argument = isset($pieces[1]) ? trim($pieces[1]) : '';
            if ($name === 'file' || $name === 'required_file') {
                $file = true;
            } elseif ($name === 'extension') {
                $spec['extensions'] = array_values(array_filter(array_map(function ($e) { return strtolower(trim(ltrim(trim($e), '.'))); }, preg_split('/[;,]/', $argument)), 'strlen'));
            } elseif ($name === 'max_size') {
                $spec['max'] = self::bytes($argument);
            }
        }
        return $file ? $spec : null;
    }

    /**
     * Rules without their file parts, for GUMP
     */
    public static function strip(array $rules)
    {
        foreach ($rules as $field => $rule) {
            if (!is_string($rule) || self::parse($rule) === null) {
                continue;
            }
            $kept = array();
            foreach (explode('|', $rule) as $part) {
                $name = strtolower(trim(explode(',', $part, 2)[0]));
                if ($name !== '' && !in_array($name, self::RULES, true)) {
                    $kept[] = $part;
                }
            }
            $rules[$field] = implode('|', $kept);
        }
        return $rules;
    }

    /**
     * '2mb', '500kb', '1.5MB', '1g' or a number of bytes
     */
    public static function bytes($size)
    {
        if (!preg_match('/^\s*(\d+(?:\.\d+)?)\s*([kmg]?)b?\s*$/i', (string) $size, $m)) {
            throw new \InvalidArgumentException("max_size,$size: write a size such as 500kb or 2mb");
        }
        $power = array('' => 0, 'k' => 1, 'm' => 2, 'g' => 3)[strtolower($m[2])];
        return (int) round((float) $m[1] * pow(1024, $power));
    }

    /**
     * The allow-list of a file input (never an executable type: isAllowedUpload() refuses those anyway)
     */
    public static function allowed(array $spec)
    {
        return $spec['extensions'] ?: DEFAULT_UPLOAD_EXTENSIONS;
    }

    /**
     * What is wrong with an uploaded file (an entry of $_FILES), or null
     */
    public static function check($file, array $spec)
    {
        if (!is_array($file) || !isset($file['name'], $file['tmp_name']) || is_array($file['name'])) {
            return 'Send one file';
        }
        $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return 'The file is too large';
        }
        if ($error !== UPLOAD_ERR_OK) {
            return 'The file could not be uploaded';
        }
        if (!isAllowedUpload($file['name'], self::allowed($spec))) {
            return 'This type of file is not accepted' . ($spec['extensions'] ? ': ' . implode(', ', $spec['extensions']) : '');
        }
        $size = is_string($file['tmp_name']) && is_file($file['tmp_name']) ? (int) filesize($file['tmp_name']) : (int) ($file['size'] ?? 0);
        if ($spec['max'] !== null && $size > $spec['max']) {
            return 'The file is too large (at most ' . self::human($spec['max']) . ')';
        }
        return null;
    }

    /**
     * What is wrong with a file input sent as a string (MCP, the command line), or null
     */
    public static function checkString($value, array $spec)
    {
        if (!is_string($value)) {
            return 'Send the URL or the path of the file';
        }
        $path = (string) (parse_url($value, PHP_URL_PATH) ?? $value);
        if (!isAllowedUpload($path, self::allowed($spec))) {
            return 'This type of file is not accepted' . ($spec['extensions'] ? ': ' . implode(', ', $spec['extensions']) : '');
        }
        return null;
    }

    /**
     * Replaces the file inputs of $input with the saved files of the request (web and REST)
     * @param array $input the inputs
     * @param array $rules the capability's rules
     * @return array the inputs, with the public URL of each saved file
     * @throws ServiceError invalid, with a message per file field
     */
    public function apply(array $input, array $rules)
    {
        $errors = array();
        $accepted = array();
        foreach (self::fields($rules) as $field => $spec) {
            unset($input[$field]);   // a string sent in a file field is not a file
            $file = $this->files[$field] ?? null;
            if ($file === null || (is_array($file) && (int) ($file['error'] ?? 0) === UPLOAD_ERR_NO_FILE)) {
                continue;            // no file: `required` reports it
            }
            $problem = self::check($file, $spec);
            if ($problem !== null) {
                $errors[$field] = $problem;
                continue;
            }
            $accepted[$field] = array($file, $spec);
        }
        if ($errors) {
            throw ServiceError::invalid($errors);
        }
        foreach ($accepted as $field => list($file, $spec)) {
            $url = (string) call_user_func($this->store, $file, self::allowed($spec));
            if ($url === '') {
                $this->discard();
                throw ServiceError::invalid(array($field => 'The file could not be saved'));
            }
            $this->saved[] = $url;
            $input[$field] = $url;
        }
        return $input;
    }

    /**
     * Deletes the files apply() saved (the call failed afterwards)
     */
    public function discard()
    {
        foreach ($this->saved as $url) {
            call_user_func($this->delete, $url);
        }
        $this->saved = array();
    }

    /**
     * The URLs apply() saved
     */
    public function saved()
    {
        return $this->saved;
    }

    private static function human($bytes)
    {
        foreach (array('GB' => 3, 'MB' => 2, 'KB' => 1) as $unit => $power) {
            if ($bytes >= pow(1024, $power)) {
                return rtrim(rtrim(number_format($bytes / pow(1024, $power), 1, '.', ''), '0'), '.') . ' ' . $unit;
            }
        }
        return $bytes . ' bytes';
    }
}
