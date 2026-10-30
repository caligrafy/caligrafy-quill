// Face login: enrolment (password, then a liveness scan) and sign-in (face in the oval, then a liveness scan),
// passkeys alone or after the face (recipe: claude-plugin/skills/caligrafy/reference/recipes-face-login.md)
const PROMPTS = { 'turn-left': 'Turn your head to your left', 'turn-right': 'Turn your head to your right', 'smile': 'Smile' };
const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

const app = Vue.createApp({
  data() {
    return {
      env: env, detector: null, cameraError: '', camera: null,
      tab: 'login', user: null, factors: [], enrolled: false,
      enrollStep: 1, newAccount: false, form: { username: '', password: '' },
      loginState: 'waiting', loginMessage: 'Starting the camera…', overlay: '', scanToken: 0,
      status: '', error: '', busy: false,
      passkeys: PasskeyClient.supported(),
      config: { headers: { 'Authorization': 'Bearer ' + apiKey, 'Accept': 'application/json', 'Content-Type': 'application/json' }, timeout: 30000 }
    };
  },
  computed: {
    signedIn() { return !!this.user; },
    cameraVisible() { return this.tab === 'login' ? !this.signedIn : this.enrollStep === 2; },
    viewportState() { return this.tab === 'login' ? this.loginState : (this.busy ? 'scanning' : ''); }
  },
  methods: {
    startCamera() {
      // markRaw: a reactive FaceDetector breaks face-api (G16); autoStart false so camera errors reach us (G42)
      this.detector = Vue.markRaw(new FaceDetector('detection', { autoStart: false }));
      this.camera = (async () => {
        await this.detector.ready;
        await this.detector.startStream({ video: { facingMode: 'user', width: 640, height: 480 }, audio: false });
      })().catch((e) => {
        this.cameraError = e.name === 'NotAllowedError' ? 'Camera access is blocked: allow it in the browser settings'
                         : e.name === 'NotFoundError' ? 'No camera found' : e.message;
        throw e;
      });
      return this.camera;
    },
    post(path, body) {
      return axios.post(this.env.home + path, body, this.config).then(({ data }) => {
        if (typeof data === 'string') throw new Error(data);         // [ERROR ...] with HTTP 200 (G48)
        return data;
      });
    },
    message(e) {
      const err = e.response?.data?.error;
      return (typeof err === 'object' ? err?.message : err) || e.message;
    },
    async run(task) {
      this.busy = true; this.error = ''; this.status = '';
      try { await task(); }
      catch (e) { this.error = this.message(e); }
      finally { this.busy = false; this.overlay = ''; }
    },
    signedInAs(data) {
      this.user = data.user.username;
      this.factors = data.factors || [];
      if (data.enrolled !== undefined) this.enrolled = data.enrolled;
    },
    async refreshUser() {
      try {
        const { data } = await axios.get(this.env.home + 'facelogin/me', this.config);
        this.signedInAs(Object.assign({ factors: ['an earlier session'] }, data));
        this.enrollStep = this.enrolled ? 3 : 2;
      } catch (e) {
        this.user = null;
      }
    },

    // switch between the two flows
    show(tab) {
      this.tab = tab; this.error = ''; this.status = '';
      this.scanToken++;                                    // stops a running scan
      if (tab === 'login' && !this.signedIn) this.scan();
    },
    stepClass(n) { return n === this.enrollStep ? 'current' : n < this.enrollStep ? 'complete' : ''; },

    // ---------- sign-in: the face in the oval starts the liveness scan, then the face is recognized ----------
    // where the face is in the frame: 'none', 'many', 'off', 'far', 'near' or 'ok'
    placement(print) {
      const media = this.detector.media;
      const w = media.videoWidth || media.width, h = media.videoHeight || media.height;
      const box = print.detection.box;
      const cx = (box.x + box.width / 2) / w, cy = (box.y + box.height / 2) / h, size = box.width / w;
      if (Math.abs(cx - 0.5) > 0.15 || Math.abs(cy - 0.5) > 0.2) return 'off';
      if (size < 0.2) return 'far';
      if (size > 0.7) return 'near';
      return 'ok';
    },
    async scan() {
      const token = ++this.scanToken;
      const live = () => token === this.scanToken && this.tab === 'login' && !this.signedIn;
      this.error = '';
      this.loginState = 'waiting'; this.loginMessage = 'Starting the camera…';
      try { await this.camera; } catch (e) { this.loginState = 'failed'; this.loginMessage = 'The camera is needed to sign in with your face'; return; }

      while (live()) {
        let print = null, where = 'none';
        try { print = await this.detector.faceprint(); where = this.placement(print); }
        catch (e) { where = e.message === 'More than one face found' ? 'many' : 'none'; }
        if (!live()) return;

        if (where !== 'ok') {
          this.loginState = 'waiting';
          this.loginMessage = { none: 'Place your face inside the oval', many: 'Only one face, please', off: 'Center your face in the oval', far: 'Move a little closer', near: 'Move back a little' }[where];
          await sleep(150);
          continue;
        }

        // the face is placed: liveness challenges chosen by the server, then the prints are matched
        this.loginState = 'scanning'; this.loginMessage = 'Follow the prompts on the video';
        try {
          const response = await this.check({});
          if (!live()) return;                                         // the user left the tab during the scan
          const data = await this.post('facelogin/login', response);
          if (data.step === 'passkey') {                              // step-up: this account also needs its passkey
            this.loginMessage = 'Face recognized: confirm with your passkey';
            await this.finishPasskey(data.options);
          } else {
            this.signedInAs(data);
          }
          this.loginState = 'success';
        } catch (e) {
          if (live()) this.scanFailed(this.message(e));
        } finally {
          this.overlay = '';
        }
        return;
      }
    },
    scanFailed(message) {
      this.loginState = 'failed';
      this.loginMessage = message === 'Face not recognized' ? 'Face not recognized. Is it enrolled?' : message;
    },
    async finishPasskey(options) {
      this.signedInAs(await this.post('passkey/login', await PasskeyClient.get(options)));
    },
    passkeyLogin() {
      this.scanToken++;
      return this.run(async () => {
        try { await this.finishPasskey(await this.post('passkey/login/options', {})); }
        catch (e) { this.scanFailed(this.message(e)); }
      });
    },

    // ---------- enrolment ----------
    account() {
      return this.run(async () => {
        const data = await this.post(this.newAccount ? 'facelogin/register' : 'facelogin/password', { username: this.form.username, password: this.form.password });
        this.form.password = '';
        this.signedInAs(data);
        this.enrollStep = this.enrolled ? 3 : 2;
      });
    },
    // server challenge -> liveness on the camera -> { nonce, liveness, prints } for the server
    async check(start) {
      await this.camera;
      const challenge = await this.post('facelogin/start', start);
      const result = await this.detector.liveness({
        challenges: challenge.challenges,
        timeout: Math.min(20000, challenge.expires_in * 1000 - 2000),
        onProgress: (p) => { this.overlay = p.challenge ? `${p.index + 1}/${p.total} · ${PROMPTS[p.challenge]}` : 'Done'; }
      });
      if (!result.passed) throw new Error('The scan did not complete (' + result.reason + '): try again');
      this.overlay = 'Checking…';
      return { nonce: challenge.nonce, liveness: { passed: true, challenges: result.challenges }, prints: result.prints.map(p => p.descriptor) };
    },
    enroll() {
      return this.run(async () => {
        this.overlay = 'Look at the camera';
        await this.post('facelogin/enroll', await this.check({ enroll: true }));
        this.enrolled = true;
        this.enrollStep = 3;
      });
    },
    passkeyRegister() {
      return this.run(async () => {
        const options = await this.post('passkey/register/options', {});
        await this.post('passkey/register', Object.assign({ name: 'This device' }, await PasskeyClient.create(options)));
        this.status = 'Passkey added: face sign-in on this account now confirms with it';
      });
    },
    async tryLogin() {
      await this.logout();
      this.show('login');
    },
    logout() {
      return this.run(async () => {
        await this.post('facelogin/logout', {});
        this.user = null; this.factors = []; this.enrolled = false;
        this.enrollStep = 1;
        if (this.tab === 'login') this.$nextTick(() => this.scan());
      });
    }
  },
  async mounted() {
    this.startCamera().catch(() => {});
    await this.refreshUser();
    this.show(this.tab);           // keeps a tab the user picked while the page was loading
  },
  beforeUnmount() { this.scanToken++; if (this.detector) this.detector.stopStream(); this.detector = null; }
});
app.mount('#app');
