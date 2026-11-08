/*
 * app.js - Common configuration and 3rd-party scripts to be used 
 * Author: Dory Azar
 *
 */

// Make axios accessible across the components
import axios from 'axios';

// The Caligrafy application this SPA calls: '' when the SPA is served by the application itself (same origin),
// else VITE_API_ROUTE (for example http://localhost:8080/ in development). No API key: anything built into the
// bundle is public (2.19.1)
export const config = {
	apiRoute: import.meta.env.VITE_API_ROUTE?? '',
	capabilities: import.meta.env.VITE_CAPABILITIES_ROUTE?? '/capabilities'
};

// The session's CSRF token: GET {apiRoute}/capabilities answers it (Route::capabilities('/capabilities') in web.php).
// An absolute path: the built app is in public/<name>/dist/, the route at the root of the application
let csrf = null;
export async function csrfToken() {
	if (csrf === null) {
		try {
			const response = await axios.get(config.apiRoute.replace(/\/+$/, '') + config.capabilities, { headers: { 'Accept': 'application/json' }, withCredentials: true });
			csrf = (response.data && response.data.csrf) || '';
		} catch {
			csrf = '';
		}
	}
	return csrf;
}

// The headers of a JSON call to the application's routes: the session cookie goes on its own and X-CSRF-Token proves
// the call comes from this app, as apiHeaders() of public/js/services/app.js does for client apps.
// axios.post(config.apiRoute + '/api/books', book, { headers: await apiHeaders(), withCredentials: true })
export async function apiHeaders(extra) {
	return Object.assign({ 'Accept': 'application/json', 'X-CSRF-Token': await csrfToken() }, extra || {});
}

// Axios configuration for the calls that read (GET): JSON, with the session cookie. Writes add await apiHeaders()
export const apiConfig = {
	
	async: true,
	withCredentials: true,
	headers: {
		'Accept': 'application/json',
		'Content-Type': 'application/json'
	}
	
};

export const callApi = axios;
