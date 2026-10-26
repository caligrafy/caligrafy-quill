# Recipe: OpenAI and Groq chat apps (chat, live model list, images, vision)

> Written from the framework source (`framework/core/OpenAI.php`, `framework/core/Agent.php`, `httpRequest()` in
> `framework/helpers/corehelpers.php`) and a working proof app. Signatures: [api-reference.md](api-reference.md).
> Related gotchas: G27 (client apps read `apiKey`), G46 (`.env` keys), G47 (controller names), G48 (errors with HTTP 200).

End result: a Vue client app (`/caligrafy-quill:new-app app chat`) that talks to data routes of one controller.
The browser never sees `OPEN_AI_KEY` or `GROQ_API_KEY`: every provider call is made by PHP.

## 1. Controller and routes

Do **not** name the controller `AIController`: the initialized `application/controllers/core/AI/AIController.php`
already declares it and a second class is a fatal error on every page (G47). Use `ChatController`, `GroqController`...

```php
// application/config/routing/web.php (inside the try, before Route::run(); keep Auth::activateAPI())
Route::get('/chat', function() { return view('Client/app', ['appName' => 'chat', 'app' => 'index']); });
Route::get('/chat/models', 'ChatController@models');
Route::post('/chat/message', 'ChatController@message');
Route::post('/chat/image', 'ChatController@image');
Route::get('/chat/groq/models', 'GroqChatController@models');
Route::post('/chat/groq/message', 'GroqChatController@message');
Route::post('/chat/groq/vision', 'GroqChatController@vision');
```

`.env` (names only, never print values): `OPEN_AI_KEY`, optional `OPEN_AI_MODEL` / `OPEN_AI_IMAGE_MODEL`,
`GROQ_API_KEY`, optional `GROQ_MODEL` / `GROQ_VISION_MODEL` / `GROQ_API_BASE`. Check
`defined('OPEN_AI_KEY') && OPEN_AI_KEY !== ''` before calling and return a 503 with a readable message otherwise.

## 2. OpenAI chat with the conversation history

The client sends the whole visible conversation; the server keeps only `user`/`assistant` turns (a client must
never inject `system` messages), caps their number and length, and adds its own system prompt.

```php
// application/controllers/Chat/ChatController.php
use Caligrafy\Controller;
use Caligrafy\OpenAI;

class ChatController extends Controller {

    const MAX_MESSAGES = 20;          // last N turns sent to the model
    const MAX_CHARS = 4000;           // per message
    const MODEL_ID = '/^[A-Za-z0-9][A-Za-z0-9._:\/-]{0,99}$/';

    public function index() { return api(['ok' => true]); }

    private function fail($code, $message) { http_response_code($code); return api(['error' => $message]); }

    private function history($messages) {
        $clean = [];
        foreach (is_array($messages) ? $messages : [] as $m) {
            $role = $m['role'] ?? '';
            $content = is_string($m['content'] ?? null) ? trim($m['content']) : '';
            if (!in_array($role, ['user', 'assistant'], true) || $content === '') { continue; }
            $clean[] = ['role' => $role, 'content' => mb_substr($content, 0, self::MAX_CHARS)];
        }
        return array_slice($clean, -self::MAX_MESSAGES);
    }

    public function message() {
        if (!defined('OPEN_AI_KEY') || OPEN_AI_KEY === '') { return $this->fail(503, 'OPEN_AI_KEY is not set'); }
        $parameters = $this->request->parameters();
        $model = (string)($parameters['model'] ?? OPENAI_DEFAULT_MODEL);
        if (!preg_match(self::MODEL_ID, $model)) { return $this->fail(422, 'Invalid model'); }
        $messages = $this->history($parameters['messages'] ?? []);
        if (!$messages || end($messages)['role'] !== 'user') { return $this->fail(422, 'The last message must be a user message'); }

        $ai = new OpenAI(TEXT);
        $result = $ai->converse([
            'model' => $model,
            'messages' => array_merge([['role' => 'system', 'content' => 'You are a helpful assistant. Answer concisely.']], $messages)
        ]);

        // prepareRequest() reports api_success true for ANY non-empty body, including OpenAI error bodies
        $response = $result['response'] ?? null;
        if (empty($result['api_success']) || !is_array($response)) { return $this->fail(502, 'No answer from OpenAI (timeout or network)'); }
        if (isset($response['error'])) { return $this->fail(502, $response['error']['message'] ?? 'OpenAI error'); }
        return api(['model' => $model, 'reply' => $response[0]['message']['content'] ?? '']);
    }
}
```

