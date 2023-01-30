<?php

/**
 * custom exception types
 */
class GFPaystationException extends Exception {
}

class GFPaystationCurlException extends Exception {
}

// ============================================================================================================================================================
/**
 * This class manages the plugin.
 */
// ============================================================================================================================================================
class GFPaystationPlugin {

	public $urlBase;                    // String: base URL path to files in plugin
	public $options;                    // Array of plugin options

	private $validationMessage = '';    // The validation message.
	private $feed = null;                // Current feed mapping form fields to payment fields.
	private $formData = null;            // Current form data collected from form.

	// ====================================================================================================================================
	/**
	 * Static method for getting the instance of this singleton object
	 *
	 * @return GFPaystationPlugin
	 */
	// ====================================================================================================================================
	public static function getInstance() {
		static $instance = null;

		if (is_null($instance)) {
			$instance = new self();
		}

		return $instance;
	}

	// ====================================================================================================================================
	/**
	 * Constructor
	 */
	// ====================================================================================================================================
	private function __construct() {

		// Grab options, setting new defaults for any that are missing
		$this->initOptions();

		// Record plugin URL base
		$this->urlBase = plugin_dir_url(__FILE__);

		add_action('init', array($this, 'init'));
		add_action('parse_request', array($this, 'processPaystationReturn'));        // Process paystation return
		add_action('wp', array($this, 'processFormConfirmation'), 5);                // Process redirect to GF confirmation
	}

	// ====================================================================================================================================
	/**
	 * initialise plug-in options, handling undefined options by setting defaults
	 */
	// ====================================================================================================================================
	private function initOptions() {

		static $defaults = array(
			'paystationId' => '',
			'gatewayId' => '',
			'testMode' => 'Y',     // Set test mode to Yes by default. It will be set to No when a payment is made if test mode if off in the settings.
			'securityHash' => '',
			'sslVerifyPeer' => true,
		);

		$this->options = (array) get_option(GFPAYSTATION_PLUGIN_OPTIONS);

		if (count(array_diff_assoc($defaults, $this->options)) > 0) {
			$this->options = array_merge($defaults, $this->options);
			update_option(GFPAYSTATION_PLUGIN_OPTIONS, $this->options);
		}
	}

	// ====================================================================================================================================
	/**
	 * handle the plugin's init action
	 */
	// ====================================================================================================================================
	public function init() {
		// Hook into Gravity Forms and specify the functions which are to be called when these hooks are triggered.
		add_filter('gform_validation', array($this, "gformValidation"));
		add_filter('gform_validation_message', array($this, "gformValidationMessage"), 10, 2);
		add_filter('gform_confirmation', array($this, "gformConfirmation"), 1000, 4);
		add_filter('gform_disable_post_creation', array($this, 'gformDelayPost'), 10, 3);

		// The first two will not work from version 1.7, the third will work for all versions as has code in there to check the version number.
		add_filter('gform_disable_user_notification', array($this, 'gformDelayUserNotification'), 10, 3);
		add_filter('gform_disable_admin_notification', array($this, 'gformDelayAdminNotification'), 10, 3);
		add_filter('gform_disable_notification', array($this, 'gformDelayNotification'), 10, 4);

		add_filter('gform_custom_merge_tags', array($this, 'gformCustomMergeTags'), 10, 4);
		add_filter('gform_replace_merge_tags', array($this, 'gformReplaceMergeTags'), 10, 7);

		// Register custom post types.
		$this->registerTypeFeed();

		if (is_admin()) {
			// kick off the admin handling
			new GFPaystationAdmin($this);
		}
	}

