<?php

use Caligrafy\FaceLogin;
use Caligrafy\FaceLoginException;
use PHPUnit\Framework\TestCase;

/**
 * Server side of face login: single-use liveness challenges and the checks of the prints captured during them
 */
final class FaceLoginTest extends TestCase
{
    private static function face($seed)
    {
        mt_srand($seed);
        return array_map(fn() => (mt_rand() / mt_getrandmax() - 0.5) * 0.3, range(1, 128));
    }

    // a print of the same face seen in another frame: about 0.15 away
    private static function frame($face, $seed)
    {
        mt_srand($seed);
        return array_map(fn($v) => $v + (mt_rand() / mt_getrandmax() - 0.5) * 0.045, $face);
    }

    private static function prints($face, $n = 3, $seed = 100)
    {
        return array_map(fn($i) => self::frame($face, $seed + $i), range(1, $n));
    }

    private static function liveness($challenges)
    {
        return ['passed' => true, 'challenges' => array_map(fn($c) => ['name' => $c, 'passed' => true], $challenges)];
    }

    protected function setUp(): void
    {
        $_SESSION = [];
        FaceLogin::$clock = null;
    }

    public function testChallengeIsRandomAndKeptInTheSession(): void
    {
        $c = FaceLogin::challenge('Alice');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $c['nonce']);
        $this->assertCount(3, $c['challenges']);
        $this->assertSame($c['challenges'], array_values(array_unique($c['challenges'])));
        $this->assertEmpty(array_diff($c['challenges'], FaceLogin::CHALLENGES));
        $this->assertSame('alice', $_SESSION[FaceLogin::SESSION_KEY]['username']);
        $orders = [];
        for ($i = 0; $i < 30; $i++) { $orders[implode(',', FaceLogin::challenge(null, 4)['challenges'])] = true; }
        $this->assertGreaterThan(3, count($orders), 'the order of the challenges must vary');
        $this->assertNotSame($c['nonce'], FaceLogin::challenge()['nonce']);
    }

    public function testVerifyAcceptsALiveResponseOnce(): void
    {
        $c = FaceLogin::challenge();
        $response = ['nonce' => $c['nonce'], 'liveness' => self::liveness($c['challenges']), 'prints' => self::prints(self::face(1))];
        $this->assertCount(3, FaceLogin::verify($response));
        $this->expectExceptionCode(401);
        FaceLogin::verify($response); // replay of the same response
    }

    public function testNonceIsConsumedEvenWhenTheCheckFails(): void
    {
        $c = FaceLogin::challenge();
        try {
            FaceLogin::verify(['nonce' => $c['nonce'], 'liveness' => ['passed' => false], 'prints' => []]);
            $this->fail('a failed liveness check must be refused');
        } catch (FaceLoginException $e) {
            $this->assertSame(422, $e->getCode());
        }
        $this->expectExceptionCode(401);
        FaceLogin::verify(['nonce' => $c['nonce'], 'liveness' => self::liveness($c['challenges']), 'prints' => self::prints(self::face(1))]);
    }

    public function testExpiredNonceIsRefused(): void
    {
        $now = 1000000;
        FaceLogin::$clock = function () use (&$now) { return $now; };
        $c = FaceLogin::challenge(null, 2, 60);
        $now += 61;
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('expired');
        FaceLogin::verify(['nonce' => $c['nonce'], 'liveness' => self::liveness($c['challenges']), 'prints' => self::prints(self::face(1))]);
    }

    public function testNonceWithinItsLifetimeIsAccepted(): void
    {
        $now = 1000000;
        FaceLogin::$clock = function () use (&$now) { return $now; };
        $c = FaceLogin::challenge(null, 2, 60);
        $now += 59;
        $this->assertCount(3, FaceLogin::verify(['nonce' => $c['nonce'], 'liveness' => self::liveness($c['challenges']), 'prints' => self::prints(self::face(1))]));
    }

    public function testWrongNonceOrAccountIsRefused(): void
    {
        $c = FaceLogin::challenge('alice');
        try {
            FaceLogin::consume(str_repeat('0', 64), 'alice');
            $this->fail('a wrong nonce must be refused');
        } catch (FaceLoginException $e) {
            $this->assertSame(401, $e->getCode());
        }
        $c = FaceLogin::challenge('alice');
        $this->expectExceptionMessage('another account');
        FaceLogin::consume($c['nonce'], 'bob');
    }

    public function testNoChallengeInTheSessionIsRefused(): void
    {
        $this->expectExceptionCode(401);
        FaceLogin::consume(null);
    }

    public function testLivenessMustMatchTheRequestedChallengesInOrder(): void
    {
        $c = FaceLogin::challenge(null, 3);
        $this->expectExceptionMessage('do not match');
        FaceLogin::verify(['nonce' => $c['nonce'], 'liveness' => self::liveness(array_reverse($c['challenges'])), 'prints' => self::prints(self::face(1))]);
    }

    public function testIdenticalPrintsAreRefused(): void
    {
        $print = self::frame(self::face(1), 7);
        $this->expectExceptionMessage('Identical');
        FaceLogin::checkPrints([$print, $print, $print]);
    }

    public function testPrintsOfDifferentFacesAreRefused(): void
    {
        $this->expectExceptionMessage('same face');
        FaceLogin::checkPrints([self::frame(self::face(1), 1), self::frame(self::face(1), 2), self::frame(self::face(2), 3)]);
    }

    public function testPrintFormatAndCount(): void
    {
        $face = self::face(1);
        $this->assertCount(3, FaceLogin::checkPrints(array_map(fn($p) => ['descriptor' => $p], self::prints($face))));
        foreach ([self::prints($face, 2), self::prints($face, 13), [array_slice($face, 1)] + self::prints($face),
                  [array_fill(0, 128, 'x'), self::frame($face, 1), self::frame($face, 2)], [array_fill(0, 128, NAN), self::frame($face, 1), self::frame($face, 2)], 'nope'] as $bad) {
            try {
                FaceLogin::checkPrints($bad);
                $this->fail('invalid prints accepted');
            } catch (FaceLoginException $e) {
                $this->assertSame(422, $e->getCode());
            }
        }
    }

    public function testMatchFindsTheAccount(): void
    {
        $alice = self::face(1); $bob = self::face(2);
        $enrolled = [];
        foreach (self::prints($alice, 4, 500) as $p) { $enrolled[] = ['user' => 7, 'descriptor' => $p]; }
        foreach (self::prints($bob, 4, 600) as $p) { $enrolled[] = ['user' => 8, 'descriptor' => $p]; }
        $this->assertSame(7, FaceLogin::match(self::prints($alice, 3, 900), $enrolled)['user']);
        $this->assertSame(8, FaceLogin::match(self::prints($bob, 3, 900), $enrolled)['user']);
        $this->assertNull(FaceLogin::match(self::prints(self::face(3), 3, 900), $enrolled));
        $this->assertSame(7, FaceLogin::enrolledElsewhere(self::prints($alice, 3, 950), $enrolled, 8));
        $this->assertNull(FaceLogin::enrolledElsewhere(self::prints($alice, 3, 950), $enrolled, 7));
    }

    public function testPrintHashIgnoresFloatFormatting(): void
    {
        $p = self::face(1);
        $this->assertSame(FaceLogin::printHash($p), FaceLogin::printHash(array_map(fn($v) => (float)sprintf('%.9f', $v), $p)));
        $this->assertNotSame(FaceLogin::printHash($p), FaceLogin::printHash(self::frame($p, 1)));
    }
}
