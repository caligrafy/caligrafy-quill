import { createApp } from 'vue'
import App from './App.vue'
import router from './router'

// import Store State Management
import store from './common/store'

// import axios
import axios from "axios"

import '../../css/styles.css'
import '../../css/theme.css'
import '../../css/animate.css'


const app = createApp(App)

// configure app
app.config.productionTip = false

// integrate http request engine
app.config.globalProperties.http = axios
app.config.globalProperties.axios = axios /* for backward compatibility also can use axios */

// app api configuration
app.config.globalProperties.config = {
	apiKey: import.meta.env.VITE_API_KEY,
	apiRoute: '' /* specify api route here or in env file */
};

app.config.globalProperties.apiConfig = {
	
	async: true,
	crossDomain: true,
	headers: {
		"Authorization": "Bearer " + app.config.globalProperties.config.apiKey,
		'Content-Type': 'application/json'
	}
	
};

app.use(router)
app.use(store)

app.mount('#app')
