<?php
// Minimal mock of the watsonx Assistant v2 API (QA only).
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$body = file_get_contents('php://input');
file_put_contents(sys_get_temp_dir() . '/qa_watson_mock.log', json_encode(['method' => $method, 'uri' => $uri, 'body' => $body,
    'auth' => $_SERVER['HTTP_AUTHORIZATION'] ?? (isset($_SERVER['PHP_AUTH_USER']) ? 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW']) : '')]) . "\n", FILE_APPEND);
header('Content-Type: application/json');
if ($method === 'POST' && preg_match('#/sessions\?version=#', $uri)) {
    echo json_encode(['session_id' => 'sess-qa']);
} elseif ($method === 'POST' && preg_match('#/sessions/sess-qa/message\?version=#', $uri)) {
    $text = json_decode($body, true)['input']['text'] ?? '';
    echo json_encode(['output' => ['generic' => [['response_type' => 'text', 'text' => 'You said ' . $text]]],
                      'context' => ['skills' => ['main skill' => ['user_defined' => ['bot_name' => 'Cali']]]]]);
} elseif ($method === 'DELETE') {
    echo '{}';
} else {
    http_response_code(404);
    echo json_encode(['error' => 'mock: ' . $method . ' ' . $uri]);
}
