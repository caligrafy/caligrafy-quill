<?php

use Caligrafy\Payment;
use PHPUnit\Framework\TestCase;

/**
 * Saved cards (2.34.0): a customer, a setup Checkout, the card it saved and its label, an off-session charge (with
 * the marketplace split and an idempotency key) and its refusals, removing the card. Stripe is a stand-in that
 * records what it was asked.
 */
final class PaymentSavedCardTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        foreach (['APP_ENV' => 'local', 'PAY_PUBLIC_KEY_TEST' => 'pk', 'PAY_PRIVATE_KEY_TEST' => 'sk', 'PAY_PUBLIC_KEY_PRODUCTION' => 'pk', 'PAY_PRIVATE_KEY_PRODUCTION' => 'sk'] as $name => $value) {
            defined($name) || define($name, $value);
        }
    }

    private function stripe(array $answers, array &$calls): object
    {
        $service = function (string $name) use ($answers, &$calls) {
            return new class($name, $answers, $calls) {
                private $calls;
                public function __construct(private string $name, private array $answers, &$calls) { $this->calls = &$calls; }
                public function __call($method, $args)
                {
                    $this->calls[] = [$this->name . '.' . $method, $args];
                    $answer = $this->answers[$this->name . '.' . $method] ?? null;
                    if ($answer instanceof \Throwable) {
                        throw $answer;
                    }
                    return is_callable($answer) ? $answer(...$args) : $answer;
                }
            };
        };
        $client = new \stdClass();
        foreach (['customers', 'setupIntents', 'paymentMethods', 'paymentIntents'] as $name) {
            $client->$name = $service($name);
        }
        $client->checkout = new \stdClass();
        $client->checkout->sessions = $service('sessions');
        return $client;
    }

    private static function obj(array $values): object
    {
        return json_decode(json_encode($values));
    }

    public function testACustomerIsMadeWithWhatIsGiven(): void
    {
        $calls = [];
        $pay = new Payment($this->stripe(['customers.create' => self::obj(['id' => 'cus_1'])], $calls));
        $made = $pay->createCustomer('maya@example.test', '', ['user_id' => '7']);
        $this->assertTrue($made['action_success']);
        $this->assertSame('cus_1', $made['data']->id);
        $this->assertSame([['email' => 'maya@example.test', 'metadata' => ['user_id' => '7']]], $calls[0][1]);
    }

    public function testASetupCheckoutSavesACardForTheCustomer(): void
    {
        $calls = [];
        $pay = new Payment($this->stripe(['sessions.create' => self::obj(['id' => 'cs_1', 'url' => 'https://checkout.stripe.com/c/cs_1'])], $calls));
        $made = $pay->createSetupCheckout('cus_1', 'https://a.test/done?s={CHECKOUT_SESSION_ID}', 'https://a.test/me', 'usd', 'fr', ['user_id' => '7'], ['card']);
        $this->assertTrue($made['action_success']);
        $this->assertSame('https://checkout.stripe.com/c/cs_1', $made['url']);
        $sent = $calls[0][1][0];
        $this->assertSame('setup', $sent['mode']);
        $this->assertSame('cus_1', $sent['customer']);
        $this->assertSame('usd', $sent['currency']);
        $this->assertSame('fr', $sent['locale']);
        $this->assertSame(['metadata' => ['user_id' => '7']], $sent['setup_intent_data']);
        $this->assertSame(['card'], $sent['allowed_payment_method_types']);
    }

    public function testTheSavedCardAndItsLabel(): void
    {
        $calls = [];
        $session = self::obj(['id' => 'cs_1', 'mode' => 'setup', 'status' => 'complete', 'setup_intent' => 'seti_1', 'customer' => 'cus_1']);
        $pay = new Payment($this->stripe([
            'sessions.retrieve' => $session,
            'setupIntents.retrieve' => self::obj(['status' => 'succeeded', 'payment_method' => 'pm_1']),
            'paymentMethods.retrieve' => self::obj(['type' => 'card', 'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 4, 'exp_year' => 2031]]),
        ], $calls));
        $card = $pay->savedCard('cs_1');
        $this->assertTrue($card['action_success']);
        $this->assertSame(['payment_method' => 'pm_1', 'customer' => 'cus_1', 'label' => 'Visa •••• 4242', 'brand' => 'visa', 'last4' => '4242', 'expires' => '04/31'], $card['data']);
        // a session that did not finish saved nothing
        $open = $pay->savedCard(self::obj(['mode' => 'setup', 'status' => 'open', 'setup_intent' => 'seti_1']));
        $this->assertFalse($open['action_success']);
        $paid = $pay->savedCard(self::obj(['mode' => 'payment', 'status' => 'complete']));
        $this->assertFalse($paid['action_success']);
    }

    public function testASavedCardIsChargedOffSessionWithTheSplitAndTheKey(): void
    {
        $calls = [];
        $pay = new Payment($this->stripe(['paymentIntents.create' => self::obj(['id' => 'pi_1', 'status' => 'succeeded'])], $calls));
        $paid = $pay->chargeSavedCard(5600, 'usd', 'cus_1', 'pm_1', ['claim_id' => '9'], 'Dinner',
            ['application_fee_amount' => 560, 'transfer_data' => ['destination' => 'acct_1']], 'claim-9');
        $this->assertTrue($paid['action_success']);
        $this->assertSame('pi_1', $paid['data']->id);
        [$input, $options] = $calls[0][1];
        $this->assertTrue($input['off_session']);
        $this->assertTrue($input['confirm']);
        $this->assertSame(5600, $input['amount']);
        $this->assertSame('pm_1', $input['payment_method']);
        $this->assertSame(560, $input['application_fee_amount']);
        $this->assertSame(['destination' => 'acct_1'], $input['transfer_data']);
        $this->assertSame(['idempotency_key' => 'claim-9'], $options);
    }

    public function testTheBankAskingForTheCardholderIsSaidApartFromADecline(): void
    {
        $calls = [];
        $asks = new class('Your card requires authentication') extends \Exception {
            public function getStripeCode() { return 'authentication_required'; }
            public function getError() { return (object) ['payment_intent' => (object) ['id' => 'pi_2', 'status' => 'requires_payment_method']]; }
        };
        $pay = new Payment($this->stripe(['paymentIntents.create' => $asks], $calls));
        $got = $pay->chargeSavedCard(1000, 'usd', 'cus_1', 'pm_1');
        $this->assertFalse($got['action_success']);
        $this->assertTrue($got['requires_action']);
        $this->assertSame('authentication_required', $got['code']);
        $this->assertSame('pi_2', $got['data']->id);

        $declined = new class('Your card was declined') extends \Exception {
            public function getStripeCode() { return 'card_declined'; }
            public function getError() { return null; }
        };
        $pay = new Payment($this->stripe(['paymentIntents.create' => $declined], $calls));
        $got = $pay->chargeSavedCard(1000, 'usd', 'cus_1', 'pm_1');
        $this->assertFalse($got['action_success']);
        $this->assertFalse($got['requires_action']);
        $this->assertSame('card_declined', $got['code']);
        $this->assertSame('Your card was declined', $got['error']);
    }

    public function testARemovedCardIsDetached(): void
    {
        $calls = [];
        $pay = new Payment($this->stripe(['paymentMethods.detach' => self::obj(['id' => 'pm_1', 'customer' => null])], $calls));
        $this->assertTrue($pay->removeSavedCard('pm_1')['action_success']);
        $this->assertSame('paymentMethods.detach', $calls[0][0]);
    }
}
