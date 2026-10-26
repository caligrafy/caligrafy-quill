<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Caligrafy Bot</title>

        <!-- Stylesheet and head scripts go here -->
        <link rel="shortcut icon" href="<?php echo scripts('favicon'); ?>" type="image/x-icon" />
        <link rel="stylesheet" href="<?php echo scripts('bootstrap_css'); ?>" />
        <link rel="stylesheet" href="<?php echo session('public').'css/bot.css';?>">
    </head>

    <body>
        <!-- Chat application (Vue 3) -->
        <div id="app" class="bot-container" v-cloak>
            <div class="bot-messages" ref="messages">
                <div v-for="(message, index) in messages" :key="index" class="bot-message" :class="message.human ? 'human' : 'bot'">
                    <template v-if="message.type === 'image'">
                        <a :href="message.source" target="_blank" rel="noopener">{{ message.title || message.source }}</a>
                        <img :src="message.source" :alt="message.title" class="bot-image">
                    </template>
                    <template v-else>{{ message.content }}</template>
                </div>
                <div v-if="waiting" class="bot-message bot typing">…</div>
            </div>

            <!-- Buttons answer (Watson "option" responses) -->
            <div v-if="action.type === 'button'" class="bot-actions">
                <button v-for="option in action.options" :key="option.value" type="button" class="btn btn-outline-primary btn-sm" @click="answer(option.value, option.text)">{{ option.text }}</button>
            </div>

            <!-- Free text answer -->
            <form v-else-if="action.type === 'text'" class="bot-input" @submit.prevent="answer(text, text)">
                <input v-model="text" class="form-control" type="text" placeholder="Type a message" aria-label="Message" autofocus>
                <button class="btn btn-primary" type="submit" :disabled="!text.trim()">Go</button>
            </form>
        </div>

        <!-- Initialization scripts -->
        <script src="<?php echo scripts('vue'); ?>"></script>
        <script src="<?php echo scripts('axios'); ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'app.js'; ?>"></script>
        <script>loadEnvironment(`<?php echo $env; ?>`);</script>
        <script>
            /* Loading the app client framework
             * Any environment variables to be passed on from the server can take place in this here
             */
            loadVue({
                scripts: ['main']
             });
        </script>
    </body>
</html>
