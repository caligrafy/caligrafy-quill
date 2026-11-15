A **passkey** (WebAuthn) is a key pair kept by the person's device (Touch ID, Windows Hello, Android, a security key or
a password manager) and unlocked with its biometrics or PIN. The private key never leaves the device and a passkey works
only on the domain it was made for: it cannot be phished or replayed. `Caligrafy\Passkey` (on
[lbuchs/webauthn](https://github.com/lbuchs/WebAuthn)) checks them on the server, `PasskeyClient`
(`public/js/services/passkey.js`) runs them in the browser, and `Route::auth(['passkeys' => true])` declares the four
endpoints. In `Route::auth` since 2.19.0.

<br/>

## With Claude

```text
"people can add a passkey once signed in, and sign in with it"
```

For face sign-in with liveness and passkeys, say *"face sign-in with liveness and passkeys"*: Claude follows the
plugin's face login recipe ([Face detection and face login](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition#face-login)).

**Check**

+ Signed in, adding a passkey answers `201` and a row appears in `passkeys`.
+ Signed out, the passkey signs you in, and `AuthRoutes::signedInBy()` answers `passkey`.

<br/>

## By hand

Before you start: the authentication module (`Auth::authentication('User', 'users');` in `web.php`).

1. **The table.** Add it with a [migration](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations)
   (`php caligrafer.php migrate make add_passkeys`), with the statement of the face login recipe:
    ```sql
    CREATE TABLE IF NOT EXISTS passkeys (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      credential_id VARCHAR(255) NOT NULL,           -- base64url
      public_key TEXT NOT NULL,                      -- PEM
      sign_count INT UNSIGNED NOT NULL DEFAULT 0,
      name VARCHAR(64) NOT NULL DEFAULT 'Passkey',
      transports VARCHAR(255) NULL,
      aaguid CHAR(32) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      last_used_at DATETIME NULL,
      CONSTRAINT uq_passkeys_credential_id UNIQUE (credential_id),
      CONSTRAINT fk_passkeys_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ```
   then `php caligrafer.php migrate`.
2. **The endpoints**, in `application/config/routing/web.php`:
    ```php
    Route::auth(['home' => '/', 'passkeys' => true]);
    ```
   It declares `POST /passkey/register/options`, `/passkey/register` (signed in), `/passkey/login/options` and
   `/passkey/login`.
3. **The browser.** Load `passkey.js` (`APP_SERVICE_ROOT` is the address of `public/js/services/`) and call the
   endpoints with the session's `X-CSRF-Token` header. In a client app page, `apiHeaders()` gives it:
    ```js
    // <script src="<?php echo APP_SERVICE_ROOT.'passkey.js'; ?>"></script>
    const config = { headers: apiHeaders() };   // the session and its X-CSRF-Token
    const options = (await axios.post(env.home + 'passkey/register/options', {}, config)).data;
    await axios.post(env.home + 'passkey/register', await PasskeyClient.create(options), config);

    const login = (await axios.post(env.home + 'passkey/login/options', {}, config)).data;
    await axios.post(env.home + 'passkey/login', await PasskeyClient.get(login), config);
    ```
   `PasskeyClient.supported()` says whether the browser has passkeys at all.
4. **In production**, set in `.env` (they are not in `.env.example`):
    ```bash
    PASSKEY_RP_ID=example.com
    PASSKEY_ORIGIN=https://www.example.com
    ```
   Without them the request host is used, which is fine on `http://localhost`. Passkeys need `https`, except on
   `localhost`.

<br/>

## <a name="endpoints"></a>Reference: the endpoints of `Route::auth`

| Endpoint | Who | Answers |
|---|---|---|
| `POST /passkey/register/options` | signed in | `200` the options for `PasskeyClient.create()`; `401` *Sign in first* |
| `POST /passkey/register` | signed in | `201` `{"passkey": {"name": ...}}` (the optional `name` field, *Passkey* by default) |
| `POST /passkey/login/options` | anyone | `200` the options for `PasskeyClient.get()`: any passkey of the site, no username to type |
| `POST /passkey/login` | anyone | `200` `{"user": {"id", "username", "permissions"}}`, a new session id and CSRF token, signed in by `passkey`; `401` *Unknown account* |

Every one needs the `X-CSRF-Token` header (`403` without it). A passkey error answers its status (`400` a failed
registration, `409` *This passkey is already registered*, `401` a failed sign-in) with `{"error": ...}`. The `level`
option of `Route::auth` applies: someone below it is refused like an unknown account.

<br/>

## <a name="class"></a>Reference: `Caligrafy\Passkey`

```text
Registration (signed in):  registrationOptions($id, $username) -> navigator.credentials.create() -> register($response, $id)
Sign-in:                   authenticationOptions([$id])        -> navigator.credentials.get()    -> authenticate($response) returns the user id
```

+ `new Passkey($rpName = APP_NAME, $rpId = PASSKEY_RP_ID or the request host, $origin = PASSKEY_ORIGIN or APP_PROTOCOL://host, $table = 'passkeys')`.
+ `registrationOptions($userId, $userName, $displayName = null)`: the user id is the WebAuthn user handle, never an
  email or a name. `register($response, $userId, $name = null)` stores the credential and returns it without its public
  key. `authenticationOptions($userId = null)`: `null` lets the browser offer any discoverable passkey of the site.
  `authenticate($response)` returns the id of the user who owns the passkey. `remove($userId, $credentialId)`,
  `credentials($userId)`.
+ The challenge of each ceremony is kept in the session, used once, and expires after `$timeout` (120 s).
  `$userVerification` and `$residentKey` are `required`.
+ To store the credentials elsewhere, extend `Passkey` and override `credentials()`, `findCredential()`,
  `saveCredential()` and `touchCredential()`.

Every method: [API: Caligrafy\Passkey](https://github.com/caligrafy/caligrafy-quill/wiki/API-Auth#passkey).

<br/>

## Common mistakes

+ **`Route::auth(['passkeys' => true])` and the face login recipe's passkey routes both declared**: the recipe's
  `Route::action` lines replace it; declare one of them.
+ **No `PASSKEY_RP_ID`/`PASSKEY_ORIGIN` in production**: the request host is used; set both (G42).
+ **`http://` on a server**: passkeys need `https`, except on `localhost`.
+ **Calling the endpoints without `X-CSRF-Token`**: `403`.

<br/>

## Related pages

+ [Sign-in, users and tokens](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication#routeauth): `Route::auth`
+ [Face detection and face login](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition#passkeys)
+ [How to sign in through another site](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Sign-In-Through-Another-Site#signedinby): `AuthRoutes::signedInBy()`
+ [API: Caligrafy\Passkey](https://github.com/caligrafy/caligrafy-quill/wiki/API-Auth#passkey)