Notes:
- `converse()` merges your parameters over `DEFAULT_TEXT_PARAMETERS` (only `model`). Current reasoning models reject
  `temperature`, `top_p`, `max_tokens`: send `max_completion_tokens` only if needed.
- On success `response` is the `choices` array (`[0]['message']['content']`); on an OpenAI error it is the raw body
  with `error.message` (invalid key, unknown model, quota). An empty body (timeout, DNS, TLS proxy) gives `api_success` false.
- JSON bodies: send `Content-Type: application/json`; `$this->request->parameters()` decodes them, nested arrays included.

## 3. Live model list (OpenAI)

Accounts see different models: list them from the key instead of hard-coding, and validate the id the client
sends with the regex above (a fixed allow-list breaks for other accounts).

```php
    public function models() {
        if (!defined('OPEN_AI_KEY') || OPEN_AI_KEY === '') { return $this->fail(503, 'OPEN_AI_KEY is not set'); }
        $body = httpRequest('https://api.openai.com/v1/models', 'GET', [], [
            'Accept: application/json', 'Authorization: Bearer ' . OPEN_AI_KEY
        ]);
        if (!is_array($body) || empty($body['data'])) { return $this->fail(502, $body['error']['message'] ?? 'Could not list the models'); }

        $ids = array_column($body['data'], 'id');
        $exclude = '/(instruct|audio|realtime|live|tts|transcribe|search|embedding|image|dall-e|whisper|moderation)/i';
        $chat = array_values(array_filter($ids, fn($id) => preg_match('/^(gpt-|o\d|chatgpt-)/i', $id) && !preg_match($exclude, $id)));
        $images = array_values(array_filter($ids, fn($id) => preg_match('/^gpt-image/i', $id)));
        sort($chat); sort($images);

        return api([
            'chat' => $chat,
            'images' => $images,
            // framework default when the key has it, else the first one
            'default' => in_array(OPENAI_DEFAULT_MODEL, $chat, true) ? OPENAI_DEFAULT_MODEL : ($chat[0] ?? OPENAI_DEFAULT_MODEL),
            'defaultImage' => in_array(OPENAI_DEFAULT_IMAGE_MODEL, $images, true) ? OPENAI_DEFAULT_IMAGE_MODEL : ($images[0] ?? OPENAI_DEFAULT_IMAGE_MODEL)
        ]);
    }
```
`httpRequest($url, $method, $data, $headers)` returns the decoded JSON (or null). Cache the list in the session
for a few minutes if the page calls it often.

## 4. Images (gpt-image)

```php
    public function image() {
        if (!defined('OPEN_AI_KEY') || OPEN_AI_KEY === '') { return $this->fail(503, 'OPEN_AI_KEY is not set'); }
        $p = $this->request->parameters();
        $prompt = trim((string)($p['prompt'] ?? ''));
        $model = (string)($p['model'] ?? OPENAI_DEFAULT_IMAGE_MODEL);
        $size = in_array($p['size'] ?? '', ['1024x1024', '1024x1536', '1536x1024', 'auto'], true) ? $p['size'] : '1024x1024';
        $quality = in_array($p['quality'] ?? '', ['low', 'medium', 'high', 'auto'], true) ? $p['quality'] : 'medium';
        if ($prompt === '' || mb_strlen($prompt) > 4000) { return $this->fail(422, 'Prompt required (4000 characters max)'); }
        if (!preg_match(self::MODEL_ID, $model)) { return $this->fail(422, 'Invalid model'); }

        $ai = new OpenAI(IMAGE);
        $result = $ai->delegate($prompt, '', ANALYZE, [], ['model' => $model, 'size' => $size, 'quality' => $quality]);
        $response = $result['response'] ?? null;
        if (empty($result['api_success']) || !is_array($response)) { return $this->fail(504, 'No image: the request timed out (30 s cURL limit) - try low quality'); }
        if (isset($response['error'])) { return $this->fail(502, $response['error']['message'] ?? 'OpenAI error'); }
        $b64 = $response[0]['b64_json'] ?? '';
        if ($b64 === '') { return $this->fail(502, 'No image returned'); }
        return api(['image' => 'data:image/png;base64,' . $b64]);
    }
```
- For images `delegate()` only sends `prompt` + your parameters (the command is ignored); defaults add `n: 1` and
  `output_format: png`. GPT image models return base64 (`data[0].b64_json`), never URLs.
