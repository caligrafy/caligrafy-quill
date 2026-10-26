/*
 * Caligrafy Bot client (Vue 3) for IBM watsonx Assistant
 *
 * Data routes (application/config/routing/web.php):
 * - POST __bots__/{appName}/{botSkillId}   connect
 * - POST __bots__/{appName}/communicate    {text, variables?} -> {action_success, response: [...], context}
 * - DELETE __bots__/{appName}              end
 *
 * Watson response types: text, option, image, pause. For "pause" responses whose context has an action,
 * define window.executeBotAction = function (context) { return { text: '...', variables: {...} }; }
 */
const app = Vue.createApp({
    data() {
        return {
            env: env,
            botId: env.botSkillId,
            route: env.home + "__bots__/" + env.appName + "/",
            messages: [],
            action: { type: null, options: [], context: null },
            text: '',
            waiting: false,
            config: {
                headers: {
                    "Authorization": "Bearer " + apiKey,
                    "Accept": "application/json",
                    "Content-Type": "application/json"
                }
            }
        };
    },
    methods: {
        addMessage(content, human = false) {
            this.messages.push({ type: 'text', content: content, human: human });
            this.scroll();
        },

        addImage(source, title) {
            this.messages.push({ type: 'image', source: source, title: title, human: false });
            this.scroll();
        },

        // action: 'text' (free input) or 'button' (options), context: variables sent with the next answer
        addAction(type, options = [], context = null) {
            this.action = { type: type, options: options, context: context };
        },

        answer(value, label) {
            if (!String(value).trim()) return;
            const input = { text: value };
            if (this.action.context) input.variables = this.action.context;
            this.addMessage(label, true);
            this.text = '';
            this.action = { type: null, options: [], context: null };
            this.communicate(input);
        },

        scroll() {
            this.$nextTick(() => {
                const box = this.$refs.messages;
                if (box) box.scrollTop = box.scrollHeight;
            });
        },

        // Connect to the bot
        connect() {
            return axios.post(this.route + this.botId, {}, this.config)
                .then(response => {
                    if (response.data !== true) console.log('Connection could not be established');
                    return response.data === true;
                })
                .catch(error => { console.log(error); return false; });
        },

        // Communicate with the bot
        communicate(input) {
            this.waiting = true;
            return axios.post(this.route + 'communicate', input, this.config)
                .then(response => {
                    this.waiting = false;
                    const data = response.data;
                    if (!(data && data.action_success === true && data.response)) {
                        this.addMessage("Bye for now. You can close the chat");
                        return;
                    }
                    data.response.forEach(element => {
                        switch (element.response_type) {
                            case 'text':
                                this.addMessage(element.text);
                                this.addAction('text');
                                break;
                            case 'option':
                                this.addMessage(element.title);
                                this.addAction('button', element.options.map(option => ({ text: option.label, value: option.value.input.text })));
                                break;
                            case 'image':
                                this.addImage(element.source, element.title);
                                this.addAction('text');
                                break;
                            case 'pause':
                                if (data.context && data.context.action && typeof window.executeBotAction === 'function') {
                                    const result = window.executeBotAction(data.context);
                                    if (result) {
                                        this.addMessage(result.text);
                                        this.addAction('text', [], result.variables);
                                    }
                                }
                                break;
                            default:
                                console.log(element);
                        }
                    });
                })
                .catch(error => {
                    this.waiting = false;
                    console.log(error);
                    this.addMessage("The assistant could not be reached.");
                });
        }
    },
    mounted() {
        this.connect();
        this.addMessage("Hi there!");
        this.addAction('text', [], { "bot_name": 'Cali' });
    }
});

app.mount('#app');
