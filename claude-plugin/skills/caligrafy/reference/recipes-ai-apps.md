# Recipe: OpenAI and open model apps (Groq, OpenRouter Llama, Ollama...: chat, live model list, images, vision)

> Written from the framework source (`framework/core/OpenAI.php`, `framework/core/Agent.php`, `framework/core/Service.php`,
> `httpRequest()` in `framework/helpers/corehelpers.php`) and the wiki pages 9.9.4 (OpenAI), 9.9.5 (Assistants), 9.9.6
> (open models) and 9.9.1 (Watson). Signatures: [api-reference.md](api-reference.md).
> Related gotchas: G46 (`.env` keys), G48 (error status codes), G53 (scopes and levels), G87 (pages call with
> `apiHeaders()`), G131 (file inputs), G137 (a capability is served to every channel).

End result: every AI call is a **capability** in `application/services/` (a `Caligrafy\Service` with `#[Capability]`),
written once and served to a page (`Route::page` + `Route::form`), REST (`Route::capabilities`), the generated JS
client (a chat page or a Vue `app`) and AI agents (`Route::mcp`). No controller: the browser never sees `OPEN_AI_KEY`,
`GROQ_API_KEY` or any provider key, every provider call is made by PHP in `handle()`, and a test replaces the provider
with a fake. Scaffold each one with `/caligrafy-quill:new-capability` or
`php caligrafer.php service Chat/ChatReply --scope ai:use --read-only` (Docker: `docker compose exec dev-box php caligrafer.php ...`).

## 1. Routes and settings

```php
// application/config/routing/web.php (inside the try, before Route::run())
Route::auth();                                    // sign-in: the AI capabilities below are not public
Route::capabilities('/capabilities');             // REST + the JS client (+ its CSRF token)
Route::mcp('/mcp');                               // agents, with a personal access token
Route::page('/chat', 'Chat/index', ['signedIn' => true]);                     // the chat page (section 6)
Route::page('/blurb', 'Chat/blurb', ['signedIn' => true]);                   // a one-shot form (section 6)
Route::form('/blurb', 'write_blurb', ['success' => '/blurb', 'back' => '/blurb', 'flash' => '{blurb}']);
```
A Vue `app` (`/caligrafy-quill:new-app app chat`) needs no route of its own: `/apps/chat` serves it, and it calls the
capabilities through `public/js/capabilities.js` (`php caligrafer.php client`, run again after every capability change).

`.env` (names only, never print values): `OPEN_AI_KEY`, optional `OPEN_AI_MODEL` / `OPEN_AI_IMAGE_MODEL` (empty =
the defaults `OPENAI_DEFAULT_MODEL` / `OPENAI_DEFAULT_IMAGE_MODEL`); `AGENT_PROVIDER` (empty = Groq) with the key of
that provider (`GROQ_API_KEY`, `TOGETHER_API_KEY`, `FIREWORKS_API_KEY`, `OPENROUTER_API_KEY`, or `AGENT_API_KEY`; none
for Ollama), optional `AGENT_MODEL` / `AGENT_VISION_MODEL` / `AGENT_API_BASE` (Groq also `GROQ_MODEL` /
`GROQ_VISION_MODEL` / `GROQ_API_BASE`). Before building, run `php caligrafer.php doctor --only openai,groq,agent`: it
confirms that the keys are accepted (a key pasted with a leading space or `sk-proj` without its dash is reported), that
the default models are allowed for the key's project (OpenAI projects can restrict models) and not retired (Groq), that
Ollama answers and has the models pulled, and lists the available ones. It cannot see billing: a `429
insufficient_quota` on the first chat means no credits.

Every capability below uses the scope `ai:use` (a token for an agent: `php caligrafer.php token create "chat agent"
--scopes ai:use --user <id>`) and `readOnly: true` (it changes none of the app's data, so `Route::page` data may use it
and agents may call it without asking). It costs money per call: never make it `public: true`.

## 2. OpenAI chat with the conversation history