	// ====================================================================================================================================
	/**
	 * register custom post type for Paystation form field mappings
	 */
	// ====================================================================================================================================
	protected function registerTypeFeed() {
		// register the post type
		register_post_type(GFPAYSTATION_TYPE_FEED, array(
			'labels' => array(
				'name' => __('Gravity Forms Paystation (3 party hosted) Feeds'),
				'singular_name' => __('Gravity Forms Paystation (3 party hosted) Feed'),
				'add_new_item' => __('Add New Gravity Forms Paystation (3 party hosted) Feed'),
				'edit_item' => __('Edit Gravity Forms Paystation (3 party hosted) Feed'),
				'new_item' => __('New Gravity Forms Paystation (3 party hosted) Feed'),
				'view_item' => __('View Gravity Forms Paystation (3 party hosted) Feed'),
				'search_items' => __('Search Gravity Forms Paystation (3 party hosted) Feeds'),
				'not_found' => __('No Gravity Forms Paystation (3 party hosted) feeds found'),
				'not_found_in_trash' => __('No Gravity Forms Paystation (3 party hosted) feeds found in Trash'),
				'parent_item_colon' => __('Parent Gravity Forms Paystation (3 party hosted) feed'),
			),
			'description' => 'Paystation Feeds, as a custom post type',
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => false,
			'hierarchical' => false,
			'has_archive' => false,
			'supports' => array('null'),
			'rewrite' => false,
		));
	}

	// ====================================================================================================================================
	/**
	 * filter whether post creation from form is enabled (yet)
	 * @param bool $is_disabled
	 * @param array $form
	 * @param array $lead
	 * @return bool
	 */
	// ====================================================================================================================================
	public function gformDelayPost($is_disabled, $form, $lead) {

		$feed = $this->getFeed($form['id']);

		if ($feed && $feed->DelayPost) {
			$is_disabled = true;
		}

		return $is_disabled;
	}

	// ====================================================================================================================================
	/**
	 * Filter whether form triggers autoresponder (yet)
	 * @param bool $is_disabled
	 * @param array $form
	 * @param array $lead
	 * @return bool
	 */
	// ====================================================================================================================================
	public function gformDelayUserNotification($is_disabled, $form, $lead) {

		$feed = $this->getFeed($form['id']);

		if ($feed && $feed->DelayAutorespond) {
			$is_disabled = true;
		}

		return $is_disabled;
	}

	// ====================================================================================================================================
	/**
	 * Filter whether form triggers admin notification (yet)
	 * @param bool $is_disabled
	 * @param array $form
	 * @param array $lead
	 * @return bool
	 */
	// ====================================================================================================================================
	public function gformDelayAdminNotification($is_disabled, $form, $lead) {

		$feed = $this->getFeed($form['id']);

		if ($feed && $feed->DelayNotify) {
			$is_disabled = true;
		}

		return $is_disabled;
	}

	// ====================================================================================================================================
	/**
	 * filter whether form triggers admin notification (yet)
	 * @param bool $is_disabled
	 * @param array $notification
	 * @param array $form
	 * @param array $lead
	 * @return bool
	 */
	// ====================================================================================================================================
	public function gformDelayNotification($is_disabled, $notification, $form, $lead) {

		$feed = $this->getFeed($form['id']);

		if ($feed) {
			switch (rgar($notification, 'type')) {
				// old "user" notification
				case 'user':
					if ($feed->DelayAutorespond) {
						$is_disabled = true;
					}
					break;

				// old "admin" notification
				case 'admin':
					if ($feed->DelayNotify) {
						$is_disabled = true;
					}
					break;

				// new since 1.7, add any notification you like
				default:
					if (trim($notification['to']) == '{admin_email}') {
						if ($feed->DelayNotify) {
							$is_disabled = true;
						}
					}
					else {
						if ($feed->DelayAutorespond) {
							$is_disabled = true;
						}
					}
					break;
			}
		}

		return $is_disabled;
	}


	// ====================================================================================================================================
	/**
	 * process a form validation filter hook; if can find a total, attempt to bill it
	 * @param array $data an array with elements is_valid (boolean) and form (array of form elements)
	 * @return array
	 */
	// ====================================================================================================================================
	public function gformValidation($data) {

		// Make sure all other validations passed.
		if ($data['is_valid']) {

			$feed = $this->getFeed($data['form']['id']);

			if ($feed) {
				$formData = $this->getFormData($data['form']);

				// Make sure form hasn't already been submitted / processed.
				if ($this->hasFormBeenProcessed($data['form'])) {
					$data['is_valid'] = false;
					$this->validationMessage .= "Payment already submitted and processed - please close your browser window.\n";
				}

				// Make sure that we have something to bill.
				if (!$formData->hasPurchaseFields()) {
					$data['is_valid'] = false;
					$this->validationMessage .= "This form has no products or totals; unable to process transaction.\n";
				}
			}
		}

		return $data;
	}

