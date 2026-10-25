// Code samples of wiki pages 9.7.3 (Requests) and 9.7.6 (Components), Vue 3
const app = Vue.createApp({
    data() {
        return {
            env: env,
            welcomeMessage: 'Hello from data',
            searchText: '',
            shown: '',
            response: null,
            error: null,
            config: {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + apiKey
                }
            }
        }
    },
    methods: {
        showMessage: function (message) { this.shown = message; },
        get: function () {
            axios.get(this.env.home + 'qa/books/7', this.config)
                .then(response => (this.response = response))
                .catch(error => (this.error = error));
        }
    },
    mounted() { this.get(); }
});

app.component('show-message', { template: '#messageBlock', props: ['message'] });
app.component('slot-message', { template: '#slotBlock' });
app.component('emit-message', { template: '#emitBlock', emits: ['display-message'] });
app.component('custom-input', {
    props: ['modelValue'],
    emits: ['update:modelValue'],
    template:
    `
        <input id="custom"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        >
    `
});

app.mount('#app');
