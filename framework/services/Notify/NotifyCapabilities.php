<?php
/**
 * The notifications capabilities of every Caligrafy project (2.15.0): what a page, REST and the JS client
 * (public/js/notify.js) use to turn notifications on, with no code in the app. Each acts only on the signed-in user's
 * own data (Context::userId()), and none is an MCP tool: only a browser can subscribe or open a confirmation link.
 */

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Notify;
use Caligrafy\Service;
use Caligrafy\ServiceError;

abstract class CaligrafyNotifyService extends Service
{
    // the signed-in user (or the owner of the token): notifications are always someone's
    protected function me(Context $context)
    {
        $id = $context->userId();
        if ($id === null || $id === '') throw ServiceError::unauthenticated('Sign in to manage your notifications');
        return $id;
    }
}

#[Capability(name: 'notify_status', description: 'Your notifications: the channels this app offers (push, email), the browsers you turned push on in, your email address and whether it is confirmed, and what is on.', readOnly: true, mcp: false)]
class CaligrafyNotifyStatus extends CaligrafyNotifyService
{
    public function handle(array $input, Context $context)
    {
        return Notify::status($this->me($context));
    }
}

#[Capability(name: 'notify_subscribe', description: 'Turns push notifications on in this browser: saves its PushSubscription (endpoint and keys) for you.', mcp: false)]
class CaligrafyNotifySubscribe extends CaligrafyNotifyService
{
    public $rules = array('endpoint' => 'required|max_len,2000', 'keys' => 'required');
    public $descriptions = array('endpoint' => 'The push endpoint of the browser', 'keys' => 'Its keys: p256dh and auth');

    public function handle(array $input, Context $context)
    {
        $me = $this->me($context);
        if (!Notify::pushConfigured()) throw ServiceError::conflict('Push notifications are not set up on this app (php caligrafer.php notify keys)');
        Notify::subscribe($me, array('endpoint' => $input['endpoint'], 'keys' => is_array($input['keys']) ? $input['keys'] : array()), $_SERVER['HTTP_USER_AGENT'] ?? '');
        Notify::prefer($me, 'push', true);
        return Notify::status($me);
    }
}

#[Capability(name: 'notify_unsubscribe', description: 'Turns push notifications off in one browser (its endpoint): your other browsers keep them.', mcp: false)]
class CaligrafyNotifyUnsubscribe extends CaligrafyNotifyService
{
    public $rules = array('endpoint' => 'required|max_len,2000');
    public $descriptions = array('endpoint' => 'The push endpoint of the browser');

    public function handle(array $input, Context $context)
    {
        $me = $this->me($context);
        Notify::unsubscribe($me, $input['endpoint']);
        return Notify::status($me);
    }
}

#[Capability(name: 'notify_email_set', description: 'Sets the address your notification emails go to and emails you a link to confirm it (valid 24 hours): nothing is sent there before.', mcp: false)]
class CaligrafyNotifyEmailSet extends CaligrafyNotifyService
{
    public $rules = array('email' => 'required|valid_email|max_len,254');
    public $descriptions = array('email' => 'Your email address');

    public function handle(array $input, Context $context)
    {
        $me = $this->me($context);
        return Notify::setEmail($me, $input['email']) + array('status' => Notify::status($me));
    }
}

#[Capability(name: 'notify_email_confirm', description: 'Confirms a notification email address from the link sent to it.', public: true, mcp: false)]
class CaligrafyNotifyEmailConfirm extends Service
{
    public $rules = array('token' => 'required|max_len,64');
    public $descriptions = array('token' => 'The token of the confirmation link');

    public function handle(array $input, Context $context)
    {
        if (Notify::confirm($input['token']) === null) throw ServiceError::notFound('This link is unknown, used or expired: ask for a new one');
        return array('confirmed' => true);
    }
}

#[Capability(name: 'notify_preferences', description: 'Turns one of your notification channels (push or email) on or off.', mcp: false)]
class CaligrafyNotifyPreferences extends CaligrafyNotifyService
{
    public $rules = array('channel' => 'required|contains_list,push;email', 'enabled' => 'required|boolean');
    public $descriptions = array('channel' => 'push or email', 'enabled' => 'true or false');

    public function handle(array $input, Context $context)
    {
        $me = $this->me($context);
        Notify::prefer($me, $input['channel'], filter_var($input['enabled'], FILTER_VALIDATE_BOOLEAN));
        return Notify::status($me);
    }
}

#[Capability(name: 'notify_test', description: 'Sends you a test notification on every channel you turned on.', mcp: false)]
class CaligrafyNotifyTest extends CaligrafyNotifyService
{
    public function handle(array $input, Context $context)
    {
        return Notify::user($this->me($context), array('title' => 'Notifications work', 'body' => 'You will get the notifications of this app here.', 'url' => '/', 'tag' => 'notify-test'));
    }
}