	// ====================================================================================================================================
	/**
	 * Alter the validation message to one that we want, wrap it in a div with a validation error message.
	 * @param string $msg
	 * @param array $form
	 * @return string
	 */
	// ====================================================================================================================================
	public function gformValidationMessage($msg, $form) {

		if ($this->validationMessage) {
			$msg = "<div class='validation_error'>" . nl2br($this->validationMessage) . "</div>";
		}

		return $msg;
	}

	// ====================================================================================================================================
	/**
	 * This function handles the form confirmation by first trying to make the payment using paystation, then either returning the url
	 * to redirect the user's browser to so they see the payment screen, or the error message is returned.
	 * @param mixed $confirmation text or redirect for form submission
	 * @param array $form the form submission data
	 * @param array $entry the form entry
	 * @param bool $ajax form submission via AJAX
	 * @return mixed
	 */
	// ====================================================================================================================================
	public function gformConfirmation($confirmation, $form, $entry, $ajax) {
		// Return if not the current form.
		if (RGForms::post('gform_submit') != $form['id']) {
			return $confirmation;
		}

		// Get feed mapping form fields to payment request, return if not set as need the feed to actually do anything.
		$feed = $this->getFeed($form['id']);
		if (!$feed) {
			return $confirmation;
		}

		// --------------------------------------
		// Record payment gateway.
		gform_update_meta($entry['id'], 'payment_gateway', 'gfpaystation');

		// --------------------------------------
		// Get the data posted from the form and build the payment request by creating a Paystation payment request object,
		// setting it's properties, then calling the processPayment() method.
		$formData = $this->getFormData($form);
		$paymentReq = null;

		// If a paystation override id has been specified on the form from dropdown to override etc then create new payment object with that Paystation id
		// Otherwise default to the normal paystation_id in the main settings. This feature allows money to be paid in to different paystation accounts
		// based on user selection of something on the form such as branch, region, country etc.
		if ((isset($formData->PaystationOverrideId)) && ($formData->PaystationOverrideId)) {
			$paymentReq = new GFPaystationPayment($formData->PaystationOverrideId, $this->options['gatewayId'], $this->options['testMode'], $this->securityHash);
		}
		else {
			$paymentReq = new GFPaystationPayment($this->options['paystationId'], $this->options['gatewayId'], $this->options['testMode'], $this->securityHash);
		}

		$paymentReq->amount = (int) bcmul($formData->total, 100); // We multiply by 100 because the amount sent to the gateawy must be an int, so cents not a float.
		$paymentReq->currency = GFCommon::get_currency(); // Get the currency from gravity forms.
		$paymentReq->merchantSession = $this->buildSessionId($entry['id']); // Call function in this class to create the merchantSession id.

		$paymentReq->merchantReference = $formData->MerchantReference;
		$paymentReq->customerDetails = $formData->CustomerDetails;
		$paymentReq->orderDetails = $formData->OrderDetails;

		// --------------------------------------
		// Try making the payment, if successful the redirect the user to the payment URL, if not then catch error.
		try {
			$response = $paymentReq->processPayment();  // Call processPayment function in PaystationPayment class, it will do the POST to the paystation gateway.

			// If digitalOrder is populated then there were no issues with the transaction request, so update the status of the
			// the lead record and then set the confirmation to the url of the payment screen.
			if ($response->digitalOrder) {
				GFFormsModel::update_lead_property($entry['id'], 'payment_status', 'Processing');
				GFFormsModel::update_lead_property($entry['id'], 'transaction_id', $response->transactionId);

				// NB: GF handles redirect via JavaScript if headers already sent, or AJAX.
				$confirmation = array('redirect' => $response->digitalOrder);
			}
			else {
				// There was an error with the payment initiation, an error message will be set so
				// throw it so that the exception code below will update the status to failed
				// and cause the user to see the error emssage.
				throw new GFPaystationException($response->errorMessage);
			}
		} catch (GFPaystationException $e) {
			// Update the status to failed.
			GFFormsModel::update_lead_property($entry['id'], 'payment_status', 'Failed');

			// If the there is a failure url set the confirmation to the failure message so it is displayed to the user.
			// When there is an error like this the form is not displayed, so we need to wrap the error div in the gform
			// wrapper div in order for the validation error style to actually be applied.
			$confirmation = "<div class='gform_wrapper'><div class='validation_error'>" . nl2br($e->getMessage()) . "</div></div>";
		}

		return $confirmation;
	}

