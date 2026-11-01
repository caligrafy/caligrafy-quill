var env = null;
var apiKey = null;
var csrfToken = null;

function loadEnvironment(environment) {
    env = JSON.parse(environment);
    apiKey = env.apiKey || null;
    csrfToken = env.csrf || env.token || null;
}

/* The headers of a JSON call from this page to its own routes (controller routes and capabilities): the session
 * cookie goes on its own, X-CSRF-Token proves the call comes from this page. No API key: it is public in the page
 * and turned off online (LEGACY_API_KEY=false). axios.get(url, { headers: apiHeaders() })
 * No Content-Type: axios sends JSON for objects and multipart for FormData by itself (fetch: add it for a JSON body)
 */
function apiHeaders(extra) {
    return Object.assign({ 'Accept': 'application/json', 'X-CSRF-Token': csrfToken }, extra || {});
}


function loadVue(parameters) {
    var body = document.getElementsByTagName('body').item(0);
    var appName = env.appName? `/${env.appName}` : '';
    for(i = 0; i < parameters.scripts.length; i++) {
        var script = document.createElement('script');
        script.setAttribute('src', env.home + 'public' + appName + '/scripts/' + parameters.scripts[i] + '.js');
        body.appendChild(script);        
    }
    
}
