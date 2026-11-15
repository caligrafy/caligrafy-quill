<?php
/**
 * WorkermanServer.php
 * The async server on Workerman (2.26.0, experimental): php caligrafer.php async start
 * @copyright 2026
 *
*/

namespace Caligrafy\Async;

use Workerman\Connection\AsyncTcpConnection;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;

/**
 * Class WorkermanServer
 * Workerman (pure PHP, composer require workerman/workerman: php caligrafer.php add async) keeps a few processes
 * that each hold the application in memory (Kernel::boot) and many open connections, answering one request at a time
 * per process. In front of the web server (ASYNC=on in the production image: docker/production/start.sh), it answers
 * the capabilities channel itself (Kernel::handles) and passes every other request, unchanged, to Apache on
 * 127.0.0.1:<backend port>, adding X-Forwarded-For; without a backend (development), others get a 404.
 */
class WorkermanServer
{
    /**
     * Runs the server (this call does not return)
     * @param string $listen e.g. http://0.0.0.0:80
     * @param int $workers processes (each holds one copy of the application)
     * @param string|null $backend the web server for everything else, e.g. 127.0.0.1:8080
     */
    public static function run($listen, $workers, $backend = null, $prefix = '/capabilities')
    {
        if (!class_exists(Worker::class)) {
            throw new \RuntimeException('The async server needs Workerman: php caligrafer.php add async (composer require workerman/workerman)');
        }
        if (!extension_loaded('pcntl') || !extension_loaded('posix')) {
            throw new \RuntimeException('The async server needs the PHP extensions pcntl and posix (the production image has them; '
                . 'elsewhere: docker-php-ext-install pcntl, or your system\'s php-pcntl package)');
        }
        // uploads that go to the web server: as large as PHP accepts there
        TcpConnection::$defaultMaxPackageSize = 64 * 1024 * 1024;
        Worker::$pidFile = sys_get_temp_dir() . '/caligrafy-async-' . substr(md5(getcwd()), 0, 12) . '.pid';
        Worker::$logFile = 'php://stderr';
        $worker = new Worker($listen);
        $worker->name = 'caligrafy-async';
        $worker->count = max(1, (int) $workers);
        $worker->reusePort = true;
        // started as root to listen on port 80, the processes then run as the web server's user (their files)
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            $worker->user = 'www-data';
        }
        $worker->onWorkerStart = function () use ($prefix) {
            Kernel::boot($prefix);
        };
        $worker->onMessage = function (TcpConnection $connection, Request $request) use ($backend) {
            $path = $request->path();
            if (Kernel::handles($request->method(), $path, $request->cookie() ?? array())) {
                $answer = Kernel::handle($request->method(), $path, array_change_key_case($request->header() ?? array(), CASE_LOWER),
                    $request->rawBody(), $request->get() ?? array());
                $connection->send(new Response($answer['status'], $answer['headers'], $answer['body']));
                return;
            }
            if ($backend === null) {
                $connection->send(new Response(404, array('Content-Type' => 'application/json'),
                    json_encode(array('error' => array('code' => 'not_found', 'message' => 'Served by the web server')))));
                return;
            }
            self::pass($connection, $request, $backend);
        };
        // Workerman reads its own command (start, stop...) from the command line: ours is caligrafer's
        $GLOBALS['argv'] = array($GLOBALS['argv'][0] ?? 'caligrafer.php', 'start');
        $GLOBALS['argc'] = 2;
        Worker::runAll();
    }

    /**
     * Passes a request to the web server as it came (plus X-Forwarded-For) and its answer back as it comes
     */
    private static function pass(TcpConnection $client, Request $request, $backend)
    {
        $raw = $request->rawBuffer();
        $end = strpos($raw, "\r\n");
        $forwarded = 'X-Forwarded-For: ' . $client->getRemoteIp() . "\r\n" . 'Connection: close' . "\r\n";
        // the request line, our two headers, then its own headers (without its Connection header) and body
        $head = substr($raw, 0, $end + 2);
        $rest = preg_replace('/^Connection:[^\r\n]*\r\n/mi', '', substr($raw, $end + 2), 1);
        $remote = new AsyncTcpConnection('tcp://' . $backend);
        $remote->onConnect = function ($remote) use ($head, $forwarded, $rest) {
            $remote->send($head . $forwarded . $rest);
        };
        $remote->onMessage = function ($remote, $data) use ($client) {
            $client->send($data, true);   // raw: the web server's own status line, headers and body
        };
        $remote->onError = function ($remote, $code, $message) use ($client) {
            $client->send("HTTP/1.1 502 Bad Gateway\r\nContent-Type: text/plain\r\nContent-Length: 31\r\nConnection: close\r\n\r\nThe web server is not reachable", true);
            $client->close();
        };
        // the web server answers with Connection: close (keep-alive is off there): so does the visitor's connection
        $remote->onClose = function () use ($client) {
            $client->close();
        };
        // a slow visitor slows the web server's sending down instead of filling this process's memory
        $client->onBufferFull = function () use ($remote) { $remote->pauseRecv(); };
        $client->onBufferDrain = function () use ($remote) { $remote->resumeRecv(); };
        $client->onClose = function () use ($remote) { $remote->close(); };
        $remote->connect();
    }
}
