<?php

use Caligrafy\Passkey;
use Caligrafy\PasskeyException;
use PHPUnit\Framework\TestCase;

/**
 * Passkey registration and authentication against a software authenticator (EC P-256, "none" attestation).
 * The credentials live in memory: the 4 storage methods are overridden
 */
final class PasskeyTest extends TestCase
{
    const ORIGIN = 'https://quill.test';

    private function passkey(): Passkey
    {
        return new class(null, 'quill.test', self::ORIGIN) extends Passkey {
            public $rows = [];
            public function credentials($userId) { return array_values(array_filter($this->rows, fn($r) => $r['user_id'] === (string)$userId)); }
            public function findCredential($id) { return $this->rows[$id] ?? null; }
            protected function saveCredential($c) { $this->rows[$c['credential_id']] = $c; }
            protected function touchCredential($id, $count) { $this->rows[$id]['sign_count'] = $count; }
        };
    }

    private function registered(): array
    {
        $passkey = $this->passkey();
        $authenticator = new QaSoftAuthenticator();
        $passkey->register($authenticator->create($passkey->registrationOptions(42, 'alice'), self::ORIGIN), 42, 'Laptop');
        return [$passkey, $authenticator];
    }

    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testRegistrationOptions(): void
    {
        $options = $this->passkey()->registrationOptions(42, 'alice')['publicKey'];
        $this->assertSame('quill.test', $options['rp']['id']);
        $this->assertSame('42', QaSoftAuthenticator::unb64u($options['user']['id']));
        $this->assertSame('required', $options['authenticatorSelection']['userVerification']);
        $this->assertSame('required', $options['authenticatorSelection']['residentKey']);
        $this->assertGreaterThanOrEqual(16, strlen(QaSoftAuthenticator::unb64u($options['challenge'])));
        $this->assertContains(-7, array_column($options['pubKeyCredParams'], 'alg'));
    }

    public function testRegisterAndAuthenticate(): void
    {
        [$passkey, $authenticator] = $this->registered();
        $row = array_values($passkey->rows)[0];
        $this->assertSame('42', $row['user_id']);
        $this->assertSame('Laptop', $row['name']);
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $row['public_key']);

        $options = $passkey->authenticationOptions(42);
        $this->assertSame($row['credential_id'], $options['publicKey']['allowCredentials'][0]['id']);
        $this->assertSame('42', $passkey->authenticate($authenticator->get($options, self::ORIGIN)));
        $this->assertSame(1, $passkey->rows[$row['credential_id']]['sign_count']);
        // discoverable: no user given
        $this->assertSame('42', $passkey->authenticate($authenticator->get($passkey->authenticationOptions(), self::ORIGIN)));
    }

    public function testExcludesRegisteredCredentials(): void
    {
        [$passkey, $authenticator] = $this->registered();
        $exclude = $passkey->registrationOptions(42, 'alice')['publicKey']['excludeCredentials'];
        $this->assertSame(QaSoftAuthenticator::b64u($authenticator->credentialId), $exclude[0]['id']);
    }

    public function testReplayedAssertionIsRefused(): void
    {
        [$passkey, $authenticator] = $this->registered();
        $assertion = $authenticator->get($passkey->authenticationOptions(42), self::ORIGIN);
        $passkey->authenticate($assertion);
        $passkey->authenticationOptions(42); // a new challenge: the old signature does not cover it
        $this->expectException(PasskeyException::class);
        $this->expectExceptionMessage('invalid challenge');
        $passkey->authenticate($assertion);
    }

    public function testEveryCeremonyHasItsOwnChallenge(): void
    {
        $passkey = $this->passkey();
        $this->assertNotSame($passkey->authenticationOptions()['publicKey']['challenge'], $passkey->authenticationOptions()['publicKey']['challenge']);
    }

    public function testChallengeIsSingleUse(): void
    {
        [$passkey, $authenticator] = $this->registered();
        $assertion = $authenticator->get($passkey->authenticationOptions(42), self::ORIGIN);
        $passkey->authenticate($assertion);
        $this->expectExceptionMessage('No passkey request in progress');
        $passkey->authenticate($assertion);
    }

    public function testWrongOriginIsRefused(): void
    {
        [$passkey, $authenticator] = $this->registered();
        $this->expectExceptionMessage('wrong origin');
        $passkey->authenticate($authenticator->get($passkey->authenticationOptions(42), 'https://quill.test.evil.example'));
    }

    public function testBadSignatureIsRefused(): void
    {
        [$passkey, $authenticator] = $this->registered();
        $assertion = $authenticator->get($passkey->authenticationOptions(42), self::ORIGIN);
        $other = new QaSoftAuthenticator();
        $other->credentialId = $authenticator->credentialId;
        $other->userHandle = '42';
        $forged = $other->get(['publicKey' => ['rpId' => 'quill.test', 'challenge' => json_decode(QaSoftAuthenticator::unb64u($assertion['clientDataJSON']), true)['challenge']]], self::ORIGIN);
        $this->expectExceptionMessage('invalid signature');
        $passkey->authenticate($forged);
    }

    public function testCounterGoingBackIsRefused(): void
    {
        [$passkey, $authenticator] = $this->registered();
        $authenticator->counter = 5;
        $passkey->authenticate($authenticator->get($passkey->authenticationOptions(42), self::ORIGIN));
        $this->expectExceptionMessage('signature counter');
        $passkey->authenticate($authenticator->get($passkey->authenticationOptions(42), self::ORIGIN, ['counter' => 3]));
    }

    public function testPasskeyOfAnotherUserIsRefused(): void
    {
        [$passkey, $authenticator] = $this->registered();
        $this->expectExceptionMessage('another account');
        $passkey->authenticate($authenticator->get($passkey->authenticationOptions(43), self::ORIGIN));
    }

    public function testUserVerificationIsRequired(): void
    {
        $passkey = $this->passkey();
        $authenticator = new QaSoftAuthenticator();
        $authenticator->flags = 0x01; // present, not verified
        $this->expectExceptionMessage('not verified');
        $passkey->register($authenticator->create($passkey->registrationOptions(42, 'alice'), self::ORIGIN), 42);
    }

    public function testRegistrationChallengeIsBoundToTheUser(): void
    {
        $passkey = $this->passkey();
        $response = (new QaSoftAuthenticator())->create($passkey->registrationOptions(42, 'alice'), self::ORIGIN);
        $this->expectExceptionMessage('another account');
        $passkey->register($response, 43);
    }

    public function testRegistrationForAnotherRelyingPartyIsRefused(): void
    {
        $passkey = $this->passkey();
        $response = (new QaSoftAuthenticator())->create($passkey->registrationOptions(42, 'alice'), self::ORIGIN, ['rpId' => 'evil.example']);
        $this->expectExceptionMessage('rpId');
        $passkey->register($response, 42);
    }

    public function testBase64Url(): void
    {
        $bin = random_bytes(33);
        $this->assertSame($bin, Passkey::fromBase64Url(Passkey::toBase64Url($bin)));
        $this->expectException(PasskeyException::class);
        Passkey::fromBase64Url('a+b/');
    }
}
