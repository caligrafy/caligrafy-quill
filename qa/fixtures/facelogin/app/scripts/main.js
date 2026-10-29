// Face login with liveness + passkeys (recipe: claude-plugin/skills/caligrafy/reference/recipes-face-login.md)
const PROMPTS = { 'blink': 'Blink', 'turn-left': 'Turn your head to your left', 'turn-right': 'Turn your head to your right', 'smile': 'Smile' };

const app = Vue.createApp({
  data() {
    return {
      env: env, detector: null, cameraError: '', username: '', prompt: 'Look at the camera', status: '', error: '', busy: false,
      passkeys: PasskeyClient.supported(),
      config: { headers: { 'Authorization': 'Bearer ' + apiKey, 'Accept': 'application/json', 'Content-Type': 'application/json' }, timeout: 30000 }
    };
  },
  methods: {
    async startCamera() {
      // markRaw: a reactive FaceDetector breaks face-api (G16); autoStart false so camera errors reach us (G42)
      this.detector = Vue.markRaw(new FaceDetector('detection', { autoStart: false }));
      try {
        await this.detector.ready;
        await this.detector.startStream({ video: { facingMode: 'user' }, audio: false });
      } catch (e) {
        this.cameraError = e.name === 'NotAllowedError' ? 'Camera access is blocked: allow it in the browser settings'
                         : e.name === 'NotFoundError' ? 'No camera found' : e.message;
      }
    },
    post(path, body) {
      return axios.post(this.env.home + path, body, this.config).then(({ data }) => {
        if (typeof data === 'string') throw new Error(data);         // [ERROR ...] with HTTP 200 (G48)
        return data;
      });
    },
    // server challenge -> liveness on the camera -> { nonce, liveness, prints } for the server
    async check(start) {
      const challenge = await this.post('facelogin/start', start);
      const result = await this.detector.liveness({
        challenges: challenge.challenges,
        timeout: Math.min(20000, challenge.expires_in * 1000 - 2000),
        onProgress: (p) => { this.prompt = p.challenge ? `${p.index + 1}/${p.total}: ${PROMPTS[p.challenge]}` : 'Done'; }
      });
      if (!result.passed) throw new Error(result.reason);
      return { nonce: challenge.nonce, liveness: { passed: true, challenges: result.challenges }, prints: result.prints.map(p => p.descriptor) };
    },
    async run(task) {
      this.busy = true; this.error = ''; this.status = '';
      try { await task(); } catch (e) { this.error = e.response?.data?.error || e.message; } finally { this.busy = false; this.prompt = 'Look at the camera'; }
    },
    enroll() {
      return this.run(async () => {
        const data = await this.post('facelogin/enroll', await this.check({ enroll: true }));
        this.status = `Face enrolled (${data.enrolled} prints)`;
      });
    },
    login() {
      return this.run(async () => {
        const username = this.username || undefined;
        const data = await this.post('facelogin/login', Object.assign({ username: username }, await this.check({ username: username })));
        if (data.step === 'passkey') {                                  // step-up: this account also needs its passkey
          this.prompt = 'Confirm with your passkey';
          return this.finishPasskey(data.options);
        }
        this.status = 'Welcome ' + data.user.username;
      });
    },
    async finishPasskey(options) {
      const data = await this.post('passkey/login', await PasskeyClient.get(options));
      this.status = `Welcome ${data.user.username} (${data.factors.join(' + ')})`;
    },
    passkeyLogin() {
      return this.run(async () => this.finishPasskey(await this.post('passkey/login/options', {})));
    },
    passkeyRegister() {
      return this.run(async () => {
        const options = await this.post('passkey/register/options', {});
        await this.post('passkey/register', Object.assign({ name: 'This device' }, await PasskeyClient.create(options)));
        this.status = 'Passkey added';
      });
    }
  },
  mounted() { this.startCamera(); },
  beforeUnmount() { if (this.detector) this.detector.stopStream(); this.detector = null; }
});
app.mount('#app');
