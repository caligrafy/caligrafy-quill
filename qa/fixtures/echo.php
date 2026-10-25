<?php
// Echo server used by the QA suite to inspect outgoing HTTP requests (httpRequest, OpenAI, ...).
header('Content-Type: application/json');
echo json_encode(array(
    'echo' => array(
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'body' => file_get_contents('php://input'),
        'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? ''
    )
));
