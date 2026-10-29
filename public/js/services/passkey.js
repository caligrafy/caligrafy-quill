/**********************************************************************************
 * Passkeys (WebAuthn) in the browser: the client side of Caligrafy\Passkey (framework/core/Passkey.php)
 * @author: Dory A.Azar
 *
 *   const options = (await axios.post(env.home + 'passkey/register/options', {}, config)).data;
 *   const credential = await PasskeyClient.create(options);      // the browser asks for the passkey
 *   await axios.post(env.home + 'passkey/register', credential, config);
 *
 *   const options = (await axios.post(env.home + 'passkey/login/options', {}, config)).data;
 *   const assertion = await PasskeyClient.get(options);
 *   await axios.post(env.home + 'passkey/login', assertion, config);
 *
 * Binary fields travel as base64url strings both ways. Passkeys need https (or http://localhost).
 * Errors: NotAllowedError (the user cancelled or the prompt timed out), InvalidStateError (already registered)
 **********************************************************************************/

class PasskeyClient {

    // true when the browser can use passkeys on this page
    static supported() {
        return typeof window !== 'undefined' && !!window.PublicKeyCredential && !!navigator.credentials;
    }

    // Promise: true when the device has a built-in authenticator (Touch ID, Windows Hello, Android)
    static async platformAvailable() {
        return PasskeyClient.supported() && PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable
            ? PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable() : false;
    }

    static toBuffer(base64url) {
        const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((base64url.length + 3) % 4);
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) { bytes[i] = binary.charCodeAt(i); }
        return bytes.buffer;
    }

    static toBase64Url(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.length; i++) { binary += String.fromCharCode(bytes[i]); }
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    // server options (Passkey::registrationOptions()) -> navigator.credentials.create() -> JSON for Passkey::register()
    static async create(options) {
        const publicKey = Object.assign({}, options.publicKey);
        publicKey.challenge = PasskeyClient.toBuffer(publicKey.challenge);
        publicKey.user = Object.assign({}, publicKey.user, { id: PasskeyClient.toBuffer(publicKey.user.id) });
        publicKey.excludeCredentials = (publicKey.excludeCredentials || []).map(c => Object.assign({}, c, { id: PasskeyClient.toBuffer(c.id) }));
        delete publicKey.extensions;
        const credential = await navigator.credentials.create({ publicKey: publicKey });
        return {
            id: PasskeyClient.toBase64Url(credential.rawId),
            clientDataJSON: PasskeyClient.toBase64Url(credential.response.clientDataJSON),
            attestationObject: PasskeyClient.toBase64Url(credential.response.attestationObject),
            transports: typeof credential.response.getTransports === 'function' ? credential.response.getTransports() : []
        };
    }

    // server options (Passkey::authenticationOptions()) -> navigator.credentials.get() -> JSON for Passkey::authenticate()
    static async get(options) {
        const publicKey = Object.assign({}, options.publicKey);
        publicKey.challenge = PasskeyClient.toBuffer(publicKey.challenge);
        if (publicKey.allowCredentials) {
            publicKey.allowCredentials = publicKey.allowCredentials.map(c => Object.assign({}, c, { id: PasskeyClient.toBuffer(c.id) }));
        }
        const assertion = await navigator.credentials.get({ publicKey: publicKey });
        return {
            id: PasskeyClient.toBase64Url(assertion.rawId),
            clientDataJSON: PasskeyClient.toBase64Url(assertion.response.clientDataJSON),
            authenticatorData: PasskeyClient.toBase64Url(assertion.response.authenticatorData),
            signature: PasskeyClient.toBase64Url(assertion.response.signature),
            userHandle: assertion.response.userHandle ? PasskeyClient.toBase64Url(assertion.response.userHandle) : null
        };
    }
}

// Node (QA tests)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { PasskeyClient };
}
