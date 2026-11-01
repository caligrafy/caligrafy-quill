<?php
/**
 * PageController.php
 * The controller behind every Route::page(): one place where pages get who is signed in and their data
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class PageController
 * Route::page('/books/{id}', 'Books/show', ['data' => ['book' => 'get_book']]) renders a view through this controller:
 * 1. access: 'signedIn' => true sends visitors to /login; 'level' => N also sends people below level N to
 *    /notAuthorized (Auth::guard on Context::$levelAttribute)
 * 2. data: each 'data' entry calls a read-only capability as the person who opens the page ($this->call(): the same
 *    rules as REST and MCP), with the query string and the route variables as inputs (the route variables win), or
 *    with the inputs it lists (['book' => ['get_book', ['id' => 3]]]). A refusal becomes the page's answer: a visitor
 *    is sent to sign in; otherwise 403 (error 1006) or 404 (error 1005), or the 'notFound' view (route variables,
 *    'with', 'message', who opens it) with a 404 status
 * 3. the view gets the route variables, 'with' values, the data, 'authorized' and 'user' (id, username, level) - never
 *    the query string
 * Pages never read models: capabilities do, so a page, REST and AI agents follow the same rules.
 */
class PageController extends Controller
{
    const RESERVED = array('authorized', 'user');

    /**
     * Nothing to connect for a page without data: the controller is set up when a capability is called
     */
    public function __construct()
    {
    }

    /**
     * @param string $view the view, as for view(): 'Books/show'
     * @param array $options 'signedIn' (bool), 'level' (int), 'data' (array name => read-only capability or
     *                       [capability, inputs]), 'with' (array of fixed view data), 'notFound' (a view for 404s)
     */
    public function render($view, array $options = array())
    {
        $level = (int) ($options['level'] ?? 0);
        $mustSignIn = !empty($options['signedIn']) || $level > 0;
        if ($mustSignIn && !Auth::active()) {
            throw new \LogicException("Route::page('$view'): 'signedIn' and 'level' need Auth::authentication(...) in web.php");
        }
        if ($mustSignIn) {
            Auth::guard(Context::$levelAttribute, $level);
        }

        $route = self::routeVariables();
        $data = $route;
        foreach ((array) ($options['with'] ?? array()) as $name => $value) {
            $data[self::name($view, $name)] = $value;
        }
        $sources = (array) ($options['data'] ?? array());
        if ($sources) {
            parent::__construct();
            $defaults = array_merge((array) (request()->parameters ?? array()), $route);
        }
        foreach ($sources as $name => $source) {
            list($capability, $input) = is_array($source) ? array($source[0], $source[1] ?? null) : array($source, null);
            $entry = Capabilities::get($capability);
            if ($entry === null) {
                throw new \LogicException("Route::page('$view'): no capability named '$capability'");
            }
            if (!$entry['capability']->readOnly) {
                throw new \LogicException("Route::page('$view'): '$capability' changes data, and opening a page must not (only readOnly capabilities)");
            }
            try {
                $data[self::name($view, $name)] = $this->call($capability, $input ?? $defaults);
            } catch (ServiceError $e) {
                return $this->refused($e, $options);
            }
        }

        return view($view, array_merge($data, self::identity()));
    }

    /**
     * Who opens the page: 'authorized' and 'user' (id, username, level), for every view the page controller renders
     */
    public static function identity()
    {
        $signedIn = Auth::active() && Auth::authorized();
        $user = $signedIn ? Auth::user() : null;
        $attribute = Context::$levelAttribute;
        return array('authorized' => $signedIn,
            'user' => $user ? array('id' => $user->id, 'username' => $user->username, 'permissions' => (int) ($user->$attribute ?? 0)) : null);
    }

    /**
     * The variables of the route pattern only (/books/{id} -> id), not the query string
     */
    public static function routeVariables()
    {
        $request = request();
        return (array) bindRouteToVar((string) Route::$currentRoute, (string) ($request->uri ?? ''));
    }

    // a view variable the page controller sets itself cannot be replaced by 'data' or 'with'
    private static function name($view, $name)
    {
        if (in_array($name, self::RESERVED, true)) {
            throw new \LogicException("Route::page('$view'): '$name' is set by the page controller: use another name");
        }
        return $name;
    }

    /**
     * A capability refused the page's data: visitors are asked to sign in, everything else answers 403 or 404
     */
    private function refused(ServiceError $e, array $options)
    {
        if ($e->kind === 'unauthenticated' && Auth::active()) {
            redirect('/login');
            exit;
        }
        if ($e->kind === 'forbidden' || $e->kind === 'unauthenticated') {
            throw Errors::raise(1006);
        }
        if (!empty($options['notFound'])) {
            http_response_code(404);
            return view($options['notFound'], array_merge(self::routeVariables(), (array) ($options['with'] ?? array()),
                array('message' => $e->getMessage()), self::identity()));
        }
        throw Errors::raise(1005);
    }
}
