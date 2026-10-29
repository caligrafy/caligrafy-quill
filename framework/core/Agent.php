<?php

/**
 * Agent.php is the model for an AI Assistant that references different open source AI models such as Llama
 * @copyright 2025
 * @author Dory A.Azar
 * @version 2.0
 */

/**
 * The Agent class talks to open models through OpenAI-compatible providers:
 * Groq (default, through groq-php), Together AI, Fireworks AI, OpenRouter, a local Ollama or any custom endpoint.
 *
 * Choose the provider in .env with AGENT_PROVIDER (groq|together|fireworks|openrouter|ollama|custom).
 * Key: the provider key (GROQ_API_KEY, TOGETHER_API_KEY, FIREWORKS_API_KEY, OPENROUTER_API_KEY) or AGENT_API_KEY.
 * AGENT_API_BASE overrides the endpoint (required for custom), AGENT_MODEL and AGENT_VISION_MODEL the default models.
 * The GROQ_* settings and constants keep working as before.
 *
 * Groq keeps groq-php: its audio endpoints (multipart uploads) and behavior stay unchanged. The other providers
 * only need chat completions and the model list, sent with cURL (no extra dependency).
 * @author Dory A.Azar
 * @version 2.0
 */

 namespace Caligrafy;
 use \Exception as Exception;
 use LucianoTonet\GroqPHP\Groq;
 use LucianoTonet\GroqPHP\GroqException;

 // Current Groq models (October 2026). Override them in .env with GROQ_MODEL and GROQ_VISION_MODEL (or AGENT_MODEL and AGENT_VISION_MODEL)
 define("GROQ_DEFAULT_MODEL", defined('GROQ_MODEL') && GROQ_MODEL !== ''? GROQ_MODEL : "openai/gpt-oss-120b");
 define("GROQ_SMALL_MODEL", "openai/gpt-oss-20b");
 define("GROQ_DEFAULT_VISION_MODEL", defined('GROQ_VISION_MODEL') && GROQ_VISION_MODEL !== ''? GROQ_VISION_MODEL : "qwen/qwen3.8-27b"); // preview model: Groq has no production vision model
 define("GROQ_TRANSCRIPTION_MODEL", "whisper-large-v3");


 class Agent extends \stdClass {

    /**
     * Providers: label, default endpoint, key setting, default models (October 2026), audio support, where to get a key
     * and where the models are listed. Ollama needs no key; custom needs AGENT_API_BASE and AGENT_MODEL.
     * Serverless Llama (October 2026): OpenRouter and Ollama only. Groq retired its Llama chat models in August 2026,
     * Together and Fireworks only serve Llama on dedicated/on-demand deployments: their defaults are gpt-oss-120b.
     * Preview models: Groq qwen/qwen3.8-27b.
     */
    const PROVIDERS = [
        'groq' => ['label' => 'Groq', 'base' => 'https://api.groq.com/openai/v1', 'key' => 'GROQ_API_KEY',
                   'model' => 'openai/gpt-oss-120b', 'vision' => 'qwen/qwen3.8-27b', 'audio' => true,
                   'keys' => 'https://console.groq.com/keys', 'docs' => 'https://console.groq.com/docs/models'],
        'together' => ['label' => 'Together AI', 'base' => 'https://api.together.ai/v1', 'key' => 'TOGETHER_API_KEY',
                   'model' => 'openai/gpt-oss-120b', 'vision' => 'Qwen/Qwen3.5-9B', 'audio' => false,
                   'keys' => 'https://api.together.ai/settings/api-keys', 'docs' => 'https://docs.together.ai/docs/serverless-models'],
        'fireworks' => ['label' => 'Fireworks AI', 'base' => 'https://api.fireworks.ai/inference/v1', 'key' => 'FIREWORKS_API_KEY',
                   'model' => 'accounts/fireworks/models/gpt-oss-120b', 'vision' => 'accounts/fireworks/models/glm-5p3-flash', 'audio' => false,
                   'keys' => 'https://fireworks.ai/account/api-keys', 'docs' => 'https://fireworks.ai/models'],
        'openrouter' => ['label' => 'OpenRouter', 'base' => 'https://openrouter.ai/api/v1', 'key' => 'OPENROUTER_API_KEY',
                   'model' => 'meta-llama/llama-3.3-70b-instruct', 'vision' => 'meta-llama/llama-4-maverick', 'audio' => false,
                   'keys' => 'https://openrouter.ai/settings/keys', 'docs' => 'https://openrouter.ai/models'],
        'ollama' => ['label' => 'Ollama', 'base' => 'http://localhost:11434/v1', 'key' => null,
                   'model' => 'llama3.2', 'vision' => 'llama3.2-vision', 'audio' => false,
                   'keys' => null, 'docs' => 'https://ollama.com/library'],
        'custom' => ['label' => 'Custom endpoint', 'base' => null, 'key' => 'AGENT_API_KEY',
                   'model' => null, 'vision' => null, 'audio' => false, 'keys' => null, 'docs' => null],
    ];

    const TIMEOUT = 120;

    /**
	* @var string the provider key
	* @property string the provider key
	*/
	private $_public_key;

    /**
	* @var object the groq php object (Groq only)
	* @property object  the groq php object
	*/
	private $_groq;

    /**
     * @var array provider, base, key, keyName, model, vision (see settings())
     */
    private $_settings;

    /**
     * @var array parameters added to every request (configure())
     */
    private $_parameters = array();


    /**
     * apiKey: optional key, otherwise taken from .env
     * provider: optional provider name, otherwise AGENT_PROVIDER (default groq)
     * baseUrl: optional endpoint, otherwise AGENT_API_BASE or the provider default
     */
    public function __construct($apiKey = "", $provider = "", $baseUrl = "")
    {
        $this->_settings = self::settings(null, $provider, $baseUrl);
        $settings = $this->_settings;
        $this->_public_key = $apiKey != ""? $apiKey : $settings['key'];
        if ($settings['provider'] === 'groq') {
            // groq-php reads GROQ_API_BASE itself; AGENT_API_BASE or the argument take precedence
            $this->_groq = new Groq($this->_public_key, $settings['baseOverride']? ['baseUrl' => rtrim($settings['base'], '/') . '/'] : []);
            return $this;
        }
        if ($settings['base'] === '') {
            throw new Exception("AGENT_PROVIDER=custom needs AGENT_API_BASE (the OpenAI-compatible endpoint, e.g. https://example.com/v1)");
        }
        if ($this->_public_key === '' && $settings['keyName'] !== null && $settings['provider'] !== 'custom') { // custom: the key is optional
            throw new Exception("The " . $settings['label'] . " API key is not set: add " . $settings['keyName'] . "=<your key> (or AGENT_API_KEY) to .env"
                . (self::PROVIDERS[$settings['provider']]['keys']? ' - create one on ' . self::PROVIDERS[$settings['provider']]['keys'] : ''));
        }
        return $this;
    }

    /**
     * Resolves the provider settings from .env (or from $get, a function that returns a setting by name: used by doctor)
     * @return array provider, label, base, baseOverride, key, keyName, model, vision
     */
    public static function settings($get = null, $provider = "", $baseUrl = "")
    {
        $get = $get ?: [self::class, 'setting'];
        $provider = strtolower(trim($provider !== ""? $provider : $get('AGENT_PROVIDER'))) ?: 'groq';
        if (!isset(self::PROVIDERS[$provider])) {
            throw new Exception("Unknown AGENT_PROVIDER '$provider'. Use one of: " . implode(', ', array_keys(self::PROVIDERS)));
        }
        $p = self::PROVIDERS[$provider];
        $groq = $provider === 'groq';

        $base = $baseUrl !== ""? $baseUrl : $get('AGENT_API_BASE');
        $override = $base !== '';
        if (!$override) $base = ($groq? $get('GROQ_API_BASE') : '') ?: (string)$p['base'];

        $keyName = $p['key'];
        $key = $keyName? $get($keyName) : '';
        if ($key === '' && $keyName) $key = $get('AGENT_API_KEY');
        if ($provider === 'ollama') $key = $get('AGENT_API_KEY') ?: 'ollama'; // Ollama ignores the key

        $model = $get('AGENT_MODEL') ?: ($groq? ($get('GROQ_MODEL') ?: $p['model']) : (string)$p['model']);
        $vision = $get('AGENT_VISION_MODEL') ?: ($groq? ($get('GROQ_VISION_MODEL') ?: $p['vision']) : (string)$p['vision']);

        return ['provider' => $provider, 'label' => $p['label'], 'base' => rtrim($base, '/'), 'baseOverride' => $override,
                'key' => $key, 'keyName' => $keyName, 'model' => $model, 'vision' => $vision];
    }

    /**
     * Reads a setting: $_ENV (filled from .env), then the constant of the same name, then getenv()
     */
    public static function setting($name)
    {
        $value = $_ENV[$name] ?? (defined($name)? constant($name) : getenv($name));
        return is_string($value)? trim($value) : '';
    }

    /**
     * Provider in use (groq, together, fireworks, openrouter, ollama or custom)
     */
    public function provider()
    {
        return $this->_settings['provider'];
    }

    /**
     * Default text model of the provider (AGENT_MODEL, GROQ_MODEL for Groq, or the built-in default)
     */
    public function model()
    {
        return $this->_settings['model'];
    }

    /**
     * Default vision model of the provider (AGENT_VISION_MODEL, GROQ_VISION_MODEL for Groq, or the built-in default)
     */
    public function visionModel()
    {
        return $this->_settings['vision'];
    }

    /**
     * Set configuration of Agent
     * parameters include: temperature, max_tokens etc. They are added to every delegate() and visualize() request.
     */
    public function configure($parameters = array())
    {
        $this->_parameters = array_merge($this->_parameters, $parameters);
        return $this;
    }

    /**
     * Create a text prompt for agent
     * model: specify the model of the provider (default: model())
     * messages: array of role (system, user) and content
     * response_format: array of types  i.e ['type' => 'json_object']
     * stream: set to true if streaming response is expected. Check documentation for fetching results
     * tool_choice: set to "auto" when tools are used
     * tools: array of tool definition (OpenAI format)
     */
    public function delegate($model = "", $messages  = array(), $response_format = array(), $stream = false, $tool_choice = "", $tools = array() )
    {
        $model = $model? $model : $this->model();
        // Prepare response
        $response = array(
            'api_success' => false,
            'response' => 'We could not resolve this ask'
        );
        if (!$model) {
            $response['response'] = 'No model given and no default model for ' . $this->_settings['label'] . ': set AGENT_MODEL in .env';
            return $response;
        }

        // Prepare parameters
        $parameters = array_merge($this->_parameters, array(
            'model' => $model,
            'messages' => $messages,
            'stream' => $stream
        ));

        // Appending additional parameters when available
        $parameters = $response_format? array_merge($parameters, ['response_format' => $response_format]) : $parameters;
        $parameters = $tool_choice? array_merge($parameters, ['tool_choice' => $tool_choice]) : $parameters;
        $parameters = $tools? array_merge($parameters, ['tools' => $tools]) : $parameters;

        if (!$this->_groq) {
            return $this->complete($parameters);
        }

        try {
            $output = $this->_groq->chat()->completions()->create($parameters);

            $response = array(
                'api_success' => true,
                'response' => $output['choices'][0]['message']['content']?? ""
            );
        }
        catch (GroqException $err) {
            $response['response'] = $err->getMessage();
        }
        return $response;
    }


    /**
     * Vision
     * file: file path (or a public image URL that the provider downloads)
     * prompt: what to do with image
     * model: a vision model of the provider (default: visionModel())
     */
    public function visualize($file = "", $prompt = "Describe this image", $model = "")
    {
       $model = $model? $model : $this->visionModel();
       if (!$model) {
           return array('api_success' => false, 'response' => $this->_settings['label'] . ' has no default vision model: set AGENT_VISION_MODEL in .env or pass a vision model');
       }
       if (!$this->_groq) {
           $image = $this->image($file);
           if (isset($image['error'])) return array('api_success' => false, 'response' => $image['error']);
           $content = [['type' => 'text', 'text' => $prompt], ['type' => 'image_url', 'image_url' => ['url' => $image['url']]]];
           return $this->complete(array_merge($this->_parameters, ['model' => $model, 'messages' => [['role' => 'user', 'content' => $content]]]));
       }

       $options = array_merge(array_intersect_key($this->_parameters, ['temperature' => 1, 'max_completion_tokens' => 1]), array('model' => $model));
       try {
           $analysis = $this->_groq->vision()->analyze($file, $prompt, $options);
       } catch (GroqException $err) {
           return array('api_success' => false, 'response' => $err->getMessage());
       }

       // return output
       return array(
            'api_success' => true,
            'response' => $analysis['choices'][0]['message']['content']?? ""
        );

    }


    /**
     * Audio Transcribe (Groq only)
     * file: file path
     * model: specify model to use
     * response_format: string. 'json'
     * language: specify language of audio
     * prompt: string of an optional transcription prompt
     */
    public function transcribe($file = "", $prompt = "", $model = GROQ_TRANSCRIPTION_MODEL, $language = "en", $response_format = "json")
    {
        $this->audio('transcribe');
        $transcription = $this->_groq->audio()->transcriptions()->create([
            'file' => $file,
            'prompt' => $prompt,
            'model' => $model,
            'response_format' => $response_format,
            'language' => $language,
        ]);

        return json_encode($transcription);
    }

    /**
     * Audio Translate (Groq only)
     * file: file path
     * model: specify model to use
     * response_format: string. 'json'
     * prompt: string of an optional transcription prompt
     */
    public function translate($file = "", $prompt = "", $model = GROQ_TRANSCRIPTION_MODEL, $response_format = "json")
    {
        $this->audio('translate');
        $translation = $this->_groq->audio()->translations()->create([
            'file' => $file,
            'prompt' => $prompt,
            'model' => $model,
            'response_format' => $response_format,
        ]);

        return json_encode($translation);
    }

    /**
     * Models of the provider (for Ollama: the models pulled on the server)
     * @return array ['api_success' => true, 'response' => sorted list of model ids] or ['api_success' => false, 'response' => error]
     */
    public function models()
    {
        $base = $this->_groq? rtrim($this->_groq->baseUrl(), '/') : $this->_settings['base'];
        $r = $this->request('GET', $base . '/models');
        if (isset($r['error'])) return array('api_success' => false, 'response' => $r['error']);
        $ids = self::modelIds($r['json']);
        if ($ids === null) return array('api_success' => false, 'response' => 'Unexpected answer from GET /models');
        return array('api_success' => true, 'response' => $ids);
    }

    /**
     * Model ids of a GET /models answer: {data: [...]} (OpenAI, Groq, OpenRouter, Ollama /v1/models), a bare list (Together),
     * or {models: [{name}]} (Ollama /api/tags, Fireworks account models). Models marked inactive (Groq) are left out.
     * @return array|null sorted ids, null when the answer is not a model list
     */
    public static function modelIds($json)
    {
        if (!is_array($json)) return null;
        $list = isset($json['data']) && is_array($json['data'])? $json['data']
            : (isset($json['models']) && is_array($json['models'])? $json['models'] : (array_is_list($json)? $json : null));
        if ($list === null) return null;
        $ids = array();
        foreach ($list as $m) {
            $id = is_array($m)? ($m['id'] ?? $m['name'] ?? null) : null;
            if ($id !== null && ($m['active'] ?? true) !== false) $ids[] = (string)$id;
        }
        sort($ids);
        return array_values(array_unique($ids));
    }


    // --- OpenAI-compatible client (Together, Fireworks, OpenRouter, Ollama, custom) -------------

    private function audio($method)
    {
        if (!$this->_groq) {
            throw new Exception("$method() needs Groq: " . $this->_settings['label'] . " has no audio transcription in Caligrafy. Use new Agent(provider: 'groq') with a GROQ_API_KEY for audio");
        }
    }

    /**
     * POST /chat/completions and returns ['api_success', 'response' => content]. With stream, the chunks are read and joined.
     */
    private function complete($parameters)
    {
        $r = $this->request('POST', $this->_settings['base'] . '/chat/completions', $parameters);
        if (isset($r['error'])) return array('api_success' => false, 'response' => $r['error']);
        if (!empty($parameters['stream'])) {
            $text = '';
            foreach (explode("\n", $r['body']) as $line) {
                if (strpos($line, 'data:') !== 0) continue;
                $chunk = json_decode(trim(substr($line, 5)), true);
                $text .= $chunk['choices'][0]['delta']['content'] ?? '';
            }
            return array('api_success' => true, 'response' => $text);
        }
        if (!isset($r['json']['choices'])) return array('api_success' => false, 'response' => 'Unexpected answer from ' . $this->_settings['label']);
        return array('api_success' => true, 'response' => $r['json']['choices'][0]['message']['content'] ?? "");
    }

    /**
     * cURL request with the provider key; returns body and decoded JSON, or error (message of the provider or network hint)
     */
    private function request($method, $url, $body = null)
    {
        $headers = ['Accept: application/json', 'Authorization: Bearer ' . $this->_public_key];
        if ($body !== null) $headers[] = 'Content-Type: application/json';
        if ($this->_settings['provider'] === 'openrouter') {
            // optional attribution headers of OpenRouter
            if (defined('APP_URL') && APP_URL) $headers[] = 'HTTP-Referer: ' . APP_URL;
            if (defined('APP_NAME') && APP_NAME) $headers[] = 'X-Title: ' . APP_NAME;
        }
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => self::TIMEOUT,
        ]);
        if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if (curl_errno($curl)) {
            $error = curl_error($curl);
            $hint = '';
            if ($this->_settings['provider'] === 'ollama' && preg_match('/refused|resolve|timed out/i', $error)) {
                $hint = ' - is Ollama running (ollama serve)? From Docker use AGENT_API_BASE=http://host.docker.internal:11434/v1';
            }
            return ['error' => $this->_settings['label'] . ' could not be reached: ' . $error . $hint];
        }
        $json = is_string($response)? json_decode($response, true) : null;
        if ($status >= 400) {
            $message = is_array($json)? ($json['error']['message'] ?? (is_string($json['error'] ?? null)? $json['error'] : ($json['message'] ?? null))) : null;
            return ['error' => $this->_settings['label'] . " error $status: " . ($message ?: substr(trim(strip_tags((string)$response)), 0, 300))];
        }
        return ['status' => $status, 'json' => $json, 'body' => (string)$response];
    }

    /**
     * Image as a data URL (local file, 20 MB max) or a public http(s) URL left to the provider
     */
    private function image($file)
    {
        if (preg_match('#^https?://#i', (string)$file)) return ['url' => $file];
        if (!is_string($file) || $file === '' || !is_file($file)) return ['error' => "Image file not found: $file"];
        if (filesize($file) > 20 * 1024 * 1024) return ['error' => 'Image file exceeds the 20 MB limit'];
        $mime = mime_content_type($file) ?: '';
        if (strpos($mime, 'image/') !== 0) return ['error' => "Not an image file ($mime)"];
        return ['url' => "data:$mime;base64," . base64_encode(file_get_contents($file))];
    }

 }
