<?php
// Minimal mock of the OpenAI threads/runs endpoints used by Caligrafy\Assistant (QA only).
// Every request is appended to sys_get_temp_dir()/qa_openai_mock.log as one JSON line.
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$body = file_get_contents('php://input');
file_put_contents(sys_get_temp_dir() . '/qa_openai_mock.log', json_encode(['method' => $method, 'uri' => $uri, 'body' => $body]) . "\n", FILE_APPEND);
header('Content-Type: application/json');

if ($method === 'POST' && preg_match('#/threads/[^/]+/runs$#', $uri)) {
    echo json_encode(['id' => 'run_qa', 'status' => 'queued']);
} elseif ($method === 'GET' && preg_match('#/threads/[^/]+/runs/run_qa$#', $uri)) {
    // the run never completes when the thread id is "stuck", otherwise it completes
    echo json_encode(['id' => 'run_qa', 'status' => strpos($uri, 'stuck') !== false ? 'in_progress' : 'completed']);
} elseif ($method === 'GET' && preg_match('#/threads/[^/]+/messages$#', $uri)) {
    echo json_encode(['data' => [['id' => 'msg_qa', 'role' => 'assistant', 'content' => [['type' => 'text', 'text' => ['value' => 'Hello']]]]]]);
} else {
    echo json_encode(['id' => 'qa', 'object' => 'unknown']);
}
