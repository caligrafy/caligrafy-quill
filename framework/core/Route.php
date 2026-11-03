<?php
/**
 * Route.php
 * Class that builds the routing logic of the framework
 * @author Dory A.Azar
 * @copyright 2019
 * @version 1.0
 *
*/

/**
 * Class that builds the routing logic of the framework
 * @version 1.0
 *
*/

namespace Caligrafy;
use \Exception as Exception;

class Route extends \stdClass
{
    /**
     * Namespace of the example controllers shipped in application/controllers/core (AIController, AuthController...)
    */
    const EXAMPLE_CONTROLLERS_NAMESPACE = 'Caligrafy\\Controllers';

    
    /**
     * @var array $_httpMethods defines all the valid http methods that are set at runtime
     * @property array $_httpMethods defines all the valid http methods at runtime
    */
    private static $_httpMethods = array();
    
    /**
     * @var array $_routes defines all the valid routes that are set at runtime
     * @property array $_routes defines all the valid routes at runtime
    */
    private static $_routes = array();

    /**
     * @var array $_routesComponents defines all the valid routes components that are set at runtime
     * @property array $_routesComponents defines all the valid routes components that are set at runtime
    */
    private static $_routesComponents = array();

    /**
     * @var array $_methods defines all the methods that are set at runtime
     * @property array $_methods defines all the methods that are set at runtime
    */
    private static $_methods = array();
    
    /**
     * @var int $_currentRouteKey defines the currently run route key
     * @property int $_currentRouteKey defines the currently run route key
    */
    private static $_currentRouteKey;
    

    /**
     * @var int $_currentRouteComponents defines the currently run route components 
     * @property int $_currentRouteComponents defines the currently run route components 
    */
    private static $_currentRouteComponents = array();

    
    /**
     * @var Request $_request defines the request
     * @property Request $_request defines the request
    */
    private static $_request;


    /**
     * @var string $currentRoute defines the currently run route
     * @property string $currentRoute defines the currently run route
    */
    public static $currentRoute;

    /**
     * @var string $currentBind defines variables bound to the route
     * @property string $currentBind defines variables bound to the route
    */
    public static $currentBind;


    /**
     * Runs the routes based upon a request
    */
    public static function run()
    {
        //fetch the request
        self::$_request = request();

        // Evaluate the Route to get the full expression with the variables bounded
        self::evaluate();

        // Bind the route variables to the request uri
        self::bind();

        // execute the routing
        self::execute();

    }
    

    /**
     * Evaluates the route and binds it with variables from the request
    */
    public static function evaluate() {
        // HEAD asks what GET would answer (monitors, curl -I)
        $requestMethod = self::$_request->method === 'HEAD' ? 'GET' : self::$_request->method;
        $uriComponents = self::$_request->uriComponents;

        $route = self::$_routesComponents;

        // clean up all the components that have a different size and a different method
        foreach(self::$_routesComponents as $key => $value) {
             if (sizeof($value) == 0 || sizeof($value) != 0 && (sizeof($value) != sizeof($uriComponents) || self::$_httpMethods[$key] != $requestMethod))
             {
                unset($route[$key]);
             } 
        }

        // among the remaining components keep only the values that match the pattern
        foreach($route as $k => $v) {
            //go through all the defined routes
            $path = implode('/', $v);
            foreach($v as $key => $value) {
                // replace all the variable patterns with values from uri
                if(preg_match(ROUTE_ARG_PATTERN, $value)) {
                     $route[$k][$key] =  $uriComponents[$key];
                }
            }
            //if evaluated route is not the same as uri, unset
            $currentPath = urldecode(implode('/', $route[$k]));
            if ($currentPath != self::$_request->uri) {
                unset($route[$k]);
            }            
        } 
        // the root: its routes have no components, so they are matched here (2.16.0; before, every unknown address ran the
        // first route declared)
        if (!$route && !$uriComponents) {
            foreach (self::$_routesComponents as $key => $value) {
                if (sizeof($value) == 0 && self::$_httpMethods[$key] == $requestMethod) { $route = array($key => $value); break; }
            }
        }
        // nothing matches: 405 when the address exists with another method, else 404
        if (!$route) {
            $methods = array();
            foreach (self::$_routesComponents as $key => $value) {
                if (self::pathMatches($value, $uriComponents)) $methods[] = self::$_httpMethods[$key];
            }
            if ($methods) {
                if (!headers_sent()) header('Allow: ' . implode(', ', array_unique($methods)));
                throw Errors::raise(1000, 'this address answers ' . implode(', ', array_unique($methods)));
            }
            throw Errors::raise(1005);
        }
        self::$_currentRouteKey = array_keys($route)[0];
        self::$_currentRouteComponents = self::$_routesComponents[self::$_currentRouteKey];
        self::$currentRoute = urldecode(implode('/', self::$_routesComponents[self::$_currentRouteKey]));
    }

