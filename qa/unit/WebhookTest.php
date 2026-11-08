<?php

use Caligrafy\Route;
use Caligrafy\Webhook;
use PHPUnit\Framework\TestCase;

/**
 * Route::webhook (2.19.0): the Stripe signature (constructEvent with a known secret), a callable, the answers
 */
final class WebhookTest extends TestCase
{
    const SECRET = 'whsec_qa_unit_secret';

    private $log;

    protected function setUp(): void
    {
        QaRecordEvent::$seen = array();
        Route::reset();
        // what goes to the PHP error log
        $this->log = '';
        Webhook::$logger = function ($message) { $this->log .= $message . "\n"; };
    }

    protected function tearDown(): void
    {
        Route::reset();
        Webhook::$logger = null;
        putenv('QA_STRIPE_SECRET');
    }

    private function payload(array $event = array('id' => 'evt_1', 'object' => 'event', 'type' => 'checkout.session.completed')): string
    {
        return json_encode($event);
    }

    // the Stripe-Signature header Stripe would send: t=<time>,v1=HMAC-SHA256("<time>.<body>", secret)
    private function sign(string $payload, string $secret = self::SECRET, ?int $time = null): string
    {
        $time = $time ?? time();
        return "t=$time,v1=" . hash_hmac('sha256', "$time.$payload", $secret);
    }

    private function stripe(array $extra = array()): array
    {
        return $extra + array('verify' => 'stripe', 'secret' => self::SECRET);
    }

    public function testRegistersAPostRouteAndNeedsAVerification(): void
    {
        Route::webhook('/hooks/stripe', 'qa_record_event', array('verify' => 'stripe'));
        Route::webhook('/hooks/{source}', 'qa_record_event', array('verify' => fn ($raw, $headers) => true));
        $this->assertSame(array(array('method' => 'POST', 'route' => 'hooks/stripe'), array('method' => 'POST', 'route' => 'hooks/{source}')), Route::routes());
        $this->assertTrue(Route::isWebhook('/hooks/stripe'));
        $this->assertTrue(Route::isWebhook('hooks/github/'));
        $this->assertFalse(Route::isWebhook('/hooks'));
        $this->assertFalse(Route::isWebhook('/books'));
        $this->expectException(InvalidArgumentException::class);
        Route::webhook('/hooks/open', 'qa_record_event');
    }

    public function testAStripeEventWithAGoodSignatureCallsTheCapabilityAsTheSystem(): void
    {
        $payload = $this->payload();
        $this->assertSame(array(200, array('received' => true)), Webhook::process('/hooks/stripe', 'qa_record_event', $this->stripe(), $payload, array('Stripe-Signature' => $this->sign($payload))));
        $this->assertCount(1, QaRecordEvent::$seen);
        $this->assertSame('checkout.session.completed', QaRecordEvent::$seen[0]['event']['type']);
        $this->assertSame(array('webhook', '/hooks/stripe'), array(QaRecordEvent::$seen[0]['actor'], QaRecordEvent::$seen[0]['id']));
    }

    public function testTheSecretComesFromTheEnvironment(): void
    {
        putenv('QA_STRIPE_SECRET=' . self::SECRET);
        $payload = $this->payload();
        $this->assertSame(200, Webhook::process('/h', 'qa_record_event', array('verify' => 'stripe', 'secret_env' => 'QA_STRIPE_SECRET'), $payload, array('stripe-signature' => $this->sign($payload)))[0]);
        // no secret: the webhook is not set up (500, written to the log)
        putenv('QA_STRIPE_SECRET');
        $answer = Webhook::process('/h', 'qa_record_event', array('verify' => 'stripe', 'secret_env' => 'QA_STRIPE_SECRET'), $payload, array('stripe-signature' => $this->sign($payload)));
        $this->assertSame(500, $answer[0]);
        $this->assertStringContainsString('QA_STRIPE_SECRET is not set', $this->log);
        $this->assertCount(1, QaRecordEvent::$seen, 'only the first call ran the capability');
    }

    public function testBadStripeSignaturesAnswer400(): void
    {
        $payload = $this->payload();
        foreach (array(
            'another secret' => $this->sign($payload, 'whsec_other'),
            'too old' => $this->sign($payload, self::SECRET, time() - 3600),
            'no header' => '',
            'garbage' => 'nonsense',
        ) as $case => $header) {
            $answer = Webhook::process('/hooks/stripe', 'qa_record_event', $this->stripe(), $payload, $header === '' ? array() : array('Stripe-Signature' => $header));
            $this->assertSame(400, $answer[0], $case);
            $this->assertSame('invalid_signature', $answer[1]['error']['code'], $case);
        }
        // a body changed after it was signed
        $answer = Webhook::process('/hooks/stripe', 'qa_record_event', $this->stripe(), str_replace('completed', 'expired', $payload), array('Stripe-Signature' => $this->sign($payload)));
        $this->assertSame(400, $answer[0]);
        $this->assertSame(array(), QaRecordEvent::$seen, 'the capability never ran');
    }

    public function testACallableVerifiesOtherSenders(): void
    {
        $verify = function ($raw, $headers) {
            return hash_equals(hash_hmac('sha256', $raw, 'gh-secret'), (string) ($headers['x-hub-signature'] ?? ''));
        };
        $payload = $this->payload(array('type' => 'push'));
        $this->assertSame(200, Webhook::process('/hooks/github', 'qa_record_event', array('verify' => $verify), $payload, array('X-Hub-Signature' => hash_hmac('sha256', $payload, 'gh-secret')))[0]);
        $this->assertSame(400, Webhook::process('/hooks/github', 'qa_record_event', array('verify' => $verify), $payload, array('X-Hub-Signature' => 'forged'))[0]);
        $this->assertSame(400, Webhook::process('/hooks/github', 'qa_record_event', array('verify' => fn () => 1), $payload, array())[0], 'only true passes');
        $this->assertCount(1, QaRecordEvent::$seen);
    }

    public function testTheOtherAnswers(): void
    {
        $yes = array('verify' => fn () => true);
        $this->assertSame(array(400, array('error' => array('code' => 'invalid', 'message' => 'The body is not JSON'))), Webhook::process('/h', 'qa_record_event', $yes, 'not json', array()));
        $this->assertSame(409, Webhook::process('/h', 'qa_record_event', $yes, $this->payload(array('type' => 'duplicate')), array())[0]);
        $failed = Webhook::process('/h', 'qa_record_event', $yes, $this->payload(array('type' => 'boom')), array());
        $this->assertSame(500, $failed[0]);
        $this->assertStringNotContainsString('database', json_encode($failed[1]), 'the cause is logged, not answered');
        $this->assertStringContainsString('the database is down', $this->log);
        $this->assertSame(500, Webhook::process('/h', 'no_such_capability', $yes, $this->payload(), array())[0]);
        $this->assertSame(500, Webhook::process('/h', 'qa_record_event', array('verify' => 'hmac'), $this->payload(), array())[0], 'an unknown verify');
    }
}