The page keeps the conversation and sends it with each question; the capability keeps only `user`/`assistant` turns (a
caller must never inject `system` messages), caps their number and length, and adds its own system prompt. The history
travels as a JSON string (`valid_json_string`): the same input for a page, REST, the CLI and an agent's tool schema.

```php
<?php
// application/services/Chat/ChatReply.php
use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\OpenAI;
use Caligrafy\Service;
use Caligrafy\ServiceError;

#[Capability(
    name: 'chat_reply',
    description: 'Answers the last user message of a conversation with OpenAI. messages: a JSON array of {role: user|assistant, content}. Returns {model, reply}.',
    scope: 'ai:use',
    readOnly: true
)]
class ChatReply extends Service
{
    const MAX_MESSAGES = 20;          // last N turns sent to the model
    const MAX_CHARS = 4000;           // per message
    const MODEL_ID = '/^[A-Za-z0-9][A-Za-z0-9._:\/-]{0,99}$/';

    public $rules = array(
        'messages' => 'required|valid_json_string|max_len,100000',
        'model' => 'max_len,100',
    );
    public $filters = array('model' => 'trim');
    public $descriptions = array(
        'messages' => 'The conversation as a JSON array of {"role": "user" or "assistant", "content": "..."}; the last one is the question',
        'model' => 'An OpenAI model id from list_chat_models (empty: the default)',
    );

    // the OpenAI client (type TEXT) comes from the container: a test replaces it with $this->fake(OpenAI::class, ...)
    public function __construct(private OpenAI $openai)
    {
    }

    public static function history($json)
    {
        $clean = array();
        foreach ((array) json_decode((string) $json, true) as $m) {
            $role = is_array($m) ? ($m['role'] ?? '') : '';
            $content = is_array($m) && is_string($m['content'] ?? null) ? trim($m['content']) : '';
            if (!in_array($role, array('user', 'assistant'), true) || $content === '') { continue; }
            $clean[] = array('role' => $role, 'content' => mb_substr($content, 0, self::MAX_CHARS));
        }
        return array_slice($clean, -self::MAX_MESSAGES);
    }

    public function handle(array $input, Context $context)
    {
        // no key check here: an empty OPEN_AI_KEY (.env.example ships it empty) comes back as OpenAI's 401 refusal
        // below, and a test that fakes OpenAI runs without a key
        $model = ($input['model'] ?? '') ?: OPENAI_DEFAULT_MODEL;
        if (!preg_match(self::MODEL_ID, $model)) {
            throw ServiceError::invalid(array('model' => 'Not a model id'));
        }
        $messages = self::history($input['messages']);
        if (!$messages || end($messages)['role'] !== 'user') {
            throw ServiceError::invalid(array('messages' => 'The last message must be a user message'));
        }

        $result = $this->openai->converse(array(
            'model' => $model,
            'messages' => array_merge(array(array('role' => 'system', 'content' => 'You are a helpful assistant. Answer concisely.')), $messages),
        ));

        // prepareRequest() reports api_success true for ANY non-empty body, including OpenAI error bodies
        $response = $result['response'] ?? null;
        if (empty($result['api_success']) || !is_array($response)) {
            throw ServiceError::conflict('OpenAI did not answer (timeout or network): try again in a moment');
        }
        if (isset($response['error'])) {
            throw ServiceError::conflict('OpenAI refused: ' . ($response['error']['message'] ?? 'unknown error'));
        }
        return array('model' => $model, 'reply' => $response[0]['message']['content'] ?? '');
    }
}
```
```bash
php caligrafer.php call chat_reply messages='[{"role":"user","content":"Summarize MVC in one sentence."}]'
# {"data": {"model": "...", "reply": "..."}}; without OPEN_AI_KEY: {"error": {"code": "conflict", ...}}
```
Notes:
- A refusal is a `ServiceError`: `conflict` (409) for the provider (no key, no credits, timeout), `invalid` (422) with
  `fields` for the input. A `Route::form` page shows it in `flash('error')` / `errors('<field>')`, the JS client throws a
  `CapabilityError` (`status`, `code`, `fields`), MCP returns `isError: true`.
