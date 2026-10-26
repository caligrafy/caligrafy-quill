<?php
// Minimal mock of the OpenAI Responses, Conversations, Files and Vector Stores endpoints (QA only).
// Every request is appended to sys_get_temp_dir()/qa_openai_mock.log as one JSON line.
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$body = file_get_contents('php://input');
if ($body === '' && !empty($_FILES)) { $body = json_encode(['multipart' => array_keys($_FILES), 'fields' => $_POST]); }
file_put_contents(sys_get_temp_dir() . '/qa_openai_mock.log', json_encode(['method' => $method, 'uri' => $uri, 'body' => $body]) . "\n", FILE_APPEND);
header('Content-Type: application/json');
$json = json_decode($body, true) ?: [];

function qa_message($text) {
    return ['id' => 'msg_qa', 'type' => 'message', 'role' => 'assistant', 'status' => 'completed',
            'content' => [['type' => 'output_text', 'text' => $text, 'annotations' => []]]];
}

$token = trim(substr($_SERVER['HTTP_AUTHORIZATION'] ?? '', 7));
if ($method === 'GET' && $uri === '/v1/models') {
    // doctor: 401 for a malformed or 'invalid' key, otherwise the models of a restricted project
    if (!preg_match('/^sk-(proj-|svcacct-)?[A-Za-z0-9_-]{8,}$/', $token) || preg_match('/^sk-proj(?!-)/', $token) || strpos($token, 'invalid') !== false) {
        http_response_code(401);
        echo json_encode(['error' => ['message' => 'Incorrect API key provided: ' . $token, 'code' => 'invalid_api_key']]);
    } else {
        echo json_encode(['object' => 'list', 'data' => array_map(function ($id) { return ['id' => $id, 'object' => 'model']; },
            ['gpt-6-luna', 'gpt-6-astra', 'gpt-6.1-sol', 'gpt-image-2', 'text-embedding-3-small', 'whisper-1'])]);
    }
} elseif ($method === 'POST' && $uri === '/v1/conversations') {
    echo json_encode(['id' => 'conv_qa', 'object' => 'conversation']);
} elseif ($method === 'DELETE' && preg_match('#^/v1/conversations/[^/]+$#', $uri)) {
    echo json_encode(['id' => basename($uri), 'object' => 'conversation.deleted', 'deleted' => true]);
} elseif ($method === 'GET' && preg_match('#^/v1/conversations/[^/]+/items$#', $uri)) {
    echo json_encode(['object' => 'list', 'data' => [qa_message('Hello')], 'has_more' => false]);
} elseif ($method === 'POST' && $uri === '/v1/responses') {
    $last = end($json['input']);
    $text = 'You said: ' . ($last['content'] ?? '');
    if (($json['background'] ?? false) || ($json['metadata']['qa'] ?? '') === 'background') {
        echo json_encode(['id' => 'resp_bg', 'object' => 'response', 'status' => 'in_progress', 'output' => []]);
    } elseif (($json['metadata']['qa'] ?? '') === 'stuck') {
        echo json_encode(['id' => 'resp_stuck', 'object' => 'response', 'status' => 'queued', 'output' => []]);
    } else {
        echo json_encode(['id' => 'resp_qa', 'object' => 'response', 'status' => 'completed', 'output' => [
            ['id' => 'rs_qa', 'type' => 'reasoning', 'summary' => []], qa_message($text)]]);
    }
} elseif ($method === 'GET' && $uri === '/v1/responses/resp_bg') {
    echo json_encode(['id' => 'resp_bg', 'object' => 'response', 'status' => 'completed', 'output' => [qa_message('Done in the background')]]);
} elseif ($method === 'GET' && $uri === '/v1/responses/resp_stuck') {
    echo json_encode(['id' => 'resp_stuck', 'object' => 'response', 'status' => 'in_progress', 'output' => []]);
} elseif ($method === 'POST' && $uri === '/v1/files') {
    echo json_encode(['id' => 'file-qa' . (isset($_FILES['file']['name']) ? strlen($_FILES['file']['name']) : 0), 'object' => 'file', 'purpose' => $_POST['purpose'] ?? '']);
} elseif ($method === 'POST' && $uri === '/v1/vector_stores') {
    echo json_encode(['id' => 'vs_qa', 'object' => 'vector_store', 'status' => 'completed']);
} elseif ($method === 'POST' && preg_match('#^/v1/vector_stores/[^/]+/files$#', $uri)) {
    echo json_encode(['id' => $json['file_id'] ?? '', 'object' => 'vector_store.file', 'status' => 'completed']);
} elseif ($method === 'DELETE' && preg_match('#^/v1/vector_stores/[^/]+(/files/[^/]+)?$#', $uri)) {
    echo json_encode(['id' => basename($uri), 'deleted' => true]);
} elseif ($method === 'DELETE' && preg_match('#^/v1/files/[^/]+$#', $uri)) {
    echo json_encode(['id' => basename($uri), 'object' => 'file', 'deleted' => true]);
} elseif ($method === 'POST' && $uri === '/v1/images/generations') {
    echo json_encode(['created' => 1, 'data' => [['b64_json' => base64_encode('QA-PNG')]], 'output_format' => 'png', 'request' => $json]);
} else {
    http_response_code(404);
    echo json_encode(['error' => ['message' => 'mock: unknown endpoint ' . $method . ' ' . $uri]]);
}
