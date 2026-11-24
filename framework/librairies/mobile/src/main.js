import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { restore } from './mobile/session.js'

// Bootstrap and Caligrafy's look, built into the app: it works offline and loads nothing from a CDN
import 'bootstrap/dist/css/bootstrap.min.css'
import '../../css/tokens.css'
// Caligrafy's look: the structure of its pages (top bar, title band, tiles, rows) and its style (white, black actions, Inter)
import '../../css/layout.css'
import '../../css/look.css'
// a design style (DESIGN_STYLE): import '../../css/designs/<name>.css' here, after look.css
import './mobile/mobile.css'
document.body.classList.add('cq-site')

// the session kept on this phone first: the router then knows who is signed in
restore().finally(() => {
  createApp(App).use(router).mount('#app')
})