- `converse()` merges your parameters over `DEFAULT_TEXT_PARAMETERS` (only `model`). Current reasoning models reject
  `temperature`, `top_p`, `max_tokens`: send `max_completion_tokens` only if needed.
- On success `response` is the `choices` array (`[0]['message']['content']`); on an OpenAI error it is the raw body
  with `error.message` (invalid key, unknown model, quota). An empty body (timeout, DNS, TLS proxy) gives `api_success` false.
- One question without history (a blurb, a summary): `$this->openai->delegate($text, $instructions, CONVERSE)` (the
  command is the system prompt; `$context` = earlier messages), as `write_blurb` on wiki 9.9.4. A conversation that
  OpenAI remembers (Assistants, files, `file_search`): `Caligrafy\Assistant` with the conversation id as an input and
  an output, never the session (wiki 9.9.5, `ask_librarian`).

## 3. Live model list (OpenAI)

Accounts see different models: list them from the key instead of hard-coding, and validate the id the caller sends
with the regex above (a fixed allow-list breaks for other accounts).

```php
#[Capability(name: 'list_chat_models', description: 'Lists the OpenAI chat and image models this key can use. Returns {chat, images, default, defaultImage}.', scope: 'ai:use', readOnly: true)]
class ListChatModels extends Service
{
    public function handle(array $input, Context $context)
    {
        if (!defined('OPEN_AI_KEY') || OPEN_AI_KEY === '') { throw ServiceError::conflict('OPEN_AI_KEY is not set'); }
        $body = httpRequest('https://api.openai.com/v1/models', 'GET', [], ['Accept: application/json', 'Authorization: Bearer ' . OPEN_AI_KEY]);
        if (!is_array($body) || empty($body['data'])) { throw ServiceError::conflict($body['error']['message'] ?? 'Could not list the models'); }

        $ids = array_column($body['data'], 'id');
        $exclude = '/(instruct|audio|realtime|live|tts|transcribe|search|embedding|image|dall-e|whisper|moderation)/i';
        $chat = array_values(array_filter($ids, fn($id) => preg_match('/^(gpt-|o\d|chatgpt-)/i', $id) && !preg_match($exclude, $id)));
        $images = array_values(array_filter($ids, fn($id) => preg_match('/^gpt-image/i', $id)));
        sort($chat); sort($images);
        return [
            'chat' => $chat,
            'images' => $images,
            'default' => in_array(OPENAI_DEFAULT_MODEL, $chat, true) ? OPENAI_DEFAULT_MODEL : ($chat[0] ?? OPENAI_DEFAULT_MODEL),
            'defaultImage' => in_array(OPENAI_DEFAULT_IMAGE_MODEL, $images, true) ? OPENAI_DEFAULT_IMAGE_MODEL : ($images[0] ?? OPENAI_DEFAULT_IMAGE_MODEL),
        ];
    }
}
```
(Same `use` lines as section 2; no `$rules`: no input.) `httpRequest($url, $method, $data, $headers)` returns the
decoded JSON (or null). A page can get the list as data: `Route::page('/chat', 'Chat/index', ['signedIn' => true,
'data' => ['models' => 'list_chat_models']])`. Cache it (`Caligrafy\Cache`) for a few minutes if it is read often.

## 4. Images (gpt-image)

