# Webhooks: `Route::webhook`

A payment provider, a library service or GitHub posts events to an address of yours. `Route::webhook($route,
$capability, $options)` checks that the event comes from them (its signature), then calls a capability with it. There
is no session and no CSRF token: the signature is the only proof. Since 2.19.0.

```php
Route::webhook('/stripe/events', 'record_event', ['verify' => 'stripe']);   // STRIPE_WEBHOOK_SECRET=whsec_... in .env
```

<br/>

## With Claude

```text
"Receive Stripe's checkout events at /stripe/events and record each one once."
```

**Check** before you say OK:
+ the route has `verify`, and the secret is the endpoint's `whsec_...`, not the API key;
+ the capability starts by refusing every caller that is not the webhook (`$context->actorType() !== 'webhook'`);
+ the same event posted twice is recorded once.

<br/>

## By hand

1. **A table that remembers the events.** Run
   `docker compose exec dev-box php caligrafer.php migrate make create_webhook_events`, and write in the file it names:

   ```sql
   -- create_webhook_events
   CREATE TABLE IF NOT EXISTS webhook_events (
       id INT UNSIGNED NOT NULL AUTO_INCREMENT,
       event_id VARCHAR(100) NOT NULL,
       type VARCHAR(100) NOT NULL,
       created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
       PRIMARY KEY (id),
       UNIQUE KEY uq_webhook_events_event_id (event_id)
   ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
   ```

   Then `docker compose exec dev-box php caligrafer.php migrate`.

2. **The capability.** `docker compose exec dev-box php caligrafer.php service Bookshelf/RecordEvent`, then make
   `application/services/Bookshelf/RecordEvent.php` read:

   ```php
   <?php

   use Caligrafy\Capability;
   use Caligrafy\Context;
   use Caligrafy\Database;
   use Caligrafy\Service;
   use Caligrafy\ServiceError;

   #[Capability(
       name: 'record_event',
       description: 'Records an event that the library service posts (its id and type), once per event id.'
   )]
   class RecordEvent extends Service
   {
       public $rules = array('event' => 'required');

       public function handle(array $input, Context $context)
       {
           // only the webhook route may record events: REST, MCP and pages would let a signed-in user make one up
           if ($context->actorType() !== 'webhook') {
               throw ServiceError::forbidden('Only the library service sends events');
           }
           $event = $input['event'];
           if (!is_array($event) || empty($event['id']) || empty($event['type'])) {
               throw ServiceError::invalid(array('event' => 'The event needs an id and a type'));
           }
           // the sender posts an event again until it gets a 2xx: record each id once
           if (!Database::table('webhook_events')->where('event_id', $event['id'])->exists()) {
               Database::table('webhook_events')->insert(array('event_id' => $event['id'], 'type' => $event['type']));
           }
           return array('recorded' => $event['id']);
       }
   }
   ```

3. **The secret and the route.** Add the secret the sender gave you to `.env` (a placeholder here):
   `LIBRARY_WEBHOOK_SECRET=whsec_test_library`. In `web.php`, before `Route::run();`:

   ```php
       // the library service signs each event: X-Signature: sha256=<HMAC-SHA256 of the body with the secret>
       Route::webhook('/hooks/library', 'record_event', ['verify' => function ($rawBody, $headers) {
           $expected = 'sha256=' . hash_hmac('sha256', $rawBody, (string) getenv('LIBRARY_WEBHOOK_SECRET'));
           return hash_equals($expected, $headers['x-signature'] ?? '');
       }]);
   ```

4. **Post a signed event.** Run:

   ```bash
   BODY='{"id":"evt_1","type":"loan.returned"}'
   SIG="sha256=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac whsec_test_library -r | cut -d' ' -f1)"
   curl -s -i -X POST -H "Content-Type: application/json" -H "X-Signature: $SIG" http://localhost:8080/hooks/library -d "$BODY"
   ```

   It answers `200` with `{"received":true}`, and `webhook_events` has one row. The same post again adds nothing. With
   `-H "X-Signature: sha256=bad"`: `400` and `{"error":{"code":"invalid_signature","message":"The signature is not valid"}}`.
   A `GET` answers `405`.

<br/>

## <a name="options"></a>Options

| Option | Default | What it does |
|---|---|---|
| `verify` | required | `'stripe'`, or `function (string $rawBody, array $headers): bool` (header names in lower case; only `true` passes) |
| `secret_env` | `'STRIPE_WEBHOOK_SECRET'` | with `'stripe'`: the `.env` variable that holds the endpoint's signing secret (`whsec_...`, not the API key) |
| `secret` | none | with `'stripe'`: the secret itself, instead of the variable (tests) |
| `tolerance` | `300` | with `'stripe'`: how many seconds old a signature may be |

`'stripe'` checks the `Stripe-Signature` header with the Stripe library. Anything else is a function of yours, as in
step 3.

<br/>

## <a name="answers"></a>What the sender gets

| The event | The answer |
|---|---|
| signature checked, capability done | `200 {"received": true}` |
| bad or missing signature | `400 {"error":{"code":"invalid_signature",...}}` |
| a body that is not JSON | `400 {"error":{"code":"invalid","message":"The body is not JSON"}}` |
| the capability refuses (`ServiceError`) | its status and its error (`422` for `invalid`) |
| no secret set, no such capability, an error in the capability | `500`; the message goes to the PHP error log |

+ `verify` is required: a webhook without it stops with an error when `web.php` runs.
+ The body must be JSON; the capability gets `['event' => the decoded body]`, so declare `$rules = ['event' => 'required']`.
+ The capability runs as the system, with full access (`Context::system('webhook', $path)`; the audit log shows
  `webhook /hooks/library`): treat the event as data from outside. Before trusting an amount, ask the provider again
  ([Stripe](https://github.com/caligrafy/caligrafy-quill/wiki/9.2-Stripe-&-Cryptocurrency-Payment#webhook)).
+ Webhook addresses skip the CSRF check that every other form post goes through.

<br/>

## Reference

+ [`Route::webhook()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-webhook)
+ [`Caligrafy\Webhook`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#webhook):
  [`verify()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#webhook-verify),
  [`process()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#webhook-process) (a test posts an event
  without a server)
+ [`Context::actorType()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing#context-actortype)

<br/>

## Common mistakes

+ **`'verify' => 'stripe'` without `STRIPE_WEBHOOK_SECRET`**: every event answers `500`. Use the endpoint's
  `whsec_...`, not the `sk_...` key.
+ **A webhook capability that is not idempotent**: services post an event again until they get a `2xx`, and a retried
  event is recorded twice.
+ **No `actorType()` check**: the capability is also served by REST, the JS client, MCP and `php caligrafer.php call`,
  to anyone its `scope` and `level` let in, with an event they made up.

## Related pages

+ [Stripe & cryptocurrency payment](https://github.com/caligrafy/caligrafy-quill/wiki/9.2-Stripe-&-Cryptocurrency-Payment#webhook)
+ [Migrations](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations)
+ [Declarative routes](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes#webhook)
+ [The audit log](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#audit)
