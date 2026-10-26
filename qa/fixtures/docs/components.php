<!DOCTYPE html>
<html lang="en">
    <head><meta charset="UTF-8"><title>QA wiki samples (9.7.3, 9.7.6)</title></head>
    <body>
        <div id="app">
            <show-message message='Welcome to Caligrafy'></show-message>
            <show-message :message='welcomeMessage'></show-message>
            <slot-message>
                {{ welcomeMessage }}
                <template v-slot:welcome>named slot: {{ welcomeMessage }}</template>
            </slot-message>
            <emit-message @display-message='showMessage($event)'></emit-message>
            <custom-input v-model="searchText"></custom-input>
            <p id="searched">searched: {{ searchText }}</p>
            <p id="shown">shown: {{ shown }}</p>
            <p id="api">api: {{ response ? response.data.id : (error ? 'ERROR ' + error : 'pending') }}</p>
        </div>

        <script type='text/x-template' id='messageBlock'>
            <div class="message">{{ message }}</div>
        </script>
        <script type='text/x-template' id='slotBlock'>
            <div class="slots"><slot></slot> | <slot name='welcome'></slot></div>
        </script>
        <script type='text/x-template' id='emitBlock'>
            <div class='horizontal-center'>
                <button id="welcome" @click="$emit('display-message','Welcome to Caligrafy')">Welcome</button>
            </div>
        </script>

        <script src="<?php echo scripts('vue'); ?>"></script>
        <script src="<?php echo scripts('axios'); ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'app.js'; ?>"></script>
        <script>loadEnvironment(`<?php echo $env; ?>`);</script>
        <script>loadVue({ scripts: ['components'] });</script>
    </body>
</html>
