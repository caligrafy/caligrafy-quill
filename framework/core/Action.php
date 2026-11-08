<?php
/**
 * Action.php
 * The web channel of web-only capabilities: Route::action()
 * @copyright 2026
 *
*/

namespace Caligrafy;

/**
 * Class Action (2.21.0)
 * Route::action('/connect', 'cloud_connect') answers a request with a capability declared webOnly: true, for what a
 * page or a form cannot say: a sign-in between two sites, a session that decides where to go, a gate, a payment page.
 * 1. access: the 'signedIn', 'level', 'comeBack' and 'visitorsOnly' options of Route::page
 * 2. the capability is called with the query string or the form, and the route variables (which win), as the person
 *    who sent it, with its rules, permissions and audit log like any capability
 * 3. its answer goes to the browser: a Caligrafy\Web (a view, a redirect, JSON, text), or JSON of the plain data
 *    A JSON call needs the page's X-CSRF-Token or an API token, signed in or not ([ERROR 1004], 401)
 * 4. a refusal it does not answer itself is treated as in Route::form: invalid input or a conflict goes back with
 *    errors(), old() and flash('error'); a visitor goes to sign in; forbidden is 403, anything else 404. JSON requests
 *    get the REST error.
 * Unlike every other capability, it may read the session and the request and may sign people in, and a GET may change
 * data: so a webOnly capability is reachable through Route::action only, never through REST, MCP, the JS client,
 * Route::page, Route::form or a controller.
 */
class Action
{
    public static function handle($capability, array $options = array(), $request = null)
    {
        $request = $request ?? request();
        $entry = Capabilities::get($capability);
        if ($entry === null) {
            throw new \LogicException("Route::action: no capability named '$capability'");
        }
        if (!$entry['capability']->webOnly) {
            throw new \LogicException("Route::action: '$capability' must be declared webOnly: true");
        }
        if (PageController::admit($options, "Route::action('$capability')")) {
            return null;
        }
        $context = Context::fromRequest($request, 'web');
        $context->viaAction = true;
        $json = $request->wantsJson();
        $input = Rest::input($request);
        // a JSON call needs the page's CSRF token or an API token, signed in or not, as on a controller route (2.21.1)
        if ($json && !$request->authorizeSessionRequest() && (!Auth::api() || !$request->authorizeApiRequest())) {
            throw Errors::raise(1004, $request->apiRefusal());
        }
        try {
            $answer = Capabilities::call($capability, $input, $context, new Uploads($_FILES ?? array()));
        } catch (ServiceError $e) {
            if (!empty($options['comeBack'])) {
                $options['signIn'] = PageController::signInAddress();
            }
            return Form::perform(Form::refused($e, $options, $input, $json, Form::environment($request)));
        }
        return self::answer($answer);
    }

    /**
     * Sends what the capability answered
     */
    public static function answer($answer)
    {
        return ($answer instanceof Web ? $answer : Web::json($answer))->send();
    }
}
