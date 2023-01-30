<?php
/*
* Classes for dealing with a Paystation payment REQUEST - this is the post to 3rd party gateway.
*/

// ============================================================================================================================================================
/**
 * Paystation 3rd Party Hosted payment request
 */
// ============================================================================================================================================================
class GFPaystationPayment {

	public $sslVerifyPeer;
	public $paystationId;         // Paystation account id form the main settings or that chosen by user input of the form. The PlaystationPlugin class will pass in the correct thing.
	public $gatewayId;            // The Gateway id.
	public $testMode;             // Set when in test mode.
	public $securityHash;         // Set to string of letters and numbers, used to help verify that postback is from paystation.
	public $merchantSession;      // Unique identification code for each transaction.
	public $amount;               // When set in this class it should be converted to cents (so a whole number / integer).
	public $currency;             // This is set from the gravity forms currency thing.
	public $merchantReference;    // The merchant reference, typlically the customer's email address.
	public $customerDetails;      // Optional 255 chars of customer detail information.
	public $orderDetails;         // Optional 255 chars of order detail informaiton.


	// ====================================================================================================================================
	/**
	 * populate members with defaults, and set account and environment information
	 *
	 * @param string $paystationId The paystation account id.
	 * @param string $gatewayId The gateway id.
	 * @param string $testMode Either Y or N to indicate if should run in test mode.
	 */
	// ====================================================================================================================================
	public function __construct($paystationId, $gatewayId, $testMode, $securityHash) {
		$this->sslVerifyPeer = true;
		$this->paystationId = $paystationId;
		$this->gatewayId = $gatewayId;
		$this->testMode = $testMode;
		$this->securityHash = $securityHash;
	}

	// ====================================================================================================================================
	/**
	 * Process a payment against Paystation 3rd part hosted gateway after validating.
	 */
	// ====================================================================================================================================
	public function processPayment() {
		$this->validate();
		return $this->sendPaymentRequest(GFPAYSTATION_API_URL);
	}

	// ====================================================================================================================================
	/**
	 * Validate the data members to ensure that sufficient and valid information has been given
	 * @throws GFPaystationException
	 */
	// ====================================================================================================================================
	protected function validate() {
		$errmsg = '';

		// According to our spec the following need to be set (i.e. required).
		// - pstn_pi : Paystation Id.
		// - pstn_gi : Gateway Id.
		// - pstn_ms : Merchant session.
		// - pstn_am : Amount - integer only.
		// - pstn_nr : t ot T - hard coded when payment is done.
		if (strlen($this->paystationId) === 0) {
			$errmsg .= "paystationId cannot be empty.\n";
		}

		if (strlen($this->gatewayId) === 0) {
			$errmsg .= "gatewayId cannot be empty.\n";
		}

		if (strlen($this->merchantSession) === 0) {
			$errmsg .= "merchantSession cannot be empty.\n";
		}

		// The gateway requires that the amount is in cents, the amount will have been converted
		// by this point to that so double-check is numeric and is integer.
		if (!is_numeric($this->amount) || $this->amount <= 0) {
			$errmsg .= "amount must be given as a number in dollars only.\n";
		}
		else if (!is_int($this->amount)) {
			$errmsg .= "amount must be an integer (cents).\n";
		}

		// If error then throw it so is displayed to the user by code up the line.
		if (strlen($errmsg) > 0) {
			throw new GFPaystationException($errmsg);
		}
		else {
			// No errors so the payment will go ahead, we need though to ensure that the parameters which will be sent
			// to the paystation gateway are not longer than what is accepted just to ensure that no issues.

			// The rules are...
			// - Merchant session must not be more than 50 chars.
			// - The merchant reference must not be more than 64 chars.
			// - The customerDetails, which is optional, cannot be more than 255.
			// - The orderDetails, which is optional, cannot be more than 255.
			if (strlen($this->merchantSession) > 50) {
				$this->merchantSession = substr($this->merchantSession, 0, 50);
			}

			if (strlen($this->merchantReference) > 64) {
				$this->merchantReference = substr($this->merchantReference, 0, 64);
			}

			if (strlen($this->customerDetails) > 255) {
				$this->customerDetails = substr($this->customerDetails, 0, 255);
			}

			if (strlen($this->orderDetails) > 255) {
				$this->orderDetails = substr($this->orderDetails, 0, 255);
			}

			// ----------------------------------
			// Also ensure that only allowed characters are included in the fields which can be populated from user input, these are...
			// Only the merchantReference, customerDetails, and orderDetails need the replacment. The biggest issue is quote characters.
			// 0123456789abcdefghijklmnopqrstuvwyxzABCDEFGHIJKLMNOPQRSTUVWYXZ@_ -.#:;*(),+[]/|
			$this->merchantReference = preg_replace('/[^a-z0-9@\_\ \-\.\,\(\)\[\]\:\;\#\+\/\|]*/i', '', $this->merchantReference);
			$this->customerDetails = preg_replace('/[^a-z0-9@\_\ \-\.\,\(\)\[\]\:\;\#\+\/\|]*/i', '', $this->customerDetails);
			$this->orderDetails = preg_replace('/[^a-z0-9@\_\ \-\.\,\(\)\[\]\:\;\#\+\/\|]*/i', '', $this->orderDetails);
		}
	}

