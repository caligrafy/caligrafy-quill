<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="content-type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Face Login</title>

        <link rel="shortcut icon" href="<?php echo scripts('favicon'); ?>" type="image/x-icon" />
        <link rel="stylesheet" type="text/css" href="<?php echo scripts('css'); ?>" />
        <link rel="stylesheet" href="<?php echo scripts('bootstrap_css'); ?>" />
        <link rel="stylesheet" href="<?php echo session('public').'css/theme.css';?>">
        <link rel="stylesheet" href="<?php echo session('public').end(request()->uriComponents).'/css/styles.css';?>">
    </head>

    <body>
        <div id="app" v-cloak>
            <header class="color0 caligrafy-header">
                <nav class="horizontal">
                    <h1 class="large-text horizontal-center"><span class="semibold">Cali</span>grafy</h1>
                </nav>
            </header>

            <main class="facelogin-page">
                <div class="card dropshadow color0 facelogin-card">

                    <!-- flow switch -->
                    <div class="tabs" role="tablist">
                        <button role="tab" :class="{ active: tab === 'login' }" :aria-selected="tab === 'login'" @click="show('login')">Sign in</button>
                        <button role="tab" :class="{ active: tab === 'enroll' }" :aria-selected="tab === 'enroll'" @click="show('enroll')">Enrol your face</button>
                    </div>

                    <!-- camera, shared by both flows -->
                    <div class="viewport" v-show="cameraVisible" :class="viewportState">
                        <video id="detection" width="640" height="480" autoplay muted playsinline></video>
                        <div class="guide"></div>
                        <div class="overlay-prompt" v-if="overlay">{{ overlay }}</div>
                    </div>
                    <p class="error" v-if="cameraError && cameraVisible">{{ cameraError }}</p>

                    <!-- ============ SIGN IN ============ -->
                    <section v-if="tab === 'login'" class="flow">
                        <template v-if="!signedIn">
                            <h2 class="flow-title">Sign in with your face</h2>
                            <p class="flow-status" :class="loginState">{{ loginMessage }}</p>
                            <div class="flow-actions">
                                <button class="btn btn-primary" v-if="loginState === 'failed'" @click="scan()">Try again</button>
                                <button class="btn btn-secondary" v-if="passkeys" @click="passkeyLogin()" :disabled="busy">Use a passkey</button>
                            </div>
                            <p class="flow-note">No face enrolled yet? <a href="#" @click.prevent="show('enroll')">Enrol your face</a></p>
                        </template>

                        <div v-else class="done">
                            <div class="done-mark">&#10003;</div>
                            <h2 class="flow-title">Welcome, {{ user }}</h2>
                            <p class="flow-note">Signed in with {{ factors.join(' + ') }}</p>
                            <div class="flow-actions">
                                <button class="btn btn-secondary" @click="logout()" :disabled="busy">Log out</button>
                            </div>
                        </div>
                    </section>

                    <!-- ============ ENROL ============ -->
                    <section v-if="tab === 'enroll'" class="flow">
                        <ol class="steps">
                            <li :class="stepClass(1)"><span>1</span>Account</li>
                            <li :class="stepClass(2)"><span>2</span>Face scan</li>
                            <li :class="stepClass(3)"><span>3</span>Done</li>
                        </ol>

                        <!-- step 1: password sign-in or new account -->
                        <form v-if="enrollStep === 1" @submit.prevent="account()" class="account-form">
                            <h2 class="flow-title">{{ newAccount ? 'Create your account' : 'Confirm it is you' }}</h2>
                            <p class="flow-note">{{ newAccount ? 'Choose a username and a password.' : 'Sign in with your password before enrolling your face.' }}</p>
                            <label class="default-text textcolor5" for="username">Username</label>
                            <input id="username" class="form-control" v-model.trim="form.username" autocomplete="username" required>
                            <label class="default-text textcolor5" for="password">Password</label>
                            <input id="password" class="form-control" type="password" v-model="form.password" :autocomplete="newAccount ? 'new-password' : 'current-password'" :minlength="newAccount ? 8 : null" required>
                            <div class="flow-actions">
                                <button class="btn btn-primary" type="submit" :disabled="busy">{{ newAccount ? 'Create' : 'Continue' }}</button>
                            </div>
                            <p class="flow-note">
                                <a href="#" @click.prevent="newAccount = !newAccount; error = ''">{{ newAccount ? 'I already have an account' : 'Create a new account' }}</a>
                            </p>
                        </form>

                        <!-- step 2: liveness scan -->
                        <div v-if="enrollStep === 2">
                            <h2 class="flow-title">Scan your face</h2>
                            <p class="flow-note">Signed in as <strong>{{ user }}</strong>. Center your face in the oval, then follow the prompts on the video (turn your head, smile).</p>
                            <div class="flow-actions">
                                <button class="btn btn-primary" @click="enroll()" :disabled="busy || !!cameraError">{{ busy ? 'Scanning…' : 'Start scan' }}</button>
                                <button class="btn btn-secondary" @click="logout()" :disabled="busy">Not you?</button>
                            </div>
                        </div>

                        <!-- step 3: enrolled -->
                        <div v-if="enrollStep === 3" class="done">
                            <div class="done-mark">&#10003;</div>
                            <h2 class="flow-title">Your face is enrolled</h2>
                            <p class="flow-note">{{ user }} can now sign in with their face: center it in the oval and follow the prompts.</p>
                            <div class="flow-actions">
                                <button class="btn btn-primary" @click="tryLogin()" :disabled="busy">Try it</button>
                                <button class="btn btn-secondary" v-if="passkeys" @click="passkeyRegister()" :disabled="busy">Add a passkey</button>
                                <button class="btn btn-secondary" @click="enrollStep = 2; status = ''">Re-scan</button>
                            </div>
                            <p class="flow-note">A passkey makes face sign-in a two-step login: the face, then this device's fingerprint, face unlock or PIN.</p>
                        </div>
                    </section>

                    <p class="flow-success" v-if="status">{{ status }}</p>
                    <p class="error" v-if="error">{{ error }}</p>
                </div>
            </main>
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