- `httpRequest()` has a **30 s** cURL timeout (`CURLOPT_TIMEOUT => 30`): high quality or large images can exceed it
  and come back empty. Prefer `low`/`medium`, or call cURL yourself with a longer timeout for `high`.

## 5. Groq (open models)

```php
// application/controllers/Chat/GroqChatController.php
use Caligrafy\Controller;
use Caligrafy\Agent;

class GroqChatController extends Controller {
    // same index(), fail(), history() and MODEL_ID as ChatController (or a shared trait in application/)

    public function models() {
        if (!defined('GROQ_API_KEY') || GROQ_API_KEY === '') { return $this->fail(503, 'GROQ_API_KEY is not set'); }
        $base = rtrim(getenv('GROQ_API_BASE') ?: 'https://api.groq.com/openai/v1', '/');   // same base as groq-php
        $body = httpRequest($base . '/models', 'GET', [], ['Accept: application/json', 'Authorization: Bearer ' . GROQ_API_KEY]);
        if (!is_array($body) || empty($body['data'])) { return $this->fail(502, $body['error']['message'] ?? 'Could not list the models'); }
        $ids = array_column(array_filter($body['data'], fn($m) => ($m['active'] ?? true)), 'id');
        $chat = array_values(array_filter($ids, fn($id) => !preg_match('/(whisper|tts|orpheus|guard|safeguard)/i', $id)));
        sort($chat);
        return api(['chat' => $chat, 'default' => in_array(GROQ_DEFAULT_MODEL, $chat, true) ? GROQ_DEFAULT_MODEL : ($chat[0] ?? GROQ_DEFAULT_MODEL),
                    'vision' => GROQ_DEFAULT_VISION_MODEL]);
    }

    public function message() {
        $p = $this->request->parameters();
        $model = (string)($p['model'] ?? GROQ_DEFAULT_MODEL);
        if (!preg_match(self::MODEL_ID, $model)) { return $this->fail(422, 'Invalid model'); }
        $messages = $this->history($p['messages'] ?? []);
        if (!$messages || end($messages)['role'] !== 'user') { return $this->fail(422, 'The last message must be a user message'); }
        $result = (new Agent())->delegate($model, array_merge([['role' => 'system', 'content' => 'You are a helpful assistant.']], $messages));
        if (empty($result['api_success'])) { return $this->fail(502, (string)$result['response']); }   // GroqException message
        return api(['model' => $model, 'reply' => $result['response']]);
    }

    public function vision() {
        $p = $this->request->parameters();
        $prompt = trim((string)($p['prompt'] ?? '')) ?: 'Describe this image';
        // data URL from the browser: png/jpeg/webp/gif only, 4 MB max, must really be an image
        if (!preg_match('#^data:image/(png|jpeg|webp|gif);base64,([A-Za-z0-9+/=]+)$#', (string)($p['image'] ?? ''), $m)) {
            return $this->fail(422, 'Send a png, jpeg, webp or gif image');
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) > 4 * 1024 * 1024) { return $this->fail(422, 'Image too large (4 MB max)'); }
        $info = @getimagesizefromstring($bytes);
        if (!$info || !in_array($info['mime'], ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) { return $this->fail(422, 'Not a valid image'); }

        $file = tempnam(sys_get_temp_dir(), 'vis');   // groq-php reads the type with mime_content_type()
        try {
            file_put_contents($file, $bytes);
            $result = (new Agent())->visualize($file, mb_substr($prompt, 0, 2000));
        } finally {
            @unlink($file);                    // never leave uploads in /tmp
        }
        if (empty($result['api_success'])) { return $this->fail(502, (string)$result['response']); }
        return api(['reply' => $result['response']]);
    }
}
```
- `Agent::delegate($model, $messages, $response_format, $stream, $tool_choice, $tools)` returns
  `['api_success', 'response' => content string]`; Groq errors are caught and returned as the message.
