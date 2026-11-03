<?php
// A push service for the notifications tests (php -S 127.0.0.1:8770 qa/fixtures/push_mock.php): /ok/* accepts (201),
// /gone/* answers 410 (the browser unsubscribed), /fail/* 500. Each accepted push is appended to /tmp/qa-pushes.log with
// its headers, so the tests check the VAPID authorization, the encryption and the options
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$headers = function_exists('getallheaders') ? getallheaders() : array();
if (strpos($path, '/ok/') === 0) {
    file_put_contents('/tmp/qa-pushes.log', json_encode(array('path' => $path, 'headers' => array_change_key_case($headers), 'bytes' => strlen(file_get_contents('php://input')))) . "\n", FILE_APPEND);
    http_response_code(201);
} elseif (strpos($path, '/gone/') === 0) {
    http_response_code(410);
} else {
    http_response_code(500);
}
