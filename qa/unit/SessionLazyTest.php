<?php

use PHPUnit\Framework\TestCase;

/**
 * Lazy sessions (2.19.0): the settings of every request stay in memory, a write after them or the CSRF token starts the
 * session, a request saves its session only when it changed it or handed out the token.
 * PHPUnit runs in the CLI, where caligrafySessionStart() never starts a session (PHP_SAPI === 'cli'): what is tested
 * here is the decision (caligrafySessionNeeded, caligrafySessionMustSave) and the ready flag, not session_start().
 */
final class SessionLazyTest extends TestCase
{
    private $saved;

    protected function setUp(): void
    {
        $this->saved = $_SESSION ?? null;
        unset($GLOBALS['__caligrafy_session_ready'], $GLOBALS['__caligrafy_session_snapshot']);
        $_SESSION = array('token' => str_repeat('a', 64));
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__caligrafy_session_ready'], $GLOBALS['__caligrafy_session_snapshot']);
        if ($this->saved === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->saved;
        }
    }

    public function testWritesBeforeTheSettingsRanDoNotStartTheSession(): void
    {
        $this->assertFalse(caligrafySessionNeeded('scripts', array('css' => 'x.css'), false), 'coresettings.php');
        $this->assertFalse(caligrafySessionNeeded('metadata', array('title' => ''), false), 'config.php');
        $this->assertFalse(caligrafySessionNeeded('home', null, false), 'a read');
        $this->assertFalse(caligrafySessionNeeded('home', null, true), 'a read after the settings');
    }

    public function testWritesAfterTheSettingsAndTheTokenStartTheSession(): void
    {
        $this->assertTrue(caligrafySessionNeeded('__authorization', 'abc', true), 'a sign-in');
        $this->assertTrue(caligrafySessionNeeded('cart', array(), true), 'an empty array is a value');
        $this->assertTrue(caligrafySessionNeeded('token', null, false), 'the token, even before the settings');
        $this->assertTrue(caligrafySessionNeeded('token', null, true));
    }

    public function testTheSnapshotMarksTheSessionReady(): void
    {
        $this->assertFalse(caligrafySessionReady());
        session('scripts', array('css' => 'styles.css'));            // the settings: kept in memory
        $this->assertSame(array('css' => 'styles.css'), $_SESSION['scripts']);
        $this->assertFalse(caligrafySessionReady());

        caligrafySessionSnapshot();
        $this->assertTrue(caligrafySessionReady());
        $this->assertTrue(caligrafySessionNeeded('cart', 1, caligrafySessionReady()));
        $this->assertSame(1, session('cart', 1));
    }

    public function testASessionIsSavedOnlyWhenChangedOrTheTokenIsHandedOut(): void
    {
        $session = array('token' => str_repeat('b', 64), 'scripts' => array('css' => 'styles.css'));
        $snapshot = serialize($session);

        $this->assertFalse(caligrafySessionMustSave($snapshot, $session, '<h1>Hello</h1>'), 'a page without a form');
        $this->assertFalse(caligrafySessionMustSave($snapshot, $session, null), 'no output buffer');
        $this->assertFalse(caligrafySessionMustSave($snapshot, $session, ''), 'no output');

        $form = "<form><input type='hidden' name='_token' value='" . $session['token'] . "'></form>";
        $this->assertTrue(caligrafySessionMustSave($snapshot, $session, $form), 'a page with csrf()');

        $changed = $session;
        $changed['cart'] = array(3);
        $this->assertTrue(caligrafySessionMustSave($snapshot, $changed, '<h1>Hello</h1>'), 'a value written after the snapshot');

        $noToken = array('scripts' => array());
        $this->assertFalse(caligrafySessionMustSave(serialize($noToken), $noToken, 'anything'), 'no token to look for');
    }

    public function testTheSettingsDoNotStartTheSession(): void
    {
        // what a plain GET runs before the snapshot: coresettings.php writes through session() and the helpers
        $_SESSION += array('imagesUrl' => '/images/', 'public' => '/public/');
        $started = array();
        foreach (array('scripts' => array('css' => '/public/css/styles.css'), 'metadata' => array('title' => '')) as $key => $value) {
            if (caligrafySessionNeeded($key, $value, caligrafySessionReady())) {
                $started[] = $key;
            }
            session($key, $value);
        }
        keywords('caligrafy');
        copyright('2026');
        $this->assertSame(array(), $started);

        caligrafySessionSnapshot();
        $snapshot = serialize($_SESSION);
        $this->assertFalse(caligrafySessionMustSave($snapshot, $_SESSION, '<p>' . copyright() . '</p>'), 'a page that reads them');
    }
}
