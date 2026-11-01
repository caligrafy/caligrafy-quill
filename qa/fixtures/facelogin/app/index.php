<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>Face Login</title>

        <link rel="shortcut icon" href="<?php echo scripts('favicon'); ?>" type="image/x-icon" />
        <!-- full-screen camera (public/css/camera.css), then this app's styles, then the design style if one is selected -->
        <link rel="stylesheet" href="<?php echo session('public').'css/camera.css';?>">
        <link rel="stylesheet" href="<?php echo session('public').'css/look.css';?>">
        <link rel="stylesheet" href="<?php echo session('public').end(request()->uriComponents).'/css/styles.css';?>">
        <?php if (design_stylesheet()): ?><link rel="stylesheet" href="<?php echo design_stylesheet(); ?>"><?php endif; ?>
    </head>
    <body class="cq-camera-body">
        <!-- Face sign-in on a full-screen camera: the oval guides the face, forms open in a sheet (public/css/camera.css) -->
        <div id="app" class="cq-camera" v-cloak>
            <div class="cq-camera-media cq-camera-mirror">
                <video id="detection" width="640" height="480" autoplay muted playsinline v-show="cameraVisible"></video>
            </div>
            <div class="cq-camera-oval" v-show="cameraVisible && !sheet" :class="viewportState ? 'is-' + viewportState : ''"></div>

            <header class="cq-camera-top">
                <nav class="facelogin-nav" role="tablist">
                    <button role="tab" :class="{ active: tab === 'login' }" :aria-selected="tab === 'login'" @click="show('login')">Sign in</button>
                    <button role="tab" :class="{ active: tab === 'enroll' }" :aria-selected="tab === 'enroll'" @click="show('enroll')">Enrol your face</button>
                </nav>
                <template v-if="tab === 'login' && !signedIn">
                    <h1 class="cq-camera-title">Sign in with your face</h1>
                    <p class="cq-camera-status" :class="{ 'is-error': loginState === 'failed' }" role="status" aria-live="polite">{{ loginMessage }}</p>
                </template>
                <template v-if="tab === 'enroll' && enrollStep === 2">
                    <h1 class="cq-camera-title">Scan your face</h1>
                    <p class="cq-camera-status" role="status" aria-live="polite">Signed in as {{ user }}. Center your face in the oval, then follow the prompts.</p>
                </template>
                <p class="cq-camera-status is-error" v-if="cameraError && cameraVisible">{{ cameraError }}</p>
                <p class="cq-camera-prompt" v-if="overlay">{{ overlay }}</p>
            </header>

            <!-- the camera's actions -->
            <footer class="cq-camera-bottom" v-if="!sheet">
                <template v-if="tab === 'login'">
                    <div class="cq-camera-actions">
                        <button class="cq-camera-button is-primary" v-if="loginState === 'failed'" @click="scan()">Try again</button>
                        <button class="cq-camera-button" v-if="passkeys" @click="passkeyLogin()" :disabled="busy">Use a passkey</button>
                    </div>
                    <button class="cq-camera-link" @click="show('enroll')">No face enrolled yet? Enrol your face</button>
                </template>
                <template v-if="tab === 'enroll' && enrollStep === 2">
                    <div class="cq-camera-actions">
                        <button class="cq-camera-button is-primary" @click="enroll()" :disabled="busy || !!cameraError">{{ busy ? 'Scanning…' : 'Start scan' }}</button>
                        <button class="cq-camera-button" @click="logout()" :disabled="busy">Not you?</button>
                    </div>
                </template>
                <p class="cq-camera-status" v-if="status">{{ status }}</p>
                <p class="cq-camera-status is-error" v-if="error">{{ error }}</p>
            </footer>

            <!-- forms and results, in a sheet over the camera -->
            <template v-if="sheet">
                <div class="cq-camera-backdrop"></div>
                <div class="cq-camera-sheet">
                    <!-- signed in -->
                    <div v-if="tab === 'login' && signedIn">
                        <div class="facelogin-done">&#10003;</div>
                        <h2>Welcome, {{ user }}</h2>
                        <p>Signed in with {{ factors.join(' + ') }}</p>
                        <button class="cq-camera-button" @click="logout()" :disabled="busy">Log out</button>
                    </div>
                    <!-- enrolment, step 1: password sign-in or new account -->
                    <form v-if="tab === 'enroll' && enrollStep === 1" @submit.prevent="account()">
                        <ol class="facelogin-steps"><li class="current">Account</li><li>Face scan</li><li>Done</li></ol>
                        <h2>{{ newAccount ? 'Create your account' : 'Confirm it is you' }}</h2>
                        <p>{{ newAccount ? 'Choose a username and a password.' : 'Sign in with your password before enrolling your face.' }}</p>
                        <label for="username">Username
                            <input id="username" v-model.trim="form.username" autocomplete="username" autocapitalize="none" required>
                        </label>
                        <label for="password">Password
                            <input id="password" type="password" v-model="form.password" :autocomplete="newAccount ? 'new-password' : 'current-password'" :minlength="newAccount ? 8 : null" required>
                        </label>
                        <button class="cq-camera-button is-primary" type="submit" :disabled="busy">{{ newAccount ? 'Create' : 'Continue' }}</button>
                        <button class="cq-camera-link" type="button" @click="newAccount = !newAccount; error = ''">{{ newAccount ? 'I already have an account' : 'Create a new account' }}</button>
                        <p class="cq-camera-error" v-if="error">{{ error }}</p>
                    </form>
                    <!-- enrolment, step 3: enrolled -->
                    <div v-if="tab === 'enroll' && enrollStep === 3">
                        <ol class="facelogin-steps"><li class="complete">Account</li><li class="complete">Face scan</li><li class="current">Done</li></ol>
                        <div class="facelogin-done">&#10003;</div>
                        <h2>Your face is enrolled</h2>
                        <p>{{ user }} can now sign in with their face: center it in the oval and follow the prompts. A passkey makes it a two-step login: the face, then this device's fingerprint, face unlock or PIN.</p>
                        <button class="cq-camera-button is-primary" @click="tryLogin()" :disabled="busy">Try it</button>
                        <button class="cq-camera-button" v-if="passkeys" @click="passkeyRegister()" :disabled="busy">Add a passkey</button>
                        <button class="cq-camera-button" @click="enrollStep = 2; status = ''">Re-scan</button>
                        <p v-if="status">{{ status }}</p>
                    </div>
                </div>
            </template>
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