	// ====================================================================================================================================
	/**
	 * This function takes the entryId and prepends a dash and then the time in seconds since Jan 1 1970
	 * to make a unique value which is required for the merchant session. It will shoudl ensure that any
	 * tests done from a test environment will not prevent live transactions because the merchant session has already been used.
	 * @param int $entryId The lead entryId.
	 * @return string The merchantSession for use in the payment is returned.
	 */
	// ====================================================================================================================================
	protected function buildSessionId($entryId) {

		$merchantSession = $entryId . "-" . time();

		return $merchantSession;
	}

	// ====================================================================================================================================
	/**
	 * This function takes the merchant session like above and splits it, and returns the
	 * the lead "entry id" so that it can be used to load the record from the database.
	 * @param string $merchantSession The merchant session returned from the paystation gateway.
	 * @return int $leadId The id of the lead record is returned.
	 */
	// ====================================================================================================================================
	protected function getLeadIdFromMerchantSession($merchantSession) {

		$sessionParts = explode('-', $merchantSession);
		$leadId = $sessionParts[0];

		return $leadId;
	}

	// ====================================================================================================================================
	/**
	 * Return from Paystation 3 party hosted screens, retreive and process payment result and redirect to form
	 * This code also deals with the postback from the paystation system (which happens in the background).
	 */
	// ====================================================================================================================================
	public function processPaystationReturn() {

		// Pull out the query part of the url as we need this to see if a paystation return.
		$parts = parse_url($_SERVER['REQUEST_URI']);
		$query = isset($parts['query']) ? $parts['query'] : '';

		// If have query parts.
		if ($query) {

			// If the query part contains the paystation return string, then we are probably getting
			// the redirect back from paystation after the payment has been done.
			if (strpos($query, GFPAYSTATION_RETURN) !== false) {

				try {
					// Create object to deal with the return result passing the query string in to it for parsing.
					$resultReq = new GFPaystationReturnResult(stripslashes($query));

					// If received valid paystation response (must contain paystation response GET variables).
					if ($resultReq->isValid) {

						// Load the lead and the form as we need some of the details of these.
						$leadId = $this->getLeadIdFromMerchantSession($resultReq->merchantSession);
						$lead = GFFormsModel::get_lead($leadId);
						$form = GFFormsModel::get_form_meta($lead['form_id']);

						// Check to see if the payment was successful.
						// The redirect back is not the final say on the matter, the postback is so we don't
						// update anything in the database until the postback has been recieved.

						// If failed then redirect the user to the failure URL, otherwise let the gravity form normal submit happen
						// and take the user to wherever the gravity form is supposed to go on success.
						if ($resultReq->errorCode != '0') {

							$feed = $this->getFeed($form['id']);

							// Redirect to the fail url for the form if there is one then exit so the code below does not
							// execute which is the redirect when successful or no fail url specified.
							if ($feed->UrlFail) {
								wp_redirect($feed->UrlFail);
								exit;
							}
						}

						// Redirect to Gravity Forms page, passing form and lead IDs.
						// Whatever the developer specified to happen after the form was submitted will happen.
						$query = "form_id={$lead['form_id']}&lead_id={$lead['id']}";
						$query .= "&hash=" . wp_hash($query);

						wp_redirect(add_query_arg(array(GFPAYSTATION_RETURN => base64_encode($query)), $lead['source_url']));
						exit;
					}
				}

					// If there is an exception with this then there is not much more we can do than echo it out.
					// We may not have the id of the form so cannot update its status to failed, nor do I know a way
					// for us to set something in the url to get wordpress to display the exception to the user in a nice way.
				catch (GFPaystationException $e) {
					echo nl2br(htmlspecialchars($e->getMessage()));
					exit;
				}
			}
			else if (strpos($query, GFPAYSTATION_POSTBACK) !== false) {
				// In order to help ensure that the postback XML came from Paystation, ensure that the value of the GFPAYSTATION_POSTBACK
				// in the querystring is equal to that of the security hash saved in the plugin options.
				$pluginOptions = (array) get_option(GFPAYSTATION_PLUGIN_OPTIONS);
				parse_str($query, $params);

				if ((isset($params[GFPAYSTATION_POSTBACK])) && ($params[GFPAYSTATION_POSTBACK] == $pluginOptions['securityHash'])) {
					try {
						// Get the contents of the XML packet that has been POSTed back from Paystation.
						$postback = file_get_contents("php://input");

						// Create object to deal with the postback passing the postback as it will parse and put xml in to properties of the class.
						$resultReq = new GFPaystationPostbackResult($postback);

						// If got valid paystation postback response.
						if ($resultReq->isValid) {

							// Load the lead and the form as we need some of the details of these.
							$leadId = $this->getLeadIdFromMerchantSession($resultReq->merchantSession);
							$lead = GFFormsModel::get_lead($leadId);

							if (!$lead) {
								http_response_code(400);
								exit("Lead not found");
							}

							$form = GFFormsModel::get_form_meta($lead['form_id']);
							$feed = $this->getFeed($form['id']);

							// Check to see if the payment was successful
							// Because this time it is from the postback we know 100% for sure that the payment was successul or not
							// so we must update the status of the lead to sucess or failure and also send any responses.
							if ($resultReq->errorCode === '0') {

								// Success (i.e. error code is 0). Update some details on the lead.
								$lead['payment_status'] = 'Approved';

								// NOTE: the date returned is New Zealand date and time, so in order for payment date to match the date_created of the lead
								// and also display correct in the admin part of the site, we need to convert the date to a timestamp then back out to UTC datetime.
								$nz_timezone = new DateTimeZone('Pacific/Auckland');
								$nz_datetime = new DateTime($resultReq->transactionTime, $nz_timezone);
								$utc_datetime = gmdate('Y-m-d H:i:s', strtotime($nz_datetime->format('r')));

								$lead['payment_date'] = $utc_datetime;

								// Set remaining details.
								$lead['payment_amount'] = $resultReq->purchaseAmount ? ($resultReq->purchaseAmount / 100) : 0; // Need to convert back to float for saving in record.
								$lead['transaction_id'] = $resultReq->transactionId;
								$lead['transaction_type'] = 1;
								GFAPI::update_entry($lead);

								// Trigger hook if one is present.
								do_action('gfpaystation_post_payment', $lead, $form);

								// Record entry's unique ID in database.
								gform_update_meta($lead['id'], 'gfpaystation_unique_id', GFFormsModel::get_form_unique_id($form['id']));

								// Now we need to send the email notifications etc since it is all confirmed.
								if (!$lead['is_fulfilled']) {

									if ($feed->DelayPost) {
										GFFormsModel::create_post($form, $lead);
									}

									if ($feed->DelayNotify || $feed->DelayAutorespond) {
										$this->sendDeferredNotifications($feed, $form, $lead);
									}

									GFFormsModel::update_lead_property($lead['id'], 'is_fulfilled', true);
								}

								exit('Payment success');
							}
							else {

								// Failure, error code is not 0 so update the status of the lead to Failed.
								$lead['payment_status'] = 'Failed';
								GFAPI::update_entry($lead);

								// Also set the error message for the lead so that this can be included with an error message
								// to the user if the developer has not specified a failure URL (which means the form will re-load).
								gform_update_meta($lead['id'], 'gfpaystation_error', $resultReq->errorMessage);

								// Trigger hook if there is one.
								do_action('gfpaystation_post_payment_fail', $lead, $form);
								exit('Payment failed');
							}

							// No redirects are done as the result of this is not seen by the user.
							// The home page of the site is displayed so that will satisfy the paystation system that the response was received.
						}
					}

						// Because the output from this code is only ever seen by the paystation system, echoing the error message etc
						// will be of not use; because a page will be rendered the paystation system will think that the resaponse was
						// successfully received, so in this case if there is an error we case an error 500 which means the paystation
						// system will try again a few times until successful and if not then paystation will know there was an issue.
					catch (GFPaystationException $e) {
						header('HTTP/1.1 500 Internal Server Error', true, 500);
						exit;
					}
				}
				else {
					// Not valid from paystation so also error 500 (just in case was sent from paystation but security hash no longer matches
					// it will be recorded in the paystation system that there was an error).
					header('HTTP/1.1 500 Internal Server Error', true, 500);
					exit;
				}
			}
		}
	}