	// ====================================================================================================================================
	/**
	 * This function sends the payment request by posting the information.
	 * @param paystationUrl is the url to the paystation gateway.
	 * @return XMLstring or false if failure.
	 */
	// ====================================================================================================================================
	protected function sendPaymentRequest($paystationUrl) {
		// Put the parameters for the post in to an associative array.
		// First the required things.
		$paramsArray = array('paystation' => '_empty',
			'pstn_pi' => $this->paystationId,
			'pstn_gi' => $this->gatewayId,
			'pstn_ms' => $this->merchantSession,
			'pstn_mr' => $this->merchantReference,
			'pstn_am' => $this->amount,
			'pstn_nr' => 't',
			'pstn_cu' => $this->currency
		);

		// Now add the optional things.
		if ($this->testMode == 'Y') {
			$paramsArray['pstn_tm'] = 't';
		}

		if ($this->customerDetails) {
			$paramsArray['pstn_mc'] = $this->customerDetails;
		}

		if ($this->orderDetails) {
			$paramsArray['pstn_mo'] = $this->orderDetails;
		}

		// Generate url encoded query string from the array.
		$formattedData = http_build_query($paramsArray);

		// Put together context for the POST request using the encoded params.
		$contextOptions = array(
			'http' => array(
				'method' => 'POST',
				'header' => "Content-type: application/x-www-form-urlencoded\r\n" . "Content-Length: " . strlen($formattedData) . "\r\n",
				'content' => $formattedData
			)
		);

		// Create a stream context, open for reading the response, then place the contents in to the response XML variable.
		$ctx = stream_context_create($contextOptions);
		$fp = @fopen($paystationUrl, 'r', false, $ctx);
		$responseXML = @stream_get_contents($fp);

		// Create new response object, and get it to load its properies from the response XML.
		$response = new GFPaystationPaymentResponse();
		$response->loadResponseXML($responseXML);

		// Return the PaystationPaymentResponse object.
		return $response;
	}
}

// ============================================================================================================================================================
/**
 * Paystation payment request response
 * (first box in the gateway row in the transaction process graph in the API document).
 */
// ============================================================================================================================================================
class GFPaystationPaymentResponse {

	// Declare properites for all the other bits which can come back via the xml, even though we don't care about much of them.
	public $errorCode;                  // <ec> - there is also the <PaystationErrorCode> tag, but is the same value so don't have 2 properties of this class.
	public $errorMessage;               // <em> - also there is <PaystationErrorMessage> tag, but is the same so don;t create 2 properies of the class.
	public $transactionId;              // <ti> - there is also the <TransactionID> and <PaystationTransactionID>, but don;t want multiple properites in the class.
	public $cardType;                   // <ct> - Is also <cardtype>, but again don't need duplicate properties.
	public $merchantRef;                // <merchant_ref> - is also <MerchantReference>, but is the same so don't include 2 properties.
	public $testMode;                   // <tm> - is also <TransactionMode>, again is same so we don't create 2 properties.
	public $merchantSession;            // <MerchantSession>
	public $userAcquirerMerchantId;     // <UserAcquirerMerchantID>
	public $purchaseAmount;             // <PurchaseAmount>
	public $locale;                     // <Locale>
	public $returnReceiptNumber;        // <ReturnReceiptNumber>
	public $shoppingTransactionNumber;  // <ShoppingTransactionNumber>
	public $acqResponseCode;            // <AcqResponseCode>
	public $qsiResponseCode;            // <QSIResponseCode>
	public $cscResultCode;              // <CSCResultCode>
	public $avsResultCode;              // <AVSResultCode>
	public $transactionTime;            // <TransactionTime>
	public $batchNumber;                // <BatchNumber>
	public $authorizeId;                // <AuthorizeID>
	public $username;                   // <Username>
	public $requestIP;                  // <RequestIP>
	public $requestUserAgent;           // <RequestUserAgent>
	public $requestHttpReferrer;        // <RequestHttpReferrer>
	public $paymentRequestTime;         // <PaymentRequestTime>
	public $digitalOrder;               // <DigitalOrder>           // Note this only appears in the request result and we redirect the browser to this.
	public $digitalOrderTime;           // <DigitalOrderTime>
	public $digitalReceiptTime;         // <DigitalReceiptTime>