```php
#[Capability(name: 'draw_image', description: 'Draws an image from a prompt with an OpenAI image model. Returns {image}: a PNG as a data URL.', scope: 'ai:use', readOnly: true)]
class DrawImage extends Service
{
    public $rules = array(
        'prompt' => 'required|max_len,4000',
        'model' => 'max_len,100',
        'size' => 'contains_list,1024x1024;1024x1536;1536x1024;auto',
        'quality' => 'contains_list,low;medium;high;auto',
    );
    public $filters = array('prompt' => 'trim', 'model' => 'trim');

    public function handle(array $input, Context $context)
    {
        $model = ($input['model'] ?? '') ?: OPENAI_DEFAULT_IMAGE_MODEL;
        if (!preg_match(ChatReply::MODEL_ID, $model)) { throw ServiceError::invalid(['model' => 'Not a model id']); }

        $openai = new OpenAI(IMAGE);   // the container builds TEXT clients: an image client is created here
        $result = $openai->delegate($input['prompt'], '', ANALYZE, [], [
            'model' => $model, 'size' => ($input['size'] ?? '') ?: '1024x1024', 'quality' => ($input['quality'] ?? '') ?: 'medium',
        ]);
        $response = $result['response'] ?? null;
        if (empty($result['api_success']) || !is_array($response)) { throw ServiceError::conflict('No image: OpenAI did not answer in time (30 s cURL limit), try the low quality'); }
        if (isset($response['error'])) { throw ServiceError::conflict('OpenAI refused: ' . ($response['error']['message'] ?? 'unknown error')); }
        $b64 = $response[0]['b64_json'] ?? '';
        if ($b64 === '') { throw ServiceError::conflict('No image returned'); }
        return ['image' => 'data:image/png;base64,' . $b64];
    }
}
```
- For images `delegate()` only sends `prompt` + your parameters (the command is ignored); defaults add `n: 1` and
  `output_format: png`. GPT image models return base64 (`data[0].b64_json`), never URLs.
- `httpRequest()` has a **30 s** cURL timeout (`CURLOPT_TIMEOUT => 30`): high quality or large images can exceed it
  and come back empty. Prefer `low`/`medium`, or call cURL yourself with a longer timeout for `high`.
- An image is too large for a flash message: the page calls `drawImage(...)` with the JS client (section 6), as
  `draw_cover` on wiki 9.9.4.

## 5. Open models (Groq, OpenRouter, Together, Fireworks, Ollama)

Choose the provider in `.env`; the capabilities are the same for all of them.

| Want | `.env` | Default models |
| - | - | - |
| Free tier, fast, no Llama | `GROQ_API_KEY` (nothing else) | `openai/gpt-oss-120b`, vision `qwen/qwen3.8-27b` (preview), audio |
| Llama in the cloud, pay per token | `AGENT_PROVIDER=openrouter`, `OPENROUTER_API_KEY` | `meta-llama/llama-3.3-70b-instruct`, vision `meta-llama/llama-4-maverick` |
| Llama on the developer's machine, free | `AGENT_PROVIDER=ollama` (+ `AGENT_API_BASE=http://host.docker.internal:11434/v1` in Docker), `ollama pull llama3.2` | `llama3.2`, vision `llama3.2-vision` |
| Together / Fireworks | `AGENT_PROVIDER=together` + `TOGETHER_API_KEY` / `fireworks` + `FIREWORKS_API_KEY` | gpt-oss-120b (Llama only on dedicated/on-demand deployments: pass their model id) |

Ask the user which one when they want "Llama": Groq no longer has it (G33). Audio (`transcribe()`) is Groq only.