	// ====================================================================================================================================
	/**
	 * send deferred notifications, handling pre- and post-1.7.0 worlds
	 * @param array $feed
	 * @param array $form the form submission data
	 * @param array $lead the form entry
	 */
	// ====================================================================================================================================
	protected function sendDeferredNotifications($feed, $form, $lead) {

		// Get the version of gravity forms.
		// NOTE: if there is not a valid license key entered for gravity forms the version information will be empty.
		$gfversion = GFCommon::get_version_info();

		if (version_compare($gfversion['version'], '1.7.0', '<')) {

			// pre-1.7.0 notifications
			//+ the code will also come in to this block if Gravity Forms is not proppery licenced
			//+ which might be an issue during development and testing if a licence has not been purchased.

			if ($feed->DelayNotify) {
				GFCommon::send_admin_notification($form, $lead);
			}
			if ($feed->DelayAutorespond) {
				GFCommon::send_user_notification($form, $lead);
			}
		}
		else {

			$notifications = GFCommon::get_notifications_to_send("form_submission", $form, $lead);

			foreach ($notifications as $notification) {

				switch (rgar($notification, 'type')) {
					// old "user" notification
					case 'user':
						if ($feed->DelayAutorespond) {
							GFCommon::send_notification($notification, $form, $lead);
						}
						break;

					// old "admin" notification
					case 'admin':
						if ($feed->DelayNotify) {
							GFCommon::send_notification($notification, $form, $lead);
						}
						break;

					// new since 1.7, add any notification you like
					default:
						if (trim($notification['to']) == '{admin_email}') {

							if ($feed->DelayNotify) {
								GFCommon::send_notification($notification, $form, $lead);
							}
						}
						else {
							if ($feed->DelayAutorespond) {
								GFCommon::send_notification($notification, $form, $lead);
							}
						}
						break;
				}
			}
		}
	}

