<?php
// Minimal mock of the Coinbase Business Checkouts API (QA only). Logs each request with its Authorization header.
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$body = file_get_contents('php://input');
file_put_contents(sys_get_temp_dir() . '/qa_coinbase_mock.log', json_encode(['method' => $method, 'uri' => $uri, 'body' => $body,
    'auth' => $_SERVER['HTTP_AUTHORIZATION'] ?? '', 'idempotency' => $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? '']) . "\n", FILE_APPEND);
header('Content-Type: application/json');
$checkout = ['id' => '68f7a946db0529ea9b6d3a12', 'url' => 'https://payments.coinbase.com/payment-links/pl_qa', 'status' => 'ACTIVE', 'amount' => '10.00', 'currency' => 'USDC', 'network' => 'base'];
if (strpos($_SERVER['HTTP_AUTHORIZATION'] ?? '', 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode(['errorType' => 'unauthorized', 'errorMessage' => 'missing bearer token']);
} elseif ($method === 'POST' && $uri === '/api/v1/checkouts') {
    http_response_code(201);
    echo json_encode($checkout + ['description' => json_decode($body, true)['description'] ?? '']);
} elseif ($method === 'GET' && preg_match('#^/api/v1/checkouts/68f7a946db0529ea9b6d3a12$#', $uri)) {
    echo json_encode(array_merge($checkout, ['status' => 'COMPLETED']));
} elseif ($method === 'GET' && strpos($uri, '/api/v1/checkouts') === 0) {
    echo json_encode(['checkouts' => [$checkout], 'nextPageToken' => '']);
} elseif ($method === 'POST' && preg_match('#/deactivate$#', $uri)) {
    echo json_encode(array_merge($checkout, ['status' => 'DEACTIVATED']));
} elseif ($method === 'POST' && preg_match('#/refund$#', $uri)) {
    echo json_encode(['id' => 'abcdefabcdefabcdefabcdef', 'checkoutId' => $checkout['id'], 'status' => 'PENDING'] + json_decode($body, true));
} else {
    http_response_code(404);
    echo json_encode(['errorType' => 'not_found', 'errorMessage' => 'mock: ' . $method . ' ' . $uri]);
}
