/*
 * A Vue app on the capabilities of the application (build once, experience everywhere).
 *
 * The generated JS client has one function per capability: php caligrafer.php client public/js/capabilities.js
 * (run it again after adding or changing a capability; web.php needs Route::capabilities('/capabilities')).
 * It uses the session of the visitor and the CSRF token: no key is ever in the page.
 *
 *   const books = await this.api.listBooks()
 *   try { await this.api.createBook({ title: 'Dune' }) } catch (e) { if (e.name === 'CapabilityError') this.error = e.message }
 */
const app = Vue.createApp({
    data () {
        return {
            env: env,
            capabilities: [],
            loaded: false,
            error: ''
        }
    },
    async mounted () {
        try {
            this.api = await import(env.home + 'public/js/capabilities.js')
        } catch (e) {
            this.error = 'The JS client is missing: run php caligrafer.php client public/js/capabilities.js'
            return
        }
        try {
            this.capabilities = await this.api.capabilities()   // what this visitor may call
        } catch (e) {
            this.error = e.message
        }
        this.loaded = true
    }
})

/* Components Definition */

// Component Registration
//app.component('component-name', {
//
//	template: '#template',
//	props: [],
//	methods: {},
//	data() {
//		return {
//			// data definitions
//		}
//	}
//
//});

// Mount the app
app.mount('#app')
