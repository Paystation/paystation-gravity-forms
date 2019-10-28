<?php
/*
* Class for dealing with a Paystation GET request result
* i.e. the redirect back to theis site after payment was completed.
*
* Note that at this point the results are not 100% confirmed, that happens
* when then postback is done. Here we just check the status and if indicates
* failure then we go to the failure URL (if successful we let the gravity forms submit).
*/

/**
 * Paystation Return (URL) result
 */
class GFPaystationReturnResult {

	public $transactionId;      // ti.
	public $errorCode;          // ec.
	public $errorMessage;       // em.
	public $merchantSession;    // ms.
	public $amount;             // am (is an integer).
	public $futurePayToken;     // futurepaytoken.

	public $isValid;

	/**
	 * The constructor takes the result and then extracts the info and populates the properties of the class.
	 * @param string $result ;
	 */
	public function __construct($result) {

		// The result is simply the query string, we need to decode in to an array.
		parse_str($result, $params);

		if ($params) {
			// Check it contains expected paystation items, it not then it is not a valid paystation response.
			if ((isset($params['ti'])) && (isset($params['ec'])) && (isset($params['em'])) && (isset($params['ms']))) {
				$this->transactionId = $params['ti'];
				$this->errorCode = $params['ec'];
				$this->errorMessage = $params['em'];
				$this->merchantSession = $params['ms'];
				$this->amount = isset($params['am']) ? $params['am'] : null;
				$this->futurePayToken = isset($params['futurepaytoken']) ? $params['futurepaytoken'] : null;

				$this->isValid = true;
			}
			else {
				$this->isValid = false;
			}
		}
	}
}
