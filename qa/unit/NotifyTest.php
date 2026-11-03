<?php

use Caligrafy\Capabilities;
use Caligrafy\Context;
use Caligrafy\Mcp\Server;
use Caligrafy\Notify;
use Caligrafy\ServiceError;
use PHPUnit\Framework\TestCase;

/**
 * Notifications without a database: the message rules, the channels offered, the keys, the signed unsubscribe link,
 * and the notify_* capabilities (in every project, never MCP tools)
 */
final class NotifyTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('NOTIFY_CHANNELS');
        putenv('APP_URL');
    }

    public function testAMessageIsCheckedAndCompleted(): void
    {
        $this->assertSame(['title' => 'Your price hit: $42', 'body' => 'Trail shoes at Shop A.', 'url' => 'https://shop.example/item', 'tag' => 'watch-17', 'urgency' => 'normal'],
            Notify::message(['title' => ' Your price hit: $42 ', 'body' => 'Trail shoes at Shop A.', 'url' => 'https://shop.example/item', 'tag' => 'watch-17']));
        $this->assertSame('/watches/17', Notify::message(['title' => 't', 'body' => 'b', 'url' => '/watches/17'])['url']);
        try {
            Notify::message(['title' => str_repeat('x', 81), 'body' => '', 'url' => 'http://insecure.example', 'tag' => 'no spaces', 'urgency' => 'now']);
            $this->fail('expected the message to be refused');
        } catch (ServiceError $e) {
            $this->assertSame(['title', 'body', 'url', 'tag', 'urgency'], array_keys($e->toArray()['error']['fields']));
        }
    }

    public function testTheChannelsOffered(): void
    {
        $this->assertSame(['push', 'email'], Notify::offered());
        putenv('NOTIFY_CHANNELS=email');
        $this->assertSame(['email'], Notify::offered());
        putenv('NOTIFY_CHANNELS=push, sms, email');
        $this->assertSame(['push', 'email'], Notify::offered());
    }

    public function testKeysAreAWebPushPair(): void
    {
        $keys = Notify::keys();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{86,88}$/', $keys['public']);   // 65 bytes, base64url
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{42,44}$/', $keys['private']);  // 32 bytes
    }

    public function testTheUnsubscribeLinkIsSignedForOneUser(): void
    {
        putenv('APP_URL=https://shop.example/');
        $url = Notify::unsubscribeUrl(7);
        $this->assertStringStartsWith('https://shop.example/notify/unsubscribe?u=7&s=', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertNotSame($query['s'], parse_url(Notify::unsubscribeUrl(8), PHP_URL_QUERY));
        $this->assertFalse(Notify::unsubscribeEmail(8, $query['s']), 'the signature of user 7 does not unsubscribe user 8');
        $this->assertFalse(Notify::unsubscribeEmail(7, 'forged'));
    }

    public function testTheCapabilitiesShipWithTheFrameworkAndAreNotMcpTools(): void
    {
        $names = ['notify_email_confirm', 'notify_email_set', 'notify_preferences', 'notify_status', 'notify_subscribe', 'notify_test', 'notify_unsubscribe'];
        foreach ($names as $name) {
            $this->assertNotNull(Capabilities::get($name), "$name is registered");
            $this->assertFalse(Capabilities::get($name)['capability']->mcp);
        }
        $this->assertTrue(Capabilities::get('notify_email_confirm')['capability']->public);
        $tools = array_column((new Server(Context::cli()))->tools(), 'name');
        $this->assertSame([], array_values(array_intersect($names, $tools)), 'no notify_* tool over MCP');
    }

    public function testNotifyCapabilitiesNeedSomeone(): void
    {
        try {
            Capabilities::call('notify_status', [], Context::cli());
            $this->fail('the command line is nobody');
        } catch (ServiceError $e) {
            $this->assertSame('unauthenticated', $e->kind);
        }
    }

    public function testEmailsTakeTheLookOfTheDesignStyle(): void
    {
        $caligrafy = Notify::theme();
        $this->assertSame('#000000', $caligrafy['primary'], "Caligrafy's black button without a style");
        $dir = sys_get_temp_dir() . '/quill-notify-' . bin2hex(random_bytes(4));
        mkdir("$dir/css/designs", 0777, true);
        copy(__DIR__ . '/../../public/css/look.css', "$dir/css/look.css");
        file_put_contents("$dir/css/designs/blue.css", ":root {\n  --design-carbon: #101820;\n  --design-primary: #3e6ae1; /* electric blue */\n  --design-on-primary: #fefefe;\n"
            . "  --design-font-body: 'Brand Text', Inter, sans-serif;\n  --cq-color-ink: var(--design-carbon);\n  --cq-color-primary: var(--design-primary);\n  --cq-radius-control: 999px;\n}\n.btn { --design-primary: red; }\n");
        putenv('DESIGN_STYLE=blue');
        $theme = Notify::theme("$dir/");
        putenv('DESIGN_STYLE');
        $this->assertSame(['#3e6ae1', '#fefefe', '#101820', '999px', "'Brand Text', Inter, sans-serif"],
            [$theme['primary'], $theme['on_primary'], $theme['ink'], $theme['radius'], $theme['font']], 'the style wins, var() resolved, only its :root');
        $this->assertSame($caligrafy['muted'], $theme['muted'], "what the style does not set stays Caligrafy's");
        $html = Notify::html('Hello', ['Body'], ['Open', 'https://x.example'], 'Footer <a href="#">Unsubscribe</a>');
        $this->assertStringContainsString('background:' . $caligrafy['primary'], $html);
        $this->assertStringContainsString('<a style="color:' . $caligrafy['muted'] . ';" href="#">Unsubscribe</a>', $html);
        exec('rm -rf ' . escapeshellarg($dir));
    }
}
