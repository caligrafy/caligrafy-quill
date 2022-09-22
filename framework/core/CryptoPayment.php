<?php

/**
 * CryptoPayment.php is the file that controls all the cryptocurrency payments
 * @copyright 2019
 * @author Dory A.Azar
 * @version 1.0
 */

/**
 * The Payment handles all payment transactions
 * @author Dory A.Azar
 * @version 1.0
 */

namespace Caligrafy;


class CryptoPayment {

    
	/**
	* @var string the Stripe public_key
	* @property string Stripe public_key
	*/
	private $_public_key;
	
	/**
	* @var string the Coinbase url 
	* @property string the Coinbase url
	*/
	private $_coinbase_url;
	
	/**
	* @var string the Coinbase API Version 
	* @property string the Coinbase API Version 
	*/
	private $_coinbase_version;


	/**
	 * Constructs the Payment Controller to initiate the Stripe api
	 * @param $key_args containing the public and private keys for the appropriate environment
	 * @author Dory A.Azar
	 * @version 1.0
	 */

	public function __construct()
	{
		$this->_public_key = CRYPTO_PAY_KEY;
		$this->_coinbase_url = "https://api.commerce.coinbase.com";
		$this->_coinbase_version = "2018-03-22";
        return $this;

	}
    
 	/**
	 * Creates a new payment transaction
	 * @author Dory A.Azar
	 * @version 1.0
	 */

	public function createTransaction($amount, $currency, $charge, $metadata = array(), $redirectUrl = '', $cancelUrl = '')
    {   

		$body = array(
			"name" => $charge && isset($charge['name'])? $charge['name'] : '',
			"description" => $charge && isset($charge['description'])? $charge['description'] : '',
			"logo_url" => $charge && isset($charge['logo_url'])? $charge['logo_url'] : session('imagesUrl').'resources/logo.png',
			"local_price" => [
				'amount' => $amount,
				'currency' => $currency
			],
			"pricing_type" => "fixed_price",
			"redirect_url" => $redirectUrl,
			"cancel_url" => $cancelUrl,
			"metadata" => $metadata
		);

		return $this->coinbaseRequest('/charges', 'POST', $body);
    
    }
    
    
    /**
	 * Cancel a transaction
     * @param $id string defines the id
	 * @author Dory A.Azar
	 * @version 1.0
	 */
    public function cancelTransaction($id)
    {
		if ($id) return $this->coinbaseRequest("/charges/$id/cancel", 'POST');
    }
    
    /**
	 * Get All the charges
	 * @author Dory A.Azar
	 * @version 1.0
	 */
    public function getCharges()
    {
        return $this->coinbaseRequest("/charges", 'GET');
    }
    
    /**
	 * Retrieve a charge
     * @param chargeId defines the id of the charge
	 * @author Dory A.Azar
	 * @version 1.0
	 */
    
    public function getCharge($chargeId)
    {
        if ($chargeId) return $this->coinbaseRequest("/charges/$chargeId", 'GET');
    }
    
    
    /**
	 * Get Charge Status
     * @param chargeId defines the id of the charge
	 * @author Dory A.Azar
	 * @version 1.0
	 */
    public function getChargeStatus($chargeId)
    {
        $charge = $this->getCharge($chargeId);
        $status = 'unknown';
        if ($charge && !empty($charge['data'])) {
            $statuses = isset($charge['data']['timeline'])? $charge['data']['timeline']: array();
            $status = !empty($statuses)? $statuses[count($statuses)-1]['status'] : $status; 
            
        }
        return $status;
        
    }
	
	/* Create a coinbase request
	 * @param string $requestPath defines the endpoint url of the api
	 * @param string $requestMethod defines the name of the method in upper case
	 * @param array $body defines the set of value/key pairs that go into the body of the request
	 * @param array $additionalHeaders defines any additional headers to be attached to the request
	 * @return array result of the request
	 */
	public function coinbaseRequest($requestPath, $requestMethod, $body = null, $additionalHeaders = null)
	{
		$url = $this->_coinbase_url.$requestPath;
		$requestMethod = strtoupper($requestMethod)?? "GET";
		
		$headers = array(
				"Accept: application/json",
				"Content-Type: application/json",
				"X-CC-Api-Key: ".$this->_public_key,
				"X-CC-Version: ".$this->_coinbase_version
			);
		$headers = $additionalHeaders? array_merge($headers, $additionalHeaders) : $headers;
		
		$result = httpRequest($url, $requestMethod, $body, $headers);
		return $result;
	}
    

}

?>
