<?php
/**
 * Designs.php
 * Selectable design styles: DESIGN_STYLE in .env and php caligrafer.php design
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Designs
 * A style is a DESIGN.md and the override stylesheet written from it, public/css/designs/<name>.css. DESIGN.md files
 * are looked up in this order, the first one wins on a name clash:
 *   application/design/<name>/                the project's own styles
 *   framework/design/local/<name>/            styles added to Caligrafy itself
 *   framework/design/vendor/<vendor>/<name>/  collections copied from elsewhere (awesome-design-md...), by vendor name
 * The layout loads that stylesheet after the Caligrafy stylesheets when DESIGN_STYLE names it; an empty DESIGN_STYLE,
 * an unknown name or a style without its stylesheet keeps Caligrafy's own look.
 */
class Designs
{
    const VENDOR = 'framework/design/vendor';
    const LOCAL = 'framework/design/local';
    const APPLICATION = 'application/design';
    const STYLESHEETS = 'public/css/designs';

    /**
     * The style name of a value when it is a valid one (lowercase letters, digits, dot, dash, underscore), else ''
     */
    public static function name($value)
    {
        $value = strtolower(trim((string) $value, " \t\"'"));
        return preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $value) && strpos($value, '..') === false ? $value : '';
    }

    /**
     * DESIGN_STYLE as loaded from .env (raw, trimmed): '' means Caligrafy's own look
     */
    public static function setting()
    {
        $value = defined('DESIGN_STYLE') ? constant('DESIGN_STYLE') : ($_ENV['DESIGN_STYLE'] ?? getenv('DESIGN_STYLE'));
        return trim((string) $value, " \t\"'");
    }

    /**
     * The folders that hold styles, first one wins: label => folder relative to the root
     */
    public static function sources($root = '.')
    {
        $sources = array('application' => self::APPLICATION, 'local' => self::LOCAL);
        $vendors = glob(rtrim($root, '/') . '/' . self::VENDOR . '/*', GLOB_ONLYDIR) ?: array();
        sort($vendors);
        foreach ($vendors as $vendor) {
            $sources['vendor/' . basename($vendor)] = self::VENDOR . '/' . basename($vendor);
        }
        return $sources;
    }

    /**
     * Every DESIGN.md of a name, in lookup order (the first one is used): label => path
     */
    public static function all($name, $root = '.')
    {
        $name = self::name($name);
        $found = array();
        if ($name === '') return $found;
        foreach (self::sources($root) as $label => $dir) {
            $file = rtrim($root, '/') . '/' . $dir . '/' . $name . '/DESIGN.md';
            if (is_file($file)) $found[$label] = $file;
        }
        return $found;
    }

    /**
     * The styles available, name => path of the DESIGN.md that is used (application, then local, then vendors)
     */
    public static function available($root = '.')
    {
        $styles = array();
        foreach (array_reverse(self::sources($root)) as $dir) {
            foreach (glob(rtrim($root, '/') . '/' . $dir . '/*/DESIGN.md') ?: array() as $file) {
                $name = self::name(basename(dirname($file)));
                if ($name !== '') $styles[$name] = $file;
            }
        }
        ksort($styles);
        return $styles;
    }

    /**
     * Where a DESIGN.md comes from: application, local or vendor/<vendor>
     */
    public static function source($file, $root = '.')
    {
        foreach (self::sources($root) as $label => $dir) {
            if (strpos($file, rtrim($root, '/') . '/' . $dir . '/') === 0) return $label;
        }
        return '';
    }

    /**
     * The DESIGN.md of a style, null when there is none
     */
    public static function find($name, $root = '.')
    {
        $found = self::all($name, $root);
        return $found ? reset($found) : null;
    }

    /**
     * Path of the override stylesheet of a style (it may not exist yet)
     */
    public static function stylesheet($name, $root = '.')
    {
        return rtrim($root, '/') . '/' . self::STYLESHEETS . '/' . self::name($name) . '.css';
    }

    /**
     * URL of the stylesheet of the selected style, versioned by its modification time, or '' for Caligrafy's own look
     */
    public static function stylesheetUrl($public, $root = '.')
    {
        $name = self::name(self::setting());
        if ($name === '') return '';
        $file = self::stylesheet($name, $root);
        return is_file($file) ? $public . 'css/designs/' . $name . '.css?v=' . filemtime($file) : '';
    }

    /**
     * One line describing a style: the description of its front matter, else its first paragraph
     */
    public static function describe($file, $max = 110)
    {
        $text = str_replace("\r\n", "\n", (string) @file_get_contents($file));
        $front = preg_match('/^---\n(.*?)\n---\n/s', $text, $f) ? $f[1] : '';
        if (preg_match('/^description:\s*(.+)$/m', $front, $m)) {
            $line = trim($m[1], " \"'");
        } else {
            $body = $front !== '' ? substr($text, strlen($f[0])) : $text;
            $line = '';
            foreach (preg_split('/\n\s*\n/', $body) as $paragraph) {
                $paragraph = trim($paragraph);
                if ($paragraph !== '' && $paragraph[0] !== '#' && $paragraph[0] !== '>' && $paragraph[0] !== '|') { $line = $paragraph; break; }
            }
        }
        $line = preg_replace('/\s+/', ' ', strip_tags($line));
        return $max && strlen($line) > $max ? rtrim(substr($line, 0, $max - 3)) . "..." : $line;
    }

    /**
     * The state of the selected style, for doctor and design status
     * @return array [status OK|WARN, message, fix]
     */
    public static function status($root = '.')
    {
        $raw = self::setting();
        if ($raw === '') {
            return array('OK', "Caligrafy's own look (DESIGN_STYLE is empty)", '');
        }
        $name = self::name($raw);
        if ($name === '') {
            return array('WARN', "'$raw' is not a style name: pages keep Caligrafy's own look", 'php caligrafer.php design list, then design use <name>');
        }
        $design = self::find($name, $root);
        $css = is_file(self::stylesheet($name, $root));
        $stylesheet = self::STYLESHEETS . "/$name.css";
        if (!$design && !$css) {
            return array('WARN', "'$name' is unknown: no DESIGN.md in " . self::APPLICATION . ", " . self::LOCAL . " or " . self::VENDOR . "/*, and no $stylesheet; pages keep Caligrafy's own look",
                'php caligrafer.php design list, then design use <name> (or DESIGN_STYLE= for the Caligrafy look)');
        }
        if (!$css) {
            return array('WARN', "$name selected, but $stylesheet is not written yet: pages keep Caligrafy's own look",
                'in Claude Code: /caligrafy-quill:design (it writes the stylesheet from ' . substr($design, strlen(rtrim($root, '/')) + 1) . ')');
        }
        return array('OK', "$name: $stylesheet loaded after the Caligrafy stylesheets", '');
    }

    /**
     * php caligrafer.php design list | show <name> | use <name> | off | status
     * @return int exit code
     */
    public static function cli(array $args, $root = '.', $envFile = '.env')
    {
        $command = strtolower($args[0] ?? 'status');
        $usage = "\n Usage: php caligrafer.php design <list [--json] | show <name> | use <name> | off | status>\n"
            . "  list    the styles of application/design, framework/design/local and framework/design/vendor/* (the first one wins on a name clash)\n"
            . "  show    the path, description and stylesheet of a style\n"
            . "  use     selects a style: DESIGN_STYLE=<name> in .env (then /caligrafy-quill:design in Claude Code writes its stylesheet)\n"
            . "  off     back to Caligrafy's own look: DESIGN_STYLE= (stylesheets are kept)\n"
            . "  status  the selected style\n\n";
        switch ($command) {
            case 'list':
                $styles = self::available($root);
                $selected = self::name(self::setting());
                if (in_array('--json', $args)) {
                    $list = array();
                    foreach ($styles as $name => $file) {
                        $list[] = array('name' => $name, 'source' => self::source($file, $root), 'design' => substr($file, strlen(rtrim($root, '/')) + 1), 'description' => self::describe($file, 0),
                            'stylesheet' => is_file(self::stylesheet($name, $root)), 'selected' => $name === $selected);
                    }
                    print(json_encode(array('selected' => $selected, 'styles' => $list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
                    return 0;
                }
                print("\n " . count($styles) . " styles (* selected, + stylesheet written). DESIGN_STYLE empty = Caligrafy's own look" . ($selected === '' ? ' (now)' : '') . "\n\n");
                foreach ($styles as $name => $file) {
                    printf(" %s%s %-16s %-26s %s\n", $name === $selected ? '*' : ' ', is_file(self::stylesheet($name, $root)) ? '+' : ' ', $name, self::source($file, $root), self::describe($file, 80));
                }
                print("\n Sources and their licenses: framework/design/README.md. Your own: application/design/<name>/DESIGN.md\n\n");
                return 0;

            case 'show':
                $name = self::name($args[1] ?? '');
                $file = $name !== '' ? self::find($name, $root) : null;
                if (!$file) {
                    fwrite(STDERR, "caligrafer design: unknown style '" . ($args[1] ?? '') . "' (php caligrafer.php design list)\n");
                    return 1;
                }
                $css = self::stylesheet($name, $root);
                $hidden = array_slice(self::all($name, $root), 1);
                print("\n $name (" . self::source($file, $root) . ")\n " . self::describe($file, 0) . "\n\n DESIGN.md:  " . substr($file, strlen(rtrim($root, '/')) + 1)
                    . "\n stylesheet: " . self::STYLESHEETS . "/$name.css" . (is_file($css) ? '' : ' (not written yet)') . "\n"
                    . ($hidden ? " also in:    " . implode(', ', array_map(function ($f) use ($root) { return substr($f, strlen(rtrim($root, '/')) + 1); }, $hidden)) . " (not used: the first one wins)\n" : '') . "\n");
                return 0;

            case 'use':
            case 'off':
                $name = $command === 'off' ? '' : self::name($args[1] ?? '');
                if ($command === 'use' && ($name === '' || (!self::find($name, $root) && !is_file(self::stylesheet($name, $root))))) {
                    fwrite(STDERR, "caligrafer design: unknown style '" . ($args[1] ?? '') . "' (php caligrafer.php design list)\n");
                    return 1;
                }
                if (!is_file($envFile)) {
                    fwrite(STDERR, "caligrafer design: $envFile not found\n");
                    return 1;
                }
                file_put_contents($envFile, Quickstart::envSet((string) file_get_contents($envFile), array('DESIGN_STYLE' => $name)));
                if ($name === '') {
                    print("\n DESIGN_STYLE= in $envFile: every page has Caligrafy's own look again. The stylesheets in " . self::STYLESHEETS . "/ are kept.\n\n");
                } elseif (is_file(self::stylesheet($name, $root))) {
                    print("\n DESIGN_STYLE=$name in $envFile: pages load " . self::STYLESHEETS . "/$name.css after the Caligrafy stylesheets. Reload them.\n\n");
                } else {
                    print("\n DESIGN_STYLE=$name in $envFile. Its stylesheet " . self::STYLESHEETS . "/$name.css is not written yet: pages keep Caligrafy's own look until it is.\n"
                        . " Write it in Claude Code: /caligrafy-quill:design (it reads " . substr(self::find($name, $root), strlen(rtrim($root, '/')) + 1) . " and restyles every page)\n\n");
                }
                return 0;

            case 'status':
                list($status, $message, $fix) = self::status($root);
                print("\n $status  $message\n" . ($fix ? "      -> $fix\n" : '') . "\n");
                return 0;

            default:
                print($usage);
                return $command === 'help' ? 0 : 1;
        }
    }
}
