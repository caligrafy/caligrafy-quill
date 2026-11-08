import { createApp } from 'vue'
import App from './App.vue'
import router from './router'

// import Store State Management (Pinia)
import { createPinia } from 'pinia'

// import axios
import axios from "axios"
import { config, apiConfig, apiHeaders } from './common/app.js'

import '../../css/styles.css'
import '../../css/theme.css'
import '../../css/tokens.css'
import '../../css/animate.css'
// Caligrafy's look: the structure of its pages (top bar, title band, tiles, rows) and its style (white, black actions, Inter)
import '../../css/layout.css'
import '../../css/look.css'
// a design style (DESIGN_STYLE): import '../../css/designs/<name>.css' here, after look.css
document.body.classList.add('cq-site')


const app = createApp(App)

// configure app
app.config.productionTip = false

// integrate http request engine
app.config.globalProperties.http = axios
app.config.globalProperties.axios = axios /* for backward compatibility also can use axios */

// app api configuration: the session (cookie + X-CSRF-Token), never an API key in the bundle (src/common/app.js)
app.config.globalProperties.config = config;
app.config.globalProperties.apiConfig = apiConfig;
app.config.globalProperties.apiHeaders = apiHeaders;

app.use(router)
app.use(createPinia())

app.mount('#app')
