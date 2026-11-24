<?php
// A push service for the notifications tests (php -S 127.0.0.1:8770 qa/fixtures/push_mock.php): /ok/* accepts (201),
// /gone/* answers 410 (the browser unsubscribed), /fail/* 500. Each accepted push is appended to /tmp/qa-pushes.log with
// its headers, so the tests check the VAPID authorization, the encryption and the options.
// The phone apps (2.27.0): Apple (APNS_URL) at /3/device/<token>, Firebase (FCM_URL) at /v1/projects/<id>/messages:send
// and its token exchange (the service account's token_uri) at /token. A device token starting with "dead" is gone.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$headers = function_exists('getallheaders') ? getallheaders() : array();
$body = file_get_contents('php://input');
$log = function ($entry) { file_put_contents('/tmp/qa-pushes.log', json_encode($entry) . "\n", FILE_APPEND); };
if (strpos($path, '/ok/') === 0) {
    $log(array('path' => $path, 'headers' => array_change_key_case($headers), 'bytes' => strlen($body)));
    http_response_code(201);
} elseif (strpos($path, '/gone/') === 0) {
    http_response_code(410);
} elseif (preg_match('#^/3/device/([0-9a-f]+)$#', $path, $m)) {
    header('Content-Type: application/json');
    if (strpos($m[1], 'dead') === 0) {
        http_response_code(410);
        echo '{"reason":"Unregistered"}';
        return;
    }
    $log(array('path' => $path, 'headers' => array_change_key_case($headers), 'body' => json_decode($body, true)));
    http_response_code(200);
} elseif ($path === '/token') {
    parse_str($body, $form);
    header('Content-Type: application/json');
    $parts = explode('.', (string) ($form['assertion'] ?? ''));
    echo json_encode(count($parts) === 3 ? array('access_token' => 'qa-google-token', 'expires_in' => 3599) : array('error' => 'invalid_grant'));
    if (count($parts) !== 3) http_response_code(400);
} elseif (preg_match('#^/v1/projects/([^/]+)/messages:send$#', $path)) {
    header('Content-Type: application/json');
    $message = json_decode($body, true)['message'] ?? array();
    if (strpos((string) ($message['token'] ?? ''), 'dead') === 0) {
        http_response_code(404);
        echo '{"error":{"code":404,"message":"Requested entity was not found.","details":[{"@type":"type.googleapis.com/google.firebase.fcm.v1.FcmError","errorCode":"UNREGISTERED"}]}}';
        return;
    }
    $log(array('path' => $path, 'headers' => array_change_key_case($headers), 'body' => json_decode($body, true)));
    echo '{"name":"projects/qa/messages/1"}';
} else {
    http_response_code(500);
}
