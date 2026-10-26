<?php

use Caligrafy\Errors;
use PHPUnit\Framework\TestCase;

final class ErrorsTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_ACCEPT']);
    }

    public function testStatusOfErrorCodes(): void
    {
        $this->assertSame(404, Errors::status(1001));
        $this->assertSame(401, Errors::status(1004));
        $this->assertSame(403, Errors::status(3000));
        $this->assertSame(500, Errors::status(9999));
        $this->assertSame(404, Errors::status('1001'));
    }

    public function testRaiseKeepsCodeAndMessage(): void
    {
        $e = Errors::raise(1001);
        $this->assertSame(1001, $e->getCode());
        $this->assertSame(Errors::get(1001), $e->getMessage());
    }

    /**
     * respond() is private and writes the body: it is called through reflection and its output captured
     * (headers are not sent from the CLI, so only the body is checked)
     */
    public function testJsonErrorShapeForApiRequests(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $body = $this->respond(404, 1001, '[ERROR 1001] Not found');
        $this->assertSame(['error' => ['code' => 1001, 'message' => 'Not found']], json_decode($body, true));
    }

    public function testPlainTextErrorForHtmlRequests(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html, application/json';
        $this->assertSame('[ERROR 1001] Not found', $this->respond(404, 1001, '[ERROR 1001] Not found'));
    }

    private function respond($status, $code, $message): string
    {
        ob_start();
        (new ReflectionMethod(Errors::class, 'respond'))->invoke(null, $status, $code, $message);
        return ob_get_clean();
    }
}
