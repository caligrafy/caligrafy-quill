<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Face Login</title>
        <link rel="shortcut icon" href="<?php echo scripts('favicon'); ?>" type="image/x-icon" />
        <link rel="stylesheet" href="<?php echo scripts('bootstrap_css'); ?>">
        <link rel="stylesheet" href="<?php echo session('public').end(request()->uriComponents).'/css/styles.css';?>">
    </head>
    <body>
        <div id="app">
            <header><h1>Face Login</h1></header>
            <section id="detector">
                <video id="detection" width="480" height="360" autoplay muted playsinline></video>
            </section>
            <section class="controls container">
                <p class="error" v-if="cameraError">{{ cameraError }}</p>
                <p id="prompt" class="default-text">{{ prompt }}</p>
                <input class="form-control" v-model="username" placeholder="Username (optional)">
                <button id="login" class="btn btn-primary" @click="login()" :disabled="busy">Log in with my face</button>
                <button id="passkeyLogin" class="btn btn-primary" v-if="passkeys" @click="passkeyLogin()" :disabled="busy">Log in with a passkey</button>
                <button id="enroll" class="btn btn-primary" @click="enroll()" :disabled="busy">Enrol my face</button>
                <button id="passkeyRegister" class="btn btn-primary" v-if="passkeys" @click="passkeyRegister()" :disabled="busy">Add a passkey</button>
                <p id="status">{{ status }}</p>
                <p id="error" class="error" v-if="error">{{ error }}</p>
            </section>
        </div>

        <script src="<?php echo scripts('vue'); ?>"></script>
        <script src="<?php echo scripts('axios'); ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'app.js'; ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'face-api.min.js'; ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'detect.js'; ?>"></script>
        <script src="<?php echo APP_SERVICE_ROOT.'passkey.js'; ?>"></script>
        <script>loadEnvironment(`<?php echo $env; ?>`);</script>
        <script>loadVue({ scripts: ['main'] });</script>
    </body>
</html>