```php
<?php
// application/services/Chat/AgentReply.php
use Caligrafy\Agent;
use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

#[Capability(
    name: 'agent_reply',
    description: 'Answers the last user message of a conversation with the open model of AGENT_PROVIDER (Llama, gpt-oss, Qwen...). messages: a JSON array of {role, content}. Returns {provider, model, reply}.',
    scope: 'ai:use',
    readOnly: true
)]
class AgentReply extends Service
{
    public $rules = array(
        'messages' => 'required|valid_json_string|max_len,100000',
        'model' => 'max_len,100',
    );
    public $filters = array('model' => 'trim');

    public static function agent()
    {
        try {
            return new Agent();                // AGENT_PROVIDER, its key and models from .env
        } catch (Exception $e) {               // unknown provider, missing key, custom without AGENT_API_BASE
            throw ServiceError::conflict('The AI provider is not set up: ' . $e->getMessage());
        }
    }

    public function handle(array $input, Context $context)
    {
        $agent = self::agent();                // created here, not injected, so that its refusal is readable
        $model = ($input['model'] ?? '') ?: $agent->model();
        if (!preg_match(ChatReply::MODEL_ID, $model)) { throw ServiceError::invalid(['model' => 'Not a model id']); }
        $messages = ChatReply::history($input['messages']);
        if (!$messages || end($messages)['role'] !== 'user') { throw ServiceError::invalid(['messages' => 'The last message must be a user message']); }

        $result = $agent->delegate($model, array_merge([['role' => 'system', 'content' => 'You are a helpful assistant.']], $messages));
        if (empty($result['api_success'])) { throw ServiceError::conflict((string) $result['response']); }   // the provider's message
        return ['provider' => $agent->provider(), 'model' => $model, 'reply' => $result['response']];
    }
}
```
Live list of the provider's models (`list_agent_models`, readOnly, no input):
```php
    public function handle(array $input, Context $context)
    {
        $agent = AgentReply::agent();
        $list = $agent->models();                 // provider list; Ollama: the pulled models
        if (empty($list['api_success'])) { throw ServiceError::conflict((string) $list['response']); }
        $chat = array_values(array_filter($list['response'], fn($id) => !preg_match('/(whisper|tts|orpheus|guard|safeguard|embed|rerank)/i', $id)));
        $default = $agent->model();
        if ($agent->provider() === 'ollama' && !str_contains($default, ':')) { $default .= ':latest'; }  // Ollama lists name:tag
        return ['provider' => $agent->provider(), 'chat' => $chat,
                'default' => in_array($default, $chat, true) ? $default : ($chat[0] ?? $agent->model()), 'vision' => $agent->visionModel()];
    }
```
Vision: the image is a **file input** (G131), never a URL or a data URL from the caller:
```php
#[Capability(name: 'describe_image', description: 'Describes an uploaded image in two sentences. Returns {description}.', scope: 'ai:use', readOnly: true, mcp: false)]
class DescribeImage extends Service
{
    public $rules = array(
        'image' => 'required|file|extension,png;jpg;jpeg;webp;gif|max_size,4mb',   // Groq: 4 MB at most
        'prompt' => 'max_len,2000',
    );
    public $filters = array('prompt' => 'trim');

    public function handle(array $input, Context $context)
    {
        // the page or REST saved the upload in public/uploads (random name) and sent its URL: read that file by name
        $file = UPLOAD_PATH . basename((string) parse_url($input['image'], PHP_URL_PATH));
        if (!is_file($file) || !@getimagesize($file)) {
            throw ServiceError::invalid(['image' => 'Send the image as an uploaded file']);
        }
        try {
            $result = AgentReply::agent()->visualize($file, ($input['prompt'] ?? '') ?: 'Describe this image in two sentences.');
        } finally {
            deleteFile($input['image']);       // only needed for this answer: never leave uploads behind
        }
        if (empty($result['api_success'])) { throw ServiceError::conflict((string) $result['response']); }
        return ['description' => $result['response']];
    }
}
```
- `mcp: false`: agents cannot send files (they would send a URL or a path, which this capability refuses). Web forms
  (`enctype='multipart/form-data'`, `Route::form`) and REST (`curl -F image=@photo.png`) send the file.
- `Agent::delegate($model, $messages, $response_format, $stream, $tool_choice, $tools)` returns
  `['api_success', 'response' => content string]`; provider errors (401, unknown model, Ollama not running) are
  returned as the message. `new Agent()` throws on configuration errors: catch it (above) or let doctor find them.
- `visualize($file, $prompt, $model = '')` takes a **file path**. Groq: groq-php, 4 MB max; it also accepts URLs and
  fetches their headers from your server, so never pass a URL from the caller (SSRF). Other providers: the file is sent
  as a base64 data URL (20 MB max). The Groq vision default is a preview model: if Groq retires it, set
  `GROQ_VISION_MODEL` (or `AGENT_VISION_MODEL`).