    /**
     * Whether a declared route (its components) matches the request's components, whatever the method
     */
    private static function pathMatches(array $components, array $uriComponents)
    {
        if (sizeof($components) != sizeof($uriComponents)) return false;
        foreach ($components as $i => $part) {
            if (!preg_match(ROUTE_ARG_PATTERN, $part) && urldecode($part) !== urldecode($uriComponents[$i])) return false;
        }
        return true;
    }

    /**
     * Binding the route variables
    */

    public static function bind() {

        $uriPathComponent = implode('/', self::$_currentRouteComponents);

        // Bind route to variables
        self::$currentBind = bindRouteToVar($uriPathComponent, self::$_request->uri);
        
        // if there are any additional parameters from GET
        if (self::$_httpMethods[self::$_currentRouteKey] == 'GET') {
            self::$currentBind = array_merge(self::$currentBind, self::$_request->parameters);
        }
    }

    /**
     * Executing the routing
    */
    public static function execute() {

         // If no method set, a route to view is implied
        if (self::$_methods[self::$_currentRouteKey] == '') {
            echo "A method needs to be specified";
            
        } else if (is_string(self::$_methods[self::$_currentRouteKey])) {
            // if the method is a string then parse Controller and Action
            $parsedMethod = explode('@',self::$_methods[self::$_currentRouteKey]);
            $controller = self::resolveController($parsedMethod[0]);
            $action = $parsedMethod[1]?? 'index';
        
            
            // Generate Exception if Controller or the Action do not exist 
            if (!$controller || !method_exists($controller, $action)) {
                throw Errors::raise(1001);
                exit;
            }

            // JSON calls need a page of this application (the session's CSRF token in X-CSRF-Token) or, when the API
            // is activated, a personal access token that grants everything or the legacy key; the refusal says why
            if (self::$_request->wantsJson() && !self::$_request->authorizeSessionRequest()
                && (!Auth::$api || !self::$_request->authorizeApiRequest())) {
                throw Errors::raise(1004, self::$_request->apiRefusal());
                exit;
            }
        
            // Execute if Controller and Action exist
            $controller = new $controller();
            $controller->$action();

        } else {
            // if method is not a string then assumption is that it is a callback function
            call_user_func(self::$_methods[self::$_currentRouteKey]);
        }

    }
    
    
    /**
     * Resolves the controller part of a 'Controller@action' route to a declared class name
     * - fully qualified names are accepted, with or without a leading backslash: 'Shop\OrderController', '\Shop\OrderController'
     * - a declared class always wins (a project's own global AIController is used as is)
     * - a short name that is not a declared class falls back to the framework example controllers
     *   namespace (Caligrafy\Controllers), so routes written before 1.6 such as 'AIController@query' keep working
     * @param string $name defines the controller part of the route
     * @return string|null the class name, or null when no such controller exists
    */
    public static function resolveController($name)
    {
        $name = ltrim(trim((string) $name), '\\');
        if ($name === '') {
            return null;
        }
        if (class_exists($name)) {
            return $name;
        }
        if (strpos($name, '\\') === false && class_exists(self::EXAMPLE_CONTROLLERS_NAMESPACE . '\\' . $name)) {
            return self::EXAMPLE_CONTROLLERS_NAMESPACE . '\\' . $name;
        }
        return null;
    }

    /**
     * GET http method handler
     * @param string $route defines the uri
     * @param object $method defines the method to be used, it could be a String or a callback function
    */
    public static function get($route, $method = null)
    {
        self::$_httpMethods[] = 'GET';
        self::set($route, $method);
    }
    
    /**
     * POST http method handler
     * @param string $route defines the uri
     * @param object $method defines the method to be used, it could be a String or a callback function
    */
    public static function post($route, $method = null)
    {
        self::$_httpMethods[] = 'POST';
        self::set($route, $method);
    }
    
    /**
     * PUT http method handler
     * @param string $route defines the uri
     * @param object $method defines the method to be used, it could be a String or a callback function
    */
    public static function put($route, $method = null)
    {
        self::$_httpMethods[] = 'PUT';
        self::set($route, $method);
    }
    
    /**
     * DELETE http method handler
     * @param string $route defines the uri
     * @param object $method defines the method to be used, it could be a String or a callback function
    */
    public static function delete($route, $method = null)
    {
        self::$_httpMethods[] = 'DELETE';
        self::set($route, $method);
    }
    