	// ====================================================================================================================================
	/**
	 * This simply checks if the confirmation page needs to be displayed and if so then does.
	 */
	// ====================================================================================================================================
	public function processFormConfirmation() {

		// check for redirect to Gravity Forms page with our encoded parameters.
		if (isset($_GET[GFPAYSTATION_RETURN])) {

			// Decode the encoded form and lead parameters.
			parse_str(base64_decode($_GET[GFPAYSTATION_RETURN]), $query);

			// Make sure we have a match.
			if (wp_hash("form_id={$query['form_id']}&lead_id={$query['lead_id']}") == $query['hash']) {

				// stop WordPress SEO from stripping off our query parameters and redirecting the page
				global $wpseo_front;

				if (isset($wpseo_front)) {
					remove_action('template_redirect', array($wpseo_front, 'clean_permalink'), 1);
				}

				// Load form and lead data.
				$form = GFFormsModel::get_form_meta($query['form_id']);
				$lead = GFFormsModel::get_lead($query['lead_id']);

				// Get confirmation page.
				if (!class_exists('GFFormDisplay')) {
					require_once(GFCommon::get_base_path() . '/form_display.php');
				}

				$confirmation = GFFormDisplay::handle_confirmation($form, $lead, false);

				// ------------------------------
				// If the confirmation is not an array (which means its just a message), then check to see if the status
				// of the payment is failed. If so then change the confirmnation message to an error div with a message that
				// the playment failed along with the error message from Paystation which is stored in the meta table for the lead.

				// In order for this to work the postback needs to have happened before this code has loaded, which should nornally be the case
				// given that paystation waits 10 seconds before redirecting back to this site.
				if ((!is_array($confirmation)) && ($lead['payment_status'] == 'Failed')) {
					$confirmation = "<div class='gform_wrapper'><div class='validation_error'>Sorry the payment failed : " . gform_get_meta($lead['id'], 'gfpaystation_error') . "</div></div>";
				}

				// ------------------------------
				// Preload the GF submission, ready for processing the confirmation message.
				GFFormDisplay::$submission[$form['id']] = array(
					'is_confirmation' => true,
					'confirmation_message' => $confirmation,
					'form' => $form,
					'lead' => $lead,
				);

				// ------------------------------
				// If it's a redirection (page or other URL) then do the redirect now.
				if (is_array($confirmation) && isset($confirmation['redirect'])) {
					header('Location: ' . $confirmation['redirect']);
					exit;
				}
			}
		}
	}

