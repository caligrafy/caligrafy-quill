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
 * 1. access: 'signedIn' => true sends visitors to /login; 'level' => N also sends people below permissions N to
 *    /notAuthorized (Auth::guard)
 * 2. data: each 'data' entry calls a capability as the person who opens the page ($this->call(): the same rules as
 *    REST and MCP), with the route variables and the query string as inputs, or with the inputs it lists
 *    (['book' => ['get_book', ['id' => 3]]]). A refusal becomes the page's answer: sign in (401), 403, 404
 * 3. the view gets the data, 'with' values, the route variables, 'authorized' and 'user' (id, username, permissions)
 * Pages never read models: capabilities do, so a page, REST and AI agents follow the same rules.
 */
class PageController extends Controller
{
    /**
     * @param string $view the view, as for view(): 'Books/show'
     * @param array $options 'signedIn' (bool), 'level' (int), 'data' (array name => capability or [capability, inputs]),
     *                       'with' (array of fixed view data)
     */
    public function render($view, array $options = array())
    {
        $level = (int) ($options['level'] ?? 0);
        $mustSignIn = !empty($options['signedIn']) || $level > 0;
        if ($mustSignIn && !Auth::active()) {
            throw new \LogicException("Route::page('$view'): 'signedIn' and 'level' need Auth::authentication(...) in web.php");
        }
        if ($mustSignIn) {
            Auth::guard('permissions', $level);
        }

        $data = (array) ($options['with'] ?? array());
        foreach ((array) ($options['data'] ?? array()) as $name => $source) {
            list($capability, $input) = is_array($source) ? array($source[0], $source[1] ?? null) : array($source, null);
            try {
                $data[$name] = $this->call($capability, $input);
            } catch (ServiceError $e) {
                $this->refused($e);
            }
        }

        $signedIn = Auth::active() && Auth::authorized();
        $user = $signedIn ? Auth::user() : null;
        return view($view, array_merge((array) (Route::$currentBind ?? array()), $data, array(
            'authorized' => $signedIn,
            'user' => $user ? array('id' => $user->id, 'username' => $user->username, 'permissions' => (int) ($user->permissions ?? 0)) : null
        )));
    }

    /**
     * A capability refused the page's data: visitors are asked to sign in, everything else answers 403 or 404
     */
    private function refused(ServiceError $e)
    {
        if ($e->kind === 'unauthenticated' && Auth::active()) {
            redirect('/login');
            exit;
        }
        throw Errors::raise($e->kind === 'forbidden' || $e->kind === 'unauthenticated' ? 1006 : 1005);
    }
}
