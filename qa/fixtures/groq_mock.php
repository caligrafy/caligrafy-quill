<?php
// Minimal mock of the Groq OpenAI-compatible endpoints used by Caligrafy\Agent (QA only).
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$body = file_get_contents('php://input');
if ($body === '' && !empty($_POST)) { $body = json_encode($_POST + ['files' => array_keys($_FILES)]); }
file_put_contents(sys_get_temp_dir() . '/qa_groq_mock.log', json_encode(['uri' => $uri, 'body' => $body]) . "\n", FILE_APPEND);
header('Content-Type: application/json');
$json = json_decode($body, true) ?: [];
if (substr($uri, -17) === '/chat/completions') {
    $last = end($json['messages']);
    $content = is_array($last['content']) ? 'image of ' . count($last['content']) . ' parts' : 'echo: ' . $last['content'];
    echo json_encode(['id' => 'chatcmpl-qa', 'model' => $json['model'], 'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content]]]]);
} elseif (substr($uri, -21) === '/audio/transcriptions' || substr($uri, -19) === '/audio/translations') {
    echo json_encode(['text' => 'hello from audio', 'model' => $json['model'] ?? '']);
} else {
    http_response_code(404);
    echo json_encode(['error' => ['message' => 'mock: unknown ' . $uri]]);
}
