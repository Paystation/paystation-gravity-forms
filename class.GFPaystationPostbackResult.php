<?php
/*
* Class for dealing with a Paystation POSTBACK result.

* The postback from paystation is the full XML response with the confirmation
* of the transaction. Once this is recieved we know the final status of the transaction.
*/

// ============================================================================================================================================================
/**
 * Paystation POSTBACK result.
 */
// ============================================================================================================================================================
class GFPaystationPostbackResult {

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
	public $digitalOrderTime;           // <DigitalOrderTime>
	public $digitalReceiptTime;         // <DigitalReceiptTime>
	public $authentication = array('authType' => null, 'authStatus' => null, 'authSecurityLevel' => null, 'authHashToken' => null,
		'auth3dsId' => null, 'auth3dsElectronicCommerceIndicator' => null, 'auth3dsEnrolled' => null, 'auth3dsStatus' => null);

	public $isValid;

	// ====================================================================================================================================
	/**
	 * The constructor takes the response and then extracts the info and populates the properties of the class.
	 * @param string $postback ;
	 */
	// ====================================================================================================================================
	public function __construct($postback) {

		try {
			// Prevent XML injection attacks, and handle errors without warnings.
			$oldDisableEntityLoader = libxml_disable_entity_loader(true);
			$oldUseInternalErrors = libxml_use_internal_errors(true);

			// ----------------------------------
			// Call function to parse the xml.
			$xml = simplexml_load_string($postback);

			if ($xml === false) {
				$errmsg = '';

				foreach (libxml_get_errors() as $error) {
					$errmsg .= $error->message;
				}

				throw new Exception($errmsg);
			}

			// Check some of the things we know should be in the XML are there as an extra test it is valid.
			if ((isset($xml->ti)) && (isset($xml->MerchantSession)) && (isset($xml->merchant_ref))) {

				// ----------------------------------
				// Now set the properies of this class to the xml items.
				// We need to cast the items to the correct datatypes otherwise code using these later on can error or warn because the item is a simple XML element.
				$this->errorCode = (int) $xml->ec;
				$this->errorMessage = (string) $xml->em;
				$this->transactionId = (string) $xml->ti;
				$this->cardType = (string) $xml->ct;
				$this->merchantRef = (string) $xml->merchant_ref;
				$this->testMode = (string) $xml->tm;
				$this->merchantSession = (string) $xml->MerchantSession;
				$this->userAcquirerMerchantId = (string) $xml->UserAcquirerMerchantID;
				$this->purchaseAmount = (int) $xml->PurchaseAmount;          // Remember this will be cents, so int. Divide by 100 to get dollars and cents.
				$this->locale = (string) $xml->Locale;
				$this->returnReceiptNumber = (string) $xml->ReturnReceiptNumber;
				$this->shoppingTransactionNumber = (int) $xml->ShoppingTransactionNumber;
				$this->acqResponseCode = (string) $xml->AcqResponseCode;
				$this->qsiResponseCode = (string) $xml->QSIResponseCode;
				$this->cscResultCode = (string) $xml->CSCResultCode;
				$this->avsResultCode = (string) $xml->AVSResultCode;
				$this->transactionTime = (string) $xml->TransactionTime;
				$this->batchNumber = (int) $xml->BatchNumber;
				$this->authorizeId = (string) $xml->AuthorizeID;
				$this->username = (string) $xml->Username;
				$this->requestIP = (string) $xml->RequestIP;
				$this->requestUserAgent = (string) $xml->RequestUserAgent;
				$this->requestHttpReferrer = (string) $xml->RequestHttpReferrer;
				$this->paymentRequestTime = (string) $xml->PaymentRequestTime;
				$this->digitalOrderTime = (string) $xml->DigitalOrderTime;
				$this->digitalReceiptTime = (string) $xml->DigitalReceiptTime;

				// This is an array of sub items.
				$this->authentication = array('authType' => (string) $xml->Authentication->auth_Type,
					'authStatus' => (string) $xml->Authentication->auth_Status,
					'authSecurityLevel' => (string) $xml->Authentication->auth_SecurityLevel,
					'authHashToken' => (string) $xml->Authentication->auth_HashToken,
					'auth3dsId' => (string) $xml->Authentication->auth_3DSID,
					'auth3dsElectronicCommerceIndicator' => (string) $xml->Authentication->auth_3DSElectronicCommerceIndicator,
					'auth3dsEnrolled' => (string) $xml->Authentication->auth_3DSEnrolled,
					'auth3dsStatus' => (string) $xml->Authentication->auth_3DSStatus);

				$this->isValid = true;
			}
			else {

				$this->isValid = false;
			}

			// ----------------------------------
			// Restore old libxml settings
			libxml_disable_entity_loader($oldDisableEntityLoader);
			libxml_use_internal_errors($oldUseInternalErrors);
		} catch (Exception $e) {
			// Restore old libxml settings
			libxml_disable_entity_loader($oldDisableEntityLoader);
			libxml_use_internal_errors($oldUseInternalErrors);

			throw new GFPaystationException('Error parsing Paystation result request: ' . $e->getMessage());
		}
	}
}