    /**
     * Maps a route to a capability (REST): Route::capability('POST', '/books', 'create_book')
     * The route variables ({id}) become inputs of the capability. See Caligrafy\Rest for the request and response format.
     * @param string $method GET, POST, PUT or DELETE
     * @param string $route the uri
     * @param string $name the capability name
    */
    public static function capability($method, $route, $name)
    {
        $method = strtolower((string) $method);
        if (!in_array($method, array('get', 'post', 'put', 'delete'), true)) {
            throw new \InvalidArgumentException("Route::capability: unknown method '$method'");
        }
        self::$method($route, function () use ($name) { Rest::respond($name); });
    }

    /**
     * A page: renders a view through the framework's page controller, which gives every page who is signed in and,
     * optionally, an access rule and data from capabilities (pages never read models):
     *   Route::page('/books', 'Books/index');
     *   Route::page('/books/{id}', 'Books/show', ['data' => ['book' => 'get_book']]);
     *   Route::page('/admin/books', 'Books/admin', ['level' => 1, 'data' => ['books' => 'list_books']]);
     * Options: 'signedIn' (bool), 'level' (int), 'data' (name => read-only capability, or [capability, inputs]),
     * 'with' (fixed values), 'notFound' (a view shown with a 404 when a data capability finds nothing)
     * @param string $route the uri
     * @param string $view the view, as for view()
     * @param array $options see PageController::render()
    */
    public static function page($route, $view, array $options = array())
    {
        self::get($route, function () use ($view, $options) { (new PageController())->render($view, $options); });
    }

    /**
     * Exposes every capability under a prefix: GET {prefix} lists what the caller may call (with the input schemas),
     * POST {prefix}/{name} calls one. The JS client of the Vue apps and the SPA (php caligrafer.php client) uses it.
     * @param string $prefix the uri prefix
    */
    public static function capabilities($prefix = '/capabilities')
    {
        $prefix = '/' . trim((string) $prefix, '/');
        self::get($prefix, function () { Rest::listing(); });
        self::post($prefix . '/{__capability}', function () { Rest::respond((string) (currentBind()['__capability'] ?? '')); });
    }

    /**
     * The two pages notification emails link to (2.15.0, Notify): /notify/confirm/{token} confirms an address,
     * /notify/unsubscribe turns the user's emails off from a signed link, also as the one-click POST of mail providers
     * (List-Unsubscribe-Post: the signature replaces the CSRF token). Each answers a short page
    */
    public static function notifications()
    {
        self::get('/notify/confirm/{__token}', function () {
            $ok = Notify::confirm((string) (currentBind()['__token'] ?? '')) !== null;
            Notify::page($ok ? 'Your email is confirmed' : 'This link no longer works', $ok ? 'Notifications will come to this address.' : 'It was used already or it expired: ask for a new one from the app.', $ok ? 200 : 404);
        });
        $unsubscribe = function () {
            $ok = Notify::unsubscribeEmail((string) ($_REQUEST['u'] ?? ''), (string) ($_REQUEST['s'] ?? ''));
            Notify::page($ok ? 'You are unsubscribed' : 'This link is not valid', $ok ? 'No more notification emails. Turn them on again from the app.' : 'Use the link of the latest email.', $ok ? 200 : 400);
        };
        self::get('/notify/unsubscribe', $unsubscribe);
        self::post('/notify/unsubscribe', $unsubscribe);
    }

    /**
     * Exposes the capabilities to AI agents over MCP (Streamable HTTP) at a uri: Route::mcp('/mcp')
     * Agents authenticate with a personal access token and see the capabilities its scopes allow.
     * @param string $route the uri of the MCP endpoint
    */
    public static function mcp($route = '/mcp')
    {
        self::post($route, function () { Mcp\Http::respond(); });
        self::get($route, function () { Mcp\Http::methodNotAllowed(); });
        self::delete($route, function () { Mcp\Http::methodNotAllowed(); });
    }

    /**
     * Private mthod that sets the routes at runtime
     * @param string $route defines the uri
     * @param object $method defines the method to be used, it could be a String or a callback function
    */
    private static function set($route, $method = null)
    {
        $route = sanitizePath($route);
        $routeWithNoParameters = explode('?', $route)?? array();
        $route = $routeWithNoParameters? $routeWithNoParameters[0] : $route;
        self::$_routes[] = $route;
        self::$_routesComponents[] = parsePath($route);
        self::$_methods[] = $method?? '';
    }

}