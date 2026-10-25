/*
 * app.js - Common configuration and 3rd-party scripts to be used 
 * Author: Dory Azar
 *
 */

// Make axios accessible across the components
import axios from 'axios';

// API configuration of the PHP back-end that was created for this course
export const config = {
	apiKey: import.meta.env.VITE_API_KEY,
	apiRoute: import.meta.env.VITE_API_ROUTE?? ''
	
};

// Axios configuration that uses a bearer token to be used with the REST API created
export const apiConfig = {
	
	async: true,
	crossDomain: true,
	headers: {
		"Authorization": "Bearer " + config.apiKey,
		'Accept': 'application/json',
		'Content-Type': 'application/json'
	}
	
};

export const callApi = axios;
