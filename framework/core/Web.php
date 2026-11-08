<?php
/**
 * Web.php
 * What a web-only capability answers with: a page, a redirect, JSON or text
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Web (2.21.0)
 * A capability declared #[Capability(..., webOnly: true)] and served by Route::action() may return one of these
 * instead of plain data, to choose what the browser gets:
 *   return Web::view('Books/show', ['book' => $book]);
 *   return Web::view('Books/missing', ['message' => 'No such book'], 404);
 *   return Web::redirect('/books');
 *   return Web::redirect('https://checkout.stripe.com/...', 303);
 *   return Web::json(['enrolled' => true], 201);
 *   return Web::text('ok')->header('Cache-Control', 'no-store');
 */
class Web
{
    private $type;
    private $target;
    private $data;
    private $code;
    private $headers = array();

    private function __construct($type, $target, $data, $code)
    {
        $this->type = $type;
        $this->target = $target;
        $this->data = $data;
        $this->code = (int) $code;
    }

    /** A view, given who is signed in as Route::page gives it ('authorized', 'user'); $data wins */
    public static function view($view, array $data = array(), $status = 200)
    {
        return new self('view', (string) $view, $data, $status);
    }

    /** A path of this site or a full http(s) address */
    public static function redirect($to, $status = 302)
    {
        return new self('redirect', (string) $to, null, $status);
    }

    public static function json($data, $status = 200)
    {
        return new self('json', null, $data, $status);
    }

    public static function text($body = '', $status = 200)
    {
        return new self('text', null, (string) $body, $status);
    }

    /** One more response header */
    public function header($name, $value)
    {
        $this->headers[$name] = (string) $value;
        return $this;
    }

    /** What it answers, for tests: ['type', 'target', 'data', 'status', 'headers'] */
    public function describe()
    {
        return array('type' => $this->type, 'target' => $this->target, 'data' => $this->data, 'status' => $this->code, 'headers' => $this->headers);
    }

    /** Answers the request */
    public function send()
    {
        if (!headers_sent()) {
            http_response_code($this->code);
            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }
        }
        switch ($this->type) {
            case 'view':
                return view($this->target, array_merge(PageController::identity(), (array) $this->data));
            case 'redirect':
                redirect($this->target);
                return null;
            case 'json':
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode(Capabilities::plain($this->data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                return null;
            default:
                echo $this->data;
                return null;
        }
    }
}