- `visualize($file, $prompt, $model = GROQ_DEFAULT_VISION_MODEL)` takes a **file path** (4 MB max, base64-encoded
  by groq-php) or a URL, not a data URL. Never pass a URL from the client: groq-php fetches its headers from your
  server (SSRF). The vision
  default is a preview model: if Groq retires it, set `GROQ_VISION_MODEL`.
- `GROQ_API_BASE` (read by groq-php from `$_ENV`/`getenv()`, which `index.php` fills from `.env`) changes the endpoint, e.g. for a gateway; the model list must use the same base.

## 6. Vue client (Caligrafy black and white chat UI)

`public/chat/index.php` is scaffolded by `caligrafer app chat` and calls `loadEnvironment()`, which sets the globals
`env` and `apiKey` (G27). Layout and classes follow the new-app skill "look" bullet (G40).

```js
// public/chat/scripts/main.js
const app = Vue.createApp({
  data() {
    return {
      env: env, provider: 'openai', models: [], model: '', input: '', messages: [], busy: false, error: '',
      config: { headers: { 'Authorization': 'Bearer ' + apiKey, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                timeout: 120000 }                       // images take 20-60 s: never use a short axios timeout
    };
  },
  computed: { base() { return this.env.home + (this.provider === 'groq' ? 'chat/groq/' : 'chat/'); } },
  methods: {
    async loadModels() {
      const { data } = await axios.get(this.base + 'models', this.config);
      this.models = data.chat; this.model = data.default;
    },
    async send() {
      const text = this.input.trim(); if (!text || this.busy) return;
      this.messages.push({ role: 'user', content: text }); this.input = ''; this.busy = true; this.error = '';
      try {
        const { data } = await axios.post(this.base + 'message', { model: this.model, messages: this.messages }, this.config);
        if (typeof data === 'string') throw new Error(data);           // [ERROR ...] text comes with HTTP 200 (G48)
        this.messages.push({ role: 'assistant', content: data.reply });
      } catch (e) {
        this.error = e.response?.data?.error || e.message;
      } finally { this.busy = false; }
    }
  },
  mounted() { this.loadModels().catch(e => this.error = e.response?.data?.error || e.message); }
});
app.mount('#app');
```
```html
<header class="color0"><nav class="horizontal"><h1 class="large-text horizontal-center"><span class="semibold">Cali</span>grafy Chat</h1></nav></header>
<section class="content-section bounded-xlarge"><div class="wrapper width-max-800">
  <div class="card card-unbounded dropshadow color0">
    <select v-model="model" class="form-select"><option v-for="m in models" :key="m">{{ m }}</option></select>
    <div v-for="(m, i) in messages" :key="i" :class="m.role === 'user' ? 'text-end' : ''">
      <p class="default-text textcolor5">{{ m.role }}</p><p style="white-space: pre-wrap">{{ m.content }}</p>
    </div>
    <p class="error" v-if="error">{{ error }}</p>
    <form @submit.prevent="send()"><textarea v-model="input" class="form-control" rows="3"></textarea>
      <button class="btn btn-primary" :disabled="busy">{{ busy ? '...' : 'Send' }}</button></form>
  </div>
</div></section>
```
Render replies as text (`{{ }}` / `white-space: pre-wrap`), never `v-html` on model output. Images: bind the returned
data URL to `<img :src="image">`; vision: read the file with `FileReader.readAsDataURL()` and post it as `image`.

## Checklist
- [ ] Controller not named `AIController` (G47); `php -l` on each file.
- [ ] Roles filtered to user/assistant, number and length capped, server-side system prompt.
- [ ] `response['error']` checked for OpenAI; `api_success` false handled (timeouts).
- [ ] Model ids validated with a regex; lists fetched live.
- [ ] Vision: data URL type, 4 MB, `getimagesizefromstring`, temp file deleted in `finally`.
- [ ] axios timeout >= 120 s; client checks for `[ERROR ...]` strings (G48).
- [ ] In Docker behind a corporate TLS proxy, empty responses mean cURL rejects the certificate: see the server skill troubleshooting.
