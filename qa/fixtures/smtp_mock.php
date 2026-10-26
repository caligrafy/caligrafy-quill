<?php
// Minimal SMTP greeter (QA only): answers the banner, EHLO and QUIT so that 'caligrafer doctor' can check MAIL_HOST.
$server = stream_socket_server('tcp://127.0.0.1:' . ($argv[1] ?? 2525), $errno, $error);
while ($client = @stream_socket_accept($server, -1)) {
    fwrite($client, "220 qa-smtp ESMTP\r\n");
    while (($line = fgets($client)) !== false) {
        $command = strtoupper(substr(trim($line), 0, 4));
        if ($command === 'EHLO') fwrite($client, "250-qa-smtp\r\n250 AUTH PLAIN LOGIN\r\n");
        elseif ($command === 'QUIT') { fwrite($client, "221 bye\r\n"); break; }
        else fwrite($client, "502 not implemented\r\n");
    }
    fclose($client);
}
