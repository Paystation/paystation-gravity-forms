<?php
/*
    This file contians classes for the plugin options in the admin part of wordpress.
*/


// ============================================================================================================================================================
/**
 * Class to display the options form.
 */
// ============================================================================================================================================================
class GFPaystationOptionsForm {

	public $paystationId;
	public $gatewayId;
	public $testMode;
	public $securityHash;

	// ====================================================================================================================================
	/**
	 * initialise from form post, if posted
	 */
	// ====================================================================================================================================
	public function __construct() {

		if (self::isFormPost()) {
			$this->paystationId = self::getPostValue('paystationId');
			$this->gatewayId = self::getPostValue('gatewayId');
			$this->testMode = self::getPostValue('testMode');
			$this->securityHash = self::getPostValue('securityHash');
		}
	}

	// ====================================================================================================================================
	/**
	 * Is this web request a form post?
	 * Checks to see whether the HTML input form was posted.
	 *
	 * @return boolean
	 */
	// ====================================================================================================================================
	public static function isFormPost() {

		return ($_SERVER['REQUEST_METHOD'] == 'POST');
	}

	// ====================================================================================================================================
	/**
	 * Read a field from form post input.
	 * Guaranteed to return a string, trimmed of leading and trailing spaces, sloshes stripped out.
	 *
	 * @return string
	 * @param string $fieldname name of the field in the form post
	 */
	// ====================================================================================================================================
	public static function getPostValue($fieldname) {

		return isset($_POST[$fieldname]) ? stripslashes(trim($_POST[$fieldname])) : '';
	}

	// ====================================================================================================================================
	/**
	 * Validate the form input, and return error messages.
	 *
	 * Return a string detailing error messages for validation errors discovered,
	 * or an empty string if no errors found.
	 * The string should be HTML-clean, ready for putting inside a paragraph tag.
	 *
	 * @return string
	 */
	// ====================================================================================================================================
	public function validate() {

		$errmsg = '';

		if (strlen($this->paystationId) === 0) {
			$errmsg .= "- Please enter your Paystation Id.<br />\n";
		}

		if (strlen($this->gatewayId) === 0) {
			$errmsg .= "- Please enter the Gateway Id.<br />\n";
		}

		if (strlen($this->securityHash) === 0) {
			$errmsg .= "- Please enter a mixture of letters and numbers for the Security Hash.<br />\n";
		}

		if (($this->testMode != 'Y') && ($this->testMode != 'N')) {
			$errmsg .= "- Please specify if to use Test Mode or not.<br />\n";
		}

		return $errmsg;
	}
}

// ============================================================================================================================================================
/**
 * Options admin class for admin options, creates the above class as a child object.
 */
// ============================================================================================================================================================
class GFPaystationOptionsAdmin {

	private $plugin;                            // handle to the plugin object
	private $menuPage;                            // slug for admin menu page
	private $scriptURL = '';
	private $frm;                                // handle for the form validator

	// ====================================================================================================================================
	/**
	 * The controller.
	 * @param GFDpsPaystationPlugin $plugin handle to the plugin object
	 * @param string $menuPage URL slug for this admin menu page
	 */
	// ====================================================================================================================================
	public function __construct($plugin, $menuPage) {

		$this->plugin = $plugin;
		$this->menuPage = $menuPage;
		$this->scriptURL = parse_url($_SERVER['SCRIPT_NAME'], PHP_URL_PATH) . "?page={$menuPage}";
	}

	// ====================================================================================================================================
	/**
	 * Process the admin request
	 */
	// ====================================================================================================================================
	public function process() {

		echo "<div class='wrap'>\n";
		screen_icon();
		echo "<h2>Gravity Forms Paystation (3 party hosted) Settings</h2>\n";

		$this->frm = new GFPaystationOptionsForm();

		if ($this->frm->isFormPost()) {

			if (!wp_verify_nonce($_POST[$this->menuPage . '_wpnonce'], 'save')) {
				die('Security exception');
			}

			$errmsg = $this->frm->validate();
			if (empty($errmsg)) {
				$this->plugin->options['paystationId'] = $this->frm->paystationId;
				$this->plugin->options['gatewayId'] = $this->frm->gatewayId;
				$this->plugin->options['securityHash'] = $this->frm->securityHash;
				$this->plugin->options['testMode'] = $this->frm->testMode;

				update_option(GFPAYSTATION_PLUGIN_OPTIONS, $this->plugin->options);
				$this->plugin->showMessage(__('Options saved.'));
			}
			else {
				$this->plugin->showError($errmsg);
			}
		}
		else {
			// Initialise form from stored options
			$this->frm->paystationId = $this->plugin->options['paystationId'];
			$this->frm->gatewayId = $this->plugin->options['gatewayId'];
			$this->frm->testMode = $this->plugin->options['testMode'] == 'Y' ? 'Y' : 'N';
			$this->frm->securityHash = $this->plugin->options['securityHash'];
		}

		$feedsURL = 'edit.php?post_type=' . GFPAYSTATION_TYPE_FEED;

		?>
		<form action="<?php echo $this->scriptURL; ?>" method="post">
			<table class="form-table">
				<tr>
					<th>Paystation Id:</th>
					<td valign='top'>
						<input type='text' class="regular-text" name='paystationId' value="<?php echo htmlspecialchars($this->frm->paystationId); ?>"/>
					</td>
					<td>
						Enter you Paystation account id here. If you don't have an account yet, please visit the <a href='https://www2.paystation.co.nz/' target='_blank'>Paystation</a> website.
					</td>
				</tr>
				<tr>
					<th>Gateway Id:</th>
					<td valign='top'>
						<input type='text' class="regular-text" name='gatewayId' value="<?php echo htmlspecialchars($this->frm->gatewayId); ?>"/>
					</td>
					<td>
						Here you need to enter the name of your payment gateway as detailed in the email we will have sent you after your Paystation account was created.
					</td>
				</tr>
				<tr>
					<th>Security Hash:</th>
					<td valign='top'>
						<input type='text' class="regular-text" name='securityHash' value="<?php echo htmlspecialchars($this->frm->securityHash); ?>"/>
					</td>
					<td>
						Please enter a collection of letters and numbers here to use as a hash when dealing with responses from the Paystation system.
						Suggested length is 8 to 20 characters. If you ever change this you will need to let us know otherwise this plugin will ignore responses from Paystation.
					</td>
				</tr>
				<tr>
					<th>Test Mode:</th>
					<td valign='top'>
						<input type="radio" name="testMode" value="Y" <?php checked($this->frm->testMode, 'Y'); ?> />&nbsp;Yes&nbsp;&nbsp;&nbsp;
						<input type="radio" name="testMode" value="N" <?php checked($this->frm->testMode, 'N'); ?> />&nbsp;No
					</td>
					<td>
						For information about how to use your account in test mode, including some test credit card numbers, please refer to the email you received with your
						Paystation account information, or visit the <a href='https://www2.paystation.co.nz/developers/test-cards/' target='_blank'>Test Card Numbers</a> page.
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="submit" name="Submit" class="button-primary" value="Save Changes"/>
				<input type="hidden" name="action" value="save"/>
				<?php wp_nonce_field('save', $this->menuPage . '_wpnonce'); ?>
			</p>
		</form>
		<p><a class="button" href="<?php echo $feedsURL; ?>">Setup Paystation Feed</a></p>
		</div>
		<?php
	}
}