	// ====================================================================================================================================
	/**
	 * load Paystation response as XML string
	 * @param string $responseXML will be xml - yes but this will be the inital response, not the postback.
	 * @throws GFPaystationException
	 */
	// ====================================================================================================================================
	public function loadResponseXML($responseXML) {

		try {
			// prevent XML injection attacks, and handle errors without warnings
			$oldDisableEntityLoader = libxml_disable_entity_loader(true);
			$oldUseInternalErrors = libxml_use_internal_errors(true);

			// Create xml object, false will be returend if there was a pasing error.
			$xml = simplexml_load_string($responseXML);

			// So if parse failed then build an error message.
			if ($xml === false) {
				$errmsg = '';

				foreach (libxml_get_errors() as $error) {
					$errmsg .= $error->message;
				}

				throw new Exception($errmsg);
			}


			// If the error code is zero then there was no error, the transaction was sucessful.
			if ($xml->ec === '0') {
				$this->success = true;
			}
			else {
				$this->success = false;
			}

			// Also set the paymentURL to the digital order url.
			$this->digitalOrder = (string) $xml->DigitalOrder;

			// ----------------------------------
			// Now set the other properies of this class to the xml items.
			// We really only use the transactionId and the error message in the plugin.
			$this->errorCode = $xml->ec;
			$this->errorMessage = $xml->em;
			$this->transactionId = $xml->ti;
			$this->cardType = $xml->ct;
			$this->merchantRef = $xml->merchant_ref;
			$this->testMode = $xml->tm;
			$this->merchantSession = $xml->MerchantSession;
			$this->userAcquirerMerchantId = $xml->UserAcquirerMerchantID;
			$this->purchaseAmount = (int) $xml->PurchaseAmount;          // Remember the paystation gateway deals with cents so this is an integer (divide by 100 to get float of dollars and cents).
			$this->locale = $xml->Locale;
			$this->returnReceiptNumber = $xml->ReturnReceiptNumber;
			$this->shoppingTransactionNumber = $xml->ShoppingTransactionNumber;
			$this->acqResponseCode = $xml->AcqResponseCode;
			$this->qsiResponseCode = $xml->QSIResponseCode;
			$this->cscResultCode = $xml->CSCResultCode;
			$this->avsResultCode = $xml->AVSResultCode;
			$this->transactionTime = $xml->TransactionTime;
			$this->batchNumber = $xml->BatchNumber;
			$this->authorizeId = $xml->AuthorizeID;
			$this->username = $xml->Username;
			$this->requestIP = $xml->RequestIP;
			$this->requestUserAgent = $xml->RequestUserAgent;
			$this->requestHttpReferrer = $xml->RequestHttpReferrer;
			$this->paymentRequestTime = $xml->PaymentRequestTime;
			$this->digitalOrderTime = $xml->DigitalOrderTime;
			$this->digitalReceiptTime = $xml->DigitalReceiptTime;

			// restore old libxml settings
			libxml_disable_entity_loader($oldDisableEntityLoader);
			libxml_use_internal_errors($oldUseInternalErrors);
		} catch (Exception $e) {
			// restore old libxml settings
			libxml_disable_entity_loader($oldDisableEntityLoader);
			libxml_use_internal_errors($oldUseInternalErrors);

			throw new GFPaystationException('Error parsing Paystation gateway response: ' . $e->getMessage());
		}
	}
}