	// ====================================================================================================================================
	/**
	 * add custom merge tags
	 * @param array $merge_tags
	 * @param int $form_id
	 * @param array $fields
	 * @param int $element_id
	 * @return array
	 */
	// ====================================================================================================================================
	public function gformCustomMergeTags($merge_tags, $form_id, $fields, $element_id) {

		if ($form_id && $this->getFeed($form_id)) {
			$merge_tags[] = array('label' => 'Transaction ID', 'tag' => '{transaction_id}');
			$merge_tags[] = array('label' => 'Payment Amount', 'tag' => '{payment_amount}');
		}

		return $merge_tags;
	}

	// ====================================================================================================================================
	/**
	 * replace custom merge tags
	 * @param string $text
	 * @param array $form
	 * @param array $lead
	 * @param bool $url_encode
	 * @param bool $esc_html
	 * @param bool $nl2br
	 * @param string $format
	 * @return string
	 */
	// ====================================================================================================================================
	public function gformReplaceMergeTags($text, $form, $lead, $url_encode, $esc_html, $nl2br, $format) {

		$tags = array(
			'{transaction_id}',
			'{payment_amount}'
		);

		$values = array(
			isset($lead['transaction_id']) ? $lead['transaction_id'] : '',
			isset($lead['payment_amount']) ? $lead['payment_amount'] : ''
		);

		return str_replace($tags, $values, $text);
	}

	// ====================================================================================================================================
	/**
	 * Check whether this form entry's unique ID has already been used; if so, we've already done a payment attempt.
	 * @param array $form
	 * @return boolean
	 */
	// ====================================================================================================================================
	protected function hasFormBeenProcessed($form) {
		$unique_id = RGFormsModel::get_form_unique_id($form['id']);
		$search = array(
			'field_filters' => array(
				array(
					'key' => 'gfpaystation_unique_id',
					'value' => $unique_id,
				),
			),
		);
		$entries = GFAPI::get_entries($form['id'], $search);
		return !empty($entries);
	}

	// ====================================================================================================================================
	/**
	 * Get feed for form using the paystationFeed object.
	 * @param int $form_id the submitted form's ID
	 * @return GFPaystationFeed
	 */
	// ====================================================================================================================================
	protected function getFeed($form_id) {

		if ($this->feed !== false && (empty($this->feed) || $this->feed->FormID != $form_id)) {
			$this->feed = GFPaystationFeed::getFormFeed($form_id);
		}

		return $this->feed;
	}

	// ====================================================================================================================================
	/**
	 * Get form data for form
	 * @param array $form the form submission data
	 * @return GFPaystationFormData
	 */
	// ====================================================================================================================================
	protected function getFormData($form) {

		if (empty($this->formData) || $this->formData->formID != $form['id']) {
			$feed = $this->getFeed($form['id']);
			$this->formData = new GFPaystationFormData($form, $feed);
		}

		return $this->formData;
	}

	// ====================================================================================================================================
	/**
	 * Display a message (already HTML-conformant)
	 * @param string $msg HTML-encoded message to display inside a paragraph
	 */
	// ====================================================================================================================================
	public static function showMessage($msg) {
		echo "<div class='updated fade'><p><strong>$msg</strong></p></div>\n";
	}

	// ====================================================================================================================================
	/**
	 * Display an error message (already HTML-conformant)
	 * I beleive this only works for errors to be output in the admin part of the site.
	 * @param string $msg HTML-encoded message to display inside a paragraph
	 */
	// ====================================================================================================================================
	public static function showError($msg) {
		echo "<div class='error'><p><strong>$msg</strong></p></div>\n";
	}
}
