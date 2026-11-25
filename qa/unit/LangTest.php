<?php

use Caligrafy\Lang;
use Caligrafy\Service;
use Caligrafy\ServiceError;
use Caligrafy\Context;
use PHPUnit\Framework\TestCase;

/**
 * The languages of an app (2.28.0): the files, the visitor's language (address, cookie, browser), the fallbacks, the
 * counts, right to left, and the form messages in the visitor's language
 */
final class LangTest extends TestCase
{
    private $dir;
    private $cookies = array();

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/caligrafy-lang-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents($this->dir . '/en.json', json_encode(array(
            'home' => array('title' => 'Good food. Better hours.', 'hello' => 'Hello {name}'),
            'tables_left' => '{count} table left|{count} tables left',
            'deals' => 'No deals|One deal|{count} deals',
            'only_en' => 'English only',
        )));
        file_put_contents($this->dir . '/fr.json', json_encode(array(
            'home' => array('title' => 'Bonne cuisine. Meilleures heures.', 'hello' => 'Bonjour {name}'),
            'tables_left' => '{count} table restante|{count} tables restantes',
            'fields' => array('email' => 'Adresse e-mail'),
        )));
        file_put_contents($this->dir . '/ar.json', json_encode(array(
            'home' => array('title' => 'أكل طيب، بأوقات أحلى'),
            'validation' => array('required' => 'الحقل {field} مطلوب'),
        ), JSON_UNESCAPED_UNICODE));
        Lang::$path = $this->dir;
        $this->cookies = array();
        $self = $this;
        Lang::$cookie = function ($name, $value, $options) use ($self) { $self->cookies[$name] = array($value, $options); };
        putenv('APP_LOCALES=en,fr,ar');
        putenv('APP_LOCALE');
        Lang::reset();
    }

    protected function tearDown(): void
    {
        Lang::$path = null;
        Lang::$cookie = null;
        Lang::reset();
        putenv('APP_LOCALES');
        putenv('APP_LOCALE');
        unset($_GET['lang'], $_COOKIE['lang'], $_SERVER['HTTP_ACCEPT_LANGUAGE'], $_SERVER['REQUEST_URI']);
        array_map('unlink', glob($this->dir . '/*'));
        rmdir($this->dir);
    }

    public function testTheLocalesComeFromTheSettings(): void
    {
        $this->assertSame(array('en', 'fr', 'ar'), Lang::locales());
        $this->assertSame('en', Lang::fallback(), 'the first one when APP_LOCALE is empty');
        putenv('APP_LOCALE=fr');
        $this->assertSame('fr', Lang::fallback());
        putenv('APP_LOCALE=de');
        $this->assertSame('en', Lang::fallback(), 'a language the app does not have is not used');
        putenv('APP_LOCALES');
        $this->assertSame(array('en'), Lang::locales(), 'en when nothing is set');
    }

    public function testTheAddressWinsAndIsRemembered(): void
    {
        $_GET['lang'] = 'FR';
        $_COOKIE['lang'] = 'ar';
        $this->assertSame('fr', Lang::locale());
        $this->assertSame('fr', $this->cookies['lang'][0], 'remembered for the next pages');
        $this->assertSame('/', $this->cookies['lang'][1]['path']);
        $this->assertSame('Lax', $this->cookies['lang'][1]['samesite']);
    }

    public function testThenTheCookieThenTheBrowser(): void
    {
        $_GET['lang'] = 'de';                                  // not one of the app's: ignored
        $_COOKIE['lang'] = 'ar';
        $this->assertSame('ar', Lang::locale());
        Lang::reset();
        unset($_COOKIE['lang']);
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de-DE,fr-CA;q=0.8,en;q=0.5';
        $this->assertSame('fr', Lang::locale(), 'fr-CA matches fr, before en');
        Lang::reset();
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de, it;q=0.9';
        $this->assertSame('en', Lang::locale(), 'none of the browser\'s: the fallback');
        $this->assertSame('ar', Lang::fromAcceptLanguage('ar-LB,ar;q=0.9,en;q=0.8', array('en', 'ar')));
        $this->assertNull(Lang::fromAcceptLanguage('fr;q=0', array('fr')), 'q=0 means not this one');
    }

    public function testWordsFallBackToTheDefaultThenToTheKey(): void
    {
        Lang::set('fr');
        $this->assertSame('Bonne cuisine. Meilleures heures.', Lang::get('home.title'));
        $this->assertSame('Bonjour Maya', Lang::get('home.hello', array('name' => 'Maya')));
        $this->assertSame('English only', Lang::get('only_en'), 'missing in fr: the default language\'s');
        $this->assertSame('nowhere.at_all', Lang::get('nowhere.at_all'), 'missing everywhere: the key');
        $this->assertTrue(Lang::has('home.title'));
        $this->assertFalse(Lang::has('only_en'));
        $this->assertSame('أكل طيب، بأوقات أحلى', Lang::get('home.title', array(), 'ar'));
        $this->assertFalse(Lang::set('de'), 'a language the app does not have');
        $this->assertSame('fr', Lang::locale());
    }

    public function testCountsPickTheForm(): void
    {
        Lang::set('en');
        $this->assertSame('1 table left', Lang::get('tables_left', array('count' => 1)));
        $this->assertSame('3 tables left', Lang::get('tables_left', array('count' => 3)));
        $this->assertSame('No deals', Lang::get('deals', array('count' => 0)));
        $this->assertSame('One deal', Lang::get('deals', array('count' => 1)));
        $this->assertSame('5 deals', Lang::get('deals', array('count' => 5)));
        $this->assertSame('2 tables restantes', Lang::get('tables_left', array('count' => 2), 'fr'));
    }

    public function testRightToLeft(): void
    {
        $this->assertSame('rtl', Lang::dir('ar'));
        $this->assertSame('rtl', Lang::dir('he-IL'));
        $this->assertSame('ltr', Lang::dir('fr'));
        Lang::set('ar');
        $this->assertSame('rtl', Lang::dir());
        $this->assertSame('rtl', lang_dir());
        $this->assertSame('ar', locale());
    }

    public function testGroupsAllAndTheJsonForScripts(): void
    {
        Lang::set('fr');
        $this->assertSame(array('title' => 'Bonne cuisine. Meilleures heures.', 'hello' => 'Bonjour {name}'), Lang::group('home'));
        $all = Lang::all();
        $this->assertSame('English only', $all['only_en'], 'the default\'s words fill the gaps');
        $json = json_decode(lang_json('home'), true);
        $this->assertSame('Bonjour {name}', $json['hello']);
        $this->assertSame('{}', lang_json('nothing_here'), 'an empty object, never an empty list');
        $this->assertStringNotContainsString('<', lang_json(), 'safe inside a script tag');
    }

    public function testTheAddressInAnotherLanguageKeepsTheQuery(): void
    {
        $this->assertSame('/deals?city=beirut&lang=ar', Lang::url('ar', '/deals?city=beirut&lang=fr'));
        $this->assertSame('/?lang=fr', Lang::url('fr', '/'));
    }

    public function testABrokenFileGivesNoWordsButNoError(): void
    {
        file_put_contents($this->dir . '/fr.json', '{"home": ');
        Lang::reset();
        Lang::set('fr');
        $log = ini_set('error_log', '/dev/null');
        $this->assertSame('Good food. Better hours.', Lang::get('home.title'), 'the default language\'s');
        ini_set('error_log', (string) $log);
        $this->assertNull(Lang::file('../etc/passwd'), 'only language codes become file names');
    }

    public function testMoneyNumbersAndDates(): void
    {
        $this->assertStringContainsString('1,234.5', Lang::money(1234.5, 'usd', 'en'));
        $this->assertNotSame('', Lang::number(1234.5, 1, 'fr'));
        $this->assertNotSame('', Lang::date('2026-11-25', 'long', 'fr'));
        $this->assertSame('', Lang::date('not a date'));
    }

    public function testFormMessagesSpeakTheVisitorsLanguage(): void
    {
        $service = new class extends Service {
            public $rules = array('email' => 'required|valid_email', 'name' => 'required');
            public function handle(array $input, Context $context) { return $input; }
        };
        Lang::set('fr');
        try {
            $service->validate(array());
            $this->fail('the empty form is refused');
        } catch (ServiceError $e) {
            $this->assertStringContainsString('Adresse e-mail', $e->fields['email'], 'the field named by the app');
            $this->assertStringNotContainsString('The ', $e->fields['name'], 'GUMP\'s French messages');
        }
        Lang::set('ar');
        try {
            $service->validate(array());
            $this->fail('the empty form is refused');
        } catch (ServiceError $e) {
            $this->assertSame('الحقل Name مطلوب', $e->fields['name'], 'GUMP has no Arabic: the app\'s message');
        }
        \GUMP::set_field_names(array('email' => 'Email'));   // the static names GUMP keeps between tests
    }
}
