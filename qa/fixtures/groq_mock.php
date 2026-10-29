<?php
// Minimal mock of the OpenAI-compatible endpoints used by Caligrafy\Agent and doctor (QA only).
// The first path segment picks the provider: /openai/v1 (Groq), /together/v1, /fireworks/inference/v1, /openrouter/api/v1,
// /ollama/v1, /ollama-empty/v1 (no model pulled), /custom/v1. Every request is appended to qa_groq_mock.log.
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body = file_get_contents('php://input');
if ($body === '' && !empty($_POST)) { $body = json_encode($_POST + ['files' => array_keys($_FILES)]); }
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
file_put_contents(sys_get_temp_dir() . '/qa_groq_mock.log', json_encode(['uri' => $uri, 'body' => $body, 'auth' => $auth]) . "\n", FILE_APPEND);
header('Content-Type: application/json');
$json = json_decode($body, true) ?: [];
$provider = explode('/', trim($uri, '/'))[0];

$models = [
    'openai' => ['openai/gpt-oss-120b', 'openai/gpt-oss-20b', 'qwen/qwen3.8-27b', 'whisper-large-v3', 'meta-llama/llama-guard-5'],
    'together' => ['openai/gpt-oss-120b', 'Qwen/Qwen3.5-9B', 'meta-models/Muse-Glimmer-30B'],
    'fireworks' => ['accounts/fireworks/models/gpt-oss-120b', 'accounts/fireworks/models/glm-5p3-flash'],
    'openrouter' => ['meta-llama/llama-3.3-70b-instruct', 'meta-llama/llama-4-maverick', 'meta-llama/llama-3.1-8b-instruct'],
    'ollama' => ['llama3.2:latest', 'llama3.2-vision:latest'],
    'ollama-empty' => [],
    'custom' => ['qa/custom-model'],
];
$public = $provider === 'openrouter' || strpos($provider, 'ollama') === 0; // no key needed for these model lists

function qa_fail($status, $message) {
    http_response_code($status);
    echo json_encode(['error' => ['message' => $message, 'code' => $status === 401 ? 'invalid_api_key' : 'qa']]);
}

if (strpos($auth, 'invalid') !== false && !(substr($uri, -7) === '/models' && $public)) {
    qa_fail(401, 'Invalid API Key');
} elseif (substr($uri, -7) === '/models' && isset($models[$provider])) {
    $list = array_map(function ($id) { return ['id' => $id, 'object' => 'model', 'active' => true]; }, $models[$provider]);
    echo json_encode($provider === 'together' ? $list : ['object' => 'list', 'data' => $list]); // Together answers a bare list
} elseif ($provider === 'openrouter' && substr($uri, -4) === '/key') {
    echo json_encode(['data' => ['label' => 'qa', 'limit' => 10, 'limit_remaining' => 7.5, 'usage' => 2.5, 'is_free_tier' => false]]);
} elseif (substr($uri, -17) === '/chat/completions') {
    if (($json['model'] ?? '') === 'qa-missing') { qa_fail(404, 'The model `qa-missing` does not exist'); return; }
    $last = end($json['messages']);
    $content = is_array($last['content']) ? 'image of ' . count($last['content']) . ' parts' : 'echo: ' . $last['content'];
    if (!empty($json['stream'])) {
        header('Content-Type: text/event-stream');
        foreach (str_split($content, 4) as $piece) {
            echo 'data: ' . json_encode(['choices' => [['index' => 0, 'delta' => ['content' => $piece]]]]) . "\n\n";
        }
        echo "data: [DONE]\n\n";
        return;
    }
    echo json_encode(['id' => 'chatcmpl-qa', 'model' => $json['model'], 'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content]]]]);
} elseif (substr($uri, -21) === '/audio/transcriptions' || substr($uri, -19) === '/audio/translations') {
    echo json_encode(['text' => 'hello from audio', 'model' => $json['model'] ?? '']);
} else {
    qa_fail(404, 'mock: unknown ' . $uri);
}
