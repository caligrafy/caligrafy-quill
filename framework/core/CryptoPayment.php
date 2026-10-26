<?php

/**
 * CryptoPayment.php is the file that controls all the cryptocurrency payments
 * @copyright 2019
 * @author Dory A.Azar
 * @version 2.0
 */

/**
 * CryptoPayment accepts crypto payments (USDC) through the Coinbase Business Checkouts API.
 *
 * Coinbase Commerce (api.commerce.coinbase.com, CRYPTO_PAY_KEY) shut down on March 31, 2026. Its successor is the
 * Checkouts API of Coinbase Business (https://business.coinbase.com/api/v1/checkouts), available to merchants in the
 * supported countries. Requests are authenticated with a short-lived JWT signed with a CDP API key:
 * - COINBASE_API_KEY_NAME: the key name, e.g. organizations/{org_id}/apiKeys/{key_id}
 * - COINBASE_API_KEY_SECRET: the Ed25519 secret (base64) or the ECDSA private key (PEM, legacy keys)
 * @author Dory A.Azar
 * @version 2.0
 */

namespace Caligrafy;
use \Exception as Exception;


class CryptoPayment extends \stdClass {

	/**
	* @var string the Coinbase Business API host
	*/
	private $_host = "business.coinbase.com";

	/**
	* @var string the API protocol (https in production)
	*/
	private $_scheme = "https";

	/**
	* @var string the CDP API key name
	*/
	private $_key_name;

	/**
	* @var string the CDP API key secret
	*/
	private $_key_secret;


	/**
	 * @param string $keyName CDP API key name (defaults to COINBASE_API_KEY_NAME)
	 * @param string $keySecret CDP API key secret (defaults to COINBASE_API_KEY_SECRET)
	 */
	public function __construct($keyName = null, $keySecret = null)
	{
		$this->_key_name = $keyName?? (defined('COINBASE_API_KEY_NAME')? COINBASE_API_KEY_NAME : '');
		$this->_key_secret = $keySecret?? (defined('COINBASE_API_KEY_SECRET')? COINBASE_API_KEY_SECRET : '');
		$this->_key_secret = str_replace('\n', "\n", (string)$this->_key_secret); // PEM keys written on one line in .env
        return $this;
	}

 	/**
	 * Creates a hosted checkout (single-use payment page)
	 * @param string|float $amount the amount (2 decimals at most)
	 * @param string $currency USDC or a fiat currency (USD, EUR, GBP, SGD...) converted to USDC at creation
	 * @param array $charge 'name' and 'description' of the product
	 * @param array $metadata up to 20 string pairs
	 * @param string $redirectUrl HTTPS URL to come back to after a successful payment
	 * @param string $cancelUrl HTTPS URL to come back to after a failed payment
	 * @param string $expiresAt RFC 3339 expiration date (default: 24 hours)
	 * @return array ['action_success' => bool, 'url' => hosted payment page, 'data' => checkout (data['hosted_url'] kept for older code)]
	 */
	public function createTransaction($amount, $currency, $charge = array(), $metadata = array(), $redirectUrl = '', $cancelUrl = '', $expiresAt = null)
    {
		$description = trim(($charge['name']?? '').(isset($charge['name'], $charge['description'])? ' - ' : '').($charge['description']?? ''));
		$body = array(
			'amount' => number_format((float)$amount, 2, '.', ''),
			'currency' => strtoupper($currency)
		);
		if ($description !== '') { $body['description'] = substr($description, 0, 500); }
		if (!empty($metadata)) { $body['metadata'] = array_map('strval', $metadata); }
		if ($redirectUrl) { $body['successRedirectUrl'] = $redirectUrl; }
		if ($cancelUrl) { $body['failRedirectUrl'] = $cancelUrl; }
		if ($expiresAt) { $body['expiresAt'] = $expiresAt; }

		$result = $this->coinbaseRequest('/api/v1/checkouts', 'POST', $body, array('X-Idempotency-Key: '.$this->uuid()));
		if (!empty($result['data']['url'])) {
			$result['url'] = $result['data']['url'];
			$result['data']['hosted_url'] = $result['data']['url'];
		}
		return $result;
    }

    /**
	 * Deactivates an active checkout so that it can no longer be paid
	 */
    public function cancelTransaction($id)
    {
		return $this->coinbaseRequest('/api/v1/checkouts/'.rawurlencode($id).'/deactivate', 'POST');
    }

    /**
	 * Refunds a completed checkout (full refund when $amount is null)
	 */
    public function refundTransaction($id, $amount = null, $currency = 'USDC', $reason = '')
    {
		$body = array('currency' => strtoupper($currency));
		if ($amount !== null) { $body['amount'] = number_format((float)$amount, 2, '.', ''); }
		if ($reason) { $body['reason'] = $reason; }
		return $this->coinbaseRequest('/api/v1/checkouts/'.rawurlencode($id).'/refund', 'POST', $body, array('X-Idempotency-Key: '.$this->uuid()));
    }