- Use `$agent->model()` / `visionModel()`, not `GROQ_DEFAULT_MODEL`, so the code follows `AGENT_PROVIDER`.
- `AGENT_API_BASE` (any provider) or `GROQ_API_BASE` (Groq, read by groq-php from `$_ENV`/`getenv()`) changes the
  endpoint, e.g. for a gateway; `models()` uses the same base.
- Llama in one call, anywhere in a capability: `(new Agent())->delegate(messages: [['role' => 'user', 'content' => '...']])`;
  `new Agent(provider: 'openrouter')` whatever `AGENT_PROVIDER` is.

## 6. The pages (Caligrafy's look, [ui-guidelines.md](ui-guidelines.md))

**A one-shot answer** (`write_blurb` of wiki 9.9.4: title in, blurb out): `Route::form('/blurb', 'write_blurb',
['success' => '/blurb', 'back' => '/blurb', 'flash' => '{blurb}'])` and a Pug form with `=csrf()`, `old()`,
`errors()`, `flash('success')` / `flash('error')` (G129). No JavaScript.

**A chat** keeps its conversation in the page and calls the capability with the generated client
(`php caligrafer.php client` writes `public/js/capabilities.js`; it sends the session cookie and the CSRF token, no key):
```pug
//- application/views/Chat/index.pug (Route::page('/chat', 'Chat/index', ['signedIn' => true]))
extends ../default/master/site

block content
  section.cq-hero.cq-hero--page
    h1.cq-display Chat

  section.cq-band.cq-band--tight
    .cq-inner.cq-inner--narrow
      ul#messages.cq-rows
      p#error.cq-alert.cq-alert--error(role='alert', hidden)
      form#chat(method='POST')
        .cq-field
          label(for='question') Your message
          textarea#question.form-control(name='question', rows='3', required)
        button.btn.btn-primary(type='submit') Send

  script(type='module', src="{$home}public/js/chat.js")
```
```js
// public/js/chat.js
import { chatReply, CapabilityError } from './capabilities.js'   // agentReply for the open models

const form = document.getElementById('chat')
const list = document.getElementById('messages')
const error = document.getElementById('error')
const button = form.querySelector('button')
const messages = []                                      // the conversation lives in the page

function show (role, content) {
  const row = document.createElement('li')
  row.className = 'cq-row'
  row.style.whiteSpace = 'pre-wrap'
  row.textContent = (role === 'user' ? 'You: ' : 'Assistant: ') + content   // text, never HTML: it comes from a model
  list.append(row)
}

form.addEventListener('submit', async (event) => {
  event.preventDefault()
  const text = form.elements.question.value.trim()
  if (!text || button.disabled) return
  messages.push({ role: 'user', content: text }); show('user', text)
  error.hidden = true; button.disabled = true; button.textContent = '...'
  try {
    const answer = await chatReply({ messages: JSON.stringify(messages) })
    messages.push({ role: 'assistant', content: answer.reply }); show('assistant', answer.reply)
    form.reset()
  } catch (e) {
    messages.pop()                                       // the question was not answered: it can be sent again
    error.textContent = e instanceof CapabilityError ? (Object.values(e.fields || {}).join(' ') || e.message) : 'Something went wrong.'
    error.hidden = false
  } finally {
    button.disabled = false; button.textContent = 'Send'
  }
})
```
In a Vue `app` (`public/chat/scripts/main.js`): `this.api = await import(env.home + 'public/js/capabilities.js')`
in `mounted()`, then `await this.api.chatReply({ model: this.model, messages: JSON.stringify(this.messages) })` and
`await this.api.listChatModels()` for the model `select` (new-app skill, step 6). In a Vite SPA:
`import { chatReply } from '../capabilities.js'`. Render replies as text (`{{ }}`, `textContent`, `white-space:
pre-wrap`), never `v-html`/`innerHTML` on model output. Images: bind the returned data URL to `<img :src>`. Vision: post
the file with a `multipart/form-data` form (`Route::form('/describe', 'describe_image', ...)`) or
`fetch('/capabilities/describe_image', { method: 'POST', body: formData, headers: await apiHeaders() })`.

