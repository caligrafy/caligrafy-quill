<?php
// Runs Caligrafy\Watson against the mock in a separate process (WATSON_* constants must exist before the bootstrap).
// usage: php qa/tests/watson_probe.php <environment id or ''>
define('WATSON_URL', 'http://127.0.0.1:8769/instances/qa-instance');
define('WATSON_API_KEY', 'watson-key');
define('WATSON_ENVIRONMENT_ID', $argv[1] ?? '');
require __DIR__ . '/bootstrap.php';
$w = new Caligrafy\Watson('assistant-qa');
$w->communicate('hello', ['bot_name' => 'Cali']);
echo json_encode(['session' => $w->getSessionId(), 'response' => $w->getResponse(), 'variables' => $w->getSkillVariables()]);
$w->closeSession();