    /**
	 * Lists the checkouts. $filters: status, startTime, endTime, descriptionQuery, pageSize, pageToken
	 */
    public function getCharges($filters = array())
    {
        $query = !empty($filters)? '?'.http_build_query($filters) : '';
        return $this->coinbaseRequest('/api/v1/checkouts'.$query, 'GET');
    }

    /**
	 * Retrieves a checkout by id
	 */
    public function getCharge($chargeId)
    {
        return $this->coinbaseRequest('/api/v1/checkouts/'.rawurlencode($chargeId), 'GET');
    }

    /**
	 * Returns the status of a checkout: ACTIVE, PROCESSING, COMPLETED, EXPIRED, FAILED, DEACTIVATED, REFUNDED,
	 * PARTIALLY_REFUNDED, or 'unknown'
	 */
    public function getChargeStatus($chargeId)
    {
        $charge = $this->getCharge($chargeId);
        return $charge['data']['status']?? 'unknown';
    }

	/**
	 * Sends an authenticated request to the Checkouts API
	 * @return array ['action_success' => bool, 'data' => decoded response] or ['action_success' => false, 'error' => message]
	 */
	public function coinbaseRequest($requestPath, $requestMethod, $body = null, $additionalHeaders = array())
	{
		$requestMethod = strtoupper($requestMethod);
		try {
			$jwt = $this->jwt($requestMethod, strtok($requestPath, '?'));
		} catch (Exception $e) {
			return array('action_success' => false, 'error' => $e->getMessage());
		}
		$headers = array_merge(array(
				"Accept: application/json",
				"Content-Type: application/json",
				"Authorization: Bearer ".$jwt
			), (array)$additionalHeaders);

		$response = httpRequest($this->_scheme.'://'.$this->_host.$requestPath, $requestMethod, $body?? array(), $headers);
		if (!is_array($response) || isset($response['errorType']) || isset($response['error'])) {
			$message = is_array($response)? ($response['errorMessage']?? ($response['error']['message']?? ($response['error']?? 'Coinbase request failed'))) : 'Coinbase request failed';
			return array('action_success' => false, 'error' => is_string($message)? $message : json_encode($message), 'data' => $response);
		}
		return array('action_success' => true, 'data' => $response);
	}

	/**
	 * Builds the JWT that authenticates one request (valid 120 seconds)
	 * Ed25519 keys (base64, 64 bytes) are signed with EdDSA, PEM EC keys with ES256
	 */
	public function jwt($method, $path)
	{
		if (!$this->_key_name || !$this->_key_secret) {
			throw new Exception('Coinbase Business credentials are missing: set COINBASE_API_KEY_NAME and COINBASE_API_KEY_SECRET');
		}
		$isPem = strpos($this->_key_secret, 'BEGIN') !== false;
		$time = time();
		$header = array('alg' => $isPem? 'ES256' : 'EdDSA', 'typ' => 'JWT', 'kid' => $this->_key_name, 'nonce' => bin2hex(random_bytes(16)));
		$payload = array(
			'sub' => $this->_key_name,
			'iss' => 'cdp',
			'aud' => array('cdp_service'),
			'nbf' => $time,
			'exp' => $time + 120,
			'uri' => $method.' '.$this->_host.$path
		);
		$message = $this->base64url(json_encode($header)).'.'.$this->base64url(json_encode($payload, JSON_UNESCAPED_SLASHES));

		if ($isPem) {
			$key = openssl_pkey_get_private($this->_key_secret);
			if (!$key || !openssl_sign($message, $der, $key, OPENSSL_ALGO_SHA256)) {
				throw new Exception('Invalid Coinbase ECDSA key');
			}
			$signature = $this->derToRaw($der);
		} else {
			$decoded = base64_decode($this->_key_secret, true);
			if ($decoded === false || strlen($decoded) !== 64) {
				throw new Exception('Invalid Coinbase Ed25519 key: expected the base64 secret of 64 bytes');
			}
			// the first 32 bytes are the seed: derive the libsodium secret key from it
			$keypair = sodium_crypto_sign_seed_keypair(substr($decoded, 0, 32));
			$signature = sodium_crypto_sign_detached($message, sodium_crypto_sign_secretkey($keypair));
		}
		return $message.'.'.$this->base64url($signature);
	}

	private function base64url($data)
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}

	/**
	 * Converts an ECDSA DER signature (openssl) to the raw R||S form that JWT ES256 expects
	 */
	private function derToRaw($der)
	{
		$offset = 3 + (ord($der[1]) & 0x80? (ord($der[1]) & 0x7f) : 0);
		$rLength = ord($der[$offset]);
		$r = substr($der, $offset + 1, $rLength);
		$offset += 1 + $rLength + 1;
		$sLength = ord($der[$offset]);
		$s = substr($der, $offset + 1, $sLength);
		return str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT).str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
	}

	private function uuid()
	{
		$b = random_bytes(16);
		$b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
		$b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
	}

}