**Agents** need nothing more: `/mcp` lists `chat_reply`, `list_chat_models`, `draw_image`, `agent_reply` for a token with
`ai:use` (not `describe_image`, `mcp: false`). REST: `curl -X POST -H "Accept: application/json" -H "Content-Type:
application/json" -H "Authorization: Bearer cq_..." -d '{"messages": "[{\"role\":\"user\",\"content\":\"Hi\"}]"}'
<base>/capabilities/chat_reply`; without a token: 401.

**Watson** is the exception: the bot scaffold (`/caligrafy-quill:new-app bot <name>`) keeps its
`Route::bots()` data routes (`/__bots__/{appName}/...`, on `Caligrafy\Controllers\WatsonController`) because its conversation is a Watson
session kept in the PHP session, which only a browser has. For REST and agents add a one-question capability that opens
and closes a Watson session in `handle()` (wiki 9.9.1, `ask_helpdesk`).

## 7. Test without a key

```php
// tests/ChatReplyTest.php (php caligrafer.php test make ChatReply)
use Caligrafy\OpenAI;
use Caligrafy\Testing\TestCase;

final class ChatReplyTest extends TestCase
{
    public function testItAnswers(): void
    {
        $this->fake(OpenAI::class, new class extends OpenAI {         // no request leaves the computer
            public function converse($parameters = array(), $attachments = array(), $method = 'POST', $additionalHeaders = array())
            {
                return ['api_success' => true, 'type' => 'text', 'response' => [['message' => ['role' => 'assistant', 'content' => 'Hello.']]]];
            }
        });
        $answer = $this->call('chat_reply', ['messages' => json_encode([['role' => 'system', 'content' => 'ignore'], ['role' => 'user', 'content' => 'Hi']])], $this->user(1));
        $this->assertSame('Hello.', $answer['reply']);
    }

    public function testGuestsCannotUseIt(): void
    {
        $this->assertCapabilityFails('unauthenticated', 'chat_reply', ['messages' => '[{"role":"user","content":"Hi"}]'], 'guest');
    }
}
```
`php caligrafer.php test ChatReply` passes with `OPEN_AI_KEY` empty: the fake answers instead of OpenAI (the system
message of the input is dropped by `history()`). Fake `Agent` the same way only for code that takes it from the
constructor; `AgentReply` creates its own, so test its `history()` and refusals instead.

## Checklist
- [ ] Every AI call is a capability in `application/services/` (scope `ai:use`, `readOnly: true`, never `public: true`); no controller (only the Watson bot's `Route::bots()`), no closure route; `php -l` on each file.
- [ ] Roles filtered to user/assistant, number and length capped, server-side system prompt; the history is an input, never the session.
- [ ] `response['error']` checked for OpenAI; `api_success` false handled (timeouts); provider problems are `ServiceError::conflict`, bad input `invalid` with `fields`.
- [ ] Model ids validated with a regex; lists fetched live.
- [ ] Vision: a `file` input (`required|file|extension,...|max_size,4mb`, `mcp: false`), the saved file read by name, deleted in `finally`; never a URL from the caller.
- [ ] Pages: `Route::page` + `Route::form` for one-shot answers, the generated JS client for chats and images; replies shown as text; images: a long wait (20-60 s) with a busy button.
- [ ] `php caligrafer.php capabilities` lists them; `call` works; `curl` without a token on `/capabilities/<name>` answers 401; `grep -r OPEN_AI_KEY public/` finds nothing; the tests pass with no real key.
- [ ] In Docker behind a corporate TLS proxy, empty responses mean cURL rejects the certificate: see the server skill troubleshooting.
